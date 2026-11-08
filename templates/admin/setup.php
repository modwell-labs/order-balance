<?php
/**
 * @var array<string, array> $steps
 * @var string               $step
 * @var string               $type     welcome | settings | emails | ready
 * @var array                $fields
 * @var WC_Email[]           $emails
 * @var string               $previous
 * @var string               $next
 * @var array<string,string> $store
 * @var string               $skip_url
 * @var string               $orders   Orders screen URL.
 */

use ModwellLabs\OrderBalance\Admin\AdminPage;
use ModwellLabs\OrderBalance\Admin\SetupWizard;
use ModwellLabs\OrderBalance\Template;

defined( 'ABSPATH' ) || exit;

$obal_keys    = array_keys( $steps );
$obal_current = (int) array_search( $step, $obal_keys, true );
?>
<div class="wrap obal-setup">
	<h1 class="obal-setup__title"><?php esc_html_e( 'Order Balance', 'modwell-order-balance-for-woocommerce' ); ?> <span><?php esc_html_e( 'setup', 'modwell-order-balance-for-woocommerce' ); ?></span></h1>

	<ol class="obal-steps">
		<?php foreach ( $obal_keys as $obal_index => $obal_key ) : ?>
			<?php
			$obal_class = $obal_index < $obal_current ? 'is-done' : ( $obal_index === $obal_current ? 'is-current' : '' );
			?>
			<li class="<?php echo esc_attr( $obal_class ); ?>">
				<?php if ( $obal_index < $obal_current ) : ?>
					<a href="<?php echo esc_url( SetupWizard::url( $obal_key ) ); ?>"><span class="obal-steps__dot"><?php echo esc_html( (string) ( $obal_index + 1 ) ); ?></span><?php echo esc_html( $steps[ $obal_key ]['label'] ); ?></a>
				<?php else : ?>
					<span class="obal-steps__dot"><?php echo esc_html( (string) ( $obal_index + 1 ) ); ?></span><?php echo esc_html( $steps[ $obal_key ]['label'] ); ?>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>

	<div class="obal-setup__card">
		<?php if ( 'welcome' === $type ) : ?>
			<h2><?php esc_html_e( 'Settle order changes without starting over', 'modwell-order-balance-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'When a paid order changes, Order Balance works out the difference. The customer pays only what is new, or gets back what is no longer due. Each extra payment is linked to the original order and does not count twice in your sales reports.', 'modwell-order-balance-for-woocommerce' ); ?></p>
			<p><?php esc_html_e( 'This setup takes about two minutes. Every choice can be changed later under Order Balance > Settings.', 'modwell-order-balance-for-woocommerce' ); ?></p>
			<table class="widefat striped obal-setup__store">
				<tbody>
					<?php foreach ( $store as $obal_label => $obal_value ) : ?>
						<tr><th><?php echo esc_html( $obal_label ); ?></th><td><?php echo esc_html( $obal_value ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="obal-setup__actions">
				<a class="button button-primary button-large" href="<?php echo esc_url( SetupWizard::url( $next ) ); ?>"><?php esc_html_e( 'Start', 'modwell-order-balance-for-woocommerce' ); ?></a>
			</p>

		<?php elseif ( 'ready' === $type ) : ?>
			<h2><?php esc_html_e( 'You are ready', 'modwell-order-balance-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'Next time an order changes after it was paid:', 'modwell-order-balance-for-woocommerce' ); ?></p>
			<ol class="obal-setup__list">
				<li><?php esc_html_e( 'Edit the order as usual and recalculate it.', 'modwell-order-balance-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Use the Order Balance box on the order screen to request the difference or refund it.', 'modwell-order-balance-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'The customer gets an email with a pay link. You see every balance payment under Order Balance > Payments.', 'modwell-order-balance-for-woocommerce' ); ?></li>
			</ol>
			<p class="obal-setup__actions">
				<a class="button button-primary button-large" href="<?php echo esc_url( $orders ); ?>"><?php esc_html_e( 'Go to orders', 'modwell-order-balance-for-woocommerce' ); ?></a>
				<a class="button button-large" href="<?php echo esc_url( AdminPage::url( 'settings' ) ); ?>"><?php esc_html_e( 'Open settings', 'modwell-order-balance-for-woocommerce' ); ?></a>
				<?php if ( ! has_action( 'obal_about_license' ) ) : ?>
					<a class="button button-large" href="https://modwelllabs.com/order-balance/" target="_blank" rel="noopener"><?php esc_html_e( 'Pro is coming: get notified', 'modwell-order-balance-for-woocommerce' ); ?></a>
				<?php endif; ?>
			</p>

		<?php else : ?>
			<h2><?php echo esc_html( $steps[ $step ]['label'] ); ?></h2>
			<?php if ( ! empty( $steps[ $step ]['intro'] ) ) : ?>
				<p class="obal-setup__intro"><?php echo esc_html( $steps[ $step ]['intro'] ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( SetupWizard::url( $step ) ); ?>">
				<?php
				if ( 'emails' === $type ) {
					Template::render(
						'admin/emails.php',
						array(
							'emails' => $emails,
							'form'   => false,
						)
					);
				} else {
					\WC_Admin_Settings::output_fields( $fields );
				}
				wp_nonce_field( SetupWizard::NONCE );
				?>
				<input type="hidden" name="obal_setup_step" value="<?php echo esc_attr( $step ); ?>">
				<p class="obal-setup__actions">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save and continue', 'modwell-order-balance-for-woocommerce' ); ?></button>
					<?php if ( '' !== $previous ) : ?>
						<a class="button button-large" href="<?php echo esc_url( SetupWizard::url( $previous ) ); ?>"><?php esc_html_e( 'Back', 'modwell-order-balance-for-woocommerce' ); ?></a>
					<?php endif; ?>
					<a class="obal-setup__skip" href="<?php echo esc_url( SetupWizard::url( $next ) ); ?>"><?php esc_html_e( 'Skip this step', 'modwell-order-balance-for-woocommerce' ); ?></a>
				</p>
			</form>
		<?php endif; ?>
	</div>

	<?php if ( 'ready' !== $type ) : ?>
		<p class="obal-setup__footer"><a href="<?php echo esc_url( $skip_url ); ?>"><?php esc_html_e( 'Not now, I will set it up later', 'modwell-order-balance-for-woocommerce' ); ?></a></p>
	<?php endif; ?>
</div>
