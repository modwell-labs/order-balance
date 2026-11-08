<?php

namespace ModwellLabs\OrderBalance\Emails;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Payments\PaymentActions;
use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

final class Emails implements Module {

	/**
	 * WooCommerce emails that stay on for balance payments: a note the store writes to the
	 * customer on the payment screen.
	 */
	private const ALLOWED = array( 'customer_note' );

	/**
	 * Order emails switched off even when they are missing from the email list: some ids are
	 * set only while sending (partial refund), some emails are registered only when their
	 * feature is on (review request, fulfillment, withdrawal, POS).
	 */
	private const KNOWN = array(
		'new_order',
		'cancelled_order',
		'failed_order',
		'customer_on_hold_order',
		'customer_processing_order',
		'customer_completed_order',
		'customer_refunded_order',
		'customer_partially_refunded_order',
		'customer_cancelled_order',
		'customer_failed_order',
		'customer_invoice',
		'customer_review_request',
		'customer_fulfillment_created',
		'customer_fulfillment_updated',
		'customer_fulfillment_deleted',
		'customer_pos_completed_order',
		'customer_pos_refunded_order',
		'order_withdrawal_requested',
		'customer_order_withdrawal_requested',
	);

	/** @var array<string, bool> */
	private $suppressed = array();

	public function __construct( Plugin $plugin ) {}

	public function register( Plugin $plugin ): void {
		add_filter( 'woocommerce_email_classes', array( $this, 'classes' ) );
		add_filter( 'woocommerce_email_classes', array( $this, 'suppress_core' ), 999 );
		add_action( 'obal_payment_paid', array( $this, 'payment_paid' ) );
	}

	/**
	 * Balance payments are orders to WooCommerce, so its own order emails (new order,
	 * processing, completed, refunded, review request...) would go out for them too. All of
	 * them are switched off for payments; Order Balance sends its own instead.
	 */
	public function suppress_core( array $emails ): array {
		$ids = self::KNOWN;
		foreach ( $emails as $email ) {
			if ( $email instanceof \WC_Email ) {
				$ids[] = $email->id;
			}
		}
		foreach ( array_unique( $ids ) as $id ) {
			if ( isset( $this->suppressed[ $id ] ) || 0 === strpos( $id, 'obal_' ) || in_array( $id, self::ALLOWED, true ) ) {
				continue;
			}
			add_filter( 'woocommerce_email_enabled_' . $id, array( $this, 'suppress_for_payments' ), 10, 2 );
			$this->suppressed[ $id ] = true;
		}
		return $emails;
	}

	/**
	 * @return string[] WooCommerce email ids switched off for balance payments.
	 */
	public function suppressed(): array {
		return array_keys( $this->suppressed );
	}

	public function classes( array $emails ): array {
		$classes = apply_filters(
			'obal_email_classes',
			array(
				'OBAL_Order_Updated'          => OrderUpdatedEmail::class,
				'OBAL_Payment_Received'       => PaymentReceivedEmail::class,
				'OBAL_Admin_Payment_Received' => AdminPaymentReceivedEmail::class,
				'OBAL_Payment_Cancelled'      => PaymentCancelledEmail::class,
			)
		);

		foreach ( $classes as $key => $class ) {
			$emails[ $key ] = new $class();
		}

		return $emails;
	}

	public function payment_paid( Payment $payment ): void {
		if ( ! $payment->is_recorded() || apply_filters( 'obal_notify_recorded_payments', false, $payment ) ) {
			PaymentActions::send( 'obal_payment_received_notification', $payment );
		}
	}

	public function suppress_for_payments( $enabled, $object ) {
		return $object instanceof Payment ? false : $enabled;
	}

	/**
	 * Inline style of the "Pay now" button, in the store's email colour.
	 */
	public static function button_style(): string {
		$base = sanitize_hex_color( (string) get_option( 'woocommerce_email_base_color', '#720eec' ) );
		$base = $base ? $base : '#720eec';
		$text = function_exists( 'wc_light_or_dark' ) ? wc_light_or_dark( $base, '#1e1e1e', '#ffffff' ) : '#ffffff';
		return sprintf(
			'display:inline-block;padding:12px 24px;border-radius:4px;background-color:%1$s;color:%2$s;text-decoration:none;font-weight:600;',
			$base,
			$text
		);
	}
}
