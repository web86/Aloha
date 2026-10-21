# Aloha Hello + GuestyBridge — Architecture & API Feasibility

**Дата проверки: 21 октября 2026 года, Europe/Istanbul.** Язык сайта по исходникам — English; язык этого документа — русский. Этап: **M0, исследование и проектирование**. Код темы и плагина не создавался. Credentials не запрашивались; запросы к авторизованному Guesty API, бронирования и платежи не выполнялись.

**Актуализация дизайн-источников:** изучены новые `макеты/`: 10 автономных HTML-пакетов и 7 PNG, восстановлены все 19 прежних blob-assets; добавлены Area/Community/Article и подготовлен [реестр](/Users/sonoma/Documents/ALOHA/docs/design-inventory.md) и [план M1](/Users/sonoma/Documents/ALOHA/docs/m1-theme-implementation-plan.md). Обновлены §§2–4 и связанные вопросы/риски/M1. API-исследование §§8–10 не переобъявляется новым runtime-тестированием; архитектурные решения сохранены.

## 1. Executive Summary

Рекомендуется новый WordPress на отдельном хостинге: лёгкая собственная **Aloha Hello Theme**, Gutenberg для редакционного контента и независимый **GuestyBridge** для каталога и интеграции. Объекты хранятся локально и отдаются серверным HTML; доступность, цена на даты и результат бронирования подтверждаются Guesty. Смена темы не должна лишать сайт объектов, идентификаторов, редакционных полей или инструментов синхронизации.

Главное дополнение к предварительному исследованию — документированный **pilot массового `totalPrice`**, который нужно отдельно включить и проверить. Он меняет оценку нагрузки каталога; подробности и ограничения приведены в §10.2. [Accurate Stay Pricing][S10]

Для первой версии основной целевой путь — checkout через Booking Engine API **после** проверки процессора и полного сценария в sandbox. Более ранний выпуск возможен с согласованным Request to Book. Перенаправление в Guesty Booking Engine Website нельзя закладывать как готовый перенос quote: публичного контракта такого переноса не установлено (§9). [Booking flow][S12], [официальные ссылки Website][S23]

| Решение | Рекомендация | Уверенность | Основание | Что ещё проверить |
| --- | --- | --- | --- | --- |
| Новый сайт | Отдельные WordPress, тема, staging и hosting | Высокая | Принятое бизнес-решение, README | Хостинг, эксплуатация и миграционное окно |
| Редактор | Gutenberg + patterns; собственные динамические блоки только для каталога и брони | Высокая | Готовые макеты; настройки `theme.json` [WP][S37] | Удобство редактора для Алексея |
| Интеграция | GuestyBridge, CPT `gb_listing`, серверный API-клиент | Высокая | Переносимость CPT [WP][S36] | Минимальные поддерживаемые версии WP/PHP в M1 |
| Источники истины | Guesty — операционные данные; WP — SEO и редактура | Высокая | Требование задания; модель §7 | Полнота реальных данных |
| Основной API | Booking Engine API; Open API — дополнительная возможность | Средняя | Отдельная авторизация API [BE][S02], [Open API][S26] | Партнёрские права и коммерческие условия |
| Цена каталога | Проверить pilot `totalPrice`; fallback — quote выбранного дома | Средняя | Официальный pilot [S10] | Включение и соответствие состава гостей |
| Checkout A | Исследовательская опция, не зависимость MVP | Низкая для переноса quote | Есть домен и ссылки, нет установленного quote handoff [S22][S23] | Ответ Guesty и end-to-end проверка |
| Checkout B | Целевой путь при выполнении платёжных gates | Средняя документальная | Quote, instant, instant-charge [S07][S13][S15] | Процессор, 3DS, восстановление, состав списания |
| Checkout C | Допустимый первый режим после согласования процесса | Средняя | Inquiry endpoint [S14] | Блокировка дат, срок удержания, ручное подтверждение |
| «Save 15%» | Убрать процент и сумму до доказательства | Высокая | В проверенных схемах нет сопоставимого live Airbnb total | Методика и подтверждения клиента |
| Начало разработки | M1 и контрактная часть M2 доступны без credentials | Высокая | Макеты получены; Demo Mode изолирован | Уточнение мобильных страниц и контента |

**Уровни доказательности независимы.** Documented означает поддержку в документации, а не рабочую функцию аккаунта Aloha Hello. Все sandbox/production проверки в этом документе имеют статус **«Не выполнялись»**. M0 даёт основание начать фундамент; готовность live booking им не устанавливается.

## 2. Scope и исходные решения

### 2.1. Первоначальный архив: полученные материалы

Прочитаны `README.md`, разметка, CSS, тексты и спецификации во всех семи HTML-файлах архива `aloha-architecture-sources.zip` в корне проекта. SHA-256 архива: `96eb5afcf87d1d4c3fc7d9fc4a6ca2c066ffe6b3ed408f6c251c97044b82bc34`.

| Метка | Исходник в архиве | Назначение |
| --- | --- | --- |
| D00 | `designs/00-site-structure.html` | URL, навигация, сущности, общая дизайн-система |
| D01 | `designs/01-catalog-wireframe.html` | Поиск, карта, состояния карточек; спецификация **под** доской |
| D02 | `designs/02-catalog-design-v2.html` | Desktop-каталог, фильтры, активные chips, легенда карты |
| D03 | `designs/03-unit-wireframe.html` | Содержание объекта, новые редакционные поля, описание бронирования |
| D04 | `designs/04-unit-design-v2.html` | Desktop Unit и вынесенная спецификация двух состояний widget/разбивки |
| D05 | `designs/05-home-design-v2.html` | Desktop главная, тексты, секции, CTA |
| D06 | `designs/06-home-mobile-390.html` | Главная 390px, другой порядок секций, компактные карточки, footer/bottom bar |

В первоначальном архиве экспорты содержат `<x-dc>`, шаблонные bindings и ссылку на отсутствующий `support.js`; изображения с `/_blob/...` в него не входили: D02 — 10 ссылок, D04 — 14, D05/D06 — по 20. Это было ограничением первой версии M0. **Новые пакеты снимают техническую недоступность этих изображений и runtime (§2.3)**: все 19 уникальных blob IDs найдены локально, PNG просмотрены, HTML отрендерены. При этом реальные свойства/место съёмки, права на фотографии и production-интерактивность не доказываются самим наличием файла.

Ссылки `Area.dc.html`, `Community.dc.html`, `Article.dc.html` и другие `.dc.html` — ссылки исходного дизайн-окружения, а не готовые маршруты сайта. В первоначальном архиве соответствующие доски отсутствовали. **Area, Community и Article теперь получены и изучены в `макеты/`; Homeowners по-прежнему отсутствует.** Статус D00 «Design ready» сам по себе не заменяет проверку конкретного файла; текущее покрытие перечислено в §2.3.

### 2.2. Приоритет требований

1. Новое бизнес-решение и ограничения задания.
2. Функциональные спецификации D01/D03/D04 и содержание финальных D02/D04/D05/D06.
3. D00 как карта сайта; устаревшие технологические назначения исключены.

Boostly PMS, Homey, Elementor v4, JetEngine, JetSmartFilters, Crocoblock, WP Rocket и Imagify из старого handoff не становятся зависимостями нового проекта. Файл `snippets.php` в корне не использовался как основание интеграции и не изменялся. Рабочий alohahello.com, DNS, бронирования и deployment не затрагивались.

В scope исследования входят архитектура и спецификации будущей реализации. Личный кабинет, OTA channel manager, изменение Guesty-тарифов из WP, групповой online checkout, SaaS backend, MODX и автоматическое управление возвратами не входят в MVP.

### 2.3. Новые дизайн-источники: текущее состояние

Рекурсивно изучена папка `макеты/`: **17 файлов = 10 ZIP + 7 PNG**. В архивах 10 HTML (7 final Design v2, 3 wireframe), 10 README, 30 JS-вхождений и 85 media-вхождений. Уникальных visual assets19: 17 JPG и 2 SVG логотипа; локальных шрифтов, видео и tour assets нет. Все 19 IDs старых blob-ссылок восстановлены; одинаковые имена имеют одинаковые SHA-256. Исходные ZIP/PNG не менялись, распаковка выполнялась только во временную изоляцию с проверкой путей.

Семь final artboards покрывают шесть типов страниц: Home desktop/mobile, Catalog, Unit, Area Mauna Lani, Community Ka Milo, Guide Article manta rays. Три дополнительных wireframe — Area, Community, Article/IRONMAN. Семь PNG не считаются отдельными семью дизайнами сверх соответствующих HTML. Вместе со старым набором —13 логических источников: структура, 5 wireframe и 7 final artboards; четыре старых final совпадают с новыми после нормализации export wrapper, media paths, SVG serialization и boolean attributes.

| Новая группа | HTML внутри ZIP | PNG | Что стало доступно |
| --- | --- | --- | --- |
| Home | `HomeDesign.dc.html`, `HomeMobile.dc.html` | 1440×8129 и 390×8000 | Полный визуальный reference, hero/cards/portraits/logo, runtime |
| Catalog | `RentalsDesign.dc.html` | 1440×3600 | Изображения, карта как визуальный mock, controls/layout |
| Unit | `UnitDesign.dc.html` | 1440×8000 | Gallery/sample photos и developer widget state board |
| Area | `Area.dc.html`, `AreaDesign.dc.html` | 1440×7900 | Новый wireframe/final, hero/gallery/beaches/communities/facts/FAQ |
| Community | `Community.dc.html`, `CommunityDesign.dc.html` | 1440×5900 | Новый wireframe/final, amenities/nearby/owner estimate form |
| Article | `Article.dc.html`, `ArticleDesign.dc.html` | 1440×5700 | Новый article template: TOC/body/quick-facts/tables/related; wireframe с другим сюжетом |

Все 10 пакетов локально воспроизведены в изолированном Chromium при 1440×1000 или 390×1000, DPR1: page errors0, failed requests0, broken images0. Полный отчёт сопоставления, точные пути и размеры — [design-inventory.md, §§2–6](/Users/sonoma/Documents/ALOHA/docs/design-inventory.md). PNG принимается за эталон внешнего вида, HTML/CSS — за дополнительный источник геометрии/поведения. Native date/select/checkbox и full-page sticky capture отличаются от PNG; pixel-perfect совпадение не заявляется.

Теперь HTML **автономны как отдельные export packages**, но не как один offline HTML: нужны соседние runtime/media и внешние Google Fonts. Во всех 10 HTML нет viewport meta и `@media`; Home mobile жёстко 390px. `.dc.html` links часто ведут на отсутствующие в конкретном ZIP соседние страницы. Это не production implementation и не утверждённая responsive система. Ранее принятое решение о собственной чистой теме сохраняется.

Остаются ограничения: mobile Catalog/Unit/Area/Community/Article и tablet не представлены; нет layouts Homeowners/Contact/About/hubs/legal; Area содержит 15 layout media placeholders, Community5; нет настоящих films/tours и полного inventory media. Имеющиеся две SVG-версии logo снимают необходимость искать логотип для структурного M1, но не вопрос прав. Наличие 17 JPG не означает наличие проверенного фото-набора каждого из 24 реальных домов.

## 3. Аудит макетов

### 3.1. Что установлено по каждому исходнику

| Исходник | Зафиксированное решение | Неясность / требуемое изменение |
| --- | --- | --- |
| D00 | Три area landing, communities, guide с четырьмя рубриками, homeowners/invest, юридические страницы; 24 дома; индексируемый HTML | Список реальных объектов, отсутствующие marketing/hub layouts, отдельный Invest в финальном меню |
| D01 | Search применяет фильтры вместе; Clear all; карта ↔ список; единая модель карточки, 4:3, первые 5–6 фото, 2–4 features; **с датами total incl. cleaning/taxes + nights**; rating на карточке отсутствует | «Show more homes» конфликтует с D00 «все 24 сразу»; интерактивность описана, но не реализована экспортом |
| D02 | Две колонки карточек, map aside 44%, sticky top 96px; sticky filter bar; 9 available, chips; price sorting и Search as I move the map | При выбранных датах всё ещё from/night; booked dots ошибочно могут представлять любую недоступность; мобильного варианта нет |
| D03 | Галерея по категориям; beds/levels; editorial special features; nearby; manager CTA; данные о stairs/accessibility; price details | Историческая привязка к Boostly исключена; «не спишем сейчас», экономия и Instant требуют замены |
| D04 | Галерея 2:1:1, два ряда по 280px; восемь facts; features, 300–400 уникальных слов, четыре sleeping cards, шесть групп amenities; widget max-width 420px sticky | Два happy-path состояния недостаточны; этажи, 14 ступеней и «3.5 baths» требуют проверки; Save/Share не специфицированы |
| D05 | Hero/search → Standard → featured homes → areas → stats → reviews → guide → team → booking CTA → owners CTA → footer | 8+11+4=23; четвёртый featured home — Kailua-Kona; «каждый дом renovated» конфликтует с D01 «20 из 24» |
| D06 | Hero/search → **featured homes → Standard** → остальные секции; четыре featured cards сеткой 2×2; 390px и поля 20px; footer через details; нижний Call / Book Now | Не масштабировать desktop-карусель механически; краткие benefits не должны скрыть существенные оговорки; в mobile footer пропал Results & Reports |
| Новый Area final + wireframe | Full-width720px hero, intro/facts, homes4col сразу после intro, photo gallery, beaches4col, activities, communities3col, SEO/FAQ/help/owner CTA | Final меняет порядок wireframe: homes раньше SEO/FAQ; video и 15 placeholders не готовы; реальных 8 homes ещё нет |
| Новый Community final + wireframe | Ka Milo: split hero,5thumbs, intro/facts,8amenities,3homes, group CTA, map/nearby/rules/FAQ, owner form | Hero/pool media placeholders; mobile и form states отсутствуют; «one booking» не подтверждено |
| Новый Article final + wireframe | Manta hero640px, sticky TOC/body/quick-facts, tables, FAQ, related articles; общий article shell | Wireframe посвящён IRONMAN, не прежний вариант manta article; цены/90%+/возраст/советы в copy не проверены как факты |

### 3.2. Главная: содержание и данные

Hero использует H1, location eyebrow, подзаголовок и поиск Arrive/Depart/Guests. Состав гостей в D05/D06 пока представлен select 2/4/6/8+, тогда как каталог/Unit показывают взрослых и ребёнка. Рекомендация: общий guest picker с adults/children/infants/pets, отображать только поддержанные бизнесом категории; возрастные правила и итоговый `guestsCount` подтвердить отдельно. Не сводить ребёнка автоматически к взрослому при передаче в quote. [Quote request][S07]

Standard содержит восемь преимуществ: mattresses, cotton, inspection, support, renovation, step-free, families, resort access. Это локальный управляемый контент. Для выборочных привилегий нужны явные условия: нельзя обещать step-free, Hilton pass или renovation всем объектам. Матрасы Stearns & Foster, 150-point inspection, 24/7 и ответ «within the hour» — утверждения клиента, требующие документального владельца и даты проверки.

Featured homes: редакционный список WP IDs и порядок, факты из нормализованной модели; подборка не равна отдельному Guesty inventory. Счётчики areas вычислять из опубликованных объектов, а не вводить «8/11/4» вручную. На главной допускается компактный вариант общей карточки, но единая логика цены и статусов обязательна.

Stats «12 years», «24 homes», «4.95 / 1,500+ Airbnb reviews» не выводить из смешанного Guesty aggregate. Отзывы главной выбираются из проверенных публичных отзывов с привязкой к объекту. Guide — восемь тематических teaser-карточек, а не восемь рубрик: Golf/Beaches/Snorkeling/Sports/Volcanoes/Whales/Culture/Food распределяются по четырём рубрикам D00. Team/CTA/owners редактируются через Gutenberg; контакты имеют общий источник для header, footer и mobile bar.

### 3.3. Каталог: функциональные решения

