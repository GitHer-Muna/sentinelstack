FROM php:8.5-apache

# Dedicated non-root user. UID is arbitrary here — it just needs to not be 0.
# The actual enforcement happens in the entrypoint check below.
RUN useradd -u 20500 -m -s /usr/sbin/nologin appuser \
    && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-enabled/000-default.conf \
    && chown -R appuser:appuser /var/www/html /var/log/apache2 /var/run/apache2 /var/lock/apache2

COPY --chown=appuser:appuser . /var/www/html

# Checks its own UID at container start and refuses to run if it's 0 (root).
# USER appuser below stops root by default, but someone could still override
# it with `docker run --user 0` — this is what makes that fail outright.
RUN printf '#!/bin/sh\nif [ "$(id -u)" = "0" ]; then\n  echo "FATAL: refusing to run as root (UID 0)"\n  exit 1\nfi\nexec apache2-foreground\n' > /entrypoint.sh \
    && chmod +x /entrypoint.sh

USER appuser
EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
