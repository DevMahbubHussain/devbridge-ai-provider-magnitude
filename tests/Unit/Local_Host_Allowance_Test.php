<?php
/**
 * Tests for the local host allowance.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Contracts\Host_Provider;
use DevBridgeAiProviderForMagnitude\Http\Local_Host_Allowance;
use PHPUnit\Framework\TestCase;

/**
 * Covers which requests WordPress is allowed to send to a local Magnitude server.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Http\Local_Host_Allowance
 */
final class Local_Host_Allowance_Test extends TestCase {

	/**
	 * Creates the allowance for a configured address.
	 *
	 * @param string $host The configured address.
	 * @return Local_Host_Allowance The allowance.
	 */
	private function allowance( string $host ): Local_Host_Allowance {
		$provider = new class( $host ) implements Host_Provider {
			/**
			 * The configured address.
			 *
			 * @var string
			 */
			private $host;

			/**
			 * Constructor.
			 *
			 * @param string $host The configured address.
			 */
			public function __construct( string $host ) {
				$this->host = $host;
			}

			/**
			 * Gets the address.
			 *
			 * @return string The address.
			 */
			public function get_host(): string {
				return $this->host;
			}

			/**
			 * Reports that the address is not locked.
			 *
			 * @return bool Always false.
			 */
			public function is_locked(): bool {
				return false;
			}
		};

		return new Local_Host_Allowance( $provider );
	}

	public function test_configured_host_is_allowed(): void {
		$this->assertTrue( $this->allowance( 'http://192.168.1.20:10100/inference' )->allow_host( false, '192.168.1.20' ) );
	}

	public function test_host_comparison_ignores_case(): void {
		$this->assertTrue( $this->allowance( 'http://Magnitude.Local:10100/inference' )->allow_host( false, 'magnitude.local' ) );
	}

	public function test_other_hosts_keep_the_default_decision(): void {
		$allowance = $this->allowance( 'http://192.168.1.20:10100/inference' );

		$this->assertFalse( $allowance->allow_host( false, '192.168.1.99' ) );
		$this->assertTrue( $allowance->allow_host( true, 'example.test' ) );
	}

	public function test_port_is_added_for_the_configured_host_only(): void {
		$allowance = $this->allowance( 'http://192.168.1.20:10100/inference' );

		$this->assertSame( array( 80, 443, 8080, 10100 ), $allowance->allow_port( array( 80, 443, 8080 ), '192.168.1.20' ) );
		$this->assertSame( array( 80, 443, 8080 ), $allowance->allow_port( array( 80, 443, 8080 ), 'example.test' ) );
		$this->assertSame( array( 80, 443, 8080 ), $allowance->allow_port( array( 80, 443, 8080 ) ) );
	}

	public function test_no_port_is_added_when_the_address_has_none(): void {
		$this->assertSame( array( 80 ), $this->allowance( 'https://magnitude.test/inference' )->allow_port( array( 80 ), 'magnitude.test' ) );
	}

	public function test_unexpected_port_list_is_handled(): void {
		$this->assertSame( array( 10100 ), $this->allowance( 'http://192.168.1.20:10100/inference' )->allow_port( null, '192.168.1.20' ) );
	}
}
