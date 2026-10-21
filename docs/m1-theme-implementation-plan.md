# ALOHA — план M1: WordPress Theme Foundation

**Подготовлено 21.10.2026, Europe/Istanbul.** Основание: [обновлённое M0](/Users/sonoma/Documents/ALOHA/docs/aloha-hello-architecture-feasibility.md) и [реестр дизайна](/Users/sonoma/Documents/ALOHA/docs/design-inventory.md). Этот файл задаёт будущую работу. Тема, GuestyBridge, WordPress-среда и Git-репозиторий в этом задании не создавались.

## 1. Цель, границы и готовность

M1 должен дать чистую собственную WordPress-тему с бренд-системой и server-rendered templates на явно тестовом контенте. Guesty credentials, availability/quote/payment API и production для этого не нужны. Наличие готовых PNG позволяет начать foundation сейчас; оно не превращает недостающие mobile/marketing states в утверждённые макеты.

**Доступная дизайн-база:** Home1440/Home390, desktop Catalog/Unit/Area Mauna Lani/Community Ka Milo/Article manta rays; wireframes Catalog/Unit/Area/Community/Article; все 19 уникальных старых blob-assets. Точные H/P/W source IDs и размеры приведены в реестре.

**Результат M1:** собственная block theme + core Gutenberg patterns, доступная навигация, responsive Home и demo templates, editorial Area/Community/Article и generic marketing shells, согласованные mobile proposals, UI states на fixtures, визуальная/keyboard/performance приёмка. Все booking-поверхности помечены Demo и не создают внешних действий.

**Вне M1:** Guesty clients/auth/sync/CPT/REST/реальные quotes, checkout/payments, plugin implementation, Google Maps billing/API keys, backend отправки заявок, реальный investment calculator, legal fact-check, production migration/deployment/push. Нельзя временно написать booking engine в теме и назвать это mock.

## 2. Состояние локального проекта

При осмотре в корне найдены исходные архивы, `макеты/`, `docs/`, `snippets.php`; `.git`, WordPress directories и package/composer manifest не обнаружены. Найдены executables Git, PHP, Docker, Node и npm; наличие бинарника **не подтверждает** работающий Docker daemon, совместимость версий, БД или готовую WP-среду. WP-CLI и mysql/mariadb CLI в PATH не найдены. Ничего не устанавливалось и не запускалось как WordPress.

Для M1.0 предпочтителен небольшой изолированный локальный WordPress + DB через имеющийся Docker, если health check подтвердит доступность. Существующую подходящую среду пользователя можно использовать вместо него. Не ставить одновременно Docker Compose, LocalWP, wp-env и системную MySQL. Решение фиксируется после preflight, без избыточной инфраструктуры. Актуальные совместимые версии WP/PHP/DB и фиксированные image tags выбираются при реальном начале M1, а не угадываются по дате этого плана.

## 3. Порядок работ и зависимости

Обозначения: **обязательное решение** — без него нельзя принять соответствующий экран; **не блокирует начало** — можно работать с явно обозначенным demo/placeholder. Этапы являются небольшими reviewable пакетами; завершение одного не означает автоматического разрешения начать production.

| Этап | Результат | Зависимости | Приёмка / граница |
| --- | --- | --- | --- |
| **M1.0 — Local baseline** | Проверенная локальная WP/DB среда, Git baseline, README с start/stop/reset, ignore policy | Отдельное задание на реализацию; проверка бинарников/daemon/портов/versions | WP открывается локально; DB persistent; WP core/uploads/secrets/runtime не коммитятся; originals сохранены; ни theme, ни plugin ещё не реализованы |
| **M1.1 — Tokens и fonts** | Theme skeleton, `theme.json`, font registration, global type/spacing/color styles; token proof page | M1.0; H01/H02, локальные разрешённые font files/лицензии | Сетка content1200 при 1440, поля 20 при 390; font normal/italic; colors/state tokens; fallback при font error; нет Claude runtime |
| **M1.2 — Header/Footer/Nav** | Desktop shell, mobile header, dropdown/menu/disclosure/footer; mobile Call/Book UI | M1.1; согласовать Invest/Results/menu destinations | Pixel-oriented проверка P01/P02, keyboard/escape/focus return, safe-area/zoom; desktop header по референсу не делается sticky автоматически |
| **M1.3 — Home desktop/mobile** | Полная главная с editable sections и demo featured collection | M1.2; shared contracts v1; H01/H02/P01/P02 | Desktop Standard до homes; mobile homes2×2 до Standard; настоящий контент страницы в SSR; carousel, CTA, no autoplay; unverifiable claims заменены |
| **M1.4 — Catalog demo** | Template с 24 fixtures, filters/chips/sort, price states и demo map/list | M1.2 + shared contracts; H03/W01/P03; согласование mobile proposal | Все 24 records в initial HTML; consistent map/list state; safe demo dates/guests; empty/loading/error/partial; total sort только для сопоставимых fixtures |
| **M1.5 — Unit demo** | Gallery/facts/features/description/rooms/amenities/location/reviews/rules/similar + demo widget | M1.4 view model; H04/W02/P04; mobile proposal | SSR editorial content; usable gallery/accordions; states из M0§9.4; mobile sheet без второго sidebar; developer state board не в public page |
| **M1.6 — Editorial templates** | Area, Community, Article; marketing/content/FAQ/form shells и archive proposal | M1.2; H05–H07/W03–W05/P05–P07; shared collection projection | Реальный порядок final templates; Article TOC/tables, Community owner form, Area homes и gallery; missing media честно обозначены; page-specific approvals не выдуманы |
| **M1.7 — Integrated QA и handoff** | Responsive/accessibility/performance checks, editor guide, screenshots и acceptance log | M1.3–M1.6, решения об исключениях и demo content | 320/390/768/1024/1440; нет page overflow, неверных confirmations/реальных calls; approved/deferred список; готовая граница подключения M2 |

