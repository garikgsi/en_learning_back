#!/bin/bash
set -euo pipefail

project_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)
test_dir=$(mktemp -d)
trap 'rm -rf -- "$test_dir"' EXIT
mkdir "$test_dir/bin"
cp "$project_dir/scripts/publish-backend.sh" "$test_dir/publish-backend.sh"
printf '\n# Outdated external copy used to verify self-refresh.\n' >> "$test_dir/publish-backend.sh"

cat > "$test_dir/bin/git" <<'MOCK_GIT'
#!/bin/bash
printf 'git %s\n' "$*" >> "$PUBLICATION_TEST_LOG"
case "$1" in
    config)
        if [[ "$2 $3 $4" == '--global --get-all safe.directory' ]]; then
            if [[ -f "$PUBLICATION_TEST_STATE/safe-directories" ]]; then
                cat "$PUBLICATION_TEST_STATE/safe-directories"
                exit 0
            fi
            exit 1
        fi
        if [[ "$2 $3 $4" == '--global --add safe.directory' ]]; then
            printf '%s\n' "$5" >> "$PUBLICATION_TEST_STATE/safe-directories"
            exit 0
        fi
        exit 1
        ;;
    fetch)
        if [[ ! -f "$PUBLICATION_TEST_STATE/safe-directories" ]] \
            || ! grep -Fqx -- "$PWD" "$PUBLICATION_TEST_STATE/safe-directories"; then
            printf "fatal: detected dubious ownership in repository at '%s'\n" "$PWD" >&2
            exit 128
        fi
        ;;
    merge-base)
        if [[ "${PUBLICATION_TEST_CASE:-}" == 'diverged' ]]; then exit 1; fi
        ;;
    diff)
        if [[ "${PUBLICATION_TEST_CASE:-}" == 'dirty' ]]; then exit 1; fi
        if [[ "${PUBLICATION_TEST_CASE:-}" == legacy-* && ! -f "$PUBLICATION_TEST_STATE/restored" ]]; then exit 1; fi
        ;;
    status)
        if [[ "${PUBLICATION_TEST_CASE:-}" == 'dirty' ]]; then
            printf ' M README.md\n'
        elif [[ "${PUBLICATION_TEST_CASE:-}" == legacy-* ]]; then
            printf ' M compose.yaml\n'
        fi
        ;;
    cat-file)
        ;;
    show)
        if [[ "$2" == 'origin/main:compose.override.yaml.example' ]]; then
            printf 'services:\n  queue:\n    volumes:\n      - ./firebase.json:/run/secrets/firebase-service-account.json:ro\n'
        else
            cat "$PUBLICATION_TEST_STATE/tracked-compose.yaml"
        fi
        ;;
    restore)
        touch "$PUBLICATION_TEST_STATE/restored"
        ;;
esac
exit 0
MOCK_GIT

cat > "$test_dir/bin/docker" <<'MOCK_DOCKER'
#!/bin/bash
printf 'docker %s\n' "$*" >> "$PUBLICATION_TEST_LOG"
if [[ "$*" == 'compose exec -T app php artisan migrate --force' && "${PUBLICATION_TEST_CASE:-}" == 'migration-fails' ]]; then exit 42; fi
exit 0
MOCK_DOCKER

chmod +x "$test_dir/bin/git" "$test_dir/bin/docker"
export PATH="$test_dir/bin:$PATH"
export PUBLICATION_TEST_LOG="$test_dir/commands.log"
export PUBLICATION_TEST_STATE="$test_dir/state"
mkdir "$PUBLICATION_TEST_STATE"
export PUBLICATION_TEST_CASE='success'

(cd "$test_dir" && bash ./publish-backend.sh "$project_dir") > "$test_dir/output.log" 2>&1
cat > "$test_dir/expected.log" <<EXPECTED
git config --global --get-all safe.directory
git config --global --add safe.directory $project_dir
git fetch origin
git checkout main
git diff --quiet
git diff --cached --quiet
git merge-base --is-ancestor HEAD origin/main
git pull --ff-only origin main
docker compose config --quiet
docker compose build --pull app nginx
docker compose up -d
docker compose exec -T app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan queue:restart
EXPECTED
diff -u "$test_dir/expected.log" "$PUBLICATION_TEST_LOG"
grep -q 'completed successfully' "$test_dir/output.log"
grep -q 'Registered the backend repository as a Git safe directory' "$test_dir/output.log"
grep -q 'Updated the external publication script from the repository template' "$test_dir/output.log"
cmp -s "$project_dir/scripts/publish-backend.sh" "$test_dir/publish-backend.sh"

: > "$PUBLICATION_TEST_LOG"
bash "$test_dir/publish-backend.sh" "$project_dir" > "$test_dir/output.log" 2>&1
grep -q '^git config --global --get-all safe.directory$' "$PUBLICATION_TEST_LOG"
! grep -q '^git config --global --add safe.directory ' "$PUBLICATION_TEST_LOG"
grep -q '^git fetch origin$' "$PUBLICATION_TEST_LOG"

