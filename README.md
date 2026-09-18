# English Learning API

Backend API приложения для изучения английского языка. Стек: Laravel 13,
PHP-FPM 8.5, Nginx и PostgreSQL 18.

## Требования

- Git;
- Docker Desktop с Docker Compose.

Локальная установка PHP, Composer, Nginx и PostgreSQL не требуется.

## Первый запуск

Клонируйте репозиторий, перейдите в его корень и создайте локальный файл
окружения:

```powershell
Copy-Item .env.example .env
```

Для Linux и macOS:

```bash
cp .env.example .env
```

Соберите и запустите контейнеры, затем подготовьте Laravel:

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

После запуска доступны:

- API: <http://localhost:8088>;
- проверка состояния: <http://localhost:8088/up>;
- PostgreSQL для локальных клиентов: `localhost:5432`;
- pgAdmin: <http://localhost:5050>.

Данные для входа в pgAdmin по умолчанию:

- email: `pgadmin4@pgadmin.org`;
- пароль: `admin`.

Сервер `English Learning (Docker)` регистрируется автоматически. Параметры
подключения к базе внутри Docker:

- host: `postgres`;
- port: `5432`;
- database: `en_learning`;
- username: `en_learning`;
- password: `en_learning`.

Это локальные данные для разработки. Их можно изменить в `.env`. При изменении
пароля базы также обновите `docker/pgadmin/pgpass`.

## Ежедневная работа

```bash
# Запустить окружение
docker compose up -d

# Посмотреть состояние контейнеров
docker compose ps

# Следить за логами
docker compose logs -f

# Применить новые миграции
docker compose exec app php artisan migrate

# Открыть Laravel Tinker
docker compose exec app php artisan tinker

# Остановить окружение
docker compose down
```

Исходники примонтированы в контейнеры `app` и `nginx`, поэтому изменения PHP-кода
применяются без пересборки. Пересобирайте образ после изменения `Dockerfile`,
PHP-расширений или конфигурации PHP:

```bash
docker compose build app
docker compose up -d --force-recreate app
```

## Тестирование

Тесты запускаются внутри контейнера `app`. Перед первым запуском убедитесь, что
контейнеры собраны и запущены:

```bash
docker compose up -d
```

Запуск всех тестов:

```bash
docker compose exec app composer test
```

Запуск отдельного файла:

```bash
docker compose exec app php artisan test tests/Feature/ExerciseControllerTest.php
```

Запуск одного теста по имени:

```bash
docker compose exec app php artisan test --filter=test_it_returns_current_users_exercises_for_inclusive_period
```

Тестовая среда использует SQLite в памяти согласно настройкам `phpunit.xml`.
Каждый запуск начинает работу с чистой тестовой базой и не изменяет локальную
PostgreSQL.

## Проверка изменений

```bash
# Проверка форматирования PHP
docker compose exec app vendor/bin/pint --test

# Автоматическое форматирование PHP
docker compose exec app vendor/bin/pint
```

## Отладка PHP

Xdebug 3 включён в локальном окружении и подключается к хосту на порт `9003`.
В `.env` должно быть:

```dotenv
XDEBUG_MODE=debug,develop
```

После изменения режима пересоздайте контейнер:

```bash
docker compose up -d --force-recreate app
```

Для VS Code в репозитории есть конфигурация `Listen for Xdebug (Docker)`. В
PhpStorm создайте сервер `en-learning-back` с host `localhost`, port `8088` и
сопоставлением корня проекта с `/var/www/html`.

## Настройка окружения

`.env.example` содержит безопасные значения для локальной разработки. Если порт
занят, измените `APP_PORT`, `DB_FORWARD_PORT` или `PGADMIN_PORT` в `.env`.
Значение `AUTH_PIN_PEPPER` также следует заменить локальным секретом.

Файл `.env` содержит локальные настройки и секреты — не добавляйте его в Git.

## Запуск вместе с фронтендом

Сначала запустите этот проект. Затем в репозитории фронтенда
`en_learning_tg_app` создайте `.env` из `.env.example` и выполните `npm run dev`.
Фронтенд будет доступен на <http://localhost:3000> и по умолчанию отправит
API-запросы на <http://localhost:8088>.

## Роли пользователей

Роль хранится в `users.role`: `user` — обычный пользователь, `admin` —
администратор. По умолчанию все новые пользователи получают `user`.
Миграция `2026_09_18_000026_add_role_to_users_table.php` назначает `admin`
двум существующим аккаунтам с указанными в ней полными номерами телефонов
(окончания `0386` и `1479`). Совпадение только последних цифр не даёт прав.

Роль возвращается в объекте пользователя при регистрации, входе и запросе
профиля. Через регистрацию и редактирование профиля изменить роль нельзя.
Для редкого изменения состава администраторов добавляйте отдельную миграцию
данных с точными номерами или идентификаторами пользователей.

Администраторы видят все слова в словаре независимо от грейда и могут открыть
отдельный редактор нажатием на строку. Редактор меняет основные значения и
дополнительные варианты перевода существующей записи; грейд и связи с
упражнениями сохраняются. API редактирования доступен только администраторам.
Синхронизация словаря поддерживает `latestUpdatedAt` / `updatedAfter`, чтобы
устройства получали не только новые, но и исправленные слова.
После административной правки версия словаря автоматически увеличивается для
пользователей, которым доступен грейд изменённого слова, и для администраторов.
Изменение версии запускает полную замену локального словаря. При изменении грейда
слова через сохранение модели `Word` наблюдатель `WordObserver` увеличивает версии прежнего и нового
грейдов, чтобы слово удалилось из словаря тех, кому оно больше не доступно,
и появилось у тех, кому оно стало доступно.
Массовые SQL-правки обходят события моделей: при таких правках увеличивайте
версии затронутых грейдов через `DictionaryRevisionService::bumpForGrades`
в той же транзакции.

## Production

Требования к production-конфигурации, порядок релиза, резервного копирования и
отката описаны в [DEPLOY.md](DEPLOY.md).