Фильтры: даты, guests, area, bedrooms, сортировка; amenities/tier/community можно передавать из тематических ссылок, не расширяя основной UI без отдельного решения. «2 / 3 / 4+» неоднозначно: рекомендуются **минимальные** спальни «2+ / 3+ / 4+» с одиночным выбором. Если требуется точное количество — фильтровать локально и переименовать UI. Guesty `numberOfBedrooms` документирован как minimum. [Listings][S03]

Search применяет draft-набор целиком. Сортировка и удаление applied chip могут запускать обновление сразу. Пока ответ загружается, предыдущая выдача остаётся с индикатором обновления; её счётчик не объявляется результатом нового запроса. «9 homes available» показывать только после свежей проверки; неполный ответ обозначать «Проверено X из Y».

На карте и карточке одна цена одного типа. Подтверждённый quote: **«$X total · N nights»**, вторично «$Y/night average, incl. required fees and taxes», где Y — только арифметическое представление готового total/N. Это не самостоятельный расчёт сборов. Search estimate без quote подписывать как предварительный total. Без дат — «Select dates for total» либо проверяемая ориентировочная цена (§10). Сортировка по total доступна только для сопоставимых результатов в одной валюте.

Все текущие опубликованные дома, исходно ожидается 24, присутствуют в серверном HTML без бесконечной подгрузки. Вариант «Show more» из D01 исключить для текущего размера; после роста inventory отдельно принять решение о пагинации. API-пагинация при этом всё равно обрабатывается полностью.

### 3.4. Unit: данные и поведение

Структура: breadcrumbs → heading/tagline/Save/Share → gallery/categories/3D → facts → special features → editorial description → sleeping arrangements → amenities → nearby/map → reviews → manager → rules → similar homes. Desktop widget справа; на mobile предлагается одна booking sheet, открываемая нижней панелью. Это **новая спецификация**, а не найденный мобильный дизайн.

Gallery categories Interior/Outside/Neighborhood и tour URL хранятся локально; наличие списка фото Guesty не подтверждает его категоризацию. Если категории пусты — вкладку скрывать; без 3D URL вкладки нет. Без картинок — нейтральный placeholder с названием, без придуманного вида дома. Hero/gallery cover редактор выбирает явно.

Facts о площади, lanai, парковке, view, beach minutes и лестницах имеют проверенные локальные поля, пока их точный Guesty mapping не подтверждён. Sleeping arrangements используют room IDs, beds и type, но floor/steps/shower/access route — локальные дополнения. Общую комнату с sofa bed нельзя посчитать отдельной спальней. [Property descriptions][S06]

Similar homes: локально тот же area/community, близкая вместимость, исключение текущего и непубликуемых объектов, максимум три. Если заголовок «More rentals in Mauna Lani», все карточки должны соответствовать ему: в D04 одна карточка из Waikoloa Beach Resort. Либо исправить выборку, либо заголовок на «Similar homes». Даты поиска сохраняются при переходе; цена других домов требует собственной проверки.

### 3.5. Реестр несоответствий и конкретные решения

| Проблема | Решение до запуска |
| --- | --- |
| 24 против 23; Kailua-Kona в фильтре/featured, но не среди трёх area landing | Получить inventory CSV с Guesty ID и area. Возможный 24-й дом в Kona — **гипотеза**. Не создавать четвёртую SEO landing автоматически; при подтверждении Kona оставить объект в общем каталоге и фильтре, пересчитать тексты |
| D00 «данные руками не редактируем» против local SEO/features в D01/D04 | Сохранить synced snapshot и локальные overrides раздельно (§7) |
| from/night при датах | Принять спецификацию D01; добавить loading/estimate/quote/error варианты (§9–10) |
| «lowest nightly» против `basePrice` | Не называть basePrice минимумом PriceLabs; скрыть from до принятия проверяемой методики |
| One booking для соседних домов | «Planning stays in nearby homes? Our team can help» и локальная group enquiry, без обещания общей транзакции |
| Instant / no charge yet / персональная экономия | Условные тексты по реально выбранному checkout; неподтверждённые обещания удалить |
| Нет mobile catalog/Unit | Согласовать list/map fullscreen, filter sheet, booking sheet и ошибки на 390px; проверить также 320/768/1024px |
| Единая карточка против разных Home-версий | Общая модель данных и функции, два presentation-варианта: full и compact. Mobile Home 2×2 сохранить |
| Footer mobile потерял Results & Reports; Invest вынесен в меню | Единый menu source; согласовать видимость Invest и Results, не удалять страницу из-за компактной версии |
| Отзывы: 4.95 Airbnb, mixed aggregate 1–10, trip labels | Разделить источники и шкалы; «family trip» не выдумывать; правила §7.4 |
| New/Luxe/Deal и «Only 3 dates left» | Editorial badge с expiration/условиями; Luxe только с подтверждением; дефицитные заявления без доказательства не публиковать |
| Видео, фото, [N], TAT/GET, лицензия | Все 19 старых image assets теперь доступны, но утверждение о подлинности/правах не добавляется. Отдельно нужны films, реальные community/beach covers и inventory media. Наличие RB-24433 в макете не является проверкой лицензии |
| Header телефон «local» с 650 и обещание Hawaii team | Подтвердить единый контакт и допустимую подпись; код телефона сам по себе не доказывает место команды |

### 3.6. Дополнение по новым Area / Community / Article

**Area:** final порядок — hero → intro/facts → homes → pictures → beaches → activities → communities → editorial SEO/FAQ/help → owner CTA → footer. Wireframe ставил homes ниже SEO, но final/PNG имеет приоритет композиции. Показаны 4 карточки при спецификации «все 8 в HTML»; количество определяется реальными matching listings, не числом примеров в макете. Facts5 строк,9communities и placeholders — контентные заготовки, не подтверждённые inventory counts. Film loop15–20s/full2min отсутствуют как файлы; имеющийся coastline JPG позволяет сделать poster.

**Community Ka Milo:** split hero1:1.15 gap64, strip5thumbs h180, intro/facts, amenity cards4col, homes3col, nearby/rules/FAQ, embedded owner estimate form. Area/community descriptions остаются core WP Page content; linked listings идут из GuestyBridge, manual/community facts — из проверенной редактуры. Form fields (home/address, bedrooms, rented now, email/phone) — новый конкретный form reference, но validation/received/error и backend submission отсутствуют. В M1 только demo без отправки.

**Article:** manta final содержит TOC, тело, quick facts/CTA справа, два типа таблиц, FAQ и related cards; wireframe IRONMAN дополняет потенциальные article-specific вставки карты/подборки. Не считать две темы двумя полностью утверждёнными final articles. Копию manta с activity prices, safety/age/legal/medication statements использовать как образец структуры, а не как проверенную рекомендацию для публикации. На M1 — neutral sample copy, fact-check позднее отдельным процессом.

Проверенные geometry anchors: при 1440 wrap1280 border-box/40 → content width1200 иcontent x120; desktop header92/logo56, mobile header68/logo40, black/white SVG322×80. Home sections120px, Area/Community110px, Unit internal64px; mobile44–56px. Article grid220 + minmax(0,720) +300 с gaps56 фактически оставляет body568px при 1200 доступной ширины. Это устраняет неоднозначность «1280 контент плюс поля» из старого handoff.

Исходные HTML не имеют breakpoints. Предложения mobile Catalog/Unit и QA320/390/768/1024/1440 описаны в реестре§8 и плане M1; они не объявляются утверждёнными screens. Не копировать `<x-dc>`/runtime/inline styles в WordPress; строится собственная семантическая адаптивная реализация.

## 4. Карта страниц и компонентов

### 4.1. Полная карта URL из D00

| Страница / количество | URL | Тип / шаблон | Основные данные и статус дизайна |
| --- | --- | --- | --- |
| Главная, 1 | `/` | Front page + patterns | D05/D06 + H01/H02 и PNG1440/390 |
| Каталог, 1 | `/vacation-rentals/` | Archive `gb_listing` | D01/D02 + H03/PNG; mobile нет |
| Unit, ожидается 24 | `/vacation-rentals/[unit-name]/` | Single `gb_listing` | D03/D04 + H04/PNG; реальные slug неизвестны, mobile нет |
| Mauna Lani | `/big-island/mauna-lani/` | Page: Area | Новый W03/H05/P05 получен и изучен |
| Waikoloa Beach Resort | `/big-island/waikoloa-beach-resort/` | Page: Area | Общий H05 template; отдельного варианта нет |
| Waikoloa Village | `/big-island/waikoloa-village/` | Page: Area | Общий H05 template; отдельного варианта нет |
| Communities hub | `/big-island/communities/` | Page: Communities | To design |
| Community, N | `/big-island/[area]/[community]/` | Hierarchical Page: Community | Ka Milo W04/H06/P06 теперь получен; N/варианты других communities неизвестны |
| Guide hub | `/big-island/guide/` | Page: Guide hub | To design |
| Guide rubrics, 4 | `/big-island/guide/things-to-do/`, `/big-island/guide/food-and-drink/`, `/big-island/guide/top-spots/`, `/big-island/guide/events/` | Category archives | To design |
| Guide article, N | `/big-island/guide/[rubric]/[article]/` | Single post | W05 Article/IRONMAN и H07/P07 manta получены; остальные варианты отсутствуют |
| Property Management | `/homeowners/` | Page: Marketing | Wireframe only по D00; самого wireframe нет |
| Investment | `/homeowners/invest/` | Page: Marketing | To design |
| Calculator | `/homeowners/invest/calculator/` | Page: Calculator | To design; финансовые формулы и допущения предоставляет клиент |
| Investor article, N | `/homeowners/invest/guides/[article]/` | Hierarchical Page: Article | Общий визуальный article template |
| Rental Estimate | `/homeowners/estimate/` | Page: Form | To design |
| Design & Renovation | `/homeowners/design-renovation/` | Page: Marketing | To design |
| Results & Reports | `/homeowners/results/` | Page: Marketing | To design, реальные отчёты неизвестны |
| Owner FAQ | `/homeowners/faq/` | Page: FAQ | To design |
| Team | `/about/` | Page: Team | To design |
| Guest Info | `/guest-info/` | Page: FAQ/Info | To design |
| Contact | `/contact/` | Page: Contact, guest/homeowner forms | To design |
| Privacy | `/privacy-policy/` | Page: Legal | To design |
| Terms | `/terms/` | Page: Legal | To design |
| Rental Agreement | `/rental-agreement/` | Page: Legal | To design |

Vacation Rentals → area и Big Island → area ведут **на одни страницы**, дубли не создавать. `/big-island/` и `/homeowners/invest/guides/` могут потребоваться как технические parent pages; в D00 самостоятельных landing для них нет. Решение: содержательные hub при наличии контента либо явный redirect к соответствующему разделу, без пустых индексируемых страниц.

Step-Free Stays присутствует в footer D05/D06 без URL и доски: в MVP это ссылка на `/vacation-rentals/?feature=step-free` (предложение). Отдельная SEO landing только после контента и проверки подходящих домов. Групповая заявка: `/contact/?intent=group-stay` с выбранными домами в состоянии формы (предложение). Save — локальный список избранного в браузере, без аккаунта; отдельная страница не обязательна.

Служебные маршруты **предлагаются**, а не обнаружены в D00: `/booking/`, `/booking/result/` для B/C, закрытые от индексации; 404, search-empty и error templates. URL внешнего checkout не определён. Административные и REST-маршруты не включать в sitemap. Полный актуальный постраничный реестр с wireframe/HTML/PNG/media/readiness — [design-inventory.md, §7](/Users/sonoma/Documents/ALOHA/docs/design-inventory.md); H/P/W метки относятся к нему.

### 4.2. Реестр визуальных компонентов и данных

| Компонент | Где | Данные / источник | Особенности |
| --- | --- | --- | --- |
| Header, dropdown menu, logo | Все | WP menus, brand asset, contacts | Desktop nav; mobile logo/call/menu |
| Footer, legal, social | Все | Общие WP settings и menus | D06 — раскрываемые группы |
| Breadcrumbs | Внутренние | Canonical hierarchy, linked area/community | Unit остаётся с URL без area в slug |
| Hero | Home, marketing | Heading, copy, media, focal point | Без недоступных blob-изображений |
| Search bar | Home/catalog | Shared search state | Full/compact responsive variants |
| Listing card full | Catalog/area/community/similar | Listing ID, gallery, facts, tagline, features, badge, price status | 4:3, 2–4 features, без рейтинга |
| Listing card compact | Home mobile | Те же данные, иной набор видимых полей | Сетка 2×2 как D06 |
| Badge | Cards | Type/text, starts/ends, eligibility, proof | Один; Deal → New → Luxe |
| Price summary | Cards/map/widget | Currency, kind, dates, nights, amount, fetchedAt, expiresAt | Нельзя смешать estimate и quote |
| Area card | Home/hub | Title, image, copy, active count, page URL | Count вычисляемый |
| Standard benefit | Home | Icon, title, copy, applicability | 8 преимуществ, mobile сокращён |
| Statistics strip | Home | Metric, scope, source, asOf | Без placeholder цифр |
| Review card | Home/Unit | Public review, scale, date, source, listing | Без host-review гостя |
| Guide teaser | Home/guide/nearby | Post ID, media, title, rubric, URL | Восемь тем, четыре рубрики |
| Team/manager | Home/Unit | Person, role, portrait, contact CTA | Локальная редактура |
| Booking/owner CTA | Home/catalog | Copy, destination, booking mode | Текст зависит от live возможностей |
| Gallery/tour | Unit | Photo IDs/URLs, order, local category/alt, tour URL | Пустые категории скрыты |
| Facts strip | Unit | Capacity, rooms, baths, size, lanai/view/parking | null не заменять нулём |
| Special feature | Unit | Title, 2–3 sentences, photo, proof | Отдельные editorial поля |
| Editorial description | Unit | 300–400 слов, секции space/neighborhood/good-for | SSR, expansion без fetch |
| Sleeping card | Unit | Room ID/name/type/beds + local level/accessibility | Не выводить stairs по догадке |
| Amenities groups | Unit | Synced amenities + local grouping dictionary | 6 групп; unknown не терять |
| Nearby row | Unit | POI, mode, verified minutes, guide link | Не live directions по умолчанию |
| Map/pin/mini-card | Catalog/Unit | Display coordinates, same results/price model | Нет точного адреса без решения клиента |
| Rules/terms | Unit | Check-in/out, policy, deposits, rate-specific terms | Не заменять условия общим рекламным текстом |
| Widget/sheet/details | Unit/booking | Stay context, quote, rates, payment schedule, state | Состояния §9.4 |
| Forms/FAQ/article | Contact/marketing/guide | Core blocks, form config, editorial content | Формы гостя и владельца различаются |

Токены из D00/final CSS: Mirage `#16232A`, Deep Sea Green `#075056`, Wild Sand `#E4EEF0`, Page `#F3F6F6`, White, Orange `#FF5B04`; Instrument Serif для headings/italic, Outfit для текста/UI. Desktop container final — **1280px с padding40 внутри border-box**, что подтверждено новыми PNG/рендером: usable1200 при 1440. D00 `.wrap=1320` остаётся историческим расхождением; для M1 принят final grid. Body18px/1.6, mobile16px/1.55/H2=38px; buttons52px. Black/white logo SVG теперь доступны, visual media восстановлены; лицензии/реальное inventory content/font files/video — отдельные вопросы. Использовать responsive tokens, а не жёсткую ширину `.m{width:390px}`.

### 4.3. Реестр интерактивности

