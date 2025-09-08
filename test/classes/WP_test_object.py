
import requests
import json, re
import os.path
import magic
from helper_functions import find_plugin_in_json_resp_body, remove_html_tags, validateJSON

import os, sys

SCRIPT_DIR = os.path.dirname(os.path.realpath(os.path.join(os.getcwd(), os.path.expanduser(__file__))))
sys.path.append(SCRIPT_DIR)

# Class definitions for WordPress
class WP_REST_API():
    """Class with methods to access a WordPress site via the REST-API"""
    url = ''
    baseurl = ''
    suburl = ''
    rest_route = ''
    headers = {}
    #wpuser = ''
    #wpauth = ''
    wpversion = '0.0.0'
    themes = {}
    active_theme = {}
    plugins = {}
    active_plugins = {}
    media = { 'count' : 0 }
    pages = { 'count' : 0 }
    posts = { 'count' : 0 }
    isgutbgactive = False
    isqmactive = False

    media_writeable_rest_fields = { 'title', 'gallery_sort', 'description', 'caption', 'alt_text', 'image_meta' }
    mimetypes = { 'image/webp', 'image/jpeg'}

    # properties for creating links, slug, title etc. all data in the rest-response containing url and file names
    wp_upload_dir = ''
    wp_upload_url = ''
    updir = ''
    subupdir = ''
    real_wp_upload_dir = '' # confusing second definition for the dir needed

    usescompleteurls = False
    hassuburl = False
    dicturl = {}
    

    def get_wp_version( self ): 
        response = requests.get( self.url )
        sfind = 'meta name="generator" content='
        sresp = str(response.content)
        pos = sresp.find( sfind )
        length = len(sfind)
        pos = pos + length
        pos2 = sresp.find( '/>', pos ) -1
        if (pos2 > pos) and (pos > 0):
            version = sresp[pos:pos2]
            self.wpversion = version.replace('"','')
        else:
            self.wpversion = '6.1 (TBC)'

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
                if 'Query Monitor' in pi['name']:
                    self.isqmactive = True
            index += 1

    def get_number_of_posts( self, posttype='media'):
        if posttype == 'media':
            count = 0
            pagenumber = 1
            geturl = self.url + '/wp-json/wp/v2/media/?per_page=100&page='+ str(pagenumber)
            response = requests.get(geturl, headers=self.headers )
            resp_body = response.json()

            # get the (relative) upload dir of wordpress from the first media
            guid = resp_body[00]['guid']['rendered']
            base = os.path.basename(guid)
            self.wp_upload_dir = guid.replace(base, '')

            # get the maxid of images in the medialib which we assume to retrieve by the first request
            # get the minid of the first reponse, could be anywhere due to updates, even at the beginning
            allids = [d['id'] for d in resp_body]
            maxid = 0
            minid = 1000000
            for id in allids:
                if id > maxid: maxid = id
                if id < minid: minid = id

            self.media['maxid'] = maxid
                       
            # retrieve all images in the medialib 
            while len(resp_body) == 100:
                count = count + 100
                pagenumber += 1
                geturl = self.url + '/wp-json/wp/v2/media/?per_page=100&page='+ str(pagenumber)
                response = requests.get(geturl, headers=self.headers )
                resp_body = response.json()

                allids = [d['id'] for d in resp_body]
                for id in allids:
                    if id < minid: minid = id
            
            count = count + len(resp_body)
            self.media['count'] = count
            self.media['minid'] = minid

            # get the upload dir
            geturl = self.url + '/wp-json/wp/v2/media/' + str(maxid)
            response = requests.get(geturl, headers=self.headers )
            resp_body = response.json()

            if response.status_code == 200:
                guid = resp_body['guid']['rendered']
                guid = guid.replace(self.url, '')
                base = os.path.basename(guid) # base ist hier der Dateiname der upload-datei
                base = guid.replace(base, '')
                for letter in base:
                    if letter.isdigit():
                        base = base.replace(letter, '')
                base = base.replace('//', '')   

                if self.hassuburl:
                    base = base.replace(self.suburl, '') 

                self.wp_upload_dir = base
                self.wp_upload_url = self.url + base
                self.updir = self.ensure_slashes( base )

                pos = resp_body['guid']['rendered'].find( self.url )
                if pos>-1:
                    self.usescompleteurls = True

                # UPDATE the dictionary with url parts used for creating all the links
                self.dicturl['ud'] = self.updir

        if posttype == 'pages':
            self.pages['count'] = 0

        if posttype == 'posts':
            self.posts['count'] = 0 
    
    def ensure_slashes(self, string):
        """ Ensure standard format for url parts: no leading slash one trailing slash."""
        string = string.replace('///', '/')
        string = string + '/'
        string = string.replace('//', '/')
        string = string.lstrip('/')
        return string

    def ensure_http(self, url):
        regex = r'https:/[^/].*'
        matches = re.search(regex, url)
        if matches != None:
            url = url.replace('https:/', 'https://')

        regex = r'http:/[^/].*'
        matches = re.search(regex, url)
        if matches != None:
            url = url.replace('http:/', 'http://')    
        
        return url

    def __init__(self, args_in_array: dict):
        self.url = args_in_array['url']
        self.rest_route = args_in_array['rest_route']
        self.wpauth = args_in_array['authentication']
        self.headers = {
            'Authorization': self.wpauth,
            #'Accept' : '*/*',
            #'Accept-Encoding' : 'gzip, deflate, br',
            #'Connection' : 'keep-alive',
            'User-Agent' : 'PostmanRuntime/7.28.3'
        }
        self.get_wp_version()
        self.get_themes()
        self.get_plugins()

        # get baseurl and suburl
        subs = self.url.replace('//', '/')
        subs = subs.split('/')
        nparts = len(subs)
        part = ''
        if nparts > 2:
            self.hassuburl = True
            for i in range(2, nparts ):
              part += '/' + subs[i]
        self.suburl = self.ensure_slashes( part ) # verändert durch ensure_slashes
        self.baseurl = self.ensure_slashes( self.url )
        self.baseurl = self.ensure_http( self.baseurl )
        self.baseurl = self.baseurl.replace( self.suburl, '')
        self.subupdir = self.ensure_slashes( args_in_array['testfolder'] )

        # the dictionary with url parts used for creating all the links
        self.dicturl = {'bu': self.baseurl,
                    'su' : self.suburl,
                    'ud': '',
                    'uf': self.subupdir,
                    'fb' : '', # ist an sich nicht nötig
                    'e': ''}
        
    def get_rest_fields( self, id: int, posttype='media', fields = {}):
        
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = 'Wrong id for media: not an integer above zero.'

        if isinstance(id, int) and id>0: # omitt this check for a more complete test
            keys = fields.keys()
            append = ''
            for f in keys:
                append = append + f + ','

            append = append[:-1] # remove the last ","
            geturl = self.url + '/wp-json/wp/v2/' + posttype + '/' + str(id) + '/?_fields=' + append
            response = requests.get(geturl, headers=self.headers )
            header = response.headers._store
            
            resp_body = response.json()
            resp_body['httpstatus'] = response.status_code
            
            if response.status_code == 200:
                resp_body['message'] = response.reason
        
        return resp_body, header 

    def set_rest_fields( self, id: int, posttype='media', fields = {} ):

        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        for f in fields:
            if f not in self.media_writeable_rest_fields:
                # only the last one will be stored
                resp_body['message'] += 'Setting of Field "' + f + '" is not allowed'
                #return

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
            header = response.headers._store
            #idpos = response.text.find('"id"')
            #st = response.text[idpos-1:]
            #response.text = st
            #resp_body.update( response.json() ) 
            resp_body['httpstatus'] = response.status_code
            resp_body['message'] += 'Success'
        
        else:
            resp_body['message'] += 'Wrong id for media: not an integer above zero.'
        
        return resp_body, header

    def add_media( self, imagefile: str, newname='' ):

        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # check path to the image file
        isfile = os.path.isfile( imagefile )
        if not isfile:
            path = os.getcwd()
            fname = os.path.join(path, 'testdata', imagefile) 
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

        # check if the file should be renamed
        if newname != '':
            imagefile = newname

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

    def delete_media( self, id, posttype='media' ):
        #do : http://127.0.0.1/wordpress/wp-json/wp/v2/media/3439?force=1
        
        geturl = self.url + '/wp-json/wp/v2/' + posttype + '/' + str(id)

        # delete image or post
        if  posttype=='media':
            geturl = geturl + '?force=true'
        response = requests.delete(geturl, headers=self.headers )    
        resp_body = json.loads( response.text)
        resp_body['httpstatus'] = response.status_code
        resp_body['message'] = response.reason

        return resp_body

    def add_post( self, data: dict, posttype='post' ):
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # upload new image
        if posttype == 'post':
            geturl = self.url + '/wp-json/wp/v2/posts/'
        elif posttype == 'page':
            geturl = self.url + '/wp-json/wp/v2/pages/'
        else:
            resp_body['message'] = 'wrong posttype'
            return resp_body

        header = self.headers
        header["Content-Type"] = "application/json"

        response = requests.post( geturl, headers=header, data=json.dumps(data) )
        body = json.loads( response.text)
        resp_body['httpstatus'] = response.status_code
        resp_body.update(body)

        if response.status_code == 201:
                resp_body['message'] = response.reason

        return resp_body

    def get_post_content(self, id: int, posttype='posts'):
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = 'Wrong id for media: not an integer above zero.'

        geturl = self.url + '/wp-json/wp/v2/' + posttype + '/' + str(id)
        response = requests.get(geturl, headers=self.headers )
        
        resp_body = response.json()
        #resp_body['_links'] = ''
        resp_body['httpstatus'] = response.status_code
        resp_body['headers'] = response.headers
        if response.status_code == 200:
            resp_body['message'] = 'OK'
        
        return resp_body

    def create_wp_image_gtb (self, id: int):
        
        fields = {}
        (result, header) = self.get_rest_fields(id, 'media', fields)
        
        altcaption = result['media_details']['image_meta']['caption']
        altcaption_from_title = result['media_details']['image_meta']['title']

        caption = result['caption']['rendered']
        if caption != '':
            caption = '<figcaption>' + result['caption']['rendered'] + '</figcaption>'

        alt = result['alt_text']
        src = result['media_details']['sizes']['full']['source_url']
        # TODO: This won't work if size full is not available!

        if result['httpstatus'] == 200:
             content = f'\
                <!-- wp:image {{"id":{id},"sizeSlug":"large","linkDestination":"none"}} -->\
                    <figure class="wp-block-image size-large">\
                    <img src="{src}" alt="{alt}" class="wp-image-{id}"/>\
                    {caption}</figure>\
                <!-- /wp:image -->' 
        else: 
            content = 'Could not find image that was requested to generate the wp-image block!'
        
        return content

    def create_wp_media_text_gtb (self, id:int, text: str, imagewidth=50, imageFill ='false'):
        # wp:media-text does not have a caption
        fields = {}
        (result, header) = self.get_rest_fields(id, 'media', fields)
        
        alt = result['alt_text']
        # TODO: This won't work if size full is not available!
        try:
            src = result['media_details']['sizes']['full']['source_url']
        except:
            src = result['media_details']['sizes']['large']['source_url']
        finally:
            src = ''
            
        link = result['link']

        if result['httpstatus'] == 200:
            content = f'\
                <!-- wp:media-text {{"mediaId":{id},"mediaLink":"{link}","mediaType":"image","mediaWidth":{imagewidth},"imageFill":{imageFill}}} -->\
                    <div class="wp-block-media-text alignwide is-stacked-on-mobile" style="grid-template-columns:{imagewidth}% auto">\
                    <figure class="wp-block-media-text__media">\
                        <img src="{src}" alt="{alt}" class="wp-image-{id} size-full" />\
                    </figure>\
                    <div class="wp-block-media-text__content">\
                    <!-- wp:paragraph {{"placeholder":"Inhalt...","fontSize":"large"}} -->\
                        <p class="has-large-font-size">{text}</p>\
                    <!-- /wp:paragraph --></div></div>\
                <!-- /wp:media-text -->' 
        else: 
            content = 'Could not find image that was requested to generate the wp-image block!'
        
        return content

    def create_wp_gallery_gtb( self, ids={}, columns=2, galcaption = ''):
        fields = {}
        idsstring = ",".join( ids.values() )
        content = ''
        columens = str(columns)
        if galcaption == '':
            galcaption = 'No Caption for the Galery provided.'
        

        for id in ids.values():
            (result, header) = self.get_rest_fields( int(id), 'media', fields)

            # check http status, skip if not 200 and remove id from idsstring
            # TODO: This won't work if size full or large are not available!
            if result['httpstatus'] == 200:
                alt = result['alt_text']
                srcfull = result['media_details']['sizes']['full']['source_url']
                try:
                    src = result['media_details']['sizes']['large']['source_url']
                except:
                    src = result['media_details']['sizes']['full']['source_url']

                link = result['link']

                caption = result['caption']['rendered']
                caption = remove_html_tags( caption )
                if caption == '':
                    caption = 'No Caption found'
                else:
                    caption = '<figcaption>' + caption + '</figcaption>'
                 
                content += f'\
                            <!-- wp:image {{"id":{id},"sizeSlug":"large","linkDestination":"none"}} -->\
                            <figure class="wp-block-image size-large">\
                                <img src="{src}" alt="{alt}" class="wp-image-{id}"/>{caption}\
                            </figure><!-- /wp:image -->'
            
            else:
                idsstring = idsstring.replace( id + ',', '')
                idsstring = idsstring.replace( id, '')
             
        contentbefore= f'<!-- wp:gallery {{"columns":{columns},"linkTo":"none"}} -->\
                    <figure class="wp-block-gallery has-nested-images columns-{columns} is-cropped">'    

        content = contentbefore + content    
        
        content += f'<figcaption class="blocks-gallery-caption">{galcaption}</figcaption>\
                    </figure><!-- /wp:gallery -->'
        
        return content

