FROM nginx:mainline-alpine

# https://blog.packagecloud.io/eng/2017/02/21/set-environment-variable-save-thousands-of-system-calls/
ENV TZ=:/etc/localtime

# nginx settings
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/upstream.conf.template /etc/nginx/templates/upstream.conf.template

# static assets
COPY public/css/ /usr/share/nginx/html/css/
COPY public/fonts/ /usr/share/nginx/html/fonts/
COPY public/sprites/ /usr/share/nginx/html/sprites/
COPY public/svg/ /usr/share/nginx/html/svg/
COPY public/robots.txt /usr/share/nginx/html/

#============================================
# Healthcheck
#============================================
HEALTHCHECK --interval=1m30s --timeout=10s --retries=3 --start-period=40s CMD curl -f http://localhost/status || exit 1

#============================================
# Metadata
#============================================
LABEL org.opencontainers.image.authors="flaviohbatista@gmail.com" \
      org.opencontainers.image.title="PHP-Package-Health-NGINX" \
      org.opencontainers.image.url="https://github.com/package-health/php" \
      org.opencontainers.image.vendor="Package Health"

WORKDIR /usr/share/nginx/html
