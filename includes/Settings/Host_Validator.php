<?php
/**
 * Host validator.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks and normalizes a Magnitude server address.
 *
 * Only plain http and https addresses with an explicit scheme and a host are
 * accepted. Credentials, query strings and fragments are rejected, because the
 * plugin appends API paths to the address. A trailing /v1 is dropped, because Magnitude shows its address
 * with it while the plugin adds that segment itself.
 *
 * @since 0.1.0
 */
final class Host_Validator {

	/**
	 * Validates a candidate address.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $host The candidate address.
	 * @return string The normalized address, or an empty string when invalid.
	 */
	public function validate( $host ): string {
		if ( ! is_string( $host ) ) {
			return '';
		}

		$host = trim( $host );

		// Require an explicit scheme and no spaces, quotes or angle brackets, so input is never silently rewritten.
		if ( 1 !== preg_match( '#^https?://[^\s<>"\']+$#i', $host ) ) {
			return '';
		}

		$url = esc_url_raw( $host, array( 'http', 'https' ) );

		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return '';
		}

		return (string) preg_replace( '#/v1$#', '', untrailingslashit( $url ) );
	}
}
