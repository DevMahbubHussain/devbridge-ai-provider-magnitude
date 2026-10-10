<?php
/**
 * Registry connection inspector.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Connection;

use DevBridgeAiProviderForMagnitude\Contracts\Connection_Inspector;
use DevBridgeAiProviderForMagnitude\Provider\MagnitudeProvider;
use Throwable;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Contracts\CachesDataInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inspects Magnitude through the provider registered with the AI Client.
 *
 * @since 0.1.0
 */
final class Registry_Connection_Inspector implements Connection_Inspector {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	public function inspect(): Connection_Status {
		if ( ! class_exists( AiClient::class ) || ! AiClient::defaultRegistry()->hasProvider( MagnitudeProvider::ID ) ) {
			return new Connection_Status( false, array(), __( 'The Magnitude provider is not registered with the WordPress AI Client.', 'devbridge-ai-provider-magnitude' ) );
		}

		try {
			$metadata = MagnitudeProvider::modelMetadataDirectory()->listModelMetadata();
		} catch ( Throwable $error ) {
			return new Connection_Status( false, array(), __( 'Magnitude did not answer. Check that the app is running and that the address is correct.', 'devbridge-ai-provider-magnitude' ) );
		}

		$models = array();

		foreach ( $metadata as $model ) {
			$models[] = array(
				'id'     => $model->getId(),
				'name'   => $model->getName(),
				'badges' => $this->get_badges( $model ),
			);
		}

		return new Connection_Status( true, $models );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	public function refresh(): void {
		$directory = MagnitudeProvider::modelMetadataDirectory();

		if ( $directory instanceof CachesDataInterface ) {
			$directory->invalidateCaches();
		}
	}

	/**
	 * Builds the capability labels of a model.
	 *
	 * @since 0.1.0
	 *
	 * @param ModelMetadata $model The model metadata.
	 * @return list<string> The labels, starting with text generation.
	 */
	private function get_badges( ModelMetadata $model ): array {
		$badges = array( __( 'Text generation', 'devbridge-ai-provider-magnitude' ) );

		foreach ( $model->getSupportedOptions() as $option ) {
			$name = $option->getName()->value;

			if ( 'functionDeclarations' === $name ) {
				$badges[] = __( 'Tools', 'devbridge-ai-provider-magnitude' );
			}

			if ( 'outputSchema' === $name ) {
				$badges[] = __( 'Structured output', 'devbridge-ai-provider-magnitude' );
			}

			if ( 'inputModalities' === $name && $this->accepts_input( $option->getSupportedValues(), 'image' ) ) {
				$badges[] = __( 'Vision', 'devbridge-ai-provider-magnitude' );
			}

			if ( 'inputModalities' === $name && $this->accepts_input( $option->getSupportedValues(), 'audio' ) ) {
				$badges[] = __( 'Audio input', 'devbridge-ai-provider-magnitude' );
			}
		}

		return $badges;
	}

	/**
	 * Checks whether any supported input combination includes a modality.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, mixed>|null $combinations The supported input modality combinations.
	 * @param string                 $modality     The modality value, such as "image".
	 * @return bool True when the modality is accepted.
	 */
	private function accepts_input( ?array $combinations, string $modality ): bool {
		foreach ( (array) $combinations as $combination ) {
			foreach ( (array) $combination as $item ) {
				if ( is_object( $item ) && isset( $item->value ) && $modality === $item->value ) {
					return true;
				}
			}
		}

		return false;
	}
}
