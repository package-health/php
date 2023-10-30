#============================================
# BUILD
#============================================
FROM php:8.2.12-cli-alpine3.18 AS builder

# https://blog.packagecloud.io/eng/2017/02/21/set-environment-variable-save-thousands-of-system-calls/
ENV TZ=:UTC

WORKDIR /usr/src

#============================================
# Force base image upgrade
#============================================
RUN apk add --no-cache --upgrade apk-tools && \
    apk upgrade --available

#============================================
# Dist dependencies
#============================================
RUN apk add --no-cache $PHPIZE_DEPS curl-dev freetype-dev libjpeg-turbo-dev libpng-dev libpq-dev libwebp-dev libxml2-dev libxpm-dev libzip-dev linux-headers openssl-dev pcre-dev pcre2-dev postgresql-dev rabbitmq-c-dev zlib-dev && \
    apk add --no-cache wget ca-certificates git unzip

#============================================
# Built-in Extensions
#============================================
RUN docker-php-ext-install -j$(nproc) dom && \
    docker-php-ext-configure gd \
      --enable-gd \
      --with-freetype \
      --with-jpeg \
      --with-webp \
      --with-xpm && \
    docker-php-ext-install -j$(nproc) gd && \
    docker-php-ext-install -j$(nproc) opcache && \
    docker-php-ext-install -j$(nproc) pcntl && \
    docker-php-ext-install -j$(nproc) pdo_pgsql && \
    docker-php-ext-install -j$(nproc) simplexml && \
    docker-php-ext-install -j$(nproc) sockets && \
    docker-php-ext-install -j$(nproc) zip

#============================================
# Third party Extensions
#============================================
RUN docker-php-source extract && \
    # amqp
    wget -O amqp.tar.gz https://github.com/php-amqp/php-amqp/archive/refs/tags/v1.11.0.tar.gz && \
    mkdir /usr/src/php/ext/amqp && \
    tar --extract --file amqp.tar.gz --directory /usr/src/php/ext/amqp --strip 1 && \
    docker-php-ext-install -j$(nproc) amqp && \
    # redis
    wget -O redis.tar.gz https://github.com/phpredis/phpredis/archive/refs/tags/5.3.7.tar.gz && \
    mkdir /usr/src/php/ext/redis && \
    tar --extract --file redis.tar.gz --directory /usr/src/php/ext/redis --strip 1 && \
    docker-php-ext-install -j$(nproc) redis && \
    # igbinary
    wget -O igbinary.tar.gz https://github.com/igbinary/igbinary/archive/refs/tags/3.2.14.tar.gz && \
    mkdir /usr/src/php/ext/igbinary && \
    tar --extract --file igbinary.tar.gz --directory /usr/src/php/ext/igbinary --strip 1 && \
    docker-php-ext-install -j$(nproc) igbinary && \
    docker-php-source delete

#============================================
# Opcache
#============================================
RUN docker-php-ext-enable opcache && \
    echo "opcache.enabled=1"                  > /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.enable_cli=1"               >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.memory_consumption=192"     >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.validate_timestamps=0"      >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.fast_shutdown=0"            >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.use_cwd=1"                  >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.save_comments=0"            >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.jit_buffer_size=100M"       >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.jit=1255"                   >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

#============================================
# Dependencies
#============================================
COPY composer.json composer.json
COPY composer.lock composer.lock

ARG COMPOSER_AUTH
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
RUN composer install --no-progress --ignore-platform-reqs --no-dev --prefer-dist --optimize-autoloader --no-interaction

#============================================
# COMMAND LINE INTERFACE
#============================================
FROM php:8.2.12-cli-alpine3.18 as cli

# https://blog.packagecloud.io/eng/2017/02/21/set-environment-variable-save-thousands-of-system-calls/
ENV TZ=:UTC
ENV PHP_ENV=dev

#============================================
# Force base image upgrade
#============================================
RUN apk add --no-cache --upgrade apk-tools && \
    apk upgrade --available

