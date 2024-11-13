<?php
namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

add_filter('intermediate_image_sizes_advanced', 'mvbplugins\extmedialib\image_subsizes_filter', 10, 3);

/**	
 * filter the image sizes to be created by WP
 * @param array $newsizes the registered image subsizes as provided by get_registered_subsizes
 * @param array $image_meta the imagemetadata with sizes
 * @param int $wp_id the wp id of the attachment
 *
 * @return array $newsizes	if this is empty no subsizes will be created by _wp_make_subsizes()
 */
function image_subsizes_filter($newsizes, $image_meta, $wp_id) {
	// get filename from $image_meta
	$file = $image_meta["file"]; // Mind! : the file name here is added with "-1" even if only subsizes exist already! So, the uploading of subsizes does not work for the WP Standard Cat.
	$expectedSizes = generate_wp_image_subsizes($file, $newsizes);
	if ( empty( $expectedSizes ) ) {
        #error_log( "image_subsizes_filter: expectedSizes: empty"  );
        return $newsizes;
    }
    #error_log( "image_subsizes_filter: expectedSizes: " . json_encode($expectedSizes) );

	foreach ( $newsizes as $new_size_name => $new_size_data ) {
		// generate the expected image filename for the subsizes from array $newsizes respecting the WP standards for width, height and crop
		$new_size_meta = $expectedSizes[ $new_size_name ]; 

		if ( isset( $new_size_meta['exists'] ) && $new_size_meta['exists'] ) {
			// Save the size meta value.
			unset( $new_size_meta['exists'] );
			$image_meta['sizes'][ $new_size_name ] = $new_size_meta;
			// example $new_size_meta = []
			#		file = "Elba-010-2560-1-300x200.avif"
			#		width = 300
			#		height = 200
			#		mime-type = "image/avif"
			#		filesize = 10578
			wp_update_attachment_metadata( $wp_id, $image_meta );
			#error_log( "image_subsizes_filter: unset: " . json_encode($newsizes[ $new_size_name ]) );
			unset($newsizes[ $new_size_name ]); 
		}
	}
	// if this is empty no subsizes will be created by _wp_make_subsizes()
	// this works only if ALL subsizes are available, so if only some are missing the metadata is not created correctly
    #error_log( "image_subsizes_filter: newsizes: " . json_encode($newsizes) );
	return $newsizes; 
}

/**
 * Generates the expected image filenames and filepaths as string for the subsizes from the array newsizes
 * "crop" is a boolean that decides wether the image should be cropped or not. If crop is true the new filename (and dimensions) will be exactly cropped to width and heigt.
 * If crop is false the larger value of the image (be it width or height respecting orientation) will remain unchanged and the shorter value (be it height or widht respecting orientation) will be set according to the aspect ratio.
 * @param mixed $original_path the original image path
 * @param mixed $newsizes   the array of subsizes
 * @return array{exists: bool, file: string, filesize: int, height: int, mime-type: bool|string, width: int[]}
 */
function generate_wp_image_subsizes($original_path, $newsizes) {
    $info = pathinfo($original_path);
    $ext = $info['extension'];
    $name = $info['filename'];
    $dir = $info['dirname'];
    $subsizes = [];

    // Check if unscaled file exists
    if ( !is_file($original_path) ) {
        $testdir = wp_get_upload_dir()["basedir"];
        $testpath = $testdir . DIRECTORY_SEPARATOR . $original_path;
        // correct the path to the original file if it is not completely given.
        if (is_file($testpath)) {
            $original_path = $testpath;
            $dir = $testdir . DIRECTORY_SEPARATOR . $dir;
            #error_log('relative path corrected to: ' . $original_path);
        } else {
            #error_log("generate_wp_image_subsizes: original_path: " . $original_path . " does not exist");
            return $subsizes;
        }
    }
    #error_log("generate_wp_image_subsizes: original_path: " . $original_path);
    // get image dimensions from $original_file
	$size = wp_getimagesize($original_path);

    if ( !is_array($size)) {
        return $subsizes;
    } elseif ( !isset($size[0]) || !isset($size[1]) || $size[0] == 0 || $size[1] == 0 ) {
        return $subsizes;
    }
	$origWidth = $size[0];
	$origHeight = $size[1];
	$aspRatio = $origWidth / $origHeight;
    
    foreach ($newsizes as $size_name => $size) {
        $width = isset($size['width']) ? (int)$size['width'] : 0;
        $height = isset($size['height']) ? (int)$size['height'] : 0;
        $crop = isset($size['crop']) ? (bool)$size['crop'] : false;
        
        // Build the suffix based on crop setting
        if ($crop && $width>0 && $height>0) {
            // Exact dimensions for cropped images
            $suffix = "{$width}x{$height}";
        } elseif ( !$crop ) {
            // Only the max dimension for scaled images
            if ($width >= $height && !($width == 0 || $width == 9999)) {
				// landscape orientation
				$calcHeight = (int)round($width / $aspRatio );
				$suffix = "{$width}x{$calcHeight}";
            } elseif (!($height == 0 || $height == 9999)) {
				// portrait orientation
				$calcWidth = (int)round($height * $aspRatio );
				$suffix = "{$calcWidth}x{$height}";
            } elseif ($width == 0 || $width == 9999) {
                $calcWidth = (int)round($height * $aspRatio);
                $suffix = "{$calcWidth}x{$height}";
            } elseif ($height == 0 || $height == 9999) {
                $calcHeight = (int)round($width / $aspRatio);
                $suffix = "{$width}x{$calcHeight}";
            } else {
                $suffix = 'wrong';
            }
        }
        // check here for changed mime-type 
        $new_filename = $name . '-' . $suffix . '.' . $ext;
        $new_filepath = $dir . DIRECTORY_SEPARATOR . $new_filename;

        // check the OS running on the server
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $new_filepath = str_replace('/', '\\', $new_filepath);
        }
        
		$fileexists = is_file($new_filepath); // works only for the initial upload of an image.
        
        // Check if file exists as renamed *.oldimagefile and if so rename it to the expected name. But only if the updated subsize file was uploaded before.
        $subsizeUploadedBefore = array_key_exists('subsizesuploaded', $_GET) && $_GET['subsizesuploaded'] == 'true';
        
        if ( !$fileexists && $subsizeUploadedBefore ) { // check that the file was uploaded before
            $old_filepath = $new_filepath . '.oldimagefile';
            if (is_file($old_filepath)) {
                rename($old_filepath, $new_filepath);
                $fileexists = true;
            }
        }
        #error_log( "generate_wp_image_subsizes: " . $new_filepath . " exists: " . strval($fileexists) );
        
        $subsizes[$size_name] = array(
            'file' => $new_filename,
            'width' => $width,
            'height' => $height,
			'mime-type' => $fileexists ? wp_get_image_mime($new_filepath) : '',
			'filesize' => $fileexists ? wp_filesize($new_filepath) : 0,
			# additional fields
			'exists' => $fileexists
        );
    }
    
    return $subsizes;
}
