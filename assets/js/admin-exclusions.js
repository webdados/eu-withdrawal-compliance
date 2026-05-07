/**
 * EU Withdrawal Compliance — instant-search picker for excluded categories.
 *
 * Renders an autocomplete dropdown over a hidden listbox and a chips list
 * underneath. Each add/remove triggers an AJAX call that updates the
 * "ayudawp_euw_excluded_categories" option immediately.
 */
( function ( $ ) {
	'use strict';

	$( function () {

		var $picker = $( '.ayudawp-euw-cat-picker' ).first();
		if ( ! $picker.length ) {
			return;
		}

		var nonce        = $picker.data( 'nonce' );
		var searchAction = $picker.data( 'search-action' );
		var toggleAction = $picker.data( 'toggle-action' );
		var i18n         = ( window.ayudawpEuwExclusions && window.ayudawpEuwExclusions.i18n ) || {};

		var $input   = $picker.find( '.ayudawp-euw-cat-picker__input' );
		var $results = $picker.find( '.ayudawp-euw-cat-picker__results' );
		var $chips   = $picker.find( '.ayudawp-euw-cat-picker__chips' );

		var debounceTimer = null;
		var lastQuery     = null;
		var activeIndex   = -1;

		function escapeHtml( str ) {
			return String( str ).replace( /[&<>"']/g, function ( c ) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
			} );
		}

		function showResults() {
			$results.removeAttr( 'hidden' );
			$input.attr( 'aria-expanded', 'true' );
		}

		function hideResults() {
			$results.empty().attr( 'hidden', 'hidden' );
			$input.attr( 'aria-expanded', 'false' );
			activeIndex = -1;
		}

		function renderEmpty() {
			$results.empty().append(
				'<li class="ayudawp-euw-cat-picker__empty" role="presentation">' +
					escapeHtml( i18n.noResults || 'No matches' ) +
					'</li>'
			);
			showResults();
		}

		function renderResults( items ) {
			if ( ! items || ! items.length ) {
				renderEmpty();
				return;
			}

			$results.empty();
			activeIndex = -1;

			items.forEach( function ( it ) {
				var bcHtml    = it.breadcrumb ? ' <span class="ayudawp-euw-cat-picker__bc">(' + escapeHtml( it.breadcrumb ) + ')</span>' : '';
				var countHtml = ' <span class="ayudawp-euw-cat-picker__count">· ' + ( parseInt( it.count, 10 ) || 0 ) + '</span>';

				$results.append(
					'<li class="ayudawp-euw-cat-picker__option" role="option" tabindex="-1" ' +
						'data-term-id="' + ( parseInt( it.id, 10 ) || 0 ) + '" ' +
						'data-name="' + escapeHtml( it.name ) + '" ' +
						'data-breadcrumb="' + escapeHtml( it.breadcrumb || '' ) + '">' +
						'<strong>' + escapeHtml( it.name ) + '</strong>' + bcHtml + countHtml +
					'</li>'
				);
			} );

			showResults();
		}

		function setActive( idx ) {
			var $opts = $results.find( '.ayudawp-euw-cat-picker__option' );
			if ( ! $opts.length ) {
				return;
			}
			if ( idx < 0 ) {
				idx = $opts.length - 1;
			}
			if ( idx >= $opts.length ) {
				idx = 0;
			}
			$opts.removeClass( 'is-active' ).attr( 'aria-selected', 'false' );
			$opts.eq( idx ).addClass( 'is-active' ).attr( 'aria-selected', 'true' );
			activeIndex = idx;
		}

		function pickActive() {
			var $opt = $results.find( '.ayudawp-euw-cat-picker__option.is-active' );
			if ( ! $opt.length ) {
				$opt = $results.find( '.ayudawp-euw-cat-picker__option' ).first();
			}
			if ( $opt.length ) {
				addCategory( parseInt( $opt.data( 'term-id' ), 10 ) );
			}
		}

		function search( q ) {
			if ( q === lastQuery ) {
				return;
			}
			lastQuery = q;

			$.ajax( {
				url: window.ajaxurl,
				method: 'GET',
				dataType: 'json',
				data: {
					action: searchAction,
					nonce: nonce,
					q: q
				}
			} ).done( function ( resp ) {
				if ( resp && resp.success && resp.data && Array.isArray( resp.data.results ) ) {
					renderResults( resp.data.results );
				} else {
					renderEmpty();
				}
			} ).fail( function () {
				renderEmpty();
			} );
		}

		function addCategory( termId ) {
			if ( ! termId ) {
				return;
			}
			$.ajax( {
				url: window.ajaxurl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: toggleAction,
					nonce: nonce,
					op: 'add',
					term_id: termId
				}
			} ).done( function ( resp ) {
				if ( resp && resp.success && resp.data && resp.data.category ) {
					addChip( resp.data.category );
					$input.val( '' ).focus();
					lastQuery = null;
					hideResults();
				}
			} );
		}

		function removeCategory( $chip, termId ) {
			$chip.addClass( 'is-removing' );

			$.ajax( {
				url: window.ajaxurl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: toggleAction,
					nonce: nonce,
					op: 'remove',
					term_id: termId
				}
			} ).done( function ( resp ) {
				if ( resp && resp.success ) {
					$chip.remove();
				} else {
					$chip.removeClass( 'is-removing' );
				}
			} ).fail( function () {
				$chip.removeClass( 'is-removing' );
			} );
		}

		function addChip( cat ) {
			if ( $chips.find( '[data-term-id="' + parseInt( cat.id, 10 ) + '"]' ).length ) {
				return;
			}

			var bcHtml      = cat.breadcrumb ? ' <span class="ayudawp-euw-chip__breadcrumb">(' + escapeHtml( cat.breadcrumb ) + ')</span>' : '';
			var labelTpl    = i18n.removeLabel || 'Remove %s from exclusions';
			var removeLabel = labelTpl.replace( '%s', cat.name );

			var html =
				'<li class="ayudawp-euw-chip" data-term-id="' + parseInt( cat.id, 10 ) + '">' +
					'<span class="ayudawp-euw-chip__label">' +
						'<strong>' + escapeHtml( cat.name ) + '</strong>' + bcHtml +
					'</span>' +
					'<button type="button" class="ayudawp-euw-chip__remove" aria-label="' + escapeHtml( removeLabel ) + '">×</button>' +
				'</li>';

			$chips.append( html );
		}

		// --- Bindings ---

		$input.on( 'input', function () {
			var q = String( $input.val() || '' ).trim();
			clearTimeout( debounceTimer );
			debounceTimer = setTimeout( function () {
				search( q );
			}, 200 );
		} );

		$input.on( 'focus', function () {
			var q = String( $input.val() || '' ).trim();
			lastQuery = null;
			search( q );
		} );

		$input.on( 'keydown', function ( e ) {
			if ( $results.is( '[hidden]' ) ) {
				return;
			}

			switch ( e.key ) {
				case 'ArrowDown':
					e.preventDefault();
					setActive( activeIndex + 1 );
					break;
				case 'ArrowUp':
					e.preventDefault();
					setActive( activeIndex - 1 );
					break;
				case 'Enter':
					e.preventDefault();
					pickActive();
					break;
				case 'Escape':
					hideResults();
					break;
			}
		} );

		$( document ).on( 'mousedown', function ( e ) {
			if ( ! $.contains( $picker[ 0 ], e.target ) && e.target !== $picker[ 0 ] ) {
				hideResults();
			}
		} );

		$results.on( 'mousedown', '.ayudawp-euw-cat-picker__option', function ( e ) {
			e.preventDefault();
			var termId = parseInt( $( this ).data( 'term-id' ), 10 );
			addCategory( termId );
		} );

		$chips.on( 'click', '.ayudawp-euw-chip__remove', function () {
			var $chip  = $( this ).closest( '.ayudawp-euw-chip' );
			var termId = parseInt( $chip.data( 'term-id' ), 10 );
			if ( ! termId ) {
				return;
			}
			removeCategory( $chip, termId );
		} );
	} );

}( jQuery ) );