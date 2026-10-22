# ALOHA — M1.2 Header, Footer & Responsive Navigation

Дата: **22.10.2026, Europe/Istanbul**. Baseline: M1.0 `7d5b216`, M1.1 `56be602`. Реализован только общий shell темы **Aloha Hello 0.2.0**. Главная, каталог, Unit, Area/Community, GuestyBridge, CPT, API clients и booking/payment flows не создавались.

## Просмотр и подключение

**[Локальный shell preview](http://127.0.0.1:8088/?aloha-shell-preview=1)** — настоящие Header/Footer template parts с длинным нейтральным содержимым, полем для focus proof и disabled control. Это не WordPress Page и не главная. Доступен только в environment `local`, через существующий отдельный dev mount/константу M1.1; no-cache, noindex/nofollow и `X-Robots-Tag` включены. Исходный [showcase M1.1](http://127.0.0.1:8088/?aloha-style-showcase=1) сохранён; shell CSS/JS там не загружаются.

`templates/index.html` подключает обе общие части. Поэтому shell работает и на стандартной Sample Page, без создания новых страниц. Единственные семантические `<header>` и `<footer>` создаёт Core Template Part по metadata `theme.json`; внутри частей используются Groups, без вложенных повторных landmarks.

Исходники продолжают подключаться read-only bind mount из `theme/aloha-hello/`. Docker volumes/core, production и глобальные настройки Mac не менялись. На существующей установке после обновления темы выполнить:

```bash
./bin/local up
./bin/local wp aloha-shell install
```

На свежей активации меню создаёт `after_switch_theme`. Команда `install` создаёт только отсутствующие/удалённые Navigation posts, сохраняет существующие правки и обновляет theme pattern cache. Ничего не копируется в Docker volume вручную.

## Структура и редактирование

| Исходник | Назначение |
| --- | --- |
| `parts/header.html` | Подключает header pattern |
| `patterns/site-header.php` | Native Group, Core Navigation с конкретным `ref`, общие brand/action slots |
| `parts/footer.html` | Footer Group, общий renderer групп/контактов, bottom bar и demo dialog |
| `inc/destinations.json` | Единый исходный реестр canonical URLs, этапов и начальной структуры меню |
| `inc/shell.php` | Идемпотентная установка Navigation posts, server rendering и presentation hooks |
| `inc/shell-settings.php` | Settings API для единого источника контактов |
| `assets/css/shell.css` | Локальные стили shell; глобальные design tokens M1.1 не меняет |
| `assets/js/shell.js` | Sticky state, Footer responsiveness, Back integration, demo dialog, bottom bar reserve/focus |
| `assets/js/shell-editor.js` | Только editor: небольшой ServerSideRender preview для theme slot block |
| `dev/shell-bootstrap.php`, `dev/shell-preview.php` | Изолированный local preview настоящих template parts |
| `scripts/shell-smoke.cjs` | Browser acceptance настоящего WordPress |

**Меню:** Appearance → Editor → Navigation: `Aloha Primary`, `Aloha Footer`, `Aloha Legal`. В текущей DB IDs — 16/17/18; в source IDs не зашиты. Они хранятся в `aloha_shell_menus` и будут иными на новой базе. Header pattern разрешает ref при загрузке, поэтому Core Navigation узнаётся и редактируется в Site Editor. Правки header/footer template parts также сохраняются штатно в WP; DB overrides имеют обычный приоритет над theme files.

Primary — **один Navigation post и один DOM nav** для desktop/mobile. Footer — один Navigation post для desktop columns и mobile disclosures. Legal — один отдельный плоский Navigation post. Не надо редактировать две мобильные/desktop копии. Footer поддерживает начальные верхние Submenu-группы с обычными Navigation Links внутри: добавляйте и переставляйте ссылки в нужной группе, сохраняйте эту простую структуру.

Начальная структура создаётся из JSON один раз. После этого редакторские изменения labels/URLs/order находятся в WordPress, а `install` их не перезаписывает; реестр является исходным handoff, не скрытым принудительным override каждого сохранения. Новые общие destinations нужно отражать в реестре и согласованно назначать в соответствующих меню. Редактирование Navigation/контактов входит в обычный backup базы.

Для общего оформления использованы native Groups, Core Navigation/Navigation Links/Submenu и **один** небольшой динамический block type `aloha/shell-slot`. Его варианты объединяют брендовые ресурсы, контакты, footer projection и demo utilities. Это presentation helper, без бизнес-логики. Он нужен для единого источника контактов/URL-реестра и семантических `<details>` в footer. React используется только уже имеющимися средствами WordPress в редакторе, собственной React frontend-сборки нет.

**Контакты:** Settings → Aloha shell (`/wp-admin/options-general.php?page=aloha-shell`). Phone/email проходят sanitization, запись защищена штатными capability/nonce Settings API. До явной отметки подтверждения значения не публикуются; номер и email из макета не перенесены как проверенные. Header, Footer и Call используют один option `aloha_shell_contact`. В исходном состоянии поля пусты, Call открывает demo message. Book Now остаётся demo-кнопкой. Подтверждённых social URLs и юридической лицензии нет, поэтому social links и RB-24433 не выводятся.

## Иерархия и URL

Изучены M0 §4.1, D00 `00-site-structure.html`, Design Inventory/M1 plan и финальные Home desktop/mobile HTML/PNG. Дополнительно сверены все шесть desktop PNG headers и Header/Footer всех семи final HTML: Catalog, Unit, Area, Community, Article используют ту же композицию. Contact sheet сохранён в evidence. Разница источников: в Home footer есть Step-Free Stays, в остальных desktop exports этой ссылки нет; mobile Home теряет Results & Reports.

Предварительное решение для **Invest**: отдельная верхняя ссылка, как в финальном PNG, на `/homeowners/invest/`; не добавлялись придуманные dropdown categories. В Primary Homeowners не дублируется второй пункт Invest, в Footer Homeowners он остаётся. Это согласованная для реализации M1.2 трактовка, **не клиентское утверждение IA**.

| Primary | Содержимое / destinations |
| --- | --- |
| Vacation Rentals | All Rentals `/vacation-rentals/`; три общих Area URL |
| Big Island | Три Area; Communities `/big-island/communities/`; Big Island Guide `/big-island/guide/` |
| Homeowners | Property Management `/homeowners/`; Free Rental Estimate `/homeowners/estimate/`; Design & Renovation `/homeowners/design-renovation/`; Results & Reports `/homeowners/results/`; Owner FAQ `/homeowners/faq/` |
| Invest | `/homeowners/invest/`, обычная ссылка |
| About | Our Team `/about/`; Guest Info `/guest-info/`; Contact `/contact/` |

Общие Area destinations: `/big-island/mauna-lani/`, `/big-island/waikoloa-beach-resort/`, `/big-island/waikoloa-village/`. Vacation Rentals и Big Island ссылаются на одни canonical URLs; дубли Page не создаются. Родитель Big Island сохраняет `/big-island/` как документированный pending hub/redirect decision, без пустой страницы.

Footer groups: Vacation Rentals (All + три Area + Step-Free Stays); Big Island (три Area + Communities + Guide); Homeowners (Management + Invest + Estimate + Design + **Results & Reports** + FAQ); About (Team + Guest Info + Contact + Privacy). Legal: `/privacy-policy/`, `/terms/`, `/rental-agreement/`. Step-Free Stays использует предложенный M0 URL `/vacation-rentals/?feature=step-free`, без отдельной landing.

Полный реестр, включая Guide rubrics/Invest child URLs будущих этапов, находится в `inc/destinations.json`. Он не означает, что все эти URL должны появиться в главном меню. **`/vacation-rentals/` зарезервирован для будущего GuestyBridge archive**, Page там не создана. Marketing/editorial цели относятся к M1.3–M1.6 либо отдельному согласованному scope; legal copy, Big Island/invest hubs остаются content/IA decisions.

Ссылки хранят настоящие canonical href, не `#`. На local shell preview отсутствующие destinations помечены и перехватываются для dialog с URL/этапом; навигация не создаёт пустых страниц и не показывает ложное подтверждение. За пределами shell preview это обычные будущие URL, которые до реализации могут отдавать 404. WordPress speculative prefetch отключён **только** для preview, чтобы не запрашивать эти маршруты заранее.

## Поведение и адаптивность

- При 1440: outer container 1280 border-box, поля 40, usable 1200, logo x=120; header **92px**, logo **56px**. Desktop включается от **1280px**, чтобы все пункты, отдельные disclosure controls и действия помещались. До 1279px используется компактный header с mobile Navigation overlay; это адаптация для отсутствующих tablet artboards.
- При 390/320: header **68px**, logo **40px / 161px wide**, поля **20px**, Call/Menu controls **44×44px**. При 768/1024 поля 40px, компактный header. Mobile bottom bar включается до 767px.
- Header sticky. При scroll >8px появляется полупрозрачный белый фон с `backdrop-filter: blur(16px)`. Blur применён к pseudo-element, чтобы не ограничивать fixed overlay containing block. Sticky учитывает admin-bar height.
- Desktop dropdown открывается отдельной Core-кнопкой (click / Enter / Space), родитель остаётся обычной ссылкой. Tab идёт по ссылкам, Escape закрывает popup и возвращает focus. Pointer hover даёт визуальный feedback; hover-only открытие отключено, чтобы popup не оставался открытым после Escape под указателем. Текущий URL получает `aria-current` и active styling.
- Mobile сохраняет стандартную **раскрытую иерархию Core Navigation**, с одним scrollable overlay, а не вторую самописную accordion-навигацию. Core даёт dialog/aria-modal, focus trap, Escape и focus return; небольшое расширение добавляет Back через временный history entry и aria-expanded/controls trigger. Back закрывает меню, не уводит со страницы. Close остаётся доступным при прокрутке. Фоновый document scroll заблокирован Core, bottom bar скрыт.
- Footer: desktop 5 колонок (identity + 4 группы), padding 88px/40px, gap 40, legal border + 72px gap/24px top. На 768–1279 identity идёт над четырьмя группами. До 767 — native Details/Summary с padding 48px/44px; Results & Reports остаётся в Homeowners. На desktop группы открыты, summaries исключены из Tab order, ссылки доступны. При отключённом JS исходные Details открыты, links не пропадают.
- Bottom bar: min button height 52px, высота по умолчанию **76px** плюс `env(safe-area-inset-bottom)`, gap 10, поля 16px. ResizeObserver резервирует фактическую высоту панели при увеличении текста, а scroll-padding позволяет довести footer links выше неё. При focus в input/select/textarea/contenteditable панель скрывается; при menu overlay тоже. Специального Unit booking mode пока нет.
- Demo dialog — native HTML dialog с Escape, focus return и заблокированным background scroll. Call при пустом/неподтверждённом телефоне ничего не набирает; Book Now объясняет недоступность каталога/booking и отсутствие бронирования/платежа. Подмена бронирования success-screen отсутствует.
- UI type использует rem; при 200% текста допускается увеличение/перенос header rows вместо обрезания. Анимация фона минимальна и выключена при reduced motion.

## Отличия от PNG

1. Sticky/blur добавлены по **новому прямому заданию**, хотя M0/M1 plan ранее предполагали non-sticky desktop. Это актуальное изменение scope.
2. Primary CTA сохраняет Mirage text на Orange из M1.1 для contrast; белый текст PNG не возвращён.
3. Dropdown controls занимают место между пунктами: labels начинаются немного левее Home PNG; отдельная contact icon и неподтверждённые контакты меняют правую группу. При 1440 x/размер логотипа, высота header и правая граница CTA совпадают с контейнером. Полный pixel-perfect shell не заявляется.
4. В mobile правое поле 20px следует заданию и системе контейнеров; экспорт использует справа 16px. Header logo и высота совпадают.
5. Footer/Homeowners дополнен Results & Reports на mobile. Step-Free Stays сохранён единообразно в Footer всех будущих страниц по Home/M0, несмотря на его отсутствие в других exports.
6. Не выводятся неподтверждённые phone/email, social icons/links и real-estate license. Поэтому identity column и общая высота footer отличаются от заполненного PNG; нет искусственных пустых spacers ради высоты.
7. Mobile overlay/dropdown открытые состояния и tablet widths отсутствуют в PNG; реализованы на Native Navigation как проверяемая адаптация. Отдельный Invest — предварительное IA-решение до согласования.

Глобальные color/font/spacing tokens M1.1 не изменены; в theme.json добавлена только metadata templateParts. Оригинальные SVG/WOFF2 не менялись.

## Приёмка

Chromium, DPR1, настоящие viewport screenshots 320/390/768/1024/1440, top/scroll/footer/menu, desktop dropdown и demo dialog. Evidence только в **`.local/evidence/m1-2/`**, не в `макеты/`.

| Проверка | Результат |
| --- | --- |
| Пять ширин | PASS: page scrollWidth равен viewport, один header/footer/Main nav; header 68/92, logo 40/56, x=20/40/120 |
| Sticky / blur | PASS: header y=0 после scroll; pseudo-element blur включён, overlay не обрезан |
| Desktop | PASS: mouse/keyboard disclosure, aria-expanded true/false, Tab, Escape, parent canonical link + focus return |
| Mobile overlay | PASS на 320/390/768/1024: dialog/aria-modal, trap last→first, Escape, Back, возврат фокуса, background scroll lock |
| Nested demo | PASS: destination dialog открывается из overlay и возвращает в то же меню |
| Footer | PASS: desktop columns, mobile keyboard disclosures, Results & Reports внутри Homeowners |
| Bottom bar | PASS: 76px reserve, footer links доводятся выше панели; скрытие при field focus / overlay |
| 200% текста | PASS: 320 и 1440 без horizontal overflow; при 320 также открытый overlay и footer link выше выросшей bottom bar, UI scales with rem |
| Reduced motion | PASS: transition 0s при reduce |
| Site Editor | PASS: Header/Footer parts открываются; blocks валидны, ref указывает на Primary, server previews загружаются |
| Редактирование | PASS: authenticated WordPress Navigation entity save + настоящая Settings UI; временные markers видны в header/footer source; unverified email скрыт |
| Stop/start | PASS: Navigation/contacts пережили restart; после проверки исходные values/menus восстановлены, QA markers отсутствуют |
| Стандартная Page | PASS: Sample Page получает общий shell, без дублей и новых Page records |
| JS / network | PASS: page/console errors и failed requests отсутствуют; внешних запросов нет; Guesty/booking/payment запросов нет |
| PHP / schema | PHP lint PASS, theme.json official schema PASS, новых theme warnings/errors в debug log нет |
| M1.0 regression | `./bin/local check` PASS, оба сервиса healthy, volumes сохранены |
| M1.1 regression | `scripts/theme-smoke.cjs` PASS: прежний showcase, fonts, геометрия, keyboard, fallback и text zoom |
| References | `scripts/check-references.py`: **21/21** исходный файл совпадает по SHA-256 |

Ключевые evidence: `frontend-chromium.json`, `editor-final.json`, `admin.json`, `persistence.json`, `php-log.txt`, `shell-{width}-*.png`, `desktop-dropdown.png`, `text-zoom-200-*.png`, `site-editor-*.png`, `reference-headers.png`. Тестовые изменения не остались в меню/контактах. Native Navigation posts 16/17/18 — реальные сохраняемые данные shell, не demo property records.

Повторяемая проверка:

```bash
PLAYWRIGHT_MODULE=/path/to/node_modules/playwright node scripts/shell-smoke.cjs
PLAYWRIGHT_MODULE=/path/to/node_modules/playwright node scripts/theme-smoke.cjs
./bin/local check
python3 scripts/check-references.py
```

Скрипт поддерживает `SHELL_BROWSER=firefox` / `webkit`, но соответствующие binaries в текущем bundled Playwright **отсутствуют**. Они не скачивались и глобальная система не менялась. Safari/iPhone, реальный safe area и browser zoom остаются отдельной проверкой; здесь 200% относится к text size. Полный WCAG audit и полевые Core Web Vitals не заявляются.

Собственный frontend JS около 4KB (около 1.5KB gzip), CSS около 10KB (около 2.4KB gzip). Дополнительно работают стандартные WordPress Core Navigation/Interactivity и emoji; новых npm/frontend framework dependencies нет. Editor script около 0.6KB и на frontend не загружается.

Существующие M1.0 warnings заблокированных WordPress.org update/translation checks сохраняются; они не связаны с темой. Исходные license notices/исследования не нормализовались ради whitespace.

## Git, ограничения и M1.3

M1.2 завершается отдельным локальным commit; SHA приводится в итоговом отчёте, рабочее дерево после commit чистое. Push/deployment M1.2 не выполняются. В тексте задания отдельно упомянута публикация M1.0/M1.1 и ниже запрещён push; этот вопрос вынесен на уточнение. Remote в локальном repo отсутствует; через подключённый GitHub найден пустой `web86/Aloha`, но он не назначен origin и ничего не опубликовано.

Перед M1.3: согласовать provisional Invest/menu destinations, проверить/подтвердить контакты и social URLs, продолжить согласованный shared UI contract. Использовать эти части в Home, сохранив разный desktop/mobile порядок секций. Реализовать саму главную и demo featured collection только в отдельном этапе. Pending destinations/preview mode переводить на реальные маршруты по мере готовности, не занимая будущий CPT archive Page-объектом.

**Фундамент готов к M1.3; M1.3 не начат.** Production site/DNS/hosting, Guesty, платежи, исходные референсы и глобальные настройки Mac не затрагивались.
