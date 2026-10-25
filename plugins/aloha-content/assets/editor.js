/* Native Gutenberg document panel. No frontend bundle or third-party controls. */
(function (wp) {
	'use strict';
	const {createElement: el, useEffect, useState} = wp.element;
	const {ComboboxControl, Notice, Spinner, Button} = wp.components;
	function AreaPanel() {
		const meta = wp.data.useSelect(select => select('core/editor').getEditedPostAttribute('meta') || {}, []);
		const {editPost} = wp.data.useDispatch('core/editor');
		const [areas, setAreas] = useState([]), [loading, setLoading] = useState(true);
		const [error, setError] = useState(''), [refresh, setRefresh] = useState(0), [query, setQuery] = useState('');
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
			})().catch(() => { if (!controller.signal.aborted) setError('Areas could not be loaded. Refresh the list to try again.'); })
				.finally(() => { if (!controller.signal.aborted) setLoading(false); });
			return () => controller.abort();
		}, [refresh]);
		const value = Number(meta.aloha_area_id) || 0;
		const title = area => wp.htmlEntities.decodeEntities(area.title.raw || area.title.rendered || '(Untitled Area)');
		const statuses = {publish: 'Published', draft: 'Draft', pending: 'Pending review', future: 'Scheduled', private: 'Private'};
		const selected = areas.find(area => area.id === value);
		const options = areas.map(area => ({value: String(area.id), label: title(area) + (area.status === 'publish' ? '' : ' (' + (statuses[area.status] || area.status) + ')')}));
		if (value && !selected) options.push({value: String(value), label: 'Unavailable Area', disabled: true});
		const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
		const noResults = query.trim() && !options.some(option => normalize(option.label).includes(normalize(query.trim())));
		return el(wp.editor.PluginDocumentSettingPanel, {name: 'aloha-community-area', title: 'Area', className: 'aloha-community-area'},
			el('div', {className: 'aloha-community-area__body', 'aria-busy': loading, onBlur: () => setQuery(''), onKeyDown: event => { if (event.key === 'Escape') setQuery(''); }},
				el('p', {className: 'aloha-community-area__intro'}, 'Choose the Area this community belongs to.'),
				loading && el('div', {className: 'aloha-community-area__loading', role: 'status'}, el(Spinner), 'Loading Areas…'),
				error && el(Notice, {status: 'error', isDismissible: false}, error),
				!loading && !error && (areas.length > 0 || value > 0) && el(ComboboxControl, {
					label: 'Area', placeholder: 'Search Areas', value: value ? String(value) : null, options,
					onFilterValueChange: setQuery,
					onChange: id => { setQuery(''); editPost({meta: Object.assign({}, meta, {aloha_area_id: id ? Number(id) : 0})}); },
					help: 'Required to publish. Drafts can be saved without an Area.',
					__next40pxDefaultSize: true, __nextHasNoMarginBottom: true
				}),
				!loading && !error && !areas.length && el('p', {className: 'aloha-community-area__empty', role: 'status'}, 'No Areas yet. Create an Area from Areas in the WordPress menu, then refresh this list.'),
				!loading && !error && noResults && el('p', {className: 'aloha-community-area__empty', role: 'status'}, 'No Areas match “' + query.trim() + '”. Try another name.'),
				selected && el('div', {className: 'aloha-community-area__selection'},
					el('span', {className: 'aloha-community-area__selection-label'}, 'Selected Area'),
					el('strong', null, title(selected)),
					el('span', {className: 'aloha-community-area__status'}, statuses[selected.status] || selected.status)
				),
				!loading && !error && value > 0 && !selected && el(Notice, {status: 'warning', isDismissible: false}, 'The selected Area is unavailable. Choose another Area or clear the selection and save a draft.'),
				selected && selected.status !== 'publish' && el(Notice, {status: 'warning', isDismissible: false}, 'Publish this Area before publishing the Community. You can still save a draft.'),
				el(Button, {variant: 'tertiary', size: 'small', onClick: () => { setQuery(''); setRefresh(refresh + 1); }, disabled: loading}, 'Refresh Areas')
			)
		);
	}
	wp.plugins.registerPlugin('aloha-community-area', {render: AreaPanel});
})(window.wp);
