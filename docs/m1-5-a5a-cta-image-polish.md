# M1.5-A.5a — Circular image в Area CTA

## Реализация

«A little help choosing?» — несинхронизированный native Gutenberg pattern `aloha-hello/area-owner-cta`, генерируемый `aloha_area_pattern_content()`. После вставки его обычные Groups, Heading, Paragraph и Buttons принадлежат `post_content` Area. Это не SSR-компонент и не synced/reusable block. `single-area.html` выводит содержимое записи и не содержит отдельного CTA.

В существующую панель добавлен **Core Image** с классом `aloha-area-advisor-image`, размером `medium`, без ссылки и без заранее выбранного attachment. Имя в List View: **CTA portrait (optional)**. Никаких новых полей, метаданных, JS или зависимостей. `area-owner-cta` используется и в `area-start`; остальные части starter этим изменением не затронуты. Старые сохранённые записи сами не перезаписываются.

Менеджер открывает Area → CTA portrait (optional) → Media Library, выбирает изображение. Для замены — стандартный Replace → Open Media Library. Alt редактируется в стандартных настройках Image / Media Library; Core переносит alt выбранного attachment в блок. Это штатное поведение Image, включая сохранение локальных alt/caption, а не отдельный синхронизируемый источник. Удаление Image block убирает фотографию; при необходимости стандартный Image можно вставить первым в панель и задать ему тот же класс в Advanced → Additional CSS class(es).

Существующий Area image filter скрывает пустой Core Image на frontend. CSS дополнительно скрывает пустой слот, в редакторе оставляет стандартный Media Library placeholder. С выбранной фотографией preview использует ту же круглую маску. WordPress формирует responsive srcset/sizes и lazy loading стандартного Image.

## Design v2

Сверка с финальным `Design v2 — Area Mauna Lani@1x.png` и исходным финальным HTML: круг **96×96px**, `border-radius:50%`, `object-fit:cover`, отступ к тексту 20px, внутренние отступы панели 32px, радиус панели 10px. Сохраняются существующие цвета и Instrument Serif / Outfit. Текст и кнопка не заменяются текстами референса. Ниже 768px изображение располагается над текстом, padding 24px. Высота панели свободная, длинный текст переносится; без фото отсутствует пустая колонка.

Портрет из макета не назначается автоматически. Изображения на изолированных screenshots служат проверке компоновки и не являются утверждённой production-фотографией.

## Файлы

- `theme/aloha-hello/inc/area-patterns.php` — один optional Image в существующем CTA.
- `theme/aloha-hello/assets/css/area.css` — круг, адаптивность, пустой слот и редактор.
- `scripts/area-cta-image-migration.php` — консервативная точечная вставка, без runtime hooks.
- `scripts/migrate-area-mauna-lani-cta.php` — явная local-only CLI команда для Area 68 с проверкой capability/origin/type/slug.
- `scripts/cta-image-check.php`, `scripts/cta-image-browser.cjs`, `scripts/test-aloha-content.sh` — изолированные проверки и opt-in этап `CONTENT_CTA_IMAGE_TEST=1`.

Незакоммиченные FAQ и Guide Highlights, существовавшие до этой задачи, сохранены. Они не относятся к перечню новых изменений A5a. Production, push и deployment не выполняются.

## Обновление Mauna Lani

Перед изменением сохранены приватный snapshot базы и исходный `post_content` в `.local/backups/cta-image-before/`. Миграция находит ровно одну панель по её block metadata className и вставляет Image перед существующим copy Group, не пересериализуя контент. При неизвестной структуре, уже имеющейся custom image или устаревшем backup запись отклоняется. Повторное применение не меняет запись. Перед сохранением создаётся и проверяется нативная ревизия оригинала; содержимое повторно проверяется на конкурентные изменения.

На основном сайте `http://127.0.0.1:8088/big-island/mauna-lani/` вставлены только **259 байт** Image block. Исходная нативная ревизия **98** проверена побайтно, ревизия **99** содержит результат. Повторный план возвращает `changed:false`. Полный исходный контент восстанавливается удалением ровно этих вставленных байтов; Hero, Gallery, Beaches, Guide Highlights, FAQ, owner CTA, тексты и ссылки не изменены. Featured Image, Area Media и остальные прежние метаданные сохранены. Navigation, Pages, категории и permalink/routing/shell settings неизменны.

