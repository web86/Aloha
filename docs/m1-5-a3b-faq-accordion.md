# M1.5-A.3b — Native FAQ Accordion

Aloha Hello **0.6.0**, Aloha Content **0.7.2** без изменений плагина. Beaches проверен и сохранён отдельным локальным коммитом **`fc55e2a`**. Ветка `codex/m1-5-a3b-faq-accordion` создана от него. Push/deployment не выполняются.

## Core и архитектура

В локальном WordPress **7.1.3** проверена регистрация `core/accordion`, `core/accordion-item`, `core/accordion-heading`, `core/accordion-panel`, их metadata, установленный editor save-format и PHP renderers. Core помечает Accordion как доступный с **6.9**; минимальная версия темы повышена до 6.9. Фактические тесты выполняются на установленном 7.1.3.

Тема не регистрирует новый FAQ block, CPT, таблицу, option или отдельную панель настроек. Нет нового frontend/editor JS. Раскрытие, `autoclose`, `openByDefault`, `aria-expanded`, уникальные IDs, `aria-controls`/`aria-labelledby` и hidden panels обслуживает **Core Interactivity API**. Ответы остаются обычными Gutenberg inner blocks. FAQPage schema автоматически не добавляется.

`aloha_area_faq_item()` и `aloha_area_faq_accordion()` в `inc/area-patterns.php` формируют стандартную сохранённую разметку Core для starter и явной CLI-миграции. На frontend выводят именно Core blocks; собственного SSR-accordion renderer нет. Дизайн меняется через scoped CSS, ответы при обновлении темы не переписываются.

## Starter и оформление

`area-faq` содержит прежний eyebrow **Questions guests ask** и один Core Accordion с native `metadata.name: Area FAQ`. Внутри — один явно редакционный вопрос **Editorial question — replace before publishing** и placeholder-ответ с указанием Add item. Нет вымышленных локальных утверждений. Другие секции starter не менялись, сохранённые записи автоматически не обновляются.

В пределах `.aloha-area-faq` используются Outfit 18px/500 для вопроса, верхние разделители, 20px вертикальные отступы, иконка справа 24px и обычный текст ответа. Сохраняется прежняя двухколоночная секция practical advice/FAQ. На узких экранах действует существующая адаптивность Area. Hover подчёркивает вопрос и меняет цвет; focus-visible — явная рамка. Цветовой transition включён только при `prefers-reduced-motion: no-preference`; вращение иконки остаётся штатным Core с его reduced-motion поддержкой. Раскрытая иконка Core поворачивается на 45°, не заменяется своей JS-логикой.

Старые Details продолжают работать и сохраняют прежние стили. Пустая публичная FAQ-секция скрывается только внутри Area Post Content. Core Accordions вне `.aloha-area-faq` не получают эти стили.

## Контролируемая миграция

`scripts/area-faq-migration.php` ищет ровно одну FAQ Group. Поддерживаются прямые Core Details и существующий eyebrow; смешанные/неоднозначные секции требуют ручной замены. Для каждого Details проверяются block attributes, HTML-атрибуты wrapper/summary, начальное открытое состояние, формат вопроса и вложенные answer blocks.

Текст вопроса с поддерживаемым inline formatting переходит в `core/accordion-heading`, точные исходные bytes answer blocks — в `core/accordion-panel`. `showContent` переходит в `openByDefault`; независимые Details сохраняют режим нескольких ответов (`autoclose:false` по умолчанию). Исходные текстовые инструкции в старых placeholder-ответах также сохраняются: редакционный контент не исправляется молча.

Проверены обычные paragraph/list/list-item/group/heading/quote/image/buttons/button/separator/spacer/code внутри ответов. Raw HTML, shortcode, неизвестные/custom blocks, вложенные Details, интерактивный вопрос, HTML-атрибуты inline-разметки вопроса, нестандартные attributes Details или неоднозначность требуют контролируемой ручной замены. Ничего не удаляется для «успешного» преобразования. Стили/атрибуты поддерживаемых внутренних answer blocks сохраняются вместе с их исходной разметкой.

