<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

class Payment extends \WC_Order {

	public const TYPE              = 'obal_payment';
	public const AMOUNT_META       = '_obal_amount';
	public const BALANCE_LINE_META = '_obal_balance_line';

	public const LINE_BALANCE = 'balance';
	public const LINE_GATEWAY = 'gateway';

	public function get_type() {
		return self::TYPE;
	}

	public function get_parent_order(): ?\WC_Order {
		$parent = wc_get_order( $this->get_parent_id() );
		return $parent instanceof \WC_Order && ! $parent instanceof self ? $parent : null;
	}

	public function get_sequence(): int {
		return (int) $this->get_meta( '_obal_sequence' );
	}

	/** The store chose not to email the customer when this payment was paid. */
	public const NO_EMAIL_META = '_obal_no_email';

	public function is_recorded(): bool {
		return (bool) $this->get_meta( '_obal_recorded' );
	}

	public function is_open(): bool {
		return $this->has_status( array( 'pending', 'on-hold', 'failed' ) );
	}

	/**
	 * Requested and not paid yet, and safe to change: nothing is in progress at the payment
	 * method. On hold (an offline payment being confirmed) is left alone.
	 */
	public function is_waiting(): bool {
		return ! $this->is_recorded() && $this->has_status( array( 'pending', 'failed' ) );
	}

	/**
	 * Amount this payment settles on the parent order. Charges added on top
	 * (payment method fees and their tax) are not part of it.
	 */
	public function get_amount(): float {
		$amount = (string) $this->get_meta( self::AMOUNT_META );
		return '' !== $amount ? (float) $amount : (float) $this->get_total();
	}

	/**
	 * Part of the payment that counts toward the parent: the amount, or less when the
	 * payment total was reduced (for example, a payment method discount).
	 */
	public function get_balance_part(): float {
		return round( max( 0.0, min( $this->get_amount(), (float) $this->get_total() ) ), wc_get_price_decimals() );
	}

	/**
	 * Everything charged on top of the amount.
	 */
	public function get_fees_part(): float {
		return round( max( 0.0, (float) $this->get_total() - $this->get_amount() ), wc_get_price_decimals() );
	}

	/**
	 * Fees and surcharges added by the payment method: everything on top of the amount.
	 */
	public function get_gateway_fees(): float {
		return $this->get_fees_part();
	}

	/**
	 * What each fee line is: the balance line or a payment method fee.
	 * Payments created before lines were marked: the first unmarked line is the balance line.
	 *
	 * @return array<int, string> item id => LINE_* constant
	 */
	public function get_line_kinds(): array {
		$kinds  = array();
		$marked = false;
		foreach ( $this->get_fees() as $id => $fee ) {
			if ( $fee->get_meta( self::BALANCE_LINE_META ) ) {
				$marked = true;
			}
		}
		$first = true;
		foreach ( $this->get_fees() as $id => $fee ) {
			if ( $marked ? (bool) $fee->get_meta( self::BALANCE_LINE_META ) : $first ) {
				$kinds[ $id ] = self::LINE_BALANCE;
				$first        = false;
			} else {
				$kinds[ $id ] = self::LINE_GATEWAY;
			}
		}
		return $kinds;
	}

	/**
	 * Money received and not refunded, including fees.
	 */
	public function get_net_paid(): float {
		return $this->is_paid() ? (float) $this->get_total() - (float) $this->get_total_refunded() : 0.0;
	}

	/**
	 * Amount credited to the parent order.
	 *
	 * Refunds come out of a pool that holds the balance part, plus payment method fees when
	 * they are refundable. The balance part takes its proportional share of each refund.
	 */
	public function get_credited(): float {
		if ( ! $this->is_paid() ) {
			return 0.0;
		}

		$part     = $this->get_balance_part();
		$pool     = $this->get_refund_pool();
		$refunded = (float) $this->get_total_refunded();

		if ( $refunded <= 0 || $pool <= 0 ) {
			return $part;
		}

		$reduction = min( $refunded, $pool ) * $part / $pool;

		return round( max( 0.0, $part - $reduction ), wc_get_price_decimals() );
	}

	public function refunds_fees(): bool {
		return Plugin::instance()->settings()->enabled( 'refund_payment_fees' );
	}

	/**
	 * The part of the payment that refunds share in proportion: the balance part, plus payment
	 * method fees when they are refundable.
	 */
	public function get_refund_pool(): float {
		$pool = $this->get_balance_part();
		if ( $this->refunds_fees() ) {
			$pool += $this->get_gateway_fees();
		}
		return (float) $pool;
	}
}
