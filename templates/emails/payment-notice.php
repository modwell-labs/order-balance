<?php
/**
 * @var WC_Order                                  $order
 * @var ModwellLabs\OrderBalance\Payments\Payment $payment
 * @var string                                    $message
 * @var bool                                      $show_pay_button
 * @var string                                    $email_heading
 * @var string                                    $additional_content
 * @var bool                                      $sent_to_admin
 * @var WC_Email                                  $email
 */

defined( 'ABSPATH' ) || exit;

$obal_currency = array( 'currency' => $payment->get_currency() );

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<?php if ( ! $sent_to_admin ) : ?>
	<p>
		<?php
		/* translators: %s: payer first name */
		printf( esc_html__( 'Hi %s,', 'order-balance' ), esc_html( $payment->get_billing_first_name() ) );
		?>
	</p>
<?php endif; ?>

<?php echo wp_kses_post( wpautop( wptexturize( $message ) ) ); ?>

<table cellspacing="0" cellpadding="6" border="1" style="width:100%;margin-bottom:24px;border-collapse:collapse;">
	<tr>
		<th scope="row" style="text-align:left;"><?php esc_html_e( 'Payment', 'order-balance' ); ?></th>
		<td style="text-align:right;">#<?php echo esc_html( $payment->get_order_number() ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align:left;"><?php esc_html_e( 'Order', 'order-balance' ); ?></th>
		<td style="text-align:right;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align:left;"><?php esc_html_e( 'Amount', 'order-balance' ); ?></th>
		<td style="text-align:right;"><strong><?php echo wp_kses_post( wc_price( $payment->get_total(), $obal_currency ) ); ?></strong></td>
	</tr>
	<?php if ( $payment->get_total_refunded() > 0 ) : ?>
		<tr>
			<th scope="row" style="text-align:left;"><?php esc_html_e( 'Refunded', 'order-balance' ); ?></th>
			<td style="text-align:right;"><?php echo wp_kses_post( wc_price( $payment->get_total_refunded(), $obal_currency ) ); ?></td>
		</tr>
	<?php endif; ?>
	<?php if ( $payment->get_payment_method_title() ) : ?>
		<tr>
			<th scope="row" style="text-align:left;"><?php esc_html_e( 'Payment method', 'order-balance' ); ?></th>
			<td style="text-align:right;"><?php echo esc_html( $payment->get_payment_method_title() ); ?></td>
		</tr>
	<?php endif; ?>
	<?php if ( $sent_to_admin ) : ?>
		<tr>
			<th scope="row" style="text-align:left;"><?php esc_html_e( 'Payer', 'order-balance' ); ?></th>
			<td style="text-align:right;"><?php echo esc_html( trim( $payment->get_formatted_billing_full_name() . ' ' . $payment->get_billing_email() ) ); ?></td>
		</tr>
	<?php endif; ?>
</table>

<?php if ( $show_pay_button && $payment->needs_payment() ) : ?>
	<p>
		<a class="button" href="<?php echo esc_url( $payment->get_checkout_payment_url() ); ?>" style="display:inline-block;padding:12px 20px;text-decoration:none;font-weight:600;">
			<?php esc_html_e( 'Pay now', 'order-balance' ); ?>
		</a>
	</p>
<?php endif; ?>

<?php if ( $sent_to_admin ) : ?>
	<p><a href="<?php echo esc_url( $payment->get_edit_order_url() ); ?>"><?php esc_html_e( 'View payment', 'order-balance' ); ?></a></p>
<?php endif; ?>

<?php
do_action( 'obal_payment_email_after_details', $payment, $order, $sent_to_admin, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
