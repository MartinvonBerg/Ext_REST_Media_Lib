<?php
/**
 * Helper functions for the extension of the rest-api
 *
 * PHP version 7.2.0 - 8.0.0
 *
 * @category   Rest_Api_Functions
 * @package    Rest_Api_Functions
 * @author     Martin von Berg <mail@mvb1.de>
 * @copyright  2021 Martin von Berg
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       https://github.com/MartinvonBerg/Ext_REST_Media_Lib
 * @since      File available since Release 5.3.0
 */

namespace mvbplugins\extmedialib;

// ---------------- helper functions ----------------------------------------------------

/**
 * Select only the original files that were NOT added yet to WP-Cat from THIS $folder, not from all folders
 *
 * @param string $folder the folder that should be used.
 *
 * @return array the original-files in the given $folder that were NOT added to WP-Cat yet
 */
function get_files_to_add( $folder ) {
	$result = array();
	$all = glob( $folder . '/*');
	$i = 0;
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];
	$dir = str_replace( '\\', '/', $dir);
	$dir = str_replace( '\\\\', '/', $dir);
	$dir = str_replace( '//', '/', $dir);
	$url = $upload_dir['baseurl'];

	foreach ( $all as $file ) {
		$test = $file;
		if (( ! preg_match_all( '/[0-9]+x[0-9]+/', $test)) && ( ! strstr( $test, '-' . EXT_SCALED)) && ( ! is_dir( $test ) ) ) {
			// Check if one of the files in $result was already attached to WPCat.
			$file = str_replace( $dir, $url, $file );
			$addedbefore = attachment_url_to_postid( $file );

			if (empty( $addedbefore )) {
				$ext = '.' . pathinfo( $file)['extension'];
				$file = str_replace( $ext, '-' . EXT_SCALED . $ext, $file );
				$addedbefore = attachment_url_to_postid( $file );
			}

			if (empty($addedbefore)) {
				$result [ $i ] = $test;
				++$i;
			}
		}
	}
	return $result;
}

/**
	* Return the original files that were already added to WP-Cat from THIS $folder
	*
	* @param string $folder the folder that should be used.
	*
	* @return array the original-files in the given $folder that were NOT added to WP-Cat yet
	*/
function get_added_files_from_folder($folder)
{
	$result = array();
	$all = glob($folder . '/*');
	$i=0;
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];
	$dir = str_replace('\\', '/', $dir);
	$dir = str_replace('\\\\', '/', $dir);
	$dir = str_replace('//', '/', $dir);
	$url = $upload_dir['baseurl'];

	foreach ($all as $file) {
		$test=$file;
		if ((! preg_match_all('/[0-9]+x[0-9]+/', $test)) && (! strstr($test, '-' . EXT_SCALED)) && (! is_dir($test))) {
			// Check if one of the files in $result was already attached to WPCat.
			$file = str_replace($dir, $url, $file);
			$addedbefore = attachment_url_to_postid($file);

			if (empty($addedbefore)) {
				$ext = '.' . pathinfo($file)['extension'];
				$file = str_replace($ext, '-' . EXT_SCALED . $ext, $file);
				$addedbefore = attachment_url_to_postid($file);
			}

			if (empty($addedbefore)) {
				$addedbefore = 0;
			}
			$result [ $i ] ['id']   = $addedbefore;
			$result [ $i ] ['file'] = $file;
			++$i;
		}
	}
	return $result;
}

/**
	* Special replace for foldernames $string '_ . ? * \ / space' to '-'
	*
	* @param string $string the string to check.
	*
	* @return string the string with replacments
	*/
function special_replace($string)
{
	$result = str_replace('_', '-', $string);
	$result = str_replace('.', '-', $result);
	$result = str_replace('?', '-', $result);
	$result = str_replace('*', '-', $result);
	$result = str_replace('\\', '-', $result);
	$result = str_replace('/', '-', $result);
	$result = str_replace('\s', '-', $result);
	return $result;
}

