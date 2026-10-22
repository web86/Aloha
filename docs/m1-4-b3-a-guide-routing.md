# M1.4-B3-A — маршруты Big Island Guide

22.10.2026. **Aloha Content 0.4.0**, WordPress 7.1.3 / PHP 8.3. Используются стандартные `post` и `category`, существующий fallback темы. Investor Guides, GuestyBridge, frontend templates, breadcrumbs и историческая карта 301 не реализованы.

## Категории и установка

Родитель `big-island-guide` и четыре прямых потомка:

| Название | Slug / маршрутная рубрика |
| --- | --- |
| Things to Do | `things-to-do` |
| Food & Drink | `food-and-drink` |
| Top Spots | `top-spots` |
| Events | `events` |

Это обычные категории в **Posts → Categories**. Названия/описания редактируются там же. ID созданного семейства хранятся в одной option `aloha_content_guide_categories`: `parent` и `rubrics[slug]`. Сами статьи используют исключительно штатные taxonomy relationships; primary-category meta или новый CPT не добавлены.

```bash
./bin/local wp aloha-content guide-categories check --user=1
./bin/local wp aloha-content guide-categories install --user=1
```

`1` заменить ID администратора/редактора с capability `manage_categories`. WP-CLI без этой capability не выполняет команду. HTTP endpoint установки и дополнительная панель настроек не создавались. Активация/обновление плагина не создаёт категории автоматически.

`install` сначала проверяет **все** целевые slugs и сохранённые ID/parent bindings. Существующие несвязанные категории с совпавшим slug/name не присваиваются Guide; при конфликте команда останавливается до первой записи. Сохранённые категории с изменившимся slug/родителем не переименовываются и не перемещаются молча. Конфликт требует ручной проверки назначения через Categories и разработчика; автоматического adopt/rename нет.

При повторном запуске существующие ID, названия, описания и связи сохраняются. Недостающие новые категории создаются через `wp_insert_term`; ID сохраняются после каждого успешного шага, чтобы повторить установку после частичной ошибки Core API. `check` только читает данные. Деактивация/обновление/удаление файлов плагина категории и записи не удаляет.

Slug и непосредственная иерархия пяти связанных категорий являются маршрутизационной конфигурацией. Менять названия/описания безопасно; менять slug/parent нужно согласованно с разработчиком. Ошибочная конфигурация не чинится перезаписью пользовательских данных: Guide routes закрываются 404, публикация Guide получает диагностическую ошибку; обычные Posts не затрагиваются.

## URL, исходящие ссылки и правила

- `/big-island/guide/` — обычная иерархическая Page, если она существует. Плагин не создаёт hub Page и не превращает категорию в hub template.
- `/big-island/guide/{rubric}/` — стандартный `WP_Query` с `cat=<term_id>`, реальный queried object `WP_Term/category`. Пустой существующий архив сохраняет стандартное поведение Core.
- `/big-island/guide/{rubric}/page/2/` — обычная архивная пагинация с `paged` и текущим Core pagination base.
- `/big-island/guide/{rubric}/{article}/` — стандартная запись `post`.

В обоих правилах рубрик regex допускает только четыре утверждённых slug. Всего **три ограниченных правила**: archive, archive pagination и article; ни одного индивидуального правила на статью или term ID. Они принадлежат одному native permastruct `aloha_guide`; hub остаётся у Core Pages.

`post_link` изменяет только ссылки однозначных Guide Posts. `get_permalink()` использует текущую категорию и `post_name`; `get_sample_permalink()`/Gutenberg сохраняют штатный `%postname%` placeholder. Plain draft preview остаётся штатным `?p=ID&preview=true`, с Core permissions/nonce. Guide preview получает `noindex`.

