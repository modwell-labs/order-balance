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

$cash = $plugin->service()->record( $cod, 10.0, 'Cash' );
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
$full   = $plugin->service()->request( $manual, null, false );
$manual = wc_get_order( $manual->get_id() );
$check( 'manual order requests full total', $full instanceof Payment && 37.0 === (float) $full->get_total(), is_wp_error( $full ) ? $full->get_error_message() : '' );
$check( 'parent pay link closed while requested', ! $manual->needs_payment(), $manual->get_status() );
wc_get_order( $full->get_id() )->payment_complete();
$manual = wc_get_order( $manual->get_id() );
$check( 'manual order settled and processing', $plugin->balance()->for_order( $manual )->is_settled() && $manual->has_status( 'processing' ), $manual->get_status() );

$native = $later_order( '', 'pending' );
$req    = $plugin->service()->request( $native, null, false );
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
