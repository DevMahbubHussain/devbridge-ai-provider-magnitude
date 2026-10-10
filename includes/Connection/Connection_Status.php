<?php
/**
 * Connection status.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Connection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes whether Magnitude answered and which models it listed.
 *
 * @since 0.1.0
 */
final class Connection_Status {

	/**
	 * Whether the server answered the model list request.
	 *
	 * @since 0.1.0
	 *
	 * @var bool
	 */
	private $connected;

	/**
	 * Models the server listed.
	 *
	 * @since 0.1.0
	 *
	 * @var list<array{id: string, name: string, badges: list<string>}>
	 */
	private $models;

	/**
	 * A message for the user when the server did not answer.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param bool                                                        $connected Whether the server answered.
	 * @param list<array{id: string, name: string, badges: list<string>}> $models    Optional. Models the server listed. Default empty.
	 * @param string                                                      $message   Optional. A message when the server did not answer. Default empty.
	 */
	public function __construct( bool $connected, array $models = array(), string $message = '' ) {
		$this->connected = $connected;
		$this->models    = $models;
		$this->message   = $message;
	}

	/**
	 * Checks whether the server answered.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True when the server answered.
	 */
	public function is_connected(): bool {
		return $this->connected;
	}

	/**
	 * Gets the models the server listed.
	 *
	 * @since 0.1.0
	 *
	 * @return list<array{id: string, name: string, badges: list<string>}> The models.
	 */
	public function get_models(): array {
		return $this->models;
	}

	/**
	 * Gets the message shown when the server did not answer.
	 *
	 * @since 0.1.0
	 *
	 * @return string The message, or an empty string.
	 */
	public function get_message(): string {
		return $this->message;
	}
}
