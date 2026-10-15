#!/bin/sh
# نسخ احتياطي يومي لقاعدة omanilab (pg_dump بصيغة مخصصة، تُستعاد بـ pg_restore) مع احتفاظ بآخر $KEEP نسخة.
# يسجّل آخر نجاح في /backups/LAST_OK ويحدّد حالة الصحة (healthcheck) بعمر آخر نسخة.
set -eu

KEEP="${BACKUP_KEEP:-14}"
INTERVAL="${BACKUP_INTERVAL_SECONDS:-86400}"
DIR=/backups

while true; do
  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  file="$DIR/omanilab-$stamp.dump"
  if PGPASSWORD="$PGPASSWORD" pg_dump -h "$PGHOST" -U "$PGUSER" -d "$PGDATABASE" -Fc -Z 6 -f "$file.tmp"; then
    # لا نعدّ النسخة سليمة قبل أن يقرأها pg_restore
    if pg_restore --list "$file.tmp" > /dev/null; then
      mv "$file.tmp" "$file"
      date -u +%s > "$DIR/LAST_OK"
      echo "[backup] ok $file ($(du -h "$file" | cut -f1))"
      ls -1t "$DIR"/omanilab-*.dump 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm -f
    else
      echo "[backup] FAILED: النسخة غير قابلة للقراءة" >&2
      rm -f "$file.tmp"
    fi
  else
    echo "[backup] FAILED: pg_dump" >&2
    rm -f "$file.tmp"
  fi
  sleep "$INTERVAL"
done
