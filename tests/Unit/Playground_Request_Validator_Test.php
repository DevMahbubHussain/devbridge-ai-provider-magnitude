<?php
/**
 * Tests for the playground request validator.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Playground\Playground_Request_Validator;
use PHPUnit\Framework\TestCase;

/**
 * Covers Playground_Request_Validator validation rules and bounds checking.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Playground\Playground_Request_Validator
 */
final class Playground_Request_Validator_Test extends TestCase {

	/**
	 * Validator instance.
	 *
	 * @var Playground_Request_Validator
	 */
	private $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new Playground_Request_Validator();
	}

	public function test_fails_when_model_is_missing(): void {
		$result = $this->validator->validate( array( 'prompt' => 'Hello' ) );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'missing_model', $result->get_error_code() );
	}

	public function test_fails_when_model_contains_invalid_characters(): void {
		$result = $this->validator->validate(
			array(
				'model'  => 'model with spaces',
				'prompt' => 'Hello',
			)
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'invalid_model_id', $result->get_error_code() );

		$xss_result = $this->validator->validate(
			array(
				'model'  => 'model<script>',
				'prompt' => 'Hello',
			)
		);
		$this->assertTrue( is_wp_error( $xss_result ) );
		$this->assertSame( 'invalid_model_id', $xss_result->get_error_code() );
	}

	public function test_fails_when_model_is_not_in_active_models_list(): void {
		$active_models = array( 'qwen2.5:7b', 'llama3.2:3b' );
		$result        = $this->validator->validate(
			array(
				'model'  => 'mistral:7b',
				'prompt' => 'Hello',
			),
			$active_models
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'model_not_found', $result->get_error_code() );
	}

	public function test_fails_when_single_turn_prompt_is_empty_or_whitespace(): void {
		$empty_result = $this->validator->validate(
			array(
				'model'  => 'qwen2.5:7b',
				'prompt' => '',
			)
		);
		$this->assertTrue( is_wp_error( $empty_result ) );
		$this->assertSame( 'missing_prompt', $empty_result->get_error_code() );

		$space_result = $this->validator->validate(
			array(
				'model'  => 'qwen2.5:7b',
				'prompt' => "   \n\t  ",
			)
		);
		$this->assertTrue( is_wp_error( $space_result ) );
		$this->assertSame( 'missing_prompt', $space_result->get_error_code() );
	}

	public function test_fails_when_prompt_exceeds_max_length(): void {
		$result = $this->validator->validate(
			array(
				'model'  => 'qwen2.5:7b',
				'prompt' => str_repeat( 'a', 10001 ),
			)
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'prompt_too_long', $result->get_error_code() );
	}

	public function test_fails_when_system_prompt_exceeds_max_length(): void {
		$result = $this->validator->validate(
			array(
				'model'         => 'qwen2.5:7b',
				'prompt'        => 'Hello',
				'system_prompt' => str_repeat( 's', 5001 ),
			)
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'system_prompt_too_long', $result->get_error_code() );
	}

	public function test_single_turn_formats_messages_correctly(): void {
		$result = $this->validator->validate(
			array(
				'model'         => 'qwen2.5:7b',
				'prompt'        => 'Explain quantum physics.',
				'system_prompt' => 'You are a physics professor.',
				'temperature'   => '0.5',
				'top_p'         => '0.9',
				'max_tokens'    => '2048',
				'stream'        => true,
				'bypass_cache'  => true,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'qwen2.5:7b', $result['model'] );
		$this->assertSame( 0.5, $result['temperature'] );
		$this->assertSame( 0.9, $result['top_p'] );
		$this->assertSame( 2048, $result['max_tokens'] );
		$this->assertTrue( $result['stream'] );
		$this->assertTrue( $result['bypass_cache'] );

		$this->assertCount( 2, $result['messages'] );
		$this->assertSame( 'system', $result['messages'][0]['role'] );
		$this->assertSame( 'You are a physics professor.', $result['messages'][0]['content'] );
		$this->assertSame( 'user', $result['messages'][1]['role'] );
		$this->assertSame( 'Explain quantum physics.', $result['messages'][1]['content'] );
	}

	public function test_multi_turn_fails_on_empty_messages(): void {
		$result = $this->validator->validate(
			array(
				'model'    => 'qwen2.5:7b',
				'messages' => array(),
			)
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'empty_messages', $result->get_error_code() );
	}

	public function test_multi_turn_fails_when_exceeding_max_turns(): void {
		$turns = array();
		for ( $i = 0; $i < 21; $i++ ) {
			$turns[] = array(
				'role'    => 0 === $i % 2 ? 'user' : 'assistant',
				'content' => 'Message ' . $i,
			);
		}

		$result = $this->validator->validate(
			array(
				'model'    => 'qwen2.5:7b',
				'messages' => $turns,
			)
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'exceeded_max_turns', $result->get_error_code() );
	}

	public function test_multi_turn_fails_on_invalid_message_structure_or_role(): void {
		$bad_structure = $this->validator->validate(
			array(
				'model'    => 'qwen2.5:7b',
				'messages' => array( array( 'role' => 'user' ) ), // Missing content.
			)
		);
		$this->assertTrue( is_wp_error( $bad_structure ) );
		$this->assertSame( 'invalid_message_format', $bad_structure->get_error_code() );

		$bad_role = $this->validator->validate(
			array(
				'model'    => 'qwen2.5:7b',
				'messages' => array(
					array(
						'role'    => 'admin', // Invalid role.
						'content' => 'Hello',
					),
				),
			)
		);
		$this->assertTrue( is_wp_error( $bad_role ) );
		$this->assertSame( 'invalid_message_role', $bad_role->get_error_code() );
	}

	public function test_multi_turn_fails_without_user_message(): void {
		$result = $this->validator->validate(
			array(
				'model'    => 'qwen2.5:7b',
				'messages' => array(
					array(
						'role'    => 'system',
						'content' => 'You are helpful.',
					),
					array(
						'role'    => 'assistant',
						'content' => 'How can I help?',
					),
				),
			)
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'missing_user_prompt', $result->get_error_code() );
	}

	public function test_multi_turn_valid_conversation_passes(): void {
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'Be concise.',
			),
			array(
				'role'    => 'user',
				'content' => 'Hi',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Hello.',
			),
			array(
				'role'    => 'user',
				'content' => 'What is 2+2?',
			),
		);

		$result = $this->validator->validate(
			array(
				'model'    => 'qwen2.5:7b',
				'messages' => $messages,
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 4, $result['messages'] );
		$this->assertSame( 'What is 2+2?', $result['messages'][3]['content'] );
		// Checks default parameters applied.
		$this->assertSame( 0.7, $result['temperature'] );
		$this->assertSame( 1.0, $result['top_p'] );
		$this->assertSame( 1024, $result['max_tokens'] );
		$this->assertFalse( $result['stream'] );
		$this->assertFalse( $result['bypass_cache'] );
	}
}
