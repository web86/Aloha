# M1.4-B2 — вложенные маршруты Community

22.10.2026. **Aloha Content 0.3.0**, WordPress 7.1.3 / PHP 8.3. Реализована только маршрутизация Community. Используется существующий fallback `index.html` темы Aloha Hello; frontend-дизайн комплекса, breadcrumbs и starter patterns не создавались.

## Permalink и модель

`community` стал публично запрашиваемым CPT с `query_var=community`, `show_in_nav_menus=true`, `has_archive=false`. Native permastruct: `{area_base}/%aloha_area_route%/%community%/`, без feed/page/embed/attachment rules. База берётся из существующего `area_base()` / PHP filter `aloha_content_area_base`.

`post_type_link` заменяет только `%aloha_area_route%` актуальным `post_name` опубликованного Area, выбранного через **единственное существующее метаполе `aloha_area_id`**. Сегмент Community формирует сам Core из `post_name`. Поэтому:

```text
get_permalink(community) → /big-island/mauna-lani/ka-milo/
после смены aloha_area_id → /big-island/waikoloa-beach-resort/ka-milo/
```

Slug, имя и URL района не дублируются. Нет отдельных Pages комплексов, archive, новых полей/таблиц/таксономий или индивидуальных rewrite rules. Редакционная модель, capabilities и серверные guards M1.4-A сохранены.

Core генерирует sample permalink для Gutenberg через тот же filter; `%community%` при необходимости сохраняется как placeholder. Plain links черновиков filter не меняет. У черновика без опубликованного Area нет корректного публичного nested permalink: редактор и preview используют штатный ID URL. Draft preview до и после выбора Area проверяется с авторизацией; анонимный preview доступа не даёт.

## Входящий запрос и 404

`community_rewrite_rules` оставляет **одно правило ровно на два сегмента после базы**, с концом строки и необязательным конечным слешем. Это filter native CPT permastruct, без универсального resolver. Внутренний rewrite tag/query var `%aloha_area_route%` не является сохранённым полем записи.

Первая часть regex использует общий с B1 `area_route_prefix()`: исключены `guide`, `communities`, feed/page/embed и остальные уже зарезервированные B1 пути, числовые Area slug и comments-pagination. Поэтому новый matcher не выбирается для:

- `/big-island/{area}/`;
- `/big-island/guide/` и `/big-island/communities/`;
- `/big-island/guide/{rubric}/`;
- `/big-island/guide/{rubric}/{article}/`;
- любого третьего сегмента после `{area}/{community}`.

Ограниченный `request` filter работает только для реально совпавшего Community rewrite rule. Он читает сегменты из `$wp->matched_query`, а не из подменяющих их GET-параметров. Area ищется по slug с явным типом `area`; требуется статус `publish` и допустимый slug. Затем стандартный главный `WP_Query` выбирает **опубликованный** `community` с указанным именем и точным `aloha_area_id`.

Если Area отсутствует/не опубликован либо Community отсутствует/не опубликован/связан с другим Area, главный запрос не получает записей и Core возвращает настоящий HTTP 404 с `is_404=true`. При неверном Area дополнительно задаётся `post__in=[0]`. Статусы и queried object проверяются тестовыми HTTP headers, которые существуют только на изолированном стенде.

Для невалидного nested запроса `redirect_canonical` возвращает `false`: существующий Community под чужим Area остаётся 404, включая URL без слеша. Общая защита B1 от Core 404 permalink guessing сохранена; запрет автоматического old-slug redirect внутри базы расширен на Community. Даже при наличии `_wp_old_slug` исторический nested URL остаётся 404. Подтверждённой карты 301 пока нет.

Для корректной записи сохраняются Core `rel=canonical` и 301 с варианта без слеша согласно текущим настройкам WordPress. Plain draft preview не попадает в nested resolver и использует Core permissions/nonce. Опубликованные preview сохраняют native preview query vars. Native query aliases не заменяются новой системой маршрутизации; канонический публичный permalink остаётся вложенным.

## Rewrite lifecycle

Сохранены option `aloha_content_area_routes`, функции signature/sync и soft flush из B1; версия схемы правил повышена с **1 до 2**. Уже активный плагин применяет правила один раз при следующем запросе, после регистрации CPT. Стабильные запросы правила не генерируют. При активации происходит принудительный soft flush, при деактивации удаляются оба CPT permastruct и временный rewrite tag.

Очистка in-memory `extra_rules_top` перед повторным flush теперь касается только собственных Area **и Community** rules, чтобы не сохранялись старые matchers в одном PHP request. Смена PHP-конфигурации базы обновляет оба маршрута через прежнюю signature; global permalink structure и `.htaccess` не меняются.

Изменение `aloha_area_id` не участвует в signature и не вызывает flush. Тест сравнивает rules/signature и счётчик `generate_rewrite_rules` до/после переноса и обратного переноса. Количество правил не зависит от количества районов/комплексов.

## Файлы и запуск проверок

| Файл | Изменение B2 |
| --- | --- |
| `plugins/aloha-content/aloha-content.php` | Версия 0.3.0, публичная регистрация Community, rewrite tag, lifecycle двух CPT |
| `plugins/aloha-content/inc/community-routing.php` | Одно правило, `post_type_link`, ограничение главного запроса и canonical guard |
| `plugins/aloha-content/inc/area-routing.php` | Общий reserved prefix, schema 2, совместная очистка rules и old-slug guard |
| `scripts/aloha-community-routing-check.php` | Новые изолированные fixtures/server checks, перенос/восстановление связи и очистка |
| `scripts/aloha-area-http-check.py` | Совместные B1/B2 HTTP проверки и четыре фазы маршрутизации |
| `scripts/aloha-content-editor.cjs` | Community preview, Gutenberg permalink, открытие правильного объекта и смена Area |
| `scripts/aloha-content-check.php`, `scripts/aloha-area-routing-check.php` | Сохранены прежние регрессии; ожидания public exposure Community обновлены для B2 |
| `scripts/test-aloha-content.sh` | Запуск всех фаз/очистки на прежнем disposable стенде, evidence B2 |

