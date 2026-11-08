<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Settings;

defined( 'ABSPATH' ) || exit;

final class LaterPayments implements Module {

	private const PAID_AT = array(
		Settings::MODE_PROCESSING => array( 'processing', 'completed' ),
		Settings::MODE_COMPLETED  => array( 'completed' ),
	);

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 20, 4 );
		add_action( 'woocommerce_payment_complete', array( $this, 'paid_online' ), 20 );
	}

	public function status_changed( $order_id, $from, $to, $order ): void {
		if ( ! PaymentRepository::is_parent( $order ) ) {
			return;
		}

		$mode    = $this->plugin->settings()->order_mode( $order );
		$targets = isset( self::PAID_AT[ $mode ] ) ? self::PAID_AT[ $mode ] : array();

		if ( in_array( $to, $targets, true ) && ! in_array( $from, $targets, true ) ) {
			$this->settle( $order, __( 'Order marked as paid.', 'modwell-order-balance-for-woocommerce' ) );
		}
	}

	public function paid_online( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( PaymentRepository::is_parent( $order ) && Settings::is_later( $this->plugin->settings()->order_mode( $order ) ) ) {
			$this->settle( $order, __( 'Order paid online.', 'modwell-order-balance-for-woocommerce' ) );
		}
	}

	private function settle( \WC_Order $order, string $reason ): void {
		$balance = $this->plugin->balance()->for_order( $order );
		if ( ! $balance->is_owed() ) {
			return;
		}

		foreach ( $this->plugin->payments()->open( $order ) as $payment ) {
			$payment->update_status( 'cancelled', __( 'The order was paid in full another way.', 'modwell-order-balance-for-woocommerce' ) );
		}

		$balance = $this->plugin->balance()->for_order( $order );
		if ( $balance->is_owed() ) {
			$this->plugin->service()->record( $order, $balance->due, '', (string) $order->get_transaction_id(), $reason );
		}
	}
}
