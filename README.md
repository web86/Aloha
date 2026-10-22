# ALOHA — локальный WordPress и тема, M1.2

Рабочая папка: `/Users/sonoma/Documents/ALOHA`. Нужны работающий Docker Desktop/OrbStack с Compose, Bash, curl и Python 3. Системный PHP и Node для запуска не нужны. Не меняйте Docker context, если в нём работают другие проекты.

Первый запуск на Mac:

```bash
cd /Users/sonoma/Documents/ALOHA
./bin/local init
./bin/local up
./bin/local wp theme activate aloha-hello
./bin/local wp aloha-shell install
./bin/local check
```

Открыть [WordPress](http://127.0.0.1:8088/) или [админку](http://127.0.0.1:8088/wp-admin/). Логин `aloha_local_admin`; уникальный пароль находится в `WP_ADMIN_PASSWORD` файла `.env`. Посмотреть файл можно локально в редакторе. Не отправляйте его и не добавляйте в Git. `init` повторно не перезаписывает credentials. При клонировании `.env` создаётся заново; не подменяйте его при подключении уже существующей базы.

Дальнейшая работа:

| Команда | Действие |
| --- | --- |
| `./bin/local up` | Запуск; первичная установка только при пустой базе |
| `./bin/local stop` | Остановка с сохранением всех данных; `down` — тот же безопасный alias |
| `./bin/local status` | Состояние контейнеров и healthchecks |
| `./bin/local check` | Проверка WP, базы и HTTP |
| `./bin/local logs` | Поток PHP/Apache/DB logs, выход Ctrl+C |
| `./bin/local wp-log` | Последние записи `wp-content/debug.log`, если они появились |
| `./bin/local wp <аргументы>` | WP-CLI в одноразовом контейнере, например `./bin/local wp post list` |
| `./bin/local backup` | SQL, WordPress files и `.env` в приватной `.local/backups/`; выполнять без параллельного редактирования |
| `./bin/local recover` | Пересоздание контейнеров с сохранением существующих volumes |
| `./bin/local reset` | Удаление только volumes ALOHA после ввода `DELETE ALOHA LOCAL DATA` в интерактивном терминале |

После перезагрузки Mac запустите Docker/OrbStack, затем `./bin/local up`. Автозапуск контейнеров выключен. Первое скачивание образов требует интернета; работа сайта после установки происходит в изолированной Docker network. Порт `8088` должен быть свободен. Если занят, остановитесь и проверьте `lsof -nP -iTCP:8088 -sTCP:LISTEN`; не останавливайте чужой сервис. Менять порт нужно согласованно в Compose и существующих WP `home`/`siteurl`.

## Данные и восстановление

Compose project — `aloha-local`, volumes — `aloha-local_database` и `aloha-local_wordpress`. База не публикует порт. Единственный HTTP mapping — `127.0.0.1:8088:80`. Применяется локальный HTTP без TLS. Не используйте для production.

Проект использует отдельный bridge `10.203.88.0/24`: автоматический пул подсетей текущего Docker исчерпан другими проектами. Подсеть проверена на отсутствие конфликтов 22.10.2026. На другом Mac при конфликте выберите свободную подсеть в `compose.yaml`. Внешний HTTP блокируется на уровне WordPress (`WP_HTTP_BLOCK_EXTERNAL`), а не сетевым firewall; Docker bridge допускает исходящие соединения. Сеть `internal: true` в проверенной конфигурации OrbStack не обеспечивает нужный проброс HTTP-порта.

При проблеме: `status` → `logs` → `check`; затем `recover`. Если Docker daemon недоступен, запустите приложение Docker/OrbStack. `recover` устраняет проблемы контейнеров, но не восстанавливает повреждённую базу. Для потери/повреждения данных понадобится приватный backup; импорт SQL или замена volume требуют отдельного решения с проверкой копии. Автоматического разрушительного restore здесь нет. SQL backup согласован в рамках транзакционных таблиц; архив файлов не является атомарным snapshot вместе с SQL. Не используйте `docker volume prune`, `docker system prune` или `docker compose down -v` для обычной остановки.

Ядро, стандартные темы, plugins и uploads находятся в Docker volume. Собственная block theme Aloha Hello хранится в `theme/aloha-hello/` и подключена read-only bind mount к WordPress и CLI. Редактируйте исходники на Mac; копировать файлы в volume не нужно. GuestyBridge ещё не создан. Закреплённые images и запрет автоматических обновлений обеспечивают воспроизводимость; обновление версий выполняется отдельным изменением после backup. WP-Cron, внешние WordPress HTTP-запросы и отправка PHP mail отключены. Проверка внешних integrations в этой среде не предполагается.

Из-за блокировки внешнего HTTP в debug log возможны предупреждения WordPress о недоступности проверок обновлений и переводов WordPress.org. Это ожидаемое ограничение локальной конфигурации. Логи MariaDB могут сообщать о fallback `io_uring` → `libaio`, Apache — об отсутствии FQDN; глобальные настройки Mac ради этих сообщений не меняются.

## Git и референсы

Git содержит конфигурацию, команды, исходники темы с licensed fonts и двумя SVG, local dev showcase, отчёты и исходные Markdown-документы. `.env`, Docker data, backup, core, uploads, logs, dependencies и временные renders исключены. Оригиналы в `макеты/` и `aloha-architecture-sources.zip` остаются на диске и исключены из Git. При переносе скопируйте их отдельно по [manifest](docs/reference-manifest.json), где указаны пути, размеры и SHA-256. Не распаковывайте экспорты поверх оригиналов; используйте игнорируемую `.local/`.

Проверка сохранности существующих документов и референсов:

```bash
python3 scripts/check-references.py
```

Локальная дизайн-система: [showcase](http://127.0.0.1:8088/?aloha-style-showcase=1). Она использует настоящие WordPress blocks/styles, не создаёт Page в базе и отсутствует в публичной навигации. `dev/` монтируется отдельно вне web root; helper включён только для local environment через `ALOHA_DEV_SHOWCASE_BOOTSTRAP`. Удалите константу из Compose и выполните `up`, чтобы отключить showcase. Для production предназначен только каталог темы.

Результаты и ограничения: [отчёт M1.1](docs/m1-1-theme-foundation.md), [baseline M1.0](docs/m1-0-local-baseline.md). Скриншоты и JSON — в игнорируемой `.local/evidence/m1-1/`. Frontend smoke: `PLAYWRIGHT_MODULE=/path/to/node_modules/playwright node scripts/theme-smoke.cjs` при установленном Playwright Chromium; теме Node не нужен. Дальнейший scope: [план M1](docs/m1-theme-implementation-plan.md). M1.2 реализован ниже; M1.3 запускается отдельным заданием.


## Header / Footer / Navigation — M1.2

[Shell preview](http://127.0.0.1:8088/?aloha-shell-preview=1) использует реальные template parts и длинный нейтральный контент; это отдельная local-only noindex-проверка. Предыдущий showcase дизайн-системы сохранён.

Меню: **Appearance → Editor → Navigation** (`Aloha Primary`, `Aloha Footer`, `Aloha Legal`). Primary один для desktop/mobile; Footer один для колонок/disclosures. Контакты: **Settings → Aloha shell**; значения не публикуются без отметки подтверждения. По умолчанию Call и Book Now показывают безопасное demo-пояснение. Не копируйте контакты из макета как подтверждённые.

`./bin/local wp aloha-shell install` идемпотентно создаёт отсутствующие Navigation posts и обновляет pattern cache; существующие меню не перезаписывает. Нужна при обновлении уже активной темы M1.1; на свежей активации меню создаются автоматически. Изменения меню/контактов хранятся в базе WordPress и входят в backup.

[Отчёт M1.2](docs/m1-2-header-footer-navigation.md) содержит URL registry, отличия от PNG и результаты QA. Browser smoke: `PLAYWRIGHT_MODULE=/path/to/node_modules/playwright node scripts/shell-smoke.cjs`; evidence — `.local/evidence/m1-2/`. Следующий отдельный этап — M1.3, автоматически не запускается.
