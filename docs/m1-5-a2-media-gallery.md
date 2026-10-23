# M1.5-A.2 — Interactive Media Gallery

23.10.2026. Aloha Hello **0.4.0**, Aloha Content **0.7.1** (без изменений плагина). Ветка `codex/m1-5-a2-media-gallery` создана от сохранённой полировки Hero **`961cc53`**; M1.5-A.1 сохранён отдельно в `82dbfdf`. Push/deployment не выполняются.

## Архитектура

`aloha/media-gallery` — один динамический Gutenberg block API v3. `blocks/media-gallery/block.json` регистрирует единственный редакционный атрибут `ids`: упорядоченный массив integer attachment IDs. `save()` возвращает `null`; PHP SSR находится в `render.php` и `inc/media-gallery.php`. URL, alt, caption и responsive sizes каждый раз берутся из текущих данных WordPress. Второго массива в post meta, глобальных настроек, новых CPT/таблиц или внешних библиотек нет.

Общие `assets/css/media-gallery.css` и `assets/css/media-dialog.css` отделены от Area presentation: блок работает и в обычной Page, пригоден для будущего Community. Foundation CSS регистрируется на `init` до блоков, затем enqueued на frontend; это сохраняет зависимости при editor/REST SSR без PHP Notice. Shared script `media-dialog.js` обслуживает фотографии и прежние Hero/YouTube triggers одним native `<dialog>`. Hero loop и его pause/resume state machine не переписаны. Из `area.css` вынесены только общие стили диалога; старые Core Gallery стили сохранены.

В дальнейшем исправления SSR/CSS/JS применяются ко всем экземплярам без пересохранения записей. Starter Pattern `area-gallery` содержит новый пустой блок; ранее сохранённые Core Gallery автоматически не переписываются.

## Работа менеджера

Вставить **Media Gallery** через Gutenberg. **Select photos / Add photos** открывают стандартную Media Library с множественным выбором изображений. Первая фотография становится главной. Внутри выбранного блока **Manage photos** доступны **Earlier**, **Later**, **Make lead**, **Replace**, **Remove**. Количество не ограничено пятью; только публичная мозаика ограничена пятью плитками.

**Edit alt & caption** открывает небольшое окно с полями самого attachment. Сохранение проходит через стандартный Core Data/REST API с его nonce, capability и sanitization; данные обновляются в Media Library везде, где используется изображение. Удаление фото из галереи не удаляет файл из медиабиблиотеки. При отсутствии разрешения API сообщает ошибку. Сами изображения не становятся вложенными Image/Group blocks.

## Мозаика и доступ к коллекции

Desktop: `2fr 1fr 1fr`, две строки по **260px**, gap **10px**, radius **10px**, `object-fit: cover`. Главное изображение занимает левую часть на две строки, следующие четыре — правую сетку 2×2. При 1–4 фото сетка перестраивается без пустых слотов. На планшете до 1023px строки по 200px; ниже 768px главный кадр занимает всю ширину, остальные максимум четыре — две колонки. На телефоне главный кадр 240px, остальные по 140px.

Для 24 действительных attachments выводятся пять `<img>`, **+19 photos** и **View all photos (24)**. Счётчик вычисляется после удаления недействительных IDs из публичной коллекции. Последняя фотография открывает свой кадр, отдельный счётчик — первый скрытый. На сервере пропускаются удалённые/trash/non-image attachments и отсутствующие локальные файлы.

Мозаика использует WordPress `large`, `srcset`, `sizes`, lazy loading и async decoding. Большие изображения представлены только метаданными JSON; `src/srcset` получает один активный кадр после открытия dialog. Автоматического перелистывания и предварительной загрузки всех originals нет. Все плитки — настоящие ссылки на изображения; при отключённом JS отдельный `noscript` список предоставляет ссылки на всю коллекцию.

## Просмотрщик

Клик по фото и **View all photos** открывает общий native dialog: активное изображение, **Previous / Next**, фактический счётчик, caption из Media Library, **Close**. ArrowLeft/ArrowRight переключают кадры; Escape закрывает окно, включая проверенный быстрый повторный Enter после закрытия; фокус возвращается к конкретному opener. Native modality делает фон inert; прокрутка body блокируется, focus trap не реализуется второй независимой библиотекой. На touch предусмотрен горизонтальный свайп более 48px с проверкой направления. Ошибка загрузки показывает понятное сообщение и сохраняет переключение.

Ctrl/Cmd/Shift-click и middle-click сохраняют стандартное поведение ссылок. Reduced motion не включает дополнительных анимаций. Точная служебная provenance-пометка исходного seed не возвращается на frontend после Hero polish: она остаётся в Media Library, а обычные редакционные captions выводятся. Длинная подпись имеет явный keyboard focus и прокручивается ArrowUp/ArrowDown внутри ограниченной области; размеры dialog ограничены viewport.

## Film из Area

