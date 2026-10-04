#!/bin/bash
set -e

# Port par défaut si non défini (Railway injecte $PORT automatiquement)
PORT="${PORT:-8080}"

echo "=========================================="
echo "  Démarrage de l'application Todofony"
echo "=========================================="

# --- 1. Génération des clés JWT si elles n'existent pas ---
echo "[1/5] Vérification des clés JWT..."
if [ ! -f config/jwt/private.pem ]; then
    echo "  -> Génération des clés JWT..."
    mkdir -p config/jwt
    openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:"${JWT_PASSPHRASE}"
    openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:"${JWT_PASSPHRASE}"
    echo "  -> Clés JWT générées avec succès !"
else
    echo "  -> Clés JWT déjà présentes."
fi

# --- 2. Nettoyage du cache ---
echo "[2/5] Nettoyage du cache..."
php bin/console cache:clear --env=prod --no-debug

# --- 3. Création de la base de données SQLite (si elle n'existe pas) ---
echo "[3/5] Vérification de la base de données..."
# SQLite ne supporte pas doctrine:database:create --if-not-exists
# Il suffit de s'assurer que le répertoire existe, le fichier sera créé automatiquement
mkdir -p "$(dirname "$(echo $DATABASE_URL | sed 's|sqlite:///||')")" 2>/dev/null || true
php bin/console doctrine:database:create --env=prod --no-interaction 2>/dev/null || echo "  -> Base de données déjà existante ou créée automatiquement."

# --- 4. Exécution des migrations ---
echo "[4/5] Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod --allow-no-migration

# --- 5. Démarrage du serveur PHP ---
echo "[5/5] Démarrage du serveur sur le port $PORT..."
echo "=========================================="
php -S 0.0.0.0:$PORT -t public