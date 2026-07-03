=== Nahnu Install and Version Tracker ===
Contributors:      ja1me4
Tags:              plugin stats, install count, version tracker, wordpress.org, github
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      7.4
Stable tag:        1.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Track active install counts and plugin versions from WordPress.org and GitHub. Display anywhere on your site via shortcode.

== Description ==

Nahnu Install and Version Tracker fetches and caches three types of data for your plugins:

* **Active install counts** from the WordPress.org Plugins API
* **Current stable versions** from the WordPress.org Plugins API
* **Release versions** from GitHub — supports both the GitHub Releases API and raw JSON files

Data is stored in the database so frontend shortcodes never hit any external API live. A shared WP-Cron job keeps everything up to date automatically, and a manual refresh button is always available.

**Shortcodes:**

* `[nit_installs slug="your-plugin"]` — outputs the active install count, e.g. `10,000+` or `Fewer than 10`
* `[nit_wp_version slug="your-plugin"]` — outputs the current WP.org version, e.g. `2.1.4`
* `[nit_gh_version id="your-plugin"]` — outputs the GitHub release version, e.g. `1.0.3`

All shortcodes return plain text only — no wrapper HTML — so they drop cleanly into any theme or page builder.

**Features:**

* Tabbed admin interface — Install Tracker, WP.org Version, GitHub Version, Fetch Settings
* Configurable auto-fetch frequency: every 1, 6, 12, or 24 hours
* Manual "Refresh Now" button
* GitHub support for both Releases API (`api.github.com`) and raw JSON files (`raw.githubusercontent.com`)
* Displays `Fewer than 10` for new plugins with under 10 active installs
* Tracks install count delta between fetches (gained/lost since last check)
* All output properly escaped and i18n-ready

== Installation ==

1. Upload the `nahnu-install-tracker` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu.
3. Go to **Settings > Install Tracker**.
4. Add your plugin slugs or GitHub sources on the relevant tab.
5. Click **Refresh Now** (or wait for the cron) to fetch initial data.
6. Use the shortcodes in any page, post, or widget.

== Frequently Asked Questions ==

= Where does the install and version data come from? =

Install counts and WP.org versions come from the official WordPress.org Plugins API (`api.wordpress.org/plugins/info/1.0/`). GitHub versions come from either the GitHub Releases API or a raw JSON file URL you provide.

= How often is data updated? =

Automatically on a schedule you choose: every 1, 6, 12, or 24 hours via WP-Cron. You can also trigger a manual refresh at any time from the Fetch Settings tab.

= Do the shortcodes make live API requests? =

No. All data is fetched in the background by the cron job or manual refresh, then stored in the database. Shortcodes read only from the database, so they add no external HTTP requests to your page loads.

= My plugin has fewer than 10 installs — will it show 0+? =

No. The WordPress.org API returns `0` for plugins with fewer than 10 active installs. The plugin detects this and displays `Fewer than 10` instead, matching what WP.org itself shows.

= What GitHub URL formats are supported? =

Two formats:

* **Releases API:** `https://api.github.com/repos/{owner}/{repo}/releases/latest` — reads the `tag_name` field automatically.
* **Raw file:** `https://raw.githubusercontent.com/{owner}/{repo}/{branch}/version.json` — reads whichever JSON key you specify (default: `version`).

= What if a plugin slug is not found on WordPress.org? =

The last known data is preserved and an error is shown in the admin table. The shortcode returns an empty string silently so your frontend is unaffected.

= How do I find the shortcode id for a GitHub source? =

The `id` is your label lowercased with spaces replaced by hyphens. For example, a label of "My Plugin" becomes `id="my-plugin"`. The exact ID is shown in the data table on the GitHub Version tab after saving.

== Screenshots ==

1. Install Tracker tab — slug management and live data table with install counts and delta.
2. WP.org Version tab — version tracking for WordPress.org plugins.
3. GitHub Version tab — row-based source management supporting Releases API and raw JSON.
4. Fetch Settings tab — auto-fetch frequency selector and cron status.

== Changelog ==

= 1.1.0 =
* Added WP.org Version Tracker tab with `[nit_wp_version]` shortcode.
* Added GitHub Version Tracker tab supporting Releases API and raw JSON file URLs.
* Added Fetch Settings tab with configurable auto-fetch frequency (1, 6, 12, 24 hours).
* Tabbed admin interface.
* `Fewer than 10` now shown correctly for new plugins instead of `0+`.

= 1.0.0 =
* Initial release — WP.org active install count tracker with `[nit_installs]` shortcode.

== Upgrade Notice ==

= 1.1.0 =
New tabs for WP.org version tracking and GitHub version tracking. No database changes — safe to upgrade.

= 1.0.0 =
Initial release. No upgrade steps required.
