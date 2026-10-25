# M1.5-B1.1a — Community Hero film polish

## Результат и дизайн

Работа выполнена в `codex/m1-5-b1-1-community-film` поверх отдельно сохранённого B1.1 **`9c6c218`**. Aloha Hello **0.7.2**; Aloha Content не менялся.

Сверены финальный `макеты/Design v2 — Community Ka Milo@1x.png` и `CommunityDesign.dc.html`. Сохранена геометрия Community: медиапанель около **608 × 560 px** на 1440, film CTA слева и снизу по **24 px**, круг **59 × 59 px**, border **1.5 px**, зазор **12 px**, Outfit **18 px / 600 / 1.6**. Дефолт подписи остаётся «{Community} in 90 seconds». В этом уточнении цвет и характер overlay следуют новому заданию B1.1a: белая CTA поверх общего затемнения фотографии.

Локальная размытая белая подложка удалена. У медиапанели с `.aloha-community-film` появляется общий `::after` с `inset: 0`: градиент Mirage **#16232A**, opacity **66% → 72% → 86%** сверху вниз. Затемнение покрывает всю фотографию, повторяет её border radius и не перехватывает нажатия. CTA находится выше overlay; Play, обводка и подпись — **#fff**. Фотография, пропорции колонок, позиция кнопки и другие CTA не изменены. CSS Area Hero не копировался и не редактировался.

У круга лёгкий белый background **12%**, при hover/focus — **20%**; opacity кнопки плавно меняется до **.94** за **180 ms**. Нет translate/scale или изменения размеров. `focus-visible` имеет белый outline **2 px**, offset **6 px** и тёмную контрастную подложку. Переходы объявлены только для `prefers-reduced-motion: no-preference`; при `reduce` они отсутствуют.

Условие затемнения — фактическое наличие film trigger внутри панели. Существующий PHP выводит его только при корректном YouTube URL. Пустой или невалидный URL оставляет исходную фотографию без overlay и без зарезервированного места для CTA. Это проверено сравнением пикселей с CSS B1.1. Если URL есть, но Featured Image отсутствует, тот же scrim применяется к существующей нейтральной панели: белый control остаётся читаемым.

Схема meta, Gutenberg panel, валидатор, ревизии, REST, `single-community.html`, starter, routing и `media-dialog.js` не менялись. Общий dialog по-прежнему создаёт privacy-enhanced iframe после активации, удаляет его при закрытии и возвращает фокус.

## Изменённые файлы

| Файл | Изменение |
| --- | --- |
| `theme/aloha-hello/assets/css/community.css` | Условный общий scrim, белая film CTA, hover/focus/reduced motion |
| `theme/aloha-hello/style.css` | Версия темы 0.7.2; stylesheet cache Community обновляется по существующему filemtime |
| `scripts/community-film-check.php` | Fixture без URL теперь использует то же фото для проверки отсутствия затемнения |
| `scripts/community-film-browser.cjs` | Проверки overlay/цветов, baseline screenshots, no-URL pixel comparison, пяти ширин, hover/focus/contrast/reduced motion |
| `scripts/community-foundation-browser.cjs` | Прежний focus-тест основных CTA теперь использует настоящий Tab вместо программного `.focus()` |
| `README.md` | Краткая инструкция и ссылка на этот отчёт |
| `docs/m1-5-b1-1a-community-film-polish.md` | Результат, evidence и воспроизведение |

## Скриншоты

Все снимки получены на изолированном WordPress **8091**. «До» использует точный CSS из `9c6c218`, подставленный браузерным route в тот же fixture; контент, фото, viewport и шрифты одинаковы. Это реальный render прежнего состояния, без ретуши. Evidence хранится локально в игнорируемой `.local/evidence/m1-5-b1-1a/`.

