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
 * Version:           0.0.19
 * Author:            Martin von Berg
 * Author URI:        https://www.berg-reise-foto.de/software-wordpress-lightroom-plugins/wordpress-plugins-fotos-und-gpx/
 * License:           GPL-2.0
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// phpstan: level 6 reached with following 11 remaining errors (used as a baseline for higher levels)
// These errors were carefully reviewed and are regarded as false negative
// 37     Right side of || is always false.: 'ABSPATH' is a WP global 
// 390    Right side of && is always true.: This is not correct.
// 600    Variable $getResp might not be defined. : The variable is defined. The analysis is wrong.
// 841    Variable $url_to_new_file might not be defined. : The variable is defined. The analysis is wrong.
// 853    Variable $title might not be defined. : The variable is defined. The analysis is wrong.
// 853    Variable $ext might not be defined. : The variable is defined. The analysis is wrong.
// 853    Variable $ext might not be defined. : The variable is defined. The analysis is wrong.
// 863    Variable $url_to_new_file might not be defined. : The variable is defined. The analysis is wrong.
// 876    Negated boolean expression is always true.: This is not correct.
// 880    Undefined variable: $upload_id : The variable is defined. The analysis is wrong.
// 892    Variable $getResp might not be defined. : The variable is defined. The analysis is wrong.

// TODO: Aufteilung in einzelne Dateien für die Funktionen zum Feld oder Endpunkt.

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

add_action('rest_api_init', '\mvbplugins\extmedialib\register_gallery');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_gallery_sort');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_md5_original');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_update_image_route');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_update_image_meta_route');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_add_image_rest_route');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_add_folder_rest_route');


// load the helper functions and classes
require_once __DIR__ . '/includes/rest_api_functions.php';
require_once __DIR__ . '/classes/replacer.php';
require_once __DIR__ . '/classes/emrFile.php';

require_once __DIR__ . '/includes/require_rest_auth.php';
require_once __DIR__ . '/includes/trigger_after_rest.php';

// REST-API-EXTENSION FOR WP MEDIA Library---------------------------------------------------------
//--------------------------------------------------------------------
/**
 * register custom-data 'gallery' as REST-API-Field only for attachments (media)
 *
 * @return void
 */ 
function register_gallery()
{
	register_rest_field(
		'attachment',
		'gallery',
		array(
			'get_callback' => '\mvbplugins\extmedialib\cb_get_gallery',
			'update_callback' => '\mvbplugins\extmedialib\cb_upd_gallery',
			'schema' => array(
				'description' => __('gallery-field for Lightroom'),
				'type' => 'string',
				),
			)
	);
}

/**
 * callback to retrieve the gallery entry for the given attachment-id
 *
 * @param array{id:int} $data key-value paired array from the get method with 'id'
 * @return string the current entry for the gallery field
 */
function cb_get_gallery($data)
{
	return (string) get_post_meta( $data['id'], 'gallery', true );
}

/**
 * callback to update the gallery entry for the given attachment-id
 *
 * @param string $value new entry for the gallery field
 * @param object $post e.g. attachment which gallery field should be updated
 * @return bool success of the callback
 */
function cb_upd_gallery($value, $post)
{
	$old = (string) get_post_meta( $post->ID, 'gallery', true );
	$ret = update_post_meta( $post->ID, 'gallery', $value );
	// check the return-value here as this also falso if the value remains unchanged.
	if ( $ret == false && ($old == $value) )
		$ret = true;
	if ( is_int( $ret) )
		$ret = true;
	return $ret;
};

//--------------------------------------------------------------------
/**
 * register custom-data 'gallery_sort' as REST-API-Field only for attachments (media)
 *
 * @return void
 */ 
function register_gallery_sort()
{
	register_rest_field(
		'attachment',
		'gallery_sort',
		array(
			'get_callback' => '\mvbplugins\extmedialib\cb_get_gallery_sort',
			'update_callback' => '\mvbplugins\extmedialib\cb_upd_gallery_sort',
			'schema' => array(
				'description' => __('Gallery-field for sort-order from Lightroom-Collection with custom sort activated'),
				'type' => 'integer',
				)
			)
	);
}

/**
 * callback to retrieve the gallery-sort entry for the given attachment-id
 *
 * @param array{id:int} $data key-value paired array from the get method with 'id'
 * @return string the current entry for the gallery-sort field
 */
function cb_get_gallery_sort($data)
{
	return (string) get_post_meta($data['id'], 'gallery_sort', true);
}

/**
 * callback to update the gallery-sort entry for the given attachment-id
 *
 * @param string $value new entry for the gallery-sort field
 * @param object $post e.g. attachment which gallery-sort-field should be updated
 * @return bool success of the callback
 */
