<?php
/**
 * Unit test bootstrap.
 *
 * Loads the AI Client that ships with WordPress core (or composer require-dev) and small stand-ins for the
 * few WordPress functions the tested classes call. Set DEVBRIDGE_AI_MAGNITUDE_ABSPATH to
 * the WordPress root when this plugin is not inside a standard install.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

$devbridge_ai_magnitude_abspath = getenv( 'DEVBRIDGE_AI_MAGNITUDE_ABSPATH' );

if ( false === $devbridge_ai_magnitude_abspath || '' === $devbridge_ai_magnitude_abspath ) {
	$devbridge_ai_magnitude_abspath = getenv( 'AI_MAGNITUDE_ABSPATH' );
}

if ( false === $devbridge_ai_magnitude_abspath || '' === $devbridge_ai_magnitude_abspath ) {
	$devbridge_ai_magnitude_abspath = dirname( __DIR__, 4 ) . '/';
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', rtrim( (string) $devbridge_ai_magnitude_abspath, '/' ) . '/' );
}

if ( file_exists( ABSPATH . 'wp-includes/php-ai-client/autoload.php' ) ) {
	require_once ABSPATH . 'wp-includes/php-ai-client/autoload.php';
} elseif ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * Stand-in for the WordPress function of the same name.
	 *
	 * @param string $value Value to strip.
	 * @return string The value without trailing slashes.
	 */
	function untrailingslashit( $value ) {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Stand-in that keeps only http and https URLs.
	 *
	 * @param string        $url       URL to check.
	 * @param array<string> $protocols Allowed protocols.
	 * @return string The URL, or an empty string when the protocol is not allowed.
	 */
	function esc_url_raw( $url, $protocols = null ) {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		return is_string( $scheme ) && in_array( $scheme, (array) $protocols, true ) ? $url : '';
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Stand-in for the WordPress function of the same name.
	 *
	 * @param string $url       URL to parse.
	 * @param int    $component Component to return.
	 * @return mixed The parsed URL or component.
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Test stand-in for wp_parse_url().
	}
}

$GLOBALS['devbridge_ai_magnitude_filters'] = array();
$GLOBALS['devbridge_ai_magnitude_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stand-in that returns a value set in $GLOBALS['devbridge_ai_magnitude_options'], if any.
	 *
	 * @param string $option        Option name.
	 * @param mixed  $default_value Value when the option is not set.
	 * @return mixed The option value.
	 */
	function get_option( $option, $default_value = false ) {
		return array_key_exists( $option, $GLOBALS['devbridge_ai_magnitude_options'] ) ? $GLOBALS['devbridge_ai_magnitude_options'][ $option ] : $default_value;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Stand-in that returns a value set in $GLOBALS['devbridge_ai_magnitude_filters'], if any.
	 *
	 * @param string $hook_name Filter name.
	 * @param mixed  $value     Value to filter.
	 * @return mixed The filtered value.
	 */
	function apply_filters( $hook_name, $value ) {
		return array_key_exists( $hook_name, $GLOBALS['devbridge_ai_magnitude_filters'] ) ? $GLOBALS['devbridge_ai_magnitude_filters'][ $hook_name ] : $value;
	}
}

$GLOBALS['devbridge_ai_magnitude_settings_errors'] = array();

if ( ! function_exists( '__' ) ) {
	/**
	 * Stand-in that returns the text unchanged.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string The text.
	 */
	function __( $text, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The stand-in must match the WordPress signature.
		return $text;
	}
}

if ( ! function_exists( 'add_settings_error' ) ) {
	/**
	 * Stand-in that records settings errors.
	 *
	 * @param string $setting Setting name.
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param string $type    Error type.
	 */
	function add_settings_error( $setting, $code, $message, $type = 'error' ) {
		$GLOBALS['devbridge_ai_magnitude_settings_errors'][] = array(
			'setting' => $setting,
			'code'    => $code,
			'message' => $message,
			'type'    => $type,
		);
	}
}

if ( ! function_exists( 'get_settings_errors' ) ) {
	/**
	 * Stand-in that returns recorded settings errors for a setting.
	 *
	 * @param string $setting Setting name.
	 * @return array<int, array<string, string>> The errors.
	 */
	function get_settings_errors( $setting = '' ) {
		return array_values(
			array_filter(
				$GLOBALS['devbridge_ai_magnitude_settings_errors'],
				static function ( $error ) use ( $setting ) {
					return '' === $setting || $error['setting'] === $setting;
				}
			)
		);
	}
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'DevBridgeAiProviderForMagnitude\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$file = dirname( __DIR__ ) . '/includes/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
