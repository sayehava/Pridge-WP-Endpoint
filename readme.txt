=== Pridge WP Endpoint ===
Contributors: sayehava
Tags: printing, woocommerce, endpoint, receipt, pridge
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Connect WordPress and WooCommerce to a Pridge Server virtual printer endpoint.

== Description ==

Pridge WP Endpoint submits raw print payloads to Pridge Server. It includes a public
PHP API for other plugins, multiple printer endpoints, document routing, optional commerce
integrations, and a protected print archive.

The Pridge Server and office-side desktop printing client are separate applications.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Activate Pridge WP Endpoint through the WordPress Plugins screen.
3. Open Pridge in the administration menu.
4. Enter the Pridge Server base URL on Overview.
5. Add endpoint tokens and document routes under Endpoints & Routing.
6. Save the settings and send a test print.

== Frequently Asked Questions ==

= Is WooCommerce required? =

No. Other plugins can use the public PHP API, and administrators can submit a manual test job
without WooCommerce. WooCommerce is required only for automatic order document submission.

= Does this plugin print directly to a local printer? =

No. It submits jobs to Pridge Server. A separate desktop client reserves jobs and sends
the raw payload to the selected physical printer.

= Is Germanized required? =

No. Germanized integration is optional and can be enabled or disabled from Pridge
settings when Germanized for WooCommerce is active.

= How are shipping labels handled? =

When Shiptastic for WooCommerce is active, each active carrier appears as a separate label
route. Pridge forwards the original label payload when Shiptastic creates it.

== Changelog ==

= 1.0.0 =

* Use the bundled icon on the Plugins and Update screens (the admin menu keeps WordPress's
  own printer icon).
* Fixed self-updating: GitHub's release download extracts into a randomly-named folder, and
  WordPress was installing the update under that name while removing the existing plugin
  folder - the plugin disappeared instead of being updated. The extracted folder is now
  renamed to match the plugin's own folder before WordPress installs it.
* Germanized invoice, packing-slip, and shipping-label PDFs can take time to finish
  generating after an order event fires and were being sent one at a time as each became
  available, sometimes printing partial paperwork for an order. Documents are now held
  until every routed one for an order exists, then sent together. A WP-Cron check (interval
  configurable under Settings, Integrations) picks up any order that was not immediately
  ready; an order still missing documents after a configurable timeout is flagged on
  Overview for a shop manager to send manually instead of waiting indefinitely.
* Added a live cron health monitor to the Overview page: last and next run, orders
  currently waiting on documents, a warning if WP-Cron does not appear to be firing, and a
  manual "Run check now" button. The panel updates itself in place.
* Added optional automatic status changes after a successful print: a WooCommerce order
  status and, separately, a Shiptastic shipment status, both configurable under Settings,
  Integrations.
* Reorganized the admin area: all configuration now lives on one Settings page with
  General, Integrations, and Endpoints & Routing sub-tabs. Overview is now a dashboard of
  connection status, test-print diagnostics, and automation health.

= 0.5.1 =

* Fixed: the Updates & Backups section added in 0.5.0 sat outside the page's layout
  container and was missing the fade-in class every other panel uses, so it didn't line up
  with the rest of the Overview page and used a plain WordPress table style instead of this
  plugin's own dark theme. Backups now render with the same table styling as the Print
  Archive page.

= 0.5.0 =

* Self-updating: the plugin checks github.com/sayehava/Pridge-WP-Endpoint for new releases
  (every 12 hours, or on demand from the Overview page's "Check for updates now" button)
  and appears as updatable on the native Plugins page, exactly like a WordPress.org-hosted
  plugin - same "Update Now" link, same version-details popup with release notes, same
  automatic rollback WordPress core performs if an update causes a fatal error.
* A full zip backup of the plugin's files is taken automatically immediately before
  WordPress installs an update - if the backup fails for any reason (e.g. the zip PHP
  extension is missing), the update itself is stopped rather than proceeding without one.
  Backups are stored under wp-content/uploads/pridge-wp-endpoint-backups/ (protected by
  .htaccess and an index.php placeholder against direct web access), with the last 5 kept
  automatically and a one-click "Restore this backup" button on the Overview page.

= 0.4.0 =

* Send the plugin's version to Pridge Server with every job submission and show a
  non-blocking notice in the admin area when the server reports it is on an
  incompatible major version.

= 0.3.0 =

* Rename the plugin from PrintBridge WP Endpoint to Pridge WP Endpoint, including the text
  domain, namespace, constants, hooks, and public function prefixes.
* Relicense under GPLv3 or later, with additional terms; see LICENSE and ADDITIONAL_TERMS.md.
* Match the administration area to the Pridge design language and color palette.

= 0.2.2 =

* Move selected-order PDF testing into the Germanized integration.
* Submit existing Germanized Pro invoice and packing-slip PDFs instead of generated text.
* Include existing routed Shiptastic label files without creating new labels.

= 0.2.1 =

* Add selected-order test printing to the WooCommerce integration page.
* Include existing routed Shiptastic labels in Germanized-enabled order tests.

= 0.2.0 =

* Add separate integration, endpoint routing, and print archive pages.
* Add multiple named endpoint tokens and per-document printer routes.
* Add receipt, invoice, and packing-slip order documents.
* Add Shiptastic shipped trigger and provider-specific shipping labels.
* Add protected text, binary, PDF, and image archive previews.

= 0.1.0 =

* Add initial WordPress plugin foundation.
* Add raw Pridge job submission API.
* Add optional WooCommerce and Germanized integrations.
* Add isolated dark-neon administration experience.

== License ==

GNU General Public License v3.0 or later, with one additional term under GPLv3 Section 7(b).
See LICENSE for the full license text and ADDITIONAL_TERMS.md for the additional term.
