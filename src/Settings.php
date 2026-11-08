<?php

namespace ModwellLabs\OrderBalance;

defined( 'ABSPATH' ) || exit;

final class Settings implements Module {

	public const MODE_CHECKOUT   = 'checkout';
	public const MODE_PROCESSING = 'later_processing';
	public const MODE_COMPLETED  = 'later_completed';
	public const MODE_MANUAL     = 'later_manual';
	public const NO_METHOD       = '_manual';
	public const MODE_META       = '_obal_mode';

	public function __construct( Plugin $plugin ) {}

	public function register( Plugin $plugin ): void {
		add_action( 'woocommerce_admin_field_obal_gateway_modes', array( $this, 'render_modes' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_obal_gateway_modes', array( $this, 'sanitize_modes' ), 10, 3 );
	}

	public function fields(): array {
		$fields = array(
			array(
				'id'    => 'obal_payments',
				'type'  => 'title',
				'title' => __( 'Payment methods', 'order-balance' ),
				'desc'  => __( 'Choose when an order counts as paid for each payment method. Orders paid at checkout start tracking from the amount paid. Orders paid later start at zero: the whole total is due until it is paid.', 'order-balance' ),
			),
			array(
				'id'   => 'obal_gateway_modes',
				'type' => 'obal_gateway_modes',
			),
			array(
				'id'   => 'obal_payments',
				'type' => 'sectionend',
			),
			array(
				'id'    => 'obal_general',
				'type'  => 'title',
				'title' => __( 'Balance', 'order-balance' ),
			),
			array(
				'id'                => 'obal_rounding_threshold',
				'type'              => 'number',
				'title'             => __( 'Rounding threshold', 'order-balance' ),
				'desc'              => __( 'Differences up to this amount are absorbed with a rounding adjustment line instead of a payment or refund. Set 0 to disable.', 'order-balance' ),
				'desc_tip'          => true,
				'default'           => '1',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
			),
			array(
				'id'      => 'obal_auto_rounding',
				'type'    => 'checkbox',
				'title'   => __( 'Automatic rounding', 'order-balance' ),
				'desc'    => __( 'Apply the rounding adjustment automatically when order totals are recalculated', 'order-balance' ),
				'default' => 'yes',
			),
			array(
				'id'      => 'obal_partial_status',
				'type'    => 'checkbox',
				'title'   => __( 'Partially paid status', 'order-balance' ),
				'desc'    => __( 'Move the order to "Partially paid" while a requested payment is outstanding', 'order-balance' ),
				'default' => 'yes',
			),
			array(
				'id'      => 'obal_paid_status',
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'title'   => __( 'Status after the balance is paid', 'order-balance' ),
				'desc'    => __( 'Order status to set when the customer pays the requested balance in full.', 'order-balance' ),
				'default' => 'previous',
				'options' => $this->paid_status_options(),
			),
			array(
				'id'       => 'obal_refund_payment_fees',
				'type'     => 'checkbox',
				'title'    => __( 'Payment fees', 'order-balance' ),
				'desc'     => __( 'Refund payment fees with the payment, in proportion to the refunded amount', 'order-balance' ),
				'desc_tip' =>__( 'Fees and surcharges that a payment method adds to a balance payment never count toward the order balance. When this is on, a refund returns a matching share of them (card network rules usually require this for card surcharges). When it is off, refunds are limited to the balance part and the fees are kept. Surcharge and refund rules differ by country and card network, so check them before you keep fees.', 'order-balance' ),
				'default'  => 'yes',
			),
			array(
				'id'      => 'obal_adjustment_label',
				'type'    => 'text',
				'title'   => __( 'Adjustment label', 'order-balance' ),
				'default' => __( 'Rounding adjustment', 'order-balance' ),
			),
			array(
				'id'   => 'obal_general',
				'type' => 'sectionend',
			),
		);

		return apply_filters( 'obal_settings_fields', $fields );
	}

	public function mode_labels(): array {
		return apply_filters(
			'obal_payment_modes',
			array(
				self::MODE_CHECKOUT   => __( 'Paid at checkout', 'order-balance' ),
				self::MODE_PROCESSING => __( 'Paid later: mark paid at Processing', 'order-balance' ),
				self::MODE_COMPLETED  => __( 'Paid later: mark paid at Completed', 'order-balance' ),
				self::MODE_MANUAL     => __( 'Paid later: record payments manually', 'order-balance' ),
			)
		);
	}

	public function gateways(): array {
		$rows = array();
		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			$rows[ $id ] = array(
				'title'   => $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title(),
				'enabled' => 'yes' === $gateway->enabled,
			);
		}
		$rows[ self::NO_METHOD ] = array(
			'title'   => __( 'No payment method (orders created in the admin)', 'order-balance' ),
			'enabled' => true,
		);
		return $rows;
	}

	public function render_modes(): void {
		$labels = $this->mode_labels();
		?>
		<tr valign="top">
			<td colspan="2" class="obal-modes-cell">
				<table class="widefat striped obal-modes">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Payment method', 'order-balance' ); ?></th>
							<th><?php esc_html_e( 'Counts as paid', 'order-balance' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $this->gateways() as $id => $row ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $row['title'] ); ?></strong>
									<?php if ( self::NO_METHOD !== $id ) : ?>
										<code><?php echo esc_html( $id ); ?></code>
										<?php if ( ! $row['enabled'] ) : ?>
											<span class="description"><?php esc_html_e( '(disabled)', 'order-balance' ); ?></span>
										<?php endif; ?>
									<?php endif; ?>
								</td>
								<td>
									<select name="obal_gateway_modes[<?php echo esc_attr( $id ); ?>]">
										<?php foreach ( $labels as $mode => $label ) : ?>
											<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $this->gateway_mode( $id ), $mode ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php
	}

	public function sanitize_modes( $value, $option, $raw ): array {
		$modes = array();
		foreach ( (array) $raw as $id => $mode ) {
			$mode = sanitize_key( $mode );
			if ( array_key_exists( $mode, $this->mode_labels() ) ) {
				$modes[ sanitize_key( $id ) ] = $mode;
			}
		}
		return $modes;
	}

	public function gateway_mode( string $gateway_id ): string {
		$id    = '' !== $gateway_id ? $gateway_id : self::NO_METHOD;
		$saved = (array) get_option( 'obal_gateway_modes', array() );

		if ( isset( $saved[ $id ] ) && array_key_exists( $saved[ $id ], $this->mode_labels() ) ) {
			return $saved[ $id ];
		}

		return (string) apply_filters( 'obal_default_gateway_mode', $this->default_mode( $id ), $id );
	}

	public function order_mode( \WC_Order $order ): string {
		$locked = (string) $order->get_meta( self::MODE_META );
		if ( '' !== $locked && array_key_exists( $locked, $this->mode_labels() ) ) {
			return $locked;
		}
		return $this->gateway_mode( (string) $order->get_payment_method() );
	}

	public function lock_mode( \WC_Order $order ): void {
		if ( '' === (string) $order->get_meta( self::MODE_META ) ) {
			$order->update_meta_data( self::MODE_META, $this->order_mode( $order ) );
		}
	}

	public static function is_later( string $mode ): bool {
		return 0 === strpos( $mode, 'later_' );
	}

	public function paid_status_options(): array {
		$options  = array( 'previous' => __( 'Status the order had before the request', 'order-balance' ) );
		$excluded = array( 'wc-pending', 'wc-failed', 'wc-cancelled', 'wc-refunded', 'wc-checkout-draft', 'wc-obal-partial' );
		foreach ( wc_get_order_statuses() as $key => $label ) {
			if ( ! in_array( $key, $excluded, true ) ) {
				$options[ substr( $key, 3 ) ] = $label;
			}
		}
		return $options;
	}

	public function paid_status(): string {
		$status = (string) $this->get( 'paid_status', 'previous' );
		return array_key_exists( $status, $this->paid_status_options() ) ? $status : 'previous';
	}

	public function get( string $key, $default = '' ) {
		return get_option( 'obal_' . $key, $default );
	}

	public function enabled( string $key, bool $default = true ): bool {
		return 'yes' === $this->get( $key, $default ? 'yes' : 'no' );
	}

	public function threshold(): float {
		return max( 0.0, (float) wc_format_decimal( $this->get( 'rounding_threshold', '1' ) ) );
	}

	public function adjustment_label(): string {
		$label = (string) $this->get( 'adjustment_label', '' );
		return '' !== $label ? $label : __( 'Rounding adjustment', 'order-balance' );
	}

	private function default_mode( string $id ): string {
		switch ( $id ) {
			case 'cod':
				return self::MODE_COMPLETED;
			case 'bacs':
			case 'cheque':
				return self::MODE_PROCESSING;
			case self::NO_METHOD:
				return self::MODE_MANUAL;
			default:
				return self::MODE_CHECKOUT;
		}
	}
}
