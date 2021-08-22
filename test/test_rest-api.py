import requests
import json
from jsonschema import validate
from jsonschema import Draft6Validator
from distutils.version import StrictVersion
import os, sys, magic, pathlib, string
import datetime, pytest

# prepare the path for the import of the WP-class
SCRIPT_DIR = os.path.dirname(os.path.realpath(os.path.join(os.getcwd(), os.path.expanduser(__file__))))
SCRIPT_DIR2 = os.path.join(SCRIPT_DIR, 'classes')
sys.path.append(SCRIPT_DIR2)

from WP_test_object import WP_EXT_REST_API, WP_REST_API
from helper_functions import find_plugin_in_json_resp_body

# define the tested site.
wp_site = {
    'url' : 'https://www.mvb1.de',
    'rest_route' : '/wp-json/wp/v2',
    'user' : 'wp2_dhz',
    'authentication' : 'Basic d3AyX2RoejptZ2xvYXczQmpCcUk3TWl5RmlKWTVROTA=',
    'testfolder' : 'pythontest2'
}

wp_site2 = {
    'url' : 'https://www.bergreisefoto.de/wordpress',
    'rest_route' : '/wp-json/wp/v2',
    'user' : 'martin',
    'authentication' : 'Basic TWFydGluOm4yc2pLdlE5Z1ZzaUNQRzJ0OGZjeUFCMA==',
    'testfolder' : 'pythontest2'
}

# generate the WordPress-Class that will be tested
wp = WP_EXT_REST_API( wp_site2 )
print('Class generated')

# get all the image files from /testdata
testdata = os.path.join(SCRIPT_DIR, 'testdata')
files = os.listdir( testdata )
jpgfiles = [x for x in files if x.endswith('.jpg') == True]
webpfiles = [x for x in files if x.endswith('.webp') == True]
files = jpgfiles + webpfiles
print('Files collected')

# --------------- tests --------------------------------------------

def test_rest_api_request_without_login():
     url = wp.url + wp.rest_route

     response = requests.get(url)
     print('--- Get URL ', url, ' with status code:', response.status_code )
     assert response.status_code == 403

def test_rest_api_request_with_login_and_header():
     url = wp.url + wp.rest_route

     response = requests.get(url, headers=wp.headers )
     print('--- Get URL ', url, ' with status code:', response.status_code )
     assert response.status_code == 200

     # Validate response content type header
     assert response.headers["Content-Type"] == "application/json; charset=UTF-8"

def test_rest_api_request_with_login_base_url():
     url = wp.url + '/wp-json/'

     response = requests.get(url, headers=wp.headers )
     resp_body = response.json()

     print('site-info:name : ', resp_body['name'])
     print('site-info:descr: ', resp_body['description'])

     assert resp_body['url'] == wp.url
     assert resp_body['home'] == wp.url

def test_rest_api_request_https_status():
     url = wp.url + '/wp-json/wp-site-health/v1/tests/https-status'

     response = requests.get(url, headers=wp.headers )
     resp_body = response.json()

     print('--- https-status: ', resp_body['status'])
     assert resp_body['status'] == 'good'

def test_wp_site_basic_tests():
     assert wp.isgutbgactive == True
     assert wp.tested_plugin_activated == True
     assert len(wp.active_plugins) > 0
     assert len(wp.plugins) > 0
     assert len(wp.active_theme) > 0

     print('--- tested Plugin Name: ', wp.tested_plugin_name )
     assert wp.tested_plugin_name == 'Ext_REST_Media_Lib'

     print('--- WP-Version: ', wp.wpversion )
     assert wp.wpversion == '5.8.0'

def test_rest_api_request_plugin_status():
     url = wp.url + '/wp-json/wp/v2/plugins'

     response = requests.get(url, headers=wp.headers )
     resp_body = response.json()

     print('--- PI-Name is:', wp.tested_plugin_name)
     print('--- PI-Version:', wp.tested_plugin_version )
     print('---------------------------------------------')
     print('--- Installed Plugins:')
     for pi in resp_body:
          print('------ ', pi['name'])
          print('             Version ', pi['version'], ' is ', pi['status'])
          #print('------------- Description:', pi['description']['rendered'] )
             
     assert ( StrictVersion( wp.tested_plugin_version ) >= wp.tested_plugin_min_version ) == True     

def test_rest_api_request_active_theme():

     url = wp.url + '/wp-json/wp/v2/themes'

     response = requests.get(url, headers=wp.headers )
     resp_body = response.json()

     pi_index = find_plugin_in_json_resp_body(resp_body, 'status', 'active')

     print('--- Theme-Name is:', resp_body[pi_index]['stylesheet'] )
     print('---  Theme-Status:', resp_body[pi_index]['status'])
     print('--- Theme-Version:', resp_body[pi_index]['version'] )
     print('---------------------------------------------')
     print('--- Installed Themes:')
     for pi in resp_body:
          print('------ ', pi['name']['rendered'])
          print('             Version ', pi['version'], ' is ', pi['status'])
          #print('------------- ', pi['version'] )
          #print('------------- Description:', pi['description']['rendered'] )

     assert pi_index >= 0
     assert resp_body[pi_index]['status'] == 'active'
     assert ( StrictVersion( resp_body[pi_index]['version'] ) >= '0.0.1' ) == True

