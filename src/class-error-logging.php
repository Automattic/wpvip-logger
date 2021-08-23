<?php

/**
 * A pretty generic error/shutdown/exception handler override and accepts a Psr3 Logger.
 *
 * It lets us customize the behavior of the errors that get logged.
 *
 * Borrows elements from WP.com / VIP Go's Error handler, with some inspiration
 * from Google's ErrorReporting lib (https://github.com/googleapis/google-cloud-php/).
 */

namespace Automattic\WPVIP\Logging;

use Psr\Log\LogLevel;

class Error_Logging {
	private static $logger;

	public static function init( \Psr\Log\LoggerInterface $logger ) {
		// TODO: default to basic stderr logger?

		self::$logger = $logger;

		register_shutdown_function( [ __CLASS__, 'shutdown_handler' ] );
		set_exception_handler( [ __CLASS__, 'exception_handler' ] );
		set_error_handler( [ __CLASS__, 'error_handler' ] );
	}

	public static function get() {
		return self::$logger;
	}

	public static function shutdown_handler() {
		$last_error = error_get_last();
		if ( ! $last_error ) {
			return;
		}

		$error_type = $last_error['type'];

		if ( ! self::is_fatal_error( $error_type ) ) {
			return;
		}

		$message = $last_error['message'];
		$file = $last_error['file'];
		$line = $last_error['line'];

		$formatted_message = sprintf(
			'%s: %s in %s on line %d',
			self::get_error_label_from_type( $error_type ),
			$message,
			$file,
			$line
		);

		self::log_error( self::get_log_level_from_type( $error_type ), $formatted_message, $file, $line );
	}

	public static function exception_handler( $exception ) {
		$message = sprintf( 'PHP Exception: %s', $exception->getMessage() );

		self::log_error( LogLevel::ERROR, $message, $exception->getFile(), $exception->getLine() );
	}

	public static function error_handler( $type, $message, $file, $line ) {
		if ( ! self::$logger ) {
			return false;
		}

		if ( ! is_int( $type ) ) {
			return true;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
		if ( ! ( $type & error_reporting() ) ) {
			return true;
		}

		$formatted_message =  sprintf(
			'%s: %s in %s on line %d',
			self::get_error_label_from_type( $type ),
			$message,
			$file,
			$line
		);

		self::log_error( self::get_log_level_from_type( $type ), $formatted_message, $file, $line );

		// TODO: die?

		return true;
	}

	private static function log_error( $log_level, $message, $file, $line, $function = '' ) {
		// TODO: add backtrace

		$context = [
			// TODO: This is GCP-specific; move to logger or separate class
			'sourceLocation' => [
				'file' => $file,
				'line' => $line,
				'function' => $function,
			],
		];

		self::$logger->log(
			$log_level,
			$message,
			$context
		);
	}

	private static function get_log_level_from_type( $type ) {
		switch ( $type ) {
			case E_PARSE:
				return LogLevel::CRITICAL;

			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
				return LogLevel::ERROR;

			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				return LogLevel::WARNING;

			case E_STRICT:
				return LogLevel::DEBUG;

			case E_NOTICE:
			case E_USER_NOTICE:
			default:
				return LogLevel::NOTICE;
		}
	}

	private static function get_error_label_from_type( $type ) {
		switch ( $type ) {
			case E_CORE_ERROR:
				return 'PHP Core error';

			case E_COMPILE_ERROR:
				return 'PHP Compile error';

			case E_PARSE:
				return 'PHP Parse error';

			case E_ERROR:
			case E_USER_ERROR:
				return 'PHP Fatal error';

			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				return 'PHP Warning';

			case E_STRICT:
				return 'PHP Strict standards';
			
			case E_RECOVERABLE_ERROR:
				return 'PHP Catchable fatal error';

			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return 'PHP Deprecated';

			case E_NOTICE:
			case E_USER_NOTICE:
			default:
				return 'PHP Notice';
		}
	}

	private static function is_fatal_error( $error_type ) {
		switch ( $error_type ) {
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_PARSE:
			case E_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
				return true;
		}

		return false;
	}
}
