<?php
/**
 * Playground rate limiter.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Playground;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Throttles playground requests to protect the local machine's GPU and CPU from resource exhaustion.
 *
 * @since 0.2.0
 */
final class Playground_Rate_Limiter {

	/**
	 * Prefix used for transient keys.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	public const TRANSIENT_PREFIX = 'devbridge_magnitude_rate_';

	/**
	 * Maximum requests permitted within the rolling window.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	private $max_requests;

	/**
	 * Rolling window length in seconds.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	private $window_seconds;

	/**
	 * Minimum cooldown period between consecutive requests in seconds.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	private $burst_cooldown;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param int $max_requests   Maximum requests permitted in rolling window. Default 20.
	 * @param int $window_seconds Length of window in seconds. Default 60.
	 * @param int $burst_cooldown Minimum seconds between consecutive requests. Default 1.
	 */
	public function __construct( int $max_requests = 20, int $window_seconds = 60, int $burst_cooldown = 1 ) {
		$this->max_requests   = max( 1, $max_requests );
		$this->window_seconds = max( 1, $window_seconds );
		$this->burst_cooldown = max( 0, $burst_cooldown );
	}

	/**
	 * Checks whether the user is permitted to make a new request.
	 *
	 * @since 0.2.0
	 *
	 * @param int|null $user_id Optional user ID. Defaults to current user.
	 * @return WP_Error|null WP_Error if throttled, or null if allowed.
	 */
	public function check( ?int $user_id = null ): ?WP_Error {
		$id  = $this->resolve_user_id( $user_id );
		$key = $this->get_transient_key( $id );
		$now = time();

		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$last_request = isset( $data['last_request'] ) && is_int( $data['last_request'] ) ? $data['last_request'] : 0;
		$timestamps   = isset( $data['timestamps'] ) && is_array( $data['timestamps'] ) ? $data['timestamps'] : array();

		// Check burst cooldown.
		if ( $this->burst_cooldown > 0 && ( $now - $last_request ) < $this->burst_cooldown ) {
			return new WP_Error(
				'rate_limit_cooldown',
				__( 'Please wait a moment before sending another prompt.', 'devbridge-ai-provider-magnitude' ),
				array(
					'status'      => 429,
					'retry_after' => max( 1, $this->burst_cooldown - ( $now - $last_request ) ),
				)
			);
		}

		// Filter timestamps to the active rolling window.
		$cutoff        = $now - $this->window_seconds;
		$active_window = array();
		foreach ( $timestamps as $ts ) {
			if ( is_int( $ts ) && $ts > $cutoff ) {
				$active_window[] = $ts;
			}
		}

		if ( array() !== $active_window && count( $active_window ) >= $this->max_requests ) {
			$earliest    = min( $active_window );
			$retry_after = max( 1, ( $earliest + $this->window_seconds ) - $now );

			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Too many requests. Please wait before sending another prompt.', 'devbridge-ai-provider-magnitude' ),
				array(
					'status'      => 429,
					'retry_after' => $retry_after,
				)
			);
		}

		return null;
	}

	/**
	 * Records a successful request execution for the rate-limiting window.
	 *
	 * @since 0.2.0
	 *
	 * @param int|null $user_id Optional user ID. Defaults to current user.
	 */
	public function record( ?int $user_id = null ): void {
		$id  = $this->resolve_user_id( $user_id );
		$key = $this->get_transient_key( $id );
		$now = time();

		$data       = get_transient( $key );
		$timestamps = is_array( $data ) && isset( $data['timestamps'] ) && is_array( $data['timestamps'] )
			? $data['timestamps']
			: array();

		// Keep only timestamps within window.
		$cutoff  = $now - $this->window_seconds;
		$cleaned = array();
		foreach ( $timestamps as $ts ) {
			if ( is_int( $ts ) && $ts > $cutoff ) {
				$cleaned[] = $ts;
			}
		}

		$cleaned[] = $now;

		set_transient(
			$key,
			array(
				'last_request' => $now,
				'timestamps'   => $cleaned,
			),
			$this->window_seconds
		);
	}

	/**
	 * Clears the rate limit tracking for a given user.
	 *
	 * @since 0.2.0
	 *
	 * @param int|null $user_id Optional user ID. Defaults to current user.
	 */
	public function reset( ?int $user_id = null ): void {
		$id = $this->resolve_user_id( $user_id );
		delete_transient( $this->get_transient_key( $id ) );
	}

	/**
	 * Resolves the target user ID.
	 *
	 * @since 0.2.0
	 *
	 * @param int|null $user_id User ID or null.
	 * @return int Resolved user ID.
	 */
	private function resolve_user_id( ?int $user_id ): int {
		if ( null !== $user_id ) {
			return $user_id;
		}

		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	/**
	 * Generates the transient key for a user.
	 *
	 * @since 0.2.0
	 *
	 * @param int $user_id User ID.
	 * @return string Transient key.
	 */
	private function get_transient_key( int $user_id ): string {
		return self::TRANSIENT_PREFIX . $user_id;
	}
}
