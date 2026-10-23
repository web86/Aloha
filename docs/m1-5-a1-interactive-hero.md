# M1.5-A.1 — Interactive Area Hero

23.10.2026. Aloha Hello **0.3.1**, Aloha Content **0.7.0**. Ветка `codex/m1-5-a1-interactive-hero`. Перед началом проверены и сохранены изменения M1.5-A отдельным локальным коммитом **`9a6ac66`** (`feat(aloha): add editable Area presentation and starter patterns`). Исходный handoff PDF, `.local/`, медиа и credentials в коммит не включались. Push/deployment не выполняются.

## Архитектура и дизайн

Расширен существующий `aloha/area-hero`; новый несовместимый блок не создавался. Нативный `templates/single-area.html`, тело записей, starter и Patterns остаются прежними. Title/excerpt/Featured Image берутся из текущей Area, без Mauna Lani в общей разметке. Header/Footer и маршрутизация не изменены.

Проверены финальный `AreaDesign.dc.html` и PNG 1440×7900 из `Design v2 — Area: Mauna Lani`: Hero около 720px, Instrument Serif/Outfit, breadcrumbs, крупный title, нижняя строка excerpt + film + CTA. Для действительного film URL добавлена кнопка **Watch the 2-min area film** с круглым play control. Все кнопки — настоящие доступные элементы, без пустых ссылок. Широкий Hero сохраняет 720px; на меньших экранах минимумы прежнего Area CSS — 640/580px, высота растёт при переносах. Poster/video используют `object-fit:cover; object-position:center`.

Затемнение привязано к фактической верхней границе breadcrumbs через один `ResizeObserver`; на 200% текста тёмная область остаётся под всей копией. Это сохраняет более светлую верхнюю часть фотографии и обеспечивает читаемость на светлых кадрах. В отсутствие JS используется CSS gradient fallback. Декоративные движения/переходы не добавлялись.

## Поля Area и права

Регистрация принадлежит Aloha Content: `inc/area-media.php`, вызов после регистрации CPT. Нет настроек в глобальном шаблоне, новых options, таблиц, CPT или миграции контента.

| Post meta | Тип/default | Проверки |
| --- | --- | --- |
| `aloha_area_loop_video_id` | integer / `0` | Существующий attachment с MIME `video/mp4` или `video/webm`; отрицательные/дробные/нечисловые ID и другие типы не принимаются |
| `aloha_area_film_url` | string / `''` | Только YouTube video URL с точным разрешённым host и 11-символьным video ID; сохраняется канонический HTTPS watch URL |

Оба поля: `single`, `show_in_rest`, `revisions_enabled`, auth callback `current_user_can('edit_post', post_id)`. REST дополнительно применяет штатную схему, cookie nonce и Core permissions. Неверное значение возвращает понятный `WP_Error` **до записи post fields**, а не молча очищает опубликованные настройки. Trusted low-level Meta API санитизирует неверные значения до пустого состояния; после удаления attachment renderer повторно проверяет его тип/существование.

Допустимы обычные `youtube.com/watch?v=…`, `youtu.be/…`, `youtube.com/embed/…`, `shorts/…`, `live/…`; `www`/`m` на youtube.com. Параметры tracking/start и fragments не хранятся; произвольные домены, playlists, iframe HTML, userinfo и нестандартные ports отклоняются. URL строится из проверенного ID, не из пользовательского iframe-кода.

## Gutenberg: Area Media

В **Areas → Edit → правая панель Area Media**:

1. **Choose background video** открывает стандартную Media Library, только MP4/WebM.
2. После выбора показываются название и native video preview с controls и `preload=none`.
3. **Replace background video** меняет файл; **Remove background video** очищает ID.
4. **YouTube film URL** — необязательная ссылка полной версии, без ручного ID/HTML.
5. Обычное Save сохраняет поля конкретной Area. Рекомендуется небольшой MP4/H.264 или WebM, короткий loop 15–20 секунд. Featured Image остаётся постером.

