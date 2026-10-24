# M1.5-A.4 — Guide Highlights

## Визуальные требования до реализации

Эталон: оригинальный `макеты/Design v2 — Area Mauna Lani@1x.png` (1440×7900) и `AreaDesign.dc.html` из финального ZIP, строки 181–193. Wireframe не используется для дизайна.

| Элемент | Финальный PNG / HTML |
| --- | --- |
| Композиция | 2 крупные карточки, затем 6 компактных в двух рядах по 3 |
| Контейнер при 1440px | 1280px border-box, внутренние gutters 40px; полезная ширина 1200px, x=120…1320 |
| Featured | 588px × 330.75px, aspect-ratio 16:9; две равные колонки |
| Additional | 384px × 288px, aspect-ratio 4:3; три равные колонки |
| Gaps | 24px по горизонтали/вертикали и между группами |
| Секция | #16232A, текст #F3F6F6, padding-block 110px |
| Заголовок секции | Instrument Serif 60px / 1.04 при 1440px, weight 400, italic area name; margin-bottom 44px |
| Featured heading / excerpt | Instrument Serif 32px / 1.25, 400; Outfit 15px / 1.6, opacity .85, margin-top 4px |
| Additional heading / excerpt | Outfit 19px / 1.25, 600; Outfit 14px / 1.6, opacity .8, margin-top 2px |
| Фото / рамка | object-fit cover, radius 8px, без видимой рамки |
| Подпись | поверх фото снизу, padding 18px, gradient transparent → rgba(22,35,42,.85) |
| Ссылка | вся карточка — обычный anchor; в HTML href="#" как заглушка, без carousel/modal/disclosure |
| Примечание | после сетки, margin-top 28px, Outfit 15px, opacity .7 |

PNG подтверждает инвентаризацию 2+6 / сетку 2+3. Первые две карточки — фото-заглушки и в самом финальном макете. Реализация получает реальные Featured Images выбранных статей, без выдуманных изображений.

## Начальное состояние

Основная ветка `codex/m1-5-a3b-faq-accordion`, HEAD `eb367dd`: Embla уже интегрирована в основную тему. Незакоммиченные файлы FAQ сохранены в приватном backup до работы. Активная тема на 8088 — `/var/www/html/wp-content/themes/aloha-hello`, bind из основной рабочей копии `theme/aloha-hello`.

В основной базе **нет опубликованных Big Island Guide Posts**. Поэтому существующая редакционная секция Area 68 не будет автоматически заменена пустым блоком. Все демонстрационные статьи создаются только в disposable WordPress.

## Архитектура

`aloha/guide-highlights` — динамический API v3 блок с PHP SSR и визуальным редактором без новой сборки или frontend JS. Атрибуты — только `featuredIds` (максимум 2) и `additionalIds` (максимум 6), массивы integer Post IDs в редакционном порядке. Дубликаты внутри групп и между ними предотвращает UI; SSR дополнительно нормализует входные данные. Никаких title/image/excerpt/URL snapshots в attributes или post meta.

`aloha_guide_highlight()` проверяет тип `post`, статус `publish`, отсутствие password и принадлежность **ровно валидному семейству Guide** через существующий `Aloha\Content\editorial_post_route()`. Используются проверенные привязки Guide Categories, включая разрешённые дочерние рубрики. Investor, обычные статьи, draft/private/trash, удалённые и ошибочно классифицированные записи не попадают в публичную подборку. При отсутствующем Aloha Content/configuration блок закрывается безопасно, без регистрации собственных категорий.

Title, Excerpt, Featured Image и `get_permalink()` разрешаются заново при каждом рендере. Excerpt — обычный excerpt записи либо штатный fallback Core из body, без HTML/shortcodes, максимум 32 слова для карточки. HTML entities декодируются для текстовых данных и экранируются на выводе. Canonical URL и изменения баз/slug/рубрики принадлежат Aloha Content, блок не хранит адреса и не меняет rewrite lifecycle.

