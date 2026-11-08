<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

final class BalanceService {

	public const ADJUSTMENT_META = '_obal_adjustment';
	public const PREVIOUS_STATUS = '_obal_previous_status';

	/** Order total when waiting payments last matched the order (set on request and update). */
	public const REQUEST_TOTAL_META = '_obal_request_total';

	/** The last "Order updated" email: payment, amount, time. */
	public const LAST_NOTICE_META = '_obal_last_notice';

	/** What happens to a waiting payment when the order changes: button, auto (Pro) or new (Pro). */
	public const WAITING_BUTTON = 'button';
	public const WAITING_AUTO   = 'auto';
	public const WAITING_NEW    = 'new';

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function max_payments( \WC_Order $order ): int {
		return max( 0, (int) apply_filters( 'obal_max_extra_payments', 1, $order ) );
	}

	public function is_limited( \WC_Order $order ): bool {
		return $this->max_payments( $order ) > 0;
	}

	public function used_payments( \WC_Order $order ): int {
		return $this->plugin->payments()->used_count( $order );
	}

	public function limit_reached( \WC_Order $order ): bool {
		return $this->is_limited( $order ) && $this->used_payments( $order ) >= $this->max_payments( $order );
	}

	/**
	 * A new payment can be requested. With a limit (the free version) there is one payment
	 * at a time: while one is waiting, changes go into it with update_waiting() instead.
	 */
	public function can_request( \WC_Order $order ): bool {
		if ( ! $this->is_limited( $order ) ) {
			return true;
		}
		return ! $this->limit_reached( $order ) && ! $this->plugin->payments()->open( $order );
	}

	public function waiting_mode( \WC_Order $order ): string {
		$mode = (string) apply_filters( 'obal_waiting_mode', self::WAITING_BUTTON, $order );
		return in_array( $mode, array( self::WAITING_BUTTON, self::WAITING_AUTO, self::WAITING_NEW ), true ) ? $mode : self::WAITING_BUTTON;
	}

	/**
	 * How much the order total has changed since its waiting payments were requested or last
	 * updated. Zero when nothing is waiting.
	 */
	public function waiting_change( \WC_Order $order ): float {
		if ( ! $this->plugin->payments()->waiting( $order ) ) {
			return 0.0;
		}
		$saved = (string) $order->get_meta( self::REQUEST_TOTAL_META );
		if ( '' !== $saved ) {
			$change = (float) $order->get_total() - (float) $saved;
		} else {
			// Payments requested before the total was saved: compare with what is requested.
			$balance = $this->plugin->balance()->for_order( $order );
			$change  = $balance->due - $balance->requested;
		}
		return round( $change, wc_get_price_decimals() );
	}

	/**
	 * What update_waiting() would do now: payment => [old amount, new amount]. A new amount of
	 * zero or less means the payment is cancelled.
	 *
	 * @return array<int, array{payment: Payment, from: float, to: float}>
	 */
	public function waiting_plan( \WC_Order $order ): array {
		$change = $this->waiting_change( $order );
		$plan   = array();
		if ( 0.0 === $change ) {
			return $plan;
		}
		foreach ( $this->plugin->payments()->waiting( $order ) as $payment ) {
			$from   = $payment->get_amount();
			$to     = round( $from + $change, wc_get_price_decimals() );
			$plan[] = array(
				'payment' => $payment,
				'from'    => $from,
				'to'      => max( 0.0, $to ),
			);
			if ( $to > 0 ) {
				break;
			}
			// Cancelling this payment covers part of a decrease; the rest goes to the next one.
			$change = $to;
		}
		return $plan;
	}

	/**
	 * Brings waiting payments in line with the order after it changed: the newest one takes
	 * the difference, and a payment the order no longer needs is cancelled.
	 *
	 * The order is saved first: cancelling a payment can change the order status on its own,
	 * and saving this order object afterwards would undo that.
	 *
	 * @return array{updated: Payment[], cancelled: Payment[]}|\WP_Error
	 */
	public function update_waiting( \WC_Order $order, bool $notify = true ) {
		$plan = $this->waiting_plan( $order );
		if ( ! $plan ) {
			return new \WP_Error( 'obal_nothing_to_update', __( 'There is no change to apply to a waiting payment.', 'order-balance' ) );
		}

		$order->update_meta_data( self::REQUEST_TOTAL_META, wc_format_decimal( $order->get_total() ) );
		$order->save();

		$currency = array( 'currency' => $order->get_currency() );
		$result   = array(
			'updated'   => array(),
			'cancelled' => array(),
		);

		foreach ( $plan as $step ) {
			$payment = $step['payment'];
			if ( $step['to'] > 0 ) {
				$this->set_amount( $payment, $step['to'] );
				$result['updated'][] = $payment;
				$order->add_order_note(
					sprintf(
						/* translators: 1: payment number, 2: old amount, 3: new amount */
						__( 'Balance payment #%1$s updated: %2$s to %3$s.', 'order-balance' ),
						$payment->get_order_number(),
						wp_strip_all_tags( wc_price( $step['from'], $currency ) ),
						wp_strip_all_tags( wc_price( $step['to'], $currency ) )
					)
				);
			} else {
				$payment->update_status( 'cancelled', __( 'Cancelled: the order no longer needs this payment.', 'order-balance' ) );
				$result['cancelled'][] = $payment;
				$order->add_order_note(
					/* translators: %s: payment number */
					sprintf( __( 'Balance payment #%s cancelled: the order no longer needs it.', 'order-balance' ), $payment->get_order_number() )
				);
			}
		}

		do_action( 'obal_waiting_updated', $order, $result );

		if ( $notify ) {
			$this->notify_waiting( $order, $result );
		}

		return $result;
	}

