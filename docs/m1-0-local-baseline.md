# ALOHA — M1.0 Local WordPress Foundation

Дата: **22.10.2026**, Mac `/Users/sonoma/Documents/ALOHA`. Scope — только локальная foundation. Исходные документы [M0](aloha-hello-architecture-feasibility.md), [design inventory](design-inventory.md) и [план M1](m1-theme-implementation-plan.md) сохранены без изменений.

## Результат и архитектура

WordPress: **http://127.0.0.1:8088/**. Админка: **http://127.0.0.1:8088/wp-admin/**. Два постоянно работающих сервиса Compose: `wordpress` (Apache/PHP) и `db` (MariaDB). Третий сервис `cli` из профиля `tools` запускается одноразово для WP-CLI и удаляется после команды. Собственная тема, страницы дизайна, CPT и GuestyBridge не создавались. Работает стандартная Twenty Twenty-Five 1.5; Akismet/Hello Dolly поставляются в официальном образе и не активированы. Дополнительные plugins не установлены.

Compose project: `aloha-local`. Данные сохраняются в named volumes `aloha-local_database` и `aloha-local_wordpress`; ядро не копируется в Git или рабочие каталоги проекта. Read-only bind mounts проверены для `config/php-local.ini` и CLI-конфигурации. Разработка собственной темы и её bind mount относятся к M1.1.

Единственный опубликованный порт: **`127.0.0.1:8088 → wordpress:80`**. MariaDB слушает внутри Docker network без host mapping. Отдельный bridge `aloha-local_default`, подсеть **`10.203.88.0/24`**, не пересекается с проверенными Docker networks и маршрутами Mac. Нет общей сети с другими проектами. Сеть допускает исходящий TCP; внешние HTTP-запросы WordPress блокируются через `WP_HTTP_BLOCK_EXTERNAL`, что не является сетевым firewall.

## Предварительная проверка и версии

| Компонент | Проверенное значение |
| --- | --- |
| Git на Mac | 2.39.5 (Apple Git-154) |
| Docker client/server | 29.4.0 / 29.4.0, context `orbstack`, daemon доступен |
| Docker Compose | v5.1.2 |
| Host PHP | 8.5.10 CLI, не используется стеком |
| Host Node / npm | v22.23.2 / 10.9.8, не используются для запуска WP |
| WordPress | **7.1.3**, подтверждён WP-CLI |
| PHP внутри WordPress | **8.3.36**, NTS, OPcache |
| Apache | **2.4.69**, Debian |
| MariaDB | **11.8.9-MariaDB-ubu2404** |
| WP-CLI | **2.12.0** |
| Стандартная активная тема | Twenty Twenty-Five **1.5** |

На старте Git и конфигурации окружения в ALOHA отсутствовали; были 3 Markdown-документа, архив исследования, 17 дизайн-референсов и `.DS_Store`. `snippets.php` **отсутствовал уже на старте M1.0**; не создан и не восстановлен из предположений. Это расхождение с историческим описанием в M1 plan зафиксировано без редактирования исходного документа.

Порт 8088 был свободен. До запуска ALOHA активных Compose stacks не обнаружено; имелись остановленные контейнеры/сети других проектов. Поиск имён конфигураций в `/Users/sonoma/Documents` не выявил других WordPress installations; это не аудит всего диска. Другие проекты, containers, volumes и настройки Mac не менялись.

Автоматический address pool Docker оказался исчерпан; вместо очистки чужих сетей в Compose задана проверенная свободная подсеть. Первый вариант с `internal: true` дал healthy containers, но OrbStack не создал работающий HTTP forwarding: браузер получил connection refused. Использован обычный project bridge, после чего mapping и браузерная проверка прошли. Пересоздание сети выполнялось с сохранением обоих volumes.

## Закрепление images

В `compose.yaml` указаны полные версии **и digests**, полученные после загрузки официальных images. `latest` не используется.

| Image tag | SHA-256 digest |
| --- | --- |
| `wordpress:7.1.3-php8.3-apache` | `f3e6835fa7d4a5b786615c24b67ec70816e06ebd517d31276bef423e49dfd126` |
| `mariadb:11.8.9` | `f16d7a344413e519dff518f27bdb221388f84b0d274c97c4fb5f2cb0dc1b34fa` |
| `wordpress:cli-2.12.0-php8.3` | `94017f46151918dc886c2d6b3a072ce7912f9a935eb162566af184722a173a04` |

