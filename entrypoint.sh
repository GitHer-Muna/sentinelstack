#!/bin/sh
set -e

# Hard guard: refuses to run even if someone overrides --user root at `docker run` time.
if [ "$(id -u)" = "0" ]; then
  echo "FATAL: this container refuses to start as root (uid 0). Use appuser." >&2
  exit 1
fi

# database/seed.php is idempotent (checks row counts / CREATE TABLE IF NOT EXISTS),
# safe to run on every container start — this is what applies schema.sql to a
# fresh DB_PATH on first boot and no-ops afterwards.
php /var/www/html/database/seed.php

exec "$@"
