# M1.5-A.2.1 — Media Gallery UI Polish

23.10.2026. Aloha Hello **0.4.1**; Aloha Content **0.7.2**: небольшой подтверждённый REST guard fix, описанный ниже. Проверенный M1.5-A.2 сохранён отдельным локальным коммитом **`d86d239`** (`feat(aloha): add interactive Media Gallery block`). От него создана ветка **`codex/m1-5-a2-1-gallery-polish`**. Исходный handoff PDF, `.local/`, media, credentials и screenshots в коммит не включались. Push/deployment не выполнялись.

## Изменения frontend

Control просмотра всей коллекции расположен внутри пятой, правой нижней плитки, поверх лёгкого нижнего градиента. Для пяти фото видна подпись **View all photos (5)**; для 24 — **+19 photos**, с доступным названием **View all 24 photos**. Счётчик строится по действительным attachments после серверной проверки. Отдельной ссылки под мозаикой больше нет.

Control открывает коллекцию с первого кадра. Клик по самой фотографии открывает её собственный индекс, в том числе пятый кадр под overlay. Film control остаётся отдельной кнопкой на первом изображении. Вложенных интерактивных элементов нет: photo link и collection link — соседние элементы внутри figure. Для 1–4 фотографий control не добавляется, пустых плиток нет; все изображения остаются ссылками. Без JavaScript доступны оригиналы и полный `noscript` список.

Сохранена геометрия финального Area HTML/PNG: 1+4, `2fr 1fr 1fr`, две строки 260px, gap/radius 10px. Белая надпись с градиентом соответствует уточнению этого задания; исходный PNG использует светлую подложку счётчика. Mobile сохраняет ведущий кадр и две колонки, при увеличении текста минимальная высота малых плиток растёт, чтобы надпись оставалась доступной.

## Gutenberg

В каждой карточке один горизонтальный ряд шести native WordPress `Button` + `Tooltip`: **Move earlier**, **Move later**, **Make lead image**, **Replace photo**, **Edit image details**, **Remove from gallery**. Использованы штатные Dashicons; `editorStyle` подключает Core `wp-components` и `dashicons` внутри iframe, только в редакторе. Новых frontend библиотек/шрифтов нет.

Кнопки 36×36px, с aria-label/title и focus-visible. Первый Move earlier и последний Move later отключены. Главное фото отмечено рамкой, **Lead image** и состоянием звезды. Колонки карточек адаптируются под минимальную ширину ряда, отдельные кнопки не переносятся. Remove меняет только галерею; файл остаётся в Media Library, что явно объяснено в панели. Прежний attachment editor и предупреждение об общих alt/caption сохранены.

`ids` остаётся единственным редакционным массивом; формат block attributes не менялся. Сохранённые страницы не пересохранялись ради новой разметки/стилей. Общие `media-dialog.js`, `media-dialog.css`, Hero и его media fields не менялись.

## GLightbox 3.3.1: решение

**Сохранить действующий native dialog.** Zoom/pan — полезное дополнение GLightbox, однако текущие требования уже покрыты. Миграция сейчас увеличит зависимости и объём accessibility/интеграционных проверок без необходимого изменения поведения. Библиотека не установлена и не подключена; выполнены только чтение закреплённых исходников и измерение файлов в игнорируемой `.local/`.

