# M2.4B.5 — Rental Visual Polish & Admin UX Consolidation

Дата: 2026-11-19. Bridge 0.3.13.

## Реализация

Публичные стили ограничены Rental. Четыре редакторских значения Facts Strip имеют отдельный класс и 17px, исходные значения Guesty сохранены. Sleeping использует column flex, padding 22px, gap 8px, заголовок 19px, описание 16px и чипсы 13px с указанными цветами. На узком экране карточки идут одной колонкой, без прежнего переопределения padding 12px.

Кнопка Amenities получила отдельный scoped selector: margin-top 32px, min-height 52px, padding 0 28px, font 16px/600, radius 999px, border 1.5px transparent. About и House Rules сохраняют свои текстовые кнопки и disclosure-поведение. Close by нормализует li margin, разделители и шрифт 17px; время теперь span, opacity .72 / weight 400 / nowrap. Карта, порядок, выбор маркеров и ручная публичная пара координат сохранены.

Отзывы используют padding 32px, текст 18px с нижним отступом 24px и ограничением в три строки. Атрибуция 15px остаётся внизу. При наличии выводятся Name и Trip type, затем всегда сохраняются Airbnb/Booking.com и дата. Рейтинг не выводится. House Rules сохраняет сетку 3/2/1 и получает типографику title 18px / value 16px.

Contact имеет фон #075056, текст #f3f6f6, radius 10px, padding 32px, фотографию и fallback 96px, introduction 16px/.85. На mobile изображение и текст перестраиваются вертикально. Ask сохраняет focus/hover и border 1.5px transparent.

Имя выбирается из first_name, затем подходящего первого слова публичного display_name, отличного от login. Login не используется как автоматическое имя. Плейсхолдер {First Name} сохранён. При отсутствии имени используются нейтральный текст команды и Contact our team. Изменение профиля отражается без Guesty Sync.

## WordPress Admin и данные

Для каждого выбранного Featured Review доступны необязательные Guest name и Trip type. Отдельный revision-enabled map `_awb_featured_review_attribution` привязан только к выбранным sourceId. Проверка подписанных ticket сохранена. Текст, рейтинг и дата оригинала не меняются. Неизвестные ключи, лишние поля и неверные типы отклоняются. Дополнения можно очистить; Remove удаляет атрибуцию, повторный Select начинает с пустых полей. Сервер требует manage_options и edit_post, сохранение проходит через существующие WordPress/Structured Editor nonce, Update, Preview и Revisions.

У Profile Picture добавлены стандартный Media Library dialog, Upload / Choose image и Remove local picture. Один приватный user_meta `_awb_profile_picture_id` хранит attachment ID. Проверяются edit_user, отдельный nonce, image attachment, право edit_post на изображение, разрешённый и фактический MIME локального файла. Поддерживаются JPEG, PNG, GIF, WebP и AVIF. SVG и несуществующие/неизображения не принимаются. Удаление выбора не удаляет Media Library файл. Стандартный WordPress avatar hook использует локальное изображение во всех Rentals этого пользователя; native avatar markup сохраняет 2x srcset. Нет нового публичного REST-поля user metadata. При локальной фотографии Gravatar для этого контакта не запрашивается; чужой аватар в штатной admin toolbar не изменяется.

Sleeping теперь одна раскрываемая секция. В каждой карточке находятся подтверждённые название/кровати Guesty и прежние Floor, Steps, Bathroom access с теми же стабильными ключами и ограничением до двух вариантов. Sync заменяет только read-only copy, сохраняя DOM редактируемых полей и незаписанные значения. Новая комната получает серверные controls; исчезнувшая во время редактирования сохраняет поля с предупреждением. Per-room nonce позволяет безопасно сохранить такое ранее показанное помещение без принятия поддельных source IDs.

House Rules теперь одна секция: Guesty rules со статусом, затем прежний Additional rules repeater. Его схема `_awb_additional_house_rules` не меняется, editable DOM не заменяется при Sync.

В расширенной проверке опубликованного Preview обнаружено некорректное разворачивание array metadata штатным WordPress preview filter. Добавлена узкая поправка формы возвращаемого metadata row только для revision-enabled полей Rental при активном native preview filter. В Preview сохраняются выбранные отзывы, новая атрибуция, access и Additional rules. Опубликованные значения остаются прежними до Update. Скалярные поля, другие CPT, public reads и Guesty source не меняются.

## Проверки

