<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the public-facing side of the site and
 * the admin area.
 *
 * @link       https://www.openwebconcept.nl
 * @since      1.0.0
 *
 * @package    Openpub_Woo_Portal_Plugin
 */

namespace Openpub_Woo_Portal_Plugin;

use Openpub_Woo_Portal_Plugin\Admin\Admin;
use Openpub_Woo_Portal_Plugin\Frontend\Frontend;
use Openpub_Woo_Portal_Plugin\RestAPI\RestAPI;
use Openpub_Woo_Portal_Plugin\Wp_Query\Where;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and public-facing site hooks.
 *
 * @since      1.0.0
 * @package    Openpub_Woo_Portal_Plugin
 * @author     Acato <richardkorthuis@acato.nl>
 */
class Plugin {

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Define the locale, and set the hooks for the admin area and the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		/**
		 * Enable internationalization.
		 */
		new I18n();

		/**
		 * Register admin specific functionality.
		 */
		new Admin();

		/**
		 * Register frontend specific functionality.
		 */
		new Frontend();

		/**
		 * Register REST API.
		 */
		new RestAPI();

		new Where();
	}
}
