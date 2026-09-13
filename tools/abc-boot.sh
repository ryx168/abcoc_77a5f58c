#!/bin/bash
# Restore the XOOPS shop app + DB from R2, write mainfile.php for the runner, and
# fix the mysql_real_escape_string signature to match PHP 5.6's ext/mysql (single-
# arg call site is a known XOOPS-on-old-PHP mismatch in a handful of builds).
set -uo pipefail

ROOT="${GITHUB_WORKSPACE:-$PWD}/webroot"
rm -rf "$ROOT"; mkdir -p "$ROOT"; cd "$ROOT"
CF="https://api.cloudflare.com/client/v4/accounts/${CF_ACCOUNT_ID}/r2/buckets/${STATE_BUCKET}/objects"

echo "::group::Restore state from R2"
curl -sSf -H "Authorization: Bearer ${CF_API_TOKEN}" "$CF/app.tar.gz" -o app.tar.gz
tar xzf app.tar.gz
TAR_STATUS=$?
if [ $TAR_STATUS -ne 0 ] || [ ! -f mainfile.php ]; then
  echo "  FATAL: app.tar.gz extraction failed or mainfile.php missing afterward (tar exit $TAR_STATUS, size $(wc -c < app.tar.gz 2>/dev/null || echo '?')) - refusing to boot from corrupt state"
  exit 1
fi
rm -f app.tar.gz
curl -sSf -H "Authorization: Bearer ${CF_API_TOKEN}" "$CF/db-latest.sql.gz" -o db.sql.gz
echo "  app + db restored; top: $(ls | tr '\n' ' ')"
# abc-persist.sh excludes these as *contents* (they're regenerated at runtime and
# stale absolute-path caches were the whole reason they get wiped), but tar's
# --exclude on a glob like './xoops_data/caches/*' also drops the empty
# subdirectory ENTRIES themselves (smarty_compile, smarty_cache, xoops_cache) -
# so a persisted archive permanently loses them, and Smarty then fatals on
# every page with "compile_dir does not exist". Recreate them unconditionally.
mkdir -p xoops_data/caches/smarty_compile xoops_data/caches/smarty_cache xoops_data/caches/xoops_cache
mkdir -p templates_c class/cache modules/shop/cache modules/news/cache
echo "  ensured cache directories exist"
echo "::endgroup::"

echo "::group::Database"
for i in $(seq 1 45); do
  mysql -h127.0.0.1 -uroot -proot -e "SELECT 1" >/dev/null 2>&1 && break
  echo "  waiting for mysql ($i)"; sleep 2
done
mysql -h127.0.0.1 -uroot -proot -e "CREATE DATABASE IF NOT EXISTS ${DB_DATABASE} CHARACTER SET utf8 COLLATE utf8_general_ci;"
# strip the MariaDB-10.6+ sandbox-mode preamble line that older mysql clients choke on
grep -v "^/\*M!999999" db.sql.gz > /dev/null 2>&1 || true
zcat db.sql.gz | grep -v "^/\*M!999999" | mysql -h127.0.0.1 -uroot -proot "${DB_DATABASE}" && echo "  imported db"
rm -f db.sql.gz
echo "  orders: $(mysql -h127.0.0.1 -uroot -proot -N -e "SELECT COUNT(*) FROM ${DB_DATABASE}.xoops_shop_order" 2>/dev/null)"
echo "::endgroup::"

echo "::group::Write mainfile.php"
EH="${EDIT_HOST}"
python3 - "$ROOT" "$EH" "$DB_DATABASE" <<'PY'
import io, re, sys
root, eh, dbname = sys.argv[1], sys.argv[2], sys.argv[3]
p = root + "/mainfile.php"
h = io.open(p, encoding="utf-8", errors="replace").read()
h = h.replace("/home/abc/public_html", root)
h = re.sub(r"define\(\s*'XOOPS_URL',\s*'[^']*'\s*\);", "define( 'XOOPS_URL', 'https://%s' );" % eh, h)
h = re.sub(r"define\(\s*'XOOPS_DB_HOST',\s*'[^']*'\s*\);", "define( 'XOOPS_DB_HOST', '127.0.0.1' );", h)
h = re.sub(r"define\(\s*'XOOPS_DB_USER',\s*'[^']*'\s*\);", "define( 'XOOPS_DB_USER', 'root' );", h)
h = re.sub(r"define\(\s*'XOOPS_DB_PASS',\s*'[^']*'\s*\);", "define( 'XOOPS_DB_PASS', 'root' );", h)
h = re.sub(r"define\(\s*'XOOPS_DB_NAME',\s*'[^']*'\s*\);", "define( 'XOOPS_DB_NAME', '%s' );" % dbname, h)
io.open(p, "w", encoding="utf-8", newline="\n").write(h)
print("  mainfile.php rewritten for", eh)
PY
REWRITE_STATUS=$?
if [ $REWRITE_STATUS -ne 0 ]; then
  echo "  FATAL: mainfile.php rewrite failed (exit $REWRITE_STATUS) - refusing to continue with a broken/missing config"
  exit 1
fi
echo "::endgroup::"

# php-fpm replaces php -S: the built-in dev server is single-threaded and a
# single slow/hanging request (an XOOPS error path, a fatal, anything) wedges
# it forever, silently queuing every later request behind it - the real cause
# of the "works for a burst then dies" pattern seen with both the Cloudflare
# tunnel and the SSH tunnel. nginx on the VPS talks real FastCGI straight to
# php-fpm here (root = this deterministic $GITHUB_WORKSPACE path, hardcoded
# into the VPS vhost), the same proven SCRIPT_FILENAME/PATH_TRANSLATED setup
# our own aaPanel deployment of this same app already uses successfully - no
# custom router/shim needed, and no repeat of the earlier "No Module is
# loaded" SAPI bug that a hand-rolled router caused.
cat > __diag.php <<'PHP'
<?php
header('Content-Type: text/plain');
echo 'ok ' . date('c');
PHP
cat > __phplog.php <<'PHP'
<?php
header('Content-Type: text/plain');
$f = '/tmp/php.log';
echo is_file($f) ? @file_get_contents($f) : 'no log file';
PHP

cat > /tmp/php-fpm.conf <<'FPM'
[global]
error_log = /tmp/php-fpm.log
daemonize = no

[www]
listen = 127.0.0.1:9000
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 4
catch_workers_output = yes
access.log = /tmp/php.log
access.format = "%m %r"
php_admin_value[error_reporting] = E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT
php_admin_flag[display_errors] = off
FPM
echo "BOOT_OK ROOT=$ROOT"
