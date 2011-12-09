<?php
/*
Plugin Name: Simple LDAP Login
Plugin URI: http://clifgriffin.com/2009/05/13/simple-ldap-login-13-for-wordpress/ 
Description:  Authenticates Wordpress usernames against LDAP.
Version: 1.4.0.5.1
Author: Clifton H. Griffin II
Author URI: http://clifgriffin.com
*/
require_once( WP_PLUGIN_DIR."/simple-ldap-login/adLDAP.php");
require_once( ABSPATH . WPINC . '/registration.php');

//Admin
function simpleldap_menu()
{
	include 'Simple-LDAP-Login-Admin.php';
}

function simpleldap_admin_actions()
{
    add_options_page("Simple LDAP Login", "Simple LDAP Login", 10, "simple-ldap-login", "simpleldap_menu");
}
function simpleldap_activation_hook()
{
	//Store settings
	add_option("simpleldap_account_suffix", "@mydomain.local");
	add_option("simpleldap_base_dn", "DC=mydomain,DC=local");
	add_option("simpleldap_domain_controllers", "dc01.mydomain.local");
	
	//Version 1.3
	add_option("simpleldap_directory_type", "directory_ad");
	add_option("simpleldap_login_mode", "mode_normal");
	add_option("simpleldap_group", "");
	add_option("simpleldap_account_type", "Contributor");
	
	//Version 1.3.0.2
	add_option("simpleldap_security_mode", "security_low");
	
	//Version 1.4
	add_option("simpleldap_ol_login", "uid");
	add_option("simpleldap_use_tls", "no");
}

//For adLDAP
$sll_use_tls = false;
if(get_option("simpleldap_use_tls") == "yes") {
	$sll_use_tls = true;
}
$sll_options=array(
	"account_suffix"=>get_option("simpleldap_account_suffix"),
	"use_tls"=>$sll_use_tls,
	"base_dn"=>get_option("simpleldap_base_dn"),
	"domain_controllers"=>explode(";",get_option("simpleldap_domain_controllers")),	
);

//For OpenLDAP
$ar_ldaphosts = explode(";",get_option("simpleldap_domain_controllers"));
$ldaphosts = ""; //string to hold each host separated by space
$ldap = null;
$adldap = new adLDAP($sll_options);
foreach ($ar_ldaphosts as $host)
{
	$ldaphosts .= $host." ";
}
define ('LDAP_HOST', $ldaphosts);
define ('LDAP_PORT', 389);
define ('LDAP_VERSION', 3);
define ('BASE_DN', get_option('simpleldap_base_dn'));
define ('LOGIN', get_option("simpleldap_ol_login"));

//Add the menu
add_action('admin_menu', 'simpleldap_admin_actions');

//Add filter
add_filter('authenticate', 'sll_authenticate', 1, 3);

