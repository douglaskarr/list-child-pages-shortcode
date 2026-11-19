<?php
/**
 * Uninstall: this plugin stores no options, transients, post or user meta,
 * or scheduled events. Defensively remove any leftover dknm_lcp_ or legacy
 * dklcp_ options and transients if they were ever created.
 *
 * @package ListChildPagesShortcode
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove leftover options and transients on the current site.
 */
function dknm_lcp_uninstall_site() {
	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup on uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'dknm_lcp_' ) . '%',
			$wpdb->esc_like( 'dklcp_' ) . '%',
			$wpdb->esc_like( '_transient_dknm_lcp_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_dknm_lcp_' ) . '%',
			$wpdb->esc_like( '_transient_dklcp_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_dklcp_' ) . '%'
		)
	);
	// phpcs:enable
}

if ( is_multisite() ) {
	$dknm_lcp_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $dknm_lcp_site_ids as $dknm_lcp_site_id ) {
		switch_to_blog( $dknm_lcp_site_id );
		dknm_lcp_uninstall_site();
		restore_current_blog();
	}
} else {
	dknm_lcp_uninstall_site();
}
