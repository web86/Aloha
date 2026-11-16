/* Rental filters use the shared Aloha media dialog; no second lightbox. */
(() => {
    'use strict';
    document.querySelectorAll('[data-rental-gallery]').forEach(gallery => {
        const dataNode = gallery.querySelector('.aloha-gallery-data');
        const mosaic = gallery.querySelector('.aloha-gallery-mosaic');
        if (!dataNode || !mosaic) return;
        let all;
        try { all = JSON.parse(dataNode.textContent); } catch { return; }
        if (!Array.isArray(all) || !all.length) return;
        const buttons = [...gallery.querySelectorAll('[data-rental-filter]')];
        const title = gallery.dataset.alohaMediaTitle || 'Rental photos';

        function render(key) {
            const current = key === 'all' ? all : all.filter(photo => photo.labelKey === key);
            if (!current.length) return;
            dataNode.textContent = JSON.stringify(current);
            mosaic.dataset.count = String(Math.min(current.length, 5));
            mosaic.replaceChildren();
            current.slice(0, 5).forEach((photo, index) => {
                const figure = document.createElement('figure');
                figure.className = 'aloha-gallery-tile';
                const link = document.createElement('a');
                link.className = 'aloha-gallery-photo';
                link.href = photo.url;
                link.dataset.alohaPhoto = String(index);
                link.setAttribute('aria-haspopup', 'dialog');
                link.setAttribute('aria-label', 'Open photo ' + (index + 1) + ' of ' + current.length);
                const img = document.createElement('img');
                img.src = photo.src;
                img.alt = photo.alt || '';
                img.loading = 'lazy';
                img.decoding = 'async';
                img.width = photo.width;
                img.height = photo.height;
                img.sizes = '(min-width:1280px) 600px, (min-width:768px) 50vw, 100vw';
                if (photo.srcset) img.srcset = photo.srcset;
                link.append(img);
                figure.append(link);
                if (index === 4) {
                    const more = document.createElement('a');
                    more.className = 'aloha-gallery-more';
                    more.href = '#photos';
                    more.dataset.alohaPhoto = '0';
                    more.setAttribute('aria-haspopup', 'dialog');
                    more.textContent = 'Show all ' + current.length + ' photos';
                    figure.append(more);
                }
                mosaic.append(figure);
            });
            buttons.forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.rentalFilter === key));
            });
            gallery.dataset.alohaMediaTitle = title + (key === 'all' ? '' : ' — ' + (buttons.find(b => b.dataset.rentalFilter === key)?.textContent || '').trim());
        }
        buttons.forEach(button => button.addEventListener('click', () => render(button.dataset.rentalFilter)));
    });
})();