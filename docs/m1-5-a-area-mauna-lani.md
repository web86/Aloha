# M1.5-A — Area Mauna Lani и Gutenberg starter patterns

Позднейшее обновление: [M1.5-A.2 — Media Gallery](m1-5-a2-media-gallery.md) заменяет галерею в новых starter/Patterns на один динамический блок. Остальные секции и сохранённые Core Gallery не переписываются автоматически.

Позднейшее обновление: [M1.5-A.3a — Interactive Beaches](m1-5-a3a-beaches.md) заменяет фиксированную Beaches-сетку новым пустым редакционным блоком в starter. Сохранённые карточки автоматически не переписываются; для локальной Mauna Lani предусмотрен отдельный проверяемый перенос.

Позднейшее обновление: [M1.5-A.3b — Native FAQ Accordion](m1-5-a3b-faq-accordion.md) заменяет Details в новом FAQ starter стандартными Core Accordion blocks. Пользовательские ответы сохраняются; для Mauna Lani используется отдельная контролируемая миграция.

22.10.2026. Тема **Aloha Hello 0.3.0**, Aloha Content **0.6.0**. Ветка `codex/m1-5-a-area-mauna-lani` от опубликованного M1.4 `ad5fe8a2bea1a022dcdd23b24dd39fb02e4c8e9d`. После уточнения задания используется **HTML**, не PHP display template.

## Архитектура

`templates/single-area.html` выбирается стандартной иерархией block theme. Он содержит существующие Header/Footer, контекстный `aloha/area-hero` и Core Post Content. Специальные template filters, rewrite rules и PHP template bridge не потребовались. Другие display templates не создавались.

Hero получает title, excerpt и featured image текущего Area. Название Mauna Lani не зашито в общий шаблон. Изображение выводится стандартной WordPress image function, с `srcset`, `sizes`, `loading=eager` и `fetchpriority=high`. Без изображения остаётся компактный текстовый Hero на фирменном фоне; отсутствующие excerpt/caption не создают пустых элементов.

В теме зарегистрированы только три узких presentation blocks:

- `aloha/area-hero` — контекстный Hero в display template, не часть редакционного body.
- `aloha/area-communities` — native `WP_Query`: опубликованные `community`, `aloha_area_id` равен текущему Area; порядок по title. Карточки используют реальные title, excerpt, featured image и `get_permalink()`. Без изображений выводятся текстовые карточки; без записей — честное пустое состояние.
- `aloha/area-rentals` — пока пустой публичный slot. В редакторе объясняется, что данные появятся после будущей интеграции GuestyBridge. Нет CPT rental, цен, provider API, поиска или бронирования.

Небольшой editor script регистрирует эти три server-rendered blocks. Он не вставляет и не синхронизирует body. На frontend дополнительного Area JS нет. Для помеченных локальных Area CTA переиспользован существующий shell demo dialog; production ссылки остаются каноническими. Меню и его механизмы не менялись.

## Starter и Patterns

Тема через `register_post_type_args` задаёт CPT Area native unlocked `template`:

```php
array( array( 'core/pattern', array( 'slug' => 'aloha-hello/area-start' ) ) )
```

Установленный Core Pattern Placeholder сам раскрывает паттерн через native `replaceBlocks`. После сохранения в `post_content` находятся обычные несинхронизированные блоки, а не reference на starter. Нет JS-инжектора, save hook, автоматической перезаписи body или синхронизированного `wp_block`.

Для уже сохранённой Area (`post_status !== auto-draft`) `block_editor_settings_all` отключает начальный template. Это сохраняет даже намеренно очищенную запись: повторное открытие не возвращает удалённые секции. Существующий непустой контент не меняется. При смене темы исчезает только тема-зависимая начальная композиция для новых записей; сохранённые Core blocks остаются в БД.

Нативные theme Patterns в категории **Aloha Area**, ограниченные Post Type `area`:

| Pattern | Назначение |
| --- | --- |
| `area-start` | Полная композиция с правильным порядком секций |
| `area-introduction` | Редакционное вступление |
| `area-quick-facts` | Редактируемые пары label/value; без выдуманных чисел |
| `area-gallery` | Core Gallery, перестраивается при другом количестве фотографий |
| `area-beaches` | Четыре начальные карточки; можно добавлять, удалять и переставлять |
| `area-activities` | Две крупные и шесть малых карточек |
| `area-practical-advice` | Редакционные практические разделы |
| `area-faq` | Core Details; количество вопросов не ограничено |
| `area-owner-cta` | Advisor panel и owner CTA |

Разметка паттернов создаётся из одного небольшого theme PHP module, без копирования export runtime/inline styles. Файлы `patterns/area-*.php` используют стандартную регистрацию WordPress. Начальная композиция общая для любых Area и не содержит Mauna Lani copy или attachment IDs.

