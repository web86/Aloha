# ALOHA — реестр дизайна и визуальная проверка для M1

**Проверено 21.10.2026, Europe/Istanbul.** Основание: рекурсивный аудит `/Users/sonoma/Documents/ALOHA/макеты/`, первоначальный `aloha-architecture-sources.zip` и обновлённое [исследование M0](/Users/sonoma/Documents/ALOHA/docs/aloha-hello-architecture-feasibility.md). Это реестр референсов, а не реализация сайта.

## 1. Сводка и правила приоритета

В `макеты/` найдено **17 исходных файлов: 10 ZIP и 7 PNG**, вложенных подкаталогов вне архивов нет. В ZIP — **10 HTML-вариантов: 7 final Design v2 artboards и 3 wireframe**. Семь PNG соответствуют тем же семи final artboards, а не семи дополнительным дизайнам. Финальные макеты покрывают **6 типов страниц**: Home, Catalog, Unit, Area, Community, Guide Article. Только Home имеет отдельно desktop и mobile.

С учётом старого архива логических источников **13**: структура D00, 5 wireframe (Catalog, Unit, Area, Community, Article) и 7 final artboards. Четыре старых final HTML дублируются новыми экспортами после нормализации упаковки. HTML wireframe Article посвящён IRONMAN, final Article — manta rays: это два контентных примера общего article template, не парные версии одной статьи.

Приоритет для реализации: **PNG — внешний вид; final HTML/CSS — геометрия и структура; wireframe/спецификация — поведение; M0 — принятые архитектурные и API-ограничения**. Противоречия фиксируются и решаются явно. PNG не подтверждает работу calendar, booking, map, dropdown или правдивость текста. Требования accessible UI, безопасных demo-действий и корректного price state сохраняются даже при отсутствии соответствующего состояния в PNG.

## 2. Физические источники

Ниже H/P/W — устойчивые идентификаторы для таблиц этого документа и плана M1. Пути после стрелки — члены архива, они не распакованы в рабочий проект.

| ID | Исходный файл → HTML внутри ZIP | Класс | Ресурсы `assets/` |
| --- | --- | --- | --- |
| H01 | [Design v2 — Home-html.zip](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Home-html.zip>) → `HomeDesign.dc.html` | Final desktop | 17 JPG + 2 SVG |
| H02 | [Design v2 — Home, mobile (390)-html.zip](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Home, mobile (390)-html.zip>) → `HomeMobile.dc.html` | Final mobile | 17 JPG + 2 SVG |
| H03 | [Design v2 — Vacation rentals (catalog)-html.zip](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Vacation rentals (catalog)-html.zip>) → `RentalsDesign.dc.html` | Final desktop | 7 JPG + 2 SVG |
| H04 | [Design v2 — Home page (unit)-html.zip](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Home page (unit)-html.zip>) → `UnitDesign.dc.html` | Final desktop + developer state board | 7 JPG + 2 SVG |
| H05 | [Design v2 — Area Mauna Lani-html.zip](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Area Mauna Lani-html.zip>) → `AreaDesign.dc.html` | Final desktop | 13 JPG + 2 SVG |
| H06 | [Design v2 — Community Ka Milo-html.zip](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Community Ka Milo-html.zip>) → `CommunityDesign.dc.html` | Final desktop | 4 JPG + 2 SVG |
| H07 | [Design v2 — Guide article manta rays-html.zip](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Guide article manta rays-html.zip>) → `ArticleDesign.dc.html` | Final desktop | 6 JPG + 2 SVG |
| W03 | [04 Area — Mauna Lani-html.zip](</Users/sonoma/Documents/ALOHA/макеты/04 Area — Mauna Lani-html.zip>) → `Area.dc.html` | Wireframe Area | Нет фото/assets |
| W04 | [05 Community page-html.zip](</Users/sonoma/Documents/ALOHA/макеты/05 Community page-html.zip>) → `Community.dc.html` | Wireframe Community | Нет фото/assets |
| W05 | [06 Guide article-html.zip](</Users/sonoma/Documents/ALOHA/макеты/06 Guide article-html.zip>) → `Article.dc.html` | Wireframe Article / IRONMAN | Нет фото/assets |

W01 = `designs/01-catalog-wireframe.html`, W02 = `designs/03-unit-wireframe.html`, D00 = `designs/00-site-structure.html` внутри [первоначального архива](/Users/sonoma/Documents/ALOHA/aloha-architecture-sources.zip). Новых автономных ZIP или PNG для W01/W02 не найдено; их функциональные спецификации остаются применимы.

