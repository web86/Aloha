# M1.4-A — Aloha Content: Area и Community

22.10.2026. Плагин **Aloha Content 0.1.0**, тема **Aloha Hello 0.2.2**, локальный WordPress **7.1.3 / PHP 8.3**. Основание: [аудит M1.3](/Users/sonoma/Documents/ALOHA/docs/content-architecture-audit.md). Этап ограничен редакционной моделью; M1.4-B, M1.5 и GuestyBridge не реализуются.

## Установка и редактор

Плагин находится в [plugins/aloha-content](/Users/sonoma/Documents/ALOHA/plugins/aloha-content) и монтируется read-only в WordPress и CLI отдельным bind mount. В основной локальной установке он активирован. Для повторной установки после получения исходников:

```bash
./bin/local up
./bin/local wp plugin activate aloha-content
```

В WordPress:

- [Areas](http://127.0.0.1:8088/wp-admin/edit.php?post_type=area) — список районов.
- [Communities](http://127.0.0.1:8088/wp-admin/edit.php?post_type=community) — список комплексов.
- В обоих редакторах доступны обычные Gutenberg-блоки, title, content, excerpt, featured image, revisions, черновики и публикация.
- В правой панели Community открыть **Area** и выбрать район по названию. Поле поддерживает поиск и очистку; неопубликованные районы отмечены статусом. **Refresh Areas** обновляет список после создания/публикации района в другой вкладке.
- Черновик можно сохранить без района. Для публикации, private или scheduled Community нужен опубликованный Area. Сначала опубликовать район, затем комплекс.
- Чтобы убрать район у опубликованного комплекса, сначала перевести комплекс в draft и очистить выбор. Менять один опубликованный Area на другой можно без снятия Community с публикации.

Список районов загружается через штатный REST API с пагинацией по 100 записей, без ограничения интерфейса первыми 100. Использованы `PluginDocumentSettingPanel`, `ComboboxControl`, `Notice`, `Spinner`, `Button`, Core data store и `apiFetch`; сборка React-приложения или сторонние компоненты не добавлены. REST-запросы Gutenberg используют штатный cookie nonce и capability checks WordPress.

## Модель и файлы

| Файл | Назначение |
| --- | --- |
| [aloha-content.php](/Users/sonoma/Documents/ALOHA/plugins/aloha-content/aloha-content.php) | Регистрация CPT/meta и подключение editor script только для Community |
| [inc/integrity.php](/Users/sonoma/Documents/ALOHA/plugins/aloha-content/inc/integrity.php) | Валидация связи, публикация/удаление/статусы, REST errors, восстановление revision meta |
| [assets/editor.js](/Users/sonoma/Documents/ALOHA/plugins/aloha-content/assets/editor.js) | Штатная панель выбора Area в Gutenberg |
| [compose.yaml](/Users/sonoma/Documents/ALOHA/compose.yaml) | Два mount плагина — WordPress и WP-CLI |
| [editor.css](/Users/sonoma/Documents/ALOHA/theme/aloha-hello/assets/css/editor.css) | Только расширение существующих editor gutters на Area/Community |

Оба CPT — non-hierarchical, с `show_in_rest`, `map_meta_cap` и стандартными post capabilities. Gutenberg supports: `title`, `editor`, `excerpt`, `thumbnail`, `revisions`, `custom-fields`. Доступ к редактированию следует стандартным ролям WordPress; новые роли не создаются.

Связь — единственное метаполе **`aloha_area_id`**: `integer`, `single=true`, default `0`, `show_in_rest`, `revisions_enabled=true`, регистрация через `register_post_meta('community', ...)`. `0` означает отсутствие связи у черновика. Названия, slug и URL района не дублируются в meta. Сохраняется существующий ID записи `area`, а не attachment, revision, Page или произвольный номер. Отрицательные, дробные и нечисловые значения отклоняются, а не превращаются в другой действительный ID.

Meta auth callback проверяет `edit_post` для конкретного Community; REST дополнительно применяет Core permissions и schema. Low-level metadata API/WP-CLI остаются доверенными серверными API WordPress, но проверки целостности действуют и для них. Данные — стандартные `wp_posts`/`wp_postmeta`, без новых таблиц или таксономий. Связи и редакционная модель не зависят от активной темы.

Шрифты, цвета и общие стили даёт Aloha Hello через существующий `theme.json` и editor styles. Плагин не содержит копии палитры/шрифтов и frontend CSS/JS. В теме изменены только два дополнительных editor selectors; новые display templates и starter patterns не создавались.

## Серверная целостность

| Операция | Поведение |
| --- | --- |
| Новый Community draft без Area | Разрешён |
| Назначение существующего draft/pending/private/future Area черновику | Разрешено; UI предупреждает, что публикация пока недоступна |
| Несуществующий ID, иной post type, trashed или auto-draft Area | Назначение отклоняется |
| Публикация/планирование/private Community без опубликованного Area | Отклоняется до сохранения данных |
| Изменение published Community на неверный Area / очистка связи | Отклоняется, прежняя связь сохраняется |
| Публикация и корректная связь в одном REST/PHP запросе | Разрешены; proposed meta участвует в проверке до записи post fields |
| Trash/force-delete Area, используемого любым Community | Отклоняется, в том числе при ссылках из draft или trash Community |
| Снятие Area с публикации при published/private/scheduled Community | Отклоняется; сначала переназначить или снять с публикации комплексы |
| Смена post type используемого Area | Отклоняется |
| Удаление Community | Разрешено; Core удаляет его meta/revisions без оставшихся meta rows |

Для удаления используемого Area нужно сначала переназначить **все** Community, очистить связь у черновиков/корзины либо окончательно удалить ненужные комплексы. Простое перемещение Community в корзину не освобождает Area. В сообщении об отказе это требование объясняется; отказ работает независимо от наличия кнопки в интерфейсе.

Использованы native hooks:

- `rest_pre_insert_area/community` возвращают конкретные `WP_Error` до записи: `aloha_invalid_area` (400), `aloha_area_not_published` (409), `aloha_area_in_use` (409). Для REST DELETE сообщение о зависимостях возвращается до Core generic deletion error.
- `wp_insert_post_empty_content` блокирует недопустимое сохранение через `wp_insert_post`/`wp_update_post`, в том числе WP-CLI. У Core нет отдельного pre-insert filter с возвратом произвольного `WP_Error`: при `$wp_error=true` возвращается стандартный `empty_content`; рядом WP-CLI выводит точную причину предупреждением, в admin — notice. Редактор Gutenberg получает понятный REST error, а не это generic сообщение. Статус не меняется молча на draft.
- `add/update/delete_post_metadata` и соответствующие `*_by_mid` guards защищают прямые meta APIs: неправильный ID, удаление/переименование обязательной связи, duplicate add и bulk removal. `single=true` само по себе не является DB uniqueness constraint, поэтому повторный `add_post_meta` не добавляет вторую строку.
- `pre_trash_post` и `pre_delete_post` защищают и корзину, и окончательное удаление. Обычный admin delete показывает объяснение с возвратом назад; PHP API возвращает `false`, WP-CLI сообщает отказ.
- `transition_post_status` дополнительно обрабатывает `wp_publish_post()`/scheduler, которые обходят обычный pre-insert hook: некорректная попытка прямой публикации возвращает Community в draft с предупреждением.

## Ревизии

Native revisioned meta включено для `aloha_area_id`. Изменение только района также создаёт revision при обычном сохранении post через Gutenberg/REST/`wp_update_post(meta_input)`. Простое отдельное `update_post_meta` в Core не создаёт revision автоматически; для серверных пакетных изменений сохранять post целиком или вызывать `wp_save_post_revision` после изменения meta.

Core восстанавливает revision meta операцией delete + add. Удаление обязательного района у published Community намеренно запрещено, поэтому только этот ключ исключается из Core restore loop **на время `wp_restore_post_revision` action** и восстанавливается одним валидируемым `update_post_meta`. Остальные revision fields/meta сохраняют штатное поведение.

Если исторический Area существует и опубликован, связь восстанавливается с сохранением публикации Community. Если район стал неопубликованным, восстановленный Community становится draft с исторической связью. Если Area удалён/в корзине, восстанавливается контент в draft и пустая связь; показывается сообщение о необходимости выбрать опубликованный Area. Недействительный ID не переносится обратно в текущую запись.

API основания: [register_meta](https://developer.wordpress.org/reference/functions/register_meta/) и [Core revision meta restore](https://developer.wordpress.org/reference/functions/wp_restore_post_revision_meta/). Использование проверено на установленном WordPress, а не только по документации.

## Маршруты и жизненный цикл

Текущая конфигурация backend-only: `publicly_queryable=false`, `rewrite=false`, `query_var=false`, `has_archive=false`, `exclude_from_search=true`, `show_in_nav_menus=false`. Админка и REST работают; публичный preview/display Area/Community на этом этапе не обещается. Плагин не добавляет rewrite rules и не выполняет flush.

Пути `/big-island/[area]/`, `/big-island/[area]/[community]/`, `/big-island/guide/`, `/big-island/communities/` не заняты новой реализацией. Navigation posts и global permalink structure не меняются. Rental не регистрируется. Reserved slugs, canonical, 301 и публичные routes относятся к отдельному M1.4-B.

Активация не создаёт записи и не выполняет миграцию. Отключение/повторное включение не удаляет post/meta/revisions; uninstall data deletion не добавлено. При отключённом плагине CPT-интерфейсы и проверки недоступны, но данные остаются в Core tables, а Aloha Hello продолжает работать. Прямой SQL и изменения данных сторонним кодом при отключённом плагине не защищаются этими hooks.

## Автоматизированные проверки

Запуск из корня проекта:

```bash
./scripts/test-aloha-content.sh

# Дополнительно Gutenberg в уже установленном Playwright Chromium:
CONTENT_BROWSER_TEST=1 PLAYWRIGHT_MODULE=/path/to/node_modules/playwright \
  ./scripts/test-aloha-content.sh
```

Требуются инструменты существующего local stack: Docker Compose, Python 3, curl; Node/Playwright нужны только для browser suite. Браузеры и зависимости глобально не устанавливаются.

Runner создаёт отдельный Compose project, WordPress/DB volumes и свободную тестовую подсеть из ограниченного диапазона. Использует те же pinned images, тему и плагин, порт **127.0.0.1:8091**, независимые случайные пароли. Существующий сервис на 8091 не останавливается: при занятом порту тест завершится ошибкой. Конфигурация/credentials записываются с приватными правами в игнорируемую `.local/` и удаляются при завершении. Только в тестовой установке временный MU helper через Core `pre_http_request` возвращает пустые ответы update/translation API WordPress.org: это исключает внешние запросы и посторонние offline warnings Core на admin screens при заблокированном external HTTP. Helper не входит в Aloha Content и не устанавливается на основном сайте. Core tests дополнительно отказываются работать без `ALOHA_CONTENT_TEST_SITE`; browser suite отказывается работать на основном URL 8088.

| Проверка | Результат |
| --- | --- |
| PHP/Core/REST integration | **88 проверок прошли**: CPT/supports/schema, CRUD, неверные IDs/types, publish/status guards, revisions и meta-only revision, stale/malformed revision, duplicate/by-ID/bulk meta APIs, trash/force-delete (также при ссылках только из draft/trash), REST permission/error/no-partial-write, scheduled Community, lifecycle и неизменность существующего контента/меню/permalink |
| Реальные WP-CLI команды | Создание Area/Community, назначение связи, публикация; отклонение неверного ID и удаления используемого Area; очистка fixtures |
| Fresh-request lifecycle | При отключении типы отсутствуют, сайт отвечает 200; после включения связь сохранена; с Twenty Twenty-Five модель остаётся зарегистрированной |
| Gutenberg / Chromium | **11 сценариев прошли**: Area publish, excerpt/featured image, Community draft, отказ публикации без Area, native picker/change/reopen, Core revisions screen, delete guard, unpublish+clear, off/on и работа темы без плагина |
| Editor styles | У обоих CPT вычисленные шрифты Instrument Serif / Outfit, цвет Mirage и preset Deep Sea Green совпадают с Aloha; JS page errors отсутствуют |
| Очистка | Тестовые Community, Areas и media удалены; временный stack/volumes удаляются trap cleanup |

Browser evidence: `.local/evidence/m1-4-a/` — `area-editor.png`, `community-picker.png`, `publication-blocked.png`, `editor-report.json`, `wp-debug.log`. Тяжёлые screenshot assets и credentials в тему/Git не добавляются.

Основная локальная база: **0 Area / 0 Community**, никаких demo записей не создавалось. До/после активации отдельно сверяется fingerprint исходных Pages/posts/Navigation/templates, контактов/mega settings, Reading/permalink/rewrite options. Разрешённое изменение БД — активация плагина, без контентной миграции. M1.4-B и M1.5 не начаты; deployment, commit и push не выполнялись.
