<?php
/**
 * @var WC_Email[] $emails
 */

use ModwellLabs\OrderBalance\Admin\AdminPage;

defined( 'ABSPATH' ) || exit;
?>
<form method="post">
	<p><?php esc_html_e( 'Choose which emails Order Balance sends. Subject, heading and text are edited in WooCommerce email settings.', 'order-balance' ); ?></p>
	<table class="widefat striped obal-emails">
		<thead>
			<tr>
				<th class="check-column"><?php esc_html_e( 'On', 'order-balance' ); ?></th>
				<th><?php esc_html_e( 'Email', 'order-balance' ); ?></th>
				<th><?php esc_html_e( 'Recipient', 'order-balance' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $emails as $obal_key => $obal_email ) : ?>
				<tr>
					<td class="check-column"><input type="checkbox" name="obal_email[<?php echo esc_attr( $obal_email->id ); ?>]" value="1" <?php checked( $obal_email->is_enabled() ); ?>></td>
					<td>
						<strong><?php echo esc_html( $obal_email->get_title() ); ?></strong><br>
						<span class="description"><?php echo esc_html( $obal_email->get_description() ); ?></span>
					</td>
					<td><?php echo $obal_email->is_customer_email() ? esc_html__( 'Customer', 'order-balance' ) : esc_html( $obal_email->get_recipient() ); ?></td>
					<td><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=' . strtolower( $obal_key ) ) ); ?>"><?php esc_html_e( 'Edit', 'order-balance' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php wp_nonce_field( AdminPage::NONCE ); ?>
	<input type="hidden" name="obal_save" value="emails">
	<?php submit_button( __( 'Save changes', 'order-balance' ) ); ?>
</form>
