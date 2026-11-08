<?php

namespace ModwellLabs\OrderBalance\Emails;

defined( 'ABSPATH' ) || exit;

class PaymentCancelledEmail extends PaymentEmail {

	public function __construct() {
		$this->id             = 'obal_payment_cancelled';
		$this->customer_email = true;
		$this->title          = __( 'Balance payment cancelled', 'modwell-order-balance-for-woocommerce' );
		$this->description    = __( 'Sent to the payer when the store cancels a requested balance payment.', 'modwell-order-balance-for-woocommerce' );
		parent::__construct();
	}

	protected function hook(): string {
		return 'obal_payment_cancelled_notification';
	}

	public function get_default_subject() {
		return __( 'Payment request for your {site_title} order #{order_number} was cancelled', 'modwell-order-balance-for-woocommerce' );
	}

	public function get_default_heading() {
		return __( 'Payment request cancelled', 'modwell-order-balance-for-woocommerce' );
	}

	public function message(): string {
		return __( 'The payment request #{payment_number} for order #{order_number} has been cancelled. You do not need to pay it.', 'modwell-order-balance-for-woocommerce' );
	}
}