| Взаимодействие | Контракт поведения |
| --- | --- |
| Dropdown/mobile nav | Клавиатура, focus, Escape, кнопка раскрытия; ссылки доступны без hover |
| Date picker | Localized date-only, checkout после checkin; disabled/CTA/CTD учитываются; неизвестные даты не выдаются за свободные |
| Guest picker | Общая структура на трёх страницах; max capacity и возрастные правила валидируются сервером |
| Search/Clear/chips | Draft → applied; Clear reset; back/forward восстанавливает applied state |
| Sort | Recommended — local editorial order; total price — только полный сравнимый набор |
| Card gallery | Swipe/arrows/dots; управление фото не открывает Unit; отдельные buttons без вложения в anchor |
| Map sync | Card hover/focus подсвечивает pin; pin открывает mini-card; один active listing ID |
| Map bounds | Toggle search-as-move, debounce после idle; при off — Search this area; отмена устаревшего ответа |
| List/map on mobile | Fullscreen map и возврат со scroll position; filters/search state общие |
| Gallery modal/tabs/3D | Focus trap/return, keyboard/swipe, fallback без tour; загрузка тяжёлого embed по действию |
| Save/Share | Local favourites по stable ID, Web Share при поддержке, copy clean canonical URL fallback |
| Read more/amenities/FAQ | Содержимое в исходном HTML; корректные disclosure controls |
| Reviews/home carousel | Swipe и кнопки; без autoplay; pagination/all reviews доступны отдельно |
| Price details | Desktop inline; mobile bottom sheet, как указано в D04 |
| Booking submit | Повторный click не создаёт вторую операцию; возврат после timeout восстанавливает состояние |
| Help/owner/group forms | Явное назначение, подтверждение получения, spam control, ошибки; contact lead не равен reservation |
| Mobile bottom bar | Call `tel:`; Book Now ведёт к поиску/booking sheet, не создаёт reservation; safe-area и отсутствие перекрытия контента |

### 4.4. Карта реализации темы и граница M1

| Категория | Компоненты / владение | Контракт с другой стороной |
| --- | --- | --- |
| A — Theme | Header/Footer/nav/mobile menu, tokens/type, hero/CTA/editorial, Area/Community marketing cards, Guide/Article/TOC/FAQ layouts, generic forms appearance | Core pages/posts сохраняют content; тема задаёт layout/brand, не Guesty business state |
| B — GuestyBridge | Listing cards/collections/data, search/filter/applied state, live price/calendar/gallery data, booking widget/API statuses | Neutral blocks/markup/styles работают под другой темой; Aloha styles — optional override |
| C — Shared contracts | Search/URL parameters, listing view model, media projection, price-kind/guest/date picker, loading/error/empty/stale | Одна versioned model и один owner поведения; M1 fixture adapter заменяется M2 provider |

В M1 это только демонстрационные projections и темы-шаблоны; CPT/sync/REST/booking engine в тему не переносятся. Все demo properties/prices маркируются, формы не отправляют заявки, checkout preview не принимает оплату и не выдаёт подтверждение брони. Подробные контракты, этапы и acceptance — [m1-theme-implementation-plan.md](/Users/sonoma/Documents/ALOHA/docs/m1-theme-implementation-plan.md).

## 5. Архитектура WordPress

### 5.1. Сравнение вариантов темы

Это сравнительная инженерная оценка для данного проекта, а не результаты performance-тестов.

| Вариант | Соответствие макетам / скорость | Редактирование / сопровождение | Лицензии и стоимость | Вывод |
| --- | --- | --- | --- | --- |
| 1. Лёгкая тема + core Gutenberg/patterns | Высокий контроль SSR/CSS; небольшие frontend assets | Клиент редактирует тексты и подборки в заданных layouts | Нет обязательной лицензии builder; больше начальной работы над шаблонами | Лучший базовый вариант |
| 2. Gutenberg + множество custom blocks | Максимальная структурированность и preview | Хорошая управляемость, но каждый собственный block требует поддержки | Без builder-лицензий, выше стоимость разработки | Делать только необходимые динамические блоки |
| 3. Elementor для marketing | Возможно точное оформление; дополнительные frontend assets/другая модель templates | Визуальное редактирование проще привычному пользователю, но два способа поддержки | Зависит от выбранных функций и действующего плана; сумму здесь не фиксируем | Оправдан только подтверждённой привычкой клиента |
| 4. Hybrid: custom listing + Gutenberg/Elementor marketing | Хорошее разделение при строгой границе | Elementor-гибрид увеличивает количество редакторов и QA; Gutenberg-гибрид остаётся проще | Возможны дополнительные лицензии при выборе Elementor | Рекомендуется гибрид **1 + узкая часть 2**, без Elementor по умолчанию |

**Выбор:** собственная block theme с `theme.json`, templates/template parts, locked patterns для marketing и небольшим количеством серверных dynamic blocks GuestyBridge. Не строить отдельный SPA/headless frontend для 24 домов. WordPress поддерживает централизованные стили через `theme.json`; CPT рекомендуется регистрировать в плагине. [Theme settings][S37], [CPT portability][S36]

Клиент сможет менять тексты, изображения, ссылки, featured IDs/order, nearby и преимущества, публиковать guide и homeowners pages. Layout, поля цены, booking controls и API mapping защищены от случайной правки редактором. Начальный набор blocks: search, listing collection, listing details/gallery, booking widget; отдельный block для каждого небольшого элемента не нужен. Theme может использовать template overrides плагина, но не ходит в Guesty напрямую.

### 5.2. Границы ответственности

| Aloha Hello Theme | GuestyBridge |
| --- | --- |
| Brand tokens, responsive layouts, header/footer, navigation | CPT, taxonomies, listing identity, schema migrations |
| Patterns для главной, guide, homeowners, team, legal | Нормализованные listing/availability/quote DTO |
| Стили gallery/cards/map/widget, brand copy | API clients, credentials handling, rate limiting, jobs, sync |
| SEO-тексты и редакционное представление | Регистрация и сохранение локальных полей, которые должны пережить смену темы |
| Breadcrumb presentation и согласованный SEO integration | Блоки с нейтральным fallback HTML, REST и booking orchestration |

Один SEO-плагин допустим при необходимости: он отвечает за title/meta/canonical/sitemap, GuestyBridge предоставляет данные объекта. Не создавать конкурирующие JSON-LD graphs и canonical из трёх источников. Контент marketing — стандартные WP pages/posts/attachments, поэтому не привязан к теме.

### 5.3. Минимальная инфраструктура

Один managed WordPress host с HTTPS, MySQL/MariaDB, backup/restore, staging и системным cron для background jobs. Page cache для публичного HTML. Объектный persistent cache — при доказанной необходимости; начальный каталог помещается в WP DB. CDN/оптимизация media — по возможностям hosting. Redis, Elasticsearch, Kubernetes, отдельный Node backend и сервис событий в MVP не нужны.

Background jobs должны исполняться независимо от посещений сайта: только traffic-driven WP-Cron недостаточен для гарантированного расписания. Выбрать один WP-compatible job runner в M2; Action Scheduler допустим после проверки поддерживаемых версий, но не обязателен как внешняя инфраструктура. Нужны persisted retries, locks, status и ручной rerun; конкретная библиотека не является решением M0.

## 6. Архитектура GuestyBridge

### 6.1. Модули и контракты

Минимальные внутренние слои: **Guesty clients → normalization → repositories/services → WP blocks/REST/admin**. Это модули одного плагина, а не микросервисы.

| Модуль | Обязанности |
| --- | --- |
| Connection/Auth | Отдельные environment и BE/Open API connections; token reuse/refresh lock; health |
| Import/Sync | Пагинация, upsert, checkpoints, validate/commit, reconciliation |
| Listing repository | Нормализованная модель, local search, editorial merge, identity |
| Availability | Calendar/search freshness, invalidation, unknown states |
| Pricing/Quotes | Search estimate отдельно от personal quote; rate plans, expiry, coupons |
| Booking | Inquiry/instant/instant-charge adapters, attempt ledger, reconciliation |
| Presentation | Нейтральные SSR blocks, accessible defaults, theme overrides |
| Admin/Diagnostics | Source/local field UI, sync status, sanitized logs, capability tests |

Предлагаемые интерфейсы: ListingProvider, AvailabilityProvider, QuoteProvider и BookingProvider с одинаковыми нормализованными результатами для Demo/Live. Не вводить универсальный CMS framework. Нормализация внутри PHP не зависит от шаблонов; MODX рассматривается только отдельным будущим адаптером.

`Listing` содержит stable ID, source ID/type, facts, coordinates, media, amenities, sync health. `SearchResult` — IDs и count со статусами проверки. `PriceSummary` — `kind` (none/indicative/search_estimate/quote), amount/currency, stay, scope гостей, timestamp, source, optional quote reference/expiry. `Quote` — listing/stay, rate options, policy, lines, total, expiry и compatibility version. `BookingAttempt` — локальная операция, mode, state, quote/rate reference, remote reservation/payment IDs, sanitized error и timestamps. Эти имена — **внутренний проектный контракт**, не названия полей Guesty.

Capabilities: `canSearchStayTotal`, `canQuote`, `canInquiry`, `canInstant`, `canInstantCharge`, `canWebhooks`, `paymentProvider`. Это проверенные администратором возможности соединения, а не вывод из наличия кнопки в макете. По умолчанию неподтверждённые операции выключены.

Предлагаемый WordPress REST namespace `guestybridge/v1`: read-only local catalog/detail, availability/search, quotes, booking attempts/status, webhook receiver при наличии Open API. Это **наши будущие endpoints**, не Guesty endpoints. Схемы версионируются; наружу выходит только allowlist публичных полей. Произвольный API proxy, пользовательский base URL и произвольный `fields` запрещены.

### 6.2. Авторизация и безопасность

Для BE документирован `POST https://booking.guesty.com/oauth2/token`, `client_credentials`, scope `booking_engine:api`; token reuse с `expires_in`. Для Open API — другой host, credentials и scope. Смешивать токены нельзя. Числовое расхождение лимитов BE вынесено в §8.3. [BE auth][S02], [Open auth][S26]

Проектные правила:

- Credentials только на сервере, предпочтительно environment/host secrets. Если коммерческому WP-плагину нужен admin setup — зашифрованное хранение с ключом вне БД, без autoload и REST exposure. Этот этап credentials не получает.
- Token cache общий для PHP workers и jobs, keyed по connection/environment; один refresh под lock с небольшим запасом до expiry. При 401 максимум одна согласованная попытка обновления после проверки поколения токена; 403 не лечится циклом refresh.
- Public read endpoints ограничивают параметры и нагрузку; anonymous WP nonce сам по себе не является защитой от bot abuse. Rate limits на session/IP, ограничение диапазонов дат, request coalescing; challenge при аномалии.
- Admin actions требуют capability и nonce; публичные booking actions — session-bound unpredictable token, origin checks и серверная валидация. Browser amount, policy text и rate ID не считаются доверенными.
- Не отдавать `raw snapshot`, internal nickname, owner data, door/access codes, private guest reviews, Guesty/Stripe secrets. Quote/booking endpoints используют private/no-store; quote ID не даёт доступа чужой сессии.
- Запросы media и API используют HTTPS allowlist, ограничения размера/таймаута; это предотвращает SSRF при импорте URL. Логи исключают authorization, PAN/CVC, payment tokens и guest contact fields.
- Webhook подпись проверяется на raw body до применения изменений; IP allowlist не заменяет подпись. Детали обработчика в §10.5.

### 6.3. Надёжность и коммерческая переносимость

Settings, field schema и DB migrations версионируются; обновление имеет backup/rollback plan. Uninstall сохраняет контент по умолчанию, удаление — отдельное осознанное действие администратора. Деактивация темы не влияет на plugin jobs; деактивация плагина останавливает операции и выводит понятное сообщение вместо работающей кнопки бронирования.

Проверка независимости: включить стандартную WP-тему и получить список, single, search/quote UI с базовыми стилями; редактировать локальные поля и запускать sync из plugin admin. Коммерческий плагин не содержит телефоны Aloha, «три района», Signature или CSS бренда как обязательные значения: это настройки/словарь конкретной установки. Лицензионный server, биллинг подписок и multi-tenant SaaS не нужны M1–M4. Условия доступа Guesty для распространения остаются внешним gate (§13).

Demo Mode использует синтетические ID и fixtures, явно показывает «Demo — not bookable», не подключает production payments и не конвертирует demo booking в live. Допустимы fixtures, созданные по опубликованным схемам, с соответствующей пометкой. Они не являются сохранёнными ответами sandbox.

## 7. Модель данных и источники истины

### 7.1. Сущности и связи

| Сущность | Хранение | Связи / причины |
| --- | --- | --- |
| Дом | CPT `gb_listing` | Один WP post ↔ один source listing в конкретном connection/environment |
| Identity | Небольшая `gb_listing_map` table с unique key | `(connection_id, environment, guesty_listing_id)` уникален; WP ID тоже контролируется; исключает дубли при конкурентном импорте |
| Area | Taxonomy `gb_area` + WP landing Page | Listing → один основной area; term → canonical page ID |
| Community | Taxonomy `gb_community` + hierarchical Page | Listing → community; term meta `area_term_id`; редакционный текст/hero/video в Page |
| Tier | Taxonomy `gb_tier` | Signature/Designer/Essential — конфиг установки, один основной tier |
| Amenities | `gb_amenity` + mapping dictionary | Guesty codes → canonical slug/group/label/icon; неизвестные коды в очередь mapping |
| Card/editorial features | `gb_feature` + ordered listing meta | Short code для фильтра; отдельные text/photo/proof overrides на доме |
| Reviews | Private/non-queryable `gb_review` CPT | Source ID + channel unique, listing foreign key, moderation; публично только approved projection |
| Guide | Core posts/categories | Четыре rubric; related listings/areas/POI через IDs |
| Owner/investor content | Core hierarchical Pages | Article layout общий с guide; без отдельного investor CPT для малого объёма |
| Team/contact/brand | Core page content и site settings | Person reference для manager CTA; не Guesty guest record |
| Media | WP attachments + source mapping | Local alt/category/order/rights; remote source provenance |
| Calendar/search cache | Transients/object cache с version+expiry | Возобновляемые производные данные; не основа reservation history |
| Booking attempts, начиная с M4 | Private `gb_booking_attempts` table | Durable unique attempt key/state; транзакционная блокировка отправки; не transient |

Для 24 объектов spatial index отдельного сервиса не нужен: координаты/факты в meta и небольшой нормализованный dataset. Уникальная identity table оправдана идемпотентностью; calendar table добавлять только при измеренной потребности хранить/искать большой набор daily rows.

Каталог обслуживает CPT archive `/vacation-rentals/`; не создавать конкурирующую WP Page с тем же URL. Вступление и SEO archive хранятся в plugin/site settings. Area/community используют обычные pages, связанные с term ID; отдельные публичные taxonomy archives отключены или ведут на canonical landing. Guide post/category permalinks настраиваются под §4; инвесторские статьи — Pages с общим article template. Проверить rewrite conflicts, основной category и slug uniqueness в M1/M2.

### 7.2. Поля, владение и обновление

`_gb_source_*` — только synced snapshot; `_gb_local_*` — локальные настройки и overrides. Поля с префиксом здесь предложены для проекта. Display model объединяет их по **явным** правилам, не копирует всё в `post_content` при каждом sync.

