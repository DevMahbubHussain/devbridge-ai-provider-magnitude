<?php
/**
 * Magnitude model metadata directory.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Metadata;

use DevBridgeAiProviderForMagnitude\Traits\Builds_Api_Url;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists the models a Magnitude server exposes through GET /v1/models.
 *
 * Magnitude lists only models that are downloaded and running.
 *
 * @since 0.1.0
 */
class MagnitudeModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {
	use Builds_Api_Url;

	/**
	 * Maps model entries to supported options.
	 *
	 * @since 0.1.0
	 *
	 * @var Model_Capability_Mapper
	 */
	private $mapper;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Model_Capability_Mapper|null $mapper Optional. The capability mapper. Default a new mapper.
	 */
	public function __construct( ?Model_Capability_Mapper $mapper = null ) {
		$this->mapper = $mapper ?? new Model_Capability_Mapper();
	}

	/**
	 * Creates a request for the models endpoint.
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
		return new Request( $method, $this->build_api_url( $path ), $headers, $data );
	}

	/**
	 * Converts the models response into model metadata.
	 *
	 * Entries without a string ID are skipped.
	 *
	 * @since 0.1.0
	 *
	 * @param Response $response The models endpoint response.
	 * @return list<ModelMetadata> The model metadata.
	 *
	 * @throws ResponseException When the response has no model list.
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		$data = $response->getData();

		if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			throw ResponseException::fromMissingData( 'Magnitude', 'data' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
		}

		$models = array();

		foreach ( $data['data'] as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) || ! is_string( $entry['id'] ) ) {
				continue;
			}

			$models[] = new ModelMetadata(
				$entry['id'],
				$this->mapper->get_name( $entry ),
				array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
				$this->mapper->build_options( $entry )
			);
		}

		return $models;
	}
}
