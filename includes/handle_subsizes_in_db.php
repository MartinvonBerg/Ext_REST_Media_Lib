<?php
namespace mvbplugins\extmedialib;

// PRIO TODO : use a setting for that.

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
function image_subsizes_filter(array $newsizes, array $image_meta, int $wp_id) : array {
	// get filename from $image_meta
    $orig_newsizes = $newsizes;
	$file = $image_meta["file"]; // TODO add_filter(wp_unique_filename) : Der 6. Parameter ist $number und zeigt, ob gleichanmige Dateien gefunden wurden. Auch subsizes und wie oft umbenannt wurde.
	$expectedSizes = generate_wp_image_subsizes($file, $newsizes); // use wp_unique_filename to generate the expected filenames for the subsizes. 
    //This is necessary because the filename can be changed by WP if a file with the same name already exists in the upload directory. 
    //The expected filenames are generated based on the original filename and the size name, width, height and crop settings of the subsizes.
    if ( empty( $expectedSizes ) ) {
        return $newsizes;
    }

	foreach ( $newsizes as $new_size_name => $new_size_data ) {
		// generate the expected image filename for the subsizes from array $newsizes respecting the WP standards for width, height and crop
        /** @var array{file: string, width: int, height: int, 'mime-type': string, filesize: int, exists: bool, crop: bool} $new_size_meta */
        $new_size_meta = $expectedSizes[ $new_size_name ]; 

		if ( isset( $new_size_meta['exists'] ) && $new_size_meta['exists'] ) {
			// Save the size meta value.
			unset( $new_size_meta['exists'] );
			$image_meta['sizes'][ $new_size_name ] = $new_size_meta;
			unset($newsizes[ $new_size_name ]); 
		}
	}
    // only update if all subsizes are available. Skip if some subsizes are missing. 
    if ( empty( $newsizes ) ) {
        // if all subsizes are available the metadata is updated with the new subsizes and the subsizes will not be created by _wp_make_subsizes() because newsizes is empty.
        wp_update_attachment_metadata( $wp_id, $image_meta );
        return $newsizes;
    } else {
        // if only some subsizes are missing the metadata is not created correctly because the missing subsizes will be created by _wp_make_subsizes() but the existing subsizes will not be included in the metadata because they are not created by _wp_make_subsizes() and the metadata is not updated with the existing subsizes. So in this case we return the original newsizes to let WP create all subsizes and update the metadata correctly.
        return $orig_newsizes;
    }
}

/**
 * Generates the expected image filenames and filepaths as string for the subsizes from the array newsizes
 * "crop" is a boolean that decides wether the image should be cropped or not. If crop is true the new filename (and dimensions) will be exactly cropped to width and heigt.
 * If crop is false the larger value of the image (be it width or height respecting orientation) will remain unchanged and the shorter value (be it height or widht respecting orientation) will be set according to the aspect ratio.
 * @param mixed $original_path the original image path
 * @param mixed $newsizes   the array of subsizes
 * @return array{exists: bool, file: string, filesize: int, height: int, mime-type: bool|string, width: int[]}
 */
function generate_wp_image_subsizes(string $original_path, array $newsizes) : array {
    $info = pathinfo($original_path);
    $ext = $info['extension'];
    $name = $info['filename']; // remove the '-scaled' suffix if it exists.
    if (str_ends_with($name, '-scaled')) {
        $name = substr($name, 0, -7);
    }
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
			'exists' => $fileexists,
            'crop' => $crop
        );
    }
    
    return $subsizes;
}
