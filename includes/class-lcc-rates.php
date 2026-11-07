<?php
/**
 * Exchange rate fetching and caching.
 *
 * @package ListivoCurrencyConverter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches the ECB daily reference feed and stores a CAD-per-USD rate.
 */
class LCC_Rates {

	const OPTION = 'lcc_rate_data';
	const LOCK   = 'lcc_rate_lock';
	const FEED   = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
	const FRESH  = 12 * HOUR_IN_SECONDS;

	/**
	 * Cached rate, refreshed when older than 12 hours.
	 *
	 * @param bool $allow_fetch Whether a stale rate may trigger a request.
	 * @return array
	 */
	public static function get_rate( $allow_fetch = true ) {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$has    = isset( $stored['usd_to_cad'] ) && self::is_valid_rate( $stored['usd_to_cad'] );
		$fresh  = $has && ! empty( $stored['fetched_at'] ) && ( time() - (int) $stored['fetched_at'] ) < self::FRESH;

		if ( $fresh ) {
			return self::normalize( $stored );
		}

		if ( $allow_fetch ) {
			$fetched = self::refresh();

			if ( is_array( $fetched ) ) {
				return $fetched;
			}
		}

		if ( $has ) {
			return self::normalize( $stored );
		}

		$settings = LCC_Settings::get();

		return array(
			'usd_to_cad' => (float) $settings['manual_rate'],
			'source'     => 'fallback',
			'fetched_at' => 0,
			'ecb_date'   => '',
		);
	}

	/**
	 * Ensure a stored rate always has the keys the UI expects.
	 *
	 * @param array $rate Stored rate data.
	 * @return array
	 */
	private static function normalize( $rate ) {
		return wp_parse_args(
			$rate,
			array(
				'usd_to_cad' => 0,
				'source'     => 'ecb',
				'fetched_at' => 0,
				'ecb_date'   => '',
			)
		);
	}

	/**
	 * Fetch and store a fresh rate.
	 *
	 * Returns false when a request is already in flight, when the feed cannot
	 * be reached, or when its data is unusable. The previous rate is kept.
	 *
	 * @return array|false
	 */
	public static function refresh() {
		if ( get_transient( self::LOCK ) ) {
			return false;
		}

		set_transient( self::LOCK, 1, 5 * MINUTE_IN_SECONDS );

		$result = self::fetch_ecb();

		if ( is_wp_error( $result ) ) {
			// Keep the lock so a failing feed is not hammered on every page load.
			return false;
		}

		delete_transient( self::LOCK );

		$data = array(
			'usd_to_cad' => (float) $result['usd_to_cad'],
			'source'     => 'ecb',
			'fetched_at' => time(),
			'ecb_date'   => (string) $result['ecb_date'],
		);

		update_option( self::OPTION, $data, false );

		return $data;
	}

	/**
	 * Whether a rate is plausible.
	 *
	 * @param mixed $rate Rate value.
	 * @return bool
	 */
	public static function is_valid_rate( $rate ) {
		$rate = (float) $rate;

		return is_finite( $rate ) && $rate > 0.1 && $rate < 10;
	}

	/**
	 * Parse the ECB daily XML feed.
	 *
	 * ECB quotes both currencies against the euro, so USD/CAD is
	 * (CAD per EUR) / (USD per EUR).
	 *
	 * @return array|WP_Error
	 */
	private static function fetch_ecb() {
		$response = wp_remote_get(
			self::FEED,
			array(
				'timeout'     => 5,
				'redirection' => 2,
				'headers'     => array(
					'Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.8',
				),
				'user-agent'  => 'ListivoCurrencyConverter/' . LCC_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			return new WP_Error(
				'lcc_http',
				/* translators: %d: HTTP status code. */
				sprintf( __( 'The exchange-rate feed returned HTTP %d.', 'listivo-currency-converter' ), $status )
			);
		}

		$body = wp_remote_retrieve_body( $response );

		if ( '' === trim( (string) $body ) ) {
			return new WP_Error( 'lcc_empty', __( 'The exchange-rate feed returned an empty response.', 'listivo-currency-converter' ) );
		}

		if ( ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'lcc_xml', __( 'The SimpleXML extension is not available on this server.', 'listivo-currency-converter' ) );
		}

		$previous = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( (string) $body, 'SimpleXMLElement', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $xml ) {
			return new WP_Error( 'lcc_xml', __( 'The exchange-rate feed could not be parsed.', 'listivo-currency-converter' ) );
		}

		$nodes = $xml->xpath( '//*[@currency]' );
		$nodes = is_array( $nodes ) ? $nodes : array();
		$rates = array();

		foreach ( $nodes as $node ) {
			$code = strtoupper( (string) $node['currency'] );
			$rate = (float) $node['rate'];

			if ( '' !== $code && $rate > 0 ) {
				$rates[ $code ] = $rate;
			}
		}

		if ( empty( $rates['USD'] ) || empty( $rates['CAD'] ) ) {
			return new WP_Error( 'lcc_missing', __( 'The feed did not contain both USD and CAD rates.', 'listivo-currency-converter' ) );
		}

		$usd_to_cad = $rates['CAD'] / $rates['USD'];

		if ( ! self::is_valid_rate( $usd_to_cad ) ) {
			return new WP_Error( 'lcc_range', __( 'The CAD/USD rate from the feed looks invalid.', 'listivo-currency-converter' ) );
		}

		$usd_to_cad = (float) apply_filters( 'lcc_usd_to_cad_rate', $usd_to_cad, $rates );

		if ( ! self::is_valid_rate( $usd_to_cad ) ) {
			return new WP_Error( 'lcc_filter', __( 'The filtered CAD/USD rate is invalid.', 'listivo-currency-converter' ) );
		}

		$dates    = $xml->xpath( '//*[@time]' );
		$ecb_date = ( is_array( $dates ) && ! empty( $dates ) ) ? (string) $dates[0]['time'] : '';

		return array(
			'usd_to_cad' => $usd_to_cad,
			'ecb_date'   => $ecb_date,
		);
	}
}