- [До/после рядом](../.local/evidence/m1-5-b1-1a/community-film-polish-before-after.png).
- [До — B1.1, 1440](../.local/evidence/m1-5-b1-1a/community-film-before-1440.png).
- [После — Hero с film CTA, 1440](../.local/evidence/m1-5-b1-1a/community-film-image-1440-viewport.png).
- [Hero без film URL, 1440](../.local/evidence/m1-5-b1-1a/community-film-no-url-1440.png).
- [Hover](../.local/evidence/m1-5-b1-1a/community-film-hover.png), [клавиатурный focus](../.local/evidence/m1-5-b1-1a/community-film-focus.png).
- [Mobile 390](../.local/evidence/m1-5-b1-1a/community-film-image-390.png), [320](../.local/evidence/m1-5-b1-1a/community-film-image-320.png), [320 / длинная подпись / 200% текста](../.local/evidence/m1-5-b1-1a/community-film-long-label-320-text200.png).
- [Без URL / 320 / 200% текста](../.local/evidence/m1-5-b1-1a/community-film-no-url-text200-320.png).
- [Общий viewer desktop](../.local/evidence/m1-5-b1-1a/community-film-dialog-desktop.png), [touch mobile](../.local/evidence/m1-5-b1-1a/community-film-dialog-mobile.png), [Gutenberg Community Media](../.local/evidence/m1-5-b1-1a/community-film-editor.png).

## Проверки

На изолированном **WordPress 7.1.3 / PHP 8.3**, порт 8091, пройдены следующие проверки. Результат подтверждён общим regression run и последующим точечным Community run; точечный runner завершился с **exit 0**.

| Набор | Server checks | Browser scenario groups |
| --- | --- | --- |
| Community film | 39 | 14 |
| Community foundation | 17 | 8 |
| Area presentation | 23 | 11 |
| Area Hero / Area Media | 48 | 17 |
| Media Gallery / shared dialog | 41 | 13 |

Также прошли **88 Aloha Content model checks**, **75 Area routing**, **22 Community routing**, **78 Area/Community HTTP checks**; прежние Guide/Investor routes, Routing Overview и Gutenberg suites. Оба подтверждающих стенда имеют пустые PHP debug logs (**0 bytes**); browser page errors отсутствуют. PHP/JS/Bash syntax checks и `git diff --check` пройдены.

Два первых запуска выявили нестабильность OrbStack DNS для hostname `db`: первый остановился при подключении WP-CLI, второй прошёл все функциональные проверки, но завершился с ошибкой на финальном debug-log guard из-за тех же предупреждений. Для следующих прогонов использована локальная копия штатного runner с единственным отличием настройки сети: фиксированный private IP сервиса DB и `extra_hosts: db:IP` только у одноразовых WordPress/CLI. Настройка сети не меняет assertions; основные Docker services и приложение не правились. [Первый лог](../.local/evidence/m1-5-b1-1a/infrastructure-retry.log), [второй лог](../.local/evidence/m1-5-b1-1a/dns-warnings.log), [копия runner для аудита](../.local/evidence/m1-5-b1-1a/test-runner-static-host-reference.sh).

Следующий общий прогон прошёл Aloha Content, маршруты, Area presentation, Area Hero и Gallery без PHP warnings, затем выявил нестабильность старого focus-теста Community. Программный `.focus()` после pointer-взаимодействия не гарантирует `:focus-visible` в Chromium. Тест исправлен на реальное продвижение Tab с проверкой activeElement; frontend основных CTA не менялся. После этого отдельно перепроверены **Community foundation и film**, включая theme switch и cleanup. Уже пройденные независимые suites повторно не запускались. [Общий regression log](../.local/evidence/m1-5-b1-1a/regression.log), [runner точечного прогона для аудита](../.local/evidence/m1-5-b1-1a/test-runner-community-reference.sh).

Браузерная проверка включает:

