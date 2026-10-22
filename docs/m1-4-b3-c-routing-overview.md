# M1.4-B3-C — Routing Overview

22.10.2026. **Aloha Content 0.6.0**. Небольшой административный экран; существующая маршрутизация 0.5.0 не менялась.

## Доступ и использование

**Tools → Aloha Content: Routing**, заголовок **Aloha Content — Routing Overview**.

Локальный URL: <http://127.0.0.1:8088/wp-admin/tools.php?page=aloha-content-routing>.

Capability `manage_options` проверяется и при регистрации меню через `add_management_page()`, и непосредственно в render callback. Прямой вызов без прав получает 403. Nonce не требуется: экран ничего не сохраняет и не принимает команд. Нет форм, кнопок Save/Reset/Rebuild/Flush, REST endpoints или новых options.

Используются native `.wrap`, headings, `widefat striped` tables, ссылки и стили WordPress admin. Таблица семейств использует responsive List Table с подписанными `data-colname` ячейками; на мобильном все строки раскрыты и читаются вертикально, без дополнительных controls. Небольшой scoped inline style на Core breakpoint **782px** адаптирует flex layout к таблице без checkbox column: название семейства и структура URL получают отдельные строки. Он загружается только на этом экране через `wp_add_inline_style('list-tables')`. Нет собственного JS, UI-фреймворка, отдельного CSS-файла или дополнительных зависимостей. Все значения экранируются через `esc_html()` / `esc_attr()` / `esc_url()`; приватные данные, полные signature hashes, credentials и серверные пути не выводятся.

## Источники данных

| Семейство | Динамическая структура | Источник |
| --- | --- | --- |
| Area | `/{area_base}/{area}/` | CPT `area` |
| Community | `/{area_base}/{area}/{community}/` | CPT `community` + `aloha_area_id` |
| Guide Hub | `/{guide_base}/` | Core Page при наличии |
| Guide Rubric | `/{guide_base}/{rubric}/` | Native Category / WP_Query |
| Guide Article | `/{guide_base}/{rubric}/{article}/` | Post + Guide Category |
| Investor Article | `/{investor_base}/{article}/` | Post + Investor Category |

UI вызывает существующие `area_base()`, `guide_segment()`, `guide_base()`, `investor_base()`, `area_reserved_slugs()`, `guide_terms()`, `guide_rubrics()`, `investor_category()`, `editorial_post_route()` и `area_routes_signature()`. Базы и утверждённые рубрики не продублированы в UI.

Для Area/Community/статей показан permalink существующего опубликованного объекта с допустимой текущей классификацией или связью. Для рубрики — ссылка существующей связанной категории, даже если архив пока пуст. Для Hub — ссылка только опубликованной Page. При отсутствии примера показан шаблон URL, без выдуманного slug. Поиск примера постраничный, по 20 записей; статьи ограничены нужным category tree. Черновики и private записи не используются как публичные примеры.

Отдельные таблицы показывают опубликованные Area/Community counts, четыре настроенные рубрики с фактическими именами/ID, Investor category, наличие и статус Guide/Communities Hub Pages, базы и reserved slugs. Investor category archive отмечен как **намеренно отключённый**. Vacation Rentals помещён в информационный блок **Not implemented**, без активного rental route. Если другой плагин когда-либо зарегистрирует `rental`, экран сообщает только о наличии CPT и прямо указывает, что его маршруты здесь не проверяются.

## Значение статусов

- **Registered**: существует нужный runtime permastruct и все ожидаемые правила этого семейства присутствуют в сохранённой option `rewrite_rules` с правильными query strings. Ожидаемые rules получены через уже существующие family filters, без второй реализации regex. Для Hub проверяются Core Page type и наличие сохранённых Page rules.
- **Content not created**: соответствующей Page пока нет; это не ошибка регистрации. Наличие непубличной Page показывается отдельно. Страницы не создаются.
- **Configuration requires attention**: нет регистрации/сохранённых rules, rules отличаются, категория удалена/не установлена/имеет неправильный slug или parent, либо сохранённая signature не совпадает с ожидаемой.
- **Not implemented**: Aloha Content не предоставляет Rental/GuestyBridge routing.

Регистрация, привязки категорий и контент — отдельные проверки. Например, Guide rewrite rules могут присутствовать, а category binding требовать внимания; оба состояния видны одновременно. Цветных «успешно» для непроведённых проверок нет.

## Граница только для чтения

`routing_overview_data()` читает runtime registration и сохранённые options через `get_option()`. Она не вызывает `WP_Rewrite::wp_rewrite_rules()`, `rewrite_rules()`, `sync_area_routes()`, installer или flush. При пустой option rules выводится проблема, а не запускается их генерация. Существующий общий lifecycle B1–B3-B остаётся без изменений; экран сам его не инициирует. При изменении PHP-конфигурации обычный lifecycle по-прежнему действует на своём штатном hook.

Нет HTTP-запросов к сайту или внешним сервисам, миграций и автоматического исправления категорий. Ожидаемая signature вычисляется существующей функцией и только сравнивается с сохранённой. Её значение не записывается.

