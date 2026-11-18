# M2.4B.3 — Rental Content & UX Refinement

## Результат

About использует выбранное Guesty-описание или единый Custom HTML. Excerpt доступен в WordPress и DTO для других компонентов. Длинный текст первоначально ограничен высотой 300 px с градиентом 70 px. Кнопка Read the full description раскрывает весь HTML, а Show less сворачивает его. Короткий текст показан целиком. При отключённом JavaScript описание и правила доступны полностью. Фокус на ссылке в описании раскрывает его, чтобы ссылка была видима при работе с клавиатурой.

Amenities использует три CSS columns на desktop, две на tablet и одну на mobile. Группа сохраняется целиком в колонке. Кнопка считает все нормализованные пункты, включая unmapped. Close by выводит места в две колонки в редакторском порядке, затем карту на всю ширину. Карта загружается автоматически при приближении к экрану. Нумерованные места связаны с маркерами, а подтверждённое публичное положение дома отмечается оранжевым символом дома.

Отзывы сохраняют полные исходные сегменты в DOM, видимую атрибуцию и дату. Карточки ограничены тремя строками, подпись прижата к низу. Airbnb URL проверяется по HTTPS, домену airbnb.com и пути /rooms/ID. Редактор подтверждает принадлежность ссылки этому дому и вводит подтверждённый счётчик. House rules показывает подтверждённые Check-in/out, Cancellation и Deposit, а остальные доступные правила раскрываются кнопкой. Перед правилами находится Questions about this home с выбранным публичным контактом.

## Проверка источников Guesty

Выполнены ограниченные read-only GET-запросы для сопоставленного листинга. Вывод диагностики содержал только статусы, названия полей и количества.

- Spaces вернул HTTP 200 и 18 помещений. Ключи помещений включают _id, unitTypeId, accountId, name, type, roomNumber, isPrivate, beds, other и __v. Поля Floor/Level, Step count, Stair access и Accessibility в этом ответе отсутствовали. Общий bathrooms находится на верхнем уровне ответа и не подтверждает наличие ванной у конкретной спальни.
- Reviews вернул HTTP 200 и шесть записей текущего листинга из Airbnb и Vrbo. В Airbnb rawReview подтверждены public_review, publication flags и submitted_at. Публичное имя и тип поездки в этом ответе отсутствовали. Booking.com записей в текущем ответе нет. Существующая проверенная процедура выбора Airbnb/Booking.com сохранена.
- Open API listing вернул terms.minNights/maxNights и наличие координат в address. Cancellation и Deposit в проверенных terms отсутствовали. Агрегат reviews в этом ответе не получен.

Поэтому новые API-поля не угадываются. Floor/Steps, правила отмены и депозита, Airbnb URL и подтверждённое число Airbnb-отзывов вводятся редактором. Имя гостя и тип поездки пропускаются. Исторические аудиты и текущий ответ относятся к разным листингам и моментам времени.

