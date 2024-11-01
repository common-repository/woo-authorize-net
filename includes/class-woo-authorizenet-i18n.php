<?php

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Woo_Authorizenet
 * @subpackage Woo_Authorizenet/includes
 * @author     easypayment <wpeasypayment@gmail.com>
 */
class Woo_Authorizenet_i18n {

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {

        load_plugin_textdomain(
                'woo-authorizenet', false, dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

}
