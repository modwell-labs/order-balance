<?php
/**
 * @var WC_Order                                         $order
 * @var ModwellLabs\OrderBalance\Payments\Balance        $balance
 * @var ModwellLabs\OrderBalance\Payments\Payment[]      $payments
 * @var float                                            $threshold
 * @var bool                                             $can_request
 * @var int                                              $max
 */

defined( 'ABSPATH' ) || exit;

$obal_currency = array( 'currency' => $order->get_currency() );
?>
<div class="obal-box" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
	<?php if ( ! $balance->tracked ) : ?>
		<p class="obal-muted"><?php esc_html_e( 'Balance tracking starts once the order is paid.', 'order-balance' ); ?></p>
	<?php else : ?>
		<table class="obal-summary">
			<tr>
				<th><?php esc_html_e( 'Order total', 'order-balance' ); ?></th>
				<td><?php echo wp_kses_post( wc_price( $balance->total, $obal_currency ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Paid', 'order-balance' ); ?></th>
				<td><?php echo wp_kses_post( wc_price( $balance->paid, $obal_currency ) ); ?></td>
			</tr>
			<?php if ( $balance->requested > 0 ) : ?>
				<tr>
					<th><?php esc_html_e( 'Requested', 'order-balance' ); ?></th>
					<td><?php echo wp_kses_post( wc_price( $balance->requested, $obal_currency ) ); ?></td>
				</tr>
			<?php endif; ?>
			<tr class="obal-due<?php echo $balance->is_settled() ? '' : ( $balance->is_owed() ? ' is-owed' : ' is-overpaid' ); ?>">
				<th><?php echo $balance->is_overpaid() ? esc_html__( 'Overpaid', 'order-balance' ) : esc_html__( 'Balance due', 'order-balance' ); ?></th>
				<td><?php echo wp_kses_post( wc_price( abs( $balance->due ), $obal_currency ) ); ?></td>
			</tr>
		</table>

		<p class="obal-muted obal-mode"><?php echo esc_html( $mode_label ); ?></p>

		<?php if ( $payments ) : ?>
			<ul class="obal-payments">
				<?php foreach ( $payments as $obal_payment ) : ?>
					<li>
						<a href="<?php echo esc_url( $obal_payment->get_edit_order_url() ); ?>">#<?php echo esc_html( $obal_payment->get_order_number() ); ?></a>
						<span class="obal-status status-<?php echo esc_attr( $obal_payment->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $obal_payment->get_status() ) ); ?></span>
						<strong><?php echo wp_kses_post( wc_price( $obal_payment->get_total(), $obal_currency ) ); ?></strong>
						<?php if ( $obal_payment->needs_payment() ) : ?>
							<span class="obal-link-row">
								<a href="<?php echo esc_url( $obal_payment->get_checkout_payment_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Payment link', 'order-balance' ); ?></a>
								<button type="button" class="button-link obal-copy" data-copy="<?php echo esc_url( $obal_payment->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Copy', 'order-balance' ); ?></button>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<div class="obal-actions">
			<?php if ( $balance->within( $threshold ) && $balance->requested <= 0 ) : ?>
				<button type="button" class="button obal-action" data-action="adjust">
					<?php
					/* translators: %s: amount */
					printf( esc_html__( 'Absorb %s as rounding', 'order-balance' ), wp_kses_post( wc_price( abs( $balance->due ), $obal_currency ) ) );
					?>
				</button>
			<?php endif; ?>

			<?php if ( $balance->unrequested() > 0 ) : ?>
				<?php if ( $can_request ) : ?>
					<?php do_action( 'obal_metabox_request_fields', $order, $balance ); ?>
					<label class="obal-notify"><input type="checkbox" id="obal-notify" checked> <?php esc_html_e( 'Email the customer', 'order-balance' ); ?></label>
					<button type="button" class="button button-primary obal-action" data-action="request_payment">
						<?php
						/* translators: %s: amount */
						printf( esc_html__( 'Request %s', 'order-balance' ), wp_kses_post( wc_price( $balance->unrequested(), $obal_currency ) ) );
						?>
					</button>
				<?php else : ?>
					<p class="obal-muted">
						<?php
						/* translators: %d: number of extra payments */
						printf( esc_html( _n( 'This order already has %d extra payment.', 'This order already has %d extra payments.', $max, 'order-balance' ) ), (int) $max );
						?>
					</p>
					<?php do_action( 'obal_metabox_limit_reached', $order, $balance ); ?>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $balance->is_owed() ) : ?>
				<details class="obal-record">
					<summary><?php esc_html_e( 'Record a payment received outside the website', 'order-balance' ); ?></summary>
					<label for="obal-record-amount"><?php esc_html_e( 'Amount', 'order-balance' ); ?></label>
					<input type="number" id="obal-record-amount" data-obal-field="record_amount" min="0.01" step="0.01" max="<?php echo esc_attr( wc_format_decimal( $balance->due, wc_get_price_decimals() ) ); ?>" value="<?php echo esc_attr( wc_format_decimal( $balance->due, wc_get_price_decimals() ) ); ?>">
					<label for="obal-record-method"><?php esc_html_e( 'Method', 'order-balance' ); ?></label>
					<input type="text" id="obal-record-method" data-obal-field="record_method" placeholder="<?php esc_attr_e( 'Cash, bank transfer…', 'order-balance' ); ?>" value="<?php echo esc_attr( $order->get_payment_method_title() ); ?>">
					<button type="button" class="button obal-action" data-action="record"><?php esc_html_e( 'Record payment', 'order-balance' ); ?></button>
				</details>
			<?php endif; ?>

			<?php if ( $balance->is_overpaid() ) : ?>
				<button type="button" class="button obal-action" data-action="refund" data-confirm="<?php esc_attr_e( 'Refund the overpaid amount to the customer?', 'order-balance' ); ?>">
					<?php
					/* translators: %s: amount */
					printf( esc_html__( 'Refund %s', 'order-balance' ), wp_kses_post( wc_price( abs( $balance->due ), $obal_currency ) ) );
					?>
				</button>
			<?php endif; ?>

			<?php if ( $balance->requested > 0 ) : ?>
				<button type="button" class="button-link obal-action" data-action="resend"><?php esc_html_e( 'Resend update email', 'order-balance' ); ?></button>
			<?php endif; ?>

			<?php do_action( 'obal_metabox_actions', $order, $balance ); ?>
		</div>

		<p class="obal-muted obal-hint"><?php esc_html_e( 'Save the order after editing items to refresh the balance.', 'order-balance' ); ?></p>
	<?php endif; ?>
</div>
