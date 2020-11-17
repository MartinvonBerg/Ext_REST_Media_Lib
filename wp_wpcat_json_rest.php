<?php

/**
 *
 * @link              www.mvb1.de
 * @since             0.0.1
 * @package           wp_wpcat_json_rest
 *
 * @wordpress-plugin
 * Plugin Name:       wp_wpcat_json_rest
 * Plugin URI:        www.mvb1.de
 * Description:       Add basic JSON Authentification (https-only!) and extend REST-API for work with Wordpress Media-Catalogue
 * Version:           0.0.7
 * Author:            Martin von Berg
 * Author URI:        www.mvb1.de
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

defined('ABSPATH') or die('Not defined');

// ----------------- global Definitions and settings ---------------------------------
define( 'MIN_IMAGE_SIZE'  , 100);  // minimal file size in bytes to upload
define( 'MAX_IMAGE_SIZE'  , 2560); // value for resize to ...-scaled.jpg TODO: big_image_size_threshold irgendwo aus WP auslesen
define( 'RESIZE_QUALITY'  , 100);  // quality for image resizing in percent.
define( 'WPCAT_NAMESPACE' , 'wpcat/v1'); // namespace for REST-API
define( 'WPCAT_SCALED'    , 'scaled');

add_filter( 'jpeg_quality', function() { return RESIZE_QUALITY;}  );

//--------------------JSON AUTH HANDLER ------------------------------------------------
// source: https://github.com/WP-API/Basic-Auth/blob/master/basic-auth.php
// ATTENTION: Do not use together with http !!!!!! Only with https
function json_basic_auth_handler( $user ) {
	global $wp_json_basic_auth_error;

	$wp_json_basic_auth_error = null;

	// Don't authenticate twice
	if ( ! empty( $user ) ) {
		return $user;
	}

	// Check that we're trying to authenticate
	if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
		return $user;
	}

	$username = $_SERVER['PHP_AUTH_USER'];
	$password = $_SERVER['PHP_AUTH_PW'];

	/**
	 * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
	 * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
	 * recursion and a stack overflow unless the current function is removed from the determine_current_user
	 * filter during authentication.
	 */
	remove_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );

	$user = wp_authenticate( $username, $password );

	add_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );

	if ( is_wp_error( $user ) ) {
		$wp_json_basic_auth_error = $user;
		return null;
	}

	$wp_json_basic_auth_error = true;

	return $user->ID;
}
add_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );

function json_basic_auth_error( $error ) {
	// Passthrough other errors
	if ( ! empty( $error ) ) {
		return $error;
	}

	global $wp_json_basic_auth_error;

	return $wp_json_basic_auth_error;
}
add_filter( 'rest_authentication_errors', 'json_basic_auth_error' );

add_filter( 'rest_authentication_errors', function( $result ) {
    // If a previous authentication check was applied,
	// pass that result along without modification.
	// source: https://developer.wordpress.org/rest-api/frequently-asked-questions/
    if ( true === $result || is_wp_error( $result ) ) {
        return $result;
    }
 
    // No authentication has been performed yet.
    // Return an error if user is not logged in.
    if ( ! is_user_logged_in() ) {
        return new WP_Error(
            'rest_not_logged_in',
            __( 'You are not currently logged in.' ),
            array( 'status' => 401 )
        );
    }
 
    // Our custom authentication check should have no effect
    // on logged-in requests
    return $result;
});


// REST-API -------------------------------------- REST -----------------------------

//--------------------------------------------------------------------
// register custom-data 'gallery' as REST-API-Field only for attachments
function register_gallery() {
	register_rest_field(
		'attachment',
		'gallery',
		array(
			'get_callback' => 'cb_get_gallery',
			'update_callback' => 'cb_upd_gallery',
			'schema' => array(
				'description' => 'gallery-field for Lightroom',
				'type' => 'string',
				)
			)	
		);
}

function cb_get_gallery( $data ) {
	return (string) get_post_meta( $data['id'], 'gallery', true);
}

function cb_upd_gallery( $value, $post) {
	update_post_meta( $post->ID, 'gallery', $value);
	return true;
};

