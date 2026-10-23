/* One native dialog for explicit YouTube film triggers; reusable by a future gallery. */
(() => {
	'use strict';
	let dialog, stage, heading, opener;
	const change = () => document.dispatchEvent(new Event('aloha-media-change'));
	document.addEventListener('click', event => {
		const trigger = event.target.closest('[data-aloha-youtube]');
		if (!trigger || !/^[A-Za-z0-9_-]{11}$/.test(trigger.dataset.alohaYoutube)) return;
		event.preventDefault();
		if (!dialog) {
			dialog = document.createElement('dialog'); dialog.className = 'aloha-media-dialog';
			dialog.setAttribute('aria-labelledby', 'aloha-media-title');
			const inner = document.createElement('div'); inner.className = 'aloha-media-inner';
			heading = document.createElement('h2'); heading.id = 'aloha-media-title';
			const close = document.createElement('button'); close.type = 'button'; close.textContent = 'Close'; close.autofocus = true;
			close.addEventListener('click', () => dialog.close());
			stage = document.createElement('div'); stage.className = 'aloha-media-stage';
			inner.append(heading, close, stage); dialog.append(inner); document.body.append(dialog);
			dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
			dialog.addEventListener('close', () => {
				stage.replaceChildren(); document.body.classList.remove('aloha-media-open');
				if (opener?.isConnected) opener.focus({preventScroll: true});
				change();
			});
		}
		opener = trigger;
		heading.textContent = trigger.dataset.alohaMediaTitle || 'Area film';
		const frame = document.createElement('iframe');
		frame.title = heading.textContent; frame.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
		frame.allowFullscreen = true; frame.referrerPolicy = 'strict-origin-when-cross-origin';
		frame.src = 'https://www.youtube-nocookie.com/embed/' + trigger.dataset.alohaYoutube + '?autoplay=1&rel=0';
		stage.replaceChildren(frame); dialog.showModal(); document.body.classList.add('aloha-media-open'); change();
	});
})();
