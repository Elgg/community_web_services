<?php
/**
 * Elgg community site web services
 */

elgg_register_event_handler('init', 'system', 'community_ws_init');

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

	foreach ($plugins as $plugin_hash) {
		$release = elgg_get_entities_from_metadata(array(
			'type' => 'object',
			'subtype' => 'plugin_release',
			'metadata_name' => 'hash',
			'metadata_value' => $plugin_hash,
		));
		if ($release) {
			$release = $release[0];
		} else {
			continue;
		}

		$project = $release->getProject();
		if (!$project) {
			// this means the project access is not set to public
			continue;
		}

		// sort by newer releases first
		$newer_releases = elgg_get_entities(array(
			'type' => 'object',
			'subtype' => 'plugin_release',
			'container_guid' => $project->getGUID(),
			'created_time_lower' => $release->getTimeCreated() + 1,
			'order_by' => 'e.time_created desc',
			'limit' => 0,
		));

		if ($newer_releases) {
			// loop from the newest to oldest, checking elgg version requirements
			$index = 0;
			while (isset($newer_releases[$index])) {
				$plugin_require = community_ws_extract_version($newer_releases[$index]->elgg_version);
				$elgg_version = community_ws_extract_version($version);
				if ($plugin_require <= $version) {
					$new_release = $newer_releases[$index];
					$dl_link = elgg_get_config('wwwroot');
					$dl_link .= "pg/plugins/download/{$new_release->getGUID()}";

					$info = new stdClass();
					$info->plugin_id = $plugin_hash;
					$info->plugin_name = $project->title;
					$info->plugin_version = $new_release->version;
					$info->plugin_url = $new_release->getURL();
					$info->download_url = $dl_link;
					$updated_plugins[] = $info;

					break;
				}
				$index++;
			}
		}

	}

	return $updated_plugins;
}

/**
 * Turn an Elgg version string into a float
 * @param string $version
 * @return float
 */
function community_ws_extract_version($version) {
	$elgg_version_arr = explode('.', $version);
	$elgg_major_version = (int)$elgg_version_arr[0];
	$elgg_minor_version = (int)$elgg_version_arr[1];

	return (float)"$elgg_major_version.$elgg_minor_version";
}
