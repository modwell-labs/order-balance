<?php

namespace ModwellLabs\OrderBalance;

defined( 'ABSPATH' ) || exit;

final class Template {

	public static function render( string $name, array $args = array() ): void {
		wc_get_template( $name, $args, 'order-balance/', MODWELL_OBAL_DIR . 'templates/' );
	}

	public static function html( string $name, array $args = array() ): string {
		return wc_get_template_html( $name, $args, 'order-balance/', MODWELL_OBAL_DIR . 'templates/' );
	}
}
