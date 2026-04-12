<?php
namespace mvbplugins\extmedialib;

// get the plugin options for the meta update
$options = get_option( 'media-lib-extension' );

// Type definitions for phpstan
/** @phpstan-type AttachmentMeta array{
 *   width?: int,
 *   height?: int,
 *   file?: string,
 *   sizes?: array<string, array{
 *     file: string,
 *     width: int,
 *     height: int,
 *     mime-type: string
 *   }>,
 *   image_meta?: array{
 *     aperture?: string,
 *     credit?: string,
 *     camera?: string,
 *     caption?: string,
 *     created_timestamp?: string,
 *     copyright?: string,
 *     focal_length?: string,
 *     iso?: string,
 *     shutter_speed?: string,
 *     title?: string,
 *     orientation?: string,
 *     keywords?: array<int, string>
 *   }
 * } 
 */

// ------------------- Hook on REST response ----------------------------------------
// Filter to catch every REST Request and do action relevant for this plugin
if ( isset( $options['use_rest_api_extension'] ) && $options['use_rest_api_extension'] === "1" ) {
	add_filter( 'rest_pre_echo_response', '\mvbplugins\extmedialib\trigger_after_rest', 10, 3 );
}

/**
 * hook on the finalized REST-response and update the image_metadata and the posts using the updated image.
 *
 * @note This function is also called after the image upload via WordPress media library.
 *       Only done if POST-method to wp-json/wp/v2/media WITH title or caption or alt_text in the request. 
 *       Update of post content with the new caption is only done if docaption===true in the request or the plugin setting 'update_post_on_rest_update' is activated.
 * 
 * @param array<string, mixed> $result the prepared result
 * @param \WP_REST_Server $server the rest server
 * @param \WP_REST_Request<array<string, mixed>> $request the request
 * @return array<string, mixed> $result the $result to provide via REST-API as http response. The keys $newmeta["image_meta"]['caption'] 
 * and $newmeta["image_meta"]['title'] were changed depending on the result of the meta update
 */
function trigger_after_rest( array $result, \WP_REST_Server $server, \WP_REST_Request $request) : array {
	
	// alt_text is only available once at 'top-level' of the json - response
	// title and caption are availabe at 'top-level' of the json - response AND response['media_details']['image_meta']
	// This function keeps these values consistent
	$route = $request->get_route(); // wp/v2/media/id
	$method = $request->get_method(); // 'POST'
	$params = $request->get_params(); // id as int
	
	$id = \array_key_exists('id', $params) ? $params['id'] : null;
	$route = \str_replace( \strval( $id ), '', $route );
	$att = wp_attachment_is_image( $id );

	$hascaption = \array_key_exists('caption', $params);
	$hastitle = \array_key_exists('title', $params);
	$hasalt_text = \array_key_exists('alt_text', $params);

	// get the plugin options for the meta update
	$options = get_option( 'media-lib-extension' );

	$docaption = false;
	if ( ( (\array_key_exists('docaption', $params) && 'true' === $params['docaption'])
		   || (isset($options['update_post_on_rest_update']) && $options['update_post_on_rest_update'] === "1") )
		   && $hascaption ) $docaption = true;

	$dopostupdate = false;
	if ( ( (isset($options['update_post_on_rest_update']) && $options['update_post_on_rest_update'] === "1") )
		   && ($hascaption || $hasalt_text) ) $dopostupdate = true;

	$newmeta["image_meta"] = []; 
	$origin = 'standard';

	if ( $hascaption || $hastitle || $hasalt_text) {
		if ( $hascaption ) $newmeta["image_meta"]['caption'] = $params['caption'];
		if ( $hastitle ) $newmeta["image_meta"]['title'] = $params['title'];
		if ( $hasalt_text ) $newmeta["image_meta"]['alt_text'] = $params['alt_text'];
	}

	// update title and caption in $meta['media_details']['image_meta'] in image metadata.
	if ( ($att) && ('POST' === $method) && ('/wp/v2/media/' === $route) && ($hascaption || $hastitle) ) {
		// update the image_meta title and caption also 
		$success = \mvbplugins\extmedialib\update_metadata( $id, $newmeta, $origin );
		if ( $success ) {
			if ($hascaption) $result["media_details"]["image_meta"]["caption"] = $params['caption'];
			if ($hastitle)  $result["media_details"]["image_meta"]["title"] = $params['title'];
		}
	}

	// update the relevant posts using the image.
	if ( ($att) && ('POST' === $method) && ('/wp/v2/media/' === $route) && $dopostupdate ) {
		
		// store the original image-data in the media replacer class with construct-method of the class
		$replacer = new \mvbplugins\extmedialib\Replacer( $id ); // file is loaded 'globally' in plugin main file
		$replacer->API_doMetaUpdate( $newmeta, $docaption ); 
		$replacer = null;
	}

	return $result;
}

