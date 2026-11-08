<?php

namespace ModwellLabs\OrderBalance\Payments;

defined( 'ABSPATH' ) || exit;

class Payment extends \WC_Order {

	public const TYPE = 'obal_payment';

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

	public function get_net_paid(): float {
		return $this->is_paid() ? (float) $this->get_total() - (float) $this->get_total_refunded() : 0.0;
	}
}