Изображения — `wp_get_attachment_image(..., 'large')` с `srcset`, `sizes`, `loading=lazy`, `decoding=async`, alt из Media Library. Удалённый attachment, не-изображение и отсутствующий локальный файл дают аккуратный фон без сломанного `<img>`.

## Редактор

Две явно подписанные группы: Featured Articles и Additional Articles. Native `SearchControl`, Spinner, Notice, Button, Tooltip и dashicons, без ручного ввода IDs/URL/HTML. Результаты поиска показывают миниатюру и заголовок; выбранные записи — preview с подписью. Кнопки 36×36px: раньше/позже, перенос в другую группу, удаление. Есть доступные названия, title/tooltip и disabled boundaries. При заполнении группы поиск заменяется пояснением; перенос в заполненную группу выключен. Чтобы перенести туда статью, сначала освободить место.

Read-only REST `GET /aloha/v1/guide-highlights`: поиск до 20 опубликованных подходящих статей, либо проверка до 8 выбранных IDs. Требуется `edit_posts` или `edit_pages`, штатная REST cookie/nonce authentication. Неподходящие выбранные IDs возвращаются как unavailable без утечки private titles; выбор сохраняется до решения менеджера. Ошибка сети не очищает IDs. Поиск исключает уже выбранные статьи из обеих групп. Свежие данные запрашиваются при изменении выбора, возвращении фокуса окна и кнопкой Refresh articles.

После изменения исходной статьи frontend обновляется без сохранения Area. В редакторе снятая с публикации/удалённая/переклассифицированная статья показана предупреждением, с доступной кнопкой удаления; это не invalid block.

## Frontend и адаптация

Вся карточка — единственная настоящая ссылка на статью, работающая без JS. Нет carousel, autoplay, modal или fake CTA. Hover/focus подчёркивает заголовок, focus-visible использует фирменный контур.

Desktop ≥1024px: 2 featured + 3 additional columns. Ниже 1024px featured становятся одной колонкой, additional — двумя; ниже 600px additional также одной колонкой. Крупные заголовки сохраняют Instrument Serif, малые — Outfit. Один и тот же набор DOM/images используется на всех ширинах.

Пропорции карточек задаёт CSS Grid ratio-sizer, подпись остаётся в нормальном потоке: нормальные тексты дают точные 16:9 / 4:3; при длинных заголовках и 200% текстовом zoom карточка увеличивается по высоте вместо обрезания текста. Empty groups не выводятся, отсутствующие места не заменяются пустыми карточками. Пустая новая Area-секция скрывается в всегда подключаемом Area CSS; правило не зависит от того, загрузил ли Core stylesheet пустого блока.

## Starter Pattern и сохранённые Area

В `aloha_area_pattern_content('area-activities')` прежние восемь заготовок заменены одним `aloha/guide-highlights` и исходным примечанием из финального HTML. Остальные ветви `area-start`, FAQ, Hero, Gallery, Beaches, Communities и owner CTA не менялись. Стандартный pattern остаётся unsynced: сохранённый `post_content` автоматически не обновляется.

## Контролируемый перенос Mauna Lani

**Статус: не применён — реальных опубликованных Guide Posts нет.** Старая секция Area 68 сохранена. Фиктивные статьи в основной базе не создавались. Приватный backup исходного body: `.local/mauna-lani-guide-post-content-before.html`.

Подготовлены CLI helpers:

- `scripts/area-guide-highlights-migration.php` — чистый planner и apply с backup/revision.
- `scripts/migrate-area-mauna-lani-guide.php` — local-only обёртка, строго Area 68 / slug mauna-lani / origin 8088, capability edit_post.

После появления настоящих проверенных статей менеджер составляет явный JSON `{ "featuredIds": [ID, ID], "additionalIds": [ID, ID, ID, ID, ID, ID] }` в прежнем порядке. `wp eval-file scripts/migrate-area-mauna-lani-guide.php plan mapping.json --user=<editor>` выполняет проверку без записи. Для `apply` дополнительно нужен файл с точным актуальным `post_content` backup; файлы должны быть доступны внутри CLI container.

