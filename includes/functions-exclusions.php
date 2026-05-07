<?php
/**
 * Article 16 exclusions: products that fall outside the right of withdrawal.
 *
 * Article 16 of Directive 2011/83/EU lists categories of goods and services
 * exempted from the right of withdrawal (custom-made, perishable, sealed
 * digital content, hygiene-sealed items, etc.). This module lets the shop
 * mark individual products or whole categories as excluded so that any
 * withdrawal request landing on an order containing those items is flagged
 * for the admin to review — never auto-rejected, since a partial withdrawal
 * over the non-excluded items can still be valid.
 *
 * Hierarchical exclusion: when a parent category is marked, all of its
 * descendants inherit the exclusion automatically.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read the excluded categories option as a clean array of integer term IDs.
 *
 * @return array<int, int>
 */
function ayudawp_euw_get_excluded_category_ids() {

	$value = (array) get_option( 'ayudawp_euw_excluded_categories', array() );

	return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
}

/**
 * Build a short breadcrumb-style label for a product_cat term so the admin
 * can disambiguate two categories that share the same leaf name.
 *
 * @param WP_Term $term Term object.
 * @return string Empty string if the term has no parent.
 */
function ayudawp_euw_get_category_breadcrumb( $term ) {

	if ( ! $term instanceof WP_Term ) {
		return '';
	}

	$ancestors = get_ancestors( $term->term_id, 'product_cat' );

	if ( empty( $ancestors ) ) {
		return '';
	}

	$ancestors = array_reverse( $ancestors );
	$names     = array();

	foreach ( $ancestors as $ancestor_id ) {

		$ancestor = get_term( (int) $ancestor_id, 'product_cat' );

		if ( $ancestor instanceof WP_Term ) {
			$names[] = $ancestor->name;
		}
	}

	return implode( ' › ', $names );
}

/**
 * Find the WP_Term that makes a product inherit the excluded status from
 * categories. Walks up the ancestor chain so a parent category marked as
 * excluded also covers products in its descendants.
 *
 * @param int $product_id Product ID.
 * @return WP_Term|null Term that triggers the exclusion, or null if none.
 */
function ayudawp_euw_get_inherited_exclusion_term( $product_id ) {

	$product_id = absint( $product_id );

	if ( ! $product_id ) {
		return null;
	}

	$excluded_ids = ayudawp_euw_get_excluded_category_ids();

	if ( empty( $excluded_ids ) ) {
		return null;
	}

	$product_terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

	if ( is_wp_error( $product_terms ) || empty( $product_terms ) ) {
		return null;
	}

	foreach ( $product_terms as $term_id ) {

		$term_id = (int) $term_id;
		$chain   = array_merge(
			array( $term_id ),
			array_map( 'intval', get_ancestors( $term_id, 'product_cat' ) )
		);

		foreach ( $chain as $candidate_id ) {

			if ( ! in_array( $candidate_id, $excluded_ids, true ) ) {
				continue;
			}

			$term = get_term( $candidate_id, 'product_cat' );

			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}
	}

	return null;
}

/**
 * Render the per-product checkbox in the WooCommerce product editor.
 *
 * When the product inherits its excluded status from a parent category we
 * show the checkbox ticked but disabled, with a contextual note pointing
 * the admin to the global setting. A hidden flag tells the save handler
 * to leave the meta untouched in that case.
 */
function ayudawp_euw_render_product_exclusion_field() {

	global $post;

	$product_id   = ( $post && isset( $post->ID ) ) ? (int) $post->ID : 0;
	$current_meta = $product_id ? get_post_meta( $product_id, '_ayudawp_euw_excluded', true ) : '';
	$inherited    = $product_id ? ayudawp_euw_get_inherited_exclusion_term( $product_id ) : null;

	if ( $inherited instanceof WP_Term ) {

		echo '<p class="form-field _ayudawp_euw_excluded_field">';
		echo '<label for="_ayudawp_euw_excluded_disabled">' . esc_html__( 'Excluded from right of withdrawal', 'eu-withdrawal-compliance' ) . '</label>';
		echo '<input type="checkbox" id="_ayudawp_euw_excluded_disabled" disabled checked>';
		echo '<input type="hidden" name="_ayudawp_euw_excluded_inherited" value="1">';
		echo '<span class="description">';
		printf(
			/* translators: %s: parent category name. */
			esc_html__( 'Inherited from the “%s” category, marked as excluded in EU Withdrawal settings. Remove the category from the exclusion list (or change the product\'s categories) to lift the exclusion.', 'eu-withdrawal-compliance' ),
			esc_html( $inherited->name )
		);
		echo '</span>';
		echo '</p>';

		return;
	}

	woocommerce_wp_checkbox(
		array(
			'id'          => '_ayudawp_euw_excluded',
			'label'       => __( 'Excluded from right of withdrawal', 'eu-withdrawal-compliance' ),
			'description' => __( 'Tick this box if the product falls under one of the Article 16 exceptions of EU Directive 2011/83 (custom-made, perishable, sealed digital content opened by the consumer, etc.).', 'eu-withdrawal-compliance' ),
			'desc_tip'    => true,
			'value'       => $current_meta,
		)
	);
}
add_action( 'woocommerce_product_options_general_product_data', 'ayudawp_euw_render_product_exclusion_field' );

/**
 * Persist the exclusion checkbox value when the product is saved.
 *
 * If the field rendered as inherited, we skip writing the meta entirely so
 * a previously-stored manual flag is preserved and the disabled checkbox
 * does not silently overwrite anything.
 *
 * @param int $post_id Product ID.
 */
