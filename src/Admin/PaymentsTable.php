<?php

namespace ModwellLabs\OrderBalance\Admin;

use ModwellLabs\OrderBalance\Payments\Payment;
use ModwellLabs\OrderBalance\Payments\PaymentActions;
use ModwellLabs\OrderBalance\Plugin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table', false ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class PaymentsTable extends \WP_List_Table {

	private const PER_PAGE = 20;

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		parent::__construct(
			array(
				'singular' => 'obal_payment',
				'plural'   => 'obal_payments',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return apply_filters(
			'obal_payments_columns',
			array(
				'cb'       => '<input type="checkbox">',
				'payment'  => __( 'Payment', 'order-balance' ),
				'order'    => __( 'Order', 'order-balance' ),
				'customer' => __( 'Payer', 'order-balance' ),
				'status'   => __( 'Status', 'order-balance' ),
				'method'   => __( 'Method', 'order-balance' ),
				'date'     => __( 'Date', 'order-balance' ),
				'amount'   => __( 'Amount', 'order-balance' ),
			)
		);
	}

	protected function get_sortable_columns() {
		return apply_filters(
			'obal_payments_sortable_columns',
			array(
				'payment' => array( 'ID', true ),
				'order'   => array( 'parent', false ),
				'date'    => array( 'date', true ),
				'amount'  => array( 'total', false ),
			)
		);
	}

	protected function get_bulk_actions() {
		return apply_filters( 'obal_payments_bulk_actions', array( 'cancel' => __( 'Cancel', 'order-balance' ) ) );
	}

	protected function get_views() {
		$current = $this->status_filter();
		$views   = array();
		$all     = array_merge( array( '' => __( 'All', 'order-balance' ) ), $this->statuses() );

		foreach ( $all as $status => $label ) {
			$count = $this->count( $status );
			if ( '' !== $status && 0 === $count ) {
				continue;
			}
			$views[ '' === $status ? 'all' : $status ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( AdminPage::url( 'payments', '' === $status ? array() : array( 'payment_status' => $status ) ) ),
				$current === $status ? ' class="current"' : '',
				esc_html( $label ),
				$count
			);
		}

		return $views;
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'payment' );

		$query = $this->query_args();

		$query['paginate'] = true;
		$query['limit']    = self::PER_PAGE;
		$query['page']     = $this->get_pagenum();

		$result      = wc_get_orders( $query );
		$this->items = array_filter(
			$result->orders,
			static function ( $order ) {
				return $order instanceof Payment;
			}
		);

		$this->set_pagination_args(
			array(
				'total_items' => (int) $result->total,
				'per_page'    => self::PER_PAGE,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No balance payments yet. Request one from a paid order that has changed.', 'order-balance' );
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="payment_ids[]" value="%d">', $item->get_id() );
	}

	protected function column_payment( Payment $item ) {
		return sprintf( '<a class="row-title" href="%s">#%s</a>', esc_url( AdminPage::payment_url( $item->get_id() ) ), esc_html( $item->get_order_number() ) );
	}

	protected function column_order( Payment $item ) {
		$parent = $item->get_parent_order();
		return $parent ? sprintf( '<a href="%s">#%s</a>', esc_url( $parent->get_edit_order_url() ), esc_html( $parent->get_order_number() ) ) : '&mdash;';
	}

	protected function column_customer( Payment $item ) {
		$name = trim( $item->get_formatted_billing_full_name() );
		return esc_html( $name ? $name : __( 'Guest', 'order-balance' ) ) . '<br><span class="description">' . esc_html( $item->get_billing_email() ) . '</span>';
	}

	protected function column_status( Payment $item ) {
		return sprintf( '<span class="obal-status status-%s">%s</span>', esc_attr( $item->get_status() ), esc_html( wc_get_order_status_name( $item->get_status() ) ) );
	}

	protected function column_method( Payment $item ) {
		return esc_html( $item->get_payment_method_title() ? $item->get_payment_method_title() : '—' );
	}

	protected function column_date( Payment $item ) {
		$date = $item->get_date_paid() ? $item->get_date_paid() : $item->get_date_created();
		return $date ? esc_html( wc_format_datetime( $date ) ) : '';
	}

	protected function column_amount( Payment $item ) {
		$html = wc_price( $item->get_total(), array( 'currency' => $item->get_currency() ) );
		if ( $item->get_total_refunded() > 0 ) {
			$html .= '<br><span class="description">' . sprintf(
				/* translators: %s: refunded amount */
				esc_html__( 'Refunded %s', 'order-balance' ),
				wp_kses_post( wc_price( $item->get_total_refunded(), array( 'currency' => $item->get_currency() ) ) )
			) . '</span>';
		}
		return $html;
	}

	protected function column_default( $item, $column_name ) {
		ob_start();
		do_action( 'obal_payments_column_' . $column_name, $item );
		return ob_get_clean();
	}

	private function statuses(): array {
		return array(
			'pending'   => __( 'Pending payment', 'order-balance' ),
			'on-hold'   => __( 'On hold', 'order-balance' ),
			'completed' => __( 'Paid', 'order-balance' ),
			'cancelled' => __( 'Cancelled', 'order-balance' ),
			'refunded'  => __( 'Refunded', 'order-balance' ),
			'failed'    => __( 'Failed', 'order-balance' ),
		);
	}

	private function status_filter(): string {
		$status = isset( $_GET['payment_status'] ) ? sanitize_key( wp_unslash( $_GET['payment_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array_key_exists( $status, $this->statuses() ) ? $status : '';
	}

	private function query_args( ?string $status = null ): array {
		$status = null === $status ? $this->status_filter() : $status;
		$allowed = array( 'ID', 'parent', 'date', 'total' );
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'ID'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = array(
			'type'    => Payment::TYPE,
			'orderby' => in_array( $orderby, $allowed, true ) ? $orderby : 'ID',
			'order'   => $order,
			'status'  => '' !== $status ? array( $status ) : array_keys( $this->statuses() ),
		);

		$search = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $search ) {
			if ( is_email( $search ) ) {
				$args['billing_email'] = $search;
			} elseif ( preg_match( '/^#?(\d+)(?:-\d+)?$/', $search, $match ) ) {
				$args['parent'] = absint( $match[1] );
			} else {
				$args['billing_last_name'] = $search;
			}
		}

		return apply_filters( 'obal_payments_query', $args );
	}

	private function count( string $status ): int {
		$args             = $this->query_args( $status );
		$args['limit']    = 1;
		$args['paginate'] = true;
		$args['return']   = 'ids';
		return (int) wc_get_orders( $args )->total;
	}

	public function process_bulk(): void {
		$action = $this->current_action();
		if ( ! $action || empty( $_GET['payment_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$actions = $this->plugin->get( PaymentActions::class );
		$done    = 0;

		foreach ( array_map( 'absint', (array) wp_unslash( $_GET['payment_ids'] ) ) as $id ) {
			$payment = wc_get_order( $id );
			if ( $payment instanceof Payment && ! is_wp_error( $actions->run( $action, $payment, array(), false ) ) ) {
				++$done;
			}
		}

		/* translators: %d: number of payments */
		AdminPage::notice( sprintf( _n( '%d payment updated.', '%d payments updated.', $done, 'order-balance' ), $done ) );
		wp_safe_redirect( AdminPage::url( 'payments' ) );
		exit;
	}
}
