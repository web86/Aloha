(() => {
    'use strict';

    document.querySelectorAll('[data-rental-disclosure]').forEach(section => {
        const button = section.querySelector('[data-rental-more]');
        if (!button) return;
        const extras = section.querySelectorAll('[data-rental-extra]');
        const show = expanded => {
            extras.forEach(item => { item.hidden = !expanded; });
            button.setAttribute('aria-expanded', String(expanded));
            button.textContent = expanded ? button.dataset.closeLabel : button.dataset.openLabel;
        };
        show(false);
        button.hidden = false;
        button.addEventListener('click', () => show(button.getAttribute('aria-expanded') !== 'true'));
    });

    if (typeof window.EmblaCarousel !== 'function') return;
    document.querySelectorAll('[data-rental-highlights]').forEach(root => {
        const viewport = root.querySelector('[data-rental-viewport]');
        const previous = root.querySelector('[data-rental-prev]');
        const next = root.querySelector('[data-rental-next]');
        const controls = previous.parentElement;
        const motion = matchMedia('(prefers-reduced-motion: reduce)');
        root.classList.add('is-enhanced');
        let carousel;
        try {
            carousel = window.EmblaCarousel(viewport, {
                align: 'start', containScroll: 'trimSnaps', slidesToScroll: 1, loop: false,
                breakpoints: {'(prefers-reduced-motion: reduce)': {duration: 0}},
            });
        } catch {
            root.classList.remove('is-enhanced');
            return;
        }
        const update = () => {
            previous.disabled = !carousel.canScrollPrev();
            next.disabled = !carousel.canScrollNext();
            controls.hidden = previous.disabled && next.disabled;
            viewport.tabIndex = controls.hidden ? -1 : 0;
        };
        carousel.on('select', update).on('reInit', update).on('pointerUp', () => {
            if (motion.matches) carousel.scrollTo(carousel.selectedScrollSnap(), true);
        });
        previous.addEventListener('click', () => carousel.scrollPrev(motion.matches));
        next.addEventListener('click', () => carousel.scrollNext(motion.matches));
        viewport.addEventListener('keydown', event => {
            if (event.target !== viewport || event.altKey || event.ctrlKey || event.metaKey || controls.hidden) return;
            const actions = {
                ArrowLeft: () => carousel.scrollPrev(motion.matches),
                ArrowRight: () => carousel.scrollNext(motion.matches),
                Home: () => carousel.scrollTo(0, motion.matches),
                End: () => carousel.scrollTo(carousel.scrollSnapList().length - 1, motion.matches),
            };
            if (actions[event.key]) {
                event.preventDefault();
                actions[event.key]();
            }
        });
        update();
    });
})();
