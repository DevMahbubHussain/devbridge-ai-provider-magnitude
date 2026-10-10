<?php
/**
 * Hook registrar contract.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a component that attaches its callbacks to WordPress hooks.
 *
 * @since 0.1.0
 */
interface Hook_Registrar {

	/**
	 * Adds the component's actions and filters.
	 *
	 * @since 0.1.0
	 */
	public function register(): void;
}
