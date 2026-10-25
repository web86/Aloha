# M1.5-B1.2 — Community editor sidebar polish

## Результат

Работа выполнена локально в **`codex/m1-5-b1-2-community-editor-polish`** от завершённого A.2.2 **`2056ee3`**. Aloha Content **0.7.4**. Тема и frontend не менялись; push и deployment не выполнялись.

Панель выбора Area уже использовала Core `ComboboxControl`. Причина неаккуратного вида подтверждена в открытом редакторе: theme rules для обычных форм задавали внутреннему input **52 px**, border, padding **12 × 16 px** и margin-top **8 px** внутри native контейнера **38 px**. Получались двойная рамка и наложение на help text.

Добавлена отдельная stylesheet, подключаемая только в Community editor с зависимостью `wp-components`. Scoped reset только для внутреннего input `.aloha-community-area` возвращает **32 px**, нулевые border/padding/margin и оставляет единственные рамку и focus ring у Core combobox. `!important` локально нужен из-за высокой специфичности theme selector с семью `:not()`; глобальные стили темы и другие editor controls не переписываются. Panel disclosure получает обычные editor hover/focus colors вместо theme button styling.

## UX

- Native label **Area**, placeholder **Search Areas**, короткое объяснение связи и help о публикации/черновиках.
- Поиск, список, стрелки/Enter/Escape и Reset остаются `ComboboxControl`. Используется его штатная фильтрация; query отслеживается только для видимого no-results feedback.
- Выбранный Area дополнительно показан в небольшом нейтральном summary с полным названием и статусом. Длинные названия и непрерывные строки переносятся, не расширяя sidebar.
- Loading содержит Spinner и текст **Loading Areas…**; Refresh временно disabled.
- Пустая коллекция объясняет, где создать Area и как обновить список. Поиск без совпадений показывает **No Areas match…**, сохраняя текущую связь. Escape и уход из поля убирают устаревшую поисковую подсказку.
- Ошибка списка отображается через Core `Notice`, с понятным предложением повторить загрузку. **Refresh Areas** восстанавливает список без изменения meta или dirty state.
- Неопубликованный Area имеет статус и warning о необходимости публикации. Недоступный сохранённый Area показан как **Unavailable Area**, с disabled option и возможностью Reset; связь автоматически не очищается.

Новой admin page, UI-библиотеки, системы связей или серверного search endpoint нет. Прежняя загрузка всех доступных Areas с REST pagination и `AbortController` сохранена.

## Сохранность архитектуры

Изменение выбора по-прежнему вызывает native `core/editor.editPost` с integer **`aloha_area_id`**; Reset задаёт **0**. Остальные meta копируются из текущего editor state. Поиск, загрузка и Refresh ничего не записывают. Save остаётся штатным Save/Publish Gutenberg.

Регистрация meta, capability checks, early validation guards, REST schema, native revisions и permalink logic не менялись. Контент Gutenberg и film label сохраняются при смене Area. Без публикации Area сервер по-прежнему разрешает только черновик Community; опубликованная запись защищена прежними guards.

## Изменённые файлы

| Файл | Изменение |
| --- | --- |
| `plugins/aloha-content/assets/editor.js` | Presentation и состояния прежней Area panel; native combobox и прежнее сохранение meta |
| `plugins/aloha-content/assets/editor.css` | Community-only spacing, input reset, wrapping и native editor colors |
| `plugins/aloha-content/aloha-content.php` | Enqueue editor stylesheet только для Community, version 0.7.4 |
| `scripts/community-editor-browser.cjs` | Изолированный suite для состояний/sidebar/Save/revisions |
| `scripts/test-aloha-content.sh` | Opt-in `CONTENT_COMMUNITY_EDITOR_TEST=1` внутри browser run |
| `README.md`, этот отчёт | Инструкция, файлы и QA |

## Проверки

Целевые UI-проверки выполнены на **WordPress 7.1.3 / PHP 8.3 / Chromium**, порт **8091**. Финальный focused runner завершился с **exit 0**: все 10 sidebar scenarios, строгие Investor/Guide/Area cleanup fingerprints и отсутствие оставшихся Area/Community fixtures подтверждены. PHP debug log — **0 bytes**; JS page errors отсутствуют. PHP/JS/Bash syntax и `git diff --check` пройдены.

Подтверждены **10 Community sidebar scenarios**, **28 штатных Gutenberg/REST scenarios** и **4 Routing Overview browser scenarios**. Штатный browser suite включает Save/reopen, смену Area и native revision restore. Серверные suites: **88 model checks**, **75 Area routing**, **22 Community routing**, **33 Guide**, **34 Investor**, **23 Routing Overview**; также **226 HTTP checks** маршрутов. Для sidebar проверено отсутствие invalid blocks и JS page errors.

Целевой диагностический прогон подтвердил **10 сценариев** на отдельной WP/DB **8091**:

