<?php
/**
 * functions for the extension of the rest-api with fields 'gallery', 'gallery_sort' and 'md5'.
 *
 * PHP version 7.4.0 - 8.0.0
 *
 * @category   Rest_Api_Functions
 * @package    Rest_Api_Functions
 * @author     Martin von Berg <mail@mvb1.de>
 * @copyright  2022 Martin von Berg
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       https://github.com/MartinvonBerg/Ext_REST_Media_Lib
 * @since      File available since Release 0.1.0
 */

namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

/**
 * callback to update the gallery entry for the given attachment-id
 *
 * @param string $value new entry for the gallery field
 * @param object $post e.g. attachment which gallery field should be updated
 * @return bool success of the callback
 */
function cb_update_field( string $value, object $post, string $field ) :bool
{
	$old = (string) get_post_meta( $post->ID, $field, true );
	$ret = update_post_meta( $post->ID, $field, $value );

	// check the return-value here as this is also false if the value remains unchanged.
	if ( (($ret == false) && ($old == $value)) || is_int( $ret) ) 
		$ret = true;
	
	return $ret;
}

/**
 * callback to retrieve the MD5 sum and size in bytes for the given attachment-id
 *
 * @param array{id: int} $data key-value paired array from the get method with 'id'
 * @return array{MD5: string, size: int|false} $md5
 * 		         $md5['MD5']: the MD5 sum of the original attachment file, 
 * 				 $md5['size']: the size in bytes of the original attachment file
 */
function cb_get_md5( array $data ) :array
{
	$original_filename = wp_get_original_image_path($data['id']);
	$md5 = array(
		'MD5' => '0',
		'size' => 0,
		'file' => $original_filename,
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