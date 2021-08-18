
import requests
import json
import os.path
import magic
import array

# define the tested site. TODO: read this from a file
wp_site = {
    'url' : 'https://www.mvb1.de',
    'rest_route' : '/wp-json/wp/v2',
    'user' : 'wp2_dhz',
    'authentication' : 'Basic d3AyX2RoejptZ2xvYXczQmpCcUk3TWl5RmlKWTVROTA='
}

wp_site2 = {
    'url' : 'https://www.bergreisefoto.de/wordpress',
    'rest_route' : '/wp-json/wp/v2',
    'user' : 'martin',
    'authentication' : 'Basic TWFydGluOm4yc2pLdlE5Z1ZzaUNQRzJ0OGZjeUFCMA=='
}

def validateJSON(jsonData):
    try:
        json.loads(jsonData)
    except:
        return False
    return True

def find_plugin_in_json_resp_body( resp_body, key, plugin_name):
    index = 0
    for pi in resp_body:
        if pi[key] == plugin_name:
            break
        index += 1
    return index  

class WP_REST_API():
    """Class with the WordPress site that is tested"""
    url = ''
    rest_route = ''
    wpuser = ''
    wpauth = ''
    wpversion = '0.0.0'
    themes = {}
    active_theme = {}
    plugins = {}
    active_plugins = {}
    media = { 'count' : 0 }
    pages = { 'count' : 0 }
    posts = { 'count' : 0 }
    isgutbgactive = False

    media_writeable_rest_fields = { 'title', 'gallery_sort', 'description', 'caption', 'alt_text', 'image_meta' }
    mimetypes = { 'image/webp', 'image/jpg'}

    headers = {}

    def get_wp_version( self ): 
        self.wpversion = '5.8.0'

    def get_themes( self ):
        geturl = self.url + self.rest_route + '/themes'
        response = requests.get(geturl, headers=self.headers )
        resp_body = response.json()
        self.themes = resp_body

        pi_index = find_plugin_in_json_resp_body(resp_body, 'status', 'active')
        self.active_theme = resp_body[ pi_index]

    def get_plugins( self ):
        geturl = self.url + self.rest_route + '/plugins' 
        response = requests.get(geturl, headers=self.headers )
        resp_body = response.json()
        self.plugins = resp_body

        key = 'status'
        index = 0
        for pi in resp_body:
          if pi[key] == 'active':
              self.active_plugins[index] = pi
              if 'utenberg' in pi['name']:
                  self.isgutbgactive = True
              index += 1

    def get_number_of_posts( self, posttype= 'media'):
        if posttype == 'media':
            count = 0
            pagenumber = 1
            geturl = self.url + '/wp-json/wp/v2/media/?per_page=100&page='+ str(pagenumber)
            response = requests.get(geturl, headers=self.headers )
            resp_body = response.json()
            self.media['maxid'] = resp_body[0]['id']

            while len(resp_body) == 100:
                count = count + 100
                pagenumber += 1
                geturl = self.url + '/wp-json/wp/v2/media/?per_page=100&page='+ str(pagenumber)
                response = requests.get(geturl, headers=self.headers )
                resp_body = response.json()
            
            count = count + len(resp_body)
            self.media['minid'] = resp_body[ len(resp_body)-1 ]['id']
            self.media['count'] = count

        if posttype == 'pages':
            self.pages['count'] = 0

        if posttype == 'posts':
            self.posts['count'] = 0 
    
    def __init__(self, args_in_array):
        self.url = args_in_array['url']
        self.rest_route = args_in_array['rest_route']
        self.wpauth = args_in_array['authentication']
        self.headers = {
            'Authorization': self.wpauth,
            'Accept' : '*/*',
            'Accept-Encoding' : 'gzip, deflate, br',
            'Connection' : 'keep-alive',
            'User-Agent' : 'PostmanRuntime/7.28.3'
        }
        self.get_wp_version()
        self.get_themes()
        self.get_plugins()
        #self.get_number_of_posts( 'media')
        #super().__init__() # use this to call the constructor of the parent class if any
   
    def get_rest_fields( self, id, posttype= 'media', fields = {}):
        newfields = fields
        newfields['httpstatus'] = 0
        newfields['message'] = 'Wrong id for media: not an integer above zero.'

        if isinstance(id, int) and id>0: # omitt this check for a more complete test
            keys = fields.keys()
            append = ''
            for f in keys:
                append = append + f + ','

            append = append[:-1] # remove the last ","
            geturl = self.url + '/wp-json/wp/v2/' + posttype + '/' + str(id) + '/?_fields=' + append
            response = requests.get(geturl, headers=self.headers )
            
            resp_body = response.json()
            resp_body['_links'] = ''
            resp_body['httpstatus'] = response.status_code
            resp_body['message'] = 'Success'
            newfields = resp_body
        
        return newfields 

    def set_rest_fields( self, id, posttype= 'media', fields = {} ):

        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        for f in fields:
            if f not in self.media_writeable_rest_fields:
                # only the last one will be stored
                resp_body['message'] += 'Setting of Field "' + f + '" is not allowed'
                #return resp_body

        if posttype != 'media':
            resp_body['message'] = 'Setting fields only for media allowd'
            return resp_body

        if isinstance(id, int) and id> 0:
            #
            keys = fields.keys()
            append = ''
            for f in keys:
                if f != 'image_meta':
                    append = append + f + '=' + fields[f] + '&'
                elif f == 'image_meta':
                    resp_body['message'] += 'Found image_meta but did not write it. Use seperate method'

            append = append[:-1] # remove the last "&"
            geturl = self.url + '/wp-json/wp/v2/' + posttype + '/' + str(id) + '/?' + append
            response = requests.post(geturl, headers=self.headers )
            resp_body.update( response.json() ) 
            resp_body['httpstatus'] = response.status_code
            resp_body['message'] += 'Success'
        
        else:
            resp_body['message'] += 'Wrong id for media: not an integer above zero.'
        
        return resp_body

    def add_media( self, imagefile ):

        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # check path to the image file
        isfile = os.path.isfile( imagefile )
        if not isfile:
            path = os.getcwd()
            # TODO: this is only correct for windows!
            fname = path + '\\test\\testdata\\' + imagefile
            isfile = os.path.isfile( fname )
            if not isfile:
                return 0
        else: 
            fname = imagefile

        # read the image file to a binary string
        fin = open(fname, "rb")
        data = fin.read()
        fin.close()

        #get the base filename with extension
        imagefile = os.path.basename( fname )

        # check image mime
        mime = magic.Magic(mime=True)
        mimetype = mime.from_file(fname)
        if mimetype not in self.mimetypes:
            resp_body['message'] = 'Wrong mime type. Try it anyway.'
       
        # upload new image
        geturl = self.url + '/wp-json/wp/v2/media'
        # set the header. 
        header = self.headers
        header['Content-Disposition'] = 'form-data; filename=' + imagefile
        header['Content-Type'] = mimetype

        response = requests.post(geturl, headers=header, data=data )
        resp_body.update( json.loads( response.text) )

        # return id of the new image on success
        if response.status_code == 201:
            resp_body['httpstatus'] = response.status_code
            resp_body['message'] += response.reason
        else:
            resp_body['message'] += 'Error. Could not upload image.'

        return resp_body

    def delete_media( self, id, posttype= 'media' ):
        #do : http://127.0.0.1/wordpress/wp-json/wp/v2/media/3439?force=1
        
        # delete image 
        geturl = self.url + '/wp-json/wp/v2/media/' + str(id) + '?force=true'
        response = requests.delete(geturl, headers=self.headers )    
        resp_body = json.loads( response.text)
        resp_body['httpstatus'] = response.status_code
        resp_body['message'] = response.reason

        return resp_body