add_action('rest_api_init', 'register_gallery');

//--------------------------------------------------------------------
// register custom-data 'md5' as REST-API-Field only for attachments
// provides md5 sum of original-file
function register_md5_original() {
	register_rest_field(
		'attachment',
		'md5_original_file',
		array(
			'get_callback' => 'cb_get_md5',
			'schema' => array(
				'description' => 'provides md5 sum of original attachment file',
				'type' => 'string',
				)
			)	
		);
}

function cb_get_md5( $data ) {
	$original_filename = wp_get_original_image_path ( $data['id'] );
	$md5 = strtoupper ( (string) md5_file($original_filename) );
	return $md5;
}

add_action('rest_api_init', 'register_md5_original');


//--------------------------------------------------------------------
// REST-API Endpunkt für Updates von gesamten Bildern im WP-Media-Cat registrieren und definieren
add_action( 'rest_api_init', 'wpcat_register_rest_route');

function wpcat_register_rest_route() {
	# function to register the endpoint for updating an Image in the WP-Media-Catalog
	$args = array(
					'id' => array(
						'validate_callback' => function ($param, $request, $key) { 
								return is_numeric( $param );
							}
						),
					);
					
	register_rest_route(
		WPCAT_NAMESPACE,
		'update/(?P<id>[\d]+)',
		array(
			array(
				'methods'   => 'GET',
				'callback'  => 'wpcat_get_image_update',
				'args' => $args,
				'permission_callback' => '__return_true',
				),
			array(
				'methods'   => 'POST',
				'callback'  => 'wpcat_post_image_update',
				'args' => $args,
				'permission_callback' => '__return_true',
				),	
		
		)
	);
};

// Callback for GET to defined REST-Route
// Check wether Parameter id (integer!) is an WP media attachment, e.g. an image and calc md5-sum of original file
// @param $data is the complete Request data
function wpcat_get_image_update( $data ) {
	$post_id = $data['id'];
	$att = wp_attachment_is_image( $post_id);
	$resized = wp_get_attachment_image_src($post_id, 'original')[3];
		
	if ( $att and (! $resized) ) {
		$original_filename = wp_get_original_image_path($post_id);;
		$md5 = strtoupper ( (string) md5_file($original_filename) );
		$getResp = array(
			'message' => 'You requested update of original Image with ID '. $post_id . ' with GET-Method. Please update with POST-Method.',
			'original-file' => $original_filename,
			'md5_original_file' => $md5,
			'max_upload_size' => (string) wp_max_upload_size() . ' bytes'
		);
	}
	elseif ( $att and $resized) {
		$file2 = get_attached_file($post_id, true); 
		$getResp = array(
			'message' => 'Image ' . $post_id . ' is a resized image',)
			;
	}
	else {
		return new WP_Error( 'no_image', 'Invalid Image of any type: ' . $post_id, array( 'status' => 404 ) );
	};

	return rest_ensure_response( $getResp );
};