## Редактирование

В **Areas → Edit** менеджер меняет title, slug, excerpt и featured image через стандартные поля. В body доступны обычные Group, Heading, Paragraph, Image, Gallery, Buttons и Details. Можно менять тексты, дублировать карточки/вопросы, переносить секции в List View, удалять ненужные блоки и добавлять новые.

Hero редактируется через поля самой Area; он не дублируется вторым названием или URL в body. Для карточек заменить Image внутри соответствующей группы либо удалить всю группу фотографии. Фоновый editorial placeholder исчезает, когда добавлено изображение. Gallery использует родную Media Library и управление порядком Core. Пустая галерея не оставляет пустую секцию на frontend. Удалённый attachment и пустой Image placeholder не выводятся как сломанный `img`.

Вручную вводить список Communities в Area не нужно. Создать/отредактировать Community, опубликовать и выбрать её Area; секция обновится по native query. Данные домов, реальные counts, availability, prices и booking ожидаются от GuestyBridge отдельным этапом.

## Медиа и локальный контент

Источник — неизменённый `макеты/Design v2 — Area Mauna Lani-html.zip`, `AreaDesign.dc.html` и вложенные JPG. Порядок/геометрия сверены с PNG **1440 × 7900** и design inventory: Hero 720, контейнер 1200 usable/1280 border-box, intro 1.3:1 gap80, gallery 2:1:1, beaches 4col, activities 2+3col, communities 3col, section rhythm 110. Используются существующие Instrument Serif, Outfit и токены темы.

Импортируются **8 уникальных JPG** из проектного ZIP, через WordPress Media Library с генерируемыми responsive sizes. Их происхождение записано в attachment meta `_aloha_reference_source`; alt/caption явно обозначают design-reference фотографию. Местоположение и права ещё требуют редакционного подтверждения. Фотографии не добавляются в тему/Git. Вместо отсутствующих пляжных/исторических фото оставлены явные редактируемые placeholders, как и в исходном дизайне.

`scripts/seed-area-mauna-lani.php` — явная local-only WP-CLI команда, не загружаемая темой. Она разрешена только на 8088 в local environment, проверяет права и существующую `area/mauna-lani`. При повторном запуске сохраняет найденную запись и не импортирует медиа повторно. Не исполняется на production или из HTTP.

После успешного общего прогона создана одна постоянная опубликованная Area **Mauna Lani, ID 68**:

