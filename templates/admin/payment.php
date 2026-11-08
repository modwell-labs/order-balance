<?php
/**
 * Balance payment screen, laid out like the WooCommerce order edit screen.
 *
 * @var ModwellLabs\OrderBalance\Payments\Payment        $payment
 * @var WC_Order|null                                    $parent
 * @var ModwellLabs\OrderBalance\Payments\Balance|null   $balance
 * @var array                                            $actions
 * @var array<string, bool>                              $enabled
 * @var array                                            $notes
 */

use ModwellLabs\OrderBalance\Admin\AdminPage;
use ModwellLabs\OrderBalance\Admin\PaymentScreen;
use ModwellLabs\OrderBalance\Payments\Payment;

defined( 'ABSPATH' ) || exit;

$obal_currency = array( 'currency' => $payment->get_currency() );
$obal_format   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$obal_paid     = $payment->get_date_paid();
$obal_kinds    = $payment->get_line_kinds();
$obal_has_tax  = (float) $payment->get_total_tax() > 0;
$obal_kind_tip = array(
	Payment::LINE_BALANCE => __( 'Counts toward the order', 'modwell-order-balance-for-woocommerce' ),
	Payment::LINE_STORE   => __( 'Store fee, does not count toward the order', 'modwell-order-balance-for-woocommerce' ),
	Payment::LINE_GATEWAY => __( 'Added by the payment method, does not count toward the order', 'modwell-order-balance-for-woocommerce' ),
);
$obal_store_refund = array(
	Payment::FEE_REFUND_FULL         => __( 'refunded with a full refund', 'modwell-order-balance-for-woocommerce' ),
	Payment::FEE_REFUND_PROPORTIONAL => __( 'refunded in proportion', 'modwell-order-balance-for-woocommerce' ),
	Payment::FEE_REFUND_NEVER        => __( 'not refundable', 'modwell-order-balance-for-woocommerce' ),
);
$obal_method = $payment->get_payment_method_title();
?>
<p class="obal-back"><a href="<?php echo esc_url( AdminPage::url( 'payments' ) ); ?>">&larr; <?php esc_html_e( 'All payments', 'modwell-order-balance-for-woocommerce' ); ?></a></p>