Поиск примеров статей не использует native `include_children`: он может лениво записать Core option `category_children` при пустом кеше. Небольшое дерево категорий читается через `get_terms(hierarchical=false)`, затем Post query использует явные term IDs и `include_children=false`. Проверка всего render/data callback с удалённым кешем подтверждает отсутствие SQL-записей, HTTP-вызовов и генерации rewrite rules.

## Изменённые файлы

- `plugins/aloha-content/inc/routing-overview.php` — меню, сбор данных, экранированный HTML.
- `plugins/aloha-content/aloha-content.php` — подключение файла и версия 0.6.0.
- `scripts/aloha-routing-overview-check.php` — целевые server checks; отказ на основной базе.
- `scripts/aloha-routing-overview-browser.cjs` — native admin/browser checks и screenshots; только порт 8091.
- `scripts/test-aloha-content.sh` — lint, запуск новых проверок на прежнем стенде и очистка test user.
- `README.md`, этот отчёт.

Файлы работающих Area/Community/Guide/Investor routes, signature schema, theme CSS/JS/PHP и модель данных не менялись этим заданием.

## Проверки

Финальный полный прогон на изолированном WordPress 7.1.3 / PHP 8.3 и Chromium завершён успешно:

| Набор | Результат |
| --- | --- |
| Routing Overview server | **23 checks**: права, шесть структур URL, изменённые PHP filters, повреждённые/удалённые category bindings, несовпадение signature/rules, отсутствие Hub content, escaping, отсутствие rental и отсутствие записи во все WP tables при inspection |
| Read-only guards | **0 SQL mutations, 0 HTTP attempts, 0 rewrite generations** при сборе данных и render; проверено также при отсутствующем `category_children` и пустой option rules |
| Routing Overview browser | **4 сценария**: native Tools page под администратором, повторный просмотр, mobile 390px без horizontal overflow и с читаемыми названиями строк, native HTTP 403 для Subscriber |
| Регрессии server | M1.4-A **88**, Area **75**, Community **28**, Guide **33**, Investor **38** проверок прошли |
| Регрессии HTTP | Прежние **226** проверок Area/Community/Guide/Investor прошли, включая смену PHP configuration и восстановление |
| Регрессии Gutenberg | Прежние **28** сценариев прошли; всего с новым экраном **32 browser checks**, JS page errors отсутствуют |
| Очистка | Fixtures и test Subscriber удалены; baseline content/terms/Navigation/permalink settings стенда восстановлен. Временные контейнеры, volumes и credentials удалены |
| Логи / syntax | PHP debug log стенда пуст; PHP lint, Bash/JS syntax и `git diff --check` прошли |

Команда: `CONTENT_BROWSER_TEST=1 PLAYWRIGHT_MODULE=/path/to/node_modules/playwright ./scripts/test-aloha-content.sh`. Новые проверки встроены в существующий стенд. Вход браузерного теста направлен сразу на целевой экран: Core Dashboard Quick Draft не создаёт посторонний auto-draft; учтён отложенный autofocus формы login.

Visual QA выполнена по desktop **1440px** и mobile **390px** screenshots. На mobile проверена полная ширина названий семейств и вертикальное расположение полей; на desktop сохранена обычная таблица WordPress admin. Отказ доступа также сохранён в screenshot. Оригинальные frontend макеты не менялись и не относятся к этому административному экрану.

Evidence сохраняется локально в `.local/evidence/m1-4-b3-c/`, игнорируется Git. Основной WordPress на 8088 не используется для fixtures. Сравниваются контрольные суммы основной локальной БД до/после; временные объекты, тестовый Subscriber, контейнеры и volumes существуют только в disposable WordPress на 8091.

В основной установке выполнен read-only render под администратором. Контрольные суммы **posts, postmeta, terms, term_taxonomy, term_relationships и authoritative options** до/после совпали. Из сравнения options исключены только Core transient caches и cron; контент, category bindings, Navigation, shell settings, permalink, saved rules и signature входят в сравнение. Никакого нового контента или конфигурации в основной БД не создано.

Итоговое evidence: `integration.log`, `routing-overview-server-report.json`, `routing-overview-browser-report.json`, `editor-report.json`, HTTP reports, пустой `wp-debug.log`, `main-before.json` / `main-after.json` с контрольными суммами, `main-overview.html`; screenshots — `routing-overview-desktop.png`, `routing-overview-mobile.png`, `routing-overview-denied.png`. Локальные данные и test evidence не добавляются в публичный Git.

## Известные ограничения

- Это просмотр регистрации и конфигурации, без публичных HTTP probes. Проверка наличия rules не доказывает отсутствие shadowing сторонним более ранним правилом, корректный template, работу web server или frontend rendering.
- Экран не является URL Inspector, редактором конфигурации или repair tool. Исправление damaged category binding требует отдельного осознанного действия разработчика; UI ничего не присваивает и не перезаписывает.
- Проверяется текущее состояние без истории URL, 301 map или аудита всех записей. Ссылка-пример не является полной проверкой целостности всего контента.
- Внешние PHP filters могут изменить конфигурацию или поведение Core APIs; проверяется состояние текущего запроса.
- Не созданы Hub Pages, frontend templates, Patterns, новые CPT/taxonomies, GuestyBridge, B4 или M1.5. Push и deployment не выполнялись.
