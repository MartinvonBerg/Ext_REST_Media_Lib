<?php
//-------------------- AUTH REQUIRED ------------------------------------------------
// https://developer.wordpress.org/rest-api/frequently-asked-questions/
// according to https://wordpress.stackexchange.com/questions/403710/permission-callback-to-check-if-user-has-application-password this is fully correct
// ATTENTION: Do not use username and Password or Application Passwords from WP-AdminPage > Users > Profiles together with basic-auth and with http !!!!!!
// Only use together with https
// require the user to be logged in for all REST requests
add_filter( 'wp_is_application_passwords_available', '__return_true' );

add_filter('rest_authentication_errors', function ( $result ) {
	// If a previous authentication check was applied,
	// pass that result along without modification.
    if ( true === $result || is_wp_error( $result ) ) {
        return $result;
    }
 
	// No authentication has been performed yet.
	// Return an error if user is not logged in.
	if ( ! is_user_logged_in()) {
		return new \WP_Error(
			'rest_not_logged_in',
			__('You are not currently logged in.'),
			array( 'status' => 401 )
		);
	}
 
	// Our custom authentication check should have no effect
	// on logged-in requests
	return $result;
});