[Guesty Rooms, Beds and Bathrooms](https://open-api-docs.guesty.com/docs/bedrooms-beds), [Guest and Host Reviews](https://booking-api-docs.guesty.com/docs/guest-and-host-reviews).

## Метаданные и совместимость

Bridge 0.3.11 регистрирует следующие редакторские поля с проверкой edit_post, штатным nonce сохранения и поддержкой Revisions.

- _awb_about_custom_html — HTML до 64 000 байт, обработанный wp_kses_post. Старые четыре поля остаются доступными. При первом Update существующий HTML имеет приоритет, иначе переносится старый Custom. На чтении работает совместимый fallback. Первое переключение Guesty → Custom подставляет локальные четыре раздела с заголовками. Явно очищенный HTML остаётся пустым.
- _awb_sleeping_access — ручные level и steps по стабильному SHA-256 ключу исходного помещения. Неизвестные значения не порождают плашку. Переименование комнаты при сохранении source ID сохраняет связь. Временно исчезнувшее помещение сохраняет свои overrides.
- _awb_cancellation, _awb_deposit, _awb_airbnb_url, _awb_airbnb_review_count — подтверждённые редактором публичные сведения.
- _awb_public_map_location — явно введённая публичная пара координат Rental. Приватные Guesty coordinates по-прежнему исключены. Без подтверждённой пары маркер дома отсутствует.
- _awb_contact_user_id, _awb_contact_intro — выбранный пользователь и индивидуальный текст. Имя, адрес электронной почты и изображение разрешаются из профиля при каждом чтении.

В профиле администратора есть разрешение Public Rental contact и выбор локальной фотографии Media Library. Разрешение хранится в _awb_public_rental_contact, фото — в _awb_public_contact_image_id пользователя. В селекторе Rental доступны только разрешённые администраторы. Отзыв разрешения или потеря прав администратора скрывает публичный блок. Роли, логины и user ID в публичную contact-проекцию не входят. При отсутствии локального фото используется инициал имени. Внешний Gravatar не запрашивается.

DTO about сохраняет старую форму. Новые поля aboutHtml, descriptionSource, contact, publicMapLocation, guestInformation, reviewLink добавлены к существующей проекции. Синхронизация Guesty сохраняет редакторский HTML, overrides и прочие новые метаданные. Preview использует штатную авторизованную autosave revision, включая изменения только метаданных. Update и восстановление ревизии сохраняют прежний post_content.

## Google Maps

Фактическая причина отсутствия карты в локальном WordPress — обе константы конфигурации отсутствуют. При этом у части выбранных Nearby Places есть валидные координаты. Страница показывает рабочий список и fallback. Реальные ограничения ключа, API activation, billing и Map ID невозможно проверить до предоставления конфигурации.

Для подключения требуется выполнить следующие действия.

1. В Google Cloud включить Maps JavaScript API и billing для проекта.
2. Создать браузерный ключ с API restriction на Maps JavaScript API и HTTP referrer restrictions для разрешённых локального и production origins. Для локального WordPress разрешить http://127.0.0.1:8088/*. Загрузчик отправляет origin-only referrer.
3. Создать JavaScript Map ID, совместимый с Advanced Markers.
4. Передать значения в окружение WordPress и определить ALOHA_GOOGLE_MAPS_BROWSER_KEY и ALOHA_GOOGLE_MAPS_MAP_ID в конфигурации установки. Значения не включаются в Git или отчёт.
5. В Rental выбрать опубликованные Nearby Places с координатами. Для оранжевого маркера дома указать подтверждённую публичную пару в Public guest information.
6. Открыть Close by, проверить запрос Maps JS и Console, интерактивность маркеров и корректность bounds. При включённом browser Global Privacy Control внешний Maps API не загружается.

Маркеры, выбор из списка, bounds, lazy initialization, сетевые ошибки, ошибка авторизации и browser opt-out проверяются контролируемым mock Maps API. Live Google Maps и production в этом этапе не подтверждены.

[Google Maps loader](https://developers.google.com/maps/documentation/javascript/load-maps-js-api), [Advanced Markers requirements](https://developers.google.com/maps/documentation/javascript/advanced-markers/migration), [API error diagnostics](https://developers.google.com/maps/documentation/javascript/error-messages).

## Визуальная проверка

Просмотрен оригинальный Design v2 — Home page (unit)@1x.png. Использован ранее распакованный Design v2 — Home page (unit)-html.zip, включая UnitDesign.dc.html. Сохранены Instrument Serif / Outfit и существующая палитра темы. Изменения проверяются по карточкам Sleeping, Guests said, Questions about this home и House rules в PNG.

Снимки используют синтетические фотографии и тексты. Реальный контент и объём сведений определяют высоту блоков. Booking сохраняет текущий контент интеграции. Карта без ключа показывает fallback. Отдельного мобильного Unit-макета не было, поэтому мобильная компоновка адаптирует desktop-референс. Имена и Family trip из макета не подставляются в неподтверждённые отзывы.

## Результаты проверок

- PHP lint, JavaScript syntax и git diff --check прошли.
- Bridge local-smoke.sh прошёл, включая импорт, REST permissions, редакторские данные, source sync, webhooks, подписи Featured Reviews и media queue fixtures. Финальный прогон завершился без PHP warnings.
- rental-public-data-fixture.php прошёл.
- rental-content-check.php проверил HTML/XSS, предел длины, приоритет миграции, сохранение очищенного HTML, защищённый профиль контакта, nonce, Airbnb URL/count, Floor/Steps и публичные координаты.
- rental-content-browser.cjs проверил первое заполнение Custom из Guesty, сохранение собственных правок, Draft Update, переход к Publish, Preview, два последовательных metadata-only Preview опубликованного Rental, неизменность опубликованного HTML при Preview, восстановление нативной ревизии и миграцию старых четырёх полей. Интерфейс проверен на 320, 390, 768, 1024 и 1440 px.
- structured-editor-browser.cjs прошёл полный прежний жизненный цикл Rental, Nearby Places, сохранение и Revisions.
- rental-details-browser.cjs прошёл sync dates, частичные и полные ошибки, recovery, Sleeping/Amenities/Rules и защищённый выбор Featured Reviews.
- Публичный rental-template-browser.cjs прошёл на 320, 390, 768, 1024 и 1440 px, включая Save/Share, sticky Booking, Embla с мышью/клавиатурой/touch, source mode, gradient/disclosures, contact, порядок Nearby Places, mock Maps и защищённый Preview.
- rental-gallery-browser.cjs прошёл регрессию M2.4B.1. Мозаика сохранила пять первоначальных запросов, повторное использование изображений и работу lightbox, slow/failed image и reduced motion.
- Для Rental 186 и 365 совпали все шесть SHA-256 проверок каждого объекта — post, mapping, editorial, source, featured image и editor service metadata.

Все изменяемые тестовые записи, users, media и mapping создавались с отдельным случайным маркером и удалялись finally/trap. Реальные Guesty Sync, booking, quote, payment и массовая загрузка фотографий не выполнялись. Диагностические запросы к Guesty были read-only GET.

Снимки сохранены локально в .local/evidence/m2-4b3/ — public-1440.png, public-390.png, editor-1440.png, editor-390.png, close-by-map-mock.png. Последний снимок демонстрирует контролируемый mock. Gallery performance measurements сохранены в gallery/gallery-performance.json.

## Изменённые файлы

### Aloha Hello

- `scripts/rental-template-browser-fixture.php`
- `scripts/rental-template-browser.cjs`
- `theme/aloha-hello/assets/css/rental.css`
- `theme/aloha-hello/assets/js/rental-content.js`
- `theme/aloha-hello/assets/js/rental-map.js`
- `theme/aloha-hello/inc/rental-content.php`
- `theme/aloha-hello/inc/rental.php`
- `docs/m2-4b3-rental-content-ux-refinement.md`

### AlohaWPBridge

- `aloha-wp-bridge.php`
- `assets/rental-details-editor.js`
- `docs/m2-4a-rental-public-data.md`
- `src/RentalDescription.php`
- `src/RentalPublicData.php`
- `src/StructuredRentalEditor.php`
- `tests/editor-browser-fixture.php`
- `tests/import-smoke.php`
- `tests/rental-public-data-fixture.php`
- `tests/structured-editor-browser.cjs`
- `assets/public-contact-profile.js`
- `src/RentalPresentation.php`
- `tests/rental-content-browser.cjs`
- `tests/rental-content-check.php`

Bridge dependency опубликована в codex/m2-3-rental-data-editor — [21c7d8721b62c620cf3d20bb5f4bfc808e4135fd](https://github.com/web86/AlohaWPBridge/commit/21c7d8721b62c620cf3d20bb5f4bfc808e4135fd), версия 0.3.11.
