<?php
namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

/**
 * Callback for GET to REST-Route 'update/<id>'. Check wether Parameter id (integer!) is an WP media attachment, e.g. an image and calc md5-sum of original file
 *
 * @param object $data is the complete Request data of the REST-api GET
 * @return object WP_REST_Response|WP_Error array for the rest response body or a WP Error object
 */
function get_image_update( object $data ) : object
{
	$post_id = $data['id'];
	$isAttachment = wp_attachment_is_image($post_id);
	$resized = wp_get_attachment_image_src($post_id, 'original');

	if ( \is_array( $resized ) )
		$resized = $resized[3];
		
	if ($isAttachment && (! $resized)) {
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
	} elseif ($isAttachment) {
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
 * Callback for POST to REST-Route 'update/<id>'. Update attachment with Parameter id (WP image ID, integer!). 
 * This function updates only the image FILE and the filename if provided. If the old title of the 
 * image was different from the filename this title will be kept. The mime-type may be change by the user under the same WP-ID.
 * Additionally the links in posts to the image will be updated with the new filename and the new url. 
 * The metadata of the attachment will NOT be updated, except the filename in _wp_attached_file and the sizes in metadata if subsizes were generated.
 * TBD: Mind the Metadata (title, excerpt, content, image_alt, keywords) will be updated by function 'trigger_after_image_upload' (if enabled in settings) automatically.
 *
 * @param object $data is the complete Request data of the REST-api GET
 * @return object WP_REST_Response|WP_Error array for the rest response body or a WP Error object
 */
function post_image_update( object $data ) : object
{
	global $wpdb;

	include_once ABSPATH . 'wp-admin/includes/image.php';
	$minsize   = MIN_IMAGE_SIZE;
	$post_id = $data['id'];
	$isAttachment = wp_attachment_is_image($post_id);
	$dir = wp_upload_dir()['basedir'];
	$image = $data->get_body(); // body as string (=jpg/webp-image) of POST-Request
	$image_size = \is_string( $image ) ? \strlen( $image ) : 0;
	$request_params = $data->get_params();
	$postRequestFileNameRaw = (string) extract_filename_from_content_disposition( $data->get_headers()['content_disposition'][0] ?? '' );
	$postRequestFileName = '';
	if ( $postRequestFileNameRaw !== '' ) {
		$requestedFileName = wp_basename( $postRequestFileNameRaw );
		if ( $requestedFileName !== $postRequestFileNameRaw || strpos( $postRequestFileNameRaw, '..' ) !== false || strpos( $postRequestFileNameRaw, '/' ) !== false || strpos( $postRequestFileNameRaw, '\\' ) !== false ) {
			return new \WP_Error( 'invalid_filename', 'Invalid filename for upload update: ' . $postRequestFileNameRaw, array( 'status' => 400 ));
		}
		$postRequestFileName = sanitize_file_name( $requestedFileName );
		if ( $postRequestFileName === '' ) {
			return new \WP_Error( 'invalid_filename', 'Filename for upload update is empty after sanitization.', array( 'status' => 400 ));
		}
	}
			
	if ( ($isAttachment) && ( $image_size > $minsize) && ( $image_size < wp_max_upload_size()) ) {
		
		// get current metadata from WP-Database
		$meta = wp_get_attachment_metadata($post_id);
		$wpmediadata = get_post( $post_id, 'ARRAY_A');
		if ( ! \is_array( $meta ) ) { $meta = []; }
		if ( ! \is_array( $wpmediadata ) ) {
			return new \WP_Error( 'invalid_attachment', 'Attachment post data missing for ID: ' . $post_id, array( 'status' => 500 ));
		}
		$meta_file = $meta['file'] ?? '';
		if ( ! \is_string( $meta_file ) || $meta_file === '' ) {
			$meta_file = get_attached_file( $post_id, true );
			if ( ! \is_string( $meta_file ) || $meta_file === '' ) {
				return new \WP_Error( 'invalid_metadata', 'Attachment file metadata missing for ID: ' . $post_id, array( 'status' => 500 ));
			}
		}
		$oldlink = get_attachment_link( $post_id ); // identical to old permalink
		
		// Define filenames in different ways for the different functions
		$dir = \str_replace('\\', '/', $dir);
		$fileName_from_att_meta = \str_replace('\\', '/', $meta['file']);
		$checker = \str_replace($dir, '', $fileName_from_att_meta);
		if ( ( $checker[0] ?? '' ) != '/' ) {
			$fileName_from_att_meta = $dir . '/' . $checker;
		}
		$old_original_fileName = str_replace( '-' . EXT_SCALED, '', $fileName_from_att_meta); // This is used to save the POST-body
		$old_extension = (string) pathinfo( $old_original_fileName, \PATHINFO_EXTENSION );
		if ( $old_extension === '' ) {
			return new \WP_Error( 'invalid_metadata', 'Attachment filename has no extension for ID: ' . $post_id, array( 'status' => 500 ));
		}
		$ext = '.' . \strtolower( $old_extension ); // Get the extension
		$old_original_fileName = set_complete_path($dir, $old_original_fileName);
		$filename_for_deletion = str_replace($ext, '', $old_original_fileName); // Filename without extension for the deletion with Wildcard '*'
		$restore_backup_files = static function( string $filenameForDeletion ) : void {
			$filearray = glob($filenameForDeletion . '*oldimagefile');
			array_walk( $filearray, function( $fileName_from_att_meta ) {
				rename( $fileName_from_att_meta, str_replace('.oldimagefile', '', $fileName_from_att_meta ) );
			} );
		};
		$delete_backup_files = static function( string $filenameForDeletion ) : void {
			array_map("unlink", glob($filenameForDeletion . '*oldimagefile'));
		};
		
		// data for the REST-response
		$base_fileName_from_att_meta = basename($fileName_from_att_meta); // filename with extension with '-scaled'
		$original_filename_old_file = str_replace('-' . EXT_SCALED, '', $base_fileName_from_att_meta);
		$old_upload_dir = str_replace( $base_fileName_from_att_meta, '', $fileName_from_att_meta ); // This is the upload dir that was used for the original file $old_upload_dir = str_replace( $original_filename_old_file, '', $old_attached_file );
		$gallerydir = str_replace($dir, '', $old_upload_dir);
		$gallerydir = trim($gallerydir, '/\\');

		// get parent
		$oldParent = \wp_get_post_parent_id( $post_id);

		// init the replacer here with the OLD metadata before any update happens.
		$replacer = new \mvbplugins\extmedialib\Replacer( $post_id );
		
		// generate the filename for the new file and set title.
		if ( $postRequestFileName === '' ) {
			// if provided filename is empty : use the old filename
			$path_to_new_file = set_complete_path($dir, $old_original_fileName);
			// keep the old title in case no filename is given; fallback to filename when title is empty.
			$new_post_title = (string) ( $wpmediadata["post_title"] ?? '' );
			if ( $new_post_title === '' ) {
				$new_post_title = pathinfo( $old_original_fileName, \PATHINFO_FILENAME );
			}
			
		} else {
			// generate the complete path for the new uploaded file
			$upload_target_dir = \wp_normalize_path( trailingslashit( $dir . '/' . $gallerydir ) );
			$path_to_new_file = $upload_target_dir . $postRequestFileName;
			// keep the old title if the old title is identical to the old filename, otherwise keep the old title. This is for example important for users who use the filename as title and want to update the image with a new filename. If the title was different from the filename before, it will be kept, because it is not clear if the user wants to change it or not.
			if ( $wpmediadata["post_title"] === $base_fileName_from_att_meta || $wpmediadata["post_title"] === '' ) {
				$new_post_title = pathinfo( $postRequestFileName )['filename'];
			} else {
				$new_post_title = $wpmediadata["post_title"];
			} 
		}
		$path_to_new_file = \wp_normalize_path( $path_to_new_file );
		$upload_target_dir = \wp_normalize_path( trailingslashit( $dir . '/' . $gallerydir ) );
		if ( strpos( $path_to_new_file, $upload_target_dir ) !== 0 ) {
			return new \WP_Error( 'invalid_path', 'Target path is outside upload directory.', array( 'status' => 400 ));
		}

		// check if file exists alreay, don't overwrite
		$fileexists = \is_file( $path_to_new_file );
		$is_same_target_as_current = \wp_normalize_path( $path_to_new_file ) === \wp_normalize_path( $old_original_fileName );
		if ( $fileexists && ! $is_same_target_as_current ) {
			$old_attached_file_before_update = get_attached_file($post_id, true);
			$getResp = array(
				'message' => __('You requested upload of file') . ' '. $postRequestFileName . ' ' . __('with POST-Method'),
				'Error_Details' => __('Path') . ': ' . $path_to_new_file,
				'file6' => 'filebase for rename: ' . $filename_for_deletion,
				'old' => 'old attach: ' . $old_attached_file_before_update,
				'dir' => 'Variable $dir: ' . $dir,
			);

			$newGetResp = \implode(' , ', $getResp);
			
			return new \WP_Error( __('File exists'), $newGetResp, array( 'status' => 409 ));
		}

		// Save new file from POST-body to a temp file first and validate before touching originals.
		$temp_upload_file = \wp_normalize_path( trailingslashit( dirname( $path_to_new_file ) ) . '.wpcat-tmp-' . \wp_generate_password( 12, false, false ) . '-' . basename( $path_to_new_file ) );
		$success_new_file_write = file_put_contents( $temp_upload_file, $image );
		if ( $success_new_file_write === false ) {
			return new \WP_Error( 'write_error', 'Could not write uploaded image data for ID: ' . $post_id, array( 'status' => 500 ));
		}
		
		// check the new file type and extension
		$changemime = \array_key_exists('changemime', $request_params ) && $request_params['changemime'] === 'true';

		// check mime-type from header. The mime-type in header is required, otherwise upload will fail.
		$content_type = $data->get_content_type();
		$new_mime_from_header = \is_array( $content_type ) ? (string) ( $content_type['value'] ?? '' ) : ''; // upload content-type of POST-Request

		$newfile_mime = wp_get_image_mime( $temp_upload_file );
		$new_File_Extension = (string) pathinfo( $path_to_new_file, \PATHINFO_EXTENSION );
		$wp_allowed_mimes = \get_allowed_mime_types();
		$wp_allowed_ext = array_search( $newfile_mime, $wp_allowed_mimes, false);
		$new_ext_matches_mime = \is_string( $wp_allowed_ext ) && $new_File_Extension !== '' && stripos( $wp_allowed_ext, $new_File_Extension) !== false;
		$new_File_Extension = '.' . \strtolower( $new_File_Extension );
		$new_File_Name  = pathinfo( $path_to_new_file )['filename'];

		// mime type of the old attachment
		$old_mime_from_att = get_post_mime_type( $post_id ) ;

		if ( !$changemime ) {
			$all_mime_ext_OK = ($newfile_mime == $old_mime_from_att) && ($newfile_mime == $new_mime_from_header) && ($ext == $new_File_Extension) && $new_ext_matches_mime;
		} else {
			$all_mime_ext_OK = ($newfile_mime == $new_mime_from_header) && $new_ext_matches_mime;
		}
		
		if ( $all_mime_ext_OK ) {
			// save old Files before replacing the attachment file so we can recover on failure. 
			$filearray = glob($filename_for_deletion . '*') ?: [];

			$filearray = array_filter(
				$filearray,
				static function( $candidate ) use ( $temp_upload_file ) {
					$candidatePath = \wp_normalize_path( (string) $candidate );
					return $candidatePath !== \wp_normalize_path( $temp_upload_file ) && substr( $candidatePath, -13 ) !== '.oldimagefile';
				}
			);
			array_walk($filearray, function( $fileName_from_att_meta ){
				rename($fileName_from_att_meta, $fileName_from_att_meta . '.oldimagefile');
			} );
			if ( ! rename( $temp_upload_file, $path_to_new_file ) ) {
				$restore_backup_files( $filename_for_deletion );
				@unlink( $temp_upload_file );
				return new \WP_Error( 'write_error', 'Could not move uploaded image into place for ID: ' . $post_id, array( 'status' => 500 ));
			}

			$datetime = current_time('mysql');
			
			// resize missing images
			$att_array = array(
				'ID'			 => $post_id,
				 //'guid'           => $path_to_new_file, // works only this way -- use a relative path to ... /uploads/ - folder
				'guid'			 => $gallerydir . '/' . $new_File_Name . $new_File_Extension,
				'post_mime_type' => $newfile_mime, // e.g.: 'image/jpg'
				'post_title'     => $new_post_title, // this creates the title and the permalink, if post_name is empty
				'post_content'   => $wpmediadata["post_content"],
				'post_excerpt'   => $wpmediadata["post_excerpt"],
				'post_status'    => 'inherit',
				'post_parent'	 => $oldParent, // int
				'post_name' 	 => '' , // this is used for Permalink :  https://example.com/title-88/, (if empty post_title is used)
				'post_date_gmt'		 => $wpmediadata['post_date_gmt'],
				'post_modified_gmt' => get_gmt_from_date( $datetime ),
			);
			
			// update the attachment = image with standard methods of WP
			wp_insert_attachment( $att_array, $gallerydir . '/' . $new_File_Name . $new_File_Extension, (int)$oldParent, true, false ); // Dieser ändert den slug und den permalink
			// calls filter 'intermediate_image_sizes_advanced' at the end of this function and then _wp_make_subsizes()
			// the filter calls image_subsizes_filter() from this plugin in handle_subsizes_in_db.php which itself stores the subsizes in a cache if they were uploaded before.
			$success_subsizes = wp_create_image_subsizes( $path_to_new_file, $post_id ); // nach dieser Funktion ist der Dateiname falsch! Nur dann wenn größer als big-image-size!
			
				// write data for description->rendered, full->file (only basename . ext is used) full->source_url, source_url,
				// use path relative to upload path without trailing-slash and -scaled if image is scaled. read this from $success_subsizes
				// only guid-rendered and orginal_image are set with full filename
				$new_basename = pathinfo( $success_subsizes['file'])['basename'];
				$attached_rel = $gallerydir . '/' . ltrim( $new_basename, '/\\' );
				\update_metadata( 'post', $post_id, '_wp_attached_file', $attached_rel, $prev_value = '' );				
				
				// update metadata doesn't update GUID on updates. guid has to be the full url to the file
				$attached_rel = get_post_meta( $post_id, '_wp_attached_file', true );
				$url_to_new_file = get_upload_url() . '/' . ltrim( $attached_rel, '/\\' );
				$wpdb->update( $wpdb->posts, array( 'guid' =>  $url_to_new_file ), array('ID' => $post_id) );

				// update the meta_data
				$newmeta = wp_get_attachment_metadata( $post_id );
				if ( $newmeta !== false ) {
					$newmeta['sizes'] = $success_subsizes['sizes'];
					wp_update_attachment_metadata( $post_id, $newmeta );
				}
				
				// Update the posts that use the image with class from plugin enable_media_replace
				// This updates only the image url that are used in the post. The metadata e.g. caption is NOT updated.
				// call the media replacer class with construct-method of the class to get basic information about the attachment-image
				$replacer->new_location_dir = $gallerydir;
				$replacer->set_oldlink( $oldlink );
				$newlink = get_attachment_link( $post_id ); // get_attachment_link( $post_id) ist hier bereits aktualisiert
				$replacer->set_newlink( $newlink );
				$replacer->target_url = $url_to_new_file;
				$replacer->target_metadata = $success_subsizes;
				$replacer->API_doSearchReplace();
				$replacer = null;
			
		} else {
			@unlink( $temp_upload_file );
			$success_subsizes = 'Check-Mime-Type mismatch';
		}
				
		if ( ($success_new_file_write != false) &&  \is_array($success_subsizes )) {
			$getResp = array(
				'id' => $post_id,
				'message' => __('Successful update. Except Metadata.'),
				'old_filename' => $original_filename_old_file,
				'new_fullpath' => $path_to_new_file,
				'gallery' => $gallerydir,
				'Bytes written' => $success_new_file_write,
				);
			
			// delete old files
			$delete_backup_files( $filename_for_deletion );
			
			// do_action wp_rest_mediacat_upload after successful update
			\do_action( 'wp_rest_mediacat_upload', $post_id, 'context-rest-upload');

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
			//array_walk($filearray, '\mvbplugins\extmedialib\recoverfile');
			$restore_backup_files( $filename_for_deletion );

			// delete only a newly created target file; never delete the original when filename is unchanged
			if ( ! $is_same_target_as_current && is_file( $path_to_new_file ) ) {
				unlink($path_to_new_file);
			}
			$newGetResp = \mvbplugins\extmedialib\implode_all(' , ', $getResp); 
			return new \WP_Error('Error', $newGetResp, array( 'status' => 400 ));
		}
		
	} elseif (($isAttachment) && ($image_size < $minsize)) {
		return new \WP_Error('too_small', 'Invalid Image (smaller than: '. $minsize .' bytes) in body for update of: ' . $post_id, array( 'status' => 400 ));
	} elseif (($isAttachment) && ($image_size > wp_max_upload_size())) {
		return new \WP_Error('too_big', 'Invalid Image (bigger than: '. wp_max_upload_size() .' bytes) in body for update of: ' . $post_id, array( 'status' => 400 ));
	} elseif (! $isAttachment) {
		return new \WP_Error('not_found', 'Attachment is not an Image: ' . $post_id, array( 'status' => 415 ));
	} else $getResp = '';
	
	return rest_ensure_response($getResp);
};