| Поле / группа | Источник истины | Хранение и правило обновления |
| --- | --- | --- |
| Guesty ID, connection/env, source type/parent | Guesty + connection config | Immutable mapping; изменять только контролируемым relink с аудитом |
| Operational title | Guesty `title` [S04] | Synced; public title override в WP; nickname не выводить вместо title |
| `post_name` / canonical URL | WP | Создаётся один раз, затем sync никогда не меняет; ручная смена требует 301 |
| SEO title/meta/OG/canonical, unique body | WP | Plugin/SEO fields и `post_content`; импорт не перезаписывает |
| Guesty public description | Guesty [S06] | Snapshot/fallback; локальный текст приоритетнее; legal policy в рекламе не переопределять |
| Tagline до ~70 символов, Good for | WP | Редактор, revisions и подтверждение фактов |
| Capacity, bedrooms, baths, beds, times/timezone | Guesty [S04][S05] | Source numeric/null; bathrooms сохранять decimal, не округлять 3.5 до 3 |
| Room IDs/name/type/beds | Guesty [S06] | Upsert по room ID; локальные level/steps не привязывать к позиции массива |
| Level, steps, accessible path, shower/grab bars | WP подтверждение | Отдельные room/listing fields + proof/asOf; unknown ≠ false |
| Size/lanai/parking/view | WP до подтверждения точного mapping | Значение+units; конфликт источников в admin, без silent replace |
| Photos/URLs/caption | Guesty [S04] | Source media registry; local hero/order/alt/category сохраняются; удалённый source media помечается missing |
| Photo category и 3D/video | WP | Explicit mapping/local URL, права и poster; не распознавать категории из порядка фото |
| Amenities | Guesty [S04] | Sync canonical availability; local label/group/icon — WP; редактор не создаёт ложный operational amenity |
| Area/community/tier | WP canonical terms | Guesty tags — входящий сигнал [S03]; whitelist mapping, preview изменений; manual assignment/lock имеет приоритет |
| Features и expanded advantages | WP | Ordered items, title/text/photo; sync их не трогает |
| Badge type/text/start/end/priority | WP | New срок настраивается (например 60–90 дней); Deal требует существующего предложения; expiry по часовому поясу сайта |
| Address/lat/lng | Guesty [S05] | Source private snapshot; public coordinates/address precision — политика WP, без раскрытия лишнего |
| Nearby POI, minutes/mode/guide link | WP | Проверка человеком с датой; не извлекать время из расстояния по прямой |
| Base/indicative price | Guesty source + утверждённая методика показа [S05] | Timestamp/currency/label; не ручной live total |
| Availability/daily rules | Guesty [S09] | Cache с TTL, никогда не hand-edit в WP |
| Quote/rate/policy/tax/fees/payment schedule | Guesty [S07][S17] | Private quote snapshot с expiry; локальный total — отображение, не собственный тариф |
| Guesty review/public score | Guesty [S18][S19][S20] | Source text/scale/ID; WP moderation отдельным флагом |
| Home aggregate / marketing statistics | WP с доказательствами | Source scope, date, owner; не смешивать с unit aggregate |
| Related/featured order, manager/contact | WP | Stable WP IDs; не source order |
| Booking status/payment outcome | Guesty/provider [S15][S16] | Local attempt mirror с reconciliation; frontend «успех» не источник истины |
| Active/visibility и sync health | Guesty signals + WP publication policy | Раздельные `source_state`, `local_publish_state`, `last_seen`, `last_success`, `sync_error`; правила ниже |

### 7.3. Идемпотентная синхронизация

1. Под lock конкретного connection открыть sync generation; получить **все** страницы доступного inventory. `GET /api/listings` ограничен объектами выбранной BE instance, а не всеми домами аккаунта. [Listings][S03]
2. Validate IDs, required facts, duplicates, environment; upsert mapping и synced fields. Новый объект сначала draft с отчётом о недостающих данных; не публиковать с placeholder именем/SEO автоматически.
3. Сравнивать payload hash; не обновлять неизменившиеся media/посты. Checkpoint позволяет безопасно повторить job. Не держать DB transaction во время сетевых запросов.
4. Локальные поля обновляются только человеком. Тест: изменить SEO/slug/tagline/gallery category/room level, повторить импорт дважды — значения и WP IDs неизменны.
5. Missing reconciliation разрешена только после успешно завершённой **нефильтрованной** пагинации того же connection. Search с датами не является inventory sync. Неполный response, 401/429/timeout не помечает отсутствующие дома удалёнными.
6. Один пропуск/404 → `needs_review` и диагностика, без удаления поста/медиа/SEO. Подозрительные abrupt count drops блокируют reconciliation. Рекомендуемый quarantine — минимум два полных прохода с интервалом и проверкой администратора; это проектное правило, не Guesty гарантия.
7. Достоверный removal/inactive event после проверки соединения отключает новые booking actions и отправляет дом на review; публичная страница/redirect решаются редактором. Для исключённого из BE inventory дома хранится история. Автоматического hard delete нет. [Listing events][S30]
8. Restore/reimport того же ID возвращает тот же WP post. Relink нового Guesty ID к существующему дому — ручной процесс с проверкой identity; по похожему названию не объединять.

Отчёт синхронизации: generation, counts added/updated/unchanged/missing/quarantined, cursor checkpoint, source timestamp, retry count, correlation ID; sanitized error. Публичный HTML может остаться доступным при сбое, но действие бронирования обязано учитывать stale/unknown live status.

### 7.4. Отзывы и рейтинги

Документация описывает опубликованные отзывы Airbnb/Booking.com и также отзывы host о guest. Использовать только **отзывы гостя о проживании**, проверять channel-specific role/visibility/publication, Guesty listing ID; не путать external Airbnb ID с Guesty ID. [Review guide][S19]

Reference поддерживает `listingId`, `channelId`, `skip`, `limit` и фильтры дат. При пагинации deduplicate по source ID/channel, не по имени гостя. [Review reference][S18]

WP хранит source text отдельно от editorial moderation: approve/hide/reason, source attribution/date; не исправляет исходный отзыв без пометки. Trip type не выдумывается по фотографии или имени. Нужны права на повторную публикацию и ограничение персональных данных; эти вопросы передаются клиенту.

`reviews.avg` Guesty — объединённая шкала **1–10**, `reviews.total` — count. D05 «4.95 Airbnb» — отдельная метрика. Не делить агрегат автоматически на два и не приписывать Airbnb. Homepage aggregate допустим только по документированному набору reviews, с весами/count и явной шкалой; простое среднее средних домов неверно. Если нет надёжных данных — скрыть число, оставить согласованные цитаты. [Review statistics][S20]

## 8. Матрица Guesty API

### 8.1. Классификация и пределы проверки

- **Documented** — возможность явно описана официальной документацией.
- **Conditional** — документирована, но зависит от подключения функции, конфигурации, тарифа, процессора или прав.
- **Local implementation** — функция WordPress; API может поставлять только часть входных данных.
- **Unverified** — достаточный документальный контракт не установлен.
- **Unsupported** — есть прямое документальное ограничение конкретного способа. Отсутствие endpoint не объявляется невозможностью.

Во всех строках ниже **runtime = Не проверено: credentials/sandbox не предоставлены**. «Поля ответа» — документированные поля/примеры, а не наблюдённые ответы аккаунта. `—` означает, что подтверждённого поля не найдено. BE = Booking Engine API; OAPI = Open API.

### 8.2. Матрица возможностей

| Функция макета | Необходимые данные | API | Endpoint | Поля ответа | Статус | Ограничения | Что проверить |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1. Список всех домов | Inventory IDs, type, pagination | BE | `GET /api/listings` [S03] | `results`, `_id`, pagination | Documented | Только объекты instance; default limit 20, max 100; cursor | Все ожидаемые 24; полный проход, MTL/children |
| 2. Название/описание/параметры | Public title, descriptions, property type | BE | `GET /api/listings/{listingId}` [S04], descriptions [S06] | `title`, `publicDescription.*`, `propertyType` | Documented | Полнота account-specific; `fields` projection | Public title против nickname, sanitation, null |
| 3. Фото и категории | URLs, sizes, caption, category, cover | BE + WP | Listing detail [S04] | `pictures[].original/large/regular/thumbnail/caption` | Documented для фото; Local implementation для категорий | В изученной схеме категорий Interior/Outside/Neighborhood не установлено | Stable media key, обновление URL, local category persistence |
| 4. Гости/спальни/ванные | Capacity, numeric facts | BE | Listing detail [S04] | `accommodates`, `bedrooms`, `bathrooms`, `beds` | Documented | Integer bathroom schema против 3.5 в макете | Реальные half baths; не терять дробь; capacity children/infants |
| 5. Beds/этажи | Room IDs/type, bed counts, levels/steps | BE + WP | Detail с `fields=... bedArrangements` [S06] | `bedArrangements.bedrooms[].{_id,name,roomNumber,type,beds}` | Documented beds; Local implementation levels | Bathrooms в guide помечены Coming soon; roomNumber ≠ floor | Coverage rooms, shared spaces, local levels |
| 6. Amenities/accessibility | Codes, группировка, verified access | BE + WP | Listing detail [S04], enums [S05] | `amenities` | Documented amenities; Conditional конкретные признаки | Amenity label не сертификат доступности; no-step route может требовать локальных данных | Codes, bed height/entry/bathroom facts и доказательства |
| 7. Tags/areas/communities | Tags → canonical terms | BE + WP | Listings filters [S03] | `tags`, `address.neighborhood` | Documented tags; Local implementation site taxonomy | Повторные tags — AND; marketing community не гарантированно PMS complex | Реальные tags, mapping и manual locks |
| 8. Координаты/карта | Lat/lng, privacy, POI | BE + WP/Maps | Listing/address [S05] | `address.lat`, `address.lng`, адрес | Documented coordinates; Local implementation map | Точность и политика раскрытия | Missing coords, Kona, границы area |
| 9. Availability на даты | Candidate IDs, checkin/out, occupants | BE | `GET /api/listings?checkIn=...&checkOut=...` [S03][S24] | Подходящие listings | Documented | Это не ценовой quote/резервация; minOccupancy — вместимость | Restrictions/guest split, race с другим каналом |
| 10. Min nights/календарные ограничения | Date status, min nights, CTA/CTD | BE | `GET /api/listings/{listingId}/calendar?from=...&to=...` [S09]; definitions [S05] | `date`, `status`, `minNights`, `cta`, `ctd` | Documented | Полный набор правил/гранулярность требует проверки | Turnover, arrival/departure, gap/preparation, timezone |
| 11. Цена без дат | Currency, base/verified indicative rate | BE + WP | Listing prices [S05] | `prices.basePrice`, `prices.currency` | Documented base; Unverified «lowest future night» | basePrice не seasonal minimum | Методика from, период и freshness либо Select dates |
| 12. Total на даты | Stay total, rate, currency | BE | Pilot listings `fields=_id totalPrice ...` [S10]; quote [S07] | Search `totalPrice`; quote `rates.ratePlans[].ratePlan.money` | Conditional search; Documented quote | Search estimate не quote; nested shape отличается от guide | Включение pilot; search → quote reconciliation |
| 13. Taxes/cleaning/mandatory fees | Breakdown и included taxes | BE | Quote [S07]; tax update [S35] | `money.invoiceItems[]`, `fareCleaning`, `totalFees`, `totalTaxes`, `includedTaxesList` | Documented fields; Conditional completeness | Source configuration; inclusive tax нельзя прибавить повторно | Все обязательные charges, tax inclusive/exclusive, amount scale |
| 14. Discounts/coupons/rate plans | Eligible plans/promotions/coupon results | BE | Quote [S07], `POST /api/reservations/quotes/{quoteId}/coupons` [S08] | `rates.ratePlans`, `promotions`, `coupons` | Conditional | Coupon update заменяет набор; create coupons — comma string, update — array | Eligibility, expired/invalid, guest split; даты скидок |
| 15. Cancellation/deposit/payment terms | Selected policy, schedule/holds | BE | Quote retrieve [S21], payouts [S17] | `ratePlan.cancellationPolicy/cancellationFee`; schedule `payments`, `holds` в guide [S11] | Conditional | Не обещать free cancellation/deposit из static copy | Timezone deadlines, hold vs charge, schedule суммарно |
| 16. Quote + expiry | ID, created/expiry, rate IDs, total | BE | `POST /api/reservations/quotes` [S07], `GET /api/reservations/quotes/{quoteId}` [S21] | `_id`, `createdAt`, `expiresAt`, rates | Documented | Price offer не доказанная блокировка inventory; quote одноразовый для booking [S11] | Expiry, retrieval shape, stale price, reused quote error |
| 17. Instant / inquiry | Guest, quote, rate, policy, token при необходимости | BE | `POST .../quotes/{quoteId}/instant` [S13], `.../inquiry` [S14] | Reservation ID/status/confirmation | Conditional | Должен совпадать booking mode; inquiry token optional, instant required | Реальный status, calendar hold, exact policy/guest schema |
| 18. Безопасная оплата | Provider, token/3DS, payment outcome | BE + provider | `/instant-charge` [S15], `/verify-payment` [S16]; SDK [S11a], Stripe [S11b] | `reservation`, `payment`, `threeDSChallenge` для instant-charge | Conditional | Разные token contracts; обычный instant не равно paid | Processor account, 3DS success/fail/abandon, double submit |
| 19. WP → внешний Guesty checkout | Listing/dates/guests/rate/quote handoff | Website / BE | Domain [S22], Website links [S23] | Подтверждённого `checkoutUrl` для BE quote не установлено | Unverified handoff; Conditional domain | Общая ссылка/filtered session URL не контракт импорта quote | Поддерживаемый URL/SDK, rate ID, source parity, expiry |
| 20. Reviews конкретного дома | Public guest reviews и source IDs | BE | `GET /api/reviews?listingId=...` [S18][S19] | `data`, channel/raw review fields | Documented; Conditional контент | Airbnb/Booking.com; не host review о guest | Публикация, права, pagination, ID mapping |
| 21. Rating/count | Scale, scope, asOf | BE | Listing `fields=... reviews` [S20] | `reviews.avg`, `reviews.total` | Documented | Mixed scale 1–10; не homepage Airbnb 4.95 | Coverage, missing count, site aggregate methodology |
| 22. Updates/webhooks | Listing/calendar/reservation/payment invalidation | OAPI | Webhook subscription [S28]; listing [S30], calendar [S29], reservation [S31] | Event IDs, affected listing/range, object refs | Conditional | Отдельный доступ; calendar не покрывает все изменения | Подпись, duplicates, order, replay; BE-only polling fallback |
| 23. PriceLabs через Guesty | Nightly rates, min stays; source policy | Guesty/PriceLabs → BE | Integration [S25], calendar/quote [S09][S07] | Результирующие rates; отдельное поле «PriceLabs quote» не установлено | Conditional | Не дополнительный WP pricing engine | Connected listings, overrides/delays, one rate strategy |
| 24. Соседние дома / group booking | Relationships, совместные даты, общая операция | WP + BE; OAPI отдельно | Local selection; одиночный quote [S07]; V3 guide [S27]; manual offers [S44] | Single-listing quote; atomic group result не установлен | Local implementation поиска; Unverified automated group checkout | Guesty manual multi-listing offer не доказывает BE group transaction | Parent/subunit vs отдельные дома, partial failure, единая оплата |

Ни одна строка не утверждает, что Guesty в целом не поддерживает неизвестную нам функцию. Примеры **Unsupported для выбранного способа**: pre-SCA Stripe `tok_...` для обычного instant [S13]; переиспользование одного payment token для нескольких reservations [S11a][S11b]; добавление произвольных fees/taxes поверх rate plan через рассматриваемый BE quote flow [S11]. Это не запрет использовать согласованные Guesty-конфигурации или иные отдельно проверенные APIs.

### 8.3. Противоречия, миграции и правила адаптера

| Расхождение | Официальные источники | Инженерное решение / незакрытый вопрос |
| --- | --- | --- |
| Base URL | Quick Start: `https://booking-api.guesty.com/v1` и `/search` [S01]; API Intro и operation Reference: `https://booking.guesty.com/api/` [S00][S03] | Предварительный adapter — по operation Reference. Не смешивать пути и не fallback автоматически на другой host; Guesty должен подтвердить production/sandbox контракт |
| Token issuance | Intro/Quick Start — 5 renewals/24h; ниже Quick Start — 3; Authentication quota header — 5, troubleshooting — 3 [S00][S01][S02] | Reuse, один refresh, консервативный budget до ответа support; runtime headers фиксировать без токена |
| Sandbox legacy example | Sandbox page ещё показывает `/api/reservations` [S34]; migration снимает V1 31.03.2026 [S24] | Sandbox host не делает старый endpoint допустимым; использовать quote flow после smoke test |
| Quote JSON nesting | Guide: `rates.ratePlans[].money` и `_id` [S11]; Reference response: `rates.ratePlans[].ratePlan.money` и `ratePlan._id` [S07] | Versioned normalizer, строгая schema validation; неизвестную структуру не превращать в $0; не переносить OAPI JSON в BE |
| Listing schema quality | Definitions: `id`, `accomodates`, amenities String; Reference/example: `_id`, `accommodates`, array [S05][S04] | Основной контракт по operation schema, aliases только по подтверждённым fixtures. Отдельный regression case |
| Bathroom count | Reference integer [S04], D04 3.5; rooms guide bathrooms Coming soon [S06] | Decimal в модели; фактический shape в sandbox; room bathrooms не считать гарантированными |
| Fees support | Quote guide запрещает request-time добавки [S11]; Help Center описывает автоматические source-specific fees [S33] | Это разные операции: настроенные fees получает Guesty; WP их не дописывает к цене |
| Website vs API sources | Website и BE API — разные manual sources [S33][S45] | Равенство totals, coupons и automations не предполагается; тестировать конфигурацию источников |
| Price sorting pilot | Описанная последовательность basePrice → totalPrice может не дать глобальный порядок по total [S10] | Проверить; для полного малого набора сортировать локально по одной price kind/currency, иначе отключить misleading sort |
| Instant-charge schema | `confirmationToken` marked required, но описание требует ровно одно из него и `initialPaymentMethodId` [S15] | Выбрать processor-specific payload в sandbox; не заполнять оба «для надёжности» |
| Reservation legacy notices | OAPI changelog 30.07.2026 помечает legacy reservation operations к retirement [S40]; BE hard EoL — отдельное уведомление [S24] | Не обобщать EoL на все APIs. Новый OAPI adapter — только актуальный V3, если вообще нужен |
| Legacy webhook dates | Reservation guide одновременно содержит «end of year» в таблице и «end of February 2027» в рекомендации [S31] | Предпочесть v2 и запросить точную дату legacy retirement; case event names проверить по subscription schema |

