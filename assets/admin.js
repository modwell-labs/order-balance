( function ( $ ) {
	'use strict';

	$( document ).on( 'click', '.obal-action', function ( event ) {
		event.preventDefault();

		const $button = $( this );
		const confirmText = $button.data( 'confirm' );

		if ( confirmText && ! window.confirm( confirmText ) ) {
			return;
		}

		const data = {
			action: 'obal_' + $button.data( 'action' ),
			nonce: obalAdmin.nonce,
			order_id: $button.closest( '.obal-box' ).data( 'order' ),
		};

		$button.closest( '.obal-box' ).find( '[data-obal-field]' ).each( function () {
			data[ $( this ).data( 'obal-field' ) ] = $( this ).val();
		} );

		let $notify = $button.closest( '[data-obal-group]' ).find( '.obal-notify-input' );
		if ( ! $notify.length ) {
			$notify = $( '#obal-notify' );
		}
		if ( $notify.length ) {
			data.notify = $notify.is( ':checked' ) ? '1' : '0';
		}

		$button.prop( 'disabled', true );

		$.post( obalAdmin.ajaxUrl, data )
			.done( function ( response ) {
				if ( response && response.success ) {
					window.location.reload();
					return;
				}
				window.alert( ( response && response.data && response.data.message ) || obalAdmin.error );
				$button.prop( 'disabled', false );
			} )
			.fail( function ( xhr ) {
				const message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				window.alert( message || obalAdmin.error );
				$button.prop( 'disabled', false );
			} );
	} );

	// Balance box on the order screen: WooCommerce edits items over AJAX, so reload the box
	// after it recalculates or saves them.
	let refreshTimer = null;
	const itemActions = /(?:^|&)action=woocommerce_(?:calc_line_taxes|save_order_items|add_order_item|remove_order_item|add_order_fee|add_order_shipping|add_order_tax|remove_order_tax|add_coupon_discount|remove_order_coupon|load_order_items)(?:&|$)/;

	const refreshBalance = function () {
		const $box = $( '.obal-box' ).first();
		if ( ! $box.length || ! window.obalAdmin ) {
			return;
		}

		$.post( obalAdmin.ajaxUrl, {
			action: 'obal_refresh',
			nonce: obalAdmin.nonce,
			order_id: $box.data( 'order' ),
		} ).done( function ( response ) {
			if ( ! response || ! response.success || ! response.data || ! response.data.html ) {
				return;
			}
			const $next = $( '<div>' ).html( response.data.html ).find( '.obal-box' ).first();
			if ( ! $next.length ) {
				return;
			}
			const recordOpen = $box.find( 'details.obal-record' ).prop( 'open' );
			const notify = $box.find( '#obal-notify' ).prop( 'checked' );
			$box.replaceWith( $next );
			if ( recordOpen ) {
				$next.find( 'details.obal-record' ).prop( 'open', true );
			}
			if ( false === notify ) {
				$next.find( '#obal-notify' ).prop( 'checked', false );
			}
		} );
	};

	$( document ).on( 'ajaxComplete', function ( event, xhr, settings ) {
		if ( 200 !== xhr.status || 'string' !== typeof settings.data || ! itemActions.test( settings.data ) ) {
			return;
		}
		window.clearTimeout( refreshTimer );
		refreshTimer = window.setTimeout( refreshBalance, 300 );
	} );

	// Help tips (the "?" icons): WooCommerce starts them only on its own screens.
	$( function () {
		if ( $.fn.tipTip ) {
			$( '.woocommerce-help-tip' ).tipTip( {
				attribute: 'data-tip',
				fadeIn: 50,
				fadeOut: 50,
				delay: 200,
				keepAlive: true,
			} );
		}
	} );

	// Order change fee: hide the amount, tax and refund rows while "No fee" is selected.
	const toggleChangeFee = function () {
		const $type = $( '#obal_change_fee_type' );
		if ( ! $type.length ) {
			return;
		}
		const off = 'none' === $type.val();
		[ 'amount', 'label', 'taxable', 'tax_class', 'refund' ].forEach( function ( key ) {
			$( '#obal_change_fee_' + key ).closest( 'tr' ).toggle( ! off );
		} );
		$( '#obal_change_fee_tax_class' ).closest( 'tr' ).toggle( ! off && $( '#obal_change_fee_taxable' ).is( ':checked' ) );
	};
	$( document ).on( 'change', '#obal_change_fee_type, #obal_change_fee_taxable', toggleChangeFee );
	$( toggleChangeFee );

	// Order changes: the email delay only applies to automatic updates.
	const toggleWaiting = function () {
		const $mode = $( '#obal_waiting_mode' );
		if ( $mode.length ) {
			$( '#obal_waiting_email_delay' ).closest( 'tr' ).toggle( 'auto' === $mode.val() );
		}
	};
	$( document ).on( 'change', '#obal_waiting_mode', toggleWaiting );
	$( toggleWaiting );

	$( document ).on( 'click', '.obal-copy', function () {
		const $button = $( this );
		const text = String( $button.data( 'copy' ) );
		const label = $button.text();
		const done = function () {
			$button.text( ( window.obalAdmin && obalAdmin.copied ) || 'Copied' );
			setTimeout( function () {
				$button.text( label );
			}, 1500 );
		};

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}

		const $temp = $( '<textarea readonly>' ).val( text ).css( { position: 'fixed', opacity: 0 } ).appendTo( 'body' );
		$temp[ 0 ].select();
		document.execCommand( 'copy' );
		$temp.remove();
		done();
	} );
} )( jQuery );
