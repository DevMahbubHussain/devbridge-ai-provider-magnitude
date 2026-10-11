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

if ( ! defined( 'DEVBRIDGE_AI_PROVIDER_MAGNITUDE_VERSION' ) ) {
	define( 'DEVBRIDGE_AI_PROVIDER_MAGNITUDE_VERSION', '0.1.0' );
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

		$allowed = is_array( $protocols ) ? $protocols : array( 'http', 'https' );

		return is_string( $scheme ) && in_array( $scheme, $allowed, true ) ? $url : '';
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Stand-in for esc_html__().
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Domain.
	 * @return string Escaped translated text.
	 */
	function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test stand-in.
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
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

$GLOBALS['devbridge_ai_magnitude_actions']            = array();
$GLOBALS['devbridge_ai_magnitude_filters_registered'] = array();
$GLOBALS['devbridge_ai_magnitude_filters']            = array();
$GLOBALS['devbridge_ai_magnitude_options']            = array();

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Stand-in for add_action().
	 *
	 * @param string   $hook_name     Hook name.
	 * @param callable $callback      Callback function.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted arguments count.
	 * @return bool True.
	 */
	function add_action( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['devbridge_ai_magnitude_actions'][ $hook_name ][] = array(
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Stand-in for add_filter().
	 *
	 * @param string   $hook_name     Hook name.
	 * @param callable $callback      Callback function.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted arguments count.
	 * @return bool True.
	 */
	function add_filter( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['devbridge_ai_magnitude_filters_registered'][ $hook_name ][] = array(
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

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

$GLOBALS['devbridge_ai_magnitude_transients'] = array();
$GLOBALS['devbridge_ai_magnitude_user_id']    = 1;

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Stand-in that returns a transient value, if any.
	 *
	 * @param string $transient Transient name.
	 * @return mixed The transient value, or false.
	 */
	function get_transient( $transient ) {
		return array_key_exists( $transient, $GLOBALS['devbridge_ai_magnitude_transients'] ) ? $GLOBALS['devbridge_ai_magnitude_transients'][ $transient ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Stand-in that stores a transient value.
	 *
	 * @param string $transient  Transient name.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Time until expiration.
	 * @return bool True.
	 */
	function set_transient( $transient, $value, $expiration = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test stand-in.
		$GLOBALS['devbridge_ai_magnitude_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Stand-in that removes a transient value.
	 *
	 * @param string $transient Transient name.
	 * @return bool True.
	 */
	function delete_transient( $transient ) {
		unset( $GLOBALS['devbridge_ai_magnitude_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Stand-in that returns the simulated user ID.
	 *
	 * @return int The user ID.
	 */
	function get_current_user_id(): int {
		return (int) $GLOBALS['devbridge_ai_magnitude_user_id'];
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

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Stand-in for WordPress WP_Error.
	 */
	class WP_Error {
		/**
		 * @var array<string, list<string>>
		 */
		private $errors = array();

		/**
		 * @var array<string, mixed>
		 */
		private $error_data = array();

		/**
		 * @param string|int $code Error code.
		 * @param string     $message Error message.
		 * @param mixed      $data Error data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( empty( $code ) ) {
				return;
			}
			$this->add( (string) $code, $message, $data );
		}

		/**
		 * @param string $code Error code.
		 * @param string $message Error message.
		 * @param mixed  $data Error data.
		 */
		public function add( string $code, string $message, $data = '' ): void {
			$this->errors[ $code ][]   = $message;
			$this->error_data[ $code ] = $data;
		}

		/**
		 * @return string The first error code, or empty string.
		 */
		public function get_error_code(): string {
			$codes = array_keys( $this->errors );
			return empty( $codes ) ? '' : (string) $codes[0];
		}

		/**
		 * @param string $code Optional error code.
		 * @return string Error message.
		 */
		public function get_error_message( string $code = '' ): string {
			if ( '' === $code ) {
				$code = $this->get_error_code();
			}
			return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
		}

		/**
		 * @param string $code Optional error code.
		 * @return mixed Error data.
		 */
		public function get_error_data( string $code = '' ) {
			if ( '' === $code ) {
				$code = $this->get_error_code();
			}
			return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Stand-in for is_wp_error().
	 *
	 * @param mixed $thing Variable to check.
	 * @return bool True if WP_Error.
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stand-in for sanitize_text_field().
	 *
	 * @param mixed $str String to sanitize.
	 * @return string Sanitized string.
	 */
	function sanitize_text_field( $str ): string {
		if ( ! is_string( $str ) ) {
			return '';
		}
		$filtered = wp_strip_all_tags( $str );
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', $filtered ) ?? '' );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Stand-in for sanitize_textarea_field().
	 *
	 * @param mixed $str String to sanitize.
	 * @return string Sanitized string.
	 */
	function sanitize_textarea_field( $str ): string {
		if ( ! is_string( $str ) ) {
			return '';
		}
		return trim( wp_strip_all_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Stand-in for wp_strip_all_tags().
	 *
	 * @param string $text Text to strip.
	 * @return string Stripped text.
	 */
	function wp_strip_all_tags( string $text ): string {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text ) ?? '';
		return strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test stand-in.
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Stand-in for wp_json_encode().
	 *
	 * @param mixed $data    Data to encode.
	 * @param int   $options Options.
	 * @param int   $depth   Depth.
	 * @return string|false JSON string or false.
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test stand-in.
	}
}

$GLOBALS['devbridge_ai_magnitude_can_manage_options'] = true;
$GLOBALS['devbridge_ai_magnitude_rest_routes']        = array();

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Stand-in for WP_REST_Request.
	 */
	class WP_REST_Request {
		/**
		 * @var array<string, mixed>
		 */
		private $params = array();

		/**
		 * @param string $key   Parameter name.
		 * @param mixed  $value Parameter value.
		 */
		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		/**
		 * @param string $key Parameter name.
		 * @return mixed Parameter value or null.
		 */
		public function get_param( string $key ) {
			return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
		}

		/**
		 * @return array<string, mixed> Parameters.
		 */
		public function get_params(): array {
			return $this->params;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Stand-in for WP_REST_Response.
	 */
	class WP_REST_Response {
		/**
		 * @var mixed
		 */
		private $data;

		/**
		 * @var int
		 */
		private $status;

		/**
		 * @param mixed $data   Response data.
		 * @param int   $status HTTP status code.
		 */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * @return mixed Response data.
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * @return int HTTP status code.
		 */
		public function get_status(): int {
			return $this->status;
		}
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Stand-in for current_user_can().
	 *
	 * @param string $capability Capability name.
	 * @return bool True if permitted.
	 */
	function current_user_can( $capability ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Test stand-in.
		return ! empty( $GLOBALS['devbridge_ai_magnitude_can_manage_options'] );
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	/**
	 * Stand-in for register_rest_route().
	 *
	 * @param string               $route_namespace Route namespace.
	 * @param string               $route           Route path.
	 * @param array<string, mixed> $args            Route arguments.
	 * @return bool True.
	 */
	function register_rest_route( string $route_namespace, string $route, array $args = array() ): bool {
		$GLOBALS['devbridge_ai_magnitude_rest_routes'][ $route_namespace . $route ] = $args;
		return true;
	}
}

$GLOBALS['devbridge_ai_magnitude_enqueued_styles']   = array();
$GLOBALS['devbridge_ai_magnitude_enqueued_scripts']  = array();
$GLOBALS['devbridge_ai_magnitude_localized_scripts'] = array();

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Stand-in for wp_enqueue_style().
	 *
	 * @param string           $handle Name of stylesheet.
	 * @param string           $src    URL to stylesheet.
	 * @param array<string>    $deps   Dependencies.
	 * @param string|bool|null $ver    Version string.
	 * @param string           $media  Media.
	 */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test stand-in.
		$GLOBALS['devbridge_ai_magnitude_enqueued_styles'][ $handle ] = array(
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Stand-in for wp_enqueue_script().
	 *
	 * @param string           $handle    Name of script.
	 * @param string           $src       URL to script.
	 * @param array<string>    $deps      Dependencies.
	 * @param string|bool|null $ver       Version string.
	 * @param bool             $in_footer In footer.
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test stand-in.
		$GLOBALS['devbridge_ai_magnitude_enqueued_scripts'][ $handle ] = array(
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $ver,
			'in_footer' => $in_footer,
		);
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * Stand-in for wp_localize_script().
	 *
	 * @param string               $handle      Script handle.
	 * @param string               $object_name JS object name.
	 * @param array<string, mixed> $l10n        Localized data.
	 * @return bool True.
	 */
	function wp_localize_script( string $handle, string $object_name, array $l10n ): bool {
		$GLOBALS['devbridge_ai_magnitude_localized_scripts'][ $handle ] = array(
			'object_name' => $object_name,
			'data'        => $l10n,
		);
		return true;
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	/**
	 * Stand-in for plugins_url().
	 *
	 * @param string $path   Relative path.
	 * @param string $plugin Plugin file.
	 * @return string Full URL.
	 */
	function plugins_url( string $path = '', string $plugin = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test stand-in.
		return 'http://example.test/wp-content/plugins/devbridge-ai-provider-magnitude/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * Stand-in for plugin_basename().
	 *
	 * @param string $file Plugin file path.
	 * @return string Basename.
	 */
	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	/**
	 * Stand-in for rest_url().
	 *
	 * @param string $path REST path.
	 * @return string REST URL.
	 */
	function rest_url( string $path = '' ): string {
		return 'http://example.test/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Stand-in for wp_create_nonce().
	 *
	 * @param string|int $action Action name.
	 * @return string Nonce string.
	 */
	function wp_create_nonce( $action = -1 ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Test stand-in.
		return 'test_nonce_mock';
	}
}

if ( ! function_exists( 'disabled' ) ) {
	/**
	 * Stand-in for disabled().
	 *
	 * @param mixed $disabled    Disabled condition.
	 * @param mixed $current     Current value.
	 * @param bool  $echo_output Whether to echo.
	 * @return string HTML disabled attribute.
	 */
	function disabled( $disabled, $current = true, bool $echo_output = true ): string {
		$output = ( (string) $disabled === (string) $current ) ? ' disabled="disabled"' : '';
		if ( $echo_output ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test stand-in.
		}
		return $output;
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	/**
	 * Stand-in for esc_attr_e().
	 *
	 * @param string $text   Text to escape and translate.
	 * @param string $domain Domain.
	 */
	function esc_attr_e( string $text, string $domain = 'default' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test stand-in.
		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Stand-in for esc_attr().
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Stand-in for esc_html().
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Stand-in for esc_html_e().
	 *
	 * @param string $text   Text to escape.
	 * @param string $domain Domain.
	 */
	function esc_html_e( string $text, string $domain = 'default' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test stand-in.
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Stand-in for esc_url().
	 *
	 * @param string $url URL to escape.
	 * @return string Escaped URL.
	 */
	function esc_url( string $url ): string {
		return $url;
	}
}

if ( ! function_exists( 'get_admin_page_title' ) ) {
	/**
	 * Stand-in for get_admin_page_title().
	 *
	 * @return string Admin page title.
	 */
	function get_admin_page_title(): string {
		return 'Magnitude';
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Stand-in for admin_url().
	 *
	 * @param string $path Path.
	 * @return string Admin URL.
	 */
	function admin_url( string $path = '' ): string {
		return 'http://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Stand-in for _n().
	 *
	 * @param string $single Single.
	 * @param string $plural Plural.
	 * @param int    $number Number.
	 * @param string $domain Domain.
	 * @return string String.
	 */
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test stand-in.
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	/**
	 * Stand-in for submit_button().
	 *
	 * @param string|null $text             Text.
	 * @param string      $type             Type.
	 * @param string      $name             Name.
	 * @param bool        $wrap             Wrap.
	 * @param mixed       $other_attributes Other attributes.
	 * @return string HTML button.
	 */
	function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test stand-in.
		$btn = '<input type="submit" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $text ) . '" />';
		echo $btn; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test stand-in.
		return $btn;
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	/**
	 * Stand-in for settings_fields().
	 *
	 * @param string $option_group Option group.
	 */
	function settings_fields( string $option_group ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Test stand-in.
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Stand-in for wp_nonce_field().
	 *
	 * @param string|int $action Action.
	 */
	function wp_nonce_field( $action = -1 ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Test stand-in.
	}
}

if ( ! function_exists( 'wp_admin_notice' ) ) {
	/**
	 * Stand-in for wp_admin_notice().
	 *
	 * @param string               $message Notice message.
	 * @param array<string, mixed> $args    Notice arguments.
	 */
	function wp_admin_notice( string $message, array $args = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test stand-in.
		echo '<div class="notice">' . esc_html( $message ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test stand-in.
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