| ID | PNG-эталон | Размер, px | CSS viewport для сравнения |
| --- | --- | --- | --- |
| P01 | [Home](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Home@1x.png>) | **1440 × 8129** | Desktop 1440 |
| P02 | [Home mobile](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Home, mobile (390)@1x.png>) | **390 × 8000** | Mobile layout 390 |
| P03 | [Catalog](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Vacation rentals (catalog)@1x.png>) | **1440 × 3600** | Desktop 1440 |
| P04 | [Unit](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Home page (unit)@1x.png>) | **1440 × 8000** | Desktop 1440 |
| P05 | [Area Mauna Lani](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Area Mauna Lani@1x.png>) | **1440 × 7900** | Desktop 1440 |
| P06 | [Community Ka Milo](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Community Ka Milo@1x.png>) | **1440 × 5900** | Desktop 1440 |
| P07 | [Guide manta rays](</Users/sonoma/Documents/ALOHA/макеты/Design v2 — Guide article manta rays@1x.png>) | **1440 × 5700** | Desktop 1440 |

Высота PNG — размер экспортной доски, **не CSS height страницы**. Нельзя растягивать содержимое до 8000px или закреплять width 1440px в production.

## 3. Assets, автономность и отсутствующие ресурсы

### 3.1. Полный реестр уникальных визуальных assets

Всего в архивах **85 вхождений media-файлов = 71 JPG + 14 SVG**. После проверки имён и SHA-256 — **19 уникальных ресурсов: 17 JPG и 2 SVG**. Одинаковые имена во всех архивах имеют одинаковое содержимое; конфликтов нет. Все 19 уникальных старых `/_blob/ID` восстановлены. Для компактности ниже ID указывается полностью, имя файла — `ID.jpg` или `ID.svg`, путь внутри экспортов — `assets/`.

Описания основаны на просмотре изображений. Место, личность человека, права и подлинность property photos требуют подтверждения клиента: наличие файла не доказывает эти сведения.

| Asset ID / расширение | Размер | Видимое содержание / назначение | Где включён |
| --- | --- | --- | --- |
| `15c3d4fd7ed48094b2cc376fcae6f058.svg` | viewBox 322 × 80 | Logo: чёрная надпись, оранжевый знак | H01–H07 |
| `67dacdfd5675ba36b03ca46eb9953074.svg` | viewBox 322 × 80 | Logo: белая надпись, оранжевый знак | H01–H07 |
| `3557956acbd0eaba40715788619508cd.jpg` | 1536 × 864 | Семья на террасе перед океаном; Home hero, Unit lead | H01, H02, H04 |
| `69a37e1323353f8396795f3f39210efb.jpg` | 1600 × 1067 | Светлая outdoor seating / pergola | H01–H06 |
| `1baef41ec86b9b9552e60cccc303810f.jpg` | 1600 × 1067 | Спальня, зелёная акцентная стена | H01–H05 |
| `86dc09717ef7566949c0b5f2caa28204.jpg` | 1600 × 1067 | Living room, высокая крыша, цветной mural | H01–H06 |
| `9e50edde879930186d51a599a9ec77ba.jpg` | 1600 × 1067 | Крытая lanai на закате | H01–H07 |
| `c804f2a8d11de7a68cfd784391470685.jpg` | 1024 × 1024 | Побережье/бухта, пальмы | H01–H05 |
| `2c270c074e2011a2e8f43d147ea0e3fe.jpg` | 1024 × 1024 | Golf course, водоём, здания | H01, H02, H03 |
| `6c330ccef195df702ef6d2bc01cf292a.jpg` | 1024 × 1024 | Пальмы/бухта на закате; Area hero/card | H01, H02, H05, H06 |
| `bf6a202faf164ed8cbc957692f61bb3e.jpg` | 1024 × 1024 | Пара на golf course | H01, H02, H05, H07 |
| `f50d3eed09b990a66fd9c0dac8f1efc6.jpg` | 1024 × 1024 | Manta underwater, дайверы со светом | H01, H02, H05, H07 |
| `3a3363e7e4b10fd76c2d3ce73581d525.jpg` | 1024 × 1024 | Кит на закате | H01, H02, H05, H07 |
| `c395525affc5b2c3b33d5d2fb150a932.jpg` | 1024 × 1024 | Fire dance / luau | H01, H02, H05 |
| `6c1f0acb1af55c964bc974cb56e2ea57.jpg` | 1024 × 1024 | Еда на фоне заката | H01, H02, H05 |
| `bffda349bb987af855fd76ae762df08a.jpg` | **547 × 365** | Лава/люди у лавового потока | H01, H02, H05, H07 |
| `041edc66b8c24c6034e8d7829ffb7b4c.jpg` | 1600 × 1067 | Велосипедист, sports/IRONMAN teaser | H01, H02 |
| `84af78abf0b00a1348d1623ac3ac0488.jpg` | 1600 × 1067 | Мужчина у побережья; founder/team portrait в макете | H01, H02 |
| `1f389c1e4d570ad43b12ab3d0e8996d3.jpg` | 600 × 600 | Мужчина в светлой рубашке; manager portrait в макете | H01–H07 |

