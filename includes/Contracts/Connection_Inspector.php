<?php
/**
 * Connection inspector contract.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Contracts;

use DevBridgeAiProviderForMagnitude\Connection\Connection_Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a way to find out whether Magnitude is reachable.
 *
 * @since 0.1.0
 */
interface Connection_Inspector {

	/**
	 * Asks Magnitude for its models and reports what happened.
	 *
	 * @since 0.1.0
	 *
	 * @return Connection_Status The connection status.
	 */
	public function inspect(): Connection_Status;

	/**
	 * Drops cached model data so the next inspection asks Magnitude again.
	 *
	 * @since 0.1.0
	 */
	public function refresh(): void;
}
