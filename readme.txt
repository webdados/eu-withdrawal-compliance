=== EU Withdrawal Compliance ===
Contributors: fernandot, ayudawp
Tags: woocommerce, eu, gdpr, withdrawal, ecommerce
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds the EU online withdrawal function required by Directive (EU) 2023/2673, applicable from June 19, 2026.

== Description ==

The EU Directive 2023/2673 obliges every online retailer in the European Union to offer a digital withdrawal function from June 19, 2026. The directive requires that exercising the right of withdrawal must be at least as easy as concluding the contract was: a single click should be enough.

This plugin gives you a clean, ready-to-deploy implementation:

* A public withdrawal page automatically created on activation, pre-filled with a neutral, multilingual sample template (with a clear "review with a legal advisor" disclaimer) and the form embedded via shortcode.
* A `[ayudawp_withdrawal_form]` shortcode you can use anywhere in your site.
* A "Right of withdrawal" endpoint inside the WooCommerce **My Account** area, with a per-order "Withdraw" button shown only while the 14-day window is open.
* Automatic injection of an "Exercise withdrawal right here" notice with link to the form inside WooCommerce transactional emails (processing, on-hold and completed orders), to comply with the trader's obligation to inform consumers about the existence and placement of the withdrawal function.
* Automatic verification of the order/email pair when WooCommerce is active, including the 14-day deadline check.
* Private order notes added to the WooCommerce order at every step of the lifecycle: when the request is received and again when it is accepted, rejected or marked as completed (including the admin's comment if any).
* Confirmation email to the customer on submission and a follow-up email when the request is accepted, rejected or completed. Optional admin comment is forwarded to the customer (required for rejections, optional for completed). Notification email to the shop admin (with reply-to set to the customer for fast handling, sanitized against header injection).
* Full request log as a private custom post type, with status tracking (pending, accepted, rejected, completed), customer details, IP address and user agent for legal traceability.
* Bulk actions in the withdrawals listing to mark several requests as accepted, rejected or completed at once.
* "Withdrawal" column in the WooCommerce orders screen (legacy and HPOS) showing the status of any linked request, toggleable from "Screen Options".
* Native integration in the WooCommerce admin menu: settings live at **WooCommerce → EU Withdrawal**, request log at **WooCommerce → Withdrawals**.
* Honeypot anti-spam protection.
* Conditional asset loading: CSS only loads on the withdrawal page and inside the plugin admin screens.
* Translation-ready, fully escaped, follows WordPress Coding Standards, HPOS-compatible.
* Ships with Spanish (Spain) translation included.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the **Plugins** screen.
3. The plugin creates a "Right of withdrawal" page automatically with a sample legal template. Review and edit it from **Pages**.
4. Go to **WooCommerce → EU Withdrawal** to configure the notification email address and the page that hosts the form.
5. Add the URL of the withdrawal page to your footer or to the legal links section so it is visible from any page on your site.

== Frequently Asked Questions ==

= Will the form check the 14-day deadline? =

Yes, when WooCommerce is active. If the order is older than 14 days the plugin rejects the request with a clear message. You can disable that check or extend the grace period with the `ayudawp_euw_grace_days` and `ayudawp_euw_skip_deadline_check` filters.

= Where are withdrawal requests stored? =

Each request is saved as a private custom post type entry called `ayudawp_withdrawal`. You can manage them under **WooCommerce → Withdrawals** in your admin area. They are not publicly accessible from the frontend.

= Does it support HPOS (High-Performance Order Storage)? =

Yes. The plugin declares HPOS compatibility on load.

= Will the notice appear on every WooCommerce email? =

No. By default the notice is only added to the customer-facing emails sent during the withdrawal window: order processing, on-hold and completed. Admin emails never receive the notice. You can change the list of emails using the `ayudawp_euw_email_ids` filter.

= Is the plugin available in Spanish? =

Yes. The plugin ships with a Spanish (Spain) translation included. The form labels, admin screens and customer emails will appear in Spanish on sites with `WPLANG` or site language set to `es_ES`.

= Does the plugin pass GDPR requirements? =

The plugin asks for explicit privacy policy acceptance before submission and stores the visitor IP and user agent only for the purpose of legal traceability of the request. You should add this storage to your privacy policy.

= I installed an early version from GitHub's "Download ZIP" and the folder is "eu-withdrawal-compliance-main". How do I migrate without losing data? =

1. Deactivate the old plugin (the one with the `-main` suffix). Do not delete it yet.
2. Install this plugin from WordPress.org normally — it will be installed at the correct slug `eu-withdrawal-compliance`.
3. Activate it. The plugin reuses your existing settings, withdrawal page and request log automatically.
4. Go back to Plugins and click "Delete" on the old `-main` entry. The uninstall script detects the canonical install and preserves all your data.

== Screenshots ==

1. Public withdrawal form with all required fields.
2. Withdrawal log inside the WordPress admin.
3. Per-request detail screen with status management.
4. WooCommerce My Account integration with per-order Withdraw button.

== Changelog ==

= 1.1.0 =
* New: customer email notifications on every status change (accepted, rejected, completed).
* New: optional admin comment forwarded to the customer on status change. Required for rejections, optional for completed requests.
* New: WooCommerce order notes on every status change so the order timeline reflects the full withdrawal lifecycle.
* New: bulk actions in the withdrawals listing to mark several requests as accepted, rejected or completed at once.
* New: "Withdrawal" column in the WooCommerce orders screen (legacy and HPOS) showing the status of any linked request, toggleable from "Screen Options".
* Tweak: trimmed inline styles in the WooCommerce email notice so it inherits the email template styles instead of forcing a coloured callout box.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
Adds customer notifications on status changes, bulk actions, a withdrawal column in the orders list and order notes for the full lifecycle.

= 1.0.0 =
First public version. Deploy before June 19, 2026 to comply with EU Directive 2023/2673.

== Support ==

Need help or have suggestions?

* [Official website](https://servicios.ayudawp.com)
* [WordPress support forum](https://wordpress.org/support/plugin/eu-withdrawal-compliance/)
* [YouTube channel](https://www.youtube.com/AyudaWordPressES)
* [Documentation and tutorials](https://ayudawp.com)

Love the plugin? Please leave us a 5-star review and help spread the word!

== About AyudaWP.com ==

We are specialists in WordPress security, SEO, AI and performance optimization plugins. We create tools that solve real problems for WordPress site owners while maintaining the highest coding standards and accessibility requirements.