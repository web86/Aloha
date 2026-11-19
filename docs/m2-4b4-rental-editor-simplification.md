# M2.4B.4 — Rental Editor KISS, Facts Strip, Bathrooms & Guesty Data

## Полностью реализовано и проверено

Facts Strip расположен после Content & SEO, перед Close by, и первоначально свёрнут. Верхние четыре ячейки читают защищённые Guesty facts и статус источника. Нижние четыре имеют произвольные plain-text Title и Value. Публичная сетка сохраняет восемь ячеек, включая незаполненные. При синхронизации значения и статусы верхнего ряда обновляются в редакторе. Подпись Listing highlights использует прежнее поле _awb_usp.

Public guest information удалён. Questions about this home содержит выбор администратора и Introduction text. Назначение в Rental разрешает публикацию display name, стандартного Profile Picture и email. Это явно поясняется рядом с выбором. Отдельных настроек профиля и второго поля фотографии больше нет. Используется get_avatar_url; Gravatar может запросить изображение в браузере. При ошибке фотографии отображается инициал. В WordPress Rental хранятся только user ID и текст.

Introduction имеет редактируемое значение по умолчанию с токеном {First Name}. На публичном выводе токен заменяется на display name. Шаблон выводит только обработанный текст. Кнопка Ask использует имя выбранного пользователя. При отсутствии действующего администратора блок скрыт.

House Rules содержит один repeater с добавлением, редактированием, перемещением вверх/вниз и удалением строк. Малого UI-лимита нет. JSON запроса ограничен 512 000 байт, заголовок — 160 байт, значение — 4 000 байт. JSON передаётся одним полем, поэтому длинная таблица не расходует отдельные PHP input variables на каждую ячейку.

Все публичные правила используют одну сетку из трёх колонок на desktop, двух на tablet и одной на mobile. Редакторские правила идут первыми, затем подтверждённые Guesty rules. Первые три блока видны сразу, остальные раскрываются без повторения первой строки. Этот порядок следует последнему явному требованию публичного вывода в задании.

Breadcrumbs начинается с Home. Close by показывает название и время без цифровых значков и категорий. Редакторский порядок, две колонки и карта под списком сохранены. Номера остаются на карте.

Bathroom access привязан к прежнему стабильному ключу комнаты. Можно выбрать до двух значений — en-suite, shared bath, walk-in shower. Отсутствие выбора означает Not specified. Старые metadata без этого поля дают пустое назначение. В публичной карточке выбранные признаки стоят после типов кроватей через разделитель.

## Подтверждено Guesty API

Проверка выполнена ограниченными read-only GET. Реальные Rental 186 и 365 не синхронизировались и не изменялись. Диагностика сохраняла только структуру, статусы, количества и названия ванных, без ключей, приватных координат и данных гостей.

| Данные | Rental 186 | Rental 365 |
| --- | --- | --- |
| Open API publishedAddress | Присутствует, валидная пара lat/lng | Отсутствует |
| Отличие от private address | Публичная пара отличается от приватной | Приватная пара существует, но не используется |
| Airbnb integration | platform=airbnb2, COMPLETED, approved | platform=airbnb2, COMPLETED, approved |
| externalUrl | HTTPS www.airbnb.com/rooms/ID, совпадает с airbnb2.id | То же подтверждение |
| Airbnb-filtered reviews | 88 записей, 87 подходящих публичных guest reviews | 4 записи, 4 подходящих guest reviews |
| Booking Engine reviews.total | 89 | 4 |
| FULL_BATHROOM | 3 | 3 |
| HALF_BATHROOM | 0 | 0 |
| external-links endpoint | Пустой ответ 200 | Пустой ответ 200 |

Число Airbnb считается по полностью прочитанной channelId=airbnb2 выборке с проверкой listingId и externalListingId. Используются существующие критерии публичного guest-to-host review, исключение host/hidden/private/unsubmitted и дедупликация. Ограниченный обход допускает до десяти страниц по 100 записей. При неполной выборке число не объявляется подтверждённым. URL требует совпадения внешнего URL с активной одобренной Airbnb-привязкой. Произвольные external links не используются как доказательство.

reviews.total объединяет полученные отзывы Airbnb и Booking.com. Этот показатель не подставляется в Airbnb-ссылку. При подтверждённом URL и неподтверждённом счётчике выводится All reviews on Airbnb. Новая нормализация с реальными GET подтвердила 87 и 4, без записи этих значений в реальные Rental.

Open publishedAddress — приоритетный автоматический источник координаты дома. При его отсутствии допускается только явно заданная редактором публичная пара. Booking Engine address не используется как универсальный fallback. Поля его address сами по себе не различают приватное и публичное положение. Координаты из обычного address не переносятся в публичный DTO.

