my_dict = {
    "attr": {
        "types": {
            "category": "employee", 
            "tag": {
                "gender": "male", 
                "name": "Tom"
            }
        }
    }
}

def dict_path(path,my_dict):
    for k,v in my_dict.items():
        if isinstance(v,list):
            for i, item in enumerate(v):
                dict_path( path + "." + k + "." + str(i), item)
        elif isinstance(v,dict):
            dict_path(path+"."+k,v)
        else:
            print (path+"."+k, "=>", v)


dict_path('tom', my_dict)

result = []
path = []
from copy import copy
 
# i is the index of the list that dict_obj is part of
def find_path(dict_obj,key,i=None):
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
 
# default starting index is set to None
find_path(my_dict,"Tom")
print(result)