<?php
/**
 * Tests for the model capability mapper.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Metadata\Model_Capability_Mapper;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\ModelRequirements;
use WordPress\AiClient\Providers\Models\DTO\RequiredOption;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Covers how Magnitude model entries map to AI Client options.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Metadata\Model_Capability_Mapper
 */
final class Model_Capability_Mapper_Test extends TestCase {

	/**
	 * Gets the option names of a mapped model.
	 *
	 * @param array<string, mixed> $entry A model entry.
	 * @return list<string> The option names.
	 */
	private function option_names( array $entry ): array {
		return array_map(
			static function ( SupportedOption $option ): string {
				return $option->getName()->value;
			},
			( new Model_Capability_Mapper() )->build_options( $entry )
		);
	}

	public function test_text_only_model_gets_base_options_only(): void {
		$names = $this->option_names( array( 'id' => 'plain' ) );

		$this->assertContains( 'maxTokens', $names );
		$this->assertContains( 'inputModalities', $names );
		$this->assertNotContains( 'functionDeclarations', $names );
		$this->assertNotContains( 'outputSchema', $names );
		$this->assertNotContains( 'outputMimeType', $names );
	}

	public function test_model_declares_text_output_so_text_generation_requirements_match(): void {
		$this->assertContains( 'outputModalities', $this->option_names( array( 'id' => 'plain' ) ) );
	}

	public function test_text_generation_requirements_are_met_by_mapped_model(): void {
		$mapper   = new Model_Capability_Mapper();
		$metadata = new ModelMetadata(
			'plain',
			'plain',
			array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			$mapper->build_options( array( 'id' => 'plain' ) )
		);

		$requirements = new ModelRequirements(
			array( CapabilityEnum::textGeneration() ),
			array(
				new RequiredOption( OptionEnum::outputModalities(), array( ModalityEnum::text() ) ),
				new RequiredOption( OptionEnum::inputModalities(), array( ModalityEnum::text() ) ),
				new RequiredOption( OptionEnum::maxTokens(), 600 ),
			)
		);

		$this->assertTrue( $requirements->areMetBy( $metadata ) );
	}

	public function test_reported_parameters_enable_matching_options(): void {
		$names = $this->option_names(
			array(
				'id'                   => 'full',
				'supported_parameters' => array( 'tools', 'structured_outputs', 'response_format' ),
			)
		);

		$this->assertContains( 'functionDeclarations', $names );
		$this->assertContains( 'outputSchema', $names );
		$this->assertContains( 'outputMimeType', $names );
	}

	public function test_response_format_alone_enables_json_mode_without_schema(): void {
		$names = $this->option_names(
			array(
				'id'                   => 'json',
				'supported_parameters' => array( 'response_format' ),
			)
		);

		$this->assertContains( 'outputMimeType', $names );
		$this->assertNotContains( 'outputSchema', $names );
	}

	public function test_image_modality_adds_a_vision_combination(): void {
		$options = ( new Model_Capability_Mapper() )->build_options(
			array(
				'id'           => 'vision',
				'architecture' => array( 'input_modalities' => array( 'text', 'image' ) ),
			)
		);

		$modalities = array_values(
			array_filter(
				$options,
				static function ( SupportedOption $option ): bool {
					return 'inputModalities' === $option->getName()->value;
				}
			)
		)[0];

		$this->assertCount( 2, $modalities->getSupportedValues() );
	}

	/**
	 * Checks whether a model with the given reported entry meets a set of required options.
	 *
	 * @param array<string, mixed>       $entry    The model entry.
	 * @param array<int, RequiredOption> $required The required options.
	 * @return bool True when the model meets every requirement.
	 */
	private function meets( array $entry, array $required ): bool {
		$mapper   = new Model_Capability_Mapper();
		$metadata = new ModelMetadata( 'm', 'm', array( CapabilityEnum::textGeneration() ), $mapper->build_options( $entry ) );

		return ( new ModelRequirements( array( CapabilityEnum::textGeneration() ), $required ) )->areMetBy( $metadata );
	}

	public function test_text_only_model_rejects_image_input(): void {
		$image_input = array( new RequiredOption( OptionEnum::inputModalities(), array( ModalityEnum::text(), ModalityEnum::image() ) ) );

		$this->assertFalse( $this->meets( array( 'id' => 'plain' ), $image_input ) );
	}

	public function test_vision_model_accepts_image_input_in_any_order(): void {
		$entry = array(
			'id'           => 'vision',
			'architecture' => array( 'input_modalities' => array( 'text', 'image' ) ),
		);

		$this->assertTrue( $this->meets( $entry, array( new RequiredOption( OptionEnum::inputModalities(), array( ModalityEnum::text(), ModalityEnum::image() ) ) ) ) );
		$this->assertTrue( $this->meets( $entry, array( new RequiredOption( OptionEnum::inputModalities(), array( ModalityEnum::image(), ModalityEnum::text() ) ) ) ) );
		$this->assertFalse( $this->meets( $entry, array( new RequiredOption( OptionEnum::inputModalities(), array( ModalityEnum::text(), ModalityEnum::audio() ) ) ) ) );
	}

	public function test_audio_and_combined_inputs_follow_what_the_model_reports(): void {
		$entry = array(
			'id'           => 'multi',
			'architecture' => array( 'input_modalities' => array( 'text', 'image', 'audio', 'video' ) ),
		);

		$this->assertTrue( $this->meets( $entry, array( new RequiredOption( OptionEnum::inputModalities(), array( ModalityEnum::text(), ModalityEnum::audio() ) ) ) ) );
		$this->assertTrue( $this->meets( $entry, array( new RequiredOption( OptionEnum::inputModalities(), array( ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::audio() ) ) ) ) );
		$this->assertFalse( $this->meets( $entry, array( new RequiredOption( OptionEnum::inputModalities(), array( ModalityEnum::text(), ModalityEnum::video() ) ) ) ), 'Video cannot be sent by the AI Client, so it is never advertised.' );
	}

	public function test_only_one_candidate_is_supported(): void {
		$this->assertTrue( $this->meets( array( 'id' => 'plain' ), array( new RequiredOption( OptionEnum::candidateCount(), 1 ) ) ) );
		$this->assertFalse( $this->meets( array( 'id' => 'plain' ), array( new RequiredOption( OptionEnum::candidateCount(), 2 ) ) ) );
	}

	public function test_malformed_server_values_are_ignored(): void {
		$names = $this->option_names(
			array(
				'id'                   => 'odd',
				'supported_parameters' => 'tools',
				'architecture'         => array( 'input_modalities' => array( 5, null ) ),
			)
		);

		$this->assertNotContains( 'functionDeclarations', $names );
	}

	public function test_non_array_architecture_is_ignored(): void {
		$names = $this->option_names(
			array(
				'id'           => 'odd',
				'architecture' => 'text',
			)
		);

		$this->assertContains( 'inputModalities', $names );
	}

	public function test_name_falls_back_to_id(): void {
		$mapper = new Model_Capability_Mapper();

		$this->assertSame(
			'Nice Name',
			$mapper->get_name(
				array(
					'id'   => 'a',
					'name' => 'Nice Name',
				)
			)
		);
		$this->assertSame(
			'a',
			$mapper->get_name(
				array(
					'id'   => 'a',
					'name' => '',
				)
			)
		);
		$this->assertSame( '', $mapper->get_name( array() ) );
	}
}
