/* Embla 8.6.0 progressively enhances the same SSR cards; native scroll is the fallback. */
(() => {
 'use strict';
 function init() {
  if (typeof window.EmblaCarousel !== 'function') return;
  document.querySelectorAll('[data-aloha-beaches].is-carousel').forEach(root => {
   if (root.classList.contains('is-enhanced')) return;
   const viewport = root.querySelector('.aloha-beaches-viewport');
   const controls = root.querySelector('.aloha-beaches-controls');
   const previous = controls.querySelector('[data-beaches-direction="-1"]');
   const next = controls.querySelector('[data-beaches-direction="1"]');
   const motion = matchMedia('(prefers-reduced-motion: reduce)');
   let carousel;
   try {
    carousel = window.EmblaCarousel(viewport, {
     align: 'start', containScroll: 'trimSnaps', slidesToScroll: 1,
     loop: false, dragFree: false,
     breakpoints: { '(prefers-reduced-motion: reduce)': { duration: 0 } }
    });
    root.classList.add('is-enhanced');
    viewport.scrollLeft = 0;
    const update = () => {
     previous.disabled = !carousel.canScrollPrev();
     next.disabled = !carousel.canScrollNext();
    };
    carousel.on('select', update).on('reInit', update).on('pointerUp', () => {
     // Embla's release momentum has its own duration, independent of options.duration.
     if (motion.matches) carousel.scrollTo(carousel.selectedScrollSnap(), true);
    });
    previous.addEventListener('click', () => carousel.scrollPrev(motion.matches));
    next.addEventListener('click', () => carousel.scrollNext(motion.matches));
    viewport.addEventListener('keydown', event => {
     // v8 can retain drag-click suppression after release outside the viewport.
     // Reset it through the public API before a native keyboard link activation.
     if (event.key === 'Enter' && event.target.closest('a[href]')) carousel.reInit();
     if (event.target !== viewport || event.altKey || event.ctrlKey || event.metaKey) return;
     if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
     event.preventDefault();
     if (event.key === 'ArrowLeft') carousel.scrollPrev(motion.matches);
     if (event.key === 'ArrowRight') carousel.scrollNext(motion.matches);
     if (event.key === 'Home') carousel.scrollTo(0, motion.matches);
     if (event.key === 'End') carousel.scrollTo(carousel.scrollSnapList().length - 1, motion.matches);
    });
    update();
    controls.hidden = false;
   } catch (error) {
    if (carousel) carousel.destroy();
    root.classList.remove('is-enhanced');
    controls.hidden = true;
   }
  });
 }
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
 else init();
})();
