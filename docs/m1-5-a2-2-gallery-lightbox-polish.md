# M1.5-A.2.2 — Media Gallery interaction & lightbox polish

## Основа и дизайн

Предыдущие Community foundation / film / polish отправлены в `origin/main` до **`a1163db`** по прямому запросу пользователя. Новый этап выполняется от этого commit в **`codex/m1-5-a2-2-gallery-lightbox-polish`**, без push и deployment. Aloha Hello **0.7.3**; Aloha Content не менялся.

Сверены `макеты/Design v2 — Area Mauna Lani@1x.png`, `AreaDesign.dc.html` и `docs/design-inventory.md`. Финальный PNG сохраняет приоритет: мозаика **1+4**, `2fr 1fr 1fr`, две строки **260 px**, gap/radius **10 px**. Белый control находится в правой нижней плитке с отступами **16 px**; в PNG это pill с тёмным текстом. Текст и иконка уточнены новым заданием: **Show all N photos** с grid icon. Отдельного утверждённого modal state в Area reference нет; его компактная композиция реализована по критериям A.2.2.

## Мозаика

Все пять изображений при hover плавно увеличиваются до **1.035** за **420 ms** внутри прежнего обрезающего контейнера. Grid и размеры плиток не меняются. Эффект действует только при hover/fine pointer и `prefers-reduced-motion: no-preference`; на touch нет зависшего hover zoom, при reduced motion zoom отключён.

Пятая плитка получает лёгкий общий scrim **#16232A / 20%**. Поверх него белый control с тёмными Outfit **15 px / 600**, SVG grid icon **18 px**, минимальной высотой **44 px** и мягкой тенью. Desktop сохраняет pill и отступы 16 px; на mobile карточка имеет radius 10 px, меньшие padding/font и перенос текста. При 200% текста она остаётся внутри плитки, включая 320 px.

Label формируется в SSR из фактического количества валидных attachments: **Show all 5 photos**, **Show all 24 photos**, после удаления файла — обновлённое число. Старые `View all photos (5)` / `+19 photos` удалены. Control открывает полную коллекцию с первого кадра. Клик по фотографии пятой плитки вне control открывает именно пятый кадр. Два соседних интерактивных элемента не вложены друг в друга. Для 1–4 фотографий control отсутствует.

## Photo lightbox

Используется тот же **единственный native dialog**. Видимый текстовый заголовок убран из photo composition, но остаётся в DOM и задаёт доступное имя через `aria-labelledby`. Close, Previous и Next имеют SVG line icons **20 px**, hit areas **44 × 44 px**, aria-label/title и белый `focus-visible`.

Убраны светлая рамка и тяжёлая панель заголовка. Photo mode использует единый тёмный backdrop, прозрачный dialog, счётчик сверху и стрелки по краям изображения. Максимальная desktop ширина — **1360 px**, внешние отступы 24 px; на mobile — по 8 px. Image остаётся `object-fit: contain`, без crop. Пустой caption/footer не занимает места. Непустой caption имеет компактный шрифт и собственный scroll до **18dvh**, доступный клавиатурой.

У видеорежима сохранены прежние heading, текстовый Close, размеры и iframe shell. Photo-only CSS ограничен `.is-photo`; источник Area Hero и его поля/стили не редактировались. Переключение film → photo → film использует прежний lifecycle: iframe создаётся только по клику и удаляется при закрытии, фокус возвращается opener.

## Навигация и Embla

**Embla внутри lightbox не используется.** Текущий viewer уже умеет хранить активный индекс, работать с одной большой фотографией и управлять native dialog. Для требуемого polish достаточно небольшого расширения Pointer Events: mouse drag и touch swipe в обе стороны, pointer capture, порог **48 px**, преобладание горизонтального движения над вертикальным. Вертикальный жест и второй touch отменяют drag; pinch zoom и прокрутка caption сохраняются. Закрытие, новый кадр и повторное открытие сбрасывают capture/визуальное состояние.

