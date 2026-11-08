<?php

namespace ModwellLabs\OrderBalance\Emails;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Payments\PaymentActions;
use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

final class Emails implements Module {

	private const SUPPRESSED = array(
		'new_order',
		'cancelled_order',
		'failed_order',
		'customer_on_hold_order',
		'customer_processing_order',
		'customer_completed_order',
		'customer_refunded_order',
	);

	public function __construct( Plugin $plugin ) {}

	public function register( Plugin $plugin ): void {
		add_filter( 'woocommerce_email_classes', array( $this, 'classes' ) );
		add_action( 'obal_payment_paid', array( $this, 'payment_paid' ) );

		foreach ( self::SUPPRESSED as $id ) {
			add_filter( 'woocommerce_email_enabled_' . $id, array( $this, 'suppress_for_payments' ), 10, 2 );
		}
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
}
