<?php
namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

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
 * @return object WP_REST_Response|WP_Error REST-response data for the folder if it exists of Error message
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
		wp_mkdir_p($folder); // TBD : sanitize this? htmlspecialchars did not work
	}
	
	// check body und Header Content-Disposition
	if ( $data->get_content_type() !== null && array_key_exists('value', $data->get_content_type()) ) {
		$type = $data->get_content_type()['value']; // upload content-type of POST-Request 
	} else { 
		$type = ''; 
	}
	 
	$image = $data->get_body(); 
	$cont = $data->get_header('Content-Disposition');
	$newfile = '';
	$url_to_new_file = '';
	$title = '';
	$ext = '';
	
	// define filename
	if (! empty($cont) ) {
		$cont = explode(';', $cont)[1];
		$cont = explode('=', $cont)[1]; // TBD : sanitize this? htmlspecialchars did not work
		$ext = pathinfo($cont)['extension'];
		$title = basename($cont, '.' . $ext);
		$searchinstring = ['\\', '\s', '/'];
		$title = str_replace($searchinstring, '-', $title);
		$newfile = $folder . '/' . $cont; // TBD : sanitize this? htmlspecialchars did not work
		// update post doesn't update GUID on updates. guid has to be the full url to the file
		$url_to_new_file = get_upload_url() . '/' . $reqfolder . '/' . $cont; // TBD : sanitize this? htmlspecialchars did not work
	}
	$newexists = file_exists($newfile);
	
	// add the new image if it is a jpg, png, or gif
	if ( ( ( 'image/jpeg' == $type ) || ( 'image/png' == $type ) || ( 'image/gif' == $type ) || ( 'image/webp' == $type ) ) && (strlen($image) > $minsize) && (strlen($image) < wp_max_upload_size()) && (! $newexists) && $url_to_new_file !== '') {
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

			if (is_wp_error( $upload_id )) {
				// something went wrong: delete file and return
				unlink($newfile);
				return new \WP_Error('error', 'Could not generate attachment for file ' . $cont, array( 'status' => 400 ));
			}

			$success_subsizes = wp_create_image_subsizes( $newfile, $upload_id ) ;
			
			if ( \strpos( $success_subsizes["file"], EXT_SCALED) != \false ) 
				$correct_new_filename = str_replace( '.' . $ext, '-'. EXT_SCALED . '.' . $ext, $cont);
			else
				$correct_new_filename = $cont;

			$attfile = $reqfolder . '/' . $correct_new_filename; 
			
			update_post_meta($upload_id, 'gallery', $reqfolder);
			update_post_meta($upload_id, '_wp_attached_file', $attfile); // korrigiert den Dateinamen

			// update post doesn't update GUID on updates. guid has to be the full url to the file
			$wpdb->update( $wpdb->posts, array( 'guid' =>  $url_to_new_file ), array('ID' => $upload_id) );
			
			$getResp = array(
				'id' => $upload_id,
				'message' => 'You requested image addition to folder '. $folder . ' with POST-Request. Done.',
				'new_file_name' => $cont,
				'gallery' => $reqfolder,
				'Bytes written' => $success_new_file_write,
			);
			// do_action after successful upload
			//global $wp_filter; $a = serialize($wp_filter[ 'wp_rest_mediacat_upload' ]->callbacks); error_log( $a );
			\do_action( 'wp_rest_mediacat_upload', $upload_id, 'context-rest-upload');

		} elseif (! $success_new_file_write) {
			// something went wrong // delete file
			unlink($newfile);
			return new \WP_Error('error', 'Could not write file ' . $cont, array( 'status' => 400 ));
		} else {
			// something went wrong // delete file
			unlink($newfile);
			return new \WP_Error('error', 'Mime-Type mismatch for upload ' . $cont, array( 'status' => 400 ));
		} 
	
	} elseif ($newexists) {
		return new \WP_Error('error', 'File ' . $cont . ' already exists!', array( 'status' => 400 ));
	} else {
		return new \WP_Error('error', 'Other Error ', array( 'status' => 400 ));
	}
	
	return rest_ensure_response($getResp);
};