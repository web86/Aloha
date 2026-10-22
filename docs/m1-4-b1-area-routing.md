# M1.4-B1 — публичные маршруты Area

22.10.2026. **Aloha Content 0.2.0**, Aloha Hello 0.2.2, локальный WordPress 7.1.3 / PHP 8.3. Объём: только маршруты Area и их защита. Используется существующий `index.html`; готовность frontend-дизайна района не заявляется.

## Реализация

Плагин регистрирует `area` с `publicly_queryable=true`, `query_var=area`, `rewrite.slug=big-island`, `with_front=false`, `has_archive=false`, без feeds/pages endpoints. Pretty permalink формируется **Core из `post_name`**, без собственного `post_type_link` filter:

- `/big-island/mauna-lani/`
- `/big-island/waikoloa-beach-resort/`
- `/big-island/waikoloa-village/`

Районы не создаются автоматически, отдельные Pages для них не используются. В основной базе эти опубликованные районы не создавались, поэтому перечисленные адреса до наполнения могут отдавать 404. Существующий auto-draft Area сохранён; тестовые данные находятся в отдельной установке на 8091.

`community` остаётся backend-only: модель, `aloha_area_id`, права, revisions и серверные проверки M1.4-A сохранены. Публичные маршруты комплексов не добавлялись. Тема и Navigation posts этим этапом не изменяются.

Файлы:

| Файл | Изменение |
| --- | --- |
| [aloha-content.php](/Users/sonoma/Documents/ALOHA/plugins/aloha-content/aloha-content.php) | Публичная регистрация только Area; именованная функция регистрации; activation/deactivation lifecycle |
| [inc/area-routing.php](/Users/sonoma/Documents/ALOHA/plugins/aloha-content/inc/area-routing.php) | База, reserved slugs, ограничение правил, signature/soft flush, защита от ошибочных 404 redirects |
| [inc/integrity.php](/Users/sonoma/Documents/ALOHA/plugins/aloha-content/inc/integrity.php) | Подключение проверки Area slug к существующим PHP/REST guards |
| [aloha-area-routing-check.php](/Users/sonoma/Documents/ALOHA/scripts/aloha-area-routing-check.php) | Изолированные fixtures, проверка порядка rules, slug validation, lifecycle и очистка |
| [aloha-area-http-check.py](/Users/sonoma/Documents/ALOHA/scripts/aloha-area-http-check.py) | HTTP status/queried object/canonical/отрицательные сценарии и смена базы |
| [aloha-content-editor.cjs](/Users/sonoma/Documents/ALOHA/scripts/aloha-content-editor.cjs) | Дополнительные Gutenberg permalink, draft preview и private Area checks |
| [test-aloha-content.sh](/Users/sonoma/Documents/ALOHA/scripts/test-aloha-content.sh) | Существующий disposable runner расширен маршрутными фазами |

## Почему нужен filter правил

Core продолжает создавать attachment, embed и числовые single-pagination rules даже при `rewrite.feeds=false` / `pages=false`. Они способны занять `/big-island/{area}/{community}/`. Поэтому `area_rewrite_rules` оставляет **одно правило глубиной в один slug**, с обязательным концом пути. Оно направляет запрос в стандартный `area` query var; отдельного resolver или временного Community handler нет.

В том же правиле negative lookahead исключает reserved slugs. В результате `/big-island/guide/` и `/big-island/communities/` вообще не выбирают Area matcher и доходят до существующих Core Page rules. Это более узкое решение, чем дополнительные Page-routing handlers из предварительного аудита: оно также пропускает обычные дочерние Pages внутри Guide. Правильный queried object проверен по HTTP, а не только по совпадению HTML.

Зарезервированы `guide`, `communities`, `feed`, `page`, `embed`, `attachment`, `trackback`, фактические feed names (`rdf`, `rss`, `rss2`, `atom`), Core pagination/comments-pagination bases и зарегистрированные permalink endpoints. Чисто числовые slug и `comment-page-N` также не используются районами. Список сортируется для стабильной конфигурационной signature.

