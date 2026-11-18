/* Rental filters use the shared Aloha media dialog. */
(() => {
    'use strict';

    document.querySelectorAll('[data-rental-actions]').forEach(actions => {
        const status = actions.querySelector('[role="status"]');
        actions.addEventListener('click', event => {
            const button = event.target.closest('[data-rental-message]');
            if (button) status.textContent = button.dataset.rentalMessage;
        });
    });

    document.querySelectorAll('[data-rental-gallery]').forEach(gallery => {
        const dataNode = gallery.querySelector('.aloha-gallery-data');
        const mosaic = gallery.querySelector('.aloha-gallery-mosaic');
        if (!dataNode || !mosaic) return;
        let all;
        let sizes;
        try {
            all = JSON.parse(dataNode.textContent);
            sizes = JSON.parse(gallery.dataset.rentalSizes);
        } catch {
            return;
        }
        if (!Array.isArray(all) || !all.length) return;

        const buttons = [...gallery.querySelectorAll('[data-rental-filter]')];
        const title = gallery.dataset.alohaMediaTitle;
        const more = mosaic.querySelector('.aloha-gallery-more');
        const tiles = new Map();
        const ranks = new Map(all.map((photo, index) => [photo, index]));
        const slotWidth = (tile, img) => mosaic.dataset.count === '1'
            ? Math.min(tile.clientWidth, tile.clientHeight * Number(img.getAttribute('width')) / Number(img.getAttribute('height')))
            : tile.clientWidth;

        function watch(img) {
            const link = img.parentElement;
            link.classList.add('is-loading');
            const finish = () => {
                link.classList.remove('is-loading');
                link.classList.toggle('is-unavailable', !img.naturalWidth);
            };
            img.addEventListener('load', finish);
            img.addEventListener('error', finish);
            if (img.complete) finish();
        }

        [...mosaic.children].forEach((tile, index) => {
            const link = tile.querySelector('.aloha-gallery-photo');
            const img = link.querySelector('img');
            // WordPress can prepend auto to lazy sizes after the block renders.
            // Keep the explicit slot when an image stops participating in the grid.
            if (img.sizes.startsWith('auto,')) img.sizes = sizes[Number(mosaic.dataset.count) - 1][index];
            watch(img);
            tile.dataset.rentalIndex = index;
            tiles.set(all[index], {tile, link, img, widest: slotWidth(tile, img)});
        });

        function newTile(photo, size, first) {
            const tile = document.createElement('figure');
            tile.className = 'aloha-gallery-tile';
            const link = document.createElement('a');
            link.className = 'aloha-gallery-photo';
            link.href = photo.url;
            link.setAttribute('aria-haspopup', 'dialog');
            const img = document.createElement('img');
            img.alt = photo.alt || '';
            img.loading = first ? 'eager' : 'lazy';
            img.decoding = 'async';
            img.width = photo.tileWidth;
            img.height = photo.tileHeight;
            img.sizes = size;
            if (photo.srcset) img.srcset = photo.srcset;
            img.src = photo.tileSrc || photo.src;
            link.append(img);
            tile.append(link);
            watch(img);
            const rank = ranks.get(photo);
            tile.dataset.rentalIndex = rank;
            const after = [...mosaic.children].find(child => Number(child.dataset.rentalIndex) > rank);
            mosaic.insertBefore(tile, after || null);
            return {tile, link, img, widest: 0};
        }

        let activeKey = 'all';
        function render(key) {
            if (key === activeKey) return;
            const current = key === 'all' ? all : all.filter(photo => photo.labelKey === key);
            if (!current.length) return;
            activeKey = key;
            const visible = current.slice(0, 5);
            const layout = visible.length === 1 ? [visible[0].singleSizes] : sizes[visible.length - 1];
            const records = visible.map((photo, index) => {
                if (!tiles.has(photo)) tiles.set(photo, newTile(photo, layout[index], index === 0));
                return tiles.get(photo);
            });
            const keep = new Set(records.map(record => record.tile));
            // Disconnecting a responsive image can trigger a new viewport-sized request.
            for (const tile of mosaic.children) tile.hidden = !keep.has(tile);
            mosaic.dataset.count = String(visible.length);
            records.forEach(({link}, index) => {
                link.dataset.alohaPhoto = String(index);
                link.setAttribute('aria-label', gallery.dataset.rentalPhotoLabel
                    .replace('%1$d', index + 1).replace('%2$d', current.length));
            });
            records.forEach((record, index) => {
                const width = slotWidth(record.tile, record.img);
                // Keep downloaded candidates when a tile moves back into a narrower slot.
                if (width > record.widest + 1) {
                    record.img.sizes = layout[index];
                    record.widest = width;
                }
                if (index === 0 && record.img.loading === 'lazy') record.img.loading = 'eager';
            });

            if (more) {
                if (visible.length === 5) {
                    more.textContent = gallery.dataset.rentalMoreLabel.replace('%d', current.length);
                    records[4].tile.append(more);
                } else {
                    more.remove();
                }
            }
            dataNode.textContent = JSON.stringify(current);
            let filterLabel = '';
            buttons.forEach(button => {
                const active = button.dataset.rentalFilter === key;
                button.setAttribute('aria-pressed', String(active));
                if (active) filterLabel = button.textContent.trim();
            });
            gallery.dataset.alohaMediaTitle = key === 'all' ? title : title + ' — ' + filterLabel;
        }

        buttons.forEach(button => button.addEventListener('click', () => {
            render(button.dataset.rentalFilter);
            button.scrollIntoView({block: 'nearest', inline: 'nearest'});
        }));
    });
})();
