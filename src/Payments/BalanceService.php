<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

final class BalanceService {

	public const ADJUSTMENT_META = '_obal_adjustment';
	public const PREVIOUS_STATUS = '_obal_previous_status';

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function max_payments( \WC_Order $order ): int {
		return max( 0, (int) apply_filters( 'obal_max_extra_payments', 1, $order ) );
	}

	public function can_request( \WC_Order $order ): bool {
		$max = $this->max_payments( $order );
		return 0 === $max || $this->plugin->payments()->active_count( $order ) < $max;
	}

	/**
	 * @return Payment|\WP_Error
	 */
	public function request( \WC_Order $order, ?float $amount = null, bool $notify = true ) {
		$balance = $this->plugin->balance()->for_order( $order );

		if ( ! $balance->tracked ) {
			return new \WP_Error( 'obal_not_paid', __( 'The order has not been paid yet.', 'order-balance' ) );
		}
		if ( ! $this->can_request( $order ) ) {
			return new \WP_Error( 'obal_limit', __( 'The extra payment limit for this order has been reached.', 'order-balance' ) );
		}

		$available = $balance->unrequested();
		$amount    = null === $amount ? $available : round( $amount, wc_get_price_decimals() );

		if ( $available <= 0 || $amount <= 0 ) {
			return new \WP_Error( 'obal_nothing_due', __( 'There is no unrequested balance on this order.', 'order-balance' ) );
		}
		if ( $amount > $available ) {
			return new \WP_Error( 'obal_too_much', __( 'The amount is higher than the unrequested balance.', 'order-balance' ) );
		}

		$this->plugin->settings()->lock_mode( $order );
		$payment = $this->plugin->payments()->create( $order, $amount, (string) apply_filters( 'obal_payment_label', '', $order, $amount ) );

		$order->add_order_note(
			sprintf(
				/* translators: 1: amount, 2: payment number */
				__( 'Balance payment of %1$s requested (#%2$s).', 'order-balance' ),
				wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ),
				$payment->get_order_number()
			)
		);

		$this->mark_partial( $order );

		do_action( 'obal_payment_requested', $payment, $order );

		if ( $notify ) {
			$this->notify( $order, $payment );
		}

