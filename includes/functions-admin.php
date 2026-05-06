<?php
/**
 * Admin UI: custom columns, metaboxes, and CPT details.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add custom columns to the withdrawal listing.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function ayudawp_euw_admin_columns( $columns ) {

	$new = array(
		'cb'                       => $columns['cb'] ?? '',
		'title'                    => __( 'Reference', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_customer'     => __( 'Customer', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_email'        => __( 'Email', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_order'        => __( 'Order', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_scope'        => __( 'Scope', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_status'       => __( 'Status', 'eu-withdrawal-compliance' ),
		'date'                     => __( 'Date', 'eu-withdrawal-compliance' ),
	);

	return $new;
}
add_filter( 'manage_ayudawp_withdrawal_posts_columns', 'ayudawp_euw_admin_columns' );

/**
 * Render values for each custom column.
 *
 * @param string $column Column key.
 * @param int    $post_id Post ID.
 */
function ayudawp_euw_admin_column_content( $column, $post_id ) {

	switch ( $column ) {
		case 'ayudawp_euw_customer':
			echo esc_html( get_post_meta( $post_id, '_ayudawp_euw_name', true ) );
			break;

		case 'ayudawp_euw_email':
			$email = get_post_meta( $post_id, '_ayudawp_euw_email', true );
			if ( $email ) {
				printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
			}
			break;

		case 'ayudawp_euw_order':
			$order   = get_post_meta( $post_id, '_ayudawp_euw_order', true );
			$wc_id   = absint( get_post_meta( $post_id, '_ayudawp_euw_wc_order_id', true ) );

			if ( $wc_id && function_exists( 'wc_get_order' ) ) {
				$wc_order = wc_get_order( $wc_id );
				if ( $wc_order ) {
					$edit_url = method_exists( $wc_order, 'get_edit_order_url' )
						? $wc_order->get_edit_order_url()
						: admin_url( 'post.php?post=' . $wc_id . '&action=edit' );
					printf(
						'<a href="%1$s">%2$s</a>',
						esc_url( $edit_url ),
						esc_html( $order )
					);
					break;
				}
			}

			echo esc_html( $order );
			break;

		case 'ayudawp_euw_scope':
			$scope = get_post_meta( $post_id, '_ayudawp_euw_scope', true );
			echo esc_html(
				'partial' === $scope
					? __( 'Partial', 'eu-withdrawal-compliance' )
					: __( 'Full', 'eu-withdrawal-compliance' )
			);
			break;

		case 'ayudawp_euw_status':
			$status = get_post_meta( $post_id, '_ayudawp_euw_status', true );
			$labels = array(
				'pending'   => __( 'Pending', 'eu-withdrawal-compliance' ),
				'accepted'  => __( 'Accepted', 'eu-withdrawal-compliance' ),
				'rejected'  => __( 'Rejected', 'eu-withdrawal-compliance' ),
				'completed' => __( 'Completed', 'eu-withdrawal-compliance' ),
			);

			$label = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Pending', 'eu-withdrawal-compliance' );
			$class = 'ayudawp-euw-status-' . sanitize_html_class( $status ? $status : 'pending' );

			printf(
				'<span class="ayudawp-euw-status %1$s">%2$s</span>',
				esc_attr( $class ),
				esc_html( $label )
			);
			break;
	}
}
add_action( 'manage_ayudawp_withdrawal_posts_custom_column', 'ayudawp_euw_admin_column_content', 10, 2 );

/**
 * Add metabox with full request details.
 */