function cb_upd_gallery_sort($value, $post)
{
	$old = (string) get_post_meta( $post->ID, 'gallery_sort', true );
	$ret = update_post_meta( $post->ID, 'gallery_sort', $value );
	// check the return-value here as this causes problems with the LR plugin. 
	if ( $ret == false && ($old == $value) )
		$ret = true;
	if ( is_int( $ret) )
		$ret = true;
	return $ret;
};

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

/**
 * callback to retrieve the MD5 sum and size in bytes for the given attachment-id
 *
 * @param array{id: int} $data key-value paired array from the get method with 'id'
 * @return array{MD5: string, size: int|false} $md5
 * 		         $md5['MD5']: the MD5 sum of the original attachment file, 
 * 				 $md5['size']: the size in bytes of the original attachment file
 */
function cb_get_md5($data)
{
	$original_filename = wp_get_original_image_path($data['id']);
	$md5 = array(
		'MD5' => '0',
		'size' => 0,
		);

	if ( false == $original_filename ) return $md5;

	if (is_file($original_filename)) {
		$size = filesize($original_filename);
		$md5 = array(
			'MD5' => strtoupper((string)md5_file($original_filename)),
			'size' => $size,
			);
	}
	return $md5;
}


//--------------------------------------------------------------------
// REST-API Endpoint to update a complete image under the same wordpress-ID. This will remain unchanged.
/**
 * function to register the endpoint 'update' for updating an Image in the WP-Media-Catalog
 *
 * @return void
 */
function register_update_image_route()
{
	$args = array(
					'id' => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return is_numeric( $param );
						},
						'required' => true,
						),
					);
					
	register_rest_route(
		REST_NAMESPACE,
		'update/(?P<id>[\d]+)',
		array(
			array(
				'methods'   => 'GET',
				'callback'  => '\mvbplugins\extmedialib\get_image_update',
				'args' => $args,
				'permission_callback' => function () {
					return current_user_can('administrator');
				},
				),
			array(
				'methods'   => 'POST',
				'callback'  => '\mvbplugins\extmedialib\post_image_update',
				'args' => $args,
				'permission_callback' => function () {
					return current_user_can('administrator');
				},
				),
		
		)
	);
};

/**
 * Callback for GET to REST-Route 'update/<id>'. Check wether Parameter id (integer!) is an WP media attachment, e.g. an image and calc md5-sum of original file
 *
 * @param array{id:int} $data is the complete Request data of the REST-api GET
 * @return \WP_REST_Response|WP_Error array for the rest response body or a WP Error object
 */
function get_image_update( $data )
{
	$post_id = $data['id'];
	$att = wp_attachment_is_image($post_id);
	$resized = wp_get_attachment_image_src($post_id, 'original');

	if ( 'array' == \gettype( $resized ) )
		$resized = $resized[3];
		
	if ($att && (! $resized)) {
		$original_filename = wp_get_original_image_path($post_id);
		if (false == $original_filename) $original_filename = '';
		
		if (is_file( $original_filename )) {
			$md5 = strtoupper( (string) md5_file( $original_filename ) );
		} else {
			$md5 = 0;
		}
		$getResp = array(
			'message' => 'You requested update of original Image with ID '. $post_id . ' with GET-Method. Please update with POST-Method.',
			'original-file' => $original_filename,
			'md5_original_file' => $md5,
			'max_upload_size' => (string)wp_max_upload_size() . ' bytes'
		);
	} elseif ($att && $resized) {
		$file2 = get_attached_file($post_id, true);
		$getResp = array(
			'message' => 'Image ' . $post_id . ' is a resized image',)
			;
	} else {
		return new \WP_Error('no_image', 'Invalid Image of any type: ' . $post_id, array( 'status' => 404 ));
	};

	return rest_ensure_response ( $getResp );
};

/**
 * Callback for POST to REST-Route 'update/<id>'. Update attachment with Parameter id (integer!) 
 * Important Source: https://developer.wordpress.org/reference/classes/wp_rest_request
 *
 * @param object $data is the complete Request data of the REST-api GET
 * @return \WP_REST_Response|WP_Error array for the rest response body or a WP Error object
 */
