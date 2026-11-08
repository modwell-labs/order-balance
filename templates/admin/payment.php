<?php
/**
 * @var ModwellLabs\OrderBalance\Payments\Payment        $payment
 * @var WC_Order|null                                    $parent
 * @var ModwellLabs\OrderBalance\Payments\Balance|null   $balance
 * @var array                                            $actions
 * @var array<string, bool>                              $enabled
 * @var array                                            $notes
 */

use ModwellLabs\OrderBalance\Admin\AdminPage;
use ModwellLabs\OrderBalance\Admin\PaymentScreen;

defined( 'ABSPATH' ) || exit;

$obal_currency = array( 'currency' => $payment->get_currency() );
$obal_paid     = $payment->get_date_paid();
?>
<p><a href="<?php echo esc_url( AdminPage::url( 'payments' ) ); ?>">&larr; <?php esc_html_e( 'All payments', 'order-balance' ); ?></a></p>

<div class="obal-payment">
	<div class="obal-card">
		<h2>
			<?php
			/* translators: %s: payment number */
			printf( esc_html__( 'Payment #%s', 'order-balance' ), esc_html( $payment->get_order_number() ) );
			?>
			<span class="obal-status status-<?php echo esc_attr( $payment->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $payment->get_status() ) ); ?></span>
		</h2>
		<table class="widefat striped obal-details">
			<tbody>
				<tr><th><?php esc_html_e( 'Amount', 'order-balance' ); ?></th><td><strong><?php echo wp_kses_post( wc_price( $payment->get_total(), $obal_currency ) ); ?></strong></td></tr>
				<?php if ( $payment->get_total_refunded() > 0 ) : ?>
					<tr><th><?php esc_html_e( 'Refunded', 'order-balance' ); ?></th><td><?php echo wp_kses_post( wc_price( $payment->get_total_refunded(), $obal_currency ) ); ?></td></tr>
				<?php endif; ?>
				<tr><th><?php esc_html_e( 'Created', 'order-balance' ); ?></th><td><?php echo esc_html( wc_format_datetime( $payment->get_date_created(), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Paid', 'order-balance' ); ?></th><td><?php echo $obal_paid ? esc_html( wc_format_datetime( $obal_paid, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) : '&mdash;'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Payment method', 'order-balance' ); ?></th><td><?php echo esc_html( $payment->get_payment_method_title() ? $payment->get_payment_method_title() : '—' ); ?></td></tr>
				<?php if ( $payment->get_transaction_id() ) : ?>
					<tr><th><?php esc_html_e( 'Transaction ID', 'order-balance' ); ?></th><td><code><?php echo esc_html( $payment->get_transaction_id() ); ?></code></td></tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Payer', 'order-balance' ); ?></th>
					<td>
						<?php echo esc_html( trim( $payment->get_formatted_billing_full_name() ) ); ?><br>
						<?php echo esc_html( $payment->get_billing_email() ); ?>
						<?php if ( $payment->get_customer_id() ) : ?>
							<br><a href="<?php echo esc_url( get_edit_user_link( $payment->get_customer_id() ) ); ?>"><?php esc_html_e( 'Customer account', 'order-balance' ); ?></a>
						<?php else : ?>
							<br><span class="description"><?php esc_html_e( 'Guest', 'order-balance' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $payment->needs_payment() ) : ?>
					<tr>
						<th><?php esc_html_e( 'Payment link', 'order-balance' ); ?></th>
						<td>
							<a href="<?php echo esc_url( $payment->get_checkout_payment_url() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $payment->get_checkout_payment_url() ); ?></a>
							<button type="button" class="button button-small obal-copy" data-copy="<?php echo esc_url( $payment->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Copy', 'order-balance' ); ?></button>
						</td>
					</tr>
				<?php endif; ?>
				<?php do_action( 'obal_payment_details_rows', $payment ); ?>
			</tbody>
		</table>
	</div>

	<div class="obal-side">
		<?php if ( $parent && $balance ) : ?>
			<div class="obal-card">
				<h2>
					<?php
					/* translators: %s: order number */
					printf( esc_html__( 'Order #%s', 'order-balance' ), esc_html( $parent->get_order_number() ) );
					?>
				</h2>
				<table class="obal-summary">
					<tr><th><?php esc_html_e( 'Order total', 'order-balance' ); ?></th><td><?php echo wp_kses_post( wc_price( $balance->total, $obal_currency ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Paid', 'order-balance' ); ?></th><td><?php echo wp_kses_post( wc_price( $balance->paid, $obal_currency ) ); ?></td></tr>
					<tr class="obal-due"><th><?php echo $balance->is_overpaid() ? esc_html__( 'Overpaid', 'order-balance' ) : esc_html__( 'Balance due', 'order-balance' ); ?></th><td><?php echo wp_kses_post( wc_price( abs( $balance->due ), $obal_currency ) ); ?></td></tr>
				</table>
				<p><a class="button" href="<?php echo esc_url( $parent->get_edit_order_url() ); ?>"><?php esc_html_e( 'Open order', 'order-balance' ); ?></a></p>
			</div>
		<?php endif; ?>

		<?php foreach ( $actions as $obal_name => $obal_action ) : ?>
			<form class="obal-card obal-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo ! empty( $obal_action['confirm'] ) ? ' onsubmit="return window.confirm(' . esc_attr( wp_json_encode( $obal_action['confirm'] ) ) . ');"' : ''; ?>>
				<h2><?php echo esc_html( $obal_action['label'] ); ?></h2>
				<?php
				wp_nonce_field( PaymentScreen::ACTION . '_' . $payment->get_id() );
				if ( ! empty( $obal_action['fields'] ) ) {
					call_user_func( $obal_action['fields'], $payment );
				}
				?>
				<input type="hidden" name="action" value="<?php echo esc_attr( PaymentScreen::ACTION ); ?>">
				<input type="hidden" name="obal_action" value="<?php echo esc_attr( $obal_name ); ?>">
				<input type="hidden" name="payment_id" value="<?php echo esc_attr( $payment->get_id() ); ?>">
				<?php if ( ! empty( $obal_action['email'] ) ) : ?>
					<label class="obal-notify">
						<input type="checkbox" name="obal_notify" value="1" <?php checked( ! empty( $enabled[ $obal_action['email'] ] ) ); ?>>
						<?php esc_html_e( 'Email the customer', 'order-balance' ); ?>
					</label>
				<?php endif; ?>
				<button type="submit" class="button<?php echo ! empty( $obal_action['primary'] ) ? ' button-primary' : ''; ?>"><?php echo esc_html( $obal_action['label'] ); ?></button>
			</form>
		<?php endforeach; ?>

		<div class="obal-card">
			<h2><?php esc_html_e( 'Notes', 'order-balance' ); ?></h2>
			<?php if ( $notes ) : ?>
				<ul class="obal-notes">
					<?php foreach ( $notes as $obal_note ) : ?>
						<li>
							<?php echo wp_kses_post( wpautop( wptexturize( $obal_note->content ) ) ); ?>
							<span class="description"><?php echo esc_html( wc_format_datetime( $obal_note->date_created, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No notes yet.', 'order-balance' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
