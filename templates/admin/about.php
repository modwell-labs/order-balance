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
		<h2><?php esc_html_e( 'Order Balance Pro', 'modwell-order-balance-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'A separate add-on is in development.', 'modwell-order-balance-for-woocommerce' ); ?> <a href="https://modwelllabs.com/order-balance/" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'modwell-order-balance-for-woocommerce' ); ?></a></p>
	</div>

	<?php do_action( 'obal_about_cards' ); ?>

	<div class="obal-card">
		<h2><?php esc_html_e( 'Support', 'modwell-order-balance-for-woocommerce' ); ?></h2>
		<p><a href="https://wordpress.org/support/plugin/modwell-order-balance-for-woocommerce/" target="_blank" rel="noopener"><?php esc_html_e( 'Support forum', 'modwell-order-balance-for-woocommerce' ); ?></a></p>
	</div>
</div>
