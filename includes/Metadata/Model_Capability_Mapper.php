<?php
/**
 * Model capability mapper.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps a Magnitude model entry to AI Client supported options.
 *
 * Magnitude lists `supported_parameters` (for example "tools") and
 * `architecture.input_modalities` (for example "image") for every model.
 * This class holds no WordPress state, so it can be tested on its own.
 *
 * @since 0.1.0
 */
final class Model_Capability_Mapper {

	/**
	 * Gets the display name of a model.
	 *
	 * @since 0.1.0
	 *
	 * @param array<mixed, mixed> $entry A model entry from the models list.
	 * @return string The model name, falling back to its ID.
	 */
	public function get_name( array $entry ): string {
		if ( isset( $entry['name'] ) && is_string( $entry['name'] ) && '' !== $entry['name'] ) {
			return $entry['name'];
		}

		return isset( $entry['id'] ) && is_string( $entry['id'] ) ? $entry['id'] : '';
	}

	/**
	 * Builds the supported options of a model.
	 *
	 * @since 0.1.0
	 *
	 * @param array<mixed, mixed> $entry A model entry from the models list.
	 * @return list<SupportedOption> The supported options.
	 */
	public function build_options( array $entry ): array {
		$parameters = $this->get_string_list( $entry['supported_parameters'] ?? null );
		$modalities = $this->get_string_list( is_array( $entry['architecture'] ?? null ) ? ( $entry['architecture']['input_modalities'] ?? null ) : null );

		$options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			// Magnitude answers with a single choice: a request for several is rejected (n must be 1).
			new SupportedOption( OptionEnum::candidateCount(), array( 1 ) ),
			new SupportedOption( OptionEnum::customOptions() ),
		);

		if ( in_array( 'response_format', $parameters, true ) || in_array( 'structured_outputs', $parameters, true ) ) {
			$options[] = new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) );
		}

		if ( in_array( 'structured_outputs', $parameters, true ) ) {
			$options[] = new SupportedOption( OptionEnum::outputSchema() );
		}

		if ( in_array( 'tools', $parameters, true ) ) {
			$options[] = new SupportedOption( OptionEnum::functionDeclarations() );
		}

		$input_modalities = $this->build_input_modalities( $modalities );

		$options[] = new SupportedOption( OptionEnum::inputModalities(), $input_modalities );

		// Text generation always asks for text output, so the model must declare it.
		$options[] = new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) );

		return $options;
	}

	/**
	 * Builds the supported input combinations from the modalities a model reports.
	 *
	 * Text is always accepted. Images and audio are the only other inputs the AI
	 * Client can send to an OpenAI-compatible server, so other modalities are ignored.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string> $reported The input modalities Magnitude reports.
	 * @return list<list<ModalityEnum>> The supported input combinations.
	 */
	private function build_input_modalities( array $reported ): array {
		$combinations = array( array( ModalityEnum::text() ) );
		$extras       = array();

		if ( in_array( 'image', $reported, true ) ) {
			$extras[] = ModalityEnum::image();
		}

		if ( in_array( 'audio', $reported, true ) ) {
			$extras[] = ModalityEnum::audio();
		}

		foreach ( $extras as $extra ) {
			$combinations[] = array( ModalityEnum::text(), $extra );
		}

		if ( count( $extras ) > 1 ) {
			$combinations[] = array_merge( array( ModalityEnum::text() ), $extras );
		}

		return $combinations;
	}

	/**
	 * Keeps only the string items of a value reported by the server.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value The reported value.
	 * @return list<string> The string items.
	 */
	private function get_string_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( $value, 'is_string' ) );
	}
}