M1.3–M1.6 могут использовать общий foundation и независимые шаблоны, но перед их реализацией должны стабилизироваться shared contracts. Полноценный M2 начинается отдельным заданием; обсуждение контрактов не является разрешением писать плагин на M1.

### 3.1. Что именно входит в M1.6

Не называть все маркетинговые страницы готовыми по одному placeholder shell:

- **По конкретному final:** Area Mauna Lani, Community Ka Milo, Article manta rays. Структуру других areas/communities/articles можно переиспользовать после отдельного content mapping.
- **Общие reusable shells:** текстовая/legal page, FAQ, sectioned marketing, article, form layout. Это foundation для About/Guest Info/Contact/Homeowners/Invest/Estimate/Results/Renovation; их собственная композиция ещё не утверждена.
- **Нужны дополнительные composition approvals:** Communities hub, Guide hub и category archives, полный Homeowners/Invest/About/Contact. Можно подготовить одну предложенную композицию каждого семейства для review; нельзя объявить PNG-соответствие отсутствующему PNG.
- Calculator в M1 — максимум нейтральный form/result shell с надписью Demo, без финансового результата и доходности. Реальные формулы, условия и функциональность — отдельный согласованный scope.

## 4. Карта будущей структуры репозитория

Это проектное предложение, директории сейчас не создаются:

| Область | Будущее содержимое | Правило |
| --- | --- | --- |
| `docs/` | Исследование, inventory, plan, QA/ADR/editor guide | История решений сохраняется |
| `макеты/`, исходный ZIP | Reference-only; исходные PNG/ZIP | Не править, не оптимизировать поверх оригиналов, не включать в deployed theme |
| `theme/aloha-hello/` | `theme.json`, `style.css`, минимальный `functions.php`, templates/parts/patterns, assets/fonts | Явный source root; локальная WP монтирует эту тему; alternative `wp-content/themes/...` допустим, выбрать один вариант |
| `theme/aloha-hello/assets/` | Собственные CSS/JS, licensed fonts и отобранные бренд-media | Не весь экспорт и не `support.js`/React runtime |
| `design-preview/` или tooling fixtures area | Synthetic fixtures/schema и review pages | Только dev/demo, не «24 реальных объекта» |
| Local environment config | Минимальный compose или настройки выбранной среды | Secrets в env/ignored files; safe sample config только с placeholders |
| Tests/tooling | Нужные smoke/visual checks, package lock при появлении dependencies | Не ставить production JS framework только ради tooling |

Перед инициализацией Git сохранить существующий `snippets.php`, выяснить его назначение и не переносить в новую тему автоматически. WordPress core, `.env`, database dumps, uploads, caches, node_modules и временные render/extraction каталоги не должны попасть в Git. Решение о хранении крупных reference ZIP/PNG — явный Git baseline choice: оставить исходные файлы на диске; либо tracked reference directory, либо documented ignore/manifest. Не удалять материалы и не подключать Git LFS без необходимости. Push в M1 не требуется.

## 5. Tokens и визуальные acceptance anchors

