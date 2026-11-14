<?php
/**
 * @var WC_Order                                         $order
 * @var ModwellLabs\OrderBalance\Payments\Balance        $balance
 * @var ModwellLabs\OrderBalance\Payments\Payment[]      $payments
 * @var float                                            $threshold
 * @var bool                                             $can_request
 * @var array                                            $plan      What updating the waiting payment would do.
 * @var bool                                             $show_update
 * @var array|null                                       $last_notice Last "Order updated" email: payment, amount, time.
 * @var string                                           $mode_label
 */

defined( 'ABSPATH' ) || exit;

$obal_currency = array( 'currency' => $order->get_currency() );
?>
<div class="obal-box" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
	<?php if ( ! $balance->tracked ) : ?>
		<p class="obal-muted"><?php esc_html_e( 'Balance tracking starts once the order is paid.', 'modwell-order-balance-for-woocommerce' ); ?></p>
	<?php else : ?>
		<table class="obal-summary">
			<tr>
				<th><?php esc_html_e( 'Order total', 'modwell-order-balance-for-woocommerce' ); ?></th>
				<td><?php echo wp_kses_post( wc_price( $balance->total, $obal_currency ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Paid', 'modwell-order-balance-for-woocommerce' ); ?></th>
				<td><?php echo wp_kses_post( wc_price( $balance->paid, $obal_currency ) ); ?></td>
			</tr>
			<?php if ( $balance->requested > 0 ) : ?>
				<tr>
					<th><?php esc_html_e( 'Requested', 'modwell-order-balance-for-woocommerce' ); ?></th>
					<td><?php echo wp_kses_post( wc_price( $balance->requested, $obal_currency ) ); ?></td>
				</tr>
			<?php endif; ?>
			<tr class="obal-due<?php echo $balance->is_settled() ? '' : ( $balance->is_owed() ? ' is-owed' : ' is-overpaid' ); ?>">
				<th><?php echo $balance->is_overpaid() ? esc_html__( 'Overpaid', 'modwell-order-balance-for-woocommerce' ) : esc_html__( 'Balance due', 'modwell-order-balance-for-woocommerce' ); ?></th>
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
								<a href="<?php echo esc_url( $obal_payment->get_checkout_payment_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Payment link', 'modwell-order-balance-for-woocommerce' ); ?></a>
								<button type="button" class="button-link obal-copy" data-copy="<?php echo esc_url( $obal_payment->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Copy', 'modwell-order-balance-for-woocommerce' ); ?></button>
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
					printf( esc_html__( 'Absorb %s as rounding', 'modwell-order-balance-for-woocommerce' ), wp_kses_post( wc_price( abs( $balance->due ), $obal_currency ) ) );
					?>
				</button>
			<?php endif; ?>

			<?php
			// The order changed while a payment is waiting: offer to update that payment.
			$obal_show_update = $show_update;
			?>
			<?php if ( $obal_show_update ) : ?>
				<div class="obal-group obal-update" data-obal-group>
					<?php foreach ( $plan as $obal_step ) : ?>
						<p class="obal-muted">
							<?php
							echo esc_html(
								$obal_step['to'] > 0
									/* translators: 1: payment number, 2: old amount, 3: new amount */
									? sprintf( __( 'Payment #%1$s: %2$s → %3$s', 'modwell-order-balance-for-woocommerce' ), $obal_step['payment']->get_order_number(), wp_strip_all_tags( wc_price( $obal_step['from'], $obal_currency ) ), wp_strip_all_tags( wc_price( $obal_step['to'], $obal_currency ) ) )
									/* translators: 1: payment number, 2: amount */
									: sprintf( __( 'Payment #%1$s (%2$s) is no longer needed and will be cancelled', 'modwell-order-balance-for-woocommerce' ), $obal_step['payment']->get_order_number(), wp_strip_all_tags( wc_price( $obal_step['from'], $obal_currency ) ) )
							);
							?>
						</p>
					<?php endforeach; ?>
					<label class="obal-notify"><input type="checkbox" class="obal-notify-input" checked> <?php esc_html_e( 'Email the customer', 'modwell-order-balance-for-woocommerce' ); ?></label>
					<button type="button" class="button button-primary obal-action" data-action="update_payment">
						<?php
						echo esc_html(
							count( $plan ) > 1 || $plan[0]['to'] > 0
								/* translators: %s: payment number */
								? sprintf( __( 'Update payment #%s', 'modwell-order-balance-for-woocommerce' ), $plan[0]['payment']->get_order_number() )
								/* translators: %s: payment number */
								: sprintf( __( 'Cancel payment #%s', 'modwell-order-balance-for-woocommerce' ), $plan[0]['payment']->get_order_number() )
						);
						?>
					</button>
				</div>
			<?php endif; ?>

			<?php if ( $balance->unrequested() > 0 && ! ( $obal_show_update && ! $can_request ) ) : ?>
				<?php if ( $can_request ) : ?>
					<div class="obal-group obal-request" data-obal-group>
						<label class="obal-notify"><input type="checkbox" id="obal-notify" class="obal-notify-input" checked> <?php esc_html_e( 'Email the customer', 'modwell-order-balance-for-woocommerce' ); ?></label>
						<button type="button" class="button<?php echo $obal_show_update ? '' : ' button-primary'; ?> obal-action" data-action="request_payment">
							<?php
							/* translators: %s: amount */
							printf( esc_html__( 'Request %s', 'modwell-order-balance-for-woocommerce' ), wp_kses_post( wc_price( $balance->unrequested(), $obal_currency ) ) );
							?>
						</button>
					</div>
				<?php
				elseif ( ! array_filter(
					$payments,
					static function ( $obal_payment ) {
						return $obal_payment->is_waiting();
					}
				) ) :
					?>
					<p class="obal-muted"><?php esc_html_e( 'A balance payment is on hold while the payment method confirms it. When it is paid or cancelled, you can request the rest.', 'modwell-order-balance-for-woocommerce' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php
			$obal_has_open = false;
			foreach ( $payments as $obal_payment ) {
				$obal_has_open = $obal_has_open || $obal_payment->is_open();
			}
			?>
			<?php if ( $obal_has_open ) : ?>
				<p class="obal-muted"><?php esc_html_e( 'Paid outside the website? Open the waiting payment and mark it as paid there.', 'modwell-order-balance-for-woocommerce' ); ?></p>
			<?php endif; ?>

			<?php if ( $balance->is_overpaid() ) : ?>
				<button type="button" class="button obal-action" data-action="refund" data-confirm="<?php esc_attr_e( 'Refund the overpaid amount to the customer?', 'modwell-order-balance-for-woocommerce' ); ?>">
					<?php
					/* translators: %s: amount */
					printf( esc_html__( 'Refund %s', 'modwell-order-balance-for-woocommerce' ), wp_kses_post( wc_price( abs( $balance->due ), $obal_currency ) ) );
					?>
				</button>
			<?php endif; ?>

			<?php if ( $balance->requested > 0 ) : ?>
				<?php if ( $last_notice ) : ?>
					<p class="obal-muted obal-email-status">
						<?php
						$obal_notice_payment = $last_notice['payment'] ? wc_get_order( (int) $last_notice['payment'] ) : null;
						echo esc_html(
							sprintf(
								/* translators: 1: date and time, 2: amount, 3: payment number */
								__( 'Last update email: %1$s, %2$s (#%3$s).', 'modwell-order-balance-for-woocommerce' ),
								wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_notice['time'] ),
								wp_strip_all_tags( wc_price( (float) $last_notice['amount'], $obal_currency ) ),
								$obal_notice_payment ? $obal_notice_payment->get_order_number() : (string) $last_notice['payment']
							)
						);
						?>
					</p>
				<?php endif; ?>
				<?php do_action( 'obal_metabox_email_status', $order ); ?>
				<button type="button" class="button-link obal-action" data-action="resend"><?php esc_html_e( 'Resend update email', 'modwell-order-balance-for-woocommerce' ); ?></button>
			<?php endif; ?>

			<?php do_action( 'obal_metabox_actions', $order, $balance ); ?>
		</div>

		<p class="obal-muted obal-hint"><?php esc_html_e( 'The balance updates when you recalculate or save the order.', 'modwell-order-balance-for-woocommerce' ); ?></p>
	<?php endif; ?>
</div>
