<?php
/**
 * Magnitude provider for the AI Client.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Provider;

use DevBridgeAiProviderForMagnitude\Host\Host_Resolver;
use DevBridgeAiProviderForMagnitude\Metadata\MagnitudeModelMetadataDirectory;
use DevBridgeAiProviderForMagnitude\Models\MagnitudeTextGenerationModel;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider class for a Magnitude server.
 *
 * The AI Client creates providers through static factory methods, so the
 * server address is read from a stateless resolver instead of an instance.
 *
 * @since 0.1.0
 */
class MagnitudeProvider extends AbstractApiProvider {

	/**
	 * Provider ID used in the AI Client registry.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	public const ID = 'magnitude';

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function baseUrl(): string {
		return ( new Host_Resolver() )->get_host();
	}

	/**
	 * Creates the model class for a model's capabilities.
	 *
	 * @since 0.1.0
	 *
	 * @param ModelMetadata    $model_metadata    The model metadata.
	 * @param ProviderMetadata $provider_metadata The provider metadata.
	 * @return ModelInterface The model.
	 *
	 * @throws RuntimeException When the model has no supported capability.
	 */
	protected static function createModel( ModelMetadata $model_metadata, ProviderMetadata $provider_metadata ): ModelInterface {
		foreach ( $model_metadata->getSupportedCapabilities() as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new MagnitudeTextGenerationModel( $model_metadata, $provider_metadata );
			}
		}

		throw new RuntimeException( 'Unsupported model capabilities for the Magnitude provider.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$arguments = array(
			self::ID,
			'Magnitude',
			ProviderTypeEnum::server(),
			null,
			RequestAuthenticationMethod::apiKey(),
		);

		// Provider descriptions need AI Client 1.2.0 or later.
		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			$arguments[] = __( 'Text generation with tool calling and image input on supported models, running locally on your own computer with Magnitude.', 'devbridge-ai-provider-magnitude' );
		}

		return new ProviderMetadata( ...$arguments );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new ListModelsApiBasedProviderAvailability( static::modelMetadataDirectory() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new MagnitudeModelMetadataDirectory();
	}
}