| Система | Принимаемое основание |
| --- | --- |
| Container | Desktop1280 border-box/padding40 → usable1200; при 1440 contentx120. Mobile390/padding20 → usable350 |
| Palette | Mirage/Deep Sea Green/Wild Sand/Page/White/Orange из inventory; orange — основной CTA, контраст проверить по действительным размерам текста |
| Typography | Instrument Serif 400 + italic; Outfit UI weights400/500/600/700; body desktop18/1.6/mobile16/1.55. Font300 только если используется |
| Rhythm | Не одна универсальная section height: Home120, Area/Community110, Unit64, mobile44–56; fluid промежуточные widths |
| Components | Pill52; SVG black/white logos322:80; gallery4:3 cards; radii6/8/10/12/16 согласно роли |
| Desktop Home | Hero/4-column Standard/featured scroll/3areas/metrics/reviews/guide/team/CTA/owners/footer |
| Mobile Home | Header68; hero560; search overlap; compact featured2×2 до Standard; benefits2col; disclosure footer; один bottom bar |
| Added templates | Area720 hero +4homes row; Community split hero +5thumbs; Article640 hero +TOC/body/aside; точные значения в inventory§6 |

Полученные SVG можно использовать как исходный brand reference без перерисовки; production rights согласуются. JPG можно использовать в локальной demo-визуализации с пометкой «reference/sample», не приписывая их конкретному реальному объекту. Сохранять crop/focal point по PNG. Media count должен соответствовать реально включённым sample images, а не «36» из референса.

Не копировать device-specific inline CSS. Создать собственные semantic templates, reusable CSS и минимальный JS для поведения. На mobile сохранять смысловые отличия, а не CSS-reorder всей страницы, ломающий screen-reader порядок. Единая модель контента может иметь отдельные presentation layouts без двух дублирующих H1/страниц для assistive technology.

## 6. A / B / C: реализация и независимость темы

### A. Theme components

Header/footer/navigation, hero/editorial/CTA sections, area/community marketing cards, guide/archive/article layouts, TOC/FAQ styling, team/benefits/stats, mobile nav, typography и layout. Текст/изображения редактируются core Gutenberg в locked patterns или структурированных template slots. Нельзя хранить стратегически важный editorial content только в PHP markup.

### B. GuestyBridge components — owner в M2+, только mock projection на M1

Listing card/collection data, search/filters/applied state, calendar, guest/date validation, dynamic price state, gallery metadata, booking state/API statuses принадлежат GuestyBridge. В M1 создаётся **dev-only fixture projection/UI preview**, а не CPT/sync/REST/plugin. Реальный data provider подключается позже в те же slots; стили темы не должны требовать ложного API result.

Демо-маршруты можно держать под `/design-preview/catalog/` и `/design-preview/unit/`, явно сопоставляя их с будущими `/vacation-rentals/` и `/vacation-rentals/[slug]/`. Это рекомендуемый способ не создать конфликт WP Page ↔ будущий CPT archive. Другой вариант допускается только с описанным M2 route handover; не оставлять два owner одного URL.

### C. Shared contracts v1 — письменная основа перед M1.3

Ниже внутренние названия предлагаемого frontend view model, не Guesty response fields.

| Контракт | Обязательные поля / события | Invariants |
| --- | --- | --- |
| `SearchState` | `checkIn/checkOut` date-only; `adults/children/infants/pets`; `area/community/bedroomsMin/features/sort`; optional bounds/view; draft/applied/version | Один источник состояния для Home/Catalog/Unit, URL allowlist, даты в timezone объекта, без PII/token |
| `ListingCardView` | Stable local ID/sourceKind, name/url, area/community, capacity/bedrooms/bathrooms, ordered media, tagline, features, optional badge, price summary | `sourceKind=demo` на M1; null ≠0; bathrooms decimal; full/compact используют одну модель |
| `MediaView` | Local sample URL, dimensions/alt/category/focalPoint, rights/provenance marker | Только существующий файл; category выбирается явно, не из filename; screenshot alt не подтверждает географию |
| `PriceDisplay` | `kind=none/indicative/search_estimate/quote`; amount/currency/stay/nights/guest scope; timestamp/expiry; `isDemo` | На M1 любой amount синтетический и маркирован; no-charge-yet/15%/Instant исключены; неизвестное не становится 0 |
| `GuestPicker` | Value/count categories, capacity context; change/apply/cancel; inline error | Браузерная сумма не authoritative; в M1 только demo constraints; дети не молча превращаются во взрослых |
| `DatePicker` | Range, loading/unknown/unavailable/demo rules; range-change/apply/cancel | Незнакомая дата не гарантированно свободна; никакого remote calendar |
| `AsyncDisplayState` | idle/loading/ready/empty/partial/error/stale; message/retryability/requestVersion | Ответ предыдущей версии не перезаписывает новый applied state; error≠0 available |
| `BookingPreviewState` | no-dates/checking/demo-quote/unavailable/error/expired; modeLabel; preview action | Никогда confirmed/paid как реальное событие; Demo acknowledgement прямо говорит «запрос не отправлен» |
| `Collection/MapView` | Result IDs, selected ID, filter version, price kind, bounds/view | Один набор/цена на list и pin; mock map географически условна; нет ложного Google Maps attribution |
| Theme styling API | Namespaced `gb-*` root/slots, semantic CSS variables, optional presentation variant | Минимальные neutral styles принадлежат будущему plugin; theme overrides не меняют бизнес-состояние/required DOM semantics |

