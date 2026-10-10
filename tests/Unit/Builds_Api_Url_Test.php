<?php
/**
 * Tests for the API URL builder trait.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Host\Host_Resolver;
use DevBridgeAiProviderForMagnitude\Traits\Builds_Api_Url;
use PHPUnit\Framework\TestCase;

/**
 * Covers how API paths are joined to the Magnitude address.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Traits\Builds_Api_Url
 */
final class Builds_Api_Url_Test extends TestCase {

	/**
	 * Builds a URL through the trait.
	 *
	 * @param string $path The API path.
	 * @return string The URL.
	 */
	private function build( string $path ): string {
		$builder = new class() {
			use Builds_Api_Url;

			/**
			 * Exposes the protected trait method.
			 *
			 * @param string $path The API path.
			 * @return string The URL.
			 */
			public function url( string $path ): string {
				return $this->build_api_url( $path );
			}
		};

		return $builder->url( $path );
	}

	/**
	 * Resets the stand-ins after each test.
	 */
	protected function tearDown(): void {
		$GLOBALS['devbridge_ai_magnitude_options'] = array();
	}

	public function test_path_is_placed_under_v1(): void {
		$this->assertSame( 'http://127.0.0.1:10100/inference/v1/chat/completions', $this->build( 'chat/completions' ) );
	}

	public function test_a_leading_v1_is_not_repeated(): void {
		$this->assertSame( 'http://127.0.0.1:10100/inference/v1/models', $this->build( 'v1/models' ) );
		$this->assertSame( 'http://127.0.0.1:10100/inference/v1/models', $this->build( '/v1/models' ) );
	}

	public function test_only_a_whole_v1_segment_is_removed(): void {
		$this->assertSame( 'http://127.0.0.1:10100/inference/v1/v1beta/foo', $this->build( 'v1beta/foo' ) );
	}

	public function test_a_saved_address_is_used(): void {
		$GLOBALS['devbridge_ai_magnitude_options'][ Host_Resolver::OPTION_NAME ] = 'https://magnitude.test:8443/inference';

		$this->assertSame( 'https://magnitude.test:8443/inference/v1/models', $this->build( 'models' ) );
	}
}
