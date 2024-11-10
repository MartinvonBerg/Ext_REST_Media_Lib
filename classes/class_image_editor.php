<?php
/**
 * Efficiently resize the current image
 *
 * This is a Plugin specific implementation of class method Imagick::thumbnailImage(),    
 * which resizes an image to given dimensions and removes any associated profiles. 
 * The execution times are roughly:
 * JPEG : 2.0 s, WEBP : 3.0 s, AVIF : 4.8 s on my local machine. Without this method it is 2.8 s for AVIF! Tested with 1 image only!
 * @since 1.1.0
 * 
 * 
 */   
namespace mvbplugins\extmedialib;

defined( 'ABSPATH' ) || die( 'Not defined' );

const RESIZE_QUALITY = 85;    // quality for jpeg image resizing in percent.
const WEBP_QUALITY   = 55;	  // quality for webp image resizing in percent.
const AVIF_QUALITY   = 55;	  // quality for avif image resizing in percent.
const MIN_QUALITY    = 30;	  // minimum quality for image resizing in percent.

\add_filter('jpeg_quality', function () { return RESIZE_QUALITY; });
\apply_filters( 'jpeg_quality', RESIZE_QUALITY, 'image/jpeg');
\add_filter( 'wp_editor_set_quality', function () { return WEBP_QUALITY; });
\apply_filters( 'wp_editor_set_quality', WEBP_QUALITY, 'image/webp' );
\add_filter( 'wp_editor_set_quality', function () { return AVIF_QUALITY; });
\apply_filters( 'wp_editor_set_quality', AVIF_QUALITY, 'image/avif' ); // see: https://github.com/WordPress/wordpress-develop/pull/7004

\add_filter( 'wp_image_editors', '\mvbplugins\extmedialib\custom_image_editors' );

function custom_image_editors( $editors ) {
	// Add a custom image editor add the beginning of the array.
	// check if imagick is installed and is_callable
	if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick', false ) ) {
		$editors = array_merge( array( 'custom_editor' => '\mvbplugins\extmedialib\Custom_Image_Editor' ), $editors );
	}
	return $editors;
}

//--------------------------------------------------------------------
require_once \ABSPATH . \WPINC . '/class-wp-image-editor.php';
require_once \ABSPATH . \WPINC . '/class-wp-image-editor-imagick.php';

final class Custom_Image_Editor extends \WP_Image_Editor_Imagick {
	
	private $reqQuality;

	public function __construct($file) {
		parent::__construct($file);

		// check if quality is set in request from REST-api at all
		if (array_key_exists('quality', $_REQUEST)) {
		// convert to int value
			$this->reqQuality = intval ( $_REQUEST['quality'] );
		} else {
			$this->reqQuality = 0;
		}
	}

