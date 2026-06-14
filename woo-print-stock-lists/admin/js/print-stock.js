/* global wooPSL, jQuery */
( function ( $ ) {
	'use strict';

	// ── Category tree interaction ──────────────────────────────────────────

	/**
	 * When a checkbox is checked/unchecked:
	 * – If checked: show its immediate child <ul> (if any).
	 * – If unchecked: hide and uncheck ALL descendants.
	 */
	$( '#woo-psl-tree' ).on( 'change', 'input[type="checkbox"]', function () {
		var $cb   = $( this );
		var $item = $cb.closest( '.woo-psl-cat-item' );
		var $sub  = $item.children( 'ul.woo-psl-subtree' ).first();

		if ( $cb.is( ':checked' ) ) {
			if ( $sub.length ) {
				$sub.slideDown( 160 );
			}
		} else {
			// Hide and uncheck all descendants.
			$sub.slideUp( 160, function () {
				$sub.find( 'input[type="checkbox"]' ).prop( 'checked', false );
				$sub.find( 'ul.woo-psl-subtree' ).hide();
			} );
		}
	} );

	// ── Form submission ────────────────────────────────────────────────────

	$( '#woo-psl-form' ).on( 'submit', function ( e ) {
		e.preventDefault();

		var $btn      = $( '#woo-psl-generate-btn' );
		var $messages = $( '#woo-psl-messages' );
		var categoryIds = [];

		$( '#woo-psl-tree input[type="checkbox"]:checked' ).each( function () {
			categoryIds.push( $( this ).val() );
		} );

		if ( categoryIds.length === 0 ) {
			$messages.html( '<p class="woo-psl-msg-error">' + wooPSL.msgError + ' (brak wybranych kategorii)</p>' );
			return;
		}

		$btn.prop( 'disabled', true ).text( wooPSL.msgLoading );
		$messages.html( '' );

		var data = {
			action:       'woo_psl_generate',
			_wpnonce:     wooPSL.nonce,
			category_ids: categoryIds,
		};

		$.post( wooPSL.ajaxUrl, data )
			.done( function ( response ) {
				if ( response.success ) {
					showSuccessMessage( $messages );
					prependHistoryRow( response.data.row_html );
				} else {
					var msg = ( response.data && response.data.message ) ? response.data.message : wooPSL.msgError;
					$messages.html( '<p class="woo-psl-msg-error">' + escapeHtml( msg ) + '</p>' );
				}
			} )
			.fail( function () {
				$messages.html( '<p class="woo-psl-msg-error">' + wooPSL.msgError + '</p>' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( 'Generuj listę' );
			} );
	} );

	function showSuccessMessage( $messages ) {
		$messages.html( '<p class="woo-psl-msg-success">✔ Lista została wygenerowana i zapisana.</p>' );
		setTimeout( function () {
			$messages.html( '' );
		}, 5000 );
	}

	function prependHistoryRow( rowHtml ) {
		var $table = $( '#woo-psl-history-table' );
		var $empty = $( '#woo-psl-empty-history' );
		var $body  = $( '#woo-psl-history-body' );

		$empty.hide();
		$table.show();
		$body.prepend( rowHtml );
	}

	// ── Delete ─────────────────────────────────────────────────────────────

	$( '#woo-psl-history-body' ).on( 'click', '.woo-psl-delete', function () {
		var $btn   = $( this );
		var id     = parseInt( $btn.data( 'id' ), 10 );
		var nonce  = $btn.data( 'nonce' );
		var $row   = $btn.closest( 'tr' );

		if ( ! window.confirm( wooPSL.msgConfirmDel ) ) {
			return;
		}

		$btn.prop( 'disabled', true ).text( wooPSL.msgDeleting );

		$.post( wooPSL.ajaxUrl, {
			action:   'woo_psl_delete',
			id:       id,
			_wpnonce: nonce,
		} )
			.done( function ( response ) {
				if ( response.success ) {
					$row.fadeOut( 300, function () {
						$row.remove();
						checkEmpty();
					} );
				} else {
					$btn.prop( 'disabled', false ).text( 'Usuń' );
					alert( ( response.data && response.data.message ) ? response.data.message : wooPSL.msgError );
				}
			} )
			.fail( function () {
				$btn.prop( 'disabled', false ).text( 'Usuń' );
				alert( wooPSL.msgError );
			} );
	} );

	function checkEmpty() {
		if ( $( '#woo-psl-history-body tr' ).length === 0 ) {
			$( '#woo-psl-history-table' ).hide();
			$( '#woo-psl-empty-history' ).show();
		}
	}

	// ── Helpers ────────────────────────────────────────────────────────────

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

}( jQuery ) );
