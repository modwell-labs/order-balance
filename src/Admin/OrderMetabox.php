<?php

namespace ModwellLabs\OrderBalance\Admin;

use Automattic\WooCommerce\Utilities\OrderUtil;
use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Payments\PaymentRepository;
use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Template;

defined( 'ABSPATH' ) || exit;

final class OrderMetabox implements Module {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public static function screen(): string {
		return OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	}

	public function add(): void {
		add_meta_box( 'obal-balance', __( 'Balance', 'modwell-order-balance-for-woocommerce' ), array( $this, 'render' ), self::screen(), 'side', 'high' );
	}

	public function render( $object ): void {
		$order = $object instanceof \WP_Post ? wc_get_order( $object->ID ) : $object;
		if ( ! PaymentRepository::is_parent( $order ) ) {
			return;
		}

		echo $this->html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
	}

	/**
	 * The box content for the order as it is saved now. Also served by the refresh request,
	 * so the box can follow item edits that WooCommerce makes over AJAX.
	 */
	public function html( \WC_Order $order ): string {
		$service  = $this->plugin->service();
		$settings = $this->plugin->settings();
		$modes    = $settings->mode_labels();
		$mode     = $settings->order_mode( $order );

		return Template::html(
			'admin/metabox.php',
			array(
				'order'       => $order,
				'balance'     => $this->plugin->balance()->for_order( $order ),
				'payments'    => $this->plugin->payments()->for_order( $order ),
				'threshold'   => $settings->threshold(),
				'can_request' => $service->can_request( $order ),
				'plan'        => $service->waiting_plan( $order ),
				'waiting'     => $service->waiting_mode( $order ),
				'last_notice' => $service->last_notice( $order ),
				'mode_label'  => isset( $modes[ $mode ] ) ? $modes[ $mode ] : '',
			)
		);
	}

	public function assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || self::screen() !== $screen->id ) {
			return;
		}

		wp_enqueue_style( 'obal-admin', ORDER_BALANCE_URL . 'assets/admin.css', array(), ORDER_BALANCE_VERSION );
		wp_enqueue_script( 'obal-admin', ORDER_BALANCE_URL . 'assets/admin.js', array( 'jquery' ), ORDER_BALANCE_VERSION, true );
		wp_localize_script(
			'obal-admin',
			'obalAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Ajax::NONCE ),
				'error'   => __( 'Something went wrong. Reload the page and try again.', 'modwell-order-balance-for-woocommerce' ),
				'copied'  => __( 'Copied', 'modwell-order-balance-for-woocommerce' ),
			)
		);
	}
}