#============================================
# Settings
#============================================
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    rm "$PHP_INI_DIR/php.ini-development" && \
    echo "memory_limit = -1"       > /usr/local/etc/php/conf.d/memory.ini && \
    echo "variables_order = EGPCS" > /usr/local/etc/php/conf.d/variables_order.ini && \
    echo "zend.assertions = -1"    > /usr/local/etc/php/conf.d/zend.ini

#============================================
# Library dependencies
#============================================
RUN apk add --no-cache libpq --repository=https://dl-cdn.alpinelinux.org/alpine/edge/main/ && \
    apk add --no-cache freetype libjpeg-turbo libpng libwebp libxml2 libxpm libzip rabbitmq-c

#============================================
# Other dependencies
#============================================
RUN apk add --no-cache dumb-init

#============================================
# CLI Extensions
#============================================
COPY --from=builder /usr/local/lib/php/extensions/no-debug-non-zts-20220829 /usr/local/lib/php/extensions/no-debug-non-zts-20220829
COPY --from=builder /usr/local/etc/php/conf.d/*.ini /usr/local/etc/php/conf.d/
RUN docker-php-ext-enable amqp && \
    docker-php-ext-enable igbinary && \
    docker-php-ext-enable opcache && \
    docker-php-ext-enable pcntl && \
    docker-php-ext-enable pdo_pgsql && \
    docker-php-ext-enable redis && \
    docker-php-ext-enable sockets && \
    docker-php-ext-enable zip

#============================================
# Application
#============================================
COPY --chown=www-data:www-data ./app/         /var/www/html/app/
COPY --chown=www-data:www-data ./bin/         /var/www/html/bin/
COPY --chown=www-data:www-data ./db/          /var/www/html/db/
COPY --chown=www-data:www-data ./src/         /var/www/html/src/
COPY --chown=www-data:www-data ./phinx.php    /var/www/html/phinx.php
COPY --chown=www-data:www-data --from=builder /usr/src/vendor/        /var/www/html/vendor/

RUN mkdir /var/www/html/run && \
    chown -R www-data:www-data /var/www/html/run

#============================================
# User
#============================================
USER www-data
WORKDIR /var/www/html/bin

#============================================
# Metadata
#============================================
ARG VERSION=latest
ENV VERSION="${VERSION}"
LABEL maintainer="Flavio Heleno <flaviohbatista@gmail.com>" \
      org.opencontainers.image.authors="flaviohbatista@gmail.com" \
      org.opencontainers.image.base.name="ghcr.io/package-health/pph-php-cli:${VERSION}" \
      org.opencontainers.image.source="https://github.com/package-health/php" \
      org.opencontainers.image.title="PHP-Package-Health: PHP-CLI" \
      org.opencontainers.image.url="https://github.com/package-health/php" \
      org.opencontainers.image.vendor="Package Health" \
      org.opencontainers.image.version="${VERSION}"

VOLUME ["/var/www/html/run"]

ENTRYPOINT ["/usr/bin/dumb-init", "--"]
CMD ["php"]

#============================================
# FPM SAPI
#============================================
FROM php:8.2.12-fpm-alpine3.18 as fpm

# https://blog.packagecloud.io/eng/2017/02/21/set-environment-variable-save-thousands-of-system-calls/
ENV TZ=:UTC
ENV PHP_ENV=dev

#============================================
# Force base image upgrade
#============================================
RUN apk add --no-cache --upgrade apk-tools && \
    apk upgrade --available

#============================================
# Settings
#============================================
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    rm "$PHP_INI_DIR/php.ini-development" && \
    echo "memory_limit = 256M"          > /usr/local/etc/php/conf.d/memory.ini && \
    echo "variables_order = EGPCS"      > /usr/local/etc/php/conf.d/variables_order.ini && \
    echo "expose_php = Off"             > /usr/local/etc/php/conf.d/expose_php.ini && \
    echo "allow_url_fopen = Off"        > /usr/local/etc/php/conf.d/security.ini && \
    echo "allow_url_include = Off"      >> /usr/local/etc/php/conf.d/security.ini && \
    echo "cgi.fix_pathinfo = Off"       >> /usr/local/etc/php/conf.d/security.ini && \
    echo "cgi.force_redirect = On"      >> /usr/local/etc/php/conf.d/security.ini && \
    echo "file_uploads = Off"           >> /usr/local/etc/php/conf.d/security.ini && \
    echo "max_input_vars = 100"         >> /usr/local/etc/php/conf.d/security.ini && \
    echo "open_basedir = /var/www/html" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "post_max_size = 256K"         >> /usr/local/etc/php/conf.d/security.ini

# https://tideways.com/profiler/blog/an-introduction-to-php-fpm-tuning
RUN echo "pm.status_path = /status" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_children = 30"     >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.start_servers = 8"     >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.min_spare_servers = 4" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_spare_servers = 8" >> /usr/local/etc/php-fpm.d/zz-docker.conf

#============================================
# Library dependencies
#============================================
RUN apk add --no-cache libpq --repository=https://dl-cdn.alpinelinux.org/alpine/edge/main/ && \
    apk add --no-cache freetype libjpeg-turbo libpng libwebp libxml2 libxpm libzip rabbitmq-c

#============================================
# Other dependencies
#============================================
RUN apk add --no-cache dumb-init fcgi
RUN wget -O /usr/local/bin/php-fpm-healthcheck https://raw.githubusercontent.com/package-health/php-fpm-healthcheck/master/php-fpm-healthcheck && \
    chmod +x /usr/local/bin/php-fpm-healthcheck

#============================================
# FPM Extensions
#============================================
COPY --from=builder /usr/local/lib/php/extensions/no-debug-non-zts-20220829 /usr/local/lib/php/extensions/no-debug-non-zts-20220829
COPY --from=builder /usr/local/etc/php/conf.d/*.ini /usr/local/etc/php/conf.d/
RUN docker-php-ext-enable gd && \
    docker-php-ext-enable opcache && \
    docker-php-ext-enable pdo_pgsql && \
    docker-php-ext-enable redis && \
    docker-php-ext-enable simplexml
RUN rm /usr/local/etc/php/conf.d/docker-php-ext-igbinary.ini && \
    rm /usr/local/etc/php/conf.d/docker-php-ext-pcntl.ini && \
    rm /usr/local/etc/php/conf.d/docker-php-ext-sockets.ini && \
    rm /usr/local/etc/php/conf.d/docker-php-ext-zip.ini

#============================================
# Application
#============================================
COPY --chown=www-data:www-data ./app/             /var/www/html/app/
COPY --chown=www-data:www-data ./public/index.php /var/www/html/public/index.php
COPY --chown=www-data:www-data ./resources/       /var/www/html/resources/
COPY --chown=www-data:www-data ./src/             /var/www/html/src/
COPY --chown=www-data:www-data ./var/             /var/www/html/var/
COPY --chown=www-data:www-data --from=builder     /usr/src/vendor/               /var/www/html/vendor/

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
ARG VERSION=latest
ENV VERSION="${VERSION}"
LABEL maintainer="Flavio Heleno <flaviohbatista@gmail.com>" \
      org.opencontainers.image.authors="flaviohbatista@gmail.com" \
      org.opencontainers.image.base.name="ghcr.io/package-health/pph-php-fpm:${VERSION}" \
      org.opencontainers.image.source="https://github.com/package-health/php" \
      org.opencontainers.image.title="PHP-Package-Health: PHP-FPM" \
      org.opencontainers.image.url="https://github.com/package-health/php" \
      org.opencontainers.image.vendor="Package Health" \
      org.opencontainers.image.version="${VERSION}"

ENTRYPOINT ["/usr/bin/dumb-init", "--"]
CMD ["php-fpm"]
