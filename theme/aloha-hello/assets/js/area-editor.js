/* Two fixed, server-rendered Area sections and the template's contextual hero. */
(function (wp) {
	'use strict';
	const el = wp.element.createElement;
	for (const [kind, title] of [['hero', 'Area hero'], ['communities', 'Area communities'], ['rentals', 'Area rentals — future integration']]) {
		wp.blocks.registerBlockType('aloha/area-' + kind, {
			apiVersion: 3, title, category: 'theme', usesContext: ['postId', 'postType'],
			supports: {html: false, multiple: false, inserter: kind !== 'hero'},
			edit: function () {
				const postId = wp.data.useSelect(select => select('core/editor')?.getCurrentPostId(), []);
				const props = wp.blockEditor.useBlockProps();
				if (kind === 'rentals') return el('div', props, el(wp.components.Placeholder, {label: title}, 'No rentals are shown publicly. This slot is reserved for the future GuestyBridge integration.'));
				return el('div', props, el(wp.serverSideRender, {block: 'aloha/area-' + kind, attributes: {}, urlQueryArgs: {post_id: postId || 0}}));
			},
			save: () => null
		});
	}
})(window.wp);
