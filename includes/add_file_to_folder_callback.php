<?php
namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

/**
 * Callback for GET to REST-Route 'addtofolder/<folder>'. Check wether folder exists and provide message if so
 * 
 * @param object $data is the complete Request data of the REST-api GET
 * @return \WP_REST_Response|\WP_Error REST-response data for the folder if it exists
 */
function get_add_file_to_folder( $data )
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
		'message' => 'You requested image FILE addition to folder '. $folder . ' with GET-Request. Please use POST Request.',
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
function post_add_file_to_folder($data)
{
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
	
	// check and create folder. Also use WP-standard-folder in media-cat
	#$standard_folder = preg_match_all('/[0-9]+\/[0-9]+/', $folder); // check if WP-standard-folder (e.g. ../2020/12)
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
		$newfile = $folder . '/' . $cont; // TODO : sanitize this? htmlspecialchars did not work
		// update post doesn't update GUID on updates. guid has to be the full url to the file
		$url_to_new_file = get_upload_url() . '/' . $reqfolder . '/' . $cont; // TBD : sanitize this? htmlspecialchars did not work
	} else {
		return new \WP_Error('error', 'Content-Disposition is missing', array( 'status' => 400 ));
	}
	if (empty($image)) {
		return new \WP_Error('error', 'File in Body is empty or missing', array( 'status' => 400 ));
	}
	// check if file exists
	$newexists = file_exists($newfile) && !array_key_exists("overwrite", $_GET);
	
	// add the new image if it is a jpg, png, gif, webp or avif
	if ( ( ( 'image/jpeg' == $type ) || ( 'image/png' == $type ) || ( 'image/gif' == $type ) || ( 'image/webp' == $type ) || ( 'image/avif' == $type ) ) && (strlen($image) > $minsize) && (strlen($image) < wp_max_upload_size()) && (! $newexists) && $url_to_new_file !== '') {
		$success_new_file_write = file_put_contents($newfile, $image);
		$new_file_mime = wp_check_filetype($newfile)['type'];
		$mime_type_ok = $type == $new_file_mime;
		
		if ($success_new_file_write && $mime_type_ok) {
			// everything ok
			$getResp = array(
				'message' => 'You requested image File addition to folder '. $folder . ' with POST-Request. Done.',
				'new_file_name' => $cont,
				'gallery' => $reqfolder,
				'Bytes written' => $success_new_file_write,
			);

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