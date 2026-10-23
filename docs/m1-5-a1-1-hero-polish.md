# M1.5-A.1.1 — Production polish Area Hero

23.10.2026. Aloha Hello **0.3.2**, Aloha Content **0.7.1**. Ветка `codex/m1-5-a1-interactive-hero`. Перед изменениями M1.5-A.1 проверен и сохранён отдельным локальным коммитом **`82dbfdf`** (`feat(aloha): add interactive Area Hero media`). Эта доработка сохраняется следующим отдельным коммитом; push/deployment не выполняются.

## Изменения

- Вместо текстовой кнопки background video — **иконка 40×40px** в правом верхнем углу Hero. SVG показывает Pause либо Resume. `aria-label` и `title` обновляются вместе с состоянием; доступна контрастная keyboard focus outline. Контрол не занимает строку главных CTA и не требует прежнего дополнительного mobile padding.
- Контрол появляется только после успешного playback действительного видео. При отсутствии видео его нет в HTML; при reduced motion, Save-Data, невозможном autoplay или ошибке он скрыт. Ручная пауза сохраняется при скролле и возвращении Hero в viewport. Условия загрузки, постер, IntersectionObserver и film dialog не менялись.
- Hero больше не выводит caption overlay фотографии. Сама фотография, Featured Image, attachment caption/description/alt и provenance meta не изменяются. Caption остаётся доступным в Media Library.
- Стандартный film label: **Watch the area film · 2 min**. В **Areas → Edit → Area Media → Film Label** менеджер может задать другой текст. При очищенном поле frontend использует стандартную подпись. Неверный/отсутствующий YouTube URL по-прежнему не создаёт film button.
- Hover и keyboard focus мягко меняют opacity до `.88` за **180ms**. Размер, padding, положение и иконка не меняются; underline/transform/scale не используются. При `prefers-reduced-motion` transition отключён.

## Новое поле

`aloha_area_film_label` зарегистрировано через Post Meta API в Aloha Content: `string`, `single`, `show_in_rest`, `revisions_enabled`, `sanitize_text_field`, capability `edit_post` конкретной Area. Значение по умолчанию задано константой `Aloha\Content\AREA_FILM_LABEL_DEFAULT` и доступно через REST/Meta API **без массового заполнения БД**. Панель Gutenberg показывает его сразу у записей без override. Пустая пользовательская строка остаётся пустой в редакторе и даёт default label на frontend.

HTML не принимается как оформление: label хранится как plain text и выводится через `esc_html()`. Native revisions сохраняют и восстанавливают label вместе с существующими video ID и film URL. Новых settings options, таблиц или миграции post content нет.

## Проверки

Целевой disposable прогон прошёл: **48 server + 17 browser** проверок Hero, без JavaScript page errors; дополнительно **23 server** проверки Area presentation. Проверены:

- Video/poster/no-video, ошибки и отказ autoplay, reduced motion и network saving.
- Icon-only control, `aria-label`/`title`, Resume, сохранение ручной паузы после скролла.
- Default/custom/cleared label, сохранение и повторное открытие в Gutenberg, sanitization и восстановление native revision.
- Hover/focus без изменения geometry, modal lifecycle/focus return/iframe cleanup.
- 1440/1024/768/390/320, 200% текста, cover framing, отсутствие overflow и пересечений с CTA.
- Отсутствие badge на frontend при сохранённом caption в Media Library.

Повторный полный прогон **прошёл**, `.local/content-test-xNPsfV`: **48 Hero server + 17 Hero browser**, **23 Area server + 11 Area browser**, прежние **275 Aloha Content server + 226 HTTP + 32 browser**. JavaScript page errors отсутствуют, PHP log **0 bytes**. Сохранность body после смены темы/обновления Patterns подтверждена. Fixtures и медиа удалены, исходный baseline сохранён, временные контейнеры и тома удалены. PHP/JS/Bash syntax и `git diff --check` прошли.

Первый полный запуск остановился на одном существующем Guide HTTP assertion: первый article URL после переключения Guide base на `travel` вернул 404 вместо 200. Без изменения production routing и HTTP assertions новый полный запуск прошёл, включая все 11 segment checks; сбой не повторился, его первопричина не установлена. Лог первого запуска сохранён как `integration-first-attempt.log`, успешного — `integration.log`. Это наблюдение оставлено для последующей диагностики отдельно от Hero polish; routing fix в этот коммит не включён.

На основной установке [Mauna Lani](http://127.0.0.1:8088/big-island/mauna-lani/) уже содержит выбранное пользователем видео **attachment 84** и действительную film URL. Они сохранены. Публичная страница проверена на пяти размерах с реальным playback, без обращений к YouTube до клика; [Gutenberg Area 68](http://127.0.0.1:8088/wp-admin/post.php?post=68&action=edit) показывает Film Label и **162 валидных блока**.

Read-only SQL-сравнение до/после: **все 76 post rows неизменны**, post content Area 68 SHA-256 `7e8e093f035e2dc403bfb8c7742d3a03e3e844feb7feb58a49a339d2ec8bdead`. Featured Image, редакционные meta, существующие video/film URL, attachment captions/provenance, Navigation, terms и permalink/rewrite/shell settings сохранены. Исключён только штатный editor lock. Новых записей или attachments в основной БД нет, override Film Label в неё не записывался.

Скриншоты/JSON/logs — **`.local/evidence/m1-5-a1-1/`**, вне Git:

- Mauna Lani: [desktop](../.local/evidence/m1-5-a1-1/mauna-lani-1440.png), [mobile](../.local/evidence/m1-5-a1-1/mauna-lani-320.png), [редактор](../.local/evidence/m1-5-a1-1/mauna-lani-editor.png); также 1024/768/390.
- Изолированные fixtures: [custom label](../.local/evidence/m1-5-a1-1/hero-custom-film-label.png), [hover](../.local/evidence/m1-5-a1-1/hero-film-hover.png), [keyboard focus](../.local/evidence/m1-5-a1-1/hero-film-focus.png), [Area Media](../.local/evidence/m1-5-a1-1/hero-editor.png), [modal](../.local/evidence/m1-5-a1-1/hero-modal-desktop.png).

YouTube lifecycle тестируется через локальный iframe test double, без внешнего сервера; реальный YouTube URL пользователя не подменялся. Browser QA — Chromium. Ограничения native cross-origin player/keyboard из отчёта M1.5-A.1 сохраняются.

`scripts/check-references.py`: прежняя единственная ошибка — отсутствующий исторический `aloha-architecture-sources.zip`. Оригиналы и manifest не изменялись.

## Файлы и границы

`plugins/aloha-content/inc/area-media.php`, version в `aloha-content.php`; `theme/aloha-hello/inc/area.php`, `assets/js/area-editor.js`, `assets/js/area-hero.js`, `assets/css/area.css`, version в `style.css`; тесты `scripts/area-hero-check.php`, `scripts/area-hero-browser.cjs`; этот отчёт и ссылка из предыдущего.

`media-dialog.js`, `single-area.html`, starter/Patterns, маршрутизация и содержимое записей не менялись. Media Gallery, M1.5-A.2, GuestyBridge и другие frontend sections не начинались.
