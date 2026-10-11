<?php
/**
 * Plugin composition root.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude;

use DevBridgeAiProviderForMagnitude\Admin\Action_Links;
use DevBridgeAiProviderForMagnitude\Connection\Registry_Connection_Inspector;
use DevBridgeAiProviderForMagnitude\Contracts\Hook_Registrar;
use DevBridgeAiProviderForMagnitude\Host\Host_Resolver;
use DevBridgeAiProviderForMagnitude\Http\Local_Host_Allowance;
use DevBridgeAiProviderForMagnitude\Integration\Ai_Plugin_Credentials;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Cache;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Rate_Limiter;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Request_Validator;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Rest_Controller;
use DevBridgeAiProviderForMagnitude\Playground\Registry_Playground_Invoker;
use DevBridgeAiProviderForMagnitude\Provider\Provider_Registrar;
use DevBridgeAiProviderForMagnitude\Settings\Host_Validator;
use DevBridgeAiProviderForMagnitude\Settings\Settings_Page;
use DevBridgeAiProviderForMagnitude\Settings\Settings_Registrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the plugin components and lets each one register its hooks.
 *
 * @since 0.1.0
 */
final class Plugin {

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
	 * Registers the hooks of every component.
	 *
	 * @since 0.1.0
	 */
	public function init(): void {
		foreach ( $this->get_registrars() as $registrar ) {
			$registrar->register();
		}
	}

	/**
	 * Creates the components that attach to WordPress hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return list<Hook_Registrar> The components.
	 */
	private function get_registrars(): array {
		$validator = new Host_Validator();
		$host      = new Host_Resolver( $validator );
		$inspector = new Registry_Connection_Inspector();

		return array(
			new Provider_Registrar(),
			new Local_Host_Allowance( $host ),
			new Ai_Plugin_Credentials(),
			new Settings_Registrar( new Settings_Page( $host, $inspector ), $validator, $inspector, $this->plugin_file ),
			new Action_Links( $this->plugin_file ),
			new Playground_Rest_Controller(
				$inspector,
				new Registry_Playground_Invoker(),
				new Playground_Request_Validator(),
				new Playground_Rate_Limiter(),
				new Playground_Cache()
			),
		);
	}
}
