<?php
/**
 * Test group "changes": the order changes again while a balance payment is waiting.
 * Included by smoke.php, which provides the helpers and shares its variables.
 *
 * @var callable                                $check          Records a check: label, bool, detail.
 * @var object                                  $plugin         The Order Balance plugin instance.
 * @var callable                                $product        Product by name.
 * @var callable                                $new_paid_order A paid order with one T-shirt.
 * @var array                                   $obal_mails     Subjects and recipients of sent emails.
 */

use ModwellLabs\OrderBalance\Admin\OrderMetabox;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Payments\PaymentType;

$service = $plugin->service();
$fresh   = static function ( $order ) {
	return wc_get_order( $order->get_id() );
};
$add     = static function ( $order, string $name ) use ( $product ) {
	$order = wc_get_order( $order->get_id() );
	$order->add_product( $product( $name ), 1 );
	$order->calculate_totals();
	return wc_get_order( $order->get_id() );
};
$remove  = static function ( $order, string $name ) {
	$order = wc_get_order( $order->get_id() );
	foreach ( $order->get_items() as $item_id => $item ) {
		if ( $name === $item->get_name() ) {
			$order->remove_item( $item_id );
			break;
		}
	}
	$order->calculate_totals();
	return wc_get_order( $order->get_id() );
};
$box     = static function ( $order ) use ( $plugin ) {
	return $plugin->get( OrderMetabox::class )->html( wc_get_order( $order->get_id() ) );
};
$mailed  = static function ( string $needle ) use ( &$obal_mails ): bool {
	foreach ( $obal_mails as $mail ) {
		if ( false !== stripos( $mail, $needle ) ) {
			return true;
		}
	}
	return false;
};
$amount  = static function ( $payment ): float {
	return (float) wc_get_order( $payment->get_id() )->get_total();
};

// 1. The waiting payment grows with the order.
$o = $add( $new_paid_order(), 'Mug' );
$p = $service->request( $o, false );
$check( 'no payment counter in the box', false === strpos( $box( $o ), 'Balance payments:' ) );
$o = $add( $o, 'Poster' );
$check( 'change since the request is +18', 18.0 === $service->waiting_change( $o ), $service->waiting_change( $o ) );
$check( 'no second payment while one waits', ! $service->can_request( $o ) );
$html = $box( $o );
$check( 'box offers 12 to 30 on the same payment', false !== strpos( $html, 'data-action="update_payment"' ) && false !== strpos( $html, '30.00' ) && false === strpos( $html, 'data-action="request_payment"' ) );
$r = $service->update_waiting( $o, false );
$o = $fresh( $o );
$check( 'update: same payment, now 30', ! is_wp_error( $r ) && 30.0 === $amount( $p ) && 30.0 === wc_get_order( $p->get_id() )->get_amount() && 1 === count( $plugin->payments()->for_order( $o ) ), is_wp_error( $r ) ? $r->get_error_message() : $amount( $p ) );
$check( 'update: nothing left over', 0.0 === $service->waiting_change( $o ) && 0.0 === $plugin->balance()->for_order( $o )->unrequested() );

// 2. And shrinks with it.
$o = $remove( $o, 'Poster' );
$check( 'change since the update is -18', -18.0 === $service->waiting_change( $o ), $service->waiting_change( $o ) );
$service->update_waiting( $o, false );
$check( 'decrease: back to 12', 12.0 === $amount( $p ), $amount( $p ) );

// 3. Cancelled when the order no longer needs it.
$o = $remove( $o, 'Mug' );
$check( 'box offers to cancel', false !== strpos( $box( $o ), 'Cancel payment #' ) );
$service->update_waiting( $o, false );
$o = $fresh( $o );
$check( 'cancel: payment cancelled', wc_get_order( $p->get_id() )->has_status( 'cancelled' ) );
$check( 'cancel: order settled, no longer partially paid', $plugin->balance()->for_order( $o )->is_settled() && ! $o->has_status( PaymentType::STATUS ), $o->get_status() );
$check( 'cancel: a payment can be requested again', $service->can_request( $o ) );

