# Развёртывание English Learning API

Инструкция описывает production-развёртывание на одном Linux-сервере с Docker
Compose и внешним HTTPS reverse proxy. Фронтенд рекомендуется отдавать с того же
домена, а запросы `/api` проксировать в backend.

`compose.yaml` содержит production-сервисы. Development-конфигурация находится
в `compose.yaml.dev`.

## Требования к серверу

- Linux-сервер с Docker Engine и Docker Compose;
- домен, направленный на сервер;
- открытые наружу только порты `80` и `443`;
- reverse proxy с TLS, например Nginx, Caddy или Traefik;
- доступ к репозиторию и отдельный каталог приложения;
- настроенное резервное копирование PostgreSQL.

## Production-конфигурация

### Настройки конкретного сервера

Не изменяйте отслеживаемый `compose.yaml` на сервере и не создавайте там коммиты
с настройками окружения. Используйте `.env` и игнорируемый `compose.override.yaml`.
Docker Compose автоматически объединяет этот файл с `compose.yaml`.

Пример для текущего production-сервера:

```bash
cp compose.override.yaml.example compose.override.yaml
```

Он сохраняет аудио в `./storage/app/dictionary/audio` для `app`, `scheduler` и
`queue` и подключает Firebase-ключ к `queue` только для чтения. Ключ должен
существовать в `./storage/app/private/firebase/service-account.json`;
`GOOGLE_APPLICATION_CREDENTIALS` внутри контейнера остаётся
`/run/secrets/firebase-service-account.json`. Файл ключа не хранится в Git.
Скрипт публикации проверяет наличие ключа до сборки и выводит понятную ошибку,
не позволяя Docker создать на его месте каталог.
Не удаляйте существующие аудиофайлы и Docker volumes при переходе на override.

Если `compose.yaml` уже изменён, сначала сохраните его резервную копию и сравните
настройки с примером. После переноса восстановите отслеживаемый файл из Git.

Создайте `.env` из `.env.example` непосредственно на сервере:

```bash
cp .env.example .env
chmod 600 .env
```

Минимально измените следующие значения:do

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
APP_PORT=127.0.0.1:8088
XDEBUG_MODE=off
AUTH_PIN_PEPPER=replace-with-a-long-random-secret

LOG_LEVEL=warning

DB_DATABASE=en_learning
DB_USERNAME=en_learning
DB_PASSWORD=replace-with-a-long-random-password

PGADMIN_EMAIL=replace@example.com
PGADMIN_PASSWORD=replace-with-a-long-random-password

SESSION_SECURE_COOKIE=true
```

`APP_KEY` не копируйте между независимыми окружениями и не храните в Git. При
первом развёртывании создайте его командой:

```bash
docker compose run --rm app php artisan key:generate
```

Значения `APP_KEY`, `AUTH_PIN_PEPPER`, пароли базы и сторонних сервисов должны
храниться в секретах CI/CD или в защищённом `.env`.

### Что изменить относительно development Compose

Production-конфигурация должна:

- собирать неизменяемый образ приложения из конкретного commit/tag;
- устанавливать Composer-зависимости с
  `--no-dev --prefer-dist --optimize-autoloader`;
- не примонтировать весь репозиторий в `app` и `nginx`;
- устанавливать `XDEBUG_MODE=off`;
- не публиковать PostgreSQL наружу;
- не запускать pgAdmin либо разрешать к нему доступ только через VPN/SSH;
- хранить данные PostgreSQL в постоянном volume;
- запускать отдельный worker для очереди;
- задавать restart policy и healthcheck;
- передавать секреты во время развёртывания, а не в образ.

До появления отдельного `compose.prod.yaml` текущий `compose.yaml` можно
использовать только на закрытом сервере как временный вариант. Ограничьте
`8088`, `5432` и `5050` локальным firewall и не допускайте прямого доступа из
интернета.

## Первое развёртывание

Получите выбранную версию приложения:

```bash
git clone <repository-url> en_learning_back
cd en_learning_back
git checkout <release-tag-or-commit>
```

Создайте production `.env`, затем соберите и запустите сервисы:

```bash
docker compose build --pull app
docker compose up -d
docker compose exec app composer install --no-dev --prefer-dist --optimize-autoloader
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose ps
```

Проверьте backend через опубликованный локальный порт:

```bash
curl --fail http://127.0.0.1:8088/up
```

После этого настройте reverse proxy:

- завершение TLS на `443`;
- перенаправление HTTP на HTTPS;
- проксирование `/api`, `/up` и при необходимости остальных backend-маршрутов
  на `http://127.0.0.1:8088`;
- передачу заголовков `Host`, `X-Forwarded-For` и `X-Forwarded-Proto`;
- разумные ограничения размера запроса и времени ожидания.

