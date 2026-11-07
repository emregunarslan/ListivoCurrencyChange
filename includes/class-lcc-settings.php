<?php
/**
 * Settings screen and stored option.
 *
 * @package ListivoCurrencyConverter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the option and renders Settings > Listivo Currency.
 */
class LCC_Settings {

	const OPTION = 'lcc_settings';
	const PAGE   = 'lcc-settings';

	/**
	 * Defaults match the field IDs currently used on racecarandgear.com.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'cad_selector' => '#listivo_130_listivo_13',
			'usd_selector' => '#listivo_9145',
			'manual_rate'  => 1.4488,
			'cad_round'    => 5,
			'usd_round'    => 1,
			'decimals'     => 0,
		);
	}

	/**
	 * Saved settings merged over the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Add the options page.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_options_page(
			__( 'Listivo Currency Converter', 'listivo-currency-converter' ),
			__( 'Listivo Currency', 'listivo-currency-converter' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the setting, sections and fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'lcc_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'lcc_fields',
			__( 'Price fields', 'listivo-currency-converter' ),
			array( __CLASS__, 'render_fields_section' ),
			self::PAGE
		);

		add_settings_field(
			'lcc_cad_selector',
			__( 'CAD field', 'listivo-currency-converter' ),
			array( __CLASS__, 'render_text_field' ),
			self::PAGE,
			'lcc_fields',
			array(
				'key'         => 'cad_selector',
				'class'       => 'regular-text code',
				'placeholder' => '#listivo_130_listivo_13',
				'description' => __( 'CSS selector for the box that holds the Canadian-dollar price.', 'listivo-currency-converter' ),
			)
		);

		add_settings_field(
			'lcc_usd_selector',
			__( 'USD field', 'listivo-currency-converter' ),
			array( __CLASS__, 'render_text_field' ),
			self::PAGE,
			'lcc_fields',
			array(
				'key'         => 'usd_selector',
				'class'       => 'regular-text code',
				'placeholder' => '#listivo_9145',
				'description' => __( 'CSS selector for the box that holds the US-dollar price.', 'listivo-currency-converter' ),
			)
		);

		add_settings_section(
			'lcc_rate',
			__( 'Rate and formatting', 'listivo-currency-converter' ),
			array( __CLASS__, 'render_rate_section' ),
			self::PAGE
		);

		add_settings_field(
			'lcc_manual_rate',
			__( 'Fallback rate', 'listivo-currency-converter' ),
			array( __CLASS__, 'render_text_field' ),
			self::PAGE,
			'lcc_rate',
			array(
				'key'         => 'manual_rate',
				'type'        => 'number',
				'class'       => 'small-text',
				'step'        => '0.0001',
				'min'         => '0.0001',
				'description' => __( 'Used only when the European Central Bank feed cannot be reached. Value is 1 USD in CAD.', 'listivo-currency-converter' ),
			)
		);

		add_settings_field(
			'lcc_cad_round',
			__( 'Round CAD to nearest', 'listivo-currency-converter' ),
			array( __CLASS__, 'render_text_field' ),
			self::PAGE,
			'lcc_rate',
			array(
				'key'         => 'cad_round',
				'type'        => 'number',
				'class'       => 'small-text',
				'step'        => '1',
				'min'         => '0',
				'description' => __( 'Set 0 to disable rounding. The site display converter rounds CAD to 5.', 'listivo-currency-converter' ),
			)
		);

		add_settings_field(
			'lcc_usd_round',
			__( 'Round USD to nearest', 'listivo-currency-converter' ),
			array( __CLASS__, 'render_text_field' ),
			self::PAGE,
			'lcc_rate',
			array(
				'key'         => 'usd_round',
				'type'        => 'number',
				'class'       => 'small-text',
				'step'        => '1',
				'min'         => '0',
				'description' => __( 'Set 0 to disable rounding. The site display converter rounds USD to 1.', 'listivo-currency-converter' ),
			)
		);

		add_settings_field(
			'lcc_decimals',
			__( 'Decimal places', 'listivo-currency-converter' ),
			array( __CLASS__, 'render_select_field' ),
			self::PAGE,
			'lcc_rate',
			array(
				'key'         => 'decimals',
				'choices'     => array(
					0 => '0',
					1 => '1',
					2 => '2',
				),
				'description' => __( 'Applies to the converted value that is written into the other box.', 'listivo-currency-converter' ),
			)
		);
	}

	/**
	 * Fields section intro.
	 *
	 * @return void
	 */
	public static function render_fields_section() {
		echo '<p>' . esc_html__( 'The plugin looks for these two inputs on the Listivo ad form. If a selector is empty, or no longer matches, the plugin falls back to the CAD / USD text in the field labels.', 'listivo-currency-converter' ) . '</p>';
	}