// Callback for POST to defined REST-Route
// Update attachment with Parameter id (integer!) only if it is a jpg-image
// @param $data is the complete Request data
// Important Source: https://developer.wordpress.org/reference/classes/wp_rest_request/
function wpcat_post_image_update( $data ) {
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	$minsize   = MIN_IMAGE_SIZE; 
	$post_id = $data['id'];
	$att = wp_attachment_is_image( $post_id);
	$dir = wp_upload_dir()['basedir'];
	$image = $data->get_body(); // body e.g. jpg-image as string of POST-Request
		
	if ( ($att) and (strlen($image) > $minsize) and (strlen($image) < wp_max_upload_size())) {
		// aktuelle Meta-Daten aus der WP-SQL Datenbank holen
		$meta = wp_get_attachment_metadata($post_id);
		
		// Dateinamen auf verschiedene Arten festlegen
		$file = $meta['file'];
		$file2 = get_attached_file($post_id, true); // liefert dasselbe wie bei $file
		$file3 = basename( $file );
		$file4 = str_replace('-' . WPCAT_SCALED, '', $file3);
		$file5 = str_replace('-' . WPCAT_SCALED, '', $file2); // Mit diesem Namen wird der POST-body gespeichert
		$ext = '.' . pathinfo($file5)['extension']; // Datei-Erweiterung bestimmen
		$file6 = str_replace($ext, '', $file5); // Name ohne Endungen f�r das L�schen mit Wildcard '*'
		$new = str_replace($file3,'',$file2);
		$gallerydir = str_replace($dir,'',$new);
		$gallerydir = trim($gallerydir, '/\\');

		// alte Files vorher sichern, falls das speichern nicht geht
		function filerename($file)
		{
			rename($file, $file . '.wpcatoldfile');
		}
		$filearray = glob( $file6 . '*' );
		array_walk( $filearray, 'filerename');

		// Neue Datei speichern und MIME-Types bestimmen
		$success_new_file_write = file_put_contents( $file5, $image);	
		$newfile_mime = wp_get_image_mime( $file5 );
		$attmime = get_post_mime_type( $post_id );
		$newtype = $data->get_content_type()['value']; // upload content-type of POST-Request
		
		if ( ($newfile_mime==$attmime) and ($newfile_mime==$newtype) ) {
			// resize missing images if mime-types are identical
			$success_subsizes = wp_create_image_subsizes( $file5, $post_id );
			}
		else {
			$success_subsizes = 'Mime-Type mismatch';
			}
				
		if ( ($success_new_file_write != false) and (is_array($success_subsizes))  ) { 
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
			array_map( "unlink", glob( $file6 . '*wpcatoldfile' ));
			
			}
		else {
			// something went wrong redo the change, recover the old files
			if (is_array($success_subsizes)) {
				$success_subsizes = 'Was OK';
			}
			elseif ( ! is_string($success_subsizes) ) {
				$success_subsizes = implode ($success_subsizes->get_error_messages());
			};

			$success_new_file_write = array(
				'message' => 'ERROR. Something went wrong. Original files not touched!',
				'new_file_write' => (string) $success_new_file_write,
				'gen_subsizes' => $success_subsizes,
			);

			$getResp = array(
				'message' => 'You requested update of '. $post_id . ' with POST-Method.',
				'Error_Details' => $success_new_file_write,
			);
			function recoverfile($file)
			{
				rename($file, str_replace('.wpcatoldfile', '', $file));
			}
			$filearray = glob( $file6 . '*wpcatoldfile' );
			array_walk( $filearray, 'recoverfile');
			unlink( $file5);
			return new WP_Error( 'Error', $getResp, array( 'status' => 400 ) );
		}
	}
	elseif ( ($att) and (strlen($image) < $minsize)) {
		return new WP_Error( 'too_small', 'Invalid Image (smaller than: '. $minsize .' bytes) in body for update of: ' . $post_id, array( 'status' => 400 ) );
	}
	elseif ( ($att) and (strlen($image) > wp_max_upload_size())) {
		return new WP_Error( 'too_big', 'Invalid Image (bigger than: '. wp_max_upload_size() .' bytes) in body for update of: ' . $post_id, array( 'status' => 400 ) );
	}
	elseif ( ! $att ) {
		return new WP_Error( 'not_found', 'Attachment is not an Image: ' . $post_id, array( 'status' => 415 ) );
		}
	
	return rest_ensure_response( $getResp );
};


//--------------------------------------------------------------------
// REST-API Endpunkt für Update von image_meta-Data registrieren und definieren
add_action( 'rest_api_init', 'wpcat_register_update_image_meta_route');

function wpcat_register_update_image_meta_route() {
	# function to register the endpoint for updating image_meta in the WP-Media-Catalog
	$args = array(
					'id' => array(
						'validate_callback' => function ($param, $request, $key) {
								return is_numeric( $param );
							}
						),
					);
					
	register_rest_route(
		WPCAT_NAMESPACE,
		'update_meta/(?P<id>[\d]+)',
		array(
			array(
				'methods'   => 'GET',
				'callback'  => 'wpcat_get_meta_update',
				'args' => $args,
				'permission_callback' => '__return_true',
				),
			array(
				'methods'   => 'POST',
				'callback'  => 'wpcat_post_meta_update',
				'args' => $args,
				'permission_callback' => '__return_true',
				),	
		),
	);
};

