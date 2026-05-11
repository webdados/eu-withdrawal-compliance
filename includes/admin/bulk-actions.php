<?php
/**
 * Admin: bulk actions for the withdrawal CPT listing.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register bulk actions for the withdrawal listing.
 *
 * Rejection is intentionally NOT a bulk action: rejecting a withdrawal
 * request requires a written reason (legally and as a matter of customer
 * service), and bulk mode cannot collect that reason. Accept and complete
 * are safe in bulk because they do not require justification toward the
 * customer.
 *
 * The native "Edit" bulk action is also removed because the inline editor
 * shown by WordPress on a CPT nested under the WooCommerce menu surfaces
 * order-related fields that do not apply to withdrawal requests.
 *
 * @param array $actions Existing bulk actions.
 * @return array
 */
function ayudawp_euw_bulk_actions( $actions ) {

	unset( $actions['edit'] );

	$actions['ayudawp_euw_mark_accepted']  = __( 'Mark as accepted', 'eu-withdrawal-compliance' );
	$actions['ayudawp_euw_mark_completed'] = __( 'Mark as completed', 'eu-withdrawal-compliance' );

	return $actions;
}
add_filter( 'bulk_actions-edit-ayudawp_withdrawal', 'ayudawp_euw_bulk_actions' );

/**
 * Handle bulk status changes.
 *
 * No comment can be supplied in bulk mode, so the customer email goes out
 * with the default body for each status. Use the per-request metabox if you
 * need to include a custom message.
 *
 * @param string $redirect_to Redirect URL.
 * @param string $action      Action key.
 * @param array  $post_ids    Selected post IDs.
 * @return string Redirect URL with feedback args.
 */
function ayudawp_euw_handle_bulk_actions( $redirect_to, $action, $post_ids ) {

	$map = array(
		'ayudawp_euw_mark_accepted'  => 'accepted',
		'ayudawp_euw_mark_completed' => 'completed',
	);

	if ( ! isset( $map[ $action ] ) ) {
		return $redirect_to;
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		return $redirect_to;
	}

	$new_status = $map[ $action ];
	$count      = 0;

	foreach ( $post_ids as $post_id ) {

		$post_id = absint( $post_id );

		if ( ! $post_id || get_post_type( $post_id ) !== 'ayudawp_withdrawal' ) {
			continue;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			continue;
		}

		$old_status = get_post_meta( $post_id, '_ayudawp_euw_status', true );
		$old_status = $old_status ? $old_status : 'pending';

		if ( $old_status === $new_status ) {
			continue;
		}

		update_post_meta( $post_id, '_ayudawp_euw_status', $new_status );
		ayudawp_euw_handle_status_transition( $post_id, $new_status, '' );

		++$count;
	}

	$redirect_to = add_query_arg( 'ayudawp_euw_bulk_done', $count, $redirect_to );
	$redirect_to = add_query_arg( 'ayudawp_euw_bulk_status', $new_status, $redirect_to );

	return $redirect_to;
}
add_filter( 'handle_bulk_actions-edit-ayudawp_withdrawal', 'ayudawp_euw_handle_bulk_actions', 10, 3 );

/**
 * Show feedback after a bulk status change.
 */
function ayudawp_euw_bulk_action_notice() {

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag from a redirect.
	if ( ! isset( $_GET['ayudawp_euw_bulk_done'] ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$count  = absint( wp_unslash( $_GET['ayudawp_euw_bulk_done'] ) );
	$status = isset( $_GET['ayudawp_euw_bulk_status'] )
		? sanitize_key( wp_unslash( $_GET['ayudawp_euw_bulk_status'] ) )
		: '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( $count <= 0 ) {
		return;
	}

	$labels = array(
		'accepted'  => __( 'accepted', 'eu-withdrawal-compliance' ),
		'completed' => __( 'completed', 'eu-withdrawal-compliance' ),
	);

	$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: number of requests, 2: status label. */
				_n(
					'%1$d withdrawal request marked as %2$s.',
					'%1$d withdrawal requests marked as %2$s.',
					$count,
					'eu-withdrawal-compliance'
				),
				$count,
				$label
			)
		)
	);
}
add_action( 'admin_notices', 'ayudawp_euw_bulk_action_notice' );