Booking Engine changelog проверен: 15.02.2026 добавлен `includedTaxesList`; 18.05.2026 — guide upsell workflow. [Changelog][S35][S35a] Это важно для разбивки: уже включённый налог — annotation, не дополнительная сумма.

Open API changelog содержит изменения 18.10.2026, включая basic fees beta и channel commissions v2; чтение этой конфигурации не даёт live Airbnb checkout total. [OAPI changes][S39] Запись 21.10.2026 видна в индексе, но её страница не была получена исследовательским браузером (cache miss); содержание не домысливалось. [Changelog index][S38]

Часть Response body вложений Reference не раскрывалась в текстовом представлении страниц. Для listing/quote/instant-charge использованы также официальные индексируемые schema/example excerpts; цельный скачанный OpenAPI bundle в исследовании не зафиксирован. Поэтому M3 должен закрепить конкретные версии схем и реальные обезличенные fixtures до разработки live parser. Ссылки `.md`/`llms.txt` через исследовательский инструмент не загрузились; это ограничение способа чтения, а не свидетельство отсутствия документации/API.

### 8.4. Open API: отдельная ветка, не скрытая зависимость

OAPI документирует `POST /v1/quotes`, `GET /v1/quotes/{quoteId}` и создание reservation через `POST /v1/reservations-v3/quote`. В request используются собственные поля, включая `source`; их нельзя автоматически подменить BE source. Reference V3 также указывает возможность создания reservation по BE quote, но это **не** handoff в hosted Website checkout и не установленная payment guarantee. [OAPI quote][S58], [V3 reservation][S59], [V3 flow][S27]

Если позднее выбран OAPI booking adapter, `ignoreCalendar`, `ignoreTerms`, `ignoreBlocks` задаются явно `false`: guide предупреждает о defaults, способных обходить ограничения. Этот адаптер требует отдельной проверки политики, финансов и post-booking payment flow. Не использовать его для «исправления» недоступного дома. [V3 flow][S27]

Обнаружен и `POST /v1/quotes/multiple` с массивом `quotes` и возможностью отдельного представления accommodation components. Это документированная **потенциальная альтернатива** fan-out, если разрешён OAPI. Не установлены batch-size limit, partial failure semantics, latency, соответствие BE source/fees и пригодность для 24 объектов в одной операции. Наличие multiple quotes не доказывает atomic multi-home reservation/payment. В MVP оставить capability исследованием; решение принимается после ответа Guesty и sandbox, а не только по названию endpoint. [Multiple quotes][S60]

## 9. Booking/Checkout Feasibility

### 9.1. Сравнение A / B / C

| Вариант | Реально установленный контракт | Сложность / преимущество | Риски / зависимость | Рекомендация |
| --- | --- | --- | --- | --- |
| A. WP → Guesty Website | Custom domain/subdomain; общая properties page и пример filtered session link [S22][S23] | Меньше собственной payment UI; hosted experience | Нет подтверждённого BE quote/rate handoff; другая session/source; повторный выбор и цена; branded subdomain ≠ отдельная checkout-only page | Рассматривать только после ответа Guesty; допустим обычный переход с честным повторным расчётом |
| B. BE API full checkout | Quote → выбранный rate → guest/policy → token → reservation; отдельный charge-first flow [S07][S13][S15] | Больше разработки; единый UX, контролируемый quote flow | Processor rights, guest schema, 3DS, payment state, retries, API dependencies | Основной целевой путь после sandbox gate |
| C. Request to Book | Quote → rate → inquiry с guest; token optional [S14] | Средняя сложность; можно обойти собственный ввод карты | Request может блокировать даты; нужен ручной workflow, сроки ответа и payment collection | Хороший phased launch, если клиент принимает ручное подтверждение |

Обычный локальный contact lead — ещё более простой fallback, но **не API inquiry и не удержание дат**. В UI писать «Request assistance», подтверждать только получение обращения. Не переименовывать lead в успешную бронь.

### 9.2. A: внешний Guesty Booking Engine Website

Домен `book.alohahello.com` — **предложение**, DNS сейчас не меняется. Guesty описывает подключение custom subdomain через CNAME и SSL для Booking Engine в целом. Это не подтверждение, что продукт отдаёт checkout-only страницу на поддомене. [Custom domain][S22]

Официальный материал о ссылках показывает generic properties URL и пример search session URL. Не установлены стабильная спецификация переноса всех dates/guests, публичный listing-specific checkout contract, параметры `ratePlanId`/`quoteId` и гарантия принятия BE API quote. Не придумывать redirect URL и не reverse-engineer временные browser tokens как production контракт. [Website links][S23]

Переход может породить новый расчёт для source Website; одинаковый календарь не доказывает равные fees, promotions, payment automations. [Source fees][S33] Поэтому до ответа Guesty кнопка не обещает «Pay exactly this amount in Guesty». Если A выбран с повторным расчётом, это явное продуктовое изменение: «Continue to Guesty to confirm price and booking details».

Успешность A требует теста с реальным разрешённым sandbox instance: дом/даты/взрослые/дети/pets, rate/coupon, сумма/валюта, истечение, no cookies, mobile browser, back/forward, custom domain и return path. Любая разница фиксируется, а не скрывается. Hyperlinks **после** confirmation из отдельной Help Center статьи — обратные ссылки из Guesty, они не доказывают входящий handoff. [Dynamic links][S46]

### 9.3. B: checkout через API

Последовательность будущей реализации:

1. Восстановить server-validated listing/stay/guest context; запросить quote, показать выбранный eligible rate, all-in total, cancellation/payment terms и expiry. Правильный `ratePlanId` берётся из этого quote. [Create/retrieve][S07][S21]
2. Собрать только необходимые guest details и acceptance policy. Точные nested required fields `guest`/`policy` закрепить operation schema и sandbox контрактом; не объявлять согласие принятой политикой по наличию checkbox в HTML.
3. Определить processor **данного listing**, не всего сайта. Для GuestyPay использовать поддерживаемую SDK integration; для Stripe токен создавать в том же connected processor account. Не создавать вручную отдельного Stripe Customer в обычной BE tokenization flow. [GuestyPay][S11a], [Stripe][S11b]
4. Выбрать **один** подтверждённый payment model:
   - Обычный `/instant`: payment method token `ccToken`, для Stripe SCA `pm_...`; booking confirmation и фактический сбор средств проверяются раздельно. [Instant][S13]
   - `/instant-charge`: другой контракт — Stripe `confirmationToken` либо non-Stripe `initialPaymentMethodId`, не обычный `pm_...` вместо confirmation token. Уточнить, какая часть stay amount списывается и как сочетается с payment automations. [Instant charge][S15]
5. GuestyPay `PENDING_AUTH` проходит SDK/3DS и `POST /api/reservations/{reservationId}/verify-payment` с `paymentId`; результат проверки определяет подтверждение или закрытие. Это отдельная ветка, её нельзя завершать success по первому HTTP 200. [Verify payment][S16]
6. Сохранить remote IDs; получить актуальный reservation details и допустимый payment outcome. Confirmation page различает «reservation confirmed», «payment processing», «additional authentication required» и «request received». [Reservation details][S32]

Guide предупреждает: обычный instant может создать reservation даже при invalid payment method. Поэтому надпись «paid» нельзя выводить из `status=confirmed`. Оплата при подтверждении, частями и authorization hold требуют отдельных текстов и статусов. [Booking flow][S12]

Для Stripe 3DS/SCA использовать документацию именно выбранного Guesty-supported flow; не вставлять произвольный собственный PaymentIntent, который Guesty не сможет сопоставить. Официальный Stripe two-step flow — полезная основа confirmation-token UI, но не доказательство автоматической совместимости любого merchant setup. [Stripe confirmation][S48]

Безопасный ввод карты — hosted fields/SDK: PAN/CVC не проходят через WP REST, БД, формы поддержки, аналитику или логи. Токенизация сокращает PCI scope, но не отменяет обязанностей оператора; конкретная форма compliance зависит от интеграции и проверяется с provider. Не обещать «PCI out of scope» или заранее назначать SAQ. [Stripe security][S47]

**Восстановление и защита от дублей — проектные требования:**

- До первого mutation создаётся durable attempt с уникальным server key и session ownership; concurrent double-click получает существующую попытку.
- Состояния attempt: `created → submitting → authentication_required / processing → confirmed / request_received / failed / outcome_unknown`. Это локальная модель; provider status хранится отдельно без переименования.
- После timeout/502 на reservation/payment mutation не повторять POST вслепую. Если reservation ID известен — читать details. Если нет — reconciliation по разрешённому correlation/search механизму Guesty; его контракт пока **Unverified**. При отсутствии безопасного lookup — `outcome_unknown`, операторская проверка, новая отправка заблокирована.
- Поддержка `Idempotency-Key` у выбранных BE mutations не установлена. Локальная блокировка не гарантирует remote exactly-once после сетевого разрыва; это обязательный вопрос Guesty и gate платёжного запуска.
- Для известного payment ID получать проверенный статус; не брать произвольный ID от браузера. Browser return/thank-you page не подтверждает charge.
- При изменении dates/occupants/rate/coupon прежний attempt нельзя продолжить с другой ценой. Создаётся новый quote/context после проверки результата старой операции.
- При наличии OAPI отслеживать payment events, включая authenticationRequired/failed/received, и перечитывать факт. При отсутствии — определить достаточный BE polling/operator workflow; отсутствие webhook не означает успешную оплату. [Payment events][S41]

Документация рекомендует ограниченные retries и предостерегает от повторов изменяющих данные запросов. GET и mutation должны иметь разные retry policies. [Failed requests][S43]

### 9.4. Состояния booking widget и mobile

| Состояние | Что видит гость | Разрешённое действие | Данные и mobile |
| --- | --- | --- | --- |
| Без дат | Select dates for total; guest picker; условный indicative from | Check availability открывает календарь | На mobile bottom CTA открывает sheet, не checkout |
| Невалидный ввод | Ошибка рядом с dates/guests, без суммы нового поиска | Исправить поля | Focus на ошибке, aria-describedby; выбранное не теряется |
| Проверка доступности / quote | Skeleton вместо цены, текст проверки | Можно поменять даты/отменить; submit брони disabled | Один request version; старый response отбрасывается |
| Доступно, search estimate | Предварительный total, N nights | Get confirmed quote / открыть Unit | Нет claim о фиксации цены; sheet запрашивает quote |
| Quote получен | Total/currency/stay/guest scope, rate, expiry, details, terms | Continue / Request to Book по mode | Mobile details bottom sheet; одна сумма, нет desktop sidebar |
| Недоступно | «These dates are unavailable», причина только если она достоверна | Изменить даты, похожие дома, помощь | Нельзя называть любую ошибку «Booked» |
| Min nights/arrival rule | Конкретное ограничение из проверенных данных | Выбрать допустимые даты | Календарь объясняет disabled date; не только серый цвет |
| API error / 429 / offline | «Unable to confirm price/availability now» | Controlled Retry, contact | Поля сохраняются; cached price не выдаётся за live offer |
| Quote expired | Старый total помечен истёкшим; submit заблокирован | Refresh quote | Новая цена явно сравнивается; согласие повторно при изменении |
| Цена изменилась | Старый/новый total и изменившиеся условия | Accept updated offer либо изменить поиск | Без silent replace перед оплатой |
| Создание booking | Прогресс и operation reference | Повторная отправка disabled | Перезагрузка восстанавливает attempt, не создаёт новый |
| 3DS / pending payment | Требуется authentication либо processing | Пройти provider challenge/проверить статус | Возврат со стороннего flow восстанавливает ту же попытку |
| Outcome unknown | «We are checking your booking. Please do not submit again» | Проверить статус / support reference | Не объявлять fail, если remote outcome неизвестен |
| Reservation / request получены | Confirmation code либо request reference, реальные next steps | Сохранить детали/связаться | «Paid» только при подтверждённом payment result |

На desktop widget sticky с учётом высоты header; при превышении viewport прокручивается доступно. На mobile одна нижняя панель с total/status и CTA, sheet с date/guest picker и последующими шагами. Call доступен отдельно; исключить две конкурирующие fixed booking bars. Учесть keyboard, safe-area, Escape/back, focus return и scroll restoration. Это требуемые дополнительные mobile-макеты для M1.

`expiresAt` — основной expiry источника; значение «24 часа» из guide не хардкодировать. Сохранение цены в quote не является доказательством hold календаря. На expiry очистить возможность submit, re-quote по действию гостя; не генерировать бесконечные фоновые quotes. [Quote flow][S11]

### 9.5. C: Request to Book

Показывать сумму из quote и текст «Request to book», собирать guest details/terms, отправлять inquiry на выбранный rate. `reservedUntil` задаётся **явно** после согласования: reference допускает -1/12/24/36/48/72 часа, default -1; бесконечный hold не оставлять случайным default. [Inquiry][S14]

Не считать API-слово inquiry безусловно неблокирующей заявкой: guide показывает `reserved`; ручные UI offers различают Inquiry/Reserved иначе. Это важный терминологический риск, который закрывается sandbox тестом календаря и письменным процессом менеджера. [BE booking options][S12], [Manual offers][S44]

Нужны ответственный сотрудник, реальный SLA, срок hold, подтверждение/отказ и безопасная дальнейшая оплата через разрешённый Guesty процесс. Не собирать карточные данные по email/в произвольной WP-форме. После отправки пользователь получает request reference и статус ожидания, а не Instant booking confirmation. До поддержки Guesty доступна только локальная форма помощи с соответствующей подписью.

## 10. Search, Pricing & Map Architecture

### 10.1. Два уровня поиска и shared state

Локальный WordPress индекс отвечает за area/community/tier/features/amenities, rooms/capacity и display coordinates; Guesty — за актуальный stay result и цену. Сетевая ошибка не меняет local matches на «0 available».

Предлагаемый канонический search state сайта: `checkIn`, `checkOut` в YYYY-MM-DD; `adults`, `children`, `infants`, `pets`; `area`, `community`, `bedroomsMin`, `feature`, `sort`, optional bounds/map toggle. Это параметры **нашего сайта**, не обещание такого контракта у Guesty Website. Legacy `arrive/depart/guest` из D03 при входе переводятся в общую модель; неоднозначный `guest` требует явных adults assumptions в UI.

URL с allowlist — основной переносимый источник состояния; sessionStorage — вспомогательное восстановление текущей вкладки, без PII/credentials. Приоритет: explicit URL → текущая session → defaults. При переходе Home → catalog → Unit → similar сохранять stay/occupants, а не принудительно применённый area к самому дому. Share по умолчанию копирует clean canonical; отдельное Share this search может содержать безопасные filters. Back/forward восстанавливает applied state и map/list view.

