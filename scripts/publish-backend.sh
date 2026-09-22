#!/bin/bash
set -Eeuo pipefail

temporary_dir=''
running_script=$(readlink -f -- "${BASH_SOURCE[0]}")

cleanup() {
    if [[ -n "$temporary_dir" && -d "$temporary_dir" ]]; then
        rm -rf -- "$temporary_dir"
    fi
}

trap cleanup EXIT
trap 'printf "Backend publication failed at line %s.\n" "$LINENO" >&2' ERR

project_dir=${1:-/var/www/docker/en_learning_back}
project_dir=$(cd -- "$project_dir" && pwd -P)

if ! git config --global --get-all safe.directory | grep -Fqx -- "$project_dir"; then
    git config --global --add safe.directory "$project_dir"
    printf 'Registered the backend repository as a Git safe directory: %s\n' "$project_dir"
fi

cd -- "$project_dir"

install_known_compose_override() {
    local tracked_status backup_dir backup_path

    tracked_status=$(git status --porcelain --untracked-files=no)
    if [[ "$tracked_status" != ' M compose.yaml' ]]; then
        return 1
    fi

    if [[ -f "$project_dir/compose.override.yaml" ]]; then
        return 1
    fi

    if ! git cat-file -e origin/main:compose.override.yaml.example; then
        return 1
    fi

    temporary_dir=$(mktemp -d)
    git show HEAD:compose.yaml > "$temporary_dir/tracked-compose.yaml"
    git show origin/main:compose.override.yaml.example > "$temporary_dir/compose.override.yaml"

    awk '
        $0 == "    - ./storage/app/dictionary/audio:/var/www/html/storage/app/dictionary/audio" {
            print "    - dictionary_audio:/var/www/html/storage/app/dictionary/audio"
            next
        }
        $0 == "    volumes:" {
            current = $0
            if ((getline following) > 0) {
                if (following == "      - /var/www/docker/en_learning_back/storage/app/private/firebase/service-account.json:/run/secrets/firebase-service-account.json:ro") {
                    next
                }
                print current
                print following
                next
            }
        }
        { print }
    ' "$project_dir/compose.yaml" > "$temporary_dir/normalized-compose.yaml"

    if ! cmp -s "$temporary_dir/tracked-compose.yaml" "$temporary_dir/normalized-compose.yaml"; then
        return 1
    fi

    backup_dir="$project_dir/storage/app/private/deployment-backups"
    mkdir -p -- "$backup_dir"
    backup_path="$backup_dir/compose.yaml.$(date -u +%Y%m%dT%H%M%SZ).bak"
    cp -- "$project_dir/compose.yaml" "$backup_path"

    cp -- "$temporary_dir/compose.override.yaml" "$project_dir/compose.override.yaml"

    git restore --worktree -- compose.yaml

    if ! git diff --quiet || ! git diff --cached --quiet; then
        return 1
    fi

    printf 'Migrated the known server Compose settings to compose.override.yaml.\n'
    printf 'Original compose.yaml backup: %s\n' "$backup_path"
}

git fetch origin
git checkout main

if ! git diff --quiet || ! git diff --cached --quiet; then
    if ! install_known_compose_override; then
        printf 'Tracked files contain unknown local changes; publication stopped without overwriting them.\n' >&2
        git status --short
        exit 1
    fi
fi

if ! git merge-base --is-ancestor HEAD origin/main; then
    printf 'Local main contains commits absent from origin/main. Publication stopped; no merge or reset was performed.\n' >&2
    git log --oneline --left-right main...origin/main
    exit 1
fi

git pull --ff-only origin main

repository_script_path="$project_dir/scripts/publish-backend.sh"
if [[ -f "$repository_script_path" ]]; then
    repository_script=$(readlink -f -- "$repository_script_path")
    if [[ "$running_script" != "$repository_script" ]] \
        && ! cmp -s "$running_script" "$repository_script"; then
        staged_script="${running_script}.new"
        cp -- "$repository_script" "$staged_script"
        chmod --reference="$running_script" "$staged_script"
        mv -- "$staged_script" "$running_script"
        printf 'Updated the external publication script from the repository template.\n'
    fi
fi

mkdir -p -- storage/app/dictionary/audio
if [[ -f compose.override.yaml ]] \
    && grep -q '/run/secrets/firebase-service-account.json' compose.override.yaml \
    && [[ ! -f storage/app/private/firebase/service-account.json ]]; then
    printf 'Firebase service account is missing: storage/app/private/firebase/service-account.json\n' >&2
    exit 1
fi

docker compose config --quiet
docker compose build --pull app nginx
docker compose up -d

docker compose exec -T app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan queue:restart

printf 'Backend publication completed successfully.\n'
