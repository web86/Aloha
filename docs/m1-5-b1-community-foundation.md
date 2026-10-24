# M1.5-B1 — Community foundation & Split Hero

## Геометрия до реализации — 24.10.2026

Источники: `макеты/Design v2 — Community Ka Milo@1x.png` (1440 × 5900), `макеты/Design v2 — Community Ka Milo-html.zip` → `CommunityDesign.dc.html`, `docs/design-inventory.md`, существующие `single-area.html`, Area patterns и Hero. PNG имеет приоритет. Экспорты распакованы только в игнорируемую `.local/reference-community/`; оригиналы не изменяются.

- На 1440 px контейнер 1280 px с padding 40 px: полезная ширина 1200, x=120…1320.
- Header из существующих template parts. Hero начинается после header; сверху 28 px. Breadcrumb 14 px / 1.6, затем 24 px до колонок.
- Колонки `1fr 1.15fr`, gap 64 px: ~528.37 и 607.63 px, правый край первой колонки x≈648, начало медиа x≈712. Вертикальное выравнивание по центру.
- H1: Instrument Serif 84 px / .98, weight 400, tracking −.01em; название обычное, `at {Area}` курсивом. Без отдельного принудительного переноса: при данных Ka Milo / Mauna Lani получаются две строки. Margin снизу 22 px.
- Excerpt: Outfit 20 px / 1.6, opacity .88, max-width 520 px, margin снизу 32 px.
- CTA: gap 14 px, min-height 52 px, горизонтальный padding 28 px, pill radius. Primary orange, white, 19 px / 700; secondary outline, 16 px / 600. Перенос при недостатке места.
- Медиа: 560 px высотой, border-radius 12 px, overflow hidden. PNG и final HTML содержат placeholder pool-club photo с градиентом `#cfe0e3 → #e3ece4 → #bcd6dd`, а не реальный pool-club JPG. Реальная картинка берётся исключительно из Featured Image; object-fit cover, center. Никакая картинка из макета не зашивается в тему.
- На 1024 px остаются две колонки; адаптивный заголовок ~66.67 px и gap ~45.51 px. Ниже 900 px — одна колонка, текст перед медиа, gap 32 px. На 768 px медиа ~405 px, на 390/320 — соотношение 1.25. H1 от 48 px. Адаптация — решение реализации: отдельного mobile Community reference нет.
- Размеры типографики в rem с изменениями по breakpoint, без ограничения высоты текста; длинные строки и 200% текста могут увеличивать Hero. Breadcrumb и кнопки переносятся.

Отклонения текущего этапа: без фиктивного `[N]` в primary CTA; нет опубликованной коллекции домов, Owner Estimate Form и фильма. Две CTA открывают доступный диалог существующего shell с объяснением будущей функции. Видеокнопка без источника отсутствует. Контакты header остаются из существующих проверенных настроек.

## Реализация и работа менеджера

Aloha Hello **0.7.0**. `templates/single-community.html` — нативный шаблон блочной темы, общий для любого Community. Он использует существующие Header/Footer, один `aloha/community-hero` и Core Post Content. Hero находится в шаблоне, не копируется в Gutenberg-контент каждого комплекса. У него нет режимов Area и нет собственных полей с дубликатами post data.

Название, явный excerpt и Featured Image читаются из текущего `community`; `at {Area}` и ссылка в breadcrumb — из единственного `aloha_area_id`. Отсутствующий excerpt не генерируется из редакционных заглушек. Не назначенный Area в draft не подменяется районом из URL. Big Island в breadcrumb остаётся текстом, как в Area: опубликованный hub ещё не создан. Входящие публичные URL и 404 по-прежнему полностью обслуживает Aloha Content, без изменений routing-кода.

Создание: **Communities → Add New**. Core CPT template с `core/pattern` разворачивает `aloha-hello/community-start` в обычные несинхронизированные блоки: About the community, Community details, Location and getting around, For homeowners. Это начальные редакционные секции без дизайна Gallery, Amenities, Nearby, Available Rentals или формы. Внести название, excerpt и Featured Image штатными средствами WordPress, выбрать район в существующей панели **Area**, заменить редакционные placeholders и сохранить. До публикации нужен опубликованный Area — прежнее правило Aloha Content.

Блоки можно редактировать, удалять, переставлять и дополнять. Нет save-hooks, миграций body, создания реальной Ka Milo или отдельного менеджера Patterns. Сохранённый документ не получает Starter повторно; это относится и к намеренно пустому body. Новая версия темы обновляет нативный cache Patterns. Смена темы или изменение Starter не перезаписывают сохранённые блоки.

В Site Editor динамический Hero использует Community post context. Без подходящего контекста редактор показывает подсказку. Preview читает сохранённые поля; несохранённые изменения post fields отображаются после сохранения/обновления preview, как у существующего Area Hero.

