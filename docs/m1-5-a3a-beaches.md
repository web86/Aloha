# M1.5-A.3a — Interactive Beaches

Aloha Hello **0.5.0**; Aloha Content **0.7.2**, без изменений плагина. Полировка Gallery A.2.1 сохранена отдельным локальным коммитом **`2875f6a`**. Работа выполнена в ветке `codex/m1-5-a3a-beaches`, созданной от этого коммита. Push/deployment не выполняются.

## Архитектура и данные

`aloha/beaches` — динамический Gutenberg block API v3 с PHP SSR. Единственная коллекция `cards` хранится непосредственно в attributes блока, в порядке редактора:

| Поле карточки | Значение |
| --- | --- |
| `title` | Обычный текст названия |
| `description` | Краткий редакционный текст, разрешены emphasis/bold/inline links |
| `imageId` | Integer attachment ID, `0` при отсутствии фото |
| `url` | Необязательная подтверждённая редакционная ссылка, включая root-relative |
| `focalPoint` | Координаты `{x,y}` от 0 до 1, по умолчанию `{0.5,0.5}` |

Нет второго списка в post meta, Beach CPT, новой таксономии, таблицы, глобальных настроек или внешней библиотеки. URL/alt/размеры изображения берутся из Media Library при SSR; координаты описывают только кадрирование экземпляра и не изменяют исходное изображение. `save()` возвращает `null`: централизованные изменения PHP/CSS/JS не требуют пересохранения записей.

`inc/beaches.php` регистрирует block.json и assets. Изображения проверяются как действительные image attachments, исключены trash, удалённые IDs и отсутствующие локальные файлы. Строки/URL/HTML экранируются или фильтруются на выводе; недействительные координаты нормализуются. Пустые карточки без текста и фото не создают публичных слотов. Пустой блок не выводит разметку; пустая Beaches-секция Area скрывается, оставаясь доступной для заполнения в редакторе.

## Редактор

В Gutenberg вставить **Beaches** или заполнить его в Area starter. **Add Beach** добавляет карточку, количество не ограничено четырьмя. У каждой карточки — название, краткое описание и optional link. Клик по фотографии на frontend сам по себе не открывает lightbox: переход возможен только при заполненной ссылке.

Под полями расположен один ряд из шести native icon buttons: **Move earlier**, **Move later**, **Choose or replace photo**, **Set focal point**, **Remove photo**, **Remove Beach**. Есть tooltip, `aria-label`, `title`, focus-visible; граничные действия перестановки отключены. Размер каждой кнопки 36×36px, ряд не переносится. Иконки используют уже доступный WordPress Dashicons, стили явно доступны в iframe редактора.

Выбор/замена фото открывает стандартную Media Library. **Set focal point** открывает стандартный `FocalPointPicker` в native WordPress Modal: клик по изображению или числовые поля задают кадрирование, **Reset focal point** возвращает центр, **Done** закрывает окно. Удаление фото/карточки не удаляет attachment из медиабиблиотеки. Alt редактируется обычным способом в Media Library и используется через WordPress image API; отдельного дублирующего alt-поля нет.

## Frontend

При 1–4 непустых карточках используется сетка: четыре колонки от 1024px, две при 600–1023px, одна ниже 600px. Геометрия соответствует desktop Area reference: gap 24px, рамка изображения **4:3**, radius 8px, Outfit 20px для названия, 16px для описания. Сохранены прежние фон, заголовок, контейнер и отступы секции.

При количестве больше четырёх та же коллекция становится горизонтальной CSS scroll-snap каруселью: четыре видимых карточки на desktop, две на tablet, 88% ширины карточки на телефоне с видимым продолжением. Все статьи присутствуют в первоначальном SSR HTML. Нет загрузки карточек через JS, autoplay или mouse drag library.

Progressive JS показывает **Previous / Next**, фактический диапазон видимых карточек и отключает кнопки на границах. Кнопки прокручивают следующую/предыдущую группу; ArrowLeft/ArrowRight — одну карточку, Home/End — начало/конец. Клавиши перехватываются только на фокусированной области прокрутки, не на дочерних ссылках. `aria-controls` уникален для каждого экземпляра. Обычные ссылки сохраняют нативное поведение мыши и клавиатуры.