Проверка происходит до сохранения через существующие guards: REST получает `aloha_reserved_area_slug` / HTTP 400; PHP/WP-CLI не сохраняют недопустимый slug и выводят причину. Это действует и для явного draft slug, и при публикации с автоматически сформированным именем. Дополнительно подключён штатный `wp_unique_post_slug_is_bad_flat_slug`. Название района может изменяться независимо от уже сохранённого допустимого slug.

Core endpoints **существующей родительской Page** не отключаются: например, `/big-island/feed/` может возвращать её штатный feed, `/big-island/embed/` — embed, числовой путь — native pagination/canonical redirect. Требование к ним — не интерпретироваться как Area, а не обязательно отвечать 404.

API основания: [CPT registration](https://developer.wordpress.org/reference/functions/register_post_type/), [permastruct rules filter](https://developer.wordpress.org/reference/hooks/permastructname_rewrite_rules/), [Core slug filter](https://developer.wordpress.org/reference/hooks/wp_unique_post_slug_is_bad_flat_slug/).

## Canonical, доступ и будущие маршруты

Slashless permalink опубликованного Area получает штатный 301 на вариант со слешем; `rel=canonical` указывает на `get_permalink()`. В Gutenberg доступен тот же permalink. Draft/private Area не доступны анонимно; authenticated preview и чтение private Area используют Core permissions, без собственного preview route.

Стандартный alias `?area=mauna-lani` в проверенной версии Core возвращает 200 с правильным pretty canonical, а не обязательный 301. Дополнительная система редиректов для query aliases не вводилась.

Для неизвестных адресов внутри текущей базы отключено Core 404 permalink guessing: неизвестный район или будущий вложенный путь не должен случайно перенаправляться на похожую запись. Автоматический old-slug redirect для Area в этой namespace также не используется. Обычная canonical-нормализация существующих объектов сохраняется.

`/big-island/{area}/{community}/`, включая числовой последний сегмент и attachment-подобные подпути, не захватывается Area. Без отдельного маршрута или существующей Page такой адрес сейчас отдаёт 404. Guide articles/categories, Community routes и специальные 301 map не реализованы.

Пространство первого уровня под базой предназначено для Area, кроме зарезервированных секций. Обычные Pages вне этой namespace не затрагиваются. Для новой дополнительной Page-секции внутри неё нужно заранее зарезервировать её slug через PHP-конфигурацию; один и тот же адрес не должен одновременно принадлежать Page и Area. Универсальная система определения владельца произвольного URL не создавалась.

## Конфигурация и lifecycle

База задаётся небольшим PHP filter, например в site-specific коде:

```php
add_filter( 'aloha_content_area_base', static fn() => 'big-island' );
```

Дополнительные section slugs можно добавить, сохраняя обязательные значения:

```php
add_filter( 'aloha_content_area_reserved_slugs', static function ( $slugs ) {
    $slugs[] = 'future-section';
    return $slugs;
} );
```

Это конфигурация разработчика, стабильная между запросами, без Settings UI. Значения базового пути нормализуются как slug segments. При изменении базы новые permalink/rules начинают действовать; старые URL автоматически не мигрируют и не получают собственные 301.

При активации типы регистрируются до **одного soft flush**. Для уже активного плагина upgrade/config change определяется signature (`schema`, base, reserved slugs) в option `aloha_content_area_routes` на `wp_loaded`, после регистрации типов/endpoints. При совпадении signature правила не генерируются. Для изменения алгоритма правил в будущем увеличивается `schema`.

При повторном flush в одном PHP request очищаются только сгенерированные Area rules из in-memory `extra_rules_top`, чтобы Core не сохранил старый matcher. Deactivation удаляет Area permastruct/rules и signature; reactivation возвращает их без изменения записей. `.htaccess` и глобальная permalink structure не переписываются. Повторная обычная генерация Core при собственной инвалидированной rewrite cache остаётся штатным поведением WordPress.

Основание: [soft/hard flush API](https://developer.wordpress.org/reference/functions/flush_rewrite_rules/).

## Проверки и ограничения

Запуск существующего стенда:

```bash
./scripts/test-aloha-content.sh
CONTENT_BROWSER_TEST=1 PLAYWRIGHT_MODULE=/path/to/node_modules/playwright \
  ./scripts/test-aloha-content.sh
```

Runner использует отдельные WP/DB volumes, порт 8091, test-only offline helper и test-only HTTP headers queried object/preview/404. Диагностические headers в Aloha Content и основную установку не добавляются. Fixtures — три опубликованных района, draft/private Area, Community, parent/hub/обычная Pages; после проверки они удаляются, затем удаляется временный stack. Evidence сохраняется в `.local/evidence/m1-4-b1/` вне Git.

Точки расширения для M1.4-B4: `post_updated` для сравнения старого/нового Area slug; `area_base()` / `sync_area_routes()` для согласованного изменения конфигурации; отдельная явная redirect map до fallback 404. Сейчас история смены баз не хранится и миграционных 301 нет. Это ограничение этапа, а не готовая redirect policy.

Полный прогон `CONTENT_BROWSER_TEST=1` завершён успешно:

| Набор | Результат |
| --- | --- |
| Регрессии M1.4-A | 88 серверных проверок; связь, права, revisions, ограничения публикации/удаления сохранены |
| Area server/rewrite | 75 проверок, включая reserved slugs в PHP/REST, порядок rules, вложенные пути и activation/config/deactivation lifecycle |
| HTTP | 36 запросов: 31 с основной базой, 3 после смены базы, 2 после восстановления; статусы, queried object, canonical, slashless redirect, Page hubs и отрицательные сценарии |
| Gutenberg / Chromium | 14 сценариев; native permalink, draft preview, private Area, редактор и связь Community; JS page errors отсутствуют |
| Очистка | Все routing/editor fixtures удалены; исходные Pages/posts/Navigation и permalink settings совпадают с baseline, Area/Community fixtures отсутствуют |
| PHP / syntax | Debug log изолированной установки пуст (0 bytes); PHP lint, Bash, JavaScript и Python syntax checks пройдены |

Основная установка на 8088 не использовалась для fixtures. SHA-256 контрольного снимка всех post types/statuses и проверяемых настроек до/после совпадает: `73125fec4a9e719f20864e2e39e17270abf5e78af45e5330dec4166e4390a0f1`. Сохранены Navigation posts, Reading/permalink и shell settings; количество Area — 1 (существовавший auto-draft), Community — 0. Обновление rewrite cache/signature — единственное намеренное изменение маршрутизации уже активного плагина.

Основной frontend проверен: HTTP 200. Основной накопительный `debug.log` не пуст: 11 старых Core/CLI warnings (недоступность WordPress.org update/translation API и прежний CLI `/dev/stdin`), последнее — 22.10.2026 12:41:51 UTC, до финального тестового прогона. Записей Aloha Content и новых ошибок маршрутизации нет; история лога сохранена. Пустой PHP-лог подтверждён именно для чистого изолированного стенда, без стирания основного журнала.

Локальные browser/HTTP отчёты, screenshots и `integration.log` находятся в `.local/evidence/m1-4-b1/`; контрольные снимки основной установки — `.local/m1-4-b1-main-content-{before,after}.json`. Эти файлы игнорируются Git. Схема CPT и маршруты Community не расширялись; новые templates, frontend-вёрстка районов, миграции, push и deployment не выполнялись.

Дополнительный `scripts/check-references.py`: все 20 присутствующих оригиналов совпадают с manifest; полный check завершается ошибкой из-за отсутствующего `aloha-architecture-sources.zip` (известно до B1). Оригиналы не изменялись и архив не восстанавливался искусственно.
