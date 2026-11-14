<?php
/**
 * The Pro tab: what the free plugin does and what the separate Pro add-on is planned to add.
 * Shown only while the add-on is not installed.
 */

defined( 'ABSPATH' ) || exit;

$obal_free = array(
	__( 'Balance payments with a pay link, after the order changes', 'modwell-order-balance-for-woocommerce' ),
	__( 'Updating a waiting payment, so the customer keeps one link', 'modwell-order-balance-for-woocommerce' ),
	__( 'Refunds of overpaid amounts', 'modwell-order-balance-for-woocommerce' ),
	__( 'Marking a balance payment as paid outside the website', 'modwell-order-balance-for-woocommerce' ),
	__( 'Rounding adjustments and order update emails', 'modwell-order-balance-for-woocommerce' ),
);
$obal_pro = array(
	__( 'Custom amounts and payments in parts, several open at once', 'modwell-order-balance-for-woocommerce' ),
	__( 'Recording payments made outside the website without a request, in full or in part', 'modwell-order-balance-for-woocommerce' ),
	__( 'A list of all balance payments with filters, search and bulk actions', 'modwell-order-balance-for-woocommerce' ),
	__( 'Automatic updates of waiting payments, with one email after the last change', 'modwell-order-balance-for-woocommerce' ),
	__( 'Refunds of single payments', 'modwell-order-balance-for-woocommerce' ),
	__( 'Combined invoices and payment reminders', 'modwell-order-balance-for-woocommerce' ),
	__( 'An optional order change fee', 'modwell-order-balance-for-woocommerce' ),
);
?>
<div class="obal-about">
	<div class="obal-card">
		<h2><?php esc_html_e( 'Included in this plugin', 'modwell-order-balance-for-woocommerce' ); ?></h2>
		<ul class="ul-disc">
			<?php foreach ( $obal_free as $obal_item ) : ?>
				<li><?php echo esc_html( $obal_item ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="obal-card">
		<h2><?php esc_html_e( 'Order Balance Pro', 'modwell-order-balance-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'A separate add-on for stores that change orders often. It is in development. Planned features:', 'modwell-order-balance-for-woocommerce' ); ?></p>
		<ul class="ul-disc">
			<?php foreach ( $obal_pro as $obal_item ) : ?>
				<li><?php echo esc_html( $obal_item ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p><a class="button button-primary" href="https://modwelllabs.com/order-balance/" target="_blank" rel="noopener"><?php esc_html_e( 'Get notified when Pro is ready', 'modwell-order-balance-for-woocommerce' ); ?></a></p>
	</div>
</div>
