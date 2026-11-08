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
				'resend'          => array( $this, 'resend' ),
				'record'          => array( $this, 'record' ),
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
	}

	private function dispatch( callable $callback ): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'order-balance' ) ), 403 );
		}

		$order = wc_get_order( isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0 );
		if ( ! PaymentRepository::is_parent( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'order-balance' ) ), 404 );
		}

		$result = call_user_func( $callback, $order );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	public function request_payment( \WC_Order $order ) {
		$raw    = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$amount = '' !== $raw ? (float) wc_format_decimal( $raw ) : null;
		$notify = ! isset( $_POST['notify'] ) || '1' === $_POST['notify']; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		return $this->plugin->service()->request( $order, $amount, $notify );
	}

	public function record( \WC_Order $order ) {
		$amount = isset( $_POST['record_amount'] ) ? (float) wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['record_amount'] ) ) ) : 0.0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$method = isset( $_POST['record_method'] ) ? sanitize_text_field( wp_unslash( $_POST['record_method'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		return $this->plugin->service()->record( $order, $amount, $method );
	}

	public function refund( \WC_Order $order ) {
		return $this->plugin->service()->refund( $order );
	}

	public function adjust( \WC_Order $order ) {
		return $this->plugin->service()->adjust( $order );
	}

	public function resend( \WC_Order $order ) {
		$open = $this->plugin->payments()->open( $order );
		$this->plugin->service()->notify( $order, $open ? end( $open ) : null );
		$order->add_order_note( __( 'Order update email sent to the customer.', 'order-balance' ) );
		return true;
	}
}
