# M1.5-B1.1 — Community Hero YouTube Film

## Сверка дизайна до реализации — 24.10.2026

Исходники: финальный `макеты/Design v2 — Community Ka Milo@1x.png` и `CommunityDesign.dc.html` из соответствующего final ZIP. PNG имеет приоритет; экспорт распакован в игнорируемую `.local/reference-community/`.

- Медиапанель Hero: x≈712, y≈166, 608 × 560 px на viewport 1440. Film control внутри неё, отступы слева и снизу **24 px**. Это отдельный третий action, не часть ряда двух основных CTA.
- Подпись PNG: **Ka Milo in 90 seconds**, Outfit **18 px / 1.6**, weight **600**, цвет Mirage **#16232A**. Зазор между кругом и подписью **12 px**.
- Play: круг около **59 × 59 px с учётом border** (HTML: 56 px content-box + 2 × 1.5 px border), border **1.5 px #16232A**, background **rgba(255,255,255,.7)**, triangle около **10 × 12 px**, по центру с оптическим смещением вправо.
- В HTML у `.play` нет отдельных hover/focus states; PNG статичен. Решение реализации: на hover круг становится белее, без transform/изменения геометрии; focus использует общий контрастный outline темы. Внешний вид Area film не копируется.
- На мобильных — те же 24 px внутри медиапанели, подпись переносится; круг не сжимается. Высота медиапанели может увеличиться для длинной подписи / 200% текста. При отсутствии URL нет control или зарезервированного под него места.
- На реальной фотографии добавляется мягкая локальная светлая подложка под control для читаемости тёмной подписи; на градиенте из PNG её нет.

Дефолт для любого Community: **{текущее название} in 90 seconds**; для Ka Milo получается точный текст PNG. Отдельное optional override-поле позволяет исправить подпись и длительность под редакционный ролик. Фиктивный фильм реальной Ka Milo не назначается.

## Реализация

Ветка `codex/m1-5-b1-1-community-film` от B1 `17a8b0c`. Aloha Content **0.7.3**, Aloha Hello **0.7.1**.

Aloha Content регистрирует два необязательных single string post meta только для `community`: `aloha_community_film_url` и `aloha_community_film_label`. Оба доступны в REST, имеют пустой default, `revisions_enabled=true`, auth через `edit_post`. Чтение defaults не создаёт строки postmeta и не назначает реальные видео автоматически.

`community-media.php` переиспользует существующие `area_youtube_id()` и `sanitize_area_film_url()` без изменения их поведения. Принимаются установленные Area формы `youtu.be`, YouTube watch/embed/shorts/live с валидным 11-символьным ID; удаляются лишние query parameters, URL сохраняется как `https://www.youtube.com/watch?v={id}`. iframe code, чужой host, похожий host, credentials, port, playlist и неверные типы отклоняются. Label сохраняется как plain text.

`rest_pre_insert_community` проверяет URL, тип label и права на изменяемое meta до записи title, excerpt, body, relation и прочих meta. Некорректный запрос возвращает 400, отсутствие разрешения — 403. `wp_insert_post(meta_input)` / `wp_update_post(meta_input)` также отказывают до изменения post fields. Низкоуровневый Metadata API остаётся доверенным API WordPress с теми же sanitize callbacks; REST и UI являются проверенным путём редактирования. Нативные media-only ревизии сохраняют оба поля, `wp_restore_post_revision` восстанавливает их вместе с остальными revisioned data.

Панель **Community Media** находится в document settings Gutenberg. Два поля — **YouTube film URL** и **Film Label**; изменения сохраняются обычной кнопкой Save. Пустой override показывает placeholder с текущим названием и позволяет использовать дефолт PNG без хранения дублирующего названия. Можно заменить или удалить URL; label при этом остаётся доступным. Панель не появляется у Area и не предлагает HTML5 background video.

`aloha/community-hero` повторно проверяет URL при render и выводит только ID в `data-aloha-youtube`, escaped label и `data-aloha-media-title`. Невалидное legacy значение также не создаёт кнопку. Ни iframe, ни YouTube thumbnail/preconnect, ни внешний player script до клика не создаются. Нет новых block attributes или сохранённых iframe в Post Content.