/**
 * Update image_meta function to update meta-data given by $post_ID and new metadata
 * (for jpgs: only keywords, credit, copyright, caption, title) 
 *
 * @param int   $post_id ID of the attachment in the WP-Mediacatalog.
 *
 * @param array $newmeta array with new metadata taken from the JSON-data in the POST-Request body.
 *
 * @return bool true if success, false if not: ouput of the WP function to update attachment metadata
 */
function update_metadata( int $post_id, array $newmeta)
{
	// get and check current Meta-Data from WP-database.
	$meta = wp_get_attachment_metadata($post_id);

	if (array_key_exists('image_meta', $newmeta)) {
		$newmeta = $newmeta['image_meta'];

		// organize metadata.
		array_key_exists('keywords', $newmeta)  ? $meta['image_meta']['keywords']  = $newmeta['keywords'] : '' ;     // Copy Keywords. GPS is missing. Does matter: is not used in WP.
		array_key_exists('credit', $newmeta)    ? $meta['image_meta']['credit']    = $newmeta['credit'] : ''     ;   // GPS is updated via file-update.
		array_key_exists('copyright', $newmeta) ? $meta['image_meta']['copyright'] = $newmeta['copyright'] : '' ;
		array_key_exists('caption', $newmeta)   ? $meta['image_meta']['caption']   = $newmeta['caption'] : ''   ;
		array_key_exists('title', $newmeta)     ? $meta['image_meta']['title']     = $newmeta['title']  : ''     ;

		// change the image capture metadata for webp only due to the fact that WP does not write this data to the database.
		$type = get_post_mime_type( $post_id ); 
		if ( 'image/webp' == $type) {
			array_key_exists('aperture', $newmeta)          ? $meta['image_meta']['aperture']           = $newmeta['aperture'] : '' ;
			array_key_exists('camera', $newmeta)            ? $meta['image_meta']['camera']             = $newmeta['camera'] : '' ;
			array_key_exists('created_timestamp', $newmeta) ? $meta['image_meta']['created_timestamp']  = $newmeta['created_timestamp'] : '' ;
			array_key_exists('focal_length', $newmeta)      ? $meta['image_meta']['focal_length']       = $newmeta['focal_length'] : '' ;
			array_key_exists('iso', $newmeta)               ? $meta['image_meta']['iso']                = $newmeta['iso'] : '' ;
			array_key_exists('shutter_speed', $newmeta)     ? $meta['image_meta']['shutter_speed']      = $newmeta['shutter_speed'] : '' ;
			array_key_exists('orientation', $newmeta)       ? $meta['image_meta']['orientation']        = $newmeta['orientation'] : '' ;
		}

		// Update the image_alt_text
		// TODO: This is somehow inconsinstent as all other metadata is updated with WP standard rest requests
		// ON the other hand is it necessary to keep the alt in the database and in the Post(s) consistent.
		if ( array_key_exists('alt_text', $newmeta) )
			$result = update_post_meta( $post_id, '_wp_attachment_image_alt', $newmeta['alt_text'] ); 

		//if ( array_key_exists('caption', $newmeta) );
			//$result = update_post_meta( $post_id, 'caption', $newmeta['caption'] );

	}

	// write metadata.
	$success = wp_update_attachment_metadata($post_id, $meta); // write new Meta-data to WP SQL-Database.

	return $success;
}

/**
 * get the upload url which corresponds to the upload directory
 *
 * @return string the url of the upload dir
 */
function get_upload_url() {
	$upload_dir = wp_upload_dir();
	//$dir = $upload_dir['basedir'];
	//$dir = str_replace('\\', '/', $dir);
	//$dir = str_replace('\\\\', '/', $dir);
	//$dir = str_replace('//', '/', $dir);
	$url = $upload_dir['baseurl'];
	return $url;
}

/* Check if given content is JSON format. */
function bodyIsJSON( $content ) {
	if ( is_array($content) || is_object($content) )
		return false; // can never be.

	$json = json_decode( $content );
	return $json && $json != $content;
}