- **1440 / 1024 / 768 / 390 / 320 px** с film, с фото и без фото; белые текст/Play, scrim покрывает всю панель, прежней локальной подложки нет, исходные отступы и размеры сохранены.
- **200% текста** на всех пяти ширинах, отдельно без URL и с длинным override на 320. Подпись остаётся внутри медиапанели, горизонтального overflow нет.
- Без URL отсутствуют CTA, overlay и modal; crop фотографии побайтово совпадает с baseline B1.1.
- Hover не сдвигает кнопку, transition **.18 s**, transform **none**; настоящий Tab достигает CTA и показывает белый focus-visible. Reduced motion даёт transition **0 s** у кнопки и круга.
- Расчётный worst-case контраст подписи при hover даже на полностью белом исходном фото — **4.84:1**. Проверка использует фактические computed gradient/opacity, а не яркость выбранного тестового фото.
- Enter открывает один shared dialog, Escape/Close удаляют iframe и возвращают фокус; touch Close доступен. До активации — **0 iframe и 0 YouTube requests**.
- Gutenberg save/reopen/replace/remove, отклонение неверного URL, native revisions, независимые фильмы двух Community, сохранность Post Content и отсутствие invalid blocks.
- Area film → Gallery photo → Area film работает через прежний viewer без оставшегося iframe или photo mode.

Машинные отчёты: [film browser](../.local/evidence/m1-5-b1-1a/community-film-browser-report.json), [film server](../.local/evidence/m1-5-b1-1a/community-film-server-report.json), [integration log](../.local/evidence/m1-5-b1-1a/integration.log), [сохранность основной БД](../.local/evidence/m1-5-b1-1a/main-preservation-report.json).

Fixtures, импортированные media, одноразовые контейнеры, volumes и credentials удалены. Основные `posts`, `postmeta`, `terms`, `term_taxonomy`, `term_relationships`, а также проверенные Reading/permalink/routing/shell options до/после равны. SHA-256 нормализованного снимка: `3255e79db1a446a4d073511ec574b133436bca4216d11c95ac792201ae0182b8`. Основной Home возвращает **HTTP 200**; активны только прежние WordPress/DB основной установки 8088.

## Воспроизведение

Для снимка «до» и проверки pixel equality сначала сохранить прежний CSS, затем запустить штатный runner:

```bash
git show 9c6c218:theme/aloha-hello/assets/css/community.css > .local/community-polish-before.css
DOCKER_CONTEXT=orbstack CONTENT_COMMUNITY_FILM_TEST=1 CONTENT_GALLERY_TEST=1 \
  CONTENT_BROWSER_TEST=1 CONTENT_EVIDENCE_DIR=.local/evidence/m1-5-b1-1a \
  ALOHA_COMMUNITY_BEFORE_CSS="$PWD/.local/community-polish-before.css" \
  PLAYWRIGHT_MODULE=/path/to/node_modules/playwright ./scripts/test-aloha-content.sh
```

Baseline CSS необязателен для обычных последующих регрессий. Нужны локальный Chromium и supplied Area JPG в `.local/reference-area/assets` либо `ALOHA_AREA_MEDIA_DIR`. Film opt-in включает Community foundation; gallery flag добавляет прежние Area Hero и Gallery suites. Runner создаёт отдельные WP/DB volumes на 8091, затем удаляет fixtures и стенд.

На этой машине для обхода OrbStack DNS использована `.local/community-polish-tests-static-host.sh` вместо `./scripts/test-aloha-content.sh` с теми же environment flags. Точечный повтор после исправления focus-теста выполнен через `.local/community-polish-tests-community.sh` с `CONTENT_COMMUNITY_FILM_TEST=1 CONTENT_BROWSER_TEST=1` и теми же baseline/evidence/Playwright flags. Сохранённые evidence-копии служат для аудита; исполняемые копии находятся непосредственно в `.local`, чтобы прежний `cd` runner указывал на корень проекта.

## Ограничения и границы этапа

- Проверен Chromium с touch emulation; физические устройства и Safari/Firefox в этот прогон не входят.
- Тестовые синтетические YouTube IDs существуют только в одноразовой БД. Сеть player перехватывает явно подписанный local test double. Проверены настоящий embed URL и lifecycle; доступность реального внешнего ролика и воспроизведение YouTube не проверялись.
- Фото взято из supplied design assets и используется только как тестовое; оно не подтверждает фактическое фото Ka Milo. Реальной Ka Milo тестовое видео не назначалось.
- B2, GuestyBridge, новые секции и другие CTA не реализовывались. Push и deployment не выполнялись.
