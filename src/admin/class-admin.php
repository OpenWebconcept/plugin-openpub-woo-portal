<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.openwebconcept.nl
 * @since      1.0.0
 *
 * @package    Openpub_Woo_Portal_Plugin
 * @subpackage Openpub_Woo_Portal_Plugin/Admin
 */

namespace Openpub_Woo_Portal_Plugin\Admin;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Openpub_Woo_Portal_Plugin
 * @subpackage Openpub_Woo_Portal_Plugin/Admin
 * @author     Acato <richardkorthuis@acato.nl>
 */
class Admin {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		// Add panel to the admin menu with title "OpenPub WOO Portal" that allows to configure the plugin under Settings.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		// Add the option "CORS Origin" to the Settings menu.
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		// Add validation to the options POST-ed.
		add_filter( 'sanitize_option_openpub_woo_portal_plugin', array( $this, 'sanitize_option_openpub_woo_portal_plugin' ) );
		// Inject CORS header, in case of a REST-API call from an external domain, that is whitelisted.
		$this->inject_cors_header();
	}

	/**
	 * Add a submenu item to the Settings menu.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu() {
		add_options_page(
			'OpenPub WOO Portal',
			'OpenPub WOO Portal',
			'manage_options',
			'openpub-woo-portal',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @since    1.0.0
	 */
	public function render_admin_page() {
		?>
		<form action='<?php print esc_attr( admin_url( 'options.php' ) ); ?>' method='post'>
			<?php
			settings_fields( 'openpub_woo_portal_plugin' );
			do_settings_sections( 'openpub_woo_portal_plugin' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Register and add settings.
	 *
	 * @since    1.0.0
	 */
	public function settings_init() {
		register_setting(
			'openpub_woo_portal_plugin',
			'openpub_woo_portal_plugin'
		);

		add_settings_section(
			'openpub_woo_portal_plugin_section',
			__( 'OpenPub WOO Portal settings - CORS Origins', 'openpub-woo-portal' ),
			array( $this, 'settings_section_callback' ),
			'openpub_woo_portal_plugin'
		);

		add_settings_field(
			'openpub_woo_portal_cors_origin',
			__( 'CORS Origins', 'openpub-woo-portal' ),
			array( $this, 'openpub_woo_portal_cors_origin_render' ),
			'openpub_woo_portal_plugin',
			'openpub_woo_portal_plugin_section'
		);
	}

	/**
	 * Sanitize the options POST-ed.
	 *
	 * @since    1.0.0
	 *
	 * @param array $input The POST-ed options of Origins.
	 *
	 * @return mixed
	 */
	public function sanitize_option_openpub_woo_portal_plugin( $input ) {
		$input['openpub_woo_portal_cors_origin'] = sanitize_textarea_field( $input['openpub_woo_portal_cors_origin'] ?? '' );
		$input['openpub_woo_portal_cors_origin'] = str_replace( "\r", "\n", $input['openpub_woo_portal_cors_origin'] );
		$input['openpub_woo_portal_cors_origin'] = explode( "\n", $input['openpub_woo_portal_cors_origin'] );
		$input['openpub_woo_portal_cors_origin'] = array_filter( $input['openpub_woo_portal_cors_origin'] );
		foreach ( $input['openpub_woo_portal_cors_origin'] as $key => $value ) {
			$input['openpub_woo_portal_cors_origin'][ $key ] = esc_url_raw( $value ) ?: esc_url_raw( "http://$value" );
		}
		$input['openpub_woo_portal_cors_origin'] = implode( "\n", $input['openpub_woo_portal_cors_origin'] );

		return $input;
	}

	/**
	 * Render the CORS Origin section.
	 *
	 * @since    1.0.0
	 */
	public function settings_section_callback() {
	}

	/**
	 * Render the CORS Origin field.
	 *
	 * @since    1.0.0
	 */
	public function openpub_woo_portal_cors_origin_render() {
		$options = get_option( 'openpub_woo_portal_plugin', [] );
		?>
		<textarea
			class="widefat"
			rows="10"
			id="openpub_woo_portal_plugin--openpub_woo_portal_cors_origin"
			name='openpub_woo_portal_plugin[openpub_woo_portal_cors_origin]'><?php print esc_textarea( $options['openpub_woo_portal_cors_origin'] ?? '' ); ?></textarea>
		<label for="openpub_woo_portal_plugin--openpub_woo_portal_cors_origin">
			<?php
			esc_html_e( 'Enter the CORS Origin(s) one per line.', 'openpub-woo-portal' );
			?>
		</label>
		<?php
	}

	/**
	 * Inject CORS header, in case of a REST-API call from an external domain, that is whitelisted.
	 */
	private function inject_cors_header() {
		// We're too late. sending them now will have no effect and will producen a warning.
		if ( headers_sent() ) {
			return;
		}
		// Only if this is a REST call.
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return;
		}

		// Only if the option is set.
		$options = get_option( 'openpub_woo_portal_plugin', [] );
		if ( empty( $options['openpub_woo_portal_cors_origin'] ) ) {
			return;
		}

		// Only if the origin is known.
		$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
		if ( ! $origin ) {
			return;
		}

		// Only if the origin is a valid URL.
		$origin = wp_parse_url( $origin, PHP_URL_HOST );
		if ( ! $origin ) {
			return;
		}

		// Generate a nice list of domains and validate against it.
		$allowed_origins = explode( "\n", $options['openpub_woo_portal_cors_origin'] );
		$allowed_origins = array_map( 'wp_parse_url', $allowed_origins );
		$allowed_origins = wp_list_pluck( $allowed_origins, 'host' );
		if ( ! in_array( $origin, $allowed_origins, true ) ) {
			return;
		}

		// Send the header.
		header( 'Access-Control-Allow-Origin: https://' . $origin );
	}
}
