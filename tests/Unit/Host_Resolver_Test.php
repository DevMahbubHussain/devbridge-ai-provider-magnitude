<?php
/**
 * Tests for the host resolver.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Host\Host_Resolver;
use PHPUnit\Framework\TestCase;

/**
 * Covers how the Magnitude server address is chosen.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Host\Host_Resolver
 */
final class Host_Resolver_Test extends TestCase {

	/**
	 * Resets the stand-ins after each test.
	 */
	protected function tearDown(): void {
		$GLOBALS['devbridge_ai_magnitude_filters'] = array();
		$GLOBALS['devbridge_ai_magnitude_options'] = array();
	}

	public function test_default_host_is_used_without_overrides(): void {
		$this->assertSame( Host_Resolver::DEFAULT_HOST, ( new Host_Resolver() )->get_host() );
	}

	public function test_saved_setting_is_used(): void {
		$GLOBALS['devbridge_ai_magnitude_options'][ Host_Resolver::OPTION_NAME ] = 'http://192.168.1.20:10100/inference/v1';

		$this->assertSame( 'http://192.168.1.20:10100/inference', ( new Host_Resolver() )->get_host() );
	}

	public function test_empty_saved_setting_falls_back_to_default(): void {
		$GLOBALS['devbridge_ai_magnitude_options'][ Host_Resolver::OPTION_NAME ] = '';

		$this->assertSame( Host_Resolver::DEFAULT_HOST, ( new Host_Resolver() )->get_host() );
	}

	public function test_invalid_saved_setting_falls_back_to_default(): void {
		$GLOBALS['devbridge_ai_magnitude_options'][ Host_Resolver::OPTION_NAME ] = 'ftp://example.test';

		$this->assertSame( Host_Resolver::DEFAULT_HOST, ( new Host_Resolver() )->get_host() );
	}

	public function test_filter_overrides_the_saved_setting(): void {
		$GLOBALS['devbridge_ai_magnitude_options'][ Host_Resolver::OPTION_NAME ]           = 'http://saved.test:1/inference';
		$GLOBALS['devbridge_ai_magnitude_filters']['devbridge_ai_provider_magnitude_host'] = 'https://filtered.test:8443/inference/';

		$this->assertSame( 'https://filtered.test:8443/inference', ( new Host_Resolver() )->get_host() );
	}

	public function test_invalid_filtered_value_falls_back_to_default(): void {
		$GLOBALS['devbridge_ai_magnitude_filters']['devbridge_ai_provider_magnitude_host'] = 'javascript:alert(1)';

		$this->assertSame( Host_Resolver::DEFAULT_HOST, ( new Host_Resolver() )->get_host() );
	}

	public function test_address_is_not_locked_without_the_constant(): void {
		$this->assertFalse( ( new Host_Resolver() )->is_locked() );
	}
}
