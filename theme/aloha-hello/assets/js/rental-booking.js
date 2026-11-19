/* Local flatpickr is a date surface. Bridge remains the authority for eligibility and money. */
(() => {
    'use strict';
    const root = document.querySelector('[data-booking-config]');
    if (!root || typeof window.flatpickr !== 'function') return;
    const config = JSON.parse(root.dataset.bookingConfig);
    const find = selector => root.querySelector(selector);
    const dialog = find('.aloha-booking-calendar');
    const surface = find('[data-calendar-surface]');
    const action = find('[data-booking-action]');
    const guestDetails = find('.aloha-booking-guests');
    const inputs = {adults: find('[data-guests="adults"]'), children: find('[data-guests="children"]')};
    const originalHeading = find('[data-booking-heading]').cloneNode(true);
    const state = {in: '', out: '', stage: 'in', days: new Map(), rules: null, total: null, busy: false, calendarBusy: false,
        priceGeneration: 0, calendarGeneration: 0, priceController: null, calendarController: null};
    let expiryTimer;
    let picker;
    let returnFocus;
    const lookupDay = value => { const row = state.days.get(value); return row && row.expiresAt > Date.now() ? row : null; };
    const iso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const date = value => new Date(`${value}T12:00:00`);
    const add = (value, n) => { const d = date(value); d.setDate(d.getDate() + n); return iso(d); };
    const nights = (start, end) => (Date.parse(`${end}T00:00:00Z`) - Date.parse(`${start}T00:00:00Z`)) / 86400000;
    const money = (amount, currency, compact = false) => new Intl.NumberFormat(document.documentElement.lang || 'en-US', {
        style: 'currency', currency, ...(compact ? {maximumFractionDigits: 0, minimumFractionDigits: 0} : {}),
    }).format(Number(amount)); // Formatting only; all sums/discounts/rounding are server-side integer arithmetic.
    const shortDate = value => date(value).toLocaleDateString(undefined, {month: 'short', day: 'numeric'});
    const message = text => { find('[data-booking-status]').textContent = text; };
    const calendarHelp = text => { find('[data-calendar-help]').textContent = text; };
    function invalidate() {
        clearTimeout(expiryTimer);
        state.priceController?.abort(); state.priceGeneration++;
        state.total = null; state.busy = false;
        find('[data-booking-price]').hidden = true;
        find('.aloha-booking-breakdown').open = false;
        action.disabled = false; action.querySelector('.aloha-booking-spinner').hidden = true;
        find('[data-action-text]').textContent = 'Check availability';
        find('[data-booking-disclaimer]').textContent = 'Online reservations and payments are not enabled yet.';
        message('');
    }
    function updateDates() {
        find('[data-check-in]').textContent = state.in ? shortDate(state.in) : 'Add date';
        find('[data-check-out]').textContent = state.out ? shortDate(state.out) : 'Add date';
        if (state.in && state.out) find('[data-booking-heading]').textContent = `${shortDate(state.in)} – ${shortDate(state.out)} · ${nights(state.in, state.out)} nights`;
        else find('[data-booking-heading]').replaceChildren(...[...originalHeading.childNodes].map(node => node.cloneNode(true)));
        find('[data-calendar-apply]').disabled = !state.in || !state.out || state.calendarBusy;
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
    function disabled(day) {
        const d = iso(day), row = lookupDay(d), rules = state.rules;
        if (state.calendarBusy || !rules || !row || d < rules.earliestCheckIn || d > rules.windowEnd) return true;
        if (state.stage === 'in' || !state.in) {
            if (row.status !== 'available' || row.cta !== false || !row.minNights) return true;
            if (add(d, row.minNights) > rules.windowEnd) return true;
            for (let i = 1; i < row.minNights; i++) { const next = lookupDay(add(d, i)); if (next && next.status !== 'available') return true; }
            return false;
        }
        const arrival = lookupDay(state.in), length = nights(state.in, d);
        if (!arrival || length < arrival.minNights || length > Math.min(arrival.maxNights ?? rules.maxNights, rules.maxNights) || row.ctd !== false) return true;
        for (let night = state.in; night < d; night = add(night, 1)) if (lookupDay(night)?.status !== 'available') return true;
        return false;
    }
    function dayCell(_dates, _text, _fp, cell) {
        const d = iso(cell.dateObj), row = lookupDay(d);
        cell.dataset.date = d;
        const number = document.createElement('span'); number.textContent = cell.textContent;
        const rate = document.createElement('small');
        rate.textContent = row?.nightlyRate ? money(row.nightlyRate.amount, row.nightlyRate.currency, true) : '—';
        cell.replaceChildren(number, rate);
        if (row?.nightlyRate) cell.setAttribute('aria-label', `${cell.getAttribute('aria-label')}, ${money(row.nightlyRate.amount, row.nightlyRate.currency)} per night`);
        cell.classList.toggle('aloha-range-start', d === state.in);
        cell.classList.toggle('aloha-range-end', d === state.out);
        cell.classList.toggle('aloha-in-range', Boolean(state.in && state.out && d > state.in && d < state.out));
        if (d === state.in || d === state.out) cell.setAttribute('aria-selected', 'true');
        if (cell.classList.contains('flatpickr-disabled')) cell.setAttribute('aria-disabled', 'true');
    }
    async function get(endpoint, params, signal) {
        const url = new URL(config.api + endpoint);
        for (const [key, value] of Object.entries({...params, ...config.preview})) url.searchParams.set(key, String(value));
        const response = await fetch(url, {signal, credentials: 'same-origin', headers: config.nonce ? {'X-WP-Nonce': config.nonce} : {}});
        const payload = await response.json();
        if (!response.ok || payload.status === 'unavailable') throw new Error(payload.message || 'Temporarily unavailable. Please retry.');
        return payload;
    }
    async function loadCalendar() {
        state.calendarController?.abort(); state.calendarController = new AbortController();
        const generation = ++state.calendarGeneration;
        const first = iso(new Date(picker.currentYear, picker.currentMonth, 1, 12));
        const visibleLast = iso(new Date(picker.currentYear, picker.currentMonth + picker.config.showMonths, 0, 12));
        const last = [add(visibleLast, 7), add(first, 62)].sort()[0];
        const to = state.rules && last > state.rules.windowEnd ? state.rules.windowEnd : last;
        if (state.rules && first > to) { calendarHelp('These dates are outside the booking window.'); return; }
        state.calendarBusy = true;
        find('[data-calendar-loading]').hidden = false; find('[data-calendar-retry]').hidden = true;
        surface.setAttribute('aria-busy', 'true'); picker.redraw(); updateDates();
        try {
            const payload = await get('availability', {from: first, to}, state.calendarController.signal);
            if (generation !== state.calendarGeneration) return;
            state.rules = payload.rules;
            payload.days.forEach(row => state.days.set(row.date, {...row, expiresAt: Date.parse(payload.expiresAt)}));
            inputs.adults.max = String(payload.rules.capacity); inputs.children.max = String(payload.rules.capacity);
            inputs.children.disabled = payload.rules.suitableForChildren === false; guestButtons();
            find('[data-guests-help]').textContent = payload.rules.suitableForChildren === false
                ? `Maximum ${payload.rules.capacity} guests. This home is not suitable for children.`
                : `Maximum ${payload.rules.capacity} guests. Guest composition is confirmed during booking.`;
            picker.set('minDate', payload.rules.today); picker.set('maxDate', payload.rules.windowEnd);
            // Single-date selection plus our arrival/departure state preserves booked checkout boundaries.
            calendarHelp(state.stage === 'out' && state.in ? `Choose checkout. Minimum stay: ${lookupDay(state.in)?.minNights ?? '—'} nights.` : 'Choose check-in, then checkout. Nightly rates exclude taxes and fees.');
            // Keep the client calendar cache bounded and never trust expired day eligibility.
            const expires = Date.parse(payload.expiresAt);
            setTimeout(() => { if (generation === state.calendarGeneration) { state.days.clear(); if (dialog.open) loadCalendar(); } }, Math.max(1, expires - Date.now()));
        } catch (error) {
            if (generation !== state.calendarGeneration || error.name === 'AbortError') return;
            // Do not allow a previous month's stale calendar after a failed refresh.
            state.days.clear();
            calendarHelp(error.message || 'Calendar temporarily unavailable.'); find('[data-calendar-retry]').hidden = false;
        } finally {
            if (generation === state.calendarGeneration) {
                state.calendarBusy = false; find('[data-calendar-loading]').hidden = true;
                surface.setAttribute('aria-busy', 'false'); picker.redraw();
                const firstEnabled = surface.querySelector('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay)');
                if (firstEnabled) firstEnabled.tabIndex = 0;
                updateDates();
            }
        }
    }
    function chooseDay(value) {
        if (disabled(date(value))) return;
        invalidate();
        if (state.stage === 'in' || !state.in) {
            state.in = value; state.out = ''; state.stage = 'out';
            calendarHelp(`Choose checkout. Minimum stay: ${lookupDay(value).minNights} nights.`);
        } else { state.out = value; calendarHelp(`${nights(state.in, state.out)} nights selected. Check availability for an estimated total.`); }
        picker.clear(false); picker.redraw(); updateDates();
    }
    function openCalendar(stage = 'in', trigger = action) {
        returnFocus = trigger;
        state.stage = stage === 'out' && state.in ? 'out' : 'in';
        if (!picker) picker = window.flatpickr(find('[data-calendar-input]'), {
            inline: true, appendTo: surface, disableMobile: true, mode: 'single', dateFormat: 'Y-m-d',
            defaultDate: config.today, showMonths: window.innerWidth >= 768 ? 2 : 1, monthSelectorType: 'static',
            minDate: config.today, disable: [disabled], ariaDateFormat: 'F j, Y',
            onDayCreate: dayCell, onChange: (_dates, value) => chooseDay(value),
            onMonthChange: () => loadCalendar(), onYearChange: () => loadCalendar(),
        });
        dialog.showModal(); document.documentElement.classList.add('aloha-booking-dialog-open');
        picker.jumpToDate(state.in || config.today, false); loadCalendar();
    }
    dialog.addEventListener('close', () => { document.documentElement.classList.remove('aloha-booking-dialog-open'); state.calendarController?.abort(); state.calendarGeneration++; state.calendarBusy = false; returnFocus?.focus(); });
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    find('[data-calendar-close]').addEventListener('click', () => dialog.close());
    find('[data-calendar-clear]').addEventListener('click', () => { invalidate(); state.in = state.out = ''; state.stage = 'in'; picker.clear(false); updateDates(); calendarHelp('Choose check-in, then checkout.'); });
    find('[data-calendar-retry]').addEventListener('click', loadCalendar);
    find('[data-calendar-apply]').addEventListener('click', () => { dialog.close(); calculate(); });
    root.querySelectorAll('[data-booking-dates]').forEach(button => button.addEventListener('click', () => openCalendar(button.dataset.bookingDates, button)));
    function renderPrice(payload) {
        state.total = payload;
        find('[data-total-amount]').textContent = money(payload.estimatedTotal, payload.currency);
        const lines = find('[data-price-lines]'); lines.replaceChildren();
        for (const item of payload.items) {
            const label = document.createElement('dt'); label.textContent = item.label;
            const amount = document.createElement('dd'); amount.textContent = money(item.amount, payload.currency);
            lines.append(label, amount);
        }
        const label = document.createElement('dt'); label.textContent = 'Total'; label.className = 'aloha-price-total';
        const amount = document.createElement('dd'); amount.textContent = money(payload.estimatedTotal, payload.currency); amount.className = 'aloha-price-total'; lines.append(label, amount);
        find('[data-price-detail-note]').textContent = payload.notices.join(' ');
        find('[data-booking-price]').hidden = false;
        find('[data-action-text]').textContent = 'Book now';
        find('[data-booking-disclaimer]').textContent = 'You won’t be charged yet. Booking checkout is not enabled.';
        message(payload.status === 'partial' ? 'Detailed price breakdown is temporarily unavailable.' : 'Estimated total. Final amount is confirmed during booking.');
        expiryTimer = setTimeout(() => { invalidate(); message('This estimate has expired. Check availability again.'); }, Math.max(1, Date.parse(payload.expiresAt) - Date.now()));
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
    }
    if (header) new ResizeObserver(measure).observe(header);
    const bar = document.getElementById('wpadminbar'); if (bar) new ResizeObserver(measure).observe(bar);
    window.addEventListener('resize', () => { measure(); if (picker) { const n = window.innerWidth >= 768 ? 2 : 1; if (picker.config.showMonths !== n) { picker.set('showMonths', n); if (dialog.open) loadCalendar(); } } });
    window.addEventListener('scroll', measure, {passive: true}); measure(); guestButtons();
})();
