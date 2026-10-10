<?php
/**
 * Magnitude host resolver.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Host;

use DevBridgeAiProviderForMagnitude\Contracts\Host_Provider;
use DevBridgeAiProviderForMagnitude\Settings\Host_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the Magnitude server address.
 *
 * The address comes from the DEVBRIDGE_AI_PROVIDER_MAGNITUDE_HOST constant when set,
 * then from the saved setting, then from the default. It then passes through a
 * filter. A value that is not a plain http or https address is replaced with the
 * default, so a bad value never reaches a request.
 *
 * @since 0.1.0
 */
final class Host_Resolver implements Host_Provider {

	/**
	 * Name of the constant that fixes the address in code.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	public const CONSTANT_NAME = 'DEVBRIDGE_AI_PROVIDER_MAGNITUDE_HOST';

	/**
	 * Name of the option that stores the address saved on the settings screen.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'devbridge_ai_provider_magnitude_host';

	/**
	 * Default address of Magnitude's OpenAI-compatible API on the same computer.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	public const DEFAULT_HOST = 'http://127.0.0.1:10100/inference';

	/**
	 * Checks and normalizes addresses.
	 *
	 * @since 0.1.0
	 *
	 * @var Host_Validator
	 */
	private $validator;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Host_Validator|null $validator Optional. The address validator. Default a new validator.
	 */
	public function __construct( ?Host_Validator $validator = null ) {
		$this->validator = $validator ?? new Host_Validator();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	public function get_host(): string {
		$host = $this->is_locked() ? constant( self::CONSTANT_NAME ) : get_option( self::OPTION_NAME, '' );

		if ( ! is_string( $host ) || '' === $host ) {
			$host = self::DEFAULT_HOST;
		}

		/**
		 * Filters the Magnitude server base URL.
		 *
		 * The value must be an http or https URL without credentials. Invalid
		 * values are replaced with the default address.
		 *
		 * @since 0.1.0
		 *
		 * @param mixed $host The server base URL.
		 */
		$host = apply_filters( 'devbridge_ai_provider_magnitude_host', $host );

		$valid = $this->validator->validate( $host );

		return '' === $valid ? self::DEFAULT_HOST : $valid;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	public function is_locked(): bool {
		return defined( self::CONSTANT_NAME );
	}
}
