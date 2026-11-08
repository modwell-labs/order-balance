<?php
/**
 * Test group "admin". Included by smoke.php, which provides the helpers and shares its variables.
 *
 * @var callable                                $check          Records a check: label, bool, detail.
 * @var object                                  $plugin         The Order Balance plugin instance.
 * @var callable                                $product        Product by name.
 * @var callable                                $new_paid_order A paid order with one T-shirt.
 * @var callable                                $later_order    An order paid later: method, status.
 * @var array                                   $obal_mails     Subjects and recipients of sent emails.
 */

use ModwellLabs\OrderBalance\Payments\BalanceCalculator;
use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Payments\PaymentType;
use ModwellLabs\OrderBalance\Plugin;
use ModwellLabs\OrderBalance\Template;

// Fixture: a paid balance payment to look at.
$adm_order = $new_paid_order();
$adm_order->add_product( $product( 'Mug' ), 1 );
$adm_order->calculate_totals();
$open = $plugin->service()->request( wc_get_order( $adm_order->get_id() ), null, false );
$open->payment_complete();
$open = wc_get_order( $open->get_id() );

require_once ABSPATH . 'wp-admin/includes/admin.php';
include_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
wp_set_current_user( 1 );
set_current_screen( 'toplevel_page_order-balance' );

$table = new \ModwellLabs\OrderBalance\Admin\PaymentsTable( $plugin );
$table->prepare_items();
$check( 'payments table lists payments', count( $table->items ) > 0, count( $table->items ) );

$page = $plugin->get( \ModwellLabs\OrderBalance\Admin\AdminPage::class );
foreach ( array( 'payments', 'settings', 'emails', 'about' ) as $tab ) {
	$_GET['tab'] = $tab;
	ob_start();
	$page->render();
	$html = ob_get_clean();
	$check( "admin tab {$tab} renders", false !== strpos( $html, 'nav-tab-active' ) && false === stripos( $html, 'fatal' ) );
}

// Settings: one page with a tab per category.
$categories = $plugin->settings()->categories();
$check( 'settings categories', array( 'payments', 'status', 'changes', 'rounding', 'fees', 'obal_reminders', 'data' ) === array_keys( $categories ), array_keys( $categories ) );
$check( 'settings categories: pro sections named after their title', 'Payment reminders' === $categories['obal_reminders']['label'] );
$expect = array(
	'payments'       => array( 'obal_gateway_modes', 'obal_auto_rounding' ),
	'status'         => array( 'obal_paid_status', 'obal_rounding_threshold' ),
	'changes'        => array( 'obal_waiting_mode', 'obal_paid_status' ),
	'rounding'       => array( 'obal_auto_rounding', 'obal_paid_status' ),
	'fees'           => array( 'obal_refund_payment_fees', 'obal_paid_status' ),
	'obal_reminders' => array( 'obal_reminder_days', 'obal_paid_status' ),
	'data'           => array( 'obal_delete_data', 'obal_paid_status' ),
);
$_GET['tab'] = 'settings';
foreach ( $expect as $category => $needles ) {
	$_GET['section'] = $category;
	ob_start();
	$page->render();
	$html = ob_get_clean();
	$check( "settings page {$category} shows only its own fields", false !== strpos( $html, 'name="' . $needles[0] ) && false === strpos( $html, 'name="' . $needles[1] ) && false !== strpos( $html, 'subsubsub' ) && false !== strpos( $html, 'name="obal_category" value="' . $category . '"' ) );
}
unset( $_GET['section'] );
ob_start();
$page->render();
$html = ob_get_clean();
$check( 'settings opens on the first category', false !== strpos( $html, 'obal_gateway_modes' ) );
$_GET['section'] = 'nonsense';
ob_start();
$page->render();
$html = ob_get_clean();
$check( 'unknown category falls back to the first', false !== strpos( $html, 'obal_gateway_modes' ) );
unset( $_GET['section'] );
$check( 'rounding category order: automatic first, then threshold', array( 'obal_rounding', 'obal_auto_rounding', 'obal_rounding_threshold', 'obal_adjustment_label', 'obal_rounding' ) === wp_list_pluck( $plugin->settings()->category_fields( 'rounding' ), 'id' ) );
$check( 'rounding is not a main tab', ! array_key_exists( 'rounding', $page->tabs() ) );
$_GET['tab']     = 'payments';
$_GET['payment'] = $open->get_id();
ob_start();
$page->render();
$html = ob_get_clean();
$check( 'payment screen renders with refund form', false !== strpos( $html, 'name="obal_action" value="refund"' ) );
$check( 'payment screen looks like an order', false !== strpos( $html, 'id="woocommerce-order-items"' ) && false !== strpos( $html, 'id="order_data"' ) && false !== strpos( $html, 'Credited to the order' ) && false !== strpos( $html, 'name="obal_note"' ) );