function post_image_update( $data )
{
	global $wpdb;

	include_once ABSPATH . 'wp-admin/includes/image.php';
	$minsize   = MIN_IMAGE_SIZE;
	$post_id = $data['id'];
	$att = wp_attachment_is_image($post_id);
	$dir = wp_upload_dir()['basedir'];
	$image = $data->get_body(); // body as string (=jpg-image) of POST-Request
	$postRequestFileName = explode( ';', $data->get_headers()['content_disposition'][0] )[1];
	$postRequestFileName = trim( \str_replace('filename=', '', $postRequestFileName) );
	$postRequestFileName = \sanitize_file_name( $postRequestFileName );
			
	if ( ($att) && (strlen($image) > $minsize) && (strlen($image) < wp_max_upload_size()) ) {
		// get current metadata from WP-Database
		$meta = wp_get_attachment_metadata($post_id);
		if ( false == $meta ) { $meta = array(); }
		$oldlink = get_attachment_link( $post_id );
		
		// Define filenames in different ways for the different functions
		$fileName_from_att_meta = $meta['file'];
		$old_attached_file = $fileName_from_att_meta;
		$old_attached_file_before_update = get_attached_file($post_id, true);
		$old_original_fileName = str_replace( '-' . EXT_SCALED, '', $old_attached_file); // This is used to save the POST-body
		$ext = '.' . pathinfo($old_original_fileName)['extension']; // Get the extension
		$file6 = str_replace($ext, '', $old_original_fileName); // Filename without extension for the deletion with Wildcard '*'
		$file6 = $dir . \DIRECTORY_SEPARATOR . $file6;

		// data for the REST-response
		$base_fileName_from_att_meta = basename($fileName_from_att_meta); // filename with extension with '-scaled'
		$original_filename_old_file = str_replace('-' . EXT_SCALED, '', $base_fileName_from_att_meta);
		$old_upload_dir = str_replace( $base_fileName_from_att_meta, '', $old_attached_file ); // This is the upload dir that was used for the original file $old_upload_dir = str_replace( $original_filename_old_file, '', $old_attached_file );
		$dir = str_replace('\\', '/', $dir ); 
		$old_upload_dir = str_replace('\\', '/', $old_upload_dir );
		$gallerydir = str_replace($dir, '', $old_upload_dir);
		$gallerydir = trim($gallerydir, '/\\');

		// save old Files before, to redo them if something goes wrong
		//function filerename($fileName_from_att_meta) {
		//	rename($fileName_from_att_meta, $fileName_from_att_meta . '.oldimagefile');
		//	if ( ! \is_file( $fileName_from_att_meta . '.oldimagefile' )) $add = 'at least one file not renamed!';
		//}
		$filearray = glob($file6 . '*');
		//array_walk($filearray, '\mvbplugins\extmedialib\filerename');

		array_walk($filearray, function( $fileName_from_att_meta ){
			rename($fileName_from_att_meta, $fileName_from_att_meta . '.oldimagefile');
		} );

		// generate the filename for the new file
		if ( $postRequestFileName == '' ) {
			// if provided filename is empty : use the old filename
			$path_to_new_file = $old_original_fileName;
		} else {
			// generate the complete path for the new uploaded file
			//$path_to_new_file = $old_upload_dir . $postRequestFileName; 
			$path_to_new_file = $dir . \DIRECTORY_SEPARATOR . $gallerydir . \DIRECTORY_SEPARATOR . $postRequestFileName;
		}
		
		// check if file exists alreay, don't overwrite
		$fileexists = \is_file( $path_to_new_file );
		if ( $fileexists ) {
			$getResp = array(
				'message' => __('You requested upload of file') . ' '. $postRequestFileName . ' ' . __('with POST-Method'),
				'Error_Details' => __('Path') . ': ' . $path_to_new_file,
				'file6' => 'filebase for rename: ' . $file6,
				'old' => 'old attach: ' . $old_attached_file_before_update,
				'dir' => 'Variable $dir: ' . $dir,
			);
			$newGetResp = \implode(' , ', $getResp);
			return new \WP_Error( __('File exists'), $newGetResp, array( 'status' => 409 ));
		}

		// Save new file from POST-body and check MIME-Type
		$success_new_file_write = file_put_contents( $path_to_new_file, $image );
		
		// check the new file type and extension
		$changemime = $data->get_params()['changemime'] == 'true' ? true : false;
		$newfile_mime = wp_get_image_mime( $path_to_new_file );
		$new_mime_from_header = $data->get_content_type()['value']; // upload content-type of POST-Request
		$new_File_Extension = pathinfo( $path_to_new_file )['extension'];
		$wp_allowed_mimes = \get_allowed_mime_types();
		$wp_allowed_ext = array_search( $newfile_mime, $wp_allowed_mimes, false);
		$new_ext_matches_mime = stripos( $wp_allowed_ext, $new_File_Extension)>-1 ? true : false;
		$new_File_Extension = '.' . $new_File_Extension;
		$new_File_Name  = pathinfo( $path_to_new_file )['filename'];

		// mime type of the old attachment
		$old_mime_from_att = get_post_mime_type( $post_id ) ;

		if ( ! $changemime ) {
			$all_mime_ext_OK = ($newfile_mime == $old_mime_from_att) && ($newfile_mime == $new_mime_from_header) && ($ext == $new_File_Extension) && $new_ext_matches_mime;
		} else {
			$all_mime_ext_OK = ($newfile_mime == $new_mime_from_header) && $new_ext_matches_mime;
		}
		
		if ( $all_mime_ext_OK ) {

			// store the original image-data in the media replacer class with construct-method of the class
			$replacer = new \mvbplugins\extmedialib\Replacer( $post_id );

			// resize missing images
			$att_array = array(
				'ID'			 => $post_id,
				'guid'           => $path_to_new_file, // works only this way -- use a relative path to ... /uploads/ - folder
				'post_mime_type' => $newfile_mime, // e.g.: 'image/jpg'
				'post_title'     => $new_File_Name, // this creates the title and the permalink, if post_name is empty
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_name' => '' , // this is used for Permalink :  https://example.com/title-88/, (if empty post_title is used)
			);
			
			// update the attachment = image with standard methods of WP
			wp_insert_attachment( $att_array, $path_to_new_file, 0, true, true );
			$success_subsizes = wp_create_image_subsizes( $path_to_new_file, $post_id );

			// update post doesn't update GUID on updates. guid has to be the full url to the file
			$url_to_new_file = get_upload_url() . '/' . $gallerydir . '/' . $new_File_Name . $new_File_Extension;
			$wpdb->update( $wpdb->posts, array( 'guid' =>  $url_to_new_file ), array('ID' => $post_id) );

			// set the meta and the caption back to the original values. Absichtlich? Das Bild kann ein völlig anderes sein.
			// Die Metadaten müssen getrennt gesetzt werden, steht doch auch so in der Anleitung.
			// Diese Funktion aktualisiert nur das Bild, nicht die Metadaten!
			
			//update the posts that use the image with class from plugin enable_media_replace
			// This updates only the image url that are used in the post. The metadata e.g. caption is NOT updated.
			$replacer->new_location_dir = $gallerydir;
			$replacer->set_oldlink( $oldlink );
			$newlink = get_attachment_link( $post_id ); // get_attachment_link( $post_id) ist hier bereits aktualisiert
			$replacer->set_newlink( $newlink );
			$replacer->target_url = $url_to_new_file;
			$replacer->target_metadata = $success_subsizes;
			$replacer->API_doSearchReplace();
			$replacer = null;

		} else {
			$success_subsizes = 'Check-Mime-Type mismatch';
		}
				
		if (($success_new_file_write != false) && (is_array($success_subsizes))) {
			$getResp = array(
				'id' => $post_id,
				'message' => __('Successful update. Except Metadata.'),
				'old_filename' => $original_filename_old_file,
				'new_fullpath' => $path_to_new_file,
				'gallery' => $gallerydir,
				'Bytes written' => $success_new_file_write,
				);
			
			// delete old files
			array_map("unlink", glob($file6 . '*oldimagefile'));

		} else {
			// something went wrong redo the change, recover the old files
			if (is_array($success_subsizes)) {
				$success_subsizes = __('Was OK');
			} elseif (! is_string($success_subsizes)) {
				$success_subsizes = implode($success_subsizes->get_error_messages());
			};

			$success_new_file_write = array(
				'message' => __('ERROR. Something went wrong. Original files not touched.'),
				'new_file_write' => (string)$success_new_file_write,
				'gen_subsizes' => $success_subsizes,
			);

			$getResp = array(
				'message' => __('You requested update with POST-Method of') . ' ID: '. $post_id,
				'Error_Details' => $success_new_file_write,
			);

			// recover the original files if something went wrong
			//function recoverfile( $fileName_from_att_meta ) {
			//	rename($fileName_from_att_meta, str_replace('.oldimagefile', '', $fileName_from_att_meta));
			//}
			$filearray = glob($file6 . '*oldimagefile');
			//array_walk($filearray, '\mvbplugins\extmedialib\recoverfile');

			array_walk( $filearray, function( $fileName_from_att_meta ) {
				rename( $fileName_from_att_meta, str_replace('.oldimagefile', '', $fileName_from_att_meta ) );
			} );

			// delete the file that was uploaded by REST - POST request
			unlink($old_original_fileName);
			$newGetResp = \implode(' , ', $getResp);
			return new \WP_Error('Error', $newGetResp, array( 'status' => 400 ));
		}
		
	} elseif (($att) && (strlen($image) < $minsize)) {
		return new \WP_Error('too_small', 'Invalid Image (smaller than: '. $minsize .' bytes) in body for update of: ' . $post_id, array( 'status' => 400 ));
	} elseif (($att) && (strlen($image) > wp_max_upload_size())) {
		return new \WP_Error('too_big', 'Invalid Image (bigger than: '. wp_max_upload_size() .' bytes) in body for update of: ' . $post_id, array( 'status' => 400 ));
	} elseif (! $att) {
		return new \WP_Error('not_found', 'Attachment is not an Image: ' . $post_id, array( 'status' => 415 ));
	}
	
	return rest_ensure_response($getResp);
};

