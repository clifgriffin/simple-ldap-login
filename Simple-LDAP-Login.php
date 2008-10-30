<?php
/*
Plugin Name: Simple LDAP Login
Plugin URI: http://clifgriffin.com/2008/10/28/simple-post-template-wordpress-plugin/ 
Description:  Authenticates Wordpress usernames against LDAP.
Version: 1.0 
Author: Clifton H. Griffin II
Author URI: http://clifgriffin.com
*/
require_once( WP_PLUGIN_DIR."/simple-ldap-login/adLDAP.php");

//Redefine wp_authenticate
if ( !function_exists('wp_authenticate') ) :
function wp_authenticate($username, $password) {
	$username = sanitize_user($username);

	if ( '' == $username )
		return new WP_Error('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));

	if ( '' == $password )
		return new WP_Error('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));

	$user = get_userdatabylogin($username);

	if ( !$user || ($user->user_login != $username) ) {
		do_action( 'wp_login_failed', $username );
		return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Invalid username.'));
	}

	$user = apply_filters('wp_authenticate_user', $user, $password);
	if ( is_wp_error($user) ) {
		do_action( 'wp_login_failed', $username );
		return $user;
	}
	//Leave everything else alone.
	$adldap = new adLDAP();
	
	if ( !wp_check_password($password, $user->user_pass, $user->ID) ) {
		if ($adldap -> authenticate($user->user_login,$password)){
			return new WP_User($user->ID);
		}
		do_action( 'wp_login_failed', $username );
		return new WP_Error('incorrect_password', __('<strong>ERROR</strong>: Incorrect password.'));
	}

	return new WP_User($user->ID);
}
endif;

?>
