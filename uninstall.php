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

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $devbridge_ai_provider_magnitude_site_id ) {
		switch_to_blog( (int) $devbridge_ai_provider_magnitude_site_id );
		delete_option( 'devbridge_ai_provider_magnitude_host' );
		restore_current_blog();
	}
} else {
	delete_option( 'devbridge_ai_provider_magnitude_host' );
}
