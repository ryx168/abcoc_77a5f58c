#!/bin/bash
# One dump-and-upload cycle: persist the DB + app tree to R2. Called both
# PERIODICALLY during an active session (so a crash never loses more than a few
# minutes of orders) and once more at session end. Never touches the export/
# static site - that is a separate, independently-maintained catalog.
set -uo pipefail
cd "${GITHUB_WORKSPACE:-$PWD}/webroot"
CF="https://api.cloudflare.com/client/v4/accounts/${CF_ACCOUNT_ID}/r2/buckets/${STATE_BUCKET}/objects"
put() { curl -sSf -m 300 -X PUT -H "Authorization: Bearer ${CF_API_TOKEN}" -H "Content-Type: $2" --data-binary @"$1" "$3" -o /dev/null; }

mysqldump -h127.0.0.1 -uroot -proot "${DB_DATABASE}" 2>/dev/null | gzip > /tmp/db.sql.gz
put /tmp/db.sql.gz application/gzip "$CF/db-latest.sql.gz" && echo "  [persist $(date -u +%H:%M:%S)] saved db-latest.sql.gz ($(du -h /tmp/db.sql.gz|cut -f1))"
put /tmp/db.sql.gz application/gzip "$CF/history/db-$(date +%Y%m%d-%H%M%S).sql.gz" || true

tar czf /tmp/app.tar.gz --warning=no-file-changed --ignore-failed-read \
    --exclude='./mainfile.php' --exclude='./templates_c/*' \
    --exclude='./xoops_data/caches/*' --exclude='./class/cache/*' \
    . 2>/dev/null || true
put /tmp/app.tar.gz application/gzip "$CF/app.tar.gz" && echo "  [persist $(date -u +%H:%M:%S)] saved app.tar.gz ($(du -h /tmp/app.tar.gz|cut -f1))"
