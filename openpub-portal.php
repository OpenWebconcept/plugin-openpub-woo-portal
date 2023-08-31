<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and starts the plugin.
 *
 * @link              https://www.openwebconcept.nl
 * @since             1.0.0
 * @package           Openpub_Woo_Portal_Plugin
 *
 * @wordpress-plugin
 * Plugin Name:       OpenPub WOO Portal
 * Plugin URI:        https://www.openwebconcept.nl
 * Description:       Adds functionality to the OpenPub required for the WOO Portal.
 * Version:           1.0.0
 * Author:            Acato
 * Author URI:        https://www.acato.nl
 * Text Domain:       openpub-woo-portal
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'OPENPUB_WOO_PORTAL_VERSION', '1.0.0' );

require_once plugin_dir_path( __FILE__ ) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'class-autoloader.php';
spl_autoload_register( array( '\Openpub_Woo_Portal_Plugin\Autoloader', 'autoload' ) );

/**
 * Begins execution of the plugin.
 */
new \Openpub_Woo_Portal_Plugin\Plugin();
