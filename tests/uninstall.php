<?php
/**
 * Run: wp eval-file wp-content/plugins/modwell-order-balance-for-woocommerce/tests/uninstall.php
 *
 * Destructive on the test site: runs uninstall.php twice (keep data, then delete all data).
 */

use ModwellLabs\OrderBalance\Payments\BalanceCalculator;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Payments\PaymentType;
use ModwellLabs\OrderBalance\Plugin;

$_SERVER['HTTP_HOST'] = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
add_filter( 'pre_wp_mail', '__return_true' );

$obal_failures = 0;
$check         = static function ( string $label, bool $ok, $detail = '' ) use ( &$obal_failures ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok || '' === $detail ? '' : ' -> ' . wp_json_encode( $detail ) ) . "\n";
	if ( ! $ok ) {
		++$obal_failures;
	}
};

$plugin = Plugin::instance();

// Run on default settings whatever the test site has saved, and put them back at the end.
global $wpdb;
$obal_saved_options = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'obal\\_%' OR option_name LIKE 'woocommerce\\_obal\\_%'", OBJECT_K ); // phpcs:ignore
foreach ( array_keys( $obal_saved_options ) as $obal_option ) {
	delete_option( $obal_option );
}

$product = static function ( string $name ) {
	$ids = wc_get_products(
		array(
			'name'   => $name,
			'limit'  => 1,
			'return' => 'ids',
		)
	);
	return wc_get_product( $ids[0] );
};

$make = static function () use ( $plugin, $product ) {
	$order = wc_create_order();
	$order->add_product( $product( 'T-Shirt' ), 1 );
	$order->set_billing_email( 'buyer@example.com' );
	$order->set_payment_method( 'test_card' );
	$order->calculate_totals();
	$order->save();
	$order->payment_complete();
	$order = wc_get_order( $order->get_id() );
	$order->add_product( $product( 'Mug' ), 1 );
	$order->calculate_totals();

	$open = $plugin->service()->request( wc_get_order( $order->get_id() ), null, false );
	$open->add_order_note( 'Test note' );
	return array( $order->get_id(), $open->get_id() );
};

$run_uninstall = static function () {
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		define( 'WP_UNINSTALL_PLUGIN', plugin_basename( dirname( __DIR__ ) . '/order-balance.php' ) );
	}
	include dirname( __DIR__ ) . '/uninstall.php';
	wp_cache_flush();
};

$fresh = static function ( int $id ) {
	wp_cache_flush();
	if ( class_exists( \Automattic\WooCommerce\Caches\OrderCache::class ) ) {
		wc_get_container()->get( \Automattic\WooCommerce\Caches\OrderCache::class )->flush();
	}
	return wc_get_order( $id );
};

echo 'Storage: ' . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'legacy' ) . "\n";

// 1. Plain delete: data is kept, partially paid orders move to on hold.
update_option( 'obal_delete_data', 'no' );
update_option( 'obal_rounding_threshold', '2' );
list( $order_id, $payment_id ) = $make();
$check( 'setup: parent partially paid', $fresh( $order_id )->has_status( PaymentType::STATUS ), $fresh( $order_id )->get_status() );

$run_uninstall();
$check( 'keep: parent moved to on hold', $fresh( $order_id )->has_status( 'on-hold' ), $fresh( $order_id )->get_status() );
$check( 'keep: payment kept', $fresh( $payment_id ) instanceof Payment );
$check( 'keep: settings kept', '2' === get_option( 'obal_rounding_threshold' ) );
$check( 'keep: parent meta kept', '' !== (string) $fresh( $order_id )->get_meta( BalanceCalculator::INITIAL_META ) );

// 2. Full delete.
update_option( 'obal_delete_data', 'yes' );
list( $order2_id, $payment2_id ) = $make();
$paid = wc_get_order( $payment2_id );
$paid->payment_complete();
$refund = wc_create_refund(
	array(
		'order_id' => $payment2_id,
		'amount'   => '5',
	)
);
$refund_id = $refund instanceof WC_Order_Refund ? $refund->get_id() : 0;
$check( 'setup: refund on payment', $refund_id > 0 );

global $wpdb;
$count_payments = static function () use ( $wpdb ) {
	$posts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'obal_payment'" );
	$hpos  = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wc_orders'" ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE type = 'obal_payment'" ) : 0;
	return $posts + $hpos;
};
$check( 'setup: payments exist', $count_payments() > 0, $count_payments() );

$run_uninstall();

$check( 'full: no payments left', 0 === $count_payments(), $count_payments() );
$check( 'full: payment gone', ! $fresh( $payment2_id ) );
$check( 'full: refund of payment gone', ! $fresh( $refund_id ) );
$check( 'full: parent order kept', $fresh( $order2_id ) instanceof WC_Order && 2 === count( $fresh( $order2_id )->get_items() ) );
$check( 'full: parent meta removed', '' === (string) $fresh( $order2_id )->get_meta( BalanceCalculator::INITIAL_META ) && '' === (string) $fresh( $order_id )->get_meta( BalanceCalculator::INITIAL_META ) );
$check( 'full: settings removed', false === get_option( 'obal_rounding_threshold' ) && false === get_option( 'obal_delete_data' ) );
$check( 'full: email settings removed', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce\\_obal\\_%'" ) );
$check( 'full: notes of payments removed', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d", $payment2_id ) ) );
$check( 'full: no order meta left', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_obal\\_%'" ) );
$check( 'full: no scheduled actions left', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE hook LIKE 'obal\\_%'" ) );

foreach ( $obal_saved_options as $obal_option => $obal_row ) {
	update_option( $obal_option, maybe_unserialize( $obal_row->option_value ) );
}

echo $obal_failures ? "\n{$obal_failures} FAILED\n" : "\nALL PASSED\n";
