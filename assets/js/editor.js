/**
 * WP to Social — Gutenberg Editor Sidebar Panel
 */
(function () {
	'use strict';

	var el = wp.element.createElement;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var CheckboxControl = wp.components.CheckboxControl;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var registerPlugin = wp.plugins.registerPlugin;

	var modules = window.wptsEditor && window.wptsEditor.modules ? window.wptsEditor.modules : [];

	if (!modules.length) {
		return;
	}

	function WPTSSidebarPanel() {
		var editPost = useDispatch('core/editor');
		var meta = useSelect(function (select) {
			return select('core/editor').getEditedPostAttribute('meta') || {};
		}, []);

		var controls = modules.map(function (mod) {
			var metaKey = '_wpts_post_to_' + mod.slug;
			var postedKey = '_wpts_' + mod.slug + '_posted';
			var isChecked = !!meta[metaKey];
			var alreadyPosted = !!meta[postedKey];

			if (alreadyPosted) {
				return el(
					'div',
					{ key: mod.slug, style: { padding: '4px 0', color: '#00a32a', fontSize: '13px' } },
					'\u2713 Posted to ' + mod.name
				);
			}

			return el(CheckboxControl, {
				key: mod.slug,
				label: 'Post to ' + mod.name,
				checked: isChecked,
				onChange: function (value) {
					var newMeta = {};
					newMeta[metaKey] = value ? 1 : 0;
					editPost.editPost({ meta: newMeta });
				},
			});
		});

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'wpts-social-panel',
				title: 'WP to Social',
				icon: 'share',
			},
			controls
		);
	}

	registerPlugin('wpts-social', {
		render: WPTSSidebarPanel,
	});
})();
