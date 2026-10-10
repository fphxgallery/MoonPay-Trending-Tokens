/* MoonPay Top 20 Trending Tokens — Front-end JS */
( function ( $ ) {
	'use strict';

	var refreshTimer = null;
	var JUP_API      = 'https://lite-api.jup.ag/tokens/v2/search';

	/* ══════════════════════════════════════════════════════════════
	   HELPERS
	══════════════════════════════════════════════════════════════ */

	function fmtPrice( n ) {
		n = parseFloat( n );
		if ( ! n || isNaN( n ) ) return '—';
		if ( n >= 1000 )   return '$' + fmtCompact( n );
		if ( n >= 1 )      return '$' + n.toFixed( 2 );
		if ( n >= 0.001 )  return '$' + n.toFixed( 6 );
		// Sub-$0.001 (micro-cap / meme tokens): count leading zeros after the
		// decimal point, then show 4 significant digits — never use scientific notation.
		var s     = n.toFixed( 20 );
		var match = s.match( /^0\.(0*)/ );
		var zeros = match ? match[1].length : 0;
		var fixed = n.toFixed( zeros + 4 );
		fixed = fixed.replace( /(\.\d*[1-9])0+$/, '$1' );
		return '$' + fixed;
	}

	function fmtCompact( n ) {
		if ( ! n ) return '—';
		if ( n >= 1e9 )  return ( n / 1e9 ).toFixed( 2 ) + 'B';
		if ( n >= 1e6 )  return ( n / 1e6 ).toFixed( 2 ) + 'M';
		if ( n >= 1e3 )  return ( n / 1e3 ).toFixed( 1 ) + 'K';
		return n.toFixed( 2 );
	}

	function usd( n ) {
		return n ? '$' + fmtCompact( n ) : '—';
	}

	function esc( str ) {
		return $( '<span>' ).text( str == null ? '' : String( str ) ).html();
	}

	function shortAddr( addr ) {
		if ( ! addr || addr.length < 12 ) return addr || '—';
		return addr.slice( 0, 6 ) + '…' + addr.slice( -4 );
	}

	function line( label, value ) {
		return '<div class="mp20-line"><span>' + esc( label ) + '</span><span>' + esc( value ) + '</span></div>';
	}

	function chip( type, text ) {
		return '<span class="mp20-badge is-' + type + '">' + esc( text ) + '</span>';
	}

	/* ══════════════════════════════════════════════════════════════
	   DETAIL PANEL
	══════════════════════════════════════════════════════════════ */

	function buildDetail( t ) {
		var s24   = t.stats24h || {};
		var audit = t.audit || {};

		var market = '<div><h4>Market</h4>'
			+ line( 'Market cap', usd( t.mcap ) )
			+ line( 'FDV', usd( t.fdv ) )
			+ line( 'Liquidity', usd( t.liquidity ) )
			+ line( 'Holders', t.holderCount ? fmtCompact( t.holderCount ) : '—' )
			+ '</div>';

		var trading = '<div><h4>Trading, 24h</h4>'
			+ line( 'Buys / sells', ( s24.numBuys ? fmtCompact( s24.numBuys ) : '—' ) + ' / ' + ( s24.numSells ? fmtCompact( s24.numSells ) : '—' ) )
			+ line( 'Buy volume', usd( s24.buyVolume ) )
			+ line( 'Sell volume', usd( s24.sellVolume ) )
			+ line( 'Fees', usd( t.fees ) )
			+ '</div>';

		var chips = chip( audit.mintAuthorityDisabled ? 'ok' : 'bad', audit.mintAuthorityDisabled ? 'Mint disabled' : 'Mint active' )
			+ chip( audit.freezeAuthorityDisabled ? 'ok' : 'bad', audit.freezeAuthorityDisabled ? 'Freeze disabled' : 'Freeze active' );

		if ( audit.topHoldersPercentage !== undefined ) {
			var top = audit.topHoldersPercentage;
			chips += chip( top > 60 ? 'bad' : ( top > 30 ? 'warn' : 'ok' ), top.toFixed( 1 ) + '% top holders' );
		}
		if ( audit.botHoldersCount ) {
			chips += chip( audit.botHoldersPercentage > 0.01 ? 'warn' : 'ok', audit.botHoldersCount + ' bot holders' );
		}
		if ( t.organicScore ) {
			var label = ( t.organicScoreLabel || 'medium' ).toLowerCase();
			chips += chip( label === 'high' ? 'ok' : ( label === 'low' ? 'bad' : 'warn' ), 'Organic score ' + Math.round( t.organicScore ) );
		}

		var foot = '';
		if ( t.id )      foot += '<a href="https://solscan.io/token/' + esc( t.id ) + '" target="_blank" rel="noopener noreferrer">Solscan</a>';
		if ( t.website ) foot += '<a href="' + esc( t.website ) + '" target="_blank" rel="noopener noreferrer">Website</a>';
		if ( t.twitter ) foot += '<a href="' + esc( t.twitter ) + '" target="_blank" rel="noopener noreferrer">X</a>';
		if ( t.id )      foot += '<span><code title="' + esc( t.id ) + '">' + esc( shortAddr( t.id ) ) + '</code> <button type="button" class="mp20-copy" data-copy="' + esc( t.id ) + '">Copy</button></span>';

		return '<div class="mp20-detail-grid">' + market + trading
			+ '<div><h4>Audit</h4><div class="mp20-chips">' + chips + '</div></div></div>'
			+ ( foot ? '<div class="mp20-detail-foot">' + foot + '</div>' : '' );
	}

	function loadDetail( $item ) {
		var $detail = $item.find( '.mp20-detail' );
		if ( $item.data( 'mp20-loaded' ) ) return;

		var address = String( $item.data( 'address' ) || '' );
		var symbol  = String( $item.data( 'symbol' ) || '' );
		var name    = String( $item.data( 'name' ) || '' );

		$detail.html( '<p class="mp20-detail-msg">Loading ' + esc( name ) + '…</p>' );

		function search( q ) {
			return fetch( JUP_API + '?query=' + encodeURIComponent( q ) )
				.then( function ( r ) { return r.json(); } );
		}

		search( address || symbol )
			.then( function ( data ) {
				var token = null;
				if ( Array.isArray( data ) && data.length ) {
					token = data.find( function ( t ) {
						return t.id && t.id.toLowerCase() === address.toLowerCase();
					} ) || data[0];
				}
				if ( ! token && address ) {
					return search( symbol ).then( function ( d ) {
						return Array.isArray( d ) && d.length ? d[0] : null;
					} );
				}
				return token;
			} )
			.then( function ( token ) {
				if ( ! token ) {
					$detail.html( '<p class="mp20-detail-msg">No detailed data for ' + esc( name ) + '. It may not be indexed by Jupiter, which covers Solana only.</p>' );
					return;
				}
				$detail.html( buildDetail( token ) );
				$item.data( 'mp20-loaded', true );
			} )
			.catch( function () {
				$detail.html( '<p class="mp20-detail-msg">Couldn\'t load token data. Close this row and try again.</p>' );
			} );
	}

	function toggleItem( $item ) {
		var open = ! $item.hasClass( 'is-open' );
		$item.toggleClass( 'is-open', open );
		$item.find( '.mp20-row' ).attr( 'aria-expanded', open ? 'true' : 'false' );
		$item.find( '.mp20-detail' ).prop( 'hidden', ! open );
		if ( open ) loadDetail( $item );
	}

	/* ══════════════════════════════════════════════════════════════
	   SORT TABS / SHOW ALL
	══════════════════════════════════════════════════════════════ */

	function applyView( $wrap ) {
		var $list    = $wrap.find( '.mp20-list' );
		var sort     = $wrap.data( 'mp20-sort' ) || 'trending';
		var expanded = !! $wrap.data( 'mp20-all' );
		var initial  = parseInt( $wrap.data( 'mp20-initial' ), 10 ) || 10;
		var $items   = $list.children( '.mp20-item' ).get();

		$items.sort( function ( a, b ) {
			var ra = +a.getAttribute( 'data-rank' ), rb = +b.getAttribute( 'data-rank' );
			var ca = parseFloat( a.getAttribute( 'data-change' ) ) || 0;
			var cb = parseFloat( b.getAttribute( 'data-change' ) ) || 0;
			if ( sort === 'gainers' ) return cb - ca || ra - rb;
			if ( sort === 'losers' )  return ca - cb || ra - rb;
			return ra - rb;
		} );

		$.each( $items, function ( i, el ) {
			$list.append( el );
			$( el ).toggleClass( 'is-hidden', ! expanded && i >= initial );
		} );

		$wrap.find( '.mp20-tabs button' ).each( function () {
			$( this ).attr( 'aria-pressed', $( this ).data( 'sort' ) === sort ? 'true' : 'false' );
		} );

		var total = $items.length;
		$wrap.find( '.mp20-more' ).prop( 'hidden', expanded || total <= initial );
	}

	/* ══════════════════════════════════════════════════════════════
	   REFRESH
	══════════════════════════════════════════════════════════════ */

	function refreshWrap( $wrap, silent ) {
		if ( $wrap.data( 'mp20-refreshing' ) ) return;

		$wrap.data( 'mp20-refreshing', true );
		var sort = $wrap.data( 'mp20-sort' );
		var all  = $wrap.data( 'mp20-all' );
		var $btn = $wrap.find( '.mp20-refresh-btn' );

		if ( ! silent ) {
			$wrap.addClass( 'mp20-is-loading' );
			$btn.addClass( 'mp20-is-loading' );
		}

		$.ajax( {
			url:    mp20Data.ajaxUrl,
			method: 'POST',
			data: {
				action: 'mp20_refresh',
				nonce:  mp20Data.nonce,
				chain:  $wrap.data( 'mp20-chain' ) || 'solana',
				limit:  $wrap.data( 'mp20-limit' ) || 20,
			},
			success: function ( response ) {
				if ( response.success && response.data && response.data.html ) {
					var $parsed = $( $.parseHTML( $.trim( response.data.html ) ) ).filter( '.mp20-wrap' );
					$wrap.html( $parsed.html() );
					$wrap.data( 'mp20-sort', sort ).data( 'mp20-all', all );
					bindEvents( $wrap );
				}
			},
			complete: function () {
				$wrap.removeClass( 'mp20-is-loading' );
				$wrap.data( 'mp20-refreshing', false );
			},
		} );
	}

	/* ══════════════════════════════════════════════════════════════
	   EVENT BINDING
	══════════════════════════════════════════════════════════════ */

	function bindEvents( $wrap ) {
		$wrap.find( '.mp20-refresh-btn' )
			.off( 'click.mp20' )
			.on(  'click.mp20', function () { refreshWrap( $wrap, false ); } );

		$wrap.find( '.mp20-tabs button' )
			.off( 'click.mp20' )
			.on(  'click.mp20', function () {
				$wrap.data( 'mp20-sort', $( this ).data( 'sort' ) );
				applyView( $wrap );
			} );

		$wrap.find( '.mp20-more' )
			.off( 'click.mp20' )
			.on(  'click.mp20', function () {
				$wrap.data( 'mp20-all', true );
				applyView( $wrap );
			} );

		$wrap.find( '.mp20-row' )
			.off( 'click.mp20 keydown.mp20' )
			.on( 'click.mp20', function ( e ) {
				if ( $( e.target ).closest( '.mp20-buy a' ).length ) return;
				toggleItem( $( this ).closest( '.mp20-item' ) );
			} )
			.on( 'keydown.mp20', function ( e ) {
				if ( e.target !== this ) return;
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					toggleItem( $( this ).closest( '.mp20-item' ) );
				}
			} );

		$wrap.off( 'click.mp20copy' ).on( 'click.mp20copy', '.mp20-copy', function () {
			var addr = $( this ).data( 'copy' );
			var $btn = $( this );
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( addr );
			} else {
				var $tmp = $( '<textarea>' ).val( addr ).appendTo( 'body' ).select();
				document.execCommand( 'copy' );
				$tmp.remove();
			}
			$btn.text( 'Copied' );
			setTimeout( function () { $btn.text( 'Copy' ); }, 1500 );
		} );

		applyView( $wrap );
	}

	/* ══════════════════════════════════════════════════════════════
	   AUTO-REFRESH
	══════════════════════════════════════════════════════════════ */

	function startAutoRefresh() {
		var interval = parseInt( mp20Data.refreshRate, 10 );
		if ( isNaN( interval ) || interval <= 0 ) return;
		if ( refreshTimer ) clearInterval( refreshTimer );
		refreshTimer = setInterval( function () {
			$( '.mp20-wrap' ).each( function () {
				refreshWrap( $( this ), true );
			} );
		}, interval );
	}

	$( function () {
		$( '.mp20-wrap' ).each( function () {
			bindEvents( $( this ) );
		} );
		startAutoRefresh();
	} );

} )( jQuery );
