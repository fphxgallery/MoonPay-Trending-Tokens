<?php
/**
 * Admin settings page for MoonPay Top 20 Trending Tokens.
 *
 * Settings > MoonPay Top 20
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MP20_Settings {

	private const PAGE_SLUG = 'moonpay-top20';

	public function __construct() {
		add_action( 'admin_menu',    [ $this, 'add_page' ] );
		add_action( 'admin_init',    [ $this, 'register_settings' ] );
		add_action( 'admin_notices', [ $this, 'maybe_show_key_notice' ] );
	}

	public function add_page(): void {
		add_options_page(
			__( 'MoonPay Top 20 Trending', MP20_TD ),
			__( 'MoonPay Top 20', MP20_TD ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		$settings = [
			'mp20_publishable_key'  => [ 'sanitize_text_field', '' ],
			'mp20_access_token'     => [ 'sanitize_text_field', '' ],
			'mp20_chain'            => [ [ $this, 'sanitize_chain' ], 'solana' ],
			'mp20_limit'            => [ [ $this, 'sanitize_limit' ], 20 ],
			'mp20_title'            => [ 'sanitize_text_field', 'Top 20 Trending Tokens' ],
			'mp20_refresh_interval' => [ [ $this, 'sanitize_interval' ], 300 ],
		];

		foreach ( $settings as $key => [ $cb, $default ] ) {
			register_setting( 'mp20_settings_group', $key, [
				'sanitize_callback' => $cb,
				'default'           => $default,
			] );
		}

		// ── Section: API credentials ───────────────────────────────────────────
		add_settings_section(
			'mp20_section_api',
			__( 'API Credentials', MP20_TD ),
			function (): void {
				echo '<p>' . esc_html__( 'Connect to MoonPay\'s Agents API to power trending data and buy links.', MP20_TD ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field(
			'mp20_publishable_key',
			__( 'MoonPay Publishable Key', MP20_TD ),
			[ $this, 'field_publishable_key' ],
			self::PAGE_SLUG,
			'mp20_section_api'
		);

		add_settings_field(
			'mp20_access_token',
			__( 'MoonPay Access Token', MP20_TD ),
			[ $this, 'field_access_token' ],
			self::PAGE_SLUG,
			'mp20_section_api'
		);

		// ── Section: Display options ───────────────────────────────────────────
		add_settings_section(
			'mp20_section_display',
			__( 'Display Options', MP20_TD ),
			null,
			self::PAGE_SLUG
		);

		add_settings_field(
			'mp20_chain',
			__( 'Chain', MP20_TD ),
			[ $this, 'field_chain' ],
			self::PAGE_SLUG,
			'mp20_section_display'
		);

		add_settings_field(
			'mp20_limit',
			__( 'Number of Tokens', MP20_TD ),
			[ $this, 'field_limit' ],
			self::PAGE_SLUG,
			'mp20_section_display'
		);

		add_settings_field(
			'mp20_title',
			__( 'Widget Title', MP20_TD ),
			[ $this, 'field_title' ],
			self::PAGE_SLUG,
			'mp20_section_display'
		);

		add_settings_field(
			'mp20_refresh_interval',
			__( 'Cache / Refresh Interval', MP20_TD ),
			[ $this, 'field_interval' ],
			self::PAGE_SLUG,
			'mp20_section_display'
		);
	}

	// ── Field renderers ────────────────────────────────────────────────────────

	public function field_publishable_key(): void {
		$val = get_option( 'mp20_publishable_key', '' );
		printf(
			'<input type="text" id="mp20_publishable_key" name="mp20_publishable_key" value="%s" class="regular-text" placeholder="pk_live_..." />
			<p class="description">%s</p>',
			esc_attr( $val ),
			esc_html__( 'Found in your MoonPay dashboard. Enables the "Buy on MoonPay" button.', MP20_TD )
		);
	}

	public function field_access_token(): void {
		$val = get_option( 'mp20_access_token', '' );
		printf(
			'<input type="password" id="mp20_access_token" name="mp20_access_token" value="%s" class="regular-text" placeholder="ey..." />
			<p class="description">%s <code>mp login</code> / <code>mp verify</code>. %s</p>',
			esc_attr( $val ),
			esc_html__( 'Optional. Obtain via the MoonPay CLI:', MP20_TD ),
			esc_html__( 'Without this, anonymous rate limits apply (5 req/min).', MP20_TD )
		);
	}

	public function field_chain(): void {
		$current = get_option( 'mp20_chain', 'solana' );
		$chains  = [
			'solana'   => 'Solana',
			'ethereum' => 'Ethereum',
			'base'     => 'Base',
			'polygon'  => 'Polygon',
			'arbitrum' => 'Arbitrum',
			'optimism' => 'Optimism',
			'bnb'      => 'BNB',
			'avalanche'=> 'Avalanche',
		];

		echo '<select id="mp20_chain" name="mp20_chain">';
		foreach ( $chains as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Chain to fetch trending tokens for.', MP20_TD ) . '</p>';
	}

	public function field_limit(): void {
		$val = absint( get_option( 'mp20_limit', 20 ) );
		printf(
			'<input type="number" id="mp20_limit" name="mp20_limit" value="%d" min="1" max="50" class="small-text" />
			<p class="description">%s</p>',
			$val,
			esc_html__( 'Number of trending tokens to display (1–50). Default: 20.', MP20_TD )
		);
	}

	public function field_title(): void {
		$val = get_option( 'mp20_title', 'Top 20 Trending Tokens' );
		printf(
			'<input type="text" id="mp20_title" name="mp20_title" value="%s" class="regular-text" />',
			esc_attr( $val )
		);
	}

	public function field_interval(): void {
		$val = absint( get_option( 'mp20_refresh_interval', 300 ) );
		printf(
			'<input type="number" id="mp20_refresh_interval" name="mp20_refresh_interval" value="%d" min="60" max="86400" class="small-text" /> %s
			<p class="description">%s</p>',
			$val,
			esc_html__( 'seconds', MP20_TD ),
			esc_html__( 'How long to cache results. Minimum 60 s. Default: 300 (5 min).', MP20_TD )
		);
	}

	// ── Sanitisers ─────────────────────────────────────────────────────────────

	public function sanitize_chain( $val ): string {
		$allowed = [ 'solana', 'ethereum', 'base', 'polygon', 'arbitrum', 'optimism', 'bnb', 'avalanche' ];
		return in_array( $val, $allowed, true ) ? $val : 'solana';
	}

	public function sanitize_limit( $val ): int {
		return min( 50, max( 1, absint( $val ) ) );
	}

	public function sanitize_interval( $val ): int {
		return max( 60, absint( $val ) );
	}

	// ── Admin notice ───────────────────────────────────────────────────────────

	public function maybe_show_key_notice(): void {
		if ( get_option( 'mp20_publishable_key', '' ) !== '' ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'settings_page_' . self::PAGE_SLUG ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a> %s</p></div>',
			esc_html__( 'MoonPay Top 20: Add your', MP20_TD ),
			'https://dashboard.moonpay.com',
			esc_html__( 'MoonPay publishable key', MP20_TD ),
			esc_html__( 'to enable the "Buy" button.', MP20_TD )
		);
	}

	// ── Page renderer ──────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MoonPay Top 20 Trending Tokens', MP20_TD ); ?></h1>

			<p>
				<?php esc_html_e( 'Use the shortcode', MP20_TD ); ?>
				<code>[moonpay_top20]</code>
				<?php esc_html_e( 'on any page or post to display the trending token list.', MP20_TD ); ?>
				<?php esc_html_e( 'Override defaults with attributes:', MP20_TD ); ?>
				<code>[moonpay_top20 chain="ethereum" limit="10" title="Hot Tokens"]</code>
			</p>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'mp20_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
