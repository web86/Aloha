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
	function AreaMediaPanel() {
		const {type, meta} = wp.data.useSelect(select => ({type: select('core/editor')?.getCurrentPostType(), meta: select('core/editor')?.getEditedPostAttribute('meta') || {}}), []);
		const id = meta.aloha_area_loop_video_id || 0;
		const video = wp.data.useSelect(select => id ? select('core').getMedia(id) : null, [id]);
		if (type !== 'area' || !Object.hasOwn(meta, 'aloha_area_loop_video_id')) return null;
		const update = fields => wp.data.dispatch('core/editor').editPost({meta: {...meta, ...fields}});
		const {Button, TextControl} = wp.components;
		return el(wp.editor.PluginDocumentSettingPanel, {name: 'aloha-area-media', title: 'Area Media'},
			el('p', null, 'Use a short 15–20 second MP4 (H.264) or WebM loop. Keep the file small. The Featured Image remains the poster.'),
			video?.source_url && el('video', {key: id, src: video.source_url, controls: true, preload: 'none', style: {width: '100%'}}, 'Video preview'),
			video?.title?.rendered && el('p', null, wp.htmlEntities.decodeEntities(video.title.rendered.replace(/<[^>]*>/g, ''))),
			el(wp.blockEditor.MediaUploadCheck, null,
				el(wp.blockEditor.MediaUpload, {
					allowedTypes: ['video/mp4', 'video/webm'], value: id,
					onSelect: media => update({aloha_area_loop_video_id: media.id}),
					render: ({open}) => el(Button, {variant: 'secondary', onClick: open}, id ? 'Replace background video' : 'Choose background video')
				}),
				!!id && el(Button, {variant: 'tertiary', isDestructive: true, onClick: () => update({aloha_area_loop_video_id: 0})}, 'Remove background video')
			),
			el(TextControl, {label: 'YouTube film URL', type: 'url', value: meta.aloha_area_film_url || '', onChange: url => update({aloha_area_film_url: url}), help: 'Optional full film. Paste a YouTube video URL, not iframe code. YouTube loads only after a visitor opens the film.', __next40pxDefaultSize: true, __nextHasNoMarginBottom: true})
		);
	}
	wp.plugins.registerPlugin('aloha-area-media', {render: AreaMediaPanel});
})(window.wp);
