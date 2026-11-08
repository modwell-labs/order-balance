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

		const $notify = $( '#obal-notify' );
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
