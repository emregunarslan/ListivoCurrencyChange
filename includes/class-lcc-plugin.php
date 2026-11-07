<?php
/**
 * Plugin wiring.
 *
 * @package ListivoCurrencyConverter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks, cron and frontend assets.
 */
class LCC_Plugin {

	/**
	 * Singleton.
	 *
	 * @var LCC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Instance accessor.
	 *
	 * @return LCC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( 'LCC_Settings', 'add_menu' ) );
		add_action( 'admin_init', array( 'LCC_Settings', 'register_settings' ) );
		add_action( 'admin_post_lcc_refresh_rate', array( $this, 'handle_refresh' ) );
		add_action( 'admin_notices', array( $this, 'refresh_notice' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'lcc_refresh_rate_event', array( 'LCC_Rates', 'refresh' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LCC_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Activation: defaults and cron.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( LCC_Settings::OPTION ) ) {
			add_option( LCC_Settings::OPTION, LCC_Settings::defaults() );
		}

		if ( ! wp_next_scheduled( 'lcc_refresh_rate_event' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'lcc_refresh_rate_event' );
		}

		wp_schedule_single_event( time() + 30, 'lcc_refresh_rate_event' );
	}

	/**
	 * Deactivation: remove cron.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'lcc_refresh_rate_event' );
	}

	/**
	 * Manual rate refresh from the settings screen.
	 *
	 * @return void
	 */
	public function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to refresh the exchange rate.', 'listivo-currency-converter' ) );
		}

		check_admin_referer( 'lcc_refresh_rate' );

		$ok = is_array( LCC_Rates::refresh() );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => LCC_Settings::PAGE,
					'lcc_refreshed' => $ok ? '1' : '0',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Admin notice after a manual refresh.
	 *
	 * @return void
	 */
	public function refresh_notice() {
		if ( ! isset( $_GET['page'], $_GET['lcc_refreshed'] ) ) {
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) );

		if ( LCC_Settings::PAGE !== $page ) {
			return;
		}

		$ok = '1' === sanitize_key( wp_unslash( $_GET['lcc_refreshed'] ) );

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $ok ? 'notice-success' : 'notice-error' ),
			esc_html(
				$ok
					? __( 'Exchange rate refreshed.', 'listivo-currency-converter' )
					: __( 'The exchange-rate feed could not be reached. The previous rate is still in use.', 'listivo-currency-converter' )
			)
		);
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=' . LCC_Settings::PAGE ) ) . '">' . esc_html__( 'Settings', 'listivo-currency-converter' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Load the converter on Listivo panel pages.
	 *
	 * @return void
	 */
	public function enqueue_frontend() {
		$uri            = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$should_enqueue = (bool) preg_match( '#/panel(?:/|$)#i', $uri );

		/**
		 * Filter whether the converter is loaded for the current request.
		 *
		 * @param bool   $should_enqueue Default match on /panel/ URLs.
		 * @param string $uri            Current request URI.
		 */
		if ( ! apply_filters( 'lcc_should_enqueue', $should_enqueue, $uri ) ) {
			return;
		}

		// The form uses the cached rate only, so a slow feed can never delay it.
		$rate = LCC_Rates::get_rate( false );

		if ( empty( $rate['usd_to_cad'] ) || ! LCC_Rates::is_valid_rate( $rate['usd_to_cad'] ) ) {
			return;
		}

		$settings = LCC_Settings::get();

		$js_path  = LCC_DIR . 'assets/js/frontend.js';
		$css_path = LCC_DIR . 'assets/css/frontend.css';
		$js_ver   = file_exists( $js_path ) ? (string) filemtime( $js_path ) : LCC_VERSION;
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : LCC_VERSION;

		wp_enqueue_style( 'lcc-frontend', LCC_URL . 'assets/css/frontend.css', array(), $css_ver );
		wp_enqueue_script( 'lcc-frontend', LCC_URL . 'assets/js/frontend.js', array(), $js_ver, true );

		wp_localize_script(
			'lcc-frontend',
			'LCC_DATA',
			array(
				'rate'     => array(
					'usdToCad'  => (float) $rate['usd_to_cad'],
					'source'    => (string) $rate['source'],
					'fetchedAt' => isset( $rate['fetched_at'] ) ? (int) $rate['fetched_at'] : 0,
				),
				'fields'   => array(
					'CAD' => (string) $settings['cad_selector'],
					'USD' => (string) $settings['usd_selector'],
				),
				'rounding' => array(
					'CAD' => (float) $settings['cad_round'],
					'USD' => (float) $settings['usd_round'],
				),
				'decimals' => (int) $settings['decimals'],
				'i18n'     => array(
					'converted' => __( 'Converted from %1$s at 1 USD = %2$s CAD', 'listivo-currency-converter' ),
					'locked'    => __( 'Auto-converted from %s. Clear that price to edit this field.', 'listivo-currency-converter' ),
					'fallback'  => __( 'fallback rate', 'listivo-currency-converter' ),
				),
			)
		);
	}
}
