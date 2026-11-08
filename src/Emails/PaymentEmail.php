<?php

namespace ModwellLabs\OrderBalance\Emails;

use ModwellLabs\OrderBalance\Payments\Payment;

defined( 'ABSPATH' ) || exit;

abstract class PaymentEmail extends \WC_Email {

	/** @var Payment|null */
	public $payment = null;

	public function __construct() {
		$this->template_base  = ORDER_BALANCE_DIR . 'templates/';
		$this->template_html  = 'emails/payment-notice.php';
		$this->template_plain = 'emails/plain/payment-notice.php';
		$this->placeholders   = array(
			'{order_number}'   => '',
			'{payment_number}' => '',
		);

		add_action( $this->hook(), array( $this, 'trigger' ) );

		parent::__construct();

		if ( ! $this->customer_email ) {
			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}
	}

	abstract protected function hook(): string;

	abstract public function message(): string;

	public function show_pay_button(): bool {
		return false;
	}

	public function get_default_additional_content() {
		return '';
	}

	public function trigger( $payment_id ): void {
		$this->setup_locale();

		$payment = wc_get_order( $payment_id );
		$parent  = $payment instanceof Payment ? $payment->get_parent_order() : null;

		if ( $parent ) {
			$this->payment                          = $payment;
			$this->object                           = $parent;
			$this->placeholders['{order_number}']   = $parent->get_order_number();
			$this->placeholders['{payment_number}'] = $payment->get_order_number();
			if ( $this->customer_email ) {
				$this->recipient = $payment->get_billing_email();
			}
		}

		if ( $parent && $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->args( false ), 'order-balance/', ORDER_BALANCE_DIR . 'templates/' );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->args( true ), 'order-balance/', ORDER_BALANCE_DIR . 'templates/' );
	}

	protected function args( bool $plain ): array {
		return array(
			'order'              => $this->object,
			'payment'            => $this->payment,
			'message'            => $this->format_string( $this->message() ),
			'show_pay_button'    => $this->show_pay_button(),
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => ! $this->customer_email,
			'plain_text'         => $plain,
			'email'              => $this,
		);
	}
}
