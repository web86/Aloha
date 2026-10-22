/* Editor-only server preview; no React dependency on the frontend. */
wp.blocks.registerBlockType('aloha/shell-slot', {
	title: 'Aloha shell element', category: 'theme',
	attributes: { slot: { type: 'string' } },
	supports: { html: false, inserter: false },
	edit: function (props) {
		return wp.element.createElement('div', wp.blockEditor.useBlockProps({className: 'aloha-editor-slot--' + props.attributes.slot}), wp.element.createElement(wp.serverSideRender, {block: 'aloha/shell-slot', attributes: props.attributes}));
	},
	save: function () { return null; }
});
