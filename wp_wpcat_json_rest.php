<?php
/**
 *
 * @link              https://github.com/MartinvonBerg/Ext_REST_Media_Lib
 * @since             5.3.0
 * @package           Ext_REST_Media_Lib
 *
 * @wordpress-plugin
 * Plugin Name:       Ext_REST_Media_Lib
 * Plugin URI:        https://github.com/MartinvonBerg/Ext_REST_Media_Lib
 * Description:       Extend the WP-REST-API to work with Wordpress Media-Library directly. Add and Update images even to folders. Only with Authorization.
 * Version:           0.1.2
 * Author:            Martin von Berg
 * Author URI:        https://www.berg-reise-foto.de/software-wordpress-lightroom-plugins/wordpress-plugins-fotos-und-gpx/
 * License:           GPL-2.0
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

// ----------------- global Definitions and settings ---------------------------------
const MIN_IMAGE_SIZE = 100;   // minimal file size in bytes to upload.
const MAX_IMAGE_SIZE = 2560;  // value for resize to ...-scaled.jpg TODO: big_image_size_threshold : read from WP settings. But where?
const RESIZE_QUALITY = 80;    // quality for jpeg image resizing in percent.
const WEBP_QUALITY   = 40;	  // quality for webp image resizing in percent.
const REST_NAMESPACE = 'extmedialib/v1'; // namespace for REST-API.
const EXT_SCALED     = 'scaled';    // filename extension for scaled images as constant. Maybe WP will change this in future.

\add_filter('jpeg_quality', function () { return RESIZE_QUALITY; });
\apply_filters( 'jpeg_quality', RESIZE_QUALITY, 'image/jpeg');

\add_filter( 'wp_editor_set_quality', function () { return WEBP_QUALITY; });
\apply_filters( 'wp_editor_set_quality', WEBP_QUALITY, 'image/webp' );

add_action('rest_api_init', '\mvbplugins\extmedialib\register_md5_original');

// load the helper functions and classes
require_once __DIR__ . '/includes/rest_api_functions.php';
require_once __DIR__ . '/classes/replacer.php';
require_once __DIR__ . '/classes/emrFile.php';

require_once __DIR__ . '/includes/require_rest_auth.php';
require_once __DIR__ . '/includes/trigger_after_rest.php';
require_once __DIR__ . '/includes/rest_api_field_functions.php';
require_once __DIR__ . '/includes/image_update_callback.php';
require_once __DIR__ . '/includes/meta_update_callback.php';
require_once __DIR__ . '/includes/add_image_to_folder_callback.php';
require_once __DIR__ . '/includes/rest_register_functions.php';


// REST-API-EXTENSION FOR WP MEDIA Library---------------------------------------------------------
$field = 'gallery';
$descr = 'gallery-field for Lightroom';
$type = 'string';
add_action_field(  $field,  $descr, $type );

$field = 'gallery_sort';
$descr = 'Gallery-field for sort-order from Lightroom-Collection with custom sort activated';
$type = 'string';
add_action_field(  $field,  $descr, $type );


//--------------------------------------------------------------------
/**
 * register custom-data 'md5' as REST-API-Field only for attachments. Provides md5 sum and size in bytes of original-file.
 *
 * @return void
 */
function register_md5_original()
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
	'validate_callback' => function ( $param, $request, $key ) {
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
		'validate_callback' => function ($param, $request, $key) {
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
		'validate_callback' => function ($param, $request, $key) {
			return is_string($param);
		},
		'required' => true,
		),
	);
$route = 'addtofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)';	// 'addtofolder/', ==> REQ = ...addtofolder/?folder=<foldername>
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
		'validate_callback' => function ($param, $request, $key) {
			return is_string($param);
		},
		'required' => true,
		),
	);
$route = 'addfromfolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)';
$function = 'add_image_from_folder';

add_rest_route( $args, $route, $function);