Меняется только точный диапазон Details внутри FAQ. Eyebrow, оболочка FAQ и все байты других секций не пересериализуются. Нужна совпадающая с текущим body резервная копия; перед записью создаётся и проверяется native revision. Повторный запуск при уже существующем Core Accordion — write-free no-op, включая последующие пользовательские изменения.

`scripts/migrate-area-mauna-lani-faq.php` ограничен WP-CLI, local `127.0.0.1:8088`, Area **68 / mauna-lani** и capability `edit_post`. `plan` читает данные, `apply` требует проверенный backup. Нет массовой миграции или runtime save hook.

## Работа менеджера и оценка UX

1. Найти **Questions guests ask** в правой колонке блока practical advice или выбрать **Area FAQ** в штатной навигации блоков.
2. Кликнуть вопрос и изменить текст в обычном RichText. Ответ открывается в редакторе при выборе его item; внутри доступны Paragraph, List, Link и другие стандартные Gutenberg blocks.
3. Выбрать **Area FAQ** в нижних breadcrumbs. На toolbar появляется штатная текстовая кнопка **Add item**. Новый вопрос получает собственные heading/panel; заполнить их обычным способом.
4. Для перестановки выбрать **Accordion Item** в breadcrumbs и использовать **Move up / Move down**. Для удаления — штатное **Options → Delete** этого item. Удаляется весь вопрос вместе с ответом, без разрушения структуры соседей.
5. Для режима одного ответа открыть общую панель **Settings** справа вверху, если она закрыта, и выбрать **Area FAQ → Block → Settings → Auto-close**. Включено — один открытый ответ; выключено — несколько. В установленном WordPress 7.1 Inspector также содержит вкладку Content: она показывает дерево, а Auto-close находится во вкладке Settings с иконкой шестерёнки.

Это проверено настоящими кликами в Gutenberg: 19 последовательных Add item, заполнение новых ответов, Move up/down, Delete, восстановление ещё одного item, настройка Auto-close, save/reopen. Параграфы, nested list и ссылки сохранились. **Собственный FAQ block не нужен.** После выбора родителя Add item заметен; breadcrumb **Area FAQ** уменьшает неоднозначность выбора.

У Core фиксирована внутренняя пара heading/panel: их lock icons являются штатной защитой структуры. Они не запрещают добавление/удаление/перестановку целых Accordion Items, и тема не вводит quantity lock.

Особенность пустого Core Accordion: на frontend сохранённые 0 items дают 0 вопросов, пустая FAQ-секция скрыта; при открытии такого блока Gutenberg 7.1.3 предлагает один пустой item по своему template. Проверено, что это пустой title, а не придуманная тема вопроса. Для временного отсутствия FAQ менеджеру проще удалить весь Accordion/FAQ section, а не оставлять пустой item и публиковать его. Это штатное ограничение Core, не основание для собственного FAQ-компонента. Дополнительный UI в этом этапе не разрабатывался.

## Клавиатура и отсутствие JavaScript

На frontend вопросы — настоящие H3 с native buttons. Enter/Space переключают состояние через Core, Tab/Shift+Tab переходят между вопросом и ссылками ответа; focus-visible сохраняется. Значения ARIA и IDs обновляет Core. Режим нескольких ответов не является модальным и не ограничивает фокус.

Без JavaScript установленный Core оставляет все answer panels видимыми в SSR: можно прочитать содержимое и открыть ссылки. Сворачивание требует JS. Это проверено на 20 вопросах в контексте с `javaScriptEnabled:false`; отдельного fallback renderer, копий ответов или custom disclosure script не потребовалось.

## Результат Mauna Lani

Перенос выполнен только для Area **68**, после успешного полного прогона. Сохранены исходные пять вопросов, их порядок и все ответы:

1. Is this area right for my trip?
2. Will I need a rental car?
3. How do I get here?
4. How does this area compare?
5. When should I visit?

Все пять paragraph-ответов сохранены без изменений, включая старую редакционную инструкцию про Duplicate Details. Новые факты или ответы не добавлялись. По умолчанию сохраняется независимое открытие нескольких ответов.

