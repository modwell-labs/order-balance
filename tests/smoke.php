<?php
/**
 * Run: wp eval-file wp-content/plugins/order-balance/tests/smoke.php [group...]
 *
 * Groups (in tests/groups/), each independent of the others:
 *   core   balance, payments, refunds, statuses, emails, rounding
 *   admin  payment screen, settings categories, setup wizard
 *   later  pay-later payment methods (COD, BACS, manual orders)
 *   fees     payment method fees, order change fee, tax on fees
 *   changes  the order changes again while a payment is waiting
 *   compat   payment plugins and other plugins finding balance payments
 * No argument runs all of them.
 */

use ModwellLabs\OrderBalance\Payments\BalanceCalculator;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Payments\PaymentType;
use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Template;

$_SERVER['HTTP_HOST'] = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';

$obal_failures = 0;
$obal_mails    = array();

add_filter(
	'wp_mail',
	static function ( $args ) use ( &$obal_mails ) {
		$obal_mails[] = $args['subject'] . ' => ' . ( is_array( $args['to'] ) ? implode( ',', $args['to'] ) : $args['to'] );
		return $args;
	}
);
add_filter( 'pre_wp_mail', '__return_true' );

$check = static function ( string $label, bool $ok, $detail = '' ) use ( &$obal_failures ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok || '' === $detail ? '' : ' -> ' . wp_json_encode( $detail ) ) . "\n";
	if ( ! $ok ) {
		++$obal_failures;
	}
};

$plugin = Plugin::instance();

// Groups to run.
$obal_all_groups = array( 'core', 'admin', 'later', 'fees', 'changes', 'compat' );
$obal_groups     = isset( $args ) && $args ? array_map( 'strval', $args ) : $obal_all_groups;
$obal_unknown    = array_diff( $obal_groups, $obal_all_groups );
if ( $obal_unknown ) {
	echo 'Unknown group: ' . implode( ', ', $obal_unknown ) . '. Use: ' . implode( ' ', $obal_all_groups ) . "\n";
	exit( 1 );
}
echo 'Groups: ' . implode( ', ', $obal_groups ) . "\n";

// Run on default settings whatever the test site has saved, and put them back at the end.
global $wpdb;
$obal_saved_options = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'obal\\_%' OR option_name LIKE 'woocommerce\\_obal\\_%'", OBJECT_K ); // phpcs:ignore
foreach ( array_keys( $obal_saved_options ) as $obal_option ) {
	delete_option( $obal_option );
}

$product = function ( string $name ) {
	$ids = wc_get_products(
		array(
			'name'   => $name,
			'limit'  => 1,
			'return' => 'ids',
		)
	);
	return wc_get_product( $ids[0] );
};

$new_paid_order = static function () use ( $product ) {
	$order = wc_create_order();
	$order->add_product( $product( 'T-Shirt' ), 1 );
	$order->set_billing_email( 'buyer@example.com' );
	$order->set_billing_first_name( 'Sam' );
	$order->set_payment_method( 'test_card' );
	$order->set_payment_method_title( 'Test card' );
	$order->calculate_totals();
	$order->save();
	$order->payment_complete();
	return wc_get_order( $order->get_id() );
};

$later_order = static function ( string $method, string $status ) use ( $product ) {
	$order = wc_create_order();
	$order->add_product( $product( 'T-Shirt' ), 1 );
	$order->add_product( $product( 'Mug' ), 1 );
	$order->set_billing_email( 'later@example.com' );
	$order->set_billing_first_name( 'Lee' );
	$order->set_payment_method( $method );
	$order->calculate_totals();
	$order->set_status( $status );
	$order->save();
	return wc_get_order( $order->get_id() );
};

echo 'Storage: ' . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'legacy' ) . "\n";

foreach ( $obal_groups as $obal_group ) {
	include __DIR__ . '/groups/' . $obal_group . '.php';
}

foreach ( $obal_saved_options as $obal_option => $obal_row ) {
	update_option( $obal_option, maybe_unserialize( $obal_row->option_value ) );
}

echo $obal_failures ? "\n{$obal_failures} FAILED\n" : "\nALL PASSED\n";
