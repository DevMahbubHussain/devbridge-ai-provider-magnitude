<?php
/**
 * Playground invoker contract.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Playground;

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface contract for dispatching model generation requests from the playground.
 *
 * @since 0.2.0
 */
interface Playground_Invoker {

	/**
	 * Dispatches a single-shot text generation request.
	 *
	 * @since 0.2.0
	 *
	 * @param string                                     $model_id Model identifier.
	 * @param list<array{role: string, content: string}> $messages Conversation message list.
	 * @param array<string, mixed>                       $options  Optional sampling parameters (temperature, top_p, max_tokens).
	 * @return Playground_Result The generation telemetry and result.
	 *
	 * @throws Throwable On transport or inference failure.
	 */
	public function generate( string $model_id, array $messages, array $options = array() ): Playground_Result;

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
	 *
	 * @throws Throwable On transport or streaming failure.
	 */
	public function generate_stream( string $model_id, array $messages, array $options, callable $chunk_callback ): Playground_Result;
}