Файл лавы мал для большого изображения: не увеличивать его до full-width hero. Два SVG уже позволяют воспроизвести логотип; прежнее требование «получить SVG» снято как технический asset blocker. Требование проверить право использования остаётся.

### 3.2. Все остальные типы файлов

В каждом из 10 ZIP есть `README.md`, `support.js` (45 305 bytes), `vendor/react.js` (10 751 bytes), `vendor/react-dom.js` (131 835 bytes). Это 10 README и 30 JS-вхождений; runtime-файлов по содержимому — 3 уникальных. Всего ZIP содержат **135 файлов**: 10 HTML + 10 README + 30 JS + 85 media.

Локальных `.woff/.woff2/.ttf/.otf`, видео, аудио, настоящих 3D tours, map tiles или данных API не найдено. Фотографии уже включены; видеокнопки/галерейные счётчики/карты остаются референсами, а не отсутствующими сетевыми файлами, которые можно скачать по известному URL.

### 3.3. Какие HTML автономны

**Все 10 новых ZIP пригодны к локальному просмотру как отдельные экспортные пакеты**, в отличие от старых фрагментов. Есть doctype/html/head/body, runtime и все явно указанные локальные image/script dependencies. Они требуют JavaScript; одиночный `.html` без соседних файлов не является самодостаточным документом. Прямой `file://` не считать гарантированным способом просмотра.

Полностью offline они **не являются**:

- Fonts загружаются с `fonts.googleapis.com` / `fonts.gstatic.com`: Instrument Serif + Outfit в final, IBM Plex Sans/Mono в wireframe. Файлов шрифтов и лицензий в ZIP нет.
- `support.js` содержит fallback URLs React/ReactDOM и Babel на `cdn.jsdelivr.net`; в выполненных рендерах фактически запрашивались только Google Fonts. Это условная зависимость runtime, не обязательный frontend будущего WordPress.
- `.dc.html` navigation targets в большинстве пакетов отсутствуют рядом: каждый ZIP содержит одну страницу. Межстраничная навигация, множество `href="#"`, CTA/формы и карта не образуют готовый сайт.
- Ни один из 10 HTML не содержит viewport meta или `@media`. `auto-fit`, `minmax`, `clamp` и flex-wrap — отдельные гибкие приёмы, но не полная responsive specification. H02 имеет жёсткий `.m{width:390px}`.

Явных оставшихся `/_blob/` ссылок в новых HTML не найдено. README предупреждает про динамическую сборку таких URL, но в изученных page logic её нет; нельзя переносить общее предупреждение README в утверждение о конкретной битой картинке.

### 3.4. Что ещё нужно получить или подготовить

| Недостаток | Где | Решение для M1 / запуска |
| --- | --- | --- |
| Video loop 15–20s и полный area film ~2min | H05 | Для M1 — имеющийся poster + disabled demo play; настоящие видео/субтитры отдельно |
| Community hero video ~90s, family/lap pool, putting green, grounds photos | H06: **5 phx placeholders** | Утверждённые реальные community media; не подставлять stock как факт Ka Milo |
| Beaches Makaiwa/Beach69/49 Black Sand, fishponds/petroglyphs, 9 community covers | H05: **15 phx placeholders** | Отдельный content checklist; placeholders видны и в PNG, это не ошибка распаковки |
| Реальные 24 дома, 36 Unit photos, +24/+20 gallery counts | H03/H04/H05/H06 | В M1 синтетические demo records с honest sample counts; реальные данные позже |
| Tour, полные FAQ answers, legal/owner content | Несколько templates | Demo empty/disabled states; контент согласовать отдельно |
| Font files и лицензии | Все ZIP | В M1 получить разрешённую локальную поставку и проверить метрики; не копировать шрифты из browser cache |
| Настоящие карты и список POI | H03/H04/H06 | В M1 локальная SVG/HTML map-заглушка с надписью Demo map, без Google key/billing |

