<?php
/**
 * Playground REST controller.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Playground;

use DevBridgeAiProviderForMagnitude\Contracts\Connection_Inspector;
use DevBridgeAiProviderForMagnitude\Contracts\Hook_Registrar;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API controller handling AI Playground test generation requests.
 *
 * @since 0.2.0
 */
final class Playground_Rest_Controller implements Hook_Registrar {

	/**
	 * REST route namespace.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'devbridge-ai-provider-magnitude/v1';

	/**
	 * REST route path.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	public const ROUTE = '/playground/generate';

	/**
	 * Connection inspector.
	 *
	 * @since 0.2.0
	 *
	 * @var Connection_Inspector
	 */
	private $inspector;

	/**
	 * Playground invoker service.
	 *
	 * @since 0.2.0
	 *
	 * @var Playground_Invoker
	 */
	private $invoker;

	/**
	 * Request parameter validator.
	 *
	 * @since 0.2.0
	 *
	 * @var Playground_Request_Validator
	 */
	private $validator;

	/**
	 * Rate limiter service.
	 *
	 * @since 0.2.0
	 *
	 * @var Playground_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Response cache service.
	 *
	 * @since 0.2.0
	 *
	 * @var Playground_Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param Connection_Inspector         $inspector    Connection inspector.
	 * @param Playground_Invoker           $invoker      Playground invoker service.
	 * @param Playground_Request_Validator $validator    Request parameter validator.
	 * @param Playground_Rate_Limiter      $rate_limiter Rate limiter service.
	 * @param Playground_Cache             $cache        Response cache service.
	 */
	public function __construct(
		Connection_Inspector $inspector,
		Playground_Invoker $invoker,
		Playground_Request_Validator $validator,
		Playground_Rate_Limiter $rate_limiter,
		Playground_Cache $cache
	) {
		$this->inspector    = $inspector;
		$this->invoker      = $invoker;
		$this->validator    = $validator;
		$this->rate_limiter = $rate_limiter;
		$this->cache        = $cache;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.2.0
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the playground REST route.
	 *
	 * @since 0.2.0
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Checks whether the current user has permission to use the playground.
	 *
	 * @since 0.2.0
	 *
	 * @return bool True if permitted.
	 */
	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handles the incoming REST request.
	 *
	 * @since 0.2.0
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error.
	 */
	public function handle_request( WP_REST_Request $request ) {
		$params = $request->get_params();

		// Check rate limiting first.
		$rate_error = $this->rate_limiter->check();
		if ( null !== $rate_error ) {
			return $rate_error;
		}

		// Retrieve active model IDs to prevent running arbitrary or unloaded models.
		$active_ids = $this->get_active_model_ids();

		// Validate parameters.
		$validated = $this->validator->validate( $params, $active_ids );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$model        = $validated['model'];
		$messages     = $validated['messages'];
		$temperature  = $validated['temperature'];
		$top_p        = $validated['top_p'];
		$max_tokens   = $validated['max_tokens'];
		$stream       = $validated['stream'];
		$bypass_cache = $validated['bypass_cache'];

		// Check response cache.
		$cached_result = $this->cache->get( $model, $messages, $temperature, $top_p, $max_tokens, $bypass_cache );

		if ( null !== $cached_result ) {
			if ( $stream ) {
				$this->emit_stream_response( $cached_result, true );
				exit;
			}

			$data           = $cached_result->to_array();
			$data['cached'] = true;

			return new WP_REST_Response( $data, 200 );
		}

		// Cache miss: record usage in the rate limiter window.
		$this->rate_limiter->record();

		$options = array(
			'temperature' => $temperature,
			'top_p'       => $top_p,
			'max_tokens'  => $max_tokens,
		);

		// Handle streaming response.
		if ( $stream ) {
			$this->handle_stream_generation( $model, $messages, $options );
			exit;
		}

		// Handle single-shot generation.
		try {
			$result = $this->invoker->generate( $model, $messages, $options );
		} catch ( Throwable $error ) {
			return new WP_Error(
				'devbridge_magnitude_request_failed',
				__( 'Magnitude did not answer. Check that the app is running and that the model is loaded.', 'devbridge-ai-provider-magnitude' ),
				array( 'status' => 502 )
			);
		}

		// Store result in cache.
		$this->cache->set( $model, $messages, $temperature, $top_p, $max_tokens, $result );

		$data           = $result->to_array();
		$data['cached'] = false;

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Emits a Server-Sent Events (SSE) stream for real-time token delivery.
	 *
	 * @since 0.2.0
	 *
	 * @param string                                     $model    Model ID.
	 * @param list<array{role: string, content: string}> $messages Messages.
	 * @param array<string, mixed>                       $options  Sampling options.
	 */
	private function handle_stream_generation( string $model, array $messages, array $options ): void {
		$this->send_stream_headers();

		try {
			$result = $this->invoker->generate_stream(
				$model,
				$messages,
				$options,
				static function ( string $delta ): void {
					echo 'data: ' . wp_json_encode( array( 'delta' => $delta ) ) . "\n\n";
					if ( function_exists( 'flush' ) ) {
						flush();
					}
				}
			);
		} catch ( Throwable $error ) {
			echo 'data: ' . wp_json_encode(
				array(
					'error' => __( 'Magnitude did not answer. Check that the app is running.', 'devbridge-ai-provider-magnitude' ),
				)
			) . "\n\n";
			echo "data: [DONE]\n\n";
			return;
		}

		// Store in cache for future instant replays.
		$temp       = isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.7;
		$top_p      = isset( $options['top_p'] ) ? (float) $options['top_p'] : 1.0;
		$max_tokens = isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 1024;
		$this->cache->set( $model, $messages, $temp, $top_p, $max_tokens, $result );

		$this->emit_stream_final( $result, false );
	}

	/**
	 * Emits a pre-cached response over SSE.
	 *
	 * @since 0.2.0
	 *
	 * @param Playground_Result $result Cached result.
	 * @param bool              $cached Whether source was cache.
	 */
	private function emit_stream_response( Playground_Result $result, bool $cached ): void {
		$this->send_stream_headers();

		echo 'data: ' . wp_json_encode( array( 'delta' => $result->get_text() ) ) . "\n\n";
		if ( function_exists( 'flush' ) ) {
			flush();
		}

		$this->emit_stream_final( $result, $cached );
	}

	/**
	 * Emits the final SSE frame containing full telemetry.
	 *
	 * @since 0.2.0
	 *
	 * @param Playground_Result $result Result DTO.
	 * @param bool              $cached Whether cached.
	 */
	private function emit_stream_final( Playground_Result $result, bool $cached ): void {
		$data           = $result->to_array();
		$data['cached'] = $cached;
		$data['done']   = true;

		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
		echo "data: [DONE]\n\n";
		if ( function_exists( 'flush' ) ) {
			flush();
		}
	}

	/**
	 * Sends Server-Sent Events HTTP headers.
	 *
	 * @since 0.2.0
	 */
	private function send_stream_headers(): void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/event-stream; charset=UTF-8' );
			header( 'Cache-Control: no-cache, no-transform' );
			header( 'Connection: keep-alive' );
			header( 'X-Accel-Buffering: no' );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}

	/**
	 * Extracts currently active model IDs from the inspector.
	 *
	 * @since 0.2.0
	 *
	 * @return list<string> Active model IDs.
	 */
	private function get_active_model_ids(): array {
		$models = $this->inspector->inspect()->get_models();
		$ids    = array();

		foreach ( $models as $model ) {
			if ( isset( $model['id'] ) && is_string( $model['id'] ) ) {
				$ids[] = $model['id'];
			}
		}

		return $ids;
	}
}
