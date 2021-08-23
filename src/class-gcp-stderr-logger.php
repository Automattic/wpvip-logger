<?php

namespace Automattic\WPVIP\Logging;

class Gcp_Stderr_Logger extends \Psr\Log\AbstractLogger {
	private $stderr;

	public function __construct() {
		if ( defined( 'STDERR' ) ) {
			$this->stderr = STDERR;
		} else {
			$this->stderr = fopen( 'php://stderr', 'w' );
		}
	}

	public function log( $level, $message, array $context = array() ) {
		$request_context = $this->build_request_context();

		$output = array_merge( [
			'severity' => $level,
			'message' => $message,
		], $context, $request_context );

		fwrite( $this->stderr, json_encode( $output ) . PHP_EOL );
	}

	private function build_request_context() {
		if ( 'cli' === PHP_SAPI ) {
			return $this->build_cli_context();
		}
			
		return $this->build_http_context();
	}

	private function build_cli_context() {
		if ( ! is_array( $GLOBALS['argv'] ) ) {
			return [];
		}

		$cli_command = '$ ' . join( ' ', $GLOBALS['argv'] );
		$hash = md5( $cli_command );

		return [
			'operation' => [
				'id' => $hash,
				'producer' => substr( $cli_command, 0, 30 ),
			],
		];
	}

	private function build_http_context() {
		$request_url = sprintf(
			'%s://%s%s',
			isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http',
			$_SERVER['HTTP_HOST'] ?? 'unknown-host',
			$_SERVER['REQUEST_URI'] ?? '',
		);

		return [
			'httpRequest' => [
				'requestMethod' => $_SERVER['REQUEST_METHOD'] ?? '',
				'requestUrl' => $request_url,
				'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
				'remoteIp' => $_SERVER['REMOTE_ADDR'] ?? '',
				'referer' => $_SERVER['HTTP_REFERER'] ?? '',
			],
		];
	}
}