Две временные CTA используют расширенный общий shell dialog (`data-aloha-info`, заголовок и текст). Для Community нет отдельного frontend JS, обработчика booking или формы. При появлении проверенной коллекции и Owner section в следующем этапе эти временные действия можно заменить целевыми ссылками. Отсутствующий фильм не создаёт неработающую кнопку.

## Изменённые файлы

| Файл | Назначение |
| --- | --- |
| `theme/aloha-hello/templates/single-community.html` | Новый нативный шаблон |
| `theme/aloha-hello/inc/community.php` | Динамический Hero, native starter assignment, assets, saved-content guard |
| `theme/aloha-hello/patterns/community-start.php` | Обычные Core editorial sections; переиспользует markup helpers Area |
| `theme/aloha-hello/assets/css/community.css` | Split Hero, no-image state и responsive foundation |
| `theme/aloha-hello/assets/js/community-editor.js` | Contextual Hero preview для редактора шаблонов |
| `theme/aloha-hello/assets/js/shell.js` | Повторно используемый информационный диалог для временных CTA |
| `theme/aloha-hello/functions.php`, `style.css` | Подключение и версия 0.7.0 |
| `scripts/community-foundation-check.php` | Изолированные fixtures, серверные проверки и cleanup |
| `scripts/community-foundation-browser.cjs` | Реальный Gutenberg, HTTP, responsive screenshots и CTA keyboard checks |
| `scripts/test-aloha-content.sh` | Opt-in Community suite и проверки смены темы |
| `README.md`, этот документ | Инструкция и результаты |

## Для переиспользования в B2

- Шаблон single-community, динамический Hero и stylesheet Community; Header/Footer остаются общими.
- `aloha-container`, theme.json tokens, licensed local fonts, button/focus styles и общий shell dialog.
- Существующие `aloha_area_group`, `aloha_area_heading`, `aloha_area_paragraph`: только генерация нативного Core markup, без нового framework.
- Несинхронизированная композиция `community-start` и её сохранённые редакционные секции. Расширять Starter для новых записей; существующие страницы обновлять только отдельным осознанным редакционным действием.
- Существующий Media Library / Featured Image flow и responsive WP image generation. Компонент `aloha/media-gallery` может стать основой Gallery после отдельного решения по Community composition; в B1 он не добавлен.
- Aloha Content Area picker, `aloha_area_id`, nested permalink и прежние guards/404. Не дублировать связь в новых атрибутах блоков.
- Изолированный test runner, Community fixtures и browser acceptance, в том числе saved-empty, removal, Area move и 200% text.

## QA и evidence

Проверено на изолированном WordPress **7.1.3 / PHP 8.3**, порт **8091**, собственные одноразовые WP/DB volumes и случайные credentials. Никакие Community fixtures не создавались на 8088. Все геометрические проверки используют настоящий frontend темы; Gutenberg — реальный Core editor в Chromium.

| Проверка | Результат |
| --- | --- |
| Community server | 17 проверок: native template/pattern, текущие post fields, `aloha_area_id`, authenticated block-renderer с `context=edit`, breadcrumb, responsive image, no-image/no-excerpt/unassigned draft, CTA без фиктивных integrations, сохранность body/rules |
| Community Gutenberg / frontend | 8 сценарных групп: создание, RichText edit, Area picker, native post fields, draft preview, публикация, сохранение/повторное открытие, смена Area, 200/404, удаление секции, saved-empty, layout matrix, CTA keyboard behavior |
| Invalid blocks / JS | Все блоки Starter и шаблона проходят реальный Gutenberg parser; invalid/missing blocks отсутствуют; page errors — 0 |
| Responsive / text | 1440, 1024, 768, 390, 320 px; фото и no-image; длинный title и excerpt, включая непрерывную строку, при 100% и 200% текста. Нет горизонтального overflow или обрезанного текста |
| CTA | Enter открывает общий dialog, Escape закрывает, фокус возвращается к trigger; focus outline видим |
| Смена темы / обновление Pattern | Saved body сохраняется побайтово; Starter не появляется у другой темы; изменение Pattern не меняет сохранённый Community |
| Aloha Content / маршруты | 88 model checks, 75 Area routing, 22 Community routing; 78 Area/Community HTTP проверок в четырёх фазах. Guide/Investor и Routing Overview regressions также пройдены |
| Area regressions | Foundation 23 server / 11 browser; Hero 48 / 17; Gallery 41 / 13; Beaches 33 / 14; FAQ 31 / 9; Guide Highlights 30 / 13; CTA Image 11 / 8 |
| Static checks | PHP lint новых PHP и pattern, JS syntax, Bash syntax и `git diff --check` пройдены |
| Очистка | Fixtures/media удаляются с проверкой baseline fingerprints; тестовые контейнеры, volumes и credentials удаляются. PHP debug log успешного стенда — 0 bytes |