Scoped `term_link` обрабатывает только семейство Guide и работает для `get_term_link()` и `get_category_link()`. Четыре рубрики ссылаются на clean archives, родитель — на Guide hub. Если менеджер создал подкатегорию внутри разрешённой рубрики, её статьи наследуют эту рубрику, а ссылка категории ведёт в архив разрешённого предка. Дополнительных публичных subcategory routes нет. Прочие категории внутри Guide без разрешённого предка ведут к hub; публикация статьи только в такой категории требует выбрать разрешённую рубрику. Внутренний `big-island-guide` не появляется в генерируемых публичных ссылках.

Конфигурация разработчика:

```php
add_filter( 'aloha_content_area_base', static fn() => 'big-island' );
add_filter( 'aloha_content_guide_slug', static fn() => 'guide' );
```

`guide_base()` соединяет эти два сегмента; отдельного Settings UI нет. Новый Guide segment добавляется к reserved slugs Area/Community. Не выбирать имя уже занятой секции. Изменение PHP-конфигурации не перемещает существующую Page hub и не создаёт исторические redirects.

## Выбор рубрики и публикация

У Guide статьи должна быть ровно одна рубрика из четырёх. При определении учитываются выбранные категории и их предки внутри **этого конкретного семейства**. Родитель + одна рубрика допустимы; две рубрики или потомки двух разных рубрик неоднозначны. Только родителя недостаточно. Обычные категории вне Guide и tags не меняют маршрут однозначной Guide статьи.

- Draft/pending могут содержать незавершённый выбор.
- REST/Gutenberg блокируют неоднозначный `publish`, `future`, `private` до изменения title/status/taxonomy с `aloha_guide_rubric_required` и понятным сообщением.
- Предложенные REST `categories` передаются в native `post_category` вместе с post fields: исправление двух рубрик на одну и публикация проходят атомарно относительно guards. Core term-assignment permissions остаются обязательными.
- Обычные `wp_insert_post` / `wp_update_post` проверяются существующим low-level guard Aloha Content.
- Для прямого `wp_set_post_categories`/`wp_set_object_terms` у Core нет pre-assignment hook с `WP_Error`. Если такой API делает public/scheduled/private Guide неоднозначным, запись явно возвращается в draft с notice. Это не тихая успешная публикация. Прямой publisher/scheduler также не оставляет неоднозначную запись опубликованной.

Gutenberg использует родную панель **Categories**, без отдельного primary-category поля, ACF или editor plugin. Нативные title/content/excerpt/image/tags не заменяются.

## Входящий запрос и canonical

Scoped resolver читает `$wp->matched_query` только собственного Guide rule. Для article проверяются существование `post`, `publish`, принадлежность Guide и точное совпадение однозначной рубрики. Несовпадение даёт пустой native query через `post__in=[0]`, затем настоящий 404. Правильный slug статьи под другой рубрикой не получает redirect.

Archive использует `cat` и `paged`, с обычным Core include-children поведением. Никакой новый Query engine не добавлен. Category rules не применяются к другим taxonomy terms.

Core 404 guessing внутри текущей базы уже выключен B1; для невалидного Guide rule дополнительно блокируется `redirect_canonical`. Автоматические old-slug redirects Guide не используются. Неизвестные рубрики не получают отдельного catch-all resolver: действительные Core Pages под Guide сохраняют маршруты, остальные неизвестные пути дают 404. Фикстуры B1/B2 с Guide Pages продолжают работать.

Текущие алиасы опубликованных Guide объектов устраняются scoped 301:

| Входящий алиас | Canonical target |
| --- | --- |
| `/{post_name}/`, `?p=ID`, текущий native post query | Вложенный Guide permalink |
| `/category/big-island-guide/{rubric}/`, `?cat=ID` | Clean rubric archive |
| Native Guide subcategory archive | Архив разрешённой рубрики-предка |
| Native parent category archive | Page hub `/big-island/guide/` |
| Native category pagination alias | Соответствующая clean archive page |

