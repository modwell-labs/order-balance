<?php

namespace ModwellLabs\OrderBalance\Admin;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Payments\PaymentRepository;
use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

final class Ajax implements Module {

	public const NONCE = 'obal-admin';

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		$actions = apply_filters(
			'obal_admin_actions',
			array(
				'request_payment' => array( $this, 'request_payment' ),
				'refund'          => array( $this, 'refund' ),
				'adjust'          => array( $this, 'adjust' ),
				'update_payment'  => array( $this, 'update_payment' ),
				'resend'          => array( $this, 'resend' ),
			)
		);

		foreach ( $actions as $name => $callback ) {
			add_action(
				'wp_ajax_obal_' . $name,
				function () use ( $callback ) {
					$this->dispatch( $callback );
				}
			);
		}

		add_action( 'wp_ajax_obal_refresh', array( $this, 'refresh' ) );
	}

	/**
	 * Sends back the Balance box for the order as saved now. The order screen calls it after
	 * WooCommerce recalculates or saves items, so the box does not need a page reload.
	 */
	public function refresh(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		$this->authorize();

		$order = $this->order( isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0 );
		wp_send_json_success( array( 'html' => $this->plugin->get( OrderMetabox::class )->html( $order ) ) );
	}

	/**
	 * Checks the nonce and the capability, then reads the posted fields and runs the action.
	 */
	private function dispatch( callable $callback ): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		$this->authorize();

		$order = $this->order( isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0 );
		$input = array(
			'notify' => ! isset( $_POST['notify'] ) || '1' === sanitize_key( wp_unslash( $_POST['notify'] ) ),
		);

		$result = call_user_func( $callback, $order, $input );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	private function authorize(): void {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'modwell-order-balance-for-woocommerce' ) ), 403 );
		}
	}

	private function order( int $order_id ): \WC_Order {
		$order = wc_get_order( $order_id );
		if ( ! PaymentRepository::is_parent( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'modwell-order-balance-for-woocommerce' ) ), 404 );
		}
		return $order;
	}

	/**
	 * Requests the unrequested balance as a new payment.
	 */
	public function request_payment( \WC_Order $order, array $input ) {
		return $this->plugin->service()->request( $order, $input['notify'] );
	}

	public function refund( \WC_Order $order ) {
		return $this->plugin->service()->refund( $order );
	}

	public function update_payment( \WC_Order $order, array $input ) {
		return $this->plugin->service()->update_waiting( $order, $input['notify'] );
	}

	public function adjust( \WC_Order $order ) {
		return $this->plugin->service()->adjust( $order );
	}

	public function resend( \WC_Order $order ) {
		$open = $this->plugin->payments()->open( $order );
		$this->plugin->service()->notify( $order, $open ? end( $open ) : null );
		$order->add_order_note( __( 'Order update email sent to the customer.', 'modwell-order-balance-for-woocommerce' ) );
		return true;
	}
}
