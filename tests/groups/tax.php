<?php
/**
 * Test group "tax". Included by smoke.php, which provides the helpers and shares its variables.
 *
 * @var callable $check   Records a check: label, bool, detail.
 * @var object   $plugin  The Order Balance plugin instance.
 * @var callable $product Product by name.
 */

use ModwellLabs\OrderBalance\Payments\Payment;

// Taxes: the balance is the gross difference, and a balance payment never adds tax of its own.
update_option( 'woocommerce_calc_taxes', 'yes' );
$tax_rate = WC_Tax::_insert_tax_rate(
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
// smoke.php makes the test products tax free; here they are taxable again.
remove_filter( 'woocommerce_product_get_tax_status', $obal_tax_free, 99 );
remove_filter( 'woocommerce_product_variation_get_tax_status', $obal_tax_free, 99 );
$obal_only_rate = $tax_rate;
$taxable   = static function ( string $name ) use ( $product ) {
	return $product( $name );
};
$tax_fresh = static function ( $order ) {
	return wc_get_order( $order->get_id() );
};

foreach ( array( 'no', 'yes' ) as $tax_incl ) {
	update_option( 'woocommerce_prices_include_tax', $tax_incl );
	$tag = 'yes' === $tax_incl ? 'tax inclusive' : 'tax exclusive';

	$o = wc_create_order();
	$o->set_billing_email( 'customer@example.com' );
	$o->set_payment_method( 'stripe' );
	$o->add_product( $taxable( 'T-Shirt' ), 1 );
	$o->calculate_totals();
	$o->payment_complete( 'txn_tax' );
	$o          = $tax_fresh( $o );
	$paid_total = (float) $o->get_total();
	$o->add_product( $taxable( 'Mug' ), 1 );
	$o->calculate_totals();
	$o    = $tax_fresh( $o );
	$diff = round( (float) $o->get_total() - $paid_total, 2 );
	$b    = $plugin->balance()->for_order( $o );
	$check( "{$tag}: order carries tax", (float) $o->get_total_tax() > 0, $o->get_total_tax() );
	$check( "{$tag}: balance due is the gross difference", abs( $b->due - $diff ) < 0.005, array( $b->due, $diff ) );

	$req = $tax_fresh( $plugin->service()->request( $o, false ) );
	$check( "{$tag}: payment equals the difference, no tax on top", abs( (float) $req->get_total() - $diff ) < 0.005 && 0.0 === (float) $req->get_total_tax(), array( $req->get_total(), $req->get_total_tax() ) );

	// A taxable surcharge from the payment method is fees, not balance.
	$fee = new WC_Order_Item_Fee();
	$fee->set_name( 'Card surcharge' );
	$fee->set_amount( '2.00' );
	$fee->set_total( '2.00' );
	$fee->set_tax_status( 'taxable' );
	$req->add_item( $fee );
	$req->set_prices_include_tax( false );
	$req->calculate_taxes();
	$req->calculate_totals( false );
	$req->save();
	$req = $tax_fresh( $req );
	$b   = $plugin->balance()->for_order( $tax_fresh( $o ) );
	$check( "{$tag}: surcharge with its tax counts as fees", abs( $req->get_amount() - $diff ) < 0.005 && abs( $req->get_fees_part() - 2.4 ) < 0.005, array( $req->get_amount(), $req->get_fees_part() ) );
	$check( "{$tag}: surcharge not counted as requested", abs( $b->unrequested() ) < 0.005, $b );
	$req->payment_complete();
	$b = $plugin->balance()->for_order( $tax_fresh( $o ) );
	$check( "{$tag}: paid with surcharge, order settled and not overpaid", $b->is_settled(), $b );

	// The order goes down again: overpaid by the gross amount.
	$o = $tax_fresh( $o );
	foreach ( $o->get_items() as $item ) {
		if ( $item->get_product_id() === $taxable( 'Mug' )->get_id() ) {
			$o->remove_item( $item->get_id() );
			break;
		}
	}
	$o->calculate_totals();
	$b = $plugin->balance()->for_order( $tax_fresh( $o ) );
	$check( "{$tag}: removing the item overpays by the gross amount", $b->is_overpaid() && abs( abs( $b->due ) - $diff ) < 0.005, array( $b->due, $diff ) );
}

$obal_only_rate = null;
WC_Tax::_delete_tax_rate( $tax_rate );
add_filter( 'woocommerce_product_get_tax_status', $obal_tax_free, 99 );
add_filter( 'woocommerce_product_variation_get_tax_status', $obal_tax_free, 99 );