URL-модель сайта остаётся принятой в M0§10.1; старые `arrive/depart/guest` имеют documented mapping. State guest-specific quote не сохраняется в share URL; Demo serialization не должна учить frontend доверять browser amount. Поиск с датами не должен оставлять непомеченное `from/night`: demo воспроизводит исправленную M0-модель, это **осознанное отличие от PNG Catalog**, которое фиксируется в QA log.

На подключении M2 один plugin controller становится owner picker/search state; тема сохраняет layout/skins и убирает fixture provider. Theme не регистрирует `gb_listing` и не начинает синхронизацию. В M2 отдельно проверяется работа GuestyBridge под стандартной WP theme, включая нейтральные blocks/cards/booking UI.

## 7. Demo safety и fixtures

Предлагаемый тестовый набор: **24 синтетических объекта `demo-001`…`demo-024`**, имена «Demo Home 01»…; 3 условных area assignments для UI и optional Kona edge case, не выдаваемые за реальное распределение inventory. Имена communities и counts в демо тоже sample. Формат ID не имитирует настоящие Guesty credentials/listing IDs.

Набор включает: длинное название, nullable fact/photo/coords, 3.5 baths, 4+ bedrooms, room с sofa bed/shared space, unsupported amenity, no reviews, несколько price states, missing video/category. Числа/цены/отзывы синтетические; не копировать гостевые PII из старого сайта.

Обязательные ограничения:

1. Видимая общая надпись «Demo — sample properties and prices, not bookable» и локальный Demo marker на карточке/booking UI. На одной общей шапке не полагаться: widget может попасть на screenshot отдельно.
2. Search фильтрует local fixtures. Quote/loading/error/expiry показываются через deterministic local scenarios. Никаких Guesty hosts, payment SDK, billing keys или auth endpoint.
3. Booking button открывает **preview**, а не имитирует успешную reservation. Завершение: «Demo preview only. No booking or request was sent.» Без confirmation code, receipt, paid badge и настоящего hold.
4. Forms не отправляют email/CRM/remote requests. Local preview validation разрешена; после submit — честное сообщение «Demo form — message not sent». Не хранить реальные контакты посетителей в fixtures/analytics.
5. Call/email controls в исходниках содержат реальные данные: на M1 review не совершать звонок/отправку; использовать test/disabled action для demo-safe сценариев. В production контакты вернутся только после проверки клиента.
6. Play/tour/map placeholders помечены. Не подставлять arbitrary video URL и не выдавать статичную mock map за реальную интерактивную доступность.
7. Все preview pages локальные/noindex; не публиковать screenshot claims 15%, 4.95/1500+, 24/7, estimated rental yield как подтверждённые результаты. Для визуальной формы использовать нейтральный sample copy.

## 8. Адаптивность и дополнительные решения

Источник устанавливает только final1440 и Home390, не breakpoints. Предложение для M1: mobile<768, tablet768–1023, desktop≥1024; map/sidebar может включаться позже, по ширине содержимого. QA ширины **320/390/768/1024/1440**, landscape и zoom200%; это тестовая матрица, не восстановленные из HTML авторские правила.

Catalog mobile: single list + compact summary + filter/sort/map controls; filter sheet Apply/Clear, fullscreen demo map и возврат к прежнему scroll/result set. Unit mobile: swipe gallery, compact facts, поток контента, один bottom booking-preview bar и sheet. Area/community: hero → facts → homes → editorial gallery/sections; grids4→2→1 по реальному содержимому. Article: TOC становится disclosure, quick facts идут перед/после intro по согласованному порядку, body на всю ширину, tables с локальным scrolling. Эти варианты **предложения**, не approved PNG.

Открытые решения до приёмки:

