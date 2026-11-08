<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Lets payment methods find balance payments.
 *
 * Balance payments are kept out of order lists, so wc_get_orders() without a type does not
 * return them. Payment plugins look orders up exactly that way when their webhooks arrive
 * (Stripe by intent id, PayPal by order id, Mollie by transaction id), and would miss a
 * balance payment: an asynchronous payment or a refund made in their dashboard would never
 * reach it.
 *
 * When a query asks for specific orders (ids, order key, transaction id or an exact meta
 * value) and does not name a type, balance payments are included. Lists and reports, which
 * filter by status or date, are left alone.
 */
final class GatewayLookup implements Module {

	public function __construct( Plugin $plugin ) {}

	public function register( Plugin $plugin ): void {
		add_filter( 'woocommerce_order_query_args', array( $this, 'include_payments' ) );
	}

	public function include_payments( $args ) {
		if ( ! is_array( $args ) || ! $this->default_type( $args ) || ! $this->is_lookup( $args ) ) {
			return $args;
		}
		$args['type'] = array_values( array_unique( array_merge( (array) $args['type'], array( Payment::TYPE ) ) ) );
		return $args;
	}

	/**
	 * The caller did not choose a type: it is still WooCommerce's default list.
	 */
	private function default_type( array $args ): bool {
		if ( ! isset( $args['type'] ) ) {
			return true;
		}
		$type    = (array) $args['type'];
		$default = (array) wc_get_order_types( 'view-orders' );
		sort( $type );
		sort( $default );
		return $type === $default;
	}

	/**
	 * The query names particular orders rather than listing them.
	 */
	private function is_lookup( array $args ): bool {
		foreach ( array( 'post__in', 'transaction_id', 'order_key' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				return true;
			}
		}

		if ( ! empty( $args['meta_key'] ) && isset( $args['meta_value'] ) && '' !== $args['meta_value'] && ( empty( $args['meta_compare'] ) || '=' === $args['meta_compare'] ) ) {
			return true;
		}

		if ( ! empty( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
			foreach ( $args['meta_query'] as $clause ) {
				if ( is_array( $clause ) && isset( $clause['key'], $clause['value'] ) && is_scalar( $clause['value'] ) && '' !== $clause['value'] && ( empty( $clause['compare'] ) || '=' === $clause['compare'] ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
