<?php
/**
 * WooCommerce integration: order validation, My Account endpoint and order notes.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the user-supplied order reference to a WC_Order instance.
 *
 * Accepts both the WordPress internal order ID (when no numbering plugin is
 * active) and the displayed order number stored by plugins such as
 * "WooCommerce Sequential Order Numbers" (free + Pro) or "Custom Order Numbers
 * for WooCommerce" (WPFactory) in the standard `_order_number` post meta.
 *
 * Resolution strategies, in order of specificity:
 *   1. `ayudawp_euw_pre_resolve_wc_order` filter — short-circuit hook for
 *      plugins with non-meta numbering schemes (e.g. YITH Sequential Order
 *      Number, custom ERP integrations).
 *   2. Lookup by `_order_number` meta — covers the de-facto standard used by
 *      the most popular numbering plugins.
 *   3. Literal ID lookup with strict cross-check against `get_order_number()`
 *      — only accepts when the displayed number matches what the customer
 *      typed. Prevents access by guessing internal IDs in stores that use
 *      custom numbering.
 *
 * The resolved order (or false) is finally passed through the
 * `ayudawp_euw_resolve_wc_order` filter for late override / auditing.
 *
 * @param string $order_ref Raw order reference as typed by the customer.
 * @return WC_Order|false WC_Order instance, or false when no match was found.
 */
function ayudawp_euw_resolve_wc_order( $order_ref ) {

	$order_ref = is_scalar( $order_ref ) ? trim( (string) $order_ref ) : '';

	if ( '' === $order_ref || ! function_exists( 'wc_get_order' ) ) {
		return false;
	}

	/**
	 * Filter the resolution result before built-in strategies run.
	 *
	 * Return a WC_Order instance to short-circuit. Return false to reject
	 * explicitly. Return null (default) to let the built-in strategies run.
	 *
	 * @param WC_Order|false|null $pre       Pre-resolved order, or null to fall through.
	 * @param string              $order_ref Raw order reference typed by the customer.
	 */
	$pre = apply_filters( 'ayudawp_euw_pre_resolve_wc_order', null, $order_ref );

	if ( $pre instanceof WC_Order ) {
		return $pre;
	}

	if ( false === $pre ) {
		return false;
	}

	$order = false;

	// Strategy 1 — meta `_order_number` lookup. HPOS/CPT agnostic via wc_get_orders().
	if ( function_exists( 'wc_get_orders' ) ) {
		$matches = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => '_order_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $order_ref,      // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'    => 'date',
				'order'      => 'DESC',
				'return'     => 'ids',
				'status'     => array_keys( wc_get_order_statuses() ),
			)
		);

		if ( ! empty( $matches ) ) {
			$order = wc_get_order( (int) $matches[0] );
		}
	}

	// Strategy 2 — literal ID with strict displayed-number cross-check.
	if ( ! $order && ctype_digit( $order_ref ) ) {
		$candidate = wc_get_order( absint( $order_ref ) );

		if ( $candidate ) {
			$displayed = method_exists( $candidate, 'get_order_number' )
				? (string) $candidate->get_order_number()
				: (string) $candidate->get_id();

			if ( trim( $displayed ) === $order_ref ) {
				$order = $candidate;
			}
		}
	}

	/**
	 * Filter the final resolution result.
	 *
	 * @param WC_Order|false $order     Resolved order or false.
	 * @param string         $order_ref Raw order reference typed by the customer.
	 */
	return apply_filters( 'ayudawp_euw_resolve_wc_order', $order, $order_ref );
}

/**
 * Validate that a given order/email pair belongs to a real WC order
 * and that the 14-day withdrawal window is still open.
 *
 * If WooCommerce is not active, the function returns valid by default
 * because we cannot check anything against an order database. When WC is
 * active, the order must exist and the email must match its billing email;
 * sites that genuinely accept non-WC purchases can opt back into the
 * lenient behaviour through the `ayudawp_euw_allow_unverified_order` filter.
 *
 * @param string $order_ref Order number or ID provided by the user.
 * @param string $email     Customer email.
 * @return array {
 *     @type bool   $valid     Whether the order is valid.
 *     @type string $error     Error code if invalid.
 *     @type int    $order_id  WooCommerce order ID if matched.
 * }
 */
