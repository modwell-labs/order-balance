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
	$payment = $plugin->service()->request( $order, null, false );
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

update_option( 'obal_refund_payment_fees', 'yes' );
list( $fo, $fp ) = $fee_case();
$fb = $plugin->balance()->for_order( $fo );
$check( 'surcharge not counted as requested', 12.36 === (float) $fp->get_total() && 0.0 === $fb->unrequested(), $fb );
$fp->payment_complete();
$fp = wc_get_order( $fp->get_id() );
$fb = $plugin->balance()->for_order( wc_get_order( $fo->get_id() ) );
$check( 'surcharge does not overpay parent', $fb->is_settled() && 37.0 === $fb->paid, $fb );
$check( 'surcharge split shown', 12.0 === $fp->get_balance_part() && 0.36 === $fp->get_fees_part() );
$check( 'fees on: refundable includes fees', 12.36 === $fp->get_refundable(), $fp->get_refundable() );
$plugin->actions()->run( 'refund', $fp, array( 'amount' => '6.18' ), false );
$check( 'fees on: half refund reopens half', 6.0 === $due( $fo ), $due( $fo ) );
$plugin->actions()->run( 'refund', wc_get_order( $fp->get_id() ), array( 'amount' => '6.18' ), false );
$check( 'fees on: full refund reopens all', 12.0 === $due( $fo ), $due( $fo ) );

update_option( 'obal_refund_payment_fees', 'no' );
list( $fo, $fp ) = $fee_case();
$fp->payment_complete();
$fp = wc_get_order( $fp->get_id() );
$check( 'fees off: refundable excludes fees', 12.0 === $fp->get_refundable(), $fp->get_refundable() );
$over = $plugin->actions()->run( 'refund', $fp, array( 'amount' => '12.36' ), false );
$check( 'fees off: refund of fees blocked', is_wp_error( $over ) );
$plugin->actions()->run( 'refund', wc_get_order( $fp->get_id() ), array( 'amount' => '4' ), false );
$fp = wc_get_order( $fp->get_id() );
$check( 'fees off: partial refund reopens same amount', 4.0 === $due( $fo ) && 8.0 === $fp->get_refundable(), array( $due( $fo ), $fp->get_refundable() ) );
$plugin->actions()->run( 'refund', $fp, array( 'amount' => '8' ), false );
$fp = wc_get_order( $fp->get_id() );
$check( 'fees off: fees kept after full refund', 12.0 === $due( $fo ) && 0.0 === $fp->get_refundable() && 12.0 === (float) $fp->get_total_refunded(), array( $due( $fo ), $fp->get_total_refunded() ) );
delete_option( 'obal_refund_payment_fees' );

// Order change fee (Pro): a store fee on balance payments after a paid order changes.
$owing = static function () use ( $new_paid_order, $product ) {
	$order = $new_paid_order();
	$order->add_product( $product( 'Mug' ), 1 );
	$order->calculate_totals();
	return wc_get_order( $order->get_id() );
};
$paid_request = static function ( $order ) use ( $plugin ) {
	$payment = wc_get_order( $plugin->service()->request( $order, null, false )->get_id() );
	$payment->payment_complete();
	return wc_get_order( $payment->get_id() );
};

$lo = $owing();
$lp = wc_get_order( $plugin->service()->request( $lo, null, false )->get_id() );
$check( 'change fee off by default', 12.0 === (float) $lp->get_total() && 0.0 === $lp->get_store_fees() );
$lp->update_status( 'cancelled' );

update_option( 'obal_change_fee_type', 'fixed' );
update_option( 'obal_change_fee_amount', '2' );
update_option( 'obal_change_fee_refund', 'never' );
$lo = $owing();
$lp = wc_get_order( $plugin->service()->request( $lo, null, false )->get_id() );
$lb = $plugin->balance()->for_order( wc_get_order( $lo->get_id() ) );
$check( 'fixed fee added', 14.0 === (float) $lp->get_total() && 2.0 === $lp->get_store_fees() && 12.0 === $lp->get_amount(), array( $lp->get_total(), $lp->get_store_fees() ) );
$check( 'fixed fee not requested', 0.0 === $lb->unrequested(), $lb );
$lp->payment_complete();
$lp = wc_get_order( $lp->get_id() );
$check( 'fixed fee does not overpay', $plugin->balance()->for_order( wc_get_order( $lo->get_id() ) )->is_settled() );
$check( 'never: fee not refundable', 12.0 === $lp->get_refundable(), $lp->get_refundable() );
$plugin->actions()->run( 'refund', $lp, array( 'amount' => '12' ), false );
$check( 'never: refund reopens all, fee kept', 12.0 === $due( $lo ) && 0.0 === wc_get_order( $lp->get_id() )->get_refundable(), $due( $lo ) );

