<?php
/**
 * Registry playground invoker.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Playground;

use DevBridgeAiProviderForMagnitude\Provider\MagnitudeProvider;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invokes model generation through the WordPress AI Client registry.
 *
 * @since 0.2.0
 */
final class Registry_Playground_Invoker implements Playground_Invoker {

	/**
	 * Dispatches a single-shot text generation request.
	 *
	 * @since 0.2.0
	 *
	 * @param string                                     $model_id Model identifier.
	 * @param list<array{role: string, content: string}> $messages Conversation message list.
	 * @param array<string, mixed>                       $options  Optional sampling parameters.
	 * @return Playground_Result The generation telemetry and result.
	 */
	public function generate( string $model_id, array $messages, array $options = array() ): Playground_Result {
		$system_instructions = array();
		$conversation        = array();

		foreach ( $messages as $message ) {
			if ( 'system' === $message['role'] ) {
				$system_instructions[] = $message['content'];
			} else {
				$conversation[] = $message;
			}
		}

		// The final turn is the active prompt. Preceding turns form the conversation history.
		$last_turn = ! empty( $conversation )
			? array_pop( $conversation )
			: array(
				'role'    => 'user',
				'content' => '',
			);
		$prompt    = $last_turn['content'];

		$builder = AiClient::prompt( $prompt )
			->usingProvider( MagnitudeProvider::ID )
			->usingModelPreference( $model_id );

		if ( ! empty( $system_instructions ) ) {
			$builder->usingSystemInstruction( implode( "\n\n", $system_instructions ) );
		}

		if ( ! empty( $conversation ) ) {
			$history_dtos = array();
			foreach ( $conversation as $turn ) {
				$role_enum      = 'assistant' === $turn['role'] ? MessageRoleEnum::model() : MessageRoleEnum::user();
				$history_dtos[] = new Message( $role_enum, array( new MessagePart( $turn['content'] ) ) );
			}
			$builder->withHistory( ...$history_dtos );
		}

		if ( isset( $options['temperature'] ) && is_numeric( $options['temperature'] ) ) {
			$builder->usingTemperature( (float) $options['temperature'] );
		}

		if ( isset( $options['top_p'] ) && is_numeric( $options['top_p'] ) ) {
			$builder->usingTopP( (float) $options['top_p'] );
		}

		if ( isset( $options['max_tokens'] ) && is_numeric( $options['max_tokens'] ) ) {
			$builder->usingMaxTokens( (int) $options['max_tokens'] );
		}

		$start_time = function_exists( 'hrtime' ) ? hrtime( true ) : (int) ( microtime( true ) * 1e9 );
		$gen_result = $builder->generateTextResult();
		$end_time   = function_exists( 'hrtime' ) ? hrtime( true ) : (int) ( microtime( true ) * 1e9 );
		$latency_ms = max( 0.0, ( $end_time - $start_time ) / 1e6 );

		$text  = $gen_result->toText();
		$usage = $gen_result->getTokenUsage();

		$prompt_tokens     = $usage->getPromptTokens();
		$completion_tokens = $usage->getCompletionTokens();
		$total_tokens      = $usage->getTotalTokens();

		$candidates    = $gen_result->getCandidates();
		$finish_reason = 'stop';
		if ( ! empty( $candidates ) ) {
			$finish_reason = $candidates[0]->getFinishReason()->value;
		}

		return new Playground_Result(
			$text,
			$model_id,
			$finish_reason,
			$latency_ms,
			$prompt_tokens,
			$completion_tokens,
			$total_tokens
		);
	}

	/**
	 * Dispatches a streaming text generation request, invoking a callback for each emitted chunk.
	 *
	 * @since 0.2.0
	 *
	 * @param string                                     $model_id       Model identifier.
	 * @param list<array{role: string, content: string}> $messages       Conversation message list.
	 * @param array<string, mixed>                       $options        Optional sampling parameters.
	 * @param callable(string): void                     $chunk_callback Callback invoked with each text delta.
	 * @return Playground_Result The aggregated completion result and final telemetry.
	 */
	public function generate_stream( string $model_id, array $messages, array $options, callable $chunk_callback ): Playground_Result {
		$result = $this->generate( $model_id, $messages, $options );

		// Emit the generated text to the stream chunk callback.
		$chunk_callback( $result->get_text() );

		return $result;
	}
}
