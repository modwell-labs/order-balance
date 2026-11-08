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

// Free version: one payment open at a time (Pro opens several at once).
$free = static function () {
	return false;
};
add_filter( 'obal_request_while_open', $free, 99 );

// 1. The waiting payment grows with the order.
$o = $add( $new_paid_order(), 'Mug' );
$p = $service->request( $o, null, false );
$check( 'free: no payment counter in the box', false === strpos( $box( $o ), 'Balance payments:' ) );
$o = $add( $o, 'Poster' );
$check( 'change since the request is +18', 18.0 === $service->waiting_change( $o ), $service->waiting_change( $o ) );
$check( 'free: no second payment while one waits', ! $service->can_request( $o ) );
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
$p = $service->request( $o, null, false );
wc_get_order( $p->get_id() )->payment_complete();
$o = $add( $o, 'Poster' );
$check( 'paid: a new payment can be requested', $service->can_request( $o ) );
$html = $box( $o );
$check( 'box: request button, no limit notice', false !== strpos( $html, 'data-action="request_payment"' ) && false === strpos( $html, 'limit' ) );
$second = $service->request( $o, null, false );
$check( 'second payment for the new change', $second instanceof \ModwellLabs\OrderBalance\Payments\Payment && 18.0 === (float) $second->get_total() && 2 === count( $plugin->payments()->for_order( $fresh( $o ) ) ), is_wp_error( $second ) ? $second->get_error_message() : '' );
wc_get_order( $second->get_id() )->payment_complete();
$o = $add( $o, 'Mug' );
$third = $service->request( $o, null, false );
$check( 'and a third', $third instanceof \ModwellLabs\OrderBalance\Payments\Payment, is_wp_error( $third ) ? $third->get_error_message() : '' );

remove_filter( 'obal_request_while_open', $free, 99 );

// 5. Pro, automatic: the waiting payment follows the order; one email after the delay.
update_option( 'obal_waiting_mode', 'auto' );
update_option( 'obal_waiting_email_delay', '5' );
$obal_mails = array();
$o          = $add( $new_paid_order(), 'Mug' );
$p          = $service->request( $o, null, false );
$o          = $add( $o, 'Poster' );
$check( 'auto: payment follows the first edit', 30.0 === $amount( $p ), $amount( $p ) );
$o = $add( $o, 'Poster' );
$check( 'auto: and the second', 48.0 === $amount( $p ), $amount( $p ) );
$next = as_next_scheduled_action( 'obal_pro_waiting_email', array( 'order_id' => $o->get_id() ), 'order-balance' );
$jobs = as_get_scheduled_actions(
	array(
		'hook'   => 'obal_pro_waiting_email',
		'args'   => array( 'order_id' => $o->get_id() ),
		'status' => ActionScheduler_Store::STATUS_PENDING,
	),
	'ids'
);
$check( 'auto: one email, about 5 minutes out', 1 === count( $jobs ) && is_int( $next ) && $next > time() + 240 && $next <= time() + 300, array( count( $jobs ), $next - time() ) );
$check( 'auto: nothing sent yet', ! $mailed( 'has been updated' ), $obal_mails );
do_action( 'obal_pro_waiting_email', $o->get_id() );
$check( 'auto: the email goes out when the delay ends', $mailed( 'has been updated' ), $obal_mails );

$o = $remove( $o, 'Poster' );
$o = $remove( $o, 'Poster' );
$o = $remove( $o, 'Mug' );
$check( 'auto: cancelled when nothing is due', wc_get_order( $p->get_id() )->has_status( 'cancelled' ) );
$obal_mails = array();
do_action( 'obal_pro_waiting_email', $o->get_id() );
$check( 'auto: no email at all once the payment is cancelled', ! $mailed( 'was cancelled' ) && ! $mailed( 'has been updated' ), $obal_mails );
as_unschedule_all_actions( 'obal_pro_waiting_email', array( 'order_id' => $o->get_id() ), 'order-balance' );