class WP_EXT_REST_API(WP_REST_API):
    """Extension with methods for Extended REST-API of the Class with the WordPress site that is tested"""
    tested_plugin_dir = 'wp-wpcat-json-rest/wp_wpcat_json_rest'
    tested_plugin_name = 'Ext_REST_Media_Lib'
    tested_plugin_min_version = '0.0.14'
    tested_plugin_version = ''
    tested_plugin_activated = False

    def get_tested_plugin ( self ):
        self.tested_plugin_version = ''
        index = find_plugin_in_json_resp_body( self.plugins, 'name', self.tested_plugin_name)
        self.tested_plugin_version = self.plugins[index]['version']
        if self.plugins[index]['status'] == 'active':
            self.tested_plugin_activated = True

    def __init__(self, args_in_array):
        super().__init__( args_in_array )
        self.get_tested_plugin()

    def set_attachment_image_meta( self, id, posttype= 'media', fields = {} ):

        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        for f in fields:
            if f not in self.media_writeable_rest_fields:
                resp_body['message'] = 'Setting of Field "' + f + '" is not allowed'

        if posttype != 'media':
            resp_body['message'] = 'Setting fields only for media allowd'
            return resp_body

        if isinstance(id, int) and id> 0:
            #
            keys = fields.keys()    

            for f in keys:           
                if f == 'image_meta':
                    # localhost/wordpress/wp-json/extmedialib/v1/update_meta/5866?docaption=true
                    geturl = self.url + '/wp-json/extmedialib/v1/update_meta/' + str(id) + '?docaption=true'
                    piece = {}
                    piece['image_meta'] = fields['image_meta']
                    body = json.dumps(piece) 

                    isvalidjson = validateJSON( body )

                    if not isvalidjson:
                        resp_body['message'] += 'Invalid JSON body in preparation of POST-Request'

                    header = self.headers    
                    header['Content-Type'] = "application/json"
                    response = requests.post(geturl, headers=header, data=body )
                    resp_body.update( response.json() )
                    resp_body['httpstatus'] = response.status_code
            
            if resp_body['message'] == '':
                resp_body['message'] = 'Field image_meta was not provided. No REST-request done.'

        else:
            resp_body['message'] += 'Wrong id for media: not an integer above zero.'
        
        return resp_body

    def get_update_image( self, id ):
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # upload new image
        geturl = self.url + '/wp-json/extmedialib/v1/update/' + str(id)
        
        response = requests.get(geturl, headers=self.headers )
        resp_body.update( json.loads( response.text) )

        # return id of the new image on success
        if response.status_code == 200:
            resp_body['httpstatus'] = response.status_code
        
        return resp_body

    def post_update_image( self, id, imagefile, changemime=True ):
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # check path to the image file
        isfile = os.path.isfile( imagefile )
        if not isfile:
            path = os.getcwd()
            # TODO: this is only correct for windows!
            fname = path + '\\test\\testdata\\' + imagefile
            isfile = os.path.isfile( fname )
            if not isfile:
                resp_body['message'] = 'File not found'
                return resp_body
        else: 
            fname = imagefile

        # read the image file to a binary string
        fin = open(fname, "rb")
        data = fin.read()
        fin.close()

        #get the base filename with extension
        imagefile = os.path.basename( fname )

        # check image mime
        mime = magic.Magic(mime=True)
        mimetype = mime.from_file(fname)
        if mimetype not in self.mimetypes:
            resp_body['message'] = 'Wrong mime type. Try it anyway.'
       
        # upload new image
        geturl = self.url + '/wp-json/extmedialib/v1/update/' + str(id)
        if changemime:
            geturl += '?changemime=true'
       
        # set the header. 
        header = self.headers
        header['Content-Disposition'] = 'form-data; filename=' + imagefile
        header['Content-Type'] = mimetype

        response = requests.post(geturl, headers=header, data=data )
        resp_body.update( json.loads( response.text) )

        # return id of the new image on success
        if response.status_code == 200:
            resp_body['httpstatus'] = response.status_code
        
        else:
            resp_body['message'] += 'Error. Could not update image.'

        return resp_body
       
    def get_add_image_to_folder( self, folder ):
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # upload new image
        geturl = self.url + '/wp-json/extmedialib/v1/addtofolder/' + folder
        
        response = requests.get(geturl, headers=self.headers )
        resp_body.update( json.loads( response.text) )

        # return id of the new image on success
        resp_body['httpstatus'] = response.status_code
        
        return resp_body

    def post_add_image_to_folder( self, folder, imagefile ):
        method = 'post'
        return 0

    def get_add_image_from_folder( self ):
        method = 'get'
        return 0

    def post_add_image_from_folder( self ):
        method = 'post'
        return 0    

