/* Native Gutenberg document panel. No frontend bundle or third-party controls. */
(function (wp) {
	'use strict';
	const {createElement: el, useEffect, useState} = wp.element;
	const {ComboboxControl, Notice, Spinner, Button} = wp.components;
	function AreaPanel() {
		const meta = wp.data.useSelect(select => select('core/editor').getEditedPostAttribute('meta') || {}, []);
		const {editPost} = wp.data.useDispatch('core/editor');
		const [areas, setAreas] = useState([]), [loading, setLoading] = useState(true);
		const [error, setError] = useState(''), [refresh, setRefresh] = useState(0);
		useEffect(() => {
			const controller = new AbortController();
			setLoading(true); setError('');
			(async () => {
				const records = [];
				for (let page = 1, pages = 1; page <= pages; page++) {
					const response = await wp.apiFetch({path: '/wp/v2/area?context=edit&status=publish,draft,pending,future,private&orderby=title&order=asc&per_page=100&page=' + page, parse: false, signal: controller.signal});
					records.push(...await response.json());
					pages = Number(response.headers.get('X-WP-TotalPages')) || 1;
				}
				if (!controller.signal.aborted) setAreas(records);
			})().catch(e => { if (!controller.signal.aborted) setError(e.message || 'Could not load Areas.'); })
				.finally(() => { if (!controller.signal.aborted) setLoading(false); });
			return () => controller.abort();
		}, [refresh]);
		const value = Number(meta.aloha_area_id) || 0;
		const options = areas.map(area => ({value: String(area.id), label: wp.htmlEntities.decodeEntities(area.title.raw || area.title.rendered || '(Untitled Area)') + (area.status === 'publish' ? '' : ' (' + area.status + ')')}));
		const selected = areas.find(area => area.id === value);
		return el(wp.editor.PluginDocumentSettingPanel, {name: 'aloha-community-area', title: 'Area', className: 'aloha-community-area'},
			loading && el(Spinner),
			error && el(Notice, {status: 'error', isDismissible: false}, error),
			!loading && !error && el(ComboboxControl, {
				label: 'Area', value: value ? String(value) : null, options,
				onChange: id => editPost({meta: Object.assign({}, meta, {aloha_area_id: id ? Number(id) : 0})}),
				help: 'Required to publish. Drafts may be saved without an Area. Publish the Area first.',
				__next40pxDefaultSize: true, __nextHasNoMarginBottom: true
			}),
			!loading && !error && !areas.length && el('p', null, 'Create an Area first under Areas in the WordPress menu.'),
			!loading && !error && value > 0 && !selected && el(Notice, {status: 'warning', isDismissible: false}, 'The selected Area is unavailable. Choose another Area or clear the selection and save a draft.'),
			selected && selected.status !== 'publish' && el(Notice, {status: 'warning', isDismissible: false}, 'This Area is not published. You can save a draft, but cannot publish this Community yet.'),
			el(Button, {variant: 'tertiary', onClick: () => setRefresh(refresh + 1), disabled: loading}, 'Refresh Areas')
		);
	}
	wp.plugins.registerPlugin('aloha-community-area', {render: AreaPanel});
})(window.wp);