## 4. Сравнение с первоначальными семью файлами

| Было | Стало | Вывод |
| --- | --- | --- |
| D02 Catalog, D04 Unit, D05 Home, D06 mobile Home | H03/H04/H01/H02 | После замены `/_blob/ID` → local assets, исключения vendor script tags и нормализации сериализации SVG/boolean `checked` разметка совпадает. Содержательного редизайна не обнаружено |
| Не было runtime | Во всех ZIP runtime/React/ReactDOM | Ограничение «новые экспорты не удаётся воспроизвести локально» снято |
| 19 уникальных blob IDs без файлов | Все 19 найдены, хеши дубликатов совпадают | Техническая недоступность старых изображений снята; клиентские права/достоверность — нет |
| Area/Community/Article были только названы в D00 | W03–W05, H05–H07, P05–P07 | Три семейства шаблонов теперь имеют изученные desktop reference и функциональные wireframe |
| D00 structure, D01/W01 Catalog, D03/W02 Unit | Новых версий нет | Сохранены без предположений об изменении |
| Homeowners wireframe/финал, hubs/legal/contact отсутствовали | По-прежнему не найдены | Общий marketing layout — проектная основа, а не утверждённая страница |
| Mobile catalog/Unit не было | Не появилось | Нужны отдельные предложения и согласование |

В новых Area wireframe и final порядок различается: wireframe ставит homes после SEO/FAQ, **final поднимает homes сразу за intro/facts**. В M1 использовать порядок H05/P05. Финал Article меняет IRONMAN story на manta rays; не создавать выдуманный final IRONMAN artboard.

Старые противоречия 24/23, `from/night` при датах, group booking, Instant/no-charge-yet и 15% сохраняются в новых исходниках. Доступные PNG делают их видимыми, но не отменяют исправления M0.

## 5. Локальная визуальная проверка PNG ↔ HTML

ZIP распакованы только во временный каталог с изоляцией каждого пакета; absolute/`..`/backslash/symlink paths отклоняются, размер распаковки ограничен. Исходники не изменялись. Проверка SHA-256 всех 17 файлов `макеты/` после работы совпала с первоначальными хешами.

Использован изолированный Playwright Chromium с локальной выдачей содержимого файлов через перехват запросов; production, Guesty и межстраничные CTA не вызывались. Desktop CSS viewport **1440×1000**, mobile layout **390×1000**, deviceScaleFactor 1, ожидание загрузки используемых fonts, full-page screenshots. Это проверка layout в Chromium с mobile **шириной**, не эмуляция Safari/iPhone. Локальный HTTP server не потребовался; попытка запуска сервера была ограничена sandbox, после чего использована выдача локальных файлов браузеру.

Все 10 страниц отрендерились; page errors = 0, failed requests = 0, битых `<img>` = 0. Wireframe имеют 0 `<img>` намеренно. Final loaded image elements: H01/H02 — по 20, H03 — 10, H04 — 14, H05 — 19, H06 — 6, H07 — 8. Повторные использования одного JPG/логотипа объясняют отличие от числа уникальных assets.

| Final | Высота PNG / локального HTML | Сопоставление и ограничения |
| --- | --- | --- |
| Home H01/P01 | 8129 / 8129 | Порядок секций, hero, 4-column Standard, carousel, areas, CTA совпадают по структуре; нативные date/select controls отличаются от PNG |
| Home mobile H02/P02 | 8000 / 7887 | 2×2 homes перед Standard, hero/search overlap, footer подтверждены. Native date hint/calendar icon и select arrow видны в Chromium, отсутствуют в PNG; sticky Call/Book Now попадает на область текущего viewport при full-page capture, в PNG вынесена вниз доски |
| Catalog H03/P03 | 3600 / 3369 | Та же 2-column list + map композиция. В local render search/result region примерно на **32px ниже** PNG; видны native select arrows/checkbox, карта/mini-card отличаются по геометрии текущего capture. Не объявлять pixel match |
| Unit H04/P04 | 8000 / 7847 | Совпадают 5-tile gallery, facts, sections и developer widget board. Есть различия переноса строки Instant label; сама developer board **не часть пользовательской страницы** |
| Area H05/P05 | 7900 / 7646 | Совпадают hero/intro/homes/gallery/beaches/activities/communities/SEO/FAQ/CTA; media placeholders присутствуют и в PNG |
| Community H06/P06 | 5900 / 5672 | Совпадают split hero, 5 thumbnails, amenities, homes, map/nearby/FAQ/owner form; hero/pool placeholders сохранены |
| Article H07/P07 | 5700 / 5340 | Совпадают manta hero, TOC/body/quick-facts, tables/FAQ/related. Sticky position зависит от viewport/scroll; артборд выше документа |