// 4. No limit: once a payment is paid, the next change is requested as a new payment.
$o = $add( $new_paid_order(), 'Mug' );
$p = $service->request( $o, false );
wc_get_order( $p->get_id() )->payment_complete();
$o = $add( $o, 'Poster' );
$check( 'paid: a new payment can be requested', $service->can_request( $o ) );
$html = $box( $o );
$check( 'box: request button, no limit notice', false !== strpos( $html, 'data-action="request_payment"' ) && false === strpos( $html, 'limit' ) );
$second = $service->request( $o, false );
$check( 'second payment for the new change', $second instanceof \ModwellLabs\OrderBalance\Payments\Payment && 18.0 === (float) $second->get_total() && 2 === count( $plugin->payments()->for_order( $fresh( $o ) ) ), is_wp_error( $second ) ? $second->get_error_message() : '' );
wc_get_order( $second->get_id() )->payment_complete();
$o = $add( $o, 'Mug' );
$third = $service->request( $o, false );
$check( 'and a third', $third instanceof \ModwellLabs\OrderBalance\Payments\Payment, is_wp_error( $third ) ? $third->get_error_message() : '' );

// 5. WooCommerce's own order emails stay off for balance payments; a customer note stays on.
WC()->mailer();
$o  = $add( $new_paid_order(), 'Mug' );
$p  = wc_get_order( $service->request( $o, false )->get_id() );
$on = static function ( string $id, $object ): bool {
	return (bool) apply_filters( 'woocommerce_email_enabled_' . $id, true, $object );
};
$check( 'core emails off for payments', ! $on( 'customer_completed_order', $p ) && ! $on( 'customer_partially_refunded_order', $p ) && ! $on( 'customer_review_request', $p ) && ! $on( 'customer_cancelled_order', $p ) );
$check( 'customer note stays on for payments', $on( 'customer_note', $p ) );
$check( 'core emails untouched for real orders', $on( 'customer_partially_refunded_order', $o ) && $on( 'customer_completed_order', $o ) );
$p->payment_complete();
$obal_mails = array();
wc_create_refund(
	array(
		'order_id' => $p->get_id(),
		'amount'   => '2',
	)
);
$check( 'partial refund of a payment sends no WooCommerce email', ! $mailed( 'refunded' ), $obal_mails );

// 6. The pay page refuses an amount the customer did not see.
$o    = $add( $new_paid_order(), 'Mug' );
$p    = wc_get_order( $service->request( $o, false )->get_id() );
$seen = \ModwellLabs\OrderBalance\Frontend\PayGuard::fingerprint( $p );
$check( 'pay guard: same amount passes', \ModwellLabs\OrderBalance\Frontend\PayGuard::matches( wc_get_order( $p->get_id() ), $seen ), $seen );
$o = $add( $o, 'Poster' );
$service->update_waiting( $o, false );
$check( 'pay guard: changed amount is stopped', ! \ModwellLabs\OrderBalance\Frontend\PayGuard::matches( wc_get_order( $p->get_id() ), $seen ), array( $seen, \ModwellLabs\OrderBalance\Frontend\PayGuard::fingerprint( wc_get_order( $p->get_id() ) ) ) );

// Express buttons (Stripe Apple Pay, Google Pay, Link) pay through the Store API.
$guard     = $plugin->get( \ModwellLabs\OrderBalance\Frontend\PayGuard::class );
$store_req = static function ( int $id, string $seen_total, ?string $key = null ) {
	$r = new WP_REST_Request( 'POST', '/wc/store/v1/checkout/' . $id );
	$r->set_param( 'key', null === $key ? wc_get_order( $id )->get_order_key() : $key );
	$r->set_param( 'extensions', array( 'order-balance' => array( 'seen_total' => $seen_total ) ) );
	return $r;
};
$stopped = $guard->check_store_api( null, array(), $store_req( $p->get_id(), $seen ) );
$check( 'pay guard: express payment with the old amount is refused', is_wp_error( $stopped ) && 'obal_amount_changed' === $stopped->get_error_code() );
$check( 'pay guard: says nothing without the payment key', null === $guard->check_store_api( null, array(), $store_req( $p->get_id(), $seen, 'wc_order_wrong' ) ) );
$now = \ModwellLabs\OrderBalance\Frontend\PayGuard::fingerprint( wc_get_order( $p->get_id() ) );
$check( 'pay guard: express payment with the shown amount goes on', null === $guard->check_store_api( null, array(), $store_req( $p->get_id(), $now ) ) );
$check( 'pay guard: other orders are not touched', null === $guard->check_store_api( null, array(), $store_req( $o->get_id(), 'GBP:1.00' ) ) );
$check( 'pay page: no zero subtotal row for a payment', ! isset( wc_get_order( $p->get_id() )->get_order_item_totals()['cart_subtotal'] ) );
