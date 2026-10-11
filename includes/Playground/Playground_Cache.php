<?php
/**
 * Playground response cache.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Playground;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caches model completion responses via WordPress transients to speed up repetitive tests and benchmark replays.
 *
 * @since 0.2.0
 */
final class Playground_Cache {

	/**
	 * Prefix used for transient keys.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	public const TRANSIENT_PREFIX = 'devbridge_magnitude_cache_';

	/**
	 * Default time-to-live in seconds (10 minutes).
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public const DEFAULT_TTL = 600;

	/**
	 * Time-to-live in seconds.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	private $ttl;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param int $ttl Cache time-to-live in seconds. Default 600.
	 */
	public function __construct( int $ttl = self::DEFAULT_TTL ) {
		$this->ttl = max( 1, $ttl );
	}

	/**
	 * Retrieves a cached completion result if one exists and bypass is false.
	 *
	 * @since 0.2.0
	 *
	 * @param string                                     $model       Model ID.
	 * @param list<array{role: string, content: string}> $messages    Conversation messages.
	 * @param float                                      $temperature Sampling temperature.
	 * @param float                                      $top_p       Top-p nucleus sampling.
	 * @param int                                        $max_tokens  Maximum tokens.
	 * @param bool                                       $bypass      Whether to bypass the cache.
	 * @return Playground_Result|null The cached result, or null if miss/bypassed.
	 */
	public function get(
		string $model,
		array $messages,
		float $temperature,
		float $top_p,
		int $max_tokens,
		bool $bypass = false
	): ?Playground_Result {
		if ( $bypass ) {
			return null;
		}

		$key  = $this->generate_key( $model, $messages, $temperature, $top_p, $max_tokens );
		$data = get_transient( $key );

		if ( ! is_array( $data ) || ! isset( $data['text'], $data['model'], $data['finish_reason'], $data['latency_ms'], $data['tokens'] ) ) {
			return null;
		}

		$tokens = is_array( $data['tokens'] ) ? $data['tokens'] : array();

		return new Playground_Result(
			(string) $data['text'],
			(string) $data['model'],
			(string) $data['finish_reason'],
			(float) $data['latency_ms'],
			isset( $tokens['prompt'] ) ? (int) $tokens['prompt'] : 0,
			isset( $tokens['completion'] ) ? (int) $tokens['completion'] : 0,
			isset( $tokens['total'] ) ? (int) $tokens['total'] : 0
		);
	}

	/**
	 * Stores a completion result in the cache.
	 *
	 * @since 0.2.0
	 *
	 * @param string                                     $model       Model ID.
	 * @param list<array{role: string, content: string}> $messages    Conversation messages.
	 * @param float                                      $temperature Sampling temperature.
	 * @param float                                      $top_p       Top-p nucleus sampling.
	 * @param int                                        $max_tokens  Maximum tokens.
	 * @param Playground_Result                          $result      The result to cache.
	 * @return bool True if cached successfully.
	 */
	public function set(
		string $model,
		array $messages,
		float $temperature,
		float $top_p,
		int $max_tokens,
		Playground_Result $result
	): bool {
		$key = $this->generate_key( $model, $messages, $temperature, $top_p, $max_tokens );

		return (bool) set_transient( $key, $result->to_array(), $this->ttl );
	}

	/**
	 * Deletes a cached entry for the given query parameters.
	 *
	 * @since 0.2.0
	 *
	 * @param string                                     $model       Model ID.
	 * @param list<array{role: string, content: string}> $messages    Conversation messages.
	 * @param float                                      $temperature Sampling temperature.
	 * @param float                                      $top_p       Top-p nucleus sampling.
	 * @param int                                        $max_tokens  Maximum tokens.
	 * @return bool True if deleted.
	 */
	public function delete(
		string $model,
		array $messages,
		float $temperature,
		float $top_p,
		int $max_tokens
	): bool {
		$key = $this->generate_key( $model, $messages, $temperature, $top_p, $max_tokens );

		return (bool) delete_transient( $key );
	}

	/**
	 * Computes a SHA-256 hash transient key from the inference parameters.
	 *
	 * @since 0.2.0
	 *
	 * @param string                                     $model       Model ID.
	 * @param list<array{role: string, content: string}> $messages    Conversation messages.
	 * @param float                                      $temperature Sampling temperature.
	 * @param float                                      $top_p       Top-p nucleus sampling.
	 * @param int                                        $max_tokens  Maximum tokens.
	 * @return string Transient cache key.
	 */
	public function generate_key(
		string $model,
		array $messages,
		float $temperature,
		float $top_p,
		int $max_tokens
	): string {
		$payload = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => round( $temperature, 2 ),
			'top_p'       => round( $top_p, 2 ),
			'max_tokens'  => $max_tokens,
		);

		$encoded = wp_json_encode( $payload );

		return self::TRANSIENT_PREFIX . hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
	}
}