Существующая autosave-ревизия 97 открытого редактора за время проверки получила новые временные метки; её содержимое и метаданные сохранены. Она не удалялась и не заменялась. Сохранённый контент и backup проверены непосредственно перед записью. Уже открытый редактор нужно перезагрузить перед дальнейшим редактированием, чтобы загрузить новый слот.

**Изображение в Area 68 намеренно не выбрано**: нет подтверждённого редакционного портрета. На frontend сохраняется чистая компоновка без пустого места. Новый Image доступен менеджеру через List View. Тестовые фотографии создавались только на 8091; в основной Media Library ничего не добавлялось.

## Проверки и screenshots

Целевые проверки: 11 серверных и 9 браузерных. Подтверждены точечность/идемпотентность/отказ при stale backup, backup + нативная ревизия, выбор и замена через настоящую Media Library, стандартный alt, удаление, повторная вставка, save/reopen и отсутствие invalid blocks. Кандидат миграции настоящей Mauna Lani дополнительно проверен реальным Gutenberg parser в изолированном редакторе.

Варианты с изображением и без него проверены на **1440, 1024, 768, 390, 320px**. Круг 96×96, cover, srcset/sizes; никаких пустых колонок. Длинные тексты при **200% text zoom** проверены на тех же пяти размерах; горизонтального overflow и обрезки панели нет. Существующая CTA-ссылка доступна с клавиатуры и имеет видимый focus.

Основной сайт 8088 отдельно проверен на всех пяти размерах. Полученный через HTTP CSS совпадает по SHA256 с активной основной темой. Hero, Embla с 5 карточками, прежние 8 Guide-карточек и 6 FAQ-пунктов присутствуют; frontend JavaScript errors отсутствуют. Новых байтов в основном PHP-логе после применения — **0**. Сам лог не очищался.

Все screenshots и JSON evidence находятся в приватной `.local/evidence/m1-5-a5a/`:

| Evidence | Файл |
| --- | --- |
| Desktop с тестовым изображением | `cta-image-1440.png` |
| Mobile с тестовым изображением | `cta-image-390.png`, `cta-image-320.png` |
| Без изображения, пять размеров | `cta-no-image-{width}.png` |
| Native editor / пустой слот | `cta-image-editor.png`, `cta-image-editor-empty.png` |
| Длинный текст, 200% | `cta-text200-{width}.png` |
| Настоящая Mauna Lani 8088 | `main-8088-cta-{width}.png`, `main-8088-context-1440.png`, `main-8088-context-390.png` |
| Сохранность / идемпотентность | `main-preservation-report.json`, `main-migration-report.json`, `main-idempotence-report.json` |
| Настоящий сайт / активные assets | `main-browser-report.json` |

Screenshot с выбранным тестовым изображением показывает компоновку, а не редакционную рекомендацию. Главное отличие от PNG — на основной Mauna Lani пока нет утверждённой фотографии; текущие заголовок, описание и CTA сохранены по заданию. Native caption, если менеджер его оставляет, отображается под кругом. Других страниц или шаблонов этот polish не меняет.

`git diff --check`, JavaScript syntax и shell syntax проходят. Проверка оригинальных источников `scripts/check-references.py` сообщает прежнее отсутствие исторического `aloha-architecture-sources.zip`; ZIP не создавался, manifest и оригинальные HTML/PNG не изменялись.

Полный прогон `CONTENT_CTA_IMAGE_TEST=1 CONTENT_BROWSER_TEST=1 ./scripts/test-aloha-content.sh` завершился успешно на disposable WordPress **7.1.3 / PHP 8.3**, порт 8091. Пройдены существующие проверки Aloha Content (модели, Area/Community/Guide/Investor routes, редактор и Routing Overview), Area presentation, Hero и его polish, Media Gallery, Beaches/Embla, Core FAQ, Guide Highlights и новые CTA-проверки: в сумме **226 HTTP и 118 браузерных проверок**, помимо серверных проверок всех перечисленных модулей. PHP debug log изолированного сайта — **0 байт**, браузерные pageErrors пусты. Fixtures удалены с проверкой исходных content/settings fingerprints; disposable containers, volumes и временные credentials удалены runner trap. Firefox/WebKit не установлены, дополнительные браузеры не устанавливались.

Ветка основной копии осталась `codex/m1-5-a3b-faq-accordion`, HEAD `eb367dd`. Ни коммитов, ни push/deployment в рамках A5a не выполнялось. Предшествующие незакоммиченные файлы сверены с backup: изменения A5a затронули из них только общие `area-patterns.php`, `area.css` и test runner; все остальные сохранены побайтно.
