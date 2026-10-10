<?php
/**
 * Host provider contract.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a source for the Magnitude server address.
 *
 * @since 0.1.0
 */
interface Host_Provider {

	/**
	 * Gets the Magnitude server base URL without a trailing slash.
	 *
	 * @since 0.1.0
	 *
	 * @return string The validated server base URL.
	 */
	public function get_host(): string;

	/**
	 * Checks whether the address is fixed in code and cannot be changed on the settings screen.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True when the address comes from a constant.
	 */
	public function is_locked(): bool;
}