		return $payment;
	}

	/**
	 * @return Payment|\WP_Error
	 */
	public function record( \WC_Order $order, float $amount, string $method_title = '', string $transaction_id = '', string $note = '' ) {
		$amount  = round( $amount, wc_get_price_decimals() );
		$balance = $this->plugin->balance()->for_order( $order );

		if ( ! $balance->tracked ) {
			return new \WP_Error( 'obal_not_tracked', __( 'Payments cannot be recorded for this order.', 'order-balance' ) );
		}
		if ( $amount <= 0 || $amount > $balance->due ) {
			return new \WP_Error( 'obal_record_amount', __( 'Enter an amount between 0 and the balance due.', 'order-balance' ) );
		}

		$this->plugin->settings()->lock_mode( $order );

		$title = '' !== $method_title ? $method_title : ( $order->get_payment_method_title() ? $order->get_payment_method_title() : __( 'Manual payment', 'order-balance' ) );

		$payment = $this->plugin->payments()->create(
			$order,
			$amount,
			/* translators: %s: order number */
			sprintf( __( 'Payment for order #%s', 'order-balance' ), $order->get_order_number() )
		);
		$payment->update_meta_data( '_obal_recorded', 1 );
		$payment->set_payment_method( $order->get_payment_method() );
		$payment->set_payment_method_title( $title );
		if ( '' !== $transaction_id ) {
			$payment->set_transaction_id( $transaction_id );
		}
		$payment->save();

		$payment->update_status(
			'completed',
			'' !== $note ? $note : __( 'Payment recorded by the store.', 'order-balance' )
		);

		$order = wc_get_order( $order->get_id() );
		if ( $order && $this->plugin->balance()->for_order( $order )->is_owed() ) {
			$this->mark_partial( $order );
		}

		do_action( 'obal_payment_recorded', $payment, $order );

		return $payment;
	}

	public function notify( \WC_Order $order, ?Payment $payment = null ): void {
		WC()->mailer();
		do_action( 'obal_order_updated_notification', $order->get_id(), $payment ? $payment->get_id() : 0 );
	}

	/**
	 * @return \WC_Order_Refund|\WP_Error
	 */
	public function refund( \WC_Order $order ) {
		$balance = $this->plugin->balance()->for_order( $order );
		if ( ! $balance->is_overpaid() ) {
			return new \WP_Error( 'obal_not_overpaid', __( 'The order is not overpaid.', 'order-balance' ) );
		}

		$amount    = abs( $balance->due );
		$available = (float) $order->get_total() - (float) $order->get_total_refunded();
		if ( $amount > $available ) {
			return new \WP_Error( 'obal_refund_limit', __( 'The refund is larger than the remaining order total. Refund it manually in your payment provider.', 'order-balance' ) );
		}

		$gateway   = wc_get_payment_gateway_by_order( $order );
		$automatic = $gateway && $gateway->supports( 'refunds' );

		$refund = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => wc_format_decimal( $amount ),
				'reason'         => __( 'Order balance refund', 'order-balance' ),
				'refund_payment' => (bool) apply_filters( 'obal_refund_via_gateway', $automatic, $order ),
				'restock_items'  => false,
			)
		);

		if ( is_wp_error( $refund ) ) {
			return $refund;
		}

		$refund->update_meta_data( BalanceCalculator::REFUND_META, 1 );
		$refund->save();

		if ( ! $automatic ) {
			$order->add_order_note( __( 'The payment method does not support automatic refunds. Send the money back manually.', 'order-balance' ) );
		}

		do_action( 'obal_balance_refunded', $refund, $order );

		return $refund;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function adjust( \WC_Order $order, bool $force = false ) {
		$balance   = $this->plugin->balance()->for_order( $order );
		$threshold = $this->plugin->settings()->threshold();

		if ( $balance->is_settled() ) {
			return true;
		}
		if ( ! $force && ! $balance->within( $threshold ) ) {
			return new \WP_Error( 'obal_above_threshold', __( 'The difference is above the rounding threshold.', 'order-balance' ) );
		}

		$delta = -$balance->due;
		$item  = $this->adjustment_item( $order );

		if ( $item ) {
			$total = round( (float) $item->get_total() + $delta, wc_get_price_decimals() );
			if ( 0.0 === $total ) {
				$order->remove_item( $item->get_id() );
			} else {
				$item->set_amount( wc_format_decimal( $total ) );
				$item->set_total( wc_format_decimal( $total ) );
			}
		} else {
			$item = new \WC_Order_Item_Fee();
			$item->set_name( $this->plugin->settings()->adjustment_label() );
			$item->set_amount( wc_format_decimal( $delta ) );
			$item->set_total( wc_format_decimal( $delta ) );
			$item->set_tax_status( 'none' );
			$item->set_tax_class( '' );
			$item->add_meta_data( self::ADJUSTMENT_META, 1, true );
			$order->add_item( $item );
		}

		$order->calculate_totals( false );
		$order->add_order_note(
			sprintf(
				/* translators: %s: amount */
				__( 'Rounding adjustment of %s applied.', 'order-balance' ),
				wp_strip_all_tags( wc_price( $delta, array( 'currency' => $order->get_currency() ) ) )
			)
		);

		do_action( 'obal_adjustment_applied', $order, $delta );

		return true;
	}

	public function mark_partial( \WC_Order $order ): void {
		if ( ! $this->plugin->settings()->enabled( 'partial_status' ) || $order->has_status( PaymentType::STATUS ) ) {
			return;
		}
		$order->update_meta_data( self::PREVIOUS_STATUS, $order->get_status() );
		$order->set_status( PaymentType::STATUS, __( 'Waiting for the balance payment.', 'order-balance' ) );
		$order->save();
	}

	public function release_partial( \WC_Order $order ): void {
		if ( ! $order->has_status( PaymentType::STATUS ) ) {
			return;
		}
		$previous = (string) $order->get_meta( self::PREVIOUS_STATUS );
		$order->delete_meta_data( self::PREVIOUS_STATUS );
		$order->set_status( $this->settled_status( $order, $previous ), __( 'Balance settled.', 'order-balance' ) );
		$order->save();
	}

	public function apply_paid_status( \WC_Order $order ): void {
		$target = $this->plugin->settings()->paid_status();
		if ( 'previous' === $target || $order->has_status( $target ) ) {
			return;
		}
		$order->set_status( $this->settled_status( $order, $order->get_status() ), __( 'Balance paid.', 'order-balance' ) );
		$order->save();
	}

	private function settled_status( \WC_Order $order, string $previous ): string {
		$target = $this->plugin->settings()->paid_status();

		if ( 'previous' === $target ) {
			$unpaid = array( '', PaymentType::STATUS, 'pending', 'failed', 'on-hold', 'checkout-draft' );
			$target = in_array( $previous, $unpaid, true ) ? ( $order->needs_processing() ? 'processing' : 'completed' ) : $previous;
		}

		return (string) apply_filters( 'obal_settled_status', $target, $order, $previous );
	}

	private function adjustment_item( \WC_Order $order ): ?\WC_Order_Item_Fee {
		foreach ( $order->get_fees() as $fee ) {
			if ( $fee->get_meta( self::ADJUSTMENT_META ) ) {
				return $fee;
			}
		}
		return null;
	}
}
