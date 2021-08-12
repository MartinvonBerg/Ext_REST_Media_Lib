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

	public function API_doMetaUpdate() {
		// settings 
		$baseurl = wp_upload_dir()['baseurl'];
		$gallerydir = \str_replace( $baseurl, '', $this->source_url );
		$file = $this->source_metadata['original_image'];
		$gallerydir = str_replace( $file, '', $gallerydir );
		$gallerydir = rtrim( $gallerydir, '/');
		$gallerydir = ltrim( $gallerydir, '/');

		$this->new_location_dir = $gallerydir;
		$this->target_url = $this->source_url;
		$this->target_metadata = $this->source_metadata;

		// settings from upload.php 
		$this->replace_type = 'replace_and_search';
		$this->timestamp_replace = 1;
		$this->do_new_location = false;
		
		$args = array(
			'thumbnails_only' => false, // means resized images only
		);
	
		// Search Replace will also update thumbnails.
		$this->setTimeMode( $this->timestamp_replace );

		// Search-and-replace filename in post database
		// TODO: Check this with scaled images. This is from the original code from enable_media_replacer
		$base_url = parse_url($this->source_url, PHP_URL_PATH);// emr_get_match_url( $this->source_url);
		$base_url = str_replace('.' . pathinfo($base_url, PATHINFO_EXTENSION), '', $base_url);
		$search_urls = array();
		$replace_urls = array();

		// replace-run for the baseurl only
		$updated = $this->doMetaReplaceQuery( $base_url );
		
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
	 * search for $base_url in the database and replace the search_urls with replace_urls
	 *
	 * @param string $base_url
	 * @return int $number_of_updates
	 */	  
	private function doMetaReplaceQuery( $base_url )
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

				// find the images
				$html = $rs[0]['post_content'];

				$dom=new \domDocument;
				$dom->loadHTML($html);

				$figures = $dom->getElementsByTagName('figure'); // every image has to be a figure, works only with gutenberg

				$xpath = new \DOMXpath($dom);
				$comments = $xpath->query("//comment()");

				$index = 0;
				foreach ( $comments as $c ) { // every image has to be in a htmls comment , works only with gutenberg
					$text = $c->data;
					$pos = \strpos( $text, $this->post_id );
					if ( false !== $pos ) {
						$found = $index;
					}
					$index += 1;
				}
				$innerhtml = $figures[ $found ];
				// get the alt and caption
				$result = $this->getAltCaption( $innerhtml );

				// get and check current Meta-Data from WP-database.
				$meta = wp_get_attachment_metadata( $this->$post_id );
				
				$replaced_content = $post_content;
	
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

	private function getAltCaption ( $html ) {
		foreach ( $html->childNodes as $child) {
			$tag = $child->tagName;
			if ( $tag == 'figcaption') ''; {
				$caption = $child->textContent;
			}
			if ( $tag == 'img') ''; {
				$alt = $child->attributes;
				foreach ( $alt as $a ) {
					if ( $a->name == 'alt') {
						$alttext = $a->textContent;
					}
				}
			}		
		}
		return array(
			'alt' => $alttext,
			'caption' => $caption );
	}
	
}