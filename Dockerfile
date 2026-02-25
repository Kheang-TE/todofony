# ---- Étape 1 : Installation des dépendances avec Composer ----
FROM composer:2 AS composer_stage

WORKDIR /app
COPY composer.json composer.lock* ./

# Symfony a besoin de APP_ENV pour ne pas chercher de fichier .env
ENV APP_ENV=prod

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---- Étape 2 : Image de production ----
FROM php:8.4-cli

# Installer les extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libicu-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install \
    pdo_sqlite \
    intl \
    zip \
    opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configuration OPcache pour la production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

# Définir APP_ENV=prod pour que Symfony n'ait pas besoin de fichier .env
ENV APP_ENV=prod

# Copier le code et les dépendances depuis l'étape Composer
COPY --from=composer_stage /app /app

# Créer les répertoires nécessaires avec les bonnes permissions
RUN mkdir -p var/cache var/log var/share/prod /data \
    && chmod -R 777 var /data

# Rendre le script de démarrage exécutable
RUN chmod +x start.sh

# Exposer le port (Railway injecte $PORT automatiquement)
EXPOSE 8080

# Commande de démarrage
CMD ["sh", "start.sh"]
