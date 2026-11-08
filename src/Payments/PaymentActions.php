<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

final class PaymentActions {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Each action: label, available(Payment): bool, callback(Payment, array $input, bool $notify): string|\WP_Error,
	 * optional: fields(Payment): void, email (email id that "notify" controls), confirm, primary.
	 */
	public function all(): array {
		return apply_filters(
			'obal_payment_actions',
			array(
				'cancel' => array(
					'label'     => __( 'Cancel payment', 'order-balance' ),
					'available' => static function ( Payment $payment ) {
						return $payment->is_open();
					},
					'callback'  => array( $this, 'cancel' ),
					'email'     => 'obal_payment_cancelled',
					'confirm'   => __( 'Cancel this payment? The customer will no longer be able to pay it.', 'order-balance' ),
				),
			),
			$this->plugin
		);
	}

	public function available( Payment $payment ): array {
		return array_filter(
			$this->all(),
			static function ( array $action ) use ( $payment ) {
				return isset( $action['available'] ) && call_user_func( $action['available'], $payment );
			}
		);
	}

	/**
	 * @return string|\WP_Error
	 */
	public function run( string $name, Payment $payment, array $input = array(), bool $notify = true ) {
		$actions = $this->available( $payment );
		if ( ! isset( $actions[ $name ] ) ) {
			return new \WP_Error( 'obal_action_unavailable', __( 'This action is not available for this payment.', 'order-balance' ) );
		}

		$result = call_user_func( $actions[ $name ]['callback'], $payment, $input, $notify );

		if ( ! is_wp_error( $result ) ) {
			do_action( 'obal_payment_action_done', $name, $payment, $input );
		}

		return $result;
	}

	public function cancel( Payment $payment, array $input = array(), bool $notify = true ): string {
		$payment->update_status( 'cancelled', __( 'Cancelled by the store.', 'order-balance' ) );

		$parent = $payment->get_parent_order();
		if ( $parent ) {
			$parent->add_order_note(
				/* translators: %s: payment number */
				sprintf( __( 'Balance payment #%s cancelled.', 'order-balance' ), $payment->get_order_number() )
			);
		}

		if ( $notify ) {
			self::send( 'obal_payment_cancelled_notification', $payment );
		}

		return __( 'Payment cancelled.', 'order-balance' );
	}

	public static function send( string $hook, Payment $payment ): void {
		WC()->mailer();
		do_action( $hook, $payment->get_id() );
	}
}
