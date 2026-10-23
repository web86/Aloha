/* Decorative loops: poster first, motion/data preferences and native playback. */
(() => {
	'use strict';
	const motion = matchMedia('(prefers-reduced-motion: reduce)');
	const connection = navigator.connection;
	document.querySelectorAll('.aloha-area-hero').forEach(hero => {
		const copy = hero.querySelector('.aloha-area-hero-content');
		const crumb = hero.querySelector('.aloha-area-breadcrumb');
		// Keep the dark part behind all copy even when zoom makes the Hero taller.
		const shade = () => hero.style.setProperty('--aloha-area-copy-start', Math.max(0, crumb.getBoundingClientRect().top - hero.getBoundingClientRect().top - 16) + 'px');
		if ('ResizeObserver' in window) { const observer = new ResizeObserver(shade); observer.observe(copy); observer.observe(hero); }
		shade(); document.fonts?.ready.then(shade);
	});
	document.querySelectorAll('.aloha-area-hero-video').forEach(video => {
		const hero = video.closest('.aloha-area-hero');
		const toggle = hero.querySelector('.aloha-area-video-toggle');
		let visible = false, ready = false, failed = false, userPaused = false, hasPlayed = false;
		const labelControl = () => {
			const label = userPaused ? 'Resume background video' : 'Pause background video';
			toggle.classList.toggle('is-paused', userPaused);
			toggle.setAttribute('aria-label', label); toggle.title = label;
		};
		const allowed = () => !motion.matches && !connection?.saveData && connection?.type !== 'cellular' && !['slow-2g', '2g', '3g'].includes(connection?.effectiveType);
		const sync = () => {
			if (!allowed()) {
				video.pause(); video.classList.remove('is-playing'); toggle.hidden = true;
				if (video.hasAttribute('src')) { video.removeAttribute('src'); video.load(); }
				return;
			}
			if (userPaused && hasPlayed && !failed) toggle.hidden = false;
			if (!ready || !visible || document.hidden || failed || userPaused || document.querySelector('.aloha-media-dialog[open]')) { video.pause(); return; }
			if (!video.hasAttribute('src')) {
				const image = hero.querySelector('.aloha-area-hero-image');
				if (image) video.poster = image.currentSrc || image.src;
				video.src = video.dataset.alohaLoopSrc; video.muted = true;
			}
			video.play().catch(() => {
				// Autoplay can be declined by the browser; keep the poster visible.
				if (!video.paused || !visible || document.hidden || userPaused || !allowed()) return;
				video.classList.remove('is-playing'); toggle.hidden = true;
			});
		};
		video.addEventListener('playing', () => {
			if (!allowed()) { sync(); return; }
			hasPlayed = true;
			video.classList.add('is-playing'); toggle.hidden = false;
			labelControl();
		});
		video.addEventListener('error', () => { failed = true; video.classList.remove('is-playing'); toggle.hidden = true; });
		toggle.addEventListener('click', () => {
			userPaused = !userPaused;
			labelControl(); sync();
		});
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(entries => { visible = entries[0].isIntersecting; sync(); }, {threshold: 0.05}).observe(hero);
		} // Without visibility observation the lightweight poster is the fallback.
		motion.addEventListener('change', sync); connection?.addEventListener?.('change', sync);
		document.addEventListener('visibilitychange', sync);
		document.addEventListener('aloha-media-change', sync);
		const start = () => { ready = true; sync(); };
		const afterLoad = () => 'requestIdleCallback' in window ? requestIdleCallback(start, {timeout: 1500}) : start();
		if (document.readyState === 'complete') afterLoad(); else window.addEventListener('load', afterLoad, {once: true});
	});
})();
