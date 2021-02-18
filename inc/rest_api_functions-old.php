<?php

namespace mvbplugins\extmedialib;

//---------------- helper functions ----------------------------------------------------

/** 
	* select only the original files that were NOT added yet to WP-Cat from THIS $folder, not from all folders
	*
	* @return array the original-files in the given $folder that were NOT added to WP-Cat yet 
	*/
function get_files_to_add( $folder ) {
	$result = array();
	$all = glob( $folder . '/*' );
	$i=0;
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];
	$dir = str_replace('\\','/',$dir);
	$dir = str_replace('\\\\','/',$dir);
	$dir = str_replace('//','/',$dir);
	$url = $upload_dir['baseurl'];
	
	foreach ($all as &$file) {
		$test=$file;
		if ( (! preg_match_all('/[0-9]+x[0-9]+/',$test)) and (! strstr($test, '-'. EXT_SCALED)) and (! is_dir($test)) ) {
			// Check if one of the files in $result was already attached to WPCat
			$file = str_replace($dir,$url,$file);
			$addedbefore = attachment_url_to_postid($file);
			
			if ( empty($addedbefore) ) {
				$ext = '.' . pathinfo($file)['extension'];
				$file = str_replace($ext,'-' . EXT_SCALED . $ext, $file);
				$addedbefore = attachment_url_to_postid($file);
			}
			
			if ( empty($addedbefore) ) {
				$result[$i] = $test;
				$i = $i +1;
			}
			
		}
	}
	return $result;
}

/** 
	* return the original files that were already added to WP-Cat from THIS $folder
	*
	* @return array the original-files in the given $folder that were NOT added to WP-Cat yet 
	*/
function get_added_files_from_folder( $folder ) {
	$result = array();
	$all = glob( $folder . '/*' );
	$i=0;
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];
	$dir = str_replace('\\','/',$dir);
	$dir = str_replace('\\\\','/',$dir);
	$dir = str_replace('//','/',$dir);
	$url = $upload_dir['baseurl'];
	
	foreach ($all as &$file) {
		$test=$file;
		if ( (! preg_match_all('/[0-9]+x[0-9]+/',$test)) and (! strstr($test, '-'. EXT_SCALED)) and (! is_dir($test)) ) {
			// Check if one of the files in $result was already attached to WPCat
			$file = str_replace($dir,$url,$file);
			$addedbefore = attachment_url_to_postid($file);
			
			if ( empty($addedbefore) ) {
				$ext = '.' . pathinfo($file)['extension'];
				$file = str_replace($ext,'-' . EXT_SCALED . $ext, $file);
				$addedbefore = attachment_url_to_postid($file);
			}
			
			if ( empty($addedbefore) ) {
				$addedbefore = 0;
			}
			$result[$i]['id'] = $addedbefore;
			$result[$i]['file'] = $file;
			$i = $i +1;
			
		}
	}
	return $result;
}

/** 
	* special replace for foldernames $string '_ . ? * \ / space' to '-'
	*
	* @return string the string with replacments
	*/
function special_replace( $string )
{
	$result = str_replace('_','-',$string);
	$result = str_replace('.','-',$result);
	$result = str_replace('?','-',$result);
	$result = str_replace('*','-',$result);
	$result = str_replace('\\','-',$result);
	$result = str_replace('/','-',$result);
	$result = str_replace('\s','-',$result);
	return $result;
}

// function to update meta-data given by $post_ID (int) and new metadata (array)
/**
	 * update image_meta (only keywords, credit, copyright, caption, title)
	 *
	 * @param int $post_id ID of the attachment in the WP-Mediacatalog
	 *
	 * @param array $newmeta array with newmeta data taken from the JSON-data in the POST-Request body
	 * 
	 * @return bool true if success, false if not: ouput of the WP function to update attachment metadata
	 */
function update_metadata($post_id, $newmeta) {
	/** JSON has to be formatted like that:
	{
		"image_meta": {
					"credit": "Martin von Berg",
					"caption": "TEst-caption",
					"copyright": "Copyright by Martin von Berg",
					"title": "Auffahrt zum Vallone d`Urtier",
					"keywords": [
						"Aosta",
						"Aostatal",
						"Berge",
						"Bike",
						"Italien",
						"Sommer",
						"Wald",
						"Wiese",
						"forest",
						"italy",
						"lärche",
						"meadow",
						"mountains",
						"summer"
					]
				}
		}
	*/	
	// get and check current Meta-Data from WP-database
	$meta = wp_get_attachment_metadata($post_id); 
	if (array_key_exists('image_meta', $newmeta) ) {
		$newmeta = $newmeta['image_meta'];
		
		// organize metadata
		array_key_exists('keywords', $newmeta)  ? $meta["image_meta"]["keywords"]  = $newmeta['keywords'] : '' ; // Copy Keywords. GPS is missing. Does matter: is not used in WP
		array_key_exists('credit', $newmeta)    ? $meta["image_meta"]["credit"]    = $newmeta["credit"] : ''     ;		// GPS is updated via file-update
		array_key_exists('copyright', $newmeta) ? $meta["image_meta"]["copyright"] = $newmeta["copyright"] : '' ;
		array_key_exists('caption', $newmeta)   ? $meta["image_meta"]["caption"]   = $newmeta["caption"] : ''   ;
		array_key_exists('title', $newmeta)     ? $meta["image_meta"]["title"]     = $newmeta["title"]  : ''     ;
	}

	// write metadata
	$success = wp_update_attachment_metadata($post_id, $meta); // write new Meta-data to WP SQL-Database
	
	return $success;
	}
