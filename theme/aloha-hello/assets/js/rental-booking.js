/* Local flatpickr is a date surface. Bridge remains the authority for eligibility and money. */
(() => {
    'use strict';
    const root = document.querySelector('[data-booking-config]');
    if (!root || typeof window.flatpickr !== 'function') return;
    const config = JSON.parse(root.dataset.bookingConfig);
    const dialog = root.querySelector('.aloha-booking-calendar');
    const find = selector => root.querySelector(selector) || dialog.querySelector(selector);
    document.body.append(dialog); // Escape the sticky card's overflow container.
    const arrow = document.createElement('span'); arrow.className = 'aloha-calendar-arrow'; arrow.setAttribute('aria-hidden', 'true'); arrow.hidden = true; document.body.append(arrow);
    const surface = find('[data-calendar-surface]');
    const action = find('[data-booking-action]');
    const guestDetails = find('.aloha-booking-guests');
    const inputs = {adults: find('[data-guests="adults"]'), children: find('[data-guests="children"]')};
    const originalHeading = find('[data-booking-heading]').cloneNode(true);
    const originalSave = find('[data-booking-save]').cloneNode(true);
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const state = {in: '', out: '', stage: 'in', days: new Map(), rules: null, total: null, busy: false, calendarBusy: false,
        priceGeneration: 0, calendarGeneration: 0, priceController: null, calendarController: null};
    let picker;
    let preview = '';
    let visibleMonth, monthAnimation, healedMonth;
    const arrivals = new Map(), departures = new Map();
    let returnFocus;
    const lookupDay = value => state.days.get(value); // Stable observation for this page; pricing rechecks the chosen stay.
    const iso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const date = value => new Date(`${value}T12:00:00`);
    const add = (value, n) => { const d = date(value); d.setDate(d.getDate() + n); return iso(d); };
    const nights = (start, end) => (Date.parse(`${end}T00:00:00Z`) - Date.parse(`${start}T00:00:00Z`)) / 86400000;
    const locale = (document.documentElement.lang || config.locale || 'en-US').replace('_', '-');
    const money = (amount, currency, compact = false) => new Intl.NumberFormat(locale, {
        style: 'currency', currency, ...(compact ? {maximumFractionDigits: 0, minimumFractionDigits: 0} : {}),
    }).format(Number(amount)); // Formatting only; all sums/discounts/rounding are server-side integer arithmetic.
    const shortDate = value => new Intl.DateTimeFormat(locale, {month: 'short', day: 'numeric'}).format(date(value));
    const message = text => { find('[data-booking-status]').textContent = text; };
    const calendarHelp = text => { find('[data-calendar-help]').textContent = text; };
    function invalidate() {
        state.priceController?.abort(); state.priceGeneration++;
        state.total = null; state.busy = false;
        find('[data-booking-price]').hidden = true;
        find('.aloha-booking-breakdown').open = false;
        action.disabled = false; action.querySelector('.aloha-booking-spinner').hidden = true;
        find('[data-action-text]').textContent = 'Check availability';
        find('[data-booking-charge]').hidden = true;
        const save = find('[data-booking-save]');
        save.replaceChildren(...[...originalSave.childNodes].map(node => node.cloneNode(true))); save.hidden = false;
        message('');
    }
    function updateDates() {
        find('[data-check-in]').textContent = state.in ? shortDate(state.in) : 'Add date';
        find('[data-check-out]').textContent = state.out ? shortDate(state.out) : 'Add date';
        const heading = find('[data-booking-heading]');
        heading.classList.toggle('aloha-booking-period', Boolean(state.in && state.out));
        if (state.in && state.out) {
            const start = date(state.in), end = date(state.out), count = nights(state.in, state.out);
            const formatter = new Intl.DateTimeFormat(locale, {month: 'short', day: 'numeric', ...(start.getFullYear() !== end.getFullYear() ? {year: 'numeric'} : {})});
            const period = document.createElement('strong');
            period.textContent = formatter.formatRange(start, end).replace(/\s*–\s*/g, ' – ');
            const length = document.createElement('span');
            length.textContent = '· ' + window.wp.i18n.sprintf(window.wp.i18n._n('%s night', '%s nights', count, 'aloha-hello'), new Intl.NumberFormat(locale).format(count));
            heading.replaceChildren(period, length);
        } else heading.replaceChildren(...[...originalHeading.childNodes].map(node => node.cloneNode(true)));
    }
    function party() {
        const adults = Number(inputs.adults.value), children = Number(inputs.children.value);
        const capacity = state.rules?.capacity ?? Number(inputs.adults.max);
        if (!Number.isInteger(adults) || !Number.isInteger(children) || adults < 1 || children < 0 || adults + children > capacity
            || (children > 0 && state.rules?.suitableForChildren === false)) return null;
        return {adults, children};
    }
    function guestButtons() {
        const total = Number(inputs.adults.value) + Number(inputs.children.value);
        const capacity = state.rules?.capacity ?? Number(inputs.adults.max);
        root.querySelectorAll('[data-guest-step]').forEach(button => {
            const [kind, direction] = button.dataset.guestStep.split(':');
            button.disabled = Number(direction) < 0 ? Number(inputs[kind].value) <= (kind === 'adults' ? 1 : 0)
                : total >= capacity || (kind === 'children' && state.rules?.suitableForChildren === false);
        });
    }
    root.querySelectorAll('[data-guest-step]').forEach(button => button.addEventListener('click', () => {
        const [kind, direction] = button.dataset.guestStep.split(':');
        inputs[kind].value = String(Number(inputs[kind].value) + Number(direction));
        inputs[kind].dispatchEvent(new Event('input', {bubbles: true}));
    }));
    function stayReason(start, end) {
        const rules = state.rules, arrival = lookupDay(start), departure = lookupDay(end);
        if (!rules || !arrival || !departure || arrival.status === 'unknown' || departure.status === 'unknown') return 'Availability not confirmed';
        if (start < rules.earliestCheckIn || end > rules.windowEnd) return 'Outside booking window';
        if (arrival.cta !== false) return 'Check-in not allowed';
        const count = nights(start, end);
        if (!arrival.minNights) return 'Availability not confirmed';
        if (count < arrival.minNights) return `Min ${arrival.minNights} nights`;
        if (count > Math.min(arrival.maxNights ?? rules.maxNights, rules.maxNights)) return 'Maximum stay exceeded';
        if (departure.ctd !== false) return 'Checkout not allowed';
        for (let night = start; night < end; night = add(night, 1)) {
            const row = lookupDay(night);
            if (!row || row.status === 'unknown') return 'Availability not confirmed';
            if (row.status !== 'available') return row.reason === 'already_booked' ? 'Unavailable — already booked' : 'Unavailable';
        }
        return '';
    }
    function arrivalReason(value) {
        if (arrivals.has(value)) return arrivals.get(value);
        const row = lookupDay(value), rules = state.rules;
        let reason = '';
        if (!row || row.status === 'unknown' || !rules) reason = 'Availability not confirmed';
        else if (value < rules.earliestCheckIn || value >= rules.windowEnd) reason = 'Outside booking window';
        else if (row.status !== 'available') reason = row.reason === 'already_booked' ? 'Unavailable — already booked' : 'Unavailable';
        else if (row.cta !== false) reason = 'Check-in not allowed';
        else if (!row.minNights) reason = 'Availability not confirmed';
        else {
            reason = `Min ${row.minNights} nights`;
            const max = Math.min(row.maxNights ?? rules.maxNights, rules.maxNights);
            for (let n = 1; n <= max && add(value, n) <= rules.windowEnd; n++) {
                if (n >= row.minNights && !stayReason(value, add(value, n))) { reason = ''; break; }
                // A closed night can be checkout, but cannot be crossed to reach a later one.
                if (lookupDay(add(value, n))?.status !== 'available') break;
            }
        }
        arrivals.set(value, reason); return reason;
    }
    function reasonFor(value) {
        if (state.calendarBusy) return 'Loading availability…';
        if (state.stage !== 'out' || !state.in) return arrivalReason(value);
        if (!departures.has(value)) departures.set(value, stayReason(state.in, value));
        return departures.get(value);
    }
    const disabled = day => Boolean(reasonFor(iso(day)));
    function minimumNote(reason = '') {
        find('[data-calendar-note]').textContent = reason || (state.in ? `Min ${lookupDay(state.in)?.minNights ?? '—'} nights` : 'Minimum stay varies by date');
    }
    function paintRange() {
        const end = preview || state.out;
        surface.querySelectorAll('.flatpickr-day').forEach(cell => {
            const d = cell.dataset.date;
            cell.classList.toggle('aloha-in-range', Boolean(state.in && end && d >= state.in && d <= end));
            cell.classList.toggle('aloha-range-start', d === state.in);
            cell.classList.toggle('aloha-range-end', d === state.out);
        });
    }
    function previewDay(value) {
        const reason = reasonFor(value);
        preview = state.stage === 'out' && !reason ? value : '';
        paintRange(); minimumNote(reason);
    }
    function dayCell(_dates, _text, _fp, cell) {
        const d = iso(cell.dateObj), row = lookupDay(d);
        cell.dataset.date = d;
        const unknown = !row || row.status === 'unknown';
        cell.dataset.calendarLabel ??= cell.getAttribute('aria-label');
        cell.setAttribute('aria-label', cell.dataset.calendarLabel);
        cell.tabIndex = -1;
        const canClear = d === state.in;
        const reason = canClear ? 'Clear selected dates' : reasonFor(d);
        const blocked = !canClear && disabled(cell.dateObj);
        cell.classList.toggle('flatpickr-disabled', blocked);
        if (blocked) cell.setAttribute('aria-disabled', 'true'); else cell.removeAttribute('aria-disabled');
        cell.classList.toggle('aloha-available', row?.status === 'available');
        cell.title = reason || (state.stage === 'in' && row?.minNights ? `Min ${row.minNights} nights` : 'Select checkout');
        cell.setAttribute('aria-label', `${cell.getAttribute('aria-label')}, ${cell.title}`);
        cell.classList.toggle('aloha-unavailable', row?.status === 'unavailable');
        cell.classList.toggle('aloha-unknown', unknown);
        const checkoutOnly = state.stage === 'out' && row?.status === 'unavailable' && !disabled(cell.dateObj);
        cell.classList.toggle('aloha-checkout-only', checkoutOnly);
        if (checkoutOnly) {
            cell.title = 'Checkout only';
            cell.setAttribute('aria-label', `${cell.getAttribute('aria-label')}, checkout only; night unavailable`);
        } else if (unknown) cell.setAttribute('aria-label', `${cell.getAttribute('aria-label')}, Availability not confirmed`);
        cell.classList.toggle('aloha-range-start', d === state.in);
        cell.classList.toggle('aloha-range-end', d === state.out);
        cell.classList.toggle('aloha-in-range', Boolean(state.in && (preview || state.out) && d >= state.in && d <= (preview || state.out)));
        if (d === state.in || d === state.out) cell.setAttribute('aria-selected', 'true'); else cell.removeAttribute('aria-selected');
    }
    async function get(endpoint, params, signal) {
        const url = new URL(config.api + endpoint);
        for (const [key, value] of Object.entries({...params, ...config.preview})) url.searchParams.set(key, String(value));
        const response = await fetch(url, {signal, credentials: 'same-origin', headers: config.nonce ? {'X-WP-Nonce': config.nonce} : {}});
        const payload = await response.json();
        if (!response.ok || payload.status === 'unavailable') throw Object.assign(new Error(payload.message || 'Temporarily unavailable. Please retry.'), {payload});
        return payload;
    }
    function receiveCalendar(payload, replace = false) {
        if (payload.rentalId !== config.rentalId || !payload.rules || !Array.isArray(payload.days)) throw new Error('Calendar temporarily unavailable.');
        arrivals.clear(); departures.clear();
        if (replace) state.days.clear();
        state.rules = payload.rules;
        payload.days.forEach(row => state.days.set(row.date, row));
        inputs.adults.max = String(payload.rules.capacity); inputs.children.max = String(payload.rules.capacity);
        inputs.children.disabled = payload.rules.suitableForChildren === false; guestButtons();
        find('[data-guests-help]').textContent = payload.rules.suitableForChildren === false
            ? `Maximum ${payload.rules.capacity} guests. This home is not suitable for children.`
            : `Maximum ${payload.rules.capacity} guests. Guest composition is confirmed during booking.`;
        if (picker) { picker.set('minDate', payload.rules.today); picker.set('maxDate', payload.rules.windowEnd); }
    }
    function focusDay() {
        const enabled = [...surface.querySelectorAll('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)')];
        const chosen = state.stage === 'out' ? (state.out || state.in) : state.in;
        const selected = [...surface.querySelectorAll('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)')].find(cell => cell.dataset.date === chosen);
        const first = selected || enabled[0];
        if (first) { first.tabIndex = 0; first.focus({preventScroll: true}); }
        else find('[data-calendar-close]').focus({preventScroll: true});
    }
    async function loadCalendar() {
        // Once loaded, never replace this page's observation on navigation/open/resize/timer.
        if (state.rules) { picker.redraw(); return; }
        if (state.calendarBusy) return;
        state.calendarController = new AbortController(); const generation = ++state.calendarGeneration;
        const first = iso(new Date(picker.currentYear, picker.currentMonth, 1, 12));
        const to = add(first, 62);
        state.calendarBusy = true;
        find('[data-calendar-loading]').hidden = false; find('[data-calendar-retry]').hidden = true;
        surface.setAttribute('aria-busy', 'true'); picker.redraw();
        try {
            const payload = await get('availability', {from: first, to}, state.calendarController.signal);
            if (generation !== state.calendarGeneration) return;
            receiveCalendar(payload); calendarHelp('Choose check-in, then checkout.');
        } catch (error) {
            if (generation !== state.calendarGeneration || error.name === 'AbortError') return;
            calendarHelp(error.message || 'Calendar temporarily unavailable.');
            find('[data-calendar-help]').classList.remove('screen-reader-text');
            find('[data-calendar-retry]').hidden = false;
        } finally {
            if (generation === state.calendarGeneration) {
                state.calendarBusy = false; find('[data-calendar-loading]').hidden = true;
                surface.setAttribute('aria-busy', 'false'); picker.redraw();
                positionCalendar(); if (!dialog.hidden) focusDay();
            }
        }
    }
    function refreshDays() {
        surface.querySelectorAll('.flatpickr-day').forEach(cell => dayCell([], '', picker, cell));
        minimumNote();
    }
    function chooseDay(value) {
        // The selected arrival is a cancellation action, even when a zero-night stay is disabled.
        if (state.in && value === state.in) {
            invalidate(); state.in = state.out = ''; state.stage = 'in';
            preview = ''; departures.clear(); refreshDays(); updateDates();
            calendarHelp('Choose check-in, then checkout.'); focusDay(); return;
        }
        if (disabled(date(value))) { previewDay(value); calendarHelp(reasonFor(value)); return; }
        invalidate();
        const complete = state.stage === 'out' && Boolean(state.in);
        if (!complete) {
            state.in = value; state.out = ''; state.stage = 'out';
            calendarHelp(`Choose checkout. Minimum stay: ${lookupDay(value).minNights} nights.`);
        } else state.out = value;
        preview = ''; departures.clear();
        // Selection belongs to our existing state machine. Keep the visible month DOM intact.
        refreshDays(); updateDates();
        if (complete) { closeCalendar(); calculate(); }
        else focusDay();
    }
    function monthChanged() {
        preview = ''; refreshDays(); positionCalendar();
        const next = picker.currentYear * 12 + picker.currentMonth;
        const direction = visibleMonth === undefined || next > visibleMonth ? 1 : -1;
        const changed = next !== visibleMonth; visibleMonth = next;
        if (changed) monthAnimation?.cancel();
        if (changed && !dialog.hidden && !reducedMotion.matches) {
            monthAnimation = surface.querySelector('.flatpickr-rContainer').animate([
                {opacity: .5, transform: `translateX(${direction * 8}px)`},
                {opacity: 1, transform: 'translateX(0)'},
            ], {duration: 200, easing: 'cubic-bezier(.2,.7,.3,1)'});
        }
    }
    reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) monthAnimation?.cancel(); });
    function positionCalendar() {
        if (dialog.hidden) return;
        const fields = find('.aloha-booking-fields').getBoundingClientRect();
        const anchor = returnFocus?.getBoundingClientRect() || fields;
        const safeTop = parseFloat(root.style.getPropertyValue('--aloha-booking-offset')) || 20;
        if (anchor.bottom < safeTop || anchor.top > window.innerHeight) { closeCalendar(false); return; }
        const margin = 8, gap = 10;
        dialog.style.width = `${Math.min(window.innerWidth >= 768 ? 644 : 332, window.innerWidth - margin * 2)}px`;
        dialog.style.maxHeight = `${Math.max(120, window.innerHeight - safeTop - margin)}px`;
        const height = dialog.getBoundingClientRect().height;
        const below = anchor.bottom + gap;
        const above = anchor.top - gap - height;
        const top = below + height <= window.innerHeight - margin ? Math.max(safeTop, below)
            : above >= safeTop ? above : Math.max(safeTop, Math.min(below, window.innerHeight - margin - height));
        dialog.style.left = `${Math.max(margin, Math.min(fields.right - dialog.offsetWidth, window.innerWidth - dialog.offsetWidth - margin))}px`;
        dialog.style.top = `${top}px`;
        const under = top >= anchor.bottom, over = top + height <= anchor.top;
        arrow.hidden = !(under || over);
        arrow.dataset.side = under ? 'top' : 'bottom';
        const center = Math.max(dialog.offsetLeft + 18, Math.min(anchor.left + anchor.width / 2, dialog.offsetLeft + dialog.offsetWidth - 18));
        arrow.style.left = `${center - 5}px`; arrow.style.top = `${under ? top - 5 : top + height - 5}px`;

    }
    function closeCalendar(restoreFocus = true) {
        if (dialog.hidden) return;
        dialog.hidden = true; arrow.hidden = true; preview = ''; monthAnimation?.cancel();
        root.querySelectorAll('[data-booking-dates]').forEach(button => button.setAttribute('aria-expanded', 'false'));
        if (restoreFocus) returnFocus?.focus({preventScroll: true});
    }
    function openCalendar(stage = 'in', trigger = action) {
        returnFocus = trigger; guestDetails.open = false; preview = ''; departures.clear();
        state.stage = stage === 'out' && state.in ? 'out' : 'in';
        if (!picker) picker = window.flatpickr(find('[data-calendar-input]'), {
            inline: true, appendTo: surface, disableMobile: true, mode: 'single', dateFormat: 'Y-m-d',
            showMonths: window.innerWidth >= 768 ? 2 : 1, monthSelectorType: 'static',
            minDate: state.rules?.today || config.today, maxDate: state.rules?.windowEnd,
            disable: [disabled], ariaDateFormat: 'F j, Y',
            onDayCreate: dayCell, onMonthChange: monthChanged, onYearChange: monthChanged,
        });
        dialog.hidden = false;
        root.querySelectorAll('[data-booking-dates]').forEach(button => button.setAttribute('aria-expanded', 'true'));
        picker.jumpToDate((state.stage === 'out' && state.out) || state.in || healedMonth || config.today, false);
        visibleMonth = picker.currentYear * 12 + picker.currentMonth;
        calendarHelp(state.stage === 'out' ? `Choose checkout. Minimum stay: ${lookupDay(state.in)?.minNights ?? '—'} nights.` : 'Choose check-in, then checkout.');
        minimumNote(); positionCalendar();
        if (state.rules) { picker.redraw(); focusDay(); } else loadCalendar();
    }
    surface.addEventListener('pointerover', event => { const cell = event.target.closest('.flatpickr-day'); if (cell) previewDay(cell.dataset.date); });
    surface.addEventListener('pointerleave', () => { preview = ''; paintRange(); minimumNote(); });
    surface.addEventListener('focusin', event => { const cell = event.target.closest('.flatpickr-day'); if (cell) previewDay(cell.dataset.date); });
    surface.addEventListener('click', event => {
        const cell = event.target.closest('.flatpickr-day');
        if (cell) {
            // Capture before flatpickr's single-date handler can change the displayed month.
            event.preventDefault(); event.stopImmediatePropagation(); chooseDay(cell.dataset.date);
        }
    }, true);
    surface.addEventListener('keydown', event => {
        const cell = event.target.closest('.flatpickr-day'); if (!cell) return;
        const step = {ArrowRight: 1, ArrowLeft: -1, ArrowDown: 7, ArrowUp: -7}[event.key];
        if (step || event.key === 'Enter' || event.key === ' ') {
            event.preventDefault(); event.stopImmediatePropagation();
            if (!step) { chooseDay(cell.dataset.date); return; }
            const value = add(cell.dataset.date, step);
            if (value < state.rules.today || value > state.rules.windowEnd) return;
            let next = [...surface.querySelectorAll('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)')].find(el => el.dataset.date === value);
            if (!next) { picker.jumpToDate(value, false); next = [...surface.querySelectorAll('.flatpickr-day')].find(el => el.dataset.date === value); positionCalendar(); }
            if (next) { next.tabIndex = 0; next.focus({preventScroll: true}); }
        }
    }, true);
    document.addEventListener('keydown', event => {
        if (!dialog.hidden && event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeCalendar(); }
    }, true);
    document.addEventListener('pointerdown', event => {
        if (!dialog.hidden && !dialog.contains(event.target) && !event.target.closest('[data-booking-dates]')) closeCalendar(false);
    });
    document.addEventListener('focusin', event => {
        if (!dialog.hidden && !dialog.contains(event.target) && event.target !== returnFocus) closeCalendar(false);
    });
    find('[data-calendar-close]').addEventListener('click', () => closeCalendar());
    find('[data-calendar-retry]').addEventListener('click', () => { find('[data-calendar-help]').classList.add('screen-reader-text'); loadCalendar(); });
    root.querySelectorAll('[data-booking-dates]').forEach(button => button.addEventListener('click', () => openCalendar(button.dataset.bookingDates, button)));
    function renderPrice(payload) {
        state.total = payload;
        find('[data-total-amount]').textContent = money(payload.estimatedTotal, payload.currency);
        const lines = find('[data-price-lines]'); lines.replaceChildren();
        const items = [...payload.items.filter(item => !item.estimated), ...payload.items.filter(item => item.estimated)];
        let firstEstimate = true;
        for (const item of items) {
            const label = document.createElement('dt'); label.textContent = item.label;
            const amount = document.createElement('dd'); amount.textContent = money(item.amount, payload.currency);
            if (item.estimated) {
                label.classList.add('aloha-price-estimated'); amount.classList.add('aloha-price-estimated');
                if (firstEstimate) { label.classList.add('aloha-price-estimated-start'); amount.classList.add('aloha-price-estimated-start'); firstEstimate = false; }
            }
            lines.append(label, amount);
        }
        const label = document.createElement('dt'); label.textContent = 'Total'; label.className = 'aloha-price-total';
        const amount = document.createElement('dd'); amount.textContent = money(payload.estimatedTotal, payload.currency); amount.className = 'aloha-price-total'; lines.append(label, amount);
        const note = find('[data-price-detail-note]');
        note.textContent = payload.status === 'partial' ? config.partialNotice : '';
        note.hidden = payload.status !== 'partial';
        find('[data-booking-price]').hidden = false;
        find('[data-action-text]').textContent = 'Book now';
        find('[data-booking-charge]').hidden = false;
        const save = find('[data-booking-save]'), marketing = payload.marketing;
        // Display-only amount comes from Bridge. Comparison approval remains a production policy decision.
        if (marketing?.percent === 15 && marketing.displayOnly === true && typeof marketing.amount === 'string' && /^\d+$/.test(marketing.amount)) {
            const amount = document.createElement('strong'); amount.dataset.marketingAmount = '';
            amount.textContent = money(marketing.amount, payload.currency, true);
            save.replaceChildren('You save ', amount, ' vs the same dates on Airbnb'); save.hidden = false;
        } else save.hidden = true;
        message('');
    }
    async function calculate() {
        invalidate(); const guests = party();
        if (!guests) { message('Choose a guest count allowed for this home.'); guestDetails.open = true; return; }
        if (!state.in || !state.out) { openCalendar(state.in ? 'out' : 'in'); return; }
        state.busy = true; action.disabled = true; action.querySelector('.aloha-booking-spinner').hidden = false;
        find('[data-action-text]').textContent = 'Calculating total…'; message('Calculating total…');
        state.priceController = new AbortController(); const generation = ++state.priceGeneration;
        try {
            const payload = await get('price-details', {checkIn: state.in, checkOut: state.out, ...guests}, state.priceController.signal);
            if (generation !== state.priceGeneration) return;
            if (!['reconciled', 'partial'].includes(payload.status) || !Array.isArray(payload.items)
                || !/^[A-Z]{3}$/.test(payload.currency) || !/^\d+(?:\.\d{1,3})?$/.test(payload.estimatedTotal)
                || !Number.isFinite(Date.parse(payload.expiresAt)) || payload.rentalId !== config.rentalId || payload.dates.checkIn !== state.in || payload.dates.checkOut !== state.out
                || payload.guests.adults !== guests.adults || payload.guests.children !== guests.children || Date.parse(payload.expiresAt) <= Date.now()) throw new Error('Stale estimate. Please retry.');
            renderPrice(payload);
        } catch (error) {
            if (generation !== state.priceGeneration || error.name === 'AbortError') return;
            if (error.payload?.calendarUpdated === true) {
                const month = picker ? iso(new Date(picker.currentYear, picker.currentMonth, 1, 12)) : state.in;
                try { receiveCalendar(error.payload.calendar, true); }
                catch { message('Calendar could not be updated. Please retry the check.'); find('[data-action-text]').textContent = 'Retry'; return; }
                healedMonth = month; invalidate(); state.in = state.out = ''; state.stage = 'in'; preview = '';
                state.calendarController?.abort(); state.calendarGeneration++; state.calendarBusy = false;
                find('[data-calendar-loading]').hidden = true; surface.setAttribute('aria-busy', 'false');
                updateDates();
                if (picker) { picker.jumpToDate(month, false); visibleMonth = picker.currentYear * 12 + picker.currentMonth; refreshDays(); positionCalendar(); }
                message('These dates are no longer available. Please choose new dates.'); return;
            }
            message(error.message || 'Price temporarily unavailable. Please retry.'); find('[data-action-text]').textContent = 'Retry';
        } finally {
            if (generation === state.priceGeneration) { state.busy = false; action.disabled = false; action.querySelector('.aloha-booking-spinner').hidden = true; }
        }
    }
    action.addEventListener('click', () => {
        if (state.total) { message('Booking checkout is not enabled yet. No reservation has been created and no charge has been made.'); return; }
        if (!state.busy) calculate();
    });
    Object.values(inputs).forEach(input => input.addEventListener('input', () => {
        invalidate(); guestButtons(); const p = party();
        find('[data-guest-summary]').textContent = p ? `${p.adults} adult${p.adults === 1 ? '' : 's'}${p.children ? `, ${p.children} child${p.children === 1 ? '' : 'ren'}` : ''}` : 'Choose guests';
        if (!p) message('Choose a guest count allowed for this home.');
    }));
    find('[data-guests-done]').addEventListener('click', () => { if (!party()) { message('Choose a guest count allowed for this home.'); return; } guestDetails.open = false; if (state.in && state.out) calculate(); });
    // Measure the actual sticky header/admin bar, including height changes and scroll transitions.
    const header = document.querySelector('.aloha-site-header');
    function measure() {
        const bar = document.getElementById('wpadminbar');
        const barBottom = bar && getComputedStyle(bar).position === 'fixed' ? Math.max(0, bar.getBoundingClientRect().bottom) : 0;
        const wrapper = header?.closest('.wp-block-template-part');
        const sticky = wrapper && ['sticky', 'fixed'].includes(getComputedStyle(wrapper).position);
        const offset = (sticky ? header.getBoundingClientRect().height : 0) + barBottom + 20;
        root.style.setProperty('--aloha-booking-offset', `${offset}px`);
        if (!dialog.hidden) positionCalendar();
    }
    if (header) new ResizeObserver(measure).observe(header);
    const bar = document.getElementById('wpadminbar'); if (bar) new ResizeObserver(measure).observe(bar);
    window.addEventListener('resize', () => { measure(); if (picker) { const n = window.innerWidth >= 768 ? 2 : 1; if (picker.config.showMonths !== n) { picker.set('showMonths', n); if (!dialog.hidden) positionCalendar(); } } });
    document.addEventListener('scroll', () => { measure(); positionCalendar(); }, {passive: true, capture: true});
    if (config.calendar) receiveCalendar(config.calendar);
    measure(); guestButtons();
})();
