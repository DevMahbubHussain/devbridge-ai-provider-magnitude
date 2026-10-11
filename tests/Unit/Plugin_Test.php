<?php
/**
 * Tests for the plugin composition root.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Covers plugin composition root and registrar initialization.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Plugin
 */
final class Plugin_Test extends TestCase {

	/**
	 * Main plugin file path stand-in.
	 *
	 * @var string
	 */
	private $plugin_file = '/plugins/devbridge-ai-provider-magnitude/devbridge-ai-provider-magnitude.php';

	public function test_init_executes_without_errors(): void {
		$plugin = new Plugin( $this->plugin_file );

		// Should initialize all registrars without exceptions.
		$plugin->init();

		// Verify REST route was registered by Playground_Rest_Controller.
		$this->assertArrayHasKey(
			'devbridge-ai-provider-magnitude/v1/playground/generate',
			$GLOBALS['devbridge_ai_magnitude_rest_routes']
		);
	}
}