// ------------------- Hook on image upload ----------------------------------------
// do not activate the filter if it is disable by the plugin settings
if ( isset( $options['use_media_upload_hook'] ) && $options['use_media_upload_hook'] === "1" ) {
	require_once __DIR__ . '/shared/autoload.php';
	add_filter( 'wp_generate_attachment_metadata', '\mvbplugins\extmedialib\trigger_after_image_upload', 10, 3 );
	//add_filter( 'wp_update_attachment_metadata', '\mvbplugins\extmedialib\fn_update', 10, 2 );
}

/**
 * Summary of mvbplugins\extmedialib\fn_update
 * @param array<string, mixed> $meta The WordPress metadata array for the attachment.
 * @param int $attachment_id
 * @return array<string, mixed> 
 */
function fn_update( array $meta, int $attachment_id ) : array {
	// this function is needed to trigger the update of the metadata after the image upload. The update of the metadata is done in the function 'trigger_after_image_upload' which is hooked on 'wp_generate_attachment_metadata'. The update of the metadata is needed to have the same metadata for webp and avif images as for jpg images. The function 'trigger_after_image_upload' is also called after the image upload via the REST-API.
	return \mvbplugins\extmedialib\trigger_after_image_upload( $meta, $attachment_id, 'update' );
}
/**
 * Add the image metadata (mainly for webp and avif) to the attachment metadata after upload and update the post meta and post data of the attachment.
 * Goal of this function hook is to have the image metadata of webp and avif images idenctical to jpg which is done by WP Standard functionality.
 * 
 * @note This function is also called after the image upload via the REST-API.
 * 
 * @param array<string, mixed> $meta The WordPress metadata array for the attachment.
 * @param int $attachment_id The ID of the attachment being processed.
 * @param string $context The context of the metadata generation, typically 'create' for new uploads.
 * 
 * @return array<string, mixed> The modified metadata array with added image metadata for webp and avif images.
 */
function trigger_after_image_upload( array $meta, int $attachment_id, string $context ) : array {
    if ( 'create' !== $context && 'update' !== $context ) {
        return $meta;
    }

    $file = get_attached_file( $attachment_id );
	if ( $file === false ) {
		return $meta;
	}

	// get the mime type
	$mime = get_post_mime_type( $attachment_id );
	if ( $mime === false ) {
		return $meta;
	}

	// get and sanitize the options to check which image types should be processed
	$options = get_option( 'media-lib-extension' );
	$process_webp = isset( $options['treat_webp_upload'] ) && $options['treat_webp_upload'] === "1";
	$process_avif = isset( $options['treat_avif_upload'] ) && $options['treat_avif_upload'] === "1";
	$process_jpeg = isset( $options['treat_jpg_upload'] ) && $options['treat_jpg_upload'] === "1";

	// check if the file is an image
	if ( !\str_contains( $mime, 'webp' ) && !\str_contains( $mime, 'avif' ) && !\str_contains( $mime, 'jpeg' ) ) {
		return $meta;
	} 

	// check if the image should be processed
	if ( (!$process_webp && \str_contains( $mime, 'webp' )) || (!$process_avif && \str_contains( $mime, 'avif' )) || (!$process_jpeg && \str_contains( $mime, 'jpeg' )) ) {
		return $meta;
	}

	$extractor = new \mvbplugins\Extractors\MetadataExtractor();
	$imagemeta = $meta;
    $imagemeta['image_meta'] = $extractor->getMetadata( $file, 'wordpress' );

	$image_title = isset( $imagemeta['image_meta']['title'] ) ? (string) $imagemeta['image_meta']['title'] : '';
	$image_caption = isset( $imagemeta['image_meta']['caption'] ) ? (string) $imagemeta['image_meta']['caption'] : '';

	$post_excerpt_source = isset( $options['fill_post_excerpt_from_xmp'] ) ? (string) $options['fill_post_excerpt_from_xmp'] : '';
	$alt_source = isset( $options['fill_alt_from_xmp'] ) ? (string) $options['fill_alt_from_xmp'] : '';

	$post_excerpt_value = '';
	if ( 'xmp_title' === $post_excerpt_source ) {
		$post_excerpt_value = $image_title; // TODO use filename if title is empty
	} elseif ( 'xmp_desc' === $post_excerpt_source ) {
		$post_excerpt_value = $image_caption;
	}

	$alt_value = '';
	if ( 'xmp_title' === $alt_source ) {
		$alt_value = $image_title;
	} elseif ( 'xmp_desc' === $alt_source ) {
		$alt_value = $image_caption;
	}

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
		'post_title'   => $image_title, // post_title -> Titel des Attachments / Attachment-Page-Titel. Der Titel wird im Frontend gar nicht angezeigt.
		'post_excerpt' => $post_excerpt_value, // post_excerpt is mapped from XMP according to fill_post_excerpt_from_xmp.
		'post_content' => $image_caption, // post_content -> Beschreibung des Attachments / Attachment-Page-Inhalt. Wird im Frontend nicht verwendet.
	]);

	update_post_meta(
		$attachment_id,
		'_wp_attachment_image_alt', // -> alt im <img> Tag
		$alt_value // alt text is mapped from XMP according to fill_alt_from_xmp.
	);

    return $imagemeta;
}