Измеренная геометрия (CSS px):

| Viewport | Текст / медиа | Высота медиа | H1 100% → 200% |
| --- | --- | --- | --- |
| 1440 | 528.38 / 607.62, две колонки | 560 | 84 → 168 |
| 1024 | 417.91 / 480.58, две колонки | 560 | 66.67 → 133.33 |
| 768 | 688 / 688, вертикально | 404.70 | 56 → 112 |
| 390 | 350 / 350, вертикально | 280 | 48 → 96 |
| 320 | 280 / 280, вертикально | 224 | 48 → 96 |

200% проверяется увеличением корневого размера текста, с отдельной проверкой фактического удвоения H1. Это browser QA, не испытание на физических iOS/Android или Safari/Firefox. Визуальные Ka Milo title/excerpt взяты из макета только для одноразовой тестовой БД. Тестовый shoreline Featured Image не является подтверждённой фотографией комплекса.

Первый полный запуск прошёл прежние Area suites, затем остановился на закрытой предыдущим тестом sidebar. Новый тест теперь явно открывает Community settings. Дополнительный SSR-тест уточнён до `context=edit`, как у редактора WordPress. Совместный запуск также выявил два одноимённых визуальных Area fixtures; для редакторского сценария выделен отдельный `Community editor area`, чтобы выбирать точную связь без неоднозначного названия. После исправлений Community acceptance и theme-switch/cleanup выполнены на коротком стенде; финальный штатный runner завершился успешно, повторно проверил базовые регрессии и Community и удалил свои одноразовые volumes/credentials. Успешные прежние Area suites отдельно сохранены в `area-regression/`, первые попытки — в `attempts/initial/`.

Основная БД: снимки `posts`, `postmeta`, `terms`, `term_taxonomy`, `term_relationships` и Reading/permalink/routing/shell options до/после равны. SHA-256 нормализованного снимка: `feef410ad53c813e1b35f41d16b1e172e87dfa482d2d9cc127b76631f9b19669`. Community — один прежний `auto-draft` (ID 67), опубликованных Community и постоянной Ka Milo нет. Основные Home и Mauna Lani возвращают HTTP **200**.

Оригиналы не изменены. `check-references.py` по-прежнему сообщает ранее отсутствующий `aloha-architecture-sources.zip`; остальные присутствующие reference files совпадают с manifest. Manifest не переписывался.

Все screenshots и JSON находятся локально в игнорируемой `.local/evidence/m1-5-b1/`; credentials и dumps не входят в Git:

- [Split Hero рядом с финальным PNG — Featured Image](../.local/evidence/m1-5-b1/community-comparison-image.png).
- [Split Hero рядом с финальным PNG — без изображения](../.local/evidence/m1-5-b1/community-comparison-no-image.png).
- [Gutenberg: Starter, Featured Image, excerpt и Area](../.local/evidence/m1-5-b1/community-gutenberg.png).
- Hero с фото: [1440](../.local/evidence/m1-5-b1/community-image-1440.png), [1024](../.local/evidence/m1-5-b1/community-image-1024.png), [768](../.local/evidence/m1-5-b1/community-image-768.png), [390](../.local/evidence/m1-5-b1/community-image-390.png), [320](../.local/evidence/m1-5-b1/community-image-320.png).
- [Длинный текст, 200%, 320 px](../.local/evidence/m1-5-b1/community-long-text200-320.png).
- [Community server report](../.local/evidence/m1-5-b1/community-server-report.json), [browser report](../.local/evidence/m1-5-b1/community-browser-report.json), [сохранность основной БД](../.local/evidence/m1-5-b1/main-preservation-report.json), [финальный integration log](../.local/evidence/m1-5-b1/integration.log).

Запуск Community acceptance с базовыми регрессиями:

```bash
DOCKER_CONTEXT=orbstack CONTENT_COMMUNITY_TEST=1 CONTENT_BROWSER_TEST=1 \
  CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-b1 \
  PLAYWRIGHT_MODULE=/path/to/node_modules/playwright ./scripts/test-aloha-content.sh
```

Для всех прежних Area suites добавить `CONTENT_CTA_IMAGE_TEST=1`. Требуются supplied Area JPG в `.local/reference-area/assets` либо `ALOHA_AREA_MEDIA_DIR`. Images/tests не входят в тему и не создают редакционный контент автоматически.

Полная Gallery, Amenities/Nearby, Available Rentals, Owner Estimate Form, GuestyBridge и другие design templates не реализованы. Push и production deployment не выполнялись.

Git: отдельная ветка `codex/m1-5-b1-community-foundation` от подтверждённого `origin/main` = `6c0a5e9`. Изменения сохраняются только локально.