[Guesty Property Location Details](https://open-api-docs.guesty.com/docs/listing-location), [Review statistics](https://booking-api-docs.guesty.com/docs/average-review-score), [Reviews endpoint and externalListingId](https://booking-api-docs.guesty.com/reference/getreviewslist).

## Не предоставляется через проверенные ответы Guesty

У ванных подтверждены name, type, isPrivate и служебные идентификаторы. У Rental 186 названия Suite bathroom 1, Suite bathroom 2 и Full Bathroom. У Rental 365 — Twin bedroom bathroom, King bedroom bathroom и Master bedroom bathroom.

Проверенные ответы не содержат связи Bathroom Space → Bedroom Space, Floor, Steps, en-suite или walk-in shower. isPrivate не считается доказательством en-suite. Эти bedroom overrides вводятся редактором. Названия комнат не используются для автоматического установления связи.

Bathroom Spaces сохраняются отдельно в source.bathroomSpaces с id, name, type и подтверждённым статусом isPrivate. Спальни и общие помещения с кроватями сохраняются в source.sleepingSpaces. Связи фотографий не изменяются. FULL_BATHROOM и HALF_BATHROOM поддержаны нормализатором, хотя live HALF_BATHROOM у двух проверенных объектов не было.

Для Rental 365 опубликованная пара координат не предоставлена. По уточнению пользователя маркер использует вручную заданные публичные координаты Rental, если они сохранены. При отсутствии обеих пар маркер не создаётся. Nearby Places продолжает работать.

## Метаданные и миграция

- _awb_facts_strip_custom содержит ровно четыре объекта title/value. Автоматические характеристики в него не копируются.
- _awb_additional_house_rules содержит title/value/order. При отсутствии нового поля старые Cancellation и Deposit показываются как миграционный fallback. При штатном Update новый массив сначала сохраняется и проверяется, затем старые _awb_cancellation и _awb_deposit удаляются из текущей записи. Явно пустой новый массив не возвращает старые правила. Исторические ревизии совместимы с fallback.
- _awb_sleeping_access расширен bathroomAccess. Прежние level/steps и ключи комнат сохранены.
- _awb_contact_user_id и _awb_contact_intro используют прежнее хранение. Legacy profile opt-in и отдельная фото-настройка не участвуют в назначении.
- _awb_airbnb_url и _awb_airbnb_review_count не стираются автоматически, но исключены из публичного источника и редакторского сохранения. _awb_public_map_location сохраняет валидируемую ручную пару для fallback.
- По уточнению пользователя при отсутствии подтверждённого publishedAddress в Close by доступны Latitude/Longitude для публичного маркера. Приоритет имеет подтверждённый publishedAddress, затем ручная _awb_public_map_location. Поля проверяют пару и диапазоны и поддерживают Update, Preview и Revisions. Приватный address не используется.

Редакторские поля зарегистрированы с edit_post, nonce-проверкой штатного Update, Preview и Revisions. Guesty Sync и webhook сохраняют их. Источники source.publicLocation, source.airbnb и source.bathroomSpaces защищены существующим механизмом source metadata. Публичный DTO читает их локально. При частичной ошибке сохраняются прежние подтверждённые данные с отметкой stale. При подтверждённом удалении публичного адреса или канальной привязки соответствующая проекция очищается.

Версия Bridge — 0.3.12. Тема использует filemtime для обновления ресурсов.

## Требуется настройка внешнего сервиса

Google Maps по-прежнему требует ALOHA_GOOGLE_MAPS_BROWSER_KEY и JavaScript Map ID в ALOHA_GOOGLE_MAPS_MAP_ID, включённых Maps JavaScript API/billing и корректных HTTP referrer restrictions. Значения должны передаваться через конфигурацию WordPress вне Git. Для локальной установки разрешается origin http://127.0.0.1:8088 с подходящим wildcard пути.

Автоматическая ленивая загрузка карты и browser Global Privacy Control сохранены. Даже после настройки карты реальные записи получат новые source-проекции только при последующей разрешённой синхронизации. В этом этапе real Rental Sync не выполнялся.

## Проверено через mock, без live API

Google Maps проверяется контролируемым mock API, включая маркеры, выбор из списка, bounds, ошибки и отсутствие запроса до приближения секции к экрану. Production Maps не подтверждена.

Полный Guesty Sync, webhook и ошибки провайдера проверяются синтетическими ответами. Реальные GET подтвердили доступность публичного адреса, Airbnb-привязки и filtered review count. Guesty write operations, бронирования, платежи и массовый импорт фотографий не запускались.

## Визуальный эталон

Просмотрен Design v2 — Home page (unit)@1x.png и ранее распакованный Design v2 — Home page (unit)-html.zip. Сохранены Instrument Serif / Outfit и цветовые токены. Скриншоты используют синтетические фотографии и copy. Различия в объёме контента влияют на высоту секций. Отдельного Unit mobile-макета нет. Booking сохраняет текущую интеграцию, а карта без конфигурации показывает fallback.

## Результаты проверок

- PHP lint и JavaScript syntax прошли для изменённых файлов, git diff --check чистый.
- Полный Bridge local-smoke.sh прошёл. Он включает импорт, rollback, capabilities/nonce/REST, media fixtures, signed review selection, синхронизацию, webhook, queue locking/backoff/dedupe и сохранность редакторских данных.
- Дополненный import-smoke подтвердил сохранение publicLocation, Airbnb count и Bathroom Spaces при Sync. Ошибка review provider сохраняет прежний подтверждённый счётчик и отмечает его stale. Facts Strip, Additional rules и Bathroom access не перезаписываются.
- rental-kiss-check.php проверил схему четырёх ячеек, plain text, legacy policies, пустые и многочисленные правила, forged order, подтверждение Airbnb URL и внешнего ID, dedupe/host exclusion, отсутствие private-coordinate fallback и приоритет publishedAddress над ручной парой.
- rental-data-audit.php проверил FULL_BATHROOM, HALF_BATHROOM, отсутствие ванных и неизменность Sleeping/photo assignments.
- rental-content-browser.cjs проверил Update, Draft → Publish, два metadata-only Preview, последующее открытие и native Revisions. При восстановлении вернулись HTML, четыре Facts Strip значения, порядок правил и две bathroom options. Старые Cancellation/Deposit мигрировали через штатное сохранение. Ручная пара координат сохранилась в Close by.
- structured-editor-browser.cjs, rental-details-browser.cjs и rental-gallery-browser.cjs прошли регрессию.
- Публичный Rental проверен в Chromium на 320, 390, 768, 1024 и 1440 px. Проверены Home, Facts Strip, Close by без цифр/категорий, отзывы, introduction без повторного имени, Avatar fallback, House Rules, Bathroom access, sticky Booking, Embla мышью/клавиатурой/touch, галерея и защищённый Preview. Горизонтального переполнения нет. CLS холодных gallery checks остался ниже 0.05.
- Aloha Content integration suite прошёл в отдельной одноразовой WordPress-инсталляции, включая 59 Nearby Places checks и регрессии Area/Community.
- После проверок совпали все шесть SHA-256 контрольных сумм каждого из Rental 186 и 365 — post, mapping, editorial, source, featured image и editor service metadata.

Тестовые Rental, users, media и mappings создавались с отдельными случайными маркерами и удалялись finally/trap. Скриншоты с полностью загруженными локальными fixture-миниатюрами находятся в .local/evidence/m2-4b4/. Помимо public-1440.png/public-390.png сохранены editor-facts, editor-rules, editor-bathroom, editor-contact и editor-manual-map в двух ширинах. Макет и полученные секции просмотрены визуально. Пример close-by-map-mock.png относится к mock Maps API, а не к live Google Maps.

## Изменённые файлы и логические коммиты

### AlohaWPBridge

- c7fed94 Add revisioned four-cell Facts Strip editor and DTO
- 1b77fe7 Use direct administrator assignment and native WordPress avatars
- 9c08438 Add ordered House Rules repeater and migrate legacy policies safely
- 26fedab Sync verified Airbnb review counts and published Guesty locations
- 7ec840c Preserve Bathroom Arrangements and revisioned bedroom access overrides
- 02ec38c Retain manual public map fallback and verify simplified editor revisions

- `aloha-wp-bridge.php`
- `assets/public-contact-profile.js`
- `assets/rental-details-editor.js`
- `assets/rental-house-rules.js`
- `assets/structured-rental.css`
- `docs/m2-4a-rental-public-data.md`
- `src/Guesty/Client.php`
- `src/Guesty/Connections.php`
- `src/Guesty/PublicListingData.php`
- `src/Guesty/RentalDataAudit.php`
- `src/Guesty/ReviewCandidates.php`
- `src/RentalDetailsEditor.php`
- `src/RentalFactsStrip.php`
- `src/RentalHouseRules.php`
- `src/RentalPresentation.php`
- `src/RentalPublicData.php`
- `src/RentalSourceSync.php`
- `src/StructuredRentalEditor.php`
- `tests/import-smoke.php`
- `tests/rental-content-browser.cjs`
- `tests/rental-content-check.php`
- `tests/rental-data-audit.php`
- `tests/rental-kiss-check.php`
- `tests/rental-public-data-fixture.php`
- `tests/structured-editor-browser.cjs`

### Aloha Hello

- e90a6b2 Render custom Facts Strip cells, Home breadcrumb and simpler nearby list
- 058ca34 Use processed contact introductions and native avatar fallback
- f03ba1f Display editorial and Guesty house rules in a unified responsive grid
- 89a014d Render confirmed bedroom bathroom access beside bed arrangements

- `docs/m2-4b4-rental-editor-simplification.md`
- `scripts/rental-template-browser-fixture.php`
- `scripts/rental-template-browser.cjs`
- `theme/aloha-hello/assets/css/rental.css`
- `theme/aloha-hello/assets/js/rental-content.js`
- `theme/aloha-hello/inc/rental-content.php`
- `theme/aloha-hello/inc/rental.php`

Bridge dependency — [02ec38c7535920fe75a79a86e694e107f33035c0](https://github.com/web86/AlohaWPBridge/commit/02ec38c7535920fe75a79a86e694e107f33035c0), версия 0.3.12.
