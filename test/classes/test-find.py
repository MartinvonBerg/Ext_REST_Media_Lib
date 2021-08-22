# taken from the class tester file
#import os.path
#import magic
#import array
#import re
#import xmltojson
#from copy import copy

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
    id = 6538
   
    #result = wp.set_attachment_image_meta( id, 'media', {
    #    'image_meta' : { 
    #        'aperture' : '88', 
    #        'credit' : 'Martin von Berg', 
    #        'camera' : 'Nikon D7500',
    #        "caption": "des is a buildl mit schiffli",
    #        #"created_timestamp": "0",
    #        "copyright": "vom maddin",
    #        "focal_length": "500",
    #        "iso": "100",
    #        "shutter_speed": "0.001",
    #        "title": "superbild von mir mit schiffen",
    #        "orientation": "1",
    #        "keywords": [ 'bild', 'hafen', 'stralsund', 'schiffe']
    #        } } )
    #print ( str(result['httpstatus']) + ' : ' + result['message'] )

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

    #result = wp.get_update_image( 133 )
    #print ( str(result['httpstatus']) + ' : ' + result['message'] )

    #result = wp.post_add_image_to_folder( 'test333', 'DSC_1722.webp')
    #print ( str(result['httpstatus']) + ' : ' + result['message'] )
    # 
   #id = 133

    #content = wp.create_wp_image_gtb(id)
    #content += wp.create_wp_media_text_gtb(11, 'Verdammt des is so a sauwetter, da magst net raus', 75, 'false')
    #content += wp.create_wp_image_gtb(11)
    #content += wp.create_wp_image_gtb(241)
    ids = {
        0: '149',
        1: '148',
        2: '135',
        3: '280',
        4: '9999',
        5: '22',
        6: '23'
    }

    newcontent = wp.create_wp_gallery_gtb( ids, 3, 'Untertitel der besten Galerie aller Zeiten')
    
  
    data = \
    {
        "title":"Galerie mit sechs Bildern",
        "content": newcontent,
        "status": "publish",
    }

    result = wp.add_post( data, 'post' )
    print ( str(result['httpstatus']) + ' : ' + result['message'] )

    if result['httpstatus'] == 200:
        newcreated = result['id']
        newcontent = wp.get_post_content(newcreated, posttype='posts')
    
    #newcontent = newcontent.replace('\n','')
    #isJSON = validateJSON(newcontent)
    #if isJSON:
    #    html = xmltojson.parse(newcontent)
    #    new = json.loads(html)
    
    #doc = html.fromstring( newcontent)
    
    #result = find_image_tag_in_dic(new, 149, 'img', 'alt')
    # possible tag-values for an image in a gallery are:
    #       all with @ before: loading, width, height, src, alt, data-id, data-full-url, data-link, class, srcset, sizes
    # figcaption is parallel to image und figure in all cases and has #text and @class nothing else
    #print(result)

    #result = find_image_tag_in_dic(new, 149, 'figcaption', 'text')
    #print(result)
    

