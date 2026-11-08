<?php
/**
 * @var WC_Order                                  $order
 * @var ModwellLabs\OrderBalance\Payments\Payment $payment
 * @var string                                    $message
 * @var bool                                      $show_pay_button
 * @var string                                    $email_heading
 * @var string                                    $additional_content
 * @var bool                                      $sent_to_admin
 * @var WC_Email                                  $email
 */

defined( 'ABSPATH' ) || exit;

$obal_currency = array( 'currency' => $payment->get_currency() );

echo "=\n" . esc_html( wp_strip_all_tags( $email_heading ) ) . "\n=\n\n";

if ( ! $sent_to_admin ) {
	/* translators: %s: payer first name */
	echo sprintf( esc_html__( 'Hi %s,', 'order-balance' ), esc_html( $payment->get_billing_first_name() ) ) . "\n\n";
}

echo esc_html( wp_strip_all_tags( $message ) ) . "\n\n";

echo esc_html__( 'Payment', 'order-balance' ) . ': #' . esc_html( $payment->get_order_number() ) . "\n";
echo esc_html__( 'Order', 'order-balance' ) . ': #' . esc_html( $order->get_order_number() ) . "\n";
echo esc_html__( 'Amount', 'order-balance' ) . ': ' . esc_html( wp_strip_all_tags( wc_price( $payment->get_total(), $obal_currency ) ) ) . "\n";

if ( $payment->get_total_refunded() > 0 ) {
	echo esc_html__( 'Refunded', 'order-balance' ) . ': ' . esc_html( wp_strip_all_tags( wc_price( $payment->get_total_refunded(), $obal_currency ) ) ) . "\n";
}

if ( $sent_to_admin ) {
	echo esc_html__( 'Payer', 'order-balance' ) . ': ' . esc_html( trim( $payment->get_formatted_billing_full_name() . ' ' . $payment->get_billing_email() ) ) . "\n";
	echo esc_html__( 'View payment', 'order-balance' ) . ': ' . esc_url_raw( $payment->get_edit_order_url() ) . "\n";
}

if ( $show_pay_button && $payment->needs_payment() ) {
	echo "\n" . esc_html__( 'Pay now:', 'order-balance' ) . ' ' . esc_url_raw( $payment->get_checkout_payment_url() ) . "\n";
}

echo "\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
