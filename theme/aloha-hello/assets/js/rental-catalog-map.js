(() => {
    'use strict';
    const root = document.querySelector('[data-rental-catalog]');
    if (!root) return;
    const config = JSON.parse(root.querySelector('[data-catalog-map-config]').textContent);
    const surface = root.querySelector('[data-catalog-map-surface]');
    const message = root.querySelector('[data-catalog-map-message]');
    const aside = root.querySelector('#catalog-map');
    const cards = new Map([...root.querySelectorAll('[data-catalog-item]')].map(card => [Number(card.dataset.catalogItem), card]));
    if (navigator.globalPrivacyControl === true) { message.textContent = config.privacy; return; }
    if (!config.key || !config.mapId || !config.points.length) return;
    let started = false;
    let failed = false;
    let script;
    let timeout;
    const fail = () => {
        failed = true; clearTimeout(timeout); script?.remove();
        surface.hidden = true; message.hidden = false; message.textContent = config.error;
        delete window.alohaCatalogMapsReady;
    };
    async function ready() {
        clearTimeout(timeout); delete window.alohaCatalogMapsReady;
        try {
            const api = window.google.maps;
            const [{Map}, {AdvancedMarkerElement}, {LatLngBounds}] = await Promise.all([
                api.importLibrary('maps'), api.importLibrary('marker'), api.importLibrary('core'),
            ]);
            if (failed) return;
            surface.hidden = false; message.hidden = true;
            const map = new Map(surface, {mapId: config.mapId, center: config.points[0].position, zoom: 13,
                mapTypeControl: false, streetViewControl: false, gestureHandling: 'cooperative'});
            const bounds = new LatLngBounds();
            const markers = config.points.map(point => {
                bounds.extend(point.position);
                const link = document.createElement('a');
                link.href = point.url;
                const marker = new AdvancedMarkerElement({map, position: point.position, title: point.title, collisionBehavior: 'REQUIRED'});
                marker.append(link);
                return {point, link, marker};
            });
            const update = () => markers.forEach(({point, link, marker}) => {
                const match = cards.has(point.id) && !cards.get(point.id).hidden;
                link.className = 'aloha-catalog-marker' + (match ? ' is-match' : ' is-neutral');
                link.textContent = match ? (point.price ? `from ${point.price}/night` : point.title) : '';
                link.setAttribute('aria-label', `${point.title} — ${match ? 'in current results' : "doesn't match your filters"}`);
                marker.zIndex = match ? 10 : 1;
            });
            const fit = () => {
                if (aside.hidden || !surface.clientWidth) return;
                if (markers.length > 1) map.fitBounds(bounds, 55);
                else map.setCenter(config.points[0].position);
            };
            update(); fit();
            document.addEventListener('aloha:catalog-filter', update);
            document.addEventListener('aloha:catalog-map-toggle', fit);
            if ('ResizeObserver' in window) new ResizeObserver(fit).observe(surface);
        } catch { fail(); }
    }
    function show() {
        if (started || aside.hidden) return;
        started = true; message.textContent = config.loading;
        const oldFailure = window.gm_authFailure;
        window.gm_authFailure = () => { if (typeof oldFailure === 'function') oldFailure(); fail(); };
        timeout = setTimeout(fail, 12000);
        // Same browser key, Map ID, origin referrer and deferred provider policy as Nearby Places.
        if (window.google?.maps?.importLibrary) { void ready(); return; }
        window.alohaCatalogMapsReady = ready;
        script = document.createElement('script');
        const url = new URL('https://maps.googleapis.com/maps/api/js');
        url.search = new URLSearchParams({key: config.key, callback: 'alohaCatalogMapsReady', loading: 'async', auth_referrer_policy: 'origin'});
        script.async = true; script.referrerPolicy = 'origin'; script.src = url; script.onerror = fail;
        document.head.append(script);
    }
    document.addEventListener('aloha:catalog-map-toggle', () => { if (!aside.hidden) show(); });
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            if (!entries.some(entry => entry.isIntersecting)) return;
            observer.disconnect(); show();
        }, {rootMargin: '200px'});
        observer.observe(aside);
    } else show();
})();
