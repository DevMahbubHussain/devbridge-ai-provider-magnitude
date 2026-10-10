<?php
/**
 * WordPress AI plugin credentials integration.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Integration;

use DevBridgeAiProviderForMagnitude\Contracts\Hook_Registrar;
use DevBridgeAiProviderForMagnitude\Provider\MagnitudeProvider;
use WordPress\AiClient\AiClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the WordPress AI feature plugin that a provider without a key is available.
 *
 * The feature plugin shows its features as available only when it finds
 * credentials. Magnitude runs without a key, so this reports it as connected
 * while it is registered. Nothing happens when that plugin is not installed.
 *
 * @since 0.1.0
 */
final class Ai_Plugin_Credentials implements Hook_Registrar {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	public function register(): void {
		add_filter( 'wpai_has_ai_credentials', array( $this, 'filter_has_credentials' ) );
	}

	/**
	 * Reports credentials as present while the Magnitude provider is registered.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $has_credentials Whether credentials were already found.
	 * @return bool Whether credentials are present.
	 */
	public function filter_has_credentials( $has_credentials ): bool {
		if ( (bool) $has_credentials ) {
			return true;
		}

		return class_exists( AiClient::class ) && AiClient::defaultRegistry()->hasProvider( MagnitudeProvider::ID );
	}
}