Backup полного body: `.local/mauna-lani-faq-post-content-before.html`, режим **0600**. Исходная revision **93** проверена по полному `post_content`; revision **94** содержит результат. SHA-256 до переноса: `00fe5da5be4c796a99204f0f34e084b8a00a682da4f4ccdaf9fc9c46d811fc88`; после: `ee0d18312a2977335bdc98c102c8cdc0d9d20df47f7afc34d7d81af4f562ad64`.

Повторный apply вернул **`changed:false`, `questions:5`**, дополнительных revisions нет. Финальная SQL-сверка: **81 полностью сохранённый прежний post row**; у Area 68 изменились только `post_content`, `post_modified`, `post_modified_gmt`. Все bytes вне диапазона Details внутри FAQ идентичны. Все прежние postmeta на интервале миграции совпали. Во время предшествующих длительных тестов наблюдалось только штатное обновление `_edit_lock`, без изменений контента. Attachments, Hero fields, Gallery, Beaches, Activities, Navigation, terms/relationships, permalink/rewrite signature, Reading и shell settings сохранены. Новых Pages, Area, Community или media в основной БД нет; добавлены только две revisions.

Точная новая версия body проверена **штатным Gutenberg parser в изолированном редакторе**: **144 блока**, `invalid: []`, пять Accordion Items; hash совпадает с фактически сохранённым body основной Area. Основной редактор после миграции автоматически не открывался, чтобы исключить известное штатное удаление старых autosaves в Core при открытии edit screen. Реальная работа в Gutenberg и save/reopen проверены на изолированной Area с той же темой/Core; реальный frontend Mauna Lani проверен отдельно.