Разные полные высоты сами по себе не доказывают редизайн: PNG могут содержать нижнее поле доски, а HTML зависит от текста/контролов/viewport. Абсолютное равенство пикселей не установлено и не заявляется. Для M1 эталон — PNG с собственными семантическими controls и согласованными responsive состояниями, а не попытка добиться совпадения путём копирования runtime.

## 6. Геометрия и дизайн-система

| Параметр | Наблюдение в final HTML/PNG | Решение для будущей темы |
| --- | --- | --- |
| Desktop grid | `.wrap` 1280px border-box с 40px padding; при 1440 — outer left 80, content left **120**, usable width **1200** | Это эталон сетки, не 1280px чистого контента + дополнительные поля |
| Header | PNG/HTML desktop высота 92px, padding 18px, logo 56px высотой; mobile 68px, logo40, controls44 | Общая геометрия; breakpoint переключения предложить отдельно |
| Palette | `#16232A`, `#075056`, `#E4EEF0`, `#F3F6F6`, white, `#FF5B04` | Semantic tokens; проверить контраст, особенно малый white/orange text |
| Typography | Instrument Serif 400 normal/italic; Outfit 300–700 imported, основная UI 400/500/600/700 | Не переносить IBM Plex из wireframe в final; weight300 включать только при использовании |
| Desktop headings | Home H1 clamp52–100 (при 1440 =100), catalog/Unit72, Area96, Community/Article84; H2 часто 44/60 | Fluid type; длины и переносы сверять с PNG |
| Mobile headings | Home H1 52/.98, H2 38/1.05; body16/1.55 против desktop18/1.6 | Сохранить hierarchy, не просто уменьшить все размеры одним коэффициентом |
| Section rhythm | Home120px, Area/Community110px, Unit64px internal, article/CTA100px; mobile44/48/52/56px | Небольшая шкала spacing с шаблонными исключениями, не округлять всё до одного отступа |
| Buttons/forms | Pill52px; desktop padding28, mobile22; gallery arrows34px; FAQ rows; owner inputs50px/radius8 | Увеличить реальные hit areas маленьких controls; labels и feedback обязательны |
| Cards | Gallery4:3, radius6; card gaps22/24/28; badge pill; no rating | Full/compact variants с единым контрактом данных/цены |
| Home | Hero min-height880; Standard4col; featured horizontal scroll-snap; areas3col | Desktop compositional reference |
| Mobile Home | Hero560; search white panel overlaps hero; content20px; featured2×2 gap22px/12px; benefits2col | Утверждённый 390 layout; прочие widths — адаптация |
| Catalog | List2col, gap22; map44% aside top96; filter bar sticky top0 | Map/filter responsive proposal; не делать desktop nav sticky без решения |
| Unit | Gallery2:1:1, 2rows×280, gap10; facts4col; sidebar max420 sticky top24 | Mobile sheet — предложение; fixed gallery height только для desktop reference |
| Area | Hero720; intro1.3:1 gap80; homes4col; gallery2:1:1/2×260; beaches4col; communities3col | Все реальные matching homes в HTML, не только 4 примера макета |
| Community | Hero1:1.15 gap64; thumbnail strip5col/h180; amenities4col; homes3col; owner form | Общий Page template с area/community relation |
| Article | Hero640; TOC220 + body minmax(0,720) + aside300, gaps56; actual body568 при usable1200 | Нельзя считать 220+720+300 фиксированным total; body сжимается. TOC/aside sticky top24 |
| Footer | Desktop колонки/тёмный фон, white logo56; mobile disclosure groups/logo46 | Общий menu source, доступные disclosure и safe-area |
| Breakpoints | **В источниках не заданы** (`@media`=0) | 320/390/768/1024/1440 — QA widths, не выданные за авторские breakpoints |

