<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

final class PaymentRepository {

	public const IDS_META = '_obal_payment_ids';

	public function __construct( Plugin $plugin ) {}

	public function create( \WC_Order $parent, float $amount, string $label = '' ): Payment {
		$payment = new Payment();
		$payment->set_parent_id( $parent->get_id() );
		$payment->set_currency( $parent->get_currency() );
		$payment->set_customer_id( $parent->get_customer_id() );
		$payment->set_prices_include_tax( $parent->get_prices_include_tax() );
		$payment->set_address( $parent->get_address( 'billing' ), 'billing' );
		$payment->set_address( $parent->get_address( 'shipping' ), 'shipping' );
		$payment->set_created_via( 'order-balance' );

		$item = new \WC_Order_Item_Fee();
		$item->set_name(
			'' !== $label
				/* translators: %s: order number */
				? $label : sprintf( __( 'Balance for order #%s', 'order-balance' ), $parent->get_order_number() )
		);
		$item->set_amount( wc_format_decimal( $amount ) );
		$item->set_total( wc_format_decimal( $amount ) );
		$item->set_tax_status( 'none' );
		$item->set_tax_class( '' );
		$payment->add_item( $item );

		$payment->update_meta_data( '_obal_sequence', $this->next_sequence( $parent ) );
		$payment->calculate_totals( false );
		$payment->set_status( 'pending' );
		$payment->save();

		$ids   = $this->ids( $parent );
		$ids[] = $payment->get_id();
		$parent->update_meta_data( self::IDS_META, array_values( array_unique( $ids ) ) );
		$parent->save();

		do_action( 'obal_payment_created', $payment, $parent );

		return $payment;
	}

	/**
	 * @return Payment[]
	 */
	public function for_order( \WC_Order $parent, array $statuses = array() ): array {
		if ( ! $parent->get_id() ) {
			return array();
		}
		$args = array(
			'type'    => Payment::TYPE,
			'parent'  => $parent->get_id(),
			'limit'   => -1,
			'orderby' => 'ID',
			'order'   => 'ASC',
		);
		if ( $statuses ) {
			$args['status'] = $statuses;
		}
		return array_values(
			array_filter(
				wc_get_orders( $args ),
				static function ( $order ) {
					return $order instanceof Payment;
				}
			)
		);
	}

	/**
	 * @return Payment[]
	 */
	public function open( \WC_Order $parent ): array {
		return array_values(
			array_filter(
				$this->for_order( $parent ),
				static function ( Payment $payment ) {
					return $payment->is_open();
				}
			)
		);
	}

	public function active_count( \WC_Order $parent ): int {
		return count(
			array_filter(
				$this->for_order( $parent ),
				static function ( Payment $payment ) {
					return ! $payment->is_recorded() && ! $payment->has_status( array( 'cancelled', 'failed', 'refunded' ) );
				}
			)
		);
	}

	public function ids( \WC_Order $parent ): array {
		$ids = $parent->get_meta( self::IDS_META );
		return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	}

	public static function is_payment( $order ): bool {
		return $order instanceof Payment;
	}

	public static function is_parent( $order ): bool {
		return $order instanceof \WC_Order && 'shop_order' === $order->get_type();
	}

	private function next_sequence( \WC_Order $parent ): int {
		$max = 0;
		foreach ( $this->for_order( $parent ) as $payment ) {
			$max = max( $max, $payment->get_sequence() );
		}
		return $max + 1;
	}
}