[Mauna Lani](http://127.0.0.1:8088/big-island/mauna-lani/) показывает новый FAQ. Продолжить редактирование можно в [Area 68](http://127.0.0.1:8088/wp-admin/post.php?post=68&action=edit). На основной странице прошли 1440/1024/768/390/320px, Enter/focus-visible, несколько открытых ответов, 320px/200% текста и no-JS с пятью доступными ответами. Gallery/Beaches соответствуют актуальному исходному body. JavaScript errors отсутствуют, новых bytes PHP-лога нет.

## Проверки и visual QA

Заключительный полный прогон **прошёл**, `.local/content-test-hGJMfT`.

Команда: `DOCKER_CONTEXT=orbstack CONTENT_FAQ_TEST=1 CONTENT_BROWSER_TEST=1 CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-a3b ./scripts/test-aloha-content.sh`, с установленным bundled Playwright через `PLAYWRIGHT_MODULE` и `ALOHA_FAQ_CANDIDATE=.local/faq-main-candidate.html`. FAQ opt-in включает прежние Beaches/Gallery/Hero/Area regressions.

| Набор | Server | HTTP | Browser |
| --- | ---: | ---: | ---: |
| Aloha Content A/B1/B2/B3-A/B3-B/B3-C | 275 | 226 | 32 |
| Area presentation | 23 | — | 11 |
| Hero + polish | 48 | — | 17 |
| Media Gallery + UI polish | 41 | — | 13 |
| Beaches | 32 | — | 10 |
| Core FAQ + controlled migration | 31 | — | 10 |

Проверены 0/1/5/10/20 вопросов, реальные Add item/Move/Delete/Auto-close/save/reopen, paragraphs/nested lists/links/inline emphasis, несколько и один открытый ответ, keyboard/focus/ARIA, reduced motion, пять ширин, 200% текста, отсутствие горизонтального overflow и invalid blocks. Внешний Core Accordion не получил scoped styles. Без JS доступны все 20 ответов и рабочая ссылка.

Миграционные проверки включают exact answer bytes, порядок и формат вопросов, открытые Details, сохранность окружающего body/meta, original revision, stale backup, нулевую коллекцию, идемпотентность и безопасный отказ для неоднозначной/custom структуры. Смена темы и обновление Pattern не меняют сохранённое содержимое. Все fixtures удалены; cleanup подтвердил прежние контент, Navigation и настройки. Временные контейнеры/volumes удалены.

В ходе проверки исправлены: безопасный отказ для повреждённых Details attributes вместо TypeError; наследование оранжевого оформления ordinary buttons внутри editor FAQ; подготовка теста к сохранённому закрытому Inspector и native Settings tab. Заключительный прогон выполнен после исправлений, с намеренно закрытой sidebar в FAQ test. PHP debug log стенда **0 bytes**, browser reports без page errors. PHP/JS/Bash syntax и `git diff --check` прошли. Исторический PHP-лог основной установки не очищался, прирост **0 bytes**.

Скриншоты и reports находятся только в `.local/evidence/m1-5-a3b/`:

- Реальная Mauna Lani: [1440](../.local/evidence/m1-5-a3b/mauna-lani-faq-1440.png), [1024](../.local/evidence/m1-5-a3b/mauna-lani-faq-1024.png), [768](../.local/evidence/m1-5-a3b/mauna-lani-faq-768.png), [390](../.local/evidence/m1-5-a3b/mauna-lani-faq-390.png), [320](../.local/evidence/m1-5-a3b/mauna-lani-faq-320.png), [keyboard](../.local/evidence/m1-5-a3b/mauna-lani-faq-keyboard.png), [200% текста](../.local/evidence/m1-5-a3b/mauna-lani-faq-text-200.png).
- Изолированный Gutenberg: [открытый Accordion](../.local/evidence/m1-5-a3b/faq-gutenberg-open.png), [родитель и Add item](../.local/evidence/m1-5-a3b/faq-gutenberg-add-item.png).
- Изолированный frontend: [пять вопросов desktop](../.local/evidence/m1-5-a3b/faq-five-1440.png), [mobile](../.local/evidence/m1-5-a3b/faq-five-390.png), [несколько ответов / keyboard](../.local/evidence/m1-5-a3b/faq-keyboard-multiple.png), [200%](../.local/evidence/m1-5-a3b/faq-text-200.png).
- Evidence: `faq-server-report.json`, `faq-browser-report.json`, остальные regression reports, `main-faq-browser-report.json`, `main-faq-preservation-report.json`, migration/repeat JSON и PHP logs.

## Файлы и границы

- Тема: `inc/area-patterns.php` (Core markup helpers и только FAQ starter), `assets/css/area.css` (scoped FAQ styles и сохранённые legacy Details styles), `style.css` (0.6.0, minimum WP 6.9).
- Проверки/перенос: `scripts/area-faq-migration.php`, `migrate-area-mauna-lani-faq.php`, `faq-accordion-check.php`, `faq-accordion-browser.cjs`; FAQ phase в прежнем `test-aloha-content.sh`; Area browser regression адаптирован к Core Items вместо Details.
- Документация: этот отчёт и ссылка из исходного M1.5-A отчёта.

Hero, Media Gallery, Beaches, Activities, Community Ka Milo, GuestyBridge, Aloha Content model/routing files и display templates не разрабатывались и не переписывались в этом этапе. Изменения FAQ оставлены локальным незакоммиченным diff в новой ветке; коммит Beaches — `fc55e2a`. Push/deployment не выполнялись.

Известные ограничения: тестируется установленный WordPress 7.1.3, отдельная матрица старых Core версий не запускалась; Firefox/WebKit отсутствуют в runtime, браузеры не устанавливались. Нестандартный Details, не прошедший проверки, требует ручного переноса с сохранением контента. Core no-JS показывает все ответы и не выполняет collapse. Текст Mauna Lani сохраняет существующие placeholders, а не утверждает факты из reference; expanded/no-JS/mobile состояния не изображены на финальном desktop PNG. Открытая иконка Core — повёрнутый плюс.

`scripts/check-references.py` сохраняет прежнее ограничение: отсутствует исторический `aloha-architecture-sources.zip`; исходные HTML/PNG/ZIP и manifest не изменялись. Остальные 20 оригиналов совпадают с manifest. ZIP не создавался ради прохождения проверки. Исходный handoff PDF остаётся прежним untracked файлом. Backups, DB snapshots и screenshots находятся только в игнорируемой `.local/`.