```bash
./scripts/test-aloha-content.sh
CONTENT_BROWSER_TEST=1 PLAYWRIGHT_MODULE=/path/to/node_modules/playwright \
  ./scripts/test-aloha-content.sh
```

Отдельные WP/DB volumes и порт **8091**, прежние pinned images, test-only offline helper, независимые случайные credentials. Fixtures не создаются на 8088. Удаляются сначала B2 fixtures, затем B1 fixtures; проверяется исходный fingerprint Pages/posts/Navigation/permalink settings и отсутствие всех Area/Community fixtures, затем удаляются контейнеры/volumes.

Для отрицательных сценариев только в disposable базе намеренно создаются повреждённые legacy связи через SQL: отсутствующий, неопубликованный или иной post type вместо Area, а также сохранённый ранее Area с reserved slug `guide`. Это проверяет входящий URL независимо от API guards и не меняет разрешённую редакционную модель. Guide rubric/article в этих тестах — синтетические Pages; реализация B3 не добавлена.

Полный финальный прогон с Chromium завершён успешно:

| Набор | Результат |
| --- | --- |
| M1.4-A | 88 регрессионных проверок модели, прав, публикации, удаления и revisions |
| M1.4-B1 | 75 серверных проверок Area; прежние HTTP сценарии сохранены, два ожидания Community exposure обновлены для B2 |
| Community server | 22 проверки permalink/sample/preview, порядка и глубины rules, reserved путей и activation/deactivation; ещё 3 при переносе и 3 при обратном переносе |
| HTTP | 78 проверок: 62 исходных, 4 после переноса, 7 со сменённой базой, 5 после восстановления. Включены правильный queried object, 200/404/301, canonical, старый Area/old slug, draft/private/повреждённые связи, Guide Pages и будущая глубина URL |
| Gutenberg / Chromium | 18 сценариев, включая Community preview до/после выбора Area, nested permalink, открытие объекта и обновление permalink при смене Area; JS page errors отсутствуют |
| Очистка | Все B2/B1/editor fixtures удалены; Pages/posts/Navigation/permalink settings совпадают с baseline; временные контейнеры/volumes удалены |
| PHP / syntax | PHP debug log стенда пуст (0 bytes); PHP lint и Bash/JS/Python syntax checks пройдены |

Основная установка **8088** возвращает HTTP 200. Контрольные снимки всех post types/statuses и Reading/permalink/shell settings до/после совпадают: SHA-256 `73125fec4a9e719f20864e2e39e17270abf5e78af45e5330dec4166e4390a0f1`. Area — 1 прежний auto-draft, Community — 0; реальные записи этим этапом не создавались. Сохранились основные Pages и Navigation. Global permalink structure — `/%postname%/`.

В основной установке подтверждены по одному Area/Community rule, актуальная signature и **0 генераций** при двух повторных стабильных вызовах sync. Накопительный основной PHP-лог сохранён: 11 прежних Core/CLI warnings, последняя запись 22.10.2026 12:41:51 UTC; новых записей и упоминаний Aloha Content нет. Это отличается от пустого лога чистого тестового стенда — старую историю не стирали.

Evidence: `.local/evidence/m1-4-b2/` — HTTP JSON reports, `editor-report.json`, `integration.log`, пустой `wp-debug.log`, сводки основной установки и screenshots (`community-draft-preview.png`, `community-route-fallback.png`, редакторы Area/Community). Контрольные снимки: `.local/m1-4-b2-main-{before,after}.json`. Все evidence/credentials остаются вне публичного Git; временные credentials удалены runner.

Оригиналы не изменены: 20 присутствующих файлов совпадают с историческим reference manifest; `aloha-architecture-sources.zip` по-прежнему отсутствует, как установлено до B1. Manifest не исправлялся ради успешного результата.

## Ограничения B3/B4

- **B3:** зарезервированное пространство Guide и Communities свободно; реальные hubs, Guide rubric/article routes и контент не создавались. Новая первая секция под базой требует дополнить существующую PHP-конфигурацию reserved slugs до её публикации.
- **B4:** смена Area, Area slug, Community slug или базы меняет текущий permalink; исторический URL не получает автоматическую карту 301. Для неё нужны согласованные источники истории/явные redirect mappings. При переносе старый nested URL сейчас 404, новый — 200.
- Community — прежний non-hierarchical CPT. Core обеспечивает уникальность Community slug внутри всего CPT, а не отдельно внутри каждого Area; одинаковые названия могут получить суффикс slug. Это не изменялось ради маршрутов.
- Nested публичные маршруты предназначены для опубликованных Community под опубликованным Area; draft/private не открываются анонимно. Обязательные редакционные проверки preview относятся к черновикам через native ID preview.
- Соседние Pages не должны владеть тем же незарезервированным nested адресом, что и Community. Произвольный Page/CPT конфликт не разрешается новым routing framework.
- Endpoint-подпути Community (feed, embed, attachment, numeric pagination) не добавлены. Отображение использует fallback темы; финальная вёрстка относится к M1.5.

Реальные Area/Community не создавались. Миграции production, B3/B4, M1.5, GuestyBridge, push и deployment не выполнялись.