- PHP syntax: 114 файлов src/tests/theme/scripts, дополнительно entry point плагина; JS syntax: 54 файла; git diff --check.
- Полный Bridge local-smoke.sh: импорт/rollback, capabilities/nonce/REST, signed reviews, source sync/webhooks, photo order/labels, queue locking/backoff/dedupe, шифрование. Дополненный import-smoke подтверждает сохранение редакторской атрибуции при Sync.
- RentalPublicData standalone fixture.
- rental-polish-check.php: signed selection, очистка и запрет чужих sourceId, локальный аватар, nonce/права/MIME/неизображение, удаление без удаления файла, сохранение just-retired room с nonce и отклонение подделки.
- Aloha Content integration suite в отдельном временном WordPress: Area, Community, Nearby Places, маршруты/права/валидация, Guide и Investor; fixtures удалены.
- rental-content-browser.cjs: native Draft → Publish → Update, custom HTML, Facts Strip, access, ordered rules, повторное открытие, metadata Preview и Revisions.
- structured-editor-browser.cjs: жизненный цикл редактора, Nearby search/Area priority/create/permissions/errors/reorder, Preview и Revisions.
- rental-details-browser.cjs: full/partial/failed/recovery Sync, local time, одна Sleeping и одна House Rules, незаписанные Floor/Steps/Bathroom access и Additional rules после Sync; signed review picker, attribution Update/Preview/clear/remove/re-add/Revisions.
- profile-picture-browser.cjs: стандартный Media Library Select, сохранение/удаление, local avatar и srcset, отсутствие Gravatar-запроса контакта, немедленное изменение имени, нейтральный fallback и публичные инициалы 96px.
- Public Rental Chromium: computed styles на 320/390/768/1024/1440 CSS px; отсутствие overflow; галерея/фильтры/lightbox, disclosure, review attribution, sticky booking, touch/keyboard highlights, native draft Preview, карта с mock API и ошибки. Проверено вертикальное выравнивание существующих иконок Save/Share. CLS desktop fixture < 0.1.

Синтетические Rentals, контактные пользователи и медиа удалены контролируемо. Для реальных Rentals 186 и 365 сравнены SHA-256 по post, mapping, editorial metadata, source metadata, featured image и editor service metadata: все шесть групп каждого Rental совпали до/после.

## Скриншоты

Локальные доказательства: `.local/evidence/m2-4b5/` в основном workspace ALOHA.

- `public/facts-{390,1440}.png`
- `public/sleeping-{390,1440}.png`
- `public/nearby-{390,1440}.png`
- `public/reviews-{390,1440}.png`
- `public/contact-{390,1440}.png`
- `public/rules-{390,1440}.png`
- `public/public-{390,1440}.png`, `public/draft-preview.png`
- `admin/sleeping.png`, `admin/house-rules.png`, `admin/featured-reviews.png`
- `profile/profile-media-choice.png`, `profile/profile-local-picture.png`

Изображения и тексты fixtures синтетические. Media Library dialog показывает существующую библиотеку read-only; выбрана только fixture-фотография. Основные секции и desktop/mobile скриншоты просмотрены визуально.

## Границы проверки

Google Maps проверена через mock API; реальная карта требует прежней отдельной настройки API key/Map ID и ограничений origin. Маркер дома по вручную заданной публичной паре координат сохранён. Guesty Sync проверен синтетическими ответами, механизм синхронизации не изменён. Реальные Guesty write operations, Booking/Payment, массовый импорт и удаление существующих медиа не выполнялись. Перед запуском сайта остаётся отдельный staging-тест Guesty CDN/cron.

## Публикация

Aloha: feature/m2-4b-rental-template, база 7965aa050044b04b12951f20c29c26d868a7531a. Три логических коммита: visual refinement, review attribution rendering, contact visual polish.

AlohaWPBridge: codex/m2-3-rental-data-editor, база 02ec38c7535920fe75a79a86e694e107f33035c0. Пять логических коммитов: review attribution metadata, local profile avatars, First Name resolution, Sleeping consolidation, House Rules consolidation и итоговая проверка.

Работа выполнена в существующих проверенных integration worktrees с публикацией в указанные feature-ветки. Исходные грязные рабочие копии сохранены. Main не изменялся, merge не выполнялся. Следующий этап не запускался.

## Изменённые файлы этого репозитория

- `theme/aloha-hello/assets/css/rental.css`
- `theme/aloha-hello/inc/rental-content.php`
- `scripts/rental-template-browser-fixture.php`
- `scripts/rental-template-browser.cjs`
- `docs/m2-4b5-rental-visual-admin-polish.md`
