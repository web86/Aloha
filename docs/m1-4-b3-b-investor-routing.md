# M1.4-B3-B — Investor Guides

22.10.2026. **Aloha Content 0.5.0**, WordPress 7.1.3 / PHP 8.3. Стандартные Posts/Categories; frontend использует существующий fallback темы. URL сверён с разделом *Pages, URLs and boards* исходного `00 Site structure — start here.pdf`: `/homeowners/invest/guides/[article]/`. Самостоятельный индекс `/guides/` там не утверждён.

## Категория и управление

Добавлена одна независимая корневая категория **Investor Guides**, slug `investor-guides`. Привязка — один стабильный integer term ID в option `aloha_content_investor_category`. Назначения статей — штатные category relationships, без post meta для семейства, primary category или сохранённых URL.

```bash
./bin/local wp aloha-content investor-category install --user=1
./bin/local wp aloha-content investor-category check --user=1
```

`1` — ID пользователя с `manage_categories`; при необходимости заменить своим. Команда без этой capability отказывает. Активация плагина не создаёт категории автоматически.

Инфраструктура B3-A **переиспользована**, а не скопирована: общая `editorial_categories()` выполняет preflight всех slug/ID/parent bindings, проверяет конфликты name и создаёт только отсутствующие новые термины через Core. Старый формат option Guide (`parent`/`rubrics`) сохранён без миграции. Обе команды `guide-categories` и `investor-category` используют один CLI handler с одинаковыми capability/error checks.

Неуправляемая категория с совпавшим slug не присваивается автоматически. Изменившийся slug/parent сохранённого ID не исправляется перезаписью; команда возвращает ошибку для ручной проверки. Названия и описания менеджера сохраняются. Повторный install не создаёт дубликатов и не вызывает flush. После частичной ошибки Core API уже созданные ID позволяют безопасно продолжить установку.

Менеджер работает в **Posts → Categories** и в родной панели **Categories** редактора. Название/описание менять можно; фиксированный slug и независимый корневой parent являются маршрутизационной конфигурацией. Дополнительная settings panel, CPT, taxonomy или таблица не создавались.

## Два семейства статей

Небольшая `editorial_post_route()` объединяет только две существующие классификации:

| Текущая принадлежность | Семейство / URL |
| --- | --- |
| Одна разрешённая рубрика Guide, без Investor | `/big-island/guide/{rubric}/{post_name}/` |
| Investor Guides, без Guide | `/homeowners/invest/guides/{post_name}/` |
| Ни одного семейства | Прежний стандартный Post permalink |
| Guide + Investor | Ошибка `aloha_article_family_conflict` |

При проверке Guide сохранены четыре разрешённые рубрики и все ограничения B3-A. Категории-потомки наследуют свою утверждённую семью; назначение через потомка другой семьи также считается конфликтом. Tags не участвуют в классификации. Это не универсальные Content Template Rules и не настраиваемая primary-category система.

Для draft/pending незавершённый выбор разрешён. Для `publish`/`future`/`private` общие guards B3-A расширены проверкой семейства:

- REST/Gutenberg получают понятный `WP_Error` до изменения post fields/taxonomy. Proposed `categories` сохраняются через native `post_category` вместе с публикацией, после Core term-assignment permission checks.
- PHP `wp_insert_post`/`wp_update_post` используют прежний pre-write guard.
- Прямой publisher/scheduler не оставляет смешанную статью опубликованной.
- У прямого taxonomy API нет native pre-assignment `WP_Error` hook: конфликтующий public/future/private Post переводится в draft с явным notice, как в B3-A. Обычные записи не затрагиваются.

После удаления Investor категории статья без Guide становится обычным Post. Новый permalink формируется немедленно, без flush; прежний Investor URL возвращает 404. История смены семьи не сохраняется.

## Rewrite и исходящие ссылки

Добавлен **один** native permastruct `aloha_investor` и **одно ограниченное правило** на один article slug после базы. Оно не содержит индивидуальных post IDs и не принимает дополнительные сегменты. Корень `/homeowners/invest/guides/` правилом не захватывается.

```php
add_filter( 'aloha_content_investor_base', static fn() => 'homeowners/invest/guides' );
```

Это минимальная developer configuration, нормализуемая как path segments, без новой панели настроек. Она не перемещает существующие Pages и не создаёт историю старых баз. Не выбирать базу, которой уже владеет другой раздел.