	/**
	 * Efficiently resize the current image
	 *
	 * This is a WordPress specific implementation of Imagick::thumbnailImage(),
	 * which resizes an image to given dimensions and removes any associated profiles.
	 *
	 * @since 4.5.0
	 *
	 * @param int    $dst_w       The destination width.
	 * @param int    $dst_h       The destination height.
	 * @param string $filter_name Optional. The Imagick filter to use when resizing. Default 'FILTER_TRIANGLE'.
	 * @param bool   $strip_meta  Optional. Strip all profiles, excluding color profiles, from the image. Default true.
	 * @return void|\WP_Error
	 */
	protected function thumbnail_image( $dst_w, $dst_h, $filter_name = 'FILTER_TRIANGLE', $strip_meta = false ) {
		$allowed_filters = array(
			'FILTER_POINT',
			'FILTER_BOX',
			'FILTER_TRIANGLE',
			'FILTER_HERMITE',
			'FILTER_HANNING',
			'FILTER_HAMMING',
			'FILTER_BLACKMAN',
			'FILTER_GAUSSIAN',
			'FILTER_QUADRATIC',
			'FILTER_CUBIC',
			'FILTER_CATROM',
			'FILTER_MITCHELL',
			'FILTER_LANCZOS',
			'FILTER_BESSEL',
			'FILTER_SINC',
		);

		/**
		 * Set the filter value if '$filter_name' name is in the allowed list and the related
		 * Imagick constant is defined or fall back to the default filter.
		 */
		if ( in_array( $filter_name, $allowed_filters, true ) && defined( 'Imagick::' . $filter_name ) ) {
			$filter = constant( 'Imagick::' . $filter_name );
		} else {
			$filter = defined( 'Imagick::FILTER_TRIANGLE' ) ? \Imagick::FILTER_TRIANGLE : false;
		}

		/**
		 * Filters whether to strip metadata from images when they're resized.
		 *
		 * This filter only applies when resizing using the Imagick editor since GD
		 * always strips profiles by default.
		 *
		 * @since 4.5.0
		 *
		 * @param bool $strip_meta Whether to strip image metadata during resizing. Default true.
		 */
		if ( apply_filters( 'image_strip_meta', $strip_meta ) ) {
			$this->strip_meta(); // Fail silently if not supported.
		}

		try {
			/*
			 * To be more efficient, resample large images to 5x the destination size before resizing
			 * whenever the output size is less that 1/3 of the original image size (1/3^2 ~= .111),
			 * unless we would be resampling to a scale smaller than 128x128.
			 */
			if ( is_callable( array( $this->image, 'sampleImage' ) ) ) {
				$resize_ratio  = ( $dst_w / $this->size['width'] ) * ( $dst_h / $this->size['height'] );
				$sample_factor = 5;

				if ( $resize_ratio < .111 && ( $dst_w * $sample_factor > 128 && $dst_h * $sample_factor > 128 ) ) {
					$this->image->sampleImage( $dst_w * $sample_factor, $dst_h * $sample_factor );
				}
			}

			/*
			 * Use resizeImage() when it's available and a valid filter value is set.
			 * Otherwise, fall back to the scaleImage() method for resizing, which
			 * results in better image quality over resizeImage() with default filter
			 * settings and retains backward compatibility with pre 4.5 functionality. 
			 */
			// Adjust quality based on image dimensions
			if ( is_callable( array( $this->image, 'resizeImage' ) ) && $filter ) {
				$this->image->setOption( 'filter:support', '2.0' );
				$this->image->setOption( 'filter:colorspace', 'sRGB' );
				$this->image->setOption( 'filter:dither', 'None' );
				#$this->image->setOption( 'filter:posterize', '136' ); Reduziert die Anzahl der Farbwerte, um ein posterisiertes Bild zu erzeugen, was oft als visueller Reduktionseffekt verwendet wird. --> unnötig
				$this->image->setOption( 'filter:interlace', 'none' );
				$this->image->resizeImage( $dst_w, $dst_h, $filter, 1 );
			} else {
				$this->image->scaleImage( $dst_w, $dst_h );
			}

			// Set appropriate quality settings after resizing.
			$area = $dst_w * $dst_h;
			
			switch ($this->mime_type) {
				case 'image/jpeg':
					$baseQuality = $this->reqQuality ? $this->reqQuality : RESIZE_QUALITY;
					$quality = min($baseQuality, max(MIN_QUALITY, floor($baseQuality * ($area / 4369920))));
					$this->image->setImageCompressionQuality($quality);
					$this->image->setOption('jpeg:fancy-upsampling', 'off');
					$this->image->setOption('jpeg:sampling-factor', '4:2:0');
					break;

				case 'image/webp':
					$baseQuality = $this->reqQuality ? $this->reqQuality : WEBP_QUALITY;
					$quality = min($baseQuality, max(MIN_QUALITY, floor($baseQuality * ($area / 4369920))));
					$this->image->setImageCompressionQuality($quality);

					$version = \Imagick::getVersion();
					if (version_compare($version['versionNumber'], '7.0.10-53', '>=')) {
						$this->image->setOption('webp:method', '6');
						$this->image->setOption('webp:low-memory', 'false');
						#$this->image->setOption('webp:thread-level', '1'); -- this does not work even with 7.1.1-39
					}
					break;

				case 'image/avif':
					$baseQuality = $this->reqQuality ? $this->reqQuality : AVIF_QUALITY;
					$quality = min($baseQuality, max(MIN_QUALITY, floor($baseQuality * ($area / 4369920))));
					$this->image->setImageCompressionQuality($quality);
					
					// Prüfen Sie die aktuelle ImageMagick-Version
					$version = \Imagick::getVersion();
					if (version_compare($version['versionNumber'], '7.0.10-53', '>=')) {
						$this->image->setOption('heic:speed', 5);
						$this->image->setOption('heic:compression-effort', 5);
						$this->image->setOption('heic:threads', 3);
						$this->image->setOption('avif:effort', '5');  // Effort-Wert für AVIF, wenn unterstützt
					}
					break;
					
				case 'image/png':
					$this->image->setOption( 'png:compression-filter', '5' );
					$this->image->setOption( 'png:compression-level', '9' );
					$this->image->setOption( 'png:compression-strategy', '1' );
					$this->image->setOption( 'png:exclude-chunk', 'all' );
					break;

				default:
					break;
			}

			// Common sharpening for all formats if dimensions are reduced
			if ($dst_w < $this->size['width'] && is_callable(array($this->image, 'unsharpMaskImage'))) {
				$this->image->unsharpMaskImage(0.25, 0.25, 8, 0.065);
			}

			/*
			 * If alpha channel is not defined, set it opaque.
			 *
			 * Note that Imagick::getImageAlphaChannel() is only available if Imagick
			 * has been compiled against ImageMagick version 6.4.0 or newer.
			*/
			if ( is_callable( array( $this->image, 'getImageAlphaChannel' ) )
				&& is_callable( array( $this->image, 'setImageAlphaChannel' ) )
				&& defined( 'Imagick::ALPHACHANNEL_UNDEFINED' )
				&& defined( 'Imagick::ALPHACHANNEL_OPAQUE' )
			) {
				if ( $this->image->getImageAlphaChannel() === \Imagick::ALPHACHANNEL_UNDEFINED ) {
					$this->image->setImageAlphaChannel( \Imagick::ALPHACHANNEL_OPAQUE );
				}
			}

			// Limit the bit depth of resized images to 8 bits per channel.
			if ( is_callable( array( $this->image, 'getImageDepth' ) ) && is_callable( array( $this->image, 'setImageDepth' ) ) ) {
				if ( 8 < $this->image->getImageDepth() ) {
					$this->image->setImageDepth( 8 );
				}
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'image_resize_error', $e->getMessage() );
		}
	}

}