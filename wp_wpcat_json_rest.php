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
 * Version:           0.0.13
 * Author:            Martin von Berg
 * Author URI:        www.mvb1.de
 * License:           GPL-2.0
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace mvbplugins\extmedialib;

use \WP_Error as WP_Error;

defined( 'ABSPATH' ) || die( 'Not defined' );

// ----------------- global Definitions and settings ---------------------------------
const MIN_IMAGE_SIZE = 100;   // minimal file size in bytes to upload.
const MAX_IMAGE_SIZE = 2560;  // value for resize to ...-scaled.jpg TODO: big_image_size_threshold : read from WP settings. But where?
const RESIZE_QUALITY = 82;    // quality for image resizing in percent. I prefer maximum quality.
const REST_NAMESPACE = 'extmedialib/v1'; // namespace for REST-API.
const EXT_SCALED     = 'scaled';    // filename extension for scaled images as constant. Maybe WP will change this in future.

add_filter('jpeg_quality', function () {
	return RESIZE_QUALITY;
});

add_action('rest_api_init', '\mvbplugins\extmedialib\register_gallery');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_gallery_sort');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_md5_original');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_update_image_route');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_update_image_meta_route');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_add_image_rest_route');
add_action('rest_api_init', '\mvbplugins\extmedialib\register_add_folder_rest_route');


// load the helper functions
require_once __DIR__ . '/inc/rest_api_functions.php';


//-------------------- AUTH REQUIRED ------------------------------------------------
// https://developer.wordpress.org/rest-api/frequently-asked-questions/
// ATTENTION: Do not use username and Password or Application Passwords from WP-AdminPage > Users > Profiles together with basic-auth and with http !!!!!!
// Only use together with https
// require the user to be logged in for all REST requests

add_filter('rest_authentication_errors', function ($result) {
	// If a previous authentication check was applied,
	// pass that result along without modification.
    if (true === $result || is_wp_error($result)) {
        return $result;
    }
 
	// No authentication has been performed yet.
	// Return an error if user is not logged in.
	if (! is_user_logged_in()) {
		return new WP_Error(
			'rest_not_logged_in',
			__('You are not currently logged in.'),
			array( 'status' => 401 )
		);
	}
 
	// Our custom authentication check should have no effect
	// on logged-in requests
	return $result;
});


// REST-API-EXTENSION FOR WP MEDIA Library---------------------------------------------------------
//--------------------------------------------------------------------
// register custom-data 'gallery' as REST-API-Field only for attachments (media)
function register_gallery()
{
	register_rest_field(
		'attachment',
		'gallery',
		array(
			'get_callback' => '\mvbplugins\extmedialib\cb_get_gallery',
			'update_callback' => '\mvbplugins\extmedialib\cb_upd_gallery',
			'schema' => array(
				'description' => 'gallery-field for Lightroom',
				'type' => 'string',
				),
			)
	);
}

function cb_get_gallery($data)
{
	return (string)get_post_meta($data['id'], 'gallery', true);
}

function cb_upd_gallery($value, $post)
{
	update_post_meta($post->ID, 'gallery', $value);
	return true;
};


//--------------------------------------------------------------------
// register custom-data 'gallery_sort' as REST-API-Field only for attachments (media)
function register_gallery_sort()
{
	register_rest_field(
		'attachment',
		'gallery_sort',
		array(
			'get_callback' => '\mvbplugins\extmedialib\cb_get_gallery_sort',
			'update_callback' => '\mvbplugins\extmedialib\cb_upd_gallery_sort',
			'schema' => array(
				'description' => 'gallery-field for sort-order from Lightroom-Collection with custom sort activated',
				'type' => 'integer',
				)
			)
	);
}

function cb_get_gallery_sort($data)
{
	return (string)get_post_meta($data['id'], 'gallery_sort', true);
}

function cb_upd_gallery_sort($value, $post)
{
	update_post_meta($post->ID, 'gallery_sort', $value);
	return true;
};


//--------------------------------------------------------------------
// register custom-data 'md5' as REST-API-Field only for attachments
// provides md5 sum and size in bytes of original-file
function register_md5_original()
{
	register_rest_field(
		'attachment',
		'md5_original_file',
		array(
			'get_callback' => '\mvbplugins\extmedialib\cb_get_md5',
			'schema' => array(
				'description' => 'provides md5 sum of original attachment file',
				'type' => 'array',
				),
		)
	);
}