//Authenticate function
function sll_authenticate($user, $username, $password) {
	if ( is_a($user, 'WP_User') ) { return $user; }
	
	//Failed, should we let it continue to lower priority authenticate methods?
	if(get_option("simpleldap_security_mode") == "security_high")
	{
		remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
	}
	
	if ( empty($username) || empty($password) ) {
		$error = new WP_Error();

		if ( empty($username) )
			$error->add('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));

		if ( empty($password) )
			$error->add('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));

		return $error;
	}
	
	$auth_result = sll_can_authenticate($username, $password);
	if($auth_result == true && !is_a($auth_result, 'WP_Error'))
	{
			$user = get_userdatabylogin($username);
	
			if ( !$user || (strtolower($user->user_login) != strtolower($username)) ) 
			{
				//No user, can we create?
				switch(get_option('simpleldap_login_mode'))
				{
					case "mode_create_all":
						$new_user_id = sll_create_wp_user($username);
						if(!is_a($new_user_id, 'WP_Error'))
						{
							//It worked
							return new WP_User($new_user_id);
						}
						else
						{
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>Simple LDAP Login Error</strong>: LDAP credentials are correct and user creation is allowed but an error occurred creating the user in Wordpress. Actual WordPress error: '.$new_user_id->get_error_message()));
						}
					break;
					
					case "mode_create_group":
						if(sll_is_in_group($username))
						{
							$new_user_id = sll_create_wp_user($username);
							if(!is_a($new_user_id, 'WP_Error'))
							{
								//It worked
								return new WP_User($new_user_id);
							}
							else
							{
								do_action( 'wp_login_failed', $username );				
								return new WP_Error('invalid_username', __('<strong>Simple LDAP Login Error</strong>: LDAP credentials are correct and user creation is allowed and you are in the correct group but an error occurred creating the user in Wordpress. Actual WordPress error: '.$new_user_id->get_error_message()));
							}
						}
						else
						{
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>Simple LDAP Login Error</strong>: LDAP Login credentials are correct and user creation is allowed but LDAP user was not in correct LDAP group.'));
						}
					break;
					
					default:
						do_action( 'wp_login_failed', $username );				
						return new WP_Error('invalid_username', __('<strong>Simple LDAP Login Error</strong>: Simple LDAP Login mode does not permit account creation.'));
				}
			}
			else
			{
				//Wordpress user exists, should we check group membership?
				if(get_option('simpleldap_login_mode') == "mode_create_group")
				{
					if(sll_is_in_group($username))
					{
						return new WP_User($user->ID);
					}
					else
					{
						do_action( 'wp_login_failed', $username );				
						return new WP_Error('invalid_username', __('<strong>Simple LDAP Login Error</strong>: LDAP credentials were correct but user is not in the correct group.'));
					}
				}
				else
				{
					//Otherwise, we're ready to return the user
					return new WP_User($user->ID);
				}
			}
	}
	else
	{
		if(is_a($auth_result, 'WP_Error'))
		{
			return $auth_result;
		}
		else
		{
			return new WP_Error('invalid_username', __('<strong>Simple LDAP Login Error</strong>: Simple LDAP Login could not authenticate your credentials. The security settings do not permit trying the Wordpress user database as a fallback.'));
		}
	}
}

function sll_can_authenticate($username, $password)
{
	global $ldap, $adldap;
	
	$result = false;
	switch(get_option('simpleldap_directory_type'))
	{
		case "directory_ad":
			$result = $adldap->authenticate($username,$password);
			if($result == false)
			{ 
				return new WP_Error('adldap_error', __('<strong>Simple LDAP Login Error</strong>: adLDAP may have errored. Message: '.$adldap->get_last_error()));
			}
		break;
		
		case "directory_ol":
			$ldap = ldap_connect(LDAP_HOST, LDAP_PORT) or die("Can't connect to LDAP server.");
			ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, LDAP_VERSION);
			if (get_option("simpleldap_use_tls") == "yes") {
				ldap_start_tls($ldap);
			}
			$ldapbind = @ldap_bind($ldap, LOGIN .'=' . $username . ',' . BASE_DN, $password);
			$result = $ldapbind; 
		break;
	}

	return $result;
}
function sll_is_in_group($username)
{
	global $ldap, $adldap;
	$result = false;
	
	switch(get_option('simpleldap_directory_type'))
	{
		case "directory_ad":
			$result = $adldap->user_ingroup($username,get_option('simpleldap_group'));
		break;
		
		case "directory_ol":
			if($ldap == null) {return false;}
			$result = ldap_search($ldap, BASE_DN, '(' . LOGIN . '=' . $username . ')', array('cn'));
			$ldapgroups = ldap_get_entries($ldap, $result);
		
			//Ok, we should have the user, all the info, including which groups he is a member of. 
			//Now let's make sure he's in the right group before proceeding.
			$groups = array();
			for ($i=0; $i<$ldapgroups['count']; $i++) {
				$groups[] .= $ldapgroups[$i]['cn'][0];
			}
			$result = in_array(get_option('simpleldap_group'),$groups);	
		break;
	}
	return $result;
}
function sll_create_wp_user($username)
{
	global $ldap, $adldap;
	$result = 0;
	
	switch(get_option('simpleldap_directory_type'))
	{
		case "directory_ad":
			$userinfo = $adldap->user_info($username, array("samaccountname","givenname","sn","mail"));
			//Create WP account
			$userData = array(
				'user_pass'     => microtime(),
				'user_login'    => $userinfo[0][samaccountname][0],
				'user_nicename' => sanitize_title($userinfo[0][givenname][0] .' '.$userinfo[0][sn][0]),
				'user_email'    => $userinfo[0][mail][0],
				'display_name'  => $userinfo[0][givenname][0] .' '.$userinfo[0][sn][0],
				'first_name'    => $userinfo[0][givenname][0],
				'last_name'     => $userinfo[0][sn][0],
				'role'			=> strtolower(get_option('simpleldap_account_type'))
				);
				
			$result = wp_insert_user($userData); 
		break;
		
		case "directory_ol":
			if($ldap == null) {return false;}
			$result = ldap_search($ldap, BASE_DN, '(' . LOGIN . '=' . $username . ')', array(LOGIN, 'sn', 'givenname', 'mail'));
			$ldapuser = ldap_get_entries($ldap, $result);
	
			if ($ldapuser['count'] == 1) {
				//Create user using wp standard include
				$userData = array(
					'user_pass'     => microtime(),
					'user_login'    => $ldapuser[0][LOGIN][0],
					'user_nicename' => sanitize_title($ldapuser[0]['givenname'][0].' '.$ldapuser[0]['sn'][0]),
					'user_email'    => $ldapuser[0]['mail'][0],
					'display_name'  => $ldapuser[0]['givenname'][0].' '.$ldapuser[0]['sn'][0],
					'first_name'    => $ldapuser[0]['givenname'][0],
					'last_name'     => $ldapuser[0]['sn'][0],
					'role'			=> strtolower(get_option('simpleldap_account_type'))
					);
			
				//Get ID of new user
				
				$result = wp_insert_user($userData);							
			}
		break;
	}
	
	return $result;
}

//Temporary fix for e-mail exists bug
if ( !function_exists('get_user_by_email') ) :
/**
 * Retrieve user info by email.
 *
 * @since 2.5
 *
 * @param string $email User's email address
 * @return bool|object False on failure, User DB row object
 */
function get_user_by_email($email) {
	if(strlen($email) == 0 || empty($email) || $email == "" || strpos($email, "@") == false)
	{
		return false;
	}
	else
	{
		return get_user_by('email', $email);
	}
}
endif;

register_activation_hook( __FILE__, 'simpleldap_activation_hook' );
?>
