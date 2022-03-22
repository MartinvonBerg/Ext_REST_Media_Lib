<?php
namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

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