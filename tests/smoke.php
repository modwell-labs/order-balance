<?php
/**
 * Run: wp eval-file wp-content/plugins/order-balance/tests/smoke.php
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

$plugin  = Plugin::instance();
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

echo 'Storage: ' . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'legacy' ) . "\n";

$order = $new_paid_order();
$check( 'initial paid captured', '25.00' === (string) $order->get_meta( BalanceCalculator::INITIAL_META ), $order->get_meta( BalanceCalculator::INITIAL_META ) );

$order->add_product( $product( 'Mug' ), 1 );
$order->calculate_totals();
$order = wc_get_order( $order->get_id() );
$b     = $plugin->balance()->for_order( $order );
$check( 'initial paid kept after edit', '25.00' === (string) $order->get_meta( BalanceCalculator::INITIAL_META ) );
$check( 'balance due 12', 12.0 === $b->due, $b );

$limit = static function () {
	return 1;
};
add_filter( 'obal_max_extra_payments', $limit, 99 );

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
$check( 'free limit blocks second payment', is_wp_error( $second ) && 'obal_limit' === $second->get_error_code() );
remove_filter( 'obal_max_extra_payments', $limit, 99 );

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

foreach ( $order->get_items() as $item ) {
	if ( 'Mug' === $item->get_name() ) {
		$item->set_total( '12.40' );
		$item->set_subtotal( '12.40' );
		$item->save();
	}
}
$order->calculate_totals();
$order = wc_get_order( $order->get_id() );
$b     = $plugin->balance()->for_order( $order );
$adj   = 0;
foreach ( $order->get_fees() as $fee ) {
	if ( $fee->get_meta( '_obal_adjustment' ) ) {
		$adj = (float) $fee->get_total();
	}
}
$check( 'auto rounding adjustment -0.40', -0.4 === $adj, $adj );
$check( 'settled after rounding', $b->is_settled(), $b );

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

require_once ABSPATH . 'wp-admin/includes/admin.php';
include_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
wp_set_current_user( 1 );
set_current_screen( 'toplevel_page_order-balance' );

$table = new \ModwellLabs\OrderBalance\Admin\PaymentsTable( $plugin );
$table->prepare_items();
$check( 'payments table lists payments', count( $table->items ) > 0, count( $table->items ) );

$page = $plugin->get( \ModwellLabs\OrderBalance\Admin\AdminPage::class );
foreach ( array( 'payments', 'settings', 'emails', 'about' ) as $tab ) {
	$_GET['tab'] = $tab;
	ob_start();
	$page->render();
	$html = ob_get_clean();
	$check( "admin tab {$tab} renders", false !== strpos( $html, 'nav-tab-active' ) && false === stripos( $html, 'fatal' ) );
}
$_GET['tab']     = 'payments';
$_GET['payment'] = $open->get_id();
ob_start();
$page->render();
$html = ob_get_clean();
$check( 'payment screen renders with refund form', false !== strpos( $html, 'name="obal_action" value="refund"' ) );
unset( $_GET['payment'], $_GET['tab'] );

$check( 'payment edit url points to plugin page', false !== strpos( $open->get_edit_order_url(), 'page=order-balance' ) );

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
$check( 'recorded payment not counted in limit', 0 === $plugin->payments()->active_count( $cod ) );

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

echo $obal_failures ?"\n{$obal_failures} FAILED\n" : "\nALL PASSED\n";
