/* MoonPay Top 20 Trending Tokens — Front-end JS */
( function ( $ ) {
	'use strict';

	var refreshTimer = null;
	var DARK_KEY     = 'mp20_dark_mode';
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
		var s     = n.toFixed( 20 );            // e.g. "0.00095600000000000000"
		var match = s.match( /^0\.(0*)/ );      // capture leading zeros
		var zeros = match ? match[1].length : 0;
		var fixed = n.toFixed( zeros + 4 );     // e.g. zeros=3 → toFixed(7) = "0.0009560"
		// Strip trailing zeros (e.g. "0.0009560" → "0.000956")
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

	function fmtPct( n ) {
		if ( n === null || n === undefined ) return '—';
		var s = ( n >= 0 ? '+' : '' ) + n.toFixed( 2 ) + '%';
		return s;
	}

	function pctClass( n ) {
		if ( n === null || n === undefined ) return '';
		return n >= 0 ? 'mp20-modal-stat-val--up' : 'mp20-modal-stat-val--down';
	}

	function esc( str ) {
		return $( '<span>' ).text( str || '' ).html();
	}

	function shortAddr( addr ) {
		if ( ! addr || addr.length < 12 ) return addr || '—';
		return addr.slice( 0, 6 ) + '…' + addr.slice( -4 );
	}

	/* ══════════════════════════════════════════════════════════════
	   MODAL — BUILD HTML
	══════════════════════════════════════════════════════════════ */

	function buildModalContent( t ) {
		var scoreLabel = ( t.organicScoreLabel || 'medium' ).toLowerCase();
		var scoreCls   = 'mp20-modal-score--' + scoreLabel;
		var scoreVal   = t.organicScore ? Math.round( t.organicScore ) : '—';

		// ── Header ──────────────────────────────────────────────
		var logo = t.icon
			? '<img class="mp20-modal-logo" src="' + esc( t.icon ) + '" alt="' + esc( t.symbol ) + '" width="52" height="52">'
			: '<span class="mp20-modal-logo--placeholder">' + esc( ( t.symbol || '?' ).charAt( 0 ) ) + '</span>';

		// Badges
		var badges = '';
		if ( t.isVerified ) {
			badges += '<span class="mp20-modal-badge mp20-modal-badge--verified">✓ Verified</span>';
		}
		if ( t.launchpad ) {
			badges += '<span class="mp20-modal-badge mp20-modal-badge--launchpad">🚀 ' + esc( t.launchpad ) + '</span>';
		}
		var ignoreTags = [ 'verified', 'community' ];
		( t.tags || [] ).slice( 0, 4 ).forEach( function ( tag ) {
			if ( ignoreTags.indexOf( tag ) === -1 ) {
				badges += '<span class="mp20-modal-badge mp20-modal-badge--tag">' + esc( tag ) + '</span>';
			}
		} );

		var header = '<div class="mp20-modal-header">'
			+ logo
			+ '<div class="mp20-modal-title-group">'
			+ '<p class="mp20-modal-name">' + esc( t.name ) + '</p>'
			+ '<span class="mp20-modal-symbol">' + esc( t.symbol ) + '</span>'
			+ ( badges ? '<div class="mp20-modal-badges">' + badges + '</div>' : '' )
			+ '</div>'
			+ '<div class="mp20-modal-score ' + scoreCls + '">'
			+ '<span class="mp20-modal-score-val">' + scoreVal + '</span>'
			+ '<span class="mp20-modal-score-lbl">Organic</span>'
			+ '</div>'
			+ '</div>';

		// ── Key stats grid ───────────────────────────────────────
		var s24 = t.stats24h || {};
		var grid = '<div class="mp20-modal-grid">'
			+ statBox( 'Price',      fmtPrice( t.usdPrice ) )
			+ statBox( 'Mkt Cap',    t.mcap   ? '$' + fmtCompact( t.mcap )   : '—' )
			+ statBox( 'FDV',        t.fdv    ? '$' + fmtCompact( t.fdv )    : '—' )
			+ statBox( 'Liquidity',  t.liquidity ? '$' + fmtCompact( t.liquidity ) : '—' )
			+ statBox( 'Holders',    t.holderCount ? fmtCompact( t.holderCount ) : '—' )
			+ statBox( 'Fees 24h',   t.fees ? '$' + fmtCompact( t.fees ) : '—' )
			+ '</div>';

		// ── Trading stats table ──────────────────────────────────
		var periods = [
			[ '5m',  t.stats5m  ],
			[ '1h',  t.stats1h  ],
			[ '6h',  t.stats6h  ],
			[ '24h', t.stats24h ],
		];

		var statsRows = '';
		periods.forEach( function ( p ) {
			var label = p[0], s = p[1] || {};
			var buyVol  = s.buyVolume  ? '$' + fmtCompact( s.buyVolume )  : '—';
			var sellVol = s.sellVolume ? '$' + fmtCompact( s.sellVolume ) : '—';
			var pChange = fmtPct( s.priceChange );
			var pCls    = pctClass( s.priceChange );
			statsRows += '<tr>'
				+ '<td>' + label + '</td>'
				+ '<td class="' + pCls + '">' + pChange + '</td>'
				+ '<td>' + ( s.numBuys  || '—' ) + '</td>'
				+ '<td>' + ( s.numSells || '—' ) + '</td>'
				+ '<td>' + buyVol  + '</td>'
				+ '<td>' + sellVol + '</td>'
				+ '</tr>';
		} );

		var statsTable = '<p class="mp20-modal-section-lbl">Trading Activity</p>'
			+ '<table class="mp20-modal-stats-table">'
			+ '<thead><tr>'
			+ '<th>Period</th><th>Δ Price</th><th>Buys</th><th>Sells</th><th>Buy Vol</th><th>Sell Vol</th>'
			+ '</tr></thead>'
			+ '<tbody>' + statsRows + '</tbody>'
			+ '</table>';

		// ── Supply ───────────────────────────────────────────────
		var supply = '<p class="mp20-modal-section-lbl">Supply</p>'
			+ '<div class="mp20-modal-grid">'
			+ statBox( 'Circ Supply', t.circSupply  ? fmtCompact( t.circSupply )  : '—' )
			+ statBox( 'Total Supply', t.totalSupply ? fmtCompact( t.totalSupply ) : '—' )
			+ statBox( 'Decimals', t.decimals !== undefined ? t.decimals : '—' )
			+ '</div>';

		// ── Audit ────────────────────────────────────────────────
		var audit    = t.audit || {};
		var auditHtml = '<p class="mp20-modal-section-lbl">Audit</p><div class="mp20-modal-audit">';

		auditHtml += chip(
			audit.mintAuthorityDisabled ? 'good' : 'bad',
			audit.mintAuthorityDisabled ? '✓ Mint Disabled' : '⚠ Mint Active'
		);
		auditHtml += chip(
			audit.freezeAuthorityDisabled ? 'good' : 'bad',
			audit.freezeAuthorityDisabled ? '✓ Freeze Disabled' : '⚠ Freeze Active'
		);

		if ( audit.topHoldersPercentage !== undefined ) {
			var topPct = audit.topHoldersPercentage.toFixed( 1 ) + '% top holders';
			var topCls = audit.topHoldersPercentage > 60 ? 'bad' : ( audit.topHoldersPercentage > 30 ? 'warn' : 'good' );
			auditHtml += chip( topCls, topPct );
		}

		if ( audit.botHoldersCount ) {
			var botPct  = audit.botHoldersPercentage ? ( audit.botHoldersPercentage * 100 ).toFixed( 2 ) + '% bots' : '';
			var botText = audit.botHoldersCount + ' bot holders' + ( botPct ? ' (' + botPct + ')' : '' );
			var botCls  = audit.botHoldersPercentage > 0.01 ? 'warn' : 'good';
			auditHtml += chip( botCls, botText );
		}

		auditHtml += '</div>';

		// ── Links ────────────────────────────────────────────────
		var linksHtml = '';
		var links = [];
		if ( t.website ) links.push( [ '🌐 Website', t.website ] );
		if ( t.twitter ) links.push( [ '𝕏 Twitter', t.twitter ] );
		if ( t.id )      links.push( [ '🔍 Solscan', 'https://solscan.io/token/' + t.id ] );

		if ( links.length ) {
			linksHtml = '<p class="mp20-modal-section-lbl">Links</p><div class="mp20-modal-links">';
			links.forEach( function ( l ) {
				linksHtml += '<a href="' + esc( l[1] ) + '" class="mp20-modal-link" target="_blank" rel="noopener noreferrer">'
					+ esc( l[0] ) + '</a>';
			} );
			linksHtml += '</div>';
		}

		// ── Address ──────────────────────────────────────────────
		var addrHtml = '';
		if ( t.id ) {
			addrHtml = '<p class="mp20-modal-section-lbl">Token Address</p>'
				+ '<div class="mp20-modal-address-row">'
				+ '<span class="mp20-modal-address" title="' + esc( t.id ) + '">' + esc( t.id ) + '</span>'
				+ '<button type="button" class="mp20-modal-copy-btn" data-copy="' + esc( t.id ) + '" title="Copy address">⧉</button>'
				+ '</div>';
		}

		return header
			+ '<div class="mp20-modal-body">'
			+ grid
			+ statsTable
			+ supply
			+ auditHtml
			+ linksHtml
			+ addrHtml
			+ '</div>';
	}

	function statBox( label, value ) {
		return '<div class="mp20-modal-stat">'
			+ '<span class="mp20-modal-stat-lbl">' + esc( label ) + '</span>'
			+ '<span class="mp20-modal-stat-val">'  + value + '</span>'
			+ '</div>';
	}

	function chip( type, text ) {
		return '<span class="mp20-modal-audit-chip mp20-modal-audit-chip--' + type + '">' + esc( text ) + '</span>';
	}

	/* ══════════════════════════════════════════════════════════════
	   MODAL — OPEN / CLOSE / FETCH
	══════════════════════════════════════════════════════════════ */

	var $overlay = null;

	function ensureOverlay() {
		if ( $overlay && $overlay.length ) return;
		$overlay = $( '<div id="mp20-modal-overlay" class="mp20-modal-overlay" role="dialog" aria-modal="true">'
			+ '<div class="mp20-modal">'
			+ '<button type="button" class="mp20-modal-close" aria-label="Close">&#10005;</button>'
			+ '<div class="mp20-modal-inner"></div>'
			+ '</div></div>' );
		$( 'body' ).append( $overlay );

		// Close on overlay backdrop click
		$overlay.on( 'click', function ( e ) {
			if ( $( e.target ).is( $overlay ) ) closeModal();
		} );

		// Close on X button
		$overlay.find( '.mp20-modal-close' ).on( 'click', closeModal );

		// Copy-to-clipboard (delegated)
		$overlay.on( 'click', '.mp20-modal-copy-btn', function () {
			var addr = $( this ).data( 'copy' );
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( addr );
			} else {
				var $tmp = $( '<textarea>' ).val( addr ).appendTo( 'body' ).select();
				document.execCommand( 'copy' );
				$tmp.remove();
			}
			$( this ).text( '✓' );
			var $self = $( this );
			setTimeout( function () { $self.text( '⧉' ); }, 1500 );
		} );
	}

	function openModal( html, isDarkMode ) {
		ensureOverlay();
		$overlay.find( '.mp20-modal-inner' ).html( html );
		$overlay.find( '.mp20-modal' )
			.toggleClass( 'mp20-dark',  isDarkMode )
			.toggleClass( 'mp20-light', ! isDarkMode );
		// Trigger open (rAF ensures transition fires)
		requestAnimationFrame( function () {
			$overlay.addClass( 'mp20-modal-open' );
		} );
		$( 'body' ).css( 'overflow', 'hidden' );
	}

	function closeModal() {
		if ( $overlay ) {
			$overlay.removeClass( 'mp20-modal-open' );
			$( 'body' ).css( 'overflow', '' );
		}
	}

	// Close on Escape key
	$( document ).on( 'keydown.mp20', function ( e ) {
		if ( e.key === 'Escape' ) closeModal();
	} );

	function fetchTokenDetail( address, symbol, name, isDarkMode ) {
		// Show spinner
		openModal(
			'<div class="mp20-modal-loading">'
			+ '<div class="mp20-modal-spinner"></div>'
			+ '<p>Loading ' + esc( name ) + '…</p>'
			+ '</div>',
			isDarkMode
		);

		// Search by address first (exact match); fall back to symbol
		var url = JUP_API + '?query=' + encodeURIComponent( address || symbol );

		fetch( url )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				var token = null;
				if ( Array.isArray( data ) && data.length ) {
					// Prefer exact address match
					token = data.find( function ( t ) {
						return t.id && t.id.toLowerCase() === ( address || '' ).toLowerCase();
					} ) || data[0];
				}

				if ( ! token ) {
					// Fallback: search by symbol
					return fetch( JUP_API + '?query=' + encodeURIComponent( symbol ) )
						.then( function ( r ) { return r.json(); } )
						.then( function ( d ) {
							return Array.isArray( d ) && d.length ? d[0] : null;
						} );
				}
				return token;
			} )
			.then( function ( token ) {
				if ( ! token ) {
					openModal(
						'<div class="mp20-modal-error">'
						+ '<p>No detailed data found for <strong>' + esc( name ) + '</strong>.</p>'
						+ '<p style="font-size:0.8rem;margin-top:8px;">This token may not be indexed by Jupiter (non-Solana chains are not supported).</p>'
						+ '</div>',
						isDarkMode
					);
					return;
				}
				openModal( buildModalContent( token ), isDarkMode );
			} )
			.catch( function () {
				openModal(
					'<div class="mp20-modal-error"><p>Failed to load token data. Please try again.</p></div>',
					isDarkMode
				);
			} );
	}

	/* ══════════════════════════════════════════════════════════════
	   DARK MODE
	══════════════════════════════════════════════════════════════ */

	function isDark( $wrap ) { return $wrap.hasClass( 'mp20-dark' ); }

	function applyTheme( $wrap, dark ) {
		$wrap.toggleClass( 'mp20-dark',  dark );
		$wrap.toggleClass( 'mp20-light', ! dark );
		$wrap.find( '.mp20-theme-icon' ).text( dark ? '\u2600' : '\u263D' );
	}

	function initTheme( $wrap ) {
		var saved = null;
		try { saved = localStorage.getItem( DARK_KEY ); } catch ( e ) {}
		if ( saved === 'true' ) {
			applyTheme( $wrap, true );
		} else if ( saved === 'false' ) {
			applyTheme( $wrap, false );
		} else {
			var prefersDark = window.matchMedia &&
				window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
			$wrap.find( '.mp20-theme-icon' ).text( prefersDark ? '\u2600' : '\u263D' );
		}
	}

	function toggleTheme( $wrap ) {
		var dark;
		if ( ! $wrap.hasClass( 'mp20-dark' ) && ! $wrap.hasClass( 'mp20-light' ) ) {
			var prefersDark = window.matchMedia &&
				window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
			dark = ! prefersDark;
		} else {
			dark = ! isDark( $wrap );
		}
		applyTheme( $wrap, dark );
		try { localStorage.setItem( DARK_KEY, dark ? 'true' : 'false' ); } catch ( e ) {}
	}

	/* ══════════════════════════════════════════════════════════════
	   REFRESH
	══════════════════════════════════════════════════════════════ */

	function refreshWrap( $wrap, silent ) {
		if ( $wrap.data( 'mp20-refreshing' ) ) return;

		$wrap.data( 'mp20-refreshing', true );
		var wasDark = isDark( $wrap );
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
					var $parsed = $( response.data.html );
					$wrap.html( $parsed.html() );
					initTheme( $wrap );
					if ( wasDark ) applyTheme( $wrap, true );
					bindEvents( $wrap );
				}
			},
			error: function () {},
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
		$wrap.find( '.mp20-theme-btn' )
			.off( 'click.mp20' )
			.on(  'click.mp20', function () { toggleTheme( $wrap ); } );

		$wrap.find( '.mp20-refresh-btn' )
			.off( 'click.mp20' )
			.on(  'click.mp20', function () { refreshWrap( $wrap, false ); } );

		// Row click → open detail modal
		$wrap.find( '.mp20-row--clickable' )
			.off( 'click.mp20 keydown.mp20' )
			.on( 'click.mp20', function ( e ) {
				// Don't open modal when clicking the Buy button link inside the row
				if ( $( e.target ).closest( '.mp20-buy-btn' ).length ) return;
				var $row = $( this );
				fetchTokenDetail(
					$row.data( 'address' ),
					$row.data( 'symbol' ),
					$row.data( 'name' ),
					isDark( $wrap )
				);
			} )
			.on( 'keydown.mp20', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					$( this ).trigger( 'click.mp20' );
				}
			} );
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

	/* ══════════════════════════════════════════════════════════════
	   BOOT
	══════════════════════════════════════════════════════════════ */

	$( function () {
		$( '.mp20-wrap' ).each( function () {
			initTheme( $( this ) );
			bindEvents( $( this ) );
		} );
		startAutoRefresh();
	} );

} )( jQuery );
