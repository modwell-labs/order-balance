<?php

namespace ModwellLabs\OrderBalance\Admin;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Payments\PaymentActions;
use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Template;

defined( 'ABSPATH' ) || exit;

final class PaymentScreen implements Module {

	public const ACTION = 'obal_payment_action';

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_filter( 'woocommerce_get_edit_order_url', array( $this, 'edit_url' ), 10, 2 );
		add_action( 'load-post.php', array( $this, 'redirect_legacy_edit' ) );
	}

	public function edit_url( $url, $order ) {
		return $order instanceof Payment ? AdminPage::payment_url( $order->get_id() ) : $url;
	}

	public function redirect_legacy_edit(): void {
		$id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id && Payment::TYPE === get_post_type( $id ) ) {
			wp_safe_redirect( AdminPage::payment_url( $id ) );
			exit;
		}
	}

	public function render( Payment $payment ): void {
		$parent  = $payment->get_parent_order();
		$actions = $this->plugin->get( PaymentActions::class )->available( $payment );
		$mailer  = WC()->mailer()->get_emails();
		$enabled = array();

		foreach ( $mailer as $email ) {
			$enabled[ $email->id ] = $email->is_enabled();
		}

		Template::render(
			'admin/payment.php',
			array(
				'payment' => $payment,
				'parent'  => $parent,
				'balance' => $parent ? $this->plugin->balance()->for_order( $parent ) : null,
				'actions' => $actions,
				'enabled' => $enabled,
				'notes'   => wc_get_order_notes( array( 'order_id' => $payment->get_id() ) ),
			)
		);
	}

	public function handle(): void {
		$payment_id = isset( $_POST['payment_id'] ) ? absint( wp_unslash( $_POST['payment_id'] ) ) : 0;
		check_admin_referer( self::ACTION . '_' . $payment_id );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'order-balance' ), 403 );
		}

		$payment = wc_get_order( $payment_id );
		if ( ! $payment instanceof Payment ) {
			wp_die( esc_html__( 'Payment not found.', 'order-balance' ), 404 );
		}

		$name   = isset( $_POST['obal_action'] ) ? sanitize_key( wp_unslash( $_POST['obal_action'] ) ) : '';
		$input  = isset( $_POST['obal'] ) ? wc_clean( wp_unslash( (array) $_POST['obal'] ) ) : array();
		$notify = ! empty( $_POST['obal_notify'] );
		$result = $this->plugin->get( PaymentActions::class )->run( $name, $payment, $input, $notify );

		if ( is_wp_error( $result ) ) {
			AdminPage::notice( $result->get_error_message(), 'error' );
		} else {
			AdminPage::notice( (string) $result );
		}

		wp_safe_redirect( AdminPage::payment_url( $payment_id ) );
		exit;
	}
}