## 7. Полный реестр страниц

Коды компонентов: **G** = header/footer/navigation/breadcrumbs/type/CTA; **L** = listing cards/collection/search context; **E** = editorial sections/media/FAQ; **A** = article body/TOC/related; **F** = form. Расшифровка ответственности в §9. «Готов» в таблице относится только к указанной части, не к запуску production.

`—` = отдельного исходника не найдено. H/P/W соответствуют точным файлам §2. Local mock listings всегда отмечены Demo; реальные URL отдельных домов пока неизвестны.

| Страница | Предполагаемый WordPress URL | Wireframe | Final HTML | PNG desktop | PNG mobile | Изображения | Общие | Специфические | Готовность |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Home | `/` | — | H01/H02 | P01 | P02 | Все referenced доступны | G/L/E | Hero/search, Standard, areas, stats, team/owners | Можно делать desktop/390 с demo content |
| Catalog | `/vacation-rentals/` | W01 | H03 | P03 | — | Все referenced, не весь inventory | G/L | Applied chips, list/map, sort, map toggle | Desktop готов; mobile/states — предложение |
| Unit, ожидается 24 | `/vacation-rentals/[unit-name]/` | W02 | H04 | P04 | — | Sample images есть; 36 real photos нет | G/L/E | Gallery/rooms/amenities/booking widget/manager | Desktop demo готов; mobile/states — предложение |
| Area Mauna Lani | `/big-island/mauna-lani/` | W03 | H05 | P05 | — | 15 media files; 15 layout placeholders | G/L/E | Hero film, facts, beaches, activities, communities | Desktop template можно делать с явными gaps |
| Area Waikoloa Beach Resort | `/big-island/waikoloa-beach-resort/` | Общая W03 | Общая H05, **нет отдельного варианта** | — | — | Нет отдельно проверенного набора | G/L/E | Собственные facts/beaches/community links | Только template reuse proposal и новое наполнение |
| Area Waikoloa Village | `/big-island/waikoloa-village/` | Общая W03 | Общая H05, **нет отдельного варианта** | — | — | То же | G/L/E | Собственные location/access facts | Только template reuse proposal |
| Communities hub | `/big-island/communities/` | — | — | — | — | Общие sample assets, нет complete covers | G/E | Area grouping/community index | Нужна композиция |
| Community Ka Milo | `/big-island/mauna-lani/ka-milo/` | W04 | H06 | P06 | — | 6 media files; hero/4 thumbs placeholders | G/L/E/F | Amenity grid, nearby, owner estimate form | Desktop template можно делать; slug предлагается |
| Other communities, N | `/big-island/[area]/[community]/` | Общая W04 | Общая H06, отдельных нет | — | — | Нужен набор каждого community | G/L/E/F | Own facts/media/HOA information | Template reuse после content mapping |
| Guide hub | `/big-island/guide/` | — | — | — | — | Есть тематические teaser assets | G/E | Rubric navigation/article index | Нужна композиция |
| Things to Do | `/big-island/guide/things-to-do/` | — | — | — | — | Sample category teasers | G/E | Category archive | Нужен общий archive layout |
| Food & Drink | `/big-island/guide/food-and-drink/` | — | — | — | — | Food sample есть | G/E | Category archive | То же |
| Top Spots | `/big-island/guide/top-spots/` | — | — | — | — | Отдельный набор не утверждён | G/E | Category archive | То же |
| Events | `/big-island/guide/events/` | — | — | — | — | Cyclist sample есть | G/E | Category archive | То же |
| Article manta rays | `/big-island/guide/things-to-do/manta-rays-kona/` | W05 — общий article, **другой сюжет** | H07 | P07 | — | Все referenced доступны | G/A/E | Quick facts, comparison tables, manager CTA | Desktop template готов; URL предложен, copy не fact-checked |
| Article IRONMAN | `/big-island/guide/events/ironman-kona/` | W05 | Нет; H07 только общий template | — | — | Только sample cyclist, нет race hero | G/A/L | Race dates/map/team stay подборка | Wireframe + reuse, не final approval |
| Other guide articles, N | `/big-island/guide/[rubric]/[article]/` | Общая W05 | Общая H07 | — | — | Зависит от статьи | G/A/E | Article-specific facts/tables/related | Требуется content и mobile adaptation |
| Property Management | `/homeowners/` | В D00 назван, файл отсутствует | — | — | — | Нет утверждённого page set | G/E/F | Owner value/services/lead | Нужен layout/content |
| Invest | `/homeowners/invest/` | — | — | — | — | — | G/E | Investor pitch/content | Нужен layout |
| Calculator | `/homeowners/invest/calculator/` | — | — | — | — | — | G/F | Inputs/assumptions/results | Нужны layout и формулы клиента, не M1 functionality |
| Investor article, N | `/homeowners/invest/guides/[article]/` | Общий article | H07 reuse, не отдельный final | — | — | — | G/A | Investor-specific disclosure/content | Generic article shell в M1 |
| Free Rental Estimate | `/homeowners/estimate/` | — | В H06 только embedded form, не вся страница | — | — | — | G/F/E | Lead form/confirmation/error | H06 даёт component, full layout требуется |
| Design & Renovation | `/homeowners/design-renovation/` | — | — | — | — | Нет реального portfolio set | G/E/F | Before/after/case studies | Нужен layout/content |
| Results & Reports | `/homeowners/results/` | — | — | — | — | Реальных reports нет | G/E | Reports/downloads/results | Нужен layout/content |
| Owner FAQ | `/homeowners/faq/` | — | — | — | — | Не требуются для shell | G/E | FAQ groups | Общий FAQ shell, не утверждённая страница |
| Team | `/about/` | — | В H01 только team teaser | — | — | Два sample portraits | G/E | Team profiles | Нужен full layout и реальные личности |
| Guest Info | `/guest-info/` | — | — | — | — | — | G/E | Policies/FAQ | Общий content shell |
| Contact | `/contact/` | — | — | — | — | Manager sample не full layout | G/F | Раздельные guest/owner intents | Нужна композиция; M1 без отправки |
| Privacy | `/privacy-policy/` | — | — | — | — | Не требуются | G/E | Legal copy | Content shell, copy клиента |
| Terms | `/terms/` | — | — | — | — | Не требуются | G/E | Legal copy | То же |
| Rental Agreement | `/rental-agreement/` | — | — | — | — | Не требуются | G/E | Rental terms | То же |
| Step-Free Stays | `/vacation-rentals/?feature=step-free` | Только footer entry | Нет отдельного | — | — | Общие listing assets | G/L | Filter preset | Query-link proposal, не новый approved landing |
| Group assistance | `/contact/?intent=group-stay` | Group CTA W01/W04 | CTA H03/H06 | — | — | Не требуются | G/F/L | Несколько selected home IDs | Proposal, без общей транзакции |
| 404 / empty / error | Служебные URL/states | — | — | — | — | Не требуются | G/E | Recovery actions | Нужно спроектировать в M1 |
| Booking/result | `/booking/`, `/booking/result/` | — | Только widget reference H04 | — | — | Не требуются | G/L | Checkout flow | Не full checkout в M1; будущий M4 |

