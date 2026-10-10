<?php
/**
 * Provider registrar.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Provider;

use DevBridgeAiProviderForMagnitude\Contracts\Hook_Registrar;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Magnitude provider with the AI Client registry.
 *
 * Once registered, the Connectors API creates the connector on its own.
 *
 * @since 0.1.0
 */
final class Provider_Registrar implements Hook_Registrar {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_provider' ), 5 );
		add_action( 'init', array( $this, 'register_fallback_authentication' ), 15 );
	}

	/**
	 * Registers the provider with the AI Client.
	 *
	 * @since 0.1.0
	 */
	public function register_provider(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( MagnitudeProvider::class ) ) {
			return;
		}

		$registry->registerProvider( MagnitudeProvider::class );
	}

	/**
	 * Sets an empty API key when none is configured.
	 *
	 * Magnitude needs no key on the same computer. Connectors credentials set
	 * earlier, from a constant, an environment variable or the database, are kept.
	 *
	 * @since 0.1.0
	 */
	public function register_fallback_authentication(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( MagnitudeProvider::ID ) ) {
			return;
		}

		if ( null !== $registry->getProviderRequestAuthentication( MagnitudeProvider::ID ) ) {
			return;
		}

		$registry->setProviderRequestAuthentication( MagnitudeProvider::ID, new ApiKeyRequestAuthentication( '' ) );
	}
}