// Callback for GET to defined REST-Route
// Check wether Parameter id (integer!) is an WP media attachment
// @param $data is the complete Request data
function wpcat_get_meta_update( $data ) {
	$post_id = $data['id'];
	$att = wp_attachment_is_image( $post_id);
		
	if ( $att  ) {
		return new WP_Error( 'not_implemented', 'You requested update of meta data for Image with ID '. $post_id . ' with GET-Method. Please get image_meta with standard REST-Request.', array( 'status' => 405 ) );
	}
	else {
		return new WP_Error( 'no_image', 'Invalid Image of any type: ' . $post_id, array( 'status' => 404 ) );
	};
};

// Callback for POST to defined REST-Route
// Update image_meta of attachment with Parameter id (integer!) only if it is a jpg-image
// @param $data is the complete Request data
function wpcat_post_meta_update( $data ) {
	$post_id = $data['id'];
	$att = wp_attachment_is_image( $post_id);
	$type = $data->get_content_type()['value']; // upload content-type of POST-Request
	$newmeta = $data->get_body(); // body e.g. as JSON with new metadata as string of POST-Request
	$newmeta = json_decode($newmeta, $assoc=true); 
	
	if (( $att ) and ( $type == 'application/json') and ($newmeta != null) ) {
		// metadaten updaten
		$success = wpcat_update_metadata($post_id, $newmeta); 
	
		$getResp = array(
			'message' => 'You requested image_meta update of '. $post_id . '. Done.',
			'note' => 'NOT changed: aperture, camera, created_timestamp, focal_length, iso, shutter_speed, orientation',
			'Bytes written' => (string) $success,
		);

	}
	elseif ( ($att) and ( ($type!='application/json') or ($newmeta == null) ) ) {
		return new WP_Error( 'wrong_data', 'Invalid JSON-Data in body', array( 'status' => 400 ) );
	}
	else {
		return new WP_Error( 'no_image', 'Invalid JPG-Image: ' . $post_id, array( 'status' => 404 ) );
	};
	
	return rest_ensure_response( $getResp );
};

//--------------------------------------------------------------------
// REST-API Endpunkt für Ergänzung Bild zum WP-Media-Cat in Folder registrieren und definieren
// Folder wird als REST - Parameter übergeben
// Bei Anfrage mit image im Body wird dieses in den Folder geschrieben, liefert eine ID und Dateinamen zurück
// Im folder sind nur Buchstaben, Ziffern, Slashes und '-', '_' erlaubt. leer darf er nicht sein, wird von REST abgelehnt
add_action( 'rest_api_init', 'wpcat_register_add_image_rest_route');

function wpcat_register_add_image_rest_route() {
	# function to register the endpoint for updating an Image in the WP-Media-Catalog
	# if folder in REST-request the .../uploads-Folder is used, so empty is allowed
	$args = array(
					'folder' => array(
						'validate_callback' => function ($param, $request, $key) {
								// exemplarisch für spätere Verwendung
								return $param;
							}
						),
					);
					
	register_rest_route(
		WPCAT_NAMESPACE,
		'addtofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)', // 'addtofolder/', ==> REQ = ...addtofolder/?folder=<foldername>
		array(
			array(
				'methods'   => 'GET',
				'callback'  => 'wpcat_get_add_image_to_folder',
				'args' => $args,
				'permission_callback' => '__return_true',
				),
			array(
				'methods'   => 'POST',
				'callback'  => 'wpcat_post_add_image_to_folder',
				'args' => $args,
				'permission_callback' => '__return_true',
				),	
		)
	);
};

// Callback for GET to defined REST-Route
// Check wether folder exists
// @param $data is the complete Request data
function wpcat_get_add_image_to_folder( $data ) {
	
	$dir = wp_upload_dir()['basedir'];
	$folder = $dir . '/' . $data['folder'];
	$folder = str_replace('\\','/',$folder);
	$folder = str_replace('\\\\','/',$folder);
	$folder = str_replace('//','/',$folder);

	if (is_dir($folder)) {
		$exists = 'OK';
	}
	else {
		$exists = 'Could not find directory';
	}

	$getResp = array(
		'message' => 'You requested image addition to folder '. $folder . ' with GET-Request.' . ' Please use POST Request.',
		'exists' => $exists,
	);

	return rest_ensure_response( $getResp );
};

