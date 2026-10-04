FROM php:8.4-cli-alpine

RUN apk add --no-cache icu-libs libpq \
	&& apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev \
	&& docker-php-ext-install -j"$(nproc)" intl pdo_pgsql opcache \
	&& apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Installer les dépendances avant le code pour profiter du cache Docker.
COPY composer.json composer.lock symfony.lock ./
RUN composer install \
	--no-dev \
	--prefer-dist \
	--no-interaction \
	--no-progress \
	--optimize-autoloader \
	--classmap-authoritative \
	--no-scripts

COPY . .

ENV APP_ENV=prod \
	APP_DEBUG=0

EXPOSE 8080

CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8080} -t public"]