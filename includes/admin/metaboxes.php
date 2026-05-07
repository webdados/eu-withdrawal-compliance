<?php
/**
 * Admin: detail and status metaboxes plus status transition lifecycle.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		'name'         => array( 'label' => __( 'Customer name', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_name' ),
		'email'        => array( 'label' => __( 'Email', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_email' ),
		'order'        => array( 'label' => __( 'Order number', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_order' ),
		'order_date'   => array( 'label' => __( 'Order date', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_order_date' ),
		'scope'        => array( 'label' => __( 'Scope', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_scope' ),
		'submitted_at' => array( 'label' => __( 'Submitted at (UTC)', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_submitted_at' ),
		'receipt_hash' => array( 'label' => __( 'Receipt hash (SHA-256)', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_receipt_hash' ),
		'ip'           => array( 'label' => __( 'IP address', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_ip' ),
		'user_agent'   => array( 'label' => __( 'User agent', 'eu-withdrawal-compliance' ), 'meta' => '_ayudawp_euw_user_agent' ),
	);

	echo '<table class="ayudawp-euw-meta-table"><tbody>';

	foreach ( $fields as $key => $field ) {
		$value = get_post_meta( $post->ID, $field['meta'], true );

		if ( 'scope' === $key ) {
			$value = ( 'partial' === $value )
				? __( 'Partial', 'eu-withdrawal-compliance' )
				: __( 'Full', 'eu-withdrawal-compliance' );
		}

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html( $field['label'] ),
			esc_html( $value )
		);
	}

	echo '</tbody></table>';

	$excluded_items = get_post_meta( $post->ID, '_ayudawp_euw_excluded_items', true );

	if ( is_array( $excluded_items ) && ! empty( $excluded_items ) ) {
		echo '<h4>' . esc_html__( 'Article 16 exclusions detected in this order', 'eu-withdrawal-compliance' ) . '</h4>';
		echo '<ul class="ayudawp-euw-excluded-items">';
		foreach ( $excluded_items as $item ) {
			$name     = isset( $item['name'] ) ? (string) $item['name'] : '';
			$quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
			printf(
				'<li>%1$s × %2$d</li>',
				esc_html( $name ),
				$quantity
			);
		}
		echo '</ul>';
		echo '<p class="description">' . esc_html__( 'These items were flagged as excluded from the right of withdrawal at the time the request was submitted. Review manually before accepting or rejecting; a partial withdrawal over the rest of the order may still be valid.', 'eu-withdrawal-compliance' ) . '</p>';
	}

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
