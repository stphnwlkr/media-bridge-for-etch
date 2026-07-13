=== Media Bridge for Etch ===
Contributors: stphnwlkr
Tags: etch, media, folders, wicked folders, happyfiles
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.0.2
License: GPLv2 or later

Keeps Etch Collections synchronized with Wicked Folders or HappyFiles.

== Description ==

Media Bridge for Etch provides bidirectional synchronization between Etch Collections and one selected client-side media folder provider.

To update the plugin settings, open Media > Media Bridge in the WordPress admin. The settings are located under the Media tab, not the Settings menu.

Initial providers:

* Wicked Folders
* HappyFiles

Features include folder creation, rename and movement synchronization; attachment assignment synchronization; provider-specific ordering; optional deletion synchronization; fixed or most-recent conflict authority; reconciliation; and a 200-entry sync history.

Etch supports two folder levels. Deeper folders in the selected client-side provider remain untouched. Media assigned below level two is represented in Etch by its nearest supported ancestor.

<img width="1223" height="1073" alt="image" src="https://github.com/user-attachments/assets/074aae87-24d0-4b9b-a246-d85f07040bdb" />


== Installation ==

1. Install and activate Etch.
2. Install and activate Wicked Folders or HappyFiles.
3. Install and activate Media Bridge for Etch.
4. Open Media > Media Bridge.
5. Select the client-side provider and save.
6. Run reconciliation once before relying on automatic synchronization.

== Changelog ==

= 1.0.2 =
* Fixed Clear History immediately restoring entries from the legacy log option.
* Fixed new folder synchronization when a fixed conflict authority is selected, while continuing to enforce that authority for mapped folders.
* Preserved alphabetical placement for new folders that have no explicit source order, while continuing to synchronize custom ordering when available.

= 1.0.1 =
* Fixed synchronized folder deletions by preserving mapped counterpart details until WordPress confirms the source folder was deleted.
* Fixed stale counterpart mappings when a folder is deleted while deletion synchronization is disabled.
* Clarified that plugin settings are managed under Media > Media Bridge, not the WordPress Settings menu.

= 1.0.0 =
* First stable release.

= 0.1.1 =
* Hardened provider input validation and mapped-term verification.
* Replaced the reconciliation tax query with taxonomy-native object lookups.
* Improved admin table semantics, status announcements and translated notices.
* Added missing translator context.

= 0.1.0 =
* Initial development release.
* Added Etch, Wicked Folders and HappyFiles providers.
* Added bidirectional folders and attachment assignments.
* Added reconciliation, conflict policy, deletion safety and sync history.
