import json, re, copy

def validateJSON(jsonData: str):
    try:
        json.loads(jsonData)
    except:
        return False
    return True

def find_plugin_in_json_resp_body( resp_body: dict, key: str, plugin_name: str):
    index = 0
    for pi in resp_body:
        if pi[key] == plugin_name:
            break
        index += 1
    return index  

def remove_html_tags(text: str):
    """Remove html tags from a string"""
    clean = re.compile('<.*?>')
    text = re.sub(clean, '', text)
    text = text.replace('\n','')
    return text

def find_value_in_dic(newdict: dict, id: int, tagtype: str, tag: str):
    """ Find an img-tag specified by id in a dictionary of html-tags 
    and return the path of keys (list-type) to the tag of this value."""
    result = []
    path = []
    
    # i is the index of the list that dict_obj is part of
    def find_path(dict_obj: dict ,key: str,i=None):
        for k,v in dict_obj.items():
            # add key to path
            path.append(k)
            if isinstance(v,dict):
                # continue searching
                find_path(v, key,i)
            if isinstance(v,list):
                # search through list of dictionaries
                for i,item in enumerate(v):
                    # add the index of list that item dict is part of, to path
                    path.append(i)
                    if isinstance(item,dict):
                        # continue searching in item dict
                        find_path(item, key,i)
                    # if reached here, the last added index was incorrect, so removed
                    path.pop()
            #if k == key:
            if v == key:
                # add path to our result
                result.append(copy(path))
            # remove the key added in the first line
            if path != []:
                path.pop()
    
    # find the value ins the dict: default starting index is set to None
    find_path(newdict , str(id))
    # [['figure', 'ul', 'li', 0, 'figure', 'img', '@data-id']]
    # get the tag of the 'img' element
    index = 0
    for t in result[0]:
        if index == 0:
            wert = newdict[t]
        else:
            wert = wert[t]
        if t == 'img':
            break
        index += 1

    imageindex = index

    if tagtype == 'img':        
        result = wert['@'+tag]

    elif tagtype == 'figcaption':
        if tag == 'text': 
            tag = '#text'
        else:
            tag = '@' + tag

        index = 0
        for t in result[0]:
            if index == 0:
                wert = newdict[t]
            else:
                wert = wert[t]
            if index == imageindex-1:
                break
            index += 1

        result = wert['figcaption'][tag]
            
    return result

def get_image( files, image_file):
    print(files)
    found = [x for x in files if image_file in x[1] ][0]
    return found