function ayudawp_euw_save_product_exclusion_field( $post_id ) {

	// WooCommerce already verifies the nonce and capability for product saves
	// before firing this hook.
	// phpcs:disable WordPress.Security.NonceVerification.Missing

	if ( isset( $_POST['_ayudawp_euw_excluded_inherited'] ) ) {
		return;
	}

	$value = isset( $_POST['_ayudawp_euw_excluded'] ) ? 'yes' : 'no';

	// phpcs:enable WordPress.Security.NonceVerification.Missing

	update_post_meta( $post_id, '_ayudawp_euw_excluded', $value );
}
add_action( 'woocommerce_process_product_meta', 'ayudawp_euw_save_product_exclusion_field' );

/**
 * Whether a product (by ID) is excluded from the right of withdrawal.
 *
 * Checks the per-product flag first and falls back to category-level
 * exclusion (including inheritance from ancestor categories).
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function ayudawp_euw_is_product_excluded( $product_id ) {

	$product_id = absint( $product_id );

	if ( ! $product_id ) {
		return false;
	}

	if ( 'yes' === get_post_meta( $product_id, '_ayudawp_euw_excluded', true ) ) {
		return true;
	}

	return null !== ayudawp_euw_get_inherited_exclusion_term( $product_id );
}

/**
 * Build the list of excluded items contained in a WooCommerce order.
 *
 * Returns an array of associative arrays with `product_id`, `name` and
 * `quantity` so the admin notification and the CPT detail screen can show
 * them without re-loading the order.
 *
 * @param int $wc_order_id WC order ID.
 * @return array<int, array<string, mixed>>
 */
function ayudawp_euw_get_excluded_items_in_order( $wc_order_id ) {

	if ( ! function_exists( 'wc_get_order' ) ) {
		return array();
	}

	$order = wc_get_order( absint( $wc_order_id ) );

	if ( ! $order ) {
		return array();
	}

	$excluded = array();

	foreach ( $order->get_items() as $item ) {

		if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
			continue;
		}

		$product_id   = (int) $item->get_product_id();
		$variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;

		$is_excluded = ayudawp_euw_is_product_excluded( $product_id )
			|| ( $variation_id && ayudawp_euw_is_product_excluded( $variation_id ) );

		if ( ! $is_excluded ) {
			continue;
		}

		$excluded[] = array(
			'product_id' => $product_id,
			'name'       => $item->get_name(),
			'quantity'   => method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 1,
		);
	}

	return $excluded;
}

/**
 * Build a presentation array for a product_cat term to send to the JS UI.
 *
 * @param WP_Term $term Term object.
 * @return array<string, mixed>
 */
function ayudawp_euw_format_category_for_js( $term ) {

	$breadcrumb = ayudawp_euw_get_category_breadcrumb( $term );

	return array(
		'id'         => (int) $term->term_id,
		'name'       => $term->name,
		'breadcrumb' => $breadcrumb,
		'count'      => (int) $term->count,
	);
}

/**
 * AJAX: search product categories matching a term.
 *
 * Excludes categories already in the exclusion list so the dropdown shows
 * only addable items. Returns at most 20 results.
 */
function ayudawp_euw_ajax_search_categories() {

	check_ajax_referer( 'ayudawp_euw_exclusions', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'eu-withdrawal-compliance' ) ), 403 );
	}

	if ( ! taxonomy_exists( 'product_cat' ) ) {
		wp_send_json_success( array( 'results' => array() ) );
	}

	$search   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$excluded = ayudawp_euw_get_excluded_category_ids();

	$args = array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'number'     => 20,
		'orderby'    => 'name',
		'order'      => 'ASC',
		'exclude'    => $excluded,
	);

	if ( '' !== $search ) {
		$args['search'] = $search;
	}

	$terms = get_terms( $args );

	if ( is_wp_error( $terms ) ) {
		wp_send_json_error( array( 'message' => $terms->get_error_message() ), 500 );
	}

	$results = array();

	foreach ( $terms as $term ) {
		$results[] = ayudawp_euw_format_category_for_js( $term );
	}

	wp_send_json_success( array( 'results' => $results ) );
}
add_action( 'wp_ajax_ayudawp_euw_search_categories', 'ayudawp_euw_ajax_search_categories' );

/**
 * AJAX: add or remove a category from the exclusion list.
 *
 * Saves the option immediately so the admin does not have to hit "Save
 * changes" for every single chip change, mimicking the NoIndexer-style
 * UX of instant-save chips.
 */
function ayudawp_euw_ajax_toggle_excluded_category() {

	check_ajax_referer( 'ayudawp_euw_exclusions', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'eu-withdrawal-compliance' ) ), 403 );
	}

	$term_id = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
	$action  = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

	if ( ! $term_id || ! in_array( $action, array( 'add', 'remove' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'eu-withdrawal-compliance' ) ), 400 );
	}

	if ( ! taxonomy_exists( 'product_cat' ) ) {
		wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'eu-withdrawal-compliance' ) ), 400 );
	}

	$term = get_term( $term_id, 'product_cat' );

	if ( ! $term instanceof WP_Term ) {
		wp_send_json_error( array( 'message' => __( 'Category not found.', 'eu-withdrawal-compliance' ) ), 404 );
	}

	$current = ayudawp_euw_get_excluded_category_ids();

	if ( 'add' === $action ) {
		if ( ! in_array( $term_id, $current, true ) ) {
			$current[] = $term_id;
		}
	} else {
		$current = array_values( array_diff( $current, array( $term_id ) ) );
	}

	update_option( 'ayudawp_euw_excluded_categories', $current );

	wp_send_json_success(
		array(
			'category' => ayudawp_euw_format_category_for_js( $term ),
			'list'     => $current,
		)
	);
}
add_action( 'wp_ajax_ayudawp_euw_toggle_excluded_category', 'ayudawp_euw_ajax_toggle_excluded_category' );