#!/bin/bash
set -Eeuo pipefail

trap 'printf "Backend publication failed at line %s.\n" "$LINENO" >&2' ERR

project_dir=${1:-/var/www/docker/en_learning_back}
cd -- "$project_dir"

git fetch origin
git checkout main

if ! git diff --quiet || ! git diff --cached --quiet; then
    printf 'Tracked files have local changes. Move server settings to .env and compose.override.yaml before publishing.\n' >&2
    git status --short
    exit 1
fi

if ! git merge-base --is-ancestor HEAD origin/main; then
    printf 'Local main contains commits absent from origin/main. Publication stopped; no merge or reset was performed.\n' >&2
    git log --oneline --left-right main...origin/main
    exit 1
fi

git pull --ff-only origin main

docker compose config --quiet
docker compose build --pull app
docker compose up -d

docker compose exec -T app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan queue:restart

printf 'Backend publication completed successfully.\n'
