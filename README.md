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
version: The Elgg version string

Return
------
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
plugins: An array of plugin id strings

Return
------
An array of plugin id strings for those that have new versions

