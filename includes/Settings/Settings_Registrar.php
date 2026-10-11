<?php
/**
 * Settings registrar.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Settings;

use DevBridgeAiProviderForMagnitude\Contracts\Connection_Inspector;
use DevBridgeAiProviderForMagnitude\Contracts\Hook_Registrar;
use DevBridgeAiProviderForMagnitude\Host\Host_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the settings screen, the saved address and the connection check.
 *
 * @since 0.1.0
 */
final class Settings_Registrar implements Hook_Registrar {


	/**
	 * Renders the settings screen.
	 *
	 * @since 0.1.0
	 *
	 * @var Settings_Page
	 */
	private $page;

	/**
	 * Checks and normalizes addresses.
	 *
	 * @since 0.1.0
	 *
	 * @var Host_Validator
	 */
	private $validator;

	/**
	 * Source of the connection status.
	 *
	 * @since 0.1.0
	 *
	 * @var Connection_Inspector
	 */
	private $inspector;

	/**
	 * Main plugin file path.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Settings_Page        $page        Renders the settings screen.
	 * @param Host_Validator       $validator   Checks and normalizes addresses.
	 * @param Connection_Inspector $inspector   Source of the connection status.
	 * @param string               $plugin_file Main plugin file path.
	 */
	public function __construct( Settings_Page $page, Host_Validator $validator, Connection_Inspector $inspector, string $plugin_file ) {
		$this->page        = $page;
		$this->validator   = $validator;
		$this->inspector   = $inspector;
		$this->plugin_file = $plugin_file;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . Settings_Page::CHECK_ACTION, array( $this, 'handle_check' ) );
	}

	/**
	 * Adds the screen under Settings.
	 *
	 * @since 0.1.0
	 */
	public function add_menu(): void {
		add_options_page(
			__( 'Magnitude', 'devbridge-ai-provider-magnitude' ),
			__( 'Magnitude', 'devbridge-ai-provider-magnitude' ),
			'manage_options',
			Settings_Page::SLUG,
			array( $this->page, 'render' )
		);
	}

	/**
	 * Registers the saved address with its sanitizer.
	 *
	 * @since 0.1.0
	 */
	public function register_setting(): void {
		register_setting(
			Settings_Page::SLUG,
			Host_Resolver::OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_host' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Sanitizes the address from the form.
	 *
	 * An empty value clears the setting. An invalid value keeps the saved one and
	 * shows an error.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value The submitted address.
	 * @return string The address to save.
	 */
	public function sanitize_host( $value ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		$valid = $this->validator->validate( $value );

		if ( '' !== $valid ) {
			return $valid;
		}

		add_settings_error(
			Host_Resolver::OPTION_NAME,
			'devbridge_ai_provider_magnitude_invalid_host',
			__( 'Enter a valid http or https address without a username or password.', 'devbridge-ai-provider-magnitude' )
		);

		$saved = get_option( Host_Resolver::OPTION_NAME, '' );

		return is_string( $saved ) ? $saved : '';
	}

	/**
	 * Loads the screen stylesheet and playground script on the settings screen only.
	 *
	 * @since 0.2.0
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_assets( $hook_suffix ): void {
		if ( 'settings_page_' . Settings_Page::SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'devbridge-ai-provider-magnitude-settings',
			plugins_url( 'assets/css/settings.css', $this->plugin_file ),
			array(),
			DEVBRIDGE_AI_PROVIDER_MAGNITUDE_VERSION
		);

		wp_enqueue_script(
			'devbridge-ai-provider-magnitude-playground',
			plugins_url( 'assets/js/playground.js', $this->plugin_file ),
			array(),
			DEVBRIDGE_AI_PROVIDER_MAGNITUDE_VERSION,
			true
		);

		wp_localize_script(
			'devbridge-ai-provider-magnitude-playground',
			'devbridgeMagnitudePlayground',
			array(
				'restUrl' => esc_url_raw( rest_url( 'devbridge-ai-provider-magnitude/v1/playground/generate' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'strings' => array(
					'send'         => __( 'Send prompt', 'devbridge-ai-provider-magnitude' ),
					'sending'      => __( 'Generating...', 'devbridge-ai-provider-magnitude' ),
					'stop'         => __( 'Stop', 'devbridge-ai-provider-magnitude' ),
					'errorGeneric' => __( 'An error occurred during generation.', 'devbridge-ai-provider-magnitude' ),
					'clearConfirm' => __( 'Are you sure you want to clear conversation history?', 'devbridge-ai-provider-magnitude' ),
					'copySuccess'  => __( 'Copied to clipboard!', 'devbridge-ai-provider-magnitude' ),
					'cachedBadge'  => __( 'Cached', 'devbridge-ai-provider-magnitude' ),
					'networkError' => __( 'Network or connection error.', 'devbridge-ai-provider-magnitude' ),
				),
			)
		);
	}

	/**
	 * Loads the screen stylesheet on the settings screen only.
	 *
	 * Kept for backwards compatibility.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_styles( $hook_suffix ): void {
		$this->enqueue_assets( $hook_suffix );
	}

	/**
	 * Refreshes the connection check and returns to the settings screen.
	 *
	 * @since 0.1.0
	 */
	public function handle_check(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'devbridge-ai-provider-magnitude' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( Settings_Page::CHECK_ACTION );

		$this->inspector->refresh();

		wp_safe_redirect( add_query_arg( Settings_Page::CHECKED_ARG, '1', Settings_Page::get_url() ) );
		exit;
	}
}
