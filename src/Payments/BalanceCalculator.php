<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Settings;

defined( 'ABSPATH' ) || exit;

final class BalanceCalculator {

	public const INITIAL_META = '_obal_initial_paid';
	public const REFUND_META  = '_obal_balance_refund';

	private const UNTRACKED_STATUSES = array( 'checkout-draft', 'auto-draft', 'cancelled', 'failed', 'trash' );

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function for_order( \WC_Order $order ): Balance {
		$mode    = $this->plugin->settings()->order_mode( $order );
		$initial = (string) $order->get_meta( self::INITIAL_META );

		if ( Settings::is_later( $mode ) ) {
			$tracked = $order->get_id() > 0 && ! $order->has_status( self::UNTRACKED_STATUSES );
			$paid    = '' === $initial ? 0.0 : (float) $initial;
		} else {
			$tracked = '' !== $initial || $order->is_paid();
			$paid    = '' !== $initial ? (float) $initial : ( $order->is_paid() ? (float) $order->get_total() : 0.0 );
		}

		$requested = 0.0;

		foreach ( $this->plugin->payments()->for_order( $order ) as $payment ) {
			$paid += $payment->get_credited();
			if ( $payment->is_open() ) {
				$requested += $payment->get_amount();
			}
		}

		foreach ( $order->get_refunds() as $refund ) {
			if ( $refund->get_meta( self::REFUND_META ) ) {
				$paid -= (float) $refund->get_amount();
			}
		}

		$balance = new Balance( (float) $order->get_total(), $paid, $requested, $tracked );

		return apply_filters( 'obal_balance', $balance, $order, $mode );
	}
}