- [Публичная страница](http://127.0.0.1:8088/big-island/mauna-lani/).
- [Редактор Gutenberg](http://127.0.0.1:8088/wp-admin/post.php?post=68&action=edit).
- Восемь attachment IDs: **69–76**, каждый привязан к этой Area. Файлы скопированы в Media Library; временный bind с исходниками больше не требуется.

Повторный запуск seed вернул `existing_preserved: true`, без новых медиа или перезаписи body. Read-only сравнение снимков основной БД подтвердило неизменность всех **59** прежних записей, **18** прежних postmeta, terms/relationships, Navigation, permalink structure, rewrite rules/signature, настроек shell и Reading. Новые записи — только согласованная Area, восемь attachments и одна Core revision этой Area. В основной БД тестовые Community и дополнительные Area не создавались.

## Проверки и Visual QA

Полный прогон завершился успешно на disposable WordPress (`.local/content-test-6O3Poc`).

| Проверки | Результат |
| --- | --- |
| Прежние Aloha Content server regressions | **275 passed**: 88 model/integrity, 75 Area, 22 Community, 33 Guide, 34 Investor, 23 Routing Overview |
| Прежние HTTP regressions | **226 passed**, включая изменённые базы, canonical, 404 и обычные Posts/Pages |
| Прежние Gutenberg / Routing Overview browser scenarios | **32 passed**: 28 + 4 |
| Новые Area server checks | **23 passed**: выборка Communities, current object, изображения, шаблон, 9 patterns, отсутствие rental CPT |
| Новые Area browser scenarios | **11 passed**, без JavaScript page errors |
| Смена темы и обновление starter pattern | Сохранённый body идентичен; стартер не переносится в другую тему и не перезаписывает запись |
| Основная локальная Mauna Lani | HTTP 200, правильный canonical; 1440/1024/768/390/320 без overflow и сломанных картинок; **162 валидных блока** в редакторе |
| PHP log изолированного стенда | **0 bytes** |
| Cleanup | Fixtures/media удалены, baseline сохранён; временные контейнеры и тома отсутствуют |
| Syntax / whitespace | PHP lint, Node syntax, Bash syntax и `git diff --check` прошли |

В реальном Gutenberg проверены начальная вставка в новую Area, редактирование RichText, добавление/удаление FAQ (до 11 вопросов), перестановка карточек, добавление/удаление/перестановка Gallery images, замена фотографии, удаление секции и добавление произвольного Core block. После save/reopen блоки остаются валидными. Отдельно проверены native draft preview, изменение title/slug без reseed, намеренно пустой сохранённый body, второй Area с общей композицией без Mauna Lani copy, удалённый attachment, клавиатурный FAQ, demo dialog/Escape, 200% текста и длинный заголовок без медиа при 320px. Эти изменения выполнялись только в тестовой БД.

Скриншоты основной сохранённой страницы: [1440](../.local/evidence/m1-5-a/mauna-lani-1440.png), [1024](../.local/evidence/m1-5-a/mauna-lani-1024.png), [768](../.local/evidence/m1-5-a/mauna-lani-768.png), [390](../.local/evidence/m1-5-a/mauna-lani-390.png), [320](../.local/evidence/m1-5-a/mauna-lani-320.png), [Gutenberg](../.local/evidence/m1-5-a/mauna-lani-editor.png).

Дополнительные состояния стенда: [три динамические Community](../.local/evidence/m1-5-a/area-1440.png), [keyboard focus](../.local/evidence/m1-5-a/area-keyboard.png), [200% text](../.local/evidence/m1-5-a/area-text-200.png), [длинный заголовок без изображений](../.local/evidence/m1-5-a/area-no-media-long-title.png). JSON reports, `integration.log` и screenshots находятся в `.local/evidence/m1-5-a/`, исключены из Git. Исходный PNG сверялся визуально по секциям; автоматического pixel-diff нет. Browser QA выполнен в Chromium, Firefox/WebKit в этом этапе не запускались.

`scripts/check-references.py` выполнен: единственная ошибка — ранее отсутствовавший **`aloha-architecture-sources.zip`**. Все **20 доступных originals** совпадают с manifest по размеру и SHA-256. Исторический ZIP не воссоздавался, manifest не менялся.

Тесты используют прежний disposable WordPress/DB на 8091, с дополнительной опцией:

```bash
CONTENT_AREA_TEST=1 CONTENT_BROWSER_TEST=1 \
  CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-a \
  PLAYWRIGHT_MODULE=/path/to/node_modules/playwright \
  ./scripts/test-aloha-content.sh
```

Нужны оригинальные JPG, извлечённые в `.local/reference-area/assets`, либо `ALOHA_AREA_MEDIA_DIR`. Без `CONTENT_AREA_TEST=1` прежние Aloha Content regressions не требуют локальных оригиналов. Тестовые медиа/Area/Community создаются только в отдельной БД и удаляются перед проверкой исходного baseline.

## Отклонения и ограничения

- В обычном публичном режиме отсутствует Featured Rentals: GuestyBridge не создан. Поэтому страница короче PNG; фиктивные дома, цены, availability и counts не показаны.
- Communities зависит от реальных опубликованных записей. В основной локальной базе не создаются выдуманные комплексы ради девяти карточек макета. На стенде используются явно названные fixtures для проверки 3-column layout.
- Нет исходного видео: Hero использует poster, film controls не имитируются. Галерея показывает имеющиеся изображения, без фиктивной надписи «+24 photos»; один отсутствующий кадр заменён другой явно помеченной reference-фотографией.
- Copy, access, поездки и прочие неподтверждённые сведения оставлены редакционными placeholders. Неподтверждённые числовые claims макета, имена/портрет сотрудника и контакты не выдаются за реальные.
- Отдельного утверждённого mobile Area нет: адаптивность — реализация на основе desktop, не pixel-perfect mobile claim. Header сохраняет breakpoint 1280; контентные сетки сокращаются на 1024/768/480.
- Core Details не создаёт FAQ schema автоматически; TOC, универсальные content rules, новый Query Builder и формы отправки не реализованы.
- При другой теме presentation blocks могут показываться как unsupported в редакторе, но сохранённый body не удаляется. Полная совместимость оформления относится к Aloha Hello.

## Файлы

`theme/aloha-hello/templates/single-area.html`, `inc/area.php`, `inc/area-patterns.php`, `patterns/area-*.php`, `assets/css/area.css`, `assets/js/area-editor.js`; подключение в `functions.php`, версия в `style.css`, один scoped local CTA condition в `assets/js/shell.js`. Aloha Content model/routing files не менялись.

Тесты/локальный импорт: `scripts/area-reference-content.php`, `scripts/seed-area-mauna-lani.php`, `scripts/area-presentation-check.php`, `scripts/area-presentation-browser.cjs`, опциональный запуск в `scripts/test-aloha-content.sh`; прежний `chooseArea()` browser helper ожидает загрузку раскрытой панели вместо её случайного закрытия. Исходные PNG/HTML/ZIP и manifest не менялись. M1.5-B/Home/Catalog/Unit/GuestyBridge/B4, push и deployment не выполнялись.
