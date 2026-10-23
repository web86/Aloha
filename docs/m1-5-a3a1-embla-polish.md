# M1.5-A.3a.1 — Beaches Carousel with Embla

Дата: 23 октября 2026.

## Объём и Git

Проверенная реализация M1.5-A.3a уже сохранена в `fc55e2acb8bb0d23336376f4a5c764901c117e4f` (`feat(aloha): add interactive Beaches block`); ветка `codex/m1-5-a3a-beaches` указывает на этот коммит.

Во время начала задания основная рабочая копия находилась на `codex/m1-5-a3b-faq-accordion` с незакоммиченным FAQ. Поэтому ветка `codex/m1-5-a3a1-embla-polish` создана от Beaches в отдельном Git worktree `.local/worktrees/embla`. FAQ не переносился, не коммитился и не менялся в рамках этого задания. Push и deployment не выполнялись.

## Реализация

Используется **Embla Carousel 8.6.0**, оригинальная Vanilla UMD distribution, локально в `assets/lib/embla-carousel/`. npm tarball проверен по SHA-512 integrity; точная версия, SHA-256 UMD, источник и MIT license сохранены рядом с библиотекой. Нет CDN, плагинов, autoplay, React/Vue и новой сборки.

`inc/beaches.php` регистрирует отдельный WP handle `aloha-embla` с версией `8.6.0`; frontend handle `aloha-beaches` зависит от него. Разметка имеет структуру viewport → container (`aloha-beaches-track`) → SSR articles. Текстовый счётчик удалён из HTML.

Формат `cards`, editor.js, block.json, обработка изображений, sanitization и содержимое карточек не изменены. Изменения PHP ограничены регистрацией зависимости, обёрткой и удалением счётчика. Миграции `post_content` нет.

При 1–4 карточках остаётся обычная адаптивная сетка; при 0 блок ничего не выводит. Для >4 карточек:

- `align: 'start'`, `containScroll: 'trimSnaps'`, `slidesToScroll: 1`, `loop: false`, `dragFree: false`.
- Четыре карточки при ширине ≥1024px, две при 600–1023px, карточка шириной 88% viewport ниже 600px. Следующая карточка частично видна на mobile, кроме конечного положения.
- Embla пересчитывает позиции своим ResizeObserver; индивидуальные слушатели resize и ручной расчёт disabled не используются.
- Две одинаковые кнопки 46×46px справа под карточками, gap 12px, верхний отступ 24px, тонкая граница, SVG. Названия `Previous beaches` / `Next beaches`. Disabled определяется только `canScrollPrev()` / `canScrollNext()` при `select` и `reInit`.

До успешной инициализации viewport имеет `overflow-x:auto`, controls скрыты. После инициализации класс `is-enhanced` переключает viewport на `overflow:hidden`, controls становятся доступны. Все карточки остаются в первоначальном SSR HTML в одном экземпляре. При отсутствующей библиотеке или ошибке инициализации сохранён native scroll, кнопки скрыты. Старый scroll-snap и JS `scrollTo`/расчёт диапазона удалены.

## Ввод и доступность

Native buttons работают мышью и клавиатурой. Фокус на viewport позволяет ArrowLeft/Right и Home/End; обработчик не перехватывает эти клавиши у дочерних ссылок и не перехватывает modifier shortcuts. Embla обеспечивает drag, touch swipe и показ слайда при переходе Tab к его ссылке. Фокус имеет фирменный видимый контур; autoplay, focus trap, роли menu и собственный accessibility framework отсутствуют.

Reduced motion применяет `duration:0` через breakpoint Embla, а вызовы кнопок и клавиш используют параметр `jump`. При `pointerUp` в reduced motion выбранная позиция также фиксируется мгновенно: upstream drag release использует собственную duration независимо от options.duration. Изменение media query обрабатывается самим Embla. Цветовые transitions кнопок включены только при `prefers-reduced-motion:no-preference`.

