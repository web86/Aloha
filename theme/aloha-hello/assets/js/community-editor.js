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
	function CommunityMediaPanel() {
		const {type, meta, title} = wp.data.useSelect(select => ({
			type: select('core/editor')?.getCurrentPostType(), meta: select('core/editor')?.getEditedPostAttribute('meta') || {},
			title: select('core/editor')?.getEditedPostAttribute('title') || ''
		}), []);
		if (type !== 'community' || !Object.hasOwn(meta, 'aloha_community_film_url')) return null;
		const update = fields => wp.data.dispatch('core/editor').editPost({meta: {...meta, ...fields}});
		return el(wp.editor.PluginDocumentSettingPanel, {name: 'aloha-community-media', title: 'Community Media'},
			el(wp.components.TextControl, {label: 'YouTube film URL', type: 'url', value: meta.aloha_community_film_url || '', onChange: url => update({aloha_community_film_url: url}), help: 'Optional. Paste a YouTube video URL, not iframe code. YouTube loads only after a visitor opens the film. Clear the URL to hide the button.', __next40pxDefaultSize: true, __nextHasNoMarginBottom: true}),
			el(wp.components.TextControl, {label: 'Film Label', value: meta.aloha_community_film_label || '', placeholder: wp.htmlEntities.decodeEntities(title) + ' in 90 seconds', onChange: label => update({aloha_community_film_label: label}), help: 'Optional override. Leave empty for the current Community name followed by “in 90 seconds”. Adjust the label to match your film.', __next40pxDefaultSize: true, __nextHasNoMarginBottom: true})
		);
	}
	wp.plugins.registerPlugin('aloha-community-media', {render: CommunityMediaPanel});
})(window.wp);