function cb_get_md5($data)
{
	$original_filename = wp_get_original_image_path($data['id']);
	$md5 = array(
		'MD5' => '0',
		'size' => 0,
		);
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

# function to register the endpoint for updating an Image in the WP-Media-Catalog
function register_update_image_route()
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

// Callback for GET to defined REST-Route
// Check wether Parameter id (integer!) is an WP media attachment, e.g. an image and calc md5-sum of original file
// @param $data is the complete Request data
function get_image_update($data)
{
	$post_id = $data['id'];
	$att = wp_attachment_is_image($post_id);
	$resized = wp_get_attachment_image_src($post_id, 'original')[3];
		
	if ($att && (! $resized)) {
		$original_filename = wp_get_original_image_path($post_id);
		;
		if (is_file($original_filename)) {
			$md5 = strtoupper((string)md5_file($original_filename));
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
		return new WP_Error('no_image', 'Invalid Image of any type: ' . $post_id, array( 'status' => 404 ));
	};

	return rest_ensure_response($getResp);
};

// Callback for POST to defined REST-Route
// Update attachment with Parameter id (integer!) only if it is a jpg-image
// @param $data is the complete Request data
// Important Source: https://developer.wordpress.org/reference/classes/wp_rest_request/
function post_image_update($data)
{
	include_once ABSPATH . 'wp-admin/includes/image.php';
	$minsize   = MIN_IMAGE_SIZE;
	$post_id = $data['id'];
	$att = wp_attachment_is_image($post_id);
	$dir = wp_upload_dir()['basedir'];
	$image = $data->get_body(); // body as string (=jpg-image) of POST-Request
		
	if (($att) && (strlen($image) > $minsize) && (strlen($image) < wp_max_upload_size())) {
		// get current metadata from WP-SQL Database
		$meta = wp_get_attachment_metadata($post_id);
		
		// Define filenames in different ways for the different functions
		$file = $meta['file'];
		$file2 = get_attached_file($post_id, true); // identical to $file
		$file3 = basename($file);
		$file4 = str_replace('-' . EXT_SCALED, '', $file3);
		$file5 = str_replace('-' . EXT_SCALED, '', $file2); // This is used to save the POST-body
		$ext = '.' . pathinfo($file5)['extension']; // Get the extension
		$file6 = str_replace($ext, '', $file5); // Filename without extension for the deletion with Wildcard '*'
		$new = str_replace($file3, '', $file2);
		$gallerydir = str_replace($dir, '', $new);
		$gallerydir = trim($gallerydir, '/\\');

		// save old Files before, to redo them if something goes wrong
		function filerename($file)
		{
			rename($file, $file . '.oldimagefile');
		}
		$filearray = glob($file6 . '*');
		array_walk($filearray, '\mvbplugins\extmedialib\filerename');

		// Save new file from POST-body and check MIME-Type
		$success_new_file_write = file_put_contents($file5, $image);
		$newfile_mime = wp_get_image_mime($file5);
		$attmime = get_post_mime_type($post_id);
		$newtype = $data->get_content_type()['value']; // upload content-type of POST-Request
		
		if (($newfile_mime==$attmime) && ($newfile_mime==$newtype)) {
			// resize missing images if mime-types are identical
			$success_subsizes = wp_create_image_subsizes($file5, $post_id);
		} else {
			$success_subsizes = 'Mime-Type mismatch';
		}
				
		if (($success_new_file_write != false) && (is_array($success_subsizes))) {
			$getResp = array(
				'message' => 'You requested update of '. $post_id . ' with POST-Method. Done, except Metadata. Do this with REST.',
				'original_filename' => $file4,
				'scaled_filename' => $file3,
				'fullpath' => $file2,
				'upload_dir' => $dir,
				'gallery' => $gallerydir,
				'Bytes written' => $success_new_file_write,
				);
			
			// delete old files
			array_map("unlink", glob($file6 . '*oldimagefile'));
		} else {
			// something went wrong redo the change, recover the old files
			if (is_array($success_subsizes)) {
				$success_subsizes = 'Was OK';
			} elseif (! is_string($success_subsizes)) {
				$success_subsizes = implode($success_subsizes->get_error_messages());
			};

			$success_new_file_write = array(
				'message' => 'ERROR. Something went wrong. Original files not touched!',
				'new_file_write' => (string)$success_new_file_write,
				'gen_subsizes' => $success_subsizes,
			);

			$getResp = array(
				'message' => 'You requested update of '. $post_id . ' with POST-Method.',
				'Error_Details' => $success_new_file_write,
			);
			function recoverfile($file)
			{
				rename($file, str_replace('.oldimagefile', '', $file));
			}
			$filearray = glob($file6 . '*oldimagefile');
			array_walk($filearray, '\mvbplugins\extmedialib\recoverfile');
			unlink($file5);
			return new WP_Error('Error', $getResp, array( 'status' => 400 ));
		}
	} elseif (($att) && (strlen($image) < $minsize)) {
		return new WP_Error('too_small', 'Invalid Image (smaller than: '. $minsize .' bytes) in body for update of: ' . $post_id, array( 'status' => 400 ));
	} elseif (($att) && (strlen($image) > wp_max_upload_size())) {
		return new WP_Error('too_big', 'Invalid Image (bigger than: '. wp_max_upload_size() .' bytes) in body for update of: ' . $post_id, array( 'status' => 400 ));
	} elseif (! $att) {
		return new WP_Error('not_found', 'Attachment is not an Image: ' . $post_id, array( 'status' => 415 ));
	}
	
	return rest_ensure_response($getResp);
};


//--------------------------------------------------------------------
// REST-API Endpoint to update image-metadata under the same wordpress-ID. This will remain unchanged.

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

// Callback for GET to defined REST-Route
// Check wether Parameter id (integer!) is an WP media attachment
// @param $data is the complete Request data
function get_meta_update($data)
{
	$post_id = $data['id'];
	$att = wp_attachment_is_image($post_id);
		
	if ($att) {
		return new WP_Error('not_implemented', 'You requested update of meta data for Image with ID '. $post_id . ' with GET-Method. Please get image_meta with standard REST-Request.', array( 'status' => 405 ));
	} else {
		return new WP_Error('no_image', 'Invalid Image of any type: ' . $post_id, array( 'status' => 404 ));
	};
};

// Callback for POST to defined REST-Route
// Update image_meta of attachment with Parameter id (integer!) only if it is a jpg-image
// @param $data is the complete Request data
function post_meta_update($data)
{
	$post_id = $data['id'];
	$att = wp_attachment_is_image($post_id);
	$type = $data->get_content_type()['value']; // upload content-type of POST-Request
	$newmeta = $data->get_body(); // body e.g. as JSON with new metadata as string of POST-Request
	$newmeta = json_decode($newmeta, $assoc=true);

	if (($att) && ($type == 'application/json') && ($newmeta != null)) {
		// update metadata
		$success = update_metadata($post_id, $newmeta);
	
		$getResp = array(
			'message' => 'You requested image_meta update of '. $post_id . '. Done.',
			'note' => 'NOT changed: aperture, camera, created_timestamp, focal_length, iso, shutter_speed, orientation',
			'Bytes written' => (string)$success,
		);
	} elseif (($att) && (($type!='application/json') || ($newmeta == null))) {
		return new WP_Error('wrong_data', 'Invalid JSON-Data in body', array( 'status' => 400 ));
	} else {
		return new WP_Error('no_image', 'Invalid JPG-Image: ' . $post_id, array( 'status' => 404 ));
	};
	
	return rest_ensure_response($getResp);
};

//--------------------------------------------------------------------
// REST-API Endpoint to add an image to a folder in the WP-Media-Catalog. Different from the standard folders under ../uploads
// <Folder> must be provided as a REST-Parameter. Folder shall have only a-z, A-Z, 0-9, _ , -. No other characters allowed.
// the jpg-image must be in the body of the POST-request.
// Provides the new WP-ID and the filename that was written to the folder

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

// Callback for GET to defined REST-Route
// Check wether folder exists
// @param $data is the complete Request data
function get_add_image_to_folder($data)
{
	$dir = wp_upload_dir()['basedir'];
	$folder = $dir . '/' . $data['folder'];
	$folder = str_replace('\\', '/', $folder);
	$folder = str_replace('\\\\', '/', $folder);
	$folder = str_replace('//', '/', $folder);

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

// Callback for POST to defined REST-Route
// Check wether folder exists. If not, create the folder and add the jpg-image from the body to media cat
// @param $data is the complete Request data
function post_add_image_to_folder($data)
{
	//URL request Parameter <namespace> / addtofolder / <foldername> / <subfoldername-if-needed> / .....
	// required https Header Param: Content-Disposition = attachment; filename=example.jpg
	// required body: the image file with identical mime-type!
	include_once ABSPATH . 'wp-admin/includes/image.php';
	$minsize   = MIN_IMAGE_SIZE;
		
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
	$standard_folder = preg_match_all('/[0-9]+\/[0-9]+/', $folder); // check if WP-standard-folder (e.g. ../2020/12)
	if ($standard_folder != false) {
		return new WP_Error('not_allowed', 'Do not add image to WP standard media directory', array( 'status' => 400 ));
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
		$title = special_replace($title);
		$newfile = $folder . '/' . $cont;
	}
	$newexists = file_exists($newfile);
	
	// add the new image if it is a jpg, png, or gif
	if ((($type == 'image/jpeg') || ($type == 'image/png') || ($type == 'image/gif')) && (strlen($image) > $minsize) && (strlen($image) < wp_max_upload_size()) && (! $newexists)) {
		$success_new_file_write = file_put_contents($newfile, $image);
		$new_file_mime = wp_check_filetype($newfile)['type'];
		$mime_type_ok = $type == $new_file_mime;
		
		if ($success_new_file_write && $mime_type_ok) {
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
		
			$attfile = $reqfolder . '/' . $cont;
			update_post_meta($upload_id, 'gallery', $reqfolder);
			update_post_meta($upload_id, '_wp_attached_file', $attfile);
			
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
			return new WP_Error('error', 'Could not write file ' . $cont, array( 'status' => 400 ));
		} elseif (! $mime_type_ok) {
			// something went // delete file
			unlink($newfile);
			return new WP_Error('error', 'Mime-Type mismatch for upload ' . $cont, array( 'status' => 400 ));
		} elseif (is_wp_error($upload_id)) {
			// something went // delete file
			unlink($newfile);
			return new WP_Error('error', 'Could not generate attachment for file ' . $cont, array( 'status' => 400 ));
		}
		// Do not check $success_resize as it could be a small png for icons or so
	} elseif ($newexists) {
		return new WP_Error('error', 'File ' . $cont . ' already exists!', array( 'status' => 400 ));
	} else {
		return new WP_Error('error', 'Other Error ', array( 'status' => 400 ));
	}
	
	return rest_ensure_response($getResp);
};

//--------------------------------------------------------------------
// REST-API Endpoint to add images from a folder different from the WP-Standard-Folder (e.g. ../uploads/2020/12) to the  WP-Media-Catalog.
// <Folder> must be provided as a REST-Parameter. Folder shall have only a-z, A-Z, 0-9, _ , -. No other characters allowed.
// Provides the new WP-IDs and the filenames that were written to the folder as a JSON-array
// if the jpg from the given folder was already added it will not be added again. But the image will be added if it is in another folder already.
// POST-Request without image in Body and content-disposition. This will be ignored even if provided.

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

// Callback for GET to defined REST-Route
// Check wether folder exists
// @param $data is the complete Request data
function get_add_image_from_folder($data)
{
	$dir = wp_upload_dir()['basedir'];
	$folder = $dir . '/' . $data['folder'];
	$folder = str_replace('\\', '/', $folder);
	$folder = str_replace('\\\\', '/', $folder);
	$folder = str_replace('//', '/', $folder);

	if (is_dir($folder)) {
		$exists = 'OK';
		$files = glob($folder . '/*');
		$files = get_added_files_from_folder($folder);
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

// Callback for POST to defined REST-Route
// Check wether folder exists. Add now images from that folder to media cat
// @param $data is the complete Request data
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
		return new WP_Error('not_allowed', 'Do not add image from WP standard media directory (again)', array( 'status' => 400 ));
	}
	if (! is_dir($folder)) {
		return new WP_Error('not_exists', 'Directory does not exist', array( 'status' => 400 ));
	}
	
	// check existing content of folder. get files that are not added to WP yet
	$files = get_files_to_add($folder);
	$id = array();
	$files_in_folder = array();
	$i = 0;

	foreach ($files as &$file) {
		// add $file to media cat
		$type = wp_check_filetype($file)['type']; //
		
		if ((($type == 'image/jpeg') || ($type == 'image/png') || ($type == 'image/gif'))) {
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
