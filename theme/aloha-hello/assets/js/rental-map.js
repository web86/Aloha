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
                const [{Map}, {AdvancedMarkerElement}, {LatLngBounds}] = await Promise.all([
                    api.importLibrary('maps'), api.importLibrary('marker'), api.importLibrary('core'),
                ]);
                if (failed) return;
                cover.hidden = true;
                surface.hidden = false;
                surface.setAttribute('aria-busy', 'true');
                const points = Object.entries(config.points);
                const withLegend = new Map(surface, {
                    mapId: config.mapId, center: {lat: points[0][1].lat, lng: points[0][1].lng}, zoom: 13,
                    mapTypeControl: false, streetViewControl: false, gestureHandling: 'cooperative',
                });
                withLegend.addListener('tilesloaded', () => surface.setAttribute('aria-busy', 'false'));
                withLegend.addListener('idle', () => { surface.dataset.mapLayout = 'ready'; });
                const bounds = new LatLngBounds();
                let activeMarker;
                function addMarker(point, index, home = false) {
                    const position = {lat: point.lat, lng: point.lng};
                    bounds.extend(position);
                    const content = document.createElement('span');
                    content.className = 'aloha-rental-map-marker' + (home ? ' is-home' : '');
                    content.dataset.labelSlot = String(index % 4);
                    const icon = document.createElement('span');
                    icon.className = 'aloha-rental-map-dot';
                    icon.setAttribute('aria-hidden', 'true');
                    if (home) {
                        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                        svg.setAttribute('viewBox', '0 0 24 24');
                        const path = document.createElementNS(svg.namespaceURI, 'path');
                        path.setAttribute('d', 'M3 11 12 3l9 8h-3v10h-5v-7h-2v7H6V11Z');
                        path.setAttribute('fill', 'currentColor');
                        svg.append(path);
                        icon.append(svg);
                    }
                    const label = document.createElement('span');
                    label.className = 'aloha-rental-map-label';
                    const name = document.createElement('span');
                    name.className = 'aloha-rental-map-name';
                    name.textContent = point.name;
                    label.append(name);
                    content.append(icon, label);
                    const marker = new AdvancedMarkerElement({map: withLegend, position, title: point.name,
                        gmpClickable: true, collisionBehavior: 'REQUIRED', anchorLeft: '-50%', anchorTop: '-50%'});
                    marker.append(content);
                    marker.setAttribute('aria-label', point.name);
                    marker.setAttribute('aria-pressed', 'false');
                    const select = () => {
                        if (activeMarker) {
                            activeMarker.zIndex = null;
                            activeMarker.classList.remove('is-selected');
                            activeMarker.setAttribute('aria-pressed', 'false');
                        }
                        activeMarker = marker;
                        marker.zIndex = 1000;
                        marker.classList.add('is-selected');
                        marker.setAttribute('aria-pressed', 'true');
                        buttons.forEach(button => {
                            const selected = !home && button.dataset.mapPoint === index;
                            button.setAttribute('aria-pressed', String(selected));
                            button.closest('li').classList.toggle('is-selected', selected);
                        });
                        withLegend.panTo(position);
                    };
                    marker.addEventListener('gmp-click', select);
                    if (!home) {
                        const button = buttons.find(item => item.dataset.mapPoint === index);
                        button.previousElementSibling.hidden = true;
                        button.hidden = false;
                        button.addEventListener('click', select);
                    }
                }
                points.forEach(([id, point]) => addMarker(point, id));
                if (config.home) addMarker(config.home, points.length, true);
                if (points.length + Number(Boolean(config.home)) > 1) {
                    const fit = () => {
                        surface.dataset.mapLayout = 'pending';
                        surface.setAttribute('aria-busy', 'true');
                        surface.dataset.mapWidth = String(surface.clientWidth);
                        withLegend.fitBounds(bounds, {top: 80, bottom: 80,
                            left: Math.min(195, Math.floor(surface.clientWidth * .42)), right: Math.min(195, Math.floor(surface.clientWidth * .42))});
                    };
                    fit();
                    if ('ResizeObserver' in window) {
                        let width = surface.clientWidth;
                        let height = surface.clientHeight;
                        new ResizeObserver(() => {
                            if (surface.clientWidth === width && surface.clientHeight === height) return;
                            width = surface.clientWidth; height = surface.clientHeight;
                            fit();
                        }).observe(surface);
                    }
                }
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
