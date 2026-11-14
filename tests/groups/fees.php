<?php
/**
 * Test group "fees". Included by smoke.php, which provides the helpers and shares its variables.
 *
 * @var callable                                $check          Records a check: label, bool, detail.
 * @var object                                  $plugin         The Order Balance plugin instance.
 * @var callable                                $product        Product by name.
 * @var callable                                $new_paid_order A paid order with one T-shirt.
 * @var callable                                $later_order    An order paid later: method, status.
 * @var array                                   $obal_mails     Subjects and recipients of sent emails.
 */

use ModwellLabs\OrderBalance\Payments\BalanceCalculator;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Payments\PaymentType;
use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Template;

// Payment fees: a payment method adds a surcharge to the balance payment at the pay page.
$fee_case = static function () use ( $plugin, $new_paid_order, $product ) {
	$order = $new_paid_order();
	$order->add_product( $product( 'Mug' ), 1 );
	$order->calculate_totals();
	$order   = wc_get_order( $order->get_id() );
	$payment = $plugin->service()->request( $order, false );
	$payment = wc_get_order( $payment->get_id() );

	$fee = new WC_Order_Item_Fee();
	$fee->set_name( 'Card surcharge' );
	$fee->set_amount( '0.36' );
	$fee->set_total( '0.36' );
	$fee->set_tax_status( 'none' );
	$payment->add_item( $fee );
	$payment->calculate_totals( false );
	$payment->save();

	return array( wc_get_order( $order->get_id() ), wc_get_order( $payment->get_id() ) );
};
$due = static function ( $order ) use ( $plugin ) {
	return $plugin->balance()->for_order( wc_get_order( $order->get_id() ) )->due;
};

$refund = static function ( $payment, string $amount ) {
	return wc_create_refund(
		array(
			'order_id' => $payment->get_id(),
			'amount'   => $amount,
		)
	);
};

update_option( 'obal_refund_payment_fees', 'yes' );
list( $fo, $fp ) = $fee_case();
$fb = $plugin->balance()->for_order( $fo );
$check( 'surcharge not counted as requested', 12.36 === (float) $fp->get_total() && 0.0 === $fb->unrequested(), $fb );
$fp->payment_complete();
$fp = wc_get_order( $fp->get_id() );
$fb = $plugin->balance()->for_order( wc_get_order( $fo->get_id() ) );
$check( 'surcharge does not overpay parent', $fb->is_settled() && 37.0 === $fb->paid, $fb );
$check( 'surcharge split shown', 12.0 === $fp->get_balance_part() && 0.36 === $fp->get_fees_part() );
$check( 'fees on: refunds share the fees', 12.36 === $fp->get_refund_pool(), $fp->get_refund_pool() );
$refund( $fp, '6.18' );
$check( 'fees on: half refund reopens half', 6.0 === $due( $fo ), $due( $fo ) );
$refund( wc_get_order( $fp->get_id() ), '6.18' );
$check( 'fees on: full refund reopens all', 12.0 === $due( $fo ), $due( $fo ) );

update_option( 'obal_refund_payment_fees', 'no' );
list( $fo, $fp ) = $fee_case();
$fp->payment_complete();
$fp = wc_get_order( $fp->get_id() );
$check( 'fees off: refunds come out of the balance part only', 12.0 === $fp->get_refund_pool(), $fp->get_refund_pool() );
$refund( $fp, '4' );
$check( 'fees off: partial refund reopens same amount', 4.0 === $due( $fo ), $due( $fo ) );
$refund( wc_get_order( $fp->get_id() ), '8' );
$fp = wc_get_order( $fp->get_id() );
$check( 'fees off: fees kept after refunding the balance part', 12.0 === $due( $fo ) && 12.0 === (float) $fp->get_total_refunded(), array( $due( $fo ), $fp->get_total_refunded() ) );
delete_option( 'obal_refund_payment_fees' );
