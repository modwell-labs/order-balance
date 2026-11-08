<?php

namespace ModwellLabs\OrderBalance\Emails;

use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

class OrderUpdatedEmail extends \WC_Email {

	/** @var Payment|null */
	public $payment = null;

	public function __construct() {
		$this->id             = 'obal_order_updated';
		$this->customer_email = true;
		$this->title          = __( 'Order updated', 'order-balance' );
		$this->description    = __( 'Sent to the customer when a paid order changes and a balance payment is requested or updated.', 'order-balance' );
		$this->template_base  = ORDER_BALANCE_DIR . 'templates/';
		$this->template_html  = 'emails/order-updated.php';
		$this->template_plain = 'emails/plain/order-updated.php';
		$this->placeholders   = array(
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		add_action( 'obal_order_updated_notification', array( $this, 'trigger' ), 10, 2 );

		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'Your {site_title} order #{order_number} has been updated', 'order-balance' );
	}

	public function get_default_heading() {
		return __( 'Your order has been updated', 'order-balance' );
	}

	public function get_default_additional_content() {
		return __( 'If you have any questions, reply to this email.', 'order-balance' );
	}

	public function trigger( $order_id, $payment_id = 0 ): void {
		$this->setup_locale();

		$order         = wc_get_order( $order_id );
		$payment       = $payment_id ? wc_get_order( $payment_id ) : null;
		$this->payment = $payment instanceof Payment ? $payment : null;

		if ( $order instanceof \WC_Order ) {
			$this->object                         = $order;
			$this->recipient                      = $this->payment ? $this->payment->get_billing_email() : $order->get_billing_email();
			$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{order_number}'] = $order->get_order_number();
		}

		if ( $this->object && $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), 'order-balance/', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), 'order-balance/', $this->template_base );
	}

	private function template_args( bool $plain ): array {
		return array(
			'order'              => $this->object,
			'payment'            => $this->payment,
			'balance'            => Plugin::instance()->balance()->for_order( $this->object ),
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => $plain,
			'email'              => $this,
		);
	}
}