Тема подключает прежние handles `aloha-media-dialog` script/style для Community. Исходники общего `media-dialog.js` и его CSS не изменены. Click/Enter создаёт один native dialog и `https://www.youtube-nocookie.com/embed/{id}?autoplay=1&rel=0`; Escape/Close удаляют iframe и возвращают фокус. Поведение остановки достигается удалением player node, как у Area. GLightbox и второй modal не добавлены. Главные два CTA, Template, Starter, routing и `aloha_area_id` не менялись.

CSS Community отдельный: background/shape/spacing из Community, а не Area. При 1440 normal text Play расположен x≈736, y≈643, нижний край y≈702. До медиапанели слева/снизу по 24 px; иконка начинается примерно на 29 px ниже нижнего края ряда основных CTA. Для увеличенного текста мобильная панель имеет минимальную высоту прежнего aspect-ratio и может расти по содержимому; фиксированная высота не обрезает подпись.

## Изменённые файлы

| Файл | Изменение |
| --- | --- |
| `plugins/aloha-content/inc/community-media.php` | REST/revision fields, reuse YouTube validator, validation/permission guards |
| `plugins/aloha-content/aloha-content.php` | Подключение, регистрация meta, version 0.7.3 |
| `theme/aloha-hello/inc/community.php` | Film trigger и enqueue существующего viewer; editor dependencies |
| `theme/aloha-hello/assets/js/community-editor.js` | Панель Community Media |
| `theme/aloha-hello/assets/css/community.css` | Community Play control, hover/focus и responsive text fit |
| `theme/aloha-hello/style.css` | Version 0.7.1 |
| `scripts/community-film-check.php` | Изолированные server/revision/permissions/atomicity fixtures и cleanup |
| `scripts/community-film-browser.cjs` | Gutenberg, privacy, geometry, shared viewer и mobile acceptance |
| `scripts/test-aloha-content.sh` | Opt-in film suite; включает Community foundation |
| `README.md`, этот отчёт | Инструкция, QA и evidence |

## Точечный QA

На отдельном WordPress 8091 пройдены **39 server checks** и **11 browser scenario groups**. Проверяются URL forms/types, отсутствие backfill, атомарный отказ update/create, meta permissions, anonymous/subscriber/author denial, нативные media-only revisions и restore, независимость двух Community, draft/editor save/reopen/replace/remove, body/Area preservation, invalid legacy URL и повторная валидация на render.

Geometry checks на **1440/1024/768/390/320 px**, с фото и без: left/bottom 24 px, circle 59 × 59 px, gap 12 px, 18 px / 600, Mirage. Дополнительно 200% текста на всех ширинах и длинный override на 320 px. При первом прогоне обнаружено обрезание no-image panel на 320 px / 200%; исправлено в CSS через адаптивную минимальную высоту, повторный прогон прошёл.

Браузер подтверждает 0 YouTube-запросов и 0 iframe до активации, один native modal, privacy-enhanced src, Enter/Tab/Escape/Close, удаление iframe, возврат фокуса и стабильный hover. Второй Community открывает другой ID; Area film → Gallery photo → Area film переключаются внутри одного viewer без старого iframe/режима. Touch на 390 px открывает viewer и позволяет нажать Close. Invalid Gutenberg blocks и JS page errors отсутствуют.

Диагностический стенд очищен с проверкой baseline fingerprints. Основной базе не назначались тестовые URL.

## Известные ограничения

- Исходный дизайн показывает «90 seconds», но не содержит видеофайла/реального YouTube источника. Default следует дизайну; менеджер должен переопределить подпись, если фактическая длительность другая.
- Во всех тестах синтетические ID `AlohaTest01/02/03` существуют только в одноразовой БД. Запросы YouTube перехватываются локальным player test double с явной надписью; проверены реальный iframe URL и lifecycle, но не доступность внешнего ролика, региональные ограничения, разрешение embedding или воспроизведение на серверах YouTube.
- Escape использует прежнее нативное поведение общего dialog. Cross-origin player управляет клавишами, пока фокус внутри него; доступная кнопка Close остаётся в родительском dialog. Отдельный trap или управление YouTube Player API не добавлены.
- Проверены Chromium и touch emulation, не физические iOS/Android, Safari/WebKit или Firefox.
- Основные CTA Community остаются информационными. Background HTML5 video, B2, GuestyBridge и другие секции не реализованы.

