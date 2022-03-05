#============================================
# BUILD
#============================================
FROM php:8.1.3-cli-alpine3.15 AS builder

# https://blog.packagecloud.io/eng/2017/02/21/set-environment-variable-save-thousands-of-system-calls/
ENV TZ=:/etc/localtime

WORKDIR /usr/src

#============================================
# Dist dependencies
#============================================
RUN apk add --no-cache libstdc++ && \
    apk add --no-cache $PHPIZE_DEPS curl-dev freetype-dev libjpeg-turbo-dev libpng-dev libpq-dev libwebp-dev libxml2-dev libxpm-dev libzip-dev openssl-dev pcre-dev pcre2-dev postgresql-dev zlib-dev && \
    apk add --no-cache wget ca-certificates git unzip

#============================================
# Extensions
#============================================
RUN docker-php-ext-install -j$(nproc) zip && \
    docker-php-ext-enable zip && \
    docker-php-ext-install -j$(nproc) pcntl && \
    docker-php-ext-enable pcntl && \
    docker-php-ext-install -j$(nproc) sockets && \
    docker-php-ext-enable sockets && \
    docker-php-ext-install -j$(nproc) pdo_pgsql && \
    docker-php-ext-enable pdo_pgsql && \
    docker-php-ext-configure gd \
      --enable-gd \
      --with-freetype \
      --with-jpeg \
      --with-webp \
      --with-xpm && \
    docker-php-ext-install -j$(nproc) gd && \
    docker-php-ext-enable gd && \
    docker-php-ext-install -j$(nproc) opcache && \
    docker-php-ext-enable opcache && \
    docker-php-ext-install -j$(nproc) simplexml && \
    docker-php-ext-enable simplexml && \
    docker-php-ext-install -j$(nproc) dom && \
    docker-php-ext-enable dom

#============================================
# Opcache
#============================================
RUN docker-php-ext-enable opcache && \
    echo "opcache.enabled=1" > /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=192" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.fast_shutdown=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.use_cwd=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.save_comments=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo 'opcache.jit_buffer_size=100M' >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo 'opcache.jit=1255' >> /usr/local/etc/php/conf.d/opcache.ini

#============================================
# Dependencies
#============================================
COPY composer.json composer.json
COPY composer.lock composer.lock

ARG COMPOSER_AUTH
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-progress --ignore-platform-reqs --no-dev --prefer-dist --optimize-autoloader --no-interaction

#============================================
# COMMAND LINE INTERFACE
#============================================
FROM php:8.1.3-cli-alpine3.15 as cli

# https://blog.packagecloud.io/eng/2017/02/21/set-environment-variable-save-thousands-of-system-calls/
ENV TZ=:/etc/localtime
ENV PHP_ENV=:dev

#============================================
# Settings
#============================================
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "memory_limit = -1" > /usr/local/etc/php/conf.d/memory.ini && \
    echo "variables_order = EGPCS" > /usr/local/etc/php/conf.d/variables_order.ini && \
    echo "zend.assertions = -1" > /usr/local/etc/php/conf.d/zend.ini

#============================================
# Application
#============================================
COPY --chown=www-data:www-data ./app/ /var/www/html/app/
COPY --chown=www-data:www-data ./bin/ /var/www/html/bin/
COPY --chown=www-data:www-data ./db/ /var/www/html/db/
COPY --chown=www-data:www-data ./src/ /var/www/html/src/
COPY --chown=www-data:www-data ./phinx.php /var/www/html/phinx.php
COPY --chown=www-data:www-data --from=builder /usr/src/vendor/ /var/www/html/vendor/
COPY --from=builder /usr/local/lib/php/extensions/no-debug-non-zts-20210902 /usr/local/lib/php/extensions/no-debug-non-zts-20210902
COPY --from=builder /usr/local/etc/php/conf.d/*.ini /usr/local/etc/php/conf.d/

#============================================
# Library dependencies
#============================================
RUN apk add --no-cache libstdc++ && \
    apk add --no-cache libpq --repository=https://dl-cdn.alpinelinux.org/alpine/edge/main/ && \
    apk add --no-cache freetype libjpeg-turbo libpng libwebp libxml2 libxpm libzip

#============================================
# Other dependencies
#============================================
RUN apk add --no-cache dumb-init

#============================================
# User
#============================================
USER www-data
WORKDIR /var/www/html/bin

#============================================
# Metadata
#============================================
LABEL org.opencontainers.image.authors="flaviohbatista@gmail.com" \
      org.opencontainers.image.title="PHP-Package-Health: PHP-CLI" \
      org.opencontainers.image.url="https://github.com/package-health/php" \
      org.opencontainers.image.vendor="Package Health"

ENTRYPOINT ["/usr/bin/dumb-init", "--"]
CMD ["php"]

#============================================
# FPM SAPI
#============================================
FROM php:8.1.3-fpm-alpine3.15 as fpm

# https://blog.packagecloud.io/eng/2017/02/21/set-environment-variable-save-thousands-of-system-calls/
ENV TZ=:/etc/localtime
ENV PHP_ENV=:dev

#============================================
# Settings
#============================================
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/memory.ini && \
    echo "variables_order = EGPCS" > /usr/local/etc/php/conf.d/variables_order.ini && \
    echo "expose_php = 0" > /usr/local/etc/php/conf.d/expose_php.ini
RUN echo "pm.status_path = /status" >> /usr/local/etc/php-fpm.d/zz-docker.conf

#============================================
# Application
#============================================
COPY --chown=www-data:www-data ./app/ /var/www/html/app/
COPY --chown=www-data:www-data ./public/index.php /var/www/html/public/index.php
COPY --chown=www-data:www-data ./resources/ /var/www/html/resources/
COPY --chown=www-data:www-data ./src/ /var/www/html/src/
COPY --chown=www-data:www-data ./var/ /var/www/html/var/
COPY --chown=www-data:www-data --from=builder /usr/src/vendor/ /var/www/html/vendor/
COPY --from=builder /usr/local/lib/php/extensions/no-debug-non-zts-20210902 /usr/local/lib/php/extensions/no-debug-non-zts-20210902
COPY --from=builder /usr/local/etc/php/conf.d/*.ini /usr/local/etc/php/conf.d/

#============================================
# Library dependencies
#============================================
RUN apk add --no-cache libstdc++ && \
    apk add --no-cache libpq --repository=https://dl-cdn.alpinelinux.org/alpine/edge/main/ && \
    apk add --no-cache freetype libjpeg-turbo libpng libwebp libxml2 libxpm libzip

#============================================
# Other dependencies
#============================================
RUN apk add --no-cache dumb-init fcgi
RUN wget -O /usr/local/bin/php-fpm-healthcheck https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck && \
    chmod +x /usr/local/bin/php-fpm-healthcheck

#============================================
# User
#============================================
USER www-data
WORKDIR /var/www/html/public
EXPOSE 9000

#============================================
# Healthcheck
#============================================
HEALTHCHECK --interval=1m30s --timeout=10s --retries=3 --start-period=40s CMD php-fpm-healthcheck || exit 1

#============================================
# Metadata
#============================================
LABEL org.opencontainers.image.authors="flaviohbatista@gmail.com" \
      org.opencontainers.image.title="PHP-Package-Health: PHP-FPM" \
      org.opencontainers.image.url="https://github.com/package-health/php" \
      org.opencontainers.image.vendor="Package Health"

ENTRYPOINT ["/usr/bin/dumb-init", "--"]
CMD ["php-fpm"]