Для контекстной Area блок читает прежние `aloha_area_film_url` и `aloha_area_film_label`, используя тот же `Aloha\Content\area_youtube_id()` validator. Неверный/пустой URL не выводит кнопку. Отдельный film control расположен поверх главного кадра; обычный клик по изображению всегда открывает фото. YouTube iframe создаётся только по действию пользователя, удаляется при закрытии, уведомляет Hero прежним событием `aloha-media-change`.

Filter `aloha_media_gallery_film` — маленькая точка будущего расширения источника; новых Community fields сейчас нет. В обычной Page без интеграции film button отсутствует.

## Перенос Mauna Lani

Выполнен после успешного изолированного полного прогона. Исходная локальная Area **68** содержит одну Core Gallery в секции `aloha-area-gallery-section`, пять IDs **70, 71, 69, 73, 72**. Inline alt совпадает с Media Library, inline captions отсутствуют; источник/caption и Featured Image сохраняются.

`scripts/area-gallery-migration.php` формирует план только для одной однозначно найденной старой галереи. Он проверяет изображения и независимые inline overrides, затем заменяет точный исходный диапазон байтов. Другие секции не пересериализуются. Несовпадающий alt/caption, неоднозначность или недействительное изображение требуют ручной замены блока вместо автоматической перезаписи.

`scripts/migrate-area-mauna-lani-gallery.php` разрешён только для local `127.0.0.1:8088`, Area 68/mauna-lani, CLI и пользователя с `edit_post`. `plan` не пишет данные; `apply` требует заранее сохранённую копию полного body, совпадающую с текущим содержимым. Перед записью принудительно создаётся и проверяется native revision. Повторный запуск при новом блоке ничего не пишет. Массовой миграции или runtime save hook нет.

Применённый перенос: backup `.local/mauna-lani-gallery-post-content-before.html` (0600), исходная revision **86**, новый body SHA-256 `50f308929d1d15e9dc222212307944223cf876ab7d16fac4fc4babd8477b9f3b`. Исходный SHA-256 `7e8e093f035e2dc403bfb8c7742d3a03e3e844feb7feb58a49a339d2ec8bdead`. Повторный CLI apply вернул `changed:false`; новых миграционных записей при повторе нет.