Сертификаты выпускайте и обновляйте автоматически, например через Let's
Encrypt. Проверяйте `/up` через публичный HTTPS-адрес.

## Очередь

В приложении используется `QUEUE_CONNECTION=database`. В production должен
постоянно работать worker:

```bash
docker compose exec app php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

В штатной схеме запускайте эту команду отдельным Compose-сервисом с той же
версией образа и автоматическим перезапуском. После каждого релиза выполните:

```bash
docker compose exec app php artisan queue:restart
```

## Скрипт публикации

Скрипт `scripts/publish-backend.sh` обновляет `main` через fast-forward, собирает
и запускает сервисы, устанавливает production-зависимости, очищает кеши, выполняет
`php artisan migrate --force`, обновляет кеши Laravel и перезапускает очередь.
Выполненные миграции повторно не запускаются. Скрипт останавливается при ошибке,
включая ошибку миграции, и не сообщает об успешной публикации.

Публикация также останавливается до сборки, если есть неизвестные изменения
отслеживаемых файлов или локальные коммиты, отсутствующие в `origin/main`.
Серверные настройки хранятся в `.env` и `compose.override.yaml`. Для старой
установки, где изменён только `compose.yaml`, скрипт распознаёт две известные
правки: bind-папку аудио и bind-файл Firebase-ключа. После временного удаления
только этих правок файл должен побайтово совпасть с tracked-версией. Тогда скрипт
автоматически создаёт резервную копию исходного файла в
`storage/app/private/deployment-backups`, устанавливает игнорируемый
`compose.override.yaml` и продолжает публикацию. Неизвестные изменения никогда
не перезаписываются автоматически. Скрипт не выполняет merge с конфликтами,
откаты миграций или удаление volumes.

Скрипт публикации на сервере хранится отдельно от проекта. Содержимое
`scripts/publish-backend.sh` используется как шаблон для этого внешнего файла.
По умолчанию он переходит в `/var/www/docker/en_learning_back` независимо от
своего расположения. После успешного fast-forward скрипт атомарно обновляет
свою внешнюю копию из репозиторного шаблона, поэтому последующие исправления
публикации подхватываются автоматически. Не заменяйте внешний скрипт ссылкой на
файл в репозитории.

Например, если внешний скрипт находится в `/root/publish-backend.sh`:

```bash
bash /root/publish-backend.sh
```

Для другого каталога проекта передайте путь первым аргументом:

```bash
bash /root/publish-backend.sh /path/to/en_learning_back
```

Перед обновлением сохраняйте резервную копию базы.

## Обновление версии

Данные актуальной Android-версии backend получает автоматически из
`update-manifest.json`, приложенного к GitHub Release frontend-приложения.
Параметры конкретной версии APK в production `.env` не добавляются. По умолчанию
используются репозиторий `garikgsi/en_learning_tg_app` и канал `prerelease`.
Полученный манифест кэшируется, а при временной недоступности GitHub используется
последняя успешно проверенная версия. Время хранения свежего результата можно
изменить через `APP_UPDATE_CACHE_TTL_SECONDS`; значение по умолчанию — 900 секунд.

Перед релизом сделайте резервную копию базы. Затем:

```bash
git fetch --tags
git checkout <release-tag-or-commit>
docker compose build --pull app
docker compose up -d
docker compose exec app composer install --no-dev --prefer-dist --optimize-autoloader
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose exec app php artisan queue:restart
curl --fail http://127.0.0.1:8088/up
```

Не запускайте `migrate:fresh` и не удаляйте Docker volumes на production.
Миграции должны быть обратно совместимы на время обновления контейнеров.

## Резервное копирование

Создавайте регулярный дамп PostgreSQL и храните копии вне сервера приложения:

```bash
docker compose exec -T postgres pg_dump -U en_learning -d en_learning -Fc > en_learning.dump
```

Также сохраняйте production `.env` в защищённом хранилище. Периодически
проверяйте восстановление дампа на отдельной базе — наличие файла ещё не
гарантирует рабочий backup.

## Откат

1. Переключитесь на предыдущий release tag или образ.
2. Пересоберите и перезапустите сервисы.
3. Выполните `php artisan optimize`.
4. Проверьте `/up` и основные API-сценарии.

Не откатывайте миграции автоматически. Если новый релиз изменил схему
несовместимым образом, используйте заранее подготовленную обратную миграцию или
восстановление проверенного дампа.

## Проверка после релиза

- публичный адрес использует HTTPS;
- `APP_DEBUG=false`, `XDEBUG_MODE=off`;
- `/up` возвращает успешный ответ;
- регистрация, вход и обновление токена работают;
- worker очереди запущен;
- PostgreSQL и pgAdmin недоступны из интернета;
- логи не содержат исключений и секретов;
- резервное копирование выполняется по расписанию.