Во время drag изображение слегка следует за указателем, с ограничением 64 px; при переключении применяется fade **180 ms / .85 → 1**. Reduced motion отключает эти визуальные эффекты, оставляя drag/swipe функциональным. Отдельного parallax нет. Новые библиотеки, slider instances и modal management не добавлены; существующий Embla Beaches не менялся.

ArrowLeft/ArrowRight, Enter, Tab/Shift+Tab, Escape, Close и возврат фокуса остаются нативными. Только активный большой кадр получает `src/srcset`; все 24 полноразмерных изображения заранее не загружаются. Без JS сохраняются ссылки на оригиналы и полный `noscript` список.

## Изменённые файлы

| Файл | Изменение |
| --- | --- |
| `theme/aloha-hello/inc/media-gallery.php` | Dynamic Show all N photos и SVG icon в прежнем SSR |
| `theme/aloha-hello/assets/css/media-gallery.css` | Маскированный hover zoom, dim fifth tile, white card и reduced motion |
| `theme/aloha-hello/assets/js/media-dialog.js` | Доступные icon controls и mouse/touch Pointer Events в прежнем dialog |
| `theme/aloha-hello/assets/css/media-dialog.css` | Компактный photo mode; film shell сохранён |
| `theme/aloha-hello/style.css` | Версия 0.7.3 |
| `scripts/media-gallery-check.php` | SSR assertions для нового динамического текста и icon |
| `scripts/media-gallery-browser.cjs` | Hover/reduced motion, compact chrome, drag и пять ширин с 200% текста |
| `README.md`, этот отчёт | Инструкция, evidence и результаты |

Block schema, attachment IDs, editor source/architecture, шаблоны, Mauna Lani Post Content и маршруты не менялись. Обновлённая разметка появляется через SSR без пересохранения страниц.

## Проверки

Проверки выполнены на одноразовом **WordPress 7.1.3 / PHP 8.3**, порт **8091**. Финальный целевой runner завершился с **exit 0**. Независимые Aloha Content / Area suites подтверждены общим regression run; после выявленного text-zoom дефекта отдельно повторены Gallery и общие dialog/Community suites.

| Набор | Server checks | Browser scenario groups |
| --- | ---: | ---: |
| Media Gallery | **41** | **15** |
| Area Hero / Area Media | **48** | **17** |
| Area presentation | **23** | **11** |
| Community foundation | **17** | **8** |
| Community film / shared dialog | **39** | **14** |

Также пройдены **88 Aloha Content model checks**, **75 Area routing**, **22 Community routing**, существующие Guide/Investor и Routing Overview suites, **226 HTTP checks** маршрутов. Native editor, связи, revisions и сохранность content проверены прежними suites. В подтверждающих browser reports нет page errors; оба PHP debug logs — **0 bytes**. PHP/JS/Bash syntax и `git diff --check` пройдены.

Целевой QA уже подтвердил 5/24 фото, индекс пятой фотографии и открытие полной коллекции, независимые gallery instances, masked hover без сдвигов, white card, keyboard focus и arrows, native modal containment/Escape/focus return, swipe, mouse drag в обе стороны и reduced motion. Проверены удалённый attachment, failed image, 0/1/2/4/5/6/12/24 фото и no-JS originals. Фото-клик и отдельная film button не конфликтуют; YouTube iframe после Close удаляется.

Браузерный тест проверяет **1440 / 1024 / 768 / 390 / 320 px**, compact photo controls и отсутствие overflow; white card дополнительно на всех пяти ширинах при **200% текста**. Native Gutenberg вставка/выбор 24 фото, все шесть management actions, alt/caption, Save/reopen и valid blocks входят в прежний suite.

Для диагностики выявлена унаследованная минимальная высота theme button **52 px**, которая мешала заданным компактным 44 px. Она переопределена только в photo mode; общая кнопочная система не менялась. Общий regression run затем выявил обрезание white card на **768 px / 200% текста**. Исправлено через меньшие tablet padding и минимальную высоту строк в `rem`: стандартные 260/200/140 px сохранены, но при увеличенном тексте строки могут расти. Финальный Gallery suite проверяет 200% на всех пяти ширинах и прошёл. Уже пройденные независимые проверки не повторялись. [Общий log с диагностикой](../.local/evidence/m1-5-a2-2/regression.log), [успешный целевой log](../.local/evidence/m1-5-a2-2/integration.log).

