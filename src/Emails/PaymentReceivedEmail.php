<?php

namespace ModwellLabs\OrderBalance\Emails;

defined( 'ABSPATH' ) || exit;

class PaymentReceivedEmail extends PaymentEmail {

	public function __construct() {
		$this->id             = 'obal_payment_received';
		$this->customer_email = true;
		$this->title          = __( 'Balance payment received', 'order-balance' );
		$this->description    = __( 'Receipt sent to the payer when a balance payment is paid.', 'order-balance' );
		parent::__construct();
	}

	protected function hook(): string {
		return 'obal_payment_received_notification';
	}

	public function get_default_subject() {
		return __( 'Payment received for your {site_title} order #{order_number}', 'order-balance' );
	}

	public function get_default_heading() {
		return __( 'Thank you for your payment', 'order-balance' );
	}

	public function message(): string {
		return __( 'We have received your payment #{payment_number} for order #{order_number}.', 'order-balance' );
	}
}
