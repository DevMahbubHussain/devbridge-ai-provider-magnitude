<?php
/**
 * API URL builder trait.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Traits;

use DevBridgeAiProviderForMagnitude\Provider\MagnitudeProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds Magnitude API URLs under the OpenAI-compatible /v1 path.
 *
 * @since 0.1.0
 */
trait Builds_Api_Url {

	/**
	 * Builds the full URL for an OpenAI-compatible API path.
	 *
	 * A leading /v1 segment on the path is accepted and not repeated.
	 *
	 * @since 0.1.0
	 *
	 * @param string $path The API path, for example "chat/completions".
	 * @return string The full API URL.
	 */
	protected function build_api_url( string $path ): string {
		$relative = ltrim( (string) preg_replace( '#^/?v1(?:/|$)#', '', $path ), '/' );

		return MagnitudeProvider::url( '/v1/' . $relative );
	}
}
