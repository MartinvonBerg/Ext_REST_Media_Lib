<?php
/**
 * Plugin Name:       Media Library Extension
 * Plugin URI:        https://github.com/MartinvonBerg/Ext_REST_Media_Lib
 * Description:       Extend the REST-API to work with Wordpress Media-Library. Organize images in Folders. Add and Update images including Metadata and Posts using the images. Access with Authorization only.
 * Version:           3.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.2
 * Author:            Martin von Berg
 * Author URI:        https://www.berg-reise-foto.de/software-wordpress-lightroom-plugins/wordpress-plugins-fotos-und-gpx/
 * License:           GPL-2.0
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * 
 * @package           Ext_REST_Media_Lib
 */

namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

// ----------------- Admin Section and Global settings ---------------------------------
require_once __DIR__ . '/classes/Admin/AdminSettings.php';

add_action( 'plugins_loaded', function(): void {
    new \mvbplugins\Admin\AdminSettings();
} );

const MIN_IMAGE_SIZE = 100;   // minimal file size in bytes to upload.
const MAX_IMAGE_SIZE = 2560;  // value for resize to ...-scaled.jpg TODO: big_image_size_threshold : read from WP settings. But where?
const REST_NAMESPACE = 'extmedialib/v1'; // namespace for REST-API.
const EXT_SCALED     = 'scaled';    // filename extension for scaled images as constant. Maybe WP will change this in future.

// load the hook functions
require_once __DIR__ . '/includes/trigger_after_rest.php'; // settings are loaded in the file to dis/enable functionality. 

// get the plugin options for the meta update
$options = get_option( 'media-lib-extension' );

// disable the REST-API completely if it is disabled by the plugin settings
if ( isset( $options['use_rest_api_extension'] ) && $options['use_rest_api_extension'] === "1" ) {
	// load the helper functions and classes
	require_once __DIR__ . '/includes/rest_api_functions.php';
	require_once __DIR__ . '/classes/replacer.php'; // is used twice so loaded here to avoid double loading.
	require_once __DIR__ . '/classes/emrFile.php';
	require_once __DIR__ . '/includes/rest_api_field_functions.php';
	require_once __DIR__ . '/includes/image_update_callback.php';
	require_once __DIR__ . '/includes/meta_update_callback.php';
	require_once __DIR__ . '/includes/add_image_to_folder_callback.php';
	require_once __DIR__ . '/includes/add_image_from_folder_callback.php';
	require_once __DIR__ . '/includes/rest_register_functions.php';
	require_once __DIR__ . '/includes/image_subsizes_callback.php';
	require_once __DIR__ . '/includes/add_file_to_folder_callback.php';
	require_once __DIR__ . '/classes/class_image_editor.php'; // uses add_filter 
	require_once __DIR__ . '/includes/require_rest_auth.php'; // uses add_filter
	require_once __DIR__ . '/includes/handle_subsizes_in_db.php'; // uses add_filter


	// REST-API-EXTENSION FOR WP MEDIA Library-----------------------------
	$field = 'gallery';
	$descr = 'gallery-field for Lightroom';
	$type = 'string';
	add_action_field(  $field,  $descr, $type );

	$field = 'gallery_sort';
	$descr = 'Gallery-field for sort-order from Lightroom-Collection with custom sort activated';
	$type = 'string';
	add_action_field(  $field,  $descr, $type );


	//--------------------------------------------------------------------
	add_action('rest_api_init', '\mvbplugins\extmedialib\register_md5_original');
	/**
	 * register custom-data 'md5' as REST-API-Field only for attachments. Provides md5 sum and size in bytes of original-file.
	 *
	 * @return void
	 */
	function register_md5_original(): void
	{
		register_rest_field(
			'attachment',
			'md5_original_file',
			array(
				'get_callback' => '\mvbplugins\extmedialib\cb_get_md5',
				'schema' => array(
					'description' => __('provides md5 sum and size in bytes of original attachment file'),
					'type' => 'array',
					),
			)
		);
	}


	//--------------------------------------------------------------------
	// REST-API Endpoint to update a complete image under the same wordpress-ID. This will remain unchanged.
	$args = array('id' => array(
		'validate_callback' => function ( mixed $param, \WP_REST_Request $request, string $key ): bool {
			return is_numeric( $param );
		},
		'required' => true,
		),
	);
	$route = 'update/(?P<id>[\d]+)';
	$function = 'image_update';

	add_rest_route( $args, $route, $function);


	//--------------------------------------------------------------------
	// REST-API Endpoint to update image-metadata under the same wordpress-ID. The image will remain unchanged.
	$args = array(
		'id' => array(
			'validate_callback' => function (mixed $param, \WP_REST_Request $request, string $key): bool {
				return is_numeric($param);
			},
			'required' => true,
			),
	);
	$route = 'update_meta/(?P<id>[\d]+)';
	$function = 'meta_update';

	add_rest_route( $args, $route, $function);

	//--------------------------------------------------------------------
	// REST-API Endpoint to add an image to a folder in the WP-Media-Catalog. Different from the standard folders under ../uploads.
	$args = array(
		'folder' => array(
			'validate_callback' => function (mixed $param, \WP_REST_Request $request, string $key): bool {
				return is_string($param);
			},
			'required' => true,
			),
	);
	$route = 'addtofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)';	// 'addtofolder/', ==> REQ = ...addtofolder/<folder>
	$function = 'add_image_to_folder';

	add_rest_route( $args, $route, $function);

	//--------------------------------------------------------------------
	// REST-API Endpoint to add images from a folder different from the WP-Standard-Folder (e.g. ../uploads/2020/12) to the WP-Media-Catalog.
	/**
	 * 'Folder' must be provided as a REST-Parameter. 'Folder' shall have only a-z, A-Z, 0-9, _ , -. No other characters allowed.
	 * Provides the new WP-IDs and the filenames that were written to the folder as a JSON-array.
	 * If the jpg from the given folder was already added it will not be added again. But the image will be added if it is in another folder already.
	 * POST-Request without image in Body and content-disposition. This will be ignored even if provided.
	*/ 
	$args = array(
		'folder' => array(
			'validate_callback' => function (mixed $param, \WP_REST_Request $request, string $key): bool {
				return is_string($param);
			},
			'required' => true,
			),
	);
	$route = 'addfromfolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)';
	$function = 'add_image_from_folder';

	add_rest_route( $args, $route, $function);

	//--------------------------------------------------------------------
	// REST-API Endpoint to get the registered image subsizes.
	$args = array(
		'folder' => array(
			'validate_callback' => function (mixed $param, \WP_REST_Request $request, string $key): bool {
				return empty($param);
			},
			'required' => false,
			),
	);
	$route = 'imagesubsizes';	// 
	$function = 'registered_image_subsizes';

	add_rest_route( $args, $route, $function);

	//--------------------------------------------------------------------
	// REST-API Endpoint to add an image file to a folder in the WP-Media-Catalog including the standard WP-Media-Folders
	$args = array(
		'folder' => array(
			'validate_callback' => function (mixed $param, \WP_REST_Request $request, string $key): bool {
				return is_string($param);
			},
			'required' => true,
			),
	);
	$route = 'filetofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)';	// 'filetofolder/', ==> REQ = ...addtofolder/<folder>
	$function = 'add_file_to_folder';

	add_rest_route( $args, $route, $function);
}