Панель использует `PluginDocumentSettingPanel`, `MediaUpload`, `MediaUploadCheck`, `TextControl`, Core editor/data stores. Она не появляется для других типов записей или при отключённом Aloha Content. Нет save hooks, вставки/синхронизации body или изменения Patterns.

Native REST/Gutenberg saves создают обычные Core revisions при изменении только этих meta. Core restore восстанавливает оба поля. Как и у других revisioned meta, отдельный trusted `update_post_meta()` сам по себе не создаёт revision: серверному редакционному импорту следует сохранять через post/REST API либо явно вызвать `wp_save_post_revision()`.

## Background loop и fallback

- При отсутствии loop остаётся Featured Image с native responsive `srcset/sizes`, `loading=eager`, `fetchpriority=high`.
- Если нет ни loop, ни Featured Image — текстовый Hero на фирменном фоне.
- Видео располагается над постоянно присутствующим fallback и под gradient/copy; `muted autoplay loop playsinline preload=none`.
- В исходном HTML нет video `src`/source. После загрузки страницы и idle callback JS проверяет видимость, motion и connection signals. Постер берётся из уже загруженного `img.currentSrc`, чтобы не скачивать ещё одну полноразмерную фотографию.
- При reduced motion, Save-Data, известной cellular-сети или `slow-2g/2g/3g` видео не загружается автоматически. Без Network Information API используются остальные проверки; неизвестный тип сети не угадывается по viewport.
- `IntersectionObserver` и document visibility приостанавливают loop вне видимой области/в скрытой вкладке. Без IntersectionObserver остаётся постер. Открытие film dialog также ставит фон на паузу.
- На `playing` видео становится видимым. На error или отказ `play()` остаётся постер/фирменный фон. Ошибка не запускает бесконечные повторы.
- Доступная кнопка **Pause background video / Play background video** позволяет остановить движение. Ручная пауза не отменяется скроллом. При изменении motion/connection preferences autoplay прекращается, `src` удаляется.

Нет стороннего плеера, preload больших видео, автоматически сгенерированного редакционного ролика или обращения к Guesty.

## Film dialog

`assets/js/media-dialog.js` — небольшой компонент для кнопок `[data-aloha-youtube="VIDEO_ID"]` и необязательного `data-aloha-media-title`. Эти же triggers сможет использовать будущая Gallery, но сама Gallery в этом этапе не менялась.

До клика нет dialog iframe, YouTube SDK, preconnect или запроса к YouTube. При явном открытии создаётся один `<dialog>` и один iframe на `youtube-nocookie.com/embed/{validated_id}`. Native `showModal()` даёт top layer и делает фон неактивным. Есть видимая кнопка Close, Escape в документе dialog и закрытие по backdrop; закрытие удаляет iframe, снимает scroll lock и возвращает фокус к trigger. При повторном открытии iframe создаётся заново, звук не остаётся в скрытом плеере.

Особенность native Chromium: Tab из iframe может переходить в интерфейс самого браузера (`document.activeElement === body`); фоновые элементы страницы при этом не активируются. Клавиши внутри cross-origin player принадлежат YouTube: Escape там может обрабатываться самим плеером. Для закрытия клавиатурой можно Tab/Shift+Tab вернуться к Close и нажать Enter/Escape. Не добавлялся собственный глобальный focus trap или перехват клавиатуры чужого iframe.

## Граница GuestyBridge

Сейчас Hero всегда использует **Explore the area → `#area-content`**, существующий раздел starter/сохранённой Mauna Lani. Нет счётчика `0`, несуществующего каталога, нового CPT `rental`, provider API или сохранённого числа домов.

Минимальный контракт будущей интеграции на стороне GuestyBridge:

```text
rental_count: integer > 0, рассчитанный по актуальной публичной подборке этого Area
rentals_available: boolean, подтверждающий существование этой подборки
rental_target: существующий URL или anchor фактически отображаемой подборки
```

