<?php
/**
 * @var WC_Order                                    $order
 * @var ModwellLabs\OrderBalance\Payments\Balance   $balance
 * @var ModwellLabs\OrderBalance\Payments\Payment[] $payments
 */

defined( 'ABSPATH' ) || exit;

$obal_currency = array( 'currency' => $order->get_currency() );
?>
<section class="obal-account-balance">
	<h2><?php esc_html_e( 'Payments', 'modwell-order-balance-for-woocommerce' ); ?></h2>
	<table class="woocommerce-table shop_table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Paid so far', 'modwell-order-balance-for-woocommerce' ); ?></th>
				<td><?php echo wp_kses_post( wc_price( $balance->paid, $obal_currency ) ); ?></td>
			</tr>
			<?php foreach ( $payments as $obal_payment ) : ?>
				<tr>
					<th scope="row">
						<?php
						/* translators: %s: payment number */
						printf( esc_html__( 'Payment #%s', 'modwell-order-balance-for-woocommerce' ), esc_html( $obal_payment->get_order_number() ) );
						?>
						<small>(<?php echo esc_html( wc_get_order_status_name( $obal_payment->get_status() ) ); ?>)</small>
					</th>
					<td>
						<?php echo wp_kses_post( wc_price( $obal_payment->get_total(), $obal_currency ) ); ?>
						<?php if ( $obal_payment->needs_payment() ) : ?>
							<a class="woocommerce-button button pay" href="<?php echo esc_url( $obal_payment->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Pay now', 'modwell-order-balance-for-woocommerce' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><?php echo $balance->is_overpaid() ? esc_html__( 'To be refunded', 'modwell-order-balance-for-woocommerce' ) : esc_html__( 'Balance due', 'modwell-order-balance-for-woocommerce' ); ?></th>
				<td><strong><?php echo wp_kses_post( wc_price( abs( $balance->due ), $obal_currency ) ); ?></strong></td>
			</tr>
		</tbody>
	</table>
	<?php do_action( 'obal_account_after_balance', $order, $balance ); ?>
</section>
