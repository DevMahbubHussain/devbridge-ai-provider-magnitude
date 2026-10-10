<?php
/**
 * Local host allowance.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Http;

use DevBridgeAiProviderForMagnitude\Contracts\Hook_Registrar;
use DevBridgeAiProviderForMagnitude\Contracts\Host_Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets WordPress send requests to the configured Magnitude server.
 *
 * WordPress blocks requests to local and private addresses by default. Only the
 * configured host and port are allowed, nothing else.
 *
 * @since 0.1.0
 */
final class Local_Host_Allowance implements Hook_Registrar {

	/**
	 * Source of the server address.
	 *
	 * @since 0.1.0
	 *
	 * @var Host_Provider
	 */
	private $host_provider;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Host_Provider $host_provider Source of the server address.
	 */
	public function __construct( Host_Provider $host_provider ) {
		$this->host_provider = $host_provider;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	public function register(): void {
		add_filter( 'http_request_host_is_external', array( $this, 'allow_host' ), 10, 2 );
		add_filter( 'http_allowed_safe_ports', array( $this, 'allow_port' ), 10, 2 );
	}

	/**
	 * Allows requests to the configured host.
	 *
	 * Host names are not case sensitive, so they are compared in lower case.
	 *
	 * @since 0.1.0
	 *
	 * @param bool   $is_external Whether the request is allowed as an external request.
	 * @param string $host        The request host.
	 * @return bool Whether the request is allowed.
	 */
	public function allow_host( $is_external, $host ): bool {
		if ( $this->is_configured_host( $host ) ) {
			return true;
		}

		return (bool) $is_external;
	}

	/**
	 * Allows the port of the configured host, for requests to that host only.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int> $ports The allowed safe ports.
	 * @param string     $host  The request host.
	 * @return array<int> The allowed safe ports, including the configured one for the configured host.
	 */
	public function allow_port( $ports, $host = '' ): array {
		$ports = is_array( $ports ) ? $ports : array();
		$port  = wp_parse_url( $this->host_provider->get_host(), PHP_URL_PORT );

		if ( is_int( $port ) && $this->is_configured_host( $host ) ) {
			$ports[] = $port;
		}

		return $ports;
	}

	/**
	 * Checks whether a request host is the configured Magnitude host.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $host The request host.
	 * @return bool True when the host matches, ignoring case.
	 */
	private function is_configured_host( $host ): bool {
		$configured = wp_parse_url( $this->host_provider->get_host(), PHP_URL_HOST );

		return is_string( $configured ) && '' !== $configured && is_string( $host ) && strtolower( $configured ) === strtolower( $host );
	}
}
