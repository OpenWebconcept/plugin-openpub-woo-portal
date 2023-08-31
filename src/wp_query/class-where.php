<?php
/**
 * Extend the WP_QUERY with the option to add WHERE clauses.
 *
 * @link       https://www.openwebconcept.nl
 * @since      1.0.0
 *
 * @package    Openpub_Woo_Portal_Plugin
 * @subpackage Openpub_Woo_Portal_Plugin/Wp_Query
 */

namespace Openpub_Woo_Portal_Plugin\Wp_Query;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Openpub_Woo_Portal_Plugin
 * @subpackage Openpub_Woo_Portal_Plugin/WP_Query
 * @author     Acato <richardkorthuis@acato.nl>
 */
class Where {


	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_filter( 'posts_where', [ $this, 'extend_where_clause' ], 10, 2 );
	}

	/**
	 * Extend the generated WHERE clause with a custom WHERE clause.
	 *
	 * @param string    $where The current WHERE clause.
	 * @param \WP_Query $wp_query The WP_Query object.
	 *
	 * @return mixed|string
	 */
	public function extend_where_clause( $where, $wp_query ) {
		$extend_where = $wp_query->get( 'extend_where' );
		if ( $extend_where ) {
			$where .= ' AND ' . implode( ' AND ', $extend_where );
		}
		return $where;
	}
}
