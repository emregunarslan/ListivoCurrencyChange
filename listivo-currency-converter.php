<?php
/**
 * Plugin Name:       Listivo Currency Converter
 * Plugin URI:        https://racecarandgear.com/
 * Description:       Live CAD/USD conversion between the two price boxes on the Listivo ad submission form.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            RaceCarAndGear
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       listivo-currency-converter
 *
 * @package ListivoCurrencyConverter
 */

defined( 'ABSPATH' ) || exit;

define( 'LCC_VERSION', '1.0.0' );
define( 'LCC_FILE', __FILE__ );
define( 'LCC_DIR', plugin_dir_path( __FILE__ ) );
define( 'LCC_URL', plugin_dir_url( __FILE__ ) );

require_once LCC_DIR . 'includes/class-lcc-settings.php';
require_once LCC_DIR . 'includes/class-lcc-rates.php';
require_once LCC_DIR . 'includes/class-lcc-plugin.php';

register_activation_hook( __FILE__, array( 'LCC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LCC_Plugin', 'deactivate' ) );

LCC_Plugin::instance()->init();