// Callback for POST to defined REST-Route
// Check wether folder exists. Add image zo media cat
// @param $data is the complete Request data
function wpcat_post_add_image_to_folder( $data ) {
	//URL request Parameter <namespace> / addtofolder / <foldername> / <subfoldername-if-needed> / .....
	// required https Header Param: Content-Disposition = attachment; filename=example.jpg
	// required body: the image file with identical mime-type!
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	$minsize   = MIN_IMAGE_SIZE; 
		
	// Verzeichnisnamen festlegen
	$dir = wp_upload_dir()['basedir'];
	$folder = $dir . '/' . $data['folder'];
	$folder = str_replace('\\','/',$folder);
	$folder = str_replace('\\\\','/',$folder);
	$folder = str_replace('//','/',$folder);
	$reqfolder = $data['folder'];
	$reqfolder = str_replace('\\','/',$reqfolder);
	$reqfolder = str_replace('\\\\','/',$reqfolder);
	$reqfolder = str_replace('//','/',$reqfolder);
	
	// Verzeichnis prüfen und ggf erzeugen
	$wp_cat_folder = preg_match_all('/[0-9]+\/[0-9]+/', $folder); // prüfe ob WP Standardverzeichnis
	if ($wp_cat_folder != false) {
		return new WP_Error( 'not_allowed', 'Do not add image to WP standard media directory', array( 'status' => 400 ) );
	}
	if ( ! is_dir($folder)) {
		wp_mkdir_p($folder);	
	}
	
	//body und Header Content-Disposition prüfen
	$type = $data->get_content_type()['value']; // upload content-type of POST-Request
	$image = $data->get_body(); // body e.g. jpg-image as string of POST-Request
	$cont =$data->get_header('Content-Disposition');
	$newfile = ''; 
	
	// Namen festlegen
	if ( ! empty($cont) ) {
		$cont = explode(';', $cont)[1];
		$cont = explode('=', $cont)[1];
		$ext = pathinfo($cont)['extension'];
		$title = basename($cont, '.' . $ext);
		$title = wpcat_replace( $title);
		$newfile = $folder . '/' . $cont;
		}
	$newexists = file_exists($newfile);
	
	// eine neues Bild soll ergänzt werden, wenn Bedingungen erfüllt
	if ( (($type == 'image/jpeg') or ($type == 'image/png') or ($type == 'image/gif')) and (strlen($image) > $minsize) and (strlen($image) < wp_max_upload_size()) and ( ! $newexists )) {
		$success_new_file_write = file_put_contents( $newfile, $image);	
		$new_file_mime = wp_check_filetype( $newfile )['type'];
		$mime_type_ok = $type == $new_file_mime;
		
		if ($success_new_file_write and $mime_type_ok) { 
			$att_array = array(
				'guid'           => $newfile, // nur so geht das
				'post_mime_type' => $new_file_mime, // 'image/jpg'
				'post_title'     => $title, // Daraus entsteht der Titel, und der permalink, wenn post_name nicht gesetzt
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_name' => '' , // Das ergibt den Permalink :  http://127.0.0.1/wordpress/title-88/, wenn leer aus post_title generiert
			); 
			
			$upload_id = wp_insert_attachment( $att_array, $newfile );
			$success_subsizes = wp_create_image_subsizes( $newfile, $upload_id );
			//$success_resize = wpcat_resize($newfile, $threshold); // hier muss -scaled erzeugt werden!
			//wp_generate_attachment_metadata( $upload_id, $newfile); // -scaled wird auch hier nicht erzeugt , gallery, alt, caption, description sind leer
			//$newmeta = wp_read_image_metadata($newfile);
			//wpcat_update_metadata($upload_id, $newmeta); // neue Meta-daten in die WP SQL-Datenbank schreiben

			update_post_meta( $upload_id, 'gallery', $reqfolder);
			
			$getResp = array(
				'id' => $upload_id,
				'message' => 'You requested image addition to folder '. $folder . ' with POST-Request. Done.',
				'new_file_name' => $cont,
				'gallery' => $reqfolder,
				'Bytes written' => $success_new_file_write,
			);
		}
		elseif ( ! $success_new_file_write) {
			// something went wrong // delete file 
			unlink($newfile);
			return new WP_Error( 'error', 'Could not write file ' . $cont, array( 'status' => 400 ) );
		}
		elseif ( ! $mime_type_ok) {
			// something went // delete file 
			unlink($newfile);
			return new WP_Error( 'error', 'Mime-Type mismatch for upload ' . $cont, array( 'status' => 400 ) );
		}
		elseif ( is_wp_error($upload_id)) {
			// something went // delete file 
			unlink($newfile);
			return new WP_Error( 'error', 'Could not generate attachment for file ' . $cont, array( 'status' => 400 ) );
		}
		// Do not check $success_resize as it could be a small png for icons or so
	}
	elseif ($newexists){
		return new WP_Error( 'error', 'File ' . $cont . ' already exists!', array( 'status' => 400 ) );
	}
	else {
		return new WP_Error( 'error', 'Other Error ', array( 'status' => 400 ) );
		}
	
	return rest_ensure_response( $getResp );
};

