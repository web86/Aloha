# ALOHA — локальный WordPress, тема и Aloha Content, M1.4-B3-C

Рабочая папка: `/Users/sonoma/Documents/ALOHA`. Нужны работающий Docker Desktop/OrbStack с Compose, Bash, curl и Python 3. Системный PHP и Node для запуска не нужны. Не меняйте Docker context, если в нём работают другие проекты.

Первый запуск на Mac:

```bash
cd /Users/sonoma/Documents/ALOHA
./bin/local init
./bin/local up
./bin/local wp theme activate aloha-hello
./bin/local wp plugin activate aloha-content
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

Локальная дизайн-система: [showcase](http://127.0.0.1:8088/?aloha-style-showcase=1). Она использует настоящие WordPress blocks/styles, не создаёт Page в базе и отсутствует в публичной навигации. `dev/` монтируется отдельно вне web root; helper включён только для local environment через `ALOHA_DEV_SHOWCASE_BOOTSTRAP`. Удалите константу из Compose и выполните `up`, чтобы отключить showcase. Дистрибутивы проекта — тема и отдельный Aloha Content; dev tooling в них не входит.

Результаты и ограничения: [отчёт M1.1](docs/m1-1-theme-foundation.md), [baseline M1.0](docs/m1-0-local-baseline.md). Скриншоты и JSON — в игнорируемой `.local/evidence/m1-1/`. Frontend smoke: `PLAYWRIGHT_MODULE=/path/to/node_modules/playwright node scripts/theme-smoke.cjs` при установленном Playwright Chromium; теме Node не нужен. Дальнейший scope: [план M1](docs/m1-theme-implementation-plan.md). M1.2 реализован ниже; M1.3 запускается отдельным заданием.


## Header / Footer / Navigation — M1.2

[Shell preview](http://127.0.0.1:8088/?aloha-shell-preview=1) использует реальные template parts и длинный нейтральный контент; это отдельная local-only noindex-проверка. Предыдущий showcase дизайн-системы сохранён.

Меню: **Appearance → Editor → Navigation** (`Aloha Primary`, `Aloha Footer`, `Aloha Legal`). Primary один для desktop/mobile; Footer один для колонок/disclosures. Контакты: **Settings → Aloha shell**; значения не публикуются без отметки подтверждения. По умолчанию Call и Book Now показывают безопасное demo-пояснение. Не копируйте контакты из макета как подтверждённые.

`./bin/local wp aloha-shell install` идемпотентно создаёт отсутствующие Navigation posts и обновляет pattern cache; существующие меню не перезаписывает. Нужна при обновлении уже активной темы M1.1; на свежей активации меню создаются автоматически. Изменения меню/контактов хранятся в базе WordPress и входят в backup.

[Отчёт M1.2](docs/m1-2-header-footer-navigation.md) содержит URL registry, отличия от PNG и результаты QA. Browser smoke: `PLAYWRIGHT_MODULE=/path/to/node_modules/playwright node scripts/shell-smoke.cjs`; evidence — `.local/evidence/m1-2/`. Следующий отдельный этап — M1.3, автоматически не запускается.


## Desktop Mega Menu — M1.2.1

Desktop от 1280px: hover mega menu без видимых стрелок, обычный переход по родительской ссылке, ArrowDown для раскрытия с клавиатуры. Touch на любой ширине: первое касание раскрывает, второе переходит. До 1279px сохранён Core overlay с компактными disclosures.

**Settings → Aloha shell → Mega Menu**: heading, description, Media Library image и CTA Label. Все URL/названия/порядок — исключительно **Site Editor → Navigation → Aloha Primary**. Связь сохраняется через классы `aloha-mega-rentals`, `aloha-mega-island`, `aloha-mega-homeowners`, `aloha-mega-about` в Advanced родительских Submenu blocks. Не удаляйте эти классы при редактировании.

На обновлённой активной теме выполнить `./bin/local wp aloha-shell install`: однократная безопасная миграция добавит классы исходным родителям и отсутствующий Step-Free Stays, сохранив редакторские изменения. Повторные install не перезаписывают меню/настройки.

[Полная архитектура, инструкция и QA M1.2.1](docs/m1-2-1-mega-menu.md). Актуальный frontend check: `PLAYWRIGHT_MODULE=/path/to/node_modules/playwright node scripts/mega-menu-smoke.cjs`; evidence — `.local/evidence/m1-2-1/`. M1.3 не начинался и автоматически не запускается.


## Mega Menu fixes & Dynamic Settings — M1.2.2

Список Mega Menu settings теперь автоматически следует текущим верхним Submenu blocks в Aloha Primary. Новые разделы получают устойчивый UUID при редактировании/сохранении; названия, URL и порядок редактируются только в Site Editor. Копии получают отдельные IDs. Существующие четыре ключа и пользовательские данные сохранены; удаление пункта не удаляет его editorial content.

В Settings → Aloha shell → Mega Menu добавлен **Show image on the left** (по умолчанию выключен). Исправлены hover boundaries/timers, Firefox visibility/focus race и начальное раскрытие до JS; no-JS fallback выделен отдельно. Граница светлая, дочерние desktop links получили декоративные стрелки. Полный [отчёт M1.2.2](docs/m1-2-2-mega-menu-fixes.md) содержит инструкции, подтверждённые причины, результаты Chromium/Firefox/initial-frame QA и ограничения. Он заменяет прежние инструкции M1.2.1 о ручном назначении классов новым разделам.

Evidence — `.local/evidence/m1-2-2/`, вне Git. Тема 0.2.2; M1.3 и GuestyBridge не начинались.

## Editorial Areas / Communities — M1.4-A

Плагин `plugins/aloha-content` монтируется в WordPress/CLI отдельно от темы. После обновления выполнить `./bin/local up` и `./bin/local wp plugin activate aloha-content`. В админке доступны **Areas** и **Communities**, обычный Gutenberg, excerpt, featured image и revisions. В Community → **Area** выбирается район по названию. Черновик допускает пустую связь; публикация требует опубликованный Area. Используемый Area защищён от удаления, а опубликованные/запланированные Community — от потери опубликованного района.

Публичные маршруты CPT отключены до M1.4-B. Rental, GuestyBridge, starter patterns и новые page templates не создаются. Дистрибутив Aloha Content хранится отдельно от дистрибутива темы; `dev/` и тестовые fixtures не входят ни в один из них.

Технические детали, инструкция редактора и результаты проверок: [M1.4-A Aloha Content](docs/m1-4-a-aloha-content.md). Автотесты: `./scripts/test-aloha-content.sh`; с уже установленным Playwright Chromium: `CONTENT_BROWSER_TEST=1 PLAYWRIGHT_MODULE=/path/to/node_modules/playwright ./scripts/test-aloha-content.sh`. Используется отдельная временная WP/DB на порту **8091**, затем fixtures/контейнеры/volumes удаляются. Главная локальная БД на 8088 не используется для fixtures.

## Area routing — M1.4-B1

Aloha Content **0.2.0** включает только публичные singles Area: `/big-island/{post_name}/`, без archive и без отдельных Pages районов. Guide/Communities и Core service slugs исключены из Area matcher; существующие Pages продолжают использовать Core Page rules. Community остаётся backend-only, связь и проверки M1.4-A сохранены. Полная [документация B1](docs/m1-4-b1-area-routing.md) описывает PHP-конфигурацию базы, one-time soft flush, canonical/preview и ограничения до B4.

Обновление уже активного плагина применяет rewrite signature один раз; сохранять Permalinks или переустанавливать меню не требуется. Реальные районы/Guide/Communities Pages этим этапом не создаются. Маршрутные и регрессионные tests входят в прежний runner; evidence теперь в `.local/evidence/m1-4-b1/`. M1.4-B2/B3, M1.5 и GuestyBridge не запускались; push/deployment не выполнялись.

## Community routing — M1.4-B2

Aloha Content **0.3.0** добавляет `/big-island/{area-slug}/{community-slug}/` через единственное вложенное правило и native `post_type_link`. Area берётся из `aloha_area_id`; смена связи меняет permalink без flush и без сохранённых URL. Несуществующие/неопубликованные/неверно связанные объекты дают 404; чужой или прежний Area не вызывает автоматический redirect. Reserved секции B1 и будущие Guide routes исключены из matcher. Archive Community нет.

Серверные, HTTP и Gutenberg проверки расширяют прежний runner, evidence — `.local/evidence/m1-4-b2/`. Архитектура, результаты и ограничения: [M1.4-B2 Community routing](docs/m1-4-b2-community-routing.md). Основная база не используется для fixtures; B3/B4, M1.5, GuestyBridge, push и deployment не выполняются.

## Big Island Guide — M1.4-B3-A

Aloha Content **0.4.0** использует стандартные Posts/Categories для `/big-island/guide/{rubric}/` и `/big-island/guide/{rubric}/{post_name}/`. Четыре approved рубрики, native архивная пагинация, scoped `post_link`/`term_link`, canonical и устранение текущих Core aliases. Неоднозначная публикация Guide блокируется; native Gutenberg Categories/preview и обычные Posts сохраняются. Global permalink structure не меняется.

Идемпотентная установка/проверка категорий: `./bin/local wp aloha-content guide-categories install --user=1` / `check --user=1` (указать пользователя с `manage_categories`). Конфликтующие существующие категории не присваиваются и не перезаписываются. Hub Pages и демо-статьи не создаются. [Отчёт B3-A](docs/m1-4-b3-a-guide-routing.md), evidence `.local/evidence/m1-4-b3-a/`. Investor Guides/B3-B, B4, frontend templates и GuestyBridge не реализованы; push/deployment не выполняются.

## Investor Guides — M1.4-B3-B

Aloha Content **0.5.0** добавляет `/homeowners/invest/guides/{post_name}/` для стандартных Posts в независимой категории `investor-guides`. Переиспользованы category installer, `post_link`, публикационные guards, canonical и rewrite lifecycle B3-A. Смешанная Guide/Investor публикация блокируется; ordinary Posts сохраняют URL. Investor category archive закрыт 404 и исключён из Core taxonomy sitemap; hub Page не создаётся.

Установка/проверка: `./bin/local wp aloha-content investor-category install --user=1` / `check --user=1`, с capability `manage_categories`. База — PHP filter `aloha_content_investor_base`. [Отчёт B3-B](docs/m1-4-b3-b-investor-routing.md), evidence `.local/evidence/m1-4-b3-b/`. Демо-статей, дополнительных Pages, frontend templates/Patterns, B3-C/B4/M1.5/GuestyBridge и push/deployment нет.

## Routing Overview — M1.4-B3-C

Aloha Content **0.6.0** добавляет **Tools → Aloha Content: Routing**, доступный с `manage_options`. Экран только читает фактическую конфигурацию, permastructs, сохранённые rules, category bindings и состояние контента. Отсутствующий Hub Page отличается от ошибки конфигурации. Нет HTTP probes, кнопок настройки, flush или вызовов lifecycle. Работающая маршрутизация B1–B3-B не изменена.

[Открыть локальный Routing Overview](http://127.0.0.1:8088/wp-admin/tools.php?page=aloha-content-routing). [Отчёт B3-C](docs/m1-4-b3-c-routing-overview.md); screenshots и test reports — `.local/evidence/m1-4-b3-c/`. Новые проверки включены в прежний `scripts/test-aloha-content.sh`, включая опциональный Chromium запуск.

## Area presentation — M1.5-A

Aloha Hello **0.3.0** добавляет нативный `templates/single-area.html`, однократный Gutenberg starter `area-start` и восемь редакционных Patterns. Hero использует поля текущей Area, Communities выбираются по `aloha_area_id`. Rentals остаётся пустым слотом до будущего GuestyBridge; Aloha Content **0.6.0** и маршрутизация не изменены.

[Локальная Mauna Lani](http://127.0.0.1:8088/big-island/mauna-lani/) сохранена как редактируемая Area; reference media и неподтверждённый copy явно помечены. [Отчёт, тесты и visual QA](docs/m1-5-a-area-mauna-lani.md). Дополнительный прогон: `CONTENT_AREA_TEST=1 CONTENT_BROWSER_TEST=1 ./scripts/test-aloha-content.sh` (нужны локальные исходные JPG и Playwright). Изменения находятся в отдельной ветке, push/deployment M1.5-A не выполнялись.
