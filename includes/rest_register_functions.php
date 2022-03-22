<?php
/**
 * functions for the extension of the rest-api with fields 'gallery', 'gallery_sort' and 'md5'.
 *
 * PHP version 7.4.0 - 8.0.0
 *
 * @category   Rest_Api_Functions
 * @package    Rest_Api_Functions
 * @author     Martin von Berg <mail@mvb1.de>
 * @copyright  2022 Martin von Berg
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       https://github.com/MartinvonBerg/Ext_REST_Media_Lib
 * @since      File available since Release 0.1.0
 */

namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

/**
 * register custom-field $field as REST-API-Field only for attachments (media) and add the action for it.
 *
 * @param  string $field the name of the field to register.
 * @param  string $descr the description for the shema.
 * @param  string $type the datatype of the field, e.g. string.
 * @return void
 */
function add_action_field( string $field, string $descr, string $type ) 
{
	add_action('rest_api_init', function($arguments) use ($field, $descr, $type) 
	{
		register_rest_field(
			'attachment',
			$field,
			array(
				'get_callback' => function ( $object ) use ( $field ) {
					return (string) get_post_meta( $object['id'], $field, true );
				},
				'update_callback' => '\mvbplugins\extmedialib\cb_update_field',
				'schema' => array(
					'description' => __( $descr ),
					'type' => $type,
					),
				)
		);
	}, 10, 3 );
}

/**
 * function to register the endpoint $route in the WP-Media-Catalog.
 *
 * @param  array  $args arguments for rest_route function
 * @param  string $route the route to register including the check sequence like 'update/(?P<id>[\d]+)'
 * @param  string $function the additional function name for get and post callback, after get_ and post_.
 * @return void
 */
function add_rest_route( array $args, string $route, string $function)
{
	add_action('rest_api_init', function($arguments) use ($args, $route, $function) 
	{
		register_rest_route(
			REST_NAMESPACE,
			$route,
			array(
				array(
					'methods'   => 'GET',
					'callback'  => '\mvbplugins\extmedialib\get_' . $function,
					'args' => $args,
					'permission_callback' => function () {
						return current_user_can('administrator');
					} ),
				array(
					'methods'   => 'POST',
					'callback'  => '\mvbplugins\extmedialib\post_' . $function,
					'args' => $args,
					'permission_callback' => function () {
						return current_user_can('administrator');
					} ),
			)
		);
	}, 10, 3 );
}