function ayudawp_euw_validate_wc_order( $order_ref, $email ) {

	$default = array(
		'valid'    => true,
		'error'    => '',
		'order_id' => 0,
	);

	// Skip validation if WooCommerce is not active.
	if ( ! function_exists( 'wc_get_order' ) ) {
		return $default;
	}

	$order = ayudawp_euw_resolve_wc_order( $order_ref );

	// If WooCommerce is active but we cannot match the order, fail validation.
	// A filter allows opting back into the previous lenient behaviour for sites
	// that genuinely accept non-WC purchases (manual invoices, marketplaces).
	if ( ! $order ) {
		if ( apply_filters( 'ayudawp_euw_allow_unverified_order', false, $order_ref, $email ) ) {
			return $default;
		}

		return array(
			'valid'    => false,
			'error'    => 'order',
			'order_id' => 0,
		);
	}

	// If we have a WC order, verify the email matches.
	$order_email = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : '';

	if ( ! empty( $order_email ) && strtolower( trim( $order_email ) ) !== strtolower( trim( $email ) ) ) {
		return array(
			'valid'    => false,
			'error'    => 'order',
			'order_id' => 0,
		);
	}

	// Check the 14-day window. Basis (order date vs completion date) and grace
	// days are configurable from the settings page.
	$deadline = ayudawp_euw_get_order_deadline_timestamp( $order );

	if ( $deadline && time() > $deadline && ! apply_filters( 'ayudawp_euw_skip_deadline_check', false, $order ) ) {
		return array(
			'valid'    => false,
			'error'    => 'expired',
			'order_id' => 0,
		);
	}

	return array(
		'valid'    => true,
		'error'    => '',
		'order_id' => $order->get_id(),
	);
}

/**
 * Compute the timestamp at which the withdrawal window for a WC order closes.
 *
 * Reads the deadline basis (order date vs completion date) and the grace days
 * from the plugin settings, then applies the `ayudawp_euw_grace_days` filter
 * for backward compatibility with installs that customised the window before
 * the UI was added.
 *
 * @param object $order WC_Order instance.
 * @return int Unix timestamp, or 0 if no usable date is available.
 */
function ayudawp_euw_get_order_deadline_timestamp( $order ) {

	$basis     = get_option( 'ayudawp_euw_deadline_basis', 'order_date' );
	$base_date = null;

	if ( 'completion_date' === $basis && method_exists( $order, 'get_date_completed' ) ) {
		$base_date = $order->get_date_completed();
	}

	if ( ! $base_date && method_exists( $order, 'get_date_created' ) ) {
		$base_date = $order->get_date_created();
	}

	if ( ! $base_date ) {
		return 0;
	}

	$deadline = $base_date->getTimestamp() + ( 14 * DAY_IN_SECONDS );

	$option_grace = (int) get_option( 'ayudawp_euw_grace_days', 0 );

	/** This filter is documented in includes/functions-woocommerce.php */
	$grace_days = (int) apply_filters( 'ayudawp_euw_grace_days', $option_grace );
	$deadline  += $grace_days * DAY_IN_SECONDS;

	return $deadline;
}

/**
 * Return the order statuses for which the withdrawal button/notice is offered.
 *
 * Statuses are stored and returned without the `wc-` prefix to align with
 * `WC_Order::get_status()`. Defaults to processing and completed.
 *
 * @param object|null $order Optional WC_Order, passed to the filter.
 * @return array<int, string>
 */