	/**
	 * Emails the customer about an update: the new amount, or the cancellation.
	 *
	 * @param array{updated: Payment[], cancelled: Payment[]} $result
	 */
	public function notify_waiting( \WC_Order $order, array $result ): void {
		foreach ( $result['updated'] as $payment ) {
			$this->notify( $order, $payment );
		}
		foreach ( $result['cancelled'] as $payment ) {
			PaymentActions::send( 'obal_payment_cancelled_notification', $payment );
		}
	}

	/**
	 * Changes what a waiting payment asks for. Fees added on top are refreshed through the
	 * obal_payment_amount_changed action.
	 */
	public function set_amount( Payment $payment, float $amount ): void {
		$amount = round( $amount, wc_get_price_decimals() );
		$old    = $payment->get_amount();

		// Change the line held by the payment itself: calculate_totals() reads those objects,
		// not a fresh copy from the database.
		$kinds = $payment->get_line_kinds();
		foreach ( $payment->get_fees() as $item_id => $item ) {
			if ( isset( $kinds[ $item_id ] ) && Payment::LINE_BALANCE === $kinds[ $item_id ] ) {
				$item->set_amount( wc_format_decimal( $amount ) );
				$item->set_total( wc_format_decimal( $amount ) );
				break;
			}
		}

		$payment->update_meta_data( Payment::AMOUNT_META, wc_format_decimal( $amount ) );
		$payment->calculate_totals( false );
		$payment->save();

		$payment->add_order_note(
			sprintf(
				/* translators: 1: old amount, 2: new amount */
				__( 'Amount changed from %1$s to %2$s because the order changed.', 'order-balance' ),
				wp_strip_all_tags( wc_price( $old, array( 'currency' => $payment->get_currency() ) ) ),
				wp_strip_all_tags( wc_price( $amount, array( 'currency' => $payment->get_currency() ) ) )
			)
		);

		do_action( 'obal_payment_amount_changed', $payment, $old, $amount );
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
			return $this->limit_reached( $order )
				? new \WP_Error( 'obal_limit', __( 'The extra payment limit for this order has been reached.', 'order-balance' ) )
				: new \WP_Error( 'obal_waiting', __( 'A balance payment is already waiting. Update it instead of requesting another one.', 'order-balance' ) );
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
		$order->update_meta_data( self::REQUEST_TOTAL_META, wc_format_decimal( $order->get_total() ) );
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

	/**
	 * Sends the "Order updated" email and remembers what it said: which payment and which
	 * amount. Scheduled sends check this, so a manual send and an automatic one never repeat
	 * the same email.
	 */
	public function notify( \WC_Order $order, ?Payment $payment = null ): void {
		WC()->mailer();
		do_action( 'obal_order_updated_notification', $order->get_id(), $payment ? $payment->get_id() : 0 );

		// A fresh copy: the caller's object may be older than the saved order (for example
		// after a payment was cancelled), and saving it would undo that.
		$fresh = wc_get_order( $order->get_id() );
		if ( $fresh ) {
			$fresh->update_meta_data(
				self::LAST_NOTICE_META,
				array(
					'payment' => $payment ? $payment->get_id() : 0,
					'amount'  => $payment ? wc_format_decimal( $payment->get_total(), wc_get_price_decimals() ) : '',
					'time'    => time(),
				)
			);
			$fresh->save();
		}

		do_action( 'obal_customer_notified', $order, $payment );
	}

	/**
	 * The last "Order updated" email: payment id, amount and time, or null.
	 *
	 * @return array{payment: int, amount: string, time: int}|null
	 */
	public function last_notice( \WC_Order $order ): ?array {
		$notice = $order->get_meta( self::LAST_NOTICE_META );
		return is_array( $notice ) && isset( $notice['payment'], $notice['amount'], $notice['time'] ) ? $notice : null;
	}

	/**
	 * The customer already has an email with this payment at its current amount.
	 */
	public function already_notified( \WC_Order $order, Payment $payment ): bool {
		$notice = $this->last_notice( $order );
		return $notice
			&& (int) $notice['payment'] === $payment->get_id()
			&& wc_format_decimal( $payment->get_total(), wc_get_price_decimals() ) === (string) $notice['amount'];
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
