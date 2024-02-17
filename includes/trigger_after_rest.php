<?php
namespace mvbplugins\extmedialib;

// ------------------- Hook on REST response ----------------------------------------
// Filter to catch every REST Request and do action relevant for this plugin
add_filter( 'rest_pre_echo_response', '\mvbplugins\extmedialib\trigger_after_rest', 10, 3 );

/**
 * hook on the finalized REST-response and update the image_meta and the posts using the updated image
 *
 * @param array<string>|\stdClass $result the prepared result
 * @param \WP_REST_Server $server the rest server
 * @param \WP_REST_Request $request the request
 * @return array<string> $result the $result to provide via REST-API as http response. The keys $newmeta["image_meta"]['caption'] 
 * and $newmeta["image_meta"]['title'] were changed depending on the result of the meta update
 */
function trigger_after_rest( $result, $server, $request) {
	global $wpdb;

	// alt_text is only available once at 'top-level' of the json - response
	// title and caption are availabe at 'top-level' of the json - response AND response['media_details']['image_meta']
	// This function keeps these values consistent
	$route = $request->get_route(); // wp/v2/media/id
	$method = $request->get_method(); // 'POST'

	$params = $request->get_params(); // id as int
	
	$id = array_key_exists('id', $params) ? $params['id'] : null;
	$route = \str_replace( \strval( $id ), '', $route );
	$att = wp_attachment_is_image( $id );

	$hascaption = array_key_exists('caption', $params);
	$hastitle = array_key_exists('title', $params);
	#$hasdescription = array_key_exists('description', $params);
	$hasalt_text = array_key_exists('alt_text', $params);

	$docaption = false;
	if (array_key_exists('docaption', $params) )
		if ( 'true' == $params['docaption'] && $hascaption )
			$docaption = true;

	$newmeta["image_meta"] = []; 
	$origin = 'standard';

	if ( $hascaption || $hastitle || $hasalt_text) {
		if ( $hascaption ) $newmeta["image_meta"]['caption'] = $params['caption'];
		if ( $hastitle ) $newmeta["image_meta"]['title'] = $params['title'];
		if ( $hasalt_text ) $newmeta["image_meta"]['alt_text'] = $params['alt_text'];
	}

	// update title and caption in $meta['media_details']['image_meta']
	if ( ($att) && ('POST' == $method) && ('/wp/v2/media/' == $route) && ($hascaption || $hastitle) ) {
		// update the image_meta title and caption also 
		$success = \mvbplugins\extmedialib\update_metadata( $id, $newmeta, $origin );
		if ( $success ) {
			if ($hascaption) $result["media_details"]["image_meta"]["caption"] = $params['caption'];
			if ($hastitle)  $result["media_details"]["image_meta"]["title"] = $params['title'];
		}
	}
	// update slug (=post_name) and therefore permalink with the new title 
	if ( ($att) && ('POST' == $method) && ('/wp/v2/media/' == $route) && $hastitle ) {
		$new_slug = \sanitize_title_with_dashes($params['title']);

		$wpdb->update( $wpdb->posts, 
			array( 'post_name' => $new_slug ), 
			array('ID' => $id) 
		);
		
		$result['link']= \str_replace($result['slug'],$new_slug,$result['link']);
		$result['slug'] = $new_slug;
	}

	// update the relevant posts using the image
	if ( ($att) && ('POST' == $method) && ('/wp/v2/media/' == $route) && ($hascaption || $hasalt_text) ) {
		
		// store the original image-data in the media replacer class with construct-method of the class
		$replacer = new \mvbplugins\extmedialib\Replacer( $id );
		$replacer->API_doMetaUpdate( $newmeta, $docaption ); 
		$replacer = null;
	}

	return $result;
}