Существует **один общий `post_link` filter** для Guide и Investor. Он использует текущее семейство и `post_name`; native `%postname%` sample token сохранён для Gutenberg. Plain draft preview остаётся Core ID URL с проверкой прав/nonce и `noindex`. Тексты, featured image и обычные image/heading/paragraph blocks не заменяются.

Входящие Guide/Investor article requests проходят через общий `editorial_article_query()`: только `post`, `publish`, текущее семейство, правильная рубрика для Guide и текущий slug. Ошибочный route получает `post__in=[0]`, настоящий 404 и отсутствие canonical/guessing redirect. Повреждённая legacy смешанная запись проверена в обоих семействах и через стандартный alias — она также закрыта 404.

Соседние Core Pages `/homeowners/`, `/homeowners/invest/`, `/homeowners/invest/calculator/` и другие дочерние Pages вне article namespace не перехватываются. Area, Community и Guide paths сохранены. Адреса непосредственно внутри `/homeowners/invest/guides/{slug}/` принадлежат Investor Posts; Page с тем же адресом создавать нельзя.

## Canonical, архив и lifecycle

Для опубликованного Investor Post canonical генерирует Core через новый `get_permalink()`. Вариант без слеша получает native 301 согласно текущей permalink structure. Scoped общий alias handler B3-A перенаправляет текущие `/{post_name}/`, `?p=ID`, `?name=slug` на Investor canonical. Guide и обычные Posts сохраняют свои canonical.

Core 404 guessing отключён только в существующей Area namespace и новой Investor namespace. Scoped canonical guard блокирует исправление ошибочного Investor route в похожую статью. Автоматический old-slug redirect для этих редакционных семей не используется. Это не историческая redirect map B4.

**Публичного Investor category archive нет.** Native `/category/investor-guides/`, query aliases, pagination/feed aliases закрываются 404. Поэтому стандартная ссылка **View Category** может вести на 404 — это намеренное отсутствие утверждённого архива, а не скрытое создание hub. Категория и её потомки исключены из Core taxonomy sitemap; статьи остаются стандартными Posts с новым permalink. Никакой Page `/homeowners/invest/guides/` не создаётся; существующая Page на этом точном base URL, если она когда-либо появится, остаётся у Core Pages.

Использован существующий lifecycle `aloha_content_area_routes` / `area_routes_signature()` / `sync_area_routes()`: schema **4**, signature дополнена Investor base. Activation регистрирует все permastructs до soft flush; deactivation удаляет также Investor permastruct/tag и собственные runtime rules. Стабильные requests не генерируют rules. Изменения категорий, текста, slug статьи или семьи flush не вызывают. Глобальные `/%postname%/`, category/tag bases не изменены.

## Файлы и тесты

| Файл | B3-B |
| --- | --- |
| `aloha-content.php` | 0.5.0, регистрация/деактивация Investor permastruct |
| `inc/guide-categories.php` | Общая категория install/check инфраструктура и Investor binding; формат Guide option сохранён |
| `inc/guide-integrity.php`, `inc/integrity.php` | Два ограниченных семейства, общие publication guards |
| `inc/guide-routing.php` | Общие post link/query/canonical/preview callbacks для обеих семей |
| `inc/investor-routing.php` | Одна база, одно правило, namespace и исключение category sitemap |
| `inc/area-routing.php` | Общая signature schema 4, runtime cleanup и scoped guessing guard |
| `scripts/aloha-investor-check.php`, `scripts/aloha-investor-http-check.py` | Изолированные server/HTTP fixtures, family switch, configuration и cleanup |
| `scripts/aloha-content-editor.cjs`, `scripts/test-aloha-content.sh` | Новые native Gutenberg сценарии и запуск общего стенда |

```bash
CONTENT_BROWSER_TEST=1 PLAYWRIGHT_MODULE=/path/to/node_modules/playwright \
  ./scripts/test-aloha-content.sh
```

Временные Posts/Categories/Homeowners Pages существуют только в отдельном WP/DB на **8091**. Для отрицательного legacy сценария только в disposable базе намеренно обойдён API guard через SQL; модель production не ослаблена. Очищается сначала Investor, затем Guide/B2/B1/editor fixtures, сверяются исходные content/terms/Navigation/settings, затем удаляются временные контейнеры/volumes/credentials.

