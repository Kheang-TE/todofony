#!/bin/sh
# Executer le script avec le shell POSIX fourni par l'image Alpine.
# -e arrete le script en cas d'erreur ; -u traite toute variable absente comme une erreur.
set -eu

# Ces deux secrets doivent etre fournis par Docker au demarrage du conteneur.
# La phrase secrete chiffre la cle privee ; APP_SECRET protege les fonctionnalites Symfony.
: "${JWT_PASSPHRASE:?JWT_PASSPHRASE must be set at container startup}"
: "${APP_SECRET:?APP_SECRET must be set at container startup}"

# Recuperer les chemins de cles configures, ou utiliser ceux attendus par le projet.
private_key="${JWT_SECRET_KEY:-/var/www/html/config/jwt/private.pem}"
public_key="${JWT_PUBLIC_KEY:-/var/www/html/config/jwt/public.pem}"
# Les deux fichiers sont crees dans le meme dossier, qui peut etre monte en volume.
key_dir=$(dirname "$private_key")

# Refuser une configuration ou les deux cles seraient reparties dans des dossiers differents.
if [ "$(dirname "$public_key")" != "$key_dir" ]; then
	echo "JWT public and private keys must use the same directory." >&2
	exit 1
fi

# Creer les dossiers necessaires en tant que www-data, sans changer d'utilisateur.
# Le dossier de base SQLite est place dans var, qui doit aussi etre accessible en ecriture.
if ! mkdir -p "$key_dir" /var/www/html/var/cache /var/www/html/var/log; then
	echo "JWT and SQLite volumes must be writable by www-data (UID 82). Use named Docker volumes or fix host directory permissions." >&2
	exit 1
fi

# Verifier les droits reellement disponibles pour l'utilisateur qui executera Symfony.
if ! test -w "$key_dir" || ! test -w /var/www/html/var; then
	echo "JWT and SQLite volumes must be writable by www-data (UID 82). Use named Docker volumes or fix host directory permissions." >&2
	exit 1
fi

# Si la paire existe deja, la conserver pour que les jetons emis restent valides.
if [ -f "$private_key" ] && [ -f "$public_key" ]; then
	echo "Existing JWT key pair found."
# Si aucune cle n'existe, generer une nouvelle paire ; ne jamais ecraser une paire incomplete.
elif [ ! -e "$private_key" ] && [ ! -e "$public_key" ]; then
	echo "Generating JWT key pair..."
	# Les nouveaux fichiers temporaires sont prives par defaut (droits 600).
	umask 077
	private_tmp=$(mktemp "$key_dir/.private.XXXXXX")
	public_tmp=$(mktemp "$key_dir/.public.XXXXXX")
	# Supprimer les fichiers temporaires si OpenSSL echoue ou si le conteneur est interrompu.
	trap 'rm -f "$private_tmp" "$public_tmp"' EXIT HUP INT TERM

	# Creer une cle RSA de 4096 bits, chiffree avec JWT_PASSPHRASE.
	openssl genpkey \
		-algorithm RSA \
		-aes-256-cbc \
		-pass env:JWT_PASSPHRASE \
		-pkeyopt rsa_keygen_bits:4096 \
		-out "$private_tmp"
	# Deriver et enregistrer la cle publique correspondante.
	openssl pkey \
		-in "$private_tmp" \
		-passin env:JWT_PASSPHRASE \
		-pubout \
		-out "$public_tmp"

	# Restreindre l'acces a la cle privee ; la cle publique peut etre lisible par tous.
	chmod 600 "$private_tmp"
	chmod 644 "$public_tmp"
	# Deplacer les deux fichiers temporaires vers leurs chemins definitifs dans le volume.
	mv "$private_tmp" "$private_key"
	mv "$public_tmp" "$public_key"
	# Les fichiers temporaires ont ete deplaces ; desactiver leur nettoyage automatique.
	trap - EXIT HUP INT TERM
else
	# Une paire incomplete ne doit pas etre remplacee automatiquement.
	echo "Incomplete JWT key pair in $key_dir; refusing to replace it." >&2
	exit 1
fi

# Verifier que le compte Symfony peut lire les deux cles avant son lancement.
if ! test -r "$private_key" || ! test -r "$public_key"; then
	echo "JWT key files must be readable by www-data (UID 82)." >&2
	exit 1
fi

# Remplacer le script par le processus PHP, execute sous le compte non privilegie.
exec "$@"