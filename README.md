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