Алгоритм: local candidates → shared live availability/search fetch → intersect по source IDs → normalized result → SSR initial shell + progressive live update → quote выбранного дома. Для малого inventory удобно получать общий live набор на dates/occupancy и фильтровать локальные признаки/границы без нового Guesty запроса при каждом движении карты. Listing search подтверждён документацией; параметры capacity не считать доказанной полною ценой для ребёнка/pet. [Listings][S03]

### 10.2. Массовый total: важное дополнение

**Pilot `totalPrice` документирован для `GET /api/listings`:** запросить `_id` и `totalPrice` в `fields`, передать `checkIn/checkOut`; ответ содержит estimated mandatory-inclusive stay total минимального rate plan, в валюте объекта. Предел — 50 listings/page, следующий cursor — `pagination.cursor.next`. Доступ к pilot согласовывается отдельно. Описанная сортировка сначала использует basePrice, затем totalPrice. [Accurate Stay Pricing][S10]

Следствие для проекта: для 24 объектов **возможен один** search request при достаточном limit и полной странице; это гипотеза производительности, не измерение. Search price не заменяет выбранный quote. Guest breakdown, coupons, выбранный rate, latency, pagination и равенство quote требуют sandbox. Если часть данных не поддерживается функцией, не подписывать estimate как подтверждённый total для всего состава гостей.

### 10.3. Нагрузка и fallback без pilot

Следующие значения — расчёт по числу запросов, **не benchmark** и не обещание SLA. Документированные BE limits: 5/s, 275/min, 16 500/hour; не запускать 24 quotes параллельно. [BE limits][S00]

| Сценарий для 24 кандидатов | Upstream запросы без cache | Следствие |
| --- | --- | --- |
| Pilot search с достаточной страницей | Потенциально 1 search + 1 quote выбранного дома | Основная гипотеза для проверки |
| Quotes всем без pilot | 1 search + 24 create quote = 25; возможно дополнительные страницы/retries | При бюджете 5/s — эквивалент 5 секунд пропускной способности; при консервативных 4/s — 6.25 секунды, плюс latency/queue |
| Quotes первым шести | 1 search + до 6 quotes | Остальные «Get total», нельзя сортировать все 24 по неполному total |
| Quote только на Unit | 1 search + 1 quote при выборе | Минимум фоновой нагрузки; явный продуктовый компромисс каталога |
| Без дат | 0 visitor API calls при свежем local snapshot | Контент/подборки от WP, live booking не обещается |

При 10 различных cold searches/min fan-out 25 даёт 250 requests/min — около 91% от 275 до прочих операций. 12 таких searches/min дают 300 и превышают лимит. В устойчивом потоке 4/s — 240/min; очередь должна также контролировать min/hour budget и другие процессы. Общий внешний бюджет конкретной instance/account и marketplace entitlement подтвердить у Guesty.

**Рекомендованный fallback:** cached availability + quote по выбору Unit. Если клиент обязательно требует totals на всех карточках, включить ограниченный progressive quote batch с общим бюджетом, очередью и нагрузочной приёмкой; до неё полный fan-out не входит в обещание MVP. Публикация всех 24 HTML-карточек не требует 24 live quotes при render.

При progressive режиме каждая карточка хранит собственный price state, не размножает цену первой карточки на другие. Partial success виден; total sort и price range при неполном наборе выключены либо явно ограничены «priced results», без ложного глобального порядка. Цена/ночь из календаря не заменяет total quote.

### 10.4. Total, breakdown, скидки и freshness

Операционная денежная модель берётся из **выбранного rate** quote: amount/currency и invoice lines. В Reference показаны `money.hostPayout`, `subTotalPrice`, accommodation/cleaning/fees/taxes; guide использует `hostPayout` как amount для payment schedule. Не переносить интуитивное бухгалтерское значение имени `hostPayout` и не считать `subTotalPrice` all-in автоматически. Mapping guest total подтвердить на контрольном счёте и schedule. [Quote schema][S07], [Flow][S11]

Схема-нормализатор должна различать nested Reference и guide (§8.3); неизвестный amount/валюта блокирует booking, а не становится нулём. Денежные значения хранить с decimal-safe арифметикой и currency precision; не предполагать, что любой numeric amount — cents. Это тестовое требование, не установленная единица всех endpoints.

Breakdown отображает полученные строки Guesty с сохранением amount/type/normalType. Cleaning/mandatory fees/taxes показываются один раз; inclusive taxes не суммируются повторно. Если сумма видимых строк не объясняет total, запрашивать корректные данные/конфигурацию, а не дописывать выдуманный «Other fee». [Tax change][S35]

Нельзя вычислять обязательные сборы собственными TAT/GET формулами из макета или восстанавливать all-in как nightly × nights. Нельзя обещать, что все source-specific fees уже правильно настроены; требуется контрольный audit. [Fees configuration][S33]

PriceLabs → Guesty → quote: не подключать второй pricing calculator к WP. Guesty описывает обновления nightly rates/min nights, возможную задержку и отдельные Guesty markups/fees. Для спорных сумм проверять timestamp и действующую rate strategy; WordPress не исправляет rate поверх Guesty. [PriceLabs][S25], [Property financials][S25a]

Promo badge — editorial содержание с eligibility/expiry, а фактическое применение скидки — ответ quote. Coupons создаются в Guesty Revenue Management; изменение coupons quote требует нового отображения суммы и подтверждения гостем. [Coupons][S08][S11]

«Save 15% vs Airbnb» и «You save $X» пока удалить. Guesty quote, channel commission config и прямой тариф **не доказывают** текущий all-in Airbnb price для тех же dates/occupants/rate/cancellation/currency. Подтверждённого comparison endpoint в исследованных схемах не найдено; статус **Unverified**, а не доказанная невозможность. [Quote][S21], [Commission updates][S39]

Предлагаемый текст до появления проверяемой методики: **“Book direct with our local team. See the full stay price before you confirm.”** Он не утверждает фиксированную экономию. Персональные savings допустимы только при законном источнике сопоставимой Airbnb цены, timestamp, условиях сравнения и согласованном расчёте; отдельный scope. Даже надбавка 15% к direct rate не означает автоматически 15% экономии от Airbnb denominator.

### 10.5. Cache, invalidation и rate limiting

Значения ниже — **начальные настройки проекта**, подлежат измерению в M3/M4; Guesty не гарантирует такую freshness.

| Данные | Начальный срок | Ключ и правило |
| --- | --- | --- |
| Listings/content | Incremental/poll 30–60 минут, reconciliation раз в сутки | Connection/env/source ID/schema version; stale editorial HTML допустим |
| Reviews | 6–24 часа | Channel/listing/source ID; moderation сохраняется |
| Calendar UI | 2–5 минут, только просматриваемый диапазон | Connection/listing/from/to/version; stale становится unknown |
| Search availability/estimate | 30–60 секунд | Dates + нормализованные occupants + фильтры upstream + env; negative result 15–30 секунд |
| Guest quote | Server session, не дольше `expiresAt` минус запас | Listing/stay/occupants/coupons/rate context; не shared public transient |
| HTML | Cache общего контента; live цены отдельно | Не кешировать персональные quotes/guest details в CDN/full-page cache |

Request coalescing объединяет одновременный одинаковый **публичный search/GET**. Не разделять персональный quote между посетителями: guide отмечает, что quote нельзя повторно использовать для новой booking. [Quote reuse][S11] Debounce 300–500ms для map idle — local UX настройка; отмена browser fetch не гарантирует отмену upstream, поэтому scheduler проверяет актуальность очереди перед отправкой.

Один scheduler на соединение обслуживает search/quote/sync; пользовательское завершение booking имеет приоритет перед прогревом. На старте budget ≤4 BE requests/s с min/hour counters и небольшим concurrency; это собственный запас к documented limit, не новая квота. При 429 уважать `Retry-After`; при временных read errors backoff+jitter и ограничение попыток. Circuit breaker после серии ошибок приостанавливает фоновые jobs, даёт пробный read и явный UI unknown. [Limits][S00], [Retry guide][S43]

Прогрев: listing snapshots, media thumbnails, popular calendar windows; не генерировать quotes на все будущие даты/guest combinations. Наблюдаемость: upstream calls/search, p50/p95, cache hit rate, rate-limit remaining, queue age, sync freshness, schema failures, unknown booking outcomes. Секреты/PII исключены.

При разрешённом OAPI: проверка подписи, durable receipt/dedup, быстрый 2xx после принятия в очередь, затем fresh fetch и invalidation. Guesty описывает HTTPS receiver, signing secret, duplicates/out-of-order и возможные задержки до 30 минут: webhook не гарантия мгновенности. [Webhooks][S28]

Calendar webhook не покрывает новые/изменённые reservations и Smart Calendar Rule blocks: подписаться также на reservation events и перечитывать затронутые даты с запасом на preparation; периодическая reconciliation остаётся. [Calendar events][S29] V2 reservation payload отделяет financial/payment changes — нужны отдельные payment/folio events при таком scope. [Reservation events][S31], [Payment events][S41]

При BE-only отсутствует доказанная замена OAPI webhooks: polling и live search/quote обязательны. OAPI имеет другие limits: 15/s, 120/min, 5 000/hour; документация различает shared account limits и официальные Marketplace integrations. Не переносить это исключение на неподтверждённый GuestyBridge access. [Open API limits][S42]

### 10.6. Карта и отказоустойчивость

Выбрать Google Maps JavaScript API как исходное намерение D02/D04; нужны production project/billing, restricted browser key и map ID для Advanced Markers. Тарифы не фиксируются без ожидаемого трафика; ключ ограничить доменами/API, staging отдельно. Не устанавливать Maps/Places features, которые не нужны. [Maps setup][S50], [Key restrictions][S51]

Один map instance, updating markers вместо remount; список и карта используют одинаковый result set/version. Hover/focus card → pin highlight; click pin → mini-card; map bounds включается явно. После background update не перемещать карту так, чтобы новый bounds снова запускал запрос бесконечно.

Pins: без дат — «Select dates» или маркированный indicative; с search estimate — estimated total; с quote — total для того же контекста. Недоступный объект можно оставить приглушённой точкой с «Unavailable for these dates»; отсутствие quote/error — **Unknown**, не booked. Reason «Booked» показывается только при соответствующем факте календаря.

Пользователь может пользоваться списком без карты. При Maps outage список/фильтры/бронирование остаются доступными; показывается текст location и retry. При Guesty outage локальный каталог доступен, но новый confirmed price/availability не обещается; возможен contact fallback без hold. При отсутствии координат дом остаётся в списке с пометкой, что положение не показано. Public precise coordinates, POI и фотосъёмка/права согласуются с клиентом.

## 11. SEO, Performance & Migration

### 11.1. Индексируемый контент и URL

Server-rendered HTML включает заголовки, уникальные описания, facts, amenities, links и весь текущий каталог. Не ждать Guesty для первого HTML: source snapshot/local editorial content доступны независимо. Read more/FAQ скрывает текст визуально, не загружает его исключительно по JS. Это не требует встраивать все оригиналы фото в HTML.

Canonical Unit постоянен и не содержит dates/guests/quote. Area/community имеют собственные содержательные landing и self-canonical; не дублировать их под Vacation Rentals. Guide имеет одну основную rubric на статью. Breadcrumbs строятся из canonical page IDs, а не из случайной цепочки фильтров: Unit — Home → Vacation Rentals → Area → Community → Unit; если community отсутствует, пропустить уровень.

Предлагаемая index policy:

| URL | Индексация / canonical / sitemap |
| --- | --- |
| Clean catalog, published Unit, area/community, guide, marketing | Index, self-canonical, sitemap по опубликованному содержанию |
| Catalog с датами/guest count/sort/map bounds | Noindex, follow; исключён из sitemap; canonical к clean catalog или соответствующей чистой landing |
| Unit с stay parameters | Canonical к clean Unit; не создавать отдельные индексируемые URL; query variants не включать в sitemap |
| Checkout/result/quote/status/search technical endpoints | Noindex/no-store и session access для персональных данных; не sitemap |
| Несуществующий slug | Настоящий 404, не 200 empty template |
| Снятый объект | Индивидуально: сохранить информационную страницу, 301 на реальную замену либо 410 при окончательном удалении; не массово на Home |

Не генерировать crawlable ссылки на все комбинации фильтров. Canonical — сигнал, а не гарантированный запрет индексации. Noindex должен быть доступен crawler; нельзя одновременно закрыть URL в robots и рассчитывать на чтение noindex. Если позже потребуется robots crawl control, принять отдельную политику после деиндексации и аудита: при малом каталоге не усложнять её преждевременно. [Faceted navigation][S54]

### 11.2. Structured data

`VacationRental` соответствует типу индивидуальной краткосрочной аренды; `BreadcrumbList` — навигации. Organization/website/article/video markup добавляется по содержимому, без дублирования сущностей и вымышленных ratings/offers. На Unit один stable `@id`, согласованный с SEO-плагином. [Schema.org][S56], [Breadcrumb guidance][S57]

Различать валидную Schema.org-разметку и право на Google Vacation Rental rich results: Google описывает eligibility, Hotel Center/Technical Account Manager и дополнительные шаги. Наличие JSON-LD не означает подключение Google Vacation Rentals и не обещает rich result. [Google VacationRental][S49]

D00 предлагает FAQPage schema. На дату проверки Google уже сообщил о прекращении FAQ rich result и удалении соответствующей документации в июне 2026. FAQ как полезный видимый контент оставить; schema не включать ради обещания расширенного сниппета. [Google documentation updates][S55]

Не размечать search estimate как универсальную гарантированную Offer без дат/условий; reviews/rating должны соответствовать видимым данным и выбранной шкале. VideoObject только для реального воспроизводимого видео с нужными метаданными; временная картинка района не является видео.

### 11.3. Производительность и доступность

Цели после накопления полевых данных, p75 отдельно mobile/desktop: **LCP ≤2.5s, INP ≤200ms, CLS ≤0.1**. До запуска — lab checks на согласованных устройствах/сетевом профиле; lab score не выдаётся за достигнутый field p75. [Core Web Vitals][S52]

Проектный стартовый budget: собственный публичный JS без Maps/payment SDK — ≤100KB gzip на основных шаблонах, собственный CSS ≤60KB gzip; это договорная цель, пересматриваемая по фактическим компонентам. Maps и payment SDK загружаются по необходимости и измеряются отдельно, а не исключаются из итоговой оценки UX. Полный доступный контент не должен требовать hydration всего сайта.

Media: responsive `srcset/sizes`, современные форматы при поддержке hosting, reserved aspect ratios, lazy-load ниже fold. Hero/LCP image не lazy; приоритет только нужной картинке. Карточка содержит ссылки/метаданные первых 5–6 фото, но последующие изображения запрашивает по действию/near visibility, а не 24×6 полных фото сразу. Галерея загружает full-size после открытия. Alt описывает реальную сцену, а не автоматически спамит названием района. Подтвердить разрешение на локальное сохранение source media; при отсутствии права — оптимизация совместимыми удалёнными вариантами.

Шрифты — локальная поставка при подходящей лицензии, ограниченный набор weights, fallback с близкими метриками. Carousel на CSS scroll-snap и минимальном JS; без autoplay. Не подключать глобально frontend React/builder bundles только ради одной gallery. Map ниже критического контента, 3D/video — click-to-load.

Accessibility target — WCAG 2.2 AA как проектная цель: labels/errors, keyboard date picker и counters, focus visible, modal focus management, screen-reader status, reduced motion, рабочий список без карты. Для важных touch controls проектировать 44–52px; формальный minimum 24px с исключениями не оправдывает неудобные стрелки. [W3C target size][S53] Проверить контраст Orange/white при фактическом размере/weight: не считать все сочетания палитры автоматически доступными. Fixed bar не перекрывает footer, формы и focus при zoom 200%.

### 11.4. Миграция и безопасный запуск