for PUBLICATION_TEST_CASE in migration-fails diverged dirty; do
    export PUBLICATION_TEST_CASE
    : > "$PUBLICATION_TEST_LOG"
    if bash "$test_dir/publish-backend.sh" "$project_dir" > "$test_dir/output.log" 2>&1; then
        printf 'Expected publication to fail: %s\n' "$PUBLICATION_TEST_CASE" >&2
        exit 1
    else
        publication_status=$?
    fi
    if grep -q 'completed successfully' "$test_dir/output.log"; then
        printf 'Failure incorrectly reported as success\n' >&2
        exit 1
    fi
    if [[ "$PUBLICATION_TEST_CASE" == 'migration-fails' ]]; then
        [[ "$publication_status" -eq 42 ]]
        if grep -Eq 'artisan (optimize$|queue:restart)' "$PUBLICATION_TEST_LOG"; then
            printf 'Publication continued after failed migration\n' >&2
            exit 1
        fi
    elif grep -Eq '^(docker |git pull )' "$PUBLICATION_TEST_LOG"; then
        printf 'Publication continued after failed Git preflight\n' >&2
        exit 1
    fi
done

legacy_project="$test_dir/legacy-project"
mkdir -p "$legacy_project/storage/app/private/firebase"
printf '{}\n' > "$legacy_project/storage/app/private/firebase/service-account.json"
export PUBLICATION_TEST_PROJECT="$legacy_project"

cat > "$PUBLICATION_TEST_STATE/tracked-compose.yaml" <<'COMPOSE_BASE'
x-php-service:
  volumes:
    - ./:/var/www/html
    - dictionary_audio:/var/www/html/storage/app/dictionary/audio
services:
  queue:
    image: app
COMPOSE_BASE

cat > "$PUBLICATION_TEST_STATE/legacy-compose.yaml" <<'COMPOSE_LEGACY'
x-php-service:
  volumes:
    - ./:/var/www/html
    - ./storage/app/dictionary/audio:/var/www/html/storage/app/dictionary/audio
services:
  queue:
    image: app
    volumes:
      - /var/www/docker/en_learning_back/storage/app/private/firebase/service-account.json:/run/secrets/firebase-service-account.json:ro
COMPOSE_LEGACY

for PUBLICATION_TEST_CASE in legacy-known legacy-unknown; do
    export PUBLICATION_TEST_CASE
    cp "$PUBLICATION_TEST_STATE/legacy-compose.yaml" "$legacy_project/compose.yaml"
    if [[ "$PUBLICATION_TEST_CASE" == 'legacy-unknown' ]]; then
        printf 'unknown: change\n' >> "$legacy_project/compose.yaml"
    fi
    rm -f "$PUBLICATION_TEST_STATE/restored" "$legacy_project/compose.override.yaml"
    rm -rf "$legacy_project/storage/app/private/deployment-backups"
    : > "$PUBLICATION_TEST_LOG"

    if bash "$test_dir/publish-backend.sh" "$legacy_project" > "$test_dir/output.log" 2>&1; then
        [[ "$PUBLICATION_TEST_CASE" == 'legacy-known' ]]
        [[ -f "$legacy_project/compose.override.yaml" ]]
        find "$legacy_project/storage/app/private/deployment-backups" -type f -name 'compose.yaml.*.bak' | grep -q .
        grep -q 'Migrated the known server Compose settings' "$test_dir/output.log"
        grep -q '^git pull --ff-only origin main$' "$PUBLICATION_TEST_LOG"
        grep -q '^docker compose build --pull app nginx$' "$PUBLICATION_TEST_LOG"
    else
        [[ "$PUBLICATION_TEST_CASE" == 'legacy-unknown' ]]
        [[ ! -e "$legacy_project/compose.override.yaml" ]]
        [[ ! -d "$legacy_project/storage/app/private/deployment-backups" ]]
        ! grep -q '^git pull ' "$PUBLICATION_TEST_LOG"
        ! grep -q '^docker compose build ' "$PUBLICATION_TEST_LOG"
    fi
done

missing_secret_project="$test_dir/missing-secret-project"
mkdir -p "$missing_secret_project"
printf 'services:\n  queue:\n    volumes:\n      - ./firebase.json:/run/secrets/firebase-service-account.json:ro\n' \
    > "$missing_secret_project/compose.override.yaml"
export PUBLICATION_TEST_CASE='success'
: > "$PUBLICATION_TEST_LOG"
if bash "$test_dir/publish-backend.sh" "$missing_secret_project" > "$test_dir/output.log" 2>&1; then
    printf 'Expected publication to reject a missing Firebase service account\n' >&2
    exit 1
fi
grep -q 'Firebase service account is missing' "$test_dir/output.log"
! grep -q '^docker compose config ' "$PUBLICATION_TEST_LOG"
! grep -q '^docker compose build ' "$PUBLICATION_TEST_LOG"

printf 'Publication checks passed: success, failures, safe legacy Compose migration and secret preflight.\n'
