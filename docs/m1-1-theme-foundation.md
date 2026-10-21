# ALOHA — M1.1 Theme Foundation & Design Tokens

Дата: **22.10.2026, Europe/Istanbul**. Основание: M1.0 `7d5b216`, принятые M0 / design inventory / M1 plan, финальные Home HTML и PNG desktop/mobile. Исходные исследования, ZIP и PNG сохранены без изменений. Этап ограничен M1.1; M1.2 не начат.

## Результат и структура

В локальном WordPress **7.1.3 / PHP 8.3.36** активирована собственная block theme **Aloha Hello 0.1.0**, slug `aloha-hello`. База — нативные Gutenberg/Core blocks, без frontend build pipeline и page builders.

```text
theme/aloha-hello/
  style.css                  Метаданные
  theme.json                 Presets, fontFace, global styles, theme.json v3
  functions.php              Подключение CSS, регистрация core block styles
  templates/index.html       Минимальный fallback Query / Post Content
  assets/css/foundation.css  Общие UI-стили и semantic aliases
  assets/css/editor.css      Поля post/page canvas, без дублирования Site Editor shell
  assets/fonts/              3 WOFF2, 2 полных OFL notices, README с provenance/hash
  assets/images/             2 исходных SVG и README с provenance/hash
dev/
  bootstrap.php              Только local: виртуальный showcase и noindex
  style-showcase.php         WordPress document shell / do_blocks()
  showcase-content.html      Сериализованные Core blocks и core/html examples
  style-showcase.css         Только раскладка showcase, без другой UI-системы
scripts/theme-smoke.cjs      Повторяемая frontend acceptance-проверка
```

`parts/` и `patterns/` пока не требуются. Главная, header/footer/nav, listing CPT, plugin, интеграции, booking и payment flows не создавались. Функциональные поля/pickers отсутствуют; поля showcase демонстрируют нативное поведение браузера и ничего не отправляют.

## Токены

### Цвет

| WordPress slug | Значение | Семантика / CSS alias |
| --- | --- | --- |
| `mirage` | `#16232A` | Основной текст, `--aloha-color-text` |
| `deep-sea-green` | `#075056` | Бренд, ссылки, focus, `--aloha-color-brand` |
| `wild-sand` | `#E4EEF0` | Мягкая поверхность, `--aloha-color-surface-soft` |
| `page` | `#F3F6F6` | Фон страницы, `--aloha-color-page` |
| `white` | `#FFFFFF` | Поверхность, `--aloha-color-surface` |
| `orange` | `#FF5B04` | Основное действие, `--aloha-color-accent` |

Все шесть утверждённых цветов сохранены. Дополнительные функциональные значения находятся в `settings.custom.color`, не засоряют редакторскую палитру: muted `#4B5A60`, border `#71858B`, disabled `#52656B`, actionHover `#F55600`. CSS aliases ссылаются на WordPress presets/custom variables; отдельной дублирующей палитры нет.

### Типографика

| Preset | Размер | Применение |
| --- | --- | --- |
| `eyebrow` | 13px | Служебные акценты, 500 / tracking .16em |
| `small` | 14px | Подсказки |
| `button` | 16px | Кнопки / UI, 600 |
| `body` | clamp 16–18px | Outfit 400; line-height 1.55 до 767px, далее 1.6 |
| `heading-1` | clamp 52–100px | Instrument Serif 400 / .98 / tracking −.01em |
| `heading-2` | clamp 38–60px | Instrument Serif 400 / 1.05 / tracking −.01em |
| `heading-3` | clamp 19–24px | Outfit 600 / 1.25 |
| `heading-4` | clamp 18–20px | Outfit 600 / 1.25 |
| `heading-5` / `heading-6` | 18 / 16px | Outfit 600 / 1.25 |

Fluid значения интерполируются между 390 и 1440px, с ограничениями сверху/снизу. Размеры используют rem и viewport units, не десятки несвязанных размеров. В theme.json выключена дополнительная автоматическая fluid-генерация WordPress, поскольку clamp уже задан явно.