Локальная [Mauna Lani](http://127.0.0.1:8088/big-island/mauna-lani/) использует новый блок. [Gutenberg Area 68](http://127.0.0.1:8088/wp-admin/post.php?post=68&action=edit) показывает **157 валидных blocks**, IDs `70,71,69,73,72`, без invalid/missing blocks. Hero, пользовательский video attachment 84, film URL, Featured Image 69 и редакционные данные не заменялись.

Read-only SQL-сравнение после переноса/QA: **75 остальных post rows полностью неизменны**. У Area 68 изменены только `post_content`, `post_modified`, `post_modified_gmt`. Содержимое вокруг заменённого диапазона совпадает побайтно. Новые записи — только revisions **86/87** этой Area. Все прежние postmeta сохранены, кроме native `_edit_lock`; новые meta относятся только к новым revisions. Attachments, Navigation, terms/relationships, permalink/rewrite signature, Reading и shell settings совпадают с baseline. Новых Pages, Area, Community или media в основной БД нет. Повторный apply не создал третью revision. За период миграции и QA в основной PHP-лог не добавилось сообщений (**0 новых bytes**); исторический лог не очищался.

На основной Mauna Lani дополнительно проверены пять viewport и **320px/200% текста**, film control помещается в главной плитке, photo dialog открывается с клавиатуры. Browser report: `pageErrors: []`, `text200: true`. Внешний YouTube заменялся только на уровне browser network test double; сохранённый пользовательский URL не менялся.

## Проверки и visual QA

Финальный полный прогон **прошёл**, `.local/content-test-hhwSZn`. Команда: `DOCKER_CONTEXT=orbstack CONTENT_GALLERY_TEST=1 CONTENT_BROWSER_TEST=1 CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-a2 ./scripts/test-aloha-content.sh` с `PLAYWRIGHT_MODULE`, указывающим на уже установленный bundled Playwright. Новые браузеры/пакеты не устанавливались.

| Набор | Server | HTTP | Browser |
| --- | ---: | ---: | ---: |
| Aloha Content A/B1/B2/B3-A/B3-B/B3-C | 275 | 226 | 32 |
| Area presentation | 23 | — | 11 |
| Hero + production polish | 48 | — | 17 |
| Media Gallery + guarded migration | 32 | — | 11 |

После добавления явного focus к длинной caption отдельно повторён полный целевой gallery/Area прогон `.local/content-test-qVjbPL`: **32 gallery server + 11 gallery browser + 23 Area server**, keyboard ArrowDown прокручивает caption на 390px; PHP log 0 bytes.

JavaScript page errors отсутствуют во всех browser reports. PHP debug log **0 bytes**, runner теперь отклоняет также Notice/Deprecated. Fixtures, attachments, временные контейнеры/тома удалены; cleanup подтвердил исходный baseline контента/settings. PHP/JS/Bash syntax и `git diff --check` прошли.

Проверены 0/1/2/4/5/6/12/24 фотографии на сервере и в браузере; размеры **1440, 1024, 768, 390, 320**; видимая сетка 1+4, фактические counts, multiple galleries, native Media Library select/append/replace/remove/reorder, сохранение и повторное открытие, общие alt/caption данные. Проверены keyboard Enter/Tab/arrows/Escape/focus return, настоящий touch swipe через Chromium CDP, 200% текста, длинная подпись, empty/deleted/missing image, link к original с JS disabled, отсутствие eager full-image/YouTube requests.

Во время разработки исправлены два обнаруженных дефекта: зависимость foundation CSS при admin/REST SSR и queued native `close` после быстрого `Escape → Enter`. Последний сценарий оставлен в browser regression; предыдущие diagnostic logs сохранены локально. Финальный прогон выполнен после обоих исправлений. Firefox/WebKit отсутствуют в доступном runtime; дополнительные smoke tests ими не выполнялись.

Скриншоты:

- Mauna Lani: [1440](../.local/evidence/m1-5-a2/mauna-lani-gallery-1440.png), [390](../.local/evidence/m1-5-a2/mauna-lani-gallery-390.png), [320](../.local/evidence/m1-5-a2/mauna-lani-gallery-320.png), [Gutenberg](../.local/evidence/m1-5-a2/mauna-lani-gallery-editor.png), [photo dialog](../.local/evidence/m1-5-a2/mauna-lani-photo-dialog.png), [film dialog](../.local/evidence/m1-5-a2/mauna-lani-film-dialog.png), [сохранённый Hero](../.local/evidence/m1-5-a2/mauna-lani-hero-preserved.png).
- Isolated QA: [5 photos](../.local/evidence/m1-5-a2/gallery-five.png), [24 photos desktop](../.local/evidence/m1-5-a2/gallery-24-1440.png), [24 photos mobile](../.local/evidence/m1-5-a2/gallery-24-390.png), [lightbox](../.local/evidence/m1-5-a2/gallery-lightbox.png), [touch/mobile](../.local/evidence/m1-5-a2/gallery-mobile-lightbox.png), [200% текста](../.local/evidence/m1-5-a2/gallery-text-200.png), [editor](../.local/evidence/m1-5-a2/gallery-editor.png), [film](../.local/evidence/m1-5-a2/gallery-film.png).

Все screenshots, JSON и logs хранятся вне публичного Git: `.local/evidence/m1-5-a2/`. Технические fixtures из 24 attachments создаются только в disposable WordPress :8091. Мозаика сверяется с финальными HTML/PNG Area; несуществующие исходные фотографии не придумываются.

## Ограничения и границы

- Сохраняется прежнее ограничение cross-origin YouTube player: его собственный focus/keyboard обрабатываются iframe; Close доступен после возврата Tab на родительский dialog. Сетевой lifecycle проверяется локальным iframe test double, без утверждения о воспроизведении внешнего сервера.
- Большие коллекции сохраняются без искусственного лимита, однако управление сотнями thumbnails в редакторе не оптимизировано виртуализацией. Для согласованного диапазона до 24 это не требуется.
- Проверка наличия файла относится к локальной Media Library; стороннее offload-хранилище не внедрялось и не тестировалось.
- Inline overrides старых Image blocks автоматически не переносятся в отдельное хранилище: перед ручной заменой согласовать их с данными Media Library.
- PNG показывает статический счётчик `+24`; реализация выводит фактическое оставшееся количество. Lightbox, состояния малых коллекций и mobile layouts — новая функциональность, отдельного финального mobile PNG Area нет.
- Прежняя исходная Mauna Lani фотография manta остаётся вместо отсутствующей turtle reference; происхождение и права медиа требуют редакционного подтверждения, новые фотографии не создавались.

Beaches, FAQ, Activities, Community Template, GuestyBridge и маршрутизация не разрабатывались.

## Файлы

Тема: `blocks/media-gallery/block.json`, `editor.js`, `render.php`; `inc/media-gallery.php`; `assets/css/media-gallery.css`, `media-dialog.css`; shared `assets/js/media-dialog.js`; подключение/ранняя регистрация foundation в `functions.php`; enqueue общего dialog в `inc/area.php`; вынос dialog styles из `area.css`; только gallery slot в `inc/area-patterns.php`; version в `style.css`.

Тесты: `scripts/media-gallery-check.php`, `media-gallery-browser.cjs`, `area-gallery-migration.php`, `migrate-area-mauna-lani-gallery.php`; расширение существующего runner, адаптация Area starter/browser fixtures к новому блоку. Документация — этот отчёт и ссылка из исходного M1.5-A.

`check-references.py` сохраняет прежнее ограничение: отсутствует исторический `aloha-architecture-sources.zip`; остальные **20 оригиналов совпадают** с manifest. ZIP не создавался, manifest/HTML/PNG не менялись. Исходный handoff PDF остаётся прежним untracked файлом. Изменения A.2 оставлены локально в отдельной ветке, без push/deployment.
