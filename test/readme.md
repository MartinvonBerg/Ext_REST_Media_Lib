# System Test

The plugin is 'completely' tested with a new python test suite (PTS). The PTS uses pytest and a bunch of other modules that have to be available for Python.

The test strategy is to reach a 'branch coverage' of 100% concerning the functional branches. It's almost impossible to test the paths that were implemented for very special
errors on the server. The testdata contains *.webp and *.jpg filess with different sizes. The use cases are 'upload image file', 'change metadata', 'change mime type',
'update image file', 'create posts (Gutenberg: image, gallery, image-with-text )' and 'delete'. Tests were conducted on local site, remote site and with a docker container. 
I could not claim a code coverage of 100% or even a test coverage of 100%. That is almost impossible. I concentrated on the main uses cases, as stated above and that is much, much better than manual testing like before.

## Preparation to run the system test with a docker container
- Clone the complete code from github to your local site
- Navigate to the file ./test/docker-compose.yml
- In this file check the line 20 with 
    ```yml 
    image: wordpress:beta-5.9-beta3-php8.1-apache
    ```  
    and decide which wordpress-, php and server version you want to test.
    The available tags could be found under: https://hub.docker.com/_/wordpress. Change the tag accordingly.
- Open a terminal an run 
    ```
    docker-compose up --force-recreate --renew-anon-volumes
    ```
    Just to be sure to recreate everything from the scratch
- Proceed with Python testing, see below (You should be in the folder ./test already)

## Preparation to run the system test with a local or remote site
- Install an empty, new WP site
- Install this plugin
- Install Gutenberg and Query Monitor plugin
- Clone the complete code from github to your local site
- Change the directory (cd) to the  ..../test directory in the cloned repository
- Provide a wp_site.json as described in ./test/test_rest-api.py

## Python-Testing
### Run the basic tests with: 
```python
    pytest -k 'basic'
```
- The first run will fail for one test if the required 'testfolder' did not exist on the server.
- Run the basic tests once again with: pytest -k 'basic'. This testrun should pass with 100% now.

### Run the full test with: 
```python
    pytest -k 'testimage or testfield or testpost or cleanup'
```
- Check the report-YYYY-MM-DD-hhmmss.html in the folder ./test/testreports after the test
### OR Run the full test and stop it after the post generation with 
```python
    pytest -k 'testimage or testfield or testpost or cleanup or testwait' -s
```
- check visually that all posts with image, gallery, image-with-text have flipped images (except one with changed mime-type)
- continue the test with Enter to delete all generated images, posts etc. from WordPress
### OR Run the full test without deletion of images, posts etc.
```python
    pytest -k 'testimage or testfield or testpost'
```

- Mind: Here you have to delete all generated images, posts etc. from WordPress manually
- NOTE: Sometimes the test_clean_up() function does not delete all files in the ./testfolder on the server. Don't know why. 
-   So it is better to check that folder ./testfolder is really empty if the test fails.
- Finally, all tests should be PASSED and GREEN.

## Dependencies
Required Python modules: json, PIL, pytest, magic, pprint, requests (Requests HTTP Library), distutils, docker install