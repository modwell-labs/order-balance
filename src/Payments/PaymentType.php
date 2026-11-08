<?php

namespace ModwellLabs\OrderBalance\Payments;

use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

final class PaymentType implements Module {

	public const STATUS = 'obal-partial';

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		add_action( 'woocommerce_after_register_post_type', array( $this, 'register_type' ) );
		add_action( 'init', array( $this, 'register_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'order_statuses' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'paid_statuses' ) );
		add_filter( 'wc_order_is_editable', array( $this, 'editable' ), 10, 2 );
		add_filter( 'woocommerce_order_number', array( $this, 'number' ), 10, 2 );
	}

	public function register_type(): void {
		wc_register_order_type(
			Payment::TYPE,
			array(
				'labels'                           => array(
					'name'          => __( 'Balance payments', 'order-balance' ),
					'singular_name' => __( 'Balance payment', 'order-balance' ),
					'edit_item'     => __( 'Edit balance payment', 'order-balance' ),
					'search_items'  => __( 'Search balance payments', 'order-balance' ),
					'parent'        => __( 'Order', 'order-balance' ),
					'menu_name'     => __( 'Balance payments', 'order-balance' ),
				),
				'public'                           => false,
				'show_ui'                          => true,
				'show_in_menu'                     => false,
				'show_in_nav_menus'                => false,
				'publicly_queryable'               => false,
				'exclude_from_search'              => true,
				'hierarchical'                     => false,
				'rewrite'                          => false,
				'query_var'                        => false,
				'has_archive'                      => false,
				'supports'                         => false,
				'capability_type'                  => 'shop_order',
				'capabilities'                     => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'                     => true,
				'exclude_from_orders_screen'       => true,
				'add_order_meta_boxes'             => true,
				'exclude_from_order_count'         => true,
				'exclude_from_order_views'         => true,
				'exclude_from_order_webhooks'      => true,
				'exclude_from_order_reports'       => true,
				'exclude_from_order_sales_reports' => true,
				'class_name'                       => Payment::class,
			)
		);
	}

	public function register_status(): void {
		register_post_status(
			'wc-' . self::STATUS,
			array(
				'label'                     => _x( 'Partially paid', 'Order status', 'order-balance' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Partially paid <span class="count">(%s)</span>', 'Partially paid <span class="count">(%s)</span>', 'order-balance' ),
			)
		);
	}

	public function order_statuses( array $statuses ): array {
		$result = array();
		foreach ( $statuses as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'wc-on-hold' === $key ) {
				$result[ 'wc-' . self::STATUS ] = _x( 'Partially paid', 'Order status', 'order-balance' );
			}
		}
		if ( ! isset( $result[ 'wc-' . self::STATUS ] ) ) {
			$result[ 'wc-' . self::STATUS ] = _x( 'Partially paid', 'Order status', 'order-balance' );
		}
		return $result;
	}

	public function paid_statuses( array $statuses ): array {
		$statuses[] = self::STATUS;
		return array_unique( $statuses );
	}

	public function editable( bool $editable, $order ): bool {
		return $editable || ( PaymentRepository::is_parent( $order ) && $order->has_status( self::STATUS ) );
	}

	public function number( $number, $order ) {
		if ( $order instanceof Payment ) {
			$parent = $order->get_parent_order();
			if ( $parent ) {
				return $parent->get_order_number() . '-' . max( 1, $order->get_sequence() );
			}
		}
		return $number;
	}
}
