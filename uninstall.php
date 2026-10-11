<?php
/**
 * Removes plugin data on uninstall.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Deletes options and transients for the active site.
 *
 * @param \wpdb $db WordPress database abstraction instance.
 */
$devbridge_ai_provider_magnitude_uninstall_site = static function ( $db ): void {
	delete_option( 'devbridge_ai_provider_magnitude_host' );

	$cache_prefix   = $db->esc_like( '_transient_devbridge_magnitude_' ) . '%';
	$timeout_prefix = $db->esc_like( '_transient_timeout_devbridge_magnitude_' ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires direct deletion of transient patterns.
	$db->query(
		$db->prepare(
			"DELETE FROM {$db->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$cache_prefix,
			$timeout_prefix
		)
	);
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $devbridge_ai_provider_magnitude_site_id ) {
		switch_to_blog( (int) $devbridge_ai_provider_magnitude_site_id );
		$devbridge_ai_provider_magnitude_uninstall_site( $wpdb );
		restore_current_blog();
	}
} else {
	$devbridge_ai_provider_magnitude_uninstall_site( $wpdb );
}