function ayudawp_euw_get_allowed_statuses( $order = null ) {

	$stored = get_option( 'ayudawp_euw_allowed_statuses', array( 'processing', 'completed' ) );

	if ( ! is_array( $stored ) ) {
		$stored = array( 'processing', 'completed' );
	}

	$statuses = array_values( array_filter( array_map( 'sanitize_key', $stored ) ) );

	/**
	 * Filter the order statuses considered eligible for the withdrawal flow.
	 *
	 * Use this filter to force a specific list programmatically, regardless of
	 * the option saved in settings.
	 *
	 * @param array<int, string> $statuses Status keys without the `wc-` prefix.
	 * @param object|null        $order    Current WC_Order, when available.
	 */
	return (array) apply_filters( 'ayudawp_euw_allowed_statuses', $statuses, $order );
}

/**
 * Decide whether the withdrawal button/notice should be shown for an order.
 *
 * Combines the eligibility checks reused by the My Account action and the
 * email notice injector: order present, status in the configured whitelist,
 * and withdrawal deadline still open.
 *
 * @param object $order WC_Order instance.
 * @return bool
 */
function ayudawp_euw_should_show_withdrawal( $order ) {

	if ( ! $order || ! method_exists( $order, 'get_status' ) ) {
		return false;
	}

	$allowed = ayudawp_euw_get_allowed_statuses( $order );

	if ( empty( $allowed ) || ! in_array( $order->get_status(), $allowed, true ) ) {
		return false;
	}

	$deadline = ayudawp_euw_get_order_deadline_timestamp( $order );

	if ( ! $deadline || time() > $deadline ) {
		return false;
	}

	return true;
}

/**
 * Add a private note to the WooCommerce order linking to the withdrawal log.
 *
 * @param int    $wc_order_id WC order ID.
 * @param int    $cpt_id      Withdrawal CPT ID.
 * @param string $scope       Withdrawal scope.
 * @param string $details     Customer-provided details.
 */
function ayudawp_euw_add_wc_order_note( $wc_order_id, $cpt_id, $scope, $details ) {

	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	$order = wc_get_order( $wc_order_id );

	if ( ! $order ) {
		return;
	}

	$scope_label = ( 'partial' === $scope )
		? __( 'partial', 'eu-withdrawal-compliance' )
		: __( 'full', 'eu-withdrawal-compliance' );

	$note = sprintf(
		/* translators: 1: scope label, 2: details, 3: log ID. */
		__( 'EU withdrawal request received (%1$s). Details: %2$s. Log ID: #%3$d', 'eu-withdrawal-compliance' ),
		$scope_label,
		( ! empty( $details ) ? $details : '—' ),
		$cpt_id
	);

	$order->add_order_note( $note, 0, false );
	$order->update_meta_data( '_ayudawp_euw_request_id', $cpt_id );
	$order->save();
}

/**
 * Add a private note to the WC order when a withdrawal status changes.
 *
 * @param int    $wc_order_id WC order ID.
 * @param int    $cpt_id      Withdrawal CPT ID.
 * @param string $status      New status.
 * @param string $comment     Optional admin comment.
 */
function ayudawp_euw_add_status_order_note( $wc_order_id, $cpt_id, $status, $comment = '' ) {

	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	$order = wc_get_order( $wc_order_id );

	if ( ! $order ) {
		return;
	}

	$labels = array(
		'pending'   => __( 'pending', 'eu-withdrawal-compliance' ),
		'accepted'  => __( 'accepted', 'eu-withdrawal-compliance' ),
		'rejected'  => __( 'rejected', 'eu-withdrawal-compliance' ),
		'completed' => __( 'completed', 'eu-withdrawal-compliance' ),
	);

	$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;

	$note = sprintf(
		/* translators: 1: status label, 2: log ID. */
		__( 'EU withdrawal request %1$s. Log ID: #%2$d', 'eu-withdrawal-compliance' ),
		$label,
		$cpt_id
	);

	if ( '' !== $comment ) {
		$note .= "\n" . sprintf(
			/* translators: %s: admin comment. */
			__( 'Comment: %s', 'eu-withdrawal-compliance' ),
			$comment
		);
	}

	$order->add_order_note( $note, 0, false );
}

