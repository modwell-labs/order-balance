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
 * Applies to the classic "Pay for order" form and to express payment buttons that pay through
 * the Store API (Stripe's Apple Pay, Google Pay and Link buttons): the page adds the amount it
 * showed to their request, and the request is refused if it no longer matches. Buttons that
 * charge without either (for example PayPal's own buttons) show the amount in their window.
 */
final class PayGuard implements Module {

	public const FIELD = 'obal_seen_total';

	/** Namespace of the Store API extension data. */
	public const NAMESPACE = 'order-balance';

	public function __construct( Plugin $plugin ) {}

	public function register( Plugin $plugin ): void {
		add_action( 'woocommerce_pay_order_before_submit', array( $this, 'field' ) );
		add_action( 'woocommerce_before_pay_action', array( $this, 'check' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'script' ) );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->store_api_schema();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'store_api_schema' ) );
		}
		add_filter( 'rest_request_before_callbacks', array( $this, 'check_store_api' ), 10, 3 );
	}

	/**
	 * Adds the shown amount to the extension data that Stripe's express buttons send.
	 */
	public function script(): void {
		$payment = $this->current_payment();
		if ( ! $payment || ! wp_script_is( 'wp-hooks', 'registered' ) ) {
			return;
		}
		wp_register_script( 'obal-pay-guard', false, array( 'wp-hooks' ), ORDER_BALANCE_VERSION, true );		wp_enqueue_script( 'obal-pay-guard' );
		wp_add_inline_script(
			'obal-pay-guard',
			sprintf(
				'wp.hooks.addFilter("wcstripe.express-checkout.cart-place-order-extension-data","order-balance/pay-guard",function(d){d=d||{};d[%1$s]={seen_total:%2$s};return d;});',
				wp_json_encode( self::NAMESPACE ),
				wp_json_encode( self::fingerprint( $payment ) )
			)
		);
	}

	/**
	 * Lets the Store API checkout accept the shown amount as extension data.
	 */
	public function store_api_schema(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => self::NAMESPACE,
				'schema_callback' => static function () {
					return array(
						'seen_total' => array(
							'description' => 'Amount the pay page showed for a balance payment.',
							'type'        => array( 'string', 'null' ),
							'context'     => array(),
						),
					);
				},
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Refuses a Store API "pay for order" request for a balance payment whose amount changed.
	 *
	 * @param mixed            $response
	 * @param array            $handler
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	public function check_store_api( $response, $handler, $request ) {
		if ( is_wp_error( $response ) || ! $request instanceof \WP_REST_Request || 'POST' !== $request->get_method() ) {
			return $response;
		}
		if ( ! preg_match( '#^/wc/store(?:/v\d+)?/checkout/(\d+)$#', $request->get_route(), $m ) ) {
			return $response;
		}
		$order = wc_get_order( (int) $m[1] );
		// Only answer someone who holds the payment's key, like the pay page itself.
		if ( ! $order instanceof Payment || ! hash_equals( $order->get_order_key(), (string) $request->get_param( 'key' ) ) ) {
			return $response;
		}
		$extensions = $request->get_param( 'extensions' );
		$seen       = is_array( $extensions ) && isset( $extensions[ self::NAMESPACE ]['seen_total'] ) ? (string) $extensions[ self::NAMESPACE ]['seen_total'] : '';
		if ( '' === $seen || self::matches( $order, $seen ) ) {
			return $response;
		}

		$order->add_order_note( __( 'Payment stopped: the amount changed while the customer was on the pay page.', 'order-balance' ) );
		return new \WP_Error(
			'obal_amount_changed',
			sprintf(
				/* translators: %s: new amount */
				__( 'The amount of this payment has just changed to %s. Please reload the page and pay again.', 'order-balance' ),
				wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) )
			),
			array( 'status' => 409 )
		);
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
