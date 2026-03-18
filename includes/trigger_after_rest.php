<?php
namespace mvbplugins\extmedialib;

require_once __DIR__ . '/shared/autoload.php';

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

add_filter( 'wp_generate_attachment_metadata', '\mvbplugins\extmedialib\trigger_after_image_upload', 10, 3 );

function trigger_after_image_upload( $meta, $attachment_id, $context ) {
    if ( 'create' !== $context ) {
        return $meta;
    }

    $file = get_attached_file( $attachment_id );

	// get the mime type
	$mime = get_post_mime_type( $attachment_id );
	$imagemeta = $meta;

	// check if the file is an image
	if ( !\str_contains( $mime, 'webp' ) && !\str_contains( $mime, 'avif' ) && !\str_contains( $mime, 'jpeg' ) ) {
		return $meta;
	} 

	// TODO: wie kann beim pull der /tests ordner ausgelassen werden 
	$extractor = new \mvbplugins\Extractors\MetadataExtractor();
    $imagemeta['image_meta'] = $extractor->getMetadata( $file, 'wordpress' );


	// remove empty keywords from the array, and set to an empty array if not existing.
	if ( \array_key_exists( 'keywords', $imagemeta['image_meta'] ) ) {
		$imagemeta['image_meta']['keywords'] = \array_filter( $imagemeta['image_meta']['keywords'] );
	} else {
		$imagemeta['image_meta']['keywords'] = [];
	}
	// use a usefull mapping for SEO.
	// permalink is not changed or handled here: It es better to use a expressive filename for SEO and not change the permalink with every title change.
	wp_update_post([
		'ID'           => $attachment_id,
		'post_title'   => $imagemeta['image_meta']['title'] ?? '', // post_title -> Titel des Attachments / Attachment-Page-Titel
		'post_excerpt' => $imagemeta['image_meta']['title'] ?? '', // post_excerpt -> Bildunterschrift. Verwende den XMP-Titel als Vorbelegung
		'post_content' => $imagemeta['image_meta']['caption'] ?? '', //b post_content -> Beschreibung des Attachments / Attachment-Page-Inhalt 
	]);

	update_post_meta(
		$attachment_id,
		'_wp_attachment_image_alt', // -> alt im <img> Tag
		//$imagemeta['image_meta']['alt_text'] // alt_text gibt es in XMP-Metadaten nicht
		$imagemeta['image_meta']['caption'] ?? '' // use description as alt text. 
		// TODO: User Documentation: Requires that description is a SEO-useful functional description of the image!
	);

    return $imagemeta;
}