/**
 * Register the My Account endpoint for WooCommerce.
 */
function ayudawp_euw_register_wc_endpoint() {

	add_rewrite_endpoint( 'withdrawal', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'ayudawp_euw_register_wc_endpoint' );

/**
 * Add the endpoint to WooCommerce's My Account query vars.
 *
 * @param array $vars Query vars.
 * @return array
 */
function ayudawp_euw_add_query_var( $vars ) {

	$vars[] = 'withdrawal';
	return $vars;
}
add_filter( 'query_vars', 'ayudawp_euw_add_query_var', 0 );

/**
 * Add the menu item to WooCommerce My Account navigation.
 *
 * @param array $items Navigation items.
 * @return array
 */
function ayudawp_euw_add_account_menu_item( $items ) {

	if ( ! function_exists( 'wc_get_endpoint_url' ) ) {
		return $items;
	}

	// Insert the new item before the logout link.
	$logout = isset( $items['customer-logout'] ) ? array( 'customer-logout' => $items['customer-logout'] ) : array();

	if ( $logout ) {
		unset( $items['customer-logout'] );
	}

	$items['withdrawal'] = __( 'Right of withdrawal', 'eu-withdrawal-compliance' );

	return array_merge( $items, $logout );
}
add_filter( 'woocommerce_account_menu_items', 'ayudawp_euw_add_account_menu_item' );

/**
 * Render the form inside the My Account endpoint.
 *
 * The form HTML is already built with esc_attr / esc_html / esc_url inside
 * ayudawp_euw_render_form(). wp_kses_post() must NOT be applied here because
 * it strips <form>, <input>, <select>, <textarea> and <button> from the markup.
 */
function ayudawp_euw_account_endpoint_content() {

	$user  = wp_get_current_user();
	$email = $user && ! empty( $user->user_email ) ? $user->user_email : '';

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped internally.
	echo ayudawp_euw_render_form(
		array(
			'email' => $email,
		)
	);
}
add_action( 'woocommerce_account_withdrawal_endpoint', 'ayudawp_euw_account_endpoint_content' );

/**
 * Add a "Withdraw" action button to each order in My Account orders list.
 *
 * @param array  $actions Order actions.
 * @param object $order   WC_Order.
 * @return array
 */
function ayudawp_euw_add_order_action( $actions, $order ) {

	if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
		return $actions;
	}

	if ( ! ayudawp_euw_should_show_withdrawal( $order ) ) {
		return $actions;
	}

	$endpoint_url = wc_get_account_endpoint_url( 'withdrawal' );
	$order_ref    = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order->get_id();
	$endpoint_url = add_query_arg( 'order_id', $order_ref, $endpoint_url );

	$actions['ayudawp_euw'] = array(
		'url'  => $endpoint_url,
		'name' => __( 'Withdraw', 'eu-withdrawal-compliance' ),
	);

	return $actions;
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'ayudawp_euw_add_order_action', 10, 2 );

/**
 * Pre-fill the order ID when the user clicks "Withdraw" on My Orders.
 *
 * @param array $atts Shortcode atts.
 * @return array
 */
function ayudawp_euw_prefill_from_query( $atts ) {

	// Read-only pre-fill from a link clicked in the user's My Account page.
	// No data mutation here, hence no nonce; the actual form submission is
	// nonce-verified in functions-handler.php.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['order_id'] ) ) {
		$atts['order_id'] = sanitize_text_field( wp_unslash( $_GET['order_id'] ) );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	return $atts;
}
add_filter( 'shortcode_atts_ayudawp_withdrawal_form', 'ayudawp_euw_prefill_from_query' );

/**
 * Find the most recent withdrawal request linked to a WC order.
 *
 * Returns 0 if none exists.
 *
 * @param int $wc_order_id WC order ID.
 * @return int Withdrawal CPT ID or 0.
 */
