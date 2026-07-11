=== Media Bridge for Etch ===
Contributors: stphnwlkr
Tags: etch, media, folders, wicked folders, happyfiles
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later

Keeps Etch Collections synchronized with Wicked Folders or HappyFiles.

== Description ==

Media Bridge for Etch provides bidirectional synchronization between Etch Collections and one selected client-side media folder provider.

Initial providers:

* Wicked Folders
* HappyFiles

Features include folder creation, rename and movement synchronization; attachment assignment synchronization; provider-specific ordering; optional deletion synchronization; fixed or most-recent conflict authority; reconciliation; and a 200-entry sync history.

Etch supports two folder levels. Deeper folders in the selected client-side provider remain untouched. Media assigned below level two is represented in Etch by its nearest supported ancestor.

== Installation ==

1. Install and activate Etch.
2. Install and activate Wicked Folders or HappyFiles.
3. Install and activate Media Bridge for Etch.
4. Open Media > Media Bridge.
5. Select the client-side provider and save.
6. Run reconciliation once before relying on automatic synchronization.

== Changelog ==

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