	/**
	 * Rate section intro.
	 *
	 * @return void
	 */
	public static function render_rate_section() {
		echo '<p>' . esc_html__( 'Rates come from the European Central Bank daily reference feed and are cached for 12 hours. The fallback rate is used only when that feed is unreachable.', 'listivo-currency-converter' ) . '</p>';
	}

	/**
	 * Render a text or number setting.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public static function render_text_field( $args ) {
		$settings = self::get();
		$key      = isset( $args['key'] ) ? $args['key'] : '';
		$type     = isset( $args['type'] ) ? $args['type'] : 'text';
		$class    = isset( $args['class'] ) ? $args['class'] : 'regular-text';
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			id="lcc_<?php echo esc_attr( $key ); ?>"
			name="lcc_settings[<?php echo esc_attr( $key ); ?>]"
			value="<?php echo esc_attr( $value ); ?>"
			class="<?php echo esc_attr( $class ); ?>"
			<?php if ( isset( $args['step'] ) ) : ?>step="<?php echo esc_attr( $args['step'] ); ?>"<?php endif; ?>
			<?php if ( isset( $args['min'] ) ) : ?>min="<?php echo esc_attr( $args['min'] ); ?>"<?php endif; ?>
			placeholder="<?php echo esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' ); ?>"
		/>
		<?php
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render a select setting.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public static function render_select_field( $args ) {
		$settings = self::get();
		$key      = isset( $args['key'] ) ? $args['key'] : '';
		$value    = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
		$choices  = isset( $args['choices'] ) ? $args['choices'] : array();
		?>
		<select id="lcc_<?php echo esc_attr( $key ); ?>" name="lcc_settings[<?php echo esc_attr( $key ); ?>]">
			<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
				<option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( $value, (string) $choice_value ); ?>>
					<?php echo esc_html( $choice_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Sanitize the option array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out   = self::get();
		$input = is_array( $input ) ? $input : array();

		if ( isset( $input['cad_selector'] ) ) {
			$out['cad_selector'] = self::sanitize_selector( $input['cad_selector'] );
		}

		if ( isset( $input['usd_selector'] ) ) {
			$out['usd_selector'] = self::sanitize_selector( $input['usd_selector'] );
		}

		if ( isset( $input['manual_rate'] ) ) {
			$rate = (float) $input['manual_rate'];

			if ( $rate > 0 && $rate < 1000 ) {
				$out['manual_rate'] = $rate;
			} else {
				add_settings_error(
					self::OPTION,
					'lcc_manual_rate',
					__( 'The fallback rate must be a positive number.', 'listivo-currency-converter' )
				);
			}
		}

		if ( isset( $input['cad_round'] ) ) {
			$out['cad_round'] = self::sanitize_round( $input['cad_round'] );
		}

		if ( isset( $input['usd_round'] ) ) {
			$out['usd_round'] = self::sanitize_round( $input['usd_round'] );
		}

		if ( isset( $input['decimals'] ) ) {
			$out['decimals'] = max( 0, min( 2, (int) $input['decimals'] ) );
		}

		return $out;
	}

	/**
	 * Keep selectors to a single short line.
	 *
	 * @param mixed $value Raw selector.
	 * @return string
	 */
	private static function sanitize_selector( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		$value = preg_replace( '/[\r\n\t]+/', ' ', $value );

		return substr( $value, 0, 200 );
	}