//--------------------------------------------------------------------
// REST-API Endpunkt für Ergänzung Bild zum WP-Media-Cat aus Folder registrieren und definieren
// Folder wird als REST - Parameter übergeben
// Anfrage OHNE image im Body und content-disposition. Diese werden ignoriert, auch wenn vorhanden.
// Es werden alle Bilder aus dem Folder zum WPCat ergänzt, falls aus diesem Folder noch nicht enthalten. liefert JSON-array mit IDs und Dateinamen zurück
// Wenn dieselbe Datei in einem anderen Ordner schon existiert, wird das jpg nochmals ergänzt!
// Im folder sind nur Buchstaben, Ziffern, Slashes und '-', '_' erlaubt. leer darf er nicht sein, wird von REST abgelehnt
add_action( 'rest_api_init', 'wpcat_register_add_folder_rest_route');

function wpcat_register_add_folder_rest_route() {
	# function to register the endpoint for updating an Image in the WP-Media-Catalog
	# if folder in REST-request the .../uploads-Folder is used, so empty is allowed
	$args = array(
					'folder' => array(
						'validate_callback' => function ($param, $request, $key) {
								// exemplarisch für spätere Verwendung
								return $param;
							}
						),
					);
					
	register_rest_route(
		WPCAT_NAMESPACE,
		'addfromfolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)', 
		array(
			array(
				'methods'   => 'GET',
				'callback'  => 'wpcat_get_add_image_from_folder',
				'args' => $args,
				'permission_callback' => '__return_true',
				),
			array(
				'methods'   => 'POST',
				'callback'  => 'wpcat_post_add_image_from_folder',
				'args' => $args,
				'permission_callback' => '__return_true',
				),	
		)
	);
};

// Callback for GET to defined REST-Route
// Check wether folder exists
// @param $data is the complete Request data
function wpcat_get_add_image_from_folder( $data ) {
	$dir = wp_upload_dir()['basedir'];
	$folder = $dir . '/' . $data['folder'];
	$folder = str_replace('\\','/',$folder);
	$folder = str_replace('\\\\','/',$folder);
	$folder = str_replace('//','/',$folder);

	if (is_dir($folder)) {
		$exists = 'OK';
		$files = glob( $folder . '/*' );
		$files = json_encode($files, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES);
	}
	else {
		$exists = 'Could not find directory';
		$files = '';
	}

	$getResp = array(
		'message' => 'You requested image addition from folder '. $folder . ' with GET-Request.' . ' Please use POST Request.',
		'exists' => $exists,
		'files' => $files,
	);

	return rest_ensure_response( $getResp );
};