Только при выполнении всех трёх условий renderer Hero сможет заменить label/target на **See N homes**. Отсутствующие, неизвестные или нулевые данные оставляют нейтральную кнопку. Значения не вводятся в Gutenberg и не сохраняются в Area meta. Получение/проверка подборки и публикация её адреса принадлежат будущему GuestyBridge; сам provider и контрактный endpoint/filter сейчас не реализованы.

## Основная локальная Mauna Lani

[Страница](http://127.0.0.1:8088/big-island/mauna-lani/) · [Редактор Area ID 68](http://127.0.0.1:8088/wp-admin/post.php?post=68&action=edit).

В проектных Area-ресурсах нет фонового видео или подтверждённой YouTube-ссылки. Оба поля у Area 68 оставлены пустыми. Нет фиктивного видео, случайного YouTube ID или новых тестовых записей в основной установке. Текущие Featured Image, title/excerpt, slug и body не перезаписывались.

## Проверки

Полный общий прогон завершился успешно: `.local/content-test-ljy2oY`, лог — `.local/evidence/m1-5-a1/integration.log`.

После финального экранирования названия файла в панели и уточнения keyboard/screenshot assertions повторён целевой smoke (`.local/content-test-UEltx1`): те же **38 server + 14 browser** прошли, включая настоящий Tab до film trigger, focus-visible и отсутствие пересечения pause/CTA при 200% текста. Лог — `final-smoke.log` в той же evidence-папке. Повторное read-only сравнение основной БД после последнего скриншота также подтвердило сохранность содержимого и настроек.

| Проверки | Результат |
| --- | --- |
| Новые Interactive Hero server checks | **38 passed**: типы/URL, REST, permissions, media-only revisions и restore, независимость записей, разметка и пустые состояния |
| Новые Interactive Hero browser scenarios | **14 passed**, JavaScript page errors отсутствуют |
| M1.5-A | **23 server + 11 browser passed**, в том числе starter, save/reopen, Gallery/FAQ/card edits, отсутствие invalid blocks |
| Прежние Aloha Content regressions | **275 server + 226 HTTP + 32 browser passed** |
| Theme switch / pattern update | Сохранённый контент не меняется |
| PHP log disposable WordPress | **0 bytes** |
| Cleanup | Fixtures и медиа удалены; baseline сохранён; тестовые контейнеры/тома отсутствуют |
| Static checks | PHP/JS/Bash syntax и `git diff --check` прошли |

Проверены 1440/1024/768/390/320, настоящее HTML5 playback, ручная pause/resume, остановка вне viewport, ошибка загрузки, имитация отказа autoplay, reduced-motion (включая изменение во время работы), Save-Data/cellular/slow-network signals, отсутствие iframe/YouTube requests до клика, cleanup iframe, focus return, Tab/Escape/Close/backdrop, no-video/no-image, неверный URL и нейтральный CTA. В Media Library действительно выбраны, сохранены, повторно открыты, заменены и удалены два отдельных видео.

На всех пяти ширинах и при 200% текста дополнительно рассчитан контраст малых breadcrumbs против худшего белого кадра с учётом фактического положения gradient: **5.56–5.74:1**. Это аналитическая проверка альфа-композиции CSS, а не аудит произвольного будущего видеоролика. На 320px и при 200% текста контрол паузы не пересекает CTA. Проверен focus-visible film trigger. Zoom проверен увеличением базового размера текста до 200%; отдельное изменение настройки масштаба браузера не эмулировалось. Browser QA выполнен в Chromium.

Основная локальная страница: **HTTP 200**, пять viewport без overflow, **162 валидных блока**, оба новых поля пустые. Read-only SQL-сравнение подтвердило неизменность всех **73** оставшихся post rows, включая пользовательские изменения Area 68 и существующего второго Area. Title/excerpt/slug/status/body, Featured Image и прочие редакционные meta, Navigation, terms, permalink/rules/signature, shell/Reading options сохранены. Новых записей или медиа в основной БД нет. При открытии Gutenberg Core обновил `_edit_lock` и удалил старый autosave **ID 78**, полностью совпадавший с уже опубликованными title/excerpt/body Area 68; обычные revisions сохранены. Эти штатные побочные действия редактора отдельно отражены в `main-data-report.json`, вместо заявления о побайтово неизменной всей БД.

Скриншоты и JSON reports сохранены в **`.local/evidence/m1-5-a1/`**, исключены из Git:

- Основная Mauna Lani: [1440](../.local/evidence/m1-5-a1/mauna-lani-1440.png), [1024](../.local/evidence/m1-5-a1/mauna-lani-1024.png), [768](../.local/evidence/m1-5-a1/mauna-lani-768.png), [390](../.local/evidence/m1-5-a1/mauna-lani-390.png), [320](../.local/evidence/m1-5-a1/mauna-lani-320.png), [редактор](../.local/evidence/m1-5-a1/mauna-lani-editor.png).
- Video fixtures: [desktop](../.local/evidence/m1-5-a1/hero-1440.png), [mobile](../.local/evidence/m1-5-a1/hero-320.png), [Area Media](../.local/evidence/m1-5-a1/hero-editor.png).
- Film dialog: [desktop](../.local/evidence/m1-5-a1/hero-modal-desktop.png), [mobile](../.local/evidence/m1-5-a1/hero-modal-mobile.png).
- Fallback/доступность: [video error](../.local/evidence/m1-5-a1/hero-video-error.png), [no media](../.local/evidence/m1-5-a1/hero-no-media.png), [reduced motion](../.local/evidence/m1-5-a1/hero-reduced-motion.png), [200% text](../.local/evidence/m1-5-a1/hero-text-200.png).

`scripts/check-references.py` выполнен. Единственная ошибка — ранее отсутствовавший `aloha-architecture-sources.zip`; остальные 20 доступных originals совпадают по SHA-256/размеру. Исходники и manifest не изменялись.

Запуск в прежнем disposable WordPress на 8091:

```bash
DOCKER_CONTEXT=orbstack CONTENT_HERO_TEST=1 CONTENT_BROWSER_TEST=1 \
  CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-a1 \
  PLAYWRIGHT_MODULE=/path/to/node_modules/playwright \
  ./scripts/test-aloha-content.sh
```

`CONTENT_HERO_TEST=1` включает M1.5-A suite. Для неё нужны исходные JPG в `.local/reference-area/assets` либо `ALOHA_AREA_MEDIA_DIR`. Новые Hero server tests дополнительно проверяют schema/types, invalid URL/ID, capabilities, REST error atomicity, native revision capture/restore и отсутствие eager iframe/ложного Rentals.

Browser test использует два маленьких, явно обозначенных colour-only WebM **только в disposable БД**, записанных через native Canvas/MediaRecorder. Это технические fixtures, не редакционные ролики и не файлы темы. YouTube requests в тестах перехватываются локальным player test double: внешние серверы не используются. Поэтому тест проверяет lifecycle/focus/embed URL, но не доступность реального YouTube-ролика, его продолжительность или сетевую политику пользователя.

## Файлы и ограничения

- Aloha Content: `aloha-content.php` (version/include/register), новый `inc/area-media.php`.
- Aloha Hello: `inc/area.php`, `assets/js/area-editor.js`, новые `assets/js/area-hero.js`, `assets/js/media-dialog.js`, расширение `assets/css/area.css`, version в `style.css`.
- Тесты: новые `scripts/area-hero-check.php`, `scripts/area-hero-browser.cjs`, opt-in интеграция в `scripts/test-aloha-content.sh`.
- Документация: этот отчёт и README.

Реального видео/полного фильма для Mauna Lani нет; основной локальный Hero остаётся постером. Нет `See N homes` до подтверждённого GuestyBridge. Copy и reference photos требуют прежней редакционной проверки. Pause control и усиленное затемнение — дополнения для доступности; film label использует формулировку задания. Утверждённого mobile Area reference по-прежнему нет. Header/Footer, Beaches, Activities, FAQ, Community template и Media Gallery не перерабатывались. Физические iOS/Android, Safari/WebKit и Firefox не проверялись; autoplay всегда зависит также от политики устройства/браузера и кодека файла.