//--------------------------------------------------------------------
// REST-API Endpoint to update image-metadata under the same wordpress-ID. The image will remain unchanged.
/**
 * function to register the endpoint 'update_meta' for updating an Image in the WP-Media-Catalog
 *
 * @return void
 */
function register_update_image_meta_route()
{
	$args = array(
					'id' => array(
						'validate_callback' => function ($param, $request, $key) {
							return is_numeric($param);
						},
						'required' => true,
						),
					);
					
	register_rest_route(
		REST_NAMESPACE,
		'update_meta/(?P<id>[\d]+)',
		array(
			array(
				'methods'   => 'GET',
				'callback'  => '\mvbplugins\extmedialib\get_meta_update',
				'args' => $args,
				'permission_callback' => function () {
					return current_user_can('administrator');
				},
				),
			array(
				'methods'   => 'POST',
				'callback'  => '\mvbplugins\extmedialib\post_meta_update',
				'args' => $args,
				'permission_callback' => function () {
					return current_user_can('administrator');
				},
				),
		)
	);
};

/**
 * Callback for GET to REST-Route 'update_meta/<id>'. Check wether Parameter id (integer!) is an WP media attachment 
 * 
 * @param object $data is the complete Request data of the REST-api GET
 * @return object WP_Error for the rest response body or a WP Error object
 */