def test_get_number_of_images_in_medialib():
     wp.get_number_of_posts() 
     print ('--- Counted ' +  str(wp.media['count']) + ' images in the media library.')
     assert wp.media['count'] > 0

@pytest.mark.parametrize( "image_file", files)
def test_image_upload_with_ext_rest_api( image_file):
     image_number_before = wp.media['count']

     # get current time and
     # assume a maximumt offset of 5 secondes between server and local machine that runs the test
     uploadtime = datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%S")
     uploadtime = datetime.datetime.strptime( uploadtime, "%Y-%m-%dT%H:%M:%S") - datetime.timedelta(seconds=5) 

     print('--- Uploading file: ', image_file)
     result=wp.post_add_image_to_folder( wp.tested_site['testfolder'], image_file)
     
     if result['httpstatus'] == 200:
          current = len(wp.created_images)
          if current == 0: 
               n = 0
          else:  
               n = current

          wp.created_images[n] = {}
          wp.created_images[n]['id'] = result['id']
          wp.created_images[n]['gallery'] = result['gallery']
          wp.created_images[n]['original_file'] = result['new_file_name']
          wp.last_media_id = result['id']
          wp.last_index_in_created_images = n

     print('--- ', result['message'])
     
     # check the upload status. 
     assert result['httpstatus'] == 200
     #assert 0 == 1 # use this for debugging with pytest --pdb. pytest stops at the first assertion error
     
     # check the media id of the new created image in the media library
     print('--- last media id: ', wp.media['maxid'])
     print('--- new media id: ', result['id'])
     assert result['id'] > wp.media['maxid']
     if result['id'] > wp.media['maxid']:
          wp.media['maxid'] = result['id']

     # check the gallery
     print('--- gallery: ', result['gallery'])
     assert result['gallery'] == wp.tested_site['testfolder']

     # check the number of media in the media library
     wp.get_number_of_posts()
     print('--- new image count: ', wp.media['count'])
     assert wp.media['count'] == image_number_before + 1

     # retrieve the rest-response for the new created image
     result = wp.get_post_content( wp.media['maxid'], 'media' )
     # store the rest response to /media/id to wp.created_images
     if result['httpstatus'] == 200:
          wp.created_images[n]['post'] =  result
         

     # check the attachment type
     print('--- attachment tpye: ', result['media_type'])
     assert result['media_type'] == 'image'

     # check the guid
     expguid = wp.wp_upload_dir + image_file
     print('--- guid: ', result['guid']['rendered'])
     assert result['guid']['rendered'] ==  expguid 
     
     #assert result['source_url'] == expguid
     basename = os.path.splitext( image_file)[0]
     explink = wp.url + '/' + basename + '/'
     explink = explink.lower()
     explink = explink.replace('_','-')
     print('--- link: ', result['link'])
     assert result['link'] == explink # wp.url + filename ohne extension / am Ende

     # check the time
     imagetime = datetime.datetime.strptime( result['modified_gmt'], "%Y-%m-%dT%H:%M:%S")
     print('--- time: ', uploadtime, 'image-time: ', result['modified_gmt'])
     assert imagetime >= uploadtime # format "2021-08-16T14:51:39" upload-time
     
     # check image mime
     path = os.getcwd()
     fullpath = os.path.join(path, 'testdata', image_file)
     mime = magic.Magic(mime=True)
     mimetype = mime.from_file( fullpath )
     print('--- mime-type: ', result['mime_type'])
     assert result['mime_type'] == mimetype # "image/jpeg" oder "image/webp"
     
def test_id_of_created_images():
     #img = wp.created_images[ wp.last_index_in_created_images ]
     id = wp.last_media_id
     #assert 0 == 1 # use this for debugging with pytest --pdb. pytest stops at the first assertion error
     assert isinstance(id, int) == True

     end = len(wp.created_images)
     for i in range(0, end): 
           assert isinstance( wp.created_images[i]['id'], int) == True   

def test_clean_up():
     #assert 0 == 1 # use this for debugging with pytest --pdb. pytest stops at the first assertion error
     # delete all created images, posts, pages
     end = len(wp.created_images)
     for i in range(0, end): 
          result = wp.delete_media( wp.created_images[i]['id'], 'media' )
          print('--- Deleted media-id: ', wp.created_images[i]['id'])
          assert result['httpstatus'] == 200
     
     # delete all created posts
     end = len(wp.created_posts)
     for i in range(0, end):
          result = wp.delete_media( wp.created_posts[i]['id'], 'posts' )
          assert result['httpstatus'] == 200
     
     # delete all created pages
     end = len(wp.created_pages)
     for i in range(0, end):
          result = wp.delete_media( wp.created_pages[i]['id'], 'pages' )
          assert result['httpstatus'] == 200
     
     print('Done.')

if __name__ == '__main__':
     for f in files:
          test_image_upload_with_rest_api( f)