| Решение | Можно начать без него | Когда требуется |
| --- | --- | --- |
| Invest отдельным nav item, Results в mobile footer | Да, общий menu data draft | M1.2 final |
| Mobile menu/picker/modal states | Tokens/shell static | До интерактивной приёмки M1.2–M1.5 |
| Mobile catalog/Unit и article/tablet layouts | Desktop implementation | До M1.7 соответствующих templates |
| Точный map/no-dates/total UI | Card mock shape | До M1.4 approval; corrected M0 model обязателен |
| Font delivery/license | Runtime preflight/структура | M1.1 visual baseline |
| Реальные images/video/FAQ/marketing pages | Skeleton/demo | До content acceptance/production, не до foundation |
| Реальные 24/23, Kona, source claims | Synthetic fixtures | M3/M5; не блокирует M1 |
| Checkout A/B/C, Guesty access | Весь M1 | M3/M4; M1 не обещает выбранный flow |

## 9. Проверка и критерии завершения M1

Visual QA сравнивает собственную theme implementation с исходными PNG при 1440 и 390, не с экспортом на случайной высоте viewport. Проверять anchors: header/logo/container, hero crop/heading/search, spacing/section order, card aspect ratios/gaps/type, footer, sheets. Состояния native inputs и sticky elements сравнивать отдельно обычными viewport screenshots на top/middle/bottom, чтобы full-page capture не маскировал перекрытие.

Базовый acceptance log различает: **matches reference**, **approved change** (например corrected total/demo label/accessibility), **proposal awaiting approval**, **content missing**, **defect**. Замена 15% или live booking claims нейтральным demo copy не должна случайно считаться недоделанной pixel-copy работой.

| Проверка | Доказательство готовности |
| --- | --- |
| Source integrity | SHA-256 originals неизменны; extracted files/runtime остаются вне theme/deploy package |
| SSR/editor | H1/description/facts/все 24demo cards в initial HTML; тексты/фото меняются core editor; layout не разваливается при длинном тексте |
| Responsive | Нет page-wide horizontal overflow; Home390 сохраняет 2×2 и порядок; page/table local scroll объяснён; контент не скрыт за mobile bar |
| Accessibility | Keyboard-only nav/date/guest/disclosure/modal; labels и error focus; visible focus; reduced motion; zoom200%; contrast reviewed |
| UI state | Search draft/applied/back/clear, stale response, empty/partial/error, no-data/expired booking preview; один owner state |
| Safety | Network trace/код не обращаются к Guesty/payment/submission services; нет real reservation/payment success; forms не отправляют данные |
| Performance | Измеренные lab LCP/CLS/interaction и asset budgets из M0; изображения responsive/lazy нижеfold; только нужные scripts/fonts; field p75 не заявляется без трафика |
| Neutral handoff | Перечень fixture adapter removal и styling hooks для M2; тема не содержит Guesty business clients или source-owned CPT |
| Scope | Список реально принятых templates/states и отложенных marketing/mobile/content items; no “all designs finished” при открытых blocking items |

В будущем использовать только meaningful smoke/visual сценарии, связанные с этими требованиями; не писать отдельный unit test на каждый CSS token. Нагрузочные/API/платёжные tests остаются M3/M4. Установку testing dependencies включать в реальное задание M1, не выполнять в рамках документации.

M1 завершён, когда принятый template/state scope работает локально на demo, известные исключения перечислены, источники сохранены и клиент может редактировать контент. **Готовность M1 не означает готовность живых бронирований или запуска сайта.**

## 10. Первый небольшой этап для отдельного задания Codex

Рекомендуется сначала заказать **только M1.0**, чтобы получить проверяемый environment baseline без одновременной сборки страниц.

Предлагаемая формулировка следующего задания:

> Выполни M1.0 по docs/m1-theme-implementation-plan.md: проверь имеющиеся локальные инструменты и Docker, создай минимальную изолированную WordPress+DB среду и Git baseline с README start/stop/reset и ignore policy. Сохрани макеты и snippets.php. Не реализовывай тему/GuestyBridge, не подключай Guesty, формы и платежи, не меняй production, DNS и hosting, не выполняй deployment/push. В отчёте дай локальный URL, выбранные версии, способ запуска/остановки, состав отслеживаемых файлов и результаты smoke check.

Следующий пакет после этого — **M1.1 tokens/fonts + M1.2 shell proof**, без главной и динамического каталога в том же изменении. Так можно принять сетку, логотипы, шрифты и редактор до размножения ошибок по всем шаблонам. Текущее задание заканчивается этими документами; реализация начнётся только по отдельному запросу.
