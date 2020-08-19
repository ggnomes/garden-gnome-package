/**
 * GGPKG Block
 *
 * To add GGPKG Content in Gutenberg Editor
 *
 */

( function(blocks, editor, components, i18n, element) {
	var el = element.createElement;
	var BlockControls = wp.editor.BlockControls;
	var InspectorControls = wp.editor.InspectorControls;
	var CheckboxControl = wp.components.CheckboxControl;
	var TextControl = wp.components.TextControl;
	var MediaUpload = editor.MediaUpload;
	var MediaPlaceholder = editor.MediaPlaceholder;
	var __ = i18n.__;

	const ggpkgIcon = el('svg', {width: 100, height: 100, viewBox: '20 20 260 260'},
		el('polyline', {fill: '#dcb286', points: '150 89.05 64.69 138.31 46.91 86.48 132.22 37.22 150 89.05'}),
		el('polyline', {fill: '#e9c598', points: '150 89.05 235.31 138.31 253.09 86.48 167.78 37.22 150 89.05'}),
		el('polyline', {fill: '#a97c57', points: '235.31 138.31 150 187.57 64.69 138.31 150 89.05 235.31 138.31'}),
		el('path', {fill: '#e4dfb9', d:'M196,131.8a44.5,44.5,0,0,0-8-14.54,44.4,44.4,0,0,1-78.64,25.55,44.45,44.45,0,1,0,86.64-11Z'}),
		el('path', {fill: '#ef4023', d:'M187.94,117.26a44,44,0,0,0-2.06-16.44c-9.68-29.77-67.25-75-69.74-77.09a2.31,2.31,0,0,0-3.25.28h0a2.24,2.24,0,0,0-.46.91c-.81,3.15-20.79,73.6-11.12,103.37a44,44,0,0,0,8,14.51,44.42,44.42,0,0,1,78.63-25.55Z'}),
		el('path', {fill: '#efba97', d:'M139.91,103.25a44.42,44.42,0,0,0-30.6,39.56,44.4,44.4,0,0,0,78.63-25.55,44.44,44.44,0,0,0-48-14Z'}),
		el('polyline', {fill: '#b68963', points: '235.31 138.31 235.31 227.55 150 276.81 150 187.57 235.31 138.31'}),
		el('polyline', {fill: '#dcb286', points: '150 187.57 150 276.81 64.69 227.55 64.69 138.31 150 187.57'}),
		el('polyline', {fill: '#dcb286', points: '150 187.57 235.31 138.31 253.09 190.14 167.78 239.4 150 187.57'}),
		el('polyline', {fill: '#e9c598', points: '132.22 239.4 150 187.57 64.69 138.31 46.91 190.14 132.22 239.4'}),
	);

	blocks.registerBlockType( 'ggpkg/ggpkg-block', {
		title: __( 'GGPKG', 'ggpkg' ),
		description: __('A custom block for displaying a Garden Gnome Package.', 'ggpkg'),
		icon: ggpkgIcon,
		category: 'widgets',
		attributes: {
			attachmentID: {
				type: 'number'
			},
			imageUrl: {
				type: 'string'
			},
			startPreview: {
				type: 'boolean',
			},
			width: {
				type: 'string',
			},
			height: {
				type: 'string',
			}
		},

		edit: function(props) {
			var attributes = props.attributes;
			var height = attributes.height;
			var width = attributes.width;
			var startPreview = attributes.startPreview;

			// default values
			if (width === undefined) {
				props.setAttributes({width: '100%', height: '800px', startPreview: false })
			}

			var onSelectPackage = function (media) {
				return props.setAttributes({
					imageUrl: media.url,
					attachmentID: media.id,
				});
			};

			var startPreviewChanged = function(value) {
				return props.setAttributes({
					startPreview: value
				});
			};

			var widthChanged = function(value) {
				return props.setAttributes({
					width: value
				});
			};

			var heightChanged = function(value) {
				return props.setAttributes({
					height: value
				});
			};

			return [
				el(InspectorControls, {},
					el('div', {},
						el('br', {}),
						el(CheckboxControl, {
							heading: '',
							label: 'Start Preview',
							help: 'If the player should start with a preview image and a play button',
							checked: startPreview,
							onChange: startPreviewChanged
						}),
						el('br', {}),
						el(TextControl, {
							label: 'Width',
							onChange: widthChanged,
							value: width
						}),
						el(TextControl, {
							label: 'Height',
							onChange: heightChanged,
							value: height
						})
					)
				),
				el(BlockControls, { key: 'controls' },
					el('div', { className: 'components-toolbar' },
						el(MediaUpload, {
							onSelect: onSelectPackage,
							type: 'image/ggsw-package',
							render: function (obj) {
								return el(components.Button, {
									className: 'components-icon-button components-toolbar__control',
									onClick: obj.open
								},
								// Add Dashicon for media upload button.
								el('svg', { className: 'dashicon dashicons-edit', width: '20', height: '20' },
									el('path', { d: 'M2.25 1h15.5c.69 0 1.25.56 1.25 1.25v15.5c0 .69-.56 1.25-1.25 1.25H2.25C1.56 19 1 18.44 1 17.75V2.25C1 1.56 1.56 1 2.25 1zM17 17V3H3v14h14zM10 6c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2zm3 5s0-6 3-6v10c0 .55-.45 1-1 1H5c-.55 0-1-.45-1-1V8c2 0 3 4 3 4s1-3 3-3 3 2 3 2z' })
								))
							}
						})
					)
				),
				el('div', { className: props.className, padding: '50px' },
					el(MediaUpload, {
						onSelect: onSelectPackage,
						type: 'image/ggsw-package',
						value: attributes.attachmentID,
						render: function (obj) {
							return el(components.Button, {
							className: attributes.attachmentID ? 'image-button' : 'button button-large',
							style: {backgroundColor: !attributes.attachmentID ? '#dbdbdb' : 'white', overflow: 'visible', 'box-shadow': 'none', 'display': 'block', 'height': 'auto'},
							onClick: obj.open
							},
							!attributes.attachmentID ? __('Select Package','ggpkg') : el('img', { src: attributes.imageUrl })
							)
						}
					})
				)
			]
		},
		save: function(props) {
			return null;
		}
	})
})(
	window.wp.blocks,
	window.wp.editor,	
	window.wp.components,
	window.wp.i18n,
	window.wp.element
);