<div id="poststuff" class="obal-payment-screen">
	<div id="post-body" class="metabox-holder columns-2">

		<div id="postbox-container-2" class="postbox-container">

			<div id="woocommerce-order-data" class="postbox">
				<div class="inside">
					<div class="panel-wrap woocommerce">
						<div id="order_data" class="panel woocommerce-order-data">
							<h2 class="woocommerce-order-data__heading">
								<?php
								/* translators: %s: payment number */
								printf( esc_html__( 'Payment #%s details', 'modwell-order-balance-for-woocommerce' ), esc_html( $payment->get_order_number() ) );
								?>
							</h2>
							<p class="woocommerce-order-data__meta order_number">
								<?php
								$obal_meta = array();
								if ( $parent ) {
									/* translators: %s: order link */
									$obal_meta[] = sprintf( __( 'Balance payment for order %s.', 'modwell-order-balance-for-woocommerce' ), '<a href="' . esc_url( $parent->get_edit_order_url() ) . '">#' . esc_html( $parent->get_order_number() ) . '</a>' );
								}
								if ( $obal_method ) {
									$obal_meta[] = $payment->get_transaction_id()
										/* translators: 1: payment method, 2: transaction id */
										? sprintf( __( 'Payment via %1$s (%2$s).', 'modwell-order-balance-for-woocommerce' ), esc_html( $obal_method ), esc_html( $payment->get_transaction_id() ) )
										/* translators: %s: payment method */
										: sprintf( __( 'Payment via %s.', 'modwell-order-balance-for-woocommerce' ), esc_html( $obal_method ) );
								}
								if ( $obal_paid ) {
									/* translators: %s: date */
									$obal_meta[] = sprintf( __( 'Paid on %s.', 'modwell-order-balance-for-woocommerce' ), esc_html( wc_format_datetime( $obal_paid, $obal_format ) ) );
								}
								echo wp_kses_post( implode( ' ', $obal_meta ) );
								?>
							</p>

							<div class="order_data_column_container">
								<div class="order_data_column">
									<h3><?php esc_html_e( 'General', 'modwell-order-balance-for-woocommerce' ); ?></h3>
									<p class="form-field form-field-wide">
										<label><?php esc_html_e( 'Date created:', 'modwell-order-balance-for-woocommerce' ); ?></label>
										<?php echo esc_html( wc_format_datetime( $payment->get_date_created(), $obal_format ) ); ?>
									</p>
									<p class="form-field form-field-wide">
										<label><?php esc_html_e( 'Status:', 'modwell-order-balance-for-woocommerce' ); ?></label>
										<mark class="order-status status-<?php echo esc_attr( $payment->get_status() ); ?>"><span><?php echo esc_html( wc_get_order_status_name( $payment->get_status() ) ); ?></span></mark>
									</p>
									<?php if ( $parent ) : ?>
										<p class="form-field form-field-wide">
											<label><?php esc_html_e( 'Order:', 'modwell-order-balance-for-woocommerce' ); ?></label>
											<a href="<?php echo esc_url( $parent->get_edit_order_url() ); ?>">#<?php echo esc_html( $parent->get_order_number() ); ?></a>
											&middot; <?php echo esc_html( wc_get_order_status_name( $parent->get_status() ) ); ?>
										</p>
									<?php endif; ?>
									<p class="form-field form-field-wide">
										<label><?php esc_html_e( 'Customer:', 'modwell-order-balance-for-woocommerce' ); ?></label>
										<?php if ( $payment->get_customer_id() ) : ?>
											<?php $obal_user = get_user_by( 'id', $payment->get_customer_id() ); ?>
											<a href="<?php echo esc_url( get_edit_user_link( $payment->get_customer_id() ) ); ?>"><?php echo esc_html( $obal_user ? $obal_user->display_name : '#' . $payment->get_customer_id() ); ?></a>
										<?php else : ?>
											<?php esc_html_e( 'Guest', 'modwell-order-balance-for-woocommerce' ); ?>
										<?php endif; ?>
									</p>
								</div>

								<div class="order_data_column">
									<h3><?php esc_html_e( 'Billing', 'modwell-order-balance-for-woocommerce' ); ?></h3>
									<div class="address">
										<?php
										$obal_address = $payment->get_formatted_billing_address();
										echo $obal_address ? '<p>' . wp_kses( $obal_address, array( 'br' => array() ) ) . '</p>' : '<p class="none_set">' . esc_html__( 'No billing address set.', 'modwell-order-balance-for-woocommerce' ) . '</p>';
										?>
										<?php if ( $payment->get_billing_email() ) : ?>
											<p><strong><?php esc_html_e( 'Email address:', 'modwell-order-balance-for-woocommerce' ); ?></strong> <a href="<?php echo esc_url( 'mailto:' . $payment->get_billing_email() ); ?>"><?php echo esc_html( $payment->get_billing_email() ); ?></a></p>
										<?php endif; ?>
										<?php if ( $payment->get_billing_phone() ) : ?>
											<p><strong><?php esc_html_e( 'Phone:', 'modwell-order-balance-for-woocommerce' ); ?></strong> <?php echo esc_html( $payment->get_billing_phone() ); ?></p>
										<?php endif; ?>
									</div>
								</div>

								<div class="order_data_column">
									<h3><?php esc_html_e( 'Payment', 'modwell-order-balance-for-woocommerce' ); ?></h3>
									<p><strong><?php esc_html_e( 'Payment method:', 'modwell-order-balance-for-woocommerce' ); ?></strong> <?php echo $obal_method ? esc_html( $obal_method ) : '&mdash;'; ?></p>
									<?php if ( $payment->get_transaction_id() ) : ?>
										<p><strong><?php esc_html_e( 'Transaction ID:', 'modwell-order-balance-for-woocommerce' ); ?></strong> <code><?php echo esc_html( $payment->get_transaction_id() ); ?></code></p>
									<?php endif; ?>
									<?php if ( $payment->needs_payment() ) : ?>
										<p>
											<strong><?php esc_html_e( 'Payment link:', 'modwell-order-balance-for-woocommerce' ); ?></strong><br>
											<a href="<?php echo esc_url( $payment->get_checkout_payment_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Customer payment page', 'modwell-order-balance-for-woocommerce' ); ?> &rarr;</a>
											<button type="button" class="button button-small obal-copy" data-copy="<?php echo esc_url( $payment->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Copy link', 'modwell-order-balance-for-woocommerce' ); ?></button>
										</p>
									<?php endif; ?>
									<?php do_action( 'obal_payment_details_rows', $payment ); ?>
								</div>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
			</div>

			<div id="woocommerce-order-items" class="postbox">
				<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Items', 'modwell-order-balance-for-woocommerce' ); ?></h2></div>
				<div class="inside">
					<div class="woocommerce_order_items_wrapper">
						<table cellpadding="0" cellspacing="0" class="woocommerce_order_items">
							<thead>
								<tr>
									<th class="item" colspan="2"><?php esc_html_e( 'Item', 'modwell-order-balance-for-woocommerce' ); ?></th>
									<th class="line_cost"><?php esc_html_e( 'Total', 'modwell-order-balance-for-woocommerce' ); ?></th>
									<?php if ( $obal_has_tax ) : ?>
										<th class="line_tax"><?php esc_html_e( 'Tax', 'modwell-order-balance-for-woocommerce' ); ?></th>
									<?php endif; ?>
								</tr>
							</thead>
							<tbody id="order_fee_line_items">
								<?php foreach ( $payment->get_fees() as $obal_item_id => $obal_fee ) : ?>
									<?php $obal_kind = isset( $obal_kinds[ $obal_item_id ] ) ? $obal_kinds[ $obal_item_id ] : Payment::LINE_GATEWAY; ?>
									<tr class="fee obal-line obal-line--<?php echo esc_attr( $obal_kind ); ?>">
										<td class="thumb"><div></div></td>
										<td class="name">
											<div class="view">
												<?php echo esc_html( $obal_fee->get_name() ); ?>
												<span class="obal-line__kind"><?php echo esc_html( $obal_kind_tip[ $obal_kind ] ); ?></span>
											</div>
										</td>
										<td class="line_cost" width="1%"><div class="view"><?php echo wp_kses_post( wc_price( $obal_fee->get_total(), $obal_currency ) ); ?></div></td>
										<?php if ( $obal_has_tax ) : ?>
											<td class="line_tax" width="1%"><div class="view"><?php echo (float) $obal_fee->get_total_tax() > 0 ? wp_kses_post( wc_price( $obal_fee->get_total_tax(), $obal_currency ) ) : '&ndash;'; ?></div></td>
										<?php endif; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
							<?php $obal_refunds = $payment->get_refunds(); ?>
							<?php if ( $obal_refunds ) : ?>
								<tbody id="order_refunds">
									<?php foreach ( $obal_refunds as $obal_refund ) : ?>
										<?php $obal_by = $obal_refund->get_refunded_by() ? get_user_by( 'id', $obal_refund->get_refunded_by() ) : null; ?>
										<tr class="refund">
											<td class="thumb"><div></div></td>
											<td class="name">
												<?php
												printf(
													/* translators: 1: refund id, 2: date */
													esc_html__( 'Refund #%1$s &ndash; %2$s', 'modwell-order-balance-for-woocommerce' ),
													esc_html( (string) $obal_refund->get_id() ),
													esc_html( wc_format_datetime( $obal_refund->get_date_created(), $obal_format ) )
												);
												if ( $obal_by ) {
													/* translators: %s: user name */
													echo ' ' . esc_html( sprintf( __( 'by %s', 'modwell-order-balance-for-woocommerce' ), $obal_by->display_name ) );
												}
												if ( $obal_refund->get_reason() ) {
													echo '<p class="description">' . esc_html( $obal_refund->get_reason() ) . '</p>';
												}
												?>
											</td>
											<td class="line_cost" width="1%"><div class="view"><?php echo wp_kses_post( wc_price( '-' . $obal_refund->get_amount(), $obal_currency ) ); ?></div></td>
											<?php if ( $obal_has_tax ) : ?>
												<td class="line_tax" width="1%"></td>
											<?php endif; ?>
										</tr>
									<?php endforeach; ?>
								</tbody>
							<?php endif; ?>
						</table>
					</div>

					<div class="wc-order-data-row wc-order-totals-items">
						<table class="wc-order-totals">
							<tr>
								<td class="label"><?php esc_html_e( 'Toward the balance:', 'modwell-order-balance-for-woocommerce' ); ?></td>
								<td width="1%"></td>
								<td class="total"><?php echo wp_kses_post( wc_price( $payment->get_balance_part(), $obal_currency ) ); ?></td>
							</tr>
							<?php if ( $payment->get_store_fees() > 0 ) : ?>
								<tr>
									<td class="label"><?php esc_html_e( 'Store fees:', 'modwell-order-balance-for-woocommerce' ); ?><br><small><?php echo esc_html( $obal_store_refund[ $payment->get_store_fee_refund_mode() ] ); ?></small></td>
									<td width="1%"></td>
									<td class="total"><?php echo wp_kses_post( wc_price( $payment->get_store_fees(), $obal_currency ) ); ?></td>
								</tr>
							<?php endif; ?>
							<?php if ( $payment->get_gateway_fees() > 0 ) : ?>
								<tr>
									<td class="label"><?php esc_html_e( 'Payment method fees:', 'modwell-order-balance-for-woocommerce' ); ?><br><small><?php echo esc_html( $payment->refunds_fees() ? __( 'refunded in proportion', 'modwell-order-balance-for-woocommerce' ) : __( 'not refundable', 'modwell-order-balance-for-woocommerce' ) ); ?></small></td>
									<td width="1%"></td>
									<td class="total"><?php echo wp_kses_post( wc_price( $payment->get_gateway_fees(), $obal_currency ) ); ?></td>
								</tr>
							<?php endif; ?>
							<?php if ( $obal_has_tax ) : ?>
								<tr>
									<td class="label"><?php esc_html_e( 'Tax on fees:', 'modwell-order-balance-for-woocommerce' ); ?></td>
									<td width="1%"></td>
									<td class="total"><?php echo wp_kses_post( wc_price( $payment->get_total_tax(), $obal_currency ) ); ?></td>
								</tr>
							<?php endif; ?>
							<tr>
								<td class="label"><?php esc_html_e( 'Payment total:', 'modwell-order-balance-for-woocommerce' ); ?></td>
								<td width="1%"></td>
								<td class="total"><?php echo wp_kses_post( wc_price( $payment->get_total(), $obal_currency ) ); ?></td>
							</tr>
						</table>

						<div class="clear"></div>

						<?php if ( $obal_paid ) : ?>
							<table class="wc-order-totals obal-totals-paid">
								<tr>
									<td class="label label-highlight"><?php esc_html_e( 'Paid:', 'modwell-order-balance-for-woocommerce' ); ?><br><small>
										<?php
										echo esc_html(
											$obal_method
												/* translators: 1: date, 2: payment method */
												? sprintf( __( '%1$s via %2$s', 'modwell-order-balance-for-woocommerce' ), wc_format_datetime( $obal_paid ), $obal_method )
												: wc_format_datetime( $obal_paid )
										);
										?>
									</small></td>
									<td width="1%"></td>
									<td class="total"><?php echo wp_kses_post( wc_price( $payment->get_total(), $obal_currency ) ); ?></td>
								</tr>
							</table>
							<div class="clear"></div>
						<?php endif; ?>

						<?php if ( $payment->get_total_refunded() > 0 ) : ?>
							<table class="wc-order-totals obal-totals-paid">
								<tr>
									<td class="label refunded-total"><?php esc_html_e( 'Refunded:', 'modwell-order-balance-for-woocommerce' ); ?></td>
									<td width="1%"></td>
									<td class="total refunded-total"><?php echo wp_kses_post( wc_price( '-' . $payment->get_total_refunded(), $obal_currency ) ); ?></td>
								</tr>
								<tr>
									<td class="label"><?php esc_html_e( 'Net payment:', 'modwell-order-balance-for-woocommerce' ); ?></td>
									<td width="1%"></td>
									<td class="total"><?php echo wp_kses_post( wc_price( $payment->get_net_paid(), $obal_currency ) ); ?></td>
								</tr>
							</table>
							<div class="clear"></div>
						<?php endif; ?>

						<?php if ( $payment->is_paid() ) : ?>
							<table class="wc-order-totals obal-totals-credited">
								<tr>
									<td class="label label-highlight"><?php esc_html_e( 'Credited to the order:', 'modwell-order-balance-for-woocommerce' ); ?></td>
									<td width="1%"></td>
									<td class="total"><?php echo wp_kses_post( wc_price( $payment->get_credited(), $obal_currency ) ); ?></td>
								</tr>
							</table>
							<div class="clear"></div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<div id="postbox-container-1" class="postbox-container">
			<?php if ( $parent && $balance ) : ?>
				<div class="postbox obal-box-order">
					<div class="postbox-header">
						<h2 class="hndle">
							<?php
							/* translators: %s: order number */
							printf( esc_html__( 'Order #%s', 'modwell-order-balance-for-woocommerce' ), esc_html( $parent->get_order_number() ) );
							?>
						</h2>
					</div>
					<div class="inside">
						<table class="obal-summary">
							<tr><th><?php esc_html_e( 'Order total', 'modwell-order-balance-for-woocommerce' ); ?></th><td><?php echo wp_kses_post( wc_price( $balance->total, $obal_currency ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Paid', 'modwell-order-balance-for-woocommerce' ); ?></th><td><?php echo wp_kses_post( wc_price( $balance->paid, $obal_currency ) ); ?></td></tr>
							<tr class="obal-due <?php echo $balance->is_owed() ? 'is-owed' : ( $balance->is_overpaid() ? 'is-overpaid' : '' ); ?>"><th><?php echo $balance->is_overpaid() ? esc_html__( 'Overpaid', 'modwell-order-balance-for-woocommerce' ) : esc_html__( 'Balance due', 'modwell-order-balance-for-woocommerce' ); ?></th><td><?php echo wp_kses_post( wc_price( abs( $balance->due ), $obal_currency ) ); ?></td></tr>
						</table>
						<p><a class="button" href="<?php echo esc_url( $parent->get_edit_order_url() ); ?>"><?php esc_html_e( 'Open order', 'modwell-order-balance-for-woocommerce' ); ?></a></p>
					</div>
				</div>
			<?php endif; ?>

			<?php foreach ( $actions as $obal_name => $obal_action ) : ?>
				<form class="postbox obal-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo ! empty( $obal_action['confirm'] ) ? ' onsubmit="return window.confirm(' . esc_attr( wp_json_encode( $obal_action['confirm'] ) ) . ');"' : ''; ?>>
					<div class="postbox-header"><h2 class="hndle"><?php echo esc_html( $obal_action['label'] ); ?></h2></div>
					<div class="inside">
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
							<p>
								<label class="obal-notify">
									<input type="checkbox" name="obal_notify" value="1" <?php checked( ! empty( $enabled[ $obal_action['email'] ] ) ); ?>>
									<?php esc_html_e( 'Email the customer', 'modwell-order-balance-for-woocommerce' ); ?>
								</label>
							</p>
						<?php endif; ?>
						<button type="submit" class="button<?php echo ! empty( $obal_action['primary'] ) ? ' button-primary' : ''; ?>"><?php echo esc_html( $obal_action['label'] ); ?></button>
					</div>
				</form>
			<?php endforeach; ?>

			<div id="woocommerce-order-notes" class="postbox">
				<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Payment notes', 'modwell-order-balance-for-woocommerce' ); ?></h2></div>
				<div class="inside">
					<?php if ( $notes ) : ?>
						<ul class="order_notes">
							<?php foreach ( $notes as $obal_note ) : ?>
								<li class="note<?php echo $obal_note->customer_note ? ' customer-note' : ''; ?><?php echo 'system' === $obal_note->added_by ? ' system-note' : ''; ?>">
									<div class="note_content"><?php echo wp_kses_post( wpautop( wptexturize( $obal_note->content ) ) ); ?></div>
									<p class="meta">
										<abbr class="exact-date" title="<?php echo esc_attr( $obal_note->date_created->date( 'Y-m-d H:i:s' ) ); ?>"><?php echo esc_html( wc_format_datetime( $obal_note->date_created, $obal_format ) ); ?></abbr>
										<?php
										if ( 'system' !== $obal_note->added_by ) {
											/* translators: %s: note author */
											echo ' ' . esc_html( sprintf( __( 'by %s', 'modwell-order-balance-for-woocommerce' ), $obal_note->added_by ) );
										}
										?>
									</p>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<ul class="order_notes"><li class="no-items"><?php esc_html_e( 'There are no notes yet.', 'modwell-order-balance-for-woocommerce' ); ?></li></ul>
					<?php endif; ?>

					<form class="add_note" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( PaymentScreen::NOTE . '_' . $payment->get_id() ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( PaymentScreen::NOTE ); ?>">
						<input type="hidden" name="payment_id" value="<?php echo esc_attr( $payment->get_id() ); ?>">
						<p>
							<label for="obal_note"><?php esc_html_e( 'Add note', 'modwell-order-balance-for-woocommerce' ); ?></label>
							<textarea id="obal_note" name="obal_note" class="input-text" cols="20" rows="4" required></textarea>
						</p>
						<p>
							<label class="obal-notify"><input type="checkbox" name="obal_note_customer" value="1"> <?php esc_html_e( 'Note to customer (sends an email)', 'modwell-order-balance-for-woocommerce' ); ?></label>
						</p>
						<p><button type="submit" class="button"><?php esc_html_e( 'Add', 'modwell-order-balance-for-woocommerce' ); ?></button></p>
					</form>
				</div>
			</div>
		</div>

	</div>
	<br class="clear">
</div>
