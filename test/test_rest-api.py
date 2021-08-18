import requests
import json
from jsonschema import validate
from jsonschema import Draft6Validator
from distutils.version import StrictVersion

base_url = 'https://www.bergreisefoto.de/wordpress' 
base_url = 'https://www.mvb1.de'
rest_route = '/wp-json/'
rest_variables = ''
plugin_dir = 'wp-wpcat-json-rest/wp_wpcat_json_rest'
plugin_name = 'Ext_REST_Media_Lib'
plugin_min_version = '0.0.14'

mvbheaders = {
     'Authorization': 'Basic d3AyX2RoejptZ2xvYXczQmpCcUk3TWl5RmlKWTVROTA=',
     'Accept' : '*/*',
     'Accept-Encoding' : 'gzip, deflate, br',
     'Connection' : 'keep-alive',
     'User-Agent' : 'PostmanRuntime/7.28.3'
}

bergreiseheaders = {
     'Accept' : '*/*',
     'Accept-Encoding' : 'gzip, deflate, br',
     'Authorization': 'Basic TWFydGluOm4yc2pLdlE5Z1ZzaUNQRzJ0OGZjeUFCMA==',
     'Connection' : 'keep-alive',
     'User-Agent' : 'PostmanRuntime/7.28.3'
}

headers = mvbheaders

payload={}

schema = {
    "type" : "object",
    "properties" : {
        "status" : {"type" : "string"},
        "employee": {
            "type": "object",
            "properties": {
                "id": { "type": "string" },
                "firstName": { "type": "string" },
                "middleName": {
                    "anyOf": [
                        {"type": "string"},
                        {"type": "null"}
                    ] },
                "lastName": { "type": "string" }
            },
            "required": ["id", "firstName", "lastName"]
        }
    }
}

def find_plugin_in_json_resp_body( resp_body, key, plugin_name):
     index = 0
     for pi in resp_body:
          if pi[key] == plugin_name:
               break
          index += 1
     return index  


#url = base_url + rest_route
#url = base_url + '/wp-json/wp/v2/plugins'
#response = requests.get(url, headers=headers )
#resp_body = response.json()
#find_plugin_in_json_resp_body(resp_body, 'wp-wpcat-json-rest/wp_wpcat_json_rest')
#print ('init ready', 'wp-wpcat-json-rest/wp_wpcat_json_rest' in resp_body )

# --------------- tests --------------------------------------------

def test_rest_api_request_without_login():
     url = base_url + rest_route

     response = requests.get(url)
     print('--- Get URL ', url, ' with status code:', response.status_code )
     assert response.status_code == 403

def test_rest_api_request_with_login_and_header():
     url = base_url + rest_route

     response = requests.get(url, headers=headers )
     print('--- Get URL ', url, ' with status code:', response.status_code )
     assert response.status_code == 200

     # Validate response content type header
     assert response.headers["Content-Type"] == "application/json; charset=UTF-8"

def test_rest_api_request_with_login_base_url():
     url = base_url + rest_route

     response = requests.get(url, headers=headers )
     resp_body = response.json()

     print('site-info:name : ', resp_body['name'])
     print('site-info:descr: ', resp_body['description'])

     assert resp_body['url'] == base_url
     assert resp_body['home'] == base_url

     # Validate will raise exception if given json is not
     # what is described in schema.
     #validate(instance=resp_body, schema=schema)

def test_rest_api_request_https_status():
     url = base_url + '/wp-json/wp-site-health/v1/tests/https-status'

     response = requests.get(url, headers=headers )
     resp_body = response.json()

     print('--- https-status: ', resp_body['status'])
     assert resp_body['status'] == 'good'

def test_rest_api_request_plugin_status():
     url = base_url + '/wp-json/wp/v2/plugins'

     response = requests.get(url, headers=headers )
     resp_body = response.json()

     pi_index = find_plugin_in_json_resp_body(resp_body, 'plugin', plugin_dir)

     print('--- PI-Name is:', resp_body[pi_index]['name'], ' with expexted name: ', plugin_name)
     print('---  PI-Status:', resp_body[pi_index]['status'])
     print('--- PI-Version:', resp_body[pi_index]['version'] )
     print('---------------------------------------------')
     print('--- Installed Plugins:')
     for pi in resp_body:
          print('------ ', pi['name'])
          print('             Version ', pi['version'], ' is ', pi['status'])
          #print('------------- status:', pi['status'])
          #print('------------- ', pi['version'] )
          #print('------------- Description:', pi['description']['rendered'] )
             
     assert pi_index >= 0
     assert resp_body[pi_index]['status'] == 'active'
     assert resp_body[pi_index]['name'] == plugin_name
     assert ( StrictVersion( resp_body[pi_index]['version'] ) >= plugin_min_version ) == True

def test_rest_api_request_active_theme():
     url = base_url + '/wp-json/wp/v2/themes'

     response = requests.get(url, headers=headers )
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


     