Это устранение **текущих Core алиасов**, не база истории slug/переносов. Неверный rubric URL не исправляется в правильный. На корректном article Core выводит canonical; для Guide category archives добавлен один scoped canonical с учётом страницы. Slashless URL получает штатный 301. Обычные Posts/Categories/Pages сохраняют прежние ссылки и запросы. Глобальные `/%postname%/`, `category_base`, `tag_base` не изменяются.

## Общий lifecycle и совместимость

Сохранены `aloha_content_area_routes`, `area_routes_signature()`, `sync_area_routes()` и soft flush B1/B2. Schema повышена до **3**; signature включает Guide base и allowlist рубрик. Стабильный запрос не генерирует rules. Term IDs и категории статьи в signature не входят: создание/редактирование/назначение категорий не требует flush.

Activation регистрирует permastruct до flush; deactivation удаляет Guide permastruct/tags и собственные сгенерированные rules из runtime cache вместе с Area/Community. При смене базы или Guide segment общий sync обновляет rules один раз. Тема, Navigation posts и модель `aloha_area_id` не менялись.

## Файлы и проверки

Основные изменения: `aloha-content.php`, `inc/area-routing.php`, `inc/integrity.php`; новые `inc/guide-categories.php`, `inc/guide-integrity.php`, `inc/guide-routing.php`. Frontend PHP/JS/CSS темы не изменялись.

Тесты: новые `scripts/aloha-guide-check.php` и `scripts/aloha-guide-http-check.py`, расширенные `scripts/test-aloha-content.sh` и `scripts/aloha-content-editor.cjs`. Прежние A/B1/B2 тесты сохранены.

```bash
CONTENT_BROWSER_TEST=1 PLAYWRIGHT_MODULE=/path/to/node_modules/playwright \
  ./scripts/test-aloha-content.sh
```

Стенд использует отдельные WP/DB volumes и порт 8091, test-only offline helper и диагностические HTTP headers. Проверяются statuses, queried term/post, фактические archive post IDs, canonical/redirect targets, отсутствие индексируемых Core aliases, slug/base configuration, публикационные ошибки и native Gutenberg. Ни одна fixture не создаётся на 8088. После browser/server cleanup сравниваются исходные Posts/Pages/categories/tags/Navigation/permalink settings, затем удаляются контейнеры/volumes и временные credentials. Evidence — `.local/evidence/m1-4-b3-a/`, вне Git.

Полный финальный прогон с Chromium завершён успешно:

| Набор | Результат |
| --- | --- |
| M1.4-A | 88 прежних серверных проверок модели, прав, публикации и revisions |
| B1 / B2 | 75 Area и 28 Community server checks (22 + 3 переноса + 3 восстановления), 78 HTTP проверок |
| Guide server | 33 проверки: unmanaged/bound conflicts без перезаписи, идемпотентность, сохранность manager text, native links/sample/preview, tags, обычные Posts, REST/PHP/term API publication guards и lifecycle |
| Guide HTTP | 91 проверка: 59 исходных, 11 при смене Area base, 11 при смене Guide segment, 10 после восстановления. Включены четыре queried categories/posts, archive contents/pagination, canonical, алиасы, неверные рубрики и draft/private/future |
| Gutenberg / Chromium | 23 сценария суммарно, из них 5 новых Guide: native Categories, authorized draft preview/noindex, ошибка двух рубрик, корректный permalink/canonical, повторное открытие и редактирование |
| Логи / syntax | PHP debug log изолированного стенда пуст; JS page errors отсутствуют; PHP lint и Bash/JS/Python syntax checks пройдены |
| Очистка | Guide/B2/B1/editor fixtures удалены, baseline Posts/Pages/terms/Navigation/settings восстановлен; временные контейнеры/volumes/credentials удалены |

### Основная локальная установка

