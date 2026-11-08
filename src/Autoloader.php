<?php

namespace ModwellLabs\OrderBalance;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	public static function register( string $prefix, string $dir ): void {
		spl_autoload_register(
			static function ( string $class ) use ( $prefix, $dir ): void {
				if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
					return;
				}
				$file = $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
				if ( is_file( $file ) ) {
					require $file;
				}
			}
		);
	}
}
