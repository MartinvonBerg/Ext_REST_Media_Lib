# System Test

The plugin is somehow 'completely' tested with a new python test suite (PTS). The PTS uses pytest and a bunch of other modules that have to be available.
I tried to reach a 'branch coverage' of 100% concerning the functional branches. It's almost impossible to test the paths that were implemented for very special
errprs on the server. The testdata contains *.webp and *.jpg filess with different sizes. The use cases are 'upload image file', 'change metadata', 'change mime type',
'update image file', 'create posts (Gutenberg: image, gallery, image-with-text )' and 'delete'. Tests were conducted on local and remote site. All fine. I could not 
claim a code coverage of 100% or even a test coverage of 100%. That is almost impossible. I concentrated on the main uses cases, as stated above 
and that is much, much better than manual testing like before.

## How to do the system test
- Install an empty, new WP site
- Install this plugin
- Install Gutenberg and Query Monitor plugin
- Clone the complete code from github to your local site
- Change the directory (cd) to the  ..../test directory in the cloned repository
- Provide a wp_site.json as described in ./test/test_rest-api.py
    ### Run the basic tests with: pytest -k 'basic'
    - The first run wil fail for one test if the required 'testfolder' did not exist on the server.
    - Run the basic tests once again with: pytest -k 'basic'. This testrun should pass with 100% now.
    - Check your WP-testsite and delete the generated image(s)
    ### Run the full test with: pytest -k 'testimage or testfield or testpost or cleanup'
    - Check the testreport.html after the test
    ### OR
    - Run the full test and stop it after the post generation with 
        - pytest -k 'testimage or testfield or testpost or cleanup or testwait' -s
    - check visually that all posts with image, gallery, image-with-text have flipped images (except one with changed mime-type)
    - continue the test with Enter to delete all generated images, posts etc. from WordPress
    ### OR
    - run pytest -k 'testimage or testfield or testpost' --> here you have to delete all generated images, posts etc. from WordPress manually
-   NOTE: Sometimes the test_clean_up() function does not delete all files in the ./testfolder on the server. Don't know why. 
-   So it is better to check that folder ./testfolder is really empty if the test fails.
- Finally, all tests should be PASSED and GREEN.

## Dependencies
Required Python modules: json, PIL, pytest, magic, pprint, requests (Requests HTTP Library), distutils