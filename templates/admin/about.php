<?php
/**
 * @var array<string, string> $info
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="obal-about">
	<div class="obal-card">
		<h2><?php esc_html_e( 'Version', 'order-balance' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<?php foreach ( $info as $obal_label => $obal_value ) : ?>
					<tr><th><?php echo esc_html( $obal_label ); ?></th><td><?php echo esc_html( $obal_value ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="obal-card">
		<h2><?php esc_html_e( 'Licence', 'order-balance' ); ?></h2>
		<?php if ( has_action( 'obal_about_license' ) ) : ?>
			<?php do_action( 'obal_about_license' ); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'You are using the free version: one extra payment per order, rounding adjustments, refunds of overpayments and order update emails.', 'order-balance' ); ?></p>
			<p><?php esc_html_e( 'Order Balance Pro adds unlimited and custom-amount payments, refunds of single payments, changing the payer, combined invoices and payment reminders.', 'order-balance' ); ?></p>
			<p><a class="button button-primary" href="https://modwelllabs.com/order-balance/" target="_blank" rel="noopener"><?php esc_html_e( 'Learn about Pro', 'order-balance' ); ?></a></p>
		<?php endif; ?>
	</div>

	<div class="obal-card">
		<h2><?php esc_html_e( 'Support', 'order-balance' ); ?></h2>
		<p><a href="https://wordpress.org/support/plugin/order-balance/" target="_blank" rel="noopener"><?php esc_html_e( 'Support forum', 'order-balance' ); ?></a></p>
	</div>
</div>
