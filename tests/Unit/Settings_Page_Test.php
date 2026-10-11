<?php
/**
 * Tests for the settings page renderer.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Connection\Connection_Status;
use DevBridgeAiProviderForMagnitude\Contracts\Connection_Inspector;
use DevBridgeAiProviderForMagnitude\Contracts\Host_Provider;
use DevBridgeAiProviderForMagnitude\Settings\Settings_Page;
use PHPUnit\Framework\TestCase;

/**
 * Covers rendering the settings screen and playground card.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Settings\Settings_Page
 */
final class Settings_Page_Test extends TestCase {

	/**
	 * Resets stand-in globals.
	 */
	protected function tearDown(): void {
		$GLOBALS['devbridge_ai_magnitude_can_manage_options'] = false;
	}

	/**
	 * Creates a host provider mock.
	 *
	 * @param string $host   Server host.
	 * @param bool   $locked Whether host is locked.
	 * @return Host_Provider Host provider.
	 */
	private function create_host_provider( string $host = 'http://127.0.0.1:10100/inference', bool $locked = false ): Host_Provider {
		return new class( $host, $locked ) implements Host_Provider {
			/**
			 * Server host.
			 *
			 * @var string
			 */
			private $host;

			/**
			 * Locked state.
			 *
			 * @var bool
			 */
			private $locked;

			public function __construct( string $host, bool $locked ) {
				$this->host   = $host;
				$this->locked = $locked;
			}

			public function get_host(): string {
				return $this->host;
			}

			public function is_locked(): bool {
				return $this->locked;
			}
		};
	}

	/**
	 * Creates an inspector mock.
	 *
	 * @param Connection_Status $status Connection status.
	 * @return Connection_Inspector Inspector.
	 */
	private function create_inspector( Connection_Status $status ): Connection_Inspector {
		return new class( $status ) implements Connection_Inspector {
			/**
			 * Connection status.
			 *
			 * @var Connection_Status
			 */
			private $status;

			public function __construct( Connection_Status $status ) {
				$this->status = $status;
			}

			public function inspect(): Connection_Status {
				return $this->status;
			}

			public function refresh(): void {}
		};
	}

	public function test_render_does_nothing_when_user_cannot_manage_options(): void {
		$GLOBALS['devbridge_ai_magnitude_can_manage_options'] = false;

		$page = new Settings_Page(
			$this->create_host_provider(),
			$this->create_inspector( new Connection_Status( true ) )
		);

		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_render_renders_playground_card_when_connected_with_models(): void {
		$GLOBALS['devbridge_ai_magnitude_can_manage_options'] = true;

		$models = array(
			array(
				'id'     => 'test-model:latest',
				'name'   => 'Test Model',
				'badges' => array( 'Tools' ),
			),
		);

		$page = new Settings_Page(
			$this->create_host_provider(),
			$this->create_inspector( new Connection_Status( true, $models ) )
		);

		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Test playground', $output );
		$this->assertStringContainsString( 'id="devbridge-playground-model"', $output );
		$this->assertStringContainsString( 'value="test-model:latest"', $output );
		$this->assertStringContainsString( 'id="devbridge-playground-prompt"', $output );
		$this->assertStringContainsString( 'id="devbridge-playground-send"', $output );
		$this->assertStringContainsString( 'id="devbridge-playground-clear"', $output );
		$this->assertStringContainsString( 'id="devbridge-playground-bypass-cache"', $output );
		$this->assertStringContainsString( 'id="param-temp"', $output );
		$this->assertStringContainsString( 'id="param-topp"', $output );
		$this->assertStringContainsString( 'id="param-max-tokens"', $output );
	}

	public function test_render_renders_empty_state_when_disconnected(): void {
		$GLOBALS['devbridge_ai_magnitude_can_manage_options'] = true;

		$page = new Settings_Page(
			$this->create_host_provider(),
			$this->create_inspector( new Connection_Status( false, array(), 'Server unreachable' ) )
		);

		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Test playground', $output );
		$this->assertStringContainsString( 'Connect to Magnitude and start at least one model to use the test playground.', $output );
		$this->assertStringNotContainsString( 'id="devbridge-playground-model"', $output );
	}
}