Проверена стабильная **v3.3.1**, отмеченная Latest в [официальных releases](https://github.com/biati-digital/glightbox/releases). Beta 4 не рассматривается. Функции и параметры проверены в [README v3.3.1](https://github.com/biati-digital/glightbox/blob/v3.3.1/README.md), реализация — в [закреплённом dist source](https://raw.githubusercontent.com/biati-digital/glightbox/v3.3.1/dist/js/glightbox.js). Ниже выводы по коду GLightbox, а не результаты его browser QA на Aloha.

| Критерий | Aloha native dialog | GLightbox 3.3.1 |
| --- | --- | --- |
| Фото/YouTube | Фото и прямой lazy nocookie iframe | Фото и видео; video module подключает Plyr 3.6.12 JS/CSS, по умолчанию с CDN |
| Zoom/swipe | Touch swipe; browser zoom; отдельного image zoom/pan нет | Image zoom/pan, touch и mouse drag |
| Keyboard | Enter, Tab/Shift+Tab, arrows, Escape; проверены на Aloha | Keyboard handler для arrows/Escape/Tab; отдельной ветки Shift+Tab в нём нет, требуется дополнительный QA |
| Focus | Native inert/modal + явный возврат opener | Custom `data-taborder`/`aria-hidden`; явного восстановления opener в `close()` не обнаружено, нужен адаптер |
| Видео при закрытии | Iframe удаляется, Hero получает событие | Player pause и удаление modal; проверить повторное открытие/cleanup Plyr в интеграции |
| Повторные открытия | Проверено, включая быстрый Escape → Enter | Есть lifecycle и closing guard; интеграционный QA ещё не выполнен |
| Несколько галерей | Контекстный JSON и один shared dialog | `data-gallery`/elements grouping |
| Lazy loading | Только активный большой кадр после открытия | Preload соседних slides по умолчанию; для текущей политики нужен `preload:false` и проверка сетевых запросов |
| Hero | Действующая общая система | Потребуется единый адаптер также для Hero, с сохранением loop/manual pause и `aloha-media-change` |
| Accessibility | Проверены native focus, caption scrolling, alt, reduced motion | Нужны проверки фокуса, описаний, reduced motion, inert background и iframe; декларация keyboard support не заменяет их |
| Обновления | Два небольших собственных файла и существующие регрессии | Пин версии, license, CSS skin, player assets, обновление адаптера и повторный QA |

Размеры измерены для точного тега v3.3.1 и файлов темы на момент задания. Gzip рассчитан локально (`gzip` level 9, mtime 0); это сравнение payload, не измерение скорости браузера. Aloha передаёт текущий неминифицированный source; GLightbox — minified dist.

| Файлы | Bytes | Gzip bytes |
| --- | ---: | ---: |
| Aloha `media-dialog.js` | 6 229 | 2 281 |
| Aloha `media-dialog.css` | 2 472 | 828 |
| GLightbox min JS | 56 343 | 15 279 |
| GLightbox min CSS | 13 749 | 2 555 |
| Aloha JS + CSS | **8 701** | **3 109** |
| GLightbox JS + CSS, без Plyr | **70 092** | **17 834** |

[MIT license GLightbox](https://github.com/biati-digital/glightbox/blob/v3.3.1/LICENSE.md) разрешает коммерческое использование, модификацию и распространение при сохранении copyright/license notice. Этот тип permissive license совместим с GPL темы; [FSF относит Expat/MIT к GPL-compatible](https://www.gnu.org/licenses/license-list.html#Expat). Технического лицензионного препятствия для темы нет.

Если image zoom станет отдельным утверждённым требованием, ориентир миграции — **2–3 человеко-дня**: единый photo/film/Hero adapter, локальные pinned assets/skin, затем keyboard/touch/a11y/performance регрессии. Это предварительная оценка, без Community Template и новых media settings. Параллельную вторую систему модальных окон оставлять не следует.

## Выявленный REST guard defect

Первый полный прогон остановился на существующем Hero assertion: массив в `aloha_area_film_label` возвращал Core `rest_invalid_type`/400, но уже после изменения `post_modified`/`post_modified_gmt`. Целевой диагностический прогон подтвердил именно эти поля и вход `["bad"]`. Прежний тест мог пройти в пределах одной секунды, когда даты совпадали.

В Aloha Content `rest_pre_insert_area` добавлена ранняя проверка string для Film Label перед записью, как у уже существующих video/film URL guards. Валидные строки, очищение, sanitization, revisions, права и маршруты не менялись; данных в основной БД не обновляли. Версия плагина — 0.7.2. Серверный тест теперь пересекает clock tick перед каждым неверным запросом и выводит диагностические поля при отказе, не ослабляя assertion. Это единственное изменение вне UI галереи, необходимое по обнаруженной регрессии сохранности данных.

## Проверки

На основной Mauna Lani browser QA прошёл на **1440/1024/768/390/320**, включая **320px / 200% текста**. В Gutenberg **157 валидных блоков**, IDs `70,71,69,73,72`, `pageErrors: []`. Публичный body SHA-256 остался `50f308929d1d15e9dc222212307944223cf876ab7d16fac4fc4babd8477b9f3b`.

Read-only сравнение основной БД: все body/editorial fields **79 прежних post rows** сохранены, опубликованная Area 68 полностью неизменна. Нет добавленных/удалённых записей. Сохранены postmeta, attachments, Navigation, terms, permalink/rewrite/shell settings; исключён только native `_edit_lock`. У уже существовавшего Core autosave 88 обновились даты, а его содержимое осталось прежним; пользовательский autosave не удалялся и не восстанавливался поверх опубликованной записи.

Финальный полный прогон **прошёл**, `.local/content-test-L8OQRv`. Команда: `DOCKER_CONTEXT=orbstack CONTENT_GALLERY_TEST=1 CONTENT_BROWSER_TEST=1 CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-a2-1 ./scripts/test-aloha-content.sh`, с уже доступным bundled Playwright через `PLAYWRIGHT_MODULE`.

| Набор | Server | HTTP | Browser |
| --- | ---: | ---: | ---: |
| Media Gallery | **41** | — | **13** |
| Hero + polish + усиленный invalid-media guard test | **48** | — | **17** |
| Area presentation | **23** | — | **11** |
| Aloha Content models/routes/overview | **275** | **226** | **32** |

Все browser reports: без page errors. PHP debug log **0 bytes**. PHP/JS/Bash syntax и `git diff --check` прошли. Cleanup подтвердил исходный baseline; fixtures/media и временные контейнеры/тома удалены. Ошибочные inputs проверены с интервалом между clock ticks, без подмены assertions. Отдельные target logs и исходный diagnostic failure сохранены локально.

Скриншоты и отчёты — `.local/evidence/m1-5-a2-1/`, вне Git. Проверены пять viewport: 1440, 1024, 768, 390, 320px; коллекции 1/4/5/6/24 и прежние 0/2/12, CTA, индексы, keyboard, YouTube, без JS, шесть editor actions, сохранение/reopen, реальные glyphs и размещение toolbar.

Скриншоты: [мозаика Mauna Lani 1440](../.local/evidence/m1-5-a2-1/mauna-lani-gallery-1440.png), [mobile 390](../.local/evidence/m1-5-a2-1/mauna-lani-gallery-390.png), [200%](../.local/evidence/m1-5-a2-1/mauna-lani-gallery-text-200.png), [Gutenberg](../.local/evidence/m1-5-a2-1/mauna-lani-gallery-editor.png), [toolbar Mauna Lani](../.local/evidence/m1-5-a2-1/mauna-lani-gallery-toolbar.png), [toolbar 320](../.local/evidence/m1-5-a2-1/gallery-toolbar-320.png), [24 photos](../.local/evidence/m1-5-a2-1/gallery-24-1440.png). Photo/film dialog и ещё четыре размера toolbar также сохранены в evidence.

## Файлы

Галерея: `inc/media-gallery.php`, `blocks/media-gallery/editor.js`, `block.json`, `assets/css/media-gallery.css`, version в `style.css`. Тесты: `scripts/media-gallery-check.php`, `media-gallery-browser.cjs`. Подтверждённый guard fix: `plugins/aloha-content/inc/area-media.php`, version в `aloha-content.php`, усиленный `scripts/area-hero-check.php`. Остальная модель данных/маршруты не менялись.

## Ограничения

GLightbox не запускался в браузере Aloha и не объявляется протестированной заменой. Текущий native viewer по-прежнему не реализует отдельный image zoom/pan. Ограничение клавиатуры внутри внешнего YouTube iframe сохраняется: player управляет своим фокусом, Close доступен после возврата Tab. YouTube lifecycle QA использует browser network test double, без изменения сохранённой film URL. Firefox/WebKit отсутствуют в доступном runtime.

Beaches, Activities, FAQ, Community, маршрутизация и GuestyBridge не изменялись. Оригинальные HTML/PNG и reference manifest сохранены.

`check-references.py`: прежнее отсутствие исторического `aloha-architecture-sources.zip`; остальные 20 оригиналов совпадают с manifest. ZIP не создавался, manifest не изменялся. Изменения полировки оставлены локально в новой ветке без push/deployment.