class WP_EXT_REST_API( WP_REST_API ):
    """Extend the class WP_REST_API with methods for the plugin that
    extendeds the REST-API of WordPress to update images and add images 
    to dedicated folders."""
    tested_plugin_dir = 'wp-wpcat-json-rest/wp_wpcat_json_rest'
    tested_plugin_name = 'Media Library Extension'
    #tested_plugin_name = 'Extended_REST-API_for_Media_Library'
    tested_plugin_min_version = '0.1.3'
    tested_plugin_version = ''
    tested_plugin_activated = False
    created_images = {}
    created_posts = {}
    created_pages = {}
    tested_site = {}
    last_media_id = 0
    last_index_in_created_images = 0
    img_isscaled = False # this should go to a "tested_image_class"
    showallPHPerrors = False

    # dieses guid ist für jede variable anders
    # Abweichung ist beim Kenner 'fb', der nicht boolsch ist, sondern ein multiplechoice
    genguid = {'bu' : 0, 'su' : 1, 'ud': 1, 'uf': 1, 'fb' : 'l+u', 'e': 1}
    gensurl = {'bu' : 1, 'su' : 1, 'ud': 1, 'uf': 1, 'fb' : 'orig', 'e': 0}

    # das dictfb = dict für base-filename ist für alle einheitlich,
    dictfb = {'orig': '', 'lower':'', 'under':'', 'l+u':'', 'ext': '', 'scaled' : '', 'pattern' : ''}

    dictall = {'guid':'', # rendered guid only
               'slug':'',
               'link':'',
               'title':'',
               'mediaDetailsFile':'',
               'mediaDetailsSizesFile':'',
               'mediaDetailsSizesSrcUrl':'',
               'mediaDetailsoriginalFile':'',
               'sourceUrl':'', #source_url
               }

    

    def get_tested_plugin ( self ):
        """ Get some information about the tested plugin."""
        self.tested_plugin_version = ''
        index = find_plugin_in_json_resp_body( self.plugins, 'name', self.tested_plugin_name)
        try:
            self.tested_plugin_version = self.plugins[index]['version']
            if self.plugins[index]['status'] == 'active':
                self.tested_plugin_activated = True
        except:
            self.tested_plugin_activated = False

    def generate_dictfb( self, filename: str ):
        """ generate the variations of the filename which must be base.extenstion without the path!
            The extension shall be stored with '.' as first chracter."""
        base = os.path.splitext(filename)[0] # basefilename
        ext = os.path.splitext(filename)[1] #extension

        if base == '' or ext == '':
            return 0

        # different variants of the base filename
        lower = base.lower()
        under = base.replace('-','_')
        minus = base.replace('_','-')
        lower_under = under.lower()
        minus_under = minus.lower()

        self.dictfb = {
                'orig': base, 
                'orig-m' : minus,
                'lower': lower, 
                'under': under, 
                'l+u': lower_under,
                'l-m': minus_under,
                'ext': ext,
                'scaled' : base + '-scaled',
                'patr' : base + "-[0-9]+x[0-9]+"
                }
    
    def generate_dictall( self ):

        def dothedict( x ):
            value = ( "{bu}{su}{ud}{uf}{fb}{e}".format(\
                bu=self.dicturl['bu'] if x['bu'] == 1 else '',\
                su=self.dicturl['su'] if x['su'] == 1 else '',\
                ud=self.dicturl['ud'] if x['ud'] == 1 else '',\
                uf=self.dicturl['uf'] if x['uf'] == 1 else '',\
                fb=self.dictfb[ x['fb']] if x['fb'] != 'no' else '',\
                e=self.dictfb['ext'] if x['e'] == 1 else ''))
            return value

        # usescompleteurls = False
        y = 0
        if self.usescompleteurls == True:
            y = 1 
        # hassuburl = True : Doesn't matter because suburl is empty if not used
        # case : isscaled = False. Generate the dict for the string generator 
        # 
        genguid =     {'bu' : y, 'su' : 1, 'ud': 1, 'uf': 1, 'fb' : 'orig' , 'e': 1} # no slash at the end. rendered. leading slash!
        #                     1   usescompleteurls = True
        genslug =     {'bu' : 0, 'su' : 0, 'ud': 0, 'uf': 0, 'fb' : 'lower', 'e': 0} # no slash at the end
        genlink =     {'bu' : 1, 'su' : 1, 'ud': 0, 'uf': 0, 'fb' : 'lower', 'e': 0} # slash at the end !!! # localhost with windows: 'l-m' but: remote linux: 'lower'
        gentitle =    {'bu' : 0, 'su' : 0, 'ud': 0, 'uf': 0, 'fb' : 'orig' , 'e': 0} # no slash at the end. rendered
        mdfile =      {'bu' : 0, 'su' : 0, 'ud': 0, 'uf': 1, 'fb' : 'orig' , 'e': 1} # fb is orig with -scaled if scaled 
        mdsizesfile = {'bu' : 0, 'su' : 0, 'ud': 0, 'uf': 0, 'fb' : 'patr' , 'e': 1} # fb is orig with pattern "-[0-9]+x[0-9]+" for size at the end
        mdsizessurl = {'bu' : y, 'su' : 1, 'ud': 1, 'uf': 1, 'fb' : 'patr' , 'e': 1} # fb is orig with pattern "-[0-9]+x[0-9]+" for size at the end
        #                     1   usescompleteurls = True
        mdorigimage = {'bu' : 0, 'su' : 0, 'ud': 0, 'uf': 0, 'fb' : 'orig' , 'e': 1} # only available if file is scaled
        gensurl =     {'bu' : y, 'su' : 1, 'ud': 1, 'uf': 1, 'fb' : 'orig' , 'e': 1} # fb is orig with -scaled if scaled. leading slash!
        #                     1   usescompleteurls = True

        # case that image is scaled 
        if self.img_isscaled:
            mdfile['fb'] =  'scaled' # fb is orig filename with -scaled if scaled !!!
            gensurl['fb'] = 'scaled' # fb is orig filename with -scaled if scaled !!!
            # media_details.sizes.full.(file / source_url) : -scaled !!!
        
        # special cases for my localhost on windows
        #pos = self.baseurl.find('127.0.0.1')
        #if pos>-1: 
        #    genlink['fb'] = 'l-m'
        #    genslug['fb'] = 'l-m'
        #    gentitle['fb'] = 'orig-m
        #        
        self.dictall['guid'] = dothedict(genguid)
        self.dictall['slug'] = dothedict(genslug)
        self.dictall['link'] = dothedict(genlink)
        self.dictall['title'] = dothedict(gentitle)
        self.dictall['mediaDetailsFile'] = dothedict(mdfile)
        self.dictall['mediaDetailsSizesFile'] = dothedict(mdsizesfile)
        self.dictall['mediaDetailsSizesSrcUrl'] = dothedict(mdsizessurl)
        self.dictall['mediaDetailsoriginalFile'] = dothedict(mdorigimage)
        self.dictall['sourceUrl'] = dothedict(gensurl)

        # special cases
        self.dictall['link'] = self.dictall['link'] + '/' # slash at the end !!!
        if self.usescompleteurls == False:
            self.dictall['guid'] = '/' + self.dictall['guid'] 
            self.dictall['sourceUrl'] = '/' + self.dictall['sourceUrl']
            self.dictall['mediaDetailsSizesSrcUrl'] = '/' + self.dictall['mediaDetailsSizesSrcUrl']

    def __init__(self, args_in_array):
        super().__init__( args_in_array )
        self.tested_site = args_in_array
        self.get_tested_plugin()

    def get_attachment_image_meta( self, id: int ):
        """ Call the GET-method of route 'update' of REST-API Extension"""
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # upload new image
        geturl = self.url + '/wp-json/extmedialib/v1/update_meta/' + str(id)
        
        response = requests.get(geturl, headers=self.headers )
        resp_body.update( json.loads( response.text) )
        header = response.headers._store

        # return id of the new image on success
        resp_body['httpstatus'] = response.status_code
        
        return resp_body, header

    def set_attachment_image_meta( self, id: int, posttype= 'media', fields = {} ):
        """ Write the image_meta given in fields via REST-API Extension to WordPress. 
        Othe values than image_meta are silently ignored."""
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
                    geturl = self.url + '/wp-json/extmedialib/v1/update_meta/' + str(id) 
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
                    header = response.headers._store
            
            if resp_body['message'] == '':
                resp_body['message'] = 'Field image_meta was not provided. No REST-request done.'

        else:
            resp_body['message'] += 'Wrong id for media: not an integer above zero.'
        
        return resp_body, header

    def get_update_image( self, id: int ):
        """ Call the GET-method of route 'update' of REST-API Extension"""
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # upload new image
        geturl = self.url + '/wp-json/extmedialib/v1/update/' + str(id)
        
        response = requests.get(geturl, headers=self.headers )
        resp_body.update( json.loads( response.text) )

        # return id of the new image on success
        resp_body['httpstatus'] = response.status_code
        header = response.headers._store
        
        return resp_body, header

    def post_update_image( self, id: int, imagefile: str, changemime=True ):
        """ Call the POST-method of route 'update' of REST-API Extension. Update the image 
        with the provided path to the imagefile. Update meta-data separately."""
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # check path to the image file
        isfile = os.path.isfile( imagefile )
        if not isfile:
            path = os.getcwd()
            fname = os.path.join(path, 'testdata', imagefile) 
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
            geturl = geturl + '?changemime=true'
       
        # set the header. 
        header = self.headers
        header['Content-Disposition'] = 'form-data; filename=' + imagefile
        header['Content-Type'] = mimetype

        response = requests.post(geturl, headers=header, data=data )
        resp_body.update( json.loads( response.text) )

        # return id of the new image on success
        resp_body['httpstatus'] = response.status_code
        header = response.headers._store
        
        #if response.status_code != 200:
        #    resp_body['message'] = resp_body['message'] + 'Error. Could not update image.'

        return resp_body, header
       
    def get_add_image_to_folder( self, folder: str ):
        """ Call the GET-method of route 'addtofolder' of REST-API Extension."""
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # upload new image
        geturl = self.url + '/wp-json/extmedialib/v1/addtofolder/' + folder
        
        response = requests.get(geturl, headers=self.headers )
        resp_body.update( json.loads( response.text) )
        header = response.headers._store

        # return id of the new image on success
        resp_body['httpstatus'] = response.status_code

        
        return resp_body, header

    def post_add_image_to_folder( self, folder: str, imagefile: str ):
        """ Call the POST-method of route 'addtofolder' of REST-API Extension and add
        the provided imagefile to the folder under ../uploads in WordPress. Similar to
        method 'add_media' of the Base-Class but adds the image to a dedicated folder."""
        resp_body = {}
        resp_body['httpstatus'] = 0
        resp_body['message'] = ''

        # check path to the image file
        isfile = os.path.isfile( imagefile )
        if not isfile:
            path = os.getcwd()
            fname = os.path.join(path, 'test/testdata', imagefile)
            print('Upload File: ', fname)
            isfile = os.path.isfile( fname )
            if not isfile:
                resp_body['message'] = 'Cannot find file'
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
        geturl = self.url + '/wp-json/extmedialib/v1/addtofolder/' + folder
        # set the header. 
        header = self.headers
        header['Content-Disposition'] = 'attachment; filename=' + imagefile
        header['Content-Type'] = mimetype

        response = requests.post(geturl, headers=header, data=data )
        resp_body.update( json.loads( response.text) )
        header = response.headers._store

        # return id of the new image on success
        resp_body['httpstatus'] = response.status_code
        #resp_body['message'] += response.reason
       
        return resp_body, header
      
    # ------ This methodes are currently not implemented and therefore not tested
    def get_add_image_from_folder( self ):
        method = 'get'
        return 0

    def post_add_image_from_folder( self ):
        method = 'post'
        return 0    
# End of Class