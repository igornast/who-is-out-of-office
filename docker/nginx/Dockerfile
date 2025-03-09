FROM nginx:1.27-alpine as base

COPY docker/nginx/tmpl.nginx.conf /etc/nginx/

ENV D="$"
ENV PHP_PATH=/var/www/public
ENV LOG_FORMAT="/dev/stdout json_combined"
CMD ["/bin/sh","-c","envsubst '$$D,$$PHP_HOST,$$PHP_PATH,$$LOG_FORMAT' < /etc/nginx/tmpl.nginx.conf > /etc/nginx/nginx.conf && exec nginx -g 'daemon off;'"]

FROM base as prod

COPY app/public/ /var/www/public/

FROM base as local