Технические `/big-island/` и `/homeowners/invest/guides/` обсуждаются как parent/hub/redirect, отдельных макетов нет. Название и число реальных communities/guide articles/Unit slug ещё не получены. Три area пункта в двух меню ведут на одни canonical pages, не создают копии.

## 8. Отсутствующие разрешения и состояния

### 8.1. Screen/state backlog

| Область | Чего нет | Приоритет для M1 |
| --- | --- | --- |
| Все final кроме Home390 | Mobile/tablet; landscape; длинные реальные названия/переводы | До приёмки соответствующего template |
| Header | Open desktop dropdown, mobile menu/submenu, focus/escape, overflow | До завершения shell |
| Search | Открытый date/guest picker, invalid range, max guests, clear/reset | В shared UI contract до catalog/Unit |
| Catalog | Mobile list/map/filter sheet, loading/empty/partial/error, disabled sort | Высокий |
| Card/map | Price estimate vs quote vs expired/unknown, missing photo, selected/focus, no coords | Высокий; доступность не выдавать за факт |
| Unit | Mobile hero/gallery/booking sheet, quote checking/unavailable/error/expiry, details modal | Высокий |
| Reviews | Zero reviews/missing rating/more reviews | Средний |
| Gallery/video | Empty category, fullscreen, failed video, captions, no 3D | Средний |
| Forms | Validation/spam/loading/error/received; consent | Высокий для UI; отправка не M1 |
| Article | Mobile TOC/quick facts, table scroll/focus, длинный контент | До приёмки article |
| Area/community | Missing video/cover/FAQ answer, zero homes, unverified resort access | До приёмки template |
| Site-wide | 320/768/1024 states, zoom200%, reduced motion, safe-area, native UI differences | Финальная QA M1 |

