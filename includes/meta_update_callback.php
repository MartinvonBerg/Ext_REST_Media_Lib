<?php
namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

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
	
	// check body und Header Content-Disposition
	if ( $data->get_content_type() !== null && array_key_exists('value', $data->get_content_type()) ) {
		$type = $data->get_content_type()['value']; // upload content-type of POST-Request 
	} else { 
		$type = ''; 
	}
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