function get_meta_update($data)
{
	$post_id = $data['id'];
	$att = wp_attachment_is_image($post_id);
		
	if ($att) {
		return new \WP_Error('not_implemented', 'You requested update of meta data for Image with ID '. $post_id . ' with GET-Method. Please get image_meta with standard REST-Request.', array( 'status' => 405 ));
	} else {
		return new \WP_Error('no_image', 'Invalid Image of any type: ' . $post_id, array( 'status' => 404 ));
	};
};

/**
 * Callback for POST to REST-Route 'update_meta/<id>'. Update image_meta of attachment with Parameter id (integer!) only if it is a jpg-image
 * 
 * @param object $data is the complete Request data of the REST-api GET
 * @return object \WP_Error for the rest response body or a WP Error object
 */
function post_meta_update($data)
{
	$post_id = $data[ 'id' ];
	$att = wp_attachment_is_image( $post_id );
	$type = $data->get_content_type()['value']; // upload content-type of POST-Request
	$newmeta = $data->get_body(); // body e.g. as JSON with new metadata as string of POST-Request
	$isJSON = bodyIsJSON( $newmeta );
	$newmeta = json_decode($newmeta, $assoc=true);
	$origin = 'mvbplugin';

	if ( ($att) && ( 'application/json' == $type ) && ($newmeta != null) && $isJSON ) {

		// update metadata
		$success = \mvbplugins\extmedialib\update_metadata( $post_id, $newmeta, $origin );

		$mime = \get_post_mime_type( $post_id );
		
		$note = __('NOT changed') . ': title, caption';
		if ( 'image/jpeg' == $mime)
			$note = $note . ', aperture, camera, created_timestamp, focal_length, iso, shutter_speed, orientation';
			
		$getResp = array(
			'message' => __('Success') . '. ' .__('You requested update of image_meta for image ') . $post_id,
			'note' => $note,
			#'Bytes written' => (string)$success,
		);

	} elseif (($att) && (($type!='application/json') || ($newmeta == null))) {
		return new \WP_Error('wrong_data', 'Invalid JSON-Data in body', array( 'status' => 400 ));

	} else {
		return new \WP_Error('no_image', 'Invalid Image: ' . $post_id, array( 'status' => 404 ));
	};
	
	return rest_ensure_response($getResp);
};

//--------------------------------------------------------------------
/**
 * REST-API Endpoint to add an image to a folder in the WP-Media-Catalog. Different from the standard folders under ../uploads.
 * <Folder> must be provided as a REST-Parameter. Folder shall have only a-z, A-Z, 0-9, _ , -. No other characters allowed. 
 * The jpg-image must be in the body of the POST-request.
 *
 * @return void 
 */
