<?php
/**
 * Test group "compat": other plugins finding or reacting to balance payments.
 * Checks for a plugin run only when that plugin is active on the test site.
 * Included by smoke.php, which provides the helpers and shares its variables.
 *
 * @var callable                                $check          Records a check: label, bool, detail.
 * @var object                                  $plugin         The Order Balance plugin instance.
 * @var callable                                $product        Product by name.
 * @var callable                                $new_paid_order A paid order with one T-shirt.
 * @var array                                   $obal_mails     Subjects and recipients of sent emails.
 */

use ModwellLabs\OrderBalance\Payments\Payment;

$cp = $new_paid_order();
$cp->add_product( $product( 'Mug' ), 1 );
$cp->calculate_totals();
$cpay = wc_get_order( $plugin->service()->request( wc_get_order( $cp->get_id() ), null, false )->get_id() );
$cpay->update_meta_data( '_stripe_intent_id', 'pi_obal_' . $cpay->get_id() );
$cpay->update_meta_data( '_mollie_payment_id', 'tr_obal_' . $cpay->get_id() );
$cpay->set_transaction_id( 'txn_obal_' . $cpay->get_id() );
$cpay->save();

$found = static function ( array $orders ) use ( $cpay ): bool {
	foreach ( $orders as $order ) {
		if ( $order instanceof Payment && $order->get_id() === $cpay->get_id() ) {
			return true;
		}
	}
	return false;
};

// How payment plugins look orders up in their webhooks, without naming a type.
$check( 'lookup by ids (PayPal)', $found( wc_get_orders( array( 'post__in' => array( $cpay->get_id() ), 'limit' => -1 ) ) ) );
$check( 'lookup by transaction id (Mollie)', $found( wc_get_orders( array( 'transaction_id' => 'txn_obal_' . $cpay->get_id(), 'limit' => 2 ) ) ) );
$check( 'lookup by meta key and value (Mollie, PayPal refunds)', $found( wc_get_orders( array( 'limit' => 2, 'meta_key' => '_mollie_payment_id', 'meta_value' => 'tr_obal_' . $cpay->get_id() ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
$check( 'lookup by meta query (Stripe)', $found( wc_get_orders( array( 'limit' => 1, 'meta_query' => array( array( 'key' => '_stripe_intent_id', 'value' => 'pi_obal_' . $cpay->get_id() ) ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery

// Lists stay as they were: no balance payments in them.
$check( 'lists do not show balance payments', ! $found( wc_get_orders( array( 'limit' => -1, 'status' => array_keys( wc_get_order_statuses() ) ) ) ) );
$check( 'a type asked for explicitly is respected', ! $found( wc_get_orders( array( 'type' => 'shop_order', 'post__in' => array( $cpay->get_id() ) ) ) ) );

if ( class_exists( 'WC_Stripe_Helper' ) ) {
	$stripe = WC_Stripe_Helper::get_order_by_intent_id( 'pi_obal_' . $cpay->get_id() );
	$check( 'Stripe finds the payment from its webhook id', $stripe instanceof Payment && $stripe->get_id() === $cpay->get_id() );
}

if ( function_exists( 'wcpdf_get_document' ) ) {
	$cpay->payment_complete();
	$cpay = wc_get_order( $cpay->get_id() );
	$check( 'PDF Invoices: no invoice made for a balance payment', '' === (string) $cpay->get_meta( '_wcpdf_invoice_number' ), $cpay->get_meta( '_wcpdf_invoice_number' ) );
}

wc_get_order( $cp->get_id() )->delete( true );