## Финальные регрессии и сохранность

Полный штатный runner завершился успешно на новой одноразовой установке **WordPress 7.1.3 / PHP 8.3**, порт **8091**:

| Набор | Server checks | Browser scenario groups |
| --- | --- | --- |
| Community film | 39 | 11 |
| Community foundation B1 | 17 | 8 |
| Area presentation | 23 | 11 |
| Area Hero / Area Media | 48 | 17 |
| Media Gallery / shared viewer | 41 | 13 |

Также пройдены **88 Aloha Content model checks**, **75 Area routing**, **22 Community routing**, **78 Area/Community HTTP checks** в четырёх фазах; существующие Guide/Investor routes и Routing Overview checks сохранены. Theme switch, saved-empty, изменения/удаление Starter sections и native revisions остаются рабочими. Invalid blocks и page errors отсутствуют. PHP debug log финального стенда — **0 bytes**. PHP/JS/Bash syntax checks и `git diff --check` пройдены.

После тестов проверены исходные fingerprints контента/настроек; все film/Community/Area fixtures и импортированные media удалены. Одноразовые контейнеры, volumes и credentials удалены. Остались только прежние контейнеры основной установки 8088.

Основные `posts`, `postmeta`, `terms`, `term_taxonomy`, `term_relationships` и Reading/permalink/routing/shell options до/после равны. SHA-256 нормализованного снимка: `c60b9bb286fe6aac71afbb3ff84b785ab9de31953dc00a01c35511a63df8d44c`. Основной Home возвращает **HTTP 200**. Реальные Community, включая Ka Milo, не получали тестовый URL и не редактировались.

Оригинальные PNG/ZIP не изменены. `check-references.py` сообщает только ранее отсутствующий `aloha-architecture-sources.zip`; остальные присутствующие reference files совпадают с manifest.

Evidence находится локально в игнорируемой `.local/evidence/m1-5-b1-1/`:

- [Hero рядом с финальным PNG](../.local/evidence/m1-5-b1-1/community-film-comparison.png).
- [Hero с тестовым Featured Image и film control](../.local/evidence/m1-5-b1-1/community-film-image-1440-viewport.png).
- [Открытый viewer — desktop](../.local/evidence/m1-5-b1-1/community-film-dialog-desktop.png), [mobile](../.local/evidence/m1-5-b1-1/community-film-dialog-mobile.png). Внутри iframe явно подписанный local player test double.
- [Панель Gutenberg Community Media](../.local/evidence/m1-5-b1-1/community-film-editor.png).
- [Focus](../.local/evidence/m1-5-b1-1/community-film-focus.png), [mobile Hero 320](../.local/evidence/m1-5-b1-1/community-film-no-image-320.png), [long label / 200%](../.local/evidence/m1-5-b1-1/community-film-long-label-320-text200.png).
- [Film server report](../.local/evidence/m1-5-b1-1/community-film-server-report.json), [film browser report](../.local/evidence/m1-5-b1-1/community-film-browser-report.json), [основная БД](../.local/evidence/m1-5-b1-1/main-preservation-report.json), [integration log](../.local/evidence/m1-5-b1-1/integration.log).

Повторный запуск с теми же регрессиями:

```bash
DOCKER_CONTEXT=orbstack CONTENT_COMMUNITY_FILM_TEST=1 CONTENT_GALLERY_TEST=1 \
  CONTENT_BROWSER_TEST=1 CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-b1-1 \
  PLAYWRIGHT_MODULE=/path/to/node_modules/playwright ./scripts/test-aloha-content.sh
```

Нужны supplied Area JPG в `.local/reference-area/assets` либо `ALOHA_AREA_MEDIA_DIR`. Без `CONTENT_GALLERY_TEST=1` film opt-in включает Community foundation и базовые Aloha Content/Area regressions; gallery flag добавляет прежние Gallery и Hero suites. Push и deployment не выполнялись.
