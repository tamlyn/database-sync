=== Database Sync ===
Contributors: tamlyn
Tags: database, db, sync, synch, copy, deploy, stage
Requires at least: 3.0
Tested up to: 4.0
Stable tag: 0.5.1

Sync databases across servers with a single click.

== Description ==

**WARNING:** This plugin is for advanced users. If used incorrectly it could wipe out *all your content*!

Keeping databases in sync between development, staging and live servers can be a pain. This plugin lets you
link together several WordPress installations by sharing a secret token. Once linked, administrators can pull
or push the entire database between servers with just a click.

Currently syncs database only, not uploaded files.

= Backups =

The plugin will attempt to make local backups of the database before overwriting. These are stored as gzipped SQL
files in wp-content/plugins/database-sync/backups/dbYYYYMMDD.HHMMSS.sql.gz The backups directory should be made
writable, and you should keep an eye on it if you sync often as it will grow in size.

= Usage =

See Installation instructions.

[Plugin by Outlandish](http://outlandish.com)

== Installation ==

1. Install as usual.
2. Activate plugin.
3. Repeat 1 & 2 on each server that you want to link.
4. Go to Tools > Database Sync on the main server and copy the token.
5. On the other server(s), go to Tools > Database Sync and paste the token.

You are now set up to pull from and push to the main server.

== ChangeLog ==

= Version 0.5.1 =

* Extra URL sanitisation.

= Version 0.5 =

* Fixed XSS vulnerability.

= Version 0.4 =

* Support for dumping and syncing MySQL VIEWs.

= Version 0.3.1 = 

* Fixed E_NOTICE warning. Thanks to miniragnarok.

= Version 0.3 =

* Use `$wpdb` instead of deprecated `mysqli` extension. Thanks to Simon East.
* Fixed manual dump download filename. Thanks to Ronald Mansveld.

= Version 0.2 =

* Initial public release.

= Version 0.1 =

* Internal testing.
