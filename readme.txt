=== wp_wpcat_json_rest ===
Plugin Name: wp_wpcat_json_rest
Contributors: martinvonberg
Donate link: http://www.mvb1.de
Tags: REST, API, JSON, image, Media-Library, folder, directory, jpg, Media-Catalog, upload, update, webp, headless
Requires at least: 5.3
Tested up to: 5.8
Requires PHP: 7.2
Stable Tag: 0.0.14
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html


== Description ==
This plugin extends the REST-API of Wordpress to directly access the Media-Library for Images. It is intended to be used together with a Lightroom Plugin or as a stand-alone interface for headless WordPress. The new REST-API endpoints (functions) allow to add additional metadata to images, update existing metadata or update images completely without changing the Wordpress-ID. Images may be added to the standard directory hierarchy of wordpress or to an additional folder which allows better organization and searching for images.


== Authorization ==
With this plugin ALL requests to the REST-API of wordpress require an authorization method in the https-header. It is no longer possible to even read data via the REST-API. There are different authorization methods: 

1. Use your WP-Admin Username and Password + username + Basic-Auth
This function is not provided by this plugin. There are plugins that allow Basic-Auth. It works fine with https. Never use it together with http. Your administrator username and password will be submitted to the internet. 

2. Use WP REST application password + Basic auth
This works only with wordpress 5.6+ and may be used together with Basic-Auth. The setting is only provided if your website runs with https. So, use it only together with https (see above). I prefer this method and recommend to update to at least WP 5.6. There is a setting to use this method together with http, but this is not recommended.
Process:
 - Login to your wordpress-site 
 - Go to Admin-Panel > User > Profile
 - Scroll down to "Application Passwords"
 - Provide a useful name for the application in the field underneath
 - Click the button "add new application password"
 - The new password will be shown. Copy it immediately and store it! It won't be shown again. Remove the spaces from the password.
 - Use the username of the admin and the new generated password in the https-header to access to wordpress 

3. OAuth2
Use existing plugins for the OAuth2 process. Best security compared to the other methods but very complicated to implement on the application side. 


== Usage (detailed list of endpoints and REST-API-fields)
1. REST-API-fields
The additional fields are available with the standard REST-API Endpoint: https://www.example.com/wp-json/wp/v2/media

1.1 Field 'gallery'
This field may be used to organize images in galleries. The wordpress standard Media-Library does not provide a sorting scheme to organize images in galleries or topics. So -provided the gallery-plugin supports it- this is a simple way to organize images. Together with the plugin 'AdvancedCustomFields' it is possible to search for this field (or others). 

1.2 Field 'gallery_sort'
This field may be used for custom sorting of images shown in an image-slider or gallery. Only Integer values are allowed. Only useable with a plugin that supports this.
See for instance: https://github.com/MartinvonBerg/Fotorama-Leaflet-Elevation.

1.3 Field 'md5_original_file'
This is an array that provides the MD5-hash-value (checksum) and the file size of the original image file. This data is used for the update process to check prior to the upload whether an image was changed or not. It's intention is to reduce network load during update process.

1.4 Example JSON-snippet of the REST-API output for the above mentioned fields

"gallery": "Albums",
"gallery_sort": "16",
"md5_original_file": {
    "MD5": "FCB639BB8191716A829F7B007056945B",
    "size": 509168
},

How to get this: Open you browser and type https://www.your-domain.whatever/wp-json/wp/v2/media. Use Firefox to get a formatted output of the response. You have to be logged in to get the response.

1.5 How to write the fields:
Writing the fields is only possible with authorization. So, check the 'authorization' section before. This may be tested with 'postman', a great software for testing http(s)-requests.

Example https-request with POST-method:
https://www.your-domain.whatever/wp-json/wp/v2/media/666?gallery=test-gallery

Mind: It is NOT required to use quotes around the value (here: test-gallery). If you use quotes, they will be used as part of the string in the field gallery.

1.6 Note on REST-API output
It is possible to reduce the REST-API output to dedicated fields. This is much better for overview and reducing net-load.
Example:
The https GET-Request 
'https://example.com/wp-json/wp/v2/media/?_fields=id,gallery' 
provides this response: 

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


2. New REST-API-Endpoints (aka functions)

2.1 extmedialib/v1/update/(?P<id>[\d]+)
Function to update images. Only integer values will be accepted for the 'id'.

2.1.1 GET-method to extmedialib/v1/update/(?P<id>[\d]+)
This function is just there for completeness. It provides some information for an existing image. The response to a GET-method to .../wp-json/extmedialib/v1/update/<wordpress-id> is the following:
        
    {
    "message": "You requested update of original Image with ID 5013 with GET-Method. Please update with POST-Method.",
    "original-file": "C:\\Bitnami\\wordpress-5.2.2-0\\apps\\wordpress\\htdocs/wp-content/uploads/Albums4/Friaul_2019_10-169_DxO.jpg",
    "md5_original_file": "01CE0E6A16954C87586E9BF16044FDA0",
    "max_upload_size": "41943040 bytes"
    }

If the given wordpress-id does not exist it returns with http status-code 404. 
 