	/**
	 * Clamp a rounding step.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	private static function sanitize_round( $value ) {
		$value = (float) $value;

		if ( $value < 0 ) {
			return 0.0;
		}

		if ( $value > 10000 ) {
			return 10000.0;
		}

		return $value;
	}

	/**
	 * Human readable rate.
	 *
	 * @param float $rate USD to CAD rate.
	 * @return string
	 */
	public static function format_rate( $rate ) {
		return rtrim( rtrim( number_format( (float) $rate, 6, '.', '' ), '0' ), '.' );
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$rate = LCC_Rates::get_rate();

		$sources = array(
			'ecb'      => __( 'European Central Bank daily reference rate', 'listivo-currency-converter' ),
			'manual'   => __( 'Manual fallback rate', 'listivo-currency-converter' ),
			'fallback' => __( 'Built-in fallback rate — the ECB feed could not be reached', 'listivo-currency-converter' ),
		);

		$source = isset( $rate['source'], $sources[ $rate['source'] ] ) ? $sources[ $rate['source'] ] : __( 'Unknown source', 'listivo-currency-converter' );

		$fetched = '';
		if ( ! empty( $rate['fetched_at'] ) ) {
			$fetched = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $rate['fetched_at'] );
		}

		$notice_class = 'fallback' === $rate['source'] ? 'notice-warning' : 'notice-info';
		$refresh_url  = wp_nonce_url( admin_url( 'admin-post.php?action=lcc_refresh_rate' ), 'lcc_refresh_rate' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Converts between the CAD and USD price boxes on the Listivo ad form while the seller types. The converted box is locked until the source box is cleared.', 'listivo-currency-converter' ); ?></p>

			<?php settings_errors(); ?>

			<div class="notice <?php echo esc_attr( $notice_class ); ?> inline" style="max-width:760px;padding:10px 12px;">
				<p>
					<strong><?php esc_html_e( 'Current rate:', 'listivo-currency-converter' ); ?></strong>
					<?php
					printf(
						/* translators: %s: CAD per USD rate. */
						esc_html__( '1 USD = %s CAD', 'listivo-currency-converter' ),
						'<code>' . esc_html( self::format_rate( $rate['usd_to_cad'] ) ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
					?>
					<br />
					<span class="description">
						<?php echo esc_html( $source ); ?>
						<?php if ( ! empty( $rate['ecb_date'] ) ) : ?>
							&middot; <?php echo esc_html( sprintf( /* translators: %s: ECB reference date. */ __( 'ECB date %s', 'listivo-currency-converter' ), $rate['ecb_date'] ) ); ?>
						<?php endif; ?>
						<?php if ( $fetched ) : ?>
							&middot; <?php echo esc_html( sprintf( /* translators: %s: fetch time. */ __( 'fetched %s', 'listivo-currency-converter' ), $fetched ) ); ?>
						<?php endif; ?>
					</span>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( $refresh_url ); ?>"><?php esc_html_e( 'Refresh rate now', 'listivo-currency-converter' ); ?></a>
				</p>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'lcc_settings_group' );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<h2><?php esc_html_e( 'How it works', 'listivo-currency-converter' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Typing in either the CAD or the USD box fills the other one immediately.', 'listivo-currency-converter' ); ?></li>
				<li><?php esc_html_e( 'The filled box becomes read-only until the source box is cleared, so a seller cannot enter both independently.', 'listivo-currency-converter' ); ?></li>
				<li><?php esc_html_e( 'The value is written into the other Listivo field and saved with the ad. Text fields receive a value like "2,761 USD"; price fields receive a plain number.', 'listivo-currency-converter' ); ?></li>
				<li><?php esc_html_e( 'A hint under the filled box shows the rate used. If the fallback rate is active, the hint says so.', 'listivo-currency-converter' ); ?></li>
				<li><?php esc_html_e( 'The plugin only loads on /panel/ pages. Use the lcc_should_enqueue filter if your form lives at a different URL.', 'listivo-currency-converter' ); ?></li>
			</ol>

			<p class="description">
				<?php esc_html_e( 'If your main Listivo Price field is actually USD, swap the two selectors above. The rate is always quoted as 1 USD = X CAD.', 'listivo-currency-converter' ); ?>
			</p>
		</div>
		<?php
	}
}