1. Нет выбранного Area: label/placeholder/help, одна native рамка, input внутри контейнера.
2. Выбор с клавиатуры через ArrowDown/Enter, обычный Save и reopen с читаемым selected state.
3. No-results без очистки/изменения существующей связи.
4. Смена на Area с длинным названием и длинным непрерывным фрагментом; сохранность body/film meta, nested permalink и revisions.
5. Native Reset и сохранение черновика без Area.
6. Управляемая задержка REST list: Spinner/status/disabled Refresh.
7. HTTP error списка: native Notice и успешная повторная загрузка.
8. Пустой список с понятной инструкцией.
9. Недоступный выбранный Area не стирается автоматически.
10. Draft Area warning, read-only Refresh, valid Gutenberg blocks и отсутствие JS page errors.

Проверены **1440 / 1024 / 768 / 390 px** viewport: стандартный sidebar и узкий editor. При responsive переключении sidebar открывается штатной кнопкой Settings. UI loading/error/empty/unavailable проверяются network interception только в disposable browser; реальные сохранение/переключение/revisions используют настоящий REST и БД. Тестовые Areas/Community удаляются в `finally`.

## Evidence и повторный запуск

Первый общий прогон прошёл все функциональные suites, но остановился на строгом Investor cleanup fingerprint. Read-only диагностика установила единственное отличие: пустой стандартный `post` со статусом `auto-draft`, созданный Dashboard Quick Draft при входе нового теста. Existing Posts/Pages/Navigation, terms и options совпали. В тестовом login добавлен `redirect_to` на Communities list, чтобы Dashboard не открывался; продуктовая логика и preservation guard не менялись. [Диагностика](../.local/evidence/m1-5-b1-2/cleanup-diagnostic-report.json), [исходный log](../.local/evidence/m1-5-b1-2/cleanup-diagnostic.log).

В одном дополнительном общем повторе существующий **Guide Categories UI assertion** получил `publish` вместо ожидаемого `draft` на этапе публикации с двумя рубриками. В двух предыдущих прогонах он проходил. Новый stylesheet/script вообще не подключается к Posts, Guide/validation code не менялся; причина этого отдельного нестабильного теста не расследовалась в B1.2. [Log](../.local/evidence/m1-5-b1-2/extended-guide-ui-diagnostic.log). Итоговый focused runner отдельно выполняет весь новый sidebar suite и строгие Area/Guide/Investor cleanup guards, без повторения несвязанных UI suites.

Локальные артефакты вне Git: `.local/evidence/m1-5-b1-2/`.

- [Selected Area](../.local/evidence/m1-5-b1-2/community-area-selected.png), [без выбора](../.local/evidence/m1-5-b1-2/community-area-empty-selection.png).
- [Длинное название / standard](../.local/evidence/m1-5-b1-2/community-area-long-1440.png), [narrow editor](../.local/evidence/m1-5-b1-2/community-area-long-390.png).
- [No results](../.local/evidence/m1-5-b1-2/community-area-no-results.png), [loading](../.local/evidence/m1-5-b1-2/community-area-loading.png), [error](../.local/evidence/m1-5-b1-2/community-area-error.png), [empty list](../.local/evidence/m1-5-b1-2/community-area-empty-list.png).
- [Browser report](../.local/evidence/m1-5-b1-2/community-editor-browser-report.json), [успешный focused log](../.local/evidence/m1-5-b1-2/integration.log), [функциональные регрессии](../.local/evidence/m1-5-b1-2/regression.log), [сохранность основной БД](../.local/evidence/m1-5-b1-2/main-preservation-report.json).

```bash
DOCKER_CONTEXT=orbstack CONTENT_COMMUNITY_EDITOR_TEST=1 CONTENT_BROWSER_TEST=1 \
  CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-b1-2 \
  PLAYWRIGHT_MODULE=/path/to/node_modules/playwright ./scripts/test-aloha-content.sh
```

На этом host общий runner запускается через игнорируемую `.local/community-editor-tests-static-host.sh`: единственное отличие orchestration — private DB IP и `extra_hosts: db:IP` у одноразовых WP/CLI для обхода ранее подтверждённой нестабильности OrbStack DNS. Финальный focused runner `.local/community-editor-tests-target.sh` повторяет новый sidebar suite с настоящими Area/Guide/Investor fixtures и всеми их cleanup guards; уже пройденные независимые UI suites не повторяются. Сохранённые copies для аудита: [общий](../.local/evidence/m1-5-b1-2/test-runner-reference.sh), [focused](../.local/evidence/m1-5-b1-2/test-runner-target-reference.sh). Основной Docker Compose не менялся.

Все тестовые записи, credentials, одноразовые контейнеры и volumes удалены. Основные post rows, editorial postmeta, attachments, Navigation, terms и Reading/permalink/routing/shell options совпали до/после. Только значения native `_edit_lock` нормализованы как volatile; сами строки/IDs не исключались и не откатывались. SHA-256 редакционного снимка до/после: **`b671d2be9c70339925e414c048b5850fefa38bb899a1fc015f0c879c66ac172e`**. Реальные Community не редактировались, активны только прежние WP/DB основной установки 8088.

## Ограничения

Проверена установленная **WordPress 7.1.3 / Chromium**. Отдельного Safari/Firefox QA не было. Длинное выбранное значение в single-line input может прокручиваться; полное название читается в summary и переносится в списке. Не проверялась работа с тысячами Areas; прежняя paginated REST загрузка сохранена. Реальная Ka Milo и её редакционный контент не использовались для тестовых сохранений.
