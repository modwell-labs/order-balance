<?php

namespace ModwellLabs\OrderBalance\Emails;

defined( 'ABSPATH' ) || exit;

class AdminPaymentReceivedEmail extends PaymentEmail {

	public function __construct() {
		$this->id             = 'obal_admin_payment_received';
		$this->customer_email = false;
		$this->title          = __( 'Balance payment received (admin)', 'modwell-order-balance-for-woocommerce' );
		$this->description    = __( 'Sent to the store when a customer pays a balance payment.', 'modwell-order-balance-for-woocommerce' );
		parent::__construct();
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields = array_merge(
			array_slice( $this->form_fields, 0, 1, true ),
			array(
				'recipient' => array(
					'title'       => __( 'Recipient(s)', 'modwell-order-balance-for-woocommerce' ),
					'type'        => 'text',
					/* translators: %s: admin email */
					'description' => sprintf( __( 'Comma-separated. Defaults to %s.', 'modwell-order-balance-for-woocommerce' ), esc_html( get_option( 'admin_email' ) ) ),
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
		return __( '[{site_title}]: Balance payment #{payment_number} received', 'modwell-order-balance-for-woocommerce' );
	}

	public function get_default_heading() {
		return __( 'Balance payment received', 'modwell-order-balance-for-woocommerce' );
	}

	public function message(): string {
		return __( 'Payment #{payment_number} for order #{order_number} has been paid.', 'modwell-order-balance-for-woocommerce' );
	}
}
