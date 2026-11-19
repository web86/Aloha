# M2.4B.6 — Related Rentals, Google Maps Labels & Frontend Refinement

Дата: 2026-11-19. Bridge 0.3.14.

## Публичный интерфейс

Amenities сохраняет прежние размеры и получает border 1.5px #16232a. Contact сохраняет светлый текст, прозрачный фон и получает border 1.5px #f3f6f6. Chromium проверил цвета и отсутствие изменения ширины/высоты при hover/focus.

Основная строка Guests said собирается только из существующих Name, Trip type и Date. Пустых разделителей нет. Channel сохранён в исходном Featured Review, публичном DTO и отдельном доступном описании. Текст, рейтинг и дата оригинала не изменяются. About использует только Area; vacation rental остаётся курсивным. Related heading использует то же Area, по которому выбираются дома, с курсивным in Area. See all homes ведёт на публичный permalink этого Area.

## Диагностика Airbnb reviewLink

Проверено чтением локальных post/meta/mapping и DTO; реальные записи не сохранялись и не синхронизировались. Время в таблице — UTC.

| Проверка | Rental 186 | Rental 365 |
| --- | --- | --- |
| WordPress status | Draft | Published |
| Подтверждённая mapping | Есть | Есть |
| source.airbnb | Отсутствует | Есть |
| source.airbnb.status | Missing | Available |
| value.url | Нет | Есть, проходит действующую проверку Airbnb URL |
| value.count | Отсутствует | 4 |
| value.countStatus | Missing | Available |
| Last data update | 2026-11-15 19:59:25 UTC | 2026-11-19 11:50:52 UTC |
| resources.airbnbReviews.lastSuccessAt | Нет | 2026-11-19 11:50:52 UTC |
| Public get() | Null: черновик | DTO с reviewLink/count 4 |
| Авторизованный preview() | DTO без reviewLink | DTO с reviewLink/count 4 |
| Фактическая публичная страница | Не публиковалась | All 4 reviews присутствует |

Программного дефекта reviewLink не обнаружено, его код не менялся. Исторический read-only GET с 87 отзывами для 186 не записывал проекцию в WordPress. У 186 нет сохранённых данных, необходимых для ссылки: потребуется отдельно разрешённый ручной Sync. К началу этой задачи у 365 проекция уже была сохранена, поэтому ранее описанное отсутствие ссылки для его текущего состояния не воспроизводится. Массовый или ручной Sync реальных объектов не выполнялся.

Синтетические проверки подтверждают: подтверждённые URL/count дают All N reviews; при известном URL и неизвестном count — All reviews on Airbnb; unconfirmed integration или поддельный URL не дают ссылки. Booking Engine reviews.total не используется. Public get и nonce-protected native Preview передают reviewLink корректно.

## Проверка требований каналов