Автоматический подбор по похожим названиям не используется. Mapping обязан покрывать все существующие карточки и сохранять title, excerpt и attachment ID каждой. Rich formatting, inline alt/crop/caption, неподдержанные структуры, дополнительный контент между группами и неоднозначные диапазоны требуют ручного переноса. Отсутствие подходящих статей — отказ без записи, а не очистка секции.

Apply требует закрытого редактора и истёкшего Core editor lock, сверяет backup, создаёт и проверяет native revision исходного body, повторно проверяет отсутствие конкурентного изменения и заменяет только непрерывный диапазон двух старых grids. Все байты вне диапазона сохраняются. Повторный запуск после успешного переноса — no-op без новых revisions. Существующие изображения не удаляются.

## Изменённые файлы

- `theme/aloha-hello/functions.php` — подключение нового компонента.
- `theme/aloha-hello/inc/guide-highlights.php` — registry, SSR, eligibility и read-only REST.
- `theme/aloha-hello/blocks/guide-highlights/{block.json,editor.js,render.php}`.
- `theme/aloha-hello/assets/css/guide-highlights.css` — общий дизайн и редактор.
- `theme/aloha-hello/assets/css/area.css` — только новое правило скрытия пустой Guide-секции поверх прежнего FAQ diff.
- `theme/aloha-hello/inc/area-patterns.php` — только ветвь area-activities поверх прежней реализации FAQ.
- `scripts/{guide-highlights-check.php,guide-highlights-browser.cjs,area-guide-highlights-migration.php,migrate-area-mauna-lani-guide.php}`.
- `scripts/test-aloha-content.sh` — opt-in CONTENT_GUIDE_HIGHLIGHTS_TEST, собственная фаза и регрессии прежних компонентов.
- Этот отчёт.

## QA

Окончательный полный прогон завершился с exit code 0: `.local/content-test-wVvVIj`. Команда: `DOCKER_CONTEXT=orbstack CONTENT_GUIDE_HIGHLIGHTS_TEST=1 CONTENT_BROWSER_TEST=1 CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-a4 ./scripts/test-aloha-content.sh`, с PLAYWRIGHT_MODULE из предустановленного runtime. Новые браузеры и npm зависимости не устанавливались.

| Часть | Основные server checks | HTTP | Browser scenarios |
| --- | ---: | ---: | ---: |
| Aloha Content, маршруты, Routing Overview | 275 | 226 | 32 |
| Area / Patterns | 23 | — | 11 |
| Hero / video / film dialog | 48 | — | 17 |
| Media Gallery | 41 | — | 13 |
| Beaches / Embla | 33 | — | 14 |
| Core FAQ | 31 | — | 9 |
| Guide Highlights | 30 | — | 13 |
| Всего | **481** | **226** | **109** |

Guide Highlights проверен на 1440/1024/768/390/320px, с 200% текстом и длинными заголовками/excerpts. Поиск, thumbnails, оба лимита, отсутствие дубликатов, сортировка, перенос, удаление, save/reopen, отсутствующие/удалённые/неподходящие публикации и fallback изображения прошли. Изменение title, slug, excerpt, фото и рубрики источника обновляет карточку без пересохранения блока. Обычные canonical links работают клавиатурой и без JS. Нет invalid blocks, горизонтального overflow или обрезанного текста.

PHP debug log — **0 байт**; pageErrors во всех browser reports — `[]`. PHP lint, JS syntax и `git diff --check` прошли. Fixtures удалены с fingerprint-проверкой исходного контента/настроек; контейнеры/volumes disposable WordPress удалены после прогона.

### Visual comparison

Виденный до реализации crop `section-2.png` пиксельно совпадает с финальным оригиналом на y=3100:4650; размеры оригинала подтверждены как 1440×7900 (`reference-source.json`). Секция финального PNG занимает y=3438:4771, то есть 1333px; SSR section screenshot — 1334px. Разница 1px соответствует округлению дробной высоты 330.75px. Ширины 588/384px, gaps 24px, radius 8px, 2+3 сетка, шрифты и положение captions воспроизведены по финальному HTML.

