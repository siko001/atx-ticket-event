/**
 * Editor script for the "Featured event (parallax)" example block.
 *
 * Uses ServerSideRender so the preview is exactly the PHP render_callback —
 * no build step, no JSX. The event dropdown is loaded from the REST API the
 * ticketing plugin exposes for its mirrored event post type (atx_event).
 */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender = wp.serverSideRender;
	var apiFetch = wp.apiFetch;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;

	function useEvents() {
		var s = useState(null);
		useEffect(function () {
			apiFetch({ path: '/wp/v2/atx_event?per_page=100&status=publish&orderby=title&order=asc&_fields=id,title' })
				.then(function (rows) {
					s[1]((rows || []).map(function (r) {
						return { value: String(r.id), label: (r.title && r.title.rendered) ? r.title.rendered : '#' + r.id };
					}));
				})
				.catch(function () { s[1]([]); });
		}, []);
		return s[0];
	}

	wp.blocks.registerBlockType('atx-example/featured-parallax', {
		title: __('Featured event (parallax)', 'atx-custom-blocks-example'),
		description: __('Example: a featured event as a parallax hero, built on the ticketing data API.', 'atx-custom-blocks-example'),
		icon: 'cover-image',
		category: 'widgets',
		attributes: {
			postId: { type: 'number', default: 0 },
			height: { type: 'number', default: 460 },
			overlay: { type: 'boolean', default: true }
		},
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;
			var events = useEvents();
			var options = [{ value: '0', label: __('Auto — next upcoming event', 'atx-custom-blocks-example') }]
				.concat(events || []);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Featured event', 'atx-custom-blocks-example') },
						el(SelectControl, {
							label: __('Event', 'atx-custom-blocks-example'),
							value: String(a.postId),
							options: options,
							onChange: function (v) { set({ postId: parseInt(v, 10) || 0 }); }
						}),
						el(RangeControl, {
							label: __('Height (px)', 'atx-custom-blocks-example'),
							value: a.height,
							min: 200,
							max: 900,
							onChange: function (v) { set({ height: v || 460 }); }
						}),
						el(ToggleControl, {
							label: __('Darken image for readable text', 'atx-custom-blocks-example'),
							checked: a.overlay,
							onChange: function (v) { set({ overlay: v }); }
						})
					)
				),
				el(ServerSideRender, {
					block: 'atx-example/featured-parallax',
					attributes: a
				})
			);
		},
		save: function () { return null; }
	});
})(window.wp);