Выбор основан на проверенных 22.10.2026 [стабильных релизах WordPress](https://wordpress.org/news/category/releases/), [требованиях WordPress](https://wordpress.org/about/requirements/), [официальном WordPress image](https://hub.docker.com/_/wordpress) и [maintenance release MariaDB 11.8.9](https://mariadb.org/mariadb-server-12-3-11-8-11-4-and-10-11-q3-2026-maintenance-releases-and-goodbye-10-6/). Взята стабильная ветка PHP 8.3, соответствующая рекомендованному минимуму WordPress. Проверено на Darwin amd64 / Docker linux amd64; другой Mac/ARM отдельно не тестировался.

Автоматические обновления core и automatic updater отключены; смена pinned images выполняется отдельным изменением с backup. Для hard flush `.htaccess` в WP-CLI задан `apache_modules: [mod_rewrite]` по [официальной инструкции](https://developer.wordpress.org/cli/commands/rewrite/flush/). Структура ссылок — `/%postname%/`.

## Запуск, credentials и данные

На Mac нужны работающий OrbStack/Docker с Compose, Bash, curl и Python 3. Первый запуск:

```bash
cd /Users/sonoma/Documents/ALOHA
./bin/local init
./bin/local up
./bin/local check
```

`init` создаёт `.env` с правами `0600`: отдельные случайные 48-символьные hex-пароли DB user, DB root и WP admin. Значения не совпадают друг с другом, не берутся из production и не выводятся установщиком. Пользователь WP — `aloha_local_admin`, email — `developer@example.test`; пароль находится в `WP_ADMIN_PASSWORD` локального `.env`. Повторный `init` ничего не перезаписывает. Нельзя заменять `.env` при уже существующей базе без отдельной процедуры смены паролей.

| Команда | Поведение |
| --- | --- |
| `./bin/local up` / `start` | Healthchecks DB/PHP/Apache, идемпотентная установка только для пустой базы |
| `./bin/local stop` / `down` | Только остановка, volumes сохраняются |
| `./bin/local status` / `check` | Состояние и базовая HTTP/WP/DB проверка |
| `./bin/local logs` | PHP/Apache/MariaDB logs, последние 100 строк и follow |
| `./bin/local wp-log` | WordPress debug log из persistent volume |
| `./bin/local wp <args>` | Одноразовый WP-CLI под UID/GID 33:33 |
| `./bin/local backup` | SQL + архив WP + копия `.env` в приватной игнорируемой `.local/backups/` |
| `./bin/local recover` | Пересоздаёт только контейнеры ALOHA, сохраняет DB и WP volumes |
| `./bin/local reset` | Только интерактивно, после точного ввода `DELETE ALOHA LOCAL DATA`; удаляет оба local volumes |

Контейнеры не стартуют автоматически после reboot; запустить Docker и `./bin/local up`. Не применять глобальный prune. Команда destructive reset при проверке **не выполнялась**: проверен отказ без интерактивного подтверждения.

`WP_ENVIRONMENT_TYPE=local`, `WP_DEBUG` и debug log включены, отображение ошибок в HTML выключено. PHP пишет стандартные ошибки в stderr, а загруженный WordPress — в `wp-content/debug.log`. WP-Cron отключён; PHP mail направлен в `/bin/true`, письма не доставляются. Индексация отключена через `blog_public=0`; это настройка локального WP, не изменение production.

## Git и неизменность исходников

Локальная ветка — `main`, remote отсутствует; push/deployment не выполнялись. Initial commit содержит только проверенные source/config/docs. Его идентификатор доступен через `git log -1 --oneline` и приводится в итоговом сообщении. Для commit используется разовая техническая identity `Codex <codex@local.invalid>`, глобальная Git identity не меняется.

Отслеживаются:

- `compose.yaml`, `.gitignore`, `.env.example`, `README.md`;
- `bin/local`, `config/php-local.ini`, `config/wp-cli.yml`;
- `scripts/check-references.py`;
- три исходных Markdown-документа без изменений, этот отчёт и `docs/reference-manifest.json`.

Не отслеживаются: `.env`, secrets, `.local/` с приватными backup и browser evidence, Docker data, SQL dumps, core, uploads/cache/logs, node dependencies, временные renders/распаковки, `.DS_Store`, `макеты/` и `aloha-architecture-sources.zip`. Никаких binary originals не удалено. Git-клон сам по себе не содержит игнорируемых референсов: их нужно переносить отдельно по manifest; Git не заменяет резервную копию оригиналов.

[Manifest](reference-manifest.json) хранит относительные пути, размеры и SHA-256 **21 файла**: 3 исходных документа и 18 ZIP/PNG references. `.DS_Store` исключён из долговременного manifest, но также включён в отдельную проверку всех **22 файлов**, существовавших до M1.0. `python3 scripts/check-references.py` проверяет originals без записи в них. Обе проверки прошли.

`git diff --cached --check` обнаружил существующую пустую строку в конце исходного M0 (`aloha-hello-architecture-feasibility.md:969`). Она сохранена согласно запрету изменения originals; проверка новых файлов проходит без whitespace errors.

## Smoke tests

Все проверки выполнялись на свежей изолированной базе ALOHA. Использован временный нейтральный post, без реализации страниц сайта. Browser screenshots и JSON/HTTP evidence сохраняются только в игнорируемой `.local/evidence/`.

| Проверка | Результат / evidence |
| --- | --- |
| WordPress локально | **PASS** — HTTP 200 на `/` и `/wp-login.php`; host mapping только `127.0.0.1:8088` |
| WordPress Admin | **PASS** — реальный Chromium login, dashboard открылся с local admin |
| Gutenberg | **PASS** — UI ввод заголовка и paragraph, Save draft; повторная загрузка редактора сохранила сериализованный `<!-- wp:paragraph -->`; JS errors `[]` |
| Stop/start persistence | **PASS** — post ID 9: ID/title/block content/status/slug совпали после `stop` → `up` |
| Pretty permalinks | **PASS** — временная запись опубликована на `/aloha-m1-0-smoke/`, HTTP 200 с ожидаемым текстом; `.htaccess` hard flush выполнен |
| PHP/WordPress logs | **PASS** — PHP `log_errors=1`, stderr configured; WordPress `error_log()` marker прочитан через `wp-log`; Apache/DB logs доступны |
| Health / critical errors | **PASS** — DB и WP healthy; критических ошибок не обнаружено; ожидаемые warnings описаны ниже |
| Local credentials | **PASS** — свежие случайные значения, права `.env` 0600, единственный local admin, site URLs local; production credentials не использовались |
| Protected originals | **PASS** — SHA-256 всех 22 pre-existing files совпадает; никаких правок исходных docs/ZIP/PNG |
| Git hygiene | **PASS** — проверен явный staged file list, отсутствие core/DB/media/secrets; значения трёх сгенерированных паролей не встречаются в tracked files |
| Backup / recover | **PASS** — SQL содержит schema, gzip/tar читается и содержит WP config, env copy совпадает; `recover` сохранил текст тестовой записи |
| Reset guard / init | **PASS** — неинтерактивный reset отклонён; повторный init сохранил credentials |

После проверок временный post и созданные браузером пустые auto-drafts удалены; стандартные исходные записи WordPress сохранены. Финальное состояние — оба сервиса запущены и healthy.

## Ограничения и M1.1

Ожидаемые нефатальные сообщения: MariaDB под OrbStack использует `libaio` при запрете `io_uring`; Apache сообщает об отсутствии FQDN. Блокировка внешнего WordPress HTTP вызывает warnings проверок обновлений/переводов WordPress.org. Они не помешали admin/editor/REST save и не требуют изменения Mac. Локальный HTTP без TLS, cron/mail и external HTTP ограничения подходят этому этапу; для будущих интеграций нужна отдельная конфигурация и отдельное разрешение.

Backup читаемость проверена; полноценное восстановление повреждённой DB из SQL не репетировалось. `recover` восстанавливает контейнеры, но не исправляет потерянные/повреждённые данные. SQL snapshot согласован для transactional tables; архив файлов вместе с ним не атомарен, поэтому backup делать без параллельного редактирования. Реальный destructive reset намеренно не запускался. Перенос на другой Mac и конфликт подсетей там не проверялись.

**Foundation готова к M1.1**: следующим отдельным заданием создать и смонтировать собственную тему, начать tokens/shell по утверждённому M1 plan. Перед использованием исторических snippets проверить местонахождение отсутствующего `snippets.php`; не заменять его догадками. Незакрытые дизайн-/контент-вопросы остаются в исходном плане. M1.1 здесь не начат. Guesty/API/production alohahello.com/DNS/hosting, системные настройки Mac, remote repository и deployment не затрагивались.
