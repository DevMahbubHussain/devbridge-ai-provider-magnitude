<?php
/**
 * Playground result DTO.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Playground;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable DTO representing an AI completion result and its performance telemetry.
 *
 * @since 0.2.0
 */
final class Playground_Result {

	/**
	 * Generated completion text.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	private $text;

	/**
	 * Model ID that generated the completion.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Reason the model stopped generating (e.g. "stop", "length").
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	private $finish_reason;

	/**
	 * Total round-trip latency in milliseconds.
	 *
	 * @since 0.2.0
	 *
	 * @var float
	 */
	private $latency_ms;

	/**
	 * Number of tokens in the prompt.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	private $prompt_tokens;

	/**
	 * Number of tokens generated in the completion.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	private $completion_tokens;

	/**
	 * Total tokens consumed (prompt + completion).
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	private $total_tokens;

	/**
	 * Generation speed in completion tokens per second.
	 *
	 * @since 0.2.0
	 *
	 * @var float
	 */
	private $tokens_per_second;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param string $text              Generated completion text.
	 * @param string $model             Model ID.
	 * @param string $finish_reason     Finish reason identifier.
	 * @param float  $latency_ms        Total latency in milliseconds.
	 * @param int    $prompt_tokens     Prompt token count.
	 * @param int    $completion_tokens Completion token count.
	 * @param int    $total_tokens      Total token count.
	 */
	public function __construct(
		string $text,
		string $model,
		string $finish_reason,
		float $latency_ms,
		int $prompt_tokens,
		int $completion_tokens,
		int $total_tokens
	) {
		$this->text              = $text;
		$this->model             = $model;
		$this->finish_reason     = $finish_reason;
		$this->latency_ms        = max( 0.0, $latency_ms );
		$this->prompt_tokens     = max( 0, $prompt_tokens );
		$this->completion_tokens = max( 0, $completion_tokens );
		$this->total_tokens      = max( 0, $total_tokens );

		if ( $this->latency_ms > 0.0 && $this->completion_tokens > 0 ) {
			$this->tokens_per_second = round( (float) $this->completion_tokens / ( $this->latency_ms / 1000.0 ), 1 );
		} else {
			$this->tokens_per_second = 0.0;
		}
	}

	/**
	 * Gets the generated text.
	 *
	 * @since 0.2.0
	 *
	 * @return string Generated text.
	 */
	public function get_text(): string {
		return $this->text;
	}

	/**
	 * Gets the model ID.
	 *
	 * @since 0.2.0
	 *
	 * @return string Model ID.
	 */
	public function get_model(): string {
		return $this->model;
	}

	/**
	 * Gets the finish reason.
	 *
	 * @since 0.2.0
	 *
	 * @return string Finish reason.
	 */
	public function get_finish_reason(): string {
		return $this->finish_reason;
	}

	/**
	 * Gets the round-trip latency in milliseconds.
	 *
	 * @since 0.2.0
	 *
	 * @return float Latency in milliseconds.
	 */
	public function get_latency_ms(): float {
		return $this->latency_ms;
	}

	/**
	 * Gets the prompt token count.
	 *
	 * @since 0.2.0
	 *
	 * @return int Prompt tokens.
	 */
	public function get_prompt_tokens(): int {
		return $this->prompt_tokens;
	}

	/**
	 * Gets the completion token count.
	 *
	 * @since 0.2.0
	 *
	 * @return int Completion tokens.
	 */
	public function get_completion_tokens(): int {
		return $this->completion_tokens;
	}

	/**
	 * Gets the total token count.
	 *
	 * @since 0.2.0
	 *
	 * @return int Total tokens.
	 */
	public function get_total_tokens(): int {
		return $this->total_tokens;
	}

	/**
	 * Gets generation throughput in tokens per second.
	 *
	 * @since 0.2.0
	 *
	 * @return float Tokens per second.
	 */
	public function get_tokens_per_second(): float {
		return $this->tokens_per_second;
	}

	/**
	 * Serializes the result to an associative array for JSON responses.
	 *
	 * @since 0.2.0
	 *
	 * @return array{
	 *     text: string,
	 *     model: string,
	 *     finish_reason: string,
	 *     latency_ms: float,
	 *     tokens: array{prompt: int, completion: int, total: int},
	 *     tokens_per_second: float
	 * } Serialized telemetry payload.
	 */
	public function to_array(): array {
		return array(
			'text'              => $this->text,
			'model'             => $this->model,
			'finish_reason'     => $this->finish_reason,
			'latency_ms'        => $this->latency_ms,
			'tokens'            => array(
				'prompt'     => $this->prompt_tokens,
				'completion' => $this->completion_tokens,
				'total'      => $this->total_tokens,
			),
			'tokens_per_second' => $this->tokens_per_second,
		);
	}
}
