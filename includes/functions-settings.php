<?php
/**
 * Settings page using the Settings API.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the settings page.
 *
 * When WooCommerce is active the page is nested under the WooCommerce menu
 * with the label "EU Withdrawal". Otherwise it sits under the CPT menu.
 */
function ayudawp_euw_register_settings_page() {

	$has_wc = class_exists( 'WooCommerce' );

	$parent_slug = $has_wc ? 'woocommerce' : 'edit.php?post_type=ayudawp_withdrawal';
	$menu_label  = $has_wc
		? __( 'EU Withdrawal', 'eu-withdrawal-compliance' )
		: __( 'Settings', 'eu-withdrawal-compliance' );

	add_submenu_page(
		$parent_slug,
		__( 'EU Withdrawal settings', 'eu-withdrawal-compliance' ),
		$menu_label,
		'manage_options',
		'ayudawp-euw-settings',
		'ayudawp_euw_settings_page_html'
	);
}
add_action( 'admin_menu', 'ayudawp_euw_register_settings_page' );

/**
 * Register settings, sections and fields.
 */
function ayudawp_euw_register_settings() {

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_notify_email',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'default'           => get_option( 'admin_email' ),
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_page_id',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_grace_days',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_deadline_basis',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_deadline_basis',
			'default'           => 'order_date',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_excluded_categories',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ayudawp_euw_sanitize_excluded_categories',
			'default'           => array(),
		)
	);

	add_settings_section(
		'ayudawp_euw_main_section',
		__( 'General', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_settings_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_notify_email',
		__( 'Notification email', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_email_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_main_section'
	);

	add_settings_field(
		'ayudawp_euw_page_id',
		__( 'Withdrawal page', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_page_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_main_section'
	);

	add_settings_section(
		'ayudawp_euw_deadline_section',
		__( 'Withdrawal deadline', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_deadline_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_deadline_basis',
		__( 'Calculate deadline from', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_deadline_basis_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_deadline_section'
	);

	add_settings_field(
		'ayudawp_euw_grace_days',
		__( 'Grace days', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_grace_days_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_deadline_section'
	);

	add_settings_section(
		'ayudawp_euw_exclusions_section',
		__( 'Article 16 exclusions', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_exclusions_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_excluded_categories',
		__( 'Excluded product categories', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_excluded_categories_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_exclusions_section'
	);
}
add_action( 'admin_init', 'ayudawp_euw_register_settings' );

/**
 * Section description.
 */
function ayudawp_euw_settings_section_callback() {

	echo '<p>' . esc_html__( 'Configure where notifications are sent and which page hosts the withdrawal form.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Email field callback.
 */
function ayudawp_euw_field_email_callback() {

	$value = get_option( 'ayudawp_euw_notify_email', get_option( 'admin_email' ) );

	printf(
		'<input type="email" name="ayudawp_euw_notify_email" value="%s" class="regular-text">',
		esc_attr( $value )
	);

	echo '<p class="description">' . esc_html__( 'Address that receives a notification each time a customer submits a withdrawal request.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Page selector field callback.
 */
function ayudawp_euw_field_page_callback() {

	$selected = (int) get_option( 'ayudawp_euw_page_id', 0 );

	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Captured with `echo => 0` and emitted through wp_kses() below.
	$dropdown = wp_dropdown_pages(
		array(
			'name'              => 'ayudawp_euw_page_id',
			'show_option_none'  => __( '— Select a page —', 'eu-withdrawal-compliance' ),
			'option_none_value' => '0',
			'selected'          => $selected,
			'echo'              => 0,
		)
	);
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

	echo wp_kses(
		$dropdown,
		array(
			'select' => array(
				'name'  => true,
				'id'    => true,
				'class' => true,
			),
			'option' => array(
				'class'    => true,
				'value'    => true,
				'selected' => true,
			),
		)
	);

	echo '<p class="description">' . esc_html__( 'Page where the withdrawal form is published. Make sure it includes the [ayudawp_withdrawal_form] shortcode.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Deadline section description.
 */
function ayudawp_euw_deadline_section_callback() {

	echo '<p>' . esc_html__( 'EU Directive 2011/83 sets a 14-day withdrawal period. Some member states extend this baseline; use these settings to adjust the calculation if your jurisdiction or contract terms require it.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Sanitize the deadline basis option to one of the allowed values.
 *
 * @param mixed $value Submitted value.
 * @return string Allowed key, falling back to 'order_date'.
 */
function ayudawp_euw_sanitize_deadline_basis( $value ) {

	$value   = sanitize_key( (string) $value );
	$allowed = array( 'order_date', 'completion_date' );

	return in_array( $value, $allowed, true ) ? $value : 'order_date';
}

/**
 * Deadline basis selector callback.
 */
function ayudawp_euw_field_deadline_basis_callback() {

	$value = get_option( 'ayudawp_euw_deadline_basis', 'order_date' );

	$options = array(
		'order_date'      => __( 'Order date (when the customer placed the order)', 'eu-withdrawal-compliance' ),
		'completion_date' => __( 'Completion date (when the WooCommerce order was marked as completed)', 'eu-withdrawal-compliance' ),
	);

	echo '<select name="ayudawp_euw_deadline_basis" id="ayudawp_euw_deadline_basis">';
	foreach ( $options as $key => $label ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $key ),
			selected( $value, $key, false ),
			esc_html( $label )
		);
	}
	echo '</select>';

	echo '<p class="description">' . esc_html__( 'If you choose Completion date but the order is not yet completed, the plugin falls back to the order date.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Grace days numeric field callback.
 */
function ayudawp_euw_field_grace_days_callback() {

	$value = (int) get_option( 'ayudawp_euw_grace_days', 0 );

	printf(
		'<input type="number" name="ayudawp_euw_grace_days" id="ayudawp_euw_grace_days" value="%d" min="0" max="365" step="1" class="small-text"> %s',
		(int) $value,
		esc_html__( 'days', 'eu-withdrawal-compliance' )
	);

	echo '<p class="description">' . esc_html__( 'Extra days added on top of the 14-day legal minimum. Useful when you offer a longer return window than required.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Exclusions section description.
 */
function ayudawp_euw_exclusions_section_callback() {

	echo '<p>' . esc_html__( 'Article 16 of EU Directive 2011/83 lists categories of goods exempted from the right of withdrawal: custom-made products, perishable goods, sealed digital content opened by the consumer, hygiene-sealed items, etc. Pick the WooCommerce categories that fit those exceptions, or mark individual products in the product editor under "Excluded from right of withdrawal". Withdrawal requests on orders containing excluded items are flagged for manual review — never auto-rejected, since a partial withdrawal over the rest of the order can still be valid.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Sanitize the excluded categories option to a clean array of term IDs.
 *
 * @param mixed $value Submitted value.
 * @return array<int, int>
 */
function ayudawp_euw_sanitize_excluded_categories( $value ) {

	if ( ! is_array( $value ) ) {
		return array();
	}

	return array_values( array_filter( array_map( 'absint', $value ) ) );
}

/**
 * Excluded categories instant-search field.
 *
 * Renders a typeahead input that adds chips to a list below. Each add/remove
 * is auto-saved via AJAX, no "Save changes" needed for this particular field.
 */
function ayudawp_euw_field_excluded_categories_callback() {

	if ( ! taxonomy_exists( 'product_cat' ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so there are no product categories to choose from.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	$selected_ids   = ayudawp_euw_get_excluded_category_ids();
	$selected_terms = array();

	if ( ! empty( $selected_ids ) ) {

		$terms_query = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'include'    => $selected_ids,
				'orderby'    => 'include',
			)
		);

		if ( ! is_wp_error( $terms_query ) ) {
			$selected_terms = $terms_query;
		}
	}
	?>
	<div class="ayudawp-euw-cat-picker"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'ayudawp_euw_exclusions' ) ); ?>"
		data-search-action="ayudawp_euw_search_categories"
		data-toggle-action="ayudawp_euw_toggle_excluded_category">

		<div class="ayudawp-euw-cat-picker__search">
			<input type="search"
				class="ayudawp-euw-cat-picker__input regular-text"
				id="ayudawp-euw-cat-picker-input"
				placeholder="<?php esc_attr_e( 'Search categories to exclude…', 'eu-withdrawal-compliance' ); ?>"
				autocomplete="off"
				aria-controls="ayudawp-euw-cat-picker-results"
				aria-expanded="false">
			<ul class="ayudawp-euw-cat-picker__results" id="ayudawp-euw-cat-picker-results" role="listbox" hidden></ul>
		</div>

		<ul class="ayudawp-euw-cat-picker__chips" id="ayudawp-euw-cat-picker-chips" aria-live="polite">
			<?php foreach ( $selected_terms as $term ) : ?>
				<?php $breadcrumb = ayudawp_euw_get_category_breadcrumb( $term ); ?>
				<li class="ayudawp-euw-chip" data-term-id="<?php echo esc_attr( (int) $term->term_id ); ?>">
					<span class="ayudawp-euw-chip__label">
						<strong><?php echo esc_html( $term->name ); ?></strong>
						<?php if ( '' !== $breadcrumb ) : ?>
							<span class="ayudawp-euw-chip__breadcrumb">(<?php echo esc_html( $breadcrumb ); ?>)</span>
						<?php endif; ?>
					</span>
					<button type="button"
						class="ayudawp-euw-chip__remove"
						aria-label="<?php
							/* translators: %s: category name. */
							echo esc_attr( sprintf( __( 'Remove %s from exclusions', 'eu-withdrawal-compliance' ), $term->name ) );
						?>">×</button>
				</li>
			<?php endforeach; ?>
		</ul>

		<p class="description">
			<?php esc_html_e( 'Type to search a product category and click it to add. Subcategories inherit the exclusion automatically. Changes are saved instantly.', 'eu-withdrawal-compliance' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Render the settings page.
 */
function ayudawp_euw_settings_page_html() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<?php
		$page_id = (int) get_option( 'ayudawp_euw_page_id', 0 );

		if ( $page_id && get_post( $page_id ) ) {
			$page_url = get_permalink( $page_id );
			printf(
				'<p>%1$s <a href="%2$s" target="_blank" rel="noopener">%3$s</a></p>',
				esc_html__( 'Withdrawal form public URL:', 'eu-withdrawal-compliance' ),
				esc_url( $page_url ),
				esc_html( $page_url )
			);
		}
		?>

		<form action="options.php" method="post">
			<?php
			settings_fields( 'ayudawp_euw_settings_group' );
			do_settings_sections( 'ayudawp-euw-settings' );
			submit_button( __( 'Save changes', 'eu-withdrawal-compliance' ) );
			?>
		</form>

		<hr>

		<h2><?php esc_html_e( 'How to use this plugin', 'eu-withdrawal-compliance' ); ?></h2>

		<p><?php esc_html_e( 'You can place the withdrawal form anywhere on your site using the following shortcode:', 'eu-withdrawal-compliance' ); ?></p>
		<p><code>[ayudawp_withdrawal_form]</code></p>

		<?php if ( function_exists( 'wc_get_account_endpoint_url' ) ) : ?>
			<p>
				<?php
				$endpoint_url = wc_get_account_endpoint_url( 'withdrawal' );
				printf(
					/* translators: %s: WooCommerce My Account withdrawal endpoint URL. */
					esc_html__( 'WooCommerce customers can also access the form from their account at: %s', 'eu-withdrawal-compliance' ),
					'<a href="' . esc_url( $endpoint_url ) . '" target="_blank" rel="noopener"><code>' . esc_html( $endpoint_url ) . '</code></a>'
				);
				?>
			</p>
		<?php endif; ?>

		<p>
			<?php
			esc_html_e(
				'For maximum visibility, link the withdrawal page from your footer next to your privacy policy and terms of service. Customers must be able to find it within a couple of clicks from any page on the site.',
				'eu-withdrawal-compliance'
			);
			?>
		</p>

		<hr>

		<h2><?php esc_html_e( 'About the EU withdrawal function', 'eu-withdrawal-compliance' ); ?></h2>
		<p>
			<?php
			echo wp_kses(
				__( 'This plugin implements the digital withdrawal function required by <a href="https://eur-lex.europa.eu/eli/dir/2023/2673/oj" target="_blank" rel="noopener">EU Directive 2023/2673</a>, applicable to all EU online retailers from June 19, 2026.', 'eu-withdrawal-compliance' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			);
			?>
		</p>

		<?php
		// Promotional banner with rotating AyudaWP plugins and services.
		if ( class_exists( 'Aeuw_Promo_Banner' ) ) {
			$promo_banner = new Aeuw_Promo_Banner( 'eu-withdrawal-compliance', 'aeuw' );
			$promo_banner->render( 'horizontal' );
		}
		?>
	</div>
	<?php
}