Полный финальный прогон с Chromium завершён успешно:

| Набор | Результат |
| --- | --- |
| M1.4-A | 88 прежних серверных проверок |
| B1/B2/B3-A | 75 Area, 28 Community и 33 Guide server checks; прежние 169 HTTP проверок прошли |
| Investor server | 38 проверок: 34 setup/lifecycle/ownership/family checks, 2 при удалении принадлежности и 2 при восстановлении |
| Investor HTTP | 57 проверок: 40 исходных, 3 после удаления категории, 7 со сменённой базой, 7 после восстановления. Включены 200/404/301, canonical, mixed legacy data, draft/private/future/pending, текущие алиасы, отсутствие архива/hub, malformed paths с `?p=ID` и Homeowners Pages |
| Gutenberg / Chromium | 28 сценариев суммарно: 23 регрессии и 5 новых Investor — Categories/text/image blocks, authorized preview, family conflict, permalink/canonical, повторное открытие и снятие Investor принадлежности |
| Очистка/логи | Все fixture records/terms удалены, baseline стенда восстановлен; временные контейнеры/volumes/credentials удалены. PHP debug log стенда пуст, JS page errors отсутствуют |
| Syntax | PHP lint, Bash/JS/Python syntax и `git diff --check` прошли |

### Основная локальная установка

После успешных тестов общей командой установлена **только утверждённая категория Investor Guides**; `check` и повторный `install` успешны. Было 6 категорий, стало 7. Демо-статьи, дополнительные Pages и taxonomy assignments существующим Posts не создавались.

До/после сравнивались все существующие post records/statuses, post meta, taxonomy relationships, категории/tags, Guide option, Navigation и Reading/permalink/category/tag/shell settings. После исключения единственного намеренно добавленного term контрольная сумма совпала с исходной: `6bcc01e407739c5ec8665b8be03080214c05c3426841e7ec1ab84df5906d0a7c`. Counts Posts/Pages/Navigation/Area/Community остались **2/2/4/1/1**; существующие auto-drafts сохранены.

Подтверждены одно Investor rule и **0 генераций** при двух повторных sync. Главная локальная страница и прежние пустые Guide archives отвечают 200. Native Investor category archive, несуществующий hub и пример статьи без созданного контента отвечают 404; реального Investor Post в основной базе не создавали ради демонстрации.

Полные контрольные снимки с исходными данными приватны: `.local/m1-4-b3-b-main-baseline-details.json` / `main-after-tests-details.json` с тем же префиксом, права 0600. Итоговая безопасная сводка — `.local/m1-4-b3-b-main-final.json`. Evidence — `.local/evidence/m1-4-b3-b/`: server log, четыре Investor HTTP JSON reports, прежние HTTP reports, `editor-report.json`, main HTTP/data/log summaries, пустой `wp-debug.log` и screenshots `investor-editor-permalink.png`, `investor-family-conflict.png`, `investor-route-fallback.png`. Они игнорируются Git.

Основной накопительный PHP-журнал не стирался; его исторические Core/CLI network warnings отделены от пустого журнала чистого тестового стенда. Все 20 присутствующих оригиналов совпадают с reference manifest; исторический `aloha-architecture-sources.zip` по-прежнему отсутствует, как установлено до B1. Оригиналы и manifest не изменялись.

## Ограничения перед следующим этапом

- Утверждены только single articles, без Investor hub/category archive, single pagination/feed/embed/attachment routes и frontend-дизайна.
- Slug уникален во всём стандартном CPT `post`, а не отдельно по семействам.
- Классификация и canonical основаны на текущих taxonomy relationships. Истории смены slug/семьи/base и подтверждённой карты 301 нет — это B4.
- Прямой SQL обходит сохранение через hooks; входящие article routes всё равно проверяют текущее семейство. Поддерживаемое редактирование — native Gutenberg/REST/Core API.
- Повреждение/удаление bound категории требует ручной проверки и успешного `check`; автоматического принятия чужой категории или миграции нет.
- Core Query/term APIs остаются доступны доверенному PHP и REST согласно WordPress capabilities; закрыт именно публичный category archive, а не доступ менеджера к taxonomy.

B3-C, B4, M1.5, GuestyBridge, push, deployment и production migrations не выполнялись.