Общий прогон использует штатный `scripts/test-aloha-content.sh` с `CONTENT_GALLERY_TEST=1 CONTENT_COMMUNITY_FILM_TEST=1 CONTENT_BROWSER_TEST=1`. На этом host применена игнорируемая копия `.local/gallery-polish-tests-static-host.sh`: единственное отличие orchestration — private DB IP и `extra_hosts: db:IP` у одноразовых WP/CLI для обхода ранее подтверждённой нестабильности OrbStack DNS. Целевой повтор — `.local/gallery-polish-tests-target.sh`, который выполняет те же Gallery/Community suites и cleanup, без повторения уже пройденных независимых наборов. Assertions не отключались; основной Docker Compose не менялся. Копии для аудита: [общий runner](../.local/evidence/m1-5-a2-2/test-runner-static-host-reference.sh), [целевой runner](../.local/evidence/m1-5-a2-2/test-runner-target-reference.sh).

Все тестовые fixtures/media, контейнеры, volumes и credentials удалены. Основная база сравнена read-only: все post rows, editorial postmeta, attachment IDs, Navigation, terms и Reading/permalink/routing/shell settings совпадают. Единственное сырое отличие — значения двух уже существовавших нативных **`_edit_lock`** у post 68/102; записи и редакционный контент не изменены. Locks не откатывались. SHA-256 снимка с маскированными только этими служебными значениями: **`b671d2be9c70339925e414c048b5850fefa38bb899a1fc015f0c879c66ac172e`** до/после. Основной Home — **HTTP 200**; активны только прежние WP/DB на 8088.

## Скриншоты и evidence

Артефакты сохраняются локально вне Git в `.local/evidence/m1-5-a2-2/`:

- [Мозаика — 5 фото](../.local/evidence/m1-5-a2-2/gallery-five.png), [24 фото](../.local/evidence/m1-5-a2-2/gallery-24-1440.png), [hover](../.local/evidence/m1-5-a2-2/gallery-hover.png).
- [Photo modal desktop](../.local/evidence/m1-5-a2-2/gallery-modal-1440.png), [mobile 390](../.local/evidence/m1-5-a2-2/gallery-modal-390.png), [mobile 320](../.local/evidence/m1-5-a2-2/gallery-modal-320.png).
- [Modal до/после](../.local/evidence/m1-5-a2-2/gallery-modal-comparison.png). «До» отрисован точными `media-dialog.js/css` из `a1163db` на том же фото; это реальный render, без ретуши.
- [320 / 200% текста](../.local/evidence/m1-5-a2-2/gallery-24-text200-320.png), [Gutenberg](../.local/evidence/m1-5-a2-2/gallery-editor.png), [video dialog](../.local/evidence/m1-5-a2-2/gallery-film.png).
- [Server report](../.local/evidence/m1-5-a2-2/media-gallery-server-report.json), [browser report](../.local/evidence/m1-5-a2-2/media-gallery-browser-report.json), [integration log](../.local/evidence/m1-5-a2-2/integration.log), [основная БД](../.local/evidence/m1-5-a2-2/main-preservation-report.json).

## Ограничения

- Проверен Chromium с touch emulation; физические iOS/Android и Safari/Firefox не входят в gallery interaction QA.
- Отдельного image zoom/pan и инерционного slider нет. Горизонтальный drag/swipe переключает кадр, browser pinch zoom сохранён.
- YouTube QA использует явно подписанный network test double только в изолированной среде. Проверены URL/lifecycle/отсутствие запросов до клика; доступность реального внешнего видео не проверялась.
- Исходные design photos в тестах — supplied reference assets, без утверждения их фактического соответствия объектам. Новые реальные media или видео в основной базе не создавались.
- A.2.2 не отправлен в main и не развёрнут. GuestyBridge и другие компоненты не начинались.
