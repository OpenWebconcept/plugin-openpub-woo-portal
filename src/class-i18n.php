<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin so that it is ready for translation.
 *
 * @link       https://www.openwebconcept.nl
 * @since      1.0.0
 *
 * @package    Openpub_Woo_Portal_Plugin
 */

namespace Openpub_Woo_Portal_Plugin;

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Openpub_Woo_Portal_Plugin
 * @author     Acato <richardkorthuis@acato.nl>
 */
class I18n {

	/**
	 * Register all hooks needed for this class.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'openpub-woo-portal',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
