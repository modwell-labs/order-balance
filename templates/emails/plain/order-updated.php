<?php
/**
 * @var WC_Order                                       $order
 * @var ModwellLabs\OrderBalance\Payments\Payment|null $payment
 * @var ModwellLabs\OrderBalance\Payments\Balance      $balance
 * @var string                                         $email_heading
 * @var string                                         $additional_content
 * @var bool                                           $sent_to_admin
 * @var bool                                           $plain_text
 * @var WC_Email                                       $email
 */

defined( 'ABSPATH' ) || exit;

$obal_currency    = array( 'currency' => $order->get_currency() );
$obal_third_party = $payment && 0 !== strcasecmp( $payment->get_billing_email(), $order->get_billing_email() );

echo "=\n" . esc_html( wp_strip_all_tags( $email_heading ) ) . "\n=\n\n";

/* translators: %s: customer first name */
echo sprintf( esc_html__( 'Hi %s,', 'order-balance' ), esc_html( $payment ? $payment->get_billing_first_name() : $order->get_billing_first_name() ) ) . "\n\n";
/* translators: %s: order number */
echo sprintf( esc_html__( 'Your order #%s has been updated. The current details are below.', 'order-balance' ), esc_html( $order->get_order_number() ) ) . "\n\n";

if ( $payment && $payment->needs_payment() ) {
	/* translators: %s: amount */
	echo sprintf( esc_html__( 'An additional payment of %s is due.', 'order-balance' ), esc_html( wp_strip_all_tags( wc_price( $payment->get_total(), $obal_currency ) ) ) ) . "\n";
	echo esc_html__( 'Pay now:', 'order-balance' ) . ' ' . esc_url_raw( $payment->get_checkout_payment_url() ) . "\n\n";
}

if ( ! $obal_third_party ) {
	do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook.
}

echo "\n" . esc_html__( 'Paid so far', 'order-balance' ) . ': ' . esc_html( wp_strip_all_tags( wc_price( $balance->paid, $obal_currency ) ) ) . "\n";
echo ( $balance->is_overpaid() ? esc_html__( 'To be refunded', 'order-balance' ) : esc_html__( 'Balance due', 'order-balance' ) ) . ': ' . esc_html( wp_strip_all_tags( wc_price( abs( $balance->due ), $obal_currency ) ) ) . "\n\n";

do_action( 'obal_email_after_summary', $order, $balance, $plain_text, $email );
if ( ! $obal_third_party ) {
	do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook.
}

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook.
