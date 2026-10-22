# ALOHA — M1.2.2 Mega Menu UX Fixes & Dynamic Settings

22.10.2026, Europe/Istanbul. Исходная версия: M1.2.1 `6d4339c9d82679d58baf37ca51e8a58dabd598d4`. Тема **0.2.2**. Сохранены Core Navigation, один Primary post/DOM nav, существующий дизайн, Footer/Legal, контакты и мобильные utilities. Главная M1.3, GuestyBridge, Pages/CPT/таблицы и production не создавались и не изменялись.

[Локальный preview](http://127.0.0.1:8088/?aloha-shell-preview=1), [Settings → Aloha shell](http://127.0.0.1:8088/wp-admin/options-general.php?page=aloha-shell). В preview будущие URL по-прежнему открывают demo-dialog; на обычной Sample Page это настоящие href и пока возможны 404. Новые страницы ради тестовых ссылок не создавались.

## Hover: причины и исправления

В M1.2.1 геометрия и состояние раскрытия были недостаточно согласованы:

- Bridge заканчивался на нижней границе Header, а `translateY(6px)` сдвигал внешнюю панель во время появления. Таким образом, hit area двигалась отдельно от bridge.
- Закрытие отменялось по `pointerenter` родительского LI; отдельного контроля входа в саму широкую absolute-панель не было.
- Timer callback обращался к глобальному `active`, не проверяя, относится ли он всё ещё к покинутому разделу. Повторное событие leave могло заменить сохранённый timer ID.
- `visibility` также анимировалась. В **нативном Firefox 158.0.1** подтверждён переходный дефект: сразу после ArrowDown ARIA уже сообщала об открытии, но computed visibility ещё мешала переводу focus в панель. Фокус оставался на родителе; следующий Tab попадал на disclosure button. После удаления visibility transition ArrowDown переводит focus на CTA, следующий Tab — на All Rentals.

Исправления: внешняя панель имеет неизменную геометрию, `translateX(-50%)` используется только для горизонтального центрирования. Плавное появление **170ms** оставлено через opacity. Visibility/pointer-events переключаются вместе с состоянием, inert скрытых панелей сохранён. И LI, и сама panel отменяют закрытие при pointerenter. Pointerleave проверяет `relatedTarget`, затем планирует единственный timer **200ms**, привязанный к конкретному разделу; callback дополнительно проверяет hover и focus. Внутренний pointerover панели не участвует в логике переключения верхних разделов. Hover bridge сохранён; бренд и Header actions подняты над ним, чтобы прозрачная область не перехватывала нижние пиксели Call/Book/logo.

Важно различать доказательства: исходный медленный проход с паузами в свежем Firefox 158 сам по себе **не воспроизвёл стабильное исчезновение** из пользовательской сессии. Подтверждены дефекты геометрии/границ/timer ownership в коде и Firefox visibility/focus race. Исправлена эта общая причина рассогласования состояния и hit area; итоговые нативные траектории, паузы 650–750ms на панели/детях, выход и возврат до close delay прошли. Точная последовательность событий в прежней пользовательской сессии Firefox не записывалась и не выдаётся за воспроизведённую.

## Мигание при загрузке

Причина определённая: правило `nav.aloha-primary-nav:not(.aloha-nav-ready) .has-child > ul` принудительно делало все подменю видимыми до основного JS. Это был no-JS fallback, который одновременно работал при обычной медленной загрузке JS.

Теперь до `.aloha-nav-ready` дочерние UL имеют `display:none!important`. JS сначала создаёт структуру, назначает закрытые состояния/ARIA/inert и обработчики, затем добавляет ready. Header и родительские ссылки видны сразу. Desktop disclosure controls визуально скрыты уже до JS, поэтому их исчезновение не меняет ширину навигации.

Отдельный fallback находится в `<noscript><style>` из `wp_head`: только при отключённом JS тот же Core Navigation отображает полную вложенную иерархию обычными ссылками, без overlay controls/editorial media и без sticky огромного Header. Второго списка ссылок или отдельного хранилища нет. При обычном включённом JS эти стили не применяются, даже если загрузка скрипта задержана.

Проверены initial frames с JS, задержанным на 1100ms, CPU throttling 3×, отключённым HTTP cache, повторный reload после открытой панели, задержанный HTML и JS-disabled contexts. На всех семи ширинах ранние кадры показывают закрытые панели и неизменную высоту Header 68/92px. При выключенном JS все 18 дочерних ссылок доступны на 320/1440 и выполняют обычный переход.

## Динамические разделы и идентичность

`aloha_mega_sections()` читает текущий post `aloha_shell_menus.primary` и обходит **только верхнеуровневые** `core/navigation-submenu` с дочерними ссылками. Группы Settings API идут в порядке Navigation, их названия берутся из текущих labels. Обычный Invest и пустые submenu не создают editorial settings. JSON используется только для прежнего начального seed/legacy upgrade; runtime links и состав групп не дублируются в PHP/JS/option.

Существующие `aloha-mega-rentals`, `aloha-mega-island`, `aloha-mega-homeowners`, `aloha-mega-about` сохранены. Новый раздел автоматически получает `aloha-mega-section-<UUID>` в стандартном атрибуте блока `className`:

1. Небольшой editor-only `mega-menu-editor.js` назначает ID до сериализации Save. Это предотвращает смену server-generated ID при повторном сохранении новой секции в той же редакторской сессии.
2. `wp_insert_post_data` проверяет ID при сохранении **только Primary wp_navigation**, включая REST/API/CLI записи. Дубликат получает новый UUID; unrelated CSS classes сохраняются. При идентичных импортированных дубликатах первая запись сохраняет прежний ключ, следующие получают новые. Новой копии автоматически не передаются редакционные поля другого раздела.
3. На Settings screen/install можно подготовить ранее неидентифицированные блоки; при обычном frontend render записи в БД нет.

Переименование, новый URL и перестановка не меняют ID. При удалении раздела его option entry сохраняется. Восстановление Navigation block с прежним ID (включая ревизию) возвращает editorial content. Намеренное удаление самого ID из Advanced создаёт новую идентичность; для возврата прежней связи восстановите прежний блок/класс из ревизии. Вручную дописывать новый раздел в PHP не требуется.

## Option и обратная совместимость

Прежнее структурированное option **`aloha_shell_mega_menu`** сохраняется. Ключ — прежнее имя четырёх разделов или `section-<UUID>`:

```text
heading: string
description: string
image_id: attachment ID
cta_label: string
image_left: boolean (отсутствует/false по умолчанию)
```

Никакого сброса option или переноса existing image IDs не выполняется. Добавление checkbox не переписывает существующие настройки и не меняет их прежний вид автоматически. Sanitizer обновляет только отправленные активные группы и сохраняет orphan entries и отсутствующие в submission группы. Неизвестные произвольные submitted keys игнорируются. Malformed input сохраняет предыдущие значения. Settings API capability/nonce, sanitization/escaping, проверка image attachment/MIME и актуальный parent CTA URL сохранены.

Прежняя идемпотентная v1 migration остаётся для обновления с M1.2; она использует существующий seed, не угадывая владельца editorial data у изменённого родителя. После неё новые/неопознанные разделы получают независимые UUID. Повторный `aloha-shell install` не перезаписывает настройки/ссылки и не размножает идентификаторы.

## Show image on the left и оформление

Settings → Aloha shell → Mega Menu → нужный текущий раздел:

- Heading, Description и CTA Label работают как раньше; URL CTA редактируется только в Navigation.
- Choose / replace image и Remove image используют штатную Media Library и attachment ID. Alt редактируется в Media Library.
- **Show image on the left** выключен — фотография сверху; включён — ограниченная фото-колонка слева от текста. Нажмите Save Changes.

Слева используется grid: фотография шириной 96–124px, aspect ratio 4:5, `object-fit:cover`, скругление 6px. Текст имеет независимую высоту, картинка не растягивается вместе с описанием. При ширине редакционной области ≤320px container query возвращает фото над текстом. При отсутствии фото — обычный текст; при отсутствии текста соответствующий wrapper не создаётся. Исчезли ненужные верхние margins у первого заполненного текстового элемента.

Отложенное обнаружение `<template>`, lazy loading, srcset/sizes, async decoding и low fetch priority сохранены. Mobile не загружает редакционные фото. Media JS явно выбирает hidden attachment input, поэтому новый checkbox не перехватывает сохранение ID; выбор в Media frame сбрасывает предыдущую selection перед восстановлением текущего изображения.

Внешняя граница и разделитель теперь **`#16232a14`**, без тёмного глобального border token. Мягкая тень сохранена. У дочерних desktop-ссылок справа добавлена тонкая стрелка через CSS mask, без icon library, дополнительной кнопки или изменения доступного имени. Hover/focus смещает её на 3px; reduced motion отключает transition. Mobile декоративные стрелки не получает. Длинные labels переносятся, включая сетку из трёх колонок.

Desktop breakpoint остаётся **1280px**; до 1279 — Core overlay. Для обычного шестого/седьмого родителя CSS уменьшает gap до 16px, сохраняя исходное расположение пяти пунктов. Это лишь подсчёт элементов для layout; идентичность никогда не определяется позицией. Очень много длинных родителей может переносить строки Header, как при 200% текста, а не создавать horizontal overflow.

## Интеграционный тест WordPress и восстановление

В реальном Site Editor создан Experiences с двумя дочерними ссылками. До Save появился UUID; после Save/reopen он сохранился. Через Core duplicate создана копия: её ID отличался, Settings автоматически показал отдельную пустую группу. Копия удалена после проверки.

Настроены heading/description/CTA, выбрано существующее пользовательское фото, проверены left/top/remove/restore на frontend. Раздел переименован в Island Experiences QA, URL изменён на `/qa-experiences/`, порядок — второй. Заголовок/позиция Settings обновились, контент и CTA сохранили связь. После удаления этого родителя сохранены настройки всех четырёх прежних разделов с временными markers, затем их значения возвращены. Восстановление тестового блока с прежним ID вернуло тексты/изображение/checkbox.

Stop/start и повторный install сохранили тестовый раздел и все поля. Затем исходные **Navigation content и option восстановлены точно по текущему снимку пользователя**, включая выбранный до задания attachment ID; не подставлялись старые defaults M1.2.1. Контакты не менялись. Временные разделы и тестовый контент не оставлены в активном меню; настоящие settings не удалены.

## Браузерная приёмка

Evidence находится только в игнорируемой **`.local/evidence/m1-2-2/`**, не в теме/публичном Git.

| Проверка | Результат / evidence |
| --- | --- |
| Chromium 320/390/768/1024/1280/1440/1920 | PASS: прежняя полная M1.2.1 матрица; `regression/frontend-chromium.json` |
| Firefox 158.0.1, установленный локально | PASS: нативные mouse trajectories, child pause, close/reentry, ArrowDown/Tab/Escape, sticky; desktop 1280/1440/1920 и mobile 320/390/768/1024; `firefox.json` |
| Initial frames / slow JS+HTML / reload | PASS: семь ширин, ранние кадры закрыты, нет header/layout shift; `initial-frames.json`, `initial-before-js-*.png` |
| JavaScript отключён | PASS: дочерние href видимы и работают; `no-js-320.png`, `no-js-1440.png` |
| Firefox left-image layout | PASS: фото слева и переход наверх при узком container query; `firefox-image-layout.json` |
| Dynamic Settings / editor copy / image variants | PASS: `dynamic-state.json`, `admin-persistence.json`, `settings-*.png`, `experiences-*.png` |
| 13 links / длинные fields / фото слева | PASS: три колонки, bounded scroll, 200% текста при 1280/1440/1920; `layout-stress.json`, `layout-*.png` |
| Keyboard/assistive relevant states | PASS: native anchors, ARIA controls/expanded, inert, focus entry/return, mobile dynamic focus trap; Firefox focus screenshot |
| Touch/hybrid, modifiers/middle click | PASS: прежние реальные Chromium Pointer Events и href/new-tab tests |
| Reduced motion | PASS: transition отключён и у panel, и у новых стрелок |
| Admin bar и Header hit testing | PASS: настоящая Sample Page, sticky под 32px admin bar, клик по нижнему краю Book; `admin-bar.json` |
| PHP Settings/migration | PASS: malformed input, MIME/deleted attachment, escaping, empty fields, dynamic IDs/deduplication/orphans, повторный install; `settings-check.txt` |
| M1.0/M1.1 и референсы | PASS: local health; foundation fonts/layout/fallback/zoom; `check-references.py` — 21 исходный reference совпадает по SHA-256 |

Firefox запускался через встроенный WebDriver BiDi с отдельным временным профилем; пользовательская сессия/профиль не управлялись. Глобальные browser/driver зависимости не устанавливались. Tooling использует имеющийся Chromium и Node 22 WebSocket. [Документация Firefox BiDi](https://developer.mozilla.org/en-US/docs/Web/WebDriver/How_to/Create_BiDi_connection).

Повторяемые frontend checks (путь к Playwright — установленный локальный runtime):

```bash
PLAYWRIGHT_MODULE=/path/to/playwright EVIDENCE_DIR=.local/evidence/m1-2-2/regression node scripts/shell-smoke.cjs
PLAYWRIGHT_MODULE=/path/to/playwright node scripts/mega-menu-load-smoke.cjs
PLAYWRIGHT_MODULE=/path/to/playwright node scripts/mega-menu-layout-smoke.cjs
node scripts/mega-menu-firefox-smoke.cjs
```

Settings/migration check, только environment local; данные восстанавливаются в `finally`:

```bash
docker compose run --rm --no-deps -T --volume "$PWD/scripts:/aloha-tests:ro" cli eval-file /aloha-tests/mega-menu-settings-check.php
```

Stress layout — browser-only fixture из текущих Core links, введённая до shell initialization; она не создаёт отдельного списка в теме и не меняет WordPress. Реальное добавление/редактирование/копирование выполнялось отдельно в Site Editor.

## Ограничения и граница этапа

Реальные физические touch-устройства и полноценный screen-reader сеанс не проверялись; Pointer Events/keyboard/ARIA проверены браузерами. WebKit smoke не выполнялся. Исходный hover-сбой в пользовательском профиле не записан; исходная медленная траектория в свежем Firefox 158 была стабильна, поэтому нельзя утверждать идентичность с прежней сессией. Проверенные дефекты устранены и расширенная приёмка проходит.

Идентификатор является частью Navigation block: чтобы восстановить удалённый раздел вместе с контентом, восстановите сам блок/ревизию, а не создавайте совершенно новый пункт с тем же названием. При внешнем импорте идентичных дубликатов первый сохраняет старый ID. При будущих обновлениях Core необходимо повторять editor/overlay/directive tests.

Реализация завершена на **M1.2.2**. К M1.3 можно переходить отдельным заданием; автоматически он не начинается.


Изменены `inc/mega-menu.php`, `inc/shell-settings.php`, `assets/js/shell.js`, `assets/js/shell-settings.js`, `assets/css/shell.css`; добавлен editor-only `assets/js/mega-menu-editor.js`. Обновлены версия темы, local preview, README/исторический отчёт M1.2.1 и acceptance tooling. Header pattern, Footer/Legal renderers, global tokens, исходные макеты и навигационный seed не переписывались.
