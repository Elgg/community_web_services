<?php

/**
 * Elgg community site web services
 */
elgg_register_event_handler('init', 'system', 'community_ws_init');

/**
 * Initialize
 * @return void
 */
function community_ws_init() {
	if (function_exists("elgg_ws_expose_function")) {
		elgg_ws_expose_function(
				'plugins.update.check', 'community_ws_plugin_check', array(
			'plugins' => array('type' => 'array', 'required' => true,),
			'version' => array('type' => 'string', 'required' => true,),
				), "Check if there are newer versions of an Elgg site's plugins available on the Elgg community site.", 'GET', false, false
		);

		$handler = elgg_is_active_plugin('community_solr') ? 'community_ws_plugin_solr_search' : 'community_ws_plugin_search';
		elgg_ws_expose_function('plugins.search', $handler, array(
			'query' => array(
				'type' => 'string',
				'required' => false,
				'default' => '',
				'description' => 'Search query',
			),
			'version' => array(
				'type' => 'string',
				'required' => true,
				'description' => 'Elgg version',
			),
			'limit' => array(
				'type' => 'int',
				'required' => true,
				'default' => 10,
				'description' => 'Max number of entries to return'
			),
			'offset' => array(
				'type' => 'int',
				'required' => true,
				'default' => 0,
				'description' => 'Pointer offset',
			),
			'category' => array(
				'type' => 'string',
				'required' => false,
				'description' => 'Comma-separated list of categories',
			),
				), 'Search plugin repository', 'GET', false, false, true
		);
	}

	elgg_register_plugin_hook_handler('to:object', 'entity', 'community_ws_entity_export');
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
					foreach ($release_compatibilities as $release_compatibility) {
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
 *
 * @param string $version Elgg version
 * @return float
 */
function community_ws_extract_version($version) {
	$elgg_version_arr = explode('.', $version);
	$elgg_major_version = (int) $elgg_version_arr[0];
	$elgg_minor_version = (int) $elgg_version_arr[1];

	return (float) "$elgg_major_version.$elgg_minor_version";
}

/**
 * Search community plugin repository
 *
 * @param array $data Request data
 *
 * @uses $data['query']   Search query
 * @uses $data['version'] Elgg version
 * @uses $data['limit']   Limit
 * @uses $data['offset']  Offset
 *
 * @return array
 */
function community_ws_plugin_search(array $data = []) {

	$serialized_settings = elgg_get_plugin_setting('search-settings', 'community_plugins');
	$settings = unserialize($serialized_settings);
	if (!is_array($settings)) {
		$settings = array();
	}

	$dbprefix = elgg_get_config('dbprefix');
	$filters['t'] = elgg_extract('query', $data);
	$filters['c'] = string_to_tag_array(elgg_extract('category', $data, ''));
	$version = community_ws_extract_version(elgg_extract('version', $data, '0.0'));
	$filters['v'] = [$version];

	$limit = (int) elgg_extract('limit', $data, 10);
	$offset = (int) elgg_extract('offset', $data, 0);

	$options = array(
		'type' => 'object',
		'subtype' => 'plugin_project',
		'offset' => $offset,
		'limit' => $limit,
		'metadata_name_value_pairs' => array(),
		'metadata_case_sensitive' => false,
		'joins' => array(),
	);

	$wheres = array();
	$group_bys = array();

	// Handle entity filtering
	if (is_array($filters) && !empty($filters)) {
		foreach ($filters as $key => $value) {
			$key = sanitise_string($key);
			switch ($key) {
				case 't' :
					// Any text value; will be matched against plugin title, description, summary, tags, author name and username
					if (is_array($settings['text']) && in_array('enabled', $settings['text'])) {
						if (strlen($value) > 0) {
							$value = sanitise_string($value);
							// Match title and description
							$options['joins'][] = "INNER JOIN {$dbprefix}objects_entity o ON (e.guid = o.guid)";
							$fields = array('title', 'description');
							$wheres[] = search_get_where_sql('o', $fields, array('query' => $value, 'joins' => $options['joins']));

							//Match author name and username
							if (in_array('author-name', $settings['text']) || in_array('author-username', $settings['text'])) {
								$options['joins'][] = "INNER JOIN {$dbprefix}users_entity u ON (e.owner_guid = u.guid)";
								$fields = array();
								if (in_array('author-name', $settings['text'])) {
									$fields[] = 'name';
								}
								if (in_array('author-username', $settings['text'])) {
									$fields[] = 'username';
								}
								$wheres[] = search_get_where_sql('u', $fields, array('query' => $value, 'joins' => $options['joins']));
							}

							// Match summary and tags
							if (in_array('summary', $settings['text']) || in_array('tags', $settings['text'])) {
								$value_parts = explode(' ', $value);
								$fields = array();
								if (in_array('summary', $settings['text'])) {
									$fields[] = 'summary';
								}
								if (in_array('tags', $settings['text'])) {
									$fields[] = 'tags';
								}
								$fields_str = "'" . implode("','", $fields) . "'";
								$options['joins'][] = "INNER JOIN {$dbprefix}metadata tm ON (e.guid = tm.entity_guid)";
								$options['joins'][] = "INNER JOIN {$dbprefix}metastrings tm_name ON (tm.name_id = tm_name.id AND tm_name.string IN ($fields_str))";
								$options['joins'][] = "INNER JOIN {$dbprefix}metastrings tm_value ON (tm.value_id = tm_value.id)";
								foreach ($value_parts as $expression) {
									$wheres[] = "tm_value.string LIKE \"%$expression%\"";
								}
							}
						}
					}
					break;
				case 'c' :
					// Categories
					if (is_array($settings['category']) && in_array('enabled', $settings['category'])) {
						if (is_array($value) && !empty($value)) {
							$categories = '("' . implode('","', $value) . '")';
							$options['joins'][] = "INNER JOIN {$dbprefix}metadata cm ON (e.guid = cm.entity_guid)";
							$options['joins'][] = "INNER JOIN {$dbprefix}metastrings cs_name ON (cm.name_id = cs_name.id AND cs_name.string = 'plugincat')";
							$options['joins'][] = "INNER JOIN {$dbprefix}metastrings cs_value ON (cm.value_id = cs_value.id AND cs_value.string IN $categories)";
						}
					}
					break;

				case 'v' :
					// Elgg versions
					if (is_array($settings['version']) && in_array('enabled', $settings['version'])) {
						if (is_array($value) && !empty($value)) {
							$versions = '("' . implode('","', $value) . '")';
							$plugin_release_subtype = get_subtype_id('object', 'plugin_release');
							$options['joins'][] = "INNER JOIN {$dbprefix}entities pre ON (e.guid = pre.container_guid AND pre.subtype = $plugin_release_subtype)";
							$options['joins'][] = "INNER JOIN {$dbprefix}metadata prm ON (pre.guid = prm.entity_guid)";
							$options['joins'][] = "INNER JOIN {$dbprefix}metastrings prm_name ON (prm.name_id = prm_name.id AND prm_name.string = 'elgg_version')";
							$options['joins'][] = "INNER JOIN {$dbprefix}metastrings prm_value ON (prm.value_id = prm_value.id AND prm_value.string IN $versions)";
							$group_bys[] = 'pre.guid';
						}
					}
					break;
			}
		}
	}


	// WHERE clauses were only added for full text search - so far all WHEREs can be safely joined by 'OR'
	if (!empty($wheres)) {
		$options['wheres'] = array();
		$options['wheres'][] = '(' . implode(' OR ', $wheres) . ')';
	}

	$options['count'] = true;
	$count = elgg_get_entities_from_metadata($options);

	$result = [
		'items' => [],
		'total' => $count,
		'limit' => $limit,
		'offset' => $offset,
	];

	if (!$count) {
		return $result;
	}

	$options['count'] = false;
	$plugins = new ElggBatch('elgg_get_entities_from_metadata', $options);

	foreach ($plugins as $plugin) {
		$release = $plugin->getRecommendedRelease((string) $version);
		if (!$release) {
			$release = $plugin->getRecentReleaseByElggVersion((string) $version);
		}
		if (!$release) {
			continue;
		}
		$result['items'][] = $release->toObject();
	}

	return $result;
}

/**
 * Search community plugin repository using solr
 *
 * @param array $data Request data
 *
 * @uses $data['query']   Search query
 * @uses $data['version'] Elgg version
 * @uses $data['limit']   Limit
 * @uses $data['offset']  Offset
 *
 * @return array
 */
function community_ws_plugin_solr_search(array $data = []) {

	$serialized_settings = elgg_get_plugin_setting('search-settings', 'community_plugins');
	$settings = unserialize($serialized_settings);
	if (!is_array($settings)) {
		$settings = array();
	}

	$filters['t'] = elgg_extract('query', $data);
	$filters['c'] = string_to_tag_array(elgg_extract('category', $data, ''));
	$version = community_ws_extract_version(elgg_extract('version', $data, '0.0'));
	$filters['v'] = [$version];

	$limit = (int) elgg_extract('limit', $data, 10);
	$offset = (int) elgg_extract('offset', $data, 0);

	$options = array(
		'type' => 'object',
		'subtype' => 'plugin_project',
		'offset' => $offset,
		'limit' => $limit,
		'metadata_name_value_pairs' => array(),
		'metadata_case_sensitive' => false,
		'joins' => array(),
	);
	
	$group_bys = array();
	$solr_params = array(
		'fq' => array(),
		'query' => '',
		'offset' => $offset,
		'limit' => $limit
	);

	// Handle entity filtering
	if (is_array($filters) && !empty($filters)) {
		foreach ($filters as $key => $value) {
			$key = sanitise_string($key);
			switch ($key) {
				case 't' :
					if (is_array($settings['text']) && in_array('enabled', $settings['text'])) {
						// Any text value; will be matched against plugin title, description, summary, tags, author name and username
						if (strlen($value) > 0) {
							$solr_params['query'] = $value;
						}
					}
					break;
				// Categories
				case 'c' :
					if (is_array($settings['category']) && in_array('enabled', $settings['category'])) {
						if (is_array($value) && !empty($value)) {
							$list = '';
							foreach ($value as $v) {
								if ($list) {
									$list .= ',';
								}
								$list .= '"' . $v . '"';
							}

							$solr_params['fq']['cat'] = 'plugincat_s:(' . $list . ')';
						}
					}
					break;
				case 'v' :
					if (is_array($settings['version']) && in_array('enabled', $settings['version'])) {
						// Elgg versions
						if (is_array($value) && !empty($value)) {
							$list = '';
							foreach ($value as $v) {
								if ($list) {
									$list .= ',';
								}
								$list .= '"' . $v . '"';
							}

							$solr_params['fq']['version'] = 'version_ss:(' . $list . ')';
						}
					}
					break;
			}
		}
	}

	// Get objects
	$result = plugin_search(null, null, array(), $solr_params);

	if (empty($result['entity'])) {
		return [
			'items' => [],
			'count' => 0,
			'limit' => $limit,
			'offset' => $offset,
		];
	}
	
	foreach ($result['entities'] as $plugin) {
		$release = $plugin->getRecommendedRelease((string) $version);
		if (!$release) {
			$release = $plugin->getRecentReleaseByElggVersion((string) $version);
		}
		if (!$release) {
			continue;
		}
		$result['items'][] = $release->toObject();
	}

	return $result;
}

/**
 * Exports plugin/plugin release
 *
 * @param string   $hook   "to:object"
 * @param string   $type   "entity"
 * @param stdClass $return Export object
 * @param array    $params Hook params
 * @return stdClass
 */
function community_ws_entity_export($hook, $type, $return, $params) {

	if (!elgg_in_context('api')) {
		return;
	}

	$entity = elgg_extract('entity', $params);

	if ($entity instanceof PluginRelease) {

		$project = $entity->getProject();

		if ($project) {
			$return->plugin_name = $project->getDisplayName();
			$return->recommendations = $project->countDiggs();
			$return->downloads = $project->countAnnotations('download');
			$return->screenshots = [];
			$return->categories = (array) $project->plugincat;
			$return->license = $project->license;
			$screenshots = $project->getScreenshots();
			if ($screenshots) {
				foreach ($screenshots as $screenshot) {
					$return->screenshots[] = elgg_get_inline_url($screenshot);
				}
			}
		}

		$return->plugin_id = $entity->hash;
		$return->plugin_version = $entity->version;
		$return->plugin_url = $entity->getURL();
		$return->download_url = elgg_normalize_url("plugins/download/{$entity->guid}");
	}

	return $return;
}
