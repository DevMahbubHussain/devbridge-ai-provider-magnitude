<?php
/**
 * Tests for the playground rate limiter.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Playground\Playground_Rate_Limiter;
use PHPUnit\Framework\TestCase;

/**
 * Covers Playground_Rate_Limiter throttling, burst protection, and reset capabilities.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Playground\Playground_Rate_Limiter
 */
final class Playground_Rate_Limiter_Test extends TestCase {

	/**
	 * Tear down stand-in transients.
	 */
	protected function tearDown(): void {
		$GLOBALS['devbridge_ai_magnitude_transients'] = array();
	}

	public function test_initial_request_is_allowed(): void {
		$limiter = new Playground_Rate_Limiter( 5, 60, 1 );

		$this->assertNull( $limiter->check( 1 ) );
	}

	public function test_burst_cooldown_throttles_immediate_consecutive_request(): void {
		$limiter = new Playground_Rate_Limiter( 5, 60, 2 );

		// Record first request at current time.
		$limiter->record( 1 );

		// Immediate check should trigger burst cooldown.
		$error = $limiter->check( 1 );
		$this->assertTrue( is_wp_error( $error ) );
		$this->assertSame( 'rate_limit_cooldown', $error->get_error_code() );
		$this->assertSame( 429, $error->get_error_data()['status'] );
	}

	public function test_requests_within_window_up_to_max_are_allowed(): void {
		// Set burst cooldown to 0 so we can test window counting in rapid succession.
		$limiter = new Playground_Rate_Limiter( 3, 60, 0 );

		$limiter->record( 1 );
		$this->assertNull( $limiter->check( 1 ) );

		$limiter->record( 1 );
		$this->assertNull( $limiter->check( 1 ) );

		$limiter->record( 1 );
		// Now we have reached 3 requests. Check should block.
		$error = $limiter->check( 1 );
		$this->assertTrue( is_wp_error( $error ) );
		$this->assertSame( 'rate_limit_exceeded', $error->get_error_code() );
		$this->assertSame( 429, $error->get_error_data()['status'] );
	}

	public function test_reset_clears_throttle_tracking(): void {
		$limiter = new Playground_Rate_Limiter( 1, 60, 0 );

		$limiter->record( 1 );
		$this->assertTrue( is_wp_error( $limiter->check( 1 ) ) );

		$limiter->reset( 1 );
		$this->assertNull( $limiter->check( 1 ) );
	}

	public function test_separate_users_have_independent_quotas(): void {
		$limiter = new Playground_Rate_Limiter( 1, 60, 0 );

		$limiter->record( 10 );
		$this->assertTrue( is_wp_error( $limiter->check( 10 ) ) );

		// User 20 should not be throttled by user 10's activity.
		$this->assertNull( $limiter->check( 20 ) );
	}
}
