=== EU Withdrawal Compliance ===
Contributors: fernandot, ayudawp
Tags: woocommerce, eu, gdpr, withdrawal, ecommerce
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds the EU online withdrawal function required by Directive (EU) 2023/2673, applicable from June 19, 2026.

== Description ==

The EU Directive 2023/2673 obliges every online retailer in the European Union to offer a digital withdrawal function from June 19, 2026. The directive requires that exercising the right of withdrawal must be at least as easy as concluding the contract was: a single click should be enough.

This plugin gives you a clean, ready-to-deploy implementation:

* A public withdrawal page automatically created on activation, pre-filled with a neutral, multilingual sample template (with a clear "review with a legal advisor" disclaimer) and the form embedded via shortcode.
* A `[ayudawp_withdrawal_form]` shortcode you can use anywhere in your site.
* A "Right of withdrawal" endpoint inside the WooCommerce **My Account** area, with a per-order "Withdraw" button shown only while the configured withdrawal window is open.
* Automatic injection of an "Exercise withdrawal right here" notice with link to the form inside WooCommerce transactional emails (processing, on-hold and completed orders), to comply with the trader's obligation to inform consumers about the existence and placement of the withdrawal function.
* Automatic verification of the order/email pair when WooCommerce is active, including the 14-day deadline check.
* **Configurable deadline calculation**: choose whether the 14-day window starts from the order date or from the WooCommerce completion date, and add extra grace days from the settings page.
* **Article 16 exclusions**: mark individual products or whole categories as excluded from the right of withdrawal (custom-made, perishable, sealed digital, etc.). Requests on orders containing excluded items are flagged for the admin to review manually — never auto-rejected, since a partial withdrawal over the rest of the order can still be valid.
* **Verifiable receipt hash**: every submission generates a SHA-256 hash sent to the customer in the confirmation email so they keep a tamper-evident proof on a durable medium.
* Private order notes added to the WooCommerce order at every step of the lifecycle: when the request is received and again when it is accepted, rejected or marked as completed (including the admin's comment if any).
* Confirmation email to the customer on submission and a follow-up email when the request is accepted, rejected or completed. Optional admin comment is forwarded to the customer (required for rejections, optional for completed). Notification email to the shop admin (with reply-to set to the customer for fast handling, sanitized against header injection).
* Full request log as a private custom post type, with status tracking (pending, accepted, rejected, completed), customer details, IP address, user agent and submission timestamp for legal traceability.
* Bulk actions in the withdrawals listing to mark several requests as accepted, rejected or completed at once.
* "Withdrawal" column in the WooCommerce orders screen (legacy and HPOS) showing the status of any linked request, toggleable from "Screen Options".
* Native integration in the WooCommerce admin menu: settings live at **WooCommerce → EU Withdrawal**, request log at **WooCommerce → Withdrawals**.
* Honeypot anti-spam protection.
* Conditional asset loading: CSS only loads on the withdrawal page and inside the plugin admin screens.
* Translation-ready, fully escaped, follows WordPress Coding Standards, HPOS-compatible.
* Ships with Spanish (Spain) translation included.

== Privacy ==

This plugin stores the following personal data for each withdrawal request, exclusively to fulfil the legal traceability of consumer rights and to allow the shop to handle the request:

* Customer name and email address (required to contact the consumer about the request).
* Order reference and order date (required to validate the request against the purchase).
* IP address and User-Agent string (required to evidence when and how the request was submitted, in line with the directive's "durable medium" requirement).
* Submission timestamp (UTC) and SHA-256 receipt hash (required to recompute and verify the integrity of the original submission if disputed).

Data is stored as a private custom post type entry (`ayudawp_withdrawal`) accessible only to administrators. The plugin does not transmit any data to third-party services; all communication happens between the shop and the customer via standard WordPress emails.

You should add a section to your site's privacy policy describing this storage. The plugin does not currently expose its data to the WordPress Personal Data Export / Erase tools — that integration is planned for a later release.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the **Plugins** screen.
3. The plugin creates a "Right of withdrawal" page automatically with a sample legal template. Review and edit it from **Pages**.
4. Go to **WooCommerce → EU Withdrawal** to configure the notification email address and the page that hosts the form.
5. Add the URL of the withdrawal page to your footer or to the legal links section so it is visible from any page on your site.

== Frequently Asked Questions ==

= Will the form check the 14-day deadline? =

Yes, when WooCommerce is active. If the order is older than the configured window the plugin rejects the request with a clear message. From version 1.2.0 you can configure the calculation basis (order date vs. completion date) and add extra grace days directly from **WooCommerce → EU Withdrawal**. The legacy `ayudawp_euw_grace_days` and `ayudawp_euw_skip_deadline_check` filters still work for programmatic overrides.

= How do I mark products that are excluded from the right of withdrawal (Article 16)? =

You have two options, which can be combined:

1. **By category**: from **WooCommerce → EU Withdrawal → Article 16 exclusions** pick the WooCommerce categories whose products fall under one of the Article 16 exceptions (custom-made, perishable, sealed digital content opened by the consumer, hygiene-sealed items, etc.).
2. **By product**: when editing a product, tick the **Excluded from right of withdrawal** checkbox under the General tab.

When a withdrawal request lands on an order containing excluded items, the plugin flags it in the admin notification email and on the request detail screen. The request is **never auto-rejected**, because a partial withdrawal over the non-excluded items in the same order can still be valid. The admin reviews and decides.

= What is the receipt verification code in the customer email? =

It is a SHA-256 hash computed from the request data (post ID, customer name, email, order reference, scope, order date and submission timestamp). The customer keeps the email as a tamper-evident proof on a durable medium. If a dispute later arises, you can recompute the hash from the stored fields with the `ayudawp_euw_compute_receipt_hash()` helper and confirm the original submission was not altered.

= Where are withdrawal requests stored? =

Each request is saved as a private custom post type entry called `ayudawp_withdrawal`. You can manage them under **WooCommerce → Withdrawals** in your admin area. They are not publicly accessible from the frontend.

= Does it support HPOS (High-Performance Order Storage)? =

Yes. The plugin declares HPOS compatibility on load.

= Will the notice appear on every WooCommerce email? =

No. By default the notice is only added to the customer-facing emails sent during the withdrawal window: order processing, on-hold and completed. Admin emails never receive the notice. You can change the list of emails using the `ayudawp_euw_email_ids` filter.

= Is the plugin available in Spanish? =

Yes. The plugin ships with a Spanish (Spain) translation included. The form labels, admin screens and customer emails will appear in Spanish on sites with `WPLANG` or site language set to `es_ES`. Other locales can be contributed via the WordPress.org translation platform once the plugin is published there.

= Does the plugin pass GDPR requirements? =

The plugin asks for explicit privacy policy acceptance before submission and stores the visitor IP and user agent only for the purpose of legal traceability of the request. See the **Privacy** section above for the full list of stored fields. Add this storage to your site's privacy policy.

= What happens if the customer deletes their WordPress user account? =

The withdrawal log is independent of the WordPress user table — it lives as a private custom post type indexed by the customer email. Deleting the user account does not delete the log; you must delete the corresponding `ayudawp_withdrawal` entries manually if your retention policy requires it. A native integration with the WordPress Personal Data Export / Erase tools is planned for a later release.

= Can I customise the email subjects and bodies? =

Currently the emails are sent in plain text and their copy is translatable through the standard WordPress text-domain. HTML email templates that respect the WooCommerce email theme are planned for a later release. For now, advanced customisation requires hooking into the wp_mail filters.

= Which hooks does the plugin expose for developers? =

Filters:

* `ayudawp_euw_grace_days` — extra days added to the 14-day deadline. The default is the value stored in settings; the filter receives that value, so returning `$days + N` adds on top of it.
* `ayudawp_euw_skip_deadline_check` — return `true` to disable the deadline check entirely. Receives the WC_Order as second argument.
* `ayudawp_euw_email_ids` — array of WooCommerce email IDs where the withdrawal notice is injected.

Actions:

* `ayudawp_euw_after_submission` — fires after a withdrawal request has been processed. Arguments: CPT ID, submission data array.
* `ayudawp_euw_after_status_change` — fires after a status change (individual or bulk). Arguments: CPT ID, new status, optional admin comment.

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

= 1.2.1 =
* Fix: validate that the WooCommerce order exists when WC is active. The previous fallback used to accept submissions whose order number could not be matched against a real WC order — intended as an escape hatch for non-WC purchases — which let users submit withdrawals with completely invented order numbers. Sites that genuinely accept non-WC purchases can opt back into the lenient behaviour with the new `ayudawp_euw_allow_unverified_order` filter.
* Fix: translate the Scope value (Full/Partial) in the withdrawal detail metabox. It used to render the raw stored value in English even on translated sites.

= 1.2.0 =
* New: Article 16 exclusions. Mark individual products or whole WooCommerce categories as excluded from the right of withdrawal. Subcategories inherit the exclusion from the parent automatically. Withdrawal requests on orders containing excluded items are flagged for manual review (never auto-rejected) so a partial withdrawal over the rest of the order can still be valid.
* New: instant-search picker for excluded categories in the settings page, with removable chips and instant auto-save.
* New: inherited exclusion is reflected in the product editor — the per-product checkbox renders ticked and disabled with a note pointing to the category responsible for the inheritance.
* New: verifiable SHA-256 receipt hash. Every submission generates a hash sent to the customer in the confirmation email and stored on the request. Acts as tamper-evident proof on a durable medium and can be recomputed later from the stored fields.
* New: configurable withdrawal deadline. Choose whether the 14-day window starts from the order date or from the WooCommerce completion date, and add extra grace days directly from the settings page. The `ayudawp_euw_grace_days` filter still works on top of the stored value.
* New: submission timestamp (UTC) stored alongside each request and surfaced in the request detail metabox.
* Tweak: polished CPT labels ("Edit withdrawal", "New withdrawal", etc.).
* Tweak: split `functions-admin.php` into `admin/columns.php`, `admin/metaboxes.php` and `admin/bulk-actions.php` for easier maintenance. No behavioural change.
* i18n: updated Spanish (es_ES) translation with every new string.

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

= 1.2.1 =
Validation fix: the form now correctly rejects submissions whose order number does not match a real WooCommerce order. Update strongly recommended.

= 1.2.0 =
Adds Article 16 exclusions (with hierarchical category inheritance and an instant-search picker), verifiable SHA-256 receipt hashes for proof of submission, and configurable deadline settings (basis + grace days) directly from the settings page.

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