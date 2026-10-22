# M1.3 — аудит контентной архитектуры Aloha Hello

Дата: 22.10.2026. Проверены исходники и работающий локальный WordPress на `http://127.0.0.1:8088/`. Базовый Git HEAD: `87387be06f2ca217c67c1713cf35a40823caa40a`, тема Aloha Hello 0.2.2. **Результат этапа — этот документ.** Код, настройки, контент и rewrite rules не изменялись. GuestyBridge не создан; его исходники не искались.

## 1. Основания и результаты аудита

### Источники и приоритет

- [00 Site structure — start here.pdf](</Users/sonoma/Documents/ALOHA/00 Site structure — start here.pdf>), раздел **Pages, URLs and boards**, страницы 1–2 и примечание о breadcrumbs/301. PDF прочитан, обе страницы таблицы просмотрены после рендера. SHA-256: `b2b1e6353bd3bfef8c04be5d2443c48ded7288a3402c8fc0796fb638df4476ee`.
- Финальные HTML внутри ZIP и PNG в [макеты](/Users/sonoma/Documents/ALOHA/макеты): Home desktop/mobile, Catalog, Unit, Area Mauna Lani, Community Ka Milo, Guide Article manta rays. Это **шесть типов страниц, семь artboards**, а не семь шаблонов. Проверены HTML-заголовки/секции и обзор PNG.
- [Реестр дизайна](/Users/sonoma/Documents/ALOHA/docs/design-inventory.md), [исторический план M1](/Users/sonoma/Documents/ALOHA/docs/m1-theme-implementation-plan.md), отчёты [M1.0](/Users/sonoma/Documents/ALOHA/docs/m1-0-local-baseline.md), [M1.1](/Users/sonoma/Documents/ALOHA/docs/m1-1-theme-foundation.md), [M1.2](/Users/sonoma/Documents/ALOHA/docs/m1-2-header-footer-navigation.md), [M1.2.1](/Users/sonoma/Documents/ALOHA/docs/m1-2-1-mega-menu.md), [M1.2.2](/Users/sonoma/Documents/ALOHA/docs/m1-2-2-mega-menu-fixes.md).

Указания PDF о Boostly/Homey, Elementor и JetEngine описывают прежний стек. Сохраняем его карту контента, адреса и дизайн, но реализацию планируем на собственной block theme, Gutenberg и будущем GuestyBridge по текущему заданию. Старый план называл M1.3 разработкой Home; **в текущем задании M1.3 — только аудит**, главная ещё не реализована. Предложения по следующей нумерации приведены в §6 и не меняют прежний план автоматически.

### Что существует в теме

Source root: [theme/aloha-hello](/Users/sonoma/Documents/ALOHA/theme/aloha-hello). Это небольшая block theme без frontend-фреймворка и сборки приложения.

| Область | Реализовано и проверено | Отсутствует / следствие |
| --- | --- | --- |
| `theme.json` | Version 3; шесть цветов; локальные Instrument Serif normal/italic и Outfit; typography/spacing/radius presets; `contentSize`/`wideSize` 1200px; объявлены header/footer parts | Нет `customTemplates` и контентных template definitions |
| Геометрия и UI | `foundation.css`: внешний контейнер 1280px с полями 20–40px, внутренний 1200px; секционные ритмы; Buttons, Card, поля, notices, focus/reduced motion | CSS для полей не означает наличие формы, поиска или обработчика отправки |
| Display templates | Только `templates/index.html`: header → main с Query Loop, title/content, pagination/no-results → footer | Нет `front-page.html`, `page.html`, `single-post.html`, `single-area.html`, `single-community.html`, `archive-rental.html`, `single-rental.html`, category/404/search templates. Fallback не является одним из шести готовых макетов |
| Parts / Patterns | `parts/header.html`, `parts/footer.html`; единственный theme pattern `aloha-hello/site-header` (`Inserter: no`) | Нет редакционных starter patterns, карточек районов/комплексов/статей, hero/FAQ/галерей |
| Shell | Sticky Header, Core Navigation, desktop mega menu, компактный overlay, Footer/Legal, disclosures, мобильный Call/Book, demo dialog, защита неподтверждённых контактов | Booking и lead forms не подключены; не считать demo dialog бронью или отправкой |
| Настройки | Settings → Aloha shell: `aloha_shell_contact`, `aloha_shell_mega_menu`; Media Library; устойчивые ключи секций Navigation | Эти options относятся к оболочке, не к полям Area/Community или контентным маршрутам |
| Custom blocks | Только динамический `aloha/shell-slot`: brand/brand-light/header-actions/footer/utilities | Нет blocks для жилья, коллекций, фильтров, карты, breadcrumbs, TOC, структурированных editorial data |
| CPT / taxonomy / meta | В теме нет регистрации контентных CPT, новых таксономий или post meta для Area/Community | Предлагаемая модель в §2 целиком будущая |
| Dev tools | Отдельные local-only style showcase/shell preview; noindex, без создания Pages | Это виртуальные review surfaces, не публичная главная, каталог или контентные templates |

Используемые Core blocks: `template-part`, `pattern`, `group`, `navigation`, `navigation-link`, `navigation-submenu`, `query`, `query-title`, `post-template`, `post-title`, `post-content`, `query-pagination`/previous/next, `query-no-results`, `paragraph`. В dev showcase дополнительно есть `heading`, `buttons`/`button`, `list`/`list-item`, `separator`, `html`. Статический HTML showcase не является зарегистрированным компонентом Gutenberg.

