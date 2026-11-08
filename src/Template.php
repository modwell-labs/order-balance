<?php

namespace ModwellLabs\OrderBalance;

defined( 'ABSPATH' ) || exit;

final class Template {

	public static function render( string $name, array $args = array(), string $base = '' ): void {
		wc_get_template( $name, $args, 'order-balance/', $base ? $base : ORDER_BALANCE_DIR . 'templates/' );
	}

	public static function html( string $name, array $args = array(), string $base = '' ): string {
		return wc_get_template_html( $name, $args, 'order-balance/', $base ? $base : ORDER_BALANCE_DIR . 'templates/' );
	}
}