function register_add_image_rest_route()
{
	$args = array(
					'folder' => array(
						'validate_callback' => function ($param, $request, $key) {
							return is_string($param);
						},
						'required' => true,
						),
					);
					
	register_rest_route(
		REST_NAMESPACE,
		'addtofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)', // 'addtofolder/', ==> REQ = ...addtofolder/?folder=<foldername>
		array(
			array(
				'methods'   => 'GET',
				'callback'  => '\mvbplugins\extmedialib\get_add_image_to_folder',
				'args' => $args,
				'permission_callback' => function () {
					return current_user_can('administrator');
				},
				),
			array(
				'methods'   => 'POST',
				'callback'  => '\mvbplugins\extmedialib\post_add_image_to_folder',
				'args' => $args,
				'permission_callback' => function () {
					return current_user_can('administrator');
				},
				),
		)
	);
};

/**
 * Callback for GET to REST-Route 'addtofolder/<folder>'. Check wether folder exists and provide message if so
 * 
 * @param object $data is the complete Request data of the REST-api GET
 * @return \WP_REST_Response|\WP_Error REST-response data for the folder if it exists
 */
function get_add_image_to_folder( $data )
{
	$dir = wp_upload_dir()['basedir'];
	$folder = $dir . '/' . $data['folder'];
	$folder = str_replace('\\', '/', $folder);
	$folder = str_replace('\\\\', '/', $folder);
	$folder = str_replace('//', '/', $folder);
	// mind: no translation here to keep the testability
	if (is_dir($folder)) {
		$exists = 'OK';
	} else {
		$exists = 'Could not find directory';
	}

	$getResp = array(
		'message' => 'You requested image addition to folder '. $folder . ' with GET-Request. Please use POST Request.',
		'exists' => $exists,
	);

	return rest_ensure_response($getResp);
};

/**
 * Callback for POST to REST-Route 'addtofolder/<folder>'. Provides the new WP-ID and the filename that was written to the folder.
 * Check wether folder exists. If not, create the folder and add the jpg-image from the body to media cat.
 * URL request Parameters: namespace / 'addtofolder' / foldername / <subfoldername-if-needed> / .....
 * required https Header Paramater: Content-Disposition = attachment; filename=example.jpg
 * required body: the image file with identical mime-type!
 * 
 * @param object $data is the complete Request data of the REST-api POST
 * @return \WP_REST_Response|WP_Error REST-response data for the folder if it exists of Error message
 */
