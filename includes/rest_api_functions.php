<?php

/**
 * Helper functions for the extension of the rest-api
 *
 * PHP version 7.4.0 - 8.0.0
 *
 * @category   Rest_Api_Functions
 * @package    Rest_Api_Functions
 * @author     Martin von Berg <mail@mvb1.de>
 * @copyright  2021 Martin von Berg
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       https://github.com/MartinvonBerg/Ext_REST_Media_Lib
 * @since      File available since Release 5.3.0
 */

// phpstan: level 8 reached without baseline

namespace mvbplugins\extmedialib;

// ---------------- general helper functions ----------------------------------------------------

/**
 * Return the original files that were already added OR NOT added to WP-Cat from THIS $folder
 *
 * @param string $folder the folder that should be used.
 * @param bool $get_added_files either provide an array with files that are IN WP-Cat or NOI in WP-Cat
 *
 * @return array<int, string> | array<int, array<string, array|int|string>> the original-files in the given $folder that are IN or NOI IN WP-Cat yet
 */
function get_files_from_folder(string $folder, bool $get_added_files)
{
	$result = array();
	$all = glob($folder . '/*');
	$i = 0;

	$dir = get_upload_dir();
	$url = get_upload_url();

	if (false == $all) {
		$all = array();
	}

	foreach ($all as $file) {
		$test=$file;
		if ((! preg_match_all('/[0-9]+x[0-9]+/', $test)) && (! strstr($test, '-' . EXT_SCALED)) && (! is_dir($test))) {
			// Check if one of the files in $result was already attached to WPCat.
			$file = str_replace($dir, $url, $file);
			$addedbefore = attachment_url_to_postid($file);

			if ($addedbefore === 0) {
				$ext = '.' . pathinfo($file, PATHINFO_EXTENSION); //['extension'];
				$file = str_replace($ext, '-' . EXT_SCALED . $ext, $file);
				$addedbefore = attachment_url_to_postid($file);
			}

			if ($get_added_files) {
				$result [ $i ] ['id']   = $addedbefore;
				$result [ $i ] ['file'] = $file;
				++$i;
			} 
			elseif ($addedbefore === 0) {
				$result[$i] = $test;
				++$i;
			}
		}
	}
	return $result;
}

/**
 * Update image_meta function to update meta-data given by $post_ID and new metadata
 * (for jpgs: only keywords, credit, copyright, caption, title) 
 *
 * @param int   $post_id ID of the attachment in the WP-Mediacatalog.
 *
 * @param array<string[]|array> $newmeta array with new metadata taken from the JSON-data in the POST-Request body.
 *
 * @param string $origin the source of the function call 
 * 
 * @return int|bool true if success, false if not: ouput of the WP function to update attachment metadata
 */
