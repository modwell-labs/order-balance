<?php

namespace ModwellLabs\OrderBalance\Admin;

use Automattic\WooCommerce\Utilities\OrderUtil;
use ModwellLabs\OrderBalance\Module;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Template;

defined( 'ABSPATH' ) || exit;

final class AdminPage implements Module {

	public const SLUG   = 'order-balance';
	public const NONCE  = 'obal-page';
	public const NOTICE = 'obal_notice_';

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_init', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ORDER_BALANCE_FILE ), array( $this, 'action_links' ) );
	}

	public static function url( string $tab = 'payments', array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	public static function payment_url( int $payment_id ): string {
		return self::url( 'payments', array( 'payment' => $payment_id ) );
	}

	public static function notice( string $message, string $type = 'success' ): void {
		set_transient( self::NOTICE . get_current_user_id(), array( $message, $type ), MINUTE_IN_SECONDS );
	}

	public function menu(): void {
		$hook = add_menu_page(
			__( 'Order Balance', 'order-balance' ),
			__( 'Order Balance', 'order-balance' ),
			'edit_shop_orders',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-money-alt',
			'55.7'
		);
		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'load' ) );
		}

		$first = true;
		foreach ( $this->tabs() as $id => $label ) {
			$cap = in_array( $id, array( 'settings', 'emails' ), true ) ? 'manage_woocommerce' : 'edit_shop_orders';
			add_submenu_page( self::SLUG, $label, $label, $cap, $first ? self::SLUG : 'admin.php?page=' . self::SLUG . '&tab=' . $id );
			$first = false;
		}
		add_filter( 'submenu_file', array( $this, 'submenu_file' ), 10, 2 );
	}

	public function submenu_file( $submenu_file, $parent_file ) {
		if ( self::SLUG !== $parent_file && ( ! isset( $_GET['page'] ) || self::SLUG !== $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $submenu_file;
		}
		$tab = $this->current_tab();
		return 'payments' === $tab ? self::SLUG : 'admin.php?page=' . self::SLUG . '&tab=' . $tab;
	}

	public function load(): void {
		if ( 'payments' === $this->current_tab() ) {
			( new PaymentsTable( $this->plugin ) )->process_bulk();
		}
	}

	public function tabs(): array {
		return apply_filters(
			'obal_admin_tabs',
			array(
				'payments' => __( 'Payments', 'order-balance' ),
				'settings' => __( 'Settings', 'order-balance' ),
				'emails'   => __( 'Emails', 'order-balance' ),
				'about'    => __( 'About', 'order-balance' ),
			)
		);
	}

	public function current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'payments'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array_key_exists( $tab, $this->tabs() ) ? $tab : 'payments';
	}

	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'order-balance' ) . '</a>' );
		return $links;
	}

	public function assets( string $hook ): void {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'obal-admin', ORDER_BALANCE_URL . 'assets/admin.css', array(), ORDER_BALANCE_VERSION );
		wp_enqueue_script( 'obal-admin', ORDER_BALANCE_URL . 'assets/admin.js', array( 'jquery' ), ORDER_BALANCE_VERSION, true );
		wp_localize_script(
			'obal-admin',
			'obalAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Ajax::NONCE ),
				'error'   => __( 'Something went wrong. Reload the page and try again.', 'order-balance' ),
				'copied'  => __( 'Copied', 'order-balance' ),
			)
		);
	}

	public function render(): void {
		$tab     = $this->current_tab();
		$payment = isset( $_GET['payment'] ) ? wc_get_order( absint( wp_unslash( $_GET['payment'] ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice  = get_transient( self::NOTICE . get_current_user_id() );
		delete_transient( self::NOTICE . get_current_user_id() );

		echo '<div class="wrap obal-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Order Balance', 'order-balance' ) . '</h1>';

		if ( is_array( $notice ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice[1] ), esc_html( $notice[0] ) );
		}

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $this->tabs() as $id => $label ) {
			printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( self::url( $id ) ), $id === $tab ? ' nav-tab-active' : '', esc_html( $label ) );
		}
		echo '</nav>';

		if ( 'payments' === $tab && $payment instanceof Payment ) {
			$this->plugin->get( PaymentScreen::class )->render( $payment );
		} elseif ( 'payments' === $tab ) {
			$this->render_payments();
		} elseif ( 'settings' === $tab && current_user_can( 'manage_woocommerce' ) ) {
			$this->render_settings();
		} elseif ( 'emails' === $tab && current_user_can( 'manage_woocommerce' ) ) {
			Template::render( 'admin/emails.php', array( 'emails' => $this->emails() ) );
		} elseif ( 'about' === $tab ) {
			Template::render( 'admin/about.php', array( 'info' => $this->environment() ) );
		} else {
			do_action( 'obal_admin_tab_' . $tab, $this->plugin );
		}

		echo '</div>';
	}

	private function render_payments(): void {
		$table = new PaymentsTable( $this->plugin );
		$table->prepare_items();
		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s"><input type="hidden" name="tab" value="payments">', esc_attr( self::SLUG ) );
		$table->views();
		$table->search_box( __( 'Search payments', 'order-balance' ), 'obal-payments' );
		$table->display();
		echo '</form>';
	}

	private function render_settings(): void {
		$this->load_settings_api();
		echo '<form method="post">';
		\WC_Admin_Settings::output_fields( $this->plugin->settings()->fields() );
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="obal_save" value="settings">';
		submit_button( __( 'Save changes', 'order-balance' ) );
		echo '</form>';
	}

	public function save(): void {
		if ( empty( $_POST['obal_save'] ) || ! isset( $_GET['page'] ) || self::SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( self::NONCE );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'order-balance' ), 403 );
		}

		$section = sanitize_key( wp_unslash( $_POST['obal_save'] ) );

		if ( 'settings' === $section ) {
			$this->load_settings_api();
			\WC_Admin_Settings::save_fields( $this->plugin->settings()->fields() );
		} elseif ( 'emails' === $section ) {
			$enabled = isset( $_POST['obal_email'] ) ? array_map( 'sanitize_key', array_keys( (array) wp_unslash( $_POST['obal_email'] ) ) ) : array();
			foreach ( $this->emails() as $email ) {
				$option              = 'woocommerce_' . $email->id . '_settings';
				$settings            = (array) get_option( $option, array() );
				$settings['enabled'] = in_array( $email->id, $enabled, true ) ? 'yes' : 'no';
				update_option( $option, $settings );
			}
		} else {
			do_action( 'obal_admin_save_' . $section, $this->plugin );
		}

		self::notice( __( 'Settings saved.', 'order-balance' ) );
		wp_safe_redirect( self::url( $this->current_tab() ) );
		exit;
	}

	/**
	 * @return array<string, \WC_Email>
	 */
	public function emails(): array {
		return array_filter(
			WC()->mailer()->get_emails(),
			static function ( $email ) {
				return 0 === strpos( $email->id, 'obal_' );
			}
		);
	}

	private function environment(): array {
		$hpos = OrderUtil::custom_orders_table_usage_is_enabled();
		$sync = 'yes' === get_option( 'woocommerce_custom_orders_table_data_sync_enabled' );

		return apply_filters(
			'obal_about_info',
			array(
				__( 'Order Balance', 'order-balance' ) => ORDER_BALANCE_VERSION,
				__( 'WordPress', 'order-balance' )     => get_bloginfo( 'version' ),
				__( 'WooCommerce', 'order-balance' )   => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				__( 'PHP', 'order-balance' )           => PHP_VERSION,
				__( 'Order storage', 'order-balance' ) => $hpos ? ( $sync ? __( 'HPOS with compatibility sync', 'order-balance' ) : __( 'HPOS', 'order-balance' ) ) : __( 'Legacy (posts)', 'order-balance' ),
			)
		);
	}

	private function load_settings_api(): void {
		if ( ! class_exists( 'WC_Admin_Settings', false ) ) {
			include_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
		}
	}
}
