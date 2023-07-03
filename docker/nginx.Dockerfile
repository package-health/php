FROM nginx:1.25-alpine-slim as nginx

# https://blog.packagecloud.io/eng/2017/02/21/set-environment-variable-save-thousands-of-system-calls/
ENV TZ=:/etc/localtime

# default PHP-FPM upstream
ENV PHP_FPM=php-fpm

#============================================
# Force base image upgrade
#============================================
RUN apk add --no-cache --upgrade apk-tools && \
    apk upgrade --available

# nginx settings
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/upstream.conf.template /etc/nginx/templates/upstream.conf.template

# static assets
COPY public/css/ /usr/share/nginx/html/css/
COPY public/favicon/ /usr/share/nginx/html/favicon/
COPY public/fonts/ /usr/share/nginx/html/fonts/
COPY public/sprites/ /usr/share/nginx/html/sprites/
COPY public/svg/ /usr/share/nginx/html/svg/
COPY public/robots.txt /usr/share/nginx/html/

#============================================
# Healthcheck
#============================================
RUN apk add --no-cache curl

HEALTHCHECK --interval=1m30s --timeout=10s --retries=3 --start-period=40s CMD curl --fail http://localhost/status || exit 1

#============================================
# Metadata
#============================================
ARG VERSION=latest
LABEL maintainer="Flavio Heleno <flaviohbatista@gmail.com>" \
      org.opencontainers.image.authors="flaviohbatista@gmail.com" \
      org.opencontainers.image.base.name="ghcr.io/package-health/pph-nginx:${VERSION}" \
      org.opencontainers.image.source="https://github.com/package-health/php" \
      org.opencontainers.image.title="PHP-Package-Health-NGINX" \
      org.opencontainers.image.url="https://github.com/package-health/php" \
      org.opencontainers.image.vendor="Package Health" \
      org.opencontainers.image.version="${VERSION}"

WORKDIR /usr/share/nginx/html
