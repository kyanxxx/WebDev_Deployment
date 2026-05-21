#!/bin/sh
set -e

# Railway and similar platforms inject PORT (Apache defaults to 80)
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    echo "Configuring Apache to listen on port ${PORT}"
    sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-enabled/000-default.conf
fi

mkdir -p var/cache var/log config/jwt
chown -R www-data:www-data var config/jwt 2>/dev/null || true

echo "Waiting for database..."
attempt=0
until php -r '
$url = getenv("DATABASE_URL");
if (!$url) { exit(1); }
$parts = parse_url($url);
$dsn = sprintf(
    "mysql:host=%s;port=%d;dbname=%s",
    $parts["host"],
    $parts["port"] ?? 3306,
    ltrim($parts["path"] ?? "", "/")
);
new PDO($dsn, $parts["user"], $parts["pass"] ?? "");
' >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        echo "Database is not reachable after 60 attempts."
        exit 1
    fi
    sleep 2
done

if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT keypair..."
    php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

if [ "$APP_ENV" = "prod" ]; then
    php bin/console cache:clear --no-warmup
    php bin/console cache:warmup
fi

php bin/console assets:install public --no-interaction

chown -R www-data:www-data var public/bundles 2>/dev/null || true

exec docker-php-entrypoint "$@"