2.1.2 POST-method to extmedialib/v1/update/(?P<id>[\d]+)
This function updates the complete image including metadata. The given wordpress-id remains unchanged. Only the image-files that belong to that wordpress-id will be updated. All image sub-sizes will be regenerated. All metadata will be updated according to the EXIF-data in the provided image. To complete the update process it is required to set the fields 'title', 'caption', 'alt_text' and 'description' with the standard REST-API-methods (see above). The function 'update_meta' is included.

Note on image resizing: Wordpress sets the standard resize quality to 82%. A setting of 100% was tested but with that the image-files were rather big. The setting may be changed in the PHP-code only. Up to now there is now administration panel for the settings of this plugin.

Note on image size: Wordpress scales all images with pixel length (long side) greater than 2560 pixels down to this size. The bigger images will be stored in the ../uploads-directory but NOT used for the wordpress pages. So, it is not useful to upload images bigger than 2560 pixels. This may be changed by setting the 'big_image_size_threshold' by a dedicated hook. This is out of scope of this plugin.

Header for POST-method
To define the content-type the following fields have to be added to the header:
    {field='Content-Disposition', value='form-data; filename=<newfile.jpg>' },
	{field='Content-Type', value='image/jpeg'}, 
    OR
    {field='Content-Type', value='image/webp'},

Body for POST-method
The new Webp- or JPG-file has to be provided in the body as binary string. Checks in mime-type and size are done to prevent the user from uploading wrong data.


2.2 extmedialib/v1/update_meta/(?P<id>[\d]+)
Function to update metadata of images. Only integer values will be accepted for the id.

2.2.1 GET-method to extmedialib/v1/update_meta/(?P<id>[\d]+)
This function is just there for completeness. 
The response to a GET-method to '.../wp-json/extmedialib/v1/update_meta/wordpress-id' is not executed. It may be used to check whether the image with the given wordpress-id is available. The response provides the http-status-code 405, if so. This could be done with a standard REST-request, too.

2.2.2 POST-method to extmedialib/v1/update_meta/(?P<id>[\d]+)
This function updates the metadata of an existing image. It does not access the metadata that may be easily changed with the standard REST-API methods of wordpress (see there). It is only done if the 'wordpress-id' is a valid image and was added to the media-library before. For Jpegs it does NOT change 'aperture, camera, created_timestamp, focal_length, iso, shutter_speed and orientation'. It is not very useful to change this data for an existing jpg-image. As the data is NOT set by WP for webp-images it is possible to add this data for Webp-Images now. The update or addition is done with a valid JSON-body and the respective settings in the http-header.

Header for POST-method
To define the content-type the following fields have to be added to the header:
    {field='Content-Type', value='application/json'}

Example Body for POST-method
    The JSON has to be formatted like that:
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
    
All fields that are provided in the JSON will be changed. Empty fields will reset the content to an empty string "". 


2.3 extmedialib/v1/addtofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
This function stores images aside the wordpress standard folders but make them available in the media-library by generating a new wordpress-id. The 'folder' must not contain other characters than a-z, A-Z, 0-9, _ and -.

2.3.1 GET-method to extmedialib/v1/addtofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
This function is just there for completeness and simple checking. The response to a GET-method to '.../wp-json/extmedialib/v1/addtofolder/foldername' simply gives the information whether the folder already exists or not.

2.3.2 POST-method to extmedialib/v1/addtofolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
With the POST-method an image will be added to the given folder and with a new wordpress id. The response provides the new id and some basic information about the added image file.

Header for POST-method
To define the content-type the following fields have to be added to the header:
    {field='Content-Disposition', value='form-data; filename='<newfile.jpg> },
	{field='Content-Type', value='image/jpeg'},
    OR
    {field='Content-Type', value='image/webp'},

Body for POST-method
The new JPG-file has to be provided in the body as binary string. Checks for mime-type and size are done to prevent the user from uploading wrong images.


2.4 extmedialib/v1/addfromfolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
This function adds already uploaded images to the media-library. This is useful for images that were uploaded with ftp before. The 'folder' must not contain other characters than a-z, A-Z, 0-9, _ and -.

2.4.1 GET-method to extmedialib/v1/addfromfolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
This method gives information about the folder content. If existing and not empty the folder content will be provided as an array. The array provides now the id's and original-files that are already in the media-library.

2.4.2 POST-method to extmedialib/v1/addfromfolder/(?P<folder>[a-zA-Z0-9\/\\-_]*)
With the POST-method all images from the given 'folder' will be added to the media-library. Image-Files that were already added before from THAT dedicated folder will be skipped. The response contains an JSON-array with IDs to be stored in the application (e.g. Lightroom) for later access. Mind that this might be a long running process. If it runs too long it will be stopped by the server and the addition is NOT complete. So, the recommendation is to do this step by step, e.g. 10 images maximum per step.
    

== Screenshots ==

There are no screenshots yet.


== Installation ==

1. Visit the plugins page on your Admin-page and click  ‘Add New’
2. Search for 'wp_wpcat_json_rest', or 'JSON' and 'REST'
1. Once found, click on 'Install'
1. Go to the plugins page and activate the plugin


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
*   Added functionality to treat webp images as well. Tested with WP 5.8-RC4 test version.

== Upgrade Notice ==

Upgrade if you want to use webp images in WP and upload it via the REST-API. 


== Credits ==
This plugin uses the great work from:

- wordpress for coding hints: https://de.wordpress.org/
- authorization hints: https://developer.wordpress.org/rest-api/frequently-asked-questions/
