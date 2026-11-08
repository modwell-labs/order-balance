<?php
/**
 * @var WC_Order                                       $order
 * @var ModwellLabs\OrderBalance\Payments\Payment|null $payment
 * @var ModwellLabs\OrderBalance\Payments\Balance      $balance
 * @var string                                         $email_heading
 * @var string                                         $additional_content
 * @var bool                                           $sent_to_admin
 * @var bool                                           $plain_text
 * @var WC_Email                                       $email
 */

defined( 'ABSPATH' ) || exit;

$obal_currency    = array( 'currency' => $order->get_currency() );
$obal_third_party = $payment && 0 !== strcasecmp( $payment->get_billing_email(), $order->get_billing_email() );

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook.
?>

<p>
	<?php
	/* translators: %s: customer first name */
	printf( esc_html__( 'Hi %s,', 'order-balance' ), esc_html( $payment ? $payment->get_billing_first_name() : $order->get_billing_first_name() ) );
	?>
</p>
<p>
	<?php
	/* translators: %s: order number */
	printf( esc_html__( 'Your order #%s has been updated. The current details are below.', 'order-balance' ), esc_html( $order->get_order_number() ) );
	?>
</p>

<?php if ( $payment && $payment->needs_payment() ) : ?>
	<p>
		<?php
		/* translators: %s: amount */
		printf( esc_html__( 'An additional payment of %s is due.', 'order-balance' ), wp_kses_post( wc_price( $payment->get_total(), $obal_currency ) ) );
		?>
	</p>
	<p>
		<a class="button" href="<?php echo esc_url( $payment->get_checkout_payment_url() ); ?>" style="<?php echo esc_attr( \ModwellLabs\OrderBalance\Emails\Emails::button_style() ); ?>">
			<?php esc_html_e( 'Pay now', 'order-balance' ); ?>
		</a>
	</p>
<?php endif; ?>

<?php
if ( ! $obal_third_party ) {
	do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook.
}
?>

<table cellspacing="0" cellpadding="6" border="1" style="width:100%;margin-bottom:24px;border-collapse:collapse;">
	<tr>
		<th scope="row" style="text-align:left;"><?php esc_html_e( 'Paid so far', 'order-balance' ); ?></th>
		<td style="text-align:right;"><?php echo wp_kses_post( wc_price( $balance->paid, $obal_currency ) ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align:left;"><?php echo $balance->is_overpaid() ? esc_html__( 'To be refunded', 'order-balance' ) : esc_html__( 'Balance due', 'order-balance' ); ?></th>
		<td style="text-align:right;"><strong><?php echo wp_kses_post( wc_price( abs( $balance->due ), $obal_currency ) ); ?></strong></td>
	</tr>
</table>

<?php
do_action( 'obal_email_after_summary', $order, $balance, $plain_text, $email );
if ( ! $obal_third_party ) {
	do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook.
}

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email hook.
