<?php
/**
 * Tests for the playground response cache.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Playground\Playground_Cache;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Result;
use PHPUnit\Framework\TestCase;

/**
 * Covers Playground_Cache transient storage, retrieval, cache keys, and bypass toggles.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Playground\Playground_Cache
 */
final class Playground_Cache_Test extends TestCase {

	/**
	 * Cache instance.
	 *
	 * @var Playground_Cache
	 */
	private $cache;

	protected function setUp(): void {
		parent::setUp();
		$this->cache = new Playground_Cache( 600 );
	}

	protected function tearDown(): void {
		$GLOBALS['devbridge_ai_magnitude_transients'] = array();
	}

	public function test_cache_miss_returns_null(): void {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
		);
		$this->assertNull( $this->cache->get( 'qwen2.5:7b', $messages, 0.7, 1.0, 1024 ) );
	}

	public function test_cache_set_and_get(): void {
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'Be concise.',
			),
			array(
				'role'    => 'user',
				'content' => 'Explain gravity.',
			),
		);

		$result = new Playground_Result(
			'Gravity is a fundamental interaction.',
			'qwen2.5:7b',
			'stop',
			1200.0,
			10,
			20,
			30
		);

		$stored = $this->cache->set( 'qwen2.5:7b', $messages, 0.7, 1.0, 1024, $result );
		$this->assertTrue( $stored );

		$cached = $this->cache->get( 'qwen2.5:7b', $messages, 0.7, 1.0, 1024 );
		$this->assertInstanceOf( Playground_Result::class, $cached );
		$this->assertSame( 'Gravity is a fundamental interaction.', $cached->get_text() );
		$this->assertSame( 'qwen2.5:7b', $cached->get_model() );
		$this->assertSame( 1200.0, $cached->get_latency_ms() );
		$this->assertSame( 10, $cached->get_prompt_tokens() );
		$this->assertSame( 20, $cached->get_completion_tokens() );
		$this->assertSame( 30, $cached->get_total_tokens() );
	}

	public function test_bypass_returns_null_even_when_cached(): void {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
		);
		$result   = new Playground_Result( 'World', 'model', 'stop', 500.0, 5, 5, 10 );

		$this->cache->set( 'model', $messages, 0.7, 1.0, 1024, $result );

		// Normal get hits cache.
		$this->assertNotNull( $this->cache->get( 'model', $messages, 0.7, 1.0, 1024, false ) );

		// Bypass = true ignores cache.
		$this->assertNull( $this->cache->get( 'model', $messages, 0.7, 1.0, 1024, true ) );
	}

	public function test_delete_removes_entry(): void {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Test',
			),
		);
		$result   = new Playground_Result( 'Output', 'model', 'stop', 500.0, 5, 5, 10 );

		$this->cache->set( 'model', $messages, 0.7, 1.0, 1024, $result );
		$this->assertNotNull( $this->cache->get( 'model', $messages, 0.7, 1.0, 1024 ) );

		$this->cache->delete( 'model', $messages, 0.7, 1.0, 1024 );
		$this->assertNull( $this->cache->get( 'model', $messages, 0.7, 1.0, 1024 ) );
	}

	public function test_different_parameters_produce_distinct_cache_keys(): void {
		$messages1 = array(
			array(
				'role'    => 'user',
				'content' => 'Query A',
			),
		);
		$messages2 = array(
			array(
				'role'    => 'user',
				'content' => 'Query B',
			),
		);

		$key1 = $this->cache->generate_key( 'model1', $messages1, 0.7, 1.0, 1024 );
		$key2 = $this->cache->generate_key( 'model1', $messages2, 0.7, 1.0, 1024 );
		$key3 = $this->cache->generate_key( 'model1', $messages1, 0.9, 1.0, 1024 );
		$key4 = $this->cache->generate_key( 'model2', $messages1, 0.7, 1.0, 1024 );

		$this->assertNotSame( $key1, $key2 );
		$this->assertNotSame( $key1, $key3 );
		$this->assertNotSame( $key1, $key4 );
	}
}
