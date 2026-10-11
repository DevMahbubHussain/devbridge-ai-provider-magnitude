<?php
/**
 * Tests for the playground REST controller.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Tests\Unit;

use DevBridgeAiProviderForMagnitude\Connection\Connection_Status;
use DevBridgeAiProviderForMagnitude\Contracts\Connection_Inspector;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Cache;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Invoker;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Rate_Limiter;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Request_Validator;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Rest_Controller;
use DevBridgeAiProviderForMagnitude\Playground\Playground_Result;
use Exception;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * Covers Playground_Rest_Controller routing, permissions, parameter validation, rate limiting, and response handling.
 *
 * @covers \DevBridgeAiProviderForMagnitude\Playground\Playground_Rest_Controller
 */
final class Playground_Rest_Controller_Test extends TestCase {

	/**
	 * Tear down test state.
	 */
	protected function tearDown(): void {
		$GLOBALS['devbridge_ai_magnitude_transients']         = array();
		$GLOBALS['devbridge_ai_magnitude_can_manage_options'] = true;
	}

	/**
	 * Creates a mock inspector reporting running models.
	 *
	 * @param array<string> $model_ids Model IDs.
	 * @return Connection_Inspector Inspector stand-in.
	 */
	private function create_inspector( array $model_ids = array( 'qwen2.5:7b', 'llama3.2:3b' ) ): Connection_Inspector {
		return new class( $model_ids ) implements Connection_Inspector {
			/**
			 * Model IDs list.
			 *
			 * @var array<string>
			 */
			private $model_ids;

			public function __construct( array $model_ids ) {
				$this->model_ids = $model_ids;
			}

			public function inspect(): Connection_Status {
				$models = array();
				foreach ( $this->model_ids as $id ) {
					$models[] = array(
						'id'     => $id,
						'name'   => $id,
						'badges' => array( 'Text generation' ),
					);
				}
				return new Connection_Status( true, $models );
			}

			public function refresh(): void {}
		};
	}

	/**
	 * Creates a mock invoker.
	 *
	 * @param Playground_Result|null $result Result to return or null to throw.
	 * @return Playground_Invoker Invoker stand-in.
	 */
	private function create_invoker( ?Playground_Result $result = null ): Playground_Invoker {
		return new class( $result ) implements Playground_Invoker {
			/**
			 * Result object.
			 *
			 * @var Playground_Result|null
			 */
			private $result;

			public function __construct( ?Playground_Result $result ) {
				$this->result = $result;
			}

			public function generate( string $model_id, array $messages, array $options = array() ): Playground_Result {
				if ( null === $this->result ) {
					throw new Exception( 'Inference engine connection refused.' );
				}
				return $this->result;
			}

			public function generate_stream( string $model_id, array $messages, array $options, callable $chunk_callback ): Playground_Result {
				if ( null === $this->result ) {
					throw new Exception( 'Stream failure.' );
				}
				$chunk_callback( $this->result->get_text() );
				return $this->result;
			}
		};
	}

	public function test_permissions_check_enforces_manage_options(): void {
		$controller = new Playground_Rest_Controller(
			$this->create_inspector(),
			$this->create_invoker(),
			new Playground_Request_Validator(),
			new Playground_Rate_Limiter(),
			new Playground_Cache()
		);

		$GLOBALS['devbridge_ai_magnitude_can_manage_options'] = true;
		$this->assertTrue( $controller->check_permissions() );

		$GLOBALS['devbridge_ai_magnitude_can_manage_options'] = false;
		$this->assertFalse( $controller->check_permissions() );
	}

	public function test_route_registration(): void {
		$controller = new Playground_Rest_Controller(
			$this->create_inspector(),
			$this->create_invoker(),
			new Playground_Request_Validator(),
			new Playground_Rate_Limiter(),
			new Playground_Cache()
		);

		$controller->register_routes();

		$route_key = Playground_Rest_Controller::REST_NAMESPACE . Playground_Rest_Controller::ROUTE;
		$this->assertArrayHasKey( $route_key, $GLOBALS['devbridge_ai_magnitude_rest_routes'] );
		$this->assertSame( 'POST', $GLOBALS['devbridge_ai_magnitude_rest_routes'][ $route_key ]['methods'] );
	}

