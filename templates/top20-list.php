<?php
/**
 * Front-end template: Top 20 Trending Tokens list.
 *
 * Variables available from calling context:
 *   $tokens   array[]  Normalised token arrays (may be empty).
 *   $title    string   Widget heading.
 *   $api_key  string   MoonPay publishable key.
 *   $chain    string   Chain being displayed.
 *   $limit    int      Max tokens requested.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$chain_labels = [
	'solana'    => 'Solana',
	'ethereum'  => 'Ethereum',
	'base'      => 'Base',
	'polygon'   => 'Polygon',
	'arbitrum'  => 'Arbitrum',
	'optimism'  => 'Optimism',
	'bnb'       => 'BNB',
	'avalanche' => 'Avalanche',
];

$chain_label = $chain_labels[ $chain ] ?? ucfirst( $chain );
$has_api_key = ! empty( $api_key );
$agents_api  = new MP20_Agents_API();
$initial_default = 10; // Rows shown before "Show all".
?>

<div class="mp20-wrap" data-mp20-chain="<?php echo esc_attr( $chain ); ?>" data-mp20-limit="<?php echo esc_attr( $limit ); ?>" data-mp20-initial="<?php echo esc_attr( $initial_default ); ?>">
<div class="mp20-card">

	<div class="mp20-head">
		<div class="mp20-meta">
			<h3 class="mp20-title"><?php echo esc_html( $title ); ?></h3>
			<?php if ( ! empty( $tokens ) ) : ?>
				<span class="mp20-badge is-ok"><?php esc_html_e( 'Live', MP20_TD ); ?></span>
			<?php endif; ?>
			<span class="mp20-badge"><?php echo esc_html( $chain_label ); ?></span>
		</div>
		<div class="mp20-controls">
			<?php if ( ! empty( $tokens ) ) : ?>
				<div class="mp20-tabs" role="group" aria-label="<?php esc_attr_e( 'Sort tokens', MP20_TD ); ?>">
					<button type="button" data-sort="trending" aria-pressed="true"><?php esc_html_e( 'Trending', MP20_TD ); ?></button>
					<button type="button" data-sort="gainers" aria-pressed="false"><?php esc_html_e( 'Gainers', MP20_TD ); ?></button>
					<button type="button" data-sort="losers" aria-pressed="false"><?php esc_html_e( 'Losers', MP20_TD ); ?></button>
				</div>
			<?php endif; ?>
			<button type="button" class="mp20-refresh-btn" aria-label="<?php esc_attr_e( 'Refresh', MP20_TD ); ?>" title="<?php esc_attr_e( 'Refresh', MP20_TD ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/></svg>
			</button>
		</div>
	</div>

	<?php if ( empty( $tokens ) ) : ?>
		<div class="mp20-empty" role="alert">
			<p><?php esc_html_e( 'No trending data available. Check your MoonPay access token or try again later.', MP20_TD ); ?></p>
			<?php if ( ! $has_api_key ) : ?>
				<p class="mp20-notice">
					<?php
					printf(
						/* translators: %s: settings page link */
						wp_kses_post( __( 'Tip: add your MoonPay access token in %s to enable trending data.', MP20_TD ) ),
						'<a href="' . esc_url( admin_url( 'options-general.php?page=moonpay-top20' ) ) . '">' . esc_html__( 'Settings', MP20_TD ) . '</a>'
					);
					?>
				</p>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<div class="mp20-row-head" aria-hidden="true">
			<span>#</span>
			<span><?php esc_html_e( 'Token', MP20_TD ); ?></span>
			<span class="mp20-num"><?php esc_html_e( 'Price', MP20_TD ); ?></span>
			<span class="mp20-num"><?php esc_html_e( '24h volume', MP20_TD ); ?></span>
			<span class="mp20-num"><?php esc_html_e( '24h', MP20_TD ); ?></span>
			<span></span>
		</div>

		<div class="mp20-list">
		<?php foreach ( $tokens as $i => $token ) :
			$is_up      = $token['change_24h'] >= 0;
			$price      = $token['price_usd'];
			$price_fmt  = $price > 0 ? '$' . ( $price >= 1 ? number_format( $price, 2 ) : number_format( $price, 6 ) ) : '—';
			$change_fmt = $token['change_24h'] !== 0.0
				? ( $is_up ? '+' : '' ) . number_format( $token['change_24h'], 2 ) . '%'
				: '—';
			$change_cls = $token['change_24h'] === 0.0 ? '' : ( $is_up ? 'mp20-up' : 'mp20-down' );
			$vol        = $token['volume_24h'];
			$vol_fmt    = $vol > 0 ? '$' . number_format( $vol, 0 ) : '—';
			$buy_url    = $has_api_key ? $agents_api->get_buy_url( $token['moonpay_code'] ) : '';
		?>
			<div class="mp20-item"
				data-rank="<?php echo esc_attr( $i + 1 ); ?>"
				data-change="<?php echo esc_attr( $token['change_24h'] ); ?>"
				data-address="<?php echo esc_attr( $token['address'] ); ?>"
				data-symbol="<?php echo esc_attr( $token['symbol'] ); ?>"
				data-name="<?php echo esc_attr( $token['name'] ); ?>">

				<div class="mp20-row" role="button" tabindex="0" aria-expanded="false"
					aria-label="<?php echo esc_attr( sprintf( 'View details for %s', $token['name'] ) ); ?>">
					<span class="mp20-rank"><?php echo esc_html( $i + 1 ); ?></span>

					<div class="mp20-token">
						<?php if ( ! empty( $token['logo_url'] ) ) : ?>
							<img class="mp20-logo" src="<?php echo esc_url( $token['logo_url'] ); ?>" alt="" width="30" height="30" loading="lazy">
						<?php else : ?>
							<span class="mp20-logo mp20-logo--ph"><?php echo esc_html( substr( $token['symbol'], 0, 1 ) ); ?></span>
						<?php endif; ?>
						<div class="mp20-token-text">
							<span class="mp20-name"><?php echo esc_html( $token['name'] ); ?></span>
							<span class="mp20-sub"><?php echo esc_html( $token['symbol'] ); ?></span>
						</div>
					</div>

					<span class="mp20-num mp20-price" data-label="<?php esc_attr_e( 'Price', MP20_TD ); ?>"><?php echo esc_html( $price_fmt ); ?></span>

					<div class="mp20-vol mp20-num" data-label="<?php esc_attr_e( '24h volume', MP20_TD ); ?>">
						<span class="mp20-sub"><?php echo esc_html( $vol_fmt ); ?></span>
					</div>

					<span class="mp20-num <?php echo esc_attr( $change_cls ); ?>" data-label="<?php esc_attr_e( '24h', MP20_TD ); ?>"><?php echo esc_html( $change_fmt ); ?></span>

					<span class="mp20-buy">
						<?php if ( ! empty( $buy_url ) ) : ?>
							<a href="<?php echo esc_url( $buy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Buy', MP20_TD ); ?></a>
						<?php endif; ?>
					</span>
				</div>

				<div class="mp20-detail" hidden></div>
			</div>
		<?php endforeach; ?>
		</div>

		<?php if ( count( $tokens ) > $initial_default ) : ?>
			<button type="button" class="mp20-more">
				<?php
				/* translators: %d: number of tokens */
				echo esc_html( sprintf( __( 'Show all %d', MP20_TD ), count( $tokens ) ) );
				?>
			</button>
		<?php endif; ?>

	<?php endif; ?>

	<div class="mp20-foot">
		<span><?php esc_html_e( 'Trending data powered by MoonPay', MP20_TD ); ?></span>
		<span><?php
		/* translators: %s: time */
		echo esc_html( sprintf( __( 'Updated %s', MP20_TD ), current_time( 'H:i' ) ) );
		?></span>
	</div>

</div>
</div><!-- .mp20-wrap -->