// 6. Pro, separate payment: the waiting one stays and the difference is requested apart.
update_option( 'obal_waiting_mode', 'new' );
$o    = $add( $new_paid_order(), 'Mug' );
$p    = $service->request( $o, null, false );
$o    = $add( $o, 'Poster' );
$html = $box( $o );
$check( 'new: box requests the difference, no update', false !== strpos( $html, 'data-action="request_payment"' ) && false === strpos( $html, 'data-action="update_payment"' ) && 12.0 === $amount( $p ) );
$second = $service->request( $o, null, false );
$check( 'new: second payment for 18', $second instanceof Payment && 18.0 === (float) $second->get_total() );
$o = $remove( $o, 'Poster' );
$check( 'new: a decrease is still offered as an update', false !== strpos( $box( $o ), 'data-action="update_payment"' ) );
$service->update_waiting( $o, false );
$check( 'new: the newest payment is cancelled first', wc_get_order( $second->get_id() )->has_status( 'cancelled' ) && 12.0 === $amount( $p ) );

// 7. The order change fee follows the new amount.
update_option( 'obal_waiting_mode', 'button' );
update_option( 'obal_change_fee_type', 'percent' );
update_option( 'obal_change_fee_amount', '10' );
update_option( 'obal_change_fee_taxable', 'no' );
$o = $add( $new_paid_order(), 'Mug' );
$p = $service->request( $o, null, false );
$check( 'fee: 10% of 12', 1.2 === wc_get_order( $p->get_id() )->get_store_fees() );
$o = $add( $o, 'Poster' );
$service->update_waiting( $o, false );
$fp = wc_get_order( $p->get_id() );
$check( 'fee: 10% of 30 after the update', 30.0 === $fp->get_amount() && 3.0 === $fp->get_store_fees() && 33.0 === (float) $fp->get_total(), array( $fp->get_amount(), $fp->get_store_fees(), $fp->get_total() ) );

foreach ( array( 'obal_waiting_mode', 'obal_waiting_email_delay', 'obal_change_fee_type', 'obal_change_fee_amount', 'obal_change_fee_taxable' ) as $obal_option ) {
	delete_option( $obal_option );
}

// 8. WooCommerce's own order emails stay off for balance payments; a customer note stays on.
WC()->mailer();
$o  = $add( $new_paid_order(), 'Mug' );
$p  = wc_get_order( $service->request( $o, null, false )->get_id() );
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

// 9. The pay page refuses an amount the customer did not see.
$o    = $add( $new_paid_order(), 'Mug' );
$p    = wc_get_order( $service->request( $o, null, false )->get_id() );
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

// 10. A manual email and the automatic one never repeat each other.
update_option( 'obal_waiting_mode', 'auto' );
update_option( 'obal_waiting_email_delay', '5' );
$o    = $add( $new_paid_order(), 'Mug' );
$p    = $service->request( $o, null, false );
$o    = $add( $o, 'Poster' );
$args = array( 'order_id' => $o->get_id() );
$check( 'tracking: automatic email scheduled', (bool) as_next_scheduled_action( 'obal_pro_waiting_email', $args, 'order-balance' ) );
$obal_mails = array();
$service->notify( $fresh( $o ), wc_get_order( $p->get_id() ) );
$o = $fresh( $o );
$check( 'tracking: a manual send is remembered', $service->already_notified( $o, wc_get_order( $p->get_id() ) ) && 1 === count( $obal_mails ), $obal_mails );
$check( 'tracking: and cancels the scheduled one', ! as_next_scheduled_action( 'obal_pro_waiting_email', $args, 'order-balance' ) );
do_action( 'obal_pro_waiting_email', $o->get_id() );
$check( 'tracking: a late automatic run sends nothing more', 1 === count( $obal_mails ), $obal_mails );
$o = $add( $o, 'Poster' );
$check( 'tracking: a new amount needs a new email', ! $service->already_notified( $fresh( $o ), wc_get_order( $p->get_id() ) ) );
do_action( 'obal_pro_waiting_email', $o->get_id() );
$check( 'tracking: so the automatic one goes out', 2 === count( $obal_mails ), $obal_mails );
$check( 'tracking: box shows the last email', false !== strpos( $box( $o ), 'Last update email:' ) );
as_unschedule_all_actions( 'obal_pro_waiting_email', $args, 'order-balance' );
delete_option( 'obal_waiting_mode' );
delete_option( 'obal_waiting_email_delay' );
