<?php
/**
 * Class for the replacment of images in a post. Taken from plugin enable_media_replace.
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

// ---------- replacer class ---------------------------
class Replacer
{
	protected $post_id;

	// everything source is the attachment being replaced
	protected $sourceFile; // File Object
	protected $source_post; // wpPost;
	protected $source_is_image;
	protected $source_metadata;
	protected $source_url;

	// everything target is what will be. This is set when the image is replaced, the result. Used for replacing.
	protected $targetFile;
	protected $targetName;
	public 	  $target_metadata;
	public	  $target_url;
	protected $target_location = false; // option for replacing to another target location
	
	// old settings moved to class attributes
	protected $replace_type;
	protected $timestamp_replace ;
	protected $do_new_location;
	public    $new_location_dir;

	protected $replaceMode = 1; // replace if nothing is set
	protected $timeMode = 1;
	protected $datetime = null;

	protected $ThumbnailUpdater; // class

	const MODE_REPLACE = 1;
	const MODE_SEARCHREPLACE = 2;

	const TIME_UPDATEALL = 1; // replace the date
	const TIME_UPDATEMODIFIED = 2; // keep the date, update only modified
	const TIME_CUSTOM = 3; // custom time entry

	public function __construct( $post_id ) {
		$this->post_id = $post_id;

		if (function_exists('wp_get_original_image_path')) // WP 5.3+
		{
			$source_file = wp_get_original_image_path($post_id);
			if ($source_file === false) // if it's not an image, returns false, use the old way.
				$source_file = trim(get_attached_file($post_id, apply_filters( 'wp_handle_replace', true )));
		}
		else
			$source_file = trim(get_attached_file($post_id, apply_filters( 'wp_handle_replace', true )));

		/* It happens that the SourceFile returns relative / incomplete when something messes up get_upload_dir with an error something.
			This case shoudl be detected here and create a non-relative path anyhow..
		*/
		if (! file_exists($source_file) && $source_file && 0 !== strpos( $source_file, '/' ) && ! preg_match( '|^.:\\\|', $source_file ) )
		{
			$file = get_post_meta( $post_id, '_wp_attached_file', true );
			$uploads = wp_get_upload_dir();
			$source_file = $uploads['basedir'] . "/$file";
		}

		//Log::addDebug('SourceFile ' . $source_file);
		$this->sourceFile = new emrFile($source_file);
		$this->source_post = get_post($post_id);
		$this->source_is_image = wp_attachment_is('image', $this->source_post);
		$this->source_metadata = wp_get_attachment_metadata( $post_id );

		if (function_exists('wp_get_original_image_url')) // WP 5.3+
		{
			$source_url = wp_get_original_image_url($post_id);
			if ($source_url === false)  // not an image, or borked, try the old way
				$source_url = wp_get_attachment_url($post_id);

			$this->source_url = $source_url;
		}
		else
			$this->source_url = wp_get_attachment_url($post_id);
  	}

	public function API_doSearchReplace () {
		// settings from upload.php 
		$this->replace_type = 'replace_and_search';
		$this->timestamp_replace = 1;
		$this->do_new_location = false;
		
		$args = array(
			'thumbnails_only' => false, // means resized images only
		);
  
		// Search Replace will also update thumbnails.
		$this->setTimeMode( $this->timestamp_replace );  
		$this->doSearchReplace( $args ); 

		// if all set and done, update the date. This updates the date of the image in the media lib only.
      	// This must be done after wp_update_posts. 
		$this->updateDate(); //  
	}

	public function API_doMetaUpdate( $newmeta ) {

		// prepare the metadata from the REST POST request
		$this->target_metadata = $this->source_metadata;
		$newmeta = $newmeta['image_meta'];
		$target_meta = array();
		// TODO: sanitize the input
		array_key_exists('alt_text', $newmeta)  ? $target_meta['alt_text'] = $newmeta['alt_text'] : '' ;
		array_key_exists('caption',  $newmeta)  ? $target_meta['caption']  = $newmeta['caption']  : '' ;
		$this->target_metadata['image_meta']['caption'] = $target_meta['caption'];
		$this->target_metadata['image_meta']['alt_text'] = $target_meta['alt_text'];

		$source_alt_text = get_post_meta( $this->post_id, '_wp_attachment_image_alt', true) ?? '' ;
		$this->source_metadata['image_meta']['alt_text'] = $source_alt_text;

		// get the directory in the uploads folder that contains the image 
		$baseurl = \mvbplugins\extmedialib\get_upload_url() ; // TODO: replace with my special func to catch all cases for http and https
		$gallerydir = \str_replace( $baseurl, '', $this->source_url );
		$file = $this->source_metadata['original_image'];
		if ( ! $file ) $file = $this->sourceFile->getFileName();

		$gallerydir = str_replace( $file, '', $gallerydir );
		$gallerydir = rtrim( $gallerydir, '/');
		$gallerydir = ltrim( $gallerydir, '/');

		$this->new_location_dir = $gallerydir;
		$this->target_url = $this->source_url;
		
		// settings from original /view/upload.php 
		$this->replace_type = 'replace_and_search';
		$this->timestamp_replace = 1;
		$this->do_new_location = false;
		
		// Search Replace will also update thumbnails.
		$this->setTimeMode( $this->timestamp_replace );

		// Search-and-replace filename in post database
		// TODO: Check this with scaled images. This is from the original code from enable_media_replacer
		$base_url = parse_url( $this->source_url, PHP_URL_PATH );// emr_get_match_url( $this->source_url);
		$base_url = str_replace('.' . pathinfo($base_url, PATHINFO_EXTENSION), '', $base_url);
		
		// replace-run for the baseurl only
		$updated = $this->doMetaReplaceQuery( $base_url );
		return $updated;	
	}

	protected function doSearchReplace($args = array()) {
		
		// Search-and-replace filename in post database
		// TODO: Check this with scaled images. This is from the original code from enable_media_replacer
		$base_url = parse_url($this->source_url, PHP_URL_PATH);// emr_get_match_url( $this->source_url);
		$base_url = str_replace('.' . pathinfo($base_url, PATHINFO_EXTENSION), '', $base_url);

		/** Fail-safe if base_url is a whole directory, don't go search/replace */
		if (is_dir($base_url)) {
			//Log::addError('Search Replace tried to replace to directory - ' . $base_url);
			//Notices::addError(__('Fail Safe :: Source Location seems to be a directory.', 'enable-media-replace'));
			return;
		}

		if (strlen(trim($base_url)) == 0) {
			//Log::addError('Current Base URL emtpy - ' . $base_url);
			//Notices::addError(__('Fail Safe :: Source Location returned empty string. Not replacing content','enable-media-replace'));
			return;
		}

		// get relurls of both source and target.
		$urls = $this->getRelativeURLS();

		if ($args['thumbnails_only']) {
			foreach($urls as $side => $data) {
				if (isset($data['base'])) {
					unset($urls[$side]['base']);
				}
				if (isset($data['file'])) {
					unset($urls[$side]['file']);
				}
			}
		}

		$search_urls  = $urls['source']; // old image urls
		$replace_urls = $urls['target']; // new image urls

		/* If the replacement is much larger than the source, there can be more thumbnails. This leads to disbalance in the search/replace arrays.
		Remove those from the equation. If the size doesn't exist in the source, it shouldn't be in use either */
		foreach( $replace_urls as $size => $url) {
			if ( ! isset( $search_urls[$size] ) ) {
				//Log::addDebug('Dropping size ' . $size . ' - not found in source urls');
				unset( $replace_urls[$size] );
			}
		}
		
		/* If on the other hand, some sizes are available in source, but not in target, try to replace them with something closeby.  */
		foreach( $search_urls as $size => $url ) {
			if ( ! isset( $replace_urls[$size] ) ) {

				$closest = $this->findNearestSize( $size );

				if ( $closest ) {
					$sourceUrl = $search_urls[$size];
					$baseurl = trailingslashit(str_replace(wp_basename($sourceUrl), '', $sourceUrl));
					$replace_urls[$size] = $baseurl . $closest;
				} 
			}
		}
		
		/* If source and target are the same, remove them from replace. This happens when replacing a file with same name, and +/- same dimensions generated.
		The code from the original plugin is not used here, because with the REST-API there ARE cases wherer the filename is identical BUT the images are NOT.
		*/
	
		// If the two sides are disbalanced, the str_replace part will cause everything that has an empty replace counterpart to replace it with empty. Unwanted.
		if (count($search_urls) !== count($replace_urls))
		{
			return 0;
		}

		$updated = 0;

		// replace-run for the baseurl only
		$updated += $this->doReplaceQuery($base_url, $search_urls, $replace_urls);

		//Log::addDebug("Updated Records : " . $updated);
		return $updated;
	} 
	
	/**
	 *  update the date of the attachment = image in the media lib. 
	 *
	 * @return void void
	 */
	protected function updateDate() {
		global $wpdb;
		$post_date = $this->datetime;
		$post_date_gmt = get_gmt_from_date($post_date);
	
		$update_ar = array('ID' => $this->post_id);
		if ($this->timeMode == static::TIME_UPDATEALL || $this->timeMode == static::TIME_CUSTOM)
		{
			$update_ar['post_date'] = $post_date;
			$update_ar['post_date_gmt'] = $post_date_gmt;
		}
		else {
			//$update_ar['post_date'] = 'post_date';
		//  $update_ar['post_date_gmt'] = 'post_date_gmt';
		}
		$update_ar['post_modified'] = $post_date;
		$update_ar['post_modified_gmt'] = $post_date_gmt;
	
		$updated = $wpdb->update( $wpdb->posts, $update_ar , array('ID' => $this->post_id) );
	
		wp_cache_delete($this->post_id, 'posts');
  
	}

	public function setTimeMode($mode, $datetime = 0)
	{
		if ($datetime == 0)
		$datetime = current_time('mysql');

		$this->datetime = $datetime;
		$this->timeMode = $mode;
	}

	// Get REL Urls of both source and target.
	private function getRelativeURLS()
	{
		$dataArray = array(
			'source' => array('url' => $this->source_url, 'metadata' => $this->getFilesFromMetadata($this->source_metadata) ),
			'target' => array('url' => $this->target_url, 'metadata' => $this->getFilesFromMetadata($this->target_metadata) ),
		);
  
		$result = array();
  
		foreach($dataArray as $index => $item)
		{
			$result[$index] = array();
			$metadata = $item['metadata'];
  
			$baseurl = parse_url($item['url'], PHP_URL_PATH);
			$result[$index]['base'] = $baseurl;  // this is the relpath of the mainfile.
			$baseurl = trailingslashit(str_replace( wp_basename($item['url']), '', $baseurl)); // get the relpath of main file.
  
			foreach($metadata as $name => $filename)
			{
				$result[$index][$name] =  $baseurl . wp_basename($filename); // filename can have a path like 19/08 etc.
			}
  
		}
		return $result;
	}

	private function findNearestSize( $sizeName ) {
     //Log::addDebug('Find Nearest: '. $sizeName);

		if ( ! isset( $this->source_metadata['sizes'][$sizeName] ) || ! isset( $this->target_metadata['width'] ) ) // This can happen with non-image files like PDF.
		{
			return false;
		}
		$old_width = $this->source_metadata['sizes'][$sizeName]['width']; // the width from size not in new image
		$new_width = $this->target_metadata['width']; // default check - the width of the main image

		$diff = abs($old_width - $new_width);
		//  $closest_file = str_replace($this->relPath, '', $this->newMeta['file']);
		$closest_file = wp_basename($this->target_metadata['file']); // mainfile as default

		foreach($this->target_metadata['sizes'] as $sizeName => $data)
		{
			$thisdiff = abs($old_width - $data['width']);

			if ( $thisdiff  < $diff ) {
				$closest_file = $data['file'];
				if( is_array( $closest_file ) ) { 
					$closest_file = $closest_file[0];
				} // HelpScout case 709692915

				if( ! empty( $closest_file )) {
					$diff = $thisdiff;
					$found_metasize = true;
				}
			}
		}


		if( empty( $closest_file ) ) 
			return false;

		return $closest_file;
    }

	/**
	 * search for $base_url in the database and replace the search_urls with replace_urls
	 *
	 * @param string $base_url
	 * @param array $search_urls
	 * @param array $replace_urls
	 * @return int $number_of_updates
	 */	  
	private function doReplaceQuery($base_url, $search_urls, $replace_urls)
	{
		global $wpdb;
		/* Search and replace in WP_POSTS */
		// Removed $wpdb->remove_placeholder_escape from here, not compatible with WP 4.8
		$posts_sql = $wpdb->prepare(
		  "SELECT ID, post_content FROM $wpdb->posts WHERE post_status = 'publish' AND post_content LIKE %s",
		  '%' . $base_url . '%');
	
		$rs = $wpdb->get_results( $posts_sql, ARRAY_A );
		$number_of_updates = 0;
	
		if ( ! empty( $rs ) ) {
		  foreach ( $rs AS $rows ) {
			$number_of_updates = $number_of_updates + 1;
	
			// replace old URLs with new URLs.
			$post_content = $rows["post_content"];
			$post_id = $rows['ID'];
			$replaced_content = $this->replaceContent($post_content, $search_urls, $replace_urls);
	
			if ($replaced_content !== $post_content)
			{
				//Log::addDebug('POST CONTENT TO SAVE', $replaced_content);

				//  $result = wp_update_post($post_ar);
				$sql = 'UPDATE ' . $wpdb->posts . ' SET post_content = %s WHERE ID = %d';
				$sql = $wpdb->prepare($sql, $replaced_content, $post_id);

				//Log::addDebug("POSt update query " . $sql);
				$result = $wpdb->query($sql);
	
				if ($result === false)
				{
					//Notice::addError('Something went wrong while replacing' .  $result->get_error_message() );
					//Log::addError('WP-Error during post update', $result);
					return 0;
				}
			}

			// Change the post date on a post with a status other than 'draft', 'pending' or 'auto-draft'
			// We do this always, event if the content of the post was not changed, but maybe the image-file was changed. And we are here after several checks of the REST-API.
			$arg = array(
				'ID'            => $post_id,
				'post_date'     => $this->datetime,
				'post_date_gmt' => get_gmt_from_date( $this->datetime ),
			);
			$result = wp_update_post( $arg );
			wp_cache_delete( $post_id, 'posts' );
	
		  }
		}
	
		$number_of_updates += $this->handleMetaData($base_url, $search_urls, $replace_urls);
		return $number_of_updates;
	}

	/**
	 * search for $base_url in the database and replace metadata 'alt' and 'caption' in the code of the post
	 *
	 * @param string $base_url
	 * @return int $number_of_updates
	 */	  
	private function doMetaReplaceQuery( $base_url )
	{
		/*
		Strukturen in wp-posts, in den alt und caption ersetzt werden kann:
			<!-- wp:media-text {"mediaId":6027, 
				"mediaLink":"https://www.mvb1.de/franz-alpen-2015-08-133-bearbeitet-bearbeitet-2/", "mediaType":"image","mediaWidth":56,"verticalAlignment":"center","imageFill":true,"focalPoint":{"x":"0.63","y":"0.41"}} -->
			<figure class="wp-block-media-text__media" 
				style="background-image:url(http://www.mvb1.de/smrtzl/uploads/Alben_Website/Bike-Tete-de-Viraysse/Franz_Alpen_2015_08-133-Bearbeitet-Bearbeitet-2.jpg);background-position:63% 41%">
				<img src="http://www.mvb1.de/smrtzl/uploads/Alben_Website/Bike-Tete-de-Viraysse/Franz_Alpen_2015_08-133-Bearbeitet-Bearbeitet-2.jpg" 
				alt="" class="wp-image-6027 size-full"/>
			</figure>
			... weiterer code 
			<!-- /wp:media-text -->

			<!-- wp:image {"align":"center","id":6262,"width":-3,"height":-2,"sizeSlug":"medium","linkDestination":"none","className":"is-style-rounded"} -->
			<div class="wp-block-image is-style-rounded">
			<figure class="aligncenter size-medium is-resized">
				<img src="https://www.mvb1.de/smrtzl/uploads/Alben_Website/Wanderung-Serra-del-Prete/Hike-Serra-del-Prete-25-450x300.jpg" 
				alt="Der Autor auf dem Gipfel der Serra del Prete" class="wp-image-6262" width="-3" height="-2"/>
				<figcaption>Der Autor auf dem Gipfel der Serra del Prete</figcaption>
			</figure>
			</div>
			<!-- /wp:image -->

			<!-- wp:gallery {"ids":[6265,6264,6209,6260,6201,6202,6199,6598],"columns":2,"linkTo":"none","block_id":"add99315"} -->
				<figure class="wp-block-gallery columns-2 is-cropped">
				<ul class="blocks-gallery-grid">
					<li class="blocks-gallery-item">
						<figure>
							<img src="/smrtzl/uploads/Alben_Website/Wanderung-Serra-del-Prete/Italien_2018_12-1091.jpg"
								alt="Schöne Abendstimmung am Schlafplatz" data-id="6265"
								data-full-url="/smrtzl/uploads/Alben_Website/Wanderung-Serra-del-Prete/Italien_2018_12-1091.jpg"
								data-link="https://www.mvb1.de/italien-2018-12-1091/" class="wp-image-6265" />
							<figcaption class="blocks-gallery-item__caption">Abendstimmung am Schlafplatz</figcaption>
						</figure>
					</li>
					<li class="blocks-gallery-item">
						<figure>
							<img src="/smrtzl/uploads/Alben_Website/Wanderung-Serra-del-Prete/Hike-Serra-del-Prete-50.jpg" alt=""
								data-id="6264"
								data-full-url="/smrtzl/uploads/Alben_Website/Wanderung-Serra-del-Prete/Hike-Serra-del-Prete-50.jpg"
								data-link="https://www.mvb1.de/hike-serra-del-prete-50/" class="wp-image-6264" />
							<figcaption class="blocks-gallery-item__caption">Aussicht nach Nordwesten</figcaption>
						</figure>
					</li>
					<li class="blocks-gallery-item">
						<figure><img src="/smrtzl/uploads/Alben_Website/Wanderung-Gole-del-Raganello/Italien_2018_12-949.jpg" alt=""
								data-id="6209"
								data-full-url="/smrtzl/uploads/Alben_Website/Wanderung-Gole-del-Raganello/Italien_2018_12-949.jpg"
								data-link="https://www.mvb1.de/italien-2018-12-949/" class="wp-image-6209" />
							<figcaption class="blocks-gallery-item__caption">Blick in die Schlucht</figcaption>
						</figure>
					</li>
					<li class="blocks-gallery-item">
						<figure><img src="/smrtzl/uploads/Alben_Website/Wanderung-Serra-del-Prete/Hike-Serra-del-Prete-47.jpg"
								alt="" data-id="6260"
								data-full-url="/smrtzl/uploads/Alben_Website/Wanderung-Serra-del-Prete/Hike-Serra-del-Prete-47.jpg"
								data-link="https://www.mvb1.de/hike-serra-del-prete-47/" class="wp-image-6260" />
							<figcaption class="blocks-gallery-item__caption">Höhenzug der Serra del Prete</figcaption>
						</figure>
					</li>
					<li class="blocks-gallery-item">
						<figure><img src="/smrtzl/uploads/Alben_Website/Wanderung-Gole-del-Raganello/Italien_2018_12-1001.jpg"
								alt="" data-id="6201"
								data-full-url="/smrtzl/uploads/Alben_Website/Wanderung-Gole-del-Raganello/Italien_2018_12-1001.jpg"
								data-link="https://www.mvb1.de/italien-2018-12-1001/" class="wp-image-6201" />
							<figcaption class="blocks-gallery-item__caption">Winterlicher Wald bei Civita</figcaption>
						</figure>
					</li>
					<li class="blocks-gallery-item">
						<figure><img src="/smrtzl/uploads/Alben_Website/Wanderung-Gole-del-Raganello/Italien_2018_12-997.jpg" alt=""
								data-id="6202"
								data-full-url="/smrtzl/uploads/Alben_Website/Wanderung-Gole-del-Raganello/Italien_2018_12-997.jpg"
								data-link="https://www.mvb1.de/italien-2018-12-997/" class="wp-image-6202" />
							<figcaption class="blocks-gallery-item__caption">Aufstieg von der Teufelsbrücke</figcaption>
						</figure>
					</li>
					<li class="blocks-gallery-item">
						<figure><img src="/smrtzl/uploads/Alben_Website/Wanderung-Gole-del-Raganello/Italien_2018_12-1009.jpg"
								alt="" data-id="6199"
								data-full-url="/smrtzl/uploads/Alben_Website/Wanderung-Gole-del-Raganello/Italien_2018_12-1009.jpg"
								data-link="https://www.mvb1.de/italien-2018-12-1009/" class="wp-image-6199" />
							<figcaption class="blocks-gallery-item__caption">Picknickplatz mit schöner Aussicht</figcaption>
						</figure>
					</li>
					<li class="blocks-gallery-item">
						<figure><img src="/smrtzl/uploads/2021/08/DSC_1667.webp" alt="Weltkugel am Hauptplatz in Wittenberg"
								data-id="6598" data-full-url="/smrtzl/uploads/2021/08/DSC_1667.webp"
								data-link="https://www.mvb1.de/dsc_1667/" class="wp-image-6598" />
							<figcaption class="blocks-gallery-item__caption">Hauptplatz in Wittenberg</figcaption>
						</figure>
					</li>
				</ul>
			</figure>
			<!-- /wp:gallery -->

		*/

		global $wpdb;
		/* Search and replace in WP_POSTS */
		// Removed $wpdb->remove_placeholder_escape from here, not compatible with WP 4.8
		$posts_sql = $wpdb->prepare(
		  "SELECT ID, post_content FROM $wpdb->posts WHERE post_status = 'publish' AND post_content LIKE %s",
		  '%' . $base_url . '%');
	
		$rs = $wpdb->get_results( $posts_sql, ARRAY_A );
		$number_of_updates = 0;
	
		if ( ! empty( $rs ) ) {
			foreach ( $rs AS $rows ) {
				$post_content = $rows["post_content"];
				$replaced_content = $post_content;
				$post_id = $rows['ID'];

				// get all the figures in the post_content
				$dom=new \domDocument;
				$dom->loadHTML($post_content);
				//$figures = $dom->getElementsByTagName('figure'); // every image has to be a figure, works only with gutenberg

				// get all the comments in the post_content
				$xpath = new \DOMXpath($dom);
				$comments = $xpath->query("//comment()");

				// find all the html comments that contain gutenberg code and replace the metadata
				// every image has to be in a html comment , works only with gutenberg
				//$index = 0;
				foreach ( $comments as $c ) { 
					$text = $c->data;
					$pos = \strpos( $text, $this->post_id );

					// Check whether the comment defines a Image, Gallery, or Media-with-Text. Find only the start, therefore include '{'
					$isWpImage = 	 \strpos( $text, 'wp:image {' ) > 0 ? true : false; // Attention: works only if there is a space before 'wp:...'! Otherwise the result would be = 0
					$isWpGallery = 	 \strpos( $text, 'wp:gallery {' ) > 0 ? true : false;
					$isWpMediatext = \strpos( $text, 'wp:media-text {' ) > 0 ? true : false;
					
					// the wp-comment contains the post-id of the image, so do the replacement
					if ( false !== $pos && ( $isWpImage || $isWpGallery || $isWpMediatext) ) {
						$number_of_updates = $number_of_updates + 1;
						//$found = $index;
						//$foundtext = $text;
						
						// do the replacement here, because images could be used more than once in the post.
						$replaced_content = $this->replaceMetaInContent( $base_url, $replaced_content, $text, $isWpImage, $isWpGallery, $isWpMediatext );
					}

					// increment the counter for the figures in the post, assumes that every type includes one figure
					//if ( $isWpImage || $isWpGallery || $isWpMediatext)
					//	$index += 1;
					
				}

				// update the post in the database with the new content
				if ($replaced_content !== $post_content)
				{
					$sql = 'UPDATE ' . $wpdb->posts . ' SET post_content = %s WHERE ID = %d';
					$sql = $wpdb->prepare($sql, $replaced_content, $post_id);

					$result = $wpdb->query($sql);
		
					if ($result === false)
					{
						//Notice::addError('Something went wrong while replacing' .  $result->get_error_message() );
						//Log::addError('WP-Error during post update', $result);
						return 0;
					}
				} else {
					return 0;
				}

				// Change the post date on a post with a status other than 'draft', 'pending' or 'auto-draft'
				// We do this always, event if the content of the post was not changed, but maybe the image-file was changed. 
				$arg = array(
					'ID'            => $post_id,
					'post_date'     => $this->datetime,
					'post_date_gmt' => get_gmt_from_date( $this->datetime ),
				);
				$result = wp_update_post( $arg );
				wp_cache_delete( $post_id, 'posts' );
				
		  	}
		}
	
		//$number_of_updates += $this->handleMetaData($base_url, $search_urls, $replace_urls);
		return $number_of_updates;
	}
	  
	private function getFilesFromMetadata($meta)  {
			$fileArray = array();
			if (isset($meta['file']))
			  $fileArray['file'] = $meta['file'];
	
			if (isset($meta['sizes']))
			{
			  foreach($meta['sizes'] as $name => $data)
			  {
				if (isset($data['file']))
				{
				  $fileArray[$name] = $data['file'];
				}
			  }
			}
		  return $fileArray;
	} 
	
	/**
		* Replaces Content across several levels and types of possible data
		* @param $content String The Content to replace
		* @param string|array $search Search string or array
		* @param string|array $replace Replacement String or array 
		* @param bool $in_deep Boolean.  This is use to prevent serialization of sublevels. Only pass back serialized from top.
		* @return string $content the changed content of the post that uses the image
	*/
	private function replaceContent($content, $search, $replace, $in_deep = false) {
		//$is_serial = false;
		$content = maybe_unserialize($content);
		$isJson = $this->isJSON($content);

		if ($isJson) 
		{
			//Log::addDebug('Found JSON Content');
			$content = json_decode($content);
			//Log::addDebug('J/Son Content', $content);
		}

		if (is_string($content))  // let's check the normal one first.
		{
			//$content = apply_filters('emr/replace/content', $content, $search, $replace);
			$content = str_replace( $search, $replace, $content );
		}
		elseif (is_wp_error($content)) // seen this.
		{
			//return $content;  // do nothing.
		}
		elseif (is_array($content) ) // array metadata and such.
		{
			foreach($content as $index => $value)
			{
				$content[$index] = $this->replaceContent($value, $search, $replace, true); //str_replace($value, $search, $replace);
				if (is_string($index)) // If the key is the URL (sigh)
				{
					$index_replaced = $this->replaceContent($index, $search,$replace, true);
					if ($index_replaced !== $index)
						$content = $this->change_key($content, array($index => $index_replaced));
				}
			}
		}
		elseif (is_object($content)) // metadata objects, they exist.
		{
			foreach($content as $key => $value)
			{
				$content->{$key} = $this->replaceContent($value, $search, $replace, true); //str_replace($value, $search, $replace);
			}
		}

		if ($isJson && $in_deep === false) // convert back to JSON, if this was JSON. Different than serialize which does WP automatically.
		{
			//Log::addDebug('Value was found to be JSON, encoding');
			// wp-slash -> WP does stripslashes_deep which destroys JSON
			$content = json_encode($content, JSON_UNESCAPED_SLASHES);
			//Log::addDebug('Content returning', array($content));
		}
		elseif($in_deep === false && (is_array($content) || is_object($content)))
			$content = maybe_serialize($content);

		return $content;
  	}

	private function replaceMetaInContent( $base_url, $post_content, $foundtext, $isWpImage, $isWpGallery, $isWpMediatext ) {
	
		// get the target alt and caption
		$target_alt_caption = array(
			'alt_text' => $this->target_metadata['image_meta']['alt_text'],
			'caption' => $this->target_metadata['image_meta']['caption'],
		);

		// get the original html of the figure tag
		$comment_length = strlen( $foundtext );
		$comment_start =  strpos( $post_content, $foundtext );
		$comment_end = 0;

		if 		($isWpImage) {
			$comment_end = \strpos( $post_content, '/wp:image', $comment_start );
			$comment_length += strlen( '/wp:image' );
		}
		elseif	($isWpGallery) {
			$comment_end = \strpos( $post_content, '/wp:gallery', $comment_start );
			$comment_length += strlen( '/wp:gallery' );
		}
		elseif	($isWpMediatext) {
			$comment_end = \strpos( $post_content, '/wp:media-text', $comment_start );
			$comment_length += strlen( '/wp:media-text' );
		}
	
		$innerhtml = substr( $post_content, $comment_start+$comment_length+4, $comment_end-$comment_length );
		$newhtml = $innerhtml;

		// get the target alt and caption
		$source_alt_caption = $this->getAltCaption( $innerhtml );

		// and correct if for the gallery
		if ( $isWpGallery ) {
			$start = strpos( $innerhtml, $base_url);
			$altstart = strpos( $innerhtml, 'alt="', $start + strlen( $base_url) );
			$altend = strpos( $innerhtml, '"', $altstart+6);
			$source_alt_caption['alt_text'] = substr( $innerhtml, $altstart +5 , $altend - $altstart - 5);
			$figures = explode( '<figure>', $innerhtml);

			foreach ($figures as $f ) {
				$pos = \strpos( $f, $this->post_id );
				if ($pos > 0) {
					$source_alt_caption = $this->getAltCaption( $f );
					$innerhtml = $f;
					break;
				}
			}
			// do the replacement for the wp:gallery
			if ( ! is_null( $target_alt_caption['caption'] ) )
				$newhtml = \str_replace( $source_alt_caption['caption'] . '</figcaption>', $target_alt_caption['caption'] . '</figcaption>', $innerhtml);
			if ( ! is_null( $target_alt_caption['alt_text'] ) )
				$newhtml = \str_replace( 'alt="' . $source_alt_caption['alt_text'] . '"', 'alt="' . $target_alt_caption['alt_text'] . '"', $newhtml);
		}

		// do the replacement
		if ( ! is_null( $target_alt_caption['alt_text'] ) && ( ! $isWpGallery ) )
			$newhtml = \str_replace( 'alt="' . $source_alt_caption['alt_text'] . '"', 'alt="' . $target_alt_caption['alt_text'] . '"', $innerhtml);
			
		if ( ! is_null( $target_alt_caption['caption'] ) && $isWpImage ) {
			if ( strlen($source_alt_caption['caption']) > 0 ) {
				$newhtml = \str_replace( $source_alt_caption['caption'] . '</figcaption>', $target_alt_caption['caption'] . '</figcaption>', $newhtml);
			} elseif ( $isWpImage ) {
				$newcaption = '<figcaption>' . $target_alt_caption['caption'] . '</figcaption></figure>'; 
				$newhtml = \str_replace( '</figure>', $newcaption, $newhtml);
			}
		}

		
	
		// finally replace the figure content		
		$replaced_content = str_replace( $innerhtml, $newhtml, $post_content); 
		return $replaced_content;
	}  

  	/* Check if given content is JSON format. */
	private function isJSON($content) {
		if (is_array($content) || is_object($content))
			return false; // can never be.

		$json = json_decode($content);
		return $json && $json != $content;
	}

	private function change_key($arr, $set) {
        if (is_array($arr) && is_array($set)) {
    		$newArr = array();
    		foreach ($arr as $k => $v) {
    		    $key = array_key_exists( $k, $set) ? $set[$k] : $k;
    		    $newArr[$key] = is_array($v) ? $this->change_key($v, $set) : $v;
    		}
    		return $newArr;
    	}
    	return $arr;
  	}

	private function handleMetaData($url, $search_urls, $replace_urls) {
		global $wpdb;
	
		$meta_options = array('post', 'comment', 'term', 'user');
		$number_of_updates = 0;
	
		foreach($meta_options as $type)
		{
			switch($type)
			{
			  case "post": // special case.
				  $sql = 'SELECT meta_id as id, meta_key, meta_value FROM ' . $wpdb->postmeta . '
					WHERE post_id in (SELECT ID from '. $wpdb->posts . ' where post_status = "publish") AND meta_value like %s';
				  $type = 'post';
	
				  $update_sql = ' UPDATE ' . $wpdb->postmeta . ' SET meta_value = %s WHERE meta_id = %d';
			  break;
			  default:
				  $table = $wpdb->{$type . 'meta'};  // termmeta, commentmeta etc
	
				  $meta_id = 'meta_id';
				  if ($type == 'user')
					$meta_id = 'umeta_id';
	
				  $sql = 'SELECT ' . $meta_id . ' as id, meta_value FROM ' . $table . '
					WHERE meta_value like %s';
	
				  $update_sql = " UPDATE $table set meta_value = %s WHERE $meta_id  = %d ";
			  break;
			}
	
			$sql = $wpdb->prepare($sql, '%' . $url . '%');
	
			// This is a desparate solution. Can't find anyway for wpdb->prepare not the add extra slashes to the query, which messes up the query.
			//    $postmeta_sql = str_replace('[JSON_URL]', $json_url, $postmeta_sql);
			$rsmeta = $wpdb->get_results($sql, ARRAY_A);
	
			if (! empty($rsmeta))
			{
			  foreach ($rsmeta as $row)
			  {
				$number_of_updates++;
				$content = $row['meta_value'];
	
	
				$id = $row['id'];
	
			   $content = $this->replaceContent($content, $search_urls, $replace_urls); //str_replace($search_urls, $replace_urls, $content);
	
			   $prepared_sql = $wpdb->prepare($update_sql, $content, $id);
	
			   //Log::addDebug('Update Meta SQl' . $prepared_sql);
			   $result = $wpdb->query($prepared_sql);
	
			  }
			}
		} // foreach
	
		return $number_of_updates;
	} // function  

	/**
	 * Take the html-object and extract alt_text and caption of the figure tag
	 *
	 * @param string $html html-string representation of the html-code using the wp image from the Mediacatalog
	 * @return array array with the current alt_text and caption of the post with the image
	 */
	private function getAltCaption ( string $html ) {
		// find the alt attribute content, assuming that there is only one.
		$patternstart = 'alt="';
		$patternend   = '"';
		$offset = 6;

		// TODO: clean the alt and caption if there are special characters
		$start =  strpos( $html, $patternstart );
		$stop  =  strpos( $html, $patternend, $start + $offset );
		
		$alttext = \utf8_decode( substr( $html, $start + $offset -1, $stop - $start - $offset +1 ) );
		$alttext2 = ( substr( $html, $start + $offset -1, $stop - $start - $offset +1 ) );
		
		$patternstart = '<figcaption';
		$patternend   = '</figcaption>';
		$offset = 13;

		$start =  strpos( $html, $patternstart );
		$start =  strpos( $html, '>', $start );
		$stop  =  strpos( $html, $patternend  );
		
		$caption = \utf8_decode( substr( $html, $start +1 , $stop - $start -1  ) );
		$caption2 = ( substr( $html, $start +1 , $stop - $start -1  ) );

		return array(
			'alt_text' => $alttext2,
			'caption' => $caption2 );
	}
	
}