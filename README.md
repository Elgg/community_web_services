Web services for the Elgg community site

Elgg version check
==================
This web service supports Elgg sites checking if there is a newer version
available for download at elgg.org.

Name
----
elgg.update.check

Parameters
----------
 * version: The Elgg version string

Response
--------
The version string for the newest release in that series


Community plugins update check
==============================
Check if there are newer versions of an Elgg site's plugins available on
the Elgg community site.

Name
----
plugins.update.check

Parameters
----------
 * plugins: An array of plugin id strings. The plugin id string is the md5 hash
   of the plugin directory name, plugin version, and the plugin author name.
 * version: Elgg version string

Response
--------
Array of new plugin versions available for download:

```
[
  {
    "plugin_id": <plugin 1 id>,
    "plugin_name": <plugin 1 name>,
    "plugin_version": <plugin 1 verson string>,
    "plugin_url": <plugin 1 homepage>,
    "download_url": <plugin 1 url>,
  },
  {
    "plugin_id": <plugin 2 id>,
    "plugin_name": <plugin 2 name>,
    "plugin_version": <plugin 2 version string>,
    "plugin_url": <plugin 2 homepage>,
    "download_url": <plugin 2 url>,
  }
]
```

Community plugins search
==============================
Search community plugin repository

Name
----
plugins.search

Parameters
----------
 * query: Search query
 * version: Elgg version
 * limit: Limit
 * offset: Offset
 * category: Comma-separated list of categories

Response
--------

```json
{
	"status": 0,
	"result": {
		"items": [
			{
				"guid": 5047,
				"type": "object",
				"subtype": "plugin_release",
				"owner_guid": 37,
				"container_guid": 5046,
				"site_guid": 1,
				"time_created": "2016-05-20T09:39:01+00:00",
				"time_updated": "2016-05-20T09:39:01+00:00",
				"url": "http://localhost/plugins/5046/releases/1",
				"read_access": 2,
				"title": "test",
				"description": null,
				"tags": [],
				"plugin_name": "test",
				"recommendations": "0",
				"downloads": "0",
				"screenshots": [
				"http://localhost/serve-file/e0/l1463737142/di/c0/UtIktDHGuOI5mMUVvZn1oCpXgfawYSxAvGr9UEkSoDI/5000/5046/plugins/5046_image_1.jpg",
				"http://localhost/serve-file/e0/l1463737143/di/c0/4bvqZzs76VOYDSICwDa38EISLVqne4wy8EccKLwTbBA/5000/5046/plugins/5046_image_2.jpg"
				],
				"plugin_id": null,
				"plugin_version": "1",
				"plugin_url": "http://localhost/plugins/5046/releases/1",
				"download_url": "http://localhost/plugins/download/5047"
			}
		],
		"total": 1,
		"limit": 10,
		"offset": 0
	}
}
```