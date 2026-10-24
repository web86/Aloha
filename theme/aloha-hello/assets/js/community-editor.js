/* Contextual template block. Title, excerpt, image and Area use native post fields. */
(function (wp) {
	'use strict';
	const el = wp.element.createElement;
	wp.blocks.registerBlockType('aloha/community-hero', {
		apiVersion: 3, title: 'Community split hero', category: 'theme', usesContext: ['postId', 'postType'],
		supports: {html: false, multiple: false, inserter: false},
		edit: function ({context}) {
			const current = wp.data.useSelect(select => ({id: select('core/editor')?.getCurrentPostId(), type: select('core/editor')?.getCurrentPostType()}), []);
			const props = wp.blockEditor.useBlockProps();
			const postId = context.postType === 'community' ? context.postId : (current.type === 'community' ? current.id : 0);
			return el('div', props, postId
				? el(wp.serverSideRender, {block: 'aloha/community-hero', attributes: {}, urlQueryArgs: {post_id: postId}})
				: el(wp.components.Placeholder, {label: 'Community split hero'}, 'Open a Community to preview its title, excerpt, featured image and selected Area.'));
		},
		save: () => null
	});
})(window.wp);