Обнаружен крайний случай upstream 8.6.0: отпускание drag вне viewport может оставить одноразовое подавление click, после чего Enter по ссылке подавляется. Перед native Enter по дочерней ссылке выполняется публичный `reInit()` с сохранением выбранной позиции. Default navigation не подменяется. Проверяется последовательность drag вне viewport → Home → Enter по ссылке, а также отсутствие перехода непосредственно после drag. Исходный код библиотеки не модифицирован.

## Изменённые файлы

- `theme/aloha-hello/inc/beaches.php` — SSR wrapper, script dependency, удаление счётчика.
- `theme/aloha-hello/assets/js/beaches.js` — единственная frontend механика на Embla.
- `theme/aloha-hello/assets/css/beaches.css` — layout, progressive overflow, кнопки и адаптивность.
- `theme/aloha-hello/assets/lib/embla-carousel/{embla-carousel.umd.js,LICENSE,README.md}` — точная версия и происхождение.
- `scripts/beaches-check.php` — SSR/dependency проверки и изолированные fixtures.
- `scripts/beaches-browser.cjs` — Embla, mouse/touch/keyboard/fallback и visual QA; сохранены проверки Gutenberg.
- Этот отчёт.

## Результаты проверки

Окончательный полный прогон `scripts/test-aloha-content.sh` завершился с exit code 0 (disposable `content-test-9f0ly8`). Параметры: `DOCKER_CONTEXT=orbstack CONTENT_BEACHES_TEST=1 CONTENT_BROWSER_TEST=1`, `CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-a3a1`; `PLAYWRIGHT_MODULE` указывал на уже установленный runtime Playwright. Дополнительные зависимости/браузеры не устанавливались.

| Проверяемая часть | Основные серверные сценарии | HTTP | Браузерные сценарии |
| --- | ---: | ---: | ---: |
| Aloha Content / маршруты / Routing Overview | 275 | 226 | 32 |
| Area / starter patterns | 23 | — | 11 |
| Hero / film dialog / polish | 48 | — | 17 |
| Media Gallery / lightbox / editor | 41 | — | 13 |
| Beaches / Embla / editor | 33 | — | 14 |
| Всего | **420** | **226** | **87** |

Все 0/1/4/5/8/12 коллекции проверены на 1440/1024/768/390/320px. Подтверждены исходное положение, два положения для 5 карточек на desktop, отсутствие пустого края и горизонтального overflow страницы, правильные API disabled states, круглые 46×46 controls и стабильные размеры hover/focus.

Проверены mouse drag с настоящим подавленным click по ссылке и отпусканием вне viewport, touch swipe, кнопки Enter/Space, ArrowLeft/Right/Home/End, Tab к offscreen ссылке, независимость двух каруселей, reduced motion для кнопок и завершения swipe. Для touch состояние проверяется после ближайшего render frame и остаётся неизменным, без продолжающейся анимации.

No-JS, заблокированная загрузка UMD и исключение при инициализации сохраняют все 12 SSR карточек, прокрутку и ссылки, без неработающих кнопок. Проверены длинные описания, 200% text, удалённый attachment, Media Library, focal point, reorder/remove, сохранение и повторное открытие без invalid blocks.

`pageErrors` во всех браузерных отчётах пусты; PHP debug log — **0 байт**. Fixtures удалены с проверкой fingerprint исходного контента/настроек; после cleanup отсутствуют Area/Community fixtures, тестовые контейнеры и volumes. `node --check`, PHP lint и `git diff --check` прошли. Изменений редактора, block.json, плагина Aloha Content и сохранённых Gutenberg-атрибутов нет.

## Сохранность основной Mauna Lani

В основной WordPress не выполнялись миграции или записи контента. Снимки `posts`, `postmeta`, `terms`, `term_taxonomy`, `term_relationships` и выбранных options до/после показали:

- Все **84 записи** побайтно неизменны, включая Area 68 и её revisions; не созданы fixtures или новые revisions в основной базе.
- SHA-256 `post_content` Area 68 до/после: `ee0d18312a2977335bdc98c102c8cdc0d9d20df47f7afc34d7d81af4f562ad64`.
- Все редакционные meta, attachment IDs, связи, таксономии, Navigation posts, permalink/rewrite/signature, Reading и выбранные shell options сохранены.
- Единственное различие — существующий редакторский heartbeat обновил `_edit_lock` (meta 58, Area 68). Это не контент и не изменение этого задания; значение не восстанавливалось принудительно.
- Незакоммиченный diff основной FAQ-ветки остался идентичен контрольному patch.

Приватные snapshots и credentials не включены в Git. Сводка без содержимого базы: `.local/evidence/m1-5-a3a1/main-preservation-report.json` внутри worktree.

## Visual QA и evidence

Сопоставлены оригинальные Area Design v2 и новая карусель. Сетка четырёх карточек сохранена: сравнение с PNG предыдущего Beaches QA дало **полное пиксельное совпадение** на 1440, 1024, 768, 390 и 320px (`four-card-visual-comparison.json`). Изначальный макет содержит четыре карточки без карусели; дополнительные стрелки для >4 — новое поведение, оформленное в существующей палитре, типографике и геометрии.

Evidence хранится локально в `.local/evidence/m1-5-a3a1/`, не в теме и не в публичном Git:

| Сценарий | Файлы |
| --- | --- |
| До Embla, прежние stress fixtures | `before-beaches-carousel-eight-{1440,1024,768,390,320}.png` |
| После: 5/8 карточек, обычные описания | `embla-compact-{5,8}-{1440,1024,768,390,320}.png` |
| После: длинное описание и 5/8 карточек | `embla-{5,8}-{1440,1024,768,390,320}.png` |
| Пять карточек, положения 1–4 и 2–5 | `embla-five-{start,end}-1440.png` |
| Grid без карусели, 4 карточки | `beaches-grid-four-{1440,1024,768,390,320}.png` |
| Клавиатура / reduced motion | `embla-keyboard-reduced.png` |
| Touch / 200% text | `embla-touch.png`, `embla-text-200.png` |
| Gutenberg / FocalPointPicker | `beaches-editor.png`, `beaches-editor-focal.png`, `beaches-editor-card-*.png` |

Before и stress-after используют одинаковое длинное описание; compact fixtures дополнительно показывают обычную высоту карточек. Синтетические портретные/панорамные/квадратные изображения служат проверке cover/focal point, не являются новым контентом Mauna Lani. Системная полоса без JS зависит от ОС; в интерактивном режиме проверяется фактическое `overflow:hidden`, без маскировки scrollbar CSS-хаком.

## Ограничения

- Проверки выполнены в Chromium на disposable WordPress 7.1.3, Aloha Content 0.7.2, от сохранённой Aloha Hello 0.5.0. Firefox/WebKit отсутствуют; браузеры не устанавливались.
- No-JS / отказ Embla сохраняет native horizontal scroll, без drag мышью и без стрелок. Это намеренный fallback.
- У коллекции с очень длинным описанием сохраняется общая высота по самой высокой карточке: кнопки располагаются ниже текста и не перекрывают его.
- Карусель включается только для >4 карточек на всех ширинах; 1–4 сохраняют прежнюю адаптивную сетку, включая mobile.
- `scripts/check-references.py` из основной рабочей копии сообщает только о ранее отсутствующем `aloha-architecture-sources.zip`. Остальные 20 оригиналов совпадают с manifest, изменённых файлов нет. ZIP не создавался, manifest не менялся.
- Основная рабочая копия и её открытый редактор FAQ не переключались. Для просмотра новой реализации используется отдельная ветка/worktree; основной локальный сайт не перенастраивался на другую тему или рабочую копию.

FAQ, Activities, Community и GuestyBridge в этой ветке не разрабатывались.
