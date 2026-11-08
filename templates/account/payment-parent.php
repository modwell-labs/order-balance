<?php
/**
 * @var WC_Order $parent
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="obal-payment-parent">
	<?php
	printf(
		/* translators: %s: link to the order */
		esc_html__( 'This payment belongs to order %s.', 'modwell-order-balance-for-woocommerce' ),
		'<a href="' . esc_url( $parent->get_view_order_url() ) . '">#' . esc_html( $parent->get_order_number() ) . '</a>'
	);
	?>
</p>
