def find_plugin_in_json_resp_body( resp_body, key, plugin_name):
     index = 0
     for pi in resp_body:
          if pi[key] == plugin_name:
               break
          index += 1
     return index  