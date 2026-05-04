<?php
/**
 * Uninstall handler.
 *
 * Removes plugin options. Withdrawal request log entries (CPT) are preserved
 * by default for legal record-keeping. Set the AYUDAWP_EUW_DELETE_DATA constant
 * to true in wp-config.php to wipe everything, including stored requests.
 *
 * Migration safeguard: if another installation of this plugin lives in the
 * canonical "eu-withdrawal-compliance" folder, this script preserves all data
 * so the user can delete a stale folder (e.g. "eu-withdrawal-compliance-main"
 * downloaded from a GitHub source ZIP before the Releases workflow existed)
 * without losing settings or logs. The canonical install will pick everything
 * up automatically.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Detect a parallel install at the canonical slug. If this uninstall is
// running for the "wrong" folder (e.g. -main suffix), keep the data so the
// canonical instance can take over.
$ayudawp_euw_canonical_dir = defined( 'WP_PLUGIN_DIR' )
	? trailingslashit( WP_PLUGIN_DIR ) . 'eu-withdrawal-compliance'
	: '';

$ayudawp_euw_current_dir = untrailingslashit( __DIR__ );

if (
	$ayudawp_euw_canonical_dir
	&& $ayudawp_euw_canonical_dir !== $ayudawp_euw_current_dir
	&& is_dir( $ayudawp_euw_canonical_dir )
) {
	// A canonical install exists alongside this one. Preserve everything.
	return;
}

// Remove plugin options.
delete_option( 'ayudawp_euw_notify_email' );
delete_option( 'ayudawp_euw_page_id' );
delete_option( 'ayudawp_euw_version' );

// Optionally delete all withdrawal records.
if ( defined( 'AYUDAWP_EUW_DELETE_DATA' ) && AYUDAWP_EUW_DELETE_DATA ) {

	$posts = get_posts(
		array(
			'post_type'      => 'ayudawp_withdrawal',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}
