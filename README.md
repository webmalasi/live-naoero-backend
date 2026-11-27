# Naoero Airlines backend (Laravel)

The single backend behind the Naoero Airlines **website** (napacificbridge.info) and the **mobile app**. Live at `https://api.napacificbridge.info`.

It provides:

| Part | URL | Used by |
|---|---|---|
| Admin panel (content, shop, inquiries, users) | `/admin` (sign in at `/admin/login`) | Staff |
| Content API: pages, blog, news, fleet, banners, forms | `/api/...` | Website and app |
| App API v1: flights, bookings, accounts, shop | `/api/v1/...` (docs at `/api/v1/docs`) | App, and the website for sign-in and shop |
| Uploaded images and files | `/storage/...` | Website and app |

Flights, bookings and customer accounts come from **Zenith** (the airline's reservation system). Shop card payments go through the **NAB Gateway**. The keys for both stay in this backend's `.env`.

## Stack

PHP 8.4, Laravel 13, PostgreSQL 16, a queue worker and a scheduler. In production it runs in Docker (`deploy/docker/`) behind nginx (`deploy/nginx/`).

## Folder guide

| Path | What it is |
|---|---|
| `app/Http/Controllers/Admin` | Admin panel pages |
| `app/Http/Controllers/Api` | Content API (`routes/api.php`) |
| `app/Http/Controllers/Api/V1` | App API (`routes/api_v1.php`) |
| `app/Services/Zenith` | Zenith client (flights, bookings, customers) |
| `app/Services/Payments` | NAB Gateway client (shop payments) |
| `resources/openapi/app-v1.json` | App API specification, served at `/api/v1/openapi.json` |
| `database/migrations`, `database/seeders` | Database structure and starter content |
| `storage/app/public` | Uploaded images and files (banners, destinations, fleet, gallery …) |
| `tests/` | Feature and unit tests |
| `deploy/docker/` | Dockerfile, docker-compose, `php.ini`, PHP-FPM pool (server copy: `/opt/naoero-laravel`) |
| `deploy/nginx/` | nginx sites as installed on the server (`/etc/nginx/sites-available`) |
| `docs/` | Postman collection for the content API, screenshots |

## Run it locally

```bash
composer install
npm install && npm run build      # admin panel styles and scripts
cp .env .env.local.backup         # .env holds the production settings; adjust DB_* and APP_URL for your machine
php artisan key:generate          # only for a fresh .env without APP_KEY
php artisan migrate --seed        # empty database with starter content
php artisan storage:link
php artisan serve
```

To work with the real data instead of starter content, restore the latest dump from `../postgres-db/` (see its README) and skip `--seed`.

### Settings (`.env`)

| Group | Keys |
|---|---|
| App | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE` |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Zenith | `ZENITH_AUTH_KEY`, `ZENITH_ENDPOINT`, `ZENITH_CULTURE`, `ZENITH_TIMEOUT_MS` |
| App API | `AUTH_JWT_SECRET`, `APP_ACCESS_TOKEN`, `SEARCH_RATE_LIMIT`, `LOG_RETENTION_DAYS`, `NAOERO_CACHE_STORE` |
| NAB / shop | `NAB_ENV`, `NAB_API_HOST`, `NAB_MERCHANT_ID`, `NAB_KEY_ID`, `NAB_SHARED_SECRET`, `NAB_UC_CLIENT_VERSION`, `SHOP_PAY_ORIGIN`, `SHOP_RETURN_HOSTS` |

`.env` contains live secrets (Zenith key, NAB credentials, database password). Share it only with people who need it.

## Tests

```bash
php artisan test        # uses an in-memory SQLite database; no network needed
```

## Deploying

The production server (37.60.224.111) keeps the code in `/var/www/naoero-laravel`, mounted into the `naoero-laravel-app`, `-queue` and `-scheduler` containers.

1. Copy the changed files to `/var/www/naoero-laravel`.
2. `docker exec naoero-laravel-app php artisan migrate --force` (only if there are new migrations).
3. `docker exec naoero-laravel-app php artisan optimize`.
4. `docker restart naoero-laravel-queue` (only if jobs changed).

## Related folders

- `../postgres-db/`: database backups and the database server's configuration.
- `../naoero-apis/`: API documentation (content API, App API, Zenith, NAB).
- `../napacificbridge.info/`: the website that uses this backend.
