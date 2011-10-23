<?php
/**
 * Elgg community site web services
 */

register_elgg_event_handler('init', 'system', 'community_ws_init');

function community_ws_init() {
	expose_function(
			'plugins.update.check',
			'community_ws_plugin_check',
			array(
				'plugins' => array('type' => 'array', 'required' => true,),
				'version' => array('type' => 'string', 'required' => true,),
			),
			"Check if there are newer versions of an Elgg site's plugins available on the Elgg community site.",
			'GET',
			false,
			false
	);
	
}

/**
 * Check for updates on the requested plugins
 *
 * @param array  $plugins An array of plugin ids. The ids are a md5 hash of the
 *                        plugin directory, plugin version, and plugin author.
 * @param string $version The Elgg version string
 * @return array
 */
function community_ws_plugin_check($plugins, $version) {
	$updated_plugins = array();

	foreach ($plugins as $plugin_id) {
		if (rand(0,1) == 1) {
			$info = new stdClass();
			$info->plugin_id = $plugin_id;
			$info->plugin_name = "Test plugin";
			$info->plugin_version = "1.8";
			$info->plugin_url = "http://community.elgg.org/pg/plugins/";
			$info->download_url = "http://elgg.org";
			$updated_plugins[] = $info;
		}
	}

	return $updated_plugins;
}