Первые две фотографии тестовых публикаций заменяют placeholders оригинального PNG — это проверка реального Featured Image, без заявления о достоверности места. Шесть компактных фотографий используют соответствующие assets финального макета. При отсутствии Featured Image используется спокойный тёмный fallback вместо исходной подписи-заглушки `Photo · …`. Mobile композиция — адаптация, отдельного утверждённого mobile PNG нет. При очень длинном тексте/200% zoom высота растёт ради читаемости; нормальные тексты сохраняют точные пропорции.

Section screenshots делаются после decode изображений, с достаточной высотой viewport для целой секции: это устраняет пропуски lazy-painted изображений и наложение sticky header/bottom bar в длинном capture. Проверки overflow и раскладки выполнялись при обычной высоте 1000px.

Evidence — `.local/evidence/m1-5-a4/`, исключено из Git:

- `reference-activities.png`, `guide-highlights-1440.png`, `visual-comparison.json` — сравнение с финальным PNG.
- `guide-highlights-{1024,768,390,320}.png` — адаптивность.
- `guide-highlights-editor.png`, `guide-highlights-editor-card-*.png` — редактор и компактные controls.
- `guide-highlights-unavailable-editor.png` — проблемные выбранные статьи.
- `guide-highlights-fewer-fallback.png`, `guide-highlights-text200-*.png`, `guide-highlights-keyboard.png`.
- `guide-highlights-{server,browser}-report.json` и отчёты регрессий.

### Основной сайт и сохранность

На **8088** проверены новая регистрация блока, dynamic renderer, Pattern и SHA-256 реально обслуживаемого editor.js. Активная основная тема версии 0.6.0 уже содержит реализацию; отдельный worktree для неё не требуется. Mauna Lani продолжает показывать прежние восемь редакционных карточек, поскольку подходящих опубликованных Guide Posts нет. Screenshot: `main-8088-legacy-preserved.png`; результат — `main-runtime.json` / `main-browser-report.json`.

Сравнение основной базы до/после: все **85 posts rows** побайтно совпадают, включая Area 68, остальные разделы, revisions и Navigation. Не создавались Guide Posts, Pages, attachments или revisions в основной базе. Terms/relationships, permalink/rewrite/signature, Reading и выбранные shell options сохранены. Редакционные meta сохранены; единственное отличие — существующий `_edit_lock` Area 68 обновлён heartbeat открытого редактора. Данные не откатывались.

Все 11 исходных незакоммиченных файлов FAQ проверены: 8 не изменены побайтно; в трёх общих файлах (`area.css`, `area-patterns.php`, test runner) добавлены только изменения Guide. После их исключения файлы побайтно совпадают с backup, включая весь FAQ CSS, функции, Pattern и тестовую фазу. Файлы Embla не изменялись; текущая карусель основного сайта работает. Сводки: `faq-preservation-report.json`, `main-preservation-report.json`.

### Ограничения

- Main migration ожидает настоящие опубликованные Guide Posts и явное соответствие существующим карточкам. Тексты/снимки Mauna Lani не подменялись демонстрационными данными.
- Максимум 20 результатов за запрос поиска; для большого Guide нужно уточнять название. Дополнительной frontend pagination нет.
- Excerpt ограничен 32 словами; rich inline formatting в карточке не переносится. Подробности остаются в статье.
- При неполной подборке выводятся только реальные выбранные карточки в утверждённых колонках, без пустых элементов.
- Firefox/WebKit отсутствуют; smoke не запускался, браузеры не устанавливались.
- `scripts/check-references.py` сообщает о ранее отсутствующем `aloha-architecture-sources.zip`; остальные 20 оригиналов совпадают с manifest. Исторический ZIP не создавался, manifest не менялся.
- Исторические 301, frontend Article template, Guide Hub design, Community и GuestyBridge не входят в этап.

Ветка остаётся `codex/m1-5-a3b-faq-accordion`, HEAD `eb367dd`; новые изменения Guide и прежний FAQ оставлены незакоммиченными в основной копии. Push/deployment не выполнялись.
