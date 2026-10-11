<?php
/**
 * Playground request validator.
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
 * Validates and sanitizes parameters for the AI Playground REST requests.
 *
 * @since 0.2.0
 */
final class Playground_Request_Validator {

	/**
	 * Maximum allowed character length for a user or assistant prompt.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public const MAX_PROMPT_LENGTH = 10000;

	/**
	 * Maximum allowed character length for a system instruction.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public const MAX_SYSTEM_PROMPT_LENGTH = 5000;

	/**
	 * Maximum conversation turns allowed in a single multi-turn session.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public const MAX_TURNS = 20;

	/**
	 * Default sampling temperature.
	 *
	 * @since 0.2.0
	 *
	 * @var float
	 */
	public const DEFAULT_TEMPERATURE = 0.7;

	/**
	 * Default nucleus sampling top-p.
	 *
	 * @since 0.2.0
	 *
	 * @var float
	 */
	public const DEFAULT_TOP_P = 1.0;

	/**
	 * Default maximum completion tokens.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public const DEFAULT_MAX_TOKENS = 1024;

	/**
	 * Validates and sanitizes incoming request parameters.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, mixed> $params           Raw request parameters.
	 * @param list<string>         $active_model_ids Optional list of currently running model IDs.
	 * @return array{
	 *     model: string,
	 *     messages: list<array{role: string, content: string}>,
	 *     temperature: float,
	 *     top_p: float,
	 *     max_tokens: int,
	 *     stream: bool,
	 *     bypass_cache: bool
	 * }|WP_Error Validated parameters array or WP_Error on validation failure.
	 */
	public function validate( array $params, array $active_model_ids = array() ) { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- The sniff cannot read list<string>.
		// 1. Model ID validation.
		$model_raw = isset( $params['model'] ) && is_string( $params['model'] ) ? trim( $params['model'] ) : '';

		if ( '' === $model_raw ) {
			return new WP_Error(
				'missing_model',
				__( 'A model must be selected.', 'devbridge-ai-provider-magnitude' ),
				array( 'status' => 400 )
			);
		}

		if ( 1 !== preg_match( '#^[a-zA-Z0-9._:/-]+$#', $model_raw ) ) {
			return new WP_Error(
				'invalid_model_id',
				__( 'The selected model identifier contains invalid characters.', 'devbridge-ai-provider-magnitude' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $active_model_ids ) && ! in_array( $model_raw, $active_model_ids, true ) ) {
			return new WP_Error(
				'model_not_found',
				__( 'The selected model is not currently running in Magnitude.', 'devbridge-ai-provider-magnitude' ),
				array( 'status' => 404 )
			);
		}

		// 2. Messages payload validation (multi-turn or single-turn fallback).
		$messages = $this->validate_messages( $params );

		if ( is_wp_error( $messages ) ) {
			return $messages;
		}

		// 3. Sampling parameter validation.
		$temperature = self::DEFAULT_TEMPERATURE;
		if ( isset( $params['temperature'] ) && is_numeric( $params['temperature'] ) ) {
			$temp_val = (float) $params['temperature'];
			if ( $temp_val >= 0.0 && $temp_val <= 2.0 ) {
				$temperature = $temp_val;
			}
		}

		$top_p = self::DEFAULT_TOP_P;
		if ( isset( $params['top_p'] ) && is_numeric( $params['top_p'] ) ) {
			$top_p_val = (float) $params['top_p'];
			if ( $top_p_val >= 0.0 && $top_p_val <= 1.0 ) {
				$top_p = $top_p_val;
			}
		}

		$max_tokens = self::DEFAULT_MAX_TOKENS;
		if ( isset( $params['max_tokens'] ) && is_numeric( $params['max_tokens'] ) ) {
			$tokens_val = (int) $params['max_tokens'];
			if ( $tokens_val >= 1 && $tokens_val <= 8192 ) {
				$max_tokens = $tokens_val;
			}
		}

		$stream       = ! empty( $params['stream'] );
		$bypass_cache = ! empty( $params['bypass_cache'] );

		return array(
			'model'        => $model_raw,
			'messages'     => $messages,
			'temperature'  => $temperature,
			'top_p'        => $top_p,
			'max_tokens'   => $max_tokens,
			'stream'       => $stream,
			'bypass_cache' => $bypass_cache,
		);
	}

	/**
	 * Validates and formats the messages array.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, mixed> $params Request parameters.
	 * @return list<array{role: string, content: string}>|WP_Error Formatted messages or WP_Error.
	 */
	private function validate_messages( array $params ) {
		// Multi-turn mode.
		if ( isset( $params['messages'] ) && is_array( $params['messages'] ) ) {
			$raw_messages = $params['messages'];

			if ( empty( $raw_messages ) ) {
				return new WP_Error(
					'empty_messages',
					__( 'The conversation cannot be empty.', 'devbridge-ai-provider-magnitude' ),
					array( 'status' => 400 )
				);
			}

			if ( count( $raw_messages ) > self::MAX_TURNS ) {
				return new WP_Error(
					'exceeded_max_turns',
					sprintf(
						/* translators: %d: Maximum allowed conversation turns. */
						__( 'The conversation exceeds the maximum limit of %d turns.', 'devbridge-ai-provider-magnitude' ),
						self::MAX_TURNS
					),
					array( 'status' => 400 )
				);
			}

			$formatted = array();
			$has_user  = false;

			foreach ( $raw_messages as $message ) {
				if ( ! is_array( $message ) || ! isset( $message['role'], $message['content'] ) || ! is_string( $message['role'] ) || ! is_string( $message['content'] ) ) {
					return new WP_Error(
						'invalid_message_format',
						__( 'Each message in the conversation must contain a role and string content.', 'devbridge-ai-provider-magnitude' ),
						array( 'status' => 400 )
					);
				}

				$role = strtolower( trim( $message['role'] ) );
				if ( ! in_array( $role, array( 'system', 'user', 'assistant' ), true ) ) {
					return new WP_Error(
						'invalid_message_role',
						__( 'Message role must be system, user, or assistant.', 'devbridge-ai-provider-magnitude' ),
						array( 'status' => 400 )
					);
				}

				$content = sanitize_textarea_field( $message['content'] );
				$cap     = 'system' === $role ? self::MAX_SYSTEM_PROMPT_LENGTH : self::MAX_PROMPT_LENGTH;

				if ( mb_strlen( $content ) > $cap ) {
					return new WP_Error(
						'message_too_long',
						sprintf(
							/* translators: 1: Message role, 2: Maximum characters allowed. */
							__( 'The %1$s message exceeds the maximum length of %2$d characters.', 'devbridge-ai-provider-magnitude' ),
							$role,
							$cap
						),
						array( 'status' => 400 )
					);
				}

				if ( 'user' === $role && '' !== trim( $content ) ) {
					$has_user = true;
				}

				$formatted[] = array(
					'role'    => $role,
					'content' => $content,
				);
			}

			if ( ! $has_user ) {
				return new WP_Error(
					'missing_user_prompt',
					__( 'The conversation must include at least one non-empty user prompt.', 'devbridge-ai-provider-magnitude' ),
					array( 'status' => 400 )
				);
			}

			return $formatted;
		}

		// Single-turn fallback mode (prompt + optional system_prompt).
		$prompt_raw = isset( $params['prompt'] ) && is_string( $params['prompt'] ) ? sanitize_textarea_field( $params['prompt'] ) : '';

		if ( '' === trim( $prompt_raw ) ) {
			return new WP_Error(
				'missing_prompt',
				__( 'Please provide a prompt.', 'devbridge-ai-provider-magnitude' ),
				array( 'status' => 400 )
			);
		}

		if ( mb_strlen( $prompt_raw ) > self::MAX_PROMPT_LENGTH ) {
			return new WP_Error(
				'prompt_too_long',
				sprintf(
					/* translators: %d: Maximum allowed characters. */
					__( 'Prompt exceeds the maximum allowed length of %d characters.', 'devbridge-ai-provider-magnitude' ),
					self::MAX_PROMPT_LENGTH
				),
				array( 'status' => 400 )
			);
		}

		$messages = array();

		if ( isset( $params['system_prompt'] ) && is_string( $params['system_prompt'] ) ) {
			$system_raw = sanitize_textarea_field( $params['system_prompt'] );

			if ( mb_strlen( $system_raw ) > self::MAX_SYSTEM_PROMPT_LENGTH ) {
				return new WP_Error(
					'system_prompt_too_long',
					sprintf(
						/* translators: %d: Maximum allowed characters. */
						__( 'System prompt exceeds the maximum allowed length of %d characters.', 'devbridge-ai-provider-magnitude' ),
						self::MAX_SYSTEM_PROMPT_LENGTH
					),
					array( 'status' => 400 )
				);
			}

			if ( '' !== trim( $system_raw ) ) {
				$messages[] = array(
					'role'    => 'system',
					'content' => $system_raw,
				);
			}
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt_raw,
		);

		return $messages;
	}
}