function update_metadata(int $post_id, array $newmeta, string $origin)
{
	// get and check current Meta-Data from WP-database.
	$meta = wp_get_attachment_metadata($post_id);
	if ( $meta === false) { $meta = [];	}
	$oldmeta = $meta;

	if (array_key_exists('image_meta', $newmeta)) {
		$newmeta = $newmeta['image_meta'];
		// sanitize the keywords
		foreach ($newmeta['keywords'] as $key => $entry) {
			$newmeta['keywords'][$key] = \htmlspecialchars( $entry );
		};

		// organize metadata. GPS-data is missing. Does matter: is not used in WP. GPS is updated via file-update.
		array_key_exists('keywords', $newmeta)  ? $meta['image_meta']['keywords']  = $newmeta['keywords'] : ''; 
		array_key_exists('credit', $newmeta)    ? $meta['image_meta']['credit']    = \htmlspecialchars($newmeta['credit']) : '';
		array_key_exists('copyright', $newmeta) ? $meta['image_meta']['copyright'] = \htmlspecialchars($newmeta['copyright']) : '';
		array_key_exists('caption', $newmeta)   ? $meta['image_meta']['caption']   = \htmlspecialchars($newmeta['caption']) : '';
		array_key_exists('title', $newmeta)     ? $meta['image_meta']['title']     = \htmlspecialchars($newmeta['title'])  : '';

		// change the image capture metadata for webp only due to the fact that WP does not write this data to the database.
		$type = get_post_mime_type($post_id);
		if ('image/webp' == $type) {
			array_key_exists('aperture', $newmeta)          ? $meta['image_meta']['aperture']           = \htmlspecialchars($newmeta['aperture']) : '';
			array_key_exists('camera', $newmeta)            ? $meta['image_meta']['camera']             = \htmlspecialchars($newmeta['camera']) : '';
			array_key_exists('created_timestamp', $newmeta) ? $meta['image_meta']['created_timestamp']  = \htmlspecialchars($newmeta['created_timestamp']) : '';
			array_key_exists('focal_length', $newmeta)      ? $meta['image_meta']['focal_length']       = \htmlspecialchars($newmeta['focal_length']) : '';
			array_key_exists('iso', $newmeta)               ? $meta['image_meta']['iso']                = \htmlspecialchars($newmeta['iso']) : '';
			array_key_exists('shutter_speed', $newmeta)     ? $meta['image_meta']['shutter_speed']      = \htmlspecialchars($newmeta['shutter_speed']) : '';
			array_key_exists('orientation', $newmeta)       ? $meta['image_meta']['orientation']        = \htmlspecialchars($newmeta['orientation']) : '';
		}
	}

	// reset title and caption in $meta to prevent overwrite with the route update_meta
	if ('mvbplugin' === $origin) {
		$meta['image_meta']['title']   = $oldmeta['image_meta']['title'];
		$meta['image_meta']['caption'] = $oldmeta['image_meta']['caption'];
	}
	// write metadata.
	$success = wp_update_attachment_metadata($post_id, $meta); // write new Meta-data to WP SQL-Database.

	return $success;
}

/**
 * Get the upload URL/path in right way (works with SSL).
 *
 * @return string the base appended with subfolder
 */
function get_upload_url()
{
	$param = 'baseurl';
	$subfolder = '';

	$upload_dir = wp_get_upload_dir();
	$url = $upload_dir[$param];

	if ($param === 'baseurl' && is_ssl()) {
		$url = str_replace('http://', 'https://', $url);
	}

	return $url . $subfolder;
}

/**
 * get the upload DIR 
 *
 * @return string the upload base DIR without subfolder
 */
function get_upload_dir()
{
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];
	$search = ['\\', '\\\\', '//'];
	$dir = str_replace($search, '/', $dir);
	return $dir;
}

/**
 * Check if given content is JSON format
 *
 * @param mixed $content
 * @return mixed return the decoded content from json to an php-array if successful
 */
function bodyIsJSON($content)
{
	if (is_array($content) || is_object($content))
		return false; // can never be.

	$json = json_decode($content);
	return $json && $json != $content;
}

/**
 * set the filename to the complete path
 *
 * @param  string $dir Path that shall be trailing the filename
 * @param  string $fileName the $filename that shall include dir
 * @return string the corrected fileName
 */
function set_complete_path( $dir, $fileName ) {
	// if provided filename is empty : use the old filename
	$isCompletePath = strpos( $fileName, $dir . '/');

	if ( $isCompletePath === false) {
		$path_to_new_file = $dir . \DIRECTORY_SEPARATOR . $fileName;
	} else {
		$path_to_new_file = $fileName;
	}
	return $path_to_new_file;
}

/**
 * Concatenate multidimensional-array-to-string with glue separator.
 * 
 * @source https://stackoverflow.com/questions/12309047/multidimensional-array-to-string multidimensional-array-to-string
 * @param  string $glue the separator for the string concetantion of array contents.
 * @param  array $arr input array
 * @return string|mixed return string on success or the input if it is not a string
 */
function implode_all( $glue, $arr ) {
	if( is_array( $arr ) ){
  
	  foreach( $arr as $key => &$value ){
  
		if( @is_array( $value ) ){
		  $arr[ $key ] = implode_all( $glue, $value );
		}
	  }
  
	  return implode( $glue, $arr );
	}
  
	// Not array
	return $arr;
}