// Callback for POST to defined REST-Route
// Check wether folder exists. Add now images from that folder to media cat
// @param $data is the complete Request data
function wpcat_post_add_image_from_folder( $data ) {
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	$threshold = MAX_IMAGE_SIZE;
	
	// Verzeichnisnamen festlegen
	$dir = wp_upload_dir()['basedir'];
	$folder = $dir . '/' . $data['folder'];
	$folder = str_replace('\\','/',$folder);
	$folder = str_replace('\\\\','/',$folder);
	$folder = str_replace('//','/',$folder);
	$reqfolder = $data['folder'];
	$reqfolder = str_replace('\\','/',$reqfolder);
	$reqfolder = str_replace('\\\\','/',$reqfolder);
	$reqfolder = str_replace('//','/',$reqfolder);
	
	// Verzeichnis prüfen und ggf erzeugen
	$wp_cat_folder = preg_match_all('/[0-9]+\/[0-9]+/', $folder); // prüfe ob WP Standardverzeichnis
	if ($wp_cat_folder != false) {
		return new WP_Error( 'not_allowed', 'Do not add image from WP standard media directory (again)', array( 'status' => 400 ) );
	}
	if ( ! is_dir($folder)) {
		return new WP_Error( 'not_exists', 'Directory does not exist', array( 'status' => 400 ) );	
	}
	
	// Inhalt Verzeichnis prüfen, nur dann wenn BODY NICHT leer ist
	$files = wpcat_get_files_to_add ($folder);
	$id = array();
	$files_in_folder = array();
	$i = 0;

	foreach ($files as &$file) {
		// add $file to media cat
		$type = wp_check_filetype($file)['type']; //
		
		if ( (($type == 'image/jpeg') or ($type == 'image/png') or ($type == 'image/gif')) ) {
			$newfile = $file; 
			$new_file_mime = $type;
			$ext = pathinfo($newfile)['extension'];
			$title = basename($newfile, '.' . $ext);
	
			$att_array = array(
				'guid'           => $newfile, // nur so geht das
				'post_mime_type' => $new_file_mime, // 'image/jpg'
				'post_title'     => $title, // Daraus entsteht der Titel, und der permalink, wenn post_name nicht gesetzt
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_name' => '' , // Das ergibt den Permalink :  http://127.0.0.1/wordpress/title-88/, wenn leer aus post_title generiert
			); 

			$upload_id = wp_insert_attachment( $att_array, $newfile );
			$success_subsizes = wp_create_image_subsizes( $newfile, $upload_id );
			//$success_resize = wpcat_resize($newfile, $threshold); // hier muss -scaled erzeugt werden!
			//wp_generate_attachment_metadata( $upload_id, $newfile); // -scaled wird auch hier nicht erzeugt , gallery, alt, caption, description sind leer
			//$newmeta = wp_read_image_metadata($newfile);
			//wpcat_update_metadata($upload_id, $newmeta); // neue Meta-daten in die WP SQL-Datenbank schreiben
			// TODO: in post_meta muss _wp_attached_file richtig gesetzt werden : folder/filename, sonst geht später die Suche nicht
			//update_post_meta( $upload_id, '_wp_attached_file', $reqfolder . '/'. $newfile );
			$newfile = str_replace('.' . $ext, '-' . WPCAT_SCALED . '.' . $ext, $newfile);  
			if (file_exists($newfile)) {
				$attfile = $reqfolder . '/'. $title . '-' . WPCAT_SCALED   . '.' . $ext;
				 }
			else {
				$attfile = $reqfolder . '/'. $title . '.' . $ext;
			}

			update_post_meta( $upload_id, '_wp_attached_file', $attfile );
		
			update_post_meta( $upload_id, 'gallery', $reqfolder);
		
			if ( is_wp_error($upload_id))  {
				// something went wrong with this single file
				$upload_id = '';
			}
			else {
				// Array für Ergebnis schreiben
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
		
	return rest_ensure_response( $getResp );
};

//---------------- helper functions ----------------------------------------------------

/** 
	* select only the original files that were NOT added yet to WP-Cat from THIS $folder, not from all folders
	*
	* @return array the original-files in the given $folder that were NOT added to WP-Cat yet 
	*/
function wpcat_get_files_to_add( $folder ) {
	$result = array();
	$all = glob( $folder . '/*' );
	$i=0;
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];
	$dir = str_replace('\\','/',$dir);
	$dir = str_replace('\\\\','/',$dir);
	$dir = str_replace('//','/',$dir);
	$url = $upload_dir['baseurl'];
	
	foreach ($all as &$file) {
		$test=$file;
		if ( (! preg_match_all('/[0-9]+x[0-9]+/',$test)) and (! strstr($test, '-'. WPCAT_SCALED)) and (! is_dir($test)) ) {
			// Check if one of the files in $result was already attached to WPCat
			$file = str_replace($dir,$url,$file);
			$addedbefore = attachment_url_to_postid($file);
			
			if ( empty($addedbefore) ) {
				$ext = '.' . pathinfo($file)['extension'];
				$file = str_replace($ext,'-' . WPCAT_SCALED . $ext, $file);
				$addedbefore = attachment_url_to_postid($file);
			}
			
			if ( empty($addedbefore) ) {
				$result[$i] = $test;
				$i = $i +1;
			}
			
		}
	}
	return $result;
}

/** 
	* special replace for foldernames $string '_ . ? * \ / space' to '-'
	*
	* @return string the string with replacments
	*/
function wpcat_replace( $string )
{
	$result = str_replace('_','-',$string);
	$result = str_replace('.','-',$result);
	$result = str_replace('?','-',$result);
	$result = str_replace('*','-',$result);
	$result = str_replace('\\','-',$result);
	$result = str_replace('/','-',$result);
	$result = str_replace('\s','-',$result);
	return $result;
}

// function to update meta-data given by $post_ID (int) and new metadata (array)
/**
	 * update image_meta (only keywords, credit, copyright, caption, title)
	 *
	 * @param int $post_id ID of the attachment in the WP-Mediacatalog
	 *
	 * @param array $newmeta array with newmeta data taken from the JSON-data in the POST-Request body
	 * 
	 * @return bool true if success, false if not: ouput of the WP function to update attachment metadata
	 */
function wpcat_update_metadata($post_id, $newmeta) {
	/** so muss das JSON aussehen
	{
		"image_meta": {
					"credit": "Martin von Berg",
					"caption": "TEst-caption",
					"copyright": "Copyright by Martin von Berg",
					"title": "Auffahrt zum Vallone d`Urtier",
					"keywords": [
						"Aosta",
						"Aostatal",
						"Berge",
						"Bike",
						"Italien",
						"Sommer",
						"Wald",
						"Wiese",
						"forest",
						"italy",
						"lärche",
						"meadow",
						"mountains",
						"summer"
					]
				}
		}
	*/	
	// aktuelle Meta-Daten aus der WP-SQL Datenbank holen und prüfen
	$meta = wp_get_attachment_metadata($post_id); 
	if (array_key_exists('image_meta', $newmeta) ) {
		$newmeta = $newmeta['image_meta'];
		
		// metadaten zuweisen
		array_key_exists('keywords', $newmeta)  ? $meta["image_meta"]["keywords"]  = $newmeta['keywords'] : '' ; // Keywords kopieren! GPS fehlt! Wird aber in WP nicht genutzt
		array_key_exists('credit', $newmeta)    ? $meta["image_meta"]["credit"]    = $newmeta["credit"] : ''     ;		// GPS steht doch in der Datei! Wird also automatisch ersetzt!
		array_key_exists('copyright', $newmeta) ? $meta["image_meta"]["copyright"] = $newmeta["copyright"] : '' ;
		array_key_exists('caption', $newmeta)   ? $meta["image_meta"]["caption"]   = $newmeta["caption"] : ''   ;
		array_key_exists('title', $newmeta)     ? $meta["image_meta"]["title"]     = $newmeta["title"]  : ''     ;
	}

	// metadaten schreiben
	$success = wp_update_attachment_metadata($post_id, $meta); // neue Meta-daten in die WP SQL-Datenbank schreiben
	
	return $success;
	}