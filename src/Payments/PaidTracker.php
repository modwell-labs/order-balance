<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Settings;

defined( 'ABSPATH' ) || exit;

final class PaidTracker implements Module {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		add_action( 'woocommerce_before_order_object_save', array( $this, 'capture' ) );
	}

	public function capture( $order ): void {
		if ( ! PaymentRepository::is_parent( $order ) || '' !== (string) $order->get_meta( BalanceCalculator::INITIAL_META ) ) {
			return;
		}

		$settings = $this->plugin->settings();
		if ( Settings::is_later( $settings->order_mode( $order ) ) ) {
			return;
		}

		$original    = $order->get_data();
		$paid_status = wc_get_is_paid_statuses();
		$was_paid    = ! empty( $original['date_paid'] ) || in_array( $original['status'], $paid_status, true );
		$is_paid     = $was_paid || $order->get_date_paid() || $order->has_status( $paid_status );

		if ( ! $is_paid ) {
			return;
		}

		$total = $was_paid && $order->get_id() ? $original['total'] : $order->get_total();
		$order->update_meta_data( BalanceCalculator::INITIAL_META, wc_format_decimal( $total ) );
		$settings->lock_mode( $order );
	}
}
