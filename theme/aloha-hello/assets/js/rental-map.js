(() => {
    'use strict';
    let pending;

    function loadProvider(key, onAuthError) {
        if (pending) return pending;
        pending = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            const timeout = setTimeout(fail, 12000);
            const oldFailure = window.gm_authFailure;
            // The provider can also report authorization failures after its bootstrap callback.
            window.gm_authFailure = () => {
                if (typeof oldFailure === 'function') oldFailure();
                fail();
                onAuthError();
            };
            function finish() {
                clearTimeout(timeout);
                delete window.alohaRentalMapsReady;
                script.onerror = null;
            }
            function fail() {
                finish();
                script.remove();
                reject(new Error('Maps unavailable'));
            }
            window.alohaRentalMapsReady = () => {
                finish();
                resolve(window.google.maps);
            };
            const url = new URL('https://maps.googleapis.com/maps/api/js');
            url.search = new URLSearchParams({key, callback: 'alohaRentalMapsReady', loading: 'async', auth_referrer_policy: 'origin'});
            script.async = true;
            script.referrerPolicy = 'origin';
            script.src = url.toString();
            script.onerror = fail;
            document.head.append(script);
        });
        return pending;
    }

    document.querySelectorAll('[data-rental-map]').forEach(root => {
        const config = JSON.parse(root.querySelector('[data-map-config]').textContent);
        const surface = root.querySelector('[data-map-surface]');
        const cover = root.querySelector('[data-map-message]');
        const status = cover.querySelector('[data-map-status]');
        const buttons = [...root.querySelectorAll('[data-map-point]')];
        let failed = false;

        const showError = () => {
            const restoreFocus = root.contains(document.activeElement);
            failed = true;
            surface.hidden = true;
            cover.hidden = false;
            status.textContent = config.error;
            buttons.forEach(button => {
                button.hidden = true;
                button.previousElementSibling.hidden = false;
            });
            if (restoreFocus) {
                status.tabIndex = -1;
                status.focus({preventScroll: true});
            }
        };

        // Leave the list visible and avoid third-party requests when the browser opts out.
        if (navigator.globalPrivacyControl === true) return;

        async function showMap() {
            status.textContent = config.loading;
            try {
                const api = await loadProvider(config.key, showError);
                const [{Map, InfoWindow}, {AdvancedMarkerElement, PinElement}, {LatLngBounds}] = await Promise.all([
                    api.importLibrary('maps'), api.importLibrary('marker'), api.importLibrary('core'),
                ]);
                if (failed) return;
                cover.hidden = true;
                surface.hidden = false;
                const points = Object.entries(config.points);
                const withLegend = new Map(surface, {
                    mapId: config.mapId, center: {lat: points[0][1].lat, lng: points[0][1].lng}, zoom: 13,
                    mapTypeControl: false, streetViewControl: false, gestureHandling: 'cooperative',
                });
                const bounds = new LatLngBounds();
                const info = new InfoWindow();
                let activeMarker;
                points.forEach(([id, point]) => {
                    const position = {lat: point.lat, lng: point.lng};
                    bounds.extend(position);
                    const pin = new PinElement({glyphText: String(point.number)});
                    const marker = new AdvancedMarkerElement({map: withLegend, position, title: point.name, gmpClickable: true});
                    marker.append(pin);
                    const select = () => {
                        if (activeMarker) activeMarker.zIndex = null;
                        activeMarker = marker;
                        marker.zIndex = 1000;
                        buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.mapPoint === id)));
                        const label = document.createElement('strong');
                        label.textContent = point.name;
                        info.setContent(label);
                        info.open({map: withLegend, anchor: marker, shouldFocus: false});
                        withLegend.panTo(position);
                    };
                    marker.addEventListener('gmp-click', select);
                    const button = buttons.find(item => item.dataset.mapPoint === id);
                    button.previousElementSibling.hidden = true;
                    button.hidden = false;
                    button.addEventListener('click', select);
                });
                if (config.home) {
                    const position = {lat: config.home.lat, lng: config.home.lng};
                    bounds.extend(position);
                    const icon = document.createElement('span');
                    icon.textContent = '⌂';
                    icon.style.cssText = 'display:grid;place-items:center;background:#ff6400;color:white;border-radius:50%;width:36px;height:36px;font-size:28px;box-shadow:0 2px 6px #0004';
                    const marker = new AdvancedMarkerElement({map: withLegend, position, title: config.home.name, gmpClickable: true});
                    marker.append(icon);
                    marker.addEventListener('gmp-click', () => {
                        const title = document.createElement('strong');
                        title.textContent = config.home.name;
                        info.setContent(title);
                        info.open({map: withLegend, anchor: marker, shouldFocus: false});
                    });
                }
                if (points.length + Number(Boolean(config.home)) > 1) withLegend.fitBounds(bounds, 36);
            } catch {
                showError();
            }
        }
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(([entry]) => {
                if (!entry.isIntersecting) return;
                observer.disconnect();
                void showMap();
            }, {rootMargin: '200px'});
            observer.observe(cover);
        } else {
            void showMap();
        }
    });
})();
