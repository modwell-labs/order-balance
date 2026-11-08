<?php

namespace ModwellLabs\OrderBalance\Frontend;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Stops a balance payment from being charged at an amount the customer did not see.
 *
 * The store can change a waiting payment while the customer has its pay page open. The page
 * remembers the total it showed; if the total is different when the customer submits, the
 * payment is not processed and the page reloads with the new amount and a notice.
 *
 * Applies to the classic "Pay for order" form. Payment buttons that charge without
 * submitting this form (for example PayPal's own buttons) show the amount in their window.
 */
final class PayGuard implements Module {

	public const FIELD = 'obal_seen_total';

	public function __construct( Plugin $plugin ) {}

	public function register( Plugin $plugin ): void {
		add_action( 'woocommerce_pay_order_before_submit', array( $this, 'field' ) );
		add_action( 'woocommerce_before_pay_action', array( $this, 'check' ) );
	}

	public function field(): void {
		$payment = $this->current_payment();
		if ( $payment ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( self::FIELD ), esc_attr( self::fingerprint( $payment ) ) );
		}
	}

	/**
	 * @param \WC_Order $order
	 */
	public function check( $order ): void {
		if ( ! $order instanceof Payment || ! isset( $_POST[ self::FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checked the pay nonce.
			return;
		}
		$seen = sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( self::matches( $order, $seen ) ) {
			return;
		}

		wc_add_notice(
			sprintf(
				/* translators: %s: new amount */
				__( 'The amount of this payment has just changed to %s. Please check it and pay again.', 'order-balance' ),
				wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) )
			),
			'error'
		);
		$order->add_order_note( __( 'Payment stopped: the amount changed while the customer was on the pay page. The page was reloaded with the new amount.', 'order-balance' ) );

		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	/**
	 * What the pay page showed: the payment total in the order currency.
	 */
	public static function fingerprint( \WC_Order $order ): string {
		return $order->get_currency() . ':' . wc_format_decimal( $order->get_total(), wc_get_price_decimals() );
	}

	public static function matches( \WC_Order $order, string $seen ): bool {
		return hash_equals( self::fingerprint( $order ), $seen );
	}

	private function current_payment(): ?Payment {
		global $wp;
		if ( empty( $wp->query_vars['order-pay'] ) ) {
			return null;
		}
		$order = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
		return $order instanceof Payment ? $order : null;
	}
}
