<?php
/**
 * Tests for the host validator.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Settings\Host_Validator;
use PHPUnit\Framework\TestCase;

/**
 * Covers validation and normalization of the Magnitude server address.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Settings\Host_Validator
 */
final class Host_Validator_Test extends TestCase {

	public function test_valid_address_is_returned_without_trailing_slash(): void {
		$this->assertSame( 'https://magnitude.test:8443/inference', ( new Host_Validator() )->validate( 'https://magnitude.test:8443/inference/' ) );
	}

	public function test_surrounding_whitespace_is_ignored(): void {
		$this->assertSame( 'http://127.0.0.1:10100/inference', ( new Host_Validator() )->validate( '  http://127.0.0.1:10100/inference  ' ) );
	}

	public function test_trailing_v1_is_dropped_because_the_plugin_adds_it(): void {
		$validator = new Host_Validator();

		$this->assertSame( 'http://192.168.1.20:10100/inference', $validator->validate( 'http://192.168.1.20:10100/inference/v1' ) );
		$this->assertSame( 'http://192.168.1.20:10100/inference', $validator->validate( 'http://192.168.1.20:10100/inference/v1/' ) );
	}

	/**
	 * Values that must be rejected.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function invalid_hosts(): array {
		return array(
			'empty string'      => array( '' ),
			'blank string'      => array( '   ' ),
			'not a string'      => array( 42 ),
			'null'              => array( null ),
			'ftp scheme'        => array( 'ftp://example.test' ),
			'javascript scheme' => array( 'javascript:alert(1)' ),
			'credentials'       => array( 'http://user:pass@example.test' ),
			'user only'         => array( 'http://user@example.test' ),
			'no host'           => array( 'http://' ),
			'no scheme'         => array( 'example.test:10100' ),
			'bare host'         => array( 'magnitude.local' ),
			'query string'      => array( 'http://example.test/inference?x=1' ),
			'fragment'          => array( 'http://example.test/inference#top' ),
			'markup'            => array( '<script>x</script>' ),
			'inner space'       => array( 'http://exa mple.test' ),
			'quote'             => array( 'http://example.test/"x' ),
		);
	}

	/**
	 * @dataProvider invalid_hosts
	 *
	 * @param mixed $value The invalid value.
	 */
	public function test_invalid_addresses_are_rejected( $value ): void {
		$this->assertSame( '', ( new Host_Validator() )->validate( $value ) );
	}
}
