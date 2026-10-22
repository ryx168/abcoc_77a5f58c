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

echo "::group::Write mainfile.php & mysql-shim"
EH="${EDIT_HOST}"
python3 - "$ROOT" "$EH" "$DB_DATABASE" <<'PY'
import io, os, re, sys
root, eh, dbname = sys.argv[1], sys.argv[2], sys.argv[3]

# 1. Write mysql-shim.php (mysqli-backed compatibility layer for PHP 5.6/7+)
shim_code = '''<?php
if (!function_exists('mysql_connect')) {
    if (!defined('MYSQL_ASSOC')) define('MYSQL_ASSOC', MYSQLI_ASSOC);
    if (!defined('MYSQL_NUM')) define('MYSQL_NUM', MYSQLI_NUM);
    if (!defined('MYSQL_BOTH')) define('MYSQL_BOTH', MYSQLI_BOTH);

    $GLOBALS['__mysql_last_conn'] = null;

    function mysql_connect($server = null, $user = null, $password = null, $new_link = false, $client_flags = 0) {
        if ($server === null) $server = '127.0.0.1';
        $parts = explode(':', $server);
        $host = $parts[0];
        $port = isset($parts[1]) ? (int)$parts[1] : 3306;
        $conn = mysqli_init();
        if (!@mysqli_real_connect($conn, $host, $user, $password, null, $port)) {
            return false;
        }
        mysqli_set_charset($conn, 'utf8');
        $GLOBALS['__mysql_last_conn'] = $conn;
        return $conn;
    }

    function mysql_pconnect($server = null, $user = null, $password = null, $client_flags = 0) {
        return mysql_connect($server, $user, $password, false, $client_flags);
    }

    function mysql_select_db($database_name, $link_identifier = null) {
        $conn = $link_identifier ?: $GLOBALS['__mysql_last_conn'];
        return mysqli_select_db($conn, $database_name);
    }

    function mysql_query($query, $link_identifier = null) {
        $conn = $link_identifier ?: $GLOBALS['__mysql_last_conn'];
        return mysqli_query($conn, $query);
    }

    function mysql_fetch_array($result, $result_type = MYSQL_BOTH) {
        if (!$result) return false;
        return mysqli_fetch_array($result, $result_type);
    }

    function mysql_fetch_assoc($result) {
        if (!$result) return false;
        return mysqli_fetch_assoc($result);
    }

    function mysql_fetch_row($result) {
        if (!$result) return false;
        return mysqli_fetch_row($result);
    }

    function mysql_num_rows($result) {
        if (!$result) return false;
        return mysqli_num_rows($result);
    }

    function mysql_affected_rows($link_identifier = null) {
        $conn = $link_identifier ?: $GLOBALS['__mysql_last_conn'];
        return mysqli_affected_rows($conn);
    }

    function mysql_insert_id($link_identifier = null) {
        $conn = $link_identifier ?: $GLOBALS['__mysql_last_conn'];
        return mysqli_insert_id($conn);
    }

    function mysql_error($link_identifier = null) {
        $conn = $link_identifier ?: $GLOBALS['__mysql_last_conn'];
        return mysqli_error($conn);
    }

    function mysql_errno($link_identifier = null) {
        $conn = $link_identifier ?: $GLOBALS['__mysql_last_conn'];
        return mysqli_errno($conn);
    }

    function mysql_close($link_identifier = null) {
        $conn = $link_identifier ?: $GLOBALS['__mysql_last_conn'];
        return mysqli_close($conn);
    }

    function mysql_real_escape_string($unescaped_string, $link_identifier = null) {
        $conn = $link_identifier ?: $GLOBALS['__mysql_last_conn'];
        if ($conn) return mysqli_real_escape_string($conn, $unescaped_string);
        return addslashes($unescaped_string);
    }

    function mysql_num_fields($result) {
        if (!$result) return false;
        return mysqli_num_fields($result);
    }

    function mysql_free_result($result) {
        if (!$result) return false;
        return mysqli_free_result($result);
    }

    function mysql_field_name($result, $field_offset) {
        $finfo = mysqli_fetch_field_direct($result, $field_offset);
        return $finfo ? $finfo->name : false;
    }

    function mysql_field_type($result, $field_offset) {
        $finfo = mysqli_fetch_field_direct($result, $field_offset);
        return $finfo ? $finfo->type : false;
    }
}
'''
with io.open(root + "/mysql-shim.php", "w", encoding="utf-8", newline="\n") as sf:
    sf.write(shim_code)
print("  mysql-shim.php created")

# 2. Patch mysqldatabase.php
db_php = root + "/class/database/mysqldatabase.php"
if os.path.exists(db_php):
    db_content = io.open(db_php, encoding="utf-8", errors="replace").read()
    if "!extension_loaded( 'mysql' )" in db_content:
        db_content = db_content.replace(
            "if ( !extension_loaded( 'mysql' ) ) {",
            "require_once dirname(dirname(dirname(__FILE__))) . '/mysql-shim.php'; if ( false ) {"
        )
        io.open(db_php, "w", encoding="utf-8", newline="\n").write(db_content)
        print("  mysqldatabase.php patched with mysql-shim")

# 3. Rewrite mainfile.php
p = root + "/mainfile.php"
h = io.open(p, encoding="utf-8", errors="replace").read()
h = h.replace("/home/abc/public_html", root)
h = re.sub(r"define\(\s*'XOOPS_URL',\s*'[^']*'\s*\);", "define( 'XOOPS_URL', 'https://%s' );" % eh, h)
h = re.sub(r"define\(\s*'XOOPS_DB_HOST',\s*'[^']*'\s*\);", "define( 'XOOPS_DB_HOST', '127.0.0.1' );", h)
h = re.sub(r"define\(\s*'XOOPS_DB_USER',\s*'[^']*'\s*\);", "define( 'XOOPS_DB_USER', 'root' );", h)
h = re.sub(r"define\(\s*'XOOPS_DB_PASS',\s*'[^']*'\s*\);", "define( 'XOOPS_DB_PASS', 'root' );", h)
h = re.sub(r"define\(\s*'XOOPS_DB_NAME',\s*'[^']*'\s*\);", "define( 'XOOPS_DB_NAME', '%s' );" % dbname, h)

# Prepend require mysql-shim to mainfile.php
if "mysql-shim.php" not in h:
    h = "<?php\nrequire_once dirname(__FILE__) . '/mysql-shim.php';\n" + h.lstrip("<?php").lstrip()

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
echo "=== PHP DIAG ===\n";
echo "PHP_VERSION: " . PHP_VERSION . "\n";
echo "Loaded ini: " . php_ini_loaded_file() . "\n";
echo "Scanned ini: " . php_ini_scanned_files() . "\n";
echo "EXT_DIR: " . ini_get('extension_dir') . "\n";
echo "MYSQL_LOADED: " . (extension_loaded('mysql') ? 'YES' : 'NO') . "\n";
echo "MYSQLI_LOADED: " . (extension_loaded('mysqli') ? 'YES' : 'NO') . "\n";
echo "EXTENSIONS: " . implode(', ', get_loaded_extensions()) . "\n";
echo "=== EXTENSION SO FILES ===\n";
foreach (glob(ini_get('extension_dir') . '/*') as $f) {
    if (strpos($f, 'mysql') !== false) echo "  $f\n";
}
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
