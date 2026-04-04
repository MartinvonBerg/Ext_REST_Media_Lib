<?php
/**
 * Class for the replacment of images in a post. Taken from plugin enable_media_replace.
 *
 * PHP version 8.0.0 - 8.5.0
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
	protected int $post_id;
	
	// everything source is the attachment being replaced
	protected emrFile $sourceFile; // File Object
	protected ?\WP_Post $source_post; // wpPost;
	protected bool $source_is_image;
	/** @var array<string, mixed> */
	protected array $source_metadata = [];
	protected string $source_url = '';

	// everything target is what will be. This is set when the image is replaced, the result. Used for replacing.
	protected ?emrFile $targetFile = null;
	protected string $targetName = '';
	/** @var array<string, mixed> */
	public array $target_metadata = [];
	public string $target_url = '';
	protected bool $target_location = false; // option for replacing to another target location
	
	// old settings moved to class attributes
	protected string $replace_type = '';
	protected bool $do_new_location = false;
	public string $new_location_dir = '';
	protected bool $docaption = false;
	protected string $oldlink = '';
	protected string $newlink = '';

	protected int $replaceMode = 1; // replace if nothing is set
	protected int $timeMode = 1;
	protected ?string $datetime = null;

	protected mixed $ThumbnailUpdater = null; // class

	const MODE_REPLACE = 1;
	const MODE_SEARCHREPLACE = 2;

	const TIME_UPDATEALL = 1; // replace the date
	const TIME_UPDATEMODIFIED = 2; // keep the date, update only modified
	const TIME_CUSTOM = 3; // custom time entry

	public function __construct( int $post_id ) {
		$this->post_id = $post_id;

		if (function_exists('wp_get_original_image_path')) // WP 5.3+
		{
			$source_file = wp_get_original_image_path($post_id);
			if ($source_file === false) { // if it's not an image, returns false, use the old way.
				$attached_file = get_attached_file($post_id, apply_filters( 'wp_handle_replace', true ));
				$source_file = is_string( $attached_file ) ? trim( $attached_file ) : '';
			}
		}
		else {
			$attached_file = get_attached_file($post_id, apply_filters( 'wp_handle_replace', true ));
			$source_file = is_string( $attached_file ) ? trim( $attached_file ) : '';
		}

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
		$source_metadata = wp_get_attachment_metadata( $post_id );
		if ( $source_metadata === false ) {
			$this->source_metadata = [];
		} else {
			$this->source_metadata = $source_metadata;
		}

		if (function_exists('wp_get_original_image_url')) // WP 5.3+
		{
			$source_url = wp_get_original_image_url($post_id);
			if ($source_url === false)  // not an image, or borked, try the old way
				$source_url = wp_get_attachment_url($post_id);

			$this->source_url = is_string( $source_url ) ? $source_url : '';
		}
		else {
			$source_url = wp_get_attachment_url($post_id);
			$this->source_url = is_string( $source_url ) ? $source_url : '';
		}
  	}

	public function set_oldlink ( string $link ) : void {
		$this->oldlink = $link;
	}

	public function set_newlink ( string $link ) : void {
		$this->newlink = $link;
	}

	public function API_doSearchReplace () : void {
		// settings from upload.php 
		$this->replace_type = 'replace_and_search';
		$this->do_new_location = false;
		
		$args = array(
			'thumbnails_only' => false, // means resized images only
		);
  
		// Search Replace will also update thumbnails.
		$this->setTimeMode( static::TIME_UPDATEMODIFIED );  
		$this->doSearchReplace( $args ); 

		// if all set and done, update the date. This updates the date of the image in the media lib only.
      	// This must be done after wp_update_posts. 
		$this->updateDate(); //  
	}

	/**
	 * Summary of API_doMetaUpdate
	 * @param array $newmeta
	 * @param bool $dothecaption
	 * @return int the number of updated posts.
	 */
	/**
	 * @param array<string, mixed> $newmeta
	 */
	public function API_doMetaUpdate( array $newmeta, bool $dothecaption ) : int {

		$this->docaption = $dothecaption;

		// prepare the metadata from the REST POST request
		$this->target_metadata = $this->source_metadata; 
		$newmeta = $newmeta['image_meta'];
		$target_meta = [];

		// get the current alt_text of the image
		$source_alt_text = get_post_meta( $this->post_id, '_wp_attachment_image_alt', true) ?? '' ;
		$this->source_metadata['image_meta']['alt_text'] = $source_alt_text;

		// sanitize the input
		array_key_exists('alt_text',  $newmeta) ? '' : $newmeta['alt_text'] = '' ;
		array_key_exists('caption',  $newmeta) ? '' : $newmeta['caption'] = '' ;
		$newmeta['alt_text'] = filter_var( $newmeta['alt_text'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW );
		$newmeta['caption'] = filter_var( $newmeta['caption'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW );
		$target_meta['alt_text'] = array_key_exists('alt_text', $newmeta) ? (string) $newmeta['alt_text'] : '';
		$target_meta['caption'] = array_key_exists('caption',  $newmeta) ? (string) $newmeta['caption'] : '';
		$this->target_metadata['image_meta']['caption'] = $target_meta['caption'];
		$this->target_metadata['image_meta']['alt_text'] = $target_meta['alt_text'];

		// get the directory in the uploads folder that contains the image 
		$baseurl = \mvbplugins\extmedialib\get_upload_url() ; 
		$gallerydir = \str_replace( $baseurl, '', $this->source_url );
		$file = array_key_exists('original_image',$this->source_metadata) ? $this->source_metadata['original_image'] : null; 
		if ( ! $file ) $file = $this->sourceFile->getFileName();

		$gallerydir = str_replace( $file, '', $gallerydir );
		$gallerydir = rtrim( $gallerydir, '/');
		$gallerydir = ltrim( $gallerydir, '/');

		$this->new_location_dir = $gallerydir;
		$this->target_url = $this->source_url;
		
		// settings from original /view/upload.php from media-replacer-class.
		$this->replace_type = 'replace_and_search';
		$this->do_new_location = false;
		
		// Search Replace will also update thumbnails.
		$this->setTimeMode( static::TIME_UPDATEMODIFIED );

		// Search-and-replace filename in post database
		// EMR comment: "Check this with scaled images." 
		// This comment is from the original code from enable_media_replacer and tested with scaled images
		$base_url = parse_url( $this->source_url, PHP_URL_PATH );// emr_get_match_url( $this->source_url);
		$base_url = is_string( $base_url ) ? $base_url : '';
		$base_url = str_replace('.' . pathinfo($base_url, PATHINFO_EXTENSION), '', $base_url );
		
		// replace-run for the baseurl only
		$updated = $this->doMetaReplaceQuery( $base_url );
		return $updated;	
	}

	/**
	 * @param array{thumbnails_only: bool} $args
	 */
	protected function doSearchReplace( array $args = array( 'thumbnails_only' => false ) ) : int {
		
		// Search-and-replace filename in post database
		// EMR comment: "Check this with scaled images." 
		// This comment is from the original code from enable_media_replacer and tested with scaled images
		$base_url = parse_url($this->source_url, PHP_URL_PATH);// emr_get_match_url( $this->source_url);
		$base_url = is_string( $base_url ) ? $base_url : '';
		$base_url = str_replace('.' . pathinfo($base_url, PATHINFO_EXTENSION), '', $base_url);

		/** Fail-safe if base_url is a whole directory, don't go search/replace */
		if (is_dir($base_url)) {
			//Log::addError('Search Replace tried to replace to directory - ' . $base_url);
			//Notices::addError(__('Fail Safe :: Source Location seems to be a directory.', 'enable-media-replace'));
			return 0;
		}

		if (strlen(trim($base_url)) === 0) {
			//Log::addError('Current Base URL emtpy - ' . $base_url);
			//Notices::addError(__('Fail Safe :: Source Location returned empty string. Not replacing content','enable-media-replace'));
			return 0;
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
		// add the link at the and of the arrays
		$search_urls['link'] = $this->oldlink;
		$replace_urls['link'] = $this->newlink;

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
		$post_date = $this->datetime ?? current_time('mysql');
		$post_date_gmt = get_gmt_from_date($post_date);
	
		$update_ar = array('ID' => $this->post_id);
		if ($this->timeMode == static::TIME_UPDATEALL || $this->timeMode == static::TIME_CUSTOM)
		{
			$update_ar['post_date'] = $post_date;
			$update_ar['post_date_gmt'] = $post_date_gmt;
		}
		else {
			//$update_ar['post_date'] = 'post_date';
			//$update_ar['post_date_gmt'] = 'post_date_gmt';
		}
		$update_ar['post_modified'] = $post_date;
		$update_ar['post_modified_gmt'] = $post_date_gmt;
	
		$updated = $wpdb->update( $wpdb->posts, $update_ar , array('ID' => $this->post_id) );
	
		wp_cache_delete($this->post_id, 'posts');
  
	}

	public function setTimeMode( int $mode, int|string $datetime = 0 ) : void
	{
		if ($datetime == 0) {
			$datetime = current_time('mysql');
		}
		if ( is_int( $datetime ) ) {
			$datetime = (string) $datetime;
		}

		$this->datetime = $datetime;
		$this->timeMode = $mode;
	}

	// Get REL Urls of both source and target.
	/**
	 * @return array<string, array<string, string>>
	 */
	private function getRelativeURLS() : array
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
			$baseurl = is_string( $baseurl ) ? $baseurl : '';
			$result[$index]['base'] = $baseurl;  // this is the relpath of the mainfile.
			$baseurl = trailingslashit(str_replace( wp_basename($item['url']), '', $baseurl)); // get the relpath of main file.
  
			foreach($metadata as $name => $filename)
			{
				$result[$index][$name] =  $baseurl . wp_basename($filename); // filename can have a path like 19/08 etc.
			}
  
		}
		return $result;
	}

	private function findNearestSize( string $sizeName ) : string|false {
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
	 * TODO: Update this according to doMetaReplaceQuery() or combine the two functions
	 * @param string $base_url
	 * @param array<string, string> $search_urls
	 * @param array<string, string> $replace_urls
	 * @return int $number_of_updates
	 */
	private function doReplaceQuery( string $base_url, array $search_urls, array $replace_urls ) : int
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
		  foreach ( $rs as $rows ) {
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
					continue;
				}
			}

			// Change the post date on a post with a status other than 'draft', 'pending' or 'auto-draft'
			// We do this always, event if the content of the post was not changed, but maybe the image-file was changed. And we are here after several checks of the REST-API.
			$post_modified = $this->datetime ?? current_time('mysql');
			$arg = array(
				'ID'            => $post_id,
				//'post_date'     => $this->datetime, // this changed the published date, too, so keep it commented out.
				'post_modified_gmt' => get_gmt_from_date( $post_modified ), // was before 'post_date_gmt' : changed the published date.
			);
			$result = wp_update_post( $arg );
			wp_cache_delete( $post_id, 'posts' );
	
		  }
		}
	
		$number_of_updates += $this->handleMetaData($base_url, $search_urls, $replace_urls);
		return $number_of_updates;
	}

	/**
	 * search for $base_url in the database and replace metadata 'alt' and 'caption' in the code of the post.
	 * This updates the metadata of the image in the possible gutenberg blocks only! It is not working for general HTML using the image tag!
	 * Note: It was already preselected that this post contains the image in the possible gutenberg blocks.
	 * 
	 * @param string $base_url
	 * @return int $number_of_updates
	 */
	private function doMetaReplaceQuery( string $base_url ): int
	{
		global $wpdb;
		/* Search and replace in WP_POSTS */
		$posts_sql = $wpdb->prepare(
		  "SELECT ID, post_content FROM $wpdb->posts WHERE post_status = 'publish' AND post_content LIKE %s",
		  '%' . $base_url . '%');
	
		$rs = $wpdb->get_results( $posts_sql, ARRAY_A );
		$number_of_updates = 0;

		// get the target alt and caption
		$target_alt_caption = [
			'alt_text' => $this->target_metadata['image_meta']['alt_text'],
			'caption' => $this->target_metadata['image_meta']['caption'],
		];
	
		foreach ( $rs as $row ) {
			$post_content = $row['post_content'];
			$replaced_content = $post_content;
			$post_id = (int) $row['ID'];
			$blocks = parse_blocks($post_content);
			// --- start replace content

			foreach ($blocks as &$block) { // Das '&' ist wichtig, damit die Änderungen auch im Array $blocks vorgenommen werden.

				if ($block['blockName'] === 'core/image') {

					if ( $block['attrs']['id'] === intval($this->post_id) ) {
						$source_alt_caption = $this->getAltCaption( $block['innerHTML'] );

						// do the replacement for the wp:image 
						$newhtml = $block['innerHTML'];

						if ( ( $target_alt_caption['alt_text'] !== '' ) )
							$newhtml = \str_replace( 'alt="' . $source_alt_caption['alt_text'] . '"', 'alt="' . $target_alt_caption['alt_text'] . '"', $newhtml);
						
						if ( ( $target_alt_caption['caption'] !== '' ) && $this->docaption )
							$newhtml = \str_replace( $source_alt_caption['caption'] . '</figcaption>', $target_alt_caption['caption'] . '</figcaption>', $newhtml);
						
						// update the innerHTML and innerContent of the block.
						// check if the innerContent exists in [0]
						if ( isset($block['innerContent'][0]) && !empty($block['innerContent'][0]) && $block['innerContent'][0] === $block['innerHTML'] ) {
							$block['innerContent'][0] = $newhtml;
						}
						$block['innerHTML'] = $newhtml;
						
						// do only if attr alt and caption is set already in the block
						if ( \array_key_exists( 'alt', $block['attrs']) ) {
							$block['attrs']['alt'] = $this->target_metadata['image_meta']['alt_text'];
						}
						
						if ( $this->docaption && \array_key_exists( 'caption', $block['attrs']) ) {
								$block['attrs']['caption'] = $this->target_metadata['image_meta']['caption'];
						}
					} 
				}
				
				if ($block['blockName'] === 'core/gallery') {

					// loop through all images in innerBlocks in the gallery. These are 'core/image' blocks
					foreach ($block['innerBlocks'] as &$innerBlock) {

						if ($innerBlock['blockName'] === 'core/image') {
							if ( $innerBlock['attrs']['id'] === intval($this->post_id) ) {
								$source_alt_caption = $this->getAltCaption( $innerBlock['innerHTML'] );
								
								// do the replacement for the wp:image 
								$newhtml = $innerBlock['innerHTML'];

								if ( ( $target_alt_caption['alt_text'] !== '' ) )
									$newhtml = \str_replace( 'alt="' . $source_alt_caption['alt_text'] . '"', 'alt="' . $target_alt_caption['alt_text'] . '"', $newhtml);
								
								if ( ( $target_alt_caption['caption'] !== '' ) && $this->docaption )
									$newhtml = \str_replace( $source_alt_caption['caption'] . '</figcaption>', $target_alt_caption['caption'] . '</figcaption>', $newhtml);
								
								// update the innerHTML and innerContent of the block.
								// check if the innerContent exists in [0]
								if ( isset($innerBlock['innerContent'][0]) && !empty($innerBlock['innerContent'][0]) && $innerBlock['innerContent'][0] === $innerBlock['innerHTML'] ) {
									$innerBlock['innerContent'][0] = $newhtml;
								}
								$innerBlock['innerHTML'] = $newhtml;
								

								// do only if attr alt and caption is set already in the block
								if ( \array_key_exists( 'alt', $innerBlock['attrs']) ) {
									$innerBlock['attrs']['alt'] = $this->target_metadata['image_meta']['alt_text'];
								}
								
								if ( $this->docaption && \array_key_exists( 'caption', $innerBlock['attrs']) ) {
										$innerBlock['attrs']['caption'] = $this->target_metadata['image_meta']['caption'];
								}
							}
						}	
					}
				}

				if ($block['blockName'] === 'core/media-text') {

					if ( $block['attrs']['mediaId'] === intval($this->post_id) && $block['attrs']['mediaType'] === 'image' ) {
						$source_alt_caption = $this->getAltCaption( $block['innerHTML'] );
						// get the target alt and caption
						
						// do the replacement for the wp:image 
						$newhtml = $block['innerHTML'];

						if ( ( $target_alt_caption['alt_text'] !== '' ) )
							$newhtml = \str_replace( 'alt="' . $source_alt_caption['alt_text'] . '"', 'alt="' . $target_alt_caption['alt_text'] . '"', $newhtml);
						
						// update the innerHTML and innerContent of the block.
						foreach ($block['innerContent'] as &$innerContent) {
							if ( !empty($innerContent) && \str_contains($innerContent, 'img') ) {
								$innerContent = \str_replace( 'alt="' . $source_alt_caption['alt_text'] . '"', 'alt="' . $target_alt_caption['alt_text'] . '"', $innerContent);
							}
						}
							
						$block['innerHTML'] = $newhtml;

						// do only if attr alt and caption is set already in the block
						if ( \array_key_exists( 'alt', $block['attrs']) ) {
							$block['attrs']['alt'] = $this->target_metadata['image_meta']['alt_text'];
						}
					}
				}
				
			}

			$replaced_content = serialize_blocks($blocks);
			// -- end replace content

			// update the post in the database with the new content

			if ( $replaced_content !== $post_content ) 
			{
				$sql = 'UPDATE ' . $wpdb->posts . ' SET post_content = %s WHERE ID = %d';
				$sql = $wpdb->prepare($sql, $replaced_content, $post_id);
				$result = $wpdb->query($sql);
	
				if ($result === false)
				{
					continue;
				}

				// Change the post date on a post with a status other than 'draft', 'pending' or 'auto-draft'
				$post_modified = $this->datetime ?? current_time('mysql');
				$arg = [
					'ID'                => $post_id,
					'post_modified_gmt' => get_gmt_from_date( $post_modified ), // was before 'post_date_gmt' : changed the published date.
					];
				
				$result = wp_update_post( $arg, true );
				wp_cache_delete( $post_id, 'posts' );

				if ( !is_wp_error( $result ) ) 
				{
					$number_of_updates +=1;
				}
			}
		}
	
		return $number_of_updates;
	}
	  
	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, string>
	 */
	private function getFilesFromMetadata( array $meta ) : array {
			$fileArray = array();
			if (isset($meta['file']) && is_string($meta['file']))
			  $fileArray['file'] = $meta['file'];
	
			if (isset($meta['sizes']) && is_array($meta['sizes']))
			{
			  foreach($meta['sizes'] as $name => $data)
			  {
				if (is_string($name) && is_array($data) && isset($data['file']) && is_string($data['file']))
				{
				  $fileArray[$name] = $data['file'];
				}
			  }
			}
		  return $fileArray;
	} 
	
	/**
		* Replaces Content across several levels and types of possible data
		* @param string $content String The Content to replace
		* @param string|array $search Search string or array
		* @param string|array $replace Replacement String or array 
		* @param bool $in_deep Boolean.  This is use to prevent serialization of sublevels. Only pass back serialized from top.
		* @return string $content the changed content of the post that uses the image
	*/
	/**
	 * @param string|array<string, string> $search
	 * @param string|array<string, string> $replace
	 * @return mixed
	 */
	private function replaceContent( mixed $content, string|array $search, string|array $replace, bool $in_deep = false ) : mixed {
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
			foreach(get_object_vars($content) as $key => $value)
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

  	/* Check if given content is JSON format. */
	private function isJSON( mixed $content ) : bool {
		if ( ! is_string( $content ) )
			return false; // can never be.

		$json = json_decode($content);
		return $json && $json != $content;
	}

	/**
	 * @param array<mixed, mixed> $arr
	 * @param array<mixed, mixed> $set
	 * @return array<mixed, mixed>
	 */
	private function change_key( array $arr, array $set ) : array {
		$newArr = array();
		foreach ($arr as $k => $v) {
			$key = array_key_exists( $k, $set) ? $set[$k] : $k;
			$newArr[$key] = is_array($v) ? $this->change_key($v, $set) : $v;
		}
		return $newArr;
  	}

	/**
	 * @param array<string, string> $search_urls
	 * @param array<string, string> $replace_urls
	 */
	private function handleMetaData( string $url, array $search_urls, array $replace_urls ) : int {
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
	 * Take the html-string and extract alt_text and caption of the figure tag where we assume that alt and caption only exists once!
	 * If not found or more than once, return null for alt_text or caption. HTML Tags of the figcaption are NOT removed.
	 *
	 * @param string $html html-string representation of the html-code using the wp image from the Mediacatalog
	 * @return array array with the current alt_text and caption of the post with the image
	 */
	/**
	 * @return array{alt_text: ?string, caption: ?string}
	 */
	private function getAltCaption ( string $html ) : array {
		// find the alt attribute content, assuming that there is only one.
		$alttext2 = null;
		$caption2 = null;

		// --- ALT via regex ---
		preg_match_all('/alt\s*=\s*["\']([^"\']*)["\']/i', $html, $matches);
		// set only if there is exactly one match
		if ( \count($matches[1]) === 1 ) {
			$alttext2 = $matches[1][0];
		}
		
		// --- CAPTION via regex ---
		preg_match_all('/<figcaption\b[^>]*>(.*?)<\/figcaption>/is', $html, $matches);
		// set only if there is exactly one match
		if ( \count($matches[1]) === 1 ) {
			$caption2 = $matches[1][0];
	
		}

		return array(
			'alt_text' => $alttext2,
			'caption' => $caption2 );
	}
	
}