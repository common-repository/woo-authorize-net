<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Woo Authorizenet
 * Plugin URI:        https://profiles.wordpress.org/easypayment/#content-plugins
 * Description:       Easily and Securely Accept Credit Cards using Authorize.net Payment
 * Version:           1.0.0
 * Author:            easypayment
 * Author URI:        https://profiles.wordpress.org/easypayment
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       woo-authorize-net
 * Domain Path:       /languages
 * Requires at least: 3.8
 * Tested up to: 4.9.1
 * WC requires at least: 3.0.0
 * WC tested up to: 3.2.2
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('WOO_AUTHORIZENET_VERSION', '1.0.0');
if (!defined('WOO_AUTHORIZENET_PLUGIN_DIR')) {
    define('WOO_AUTHORIZENET_PLUGIN_DIR', dirname(__FILE__));
}
if (!defined('WOO_AUTHORIZENET_ASSET_URL')) {
    define('WOO_AUTHORIZENET_ASSET_URL', plugin_dir_url(__FILE__));
}
if (!defined('WOO_AUTHORIZENET_PLUGIN_BASENAME')) {
    define('WOO_AUTHORIZENET_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woo-authorizenet-activator.php
 */
function activate_woo_authorizenet() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-woo-authorizenet-activator.php';
    Woo_Authorizenet_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woo-authorizenet-deactivator.php
 */
function deactivate_woo_authorizenet() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-woo-authorizenet-deactivator.php';
    Woo_Authorizenet_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_woo_authorizenet');
register_deactivation_hook(__FILE__, 'deactivate_woo_authorizenet');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-woo-authorizenet.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woo_authorizenet() {

    $plugin = new Woo_Authorizenet();
    $plugin->run();
}

run_woo_authorizenet();
