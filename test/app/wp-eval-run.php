<?php
ini_set( 'display_errors', 1 );
error_reporting(E_ALL);

global $wpdb;

// die "1" ist die User-ID. Kann anders sein!
$Json = file_get_contents("/tmp/user.json");
// Converts to an array 
$myarray = json_decode($Json, true);
$user = $myarray["ID"];
$login = $myarray["user_login"];

WP_Application_Passwords::delete_all_application_passwords( $user );
$result = WP_Application_Passwords::create_new_application_password( $user, array( 'name' => 'test' ));

$infoForPythonAccess = array(
    "url" => "http://localhost",
    "rest_route" => "/wp-json/wp/v2",
    "user" => $login,
    "password" => $result[0],
    "testfolder" => "python"
);

echo json_encode($infoForPythonAccess);