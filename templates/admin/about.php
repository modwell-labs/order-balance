<?php
/**
 * @var array<string, string> $info
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="obal-about">
	<div class="obal-card">
		<h2><?php esc_html_e( 'Version', 'modwell-order-balance-for-woocommerce' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<?php foreach ( $info as $obal_label => $obal_value ) : ?>
					<tr><th><?php echo esc_html( $obal_label ); ?></th><td><?php echo esc_html( $obal_value ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="obal-card">
		<h2><?php esc_html_e( 'Licence', 'modwell-order-balance-for-woocommerce' ); ?></h2>
		<?php if ( has_action( 'obal_about_license' ) ) : ?>
			<?php do_action( 'obal_about_license' ); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'You are using the free version: balance payments and pay links, refunds of overpayments, rounding adjustments and order update emails.', 'modwell-order-balance-for-woocommerce' ); ?></p>
			<p><?php esc_html_e( 'Order Balance Pro is in development. Planned: automatic payment updates, custom amounts and payments in parts, refunds of single payments, combined invoices, payment reminders and an order change fee.', 'modwell-order-balance-for-woocommerce' ); ?></p>
			<p><a class="button button-primary" href="https://modwelllabs.com/order-balance/" target="_blank" rel="noopener"><?php esc_html_e( 'Get notified when Pro is ready', 'modwell-order-balance-for-woocommerce' ); ?></a></p>
		<?php endif; ?>
	</div>

	<div class="obal-card">
		<h2><?php esc_html_e( 'Support', 'modwell-order-balance-for-woocommerce' ); ?></h2>
		<p><a href="https://wordpress.org/support/plugin/modwell-order-balance-for-woocommerce/" target="_blank" rel="noopener"><?php esc_html_e( 'Support forum', 'modwell-order-balance-for-woocommerce' ); ?></a></p>
	</div>
</div>
