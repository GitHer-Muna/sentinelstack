FROM php:8.5-apache

# --- Extensions the app actually requires (composer.json) + Composer itself ---
RUN apt-get update && apt-get install -y --no-install-recommends \
      unzip git libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite mbstring \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# --- App expects DocumentRoot = public/ (see public/index.php + public/.htaccess) ---
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# --- Apache on an unprivileged port; container never runs as root ---
RUN sed -ri -e 's!Listen 80!Listen 8080!' /etc/apache2/ports.conf \
    && sed -ri -e 's!:80>!:8080>!' /etc/apache2/sites-available/000-default.conf

# --- Baked-in non-root user, UID in the 10000-12000 range required by the project ---
RUN groupadd -g 10501 appuser && useradd -u 10501 -g appuser -m -s /usr/sbin/nologin appuser

WORKDIR /var/www/html

# Install deps first (better layer caching), no dev deps, matches composer.json (no require-dev)
COPY --chown=appuser:appuser composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

COPY --chown=appuser:appuser . .

# Move Apache's runtime dirs to locations appuser can write (no /var/run, /var/log access)
RUN mkdir -p /tmp/apache-run /tmp/apache-lock /tmp/apache-log \
    && sed -ri \
       -e 's!ErrorLog .*!ErrorLog /tmp/apache-log/error.log!' \
       -e 's!CustomLog .*!CustomLog /tmp/apache-log/access.log combined!' \
       /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/run/apache2!/tmp/apache-run!' -e 's!/var/lock/apache2!/tmp/apache-lock!' /etc/apache2/envvars \
    && chown -R appuser:appuser /var/www/html /etc/apache2 /tmp/apache-run /tmp/apache-lock /tmp/apache-log

COPY --chown=root:root entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 755 /usr/local/bin/entrypoint.sh

EXPOSE 8080
USER appuser
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
