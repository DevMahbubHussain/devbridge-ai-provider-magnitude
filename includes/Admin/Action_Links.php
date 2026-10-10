<?php
/**
 * Plugin action links.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Admin;

use DevBridgeAiProviderForMagnitude\Contracts\Hook_Registrar;
use DevBridgeAiProviderForMagnitude\Settings\Settings_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Settings and Connectors links on the Plugins screen.
 *
 * @since 0.1.0
 */
final class Action_Links implements Hook_Registrar {

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
	 * @param string $plugin_file Main plugin file path.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	public function register(): void {
		add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin_file ), array( $this, 'add_links' ) );
	}

	/**
	 * Adds the links before the existing action links.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string> Action links including Settings and Connectors.
	 */
	public function add_links( $links ): array {
		$links = is_array( $links ) ? $links : array();

		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$own = array(
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( Settings_Page::get_url() ),
				esc_html__( 'Settings', 'devbridge-ai-provider-magnitude' )
			),
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'options-connectors.php' ) ),
				esc_html__( 'Connectors', 'devbridge-ai-provider-magnitude' )
			),
		);

		return array_merge( $own, $links );
	}
}