$wizard = $plugin->get( \ModwellLabs\OrderBalance\Admin\SetupWizard::class );
$steps  = array_keys( $wizard->steps() );
$check( 'setup steps', array( 'welcome', 'payments', 'status', 'changes', 'rounding', 'fees', 'emails', 'more', 'ready' ) === $steps, $steps );
$check( 'setup: pro sections placed', in_array( 'obal_change_fee', $wizard->steps()['fees']['sections'], true ) && in_array( 'obal_reminders', $wizard->steps()['more']['sections'], true ) );
$check( 'setup: data not in wizard', ! in_array( 'obal_data', array_merge( ...array_values( array_map( static function ( $s ) { return isset( $s['sections'] ) ? $s['sections'] : array(); }, $wizard->steps() ) ) ), true ) );
$rounding_ids = wp_list_pluck( $wizard->fields( 'rounding' ), 'id' );
$status_ids   = wp_list_pluck( $wizard->fields( 'status' ), 'id' );
$check( 'setup: rounding step has only rounding settings', array( 'obal_rounding', 'obal_auto_rounding', 'obal_rounding_threshold', 'obal_adjustment_label', 'obal_rounding' ) === $rounding_ids, $rounding_ids );
$check( 'setup: status step has only status settings', array( 'obal_statuses', 'obal_partial_status', 'obal_paid_status', 'obal_statuses' ) === $status_ids, $status_ids );
$check( 'settings: no mixed section left', ! isset( $plugin->settings()->sections()['obal_general'] ) );
$check( 'settings: data section last', 'obal_data' === array_key_last( $plugin->settings()->sections() ) );
foreach ( $steps as $obal_step ) {
	$_GET['page'] = \ModwellLabs\OrderBalance\Admin\SetupWizard::PAGE;
	$_GET['step'] = $obal_step;
	ob_start();
	$wizard->render();
	$html = ob_get_clean();
	$check( "setup step {$obal_step} renders", false !== strpos( $html, 'obal-steps' ) && false !== strpos( $html, 'is-current' ) );
}
unset( $_GET['page'], $_GET['step'] );
delete_option( \ModwellLabs\OrderBalance\Admin\SetupWizard::DONE );
unset( $_GET['payment'], $_GET['tab'] );

$check( 'payment edit url points to plugin page', false !== strpos( $open->get_edit_order_url(), 'page=order-balance' ) );

// Balance box: the order screen reloads it after WooCommerce recalculates items.
$box_order = $new_paid_order();
$metabox   = $plugin->get( \ModwellLabs\OrderBalance\Admin\OrderMetabox::class );
$html      = $metabox->html( wc_get_order( $box_order->get_id() ) );
$check( 'balance box renders the order', false !== strpos( $html, 'data-order="' . $box_order->get_id() . '"' ) && false !== strpos( $html, 'Balance due' ) );
$box_order->add_product( $product( 'Mug' ), 1 );
$box_order->calculate_totals();
$html = $metabox->html( wc_get_order( $box_order->get_id() ) );
$check( 'balance box follows the saved total', false !== strpos( $html, '37.00' ) && false !== strpos( $html, '12.00' ) && false !== strpos( $html, 'is-owed' ), $html );

$call_refresh = static function ( int $order_id ) use ( $plugin ) {
	$_POST = $_REQUEST = array(
		'action'   => 'obal_refresh',
		'nonce'    => wp_create_nonce( \ModwellLabs\OrderBalance\Admin\Ajax::NONCE ),
		'order_id' => $order_id,
	);
	$noop = static function () {
		return static function () {};
	};
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $noop );
	ob_start();
	$plugin->get( \ModwellLabs\OrderBalance\Admin\Ajax::class )->refresh();
	$out = ob_get_clean();
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', $noop );
	$_POST = $_REQUEST = array();
	return json_decode( $out, true );
};
$response = $call_refresh( $box_order->get_id() );
$check( 'refresh request returns the box', is_array( $response ) && ! empty( $response['success'] ) && false !== strpos( $response['data']['html'], 'data-order="' . $box_order->get_id() . '"' ) && false !== strpos( $response['data']['html'], 'is-owed' ), $response );
