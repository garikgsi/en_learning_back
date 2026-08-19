# Project instructions

- Every API endpoint must be documented in `api_docs/*.http` using the JetBrains
  HTTP Client format.
- Add or update the corresponding HTTP request in the same change that creates
  or changes an endpoint.
- Use named requests, environment variables, realistic request bodies, and
  multipart examples where applicable.
- Never commit real access tokens, PIN codes, passwords, or other secrets to the
  HTTP Client files.
- Never configure cascading deletes for database foreign keys. Deleting a
  referenced parent row must be rejected while related rows exist.
- Document every computed Eloquent model property in the model PHPDoc using
  `@property-read` so it is available in IDE autocomplete.

## Testing and formatting

- Do not run Laravel tests, Artisan commands, Composer scripts, or Pint with
  the host PHP: its version is older than the version required by the project.
- Run backend checks directly in the existing `app` container:
  `docker compose exec -T app php artisan test` for tests and
  `docker compose exec -T app vendor/bin/pint --test` for formatting checks.
- For targeted checks, append test paths or Pint file paths to those container
  commands. Check `docker compose ps` only when a container command fails.
- Before running backend tests in a container that has been used with
  production configuration, always clear Laravel's config cache first. Run
  tests with explicit `APP_ENV=testing`, `DB_CONNECTION=sqlite`,
  `DB_DATABASE=:memory:`, `CACHE_STORE=array`, and `SESSION_DRIVER=array`
  overrides. Do this
  immediately instead of investigating the predictable cascade of missing
  seed data and PostgreSQL foreign-key failures again.

## Production deployment handoff

- The production server has a backend publication script that fetches and
  fast-forwards `main`, rebuilds and starts the `app` service, installs
  production Composer dependencies, refreshes Laravel optimization caches,
  and restarts queue workers.
- For an ordinary backend deployment, do not repeat the individual shell
  commands. Tell the user only: `запусти скрипт публикации на бэке`.
- Provide additional production commands only when a release requires an
  exceptional operation that is not covered by the publication script, such
  as a database migration or a one-time maintenance action.
