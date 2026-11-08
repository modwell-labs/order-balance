<?php

namespace ModwellLabs\OrderBalance\Admin;

use ModwellLabs\OrderBalance\Module;
use Automattic\WooCommerce\Utilities\OrderUtil;
use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Template;

defined( 'ABSPATH' ) || exit;

/**
 * First-run setup: walks through every settings section, then emails.
 *
 * Steps are built from the settings sections, so sections added by other code (Pro)
 * appear automatically: known ones are placed by the step map, the rest go to "More options".
 */
final class SetupWizard implements Module {

	public const PAGE     = 'order-balance-setup';
	public const DONE     = 'obal_setup_done';
	public const REDIRECT = 'obal_setup_redirect';
	public const NONCE    = 'obal-setup';

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register( Plugin $plugin ): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 70 );
		add_action( 'admin_head', array( $this, 'hide_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_init', array( $this, 'save' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Called from the activation hook.
	 */
	public static function activated(): void {
		if ( 'yes' !== get_option( self::DONE ) ) {
			set_transient( self::REDIRECT, 1, MINUTE_IN_SECONDS );
		}
	}

	public static function url( string $step = '' ): string {
		$args = array( 'page' => self::PAGE );
		if ( '' !== $step ) {
			$args['step'] = $step;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public function is_done(): bool {
		return in_array( get_option( self::DONE ), array( 'yes', 'skipped' ), true );
	}

	public function menu(): void {
		add_submenu_page( AdminPage::SLUG, __( 'Order Balance setup', 'order-balance' ), __( 'Setup', 'order-balance' ), 'manage_woocommerce', self::PAGE, array( $this, 'render' ) );
	}

	public function hide_menu(): void {
		remove_submenu_page( AdminPage::SLUG, self::PAGE );
	}

	public function maybe_redirect(): void {
		if ( ! get_transient( self::REDIRECT ) ) {
			return;
		}
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		delete_transient( self::REDIRECT );
		if ( $this->is_done() ) {
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	public function notice(): void {
		if ( $this->is_done() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( 'plugins' !== $screen->id && false === strpos( $screen->id, AdminPage::SLUG ) ) || false !== strpos( $screen->id, self::PAGE ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a> <a class="button" href="%5$s">%6$s</a></p></div>',
			esc_html__( 'Order Balance is installed.', 'order-balance' ),
			esc_html__( 'Take two minutes to choose how payments, balances and emails work in your store.', 'order-balance' ),
			esc_url( self::url() ),
			esc_html__( 'Run the setup', 'order-balance' ),
			esc_url( wp_nonce_url( add_query_arg( 'obal_setup', 'skip', self::url() ), self::NONCE ) ),
			esc_html__( 'Skip', 'order-balance' )
		);
	}

	public function assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'jquery-tiptip' );
		wp_enqueue_style( 'obal-admin', ORDER_BALANCE_URL . 'assets/admin.css', array(), ORDER_BALANCE_VERSION );
		wp_enqueue_script( 'obal-admin', ORDER_BALANCE_URL . 'assets/admin.js', array( 'jquery', 'jquery-tiptip' ), ORDER_BALANCE_VERSION, true );
	}

	/**
	 * Step map: step id => label and the settings sections shown on it.
	 * A section that is not listed lands on the "more" step.
	 *
	 * @return array<string, array{label: string, sections?: string[], type?: string, intro?: string}>
	 */
	public function steps(): array {
		$steps = array(
			'welcome'  => array(
				'label' => __( 'Welcome', 'order-balance' ),
				'type'  => 'welcome',
			),
			'payments' => array(
				'label'    => __( 'Payment methods', 'order-balance' ),
				'sections' => $this->plugin->settings()->category_sections( 'payments' ),
				'intro'    => __( 'Order Balance needs to know when an order counts as paid. Card and wallet payments are paid at checkout. Cash on delivery and bank transfer are paid later, so the whole total stays due until you mark it paid.', 'order-balance' ),
			),
			'status'   => array(
				'label'    => __( 'Order status', 'order-balance' ),
				'sections' => $this->plugin->settings()->category_sections( 'status' ),
				'intro'    => __( 'Which status an order has while a balance payment is outstanding, and after it has been paid.', 'order-balance' ),
			),
			'changes'  => array(
				'label'    => __( 'Order changes', 'order-balance' ),
				'sections' => $this->plugin->settings()->category_sections( 'changes' ),
				'intro'    => __( 'What happens to a balance payment that is still waiting when the order changes again.', 'order-balance' ),
			),
			'rounding' => array(
				'label'    => __( 'Rounding', 'order-balance' ),
				'sections' => $this->plugin->settings()->category_sections( 'rounding' ),
				'intro'    => __( 'Absorb a small difference, for example from tax rounding, with a rounding line on the order instead of asking for a payment or a refund.', 'order-balance' ),
			),
			'fees'     => array(
				'label'    => __( 'Fees', 'order-balance' ),
				'sections' => $this->plugin->settings()->category_sections( 'fees' ),
				'intro'    => __( 'How fees on balance payments are treated. Fees never count toward the order total.', 'order-balance' ),
			),
			'emails'   => array(
				'label' => __( 'Emails', 'order-balance' ),
				'type'  => 'emails',
			),
			'more'     => array(
				'label'    => __( 'More options', 'order-balance' ),
				'sections' => array(),
			),
			'ready'    => array(
				'label' => __( 'Ready', 'order-balance' ),
				'type'  => 'ready',
			),
		);

		$steps = (array) apply_filters( 'obal_setup_steps', $steps );

		// Only sections with something to set count; a section that only explains (such as
		// "Order changes" in the free version) stays in Settings. Unplaced sections go to "more".
		// Data handling stays in Settings only.
		$all = array_keys(
			array_filter(
				$this->plugin->settings()->sections(),
				static function ( array $fields ) {
					foreach ( $fields as $field ) {
						if ( isset( $field['type'] ) && ! in_array( $field['type'], array( 'title', 'sectionend' ), true ) ) {
							return true;
						}
					}
					return false;
				}
			)
		);
		$placed = array( 'obal_data' );
		foreach ( $steps as $step ) {
			$placed = array_merge( $placed, isset( $step['sections'] ) ? $step['sections'] : array() );
		}
		if ( isset( $steps['more'] ) ) {
			$steps['more']['sections'] = array_merge( $steps['more']['sections'], array_values( array_diff( $all, $placed ) ) );
		}

		// Drop section steps that have nothing to show.
		return array_filter(
			$steps,
			function ( array $step ) use ( $all ) {
				return isset( $step['type'] ) || array_intersect( isset( $step['sections'] ) ? $step['sections'] : array(), $all );
			}
		);
	}

	public function current_step(): string {
		$steps = $this->steps();
		$step  = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $steps[ $step ] ) ? $step : (string) key( $steps );
	}

	public function next_step( string $step ): string {
		$keys  = array_keys( $this->steps() );
		$index = array_search( $step, $keys, true );
		return false !== $index && isset( $keys[ $index + 1 ] ) ? $keys[ $index + 1 ] : $step;
	}

	public function previous_step( string $step ): string {
		$keys  = array_keys( $this->steps() );
		$index = array_search( $step, $keys, true );
		return false !== $index && $index > 0 ? $keys[ $index - 1 ] : '';
	}

	/**
	 * Settings fields for the step's sections, in settings order.
	 */
	public function fields( string $step ): array {
		$steps    = $this->steps();
		$wanted   = isset( $steps[ $step ]['sections'] ) ? $steps[ $step ]['sections'] : array();
		$sections = $this->plugin->settings()->sections();
		$fields   = array();
		foreach ( $sections as $id => $section_fields ) {
			if ( in_array( $id, $wanted, true ) ) {
				$fields = array_merge( $fields, $section_fields );
			}
		}
		return $fields;
	}

	public function save(): void {
		if ( ! isset( $_GET['page'] ) || self::PAGE !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( isset( $_GET['obal_setup'] ) && 'skip' === $_GET['obal_setup'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( self::NONCE );
			if ( current_user_can( 'manage_woocommerce' ) ) {
				update_option( self::DONE, 'skipped', false );
			}
			wp_safe_redirect( AdminPage::url( 'settings' ) );
			exit;
		}

		if ( empty( $_POST['obal_setup_step'] ) ) {
			return;
		}
		check_admin_referer( self::NONCE );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'order-balance' ), 403 );
		}

		$step  = sanitize_key( wp_unslash( $_POST['obal_setup_step'] ) );
		$steps = $this->steps();
		if ( ! isset( $steps[ $step ] ) ) {
			wp_safe_redirect( self::url() );
			exit;
		}

		$type = isset( $steps[ $step ]['type'] ) ? $steps[ $step ]['type'] : 'settings';
		if ( 'settings' === $type ) {
			$page = $this->plugin->get( AdminPage::class );
			$page->load_settings_api();
			\WC_Admin_Settings::save_fields( $this->fields( $step ) );
		} elseif ( 'emails' === $type ) {
			$this->plugin->get( AdminPage::class )->save_emails();
		}

		do_action( 'obal_setup_saved', $step, $this->plugin );

		$next = $this->next_step( $step );
		if ( 'ready' === $next || $next === $step ) {
			update_option( self::DONE, 'yes', false );
		}

		wp_safe_redirect( self::url( $next ) );
		exit;
	}

	public function render(): void {
		$step  = $this->current_step();
		$steps = $this->steps();
		$page  = $this->plugin->get( AdminPage::class );

		if ( 'ready' === $step ) {
			update_option( self::DONE, 'yes', false );
		}

		$page->load_settings_api();

		// A single-section step already shows the section as the step heading and intro.
		$fields = $this->fields( $step );
		$titles = array_filter(
			$fields,
			static function ( $field ) {
				return isset( $field['type'] ) && 'title' === $field['type'];
			}
		);
		if ( 1 === count( $titles ) ) {
			foreach ( $fields as $index => $field ) {
				if ( isset( $field['type'] ) && 'title' === $field['type'] ) {
					$fields[ $index ]['title'] = '';
					if ( ! empty( $steps[ $step ]['intro'] ) ) {
						$fields[ $index ]['desc'] = '';
					}
				}
			}
		}

		Template::render(
			'admin/setup.php',
			array(
				'steps'    => $steps,
				'step'     => $step,
				'type'     => isset( $steps[ $step ]['type'] ) ? $steps[ $step ]['type'] : 'settings',
				'fields'   => $fields,
				'emails'   => $page->emails(),
				'previous' => $this->previous_step( $step ),
				'next'     => $this->next_step( $step ),
				'store'    => $this->store_summary(),
				'skip_url' => wp_nonce_url( add_query_arg( 'obal_setup', 'skip', self::url() ), self::NONCE ),
				'orders'   => admin_url( class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' ),
			)
		);
	}

	/**
	 * Facts about the store shown on the welcome step.
	 *
	 * @return array<string, string>
	 */
	private function store_summary(): array {
		$enabled = array();
		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			if ( 'yes' === $gateway->enabled ) {
				$enabled[] = $gateway->get_title();
			}
		}
		return array(
			__( 'Currency', 'order-balance' )        => get_woocommerce_currency() . ' (' . html_entity_decode( get_woocommerce_currency_symbol() ) . ')',
			__( 'Payment methods', 'order-balance' ) => $enabled ? implode( ', ', $enabled ) : __( 'None enabled yet', 'order-balance' ),
			__( 'Taxes', 'order-balance' )           => wc_tax_enabled() ? __( 'Enabled', 'order-balance' ) : __( 'Disabled', 'order-balance' ),
		);
	}
}
