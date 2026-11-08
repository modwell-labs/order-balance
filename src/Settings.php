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
	public const DELETE_DATA     = 'obal_delete_data';

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
				'title' => __( 'Payment methods', 'modwell-order-balance-for-woocommerce' ),
				'desc'  => __( 'Choose when an order counts as paid for each payment method. Orders paid at checkout start tracking from the amount paid. Orders paid later start at zero: the whole total is due until it is paid.', 'modwell-order-balance-for-woocommerce' ),
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
				'id'    => 'obal_statuses',
				'type'  => 'title',
				'title' => __( 'Order status', 'modwell-order-balance-for-woocommerce' ),
				'desc'  => __( 'Which status an order has while a balance payment is outstanding, and after it has been paid.', 'modwell-order-balance-for-woocommerce' ),
			),
			array(
				'id'      => 'obal_partial_status',
				'type'    => 'checkbox',
				'title'   => __( 'Partially paid status', 'modwell-order-balance-for-woocommerce' ),
				'desc'    => __( 'Move the order to "Partially paid" while a requested payment is outstanding', 'modwell-order-balance-for-woocommerce' ),
				'default' => 'yes',
			),
			array(
				'id'      => 'obal_paid_status',
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'title'   => __( 'Status after the balance is paid', 'modwell-order-balance-for-woocommerce' ),
				'desc'    => __( 'Order status to set when the customer pays the requested balance in full.', 'modwell-order-balance-for-woocommerce' ),
				'default' => 'previous',
				'options' => $this->paid_status_options(),
			),
			array(
				'id'   => 'obal_statuses',
				'type' => 'sectionend',
			),
			array(
				'id'    => 'obal_waiting',
				'type'  => 'title',
				'title' => __( 'When the order changes again', 'modwell-order-balance-for-woocommerce' ),
				'desc'  => (string) apply_filters(
					'obal_waiting_description',
					__( 'If the order changes while a balance payment is still waiting to be paid, the Balance box offers to update that payment, so the customer keeps one link and pays once. If the order no longer needs it, the payment is cancelled instead. When the payment is paid and the order changes again, you can request the new difference as another payment. Automatic updates, with the email sent a few minutes later, are planned for Order Balance Pro, which is in development.', 'modwell-order-balance-for-woocommerce' )
				),
			),
			array(
				'id'   => 'obal_waiting',
				'type' => 'sectionend',
			),
			array(
				'id'    => 'obal_rounding',
				'type'  => 'title',
				'title' => __( 'Rounding', 'modwell-order-balance-for-woocommerce' ),
				'desc'  => __( 'Absorb a small difference, for example from tax rounding, with a rounding line on the order instead of asking for a payment or a refund.', 'modwell-order-balance-for-woocommerce' ),
			),
			array(
				'id'       => 'obal_auto_rounding',
				'type'     => 'checkbox',
				'title'    => __( 'Automatic rounding', 'modwell-order-balance-for-woocommerce' ),
				'desc'     => __( 'Apply the rounding adjustment automatically when order totals are recalculated', 'modwell-order-balance-for-woocommerce' ),
				'desc_tip' => __( 'Off by default. When on, a difference within the threshold is absorbed without asking: the order total changes by that amount and nothing is requested or refunded, so a small item added to a paid order can end up free. When off, the order shows the small balance and you absorb it with one click.', 'modwell-order-balance-for-woocommerce' ),
				'default'  => 'no',
			),
			array(
				'id'                => 'obal_rounding_threshold',
				'type'              => 'number',
				'title'             => __( 'Rounding threshold', 'modwell-order-balance-for-woocommerce' ),
				'desc'              => __( 'Differences up to this amount can be absorbed, automatically or with the "Absorb difference" button on the order. Set 0 to turn rounding off.', 'modwell-order-balance-for-woocommerce' ),
				'desc_tip'          => __( 'The amount is in your store currency. Keep it small: absorbing changes the order total by that amount.', 'modwell-order-balance-for-woocommerce' ),
				'default'           => '1',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
			),
			array(
				'id'      => 'obal_adjustment_label',
				'type'    => 'text',
				'title'   => __( 'Rounding line label', 'modwell-order-balance-for-woocommerce' ),
				'desc'    => __( 'Name of the rounding line the customer sees on the order.', 'modwell-order-balance-for-woocommerce' ),
				'default' => __( 'Rounding adjustment', 'modwell-order-balance-for-woocommerce' ),
			),
			array(
				'id'   => 'obal_rounding',
				'type' => 'sectionend',
			),
			array(
				'id'    => 'obal_fees',
				'type'  => 'title',
				'title' => __( 'Payment method fees', 'modwell-order-balance-for-woocommerce' ),
				'desc'  => __( 'Fees and surcharges that a payment method adds to a balance payment never count toward the order balance.', 'modwell-order-balance-for-woocommerce' ),
			),
			array(
				'id'       => 'obal_refund_payment_fees',
				'type'     => 'checkbox',
				'title'    => __( 'Refunds', 'modwell-order-balance-for-woocommerce' ),
				'desc'     => __( 'Refund payment method fees with the payment, in proportion to the refunded amount', 'modwell-order-balance-for-woocommerce' ),
				'desc_tip' => __( 'When this is on, a refund returns a matching share of the fees (card network rules usually require this for card surcharges). When it is off, refunds are limited to the balance part and the fees are kept. Surcharge and refund rules differ by country and card network, so check them before you keep fees.', 'modwell-order-balance-for-woocommerce' ),
				'default'  => 'yes',
			),
			array(
				'id'   => 'obal_fees',
				'type' => 'sectionend',
			),
		);

		$fields = apply_filters( 'obal_settings_fields', $fields );

		// Data handling always comes last.
		return array_merge(
			$fields,
			array(
				array(
					'id'    => 'obal_data',
					'type'  => 'title',
					'title' => __( 'Data', 'modwell-order-balance-for-woocommerce' ),
					'desc'  => __( 'When you delete the plugin, balance payments and settings are kept so you can install it again without losing anything. Orders that are "Partially paid" are moved to "On hold" so they stay visible.', 'modwell-order-balance-for-woocommerce' ),
				),
				array(
					'id'       => self::DELETE_DATA,
					'type'     => 'checkbox',
					'title'    => __( 'Delete all data', 'modwell-order-balance-for-woocommerce' ),
					'desc'     => __( 'Delete everything Order Balance created when the plugin is deleted, including balance payments, their refunds and notes', 'modwell-order-balance-for-woocommerce' ),
					'desc_tip' => __( 'This cannot be undone. Payment records are part of your order history, so export or back them up first. Orders keep their items and totals; only Order Balance data is removed.', 'modwell-order-balance-for-woocommerce' ),
					'default'  => 'no',
				),
				array(
					'id'   => 'obal_data',
					'type' => 'sectionend',
				),
			)
		);
	}

	/**
	 * Settings categories: the tabs inside the Settings page. Each one lists the settings
	 * sections it shows. Sections nobody has placed (for example from Pro) get a category
	 * of their own, named after the section; data handling always comes last.
	 *
	 * @return array<string, array{label: string, sections: string[]}>
	 */
	public function categories(): array {
		$sections   = $this->sections();
		$categories = (array) apply_filters(
			'obal_settings_categories',
			array(
				'payments' => array(
					'label'    => __( 'Payment methods', 'modwell-order-balance-for-woocommerce' ),
					'sections' => array( 'obal_payments' ),
				),
				'status'   => array(
					'label'    => __( 'Order status', 'modwell-order-balance-for-woocommerce' ),
					'sections' => array( 'obal_statuses' ),
				),
				'changes'  => array(
					'label'    => __( 'Order changes', 'modwell-order-balance-for-woocommerce' ),
					'sections' => array( 'obal_waiting' ),
				),
				'rounding' => array(
					'label'    => __( 'Rounding', 'modwell-order-balance-for-woocommerce' ),
					'sections' => array( 'obal_rounding' ),
				),
				'fees'     => array(
					'label'    => __( 'Fees', 'modwell-order-balance-for-woocommerce' ),
					'sections' => array( 'obal_fees', 'obal_change_fee' ),
				),
			)
		);

		$placed = array( 'obal_data' );
		foreach ( $categories as $category ) {
			$placed = array_merge( $placed, $category['sections'] );
		}
		foreach ( $sections as $id => $fields ) {
			if ( in_array( $id, $placed, true ) ) {
				continue;
			}
			$label = $id;
			foreach ( $fields as $field ) {
				if ( isset( $field['type'], $field['title'] ) && 'title' === $field['type'] && '' !== $field['title'] ) {
					$label = (string) $field['title'];
					break;
				}
			}
			$categories[ $id ] = array(
				'label'    => $label,
				'sections' => array( $id ),
			);
		}
		if ( isset( $sections['obal_data'] ) ) {
			$categories['data'] = array(
				'label'    => __( 'Data', 'modwell-order-balance-for-woocommerce' ),
				'sections' => array( 'obal_data' ),
			);
		}

		// Drop categories whose sections do not exist (Pro sections without Pro).
		return array_filter(
			$categories,
			static function ( array $category ) use ( $sections ) {
				return (bool) array_intersect( $category['sections'], array_keys( $sections ) );
			}
		);
	}

	/**
	 * @return string[] Section ids of a category.
	 */
	public function category_sections( string $category ): array {
		$categories = $this->categories();
		return isset( $categories[ $category ] ) ? $categories[ $category ]['sections'] : array();
	}

	/**
	 * Settings fields of a category, in settings order.
	 */
	public function category_fields( string $category ): array {
		$wanted = $this->category_sections( $category );
		$fields = array();
		foreach ( $this->sections() as $id => $section_fields ) {
			if ( in_array( $id, $wanted, true ) ) {
				$fields = array_merge( $fields, $section_fields );
			}
		}
		return $fields;
	}

	/**
	 * Settings fields grouped by section id (the id of each "title" field).
	 *
	 * @return array<string, array>
	 */
	public function sections(): array {
		$sections = array();
		$current  = '';
		foreach ( $this->fields() as $field ) {
			$type = isset( $field['type'] ) ? $field['type'] : '';
			if ( 'title' === $type ) {
				$current              = (string) $field['id'];
				$sections[ $current ] = array();
			}
			if ( '' !== $current ) {
				$sections[ $current ][] = $field;
			}
			if ( 'sectionend' === $type ) {
				$current = '';
			}
		}
		return $sections;
	}

	public function mode_labels(): array {
		return apply_filters(
			'obal_payment_modes',
			array(
				self::MODE_CHECKOUT   => __( 'Paid at checkout', 'modwell-order-balance-for-woocommerce' ),
				self::MODE_PROCESSING => __( 'Paid later: mark paid at Processing', 'modwell-order-balance-for-woocommerce' ),
				self::MODE_COMPLETED  => __( 'Paid later: mark paid at Completed', 'modwell-order-balance-for-woocommerce' ),
				self::MODE_MANUAL     => __( 'Paid later: record payments manually', 'modwell-order-balance-for-woocommerce' ),
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
			'title'   => __( 'No payment method (orders created in the admin)', 'modwell-order-balance-for-woocommerce' ),
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
							<th><?php esc_html_e( 'Payment method', 'modwell-order-balance-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Counts as paid', 'modwell-order-balance-for-woocommerce' ); ?></th>
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
											<span class="description"><?php esc_html_e( '(disabled)', 'modwell-order-balance-for-woocommerce' ); ?></span>
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
		$options  = array( 'previous' => __( 'Status the order had before the request', 'modwell-order-balance-for-woocommerce' ) );
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
		return '' !== $label ? $label : __( 'Rounding adjustment', 'modwell-order-balance-for-woocommerce' );
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