function ayudawp_euw_register_metabox() {

	add_meta_box(
		'ayudawp_euw_details',
		__( 'Withdrawal request details', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_metabox_content',
		'ayudawp_withdrawal',
		'normal',
		'high'
	);

	add_meta_box(
		'ayudawp_euw_status',
		__( 'Status', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_metabox_status',
		'ayudawp_withdrawal',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'ayudawp_euw_register_metabox' );

/**
 * Render the details metabox.
 *
 * @param WP_Post $post Current post.
 */
function ayudawp_euw_metabox_content( $post ) {

	$fields = array(
		'name'       => array( 'label' => __( 'Customer name', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_name' ),
		'email'      => array( 'label' => __( 'Email', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_email' ),
		'order'      => array( 'label' => __( 'Order number', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_order' ),
		'order_date' => array( 'label' => __( 'Order date', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_order_date' ),
		'scope'      => array( 'label' => __( 'Scope', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_scope' ),
		'ip'         => array( 'label' => __( 'IP address', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_ip' ),
		'user_agent' => array( 'label' => __( 'User agent', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_user_agent' ),
	);

	echo '<table class="ayudawp-euw-meta-table"><tbody>';

	foreach ( $fields as $field ) {
		$value = get_post_meta( $post->ID, $field['meta'], true );

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html( $field['label'] ),
			esc_html( $value )
		);
	}

	echo '</tbody></table>';

	echo '<h4>' . esc_html__( 'Additional information', 'eu-withdrawal-compliance' ) . '</h4>';
	echo '<div class="ayudawp-euw-details">';
	echo wp_kses_post( wpautop( $post->post_content ) );
	echo '</div>';
}

/**
 * Render the status metabox with a save button.
 *
 * @param WP_Post $post Current post.
 */
function ayudawp_euw_metabox_status( $post ) {

	wp_nonce_field( 'ayudawp_euw_save_status', 'ayudawp_euw_status_nonce' );

	$status   = get_post_meta( $post->ID, '_ayudawp_euw_status', true );
	$status   = $status ? $status : 'pending';

	$statuses = array(
		'pending'   => __( 'Pending', 'eu-withdrawal-compliance' ),
		'accepted'  => __( 'Accepted', 'eu-withdrawal-compliance' ),
		'rejected'  => __( 'Rejected', 'eu-withdrawal-compliance' ),
		'completed' => __( 'Completed', 'eu-withdrawal-compliance' ),
	);

	echo '<p>';
	echo '<label for="ayudawp_euw_status_field"><strong>' . esc_html__( 'Set status', 'eu-withdrawal-compliance' ) . '</strong></label><br>';
	echo '<select name="ayudawp_euw_status_field" id="ayudawp_euw_status_field" style="width:100%">';

	foreach ( $statuses as $key => $label ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $key ),
			selected( $status, $key, false ),
			esc_html( $label )
		);
	}

	echo '</select>';
	echo '</p>';
	echo '<p class="description">' . esc_html__( 'Track the internal handling state of this request.', 'eu-withdrawal-compliance' ) . '</p>';

	echo '<p>';
	echo '<label for="ayudawp_euw_status_comment"><strong>' . esc_html__( 'Comments for the customer', 'eu-withdrawal-compliance' ) . '</strong></label><br>';
	echo '<textarea name="ayudawp_euw_status_comment" id="ayudawp_euw_status_comment" rows="4" style="width:100%" placeholder="' . esc_attr__( 'Will be included in the email sent to the customer when the status changes.', 'eu-withdrawal-compliance' ) . '"></textarea>';
	echo '</p>';
	echo '<p class="description">' . esc_html__( 'Required when rejecting a request, optional when marking as completed. Not stored — sent in the email and saved to the order note.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Save status changes from the metabox.
 *
 * Detects status transitions and triggers side effects: linked WC order note
 * and a customer email. The free-form admin comment is forwarded to the
 * customer email and the order note, but is not persisted as post meta — we
 * deliberately keep it ephemeral to avoid stockpiling per-request comment
 * history outside the audit trail (order notes already serve that purpose).
 *
 * @param int $post_id Post ID.
 */
function ayudawp_euw_save_status( $post_id ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['ayudawp_euw_status_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['ayudawp_euw_status_nonce'] ) ),
			'ayudawp_euw_save_status'
		)
	) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( get_post_type( $post_id ) !== 'ayudawp_withdrawal' ) {
		return;
	}

	if ( ! isset( $_POST['ayudawp_euw_status_field'] ) ) {
		return;
	}

	$new_status = sanitize_key( wp_unslash( $_POST['ayudawp_euw_status_field'] ) );
	$allowed    = array( 'pending', 'accepted', 'rejected', 'completed' );

	if ( ! in_array( $new_status, $allowed, true ) ) {
		return;
	}

	$comment = isset( $_POST['ayudawp_euw_status_comment'] )
		? sanitize_textarea_field( wp_unslash( $_POST['ayudawp_euw_status_comment'] ) )
		: '';

	$old_status = get_post_meta( $post_id, '_ayudawp_euw_status', true );
	$old_status = $old_status ? $old_status : 'pending';

	// Reject without a comment is not allowed: the customer must be told why.
	if ( 'rejected' === $new_status && '' === $comment && 'rejected' !== $old_status ) {
		set_transient(
			'ayudawp_euw_status_error_' . get_current_user_id(),
			__( 'A comment is required when rejecting a withdrawal request. Status not changed.', 'eu-withdrawal-compliance' ),
			60
		);
		return;
	}

	update_post_meta( $post_id, '_ayudawp_euw_status', $new_status );

	if ( $new_status !== $old_status ) {
		ayudawp_euw_handle_status_transition( $post_id, $new_status, $comment );
	}
}
add_action( 'save_post_ayudawp_withdrawal', 'ayudawp_euw_save_status' );

/**
 * Show the metabox validation error after a redirect-after-save.
 */
function ayudawp_euw_status_admin_notice() {

	$key     = 'ayudawp_euw_status_error_' . get_current_user_id();
	$message = get_transient( $key );

	if ( ! $message ) {
		return;
	}

	delete_transient( $key );

	printf(
		'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
		esc_html( $message )
	);
}
add_action( 'admin_notices', 'ayudawp_euw_status_admin_notice' );

/**
 * React to a status change: write a note on the linked WC order and email the customer.
 *
 * @param int    $post_id    Withdrawal CPT ID.
 * @param string $new_status Target status.
 * @param string $comment    Optional admin comment to forward to the customer.
 */
function ayudawp_euw_handle_status_transition( $post_id, $new_status, $comment = '' ) {

	$wc_order_id = absint( get_post_meta( $post_id, '_ayudawp_euw_wc_order_id', true ) );

	if ( $wc_order_id ) {
		ayudawp_euw_add_status_order_note( $wc_order_id, $post_id, $new_status, $comment );
	}

	if ( in_array( $new_status, array( 'accepted', 'rejected', 'completed' ), true ) ) {

		$email = get_post_meta( $post_id, '_ayudawp_euw_email', true );
		$name  = get_post_meta( $post_id, '_ayudawp_euw_name', true );
		$order = get_post_meta( $post_id, '_ayudawp_euw_order', true );

		if ( $email && is_email( $email ) ) {
			ayudawp_euw_send_status_email( $email, $name, $order, $new_status, $comment );
		}
	}

	/**
	 * Fires after a withdrawal request status changes.
	 *
	 * @param int    $post_id    Withdrawal CPT ID.
	 * @param string $new_status New status.
	 * @param string $comment    Admin comment.
	 */
	do_action( 'ayudawp_euw_after_status_change', $post_id, $new_status, $comment );
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

		$count++;
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