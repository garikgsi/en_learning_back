#!/bin/bash
set -euo pipefail

project_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)
test_dir=$(mktemp -d)
trap 'rm -rf -- "$test_dir"' EXIT
mkdir "$test_dir/bin"
cp "$project_dir/scripts/publish-backend.sh" "$test_dir/publish-backend.sh"

cat > "$test_dir/bin/git" <<'MOCK_GIT'
#!/bin/bash
printf 'git %s\n' "$*" >> "$PUBLICATION_TEST_LOG"
if [[ "$1" == 'merge-base' && "${PUBLICATION_TEST_CASE:-}" == 'diverged' ]]; then exit 1; fi
if [[ "$1" == 'diff' && "${PUBLICATION_TEST_CASE:-}" == 'dirty' ]]; then exit 1; fi
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
export PUBLICATION_TEST_CASE='success'

bash "$test_dir/publish-backend.sh" "$project_dir" > "$test_dir/output.log" 2>&1
cat > "$test_dir/expected.log" <<'EXPECTED'
git fetch origin
git checkout main
git diff --quiet
git diff --cached --quiet
git merge-base --is-ancestor HEAD origin/main
git pull --ff-only origin main
docker compose config --quiet
docker compose build --pull app
docker compose up -d
docker compose exec -T app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan queue:restart
EXPECTED
diff -u "$test_dir/expected.log" "$PUBLICATION_TEST_LOG"
grep -q 'completed successfully' "$test_dir/output.log"

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

printf 'Publication checks passed: success, migration failure, divergent history, dirty tracked files.\n'