Touch swipe, горизонтальный wheel/trackpad и scrollbar — штатная прокрутка браузера. При `prefers-reduced-motion` переходы немедленные; иначе плавные. Без JS скрыты только дополнительные кнопки: все карточки/ссылки остаются доступны через native horizontal scrolling и ArrowLeft/ArrowRight в фокусированной области. Без JS Home/End не обещаются: Chromium трактует End как вертикальную навигацию.

Изображения используют WordPress `large`, `srcset`, `sizes`, `loading="lazy"`, async decoding и `object-fit: cover`; `object-position` задаётся focal point. Нет копий оригинальных URL в attributes. Без фото остаётся аккуратная текстовая карточка с теми же редакционными данными. Без URL название/изображение не имитируют неработающую ссылку.

## Starter и контролируемый перенос

В `area-beaches` заменена только старая фиксированная сетка на пустой `aloha/beaches`. Остальные секции `area-start` не изменены. Ранее сохранённые native Beach cards продолжают отображаться; тема не запускает автоматических save hooks или массовых обновлений.

`scripts/area-beaches-migration.php` — отдельный CLI helper, не подключённый к теме. Он допускает ровно одну известную legacy grid в секции Beaches и проверяет структуру карточек, изображения, alt, тексты и ссылки. Inline alt, отличающийся от Media Library, нестандартная подпись/кадрирование, конфликтующие destinations, добавленный свободный HTML или неоднозначная структура требуют ручной замены, без записи.

Замена выполняется по точному диапазону байтов сбалансированных block comments, без пересериализации окружающего содержимого. `apply` требует резервную копию, совпадающую с актуальным полным `post_content`; перед записью создаётся и проверяется native WordPress revision. После переноса повторный запуск ничего не пишет, не создаёт новых revisions и не перезаписывает последующие редакционные изменения.

`scripts/migrate-area-mauna-lani-beaches.php` дополнительно ограничен local `127.0.0.1:8088`, WP-CLI, Area **68 / mauna-lani** и capability `edit_post`. `plan` только анализирует; `apply` требует путь к проверенной копии body. Другие Area никогда не обновляются этой командой.

## Результат переноса Mauna Lani

Выполнен после успешного полного изолированного прогона. Сохранённая секция содержала четыре native cards, без ссылок и inline captions:

| Название | Attachment ID |
| --- | ---: |
| Pauoa Bay · Beach Club | 69 |
| Makaiwa Bay | 79 |
| Beach 69 (Waialea Bay) | 27 |
| 49 Black Sand Beach | 0 — текстовая карточка |

Сохранены порядок, названия, три существующие фотографии, пустые URL и все четыре исходных описания **Editorial placeholder: add a verified description, access information and practical advice.** Они не заменены непроверенными фактами. Inline alt совпадал с Library, custom crop отсутствовал; focal point перенесён как центр.

Приватная резервная копия: `.local/mauna-lani-beaches-post-content-before.html`, режим 0600. Native revision **89** содержит исходный полный body, revision **90** — результат. SHA-256 до переноса: `50f308929d1d15e9dc222212307944223cf876ab7d16fac4fc4babd8477b9f3b`; после: `a7c3f3ae88ea5ec15f79b2029ff1c475bbc930a23be6c369db713d2610c801fe`.

Повторный apply вернул **`changed:false`**, третьей migration revision нет. Финальная SQL-сверка: **78 остальных прежних post rows полностью сохранены**; у Area 68 изменились только `post_content`, `post_modified`, `post_modified_gmt`. Все байты вокруг заменённой Beaches grid идентичны. Прежние postmeta сохранены, исключая штатный `_edit_lock`; новые meta принадлежат новым revisions. Images/Featured Image, Hero video/film fields, Gallery, Navigation, terms/relationships, permalink/rewrite signature, Reading и shell settings не изменены. Новых Pages, Area, Community или attachments в основной базе нет.

