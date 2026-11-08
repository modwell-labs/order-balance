<?php
/**
 * Test group "core". Included by smoke.php, which provides the helpers and shares its variables.
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

$order = $new_paid_order();
$check( 'initial paid captured', '25.00' === (string) $order->get_meta( BalanceCalculator::INITIAL_META ), $order->get_meta( BalanceCalculator::INITIAL_META ) );

$order->add_product( $product( 'Mug' ), 1 );
$order->calculate_totals();
$order = wc_get_order( $order->get_id() );
$b     = $plugin->balance()->for_order( $order );
$check( 'initial paid kept after edit', '25.00' === (string) $order->get_meta( BalanceCalculator::INITIAL_META ) );
$check( 'balance due 12', 12.0 === $b->due, $b );

$limit = static function () {
	return false;
};
add_filter( 'obal_request_while_open', $limit, 99 );

$status_before = $order->get_status();
$mails_before  = count( $obal_mails );
$payment      = $plugin->service()->request( $order );
$check( 'payment created', $payment instanceof Payment, is_wp_error( $payment ) ? $payment->get_error_message() : '' );
$order = wc_get_order( $order->get_id() );
$check( 'parent partially paid', $order->has_status( PaymentType::STATUS ), $order->get_status() );
$check( 'payment linked both ways', $payment->get_parent_id() === $order->get_id() && in_array( $payment->get_id(), $plugin->payments()->ids( $order ), true ) );
$check( 'payment number', $order->get_order_number() . '-1' === $payment->get_order_number(), $payment->get_order_number() );
$check( 'order updated email sent', count( $obal_mails ) > $mails_before, $obal_mails );
$check( 'payment hidden from orders', ! in_array( $payment->get_id(), wc_get_orders( array( 'return' => 'ids', 'limit' => -1 ) ), true ) );

$order->add_product( $product( 'Poster' ), 1 );
$order->calculate_totals();
$order  = wc_get_order( $order->get_id() );
$second = $plugin->service()->request( $order );
$check( 'free: no second payment while one is waiting', is_wp_error( $second ) && 'obal_waiting' === $second->get_error_code(), is_wp_error( $second ) ? $second->get_error_code() : 'created' );
remove_filter( 'obal_request_while_open', $limit, 99 );

$custom = $plugin->service()->request( $order, 5.0, false );
$check( 'pro custom amount payment', $custom instanceof Payment && 5.0 === (float) $custom->get_total(), is_wp_error( $custom ) ? $custom->get_error_message() : '' );
$custom->update_status( 'cancelled' );

$payment = wc_get_order( $payment->get_id() );
$payment->payment_complete();
$payment = wc_get_order( $payment->get_id() );
$order   = wc_get_order( $order->get_id() );
$b       = $plugin->balance()->for_order( $order );
$check( 'payment completed', $payment->has_status( 'completed' ), $payment->get_status() );
$check( 'paid 37 after payment', 37.0 === $b->paid, $b );
$check( 'still owed poster 18', 18.0 === $b->due, $b );

$rest = $plugin->service()->request( $order, null, false );
wc_get_order( $rest->get_id() )->payment_complete();
$order = wc_get_order( $order->get_id() );
$b     = $plugin->balance()->for_order( $order );
$check( 'settled after second payment', $b->is_settled(), $b );
$check( 'status restored', $order->has_status( $status_before ), array( $status_before, $order->get_status() ) );

foreach ( $order->get_items() as $item_id => $item ) {
	if ( 'Poster' === $item->get_name() ) {
		$order->remove_item( $item_id );
	}
}
$order->calculate_totals();
$order = wc_get_order( $order->get_id() );
$b     = $plugin->balance()->for_order( $order );
$check( 'overpaid 18', $b->is_overpaid() && -18.0 === $b->due, $b );

$refund = $plugin->service()->refund( $order );
$check( 'refund created', $refund instanceof WC_Order_Refund, is_wp_error( $refund ) ? $refund->get_error_message() : '' );
$order = wc_get_order( $order->get_id() );
$b     = $plugin->balance()->for_order( $order );
$check( 'settled after refund', $b->is_settled(), $b );

$set_mug = static function ( $order, string $price ) {
	foreach ( $order->get_items() as $item ) {
		if ( 'Mug' === $item->get_name() ) {
			$item->set_total( $price );
			$item->set_subtotal( $price );
			$item->save();
		}
	}
	$order->calculate_totals();
	return wc_get_order( $order->get_id() );
};
$adjustment = static function ( $order ) {
	$adj = 0.0;
	foreach ( $order->get_fees() as $fee ) {
		if ( $fee->get_meta( '_obal_adjustment' ) ) {
			$adj = (float) $fee->get_total();
		}
	}
	return $adj;
};

// Automatic rounding is off by default: a small difference stays visible as a balance.
$check( 'auto rounding off by default', ! $plugin->settings()->enabled( 'auto_rounding', false ) );
$order = $set_mug( $order, '12.40' );
$b     = $plugin->balance()->for_order( $order );
$check( 'off: no adjustment, 0.40 stays due', 0.0 === $adjustment( $order ) && 0.4 === $b->due, array( $adjustment( $order ), $b->due ) );
$check( 'off: manual absorb is available', $b->within( $plugin->settings()->threshold() ) );
$manual = $plugin->service()->adjust( $order );
$order  = wc_get_order( $order->get_id() );
$check( 'off: manual absorb works', true === $manual && -0.4 === $adjustment( $order ) && $plugin->balance()->for_order( $order )->is_settled(), is_wp_error( $manual ) ? $manual->get_error_message() : $adjustment( $order ) );

// Automatic rounding switched on.
update_option( 'obal_auto_rounding', 'yes' );
$order = $set_mug( $order, '12.00' );
$order = $set_mug( $order, '12.40' );
$b     = $plugin->balance()->for_order( $order );
$check( 'auto rounding adjustment -0.40', -0.4 === $adjustment( $order ), $adjustment( $order ) );
$check( 'settled after rounding', $b->is_settled(), $b );
delete_option( 'obal_auto_rounding' );

ob_start();
do_action( 'woocommerce_order_details_after_order_table', $order );
$account = ob_get_clean();
$check( 'account balance renders', false !== strpos( $account, 'obal-account-balance' ) );

$invoice = Template::html(
	'invoice.php',
	array(
		'order'    => $order,
		'balance'  => $b,
		'payments' => $plugin->payments()->for_order( $order ),
		'refunds'  => $order->get_refunds(),
		'initial'  => 25.0,
		'store'    => array(
			'name'    => 'Test',
			'address' => '',
			'email'   => '',
		),
	),
	WP_PLUGIN_DIR . '/order-balance-pro/templates/'
);
$check( 'invoice renders', false !== strpos( $invoice, 'Refund of overpayment' ) );

$other = $new_paid_order();
$other->add_product( $product( 'Mug' ), 1 );
$other->calculate_totals();
$open = $plugin->service()->request( wc_get_order( $other->get_id() ), null, false );
wc_get_order( $other->get_id() )->update_status( 'cancelled' );
$check( 'open payment cancelled with parent', wc_get_order( $open->get_id() )->has_status( 'cancelled' ) );

$check( 'reminder scheduled', (bool) as_next_scheduled_action( 'obal_pro_payment_reminder', null, 'order-balance' ) );

wc_get_order( $other->get_id() )->delete( true );
$check( 'payments deleted with parent', ! wc_get_order( $open->get_id() ) );

$mail_to = static function ( string $needle ) use ( &$obal_mails ): bool {
	foreach ( $obal_mails as $mail ) {
		if ( false !== stripos( $mail, $needle ) ) {
			return true;
		}
	}
	return false;
};

$third = $new_paid_order();
$third->add_product( $product( 'Hoodie' ), 1 );
$third->calculate_totals();
$third = wc_get_order( $third->get_id() );
$open  = $plugin->service()->request( $third, null, false );
$names = array_keys( $plugin->actions()->available( $open ) );
$check( 'open payment actions', array( 'cancel', 'reassign' ) === array_values( array_intersect( array( 'cancel', 'refund', 'reassign' ), $names ) ), $names );

$obal_mails = array();
$result     = $plugin->actions()->run(
	'reassign',
	$open,
	array(
		'email'      => 'company@example.com',
		'first_name' => 'Acme',
		'last_name'  => 'Ltd',
	),
	true
);
$open = wc_get_order( $open->get_id() );
$check( 'payer changed', ! is_wp_error( $result ) && 'company@example.com' === $open->get_billing_email(), is_wp_error( $result ) ? $result->get_error_message() : $open->get_billing_email() );
$check( 'update email goes to new payer', $mail_to( 'company@example.com' ), $obal_mails );
$check( 'parent payer unchanged', 'buyer@example.com' === wc_get_order( $third->get_id() )->get_billing_email() );

$obal_mails = array();
$open->payment_complete();
$check( 'receipt to payer', $mail_to( 'Payment received' ) && $mail_to( 'company@example.com' ), $obal_mails );
$check( 'admin receipt', $mail_to( get_option( 'admin_email' ) ), $obal_mails );

$open  = wc_get_order( $open->get_id() );
$names = array_keys( $plugin->actions()->available( $open ) );
$check( 'paid payment actions', array( 'refund' ) === array_values( array_intersect( array( 'cancel', 'refund', 'reassign' ), $names ) ), $names );

$obal_mails = array();
$result     = $plugin->actions()->run( 'refund', $open, array( 'amount' => '20' ), true );
$open       = wc_get_order( $open->get_id() );
$third      = wc_get_order( $third->get_id() );
$tb         = $plugin->balance()->for_order( $third );
$check( 'partial refund of payment', ! is_wp_error( $result ) && 20.0 === (float) $open->get_total_refunded(), is_wp_error( $result ) ? $result->get_error_message() : $open->get_total_refunded() );
$check( 'parent owes refunded part', 20.0 === $tb->due, $tb );
$check( 'parent back to partially paid', $third->has_status( PaymentType::STATUS ), $third->get_status() );
$check( 'refund email sent', $mail_to( 'Refund for your' ), $obal_mails );

$fourth = $new_paid_order();
$fourth->add_product( $product( 'Mug' ), 1 );
$fourth->calculate_totals();
$cancel_me  = $plugin->service()->request( wc_get_order( $fourth->get_id() ), null, false );
$obal_mails = array();
$result     = $plugin->actions()->run( 'cancel', $cancel_me, array(), true );
$check( 'cancel action', ! is_wp_error( $result ) && wc_get_order( $cancel_me->get_id() )->has_status( 'cancelled' ) );
$check( 'cancel email sent', $mail_to( 'was cancelled' ), $obal_mails );
$check( 'cancel unavailable after cancel', is_wp_error( $plugin->actions()->run( 'cancel', wc_get_order( $cancel_me->get_id() ) ) ) );
