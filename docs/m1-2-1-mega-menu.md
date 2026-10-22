# ALOHA — M1.2.1 Desktop Mega Menu

Дата: 22.10.2026, Europe/Istanbul. Тема Aloha Hello **0.2.1**. Этап ограничен общей навигацией; главная M1.3, страницы, GuestyBridge и production не изменялись.

## Просмотр

[Локальный shell preview](http://127.0.0.1:8088/?aloha-shell-preview=1) — настоящие Header/Footer template parts, local-only/noindex, без создания Page. В этом preview будущие URL открывают demo-dialog; на обычной [Sample Page](http://127.0.0.1:8088/sample-page/) ссылки выполняют обычный переход. Не реализованные маршруты пока могут возвращать 404. `/vacation-rentals/` остаётся зарезервированным для GuestyBridge archive.

На существующей установке выполнить `./bin/local wp aloha-shell install`. Новая активация темы вызывает установку автоматически. Настройки и меню находятся в БД, поэтому их следует сохранять штатным backup, а не копировать в тему.

## Архитектура и совместимость с Core

Используется прежний **один** `core/navigation` с `ref` на `Aloha Primary` (`aloha_shell_menus.primary`). Один DOM nav и одни дочерние ссылки обслуживают desktop/mobile. Footer/Legal сохраняют свои Navigation posts и прежние renderers.

Core формирует родительские anchors, disclosure buttons, дочерние links, их label/href/target/rel и responsive overlay. Фильтр `render_block_core/navigation-submenu` добавляет только необязательный редакционный `<li>` в существующий `<ul>`. Это валидная списочная структура, без второй копии навигации. CTA копирует актуальный parent anchor и меняет только подпись и presentation class; отдельного поля URL нет.

После Core rendering фильтр **только для `aloha-primary-nav`** удаляет Interactivity API directives с submenu LI/button/UL. Поэтому Core и Aloha не конкурируют за раскрытие. Directives самого responsive dialog, open/close buttons и блокировка фоновой прокрутки остаются Core. В editor эти frontend directives не заменяются. Значения `showSubmenuIcon: true` и `submenuVisibility: hover` в Header pattern сохраняют настоящую родительскую ссылку и Core disclosure control.

`shell.js` управляет единым состоянием подменю и добавляет классы/ARIA/inert. Desktop показывает тот же UL как широкую панель. JS перемещает существующие дочерние LI в один вложенный list wrapper, не клонируя ссылки: это позволяет независимо выровнять компактную сетку ссылок по верху даже при очень длинном editorial content. Mobile показывает его как компактную вложенную иерархию; редакционный LI скрыт. Новые обычные submenu без editorial key также получают раскрытие, но без редакционного блока. При добавлении новых пунктов сохраняйте Core Navigation Links/Submenus и настройку родительских ссылок.

Core кеширует крайние focusable elements при открытии overlay. Поскольку раскрытие секций меняет их состав, Aloha перехватывает Tab **только в открытом mobile overlay** и вычисляет актуальные видимые границы. Внутренний Tab-порядок остаётся нативным. Escape/Back/открытие/закрытие/scroll lock и возврат к кнопке меню продолжают использовать Core. Desktop не имеет trap или modal roles.

Если JS недоступен, CSS раскрывает дочерние ссылки как простую иерархию; интерактивный Core mobile overlay, как и раньше, требует JS.

## Настройки и связь с пунктами

Settings API option `aloha_shell_mega_menu`:

```text
rentals | island | homeowners | about
    heading: string
    description: string
    image_id: integer (attachment ID, 0 = none)
    cta_label: string
```

В option нет дочерних ссылок, parent URL, порядковых позиций или Navigation post IDs. Defaults создаются через `add_option` только при отсутствии option. Очистка полей остаётся очисткой: defaults не подставляются заново при каждом render/install.

Связь хранится в стандартном `className` соответствующего **Navigation Submenu block**:

| Раздел | Сохраняемый класс |
| --- | --- |
| Vacation Rentals | `aloha-mega-rentals` |
| Big Island | `aloha-mega-island` |
| Homeowners | `aloha-mega-homeowners` |
| About | `aloha-mega-about` |

Это поддерживаемое Core поле **Advanced → Additional CSS Class(es)**, а не вычисление по URL, названию или `nth-child`. При редактировании сохраняйте этот класс. Если удалили и создали родителя заново, назначьте нужный класс новому Submenu block. Каждый editorial key назначайте одному родителю. Копирование блока копирует и класс — у нового независимого раздела его нужно удалить. Invest остаётся обычным Navigation Link.

В реальном Site Editor проверены изменение label на `Island stays QA`, URL на `/qa-rentals/`, перестановка rentals после других пунктов, Save, повторное открытие editor и сохранение после stop/start. Класс сохранился, все 24 блока валидны, editorial text остался у rentals, CTA получил новый URL. После проверки исходные label/URL/order и тексты восстановлены.

Миграция версии 1 выполняется **один раз**: находит только однозначные исходные пары label+URL или уже назначенный ключ, дописывает класс и добавляет отсутствующий Step-Free Stays в rentals. Проверяется URL (relative/absolute) и label среди потомков; существующие пункты не перезаписываются и не сортируются. Повторный install не возвращает удалённый редактором пункт. Если исходный родитель уже переименован/перенаправлен до миграции, автоматическое угадывание пропускается: вручную назначьте класс и, при необходимости, добавьте Step-Free Stays в Site Editor. Исходный URL берётся из существующего `destinations.json`, а не нового списка ссылок. `wp_slash(serialize_blocks(...))` сохраняет специальные символы при записи через WordPress.

## Как редактировать

**Тексты и фотографии:** Settings → Aloha shell → Mega Menu. Для каждого из четырёх разделов доступны Heading, Description, CTA Label и Choose / replace image. Изображение выбирается/загружается штатной Media Library. Remove image очищает связь; затем нажмите Save Changes. Alt Text задаётся в Media Library. Heading/Description/CTA могут быть пустыми. При отсутствии всех editorial fields блок вообще не создаётся; сетка ссылок занимает панель без пустой колонки.

**Ссылки:** Appearance → Editor → Navigation → Aloha Primary. Здесь редактируются названия, href, порядок и дочерние элементы. Не вводите ссылки в Settings. Aloha Footer и Aloha Legal редактируются отдельно, как в M1.2.

Поля проходят sanitization и escaping. Стандартный `options.php` Settings API проверяет `manage_options` и nonce; сама settings page и Media enqueue также проверяют capability. Attachment ID проверяется как существующее изображение разрешённого MIME (JPEG/PNG/WebP/AVIF/GIF). Произвольные URLs и SVG в поле изображения не принимаются. Удалённый attachment не создаёт `<img>`.

Для frontend используется `wp_get_attachment_image('medium_large')`, стандартные alt, width/height, srcset, sizes, lazy loading, async decoding и low fetch priority. Изображение находится в инертном `<template>` до **первого открытия соответствующей desktop-панели**. Поэтому даже lazy preload всех скрытых панелей отсутствует; mobile изображения не запрашивает. После первого открытия изображение остаётся для повторного использования. Оригинальные фотографии из макетов не копировались в тему; для QA они импортировались только в локальную Media Library как reference samples. Начальные настройки поставляются без изображений.

## Мышь, touch и клавиатура

- Desktop от **1280px**: hover delay **110ms**, close delay **200ms**. Переключение отменяет старые timers. Панель под Header; прозрачный hit area соединяет родительский пункт с панелью. Переход курсора вниз не создаёт flicker. Hover на Invest закрывает предыдущее подменю.
- Обычный mouse click по родителю выполняет переход сразу. Cmd/Ctrl-click, Shift/Alt-click и middle-click не перехватываются. На local preview обычные будущие destinations сохраняют существующее demo-пояснение.
- Реальные `PointerEvent.pointerType` отличают mouse/touch/pen на любой ширине. Первое касание родителя с детьми раскрывает и устанавливает отдельный touch-armed state. Второе касание того же открытого родителя переходит по href. Другой родитель переключает секцию первым касанием; дети и Invest переходят сразу. Hover-open не считается первым касанием. Outside pointerdown закрывает меню; у открытого touch меню нет close timer. Смена viewport/закрытие/другой ввод сбрасывают arming.
- В mobile старое подменю не схлопывается на pointerdown/focusin нового родителя: иначе его положение меняется между pointerdown и click. Переключение выполняется в click после распознавания касания. Внешние касания по пустой области overlay закрывают раскрытую секцию.
- Родитель — настоящий anchor: **Enter переходит**, **ArrowDown / Alt+ArrowDown раскрывает** и переводит фокус на первую видимую ссылку. Есть отдельная доступная disclosure button: desktop она визуально скрыта и появляется при keyboard focus, mobile видна как компактный control. Tab/Shift+Tab следуют DOM-порядку. Ссылки каждой колонки идут сверху вниз.
- Escape закрывает подменю и возвращает focus родителю. В mobile следующий Escape закрывает overlay; Back закрывает весь overlay. Фокус внутри панели удерживает её открытой. Переключение на другой раздел обновляет обе ARIA state и inert скрытых панелей. Нет `role=menu/menuitem`.
- Native demo dialog сохраняет открытый submenu/overlay при временном переносе фокуса и возвращает его исходной ссылке после закрытия.

## Оформление и адаптивность

Сохранены токены M1.1, Outfit/Instrument Serif, desktop Header 92px, logo 56px, compact Header 68px/logo 40px, sticky translucent background и blur. При 200% текста Header может перенести строки и увеличить высоту. Panel top всегда равен фактическому низу Header, а доступная высота учитывает его положение и admin bar. Позиционирование absolute не сдвигает страницу; ни overflow hidden, ни blur-containing block над панелью не добавлены.

Panel width — до 1200px, с общими gutters; фон Page `#F3F6F6`, тонкая граница, мягкая тень, padding 36/40px. Справа одна колонка до 3 ссылок, две для 4–8, три от 9; слева только заполненное editorial content. Фото ограничено высотой 128px. Длинные тексты переносятся; max-height и локальный vertical scroll обеспечивают доступ к содержимому. Анимация opacity/translate **170ms**, отключена при reduced motion.

До **1279px** — Core responsive overlay, без широкой мегапанели. До **767px** сохранена нижняя Call / Book Now bar, скрытая при overlay/поле ввода. Footer/Legal/disclosures не изменены.

## Проверки и evidence

Итоговая матрица Chromium прошла: `frontend-chromium.json` содержит 7 ширин, 16 групп проверок, пустые errors/consoleErrors/external/failed. `visual-variants.json`, `admin.json`, `persistence.json` и `settings-check.txt` фиксируют остальные проверки. Все PNG/JSON/browser logs находятся в игнорируемом `.local/evidence/m1-2-1/`. Они не входят в публичный Git и тему. Финальные файлы: `closed-{1280,1440,1920}.png`, `open-{rentals,island,homeowners,about}.png`, `with-photo-{1280,1440,1920}.png`, `links-only-no-empty-editorial.png`, `long-description-scroll.png`, `long-description-scroll-bottom.png`, `sticky-scroll.png`, `admin-bar-sticky-photo.png`, `keyboard-focus.png`, `touch-state.png`, `mobile-overlay-{320,390,768,1024}.png`, `text-200-{320,1440}.png`, editor/settings/media screenshots. Диагностические captures сохранены отдельно по именам `debug`/`initial` и не являются финальными.

| Проверка | Результат |
| --- | --- |
| Desktop 1280/1440/1920 | PASS: Hover всех разделов, быстрое переключение, bridge, delayed close, parent/child clicks, Escape, keyboard, sticky, no overflow/layout shift |
| Touch/hybrid | PASS: Реальные Chromium touchscreen Pointer Events: first/second tap, другой родитель, дети/Invest, outside, hover→touch, touch→mouse, resize reset |
| Mobile 320/390/768/1024 | PASS: Overlay, двухэтапные касания, дети, динамические trap endpoints в обоих направлениях, Escape/Back, focus return, scroll lock, bottom bar |
| Доступность | PASS: ARIA controls/expanded, inert, видимый focus, ArrowDown/Enter, 200% текста на 320/1440, reduced motion |
| WordPress settings | PASS: Реальная форма: heading/description/CTA; Media Library upload/select/replace/remove; alt и responsive image output |
| Navigation | PASS: Реальные block editor actions + UI Save/reopen; className пережил rename/URL/reorder; все blocks валидны |
| Persistence | PASS: stop/start + повторный install не перезаписали QA изменения; после проверки исходные пользовательские значения восстановлены |
| PHP checks | PASS: Sanitization/malformed input, image type/deletion, пустые поля, escaping, targeted migration/deduplication, special characters, repeat install |
| Регрессии | PASS: M1.0 local health; M1.1 foundation smoke; Footer/Legal/contacts/Call/Book/dialog/field-focus сохранены |
| Source integrity | PASS: `scripts/check-references.py`: 21 исходный документ/reference соответствует SHA-256; snippets.php отсутствовал ещё до M1.0 |
| Дополнительные браузеры | На машине установлен только Playwright Chromium; Firefox/WebKit отсутствуют. Новые браузеры не устанавливались |

Повторяемый frontend check:

```bash
PLAYWRIGHT_MODULE=/path/to/node_modules/playwright node scripts/mega-menu-smoke.cjs
```

Settings/migration check выполняется **только локально** с временным read-only mount scripts в CLI container. Тест сохраняет и восстанавливает menu/settings/version в `finally`:

```bash
docker compose run --rm --no-deps -T --volume "$PWD/scripts:/aloha-tests:ro" cli eval-file /aloha-tests/mega-menu-settings-check.php
```

## Отличия от PNG и ограничения

Закрытый Header сохраняет геометрию M1.2 и чистые ссылки без стрелок; отступы между пунктами освобождены от видимых desktop disclosure icons. Открытая широкая панель и touch accordion — новый согласованный компонент, отсутствующий в PNG. Не подтверждённые контакты по-прежнему заменены безопасной подписью, CTA сохраняет Mirage text на Orange для контраста. Mobile правый gutter 20px и tablet adaptation остаются отклонениями M1.2. Pixel-perfect совпадение нового открытого состояния с отсутствующим PNG не заявляется.

Проверено browser automation, а не физическими touch-устройствами или реальным screen reader. Firefox/WebKit smoke не выполнены из-за отсутствия установленных binaries. При обновлении Core необходимо повторить проверки selectors/directives/overlay trap. Служебные классы доступны редактору в Advanced; их намеренное удаление разрывает связь, а повторный install не перезаписывает это решение. Нестандартные Navigation blocks и пользовательские DB overrides Header требуют отдельной проверки; тема сохраняет исходную Core конфигурацию parent links/disclosure buttons.

M1.2.1 подготовлен как завершённый отдельный этап. Следующий этап **M1.3 может начинаться только по отдельному заданию**.

## Изменённые исходники

`inc/mega-menu.php` — связь/upgrade/editorial renderer; `inc/shell.php` — подключение и install; `inc/shell-settings.php` — Settings API/Media; `inc/destinations.json` — исходный seed Step-Free Stays. `assets/js/shell.js` — взаимодействия и trap boundary extension; `assets/js/shell-settings.js` — Media Library controls; `assets/css/shell.css` — panels/mobile states/motion. Старый `scripts/shell-smoke.cjs` перенаправлен на актуальный mega-menu acceptance с сохранёнными shell regression checks; foundation smoke принимает `EVIDENCE_DIR` для отдельного evidence. `style.css` — версия 0.2.1. Header pattern, template structure и footer rendering сохранены. Local preview copy и smoke tooling обновлены; M1.3-контента нет.

Core reference: [Submenu block / Advanced fields](https://wordpress.org/documentation/article/submenu-block/), [Settings API](https://developer.wordpress.org/plugins/settings/using-settings-api/). Фактическое поведение дополнительно проверено по установленному Core 7.1.3 и в его Site Editor.
