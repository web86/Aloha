(() => {
    'use strict';
    function initialize() {
        if (typeof window.EmblaCarousel !== 'function') return;
        document.querySelectorAll('[data-related-carousel]').forEach(root => {
            if (root.dataset.carouselReady) return;
            const viewport = root.querySelector('[data-related-viewport]');
            const slides = [...root.querySelectorAll('.aloha-related-slide')];
            const dots = [...root.querySelectorAll('[data-related-dot]')];
            const previous = root.querySelector('[data-related-prev]');
            const next = root.querySelector('[data-related-next]');
            const motion = matchMedia('(prefers-reduced-motion: reduce)');
            if (root.closest('[hidden]')) return;
            let carousel;
            try {
                carousel = window.EmblaCarousel(viewport, {align: 'start', loop: false,
                    breakpoints: {'(prefers-reduced-motion: reduce)': {duration: 0}}});
            } catch { return; }
            root.dataset.carouselReady = 'true';
            function update() {
                const selected = carousel.selectedScrollSnap();
                root.dataset.relatedIndex = String(selected);
                previous.disabled = !carousel.canScrollPrev();
                next.disabled = !carousel.canScrollNext();
                slides.forEach((slide, index) => { slide.tabIndex = index === selected ? 0 : -1; });
                dots.forEach((dot, index) => dot.setAttribute('aria-pressed', String(index === selected)));
                const image = slides[selected].querySelector('img');
                if (image.dataset.src) {
                    if (image.dataset.srcset) {
                        image.srcset = image.dataset.srcset;
                        delete image.dataset.srcset;
                    }
                    image.src = image.dataset.src;
                    delete image.dataset.src;
                }
            }
            carousel.on('select', update).on('reInit', update);
            previous.addEventListener('click', () => carousel.scrollPrev(motion.matches));
            next.addEventListener('click', () => carousel.scrollNext(motion.matches));
            dots.forEach((dot, index) => dot.addEventListener('click', () => carousel.scrollTo(index, motion.matches)));
            viewport.addEventListener('keydown', event => {
                if (event.altKey || event.ctrlKey || event.metaKey) return;
                const actions = {ArrowLeft: () => carousel.scrollPrev(motion.matches),
                    ArrowRight: () => carousel.scrollNext(motion.matches), Home: () => carousel.scrollTo(0,motion.matches),
                    End: () => carousel.scrollTo(slides.length - 1,motion.matches)};
                if (actions[event.key]) { event.preventDefault(); actions[event.key](); }
            });
            root.querySelector('[data-related-controls]').hidden = false;
            update();
        });
    }
    initialize();
    document.addEventListener('aloha:catalog-filter', initialize);
})();