if __name__ == '__main__':
    wp = WP_EXT_REST_API( wp_site2 )   
    wp.get_number_of_posts() 
    print (wp.url,  ' with version: ', wp.wpversion) 
    #wp.media['count'] = 93
    print ('Counted ' +  str(wp.media['count']) + ' images in the media library.')
    fields = { 
        'gallery' : '',
        'gallery_sort' : '',
        'media_details' : '' ,
        "md5_original_file": '',
        'caption' : '',
        'alt_text' : '',
        'mime_type': '',
        'media_details' : ''
    }
    id = 133
   
    result = wp.set_attachment_image_meta( id, 'media', {
        'image_meta' : { 
            'aperture' : '88', 
            'credit' : 'Martin von Berg', 
            'camera' : 'Nikon D7500',
            "caption": "des is a buildl mit schiffli",
            #"created_timestamp": "0",
            "copyright": "vom maddin",
            "focal_length": "500",
            "iso": "100",
            "shutter_speed": "0.001",
            "title": "superbild von mir mit schiffen",
            "orientation": "1",
            "keywords": [ 'bild', 'hafen', 'stralsund', 'schiffe']
            } } )
    print ( str(result['httpstatus']) + ' : ' + result['message'] )

    result = wp.get_rest_fields( id, 'media', fields )
    print ( str(result['httpstatus']) + ' : ' + result['message'] )
    #print(json.dumps( result['media_details']['image_meta'], indent=4, sort_keys=True))

    #result = wp.add_media( 'DSC_1722.webp')
    #print ( str(result['httpstatus']) + ' : ' + result['message'] )
    #print('Done. Added media with id: ', result['id'])
    
    #result = wp.delete_media( result['id'])
    #print ( str(result['httpstatus']) + ' : ' + result['message'] )

    #result = wp.post_update_image(id, 'DSC_1722.webp')
    #print ( str(result['httpstatus']) + ' : ' + result['message'] )

    result = wp.get_update_image( 133 )
    print ( str(result['httpstatus']) + ' : ' + result['message'] )

    result = wp.get_add_image_to_folder( 'test333' )
    print ( str(result['httpstatus']) + ' : ' + result['message'] )
    # 
    