function post_add_image_to_folder($data)
{
	global $wpdb;

	include_once ABSPATH . 'wp-admin/includes/image.php';
	$minsize   = MIN_IMAGE_SIZE;
		
	// Define folder names, escape slashes (could be done with regex but then it's really hard to read)
	$dir = wp_upload_dir()['basedir'];
	$folder = $dir . '/' . $data['folder'];
	$folder = str_replace('\\', '/', $folder );
	$folder = str_replace('\\\\', '/', $folder);
	$folder = str_replace('//', '/', $folder);
	$reqfolder = $data['folder'];
	$reqfolder = str_replace('\\', '/', $reqfolder);
	$reqfolder = str_replace('\\\\', '/', $reqfolder);
	$reqfolder = str_replace('//', '/', $reqfolder);
	
	// check and create folder. Do not use WP-standard-folder in media-cat
	$standard_folder = preg_match_all('/[0-9]+\/[0-9]+/', $folder); // check if WP-standard-folder (e.g. ../2020/12)
	if ($standard_folder != false) {
		return new \WP_Error('not_allowed', 'Do not add image to WP standard media directory', array( 'status' => 400 ));
	}
	if (! is_dir($folder)) {
		wp_mkdir_p($folder);
	}
	
	// check body und Header Content-Disposition
	$type = $data->get_content_type()['value']; // upload content-type of POST-Request
	$image = $data->get_body(); // body e.g. jpg-image as string of POST-Request
	$cont =$data->get_header('Content-Disposition');
	$newfile = '';
	
	// define filename
	if (! empty($cont)) {
		$cont = explode(';', $cont)[1];
		$cont = explode('=', $cont)[1];
		$ext = pathinfo($cont)['extension'];
		$title = basename($cont, '.' . $ext);
		$searchinstring = ['\\', '\s', '/'];
		$title = str_replace($searchinstring, '-', $title);
		$newfile = $folder . '/' . $cont;
		// update post doesn't update GUID on updates. guid has to be the full url to the file
		$url_to_new_file = get_upload_url() . '/' . $reqfolder . '/' . $cont;
	}
	$newexists = file_exists($newfile);
	
	// add the new image if it is a jpg, png, or gif
	if ( ( ( 'image/jpeg' == $type ) || ( 'image/png' == $type ) || ( 'image/gif' == $type ) || ( 'image/webp' == $type ) ) && (strlen($image) > $minsize) && (strlen($image) < wp_max_upload_size()) && (! $newexists)) {
		$success_new_file_write = file_put_contents($newfile, $image);
		$new_file_mime = wp_check_filetype($newfile)['type'];
		$mime_type_ok = $type == $new_file_mime;
		
		if ($success_new_file_write && $mime_type_ok) {
			$att_array = array(
				'guid'           => $url_to_new_file, // // alt: $url_to_new_file works only this way -- use a relative path to ... /uploads/ - folder
				'post_mime_type' => $new_file_mime, // 'image/jpg'
				'post_title'     => $title, // this creates the title and the permalink, if post_name is empty
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_name' => '' , // this is used for Permalink :  https://example.com/title-88/, (if empty post_title is used)
			);
			
			$upload_id = wp_insert_attachment( $att_array, $newfile, 0, true, true ); 
			$success_subsizes = wp_create_image_subsizes( $newfile, $upload_id ) ;
			
			if ( \strpos( $success_subsizes["file"], EXT_SCALED) != \false ) 
				$correct_new_filename = str_replace( '.' . $ext, '-'. EXT_SCALED . '.' . $ext, $cont);
			else
				$correct_new_filename = $cont;

			$attfile = $reqfolder . '/' . $correct_new_filename; 
			
			update_post_meta($upload_id, 'gallery', $reqfolder);
			update_post_meta($upload_id, '_wp_attached_file', $attfile);

			// update post doesn't update GUID on updates. guid has to be the full url to the file
			$wpdb->update( $wpdb->posts, array( 'guid' =>  $url_to_new_file ), array('ID' => $upload_id) );
			
			$getResp = array(
				'id' => $upload_id,
				'message' => 'You requested image addition to folder '. $folder . ' with POST-Request. Done.',
				'new_file_name' => $cont,
				'gallery' => $reqfolder,
				'Bytes written' => $success_new_file_write,
			);
		} elseif (! $success_new_file_write) {
			// something went wrong // delete file
			unlink($newfile);
			return new \WP_Error('error', 'Could not write file ' . $cont, array( 'status' => 400 ));
		} elseif (! $mime_type_ok) {
			// something went wrong // delete file
			unlink($newfile);
			return new \WP_Error('error', 'Mime-Type mismatch for upload ' . $cont, array( 'status' => 400 ));
		} elseif (is_wp_error( $upload_id )) {
			// something went wrong // delete file
			unlink($newfile);
			return new \WP_Error('error', 'Could not generate attachment for file ' . $cont, array( 'status' => 400 ));
		}
		// Do not check $success_resize as it could be a small png for icons or so
	} elseif ($newexists) {
		return new \WP_Error('error', 'File ' . $cont . ' already exists!', array( 'status' => 400 ));
	} else {
		return new \WP_Error('error', 'Other Error ', array( 'status' => 400 ));
	}
	
	return rest_ensure_response($getResp);
};

//--------------------------------------------------------------------
/**
 * REST-API Endpoint to add images from a folder different from the WP-Standard-Folder (e.g. ../uploads/2020/12) to the WP-Media-Catalog.
 * 'Folder' must be provided as a REST-Parameter. 'Folder' shall have only a-z, A-Z, 0-9, _ , -. No other characters allowed.
 * Provides the new WP-IDs and the filenames that were written to the folder as a JSON-array.
 * If the jpg from the given folder was already added it will not be added again. But the image will be added if it is in another folder already.
 * POST-Request without image in Body and content-disposition. This will be ignored even if provided.
 *
 * @return void
 */
function register_add_folder_rest_route()
{
	$args = array(
					'folder' => array(
						'validate_callback' => function ($param, $request, $key) {
							return is_string($param);
						},
						'required' => true,
						),
					);
					
	register_rest_route(
		REST_NAMESPACE,
		'addfromfolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)',
		array(
			array(
				'methods'   => 'GET',
				'callback'  => '\mvbplugins\extmedialib\get_add_image_from_folder',
				'args' => $args,
				'permission_callback' => function () {
					return current_user_can('administrator');
				},
				),
			array(
				'methods'   => 'POST',
				'callback'  => '\mvbplugins\extmedialib\post_add_image_from_folder',
				'args' => $args,
				'permission_callback' => function () {
					return current_user_can('administrator');
				},
				),
		)
	);
};

/**
 * Callback for GET to REST-Route 'addfromfolder/<folder>'. Check wether folder exists and provide message if so
 * 
 * @param object $data is the complete Request data of the REST-api GET
 * @return \WP_REST_Response|\WP_Error REST-response data for the folder if it exists
 */
