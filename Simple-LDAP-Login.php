<?php
/*
Plugin Name: Simple LDAP Login
Plugin URI: http://clifgriffin.com/2008/10/28/simple-ldap-login-wordpress-plugin/ 
Description:  Authenticates Wordpress usernames against LDAP.
Version: 1.2
Author: Clifton H. Griffin II
Author URI: http://clifgriffin.com
*/
require_once( WP_PLUGIN_DIR."/simple-ldap-login/adLDAP.php");

//Admin
function simpleldap_menu()
{
	include 'Simple-LDAP-Login-Admin.php';
}

function simpleldap_admin_actions()
{
    add_options_page("Simple LDAP Login", "Simple LDAP Login", 1, "simple-ldap-login", "simpleldap_menu");
}
function simpleldap_activation_hook()
{
	//Store settings
	add_option("simpleldap_account_suffix", "@mydomain.local");
	add_option("simpleldap_base_dn", "DC=mydomain,DC=local");
	add_option("simpleldap_domain_controllers", "dc01.mydomain.local");
}
$options=array(
	"account_suffix"=>get_option("simpleldap_account_suffix"),
	"base_dn"=>get_option("simpleldap_base_dn"),
	"domain_controllers"=>explode(";",get_option("simpleldap_domain_controllers")),
);

//Add the menu
add_action('admin_menu', 'simpleldap_admin_actions');

//Redefine wp_authenticate
if ( !function_exists('wp_authenticate') ) :
function wp_authenticate($username, $password) {
	global $options;
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
	$adldap = new adLDAP($options);
	
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
register_activation_hook( __FILE__, 'simpleldap_activation_hook' );
?>
