# English Learning API

Backend API for the English Learning application. The project uses Laravel 13,
PHP 8.5 FPM, Nginx, and PostgreSQL 18.

## Requirements

- Docker Desktop with Docker Compose

No local PHP, Composer, Nginx, or PostgreSQL installation is required.

## First run

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

On Windows PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

The application is available at <http://localhost:8088>. The health endpoint is
<http://localhost:8088/up>. PostgreSQL is available to host tools at
`localhost:5432`.

### pgAdmin

Open <http://localhost:5050> and sign in with:

- Email: `pgadmin4@pgadmin.org`
- Password: `admin`

The `English Learning (Docker)` server is registered automatically:

- Host: `postgres`
- Port: `5432`
- Database: `en_learning`
- Username: `en_learning`
- Password: `en_learning`

These are development-only credentials and can be changed in `.env`. If the
database password is changed, update `docker/pgadmin/pgpass` as well.

## Daily commands

```bash
# Start
docker compose up -d

# Stop
docker compose down

# Follow logs
docker compose logs -f

# Run Artisan or Composer
docker compose exec app php artisan migrate
docker compose exec app composer install

# Run tests and formatting
docker compose exec app composer test
docker compose exec app vendor/bin/pint --test
```

## PHP debugging

Xdebug 3 step debugging is enabled in the development environment:

```dotenv
XDEBUG_MODE=debug,develop
```

```bash
docker compose up -d --force-recreate app
```

Xdebug connects to the host on port `9003` at the start of each request. A ready-to-use VS Code
`Listen for Xdebug (Docker)` configuration is included. In PhpStorm, configure a
server named `en-learning-back` with host `localhost`, port `8088`, and path
mapping from the project root to `/var/www/html`.

Application files are bind-mounted into the PHP-FPM and Nginx containers, so
source changes are immediately visible without rebuilding. Rebuild the `app`
image only after changing the Dockerfile, PHP extensions, or PHP configuration:

```bash
docker compose build app
```

## Configuration

The checked-in `.env.example` contains safe development defaults. Change
`APP_PORT` or `DB_FORWARD_PORT` if the host ports are already occupied. Never
commit `.env`.