	public function test_fails_on_invalid_parameters(): void {
		$controller = new Playground_Rest_Controller(
			$this->create_inspector(),
			$this->create_invoker(),
			new Playground_Request_Validator(),
			new Playground_Rate_Limiter(),
			new Playground_Cache()
		);

		$request = new WP_REST_Request();
		$request->set_param( 'model', '' ); // Missing model.
		$request->set_param( 'prompt', 'Hello' );

		$response = $controller->handle_request( $request );
		$this->assertTrue( is_wp_error( $response ) );
		$this->assertSame( 'missing_model', $response->get_error_code() );
	}

	public function test_single_shot_generation_success(): void {
		$expected_result = new Playground_Result(
			'WordPress is an open source CMS.',
			'qwen2.5:7b',
			'stop',
			1150.0,
			10,
			25,
			35
		);

		$controller = new Playground_Rest_Controller(
			$this->create_inspector(),
			$this->create_invoker( $expected_result ),
			new Playground_Request_Validator(),
			new Playground_Rate_Limiter( 20, 60, 0 ),
			new Playground_Cache()
		);

		$request = new WP_REST_Request();
		$request->set_param( 'model', 'qwen2.5:7b' );
		$request->set_param( 'prompt', 'What is WordPress?' );

		$response = $controller->handle_request( $request );
		$this->assertFalse( is_wp_error( $response ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'WordPress is an open source CMS.', $data['text'] );
		$this->assertSame( 'qwen2.5:7b', $data['model'] );
		$this->assertFalse( $data['cached'] );

		// Second request with same parameters should return cached response.
		$cached_response = $controller->handle_request( $request );
		$this->assertFalse( is_wp_error( $cached_response ) );
		$cached_data = $cached_response->get_data();
		$this->assertTrue( $cached_data['cached'] );
	}

	public function test_invoker_failure_returns_502_wp_error(): void {
		$controller = new Playground_Rest_Controller(
			$this->create_inspector(),
			$this->create_invoker( null ), // Invoker will throw.
			new Playground_Request_Validator(),
			new Playground_Rate_Limiter( 20, 60, 0 ),
			new Playground_Cache()
		);

		$request = new WP_REST_Request();
		$request->set_param( 'model', 'qwen2.5:7b' );
		$request->set_param( 'prompt', 'Test prompt' );

		$response = $controller->handle_request( $request );
		$this->assertTrue( is_wp_error( $response ) );
		$this->assertSame( 'devbridge_magnitude_request_failed', $response->get_error_code() );
		$this->assertSame( 502, $response->get_error_data()['status'] );
	}

	public function test_rate_limiter_throttles_excessive_requests(): void {
		$result = new Playground_Result( 'OK', 'qwen2.5:7b', 'stop', 100.0, 5, 5, 10 );

		$limiter = new Playground_Rate_Limiter( 1, 60, 0 );
		$cache   = new Playground_Cache();

		$controller = new Playground_Rest_Controller(
			$this->create_inspector(),
			$this->create_invoker( $result ),
			new Playground_Request_Validator(),
			$limiter,
			$cache
		);

		$request = new WP_REST_Request();
		$request->set_param( 'model', 'qwen2.5:7b' );
		$request->set_param( 'prompt', 'Prompt 1' );
		$request->set_param( 'bypass_cache', true );

		// First request passes.
		$res1 = $controller->handle_request( $request );
		$this->assertFalse( is_wp_error( $res1 ) );

		// Second request hits rate limit (limit is 1).
		$request->set_param( 'prompt', 'Prompt 2' );
		$res2 = $controller->handle_request( $request );
		$this->assertTrue( is_wp_error( $res2 ) );
		$this->assertSame( 'rate_limit_exceeded', $res2->get_error_code() );
		$this->assertSame( 429, $res2->get_error_data()['status'] );
	}
}
