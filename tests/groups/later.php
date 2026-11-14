<?php
/**
 * Test group "later". Included by smoke.php, which provides the helpers and shares its variables.
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

$settings = $plugin->settings();
$check( 'default modes', 'later_completed' === $settings->gateway_mode( 'cod' ) && 'later_processing' === $settings->gateway_mode( 'bacs' ) && 'later_manual' === $settings->gateway_mode( '' ) && 'checkout' === $settings->gateway_mode( 'stripe' ) );

$cod = $later_order( 'cod', 'processing' );
$b   = $plugin->balance()->for_order( $cod );
$check( 'COD processing is not paid', $b->tracked && 0.0 === $b->paid && 37.0 === $b->due, $b );

$ten  = static function () {
	return 10.0;
};
add_filter( 'obal_record_amount', $ten );
$cash = $plugin->service()->record( $cod, 'Cash' );
remove_filter( 'obal_record_amount', $ten );
$cod  = wc_get_order( $cod->get_id() );
$b    = $plugin->balance()->for_order( $cod );
$check( 'record partial cash', $cash instanceof Payment && $cash->is_recorded() && 27.0 === $b->due, is_wp_error( $cash ) ? $cash->get_error_message() : $b );
$check( 'partial cash sets partially paid', $cod->has_status( PaymentType::STATUS ), $cod->get_status() );

$cod->update_status( 'completed' );
$cod = wc_get_order( $cod->get_id() );
$b   = $plugin->balance()->for_order( $cod );
$check( 'COD completed records the rest', $b->is_settled() && 2 === count( $plugin->payments()->for_order( $cod ) ), $b );

$bacs = $later_order( 'bacs', 'on-hold' );
$check( 'BACS on-hold owes total', 37.0 === $plugin->balance()->for_order( $bacs )->due );
$bacs->update_status( 'processing' );
$bacs = wc_get_order( $bacs->get_id() );
$check( 'BACS processing records payment', $plugin->balance()->for_order( $bacs )->is_settled() );

$manual = $later_order( '', 'pending' );
$full   = $plugin->service()->request( $manual, false );
$manual = wc_get_order( $manual->get_id() );
$check( 'manual order requests full total', $full instanceof Payment && 37.0 === (float) $full->get_total(), is_wp_error( $full ) ? $full->get_error_message() : '' );
$check( 'parent pay link closed while requested', ! $manual->needs_payment(), $manual->get_status() );
wc_get_order( $full->get_id() )->payment_complete();
$manual = wc_get_order( $manual->get_id() );
$check( 'manual order settled and processing', $plugin->balance()->for_order( $manual )->is_settled() && $manual->has_status( 'processing' ), $manual->get_status() );

$native = $later_order( '', 'pending' );
$req    = $plugin->service()->request( $native, false );
$native = wc_get_order( $native->get_id() );
$native->set_status( 'pending' );
$native->save();
$native->payment_complete( 'txn_123' );
$native = wc_get_order( $native->get_id() );
$check( 'native payment cancels open request', wc_get_order( $req->get_id() )->has_status( 'cancelled' ) );
$check( 'native payment settles order', $plugin->balance()->for_order( $native )->is_settled(), $plugin->balance()->for_order( $native ) );

$checkout = $new_paid_order();
$checkout->add_product( $product( 'Mug' ), 1 );
$checkout->calculate_totals();
$check( 'checkout mode still diffs from paid amount', 12.0 === $plugin->balance()->for_order( wc_get_order( $checkout->get_id() ) )->due );

// A waiting payment paid in cash: the same payment is completed, nothing is recorded twice.
$cash_order = $new_paid_order();
$cash_order->add_product( $product( 'Mug' ), 1 );
$cash_order->calculate_totals();
$waiting    = $plugin->service()->request( wc_get_order( $cash_order->get_id() ), false );
$before     = count( $plugin->payments()->for_order( wc_get_order( $cash_order->get_id() ) ) );
$obal_mails = array();
$marked     = $plugin->actions()->run( 'mark_paid', wc_get_order( $waiting->get_id() ), array( 'method' => 'Cash' ), false );
$waiting    = wc_get_order( $waiting->get_id() );
$cash_order = wc_get_order( $cash_order->get_id() );
$check( 'mark paid: same payment completed', ! is_wp_error( $marked ) && $waiting->has_status( 'completed' ) && 'Cash' === $waiting->get_payment_method_title(), is_wp_error( $marked ) ? $marked->get_error_message() : $waiting->get_status() );
$check( 'mark paid: no second payment', count( $plugin->payments()->for_order( $cash_order ) ) === $before );
$check( 'mark paid: balance settled, pay link closed', $plugin->balance()->for_order( $cash_order )->is_settled() && ! $waiting->needs_payment() );
$customer_mails = array_filter(
	$obal_mails,
	static function ( string $mail ) use ( $cash_order ): bool {
		return false !== strpos( $mail, '=> ' . $cash_order->get_billing_email() );
	}
);
$check( 'mark paid: no customer email when unticked', '' !== $cash_order->get_billing_email() && ! $customer_mails, $obal_mails );
$check( 'mark paid: not offered once paid', is_wp_error( $plugin->actions()->run( 'mark_paid', $waiting, array(), false ) ) );