Проверены первичные публичные источники на дату задачи: [Airbnb Terms, §11.1](https://www.airbnb.com/help/article/2908) и [Booking.com General Delivery Terms, §4.2.4](https://admin.booking.com/hotelreg/terms-and-conditions.html). Они требуют соответствующих разрешений на использование контента/отзывов. Универсальный обязательный формат или позиция видимой строки источника из этих публичных документов не установлены. Конкретные условия партнёрского соглашения Guesty/канала недоступны этой проверке.

В соответствии с заданием Channel исключён из основной видимой подписи; исходное значение сохранено, включая доступное описание для screen reader. Это изменение не подтверждает права на повторную публикацию отзывов. Если применимое соглашение требует отдельной видимой идентификации, её нужно показывать отдельно от Name/Trip type/Date. Новые флаги разрешений или дополнительные approval flows не добавлены.

## Google Maps

Существующие AdvancedMarkerElement и loader сохранены. PinElement, цифровые пины и InfoWindow удалены. HTML-маркеры строятся DOM API и textContent: бирюзовая точка, светлая плашка с названием, тень, тёмный текст. Для дома сохранены оранжевый #ff6400, SVG house icon и постоянное название Rental. Подпись ограничена двумя строками, полное имя остаётся доступным через title/aria-label.

Collision behavior REQUIRED сохраняет маркеры видимыми; подписи размещаются по разным сторонам точки. Выбранный маркер поднимается и выделяется, список сохраняет aria-pressed. Нажатие списка центрирует карту, нажатие маркера выбирает пункт. Bounds включают все точки и дом; ResizeObserver повторно подгоняет их при изменении размера. Источники координат не менялись: publishedAddress и разрешённый ручной fallback. Отсутствующие координаты места не выдумываются.

Локальные API key и Map ID настроены. Настоящий Google Maps API проверен на синтетическом Rental с двумя очень близкими местами, длинным именем и маркером дома: подложка загружена, 3 постоянные подписи, 0 InfoWindow, ошибок API/авторизации нет. Проверены 390 → 1440 → 390 px, завершение tile loading/idle, нахождение подписей внутри карты и выбор через список/маркер. Ключи, Guesty секреты и полные provider request URLs не выводились.

Mock-проверки дополнительно покрыли success, network error, authorization error, mobile, browser opt-out, lazy loading, bounds и восстановление доступного списка. Отсутствие конфигурации/доступа сохраняет прежний fallback.

## Related Rentals

Запрос остаётся локальным: до трёх опубликованных mapped Rentals того же Area, текущий дом исключён. Private/draft/unmapped/неподходящие DTO не выводятся. Сортировка date/ID даёт устойчивый порядок при одинаковом времени публикации. Guesty при рендеринге не вызывается.

Карточки следуют Design v2: фото 4:3, Area uppercase 12px с letter-spacing, Instrument Serif title 30px, одна строка Listing highlights с CSS ellipsis и полным доступным текстом, bedrooms/baths/guests, затем отделённая строка цены. CSS subgrid выравнивает уровни контента по самой длинной карточке без обрезания названия. Сетка — 3/2/1 колонки. Меньшее число домов не дополняется пустыми карточками.

Каждый альбом использует отдельный instance уже подключённого Embla 8.6.0. Максимум шесть первых видимых локальных фото из DTO, с сохранением редакторского порядка. У неактивных слайдов src/srcset назначаются только при выборе. Повторный переход к уже загруженным изображениям не создаёт новых запросов. Используются WordPress srcset/sizes, lazy loading, стабильный aspect ratio и серый фон. Поддержаны стрелки, точки, мышь, swipe и keyboard/reduced motion. Кнопки не вложены в ссылки; название и изображение ведут на Rental. Одна фотография не получает неактивные controls. Пустая галерея использует валидную локальную Featured Image либо нейтральный placeholder.

На тестовых JPEG выбран ресурс 768×512, encoded body примерно 11.4–11.7 КБ; оригиналы 2200px для карточек не запрашивались. Это измерения fixture, а не обещание веса любых production-изображений. Для стабильности первого отображения добавлены preloads трёх уже существующих локальных шрифтов только на Rental pages.

## Цена

Публичные карточки показывают Check dates for pricing. Фиктивных сумм и Guesty pricing-запросов нет. Отдельный renderer принимает будущий подтверждённый Pricing DTO amount/currency/perNight; сейчас он вызывается без цены. Booking Widget не изменялся.

## Listing badge и первая публикация

Content & SEO содержит single select None / NEW / LUXE. `_awb_listing_badge` зарегистрирован, проходит строгую серверную валидацию через существующее сохранение Structured Editor, права edit_post и nonce. Поддержаны Update, Preview и Revisions. Public DTO содержит только эффективный badge: new, luxe или null.

`_awb_first_published_at` — отдельный защищённый UTC timestamp без revisions и REST-доступа. При первом запуске сохраняется post-ID high-water mark существующих записей в site option; сами старые Rentals не перезаписываются. Для новых после rollout записей timestamp добавляется уникально при первом реальном переходе в publish, независимо от редактируемой post_date. Update, изменение даты, повторная публикация, Restore Revision и Guesty Sync не сбрасывают срок.

NEW действует строго менее 7 × 24 часов от первой публикации, затем исчезает без cron. Для неизвестной истории старого Rental дата не придумывается: NEW не показывается. Если уже есть достоверный сохранённый first-published marker, используется он. В редакторе есть подсказки об истёкшем NEW и неподтверждённой истории. LUXE не имеет срока. Параллельных isNew/isLuxe flags нет.

## Результаты проверок

- PHP/JS syntax и git diff --check.
- Полный Bridge local-smoke.sh, включая дополнительную проверку сохранения listing badge при Guesty Sync.
- RentalPublicData fixture: исходные privacy/gallery checks, подтверждённый Airbnb count, неизвестный count, unconfirmed integration и поддельные URL.
- listing-badge-check.php: enum/типы/права, первый publish, изменение даты, republication, точное истечение семи суток, LUXE/None, неизвестная история и неизменность source.
- listing-badge-browser.cjs: Draft → Publish → Update, unsaved native Preview и Restore Revision; сохранены source, post_content и первый timestamp.
- Structured editor и details browser regression: Nearby catalog/Area/create/errors, native lifecycle, review picker/attribution, source Sync states, unsaved access/rules, Preview и Revisions.
- Aloha Content integration suite: Area, Community, Nearby Places, Guide, Investor, routes, permissions, validation, fixture cleanup.
- Public browser: computed styles на 320/390/768/1024/1440 px, Gallery/Lightbox, Highlights Embla, contact, house rules, sticky booking, disclosures, map mocks и protected Preview.
- Related browser: правильный Area и исключения, две независимые карусели, 0/1/multiple фото, order/visibility, controls/keyboard/swipe/cache, accessible full highlights, NEW expiry/LUXE, image dimensions/weight, ровные уровни карточек и отсутствие overflow на пяти ширинах.
- Live Google Maps browser: успешная загрузка настоящего API/tiles, близкие и длинные подписи, mobile/desktop bounds, дом, выбор и отсутствие InfoWindow.

Финальные результаты: PHP syntax — 116 файлов плюс entry point, JS syntax — 57 файлов; public CLS 0.0099, Related CLS 0.0000. SHA-256 Rentals 186 и 365 совпали до/после по всем шести группам: post, mapping, editorial metadata, source metadata, featured image, editor service metadata. Fixtures создавались и удалялись контролируемо. Реальные Rentals 186 и 365 не изменялись. Guesty write operations, массовый Sync/import, Booking/Quote/Payment не выполнялись.

## Скриншоты и оставшиеся отличия от Design v2

Локальные доказательства в основном workspace: `.local/evidence/m2-4b6/related-1440.png`, `related-390.png`, `maps-live-1440.png`, `maps-live-390.png`, `maps-live-result.json`; полный публичный экран и секции — в `public/`.

Скриншоты просмотрены визуально. Карточки используют синтетические изображения и тексты. Реальные цены заменены нейтральным состоянием. Tags из макета (например Beach club access) не придуманы: отдельного подтверждённого контракта для них в задании нет. Названия не обрезаются, поэтому длинный title увеличивает общий ряд, сохраняя уровни соседних карточек. Google Maps использует настоящую географическую подложку и её штатные controls/attribution. Для большого числа точек в одной координате возможны пересечения; сложная кластеризация не добавлена, выбранная подпись поднимается. Других библиотек нет.

## Публикация

Aloha: feature/m2-4b-rental-template, база 23e51a15184ffe00a892c4371a06bf5270ba5d79. Отдельные коммиты: Buttons/Reviews/Area headings; Google Maps text markers; Related Rentals/Embla.

Bridge: codex/m2-3-rental-data-editor, база b05f0bf0dfd3a2cbbb16145dd68fccfe309a70ec. Отдельные коммиты: listing badge editor/DTO; immutable first publication handling и итоговые проверки. Airbnb reviewLink не исправлялся, поскольку дефект не подтверждён.

Исходные грязные рабочие копии сохранены; работа велась в verified feature worktrees. Main не изменялся, merge не выполнялся. Следующий этап не запускался. Live Guesty CDN/cron остаётся отдельной ранее оговорённой staging-проверкой.

## Изменённые файлы этого репозитория

- `docs/m2-4b6-rental-related-map-refinement.md`
- `scripts/rental-map-live-browser.cjs`
- `scripts/rental-related-browser.cjs`
- `scripts/rental-template-browser-fixture.php`
- `scripts/rental-template-browser.cjs`
- `theme/aloha-hello/assets/css/rental.css`
- `theme/aloha-hello/assets/js/rental-content.js`
- `theme/aloha-hello/assets/js/rental-map.js`
- `theme/aloha-hello/inc/rental-content.php`
- `theme/aloha-hello/inc/rental.php`
