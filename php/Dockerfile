FROM php:8.4-fpm-alpine

ENV COMPOSER_ALLOW_SUPERUSER 1
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apk update && apk upgrade

WORKDIR /srv

COPY . .

RUN composer install

CMD ["php-fpm", "-R", "-F"]