### 8.2. Mobile Catalog — проектное предложение

Header → breadcrumb/H1 и короткий intro → compact dates/guests summary → Filters/Sort/Map row → applied chips/count → single-column cards → помощь/CTA → footer. Filter sheet содержит dates, guests, area, bedrooms и Apply/Clear, закрытие сохраняет draft без ложного применения. Map полноэкранная с тем же applied result set, close/back и mini-card, без одновременной второй map instance. Состояние/scroll position сохраняются при возврате. В M1 карта — подписанная Demo map, все объекты — Demo homes; нет live availability.

### 8.3. Mobile Unit — проектное предложение

Header → breadcrumbs/title/share/save → swipe gallery + gallery button → компактные facts → features → description → rooms → grouped amenities → location → reviews → manager/rules → similar homes → footer. Один нижний booking CTA открывает sheet с dates/guests/price state; detail breakdown — sheet/dialog с focus return. На странице не дублируется desktop sidebar; Call не перекрывает CTA. В M1 CTA называется «Preview booking UI» / «Demo», никакой reservation/payment/успешной брони.

**Предлагаемые** content breakpoints: <768px — mobile, 768–1023 — tablet, ≥1024 — desktop с возможной более поздней активацией map sidebar (например 1200). Это старт для проверки, не значения из Claude. При 1024 не пытаться вместить article220+body+aside300 с desktop gaps; сначала убрать боковые колонки в основной поток. При 320 и длинном имени карточка не создаёт horizontal page overflow. Таблицы допускают локальный horizontal scroll с доступной подписью.

## 9. Карта компонентов A / B / C

| Категория | Компоненты | Кто владеет и как переносится |
| --- | --- | --- |
| **A. Theme** | Tokens/type, header/footer/nav/mobile menu, hero, editorial layout/CTA, area/community cards, Standard/stats/team, guide/archive/article layouts, TOC/FAQ appearance, marketing/form shell | Aloha Theme отвечает за композицию/бренд. Редакционный контент — core pages/posts/media. Forms — только demo UI на M1; будущий submission handler не в шаблоне |
| **B. GuestyBridge** | Listing identity/data, cards/collections, search state/filter/query, display prices, calendar rules, gallery data, booking widget/state machine, API statuses, retries/sync | В M2+ регистрирует свои blocks/fields/REST/admin и neutral fallback HTML/CSS. Theme стилизует slots/variables, не владеет ID/API/state |
| **C. Shared contracts** | Search form inputs/URL model; listing card view model; price-kind/status; guest/date picker events; loading/error/empty; media/projection; theme style hooks | Версионируемые описания в [плане M1](/Users/sonoma/Documents/ALOHA/docs/m1-theme-implementation-plan.md), затем один owner каждого поведения. Fixture adapter в M1 заменяется plugin provider, без копии booking engine в теме |

Listings внутри Area/Community/Guide остаются B, даже когда стоят в редакционном шаблоне A. Theme может задать колонки, размер фото и типографику, но **не** вычисляет total/availability. Plugin fallback должен работать со стандартной WP theme; обязательные классы/role/status нельзя удалять ради оформления. Тема не должна вызывать Guesty, читать credentials или выбирать режим оплаты.

## 10. Решение о готовности

**Можно начинать M1 foundation:** визуальные источники теперь достаточны для общей системы, shell, Home desktop/mobile и desktop templates Catalog/Unit/Area/Community/Article с demo fixtures. API credentials не нужны.

Нельзя считать весь сайт полностью спроектированным: mobile catalog/Unit и остальных новых templates, hubs/marketing pages, interactions и данные требуют решений. Отсутствующие media не блокируют skeleton, но блокируют финальную контентную/визуальную приёмку соответствующих секций. PNG labels/числа/claims не переносятся в demo как настоящие сведения.

Рекомендуемый первый отдельный заказ — **M1.0: локальная WP/Git среда и письменный baseline**, описанный в плане; затем M1.1 tokens/fonts и один header/footer proof. Никаких WP-файлов, plugin implementation, installation, deployment, push или изменений production в ходе этого аудита не выполнено.