1. Получить согласованный read-only inventory старых URLs, sitemap, redirects, media, metadata, analytics/search priorities. В M0 production crawl не выполнялся; точная 301-карта пока не составлена.
2. Сопоставить старые `/homes/...`, `/listing/...`, `/search-results/` и фактические legacy routes с **реальными** новыми canonical. Имена/ID/slug проверять вручную при неоднозначности. Не использовать общий wildcard, теряющий идентичность домов.
3. На staging включить HTTP access control и noindex; robots — дополнительный сигнал. Sandbox connection allowlist, тестовые processor keys, отключённые production email/SMS/analytics и автоматизации. Копия production DB не должна незаметно унести production Guesty secrets.
4. Наполнить уникальные descriptions, проверить references, реальные media, условия и legal copy. Гостевые и homeowner формы проверяются с безопасными тестовыми получателями.
5. Пройти M3/M4, проверить migration redirects, canonical/sitemap, asset URLs, mobile/errors/performance и restore backup. Подготовить cutover/rollback runbook и владельцев операций.
6. Перед отдельным разрешённым запуском — backup, короткий content freeze, финальный import/reconciliation, DNS/certificate/cache план. Guesty остаётся единственной операционной системой бронирований; перенос сайта не требует копировать reservations в новую «вторую PMS».
7. После согласованного cutover отключить **только устаревшие** интеграционные jobs/формы, проверить новые sources/fees/automations, redirect monitoring, 404/429/payment errors. Старый сайт сохранить для rollback, не оставлять две активные отправки одной формы.
8. Rollback сайта не откатывает уже созданные reservations/payments. Их сверка выполняется в Guesty; перед возвратом старого frontend нужно исключить дубль операции и сохранить attempt log.

Этот документ не является командой выполнить миграцию или production test booking. Запуск и production payment проверки требуют отдельного задания и согласованного безопасного процесса.

## 12. Риски и ограничения

| Риск | Влияние | Снижение / release gate | Владелец |
| --- | --- | --- | --- |
| R1. Checkout/payment/recovery не проверены | Дубли брони, ошибочная оплата/confirmation | Sandbox end-to-end, 3DS/timeout recovery, idempotency ответ Guesty; без gate только согласованный C/contact | Разработчик + Guesty + клиент |
| R2. Доступы и partner entitlement | Невозможен выбранный API или коммерческая модель | Отдельно BE/OAPI/Marketplace права, sandbox, processor rights; OAPI не hard dependency базы | Guesty Partnerships |
| R3. Pilot недоступен / quote fan-out | Медленный каталог, 429, некорректный price sort | Capability flag, fallback quote selected home, общий budget и нагрузочная проверка | Разработчик |
| R4. Source-specific money и schema drift | Неверный total/сборы/политика | Source audit, fixtures/contract tests, schema errors fail closed, recheck changelog | Клиент + разработчик |
| R5. Sync стирает local content / missing inventory | Потеря SEO и ложное исчезновение объектов | Write ownership, mapping unique keys, quarantine/no delete, partial-sync tests | Разработчик |
| R6. Неполный mobile/недостающие marketing/media | Изменения объёма и спорная визуальная приёмка | Area/Community/Article desktop и все 19 прежних image assets уже получены. Нужны mobile/additional states, marketing layouts, реальные films/covers/content и согласованный screen list | Клиент/дизайнер |
| R7. Недостоверные цифры/экономия/accessibility | Неверные ожидания гостя | Evidence fields, expiry, согласование claim, нейтральные тексты | Клиент |
| R8. Webhook gaps/задержки/outage | Stale availability | Несколько типов events + periodic reconciliation + live final check; unknown state | Разработчик |
| R9. SEO migration/dual operation | Потеря URL, дубли форм/броней | Пообъектный redirect map, staging isolation, cutover/rollback runbook | Разработчик + hosting |

Главные пять — R1–R5. Имеющаяся документация не закрывает runtime-риски. Не измерены фактические latency/p95, quota scope BE, inventory completeness, processor behaviour, charge amounts и точные response shapes. Дизайн-пакеты теперь визуально проверены (§2.3), но реальные inventory media/права/отсутствующие mobile и marketing states остаются ограничениями. Успешный локальный render не является успешным API-тестом; фиктивных API-результатов не создавалось.

## 13. Вопросы Guesty Support / Partnerships

Ниже вопросы о конкретном доступе/неоднозначностях; повторение базовых возможностей из документации исключено. «Блокирует» относится к указанному этапу, а не ко всему M1.

| ID / приоритет | Точный вопрос | Что блокирует / нужный результат |
| --- | --- | --- |
| G1 — ранний gate | Какой onboarding и договор разрешают распространять GuestyBridge как коммерческий Marketplace plugin? Какие APIs/scopes и account authorization разрешены партнёру, включая отдельный OAPI доступ? | Commercial live adapter; письменная entitlement matrix |
| G2 — M3 blocker | Как получить изолированный sandbox для этого проекта: стоимость, срок, одна BE instance, test listings, processor test mode, OAPI/webhooks и ограничения отличий от production? | Реальные API и payment tests; выданное окружение без передачи ключей в этот документ |
| G3 — M3 blocker | Какой host/base path является поддерживаемым для BE production/sandbox на октябрь 2026: operation Reference `booking.guesty.com/api` или Quick Start `booking-api.guesty.com/v1/search`? Какой token issuance limit — 3 или 5/24h? | Auth/transport; подтверждённый контракт |
| G4 — до scope M4 | Можно ли включить Accurate Stay Pricing pilot на Aloha instance/partner connection? Как передать actual adults/children/infants/pets, coupons/rate context, какая pagination/sort semantics и ожидаемая latency? | Total-on-all-cards; примеры ответа и conditions |
| G5 — A blocker | Есть ли **поддерживаемый** BE quote → Guesty Website checkout handoff: точные URL/SDK/request fields для listing/dates/guests/rate/quote, срок действия и price guarantee при Website vs BE API source? | Выбор A; спецификация и working sandbox example |
| G6 — A blocker | Поддерживается ли checkout-only experience на `book.alohahello.com`, return/cancel URLs и session recovery? Custom domain guide относится к engine в целом: есть ли отдельные ограничения checkout? | Branded hosted flow |
| G7 — B/C blocker | Какой точный guest total field и units/precision использовать для этой BE schema? Как согласовать quote money, included taxes, обязательные account/listing fees и payment schedule без double count? | Показ total/оплата; контрольный пример счёта |
| G8 — B blocker | Какие flows разрешены для назначенных Aloha processors: ordinary instant или instant-charge? Какой amount списывает instant-charge при нескольких auto payments/hold/deposit, как проходят 3DS и abandon для Stripe/GuestyPay? | Payment model и точные UI promises |
| G9 — B/C blocker | Поддерживается ли remote idempotency на quote/instant/inquiry/instant-charge/verify-payment? Как безопасно восстановить reservation ID после timeout без response и correlation lookup? | Отсутствие дублей и unknown outcome resolution |
| G10 — C blocker | Что именно делает BE inquiry `reservedUntil` на этом account: блокировка календаря, status, expiration, подтверждение/отказ, безопасное получение оплаты после запроса? | Request to Book workflow |
| G11 — M3 blocker | Какой актуальный quote nesting и rate ID contract: `ratePlans[].ratePlan` против примера guide? Как приходят half bathrooms и room bath details, помеченные Coming soon? | Parser и корректные facts; реальные schema/examples |
| G12 — M3/M4 | Какие BE квоты применяются к partner connection/account/instances и shared traffic? Можно ли получить headers и разрешённые нагрузочные условия без production воздействия? | Нагрузочная модель |
| G13 — до OAPI webhook scope | Доступны ли подписанный receiver, calendar.updated.v2 и reservation v2 для нашей модели доступа? Какой точный legacy retirement: end of 2026 или February 2027; как получать payment state без V3 payment records? | OAPI invalidation/recovery |
| G14 — позже | Есть ли документированный atomic multi-listing quote/reservation/payment API для соседних отдельных домов? Это отличается от MTL и ручного multi-listing offer; нужны partial-failure и cancellation semantics. | Только future group checkout |
| G15 — после G1/G4 | Если BE pilot недоступен, разрешено ли использовать OAPI `/v1/quotes/multiple` для каталога? Каковы max batch, per-item errors, quota cost, source/fee parity и связь полученных quotes с выбранным BE checkout? | Возможная оптимизация; не блокирует fallback quote на Unit |

Неизвестный G14 не блокирует локальный подбор соседних домов и contact lead. Отсутствие OAPI не блокирует BE-only фундамент при согласованном polling/recovery; отсутствие безопасного reconciliation может блокировать платёжный live launch.

## 14. Вопросы Алексею

| ID / приоритет | Вопрос | Решение, которое зависит от ответа |
| --- | --- | --- |
| A1 — до M3 | Включён ли Booking Engine API в существующем account и кто администрирует instance? Есть ли ограничения договора/тарифа, processor и partner connection? Ключи сейчас не присылать. | Доступ и responsible owner |
| A2 — до content/M3 | Нужен актуальный перечень 24 объектов: Guesty ID, публичное имя, текущий URL, area/community/tier, active/published. Как распределены 8+11+4 и где Kailua-Kona? | Mapping, slug, counts, полнота inventory |
| A3 — до M4 scope | Предпочтение: собственный API checkout, переход с повторным расчётом или Request to Book? Допустим ли phased launch с ручным подтверждением? | Scope B/A/C и тексты CTA |
| A4 — до M4 | Кто принимает request, в какой срок, какой hold, когда списываются деньги/депозит, какая cancellation policy и rental agreement? Как проходят существующие booking и payment automations? | Реальные terms и operational workflow |
| A5 — до M3/M4 | PriceLabs подключён ко всем домам? Какие ещё rate tools, markups, seasonal/length discounts и promo codes активны? Для каких sources настроены fees/taxes? | Контрольные сценарии цены |
| A6 — до content launch | Чем подтверждаются 15%, savings amount, 4.95/1,500+, 12 years, 20/24 renovated, матрасы во всех домах, 150-point inspection, 24/7/within-hour SLA? | Утверждённые маркетинговые тексты |
| A7 — до M1 visual acceptance | Logo SVG, все 19 старых images и desktop Area/Community/Article теперь есть. Подтверждаете ли права/достоверность sample media и людей на портретах? Нужны реальные inventory/community/beach media, films/tours, licensed fonts и отсутствующие Homeowners/marketing/hub layouts. | Приёмка конкретных секций; foundation не блокируется |
| A8 — до M1 completion | Подтверждается ли mobile Home: 2×2 homes перед Standard? Согласуем mobile catalog/Unit, map/filter/booking sheets и отдельный Invest в меню, Results в footer? | Полный responsive screen list |
| A9 — до M2/content | Какие дома и какие rooms действительно step-free: вход/маршрут/этаж/ступени/душ/ширина проходов? Какие resort passes включены и на каких условиях? | Достоверные features/accessibility |
| A10 — до M1 editor handoff | Кто редактирует сайт и что чаще: тексты, подборки, статьи, whole layouts? Есть ли необходимость именно Elementor и готовность поддерживать лицензию? | Редактор; default остаётся Gutenberg |
| A11 — до migration | Кто отвечает за hosting/DNS/backups, legacy URL export, Search Console и legal content? Желаемое launch/rollback окно? | M5 runbook и redirect map |
| A12 — можно позднее | Кто подтверждает соседство домов и group enquiries? Нужна ли одна оплата обязательно или подходит подбор менеджером? | Future groups, не блокирует обычные bookings |
| A13 — можно позднее | Нужны ли отдельная Step-Free landing, multilingual, favourites между устройствами, owner reports и calculator? Какие реальные формулы/данные предоставите? | Дополнительный scope; не входит в неопределённый «всё как макеты» |

## 15. Milestones и критерии готовности

Порядок: **M0 → M1 + контрактная M2 → M2 → M3 → M4 → M5**. Контент/SEO инвентаризация из M5 и запросы Guesty начинаются рано; live checkout не откладывается на последний день. Календарные сроки и бюджет в M0 не обещаются: их можно оценить после выбора payment flow, объёма недостающих макетов и доступа.

| Milestone | Результат | Зависимости | Что возможно без Guesty | Критерии приёмки | Риски и gate перехода |
| --- | --- | --- | --- | --- | --- |
| **M0 — Architecture & API Feasibility** | Этот документ и актуализация design-inventory/M1 plan | Старый архив, новые макеты и публичные источники | Весь документ | Старые 7 + новые 10 HTML/7 PNG изучены;24 API-функции с ссылками; API runtime остаётся untested; local render findings явно отделены | Исследование завершено с известными внешними ограничениями; live feasibility не объявлена проверенной |
| **M1 — Theme Foundation** | Локальная WP/Git основа, tokens/fonts/header/footer, Home desktop/mobile; SSR catalog/Unit на явно demo fixtures; Area/Community/Article и generic marketing shells | M0 + design-inventory; A7/A8 и дополнительные mobile compositions для финальной приёмки | Все M1.0–M1.7; Guesty не нужен | Home390 сохраняет 2×2 и порядок; PNG desktop anchors;24 synthetic records в SSR; mobile proposals/states согласованы; editor/keyboard/zoom/asset budgets; никаких настоящих booking/form/payment actions | Пакеты соответствуют плану M1; недостающие media/marketing layouts перечислены; fixture contracts подготовлены для M2 |
| **M2 — GuestyBridge Foundation** | CPT/taxonomies/local fields/mapping; repositories, Demo Mode, settings/admin, sync jobs; neutral blocks | M0, согласованный DTO; M1 styles могут идти параллельно | Всё на синтетических fixtures; sync engine с fake transport | Повторный import не дублирует WP IDs; local SEO/slug/features сохраняются; partial sync не удаляет; standard theme работает; demo/live изолированы; migrations/backup проверены | До M3 нужны sandbox, schema version, connection rights; fake transport не live integration |
| **M3 — Live Integration в sandbox** | Auth, полный inventory import, live calendars/search/pricing pilot probe, quotes, reviews, разрешённые webhooks/polling | G1–G4/G7/G11/G12, sandbox access; fixtures из реальных ответов | Подготовка schema tests и admin diagnostics | Подтверждён base/auth/quotas; inventory mapping полный; null/half baths/media/room IDs проверены; даты/guest mix/seasonal/promo/mandatory totals сверены; 401/403/429/outage безопасны; real fixtures обезличены | До M4 документированный выбор checkout и восстановление; при отказе pilot принять fallback/catalog UX |
| **M4 — Search & Booking** | Полный search/map/state, quote lifecycle, выбранный A/B/C, attempt ledger, operational workflow | M3; G5/G6 для A либо G8/G9 для B, G9/G10 для C; согласованные условия | UI всех states и simulation failure до доступа | End-to-end sandbox success/decline/3DS/abandon/expiry/timeout/reload/double-submit; totals/terms/current rate совпадают; partial search не ложный 0; без duplicate charge/reservation; request mode проверяет hold; operator runbook | Нельзя пройти gate по happy-path видео или HTTP 200; при unknown recovery не запускать online payment |
| **M5 — Content, SEO & Migration** | Реальный контент/media, redirect map, SEO/performance/accessibility, обученный клиент, cutover/rollback package | M1–M4 и подписанный scope/claims/content | Подготовка контента, URL mapping и lab QA заранее | Нет placeholders; все опубликованные дома/area links проверены; 301 без chains; canonical/sitemap/schema корректны; mobile forms/booking приняты; restore rehearsal; payment/source audit; launch только отдельным разрешённым действием | Credentials и production automations строго разделены; rollback учитывает брони, созданные после cutover |

### 15.1. Обязательный протокол M3/M4 — пока не выполнен

