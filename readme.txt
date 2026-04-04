=== Media Library Extension ===
Plugin Name: Media Library Extension
Contributors: martinvonberg
Donate link: https://www.berg-reise-foto.de/software-wordpress-lightroom-plugins/wordpress-plugins-fotos-und-gpx/
Tags: REST-API, Media-Library, Media-Catalog, upload, wpcat
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Stable Tag: 3.0.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html


== Description ==

Extend the REST-API to work with Wordpress Media-Library. Organize images in Folders. Add and Update images including Metadata and Posts using the images. Access with Authorization only.
This plugin extends the REST-API of Wordpress to directly access the Media-Library for Images. It is intended to be used together with a Lightroom Plugin or as a stand-alone interface for headless WordPress. The new REST-API endpoints (functions) allow to add additional metadata to images, update existing metadata or update images completely without changing the Wordpress-ID. Images may be added to the standard directory hierarchy of wordpress or to an additional folder which allows better organization and searching for images.
NEW FUNCTION : See 3.2

== Screenshots ==
There are no screenshots yet.


== Installation ==

1. Visit the plugins page on your Admin-page and click  ‘Add New’
2. Search for 'wp_wpcat_json_rest', or 'JSON' and 'REST'
3. Once found, click on 'Install'
4. Go to the plugins page and activate the plugin
5. Go to Admin Settings and activate what you prefer.

== Upgrade Notice ==
Upgrade to 3.0.0 is not necessary. Only, if  you want to use the new image resizing class for smaller images and / or the the new hook method for metadata.

== Frequently Asked Questions ==

There are no FAQs just yet.


== Changelog ==

= 0.0.1 to 0.0.6 =
*   Development phase

= 0.0.7 =
*   First working release: 1.04.2020

= 0.0.8 =
*   Translation of comments. Preparation for wordpress.org Plugin-directory

= 0.0.9 =
*   Adaptations for publish to wordpress.org Plugin-directory
    + implemented namespace for the plugin
    + changed define to const (only const is in the namespace, define not)
    + changed the REST-namespace
    + removed all wpcat and wp_ - prefixes for plugin-code, except in comments
    + GET - /addfromfolder provides now a list with id's and original-files that are already added to the media-library
    + changed permission callbacks to is_user_logged_in
    + added required = true to args of rest-route-functions
    + added authorization required for complete REST-API
    + fixed md5_original_file request for deleted files in folder, but still in media-library

= 0.0.10 =
*   Removed minor inconsistencies at the rest_field definitions

= 0.0.11 =
*   added namespace to inner functions

= 0.0.12 =
*   set resize quality back to standard value (82). Images were too big!

= 0.0.13 =
*   Readme updated. No functional change.
*   2020-02-12: Test with WP5.6.1 an PHP8.0 on live site: no errors reported. Works!
*   PHP-Compatibility check with phpcs. Compatible from PHP 5.4 - 8.0. But keep PHP 7.0 as minimum version
*   Update to keep some WP coding guideline. But still not all! Only partially done.

= 0.0.14 =
*   Readme and docblocks updated. 
*   Added functionality to handle webp images as well. Tested with WP 5.8-RC4 test version.

= 0.0.15 =
*   Update function updated! The update includes now also ALL posts that are using the updated image. All links are changed to the new links.
*   The plugin is partly re-using the great work of 'Enable Media Replacer' that solved the task already for manual updates via the frontend.
*   Aditionally the 'alt-text' and the 'caption' are updated if the are used in gutenberg blocks 'image', 'gallery' and 'media-with-text'. 
*   Important: There are many, many other blocks, images, galleries around. For these I couldn't realize the update of 'alt-text' and 'caption'.
*   BUT: The links are updated!
*   Code quality check with phpstan: level 5 is OK except 19 remaining Errors. These were carefully checked and regarded as OK. Minor changes to reach level 5.

= 0.0.16 =
*   Bug-Fix for Image Update with same filename. Python testcase added for that and successfully tested.

= 0.0.17 =
*   Just a new tag for SVN upload test

