<?php
/**
 * Runs when the plugin is deleted.
 *
 * Always: orders in the plugin's "Partially paid" status move to "On hold", so they stay
 * visible once the status no longer exists.
 * Only with "Delete all data" on: balance payments with their refunds, items and notes,
 * Order Balance meta, settings and scheduled actions are removed.
 *
 * WooCommerce may already be inactive here, so this works on the database directly and
 * covers both order storages (HPOS tables and posts).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-off cleanup on uninstall, WooCommerce may be inactive.

if ( ! function_exists( 'obal_uninstall_site' ) ) {

	/**
	 * @param string $table Full table name.
	 */
	function obal_uninstall_table_exists( string $table ): bool {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Placeholders for a list of ids: "%d,%d,%d".
	 *
	 * @param int[] $ids
	 */
	function obal_uninstall_placeholders( array $ids ): string {
		return implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	}

	/**
	 * Runs a prepared query with a table name and a list of ids: "... %i ... IN (%d,...)".
	 *
	 * @param string $sql   SQL with "%i" placeholders for the tables and "{ids}" for the id list.
	 * @param array  $names Table and column names, in the order of the "%i" placeholders.
	 * @param int[]  $ids
	 * @param string $method "query" or "get_col".
	 * @return mixed
	 */
	function obal_uninstall_run( string $sql, array $names, array $ids, string $method = 'query' ) {
		global $wpdb;
		$sql = str_replace( '{ids}', obal_uninstall_placeholders( $ids ), $sql );
		return $wpdb->$method( $wpdb->prepare( $sql, array_merge( $names, array_map( 'absint', $ids ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared here, placeholders only.
	}

	function obal_uninstall_site(): void {
		global $wpdb;

		$hpos     = $wpdb->prefix . 'wc_orders';
		$has_hpos = obal_uninstall_table_exists( $hpos );

		if ( $has_hpos ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s WHERE status = %s', $hpos, 'wc-on-hold', 'wc-obal-partial' ) );
		}
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET post_status = %s WHERE post_status = %s', $wpdb->posts, 'wc-on-hold', 'wc-obal-partial' ) );

		if ( 'yes' !== get_option( 'obal_delete_data', 'no' ) ) {
			wp_cache_flush();
			return;
		}

		// Balance payments and their refunds, in either storage.
		$payments = $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE post_type = %s', $wpdb->posts, 'obal_payment' ) );
		if ( $has_hpos ) {
			$payments = array_merge( $payments, $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE type = %s', $hpos, 'obal_payment' ) ) );
		}
		$payments = array_values( array_unique( array_map( 'absint', $payments ) ) );

		$ids = $payments;
		foreach ( array_chunk( $payments, 500 ) as $chunk ) {
			$ids = array_merge( $ids, (array) obal_uninstall_run( "SELECT ID FROM %i WHERE post_type = 'shop_order_refund' AND post_parent IN ({ids})", array( $wpdb->posts ), $chunk, 'get_col' ) );
			if ( $has_hpos ) {
				$ids = array_merge( $ids, (array) obal_uninstall_run( "SELECT id FROM %i WHERE type = 'shop_order_refund' AND parent_order_id IN ({ids})", array( $hpos ), $chunk, 'get_col' ) );
			}
		}
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );

		$items     = $wpdb->prefix . 'woocommerce_order_items';
		$itemmeta  = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$has_items = obal_uninstall_table_exists( $items );
		$by_order  = array(
			$wpdb->prefix . 'wc_orders_meta'            => 'order_id',
			$wpdb->prefix . 'wc_order_addresses'        => 'order_id',
			$wpdb->prefix . 'wc_order_operational_data' => 'order_id',
			$wpdb->prefix . 'wc_order_stats'            => 'order_id',
			$wpdb->prefix . 'wc_order_tax_lookup'       => 'order_id',
			$wpdb->prefix . 'wc_order_coupon_lookup'    => 'order_id',
			$wpdb->prefix . 'wc_order_product_lookup'   => 'order_id',
			$hpos                                       => 'id',
		);
		$existing  = array_filter( array_keys( $by_order ), 'obal_uninstall_table_exists' );

		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			if ( $has_items ) {
				obal_uninstall_run( 'DELETE im FROM %i im INNER JOIN %i i ON i.order_item_id = im.order_item_id WHERE i.order_id IN ({ids})', array( $itemmeta, $items ), $chunk );
				obal_uninstall_run( 'DELETE FROM %i WHERE order_id IN ({ids})', array( $items ), $chunk );
			}

			obal_uninstall_run( 'DELETE cm FROM %i cm INNER JOIN %i c ON c.comment_ID = cm.comment_id WHERE c.comment_post_ID IN ({ids})', array( $wpdb->commentmeta, $wpdb->comments ), $chunk );
			obal_uninstall_run( 'DELETE FROM %i WHERE comment_post_ID IN ({ids})', array( $wpdb->comments ), $chunk );

			foreach ( $existing as $table ) {
				obal_uninstall_run( 'DELETE FROM %i WHERE %i IN ({ids})', array( $table, $by_order[ $table ] ), $chunk );
			}

			obal_uninstall_run( 'DELETE FROM %i WHERE post_id IN ({ids})', array( $wpdb->postmeta ), $chunk );
			obal_uninstall_run( 'DELETE FROM %i WHERE ID IN ({ids})', array( $wpdb->posts ), $chunk );
		}

		// Order Balance meta on the orders, refunds and order lines that stay.
		$like = $wpdb->esc_like( '_obal_' ) . '%';
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->postmeta, $like ) );
		if ( obal_uninstall_table_exists( $wpdb->prefix . 'wc_orders_meta' ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->prefix . 'wc_orders_meta', $like ) );
		}
		if ( $has_items ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $itemmeta, $like ) );
		}

		// Settings, email settings and transients.
		foreach ( array( 'obal_', 'woocommerce_obal_', '_transient_obal_', '_transient_timeout_obal_' ) as $prefix ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( $prefix ) . '%' ) );
		}

		// Scheduled actions (payment reminders).
		$actions = $wpdb->prefix . 'actionscheduler_actions';
		if ( obal_uninstall_table_exists( $actions ) ) {
			$hook = $wpdb->esc_like( 'obal_' ) . '%';
			if ( obal_uninstall_table_exists( $wpdb->prefix . 'actionscheduler_logs' ) ) {
				$wpdb->query( $wpdb->prepare( 'DELETE l FROM %i l INNER JOIN %i a ON a.action_id = l.action_id WHERE a.hook LIKE %s', $wpdb->prefix . 'actionscheduler_logs', $actions, $hook ) );
			}
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE hook LIKE %s', $actions, $hook ) );
			if ( obal_uninstall_table_exists( $wpdb->prefix . 'actionscheduler_groups' ) ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE slug = %s', $wpdb->prefix . 'actionscheduler_groups', 'order-balance' ) );
			}
		}

		wp_cache_flush();
	}
}

// phpcs:enable

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $obal_site_id ) {
		switch_to_blog( (int) $obal_site_id );
		obal_uninstall_site();
		restore_current_blog();
	}
} else {
	obal_uninstall_site();
}