После тестов штатной командой `install --user=1` созданы **только пять утверждённых категорий**; повторный `check` успешен. Это постоянная конфигурация раздела, не demo fixtures. Никаких Guide articles или hub Pages не создавалось, существующим Posts категории не назначались. Было 1 category, стало 6. Четыре **пустых** архива на 8088 возвращают 200 и правильный canonical:

- `/big-island/guide/things-to-do/`
- `/big-island/guide/food-and-drink/`
- `/big-island/guide/top-spots/`
- `/big-island/guide/events/`

Основной frontend также отвечает 200. Три Guide rules активны; два повторных sync дают **0 генераций** rules. Global permalink structure осталась `/%postname%/`.

PHP-лог чистого стенда пуст. Основной накопительный журнал не стирали: 12 Core/CLI warnings, в том числе последний `wp_update_themes()` от 22.10.2026 17:51:14 UTC о недоступности WordPress.org через secure connection. Это отдельная проблема внешних update checks; сообщений Aloha Content в журнале нет.

За время выполнения в основной базе отдельно появился Community auto-draft, которого не было в начальном снимке. Его не удаляли и не считали fixture: все автоматизированные записи тестов выполняются только на 8091. Контрольная сумма **исходных** post records/categories/tags/Navigation/Reading/permalink/shell settings после отдельного учёта этого нового auto-draft и пяти намеренно добавленных Guide categories совпала с начальной: `7fe923175ec55b310cc6896f5d53254ff61f3de11be525a0bc07d145c2f7d89b`.

Снимок непосредственно перед установкой категорий, уже включающий новый auto-draft, также совпал с финальным после исключения только пяти новых terms: `89102d345b4c38a87fd0057380d36c31d61bdc2243be23f701bc1bde90b3e1b2`. Итоговые counts: Posts 2, Pages 2, Navigation 4, Area 1, Community 1; auto-drafts сохранены. Основная база не использовалась для fixtures.

Evidence: `.local/evidence/m1-4-b3-a/` содержит четыре Guide HTTP отчёта, прежние HTTP регрессии, `editor-report.json`, `integration.log`, `wp-debug.log`, main HTTP/log reports и screenshots `guide-editor-permalink.png`, `guide-ambiguous-rubric.png`, `guide-route-fallback.png`. Контрольные снимки основной базы: `.local/m1-4-b3-a-main-before.json`, `main-after-tests.json`, `main-concurrent.json`, `main-final.json` с тем же префиксом. Они игнорируются Git и не публикуются.

Все 20 присутствующих оригиналов совпадают с reference manifest. Исторический `aloha-architecture-sources.zip` по-прежнему отсутствует, как установлено до B1; оригиналы и manifest не изменялись ради успешного check.

## Перед B3-B

- Investor family, его URL и правило несовместимости Guide/Investor ещё не реализованы. Текущая проверка касается только Guide.
- Hub Page и frontend-дизайн не создавались. Публичная ссылка родительской категории ведёт на согласованный hub URL, который до появления Page может отвечать 404.
- Только четыре архивных рубрики, без самостоятельных descendant archives, дополнительных Guide feeds, embed/attachment endpoints и single-post multipage routes.
- Slug и approved hierarchy являются конфигурацией. Удаление/перемещение привязанных категорий требует исправления Categories и успешного `check`; автоматической миграции нет.
- Прямой SQL обходит редакционные hooks. Поддерживаемый путь — стандартный редактор/REST/Core API. Операции категорий над public статьёй через low-level API могут вернуть draft вместо pre-assignment `WP_Error`, как описано выше.
- История смены рубрики, slug и базы не хранится; подтверждённые исторические 301 относятся к B4. При удалении Guide-категорий из статьи её текущий маршрут снова становится обычным Post URL; карта старых адресов не создаётся.
- Slug статьи уникален по обычным правилам Core для `post`, без локальной уникальности внутри рубрик.

Push, deployment, production migrations, B3-B, B4, M1.5 и GuestyBridge не выполнялись.
