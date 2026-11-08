<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

class Payment extends \WC_Order {

	public const TYPE           = 'obal_payment';
	public const AMOUNT_META    = '_obal_amount';
	public const STORE_FEE_META = '_obal_store_fee';

	/** Store fee refund modes, stored on the fee line when it is added. */
	public const FEE_REFUND_FULL         = 'full';
	public const FEE_REFUND_PROPORTIONAL = 'proportional';
	public const FEE_REFUND_NEVER        = 'never';

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

	public function is_recorded(): bool {
		return (bool) $this->get_meta( '_obal_recorded' );
	}

	public function is_open(): bool {
		return $this->has_status( array( 'pending', 'on-hold', 'failed' ) );
	}

	/**
	 * Amount this payment settles on the parent order. Charges added on top
	 * (store fees, payment method fees and their tax) are not part of it.
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
	 * Everything charged on top of the amount: store fees and payment method fees.
	 */
	public function get_fees_part(): float {
		return round( max( 0.0, (float) $this->get_total() - $this->get_amount() ), wc_get_price_decimals() );
	}

	/**
	 * Fees the store charges on this payment (fee lines marked with STORE_FEE_META), with their tax.
	 * They never count toward the order.
	 */
	public function get_store_fees(): float {
		$total = 0.0;
		foreach ( $this->get_fees() as $fee ) {
			if ( $fee->get_meta( self::STORE_FEE_META ) ) {
				$total += (float) $fee->get_total() + (float) $fee->get_total_tax();
			}
		}
		return round( max( 0.0, min( $total, $this->get_fees_part() ) ), wc_get_price_decimals() );
	}

	/**
	 * Fees and surcharges added by the payment method.
	 */
	public function get_gateway_fees(): float {
		return round( max( 0.0, $this->get_fees_part() - $this->get_store_fees() ), wc_get_price_decimals() );
	}

	/**
	 * How store fees are refunded: full (only with a full refund), proportional or never.
	 */
	public function get_store_fee_refund_mode(): string {
		foreach ( $this->get_fees() as $fee ) {
			$mode = (string) $fee->get_meta( self::STORE_FEE_META );
			if ( '' !== $mode ) {
				return in_array( $mode, array( self::FEE_REFUND_FULL, self::FEE_REFUND_PROPORTIONAL ), true ) ? $mode : self::FEE_REFUND_NEVER;
			}
		}
		return self::FEE_REFUND_NEVER;
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
	 * they are refundable, plus store fees in proportional mode. The balance part takes its
	 * proportional share of each refund. In full mode store fees are refunded only after the
	 * whole pool, so they come back with a full refund.
	 */
	public function get_credited(): float {
		if ( ! $this->is_paid() ) {
			return 0.0;
		}

		$part     = $this->get_balance_part();
		$pool     = $this->refund_pool();
		$refunded = (float) $this->get_total_refunded();

		if ( $refunded <= 0 || $pool <= 0 ) {
			return $part;
		}

		$reduction = min( $refunded, $pool ) * $part / $pool;

		return round( max( 0.0, $part - $reduction ), wc_get_price_decimals() );
	}

	/**
	 * Most that can still be refunded on this payment.
	 */
	public function get_refundable(): float {
		if ( ! $this->is_paid() ) {
			return 0.0;
		}
		$limit = $this->refund_pool();
		if ( self::FEE_REFUND_FULL === $this->get_store_fee_refund_mode() ) {
			$limit += $this->get_store_fees();
		}
		return round( max( 0.0, $limit - (float) $this->get_total_refunded() ), wc_get_price_decimals() );
	}

	public function refunds_fees(): bool {
		return (bool) apply_filters( 'obal_refund_payment_fees', Plugin::instance()->settings()->enabled( 'refund_payment_fees' ), $this );
	}

	private function refund_pool(): float {
		$pool = $this->get_balance_part();
		if ( $this->refunds_fees() ) {
			$pool += $this->get_gateway_fees();
		}
		if ( self::FEE_REFUND_PROPORTIONAL === $this->get_store_fee_refund_mode() ) {
			$pool += $this->get_store_fees();
		}
		return $pool;
	}
}
