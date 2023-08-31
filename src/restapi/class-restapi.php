<?php
/**
 * The REST API specific functionality of the plugin.
 *
 * @link       https://www.openwebconcept.nl
 * @since      1.0.0
 *
 * @package    Openpub_Woo_Portal_Plugin
 * @subpackage Openpub_Woo_Portal_Plugin/RestAPI
 */

namespace Openpub_Woo_Portal_Plugin\RestAPI;

/**
 * The REST API specific functionality of the plugin.
 *
 * @package    Openpub_Woo_Portal_Plugin
 * @subpackage Openpub_Woo_Portal_Plugin/RestAPI
 * @author     Acato <richardkorthuis@acato.nl>
 */
class RestAPI extends \WP_REST_Controller {

	/**
	 * The namespace for this API endpoint.
	 *
	 * @var string $namespace
	 */
	protected $namespace = 'owc/portal/v1';

	/**
	 * The post types returned by this API endpoint, mapped against their meta prefix.
	 *
	 * @var string[] $post_type_mappings
	 */
	private $post_type_mappings = [
		'openwoo-item'       => [
			'meta_prefix'    => 'woo',
			'rest_base'      => 'openwoo',
			'rest_id'        => 'UUID',
			'rest_namespace' => 'owc/openwoo/v1',
		],
		'openconvenant-item' => [
			'meta_prefix'    => 'convenant',
			'rest_base'      => 'openconvenanten',
			'rest_id'        => 'slug',
			'rest_namespace' => 'owc/openconvenanten/v1',
			'slug_regex'     => '/^openconvenanten-(.*)/',
		],
	];

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers the routes for this API endpoint.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'items',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_params(),
			]
		);

		\register_rest_route(
			$this->namespace,
			'filters',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_filters' ],
				'permission_callback' => '__return_true',
			]
		);

		\register_rest_route(
			$this->namespace,
			'filters/years',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_years_filters' ],
				'permission_callback' => '__return_true',
			]
		);

		\register_rest_route(
			$this->namespace,
			'filters/types',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_post_type_filters' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Get a list of all available filters.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function get_filters() {

		$response = [
			'Filters' => [
				[
					'label' => __( 'Year', 'openpub-woo-portal' ),
					'param' => 'owc_year',
					'items' => $this->get_years_filters(),
				],
				[
					'label' => __( 'Post types', 'openpub-woo-portal' ),
					'param' => 'type',
					'items' => $this->get_post_type_filters(),
				],
			],
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Get a list of all available post type filters.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return array|\WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function get_post_type_filters( $request = null ) {
		$post_types = [];
		foreach ( $this->post_type_mappings as $post_type => $mapping ) {
			$post_type    = get_post_type_object( $post_type );
			$post_types[] = [
				'label' => $post_type->labels->name,
				'key'   => $post_type->name,
			];
		}

		array_multisort( array_column( $post_types, 'label' ), SORT_ASC, SORT_REGULAR, $post_types );

		if ( is_null( $request ) ) {
			return $post_types;
		}

		$response = [
			'Types' => $post_types,
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Get a list of all available year filters.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return array|\WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function get_years_filters( $request = null ) {
		global $wpdb;

		$query = sprintf(
			"SELECT YEAR( STR_TO_DATE( pm.meta_value, '%%d-%%m-%%Y' ) ) AS available_year
			FROM {$wpdb->postmeta} pm, {$wpdb->posts} p
			WHERE pm.post_id = p.ID
			AND p.post_type IN ( '%s' )
			AND pm.meta_key IN ( '%s' )
			GROUP BY YEAR( STR_TO_DATE( pm.meta_value, '%%d-%%m-%%Y' ) )
			ORDER BY YEAR( STR_TO_DATE( pm.meta_value, '%%d-%%m-%%Y' ) )",
			implode( "', '", array_keys( $this->post_type_mappings ) ),
			implode( "', '", [ 'woo_Besluitdatum', 'convenant_Datum_ondertekening' ] )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );
		$years   = [];
		foreach ( $results as $result ) {
			$years[] = [
				'label' => $result->available_year,
				'key'   => $result->available_year,
			];
		}

		if ( is_null( $request ) ) {
			return $years;
		}

		$response = [
			'Years' => $years,
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		global $wpdb;

		// Retrieve the list of registered collection query parameters.
		$registered = $this->get_collection_params();
		$args       = array();

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'author'         => 'author__in',
			'author_exclude' => 'author__not_in',
			'exclude'        => 'post__not_in',
			'include'        => 'post__in',
			'menu_order'     => 'menu_order',
			'offset'         => 'offset',
			'order'          => 'order',
			'orderby'        => 'orderby',
			'page'           => 'paged',
			'parent'         => 'post_parent__in',
			'parent_exclude' => 'post_parent__not_in',
			'search'         => 's',
			'slug'           => 'post_name__in',
			'status'         => 'post_status',
		);

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$args[ $wp_param ] = $request[ $api_param ];
			}
		}

		// Check for & assign any parameters which require special handling or setting.
		$args['date_query'] = array();

		if ( isset( $registered['modified_before'], $request['modified_before'] ) ) {
			$args['date_query'][] = array(
				'before' => $request['modified_before'],
				'column' => 'post_modified',
			);
		}

		if ( isset( $registered['modified_after'], $request['modified_after'] ) ) {
			$args['date_query'][] = array(
				'after'  => $request['modified_after'],
				'column' => 'post_modified',
			);
		}

		if ( isset( $registered['owc_year'], $request['owc_year'] ) ) {
			$year_where_clauses = [];
			foreach ( $request['owc_year'] as $year ) {
				$year_where_clauses[] = sprintf( "ID IN ( SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE ( `meta_key` = 'woo_Besluitdatum' OR `meta_key` = 'convenant_Datum_ondertekening' ) AND YEAR( STR_TO_DATE( meta_value, '%%d-%%m-%%Y' ) ) = %d )", $year );
			}
			if ( count( $year_where_clauses ) ) {
				$args['extend_where'][] = '( ' . implode( ' OR ', $year_where_clauses ) . ' )';
			}
		}

		if ( isset( $registered['after'], $request['after'] ) ) {
			$args['extend_where'][] = sprintf( "ID IN ( SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE ( `meta_key` = 'woo_Besluitdatum' OR `meta_key` = 'convenant_Datum_ondertekening' ) AND STR_TO_DATE( meta_value, '%%d-%%m-%%Y' ) >= '%s' )", $request['after'] );
		}
		if ( isset( $registered['before'], $request['before'] ) ) {
			$args['extend_where'][] = sprintf( "ID IN ( SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE ( `meta_key` = 'woo_Besluitdatum' OR `meta_key` = 'convenant_Datum_ondertekening' ) AND STR_TO_DATE( meta_value, '%%d-%%m-%%Y' ) <= '%s' )", $request['before'] );
		}

		// Ensure our per_page parameter overrides any provided posts_per_page filter.
		if ( isset( $registered['per_page'] ) ) {
			$args['posts_per_page'] = $request['per_page'];
		}

		$args['post_type'] = array_keys( $this->post_type_mappings );

		if ( isset( $request['type'] ) ) {
			$intersect = array_intersect( $request['type'], $args['post_type'] );
			if ( count( $intersect ) ) {
				$args['post_type'] = $intersect;
			}
		}

		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new \WP_Query();
		$query_result = $posts_query->query( $query_args );

		$posts = [];
		foreach ( $query_result as $post ) {
			$posts[] = $this->prepare_item_for_response( $post, $request );
		}

		$response = [
			'Results'    => $posts,
			'Pagination' => [
				'total' => $posts_query->found_posts,
				'limit' => (int) $posts_query->query_vars['posts_per_page'],
				'pages' => [
					'total'   => ceil( $posts_query->found_posts / (int) $posts_query->query_vars['posts_per_page'] ),
					'current' => (int) $query_args['paged'],
				],
			],
			'Links'      => [],
		];

		foreach ( $this->post_type_mappings as $type => $post_type_mapping ) {
			$response['Links'][ $type ] = rest_url() . $post_type_mapping['rest_namespace'] . '/items/{owc_item}';
		}

		$response = rest_ensure_response( $response );

		return $response;
	}

	/**
	 * Prepare an item for the response.
	 *
	 * @param \WP_Post         $item    The current item.
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return array|\WP_Error|\WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$post = [
			'Type'         => $item->post_type,
			'ID'           => $this->get_meta( 'Kenmerk', $item ) ?: $this->get_meta( 'ID', $item ),
			'Titel'        => $this->get_meta( 'Onderwerp', $item ) ?: $this->get_meta( 'Titel', $item ),
			'Samenvatting' => $this->get_meta( 'Samenvatting', $item ) ?: $item->post_excerpt,
			'Bijlagen'     => $this->map_attachments( $this->get_meta( 'Bijlagen', $item, false ) ?: $this->get_meta( 'Bijlagen_bestanden', $item, false ), $item ),
			'Datum'        => $this->format_date( $this->get_meta( 'Besluitdatum', $item ) ?: $this->get_meta( 'Datum_ondertekening', $item ) ),
			'Partijen'     => $this->map_parties( $this->get_meta( 'Partijen', $item, false ) ),
		];

		$rest_namespace = $this->post_type_mappings[ $item->post_type ]['rest_namespace'];
		$rest_base      = $this->post_type_mappings[ $item->post_type ]['rest_base'];

		$item->slug = $item->post_name;
		if ( ! empty( $this->post_type_mappings[ $item->post_type ]['slug_regex'] ) ) {
			$item->slug = preg_replace( $this->post_type_mappings[ $item->post_type ]['slug_regex'], '\1', $item->slug );
		}
		$rest_id = $this->get_meta( $this->post_type_mappings[ $item->post_type ]['rest_id'], $item );
		if ( 'slug' === $this->post_type_mappings[ $item->post_type ]['rest_id'] ) {
			$rest_id = $item->slug;
		}

		$post['_links']['rest'] = sprintf(
			rest_url() . $rest_namespace . '/items/%s',
			$rest_id
		);
		$post['_links']['self'] = '?' . http_build_query(
			[
				'owc_type' => $item->post_type,
				'owc_item' => $rest_id,
			]
		);

		return array_filter( $post );
	}

	/**
	 * Translate date format (dd-mm-YYYY) to English date format (YYYY-mm-dd).
	 *
	 * @param string $date The date in the format dd-mm-YYYY.
	 *
	 * @return string
	 */
	private function format_date( $date ) {
		$formatted_date = \DateTime::createFromFormat( 'd-m-Y', $date );

		if ( ! $formatted_date instanceof \DateTime ) {
			return $date;
		}

		return $formatted_date->format( 'Y-m-d' );
	}

	/**
	 * Map parties to a uniform naming.
	 *
	 * @param array $parties An array of parties.
	 *
	 * @return array
	 */
	private function map_parties( $parties ) {
		$parties = array_map(
			function ( $item ) {
				return [ 'Naam' => $item['convenant_Partijen_Naam'] ];
			},
			$parties
		);

		return $parties;
	}

	/**
	 * Map attachments to a uniform naming.
	 *
	 * @param array    $attachments An array of attachments.
	 * @param \WP_Post $post        The current post.
	 *
	 * @return array
	 */
	private function map_attachments( $attachments, $post ) {
		$attachments = ! empty( $attachments[0] ) ? $attachments[0] : [];

		$items = [];
		foreach ( $attachments as $attachment ) {
			$item['Titel_Bijlage'] = $attachment['woo_Titel_Bijlage'] ?? $attachment['convenant_Bijlagen_Naam'] ?? '';
			$item['URL_Bijlage']   = $attachment['woo_URL_Bijlage'] ?? $attachment['convenant_Bijlagen_URL'] ?? '';

			if ( empty( $item['URL_Bijlage'] ) && isset( $attachment['convenant_Bijlagen_Bestand_id'] ) ) {
				$item['URL_Bijlage'] = \wp_get_attachment_url( $attachment['convenant_Bijlagen_Bestand_id'] );
			} elseif ( empty( $item['URL_Bijlage'] ) && isset( $attachment['woo_Bijlage'] ) ) {
				$item['URL_Bijlage'] = $attachment['woo_Bijlage'];
			}

			if ( ! empty( $item['Titel_Bijlage'] ) || ! empty( $item['URL_Bijlage'] ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Get meta from the current item.
	 *
	 * @param string   $key    The meta key.
	 * @param \WP_Post $item   The current post.
	 * @param bool     $single Return a single item or array.
	 *
	 * @return mixed
	 */
	private function get_meta( $key, $item, $single = true ) {
		return get_post_meta( $item->ID, $this->post_type_mappings[ $item->post_type ]['meta_prefix'] . '_' . $key, $single );
	}

	/**
	 * Retrieves the query params for the collection.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['context']['default'] = 'view';

		$query_params['type'] = [
			'description' => __( 'Limit response to posts within (a) specific post type(s).', 'openpub-woo-portal' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'string',
			],
			'default'     => [],
		];

		$query_params['owc_year'] = [
			'description' => __( 'Limit response to posts within (a) specific year(s).', 'openpub-woo-portal' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'integer',
			],
			'default'     => [],
		];

		$query_params['after'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts published after a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['modified_after'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts modified after a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['author']         = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to posts assigned to specific authors.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);
		$query_params['author_exclude'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Ensure result set excludes posts assigned to specific authors.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['before'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts published before a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['modified_before'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts modified before a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['exclude'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Ensure result set excludes specific IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['include'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to specific IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['offset'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Offset the result set by a specific number of items.' ),
			'type'        => 'integer',
		);

		$query_params['order'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Order sort attribute ascending or descending.' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		$query_params['orderby'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Sort collection by post attribute.' ),
			'type'        => 'string',
			'default'     => 'date',
			'enum'        => array(
				'author',
				'date',
				'id',
				'include',
				'modified',
				'parent',
				'relevance',
				'slug',
				'include_slugs',
				'title',
			),
		);

		$query_params['parent']         = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to items with particular parent IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);
		$query_params['parent_exclude'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to all items except those of a particular parent ID.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['slug'] = array(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to posts with one or more specific slugs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		return $query_params;
	}

	/**
	 * Determines the allowed query_vars for a get_items() response and prepares them for WP_Query.
	 *
	 * @param array            $prepared_args Optional. Prepared WP_Query arguments. Default empty array.
	 * @param \WP_REST_Request $request       Optional. Full details about the request.
	 *
	 * @return array Items query arguments.
	 */
	protected function prepare_items_query( $prepared_args = array(), $request = null ) {
		$query_args = array();

		foreach ( $prepared_args as $key => $value ) {
			$query_args[ $key ] = $value;
		}

		// Map to proper WP_Query orderby param.
		if ( isset( $query_args['orderby'] ) && isset( $request['orderby'] ) ) {
			$orderby_mappings = array(
				'id'            => 'ID',
				'include'       => 'post__in',
				'slug'          => 'post_name',
				'include_slugs' => 'post_name__in',
			);

			if ( isset( $orderby_mappings[ $request['orderby'] ] ) ) {
				$query_args['orderby'] = $orderby_mappings[ $request['orderby'] ];
			}
		}

		return $query_args;
	}
}