`functions.php` подключает shell и assets, добавляет editor styles и стили Core Button `secondary`/`text`, Core Group `card`. `inc/shell.php`, `inc/mega-menu.php`, `inc/shell-settings.php` реализуют оболочку. `assets/js/*` обслуживают навигацию и её редактор/настройки, а не контентную модель. `editor.css` пока задаёт поля только для `page`/`post`: при добавлении CPT понадобится отдельно проверить их editor canvas.

### Что существует в локальной БД и маршрутизации

Проверено read-only WP-CLI: WordPress **7.1.3**, Aloha Hello активна; активных plugins и MU plugins нет. Нестандартных зарегистрированных типов/таксономий нет. У `post` есть стандартные `category`, `post_tag`, `post_format`; единственная текущая категория — `uncategorized`.

| Объект / настройка | Текущее значение |
| --- | --- |
| Permalinks | `/%postname%/`; `category_base` и `tag_base` пусты, фактический Core category prefix — `/category/` |
| Главная | `show_on_front=posts`, `page_on_front=0`, `page_for_posts=0`: сейчас `/` — индекс стандартных записей |
| Контент | Опубликованы `Hello world!` (post #1) и `Sample Page` (page #2); Privacy Policy #3 — draft |
| Navigation | Aloha Primary #16, Footer #17, Legal #18; также старый Navigation #7. Рабочие refs берутся из `aloha_shell_menus`, не из предполагаемых постоянных ID |
| DB templates | Сохранённых `wp_template`, `wp_template_part`, synced `wp_block` не найдено; global styles Aloha содержат только служебную оболочку JSON, без пользовательских style overrides |
| Rewrite | 94 сохранённых правила Core; дополнительных Aloha rules нет. В коде только local preview `template_include` filters, без публичных content rewrites |

`url_to_postid()` подтверждает Sample Page #2, но не находит опубликованных объектов для `/vacation-rentals/`, `/big-island/mauna-lani/`, `/big-island/communities/`, `/big-island/guide/`, `/homeowners/`, `/privacy-policy/`. Сам по себе результат `0` не доказывает 404 для архива: здесь вывод об отсутствии целевых маршрутов основан также на типах, rewrite rules и контенте БД.

Ссылки Primary/Footer/Legal уже соответствуют основным направлениям handoff. Все сейчас `kind: custom`: изменение slug будущего объекта **не обновит такой href автоматически**. `inc/destinations.json` — seed-реестр, а не router и не второй источник актуальной структуры Navigation. При реализации контента существующие Navigation posts надо сохранять; связывать ссылки с объектами целевым изменением через Site Editor, без повторной установки меню поверх правок менеджера.

## 2. Предлагаемая модель контента

### Типы и владение

| Тип | Назначение | Владелец и редактирование |
| --- | --- | --- |
| `page` | Home, маркетинговые/legal страницы, редакционные hubs | Core; менеджер редактирует блоки конкретной страницы |
| `post` | Big Island Guide articles и Investor Guides, при необходимости обычные статьи | Core; один общий article display template, принадлежность разделу задаётся явно |
| `area` | Три редакционных района из handoff | Предлагается небольшой site-specific content plugin: регистрация/поля/связи/routes. Тема отвечает за отображение |
| `community` | Жилые комплексы, каждый связан с одним Area | Тот же content plugin; собственный контент Gutenberg |
| `rental` | Каталог и отдельные дома | **Только будущий GuestyBridge** регистрирует CPT, владеет данными, archive/single routes и интеграцией. Сейчас не регистрировать даже временный `rental` |

Content plugin здесь — рекомендация для будущего отдельного задания, не новый плагин в этом этапе. Он позволяет сохранить модель и адреса при смене темы; не требует CPT UI, ACF, собственных таблиц или универсального конструктора. Тема не должна содержать второй регистратор тех же CPT.

### Area / Community: минимальные поля и связи

Для обоих CPT: `public`, `show_in_rest`, поддержка `title`, `editor`, `thumbnail`, `excerpt`, `revisions`, `custom-fields`; `page-attributes` только при необходимости ручного порядка (`menu_order`). Рекомендуется `hierarchical=false`: вложенный URL Community описывает связь разных типов, а не дерево записей одного CPT. Gutenberg включается через `show_in_rest`; editor starter template задаётся отдельно от frontend template. Основание: [register_post_type](https://developer.wordpress.org/reference/functions/register_post_type/).

| Данные | Area | Community | Хранение / источник |
| --- | --- | --- | --- |
| Название / slug | Название района; slug из URL handoff | Название комплекса; собственный slug | Core `post_title`, `post_name`; единственный источник permalink slug |
| Краткий анонс | Для карточки и intro | Для карточки и intro | Core excerpt; не копировать тот же текст в отдельное meta |
| Обложка | Hero/card fallback | Hero/card fallback | Core featured image, attachment ID; responsive image functions, alt в Media Library |
| Короткое название места | При необходимости отличается от маркетингового H1 | Аналогично | Необязательный `aloha_place_name`, string; fallback на title. Не нужен отдельный H1 meta, если достаточно Core title |
| Основной контент | Intro, факты, beaches, activities, advice, FAQ, editorial CTA | About, amenities, nearby, arrival notes, FAQ, owner CTA | `post_content`: Core blocks и starter patterns, без HTML/PHP-копии текстов |
| Фото/видео | Галерея, optional video/poster | Галерея, optional video/poster | Core Gallery/Image/Video blocks с Media Library IDs; не отдельный repeater и не обязательное видео |
| Родительский район | Не применяется | **Обязательный `aloha_area_id`** для публикации | Integer post meta, selector опубликованного `area`; проверка типа/статуса, не URL и не имя района |
| Координаты | Только если понадобится карта района | Только если понадобится карта комплекса | Необязательные валидируемые lat/lng meta; отсутствие не равно `0,0`. Подключение карты — отдельная задача |
| Подборка жилья / число домов | По стабильной связи с Area | По стабильной связи с Community | Вычисляется будущим provider GuestyBridge, не вводится менеджером вручную |
| Комплексы района | Обратная выборка Community по `aloha_area_id` | Не хранить второй список | Query по relation; ручной порядок допустим через `menu_order` |

На первом шаге обязательны только Core-поля, публикационная проверка relation и сам `aloha_area_id`; необязательные meta добавлять по потребности макета. Табличные editorial facts, amenities, quick facts, вопросы и ответы достаточно хранить блоками: не превращать каждый пункт в поле. Повторяемые карточки локаций — Groups/Image/Heading/Paragraph/Buttons; связи с существующими постами предпочтительнее вручную набранных URL.

Meta регистрировать по конкретному subtype, с типом, sanitization, capability-проверкой и REST-схемой. Небольшая editor sidebar / native control нужна для relation; одна регистрация meta не создаёт удобный selector. При включении meta в REST учитывать `custom-fields` support; revisions для важных meta включить явно и проверить восстановление. Медиа удалено — блок/шаблон использует fallback, не broken image. См. [register_post_meta](https://developer.wordpress.org/reference/functions/register_post_meta/).

Связи: **Area 1 → N Community**; Community имеет один Area. Будущий Rental может иметь Area и Community, но Community необязателен. Handoff отдельно отмечает дом в Kailua-Kona только в каталоге: не создавать четвёртый публичный Area ради такого объекта и не принуждать каждый Rental иметь editorial Area. Если у Rental есть Community, его Area должен согласовываться с Area комплекса. Сопоставление Guesty identifiers → WP IDs выполняет GuestyBridge; названия/слуги не служат integration IDs. Источник цен, availability, unit photos/descriptions/parameters — Guesty, без параллельного ручного редактирования в теме.

При смене Area комплекса permalink меняется: до сохранения показать новый адрес и потребность 301; старую связь/адрес сохранить для redirect mapping. Удаление Area с опубликованными Community требует переназначения или снятия их с публикации, не молчаливого orphan. Slugs Community на первом этапе оставлять уникальными в пределах CPT: одинаковые slugs под разными Area требуют дополнительной логики уникальности и сейчас не оправданы.

### Статьи: минимальная классификация

Предлагается использовать **стандартную `category`**, без отдельного Guide CPT и без дублирующей taxonomy районов:

- Родительская категория Guide с устойчивым внутренним slug, например `big-island-guide`; четыре дочерних: `things-to-do`, `food-and-drink`, `top-spots`, `events`.
- Отдельная категория `investor-guides` для второго семейства статей.
- Для Guide обязательна ровно одна маршрутная рубрика из четырёх; для Investor — категория Investor Guides и отсутствие Guide-рубрики. Другие тематические tags не меняют адрес. Смешанное назначение блокируется публикационной проверкой.

Это даёт один однозначный путь без primary-category плагина, отдельного enum и универсальной системы правил. Идентификаторы назначенных категорий хранятся в небольшой конфигурации content plugin, связи статей — стандартные taxonomy relations. Новая рубрика потребует явного расширения списка, а не создания произвольных путей менеджером. Не относящиеся к этим семействам записи сохраняют текущий `/%postname%/`.

## 3. Полная таблица адресов handoff

Ниже **28 строк** после раскрытия трёх Area, четырёх рубрик и трёх Legal страниц. Все целевые пути взяты из **Pages, URLs and boards**; точные три Area slug дополнительно подтверждены Main menu на странице 1. `[unit-name]`, `[area]`, `[community]`, `[rubric]`, `[article]` — обозначения переменных из PDF, не реальные slug. Список 24 домов, полный список комплексов и slugs отдельных статей в handoff не приведены: они здесь не придуманы.

Все display templates ниже **предлагаемые**, кроме уже существующего fallback `index.html`. Custom names `aloha-*.html` надо будет зарегистрировать в `theme.json` и назначить конкретным Pages; имя файла само не создаёт маршрут.

Обозначения генерации: **P** — Core Page slug/иерархия родителей; **A** — штатный CPT rewrite Area; **C** — ограниченный Community resolver с проверкой Area; **G** — разрешённая Guide category и scoped rules; **I** — scoped Investor post route; **R** — будущий GuestyBridge. «Нет» в колонке special rewrite означает отсутствие вручную добавленного правила, а не отсутствие внутренних правил Core вообще.

| Назначение | Требуемый URL | Тип / taxonomy | Display template | Генерация маршрута | Special rewrite | Конфликты / условия |
| --- | --- | --- | --- | --- | --- | --- |
| Home | `/` | `page`, статическая главная | `front-page.html` | P + Settings → Reading | Нет | Сейчас здесь posts index; `home.html` не является шаблоном маркетинговой главной |
| All Rentals, catalog/map | `/vacation-rentals/` | `rental` archive | `archive-rental.html` | R, `has_archive` | Нет отдельного, CPT генерирует | Не создавать Page с тем же адресом |
| Unit ×24 по handoff | `/vacation-rentals/[unit-name]/` | `rental` | `single-rental.html` | R, CPT single | Нет отдельного при обычной структуре CPT | Зарезервировать служебные endpoints; не делать Pages домов |
| Mauna Lani | `/big-island/mauna-lani/` | `area` | `single-area.html` | A | Нет, штатный CPT rewrite | Один объект для ссылок из обоих разделов меню |
| Waikoloa Beach Resort | `/big-island/waikoloa-beach-resort/` | `area` | `single-area.html` | A | Нет, штатный CPT rewrite | Не создавать одноимённую дочернюю Page |
| Waikoloa Village | `/big-island/waikoloa-village/` | `area` | `single-area.html` | A | Нет, штатный CPT rewrite | То же; нужен собственный контент, не копия Mauna Lani |
| Communities list | `/big-island/communities/` | `page` + выборка `community` | `aloha-communities.html` | P + приоритетное исключение из Area matcher | **Да**, точное правило hub | `communities` запрещён как Area slug; не включать второй Community archive здесь |
| Community | `/big-island/[area]/[community]/` | `community` + relation к `area` | `single-community.html` | C + permalink из relation | **Да** | Не перехватывать Guide rubric; неверный Area → 404, старый подтверждённый адрес → 301 |
| Big Island Guide hub | `/big-island/guide/` | `page` + Query по Guide posts | `aloha-guide-hub.html` | P + приоритетное исключение из Area matcher | **Да**, точное правило hub | `guide` запрещён как Area slug; не назначать этот Page глобальным `page_for_posts` |
| Things to Do | `/big-island/guide/things-to-do/` | `category` | `category.html` | G, term archive | **Да**, только Guide subtree | Стандартный `/category/…/` должен иметь один canonical target |
| Food & Drink | `/big-island/guide/food-and-drink/` | `category` | `category.html` | G, term archive | **Да**, только Guide subtree | Не создавать Page на месте taxonomy archive |
| Top Spots | `/big-island/guide/top-spots/` | `category` | `category.html` | G, term archive | **Да**, только Guide subtree | То же |
| Events | `/big-island/guide/events/` | `category` | `category.html` | G, term archive | **Да**, только Guide subtree | То же; даты события не определяют URL |
| Guide article | `/big-island/guide/[rubric]/[article]/` | `post`, одна Guide category | `single-post.html` | G + `post_link` | **Да** | Проверить рубрику и семейство, не только slug статьи; исключить несколько индексируемых адресов |
| Property Management | `/homeowners/` | `page` | `page.html` | P | Нет | Самостоятельная страница и родитель следующих Pages |
| Invest in a Vacation Rental | `/homeowners/invest/` | `page` | `page.html` | P, parent Homeowners | Нет | Сохранить этот путь, даже если в Header отдельный Invest |
| Investment Calculator | `/homeowners/invest/calculator/` | `page` | `page.html` | P, parent Invest | Нет | Не конфликтует с guides; формулы/JS отдельным scope |
| Investor Guides article | `/homeowners/invest/guides/[article]/` | `post`, Investor Guides category | `single-post.html` | I + `post_link` | **Да** | Это не дочерняя Page и не Guide rubric; индекс `/guides/` отдельно не утверждён |
| Free Rental Estimate | `/homeowners/estimate/` | `page` | `page.html` | P, parent Homeowners | Нет | Форма и отправка не создаются фактом существования Page |
| Design & Renovation | `/homeowners/design-renovation/` | `page` | `page.html` | P, parent Homeowners | Нет | Сохранить полный slug, не сокращать до `/design/` |
| Results & Reports | `/homeowners/results/` | `page` | `page.html` | P, parent Homeowners | Нет | Название меню не требует изменения `/results/` |
| Owner FAQ | `/homeowners/faq/` | `page` | `page.html` + FAQ pattern | P, parent Homeowners | Нет | Отдельно от гостевого FAQ; schema только для фактически опубликованных ответов |
| Our Team | `/about/` | `page` | `page.html` | P | Нет | Не создавать `/about/team/` вместо handoff URL |
| Guest Info | `/guest-info/` | `page` | `page.html` | P | Нет | Guest FAQ переносится сюда по handoff |
| Contact | `/contact/` | `page` | `page.html` | P | Нет | Два intent: guest/homeowner; не два разных публичных адреса |
| Privacy Policy | `/privacy-policy/` | `page` | `page.html` | P | Нет | Уже есть draft #3: доработать, не создавать дубликат с `-2` |
| Terms of Use | `/terms/` | `page` | `page.html` | P | Нет | Не заменять произвольно на `/terms-of-use/` |
| Rental Agreement | `/rental-agreement/` | `page` | `page.html` | P | Нет | Legal content клиента; не копировать договор из макета |

### Как разрешать вложенные пути

1. **Area:** штатная CPT permastruct с base `big-island`, `with_front=false`, `has_archive=false`, без single pagination/feed endpoints, пока они не требуются. Не нужно отдельное правило на каждый район. **Одного запрета slug недостаточно:** сгенерированный общий Area matcher всё равно перехватит `guide`/`communities` до Core Page rules. Поэтому два точных правила hub должны раньше направить запрос на соответствующий `pagename`; объекты Pages создаются отдельно. Для их иерархии предпочтительно хранить родителя Big Island как Page после решения о его содержимом ниже; сам родитель не создаёт районы как дочерние Pages.
2. **Community:** один ограниченный двухсегментный resolver под `big-island`, исключающий `guide`/`communities` и служебные endpoints. Выходной permalink строится из текущего Area ID; входной запрос должен найти опубликованный Community и сравнить его relation с Area из URL. Нельзя просто игнорировать первый сегмент и искать Community по имени. Для CPT permalink используется [post_type_link](https://developer.wordpress.org/reference/hooks/post_type_link/).
3. **Guide:** только четыре разрешённые рубрики. Архив → реальная Core category query; article → `post` с проверенной принадлежностью этой рубрике. Выходные ссылки — [category_link](https://developer.wordpress.org/reference/hooks/category_link/) и [post_link](https://developer.wordpress.org/reference/hooks/post_link/), а не `post_type_link` для обычных posts. Родитель Guide category нужен для классификации/выборки, не дополнительный сегмент handoff URL.
4. **Investor:** отдельный точный префикс `homeowners/invest/guides` и проверка Investor category. Один article display template с Guide; данные breadcrumbs и related posts зависят от семейства, не от вручную выбранного имени template.
5. **Core Pages:** действительная иерархия Homeowners → Invest → Calculator и Homeowners → остальные дочерние Pages. Не пытаться получить вложенный путь, записав слеши в `post_name`.

Точные hub rules, Guide/Investor и специальные endpoints располагаются до общих Area/Community matchers и до fallback. Все regex ограничены началом/концом и нужной глубиной; неизвестные rubric/Area и лишние сегменты дают 404. Правила category pagination/feed добавляются явно, если соответствующие поверхности включены, и не принимаются за Community или article. Публичные attachment subpages под Area/Community не включать: те же подпути нужны комплексам, а media доступны как файлы. Это ограниченная маршрутизация нескольких семейств, **не Content Template Rules engine**. Механизм Core: [add_rewrite_rule](https://developer.wordpress.org/reference/functions/add_rewrite_rule/).

Breadcrumbs строятся по объектам и relations: Unit — Home → Vacation Rentals → Area → Community → Unit (отсутствующие звенья пропускаются); Community — Home → Big Island → Area → Community; Guide — Home → Big Island Guide → Rubric → Article. Для Investor использовать существующие Homeowners/Invest и статью, не придумывать промежуточную ссылку на неутверждённый Guides hub.

Выходной permalink filter сам по себе не создаёт входной маршрут. При реализации нужны обе стороны, sample permalink в редакторе, preview/draft без редиректа на опубликованный адрес и проверка canonical. Для старых сохранённых адресов — явный 301; произвольный неверный Area/rubric не должен автоматически становиться успешной копией статьи. Старые default post/category URLs для перенесённых объектов должны вести на canonical; родительская Guide category — на Guide hub. Отдельный публичный Investor category archive не нужен: его индексируемость/редирект надо решить вместе с индексом Investor Guides. Не менять глобально `/%postname%/` или `category_base` ради двух семейств.

До включения правил проверить занятость path у Pages, CPT, categories и endpoints. В Area reserve list как минимум `guide`, `communities`, `feed`, `page`, `embed`; для Rental исключить пересечение со служебными путями будущего плагина. Перечень должен расширяться из реальной конфигурации, не из номера позиции меню. Изменение названия/порядка Navigation не меняет routing и relation.

### Пути, которые нельзя выдать за утверждённые строки таблицы

| Путь | Фактическое основание | Предлагаемое решение перед реализацией |
| --- | --- | --- |
| `/big-island/` | Родительская ссылка уже сохранена в Navigation, отдельной строки Page в PDF нет | Решить: короткий hub Page (удобен и как родитель Guide/Communities) либо осмысленный redirect на согласованную существующую цель. Не создавать пустую Page молча |
| `/homeowners/invest/guides/` | В PDF есть только статьи ниже этого префикса; seed отмечает pending hub | Для single posts родительская Page технически не нужна. Отдельный hub не создавать без решения о контенте |
| `/vacation-rentals/?feature=step-free` | Дополнение существующего меню, не строка Pages/URLs | Зарезервированный filter proposal; будущий plugin должен подтвердить query contract |
| `/contact/?intent=group-stay` | Предложение прежнего design inventory | Не новый Page и не утверждённый handoff route |
| `/booking/`, `/booking/result/` | Предложение прежних документов, не таблица PDF | Checkout routing решает будущий GuestyBridge отдельным scope |

Примерный slug Ka Milo и manta rays из старого inventory также не заменяет утверждённые реальные slug. До получения списка контента достаточно семейства маршрутов.

### Базы путей и 301

Минимальная настройка — одна версия конфигурации в будущем content plugin и небольшой filter для разработчика: `island_base`, `guide_base`, `investor_guides_base`; production defaults строго соответствуют таблице. Деривативные пути вычисляются из этих баз. Rental base принадлежит конфигурации GuestyBridge, а не копируется в content plugin. Не добавлять общую административную панель URL и не давать менять базы редактированием Navigation.

Изменение base — отдельная контролируемая операция: проверить конфликты, согласовать Page hierarchy/href меню, обновить permalink generation и explicit redirect map, один раз пересоздать rules. Flush при активации/подтверждённом изменении конфигурации, **не на каждом запросе**. Сейчас базовые пути и rules не менялись.

Примечание PDF требует 301 с **`/homes/…`, `/listing/…`, `/search-results/`**. Это обозначения старых семейств, не полный список исходных URL. Для миграции отдельно получить экспорт адресов и соответствие старых listing IDs новым Rental IDs. `/homes/…` и `/listing/…` → конкретные новые units; `/search-results/` → `/vacation-rentals/` с переносом только поддержанных фильтров после утверждения query contract. Не перенаправлять все неизвестные дома на главную. Проверить один hop, отсутствие циклов, сохранение нужных query parameters и поведение удалённых объектов. Реестр миграции, canonical и смена slug Area/Community/рубрики — одна согласованная задача; текущий аудит не выполняет ни редиректы, ни запросы к production.

## 4. План Gutenberg Templates и Patterns

**Display template** задаёт document shell, размещение Post Content и динамических слотов. **Editor starter template/pattern** вставляет стартовые блоки в `post_content` при создании записи; менеджер меняет собственный текст/фото. Повторное открытие или обновление темы не должно повторно вставлять starter и перезаписывать данные. Изменение несинхронизированного pattern не обновляет уже созданные страницы.

Предлагается держать header/footer и общую layout-структуру в template parts/templates, а editorial body — в записи. Для структурных Groups допустим content-only editing/точечная блокировка перемещения; не блокировать весь документ там, где нужны новые секции. Для запросов и данных, зависящих от текущей записи, оставлять немного явных динамических слотов. Смысл Core hierarchy и регистрации custom templates проверен по [Template Hierarchy](https://developer.wordpress.org/themes/templates/template-hierarchy/) и [Templates](https://developer.wordpress.org/themes/templates/templates/).

### Шесть утверждённых типов макетов

«Статическая» ниже означает структуру шаблона, а не жёстко зашитый редакционный текст.

| Макет / тип | Display template | Статическая структура | Менеджер / Core blocks | Динамические данные | Starter / Patterns |
| --- | --- | --- | --- | --- | --- |
| **Home** — `page`, static front | `front-page.html` с Post Content | Shell, hero/search position; desktop Standard → featured, mobile featured 2×2 → Standard; секции areas → metrics → reviews → guide → founder → direct-booking → owner CTA | Hero/intro, Standard benefits, editorial headings, founder/CTA и проверенные claims: Group, Cover/Image, Heading, Paragraph, Columns, Buttons, Quote. Порядок desktop/mobile решается согласованной layout-композицией, без двух копий текста в БД | Search, featured rentals, listing counts/prices/reviews — будущий provider. Area/guide cards — queries к editorial контенту; до данных — явный local demo | `home-start`, `hero-search-shell`, `benefits-grid`, `area-teasers`, `metrics`, `founder-story`, `split-cta`, `owner-cta` |
| **Catalog** — будущий `rental` archive | `archive-rental.html` | Heading/intro, search/filter bar, results/map layout, advisor CTA, direct-booking benefits | Intro/CTA/benefit copy — редакционные blocks/отдельная согласованная настройка архива. Archive не имеет собственного `post_content`: не создавать ради него Page с конфликтующим адресом | Rentals, filters, sort, applied state, map markers, dates/prices, empty/error — GuestyBridge. Требование handoff: все 24 актуальные карточки в initial HTML, без infinite scroll; число 24 не hardcode | `catalog-intro`, `advisor-cta`, `direct-benefits`; динамический collection slot вместо ручных карточек. До GuestyBridge только local fixture preview |
| **Unit** — будущий `rental` single | `single-rental.html` | Breadcrumb/title → gallery → facts + booking sidebar → special features → description → sleeping → amenities → nearby/map → reviews → manager → rules → similar | В теме — структура/стили reusable blocks. Unit-specific описание/фото/параметры не копировать в независимый Gutenberg body для менеджера. Общие editorial CTA можно переиспользовать отдельно от source-owned данных | Весь unit data и booking state — GuestyBridge; related rentals/availability/quote также. Тема получает projection, не хранит второй источник | `manager-cta`, `unit-section-shell`; до плагина dev fixtures. Developer board «Booking widget: two states» под финальным HTML не публиковать секцией дома |
| **Area** — `area` | `single-area.html` | Hero → intro/facts → homes → pictures → beaches → activities → communities → practical advice/FAQ → advisor CTA | Title/excerpt/featured image + Cover/Video, Gallery, Group/Columns, Heading, Paragraph, List/Table, Details, Buttons. Beaches/activities и practical copy принадлежат записи, а не template PHP | Homes/count по Area — provider; Communities — query по `aloha_area_id`. CTA и cards используют текущие permalinks | `area-start`, `place-intro-facts`, `place-gallery`, `place-cards`, `practical-faq`, `advisor-cta`; collection/communities slots на нужных позициях |
| **Community** — `community` | `single-community.html` | Split hero → thumbnails → About/facts → amenities → homes → Around/map → FAQ → owner estimate CTA/form | Core title/excerpt/image; Image/Gallery, Columns, Group, Heading, Paragraph, List/Table, Details, Buttons; relation — sidebar selector | Breadcrumb Area; filtered homes/count — provider; карта при подключении; form submission отдельным модулем. Менеджер не вводит homes list или число домов | `community-start`, `split-place-hero`, `amenities-grid`, `nearby-facts`, `practical-faq`, `owner-estimate-shell` |
| **Guide article** — `post`; Investor использует ту же основу | `single-post.html` | Hero → TOC/body/quick-facts-aside → related. Для Guide и Investor меняются breadcrumb/related scope, не весь layout engine | Title/excerpt/featured image; Heading с anchors, Paragraph, Image, List, Quote, Table, Details, Buttons. Quick facts/таблицы/FAQ — контент статьи, обязательность зависит от темы; Investor не обязан иметь manta-specific факты | TOC из реальных H2/H3 с устойчивыми anchors; taxonomy breadcrumbs/related posts; rental CTA только при наличии provider. TOC не хранить вторым независимым списком | `article-start`, `article-quick-facts`, `comparison-table`, `article-faq`, `related-editorial`, `advisor-cta` |

Core Query Loop годится для простого списка posts/areas/communities и стандартного taxonomy archive; relation-фильтр Community текущего Area потребует небольшой query extension/динамический block с editor preview. Для SSR listing collection, интерактивной карты, фильтров, booking и формы отправки стандартных layout blocks недостаточно. Не подменять эти функции `core/html` или вручную набранными ценами. FAQ можно начать с Core Details, но schema не возникает от одного раскрывающегося блока. TOC/breadcrumbs также ещё предстоит реализовать.

Для Home/Catalog/Unit временные demo providers должны оставаться local-only и явно обозначенными. Оболочка и конфигурация шаблона могут быть редактируемыми, даже когда источником данных жилья остаётся внешний плагин. Нужный способ редактирования intro архива выбрать в отдельном задании; минимальный вариант — блоки непосредственно в Site Editor archive template, без второго Page по тому же URL.

### Небольшой общий набор Patterns

| Семейство | Состав | Где переиспользовать |
| --- | --- | --- |
| Hero / intro | Cover или split Image+Group, heading/lead/buttons | Home, Area, Community; без встроенного booking engine |
| Facts / amenities | Group grid, Heading/Paragraph/List/Table | Area, Community, article quick facts; не копия unit API данных |
| Editorial cards | Image + title + excerpt + link; внешний Query Loop для живой коллекции | Areas, Communities, Guide/related articles |
| Gallery / media | Core Gallery/Image/Video + captions | Area, Community, article; media picker, alt, отсутствие empty slots |
| Advice / FAQ | Headings, text, Details, optional CTA | Area, Community, Owner FAQ, Guest Info |
| CTA | Advisor, split guest CTA, owner estimate shell | Все соответствующие layouts; contacts из существующих settings |
| Article body | Intro, anchors, quick facts, optional table/FAQ | Guide и Investor; самостоятельный post content |

Начать с двух полных starter compositions Area/Community и нескольких общих patterns. Не создавать десятки variations и специальный CPT для каждого блока. Guides/Communities hubs, все Homeowners/About/Legal страницы пока без отдельных final PNG; для них нужны простые `page.html` и согласованные composition proposals. Только Home имеет утверждённый mobile 390; остальные mobile layouts остаются адаптацией для отдельной приёмки. Текущий Header breakpoint **1280px** сохраняется; он не устанавливает breakpoint сетки всех контентных templates.

## 5. Компоненты для переиспользования

| Уже есть | Файлы / данные | Как использовать дальше |
| --- | --- | --- |
| Tokens, fonts, общий UI | `theme.json`, `assets/css/foundation.css`, `assets/fonts/` | Та же типографика/палитра/spacing; не переносить inline styles и runtime из HTML export |
| Header/Footer, Navigation, contact protection | `parts/*`, `patterns/site-header.php`, `inc/shell.php`, `inc/mega-menu.php`, `assets/css/shell.css`, `assets/js/shell.js` | Подключать во все display templates; не размножать Header/меню в контенте страниц |
| Настройки редакционного mega menu | `inc/shell-settings.php`, `assets/js/shell-settings.js`, `assets/js/mega-menu-editor.js`, option `aloha_shell_mega_menu` | Сохранить независимость текстов mega panel от содержания Area/Community; CTA URL берётся из Navigation |
| Native button/card styles и editor preview | `functions.php`, `assets/css/editor.css`, `assets/js/shell-editor.js` | Использовать Core blocks; расширить editor canvas под CPT при реализации |
| Primary/Footer/Legal posts | БД, refs в `aloha_shell_menus` | Сохранить имена, порядок, устойчивые ключи mega sections и правки менеджера при связывании с объектами |
| Dev showcase/preview, smoke tooling | `dev/*`, `scripts/theme-smoke.cjs`, `scripts/mega-menu-*.cjs` | Основа будущей QA, без объявления dev markup production-компонентом. Settings smoke tests могут менять данные: в этом read-only аудите не запускались |

**Ещё нет reusable implementations** listing cards, search/date/guest pickers, editorial card pattern, hero/gallery/media viewer, TOC, breadcrumbs, FAQ/schema, relation selector, related collections, map и lead form. Их внешний вид в ZIP и описание в прежнем плане не означают готовую реализацию.

## 6. Небольшие следующие задачи

Ни один следующий этап не запускается этим документом. Старый план использовал M1.4=Catalog demo, M1.5=Unit demo. Ниже предложены **новые пакеты после текущего аудита**; если историческая нумерация сохраняется, назвать их Content A/B, а Catalog/Unit оставить отдельными будущими задачами. Главное — не включать незаметно регистрацию/маршруты и все шесть макетов в одно задание.

| Пакет | Минимальный результат | Приёмка | Оценка |
| --- | --- | --- | --- |
| **M1.4-A — редакционная модель** | Небольшой content plugin: только Area/Community, Gutenberg supports, relation selector `aloha_area_id`, валидация/санитизация; без Rental, без визуальных templates и специальных nested routes | Создание/сохранение/повторное открытие, revisions, отсутствие дубликата relation, реакция на удалённый Area, работа при смене темы; fixtures только явно тестовые | 8–12 ч |
| **M1.4-B — маршруты** | После отдельного задания: A/C/G/I, четыре категории + Investor classification, reserved slugs, выходные permalinks и входная проверка, canonical/redirect policy | 200 для правильного URL, 404 для чужого Area/рубрики, отсутствие коллизии с hubs/Pages, preview, slash/pagination, смена slug/Area и один 301 hop; без production migration | 12–20 ч |
| **M1.5-A — один editorial экран** | Сначала Area Mauna Lani: `single-area.html`, `area-start`, intro/facts/gallery/advice; reuse shell; homes slot с честным empty/demo fallback | Реальные правки текста/медиа в Gutenberg, long content, 320/390/768/1024/1440, keyboard/200%, порядок секций, отсутствие пустых media; остальные страницы не менять | 12–20 ч |
| **M1.5-B — последующее переиспользование** | Отдельными reviewable задачами Community, затем общий Guide/Investor article с TOC/tables/related | Проверка relation/breadcrumbs, editing, mobile proposals и reusable patterns; формы пока без отправки | Community 12–20 ч; article 12–20 ч |

**Минимальный следующий заказ:** только M1.4-A, после согласования имени/расположения content plugin. Не включать Home, Catalog, Unit, GuestyBridge, новые публичные Page hubs, rewrite rules, массовое наполнение или миграцию. На этом backend-шаге не обещать готовые pretty URLs: их проверяемая реализация — M1.4-B. Разработка главной также требует отдельного задания, поскольку текущий M1.3 её не выполнял.

## 7. Риски, ограничения и проверки

| Риск / открытое решение | Влияние / действие |
| --- | --- |
| Пересечение Area/Community/Guide/Pages | Основной технический риск. Reserved slugs + ограниченные rules + проверка relation; отдельно протестировать Core canonical, preview и 404 guessing |
| Две семьи `post` | Нужны однозначная публикационная классификация и scoped URL filters; не менять весь permalink structure сайта |
| `/big-island/` и Investor Guides hub не описаны отдельными страницами | Решить содержание/redirect перед созданием Page hierarchy и полной breadcrumb/navigation приёмкой |
| Отсутствуют реальные slugs/миграционный экспорт | Нельзя завершить поштучную 301 map; PDF даёт только три старых семейства |
| Нет GuestyBridge | Каталог/Unit/цены/availability не реализуемы как live data сейчас. Не регистрировать временный Rental в теме или content plugin |
| Нет mobile final для пяти типов и final для hubs/marketing | Нужна отдельная адаптивная/контентная приёмка; не заявлять совпадение с отсутствующим PNG |
| Site Editor overrides в БД | Сейчас содержательных overrides нет; в будущем могут перекрывать файлы темы. Изменение файлов не должно уничтожать пользовательские templates/pattern content |
| Custom href Navigation | Slug/base changes требуют целевой проверки меню; повторный install не должен сбрасывать сохранённый Navigation |
| Контент и медиа | Фото/видео, verified copy, FAQ, legal, показатели/отзывы и права требуют подготовки. Редактируемость не подтверждает достоверность placeholder claims |

Оценка для редакционной foundation, маршрутов и трёх editorial displays: **56–92 инженерных часа** (примерно 7–12 рабочих дней), включая базовую editor/routing/responsive QA. Это не оценка всего M1: Home, Catalog/Unit demo, реальные данные, production redirects, формы, калькулятор, GuestyBridge и финальное наполнение исключены. При готовых текстах/медиа и подтверждённых решениях риск ниже; двусмысленные URL и отсутствующие mobile states увеличивают объём согласования.

Проведено в этом аудите:

- Read-only проверка файлов темы, Core block markup/регистраций, конфигурации `theme.json`, текущих Navigation posts, WP options, supports, taxonomy terms, DB templates/global styles, сохранённых rewrite rules и поиска объектов по целевым URL.
- Сверка всех строк Pages/URLs на двух страницах PDF и структуры шести final HTML; обзор семи PNG artboards. Отдельная визуальная приёмка новых экранов не проводилась: экранов и кода в этом задании не создавали.
- `python3 scripts/check-references.py` **не прошёл**: отсутствует исходный `aloha-architecture-sources.zip`, который указан в историческом manifest. Его отсутствие установлено до изменений этого аудита; архив не восстанавливался и manifest не исправлялся. Отдельная SHA-256 проверка **всех 17 файлов `макеты/` прошла**. Нынешний PDF доступен независимо от старого архива, поэтому URL-аудит завершён по оригиналу.
- Изменение ограничено добавлением этого документа. Исходный PDF пользователя остаётся отдельным untracked файлом; код, конфигурация темы и БД не редактировались. Commit, push, deployment и миграции не выполнялись.

Перед реализацией достаточно подтвердить порядок следующего небольшого пакета и решение о будущих hub Pages. Сам аудит завершён; он не означает реализацию шести макетов или готовность сайта к запуску.