function get_add_image_from_folder($data)
{
	$dir = wp_upload_dir()['basedir'];
	$folder = $dir . '/' . $data['folder'];
	$folder = str_replace('\\', '/', $folder);
	$folder = str_replace('\\\\', '/', $folder);
	$folder = str_replace('//', '/', $folder);

	if (is_dir($folder)) {
		$exists = 'OK';
		//$files = glob($folder . '/*');
		$files = get_files_from_folder($folder, true);
		//$files = json_encode($files, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES);
	} else {
		$exists = 'Could not find directory';
		$files = '';
	}

	$getResp = array(
		'message' => 'You requested image addition from folder '. $folder . ' with GET-Request. Please use POST Request.',
		'exists' => $exists,
		'files' => $files,
	);

	return rest_ensure_response($getResp);
};

/**
 * Callback for POST to REST-Route 'addfromfolder/<folder>'. Check wether folder exists. Add new images from that folder to media cat.
 * Provides the new WP-ID and the filename that was written to the folder.
 * 
 * @param object $data is the complete Request data of the REST-api POST
 * @return \WP_REST_Response|\WP_Error REST-response data for the folder if it exists of Error message
 */
function post_add_image_from_folder($data)
{
	include_once ABSPATH . 'wp-admin/includes/image.php';
	//$threshold = MAX_IMAGE_SIZE;
	
	// Define folder names, escape slashes (could be done with regex but then it's really hard to read)
	$dir = wp_upload_dir()['basedir'];
	$folder = $dir . '/' . $data['folder'];
	$folder = str_replace('\\', '/', $folder);
	$folder = str_replace('\\\\', '/', $folder);
	$folder = str_replace('//', '/', $folder);
	$reqfolder = $data['folder'];
	$reqfolder = str_replace('\\', '/', $reqfolder);
	$reqfolder = str_replace('\\\\', '/', $reqfolder);
	$reqfolder = str_replace('//', '/', $reqfolder);
	
	// check and create folder. Do not use WP-standard-folder in media-cat
	$standard_folder = preg_match_all('/[0-9]+\/[0-9]+/', $folder); // check if WP-standard-folder
	if ($standard_folder != false) {
		return new \WP_Error('not_allowed', 'Do not add image from WP standard media directory (again)', array( 'status' => 400 ));
	}
	if (! is_dir($folder)) {
		return new \WP_Error('not_exists', 'Directory does not exist', array( 'status' => 400 ));
	}
	
	// check existing content of folder. get files that are not added to WP yet
	$files = get_files_from_folder($folder, false);
	$id = array();
	$files_in_folder = array();
	$i = 0;

	foreach ($files as $file) {
		// add $file to media cat
		$type = wp_check_filetype($file)['type']; //
		
		if ( ( 'image/jpeg' == $type ) || ( 'image/png' == $type ) || ( 'image/gif' == $type ) || ( 'image/webp' == $type ) ) {
			$newfile = $file;
			$new_file_mime = $type;
			$ext = pathinfo($newfile)['extension'];
			$title = basename($newfile, '.' . $ext);
	
			$att_array = array(
				'guid'           => $newfile, // works only this way -- use a relative path to ... /uploads/ - folder
				'post_mime_type' => $new_file_mime, // 'image/jpg'
				'post_title'     => $title, // this creates the title and the permalink, if post_name is empty
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_name' => '' , // this is used for Permalink :  https://example.com/title-88/, (if empty post_title is used)
			);

			$upload_id = wp_insert_attachment($att_array, $newfile);
			$success_subsizes = wp_create_image_subsizes($newfile, $upload_id);
			
			$newfile = str_replace('.' . $ext, '-' . EXT_SCALED . '.' . $ext, $newfile);
			if (file_exists($newfile)) {
				$attfile = $reqfolder . '/'. $title . '-' . EXT_SCALED   . '.' . $ext;
			} else {
				$attfile = $reqfolder . '/'. $title . '.' . $ext;
			}

			update_post_meta($upload_id, '_wp_attached_file', $attfile);
		
			update_post_meta($upload_id, 'gallery', $reqfolder);
		
			if (is_wp_error($upload_id)) {
				// something went wrong with this single file
				$upload_id = '';
			} else {
				// produce Array to provide by REST to the user / application
				$id[$i] = $upload_id;
				$files_in_folder[$i] = $file;
				$i = $i + 1;
			}
		}
	} // end foreach

	$getResp = array(
		'id' => $id,
		'folder' => $reqfolder,
		'message' => 'You requested image addition to folder '. $folder . ' with POST-Request.',
		'files_in_folder' => $files_in_folder,
	);
		
	return rest_ensure_response($getResp);
};