= 0.0.18 =
*Code-Rework:
* loaded updated emrFile.php from github
* split helper functions in several files and renamed /inc to /includes
* simplified loading of WP-Error class
* added a programatical filter for image quality. Standard is now 80 for jpeg and 40 for webp.
* tested the whole bunch of changes with the python-test-suite and Lightroom.
* The further code rework acc. to: https://carlalexander.ca/designing-system-wordpress-rest-api-endpoints/ or
* https://torquemag.io/2018/03/advanced-oop-wordpress-customizing-rest-api-endpoints-improve-wordpress-search/ looks very promising but won't be done.

= 0.0.19 =
*   Just a new tag for SVN upload test

= 0.1.0 =
* Rework of the image update function (endpoint of POST-Request /update/): The function updates the image FILE only and the filename if provided in POST request. 
*   Content, description, alt-text, parent ASO are now kept and no longer overwritten.
*   Change the modified date, only and not the published date on changes. Valid for image and post that uses it.
*   Set the slug and permalink according to title, if the title is changed.
*   If the title of the old image was different from the filename than title will be kept. All other meta-data remains unchanged including post-parent.
* Minimum required PHP version is 7.3 now as now tests with 7.2 were done.

= 0.1.1 =
* Code Refactoring and meaningful PHPunit tests completed

= 0.1.2 =
* Test with WordPress 6.0.

= 0.1.3 =
* Test with WordPress 6.1. Minor Bug Fixes.

= 0.1.4 =
* Test with WordPress 6.2. Minor Bug Fixes especially in image_update_callback.php.

= 0.1.5 =
* Minor Bug Fixes in image_update_callback.php: added the do_action. This is the event trigger for the Pugin to strip metadata.
* Test with WordPress 6.3

= 0.1.5 =
* Test with WordPress 6.4. No changes. Detected Issue during test: If image is attached to parent the SQL wpdb->query does not update the post! Detected WordPress-Feature: The Post is not updated if it is open for editing.

= 0.1.5 =
* Test with WordPress 6.6. No changes.

= 1.0.0 =
* Added support for AVIF-Files and tested with WordPress 6.6.2. Minor change of quality for image resizing. Increased minimum versions of WP and PHP.

= 1.1.0 =
* EXPERIMENTAL !!!
* Added an own class to generate the image-sizes with ImageMagick. This produces smaller files as expected where AVIF is 0.5 Jpeg-size and -30% of WebP-size.
* The calculation times are roughly:
* JPEG : 2.0 s, WEBP : 3.0 s, AVIF : 4.8 s on my local machine. Without my Image_Editor its 2.6s for AVIF only! Tested with 1 image only!
* Tested with WordPress 6.7-RC4 This class to generate the image-sizes with ImageMagick is used always! For every upload!
* Added new routes and functions to handle local generated images.

= 1.1.0 =
* Test with WordPress 6.8. No changes.

= 1.2.0 =
* Test with WordPress 6.9. No changes for that. Minor Update of PHP-Function post_add_file_to_folder() for better folder name generation. Added PHPUnit Tests for the new PHP-functions.

= 2.0.0 =
* Updated minimum PHP-Version to 8.0 (8.3 would be even better)
* Updated method doMetaReplaceQuery() in replacer.php as start of update to new WP and PHP principles. (Old code works but is very old fashioned)

= 3.0.0 =
* Update method doMetaReplaceQuery in replacer.php. Used Copilot to review and update replacer.php for PHPStan Level 8.
* Added a new hook for the standard media upload to have Metadata for webp and avif identical to jpg. Rework of Metadata Extractor.
* Rework of 'trigger_after_rest' for PHPStan Level 8 and removed the update of the slug in title change. Implemented the usage of the new setting to update content with caption and title.
* BREAKING CHANGE: Minimum PHP is now 8.x. Minor Updates in almost all PHP-Files.
* Added simple Admin Settings page mainly for the new Hook (AdminSettings.php.) and some for the existing REST-API.
* Test with WP 7.0
* Rebranding: Plugin renamed to "Media Library Extension"
