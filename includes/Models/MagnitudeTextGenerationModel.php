<?php
/**
 * Magnitude text generation model.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Models;

use DevBridgeAiProviderForMagnitude\Traits\Builds_Api_Url;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Text generation through Magnitude's OpenAI-compatible chat completions API.
 *
 * @since 0.1.0
 */
class MagnitudeTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {
	use Builds_Api_Url;

	/**
	 * Default request timeout in seconds. A model can take about a minute to load on first use.
	 *
	 * @since 0.1.0
	 *
	 * @var float
	 */
	private const DEFAULT_TIMEOUT = 120.0;

	/**
	 * Creates a request with the Magnitude URL and timeout.
	 *
	 * @since 0.1.0
	 *
	 * @param HttpMethodEnum                     $method  The HTTP method.
	 * @param string                             $path    The API path.
	 * @param array<string, string|list<string>> $headers Optional. Request headers. Default empty.
	 * @param string|array<string, mixed>|null   $data    Optional. Request data. Default null.
	 * @return Request The request.
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		$options = new RequestOptions();
		$options->setTimeout( $this->get_timeout() );

		return new Request( $method, $this->build_api_url( $path ), $headers, $data, $options );
	}

	/**
	 * Prepares the messages, sending system and assistant text as plain strings.
	 *
	 * Magnitude rejects a list of content parts for these roles ("expected a
	 * string"), while OpenAI-style servers accept both. User messages keep their
	 * parts so images can still be sent.
	 *
	 * @since 0.1.0
	 *
	 * @param list<Message> $messages           The messages to prepare.
	 * @param string|null   $system_instruction Optional. A system instruction to prepend. Default null.
	 * @return list<array<string, mixed>> The prepared messages.
	 */
	protected function prepareMessagesParam( array $messages, ?string $system_instruction = null ): array { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- The sniff cannot read the list<Message> type that PHPStan requires from the parent signature.
		$prepared = array_map(
			array( $this, 'flatten_text_content' ),
			parent::prepareMessagesParam( $messages )
		);

		if ( null !== $system_instruction && '' !== $system_instruction ) {
			array_unshift(
				$prepared,
				array(
					'role'    => 'system',
					'content' => $system_instruction,
				)
			);
		}

		return $prepared;
	}

	/**
	 * Prepares the response format, naming the JSON schema as Magnitude requires.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed>|null $output_schema The output schema, or null for any JSON object.
	 * @return array<string, mixed> The response format.
	 */
	protected function prepareResponseFormatParam( ?array $output_schema ): array {
		if ( null === $output_schema ) {
			return array( 'type' => 'json_object' );
		}

		return array(
			'type'        => 'json_schema',
			'json_schema' => array(
				'name'   => 'response_schema',
				'schema' => $output_schema,
			),
		);
	}

	/**
	 * Collapses text-only content parts into a string for system and assistant messages.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $message A prepared message.
	 * @return array<string, mixed> The message, with string content when it was only text.
	 */
	private function flatten_text_content( array $message ): array {
		if ( ! in_array( $message['role'] ?? '', array( 'system', 'assistant' ), true ) || ! isset( $message['content'] ) || ! is_array( $message['content'] ) ) {
			return $message;
		}

		$text = '';

		foreach ( $message['content'] as $part ) {
			if ( ! is_array( $part ) || ! isset( $part['type'], $part['text'] ) || 'text' !== $part['type'] || ! is_string( $part['text'] ) ) {
				return $message;
			}

			$text .= $part['text'];
		}

		$message['content'] = $text;

		return $message;
	}

	/**
	 * Gets the request timeout in seconds.
	 *
	 * @since 0.1.0
	 *
	 * @return float The timeout, never below one second.
	 */
	private function get_timeout(): float {
		/**
		 * Filters the Magnitude request timeout in seconds.
		 *
		 * @since 0.1.0
		 *
		 * @param float $timeout The timeout in seconds.
		 */
		$timeout = apply_filters( 'devbridge_ai_provider_magnitude_request_timeout', self::DEFAULT_TIMEOUT );

		return is_numeric( $timeout ) ? max( 1.0, (float) $timeout ) : self::DEFAULT_TIMEOUT;
	}
}
