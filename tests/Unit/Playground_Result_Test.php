<?php
/**
 * Tests for the playground result DTO.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Playground\Playground_Result;
use PHPUnit\Framework\TestCase;

/**
 * Covers the Playground_Result DTO and its calculations.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Playground\Playground_Result
 */
final class Playground_Result_Test extends TestCase {

	public function test_getters_return_constructor_values(): void {
		$result = new Playground_Result(
			'Hello world',
			'llama3.2:3b',
			'stop',
			1250.5,
			10,
			25,
			35
		);

		$this->assertSame( 'Hello world', $result->get_text() );
		$this->assertSame( 'llama3.2:3b', $result->get_model() );
		$this->assertSame( 'stop', $result->get_finish_reason() );
		$this->assertSame( 1250.5, $result->get_latency_ms() );
		$this->assertSame( 10, $result->get_prompt_tokens() );
		$this->assertSame( 25, $result->get_completion_tokens() );
		$this->assertSame( 35, $result->get_total_tokens() );
	}

	public function test_tokens_per_second_calculation(): void {
		// 50 tokens generated in 1000ms = 50.0 tok/s.
		$result = new Playground_Result(
			'Sample output',
			'qwen2.5:7b',
			'stop',
			1000.0,
			15,
			50,
			65
		);

		$this->assertSame( 50.0, $result->get_tokens_per_second() );
	}

	public function test_tokens_per_second_with_zero_tokens_or_zero_latency(): void {
		$zero_tokens = new Playground_Result(
			'Output',
			'test-model',
			'stop',
			500.0,
			10,
			0,
			10
		);
		$this->assertSame( 0.0, $zero_tokens->get_tokens_per_second() );

		$zero_latency = new Playground_Result(
			'Output',
			'test-model',
			'stop',
			0.0,
			10,
			25,
			35
		);
		$this->assertSame( 0.0, $zero_latency->get_tokens_per_second() );
	}

	public function test_negative_values_are_clamped_to_zero(): void {
		$result = new Playground_Result(
			'Text',
			'model',
			'stop',
			-100.0,
			-5,
			-10,
			-15
		);

		$this->assertSame( 0.0, $result->get_latency_ms() );
		$this->assertSame( 0, $result->get_prompt_tokens() );
		$this->assertSame( 0, $result->get_completion_tokens() );
		$this->assertSame( 0, $result->get_total_tokens() );
		$this->assertSame( 0.0, $result->get_tokens_per_second() );
	}

	public function test_to_array_serializes_telemetry(): void {
		$result = new Playground_Result(
			'Generated response',
			'mistral:7b',
			'stop',
			2000.0,
			20,
			80,
			100
		);

		$expected = array(
			'text'              => 'Generated response',
			'model'             => 'mistral:7b',
			'finish_reason'     => 'stop',
			'latency_ms'        => 2000.0,
			'tokens'            => array(
				'prompt'     => 20,
				'completion' => 80,
				'total'      => 100,
			),
			'tokens_per_second' => 40.0,
		);

		$this->assertSame( $expected, $result->to_array() );
	}
}
