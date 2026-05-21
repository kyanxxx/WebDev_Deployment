# Docker deployment

This project runs as a Symfony app behind Apache with MySQL, phpMyAdmin, and automatic migrations on startup.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine + Compose v2)

## Quick start

1. Ensure environment variables exist (copy the example if needed):

   ```bash
   cp .env.docker.example .env
   ```

   Edit `.env` and set at least `APP_SECRET`, `JWT_PASSPHRASE`, and any mailer/OAuth values you use.

2. Build and start:

   ```bash
   docker compose up -d --build
   ```

3. Open the app:

   - **Application:** http://localhost:8081 (override with `APP_PORT` in `.env`)
   - **phpMyAdmin:** http://localhost:8080 (`PHPMYADMIN_PORT`)

## Services

| Service     | Role                                      |
|------------|-------------------------------------------|
| `app`      | PHP 8.3 + Apache, Webpack assets, Symfony |
| `mysql`    | MySQL 8 database                          |
| `phpmyadmin` | DB admin UI                             |

On first start, the app container waits for MySQL, runs migrations, warms the cache (in `prod`), and generates JWT keys if missing.

## Useful commands

```bash
# View logs
docker compose logs -f app

# Run Symfony console
docker compose exec app php bin/console cache:clear

# Stop and remove containers (keeps DB volume)
docker compose down

# Stop and remove containers + database volume
docker compose down -v
```

## Google OAuth in Docker

Add your Docker app URL to Google Cloud Console authorized redirect URIs, for example:

`http://localhost:8081/connect/google/check`

## Production notes

- Set strong values for `APP_SECRET`, `MYSQL_*`, and `JWT_PASSPHRASE`.
- Do not commit `.env` with real secrets (use `.env.dist` in the image + platform env vars).
- Put a reverse proxy (TLS) in front of the `app` service for public deployment.
- Consider `APP_ENV=prod` and `APP_DEBUG=0` (defaults in Compose).

## Railway

The Docker image ships `.env.dist` as `.env` so Symfony can boot. Set these **Variables** in the Railway service (they override the file):

| Variable | Required |
|----------|----------|
| `APP_SECRET` | Yes (long random string — empty value causes login/session errors) |
| `DATABASE_URL` | Yes (from Railway MySQL plugin, e.g. `mysql://user:pass@host:port/railway`) |
| `JWT_PASSPHRASE` | Yes |
| `MAILER_DSN` | If using email |
| `OAUTH_GOOGLE_CLIENT_ID` / `OAUTH_GOOGLE_CLIENT_SECRET` | If using Google login |
| `CORS_ALLOW_ORIGIN` | Your Railway URL, e.g. `'^https://webdevdeployment-production\.up\.railway\.app$'` |

`APP_ENV=prod` and `APP_DEBUG=0` are set in the image. Railway’s `PORT` is applied automatically in the entrypoint.

On deploy, `app:create-user` ensures default users exist (`admin` / `admin123`). Change passwords after first login.

If you created an admin manually in phpMyAdmin, ensure the `user` table has a `roles` JSON column (not legacy `role`). After redeploy, migration `Version20260522120000` converts old schemas automatically.
