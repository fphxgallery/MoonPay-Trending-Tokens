<?php
/**
 * MoonPay Agents API client.
 *
 * Calls https://agents.moonpay.com/api/tools/token_trending_list to retrieve
 * the top trending tokens for a given chain.
 *
 * Anonymous usage: 5 req/min.
 * Authenticated (Bearer token): 60 req/min.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MP20_Agents_API {

	private const BASE_URL = 'https://agents.moonpay.com/api/tools/';

	/**
	 * Fetches and normalises trending tokens for the given chain.
	 *
	 * @param string $chain  Chain ID: solana | ethereum | base | polygon | arbitrum | optimism | bnb | avalanche
	 * @param int    $limit  Number of tokens to return (1–50).
	 * @param int    $page   Page number (1-indexed).
	 *
	 * @return array[] Normalised token arrays, or empty array on error.
	 */
	public function get_trending( string $chain = 'solana', int $limit = 20, int $page = 1 ): array {
		$raw = $this->request( 'token_trending_list', [
			'chain' => $chain,
			'limit' => $limit,
			'page'  => $page,
		] );

		if ( is_wp_error( $raw ) || empty( $raw ) ) {
			error_log( '[MoonPay Top 20] Trending API error: ' . ( is_wp_error( $raw ) ? $raw->get_error_message() : 'empty response' ) );
			return [];
		}

		return $this->normalise( $raw, $chain );
	}

	/**
	 * Normalises the raw Agents API response into a consistent shape.
	 *
	 * Actual response format from agents.moonpay.com/api/tools/token_trending_list:
	 *   { "items": [ { "address", "name", "symbol", "image", "chain", "decimals",
	 *                  "marketData": { "price", "marketCap",
	 *                    "priceChangePercent": { "24h": … },
	 *                    "volume": { "24h": … } } } ] }
	 *
	 * Each returned element has:
	 *   name         string   Full token name
	 *   symbol       string   Ticker (upper-case)
	 *   address      string   Contract / mint address
	 *   chain        string   Chain ID
	 *   price_usd    float    Current USD price
	 *   change_24h   float    24-hour price change %
	 *   volume_24h   float    24-hour volume in USD
	 *   market_cap   float    Market cap in USD
	 *   logo_url     string   Token logo URL
	 *   rank         int      Trending position (1-indexed)
	 *   moonpay_code string   MoonPay buy-widget currency code
	 *
	 * @param mixed  $raw   Decoded JSON from the API.
	 * @param string $chain Chain used for this request.
	 * @return array[]
	 */
	private function normalise( $raw, string $chain ): array {
		// Unwrap envelope — actual key is "items", but fall back to other shapes.
		$items = [];

		if ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
			$items = $raw['items'];
		} elseif ( isset( $raw['tokens'] ) && is_array( $raw['tokens'] ) ) {
			$items = $raw['tokens'];
		} elseif ( isset( $raw['result'] ) && is_array( $raw['result'] ) ) {
			$items = $raw['result'];
		} elseif ( isset( $raw['data'] ) && is_array( $raw['data'] ) ) {
			$items = $raw['data'];
		} elseif ( is_array( $raw ) && isset( $raw[0] ) ) {
			$items = $raw;
		} elseif ( isset( $raw['content'] ) ) {
			$decoded = json_decode( $raw['content'], true );
			if ( is_array( $decoded ) ) {
				return $this->normalise( $decoded, $chain );
			}
			return [];
		}

		$tokens = [];
		foreach ( $items as $idx => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			// Market data lives in a nested "marketData" object.
			$md = $item['marketData'] ?? [];

			$symbol  = strtoupper( $item['symbol'] ?? $item['ticker'] ?? '' );
			$name    = $item['name'] ?? $symbol;
			$address = $item['address'] ?? $item['mint'] ?? $item['contractAddress'] ?? '';
			$logo    = $item['image'] ?? $item['logoUrl'] ?? $item['logo_url'] ?? $item['logo'] ?? '';

			// Price: marketData.price, or flat field fallbacks.
			$price = (float) (
				$md['price'] ?? $item['price'] ?? $item['priceUsd'] ?? $item['price_usd'] ?? 0
			);

			// 24h change %: marketData.priceChangePercent.24h, or flat fallbacks.
			$change = (float) (
				$md['priceChangePercent']['24h']
				?? $md['priceChange24h']
				?? $item['priceChange24h']
				?? $item['price_change_24h']
				?? $item['change_24h']
				?? 0
			);

			// 24h volume: marketData.volume.24h, or flat fallbacks.
			$volume = (float) (
				$md['volume']['24h']
				?? $md['volume24h']
				?? $item['volume24h']
				?? $item['volume_24h']
				?? 0
			);

			// Market cap: marketData.marketCap, or flat fallbacks.
			$mcap = (float) (
				$md['marketCap']
				?? $item['marketCap']
				?? $item['market_cap']
				?? $item['marketCapUsd']
				?? 0
			);

			// Rank: use trending position (1-indexed), or field if present.
			$rank = (int) ( $item['rank'] ?? $item['marketCapRank'] ?? $item['market_cap_rank'] ?? ( $idx + 1 ) );

			$tokens[] = [
				'name'         => $name,
				'symbol'       => $symbol,
				'address'      => $address,
				'chain'        => $chain,
				'price_usd'    => $price,
				'change_24h'   => $change,
				'volume_24h'   => $volume,
				'market_cap'   => $mcap,
				'logo_url'     => $logo,
				'rank'         => $rank,
				'moonpay_code' => $this->to_moonpay_code( $symbol, $chain ),
			];
		}

		return $tokens;
	}

	/**
	 * Converts a symbol + chain pair into the MoonPay buy-widget currency code.
	 *
	 * MoonPay uses lowercase codes with optional chain suffix:
	 *   solana  → sol, bonk, jup  (no suffix needed; they're Solana-native codes)
	 *   polygon → eth_polygon
	 *   base    → eth_base
	 *   arbitrum → eth_arbitrum
	 *   optimism → eth_optimism
	 *   bnb     → bnb  (no suffix; native)
	 *   ethereum → eth (no suffix)
	 */
	private function to_moonpay_code( string $symbol, string $chain ): string {
		$code = strtolower( $symbol );

		// For EVM chains that aren't Ethereum mainnet, MoonPay appends a suffix.
		$chain_suffix = [
			'polygon'  => '_polygon',
			'base'     => '_base',
			'arbitrum' => '_arbitrum',
			'optimism' => '_optimism',
		];

		if ( isset( $chain_suffix[ $chain ] ) ) {
			$code .= $chain_suffix[ $chain ];
		}

		return $code;
	}

	/**
	 * Returns a MoonPay buy-widget URL for the given currency code.
	 */
	public function get_buy_url( string $moonpay_code ): string {
		$api_key = get_option( 'mp20_publishable_key', '' );
		if ( empty( $api_key ) ) {
			return '';
		}

		return add_query_arg( [
			'apiKey'       => $api_key,
			'currencyCode' => $moonpay_code,
		], 'https://buy.moonpay.com/' );
	}

	/**
	 * POSTs to the Agents API tool endpoint.
	 *
	 * @param string $tool   Tool name (e.g. 'token_trending_list').
	 * @param array  $body   JSON-encoded request body.
	 * @return array|WP_Error Decoded JSON or WP_Error.
	 */
	private function request( string $tool, array $body = [] ) {
		$url         = self::BASE_URL . $tool;
		$access_token = get_option( 'mp20_access_token', '' );

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'User-Agent'   => 'WordPress/' . get_bloginfo( 'version' ) . '; MoonPay-Top20/' . MP20_VERSION,
		];

		if ( ! empty( $access_token ) ) {
			$headers['Authorization'] = 'Bearer ' . $access_token;
		}

		$response = wp_remote_post( $url, [
			'timeout' => 15,
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'mp20_json_error',
				sprintf( 'MoonPay Agents API returned non-JSON (HTTP %d).', $code )
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			$msg = $data['error'] ?? $data['message'] ?? "HTTP $code";
			return new WP_Error( 'mp20_api_error', "MoonPay Agents API error: $msg" );
		}

		return $data;
	}
}
