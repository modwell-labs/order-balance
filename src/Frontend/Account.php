<?php

namespace ModwellLabs\OrderBalance\Frontend;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Payments\PaymentRepository;
use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Template;

defined( 'ABSPATH' ) || exit;

final class Account implements Module {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'orders_actions' ), 10, 2 );
	}

	public function render( $order ): void {
		if ( $order instanceof Payment ) {
			$parent = $order->get_parent_order();
			if ( $parent ) {
				Template::render( 'account/payment-parent.php', array( 'parent' => $parent ) );
			}
			return;
		}

		if ( ! PaymentRepository::is_parent( $order ) ) {
			return;
		}

		$payments = $this->plugin->payments()->for_order( $order );
		$balance  = $this->plugin->balance()->for_order( $order );

		if ( ! $payments && $balance->is_settled() ) {
			return;
		}

		Template::render(
			'account/balance.php',
			array(
				'order'    => $order,
				'balance'  => $balance,
				'payments' => $payments,
			)
		);
	}

	public function orders_actions( array $actions, $order ): array {
		if ( ! PaymentRepository::is_parent( $order ) ) {
			return $actions;
		}
		foreach ( $this->plugin->payments()->open( $order ) as $payment ) {
			if ( $payment->needs_payment() ) {
				$actions['obal_pay'] = array(
					'url'  => $payment->get_checkout_payment_url(),
					'name' => __( 'Pay balance', 'order-balance' ),
				);
				break;
			}
		}
		return $actions;
	}
}
