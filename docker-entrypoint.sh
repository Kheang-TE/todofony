#!/bin/sh
set -eu

: "${JWT_PASSPHRASE:?JWT_PASSPHRASE must be set at container startup}"
: "${APP_SECRET:?APP_SECRET must be set at container startup}"

private_key="${JWT_SECRET_KEY:-/var/www/html/config/jwt/private.pem}"
public_key="${JWT_PUBLIC_KEY:-/var/www/html/config/jwt/public.pem}"
key_dir=$(dirname "$private_key")

if [ "$(dirname "$public_key")" != "$key_dir" ]; then
	echo "JWT public and private keys must use the same directory." >&2
	exit 1
fi

mkdir -p "$key_dir" /var/www/html/var/cache /var/www/html/var/log
chown www-data:www-data "$key_dir" /var/www/html/var
chmod 700 "$key_dir"

if [ -f "$private_key" ] && [ -f "$public_key" ]; then
	echo "Existing JWT key pair found."
elif [ ! -e "$private_key" ] && [ ! -e "$public_key" ]; then
	echo "Generating JWT key pair..."
	umask 077
	private_tmp=$(mktemp "$key_dir/.private.XXXXXX")
	public_tmp=$(mktemp "$key_dir/.public.XXXXXX")
	trap 'rm -f "$private_tmp" "$public_tmp"' EXIT HUP INT TERM

	openssl genpkey \
		-algorithm RSA \
		-aes-256-cbc \
		-pass env:JWT_PASSPHRASE \
		-pkeyopt rsa_keygen_bits:4096 \
		-out "$private_tmp"
	openssl pkey \
		-in "$private_tmp" \
		-passin env:JWT_PASSPHRASE \
		-pubout \
		-out "$public_tmp"

	chmod 600 "$private_tmp"
	chmod 644 "$public_tmp"
	mv "$private_tmp" "$private_key"
	mv "$public_tmp" "$public_key"
	trap - EXIT HUP INT TERM
else
	echo "Incomplete JWT key pair in $key_dir; refusing to replace it." >&2
	exit 1
fi

chown www-data:www-data "$private_key" "$public_key"
chmod 600 "$private_key"
chmod 644 "$public_key"

exec su-exec www-data "$@"