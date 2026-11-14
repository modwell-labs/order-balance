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
		return array(
			'mark_paid' => array(
				'label'     => __( 'Mark as paid outside the website', 'modwell-order-balance-for-woocommerce' ),
				'available' => static function ( Payment $payment ) {
					return $payment->is_open();
				},
				'fields'    => array( $this, 'mark_paid_fields' ),
				'callback'  => array( $this, 'mark_paid' ),
				'email'     => 'obal_payment_received',
				'primary'   => true,
			),
			'cancel'    => array(
				'label'     => __( 'Cancel payment', 'modwell-order-balance-for-woocommerce' ),
				'available' => static function ( Payment $payment ) {
					return $payment->is_open();
				},
				'callback'  => array( $this, 'cancel' ),
				'email'     => 'obal_payment_cancelled',
				'confirm'   => __( 'Cancel this payment? The customer will no longer be able to pay it.', 'modwell-order-balance-for-woocommerce' ),
			),
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
			return new \WP_Error( 'obal_action_unavailable', __( 'This action is not available for this payment.', 'modwell-order-balance-for-woocommerce' ) );
		}

		return call_user_func( $actions[ $name ]['callback'], $payment, $input, $notify );
	}

	public function mark_paid_fields( Payment $payment ): void {
		printf(
			'<p><label for="obal-paid-method">%1$s</label><br><input type="text" class="widefat" id="obal-paid-method" name="obal[method]" value="" placeholder="%2$s"></p>',
			esc_html__( 'How the customer paid', 'modwell-order-balance-for-woocommerce' ),
			esc_attr__( 'Cash, bank transfer…', 'modwell-order-balance-for-woocommerce' )
		);
	}

	/**
	 * The customer paid this payment in another way, for example in cash. The same payment is
	 * completed, so its pay link closes and nothing is recorded twice.
	 *
	 * @return string|\WP_Error
	 */
	public function mark_paid( Payment $payment, array $input = array(), bool $notify = true ) {
		$method = isset( $input['method'] ) ? sanitize_text_field( (string) $input['method'] ) : '';
		$title  = '' !== $method ? $method : __( 'Paid outside the website', 'modwell-order-balance-for-woocommerce' );

		$payment->set_payment_method_title( $title );
		if ( ! $notify ) {
			$payment->update_meta_data( Payment::NO_EMAIL_META, 1 );
		}
		$payment->save();

		$payment->update_status(
			'completed',
			/* translators: %s: payment method, for example cash */
			sprintf( __( 'Marked as paid outside the website by the store (%s).', 'modwell-order-balance-for-woocommerce' ), $title )
		);

		return __( 'Payment marked as paid.', 'modwell-order-balance-for-woocommerce' );
	}

	public function cancel( Payment $payment, array $input = array(), bool $notify = true ): string {
		$payment->update_status( 'cancelled', __( 'Cancelled by the store.', 'modwell-order-balance-for-woocommerce' ) );

		$parent = $payment->get_parent_order();
		if ( $parent ) {
			$parent->add_order_note(
				/* translators: %s: payment number */
				sprintf( __( 'Balance payment #%s cancelled.', 'modwell-order-balance-for-woocommerce' ), $payment->get_order_number() )
			);
		}

		if ( $notify ) {
			self::send( 'obal_payment_cancelled_notification', $payment );
		}

		return __( 'Payment cancelled.', 'modwell-order-balance-for-woocommerce' );
	}

	public static function send( string $hook, Payment $payment ): void {
		WC()->mailer();
		do_action( $hook, $payment->get_id() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- callers pass obal_* email trigger hooks.
	}
}
