<?php
/**
 * Plugin Name: MoonPay Top 20 Trending Tokens
 * Plugin URI:  https://moonpay.com
 * Description: Displays the top 20 trending crypto tokens powered by the MoonPay Agents API, with direct buy links.
 * Version:     1.1.1
 * Author:      @fPHXGallery
 * License:     GPL-2.0-or-later
 * Text Domain: moonpay-top20
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MP20_VERSION',    '1.1.1' );
define( 'MP20_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MP20_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MP20_TD',         'moonpay-top20' );

require_once MP20_PLUGIN_DIR . 'includes/class-moonpay-agents-api.php';
require_once MP20_PLUGIN_DIR . 'admin/class-top20-settings.php';

/**
 * Main plugin bootstrap.
 */
final class MoonPay_Top20 {

	private static ?MoonPay_Top20 $instance = null;

	public static function instance(): MoonPay_Top20 {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_shortcode( 'moonpay_top20', [ $this, 'render_shortcode' ] );
		add_action( 'wp_ajax_mp20_refresh',        [ $this, 'ajax_refresh' ] );
		add_action( 'wp_ajax_nopriv_mp20_refresh', [ $this, 'ajax_refresh' ] );

		new MP20_Settings();
	}

	public function enqueue_assets(): void {
		wp_enqueue_style(
			'mp20-style',
			MP20_PLUGIN_URL . 'assets/css/style.css',
			[],
			MP20_VERSION
		);

		wp_enqueue_script(
			'mp20-main',
			MP20_PLUGIN_URL . 'assets/js/main.js',
			[ 'jquery' ],
			MP20_VERSION,
			true
		);

		wp_localize_script( 'mp20-main', 'mp20Data', [
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'mp20_refresh_nonce' ),
			'refreshRate' => absint( get_option( 'mp20_refresh_interval', 300 ) ) * 1000,
		] );
	}

	/**
	 * [moonpay_top20] shortcode handler.
	 *
	 * Attributes:
	 *   chain  – chain to fetch trending tokens for (default: option value or 'solana')
	 *   limit  – number of tokens to show (default: 20, max: 50)
	 *   title  – widget heading
	 */
	public function render_shortcode( array $atts = [] ): string {
		$atts = shortcode_atts( [
			'chain' => get_option( 'mp20_chain', 'solana' ),
			'limit' => get_option( 'mp20_limit', 20 ),
			'title' => get_option( 'mp20_title', __( 'Top 20 Trending Tokens', MP20_TD ) ),
		], $atts, 'moonpay_top20' );

		$atts['limit'] = min( 50, max( 1, absint( $atts['limit'] ) ) );

		return $this->get_display_html( $atts );
	}

	/**
	 * Builds the display HTML using cached token data.
	 */
	public function get_display_html( array $args = [] ): string {
		$chain   = sanitize_text_field( $args['chain'] ?? get_option( 'mp20_chain', 'solana' ) );
		$limit   = min( 50, max( 1, absint( $args['limit'] ?? get_option( 'mp20_limit', 20 ) ) ) );
		$title   = $args['title'] ?? get_option( 'mp20_title', __( 'Top 20 Trending Tokens', MP20_TD ) );
		$api_key = get_option( 'mp20_publishable_key', '' );
		$tokens  = $this->get_trending_tokens( $chain, $limit );

		ob_start();
		include MP20_PLUGIN_DIR . 'templates/top20-list.php';
		return ob_get_clean();
	}

	/**
	 * Fetches trending tokens from MoonPay Agents API, with transient caching.
	 *
	 * @return array[] Array of normalised token arrays, or empty array on error.
	 */
	public function get_trending_tokens( string $chain = 'solana', int $limit = 20 ): array {
		$cache_key = 'mp20_trending_' . $chain . '_' . $limit;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$api    = new MP20_Agents_API();
		$tokens = $api->get_trending( $chain, $limit );

		if ( empty( $tokens ) ) {
			return [];
		}

		$ttl = absint( get_option( 'mp20_refresh_interval', 300 ) );
		set_transient( $cache_key, $tokens, $ttl );

		return $tokens;
	}

	/**
	 * AJAX: clears the cache and returns fresh HTML.
	 */
	public function ajax_refresh(): void {
		check_ajax_referer( 'mp20_refresh_nonce', 'nonce' );

		$chain = sanitize_text_field( $_POST['chain'] ?? get_option( 'mp20_chain', 'solana' ) );
		$limit = min( 50, max( 1, absint( $_POST['limit'] ?? get_option( 'mp20_limit', 20 ) ) ) );

		// Clear this cache key so get_display_html fetches fresh data.
		delete_transient( 'mp20_trending_' . $chain . '_' . $limit );

		wp_send_json_success( [
			'html' => $this->get_display_html( [
				'chain' => $chain,
				'limit' => $limit,
			] ),
		] );
	}
}

/**
 * Activation: seed default options.
 */
register_activation_hook( __FILE__, function (): void {
	add_option( 'mp20_publishable_key',  '' );
	add_option( 'mp20_access_token',     '' );
	add_option( 'mp20_chain',            'solana' );
	add_option( 'mp20_limit',            20 );
	add_option( 'mp20_title',            'Top 20 Trending Tokens' );
	add_option( 'mp20_refresh_interval', 300 );
} );

/**
 * Deactivation: purge all plugin transients.
 */
register_deactivation_hook( __FILE__, function (): void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mp20_%' OR option_name LIKE '_transient_timeout_mp20_%'"
	);
} );

add_action( 'plugins_loaded', function (): void {
	MoonPay_Top20::instance();
} );
