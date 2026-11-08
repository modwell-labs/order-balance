<?php

namespace ModwellLabs\OrderBalance\Emails;

defined( 'ABSPATH' ) || exit;

class AdminPaymentReceivedEmail extends PaymentEmail {

	public function __construct() {
		$this->id             = 'obal_admin_payment_received';
		$this->customer_email = false;
		$this->title          = __( 'Balance payment received (admin)', 'order-balance' );
		$this->description    = __( 'Sent to the store when a customer pays a balance payment.', 'order-balance' );
		parent::__construct();
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields = array_merge(
			array_slice( $this->form_fields, 0, 1, true ),
			array(
				'recipient' => array(
					'title'       => __( 'Recipient(s)', 'order-balance' ),
					'type'        => 'text',
					/* translators: %s: admin email */
					'description' => sprintf( __( 'Comma-separated. Defaults to %s.', 'order-balance' ), esc_html( get_option( 'admin_email' ) ) ),
					'placeholder' => '',
					'default'     => '',
					'desc_tip'    => true,
				),
			),
			array_slice( $this->form_fields, 1, null, true )
		);
	}

	protected function hook(): string {
		return 'obal_payment_received_notification';
	}

	public function get_default_subject() {
		return __( '[{site_title}]: Balance payment #{payment_number} received', 'order-balance' );
	}

	public function get_default_heading() {
		return __( 'Balance payment received', 'order-balance' );
	}

	public function message(): string {
		return __( 'Payment #{payment_number} for order #{order_number} has been paid.', 'order-balance' );
	}
}
