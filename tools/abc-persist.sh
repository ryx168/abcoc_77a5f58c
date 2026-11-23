#!/bin/bash
# One dump-and-upload cycle: persist the DB + app tree to R2. Called both
# PERIODICALLY during an active session (so a crash never loses more than a few
# minutes of orders) and once more at session end. Never touches the export/
# static site - that is a separate, independently-maintained catalog.
set -uo pipefail
cd "${GITHUB_WORKSPACE:-$PWD}/webroot" \
  || { echo "  [persist] FATAL: webroot is missing - the restore never happened; NOT saving"; exit 1; }
CF="https://api.cloudflare.com/client/v4/accounts/${CF_ACCOUNT_ID}/r2/buckets/${STATE_BUCKET}/objects"
put() { curl -sSf -m 300 -X PUT -H "Authorization: Bearer ${CF_API_TOKEN}" -H "Content-Type: $2" --data-binary @"$1" "$3" -o /dev/null; }

# Dump to a FILE and check it before anything is uploaded. The old form
# (`mysqldump 2>/dev/null | gzip`) hid every error and, with pipefail but no -e,
# passed on a tiny valid .gz even when MySQL was dead or the database empty,
# which the next line uploaded over db-latest.sql.gz. This database holds the
# live orders, so refuse unless it is demonstrably a whole dump: mysqldump
# succeeded, it has >= 30 tables (the real one has 76), and it has the
# orders table. A refusal leaves the last-known-good R2 copy untouched.
if ! mysqldump -h127.0.0.1 -uroot -proot "${DB_DATABASE}" > /tmp/db.sql 2> /tmp/dump.err; then
  echo "  [persist] FATAL: mysqldump failed - NOT saving over db-latest.sql.gz: $(head -c 300 /tmp/dump.err)"
  exit 1
fi
n_tables=$(grep -c '^CREATE TABLE ' /tmp/db.sql || true)
if [ "${n_tables:-0}" -lt 30 ] || ! grep -q '^CREATE TABLE `xoops_shop_order`' /tmp/db.sql; then
  echo "  [persist] REFUSING to save: the dump has ${n_tables:-0} tables or no orders table ($(wc -c < /tmp/db.sql) bytes) - leaving the last-known-good R2 copy"
  exit 1
fi
gzip -c /tmp/db.sql > /tmp/db.sql.gz
put /tmp/db.sql.gz application/gzip "$CF/db-latest.sql.gz" && echo "  [persist $(date -u +%H:%M:%S)] saved db-latest.sql.gz ($(du -h /tmp/db.sql.gz|cut -f1))"
put /tmp/db.sql.gz application/gzip "$CF/history/db-$(date +%Y%m%d-%H%M%S).sql.gz" || true

# mainfile.php IS included - abc-boot.sh rewrites every environment-specific
# constant (URL/DB host/user/pass) on every boot regardless of what's already
# in the file, so persisting it is harmless. Excluding it here previously (with
# set -uo pipefail, not -e) let a failed/missing-file rewrite pass silently,
# permanently corrupting the saved state - a real incident, see memory.
tar czf /tmp/app.tar.gz --warning=no-file-changed --ignore-failed-read \
    --exclude='./templates_c/*' \
    --exclude='./xoops_data/caches/*' --exclude='./class/cache/*' \
    . 2>/dev/null || true
# Guard against ever uploading a broken/truncated tarball over the last-known-good
# R2 copy - this exact failure mode (tar producing a near-empty file, silently
# accepted because this script itself has no -e) corrupted app.tar.gz once already.
# (Read the listing into a variable rather than piping to grep -q directly - with
# set -o pipefail, grep -q's early exit on the first match SIGPIPEs tar, which
# then reports nonzero and fails the check even for a perfectly good archive.)
TAR_LISTING="$(tar tzf /tmp/app.tar.gz 2>/dev/null)" || TAR_LISTING=""
if [ -n "$TAR_LISTING" ] && grep -qx './mainfile.php' <<<"$TAR_LISTING"; then
  put /tmp/app.tar.gz application/gzip "$CF/app.tar.gz" && echo "  [persist $(date -u +%H:%M:%S)] saved app.tar.gz ($(du -h /tmp/app.tar.gz|cut -f1))"
else
  echo "  [persist $(date -u +%H:%M:%S)] REFUSING to upload app.tar.gz - failed integrity check (size $(wc -c < /tmp/app.tar.gz 2>/dev/null || echo '?')), leaving last-known-good R2 copy untouched"
fi
