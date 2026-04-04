# Media Library Extension <!-- omit from toc --> 

Extend the WordPress REST API to fully manage the Media Library — including uploading, updating, organizing in custom folders, and synchronizing images with metadata and posts.

Ensures consistent metadata handling for JPG, WEBP, and AVIF for every images uploaded via WordPress Media uploader (after activation, REST-API **not** required).

Improves the WordPress Image Subsize generation with selectable quality settings (after activation, REST-API **not** required).

This REST-API functionality is mainly designed for developers, headless WordPress setups, and advanced media workflows (e.g. Lightroom integration)..

Key capabilities:
- Update images **without changing the WordPress ID**
- Synchronize metadata (title, caption, alt text) across **all posts using the image**
- Organize media in **custom folder structures** beyond the default WordPress hierarchy
- Upload, replace, and manage images directly via REST API
- Ensure consistent metadata handling for JPG, WEBP, and AVIF
- Improves the WordPress Image Subsize generation (smaller sizes, comparable visual quality)

All endpoints require authorization, making it suitable for secure API-driven workflows.

## Contents <!-- omit from toc --> 
- [Installation](#installation)
- [Admin Settings](#admin-settings)
- [Authorization for REST-API](#authorization-for-rest-api)
- [Usage (detailed list of endpoints and REST-API-fields)](#usage-detailed-list-of-endpoints-and-rest-api-fields)
  - [1. REST-API-fields](#1-rest-api-fields)
    - [1.1 Field 'gallery'](#11-field-gallery)
    - [1.2 Field 'gallery\_sort'](#12-field-gallery_sort)
    - [1.3 Field 'md5\_original\_file'](#13-field-md5_original_file)
    - [1.4 Example JSON-snippet of the REST-API output for the above mentioned fields](#14-example-json-snippet-of-the-rest-api-output-for-the-above-mentioned-fields)
    - [1.5 How to write the fields](#15-how-to-write-the-fields)
    - [1.6 Note on REST-API output](#16-note-on-rest-api-output)
  - [2. New REST-API-Endpoints](#2-new-rest-api-endpoints)
    - [2.1 extmedialib/v1/update/(?P\[\\d\]+)](#21-extmedialibv1updatepd)
      - [2.1.1 GET-method to extmedialib/v1/update/(?P\[\\d\]+)](#211-get-method-to-extmedialibv1updatepd)
      - [2.1.2 POST-method to extmedialib/v1/update/(?P\[\\d\]+)](#212-post-method-to-extmedialibv1updatepd)
    - [2.2 extmedialib/v1/update\_meta/(?P\[\\d\]+)](#22-extmedialibv1update_metapd)
      - [2.2.1 GET-method to extmedialib/v1/update\_meta/(?P\[\\d\]+)](#221-get-method-to-extmedialibv1update_metapd)
      - [2.2.2 POST-method to extmedialib/v1/update\_meta/(?P\[\\d\]+)](#222-post-method-to-extmedialibv1update_metapd)
    - [2.3 extmedialib/v1/addtofolder/(?P\[a-zA-Z0-9/\\-\_\]\*)](#23-extmedialibv1addtofolderpa-za-z0-9-_)
      - [2.3.1 GET-method to extmedialib/v1/addtofolder/(?P\[a-zA-Z0-9/\\-\_\]\*)](#231-get-method-to-extmedialibv1addtofolderpa-za-z0-9-_)
      - [2.3.2 POST-method to extmedialib/v1/addtofolder/(?P\[a-zA-Z0-9/\\-\_\]\*)](#232-post-method-to-extmedialibv1addtofolderpa-za-z0-9-_)
    - [2.4 extmedialib/v1/addfromfolder/(?P\[a-zA-Z0-9/\\-\_\]\*)](#24-extmedialibv1addfromfolderpa-za-z0-9-_)
      - [2.4.1 GET-method to extmedialib/v1/addfromfolder/(?P\[a-zA-Z0-9/\\-\_\]\*)](#241-get-method-to-extmedialibv1addfromfolderpa-za-z0-9-_)
      - [2.4.2 POST-method to extmedialib/v1/addfromfolder/(?P\[a-zA-Z0-9/\\-\_\]\*)](#242-post-method-to-extmedialibv1addfromfolderpa-za-z0-9-_)
    - [2.5 extmedialib/v1/imagesubsizes](#25-extmedialibv1imagesubsizes)
      - [2.5.1 GET-method](#251-get-method)
      - [2.5.2 POST-method](#252-post-method)
    - [2.6 extmedialib/v1/filetofolder/(?P\[a-zA-Z0-9/\\-\_\]\*)'](#26-extmedialibv1filetofolderpa-za-z0-9-_)
      - [2.6.1 GET-method](#261-get-method)
      - [2.6.2 POST-method](#262-post-method)
  - [3. Hooks for Metadata handling](#3-hooks-for-metadata-handling)
    - [3.1 Hook: rest\_pre\_echo\_response](#31-hook-rest_pre_echo_response)
    - [3.2 Hook: wp\_generate\_attachment\_metadata](#32-hook-wp_generate_attachment_metadata)
  - [4. Filter for subsize generation](#4-filter-for-subsize-generation)
- [Frequently Asked Questions](#frequently-asked-questions)
- [Upgrade Notice](#upgrade-notice)
- [Credits](#credits)

## Installation

1. Visit the plugins page on your Admin-page and click  'Add New'
2. Search for 'wp_wpcat_json_rest', or 'JSON' and 'REST'
3. Once found, click on 'Install'
4. Go to the plugins page and activate the plugin
5. Go to Admin Settings and activate what you prefer.

## Admin Settings
There is an Admin panels that allows to do the basic settings.

## Authorization for REST-API
With this plugin ALL requests to the REST-API of WordPress require an authorization method in the https-header. It is no longer possible to even read data via the REST-API. There are different authorization methods:

1. Use your WP-Admin Username and Password + username + Basic-Auth
This function is not provided by this plugin. There are plugins that allow Basic-Auth. It works fine with https. Never use it together with http. Your administrator username and password will be submitted to the internet.

2. Use WP REST application password + Basic auth
This works only with WordPress 5.6+ and may be used together with Basic-Auth. The setting is only provided if your website runs with https. So, use it only together with https (see above). I prefer this method and recommend to update to at least WP 5.6. There is a setting to use this method together with http, but this is not recommended.
Process:
- Login to your WordPress-site
- Go to Admin-Panel > User > Profile
- Scroll down to "Application Passwords"
- Provide a useful name for the application in the field underneath
- Click the button "add new application password"
- The new password will be shown. Copy it immediately and store it! It won't be shown again. Remove the spaces from the password.
- Use the username of the admin and the new generated password in the https-header to access to WordPress

3. OAuth2
Use existing plugins for the OAuth2 process. Best security compared to the other methods but very complicated to implement on the application side.

## Usage (detailed list of endpoints and REST-API-fields)
### 1. REST-API-fields
The additional fields are available with the standard REST-API Endpoint: https://www.example.com/wp-json/wp/v2/media

#### 1.1 Field 'gallery'
This field may be used to organize images in galleries. The WordPress standard Media-Library does not provide a sorting scheme to organize images in galleries or topics. So -provided the gallery-plugin supports it- this is a simple way to organize images. Together with the plugin 'AdvancedCustomFields' it is possible to search for this field (or others).

#### 1.2 Field 'gallery_sort'
This field may be used for custom sorting of images shown in an image-slider or gallery. Only Integer values are allowed. Only useable with a plugin that supports this.
See for instance: https://github.com/MartinvonBerg/Fotorama-Leaflet-Elevation.

#### 1.3 Field 'md5_original_file'
This is an array that provides the MD5-hash-value (checksum) and the file size of the original image file. This data is used for the update process to check prior to the upload whether an image was changed or not. It's intention is to reduce network load during update process.

#### 1.4 Example JSON-snippet of the REST-API output for the above mentioned fields

```json
"gallery": "Albums",
"gallery_sort": "16",
"md5_original_file": {
    "MD5": "FCB639BB8191716A829F7B007056945B",
    "size": 509168
},
```

How to get this: Open you browser and type https://www.your-domain.whatever/wp-json/wp/v2/media. Use Firefox to get a formatted output of the response. You have to be logged in to get the response.

#### 1.5 How to write the fields
Writing the fields is only possible with authorization. So, check the 'authorization' section before. This may be tested with 'postman', a great software for testing http(s)-requests.

Example https-request with POST-method:
https://www.your-domain.whatever/wp-json/wp/v2/media/666?gallery=test-gallery

New functionality behind this request
A POST-request with 'alt_text' and / or 'caption' will change the content of ALL posts using that image. The 'alt_text' and the 'caption' are updated if they are used in
gutenberg blocks 'image', 'gallery' and 'media-with-text'. Note: There are many, many other blocks, images, galleries around. For these I couldn't realize the update of 'alt-text' and 'caption'.

New Parameter for the above POST-request
Add ?docaption=true to the http request and update ALL captions in the content, too. The 'alt_text' is always changed in the content, because IMO there could be only one alt_text for an image.
But, the caption may depend on the context, so it is up to the user, to change it automatically for all posts or not.

Mind: It is NOT required to use quotes around the value (here: test-gallery). If you use quotes, they will be used as part of the string in the field gallery.

#### 1.6 Note on REST-API output
It is possible to reduce the REST-API output to dedicated fields. This is much better for overview and reducing net-load.
Example:
The https GET-Request
'https://example.com/wp-json/wp/v2/media/?_fields=id,gallery'
provides this response:

```json
[
    {
        "id": 5013,
        "gallery": "Albums4"
    },
    {
        "id": 5012,
        "gallery": "Albums4"
    },
    {
        "id": 5011,
        "gallery": "Foto_Albums/Albums3"
    },
    {
        "id": 4932,
        "gallery": "Foto_Albums/Franken-Dennenlohe"
    },
    {
        "id": 4930,
        "gallery": "Foto_Albums/Franken-Dennenlohe"
    },
    {
        "id": 4929,
        "gallery": "Foto_Albums/Franken-Dennenlohe"
    },
    {
        "id": 4928,
        "gallery": "Foto_Albums/Franken-Dennenlohe"
    },
    {
        "id": 4927,
        "gallery": "Foto_Albums/Franken-Dennenlohe"
    },
    {
        "id": 4926,
        "gallery": "Foto_Albums/Franken-Dennenlohe"
    },
    {
        "id": 4925,
        "gallery": "Foto_Albums/Franken-Dennenlohe"
    }
]
```

### 2. New REST-API-Endpoints

#### 2.1 extmedialib/v1/update/(?P<id>[\d]+)
Function to update images. Only integer values will be accepted for the 'id'.

##### 2.1.1 GET-method to extmedialib/v1/update/(?P<id>[\d]+)
This function is just there for completeness. It provides some information for an existing image. The response to a GET-method to .../wp-json/extmedialib/v1/update/<wordpress-id> is the following:

```json
{
"message": "You requested update of original Image with ID 5013 with GET-Method. Please update with POST-Method.",
"original-file": "C:\\Bitnami\\wordpress-5.2.2-0\\apps\\wordpress\\htdocs/wp-content/uploads/Albums4/Friaul_2019_10-169_DxO.jpg",
"md5_original_file": "01CE0E6A16954C87586E9BF16044FDA0",
"max_upload_size": "41943040 bytes"
}
```

If the given WordPress-id does not exist it returns with http status-code 404.

##### 2.1.2 POST-method to extmedialib/v1/update/(?P<id>[\d]+)
This function updates the complete image including metadata. The given WordPress-id remains unchanged. Only the image-files that belong to that WordPress-id will be updated. All image sub-sizes will be regenerated. All metadata will be updated according to the EXIF-data in the provided image. To complete the update process it is required to set the fields 'title', 'caption', 'alt_text' and 'description' with the standard REST-API-methods (see above). The function 'update_meta' is included.

Note on image resizing: WordPress sets the standard resize quality to 82%. A setting of 100% was tested but with that the image-files were rather big.
The setting may be changed in the PHP-code only. Up to now there is now administration panel for the settings of this plugin. A programatical setting was added for that in version 0.0.18.

Note on image size: WordPress scales all images with pixel length (long side) greater than 2560 pixels down to this size. The bigger images will be stored in the ../uploads-directory but NOT used for the WordPress pages. So, it is not useful to upload images bigger than 2560 pixels. This may be changed by setting the 'big_image_size_threshold' by a dedicated hook. This is out of scope of this plugin.

Header for POST-method
To define the content-type the following fields have to be added to the header:
- {field='Content-Disposition', value='form-data; filename=<newfile.jpg>' }
- {field='Content-Type', value='image/jpeg'}
- OR
- {field='Content-Type', value='image/webp'}

Body for POST-method
The new Webp- or JPG-file has to be provided in the body as binary string. Checks in mime-type and size are done to prevent the user from uploading wrong data.

New Parameter for the POST-request
Add '?changemime=true to the http request and update the file with one that does have another mime-type.

#### 2.2 extmedialib/v1/update_meta/(?P<id>[\d]+)
Function to update metadata of images. Only integer values will be accepted for the id.

##### 2.2.1 GET-method to extmedialib/v1/update_meta/(?P<id>[\d]+)
This function is just there for completeness.
The response to a GET-method to '.../wp-json/extmedialib/v1/update_meta/wordpress-id' is not executed. It may be used to check whether the image with the given WordPress-id is available. The response provides the http-status-code 405, if so. This could be done with a standard REST-request, too.

##### 2.2.2 POST-method to extmedialib/v1/update_meta/(?P<id>[\d]+)
This function updates the metadata of an existing image. It does not access the metadata that may be easily changed with the standard REST-API methods of WordPress (see there). It is only done if the 'WordPress-id' is a valid image and was added to the media-library before. For Jpegs it does NOT change 'aperture, camera, created_timestamp, focal_length, iso, shutter_speed and orientation'. It is not very useful to change this data for an existing jpg-image. As the data is NOT set by WP for webp-images it is possible to add this data for Webp-Images now. The update or addition is done with a valid JSON-body and the respective settings in the http-header.

Header for POST-method
To define the content-type the following fields have to be added to the header:
- {field='Content-Type', value='application/json'}

Example Body for POST-method
The JSON has to be formatted like that:

```json
{
    "image_meta": {
            "credit": "Martin von Berg",
            "caption": "Test-caption",
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
```

All fields that are provided in the JSON will be changed. Empty fields will reset the content to an empty string "".

#### 2.3 extmedialib/v1/addtofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
This function stores images aside the WordPress standard folders but make them available in the media-library by generating a new WordPress-id. The 'folder' must not contain other characters than a-z, A-Z, 0-9, _ and -.

##### 2.3.1 GET-method to extmedialib/v1/addtofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
This function is just there for completeness and simple checking. The response to a GET-method to '.../wp-json/extmedialib/v1/addtofolder/foldername' simply gives the information whether the folder already exists or not.

##### 2.3.2 POST-method to extmedialib/v1/addtofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
With the POST-method an image will be added to the given folder and with a new WordPress id. The response provides the new id and some basic information about the added image file.

Header for POST-method
To define the content-type the following fields have to be added to the header:
- {field='Content-Disposition', value='form-data; filename='<newfile.jpg> }
- {field='Content-Type', value='image/jpeg'}
- OR
- {field='Content-Type', value='image/webp'}

Body for POST-method
The new JPG-file has to be provided in the body as binary string. Checks for mime-type and size are done to prevent the user from uploading wrong images.

#### 2.4 extmedialib/v1/addfromfolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
This function adds already uploaded images to the media-library. This is useful for images that were uploaded with ftp before. The 'folder' must not contain other characters than a-z, A-Z, 0-9, _ and -.

##### 2.4.1 GET-method to extmedialib/v1/addfromfolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
This method gives information about the folder content. If existing and not empty the folder content will be provided as an array. The array provides now the id's and original-files that are already in the media-library.

##### 2.4.2 POST-method to extmedialib/v1/addfromfolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
With the POST-method all images from the given 'folder' will be added to the media-library. Image-Files that were already added before from THAT dedicated folder will be skipped. The response contains an JSON-array with IDs to be stored in the application (e.g. Lightroom) for later access. Mind that this might be a long running process. If it runs too long it will be stopped by the server and the addition is NOT complete. So, the recommendation is to do this step by step, e.g. 10 images maximum per step.

#### 2.5 extmedialib/v1/imagesubsizes
##### 2.5.1 GET-method 
REST-API Endpoint to get the registered image subsizes by 'wp_get_registered_image_subsizes'.
##### 2.5.2 POST-method
Just returns 'not implemented'.

#### 2.6 extmedialib/v1/filetofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)'
REST-API Endpoint to add an image file to a folder in the WP-Media-Catalog including the standard WP-Media-Folders.
##### 2.6.1 GET-method 
Callback for GET to REST-Route 'addtofolder/<folder>'. Check wether folder exists and provide message if so.
##### 2.6.2 POST-method 
Callback for POST to REST-Route 'addtofolder/<folder>'. Provides the new WP-ID and the filename that was written to the folder. Checks wether folder exists. If not, creates the folder and adds the image from the body to media cat.
	

### 3. Hooks for Metadata handling 
#### 3.1 Hook: rest_pre_echo_response

This hook is applied to the finalized REST API response just before it is sent to the client. At this stage, the image metadata as well as all affected post contents are updated.
In detail, the following actions are performed:
- The image metadata is overwritten using the provided parameters (caption, title, alt_text):
- caption -> image caption
- title -> image title
- alt_text -> alt text
- Finally, all posts that use this image are updated automatically to ensure that the new image data is consistently reflected in the post content.
- Admin Settings allows to enable / disable that.

#### 3.2 Hook: wp_generate_attachment_metadata

This hook is executed after a standard image upload and is used to extend and normalize the attachment metadata. Goal:
Ensure that metadata handling for WEBP and AVIF images behaves identically to JPG images, as WordPress core provides full metadata support primarily for JPG by default.

How it works:
- The existing image metadata (image_meta) is evaluated and mapped to the corresponding WordPress attachment fields. This was primarily designed for webp and avif files which prefer XMP-Metadata and do not support IPTC.
- The mapping is defined as follows:
- XMP dc:title -> post_title -> attachment title (not used in the frontend). The dc:title is not used for the slug.
- XMP dc:title -> post_excerpt -> image caption, so the subtitle shown in the Frontend. Selectable on Admin Page!
- XMP dc:description -> post_content -> attachment description. (rarely used in WordPress)
- XMP dc:description -> _wp_attachment_image_alt -> alt attribute in the <img> tag ( therefore, the dc:description should be SEO-friendly. The check is up to the user.) Selectable on Admin Page!
- Mind: IPTC is intentionally ignored in favour of XMP as the primary metadata source.
- Admin Settings allows to enable / disable that.

Result:
After upload, WEBP and AVIF images provide the same metadata structure and behaviour as JPG images in standard WordPress, ensuring consistency in handling, display, and downstream processing.

### 4. Filter for subsize generation
The filter checks if the required subsizes were uploaded prior to the unscaled and shall therefore not be generated. So, it is possible to generate the subsizes not on server but in any app. Generation of filenames is crucial, so it does not work always.
This filter is always on because nothing will happen if subsizes are not present.

## Frequently Asked Questions

There are no FAQs just yet.

## Changelog <!-- omit from toc --> 

### 0.0.7 <!-- omit from toc --> 
- First working release: 1.04.2020

### 0.0.8 <!-- omit from toc -->
- Translation of comments. Preparation for WordPress.org Plugin-directory

### 0.0.9 <!-- omit from toc -->
- Adaptations for publish to WordPress.org Plugin-directory
- implemented namespace for the plugin
- changed define to const (only const is in the namespace, define not)
- changed the REST-namespace
- removed all wpcat and wp_ - prefixes for plugin-code, except in comments
- GET - /addfromfolder provides now a list with id's and original-files that are already added to the media-library
- changed permission callbacks to is_user_logged_in
- added required = true to args of rest-route-functions
- added authorization required for complete REST-API
- fixed md5_original_file request for deleted files in folder, but still in media-library

### 0.0.10 <!-- omit from toc -->
- Removed minor inconsistencies at the rest_field definitions

### 0.0.11 <!-- omit from toc -->
- added namespace to inner functions

### 0.0.12 <!-- omit from toc -->
- set resize quality back to standard value (82). Images were too big!

### 0.0.13 <!-- omit from toc -->
- Readme updated. No functional change.
- 2020-02-12: Test with WP5.6.1 an PHP8.0 on live site: no errors reported. Works!
- PHP-Compatibility check with phpcs. Compatible from PHP 5.4 - 8.0. But keep PHP 7.0 as minimum version
- Update to keep some WP coding guideline. But still not all! Only partially done.

### 0.0.14 <!-- omit from toc -->
- Readme and docblocks updated.
- Added functionality to handle webp images as well. Tested with WP 5.8-RC4 test version.

### 0.0.15 <!-- omit from toc -->
- Update function updated! The update includes now also ALL posts that are using the updated image. All links are changed to the new links.
- The plugin is partly re-using the great work of 'Enable Media Replacer' that solved the task already for manual updates via the frontend.
- Additionally the 'alt-text' and the 'caption' are updated if the are used in gutenberg blocks 'image', 'gallery' and 'media-with-text'.
- Important: There are many, many other blocks, images, galleries around. For these I couldn't realize the update of 'alt-text' and 'caption'.
- BUT: The links are updated!
- Code quality check with phpstan: level 5 is OK except 19 remaining Errors. These were carefully checked and regarded as OK. Minor changes to reach level 5.

### 0.0.16 <!-- omit from toc -->
- Bug-Fix for Image Update with same filename. Python testcase added for that and successfully tested.

### 0.0.17 <!-- omit from toc -->
- Just a new tag for SVN upload test

### 0.0.18 <!-- omit from toc -->
- Code-Rework:
- loaded updated emrFile.php from github
- split helper functions in several files and renamed /inc to /includes
- simplified loading of WP-Error class
- added a programatical filter for image quality. Standard is now 80 for jpeg and 40 for webp.
- tested the whole bunch of changes with the python-test-suite and Lightroom.
- The further code rework acc. to: https://carlalexander.ca/designing-system-wordpress-rest-api-endpoints/ or
- https://torquemag.io/2018/03/advanced-oop-wordpress-customizing-rest-api-endpoints-improve-wordpress-search/ looks very promising but won't be done.

### 0.0.19 <!-- omit from toc -->
- Just a new tag for SVN upload test

### 0.1.0 <!-- omit from toc -->
- Rework of the image update function (endpoint of POST-Request /update/): The function updates the image FILE only and the filename if provided in POST request.
- Content, description, alt-text, parent ASO are now kept and no longer overwritten.
- Change the modified date, only and not the published date on changes. Valid for image and post that uses it.
- Set the slug and permalink according to title, if the title is changed.
- If the title of the old image was different from the filename than title will be kept. All other meta-data remains unchanged including post-parent.
- Minimum required PHP version is 7.3 now as now tests with 7.2 were done.

### 0.1.1 <!-- omit from toc -->
- Code Refactoring and meaningful PHPunit tests completed

### 0.1.2 <!-- omit from toc -->
- Test with WordPress 6.0.

### 0.1.3 <!-- omit from toc -->
- Test with WordPress 6.1. Minor Bug Fixes.

### 0.1.4 <!-- omit from toc -->
- Test with WordPress 6.2. Minor Bug Fixes especially in image_update_callback.php.

### 0.1.5 <!-- omit from toc -->
- Minor Bug Fixes in image_update_callback.php: added the do_action. This is the event trigger for the Pugin to strip metadata.
- Test with WordPress 6.3

### 0.1.5 <!-- omit from toc -->
- Test with WordPress 6.4. No changes. Detected Issue during test: If image is attached to parent the SQL wpdb->query does not update the post! Detected WordPress-Feature: The Post is not updated if it is open for editing.

### 0.1.5 <!-- omit from toc -->
- Test with WordPress 6.6. No changes.

### 1.0.0 <!-- omit from toc -->
- Added support for AVIF-Files and tested with WordPress 6.6.2. Minor change of quality for image resizing. Increased minimum versions of WP and PHP.

### 1.1.0 <!-- omit from toc -->
- EXPERIMENTAL !!!
- Added an own class to generate the image-sizes with ImageMagick. This produces smaller files as expected where AVIF is 0.5 Jpeg-size and -30% of WebP-size.
- The calculation times are roughly:
- JPEG : 2.0 s, WEBP : 3.0 s, AVIF : 4.8 s on my local machine. Without my Image_Editor its 2.6s for AVIF only! Tested with 1 image only!
- Tested with WordPress 6.7-RC4 This class to generate the image-sizes with ImageMagick is used always! For every upload!
- Added new routes and functions to handle local generated images.

### 1.1.0 <!-- omit from toc -->
- Test with WordPress 6.8. No changes.

### 1.2.0 <!-- omit from toc -->
- Test with WordPress 6.9. No changes for that. Minor Update of PHP-Function post_add_file_to_folder() for better folder name generation. Added PHPUnit Tests for the new PHP-functions.

### 2.0.0 <!-- omit from toc -->
- Updated minimum PHP-Version to 8.0 (8.3 would be even better)
- Updated method doMetaReplaceQuery() in replacer.php as start of update to new WP and PHP principles. (Old code works but is very old fashioned)

### 3.0.0 <!-- omit from toc -->
- Update method doMetaReplaceQuery in replacer.php. Used Copilot to review and update replacer.php for PHPStan Level 8.
- Added a new hook for the standard media upload to have Metadata for webp and avif identical to jpg. Rework of Metadata Extractor.
- Rework of 'trigger_after_rest' for PHPStan Level 8 and removed the update of the slug in title change. Implemented the usage of the new setting to update content with caption and title.
- BREAKING CHANGE: Minimum PHP is now 8.x. Minor Updates in almost all PHP-Files.
- Added simple Admin Settings page mainly for the new Hook (AdminSettings.php.) and some for the existing REST-API.
- Test with WP 7.0
- Rebranding: Plugin renamed to "Media Library Extension"

## Upgrade Notice
Upgrade to 3.0.0 is not necessary. Only, if you want to use the new image resizing class for smaller images and / or the the new hook method for metadata.

## Credits
This plugin uses the great work from:

- wordpress for coding hints: https://de.wordpress.org/
- authorization hints: https://developer.wordpress.org/rest-api/frequently-asked-questions/
- Enable Media Replacer: https://de.wordpress.org/plugins/enable-media-replace/ I'm using two classes of this great plugin to handle the link updates.
- PHPunit and BrainMonkey for Testing.
