<?php
namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

/**
 * Callback for GET to REST-Route registered_image_subsizes'. 
 * 
 * @return \WP_REST_Response|\WP_Error for the rest response body or a WP Error object
 */
function get_registered_image_subsizes()
{
	$sizes = wp_get_registered_image_subsizes();
		
	if ($sizes) {
		$getResp = array(
			'message' => __('Success') . '. ' .__('You requested registered images subsizes'),
			'sizes' => $sizes,
		);
        return rest_ensure_response($getResp);
	} else {
		return new \WP_Error('not_found', 'Could not get Image Subsizes', array( 'status' => 404 ));
	};
};

/**
 * Callback for POST to REST-Route 'registered_image_subsizes'.
 * 
 * @return \WP_Error for the rest response body or a WP Error object
 */
function post_registered_image_subsizes()
{
	return new \WP_Error('not_implemented', 'You requested images subsizes with POST-Method. Please get image_meta with GET-Request. Registering of images subsizes is not implemented', array( 'status' => 405 ));	
};