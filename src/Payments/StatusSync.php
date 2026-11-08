<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

final class StatusSync implements Module {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'complete_status' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 10, 4 );
		add_action( 'woocommerce_trash_order', array( $this, 'trash_children' ) );
		add_action( 'wp_trash_post', array( $this, 'trash_children' ) );
		add_action( 'woocommerce_before_delete_order', array( $this, 'delete_children' ) );
		add_action( 'before_delete_post', array( $this, 'delete_children' ) );
	}

	public function complete_status( $status, $order_id, $order = null ) {
		$order = $order ? $order : wc_get_order( $order_id );
		return $order instanceof Payment ? 'completed' : $status;
	}

	public function status_changed( $order_id, $from, $to, $order ): void {
		if ( $order instanceof Payment ) {
			$this->payment_changed( $order, $from, $to );
			return;
		}
		if ( PaymentRepository::is_parent( $order ) && 'cancelled' === $to ) {
			foreach ( $this->plugin->payments()->open( $order ) as $payment ) {
				$payment->update_status( 'cancelled', __( 'Parent order cancelled.', 'modwell-order-balance-for-woocommerce' ) );
			}
		}
	}

	public function trash_children( $id ): void {
		$order = wc_get_order( $id );
		if ( ! PaymentRepository::is_parent( $order ) ) {
			return;
		}
		foreach ( $this->plugin->payments()->open( $order ) as $payment ) {
			$payment->delete( false );
		}
	}

	public function delete_children( $id ): void {
		$order = wc_get_order( $id );
		if ( ! PaymentRepository::is_parent( $order ) ) {
			return;
		}
		$payments = $this->plugin->payments()->for_order( $order, array_merge( array_keys( wc_get_order_statuses() ), array( 'trash' ) ) );
		foreach ( $payments as $payment ) {
			$payment->delete( true );
		}
	}

	private function payment_changed( Payment $payment, string $from, string $to ): void {
		$parent = $payment->get_parent_order();
		if ( ! $parent ) {
			return;
		}

		$paid = wc_get_is_paid_statuses();

		if ( in_array( $to, $paid, true ) && ! in_array( $from, $paid, true ) ) {
			$currency = array( 'currency' => $payment->get_currency() );
			$note     = sprintf(
				/* translators: 1: payment number, 2: amount */
				__( 'Balance payment #%1$s received: %2$s.', 'modwell-order-balance-for-woocommerce' ),
				$payment->get_order_number(),
				wp_strip_all_tags( wc_price( $payment->get_total(), $currency ) )
			);
			if ( $payment->get_fees_part() > 0 ) {
				$note .= ' ' . sprintf(
					/* translators: 1: amount credited to the order, 2: fees */
					__( '%1$s counts toward the balance, %2$s is fees.', 'modwell-order-balance-for-woocommerce' ),
					wp_strip_all_tags( wc_price( $payment->get_balance_part(), $currency ) ),
					wp_strip_all_tags( wc_price( $payment->get_fees_part(), $currency ) )
				);
			}
			$parent->add_order_note( $note );
			do_action( 'obal_payment_paid', $payment, $parent );
		}

		$this->refresh( $parent );

		if ( in_array( $to, $paid, true ) && ! $payment->is_recorded() ) {
			$parent = wc_get_order( $parent->get_id() );
			if ( $parent && ! $this->plugin->payments()->open( $parent ) && $this->plugin->balance()->for_order( $parent )->is_settled() ) {
				$this->plugin->service()->apply_paid_status( $parent );
			}
		}
	}

	public function refresh( \WC_Order $parent ): void {
		$balance = $this->plugin->balance()->for_order( $parent );
		$open    = $this->plugin->payments()->open( $parent );

		if ( ! $open && ( $balance->is_settled() || $balance->is_overpaid() ) ) {
			$this->plugin->service()->release_partial( $parent );
		}

		do_action( 'obal_balance_refreshed', $parent, $balance );
	}
}
