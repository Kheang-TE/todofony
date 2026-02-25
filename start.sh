#!/bin/bash

echo "Nettoyage du cache..."
php bin/console cache:clear --env=prod

echo "Installation des dépendances..."
composer install --no-dev --optimize-autoloader

echo "Création de la base de données..."
php bin/console doctrine:database:create

echo "Exécution des migrations..."
php bin/console doctrine:migrations:migrate

echo "Démarrage du serveur..."
php -S 0.0.0.0:$PORT -t public