### Геометрия, spacing и UI

`.aloha-container` — opt-in контейнер **1280px border-box**, поля fluid **20–40px**, 40px начиная с 768px. WordPress `contentSize` / `wideSize` — 1200px. Контейнер применяется в шаблоне и showcase; не накладывается второй раз на вложенные группы. Корневые отступы WordPress не добавляют вторые поля. В post/page canvas без шаблонной оболочки editor.css добавляет те же внутренние поля; Site Editor сохраняет геометрию шаблона.

Малые spacing presets: `xs` 8, `sm` 12, `md` 16, `lg` 24, `xl` 32, `2xl` 40px. Секционные presets:

| Preset / utility class | Mobile → desktop |
| --- | --- |
| `section` / `.aloha-section` | 48 → 100px |
| `home` / `.aloha-section--home` | 56 → 120px |
| `area` / `.aloha-section--area` | 52 → 110px |
| `unit` / `.aloha-section--unit` | 44 → 64px |

Это выбираемые пресеты для будущих секций, без универсального правила высоты всех страниц. Radius tokens: card 6px, field 8px, pill 999px. Кнопки min-height 52px; поля min-height 52px; checkbox/radio имеют label hit area 44px.

Core Button использует primary по умолчанию, зарегистрированы `Secondary` и `Text link`, стандартный Outline тоже поддержан. Core Group получает opt-in style `Card`; доступны `.aloha-card`, `.aloha-notice`, warning/error-варианты, field help/error и skip-link. Статус выражается текстом и оформлением, без функциональной логики статусов. Focus — зелёный outline 3px с белым внутренним кольцом; он виден на светлой и тёмной поверхности. Disabled native buttons исключены из Tab order; внешний вид disabled links демонстрируется элементом без `href`.

## Шрифты и assets

WOFF2 получены напрямую из официальной поставки Google Fonts, **без browser cache**. Instrument Serif 400 normal и italic — два статических файла; Outfit 400/500/600/700 обслуживает один variable-файл, зарегистрированный как `400 700`. В исходном Outfit доступна ось 100–900, тема использует только требуемый диапазон. Общий объём — **62952 bytes**. Все три лица используют `font-display: swap` через theme.json.

Лицензии обоих семейств — **SIL OFL 1.1**; полные оригинальные notices и copyright сохранены рядом с файлами. [README шрифтов](../theme/aloha-hello/assets/fonts/README.md) содержит точные Google Fonts / fonts.gstatic.com / upstream ссылки, размеры и SHA-256. Проверены WOFF2 контейнеры, cmap и variable axis с FontTools. Локальная тема не делает runtime-запросов к Google Fonts.

Fallback: Instrument Serif → Georgia / Times New Roman / serif; Outfit → system-ui / Apple / Segoe UI / sans-serif. Поставка Latin рассчитана на текущий английский сайт. Кириллица, extended Latin и отсутствующий U+02BB используют системные glyph fallbacks; перед многоязычностью или расширением Hawaiian copy следует проверить покрытие и лицензированные дополнительные файлы.

Оба логотипа скопированы byte-for-byte из Home ZIP по реестру: тёмный `15c3…058.svg`, светлый `67da…074.svg`. ViewBox 322×80. Проверены пути ZIP, размер, тип записи и XML; нет script, event handlers, foreignObject, entities, внешних href/URL/imports. Используются как `<img>`. Provenance и SHA-256 — [README assets](../theme/aloha-hello/assets/images/README.md). JPG не включены. Production-права на брендовые ресурсы должны быть подтверждены клиентом; их наличие не заменяет разрешение.

## Docker и showcase

Source root — `theme/aloha-hello/`. В **wordpress и cli** добавлен read-only bind mount:

```text
./theme/aloha-hello → /var/www/html/wp-content/themes/aloha-hello:ro
```

У wordpress есть отдельный `./dev → /var/www/aloha-dev:ro`. Он находится вне web root и не входит в дистрибутив темы. CLI не монтирует dev-helper. Исходники редактируются на Mac; копирование кода в volume не нужно. Именованные volumes M1.0 сохранены. `./bin/local up` применяет mounts, тема активирована через `./bin/local wp theme activate aloha-hello`.

