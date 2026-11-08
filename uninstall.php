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

if ( ! function_exists( 'obal_uninstall_site' ) ) {

	/**
	 * @param string $table Full table name.
	 */
	function obal_uninstall_table_exists( string $table ): bool {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @param int[] $ids
	 */
	function obal_uninstall_in( array $ids ): string {
		return implode( ',', array_map( 'absint', $ids ) );
	}

	function obal_uninstall_site(): void {
		global $wpdb;

		$hpos     = $wpdb->prefix . 'wc_orders';
		$has_hpos = obal_uninstall_table_exists( $hpos );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $has_hpos ) {
			$wpdb->query( "UPDATE {$hpos} SET status = 'wc-on-hold' WHERE status = 'wc-obal-partial'" );
		}
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_status = 'wc-on-hold' WHERE post_status = 'wc-obal-partial'" );

		if ( 'yes' !== get_option( 'obal_delete_data', 'no' ) ) {
			wp_cache_flush();
			return;
		}

		// Balance payments and their refunds, in either storage.
		$payments = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'obal_payment' ) );
		if ( $has_hpos ) {
			$payments = array_merge( $payments, $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$hpos} WHERE type = %s", 'obal_payment' ) ) );
		}
		$payments = array_values( array_unique( array_map( 'absint', $payments ) ) );

		$ids = $payments;
		foreach ( array_chunk( $payments, 500 ) as $chunk ) {
			$in  = obal_uninstall_in( $chunk );
			$ids = array_merge( $ids, $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order_refund' AND post_parent IN ({$in})" ) );
			if ( $has_hpos ) {
				$ids = array_merge( $ids, $wpdb->get_col( "SELECT id FROM {$hpos} WHERE type = 'shop_order_refund' AND parent_order_id IN ({$in})" ) );
			}
		}
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );

		$items     = $wpdb->prefix . 'woocommerce_order_items';
		$itemmeta  = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$has_items = obal_uninstall_table_exists( $items );
		$by_order  = array(
			$wpdb->prefix . 'wc_orders_meta'             => 'order_id',
			$wpdb->prefix . 'wc_order_addresses'         => 'order_id',
			$wpdb->prefix . 'wc_order_operational_data'  => 'order_id',
			$wpdb->prefix . 'wc_order_stats'             => 'order_id',
			$wpdb->prefix . 'wc_order_tax_lookup'        => 'order_id',
			$wpdb->prefix . 'wc_order_coupon_lookup'     => 'order_id',
			$wpdb->prefix . 'wc_order_product_lookup'    => 'order_id',
			$hpos                                        => 'id',
		);
		$existing  = array_filter( array_keys( $by_order ), 'obal_uninstall_table_exists' );

		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$in = obal_uninstall_in( $chunk );

			if ( $has_items ) {
				$wpdb->query( "DELETE im FROM {$itemmeta} im INNER JOIN {$items} i ON i.order_item_id = im.order_item_id WHERE i.order_id IN ({$in})" );
				$wpdb->query( "DELETE FROM {$items} WHERE order_id IN ({$in})" );
			}

			$wpdb->query( "DELETE cm FROM {$wpdb->commentmeta} cm INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_post_ID IN ({$in})" );
			$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_post_ID IN ({$in})" );

			foreach ( $existing as $table ) {
				$wpdb->query( "DELETE FROM {$table} WHERE {$by_order[ $table ]} IN ({$in})" );
			}

			$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$in})" );
			$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$in})" );
		}

		// Order Balance meta on the orders, refunds and order lines that stay.
		$like = $wpdb->esc_like( '_obal_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like ) );
		if ( obal_uninstall_table_exists( $wpdb->prefix . 'wc_orders_meta' ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key LIKE %s", $like ) );
		}
		if ( $has_items ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$itemmeta} WHERE meta_key LIKE %s", $like ) );
		}

		// Settings, email settings and transients.
		foreach ( array( 'obal_', 'woocommerce_obal_', '_transient_obal_', '_transient_timeout_obal_' ) as $prefix ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
		}

		// Scheduled actions (payment reminders).
		$actions = $wpdb->prefix . 'actionscheduler_actions';
		if ( obal_uninstall_table_exists( $actions ) ) {
			$hook = $wpdb->esc_like( 'obal_' ) . '%';
			if ( obal_uninstall_table_exists( $wpdb->prefix . 'actionscheduler_logs' ) ) {
				$wpdb->query( $wpdb->prepare( "DELETE l FROM {$wpdb->prefix}actionscheduler_logs l INNER JOIN {$actions} a ON a.action_id = l.action_id WHERE a.hook LIKE %s", $hook ) );
			}
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$actions} WHERE hook LIKE %s", $hook ) );
			if ( obal_uninstall_table_exists( $wpdb->prefix . 'actionscheduler_groups' ) ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}actionscheduler_groups WHERE slug = %s", 'order-balance' ) );
			}
		}
		// phpcs:enable

		wp_cache_flush();
	}
}

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