update_option( 'obal_change_fee_refund', 'full' );
$lo = $owing();
$lp = $paid_request( $lo );
$check( 'full: refundable includes fee', 14.0 === $lp->get_refundable(), $lp->get_refundable() );
$plugin->actions()->run( 'refund', $lp, array( 'amount' => '12' ), false );
$lp = wc_get_order( $lp->get_id() );
$check( 'full: refund of the amount keeps fee', 12.0 === $due( $lo ) && 2.0 === $lp->get_refundable(), array( $due( $lo ), $lp->get_refundable() ) );
$plugin->actions()->run( 'refund', $lp, array( 'amount' => '2' ), false );
$check( 'full: whole payment refunded', 14.0 === (float) wc_get_order( $lp->get_id() )->get_total_refunded() && 12.0 === $due( $lo ) );

update_option( 'obal_change_fee_refund', 'proportional' );
$lo = $owing();
$lp = $paid_request( $lo );
$plugin->actions()->run( 'refund', $lp, array( 'amount' => '7' ), false );
$check( 'proportional: half refund reopens half', 6.0 === $due( $lo ), $due( $lo ) );

update_option( 'obal_change_fee_type', 'percent' );
update_option( 'obal_change_fee_amount', '10' );
update_option( 'obal_change_fee_refund', 'full' );
update_option( 'obal_refund_payment_fees', 'yes' );
list( $fo, $fp ) = $fee_case();
$check( 'percent fee plus surcharge', 13.56 === (float) $fp->get_total() && 1.2 === $fp->get_store_fees() && 0.36 === $fp->get_gateway_fees(), array( $fp->get_total(), $fp->get_store_fees(), $fp->get_gateway_fees() ) );
$fp->payment_complete();
$fp = wc_get_order( $fp->get_id() );
$check( 'percent fee: refundable is everything', 13.56 === $fp->get_refundable(), $fp->get_refundable() );
$plugin->actions()->run( 'refund', $fp, array( 'amount' => '6.18' ), false );
$check( 'percent fee: half of the rest reopens half', 6.0 === $due( $fo ), $due( $fo ) );

$co  = $later_order( 'cod', 'processing' );
$cp  = $plugin->service()->request( $co, null, false );
$check( 'no fee on orders paid later', 0.0 === wc_get_order( $cp->get_id() )->get_store_fees(), wc_get_order( $cp->get_id() )->get_total() );
$cp->update_status( 'cancelled' );
$rec = $plugin->service()->record( wc_get_order( $co->get_id() ), 10.0, 'Cash' );
$check( 'no fee on recorded payments', 10.0 === (float) wc_get_order( $rec->get_id() )->get_total(), wc_get_order( $rec->get_id() )->get_total() );

// Tax on the fee: 20% on top, then 20% inside the amount.
$tax_before = array( get_option( 'woocommerce_calc_taxes' ), get_option( 'woocommerce_prices_include_tax' ) );
update_option( 'woocommerce_calc_taxes', 'yes' );
update_option( 'woocommerce_prices_include_tax', 'no' );
$rate_id = WC_Tax::_insert_tax_rate(
	array(
		'tax_rate_country'  => '',
		'tax_rate'          => '20.0000',
		'tax_rate_name'     => 'VAT',
		'tax_rate_priority' => 1,
		'tax_rate_shipping' => 1,
		'tax_rate_order'    => 1,
		'tax_rate_class'    => '',
	)
);
update_option( 'obal_change_fee_type', 'fixed' );
update_option( 'obal_change_fee_amount', '2' );

$tp = wc_get_order( $plugin->service()->request( $owing(), null, false )->get_id() );
$check( 'fee taxed on top', 2.4 === $tp->get_store_fees() && 0.4 === (float) $tp->get_total_tax(), array( $tp->get_store_fees(), $tp->get_total_tax() ) );

update_option( 'woocommerce_prices_include_tax', 'yes' );
$tp = wc_get_order( $plugin->service()->request( $owing(), null, false )->get_id() );
$check( 'fee taxed inside the amount', 2.0 === $tp->get_store_fees() && 0.33 === (float) $tp->get_total_tax(), array( $tp->get_store_fees(), $tp->get_total_tax() ) );

update_option( 'obal_change_fee_taxable', 'no' );
$tp = wc_get_order( $plugin->service()->request( $owing(), null, false )->get_id() );
$check( 'untaxed fee', 2.0 === $tp->get_store_fees() && 0.0 === (float) $tp->get_total_tax(), array( $tp->get_store_fees(), $tp->get_total_tax() ) );

WC_Tax::_delete_tax_rate( $rate_id );
update_option( 'woocommerce_calc_taxes', $tax_before[0] );
update_option( 'woocommerce_prices_include_tax', $tax_before[1] );
foreach ( array( 'type', 'amount', 'refund', 'taxable' ) as $obal_key ) {
	delete_option( 'obal_change_fee_' . $obal_key );
}
delete_option( 'obal_refund_payment_fees' );
