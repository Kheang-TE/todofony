# Image PHP CLI officielle, disponible notamment pour les plateformes ARM64.
FROM php:8.4-cli-alpine

# Installer les bibliotheques utilisees a l'execution, compiler les extensions PHP
# necessaires a Symfony et SQLite, puis supprimer les outils de compilation.
RUN apk add --no-cache icu-libs sqlite-libs openssl \
	&& apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev sqlite-dev \
	&& docker-php-ext-install -j"$(nproc)" intl opcache pdo_sqlite \
	&& apk del .build-deps

# Copier Composer depuis son image officielle, sans installer Composer dans l'image finale.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Tous les chemins relatifs suivants sont evalues depuis le dossier du projet Symfony.
WORKDIR /var/www/html

# Copier d'abord les fichiers de dependances : Docker peut reutiliser cette couche
# tant que les fichiers Composer ne changent pas.
COPY composer.json composer.lock symfony.lock ./

# Installer uniquement les dependances de production et optimiser l'autoloader.
# --no-scripts reporte les scripts Symfony, qui peuvent necessiter la configuration runtime.
RUN composer install \
	--no-dev \
	--prefer-dist \
	--no-interaction \
	--no-progress \
	--optimize-autoloader \
	--classmap-authoritative \
	--no-scripts

# Copier le code applicatif. Le .dockerignore exclut notamment les fichiers .env,
# le cache local, vendor et les cles JWT PEM presentes sur la machine de build.
COPY . .

# Creer les dossiers necessaires et les rendre accessibles a l'utilisateur PHP.
RUN mkdir -p var/cache var/log config/jwt \
	&& chown -R www-data:www-data var config/jwt

# Installer le script qui generera les cles JWT au demarrage du conteneur.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

# Valeurs de production non sensibles et chemins par defaut.
# APP_SECRET et JWT_PASSPHRASE doivent etre fournis au lancement du conteneur.
ENV APP_ENV=prod \
	APP_DEBUG=0 \
	APP_SECRET= \
	DATABASE_URL=sqlite:////var/www/html/var/data_app.db \
	MAILER_DSN=null://null \
	MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0 \
	CORS_ALLOW_ORIGIN='^https?://(localhost|127[.]0[.]0[.]1)(:[0-9]+)?$' \
	DEFAULT_URI=http://localhost \
	JWT_COOKIE_SECURE=1 \
	JWT_SECRET_KEY=/var/www/html/config/jwt/private.pem \
	JWT_PUBLIC_KEY=/var/www/html/config/jwt/public.pem

# Executer directement le conteneur sous le compte non privilegie qui possede
# les dossiers var/ et config/jwt/ prepares pendant le build.
USER www-data

# Documenter le port HTTP utilise par le serveur PHP et le reverse proxy Caddy.
EXPOSE 8000

# Generer/verifier les cles JWT avant de lancer la commande principale.
ENTRYPOINT ["/usr/local/bin/docker-entrypoint"]
# Demarrer le serveur PHP sur toutes les interfaces du conteneur.
# Le serveur web peut ensuite transmettre les requetes au service sur le port 8000.
CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8000} -t public"]