function ayudawp_euw_get_request_for_order( $wc_order_id ) {

	$wc_order_id = absint( $wc_order_id );

	if ( ! $wc_order_id ) {
		return 0;
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'ayudawp_withdrawal',
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'   => '_ayudawp_euw_wc_order_id',
					'value' => $wc_order_id,
				),
			),
		)
	);

	if ( empty( $query->posts ) ) {
		return 0;
	}

	return (int) $query->posts[0];
}

/**
 * Add the "Withdrawal" column to the WC orders screen (legacy + HPOS).
 *
 * @param array $columns Existing columns.
 * @return array
 */
function ayudawp_euw_add_orders_column( $columns ) {

	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		// Insert after the order status column for both legacy and HPOS.
		if ( 'order_status' === $key ) {
			$new['ayudawp_euw_withdrawal'] = __( 'Withdrawal', 'eu-withdrawal-compliance' );
		}
	}

	// If neither table has an order_status column, append at the end.
	if ( ! isset( $new['ayudawp_euw_withdrawal'] ) ) {
		$new['ayudawp_euw_withdrawal'] = __( 'Withdrawal', 'eu-withdrawal-compliance' );
	}

	return $new;
}
add_filter( 'manage_edit-shop_order_columns', 'ayudawp_euw_add_orders_column' );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'ayudawp_euw_add_orders_column' );

/**
 * Render the "Withdrawal" column for legacy CPT-based orders.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function ayudawp_euw_render_orders_column_legacy( $column, $post_id ) {

	if ( 'ayudawp_euw_withdrawal' !== $column ) {
		return;
	}

	echo ayudawp_euw_orders_column_html( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped internally.
}
add_action( 'manage_shop_order_posts_custom_column', 'ayudawp_euw_render_orders_column_legacy', 10, 2 );

/**
 * Render the "Withdrawal" column for HPOS orders.
 *
 * @param string $column Column key.
 * @param object $order  WC_Order instance.
 */
function ayudawp_euw_render_orders_column_hpos( $column, $order ) {

	if ( 'ayudawp_euw_withdrawal' !== $column ) {
		return;
	}

	$order_id = is_object( $order ) && method_exists( $order, 'get_id' ) ? $order->get_id() : 0;

	echo ayudawp_euw_orders_column_html( $order_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped internally.
}
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'ayudawp_euw_render_orders_column_hpos', 10, 2 );

/**
 * Build the HTML shown inside the "Withdrawal" column for an order.
 *
 * @param int $wc_order_id WC order ID.
 * @return string Already-escaped HTML.
 */
function ayudawp_euw_orders_column_html( $wc_order_id ) {

	$cpt_id = ayudawp_euw_get_request_for_order( $wc_order_id );

	if ( ! $cpt_id ) {
		return '<span class="ayudawp-euw-status ayudawp-euw-status-empty" aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No withdrawal request', 'eu-withdrawal-compliance' ) . '</span>';
	}

	$status = get_post_meta( $cpt_id, '_ayudawp_euw_status', true );
	$status = $status ? $status : 'pending';

	$labels = array(
		'pending'   => __( 'Pending', 'eu-withdrawal-compliance' ),
		'accepted'  => __( 'Accepted', 'eu-withdrawal-compliance' ),
		'rejected'  => __( 'Rejected', 'eu-withdrawal-compliance' ),
		'completed' => __( 'Completed', 'eu-withdrawal-compliance' ),
	);

	$label    = isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['pending'];
	$class    = 'ayudawp-euw-status-' . sanitize_html_class( $status );
	$edit_url = get_edit_post_link( $cpt_id );

	return sprintf(
		'<a href="%1$s" class="ayudawp-euw-status %2$s">%3$s</a>',
		esc_url( $edit_url ),
		esc_attr( $class ),
		esc_html( $label )
	);
}