Открытие редактора при QA вызвало штатное удаление старого пользовательского autosave **88** в Core `wp-admin/edit-form-blocks.php:303–310`: он стал старше вновь сохранённой Area. После подтверждения причины точечно восстановлены только этот прежний row и его четыре meta rows из снимка, с исходными ID/значениями, без перезаписи Area и других данных. Финальная сверка включает восстановленный autosave. При последующем открытии редактора WordPress может снова штатно убрать устаревший autosave; приватный исходный snapshot сохранён. Нового runtime hook или изменения Core ради этого не добавлено.

Публичная [Mauna Lani](http://127.0.0.1:8088/big-island/mauna-lani/) показывает новую секцию. [Gutenberg Area 68](http://127.0.0.1:8088/wp-admin/post.php?post=68&action=edit) проверен без сохранения: **133 валидных блока**, `invalid: []`, cards IDs `69,79,27,0`, `isEditedPostDirty:false`. На основной странице прошли пять viewport и 200% текста; Gallery dialog и ручная пауза Hero работают. `pageErrors: []`, новых сообщений основного PHP-лога нет.

## Проверки и visual QA

Финальный полный прогон **прошёл**, `.local/content-test-cwaogO`. Команда: `DOCKER_CONTEXT=orbstack CONTENT_BEACHES_TEST=1 CONTENT_BROWSER_TEST=1 CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-a3a ./scripts/test-aloha-content.sh` с `PLAYWRIGHT_MODULE`, указывающим на уже установленный bundled Playwright. Флаг Beaches включает Gallery, Hero и Area регрессии.

| Набор | Server | HTTP | Browser |
| --- | ---: | ---: | ---: |
| Aloha Content A/B1/B2/B3-A/B3-B/B3-C | 275 | 226 | 32 |
| Area presentation | 23 | — | 11 |
| Hero + polish | 48 | — | 17 |
| Media Gallery + UI polish | 41 | — | 13 |
| Beaches + guarded migration | 32 | — | 10 |

Проверены 0/1/4/5/8/12 карточек, все редакторские операции через настоящие Gutenberg/Media Library/FocalPointPicker, порядок и сохранение/reopen, отсутствие invalid blocks. Проверены portrait/landscape/square images, 4:3/object-fit/object-position, deleted attachment/text fallback, безопасный HTML/URL, нативные ссылки, multiple carousels и уникальные controls IDs. Миграционные проверки включают сохранность окружающих байтов/meta, revision, stale backup, неоднозначную структуру, отличающийся alt, свободный HTML и идемпотентность.

Frontend проверен на **1440, 1024, 768, 390, 320px**, с длинным описанием и **200% текста**, без горизонтального overflow страницы. Пройдены Previous/Next, горизонтальный mouse wheel, ArrowLeft/Right, Home/End, настоящий touch swipe через Chromium CDP, reduced motion и обычная плавная прокрутка. Без JavaScript доступны все 12 SSR cards, native ArrowRight scrolling и действительная ссылка. В редакторе все шесть icon controls сохраняют один ряд на пяти ширинах.

В ходе подготовки тестов исправлена обработка отсутствующих текстовых attributes без PHP Notice/Deprecated. Отдельно устранены проблемы самого стенда: fixture не использует attachment, намеренно удалённый Area regression; вход браузера направлен в список Area, чтобы Dashboard не создавал посторонний Quick Draft. No-JS тест использует штатные ArrowRight вместо неподдерживаемого горизонтального End; тест состояния кнопки ожидает обновление следующего animation frame. Финальный прогон выполнен после этих исправлений, без повторов для маскировки ошибок.

PHP debug log изолированного стенда **0 bytes**, JavaScript page errors отсутствуют. Cleanup подтвердил сохранность исходного контента/настроек, все fixtures/attachments удалены. После завершения временные Docker containers/volumes удалены; остались только основное локальное WordPress и его БД. PHP/JS/Bash syntax и `git diff --check` прошли.

Скриншоты и browser evidence находятся только в `.local/evidence/m1-5-a3a/`:

- Mauna Lani: [1440](../.local/evidence/m1-5-a3a/mauna-lani-beaches-1440.png), [1024](../.local/evidence/m1-5-a3a/mauna-lani-beaches-1024.png), [768](../.local/evidence/m1-5-a3a/mauna-lani-beaches-768.png), [390](../.local/evidence/m1-5-a3a/mauna-lani-beaches-390.png), [320](../.local/evidence/m1-5-a3a/mauna-lani-beaches-320.png), [200% текста](../.local/evidence/m1-5-a3a/mauna-lani-beaches-text-200.png), [Gutenberg](../.local/evidence/m1-5-a3a/mauna-lani-beaches-editor.png).
- Изолированный стенд: [четыре карточки](../.local/evidence/m1-5-a3a/beaches-grid-four-1440.png), [восемь карточек](../.local/evidence/m1-5-a3a/beaches-carousel-eight-1440.png), [mobile carousel](../.local/evidence/m1-5-a3a/beaches-carousel-eight-390.png), [keyboard / 12 cards](../.local/evidence/m1-5-a3a/beaches-carousel-keyboard.png), [touch](../.local/evidence/m1-5-a3a/beaches-touch.png), [200%](../.local/evidence/m1-5-a3a/beaches-text-200.png), [editor](../.local/evidence/m1-5-a3a/beaches-editor.png), [focal point](../.local/evidence/m1-5-a3a/beaches-editor-focal.png).
- Reports: `beaches-server-report.json`, `beaches-browser-report.json`, остальные regression reports, `main-beaches-browser-report.json`, `main-beaches-preservation-report.json`, migration/repeat JSON и `main-autosave-preservation.json` в том же каталоге.

Git: A.2.1 сохранён в `2875f6a`; изменения Beaches и этот отчёт оставлены локальным незакоммиченным diff в новой ветке. Push/deployment не выполнялись.

## Файлы

- Тема: `blocks/beaches/block.json`, `editor.js`, `render.php`; `inc/beaches.php`; `assets/css/beaches.css`; `assets/js/beaches.js`.
- Подключение блока: `functions.php`; версия: `style.css`; пустой Beaches slot: `inc/area-patterns.php`; скрытие пустой секции с сохранением legacy cards: `assets/css/area.css`.
- Проверки и перенос: `scripts/beaches-check.php`, `beaches-browser.cjs`, `area-beaches-migration.php`, `migrate-area-mauna-lani-beaches.php`; новый opt-in `CONTENT_BEACHES_TEST=1` существующего runner; адаптация Area reference/browser fixtures к новому starter.
- Hero, Media Gallery, shared dialog, Aloha Content и его маршруты в этом этапе не изменены.

## Ограничения и отличия от reference

- Макет содержит неподтверждённые descriptions/travel times и placeholders. Они не превращены в новые факты или фиктивные Guide-статьи. Ссылки выбираются менеджером вручную.
- В локальной Mauna Lani сохранены пользовательские фотографии, которые отличаются от пустых слотов reference. При отсутствии фото показывается текстовая карточка, а не пустая декоративная плитка. Карточки не имитируют clickable link без URL.
- Carousel, empty/deleted-image states и mobile layouts — новые состояния; отдельного финального mobile PNG Area нет. Desktop grid сохраняет исходную геометрию. Длинные описания не обрезаются и могут увеличивать высоту ряда; редактору следует придерживаться краткого текста.
- Изображения используют текущий Library alt. Автономные inline alt/caption overrides старых Image blocks не переносятся молча: перед ручной заменой нужно согласовать их с attachment data. Происхождение/права reference-фотографий по-прежнему требуют редакционного подтверждения.
- Проверка наличия файла рассчитана на существующую локальную Media Library; стороннее media offload-хранилище не добавлено. Очень большие коллекции в редакторе не виртуализируются, искусственного лимита карточек нет.
- Firefox/WebKit отсутствуют в доступном runtime; новые браузеры не устанавливались. Браузерные проверки выполнены в Chromium.
- `scripts/check-references.py` сообщает прежнее отсутствие `aloha-architecture-sources.zip`: остальные **20 оригиналов совпадают** с manifest, изменённых оригиналов нет. ZIP не создавался, manifest не изменялся. Исходный handoff PDF остаётся прежним untracked файлом.

FAQ, Activities, Community Ka Milo, GuestyBridge и другие этапы не разрабатывались. Скриншоты, backups, DB snapshots и browser evidence остаются только в игнорируемой `.local/`, вне публичного Git.
