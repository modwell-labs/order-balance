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
		add_action( 'wp_ajax_obal_request_payment', array( $this, 'request_payment' ) );
		add_action( 'wp_ajax_obal_update_payment', array( $this, 'update_payment' ) );
		add_action( 'wp_ajax_obal_refund', array( $this, 'refund' ) );
		add_action( 'wp_ajax_obal_adjust', array( $this, 'adjust' ) );
		add_action( 'wp_ajax_obal_resend', array( $this, 'resend' ) );
		add_action( 'wp_ajax_obal_refresh', array( $this, 'refresh' ) );
	}

	/**
	 * Requests the unrequested balance as a new payment.
	 */
	public function request_payment(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'modwell-order-balance-for-woocommerce' ) ), 403 );
		}

		$order  = $this->order( isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0 );
		$notify = ! isset( $_POST['notify'] ) || '1' === sanitize_key( wp_unslash( $_POST['notify'] ) );

		$this->respond( $this->plugin->service()->request( $order, $notify ) );
	}

	/**
	 * Brings the waiting payment in line with the changed order.
	 */
	public function update_payment(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'modwell-order-balance-for-woocommerce' ) ), 403 );
		}

		$order  = $this->order( isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0 );
		$notify = ! isset( $_POST['notify'] ) || '1' === sanitize_key( wp_unslash( $_POST['notify'] ) );

		$this->respond( $this->plugin->service()->update_waiting( $order, $notify ) );
	}

	/**
	 * Refunds the overpaid amount of the order.
	 */
	public function refund(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'modwell-order-balance-for-woocommerce' ) ), 403 );
		}

		$order = $this->order( isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0 );

		$this->respond( $this->plugin->service()->refund( $order ) );
	}

	/**
	 * Absorbs a difference within the rounding threshold.
	 */
	public function adjust(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'modwell-order-balance-for-woocommerce' ) ), 403 );
		}

		$order = $this->order( isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0 );

		$this->respond( $this->plugin->service()->adjust( $order ) );
	}

	/**
	 * Sends the "Order updated" email again.
	 */
	public function resend(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'modwell-order-balance-for-woocommerce' ) ), 403 );
		}

		$order = $this->order( isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0 );
		$open  = $this->plugin->payments()->open( $order );
		$this->plugin->service()->notify( $order, $open ? end( $open ) : null );
		$order->add_order_note( __( 'Order update email sent to the customer.', 'modwell-order-balance-for-woocommerce' ) );

		$this->respond( true );
	}

	/**
	 * Sends back the Balance box for the order as saved now. The order screen calls it after
	 * WooCommerce recalculates or saves items, so the box does not need a page reload.
	 */
	public function refresh(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'modwell-order-balance-for-woocommerce' ) ), 403 );
		}

		$order = $this->order( isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0 );
		wp_send_json_success( array( 'html' => $this->plugin->get( OrderMetabox::class )->html( $order ) ) );
	}

	/**
	 * @param mixed $result A WP_Error, or anything else for success.
	 */
	private function respond( $result ): void {
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
	}

	private function order( int $order_id ): \WC_Order {
		$order = wc_get_order( $order_id );
		if ( ! PaymentRepository::is_parent( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'modwell-order-balance-for-woocommerce' ) ), 404 );
		}
		return $order;
	}
}
