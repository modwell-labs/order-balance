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
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'item_totals' ), 10, 2 );
	}

	/**
	 * A balance payment has no products, so its "Subtotal: 0.00" row says nothing. Hide it
	 * on the pay page, in the order details and in emails.
	 *
	 * @param array<string, array<string, string>> $totals
	 * @param \WC_Order                              $order
	 * @return array<string, array<string, string>>
	 */
	public function item_totals( $totals, $order ) {
		if ( is_array( $totals ) && $order instanceof Payment && 0.0 === (float) $order->get_subtotal() ) {
			unset( $totals['cart_subtotal'] );
		}
		return $totals;
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
