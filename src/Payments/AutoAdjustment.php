<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

final class AutoAdjustment implements Module {

	/** @var Plugin */
	private $plugin;

	/** @var bool */
	private $running = false;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		add_action( 'woocommerce_order_after_calculate_totals', array( $this, 'maybe_adjust' ), 20, 2 );
	}

	public function maybe_adjust( $and_taxes, $order ): void {
		if ( $this->running || ! PaymentRepository::is_parent( $order ) || ! $order->get_id() ) {
			return;
		}
		if ( ! $this->plugin->settings()->enabled( 'auto_rounding' ) || '' === (string) $order->get_meta( BalanceCalculator::INITIAL_META ) ) {
			return;
		}

		$balance = $this->plugin->balance()->for_order( $order );
		if ( $balance->requested > 0 || ! $balance->within( $this->plugin->settings()->threshold() ) ) {
			return;
		}

		$this->running = true;
		$this->plugin->service()->adjust( $order );
		$this->running = false;
	}
}
