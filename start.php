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
				'version' => array('type' => 'string', 'required' => true,),
			),
			"Check if there are newer versions of an Elgg site's plugins available on the Elgg community site.",
			'POST',
			false,
			false
	);
	
}

/**
 * Check for updates on the requested plugins
 *
 * @param string $version
 * @return array
 */
function community_ws_plugin_check($version) {
	$updated_plugins = array();
	
	$info = new stdClass();
	$info->plugin_id = md5(time());
	$info->plugin_name = "Test plugin";
	$info->plugin_version = "1.6";
	$info->plugin_url = "http://community.elgg.org/pg/plugins/";
	$info->download_url = "http://elgg.org";
	$updated_plugins[] = $info;

	return $updated_plugins;
}
