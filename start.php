<?php
/**
 * Elgg community site web services
 */

elgg_register_event_handler('init', 'system', 'community_ws_init');

function community_ws_init() {
	if (function_exists("elgg_ws_expose_function")) {
		elgg_ws_expose_function(
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
				// elgg version we are looking for a new plugin release for
				$elgg_version = community_ws_extract_version($version);
				// each plugin release can now be compatible with more than a single elgg version
				$release_compatibilities = $newer_releases[$index]->elgg_version;

				$compatible_release = false;

				// make sure that there is an array
				if (is_array($release_compatibilities)) {
					foreach($release_compatibilities as $release_compatibility)
						$plugin_require = community_ws_extract_version($release_compatibility);
						if ($plugin_require == $elgg_version) {
							$compatible_release = true;
						}
					}
				} else { // otherwise assume it's a string
					$plugin_require = community_ws_extract_version($release_compatibilities);
					if ($plugin_require == $elgg_version) {
						$compatible_release = true;
					}
				}

				// have we found the newest compatible release?
				if ($compatible_release) {
					$new_release = $newer_releases[$index];
					$dl_link = elgg_get_config('wwwroot');
					$dl_link .= "plugins/download/{$new_release->getGUID()}";

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
