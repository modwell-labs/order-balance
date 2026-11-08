<?php
/**
 * Plugin Name: Order Balance
 * Description: Collect the difference when a paid order changes, refund overpayments, and absorb rounding differences.
 * Version: 0.1.0
 * Author: Modwell Labs
 * Author URI: https://modwelllabs.com
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.2
 * Text Domain: order-balance
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'ORDER_BALANCE_VERSION', '0.1.0' );
define( 'ORDER_BALANCE_FILE', __FILE__ );
define( 'ORDER_BALANCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORDER_BALANCE_URL', plugin_dir_url( __FILE__ ) );

require_once ORDER_BALANCE_DIR . 'src/Autoloader.php';
\ModwellLabs\OrderBalance\Autoloader::register( 'ModwellLabs\\OrderBalance\\', ORDER_BALANCE_DIR . 'src/' );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ORDER_BALANCE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ORDER_BALANCE_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Order Balance requires WooCommerce.', 'order-balance' ) . '</p></div>';
				}
			);
			return;
		}
		\ModwellLabs\OrderBalance\Plugin::instance()->boot();
	},
	20
);
