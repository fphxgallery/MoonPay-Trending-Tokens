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
	'solana'   => 'Solana',
	'ethereum' => 'Ethereum',
	'base'     => 'Base',
	'polygon'  => 'Polygon',
	'arbitrum' => 'Arbitrum',
	'optimism' => 'Optimism',
	'bnb'      => 'BNB',
	'avalanche'=> 'Avalanche',
];

$chain_label = $chain_labels[ $chain ] ?? ucfirst( $chain );
$has_api_key = ! empty( $api_key );
$agents_api  = new MP20_Agents_API();
?>

<div class="mp20-wrap" data-mp20-chain="<?php echo esc_attr( $chain ); ?>" data-mp20-limit="<?php echo esc_attr( $limit ); ?>">

	<div class="mp20-header">
		<div class="mp20-header-left">
			<h3 class="mp20-title"><?php echo esc_html( $title ); ?></h3>
			<span class="mp20-chain-badge"><?php echo esc_html( $chain_label ); ?></span>
		</div>
		<div style="display:flex;gap:6px;align-items:center;">
			<button type="button" class="mp20-theme-btn" aria-label="<?php esc_attr_e( 'Toggle dark mode', MP20_TD ); ?>" title="<?php esc_attr_e( 'Toggle dark mode', MP20_TD ); ?>">
				<span class="mp20-theme-icon">&#9790;</span>
			</button>
			<button type="button" class="mp20-refresh-btn" aria-label="<?php esc_attr_e( 'Refresh', MP20_TD ); ?>">
				<span class="mp20-refresh-icon">&#8635;</span>
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
						esc_html__( 'Tip: add your MoonPay access token in %s to enable trending data.', MP20_TD ),
						'<a href="' . esc_url( admin_url( 'options-general.php?page=moonpay-top20' ) ) . '">' . esc_html__( 'Settings', MP20_TD ) . '</a>'
					);
					?>
				</p>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<div class="mp20-table-wrapper">
			<table class="mp20-table">
				<thead>
					<tr>
						<th class="mp20-col-rank">#</th>
						<th class="mp20-col-token"><?php esc_html_e( 'Token', MP20_TD ); ?></th>
						<th class="mp20-col-price"><?php esc_html_e( 'Price', MP20_TD ); ?></th>
						<th class="mp20-col-change"><?php esc_html_e( '24h', MP20_TD ); ?></th>
						<th class="mp20-col-volume"><?php esc_html_e( 'Volume', MP20_TD ); ?></th>
						<?php if ( $has_api_key ) : ?>
							<th class="mp20-col-buy"><?php esc_html_e( 'Buy', MP20_TD ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $tokens as $i => $token ) :
					$is_up       = $token['change_24h'] >= 0;
					$change_cls  = $is_up ? 'mp20-up' : 'mp20-down';
					$change_icon = $is_up ? '&#9650;' : '&#9660;';
					$price       = $token['price_usd'];
					$price_fmt   = $price > 0 ? '$' . ( $price >= 1 ? number_format( $price, 2 ) : number_format( $price, 6 ) ) : '—';
					$change_fmt  = $token['change_24h'] !== 0.0
						? ( $is_up ? '+' : '' ) . number_format( $token['change_24h'], 2 ) . '%'
						: '—';
					$vol         = $token['volume_24h'];
					$vol_fmt     = $vol > 0 ? '$' . number_format( $vol, 0 ) : '—';
					$buy_url     = $has_api_key ? $agents_api->get_buy_url( $token['moonpay_code'] ) : '';
				?>
					<tr class="mp20-row mp20-row--clickable"
					data-rank="<?php echo esc_attr( $i + 1 ); ?>"
					data-address="<?php echo esc_attr( $token['address'] ); ?>"
					data-symbol="<?php echo esc_attr( $token['symbol'] ); ?>"
					data-name="<?php echo esc_attr( $token['name'] ); ?>"
					data-chain="<?php echo esc_attr( $token['chain'] ); ?>"
					tabindex="0"
					role="button"
					aria-label="<?php echo esc_attr( sprintf( 'View details for %s', $token['name'] ) ); ?>"
				>

						<td class="mp20-col-rank">
							<span class="mp20-rank-num"><?php echo esc_html( $i + 1 ); ?></span>
							<span class="mp20-fire" title="Trending">&#128293;</span>
						</td>

						<td class="mp20-col-token">
							<div class="mp20-token-info">
								<?php if ( ! empty( $token['logo_url'] ) ) : ?>
									<img
										class="mp20-token-logo"
										src="<?php echo esc_url( $token['logo_url'] ); ?>"
										alt="<?php echo esc_attr( $token['name'] ); ?>"
										width="32"
										height="32"
										loading="lazy"
									>
								<?php else : ?>
									<span class="mp20-token-logo mp20-token-logo--placeholder">
										<?php echo esc_html( substr( $token['symbol'], 0, 1 ) ); ?>
									</span>
								<?php endif; ?>
								<div>
									<span class="mp20-token-name"><?php echo esc_html( $token['name'] ); ?></span>
									<span class="mp20-token-symbol"><?php echo esc_html( $token['symbol'] ); ?></span>
								</div>
							</div>
						</td>

						<td class="mp20-col-price"><?php echo esc_html( $price_fmt ); ?></td>

						<td class="mp20-col-change">
							<?php if ( $token['change_24h'] !== 0.0 ) : ?>
								<span class="mp20-change <?php echo esc_attr( $change_cls ); ?>">
									<?php echo $change_icon; // phpcs:ignore -- safe HTML entity ?>
									<?php echo esc_html( $change_fmt ); ?>
								</span>
							<?php else : ?>
								<span class="mp20-change">—</span>
							<?php endif; ?>
						</td>

						<td class="mp20-col-volume"><?php echo esc_html( $vol_fmt ); ?></td>

						<?php if ( $has_api_key ) : ?>
							<td class="mp20-col-buy">
								<?php if ( ! empty( $buy_url ) ) : ?>
									<a
										href="<?php echo esc_url( $buy_url ); ?>"
										class="mp20-buy-btn"
										target="_blank"
										rel="noopener noreferrer"
									><?php esc_html_e( 'Buy', MP20_TD ); ?></a>
								<?php else : ?>
									<span class="mp20-buy-na">—</span>
								<?php endif; ?>
							</td>
						<?php endif; ?>

					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div><!-- .mp20-table-wrapper -->

	<?php endif; ?>

	<p class="mp20-attribution">
		<?php esc_html_e( 'Trending data powered by MoonPay', MP20_TD ); ?>
		&bull;
		<span class="mp20-updated-at"><?php echo esc_html( current_time( 'H:i' ) ); ?></span>
	</p>

</div><!-- .mp20-wrap -->