**Showcase:** [http://127.0.0.1:8088/?aloha-style-showcase=1](http://127.0.0.1:8088/?aloha-style-showcase=1).

Виртуальная страница использует `wp_head()`, `wp_footer()`, `do_blocks()`, реальные core styles, theme.json и foundation.css. Собственный showcase CSS задаёт только раскладку образцов. В базе нет showcase Page, публичной навигации тоже нет. Выдаются no-cache и `X-Robots-Tag: noindex, nofollow, noarchive`, дополнительно wp_robots noindex/nofollow.

Helper загружается только при `wp_get_environment_type() === 'local'`, наличии config-константы `ALOHA_DEV_SHOWCASE_BOOTSTRAP` и доступного файла. Сам bootstrap повторяет environment guard. Чтобы убрать showcase локально, удалить эту константу из Compose и выполнить `./bin/local up`. Для production переносить только `theme/aloha-hello/`, без `dev/`, dev mount и константы; production-конфигурация в этом этапе не создавалась.

## Приёмка

Chromium, DPR1, viewport height 1000px; не эмуляция Safari/iPhone. Скриншоты и JSON находятся только в игнорируемой **`.local/evidence/m1-1/`**. В исходных `макеты/` новых файлов нет.

| Viewport | Начало контента x | Полезная ширина | Body | H1 | H2 | Page overflow |
| --- | --- | --- | --- | --- | --- | --- |
| 320 | 20 | 280 | 16 | 52 | 38 | Нет |
| 390 | 20 | 350 | 16 | 52 | 38 | Нет |
| 768 | 40 | 688 | 16.72 | 69.28 | 45.92 | Нет |
| 1024 | 40 | 944 | 17.21 | 80.98 | 51.28 | Нет |
| 1440 | 120 | 1200 | 18 | 100 | 60 | Нет |

Все величины в px, округлены. При 1440 внешний контейнер — 1280px; при 390 поля — 20px. Цвета, шрифтовые семейства, normal/italic, основные размеры, радиусы и ритм соответствуют design inventory и визуальным anchors PNG. Сравнение относится к системе стилей, не к полной главной.

| Проверка | Результат / evidence |
| --- | --- |
| Активация / block theme | PASS, `wp_is_block_theme()`, активные template/stylesheet `aloha-hello` |
| theme.json | PASS: официальная JSON Schema v3 для WP 6.6 + реальный WP Theme JSON Resolver / generated CSS |
| Responsive / fonts | PASS: пять ширин, 3 загруженных local font faces; все font responses 200; `frontend.json`, `showcase-{width}.png` |
| Keyboard / states | PASS: skip-link → main, 17 focus stops, видимый outline, disabled пропущены; primary hover/active, native input, select type-ahead, checkbox; `keyboard-*.png` |
| Text zoom / fallback | PASS: 200% размера текста при 320px, без page overflow; при намеренном отказе всех WOFF2 контент виден с fallback при 390px; отдельные PNG |
| Gutenberg blocks | PASS: все blocks showcase валидны (`invalid: []`), `block-validation.json` |
| Gutenberg presets | PASS: 6 цветов, 2 font families, theme sizes/spacing, Secondary/Text/Card styles; `editor-settings.json`, `gutenberg.png` |
| Site Editor | PASS: Index template открывается в настоящем canvas; `site-editor.png`; JS page errors отсутствуют |
| Core content | PASS: временный native draft сохранён и повторно загружен; невалидных блоков в итоговом сохранении нет |
| Editor / frontend | Совпадают families, sizes, colors и primary button styling при одинаковой ширине canvas; `editor-frontend-comparison.json` |
| Stop/start | PASS: template/stylesheet, theme_mods, SHA-256 global settings и содержимого draft совпали; `persistence-before.json` / `persistence-after.json` |
| Runtime dependencies | PASS: custom frontend JS **0**; только стандартный WP emoji script. В анонимном showcase trace внешних HTTP-запросов нет |
| PHP | PHP lint PASS; ошибок/warnings из темы нет; сохранён `php-log.txt` |
| Originals | PASS: `python3 scripts/check-references.py` — все 21 protected original совпадают по SHA-256 |
| Git / whitespace | Авторские файлы проходят diff check; в двух upstream OFL notices сохранён исходный trailing space строки 21, текст лицензий не нормализован |

Основной CSS — 7715 bytes, около 2137 bytes gzip; editor.css загружается только в редакторе. Это размеры assets, не измерение полевых Core Web Vitals. Временный editor draft после QA удалён; стандартные записи WordPress сохранены.

Повтор frontend QA: нужен доступный Node и Playwright Chromium. Используется уже имеющийся bundled Playwright; никаких npm/build dependencies тема не требует:

```bash
PLAYWRIGHT_MODULE=/path/to/node_modules/playwright node scripts/theme-smoke.cjs
python3 scripts/check-references.py
./bin/local check
```

## Контраст и отклонения от PNG

WCAG contrast ratios рассчитаны по sRGB relative luminance, без округления до сравнения с порогом:

| Пара | Ratio | Решение |
| --- | --- | --- |
| White / Orange | 3.11:1 | Недостаточно для обычного текста кнопки 16px/600 |
| Mirage / Orange | 5.16:1 | Primary default, обычный текст AA |
| Mirage / hover `#F55600` | >4.5:1 | Primary hover |
| White / Mirage | 16.06:1 | Primary active |
| Deep Sea Green / Page | 8.44:1 | Ссылки и focus |
| Mirage / Page | 14.77:1 | Основной текст |
| Muted / Page | 6.59:1 | Подписи |
| Disabled / Wild Sand | 5.18:1 | Неактивные элементы читаемы |
| Border / White или Page | 3.87 / 3.56:1 | Границы полей |

**Accessibility deviation:** белый текст primary CTA из PNG заменён на Mirage, фон Orange сохранён. Hover слегка темнее фирменного Orange, границы полей темнее очень светлых линий макета, добавлен явный focus. Эти изменения проверены технически; отдельное визуальное одобрение клиентом не подразумевается. Secondary/Text сохраняют цвет текущей поверхности, underline не полагается только на оттенок. Статические notice labels показывают состояние словами.

Tablet/intermediate fluid-значения — адаптация, поскольку исходные HTML не задают responsive breakpoints. H3–H6 объединены в общую шкалу. Полная pixel-perfect главная на M1.1 не заявляется.

## Известные ограничения и M1.2

- Проверен Chromium; Safari/Firefox, настоящий мобильный браузер, OS-native select popup и browser zoom требуют отдельной проверки. В headless select проверен клавиатурным type-ahead; тест 200% относится к размеру текста.
- Существующие warnings WordPress.org update/translation checks при `WP_HTTP_BLOCK_EXTERNAL` сохраняются с M1.0. Они не связаны с темой; core не менялся. Apache FQDN / MariaDB io_uring notices также относятся к среде.
- Это минимальный Index fallback. Нет готового page shell, nav, header/footer, production media/content и booking UI. Локальный showcase не следует превращать в публичную страницу.
- Latin font coverage и права на брендовые assets требуют учёта до расширения контента/production. Полевая производительность и полный WCAG аудит всего будущего сайта здесь не подтверждаются.

Для M1.2 фундамент готов: использовать существующие токены, `.aloha-container`, обе SVG-версии и native blocks. Следующий отдельный этап — desktop/mobile header/footer/nav, согласование Invest/Results/menu destinations, dropdown/disclosure states, Escape/focus return, safe-area/zoom. Не делать desktop header sticky автоматически. M1.2 начинается только по отдельному запросу.

Локальный commit с этим документом завершает M1.1; SHA выводится через `git log -1 --oneline` и приводится в итоговом отчёте. Push, deployment, production, Guesty/API и глобальные настройки Mac не затрагивались.
