#============================================
# BUILD
#============================================
ARG VERSION=latest

#============================================
# COMMAND LINE INTERFACE
#============================================
FROM package-health/php-cli:${VERSION} AS cli-debug

USER root

#============================================
# Third party Extensions
#============================================

RUN docker-php-source extract && \
    wget -O xdebug.tar.gz https://github.com/xdebug/xdebug/archive/refs/tags/3.2.2.tar.gz && \
    mkdir /usr/src/php/ext/xdebug && \
    tar --extract --file xdebug.tar.gz --directory /usr/src/php/ext/xdebug --strip 1 && \
    docker-php-ext-install -j$(nproc) xdebug && \
    docker-php-source delete

#============================================
# XDebug
#============================================
RUN echo "xdebug.mode=develop,debug,profile" > /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.cli_color=1" >> /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.output_dir=/var/www/html/bin" >> /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.use_compression=false" >> /usr/local/etc/php/conf.d/xdebug.ini

#============================================
# Disable Opcache/JIT (https://xdebug.org/docs/compat#compat)
#============================================
RUN rm /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

#============================================
# Metadata
#============================================
LABEL maintainer="Flavio Heleno <flaviohbatista@gmail.com>" \
      org.opencontainers.image.authors="flaviohbatista@gmail.com" \
      org.opencontainers.image.base.name="ghcr.io/package-health/pph-php-cli-debug:${VERSION}" \
      org.opencontainers.image.title="PHP-Package-Health: PHP-CLI-Debug" \
      org.opencontainers.image.url="https://github.com/package-health/php" \
      org.opencontainers.image.vendor="Package Health" \
      org.opencontainers.image.version="${VERSION}"

USER www-data

#============================================
# FPM SAPI
#============================================
FROM package-health/php-fpm:${VERSION} AS fpm-debug

USER root

#============================================
# Third party Extensions
#============================================

RUN docker-php-source extract && \
    wget -O xdebug.tar.gz https://github.com/xdebug/xdebug/archive/refs/tags/3.2.2.tar.gz && \
    mkdir /usr/src/php/ext/xdebug && \
    tar --extract --file xdebug.tar.gz --directory /usr/src/php/ext/xdebug --strip 1 && \
    docker-php-ext-install -j$(nproc) xdebug && \
    docker-php-source delete

#============================================
# XDebug
#============================================
RUN echo "xdebug.mode=develop,debug,profile" > /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.cli_color=1" >> /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.output_dir=/var/www/html" >> /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.use_compression=false" >> /usr/local/etc/php/conf.d/xdebug.ini

#============================================
# Disable Opcache/JIT (https://xdebug.org/docs/compat#compat)
#============================================
RUN rm /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

#============================================
# Metadata
#============================================
LABEL maintainer="Flavio Heleno <flaviohbatista@gmail.com>" \
      org.opencontainers.image.authors="flaviohbatista@gmail.com" \
      org.opencontainers.image.base.name="ghcr.io/package-health/pph-php-fpm-debug:${VERSION}" \
      org.opencontainers.image.title="PHP-Package-Health: PHP-FPM-Debug" \
      org.opencontainers.image.url="https://github.com/package-health/php" \
      org.opencontainers.image.vendor="Package Health" \
      org.opencontainers.image.version="${VERSION}"

USER www-data