| Группа | Будущая проверка | Критерий |
| --- | --- | --- |
| Inventory/schema | SINGLE/MTL/child, все страницы, missing optional fields, unknown enum, decimal baths | Parser не теряет ID/дроби, не публикует неподтверждённые поля |
| Sync ownership | Repeat import, concurrent jobs, partial pagination, 429, исчезновение и восстановление source | Один WP ID, local data intact, ни одного auto delete |
| Dates | Min/max stay, CTA/CTD, checkout boundary, gap/preparation, local timezone, unavailable race | Результат согласуется с Guesty; quote/reservation имеет финальное слово |
| Guest mix | Adults/children/infants/pets, capacity boundaries, разные fee cases | Display context соответствует переданному и рассчитанному Guesty |
| Pricing | Seasonal, weekday, longer stay, plan/coupon, cleaning, tax inclusive/exclusive, deposits | Сверенный total/lines/currency/schedule; никаких self-added fees |
| Pilot/load | 24 candidates, multiple unique searches, cursor, sort, quota headers, cold/warm cache | Измеренные p50/p95 и calls/search; нагрузка внутри разрешённых limits |
| Payment B | Success/invalid method, insufficient funds, SCA challenge success/fail/abandon, wrong processor, pending | UI показывает реальные reservation/payment statuses; no false paid |
| Recovery | Timeout до/после remote commit, duplicate click/tab, refresh/back, known/unknown ID | Одна допустимая операция либо безопасный unknown с operator resolution |
| Request C | Explicit reservedUntil, calendar hold/expiry, accept/decline, later payment | Реальный процесс соответствует обещанию гостю |
| Hosted A, если выбран | Context transfer, quote/rate/source parity, cookies/mobile/domain/expiry | Документированный handoff либо честный новый расчёт; без invented URLs |
| Webhooks | Invalid signature/replay/duplicate/out-of-order/delivery outage и missing triggers | No unauthorized write, convergent fresh state, reconciliation исправляет gaps |
| Degradation | Guesty/Maps/network unavailable, partial quotes, stale response race | Полезный local catalog; unknown не превращается в guaranteed available |

Для каждого будущего test case хранить environment, дата/время, build/schema version, sanitized input, expected/actual, response status, correlation ID, verdict и unresolved issue. Sandbox и production verdicts — отдельные колонки. На дату M0 **все строки not run**, включая нагрузку и оплату.

## 16. Рекомендуемые следующие действия

1. Передать Guesty вопросы G1–G12 в рамках существующей partner заявки; этот документ сам сообщения не отправляет. Критические ответы — sandbox, supported hosts/quotas, pilot и checkout recovery.
2. Зафиксировать A2 inventory и A7/A8 дизайн/контент: закрыть 24/23, scope Kona, mobile catalog/Unit, реальные inventory media, films и недостающие marketing/hub templates. Старые 19 blob-assets и Area/Community/Article больше не числятся отсутствующими.
3. Следующим отдельным заданием запускать **M1.0 — Local baseline** по [плану M1](/Users/sonoma/Documents/ALOHA/docs/m1-theme-implementation-plan.md), затем tokens/shell. Параллельно согласовать DTO/local field contract M2; demo acceptance dataset синтетический и не создаёт бронирований.
4. M2 строить независимо от темы и доступа; не покупать builder/filter stack без доказанной потребности клиента.
5. После sandbox выполнить короткий M3 feasibility slice: один реальный listing → availability → quote → selected checkout → status/recovery. Затем масштабировать на весь inventory и UI.
6. До выбора A/B/C не фиксировать CTA «Instant», «не спишем сейчас», quote transfer или group booking. Каталог с pilot и без pilot — два заранее определённых сценария, один из них принимается после проверки.

**Итог M0:** архитектура и план готовы для начала foundation; разрешение на реализацию, deployment/push, получение секретов или работу с production этот документ не добавляет.

## 17. Источники с URL и датой проверки

Все записи проверялись **21.10.2026**. Это дата доступа исследователя, не обещание неизменности документации. Статус «прочитано» относится к документации; авторизованные API-запросы не выполнялись. Тексты здесь пересказаны; API identifiers сохранены. Внутренние ссылки Sxx в тексте открывают конкретный официальный источник.

| ID | Официальный источник | Проверено / значение |
| --- | --- | --- |
| S00 | [BE API Intro][S00] | 21.10.2026: base, limits, security |
| S01 | [Quick start][S01] | 21.10.2026: альтернативный base, token discrepancy |
| S02 | [BE Authentication][S02] | 21.10.2026: scope/token и противоречие 3/5 |
| S03 | [Get application listings][S03] | 21.10.2026: query/filter/pagination |
| S04 | [Get specific listing][S04] | 21.10.2026: detail и schema/example excerpts |
| S05 | [Definitions][S05] | 21.10.2026: fields/calendar, имеются legacy несовпадения |
| S06 | [Property descriptions][S06] | 21.10.2026: rooms/description, Coming soon |
| S07 | [Create reservation quote][S07] | 21.10.2026: request и nested response excerpts |
| S08 | [Update quote coupons][S08] | 21.10.2026: replace coupon set |
| S09 | [Listing calendar][S09] | 21.10.2026: endpoint/from/to |
| S10 | [Retrieve Accurate Stay Pricing (pilot)][S10] | 21.10.2026: bulk estimated totals, pilot/pagination/sort |
| S11 | [Reservation Quote Flow][S11] | 21.10.2026: pricing flow/expiry/limitations |
| S11a | [GuestyPay tokenization][S11a] | 21.10.2026: SDK, provider, token reuse restriction |
| S11b | [Stripe tokenization][S11b] | 21.10.2026: matching account, tokenization contract |
| S12 | [Booking Website Flow][S12] | 21.10.2026: modes/payment caveat; legacy ссылки сверены с migration |
| S13 | [Instant reservation from quote][S13] | 21.10.2026: rate/ccToken/SCA |
| S14 | [Inquiry from quote][S14] | 21.10.2026: hold durations/optional token |
| S15 | [Instant charge][S15] | 21.10.2026: confirmationToken/initialPaymentMethodId, pending auth |
| S16 | [Verify payment][S16] | 21.10.2026: GuestyPay confirmation branch |
| S17 | [Payouts schedule][S17] | 21.10.2026: total/booking type request |
| S18 | [Retrieve reviews][S18] | 21.10.2026: listing/channel/date/pagination |
| S19 | [Guest and Host Reviews][S19] | 21.10.2026: channel-specific review content |
| S20 | [Review statistics][S20] | 21.10.2026: combined 1–10 avg/count |
| S21 | [Retrieve quote][S21] | 21.10.2026: quote retrieval |
| S22 | [Custom domain/subdomain][S22] | 21.10.2026: engine domain, CNAME/SSL |
| S23 | [Promoting Booking Engine using links][S23] | 21.10.2026: static/session links, не quote handoff |
| S24 | [Booking Flow Migration][S24] | 21.10.2026: BE legacy EoL 31.03.2026 |
| S25 | [PriceLabs][S25] | 21.10.2026: integration behaviour |
| S25a | [Property Financials][S25a] | 21.10.2026: calendar vs base/rate tools |
| S26 | [Open API Authentication][S26] | 21.10.2026: отдельный scope/host/token |
| S27 | [Reservations V3 Booking Flow][S27] | 21.10.2026: OAPI alternative; ignore flags требуют осторожности |
| S28 | [Subscribe to webhooks][S28] | 21.10.2026: signatures/delivery/dedup/ordering |
| S29 | [Calendar webhooks][S29] | 21.10.2026: исключённые triggers, v2 early access |
| S30 | [Property webhooks][S30] | 21.10.2026: new/updated/removed |
| S31 | [Reservation webhooks][S31] | 21.10.2026: v2/financial split, противоречие retirement |
| S32 | [Reservation details][S32] | 21.10.2026: BE quote-created reservation retrieval |
| S33 | [BE API additional fees][S33] | 21.10.2026: source-specific automatic fees |
| S34 | [Requesting test environment][S34] | 21.10.2026: request/possible fee/one app, legacy example |
| S35 | [Included Taxes — 2026-02-15][S35] | 21.10.2026: includedTaxesList |
| S35a | [BE changelog 2026-05-18][S35a] | 21.10.2026: upsell guide addition |
| S36 | [WP CPT registration][S36] | 21.10.2026: CPT в plugin для переносимости |
| S37 | [WP Global Settings and Styles][S37] | 21.10.2026: theme.json |
| S38 | [Open API changelog][S38] | 21.10.2026: индекс прочитан; linked entry 21.10 cache miss |
| S39 | [Open API 2026-10-18][S39] | 21.10.2026: beta fees/commissions и изменения schemas |
| S40 | [Open API 2026-07-30][S40] | 21.10.2026: legacy reservation deprecations |
| S41 | [Payment webhooks][S41] | 21.10.2026: payment lifecycle notifications |
| S42 | [Open API Rate Limits][S42] | 21.10.2026: другие quotas/marketplace distinction |
| S43 | [Handling Failed Requests][S43] | 21.10.2026: retry и mutation caution |
| S44 | [Sending a quote to a guest][S44] | 21.10.2026: manual offers и multiple listings; не BE group API |
| S45 | [Reservation sources/platforms][S45] | 21.10.2026: Website/BE API distinction |
| S46 | [Dynamic hyperlinks][S46] | 21.10.2026: outbound confirmation links |
| S47 | [Stripe integration security][S47] | 21.10.2026: PCI/shared obligations, hosted collection |
| S48 | [Stripe two-step confirmation][S48] | 21.10.2026: primary reference из instant-charge |
| S49 | [Google VacationRental structured data][S49] | 21.10.2026: eligibility и integration requirements |
| S50 | [Maps Advanced Markers setup][S50] | 21.10.2026: map ID/project/billing |
| S51 | [Maps API security][S51] | 21.10.2026: key restrictions |
| S52 | [Web Vitals][S52] | 21.10.2026: thresholds/p75 |
| S53 | [W3C target size][S53] | 21.10.2026: minimum и исключения |
| S54 | [Google faceted navigation][S54] | 21.10.2026: canonical/crawl controls |
| S55 | [Google documentation updates][S55] | 21.10.2026: прекращение FAQ rich results и removal docs |
| S56 | [Schema.org VacationRental][S56] | 21.10.2026: semantic type |
| S57 | [Google BreadcrumbList][S57] | 21.10.2026: structured navigation |
| S58 | [Open API Create a Quote][S58] | 21.10.2026: отдельный request/source/ignore flags |
| S59 | [V3 reservation from quote][S59] | 21.10.2026: OAPI conversion, также с BE quote |
| S60 | [Open API Multiple quotes][S60] | 21.10.2026: batch endpoint; limits/semantics требуют проверки |

Дополнительно 21.10.2026 прочитаны [главная Booking API](https://booking-api-docs.guesty.com/), [главная Open API](https://open-api-docs.guesty.com/) и связанные официальные материалы о создании BE instance, API access migration, processor tokenization SDK. Они не заменяют конкретные operation references выше. Последовательность интеграции, предлагаемые TTL/budgets/DTO и WordPress field names — проектные решения автора; не выдаются за требования Guesty.

[S00]: https://booking-api-docs.guesty.com/docs/api-intro
[S01]: https://booking-api-docs.guesty.com/docs/quick-start
[S02]: https://booking-api-docs.guesty.com/docs/authentication-1
[S03]: https://booking-api-docs.guesty.com/reference/getapplicationlistingslist
[S04]: https://booking-api-docs.guesty.com/reference/getapplicationlisting
[S05]: https://booking-api-docs.guesty.com/reference/definitions
[S06]: https://booking-api-docs.guesty.com/docs/property-descriptions
[S07]: https://booking-api-docs.guesty.com/reference/createreservationquote
[S08]: https://booking-api-docs.guesty.com/reference/managerateplanquotecoupons
[S09]: https://booking-api-docs.guesty.com/reference/getcalendarbylistingid
[S10]: https://booking-api-docs.guesty.com/docs/retrieve-accurate-stay-pricing
[S11]: https://booking-api-docs.guesty.com/docs/new-reservation-creation-flow
[S11a]: https://booking-api-docs.guesty.com/docs/tokenizing-payment-methods
[S11b]: https://booking-api-docs.guesty.com/docs/stripe-tokenization-flow
[S12]: https://booking-api-docs.guesty.com/docs/booking-flow
[S13]: https://booking-api-docs.guesty.com/reference/createinstantreservationfromquote
[S14]: https://booking-api-docs.guesty.com/reference/createinquiryreservationfromquote
[S15]: https://booking-api-docs.guesty.com/reference/createinstantchargereservationfromquote
[S16]: https://booking-api-docs.guesty.com/reference/verifychargeforreservationfromquote
[S17]: https://booking-api-docs.guesty.com/reference/retrievepayoutsschedule
[S18]: https://booking-api-docs.guesty.com/reference/getreviewslist
[S19]: https://booking-api-docs.guesty.com/docs/guest-and-host-reviews
[S20]: https://booking-api-docs.guesty.com/docs/average-review-score
[S21]: https://booking-api-docs.guesty.com/reference/retrievequotebyid
[S22]: https://help.guesty.com/hc/en-gb/articles/9363657381277-Connecting-a-custom-domain-to-your-Guesty-Booking-Engine
[S23]: https://help.guesty.com/hc/en-gb/articles/32237071010461-Promoting-your-Guesty-Booking-Engine-using-links
[S24]: https://booking-api-docs.guesty.com/docs/booking-flow-migration
[S25]: https://help.guesty.com/hc/en-gb/articles/9358205175453-PriceLabs
[S25a]: https://open-api-docs.guesty.com/docs/listing-financials
[S26]: https://open-api-docs.guesty.com/reference/authentication-2
[S27]: https://open-api-docs.guesty.com/docs/reservations-v3-booking-flow
[S28]: https://open-api-docs.guesty.com/docs/webhooks
[S29]: https://open-api-docs.guesty.com/docs/webhooks-calendar
[S30]: https://open-api-docs.guesty.com/docs/webhooks-listings
[S31]: https://open-api-docs.guesty.com/docs/webhooks-reservations
[S32]: https://booking-api-docs.guesty.com/reference/getquotereservationbyid
[S33]: https://help.guesty.com/hc/en-gb/articles/9370331582109-Applying-additional-fees-to-a-Booking-Engine-API-reservation
[S34]: https://booking-api-docs.guesty.com/docs/api-sandbox-environment
[S35]: https://booking-api-docs.guesty.com/changelog/include-taxes-2026-02-15
[S35a]: https://booking-api-docs.guesty.com/changelog/2026-05-18
[S36]: https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/
[S37]: https://developer.wordpress.org/themes/global-settings-and-styles/
[S38]: https://open-api-docs.guesty.com/changelog
[S39]: https://open-api-docs.guesty.com/changelog/2026-10-18
[S40]: https://open-api-docs.guesty.com/changelog/2026-07-30
[S41]: https://open-api-docs.guesty.com/docs/webhooks-payments
[S42]: https://open-api-docs.guesty.com/docs/rate-limits
[S43]: https://booking-api-docs.guesty.com/reference/handling-failed-requests
[S44]: https://help.guesty.com/hc/en-gb/articles/9358926644765-Sending-a-quote-offer-to-a-guest
[S45]: https://help.guesty.com/hc/en-gb/articles/9365055850269-Managing-reservation-sources-and-platforms
[S46]: https://help.guesty.com/hc/en-gb/articles/26611709083805-Adding-dynamic-hyperlinks-to-your-Guesty-Booking-Engine
[S47]: https://docs.stripe.com/security/guide
[S48]: https://docs.stripe.com/payments/build-a-two-step-confirmation
[S49]: https://developers.google.com/search/docs/appearance/structured-data/vacation-rental
[S50]: https://developers.google.com/maps/documentation/javascript/advanced-markers/start
[S51]: https://developers.google.com/maps/api-security-best-practices
[S52]: https://web.dev/articles/vitals
[S53]: https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html
[S54]: https://developers.google.com/crawling/docs/faceted-navigation
[S55]: https://developers.google.com/search/updates
[S56]: https://schema.org/VacationRental
[S57]: https://developers.google.com/search/docs/appearance/structured-data/breadcrumb
[S58]: https://open-api-docs.guesty.com/reference/quotesopenapicontroller_create
[S59]: https://open-api-docs.guesty.com/reference/reservationsopenapicontroller_createreservation
[S60]: https://open-api-docs.guesty.com/reference/quotesopenapicontroller_createmultiple




