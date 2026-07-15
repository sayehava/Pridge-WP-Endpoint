=== Pridge WP Endpoint ===
Contributors: sayehava
Tags: printing, woocommerce, endpoint, receipt, pridge
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.3.0
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
