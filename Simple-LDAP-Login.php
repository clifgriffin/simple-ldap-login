<?php
/*
Plugin Name: Simple LDAP Login
Plugin URI: http://clifgriffin.com/2009/05/13/simple-ldap-login-13-for-wordpress/ 
Description:  Authenticates Wordpress usernames against LDAP.
Version: 1.3.0.3
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
}
//For adLDAP
$sll_options=array(
	"account_suffix"=>get_option("simpleldap_account_suffix"),
	"base_dn"=>get_option("simpleldap_base_dn"),
	"domain_controllers"=>explode(";",get_option("simpleldap_domain_controllers")),	
);
//For OpenLDAP
$ar_ldaphosts = explode(";",get_option("simpleldap_domain_controllers"));
$ldaphosts = ""; //string to hold each host separated by space
foreach ($ar_ldaphosts as $host)
{
	$ldaphosts .= $host." ";
}
define ('LDAP_HOST', $ldaphosts);
define ('LDAP_PORT', 389);
define ('LDAP_VERSION', 3);
define ('BASE_DN', get_option('simpleldap_base_dn'));
define ('LOGIN', 'uid');

//Add the menu
add_action('admin_menu', 'simpleldap_admin_actions');

//Redefine wp_authenticate
if ( !function_exists('wp_authenticate') ) :
function wp_authenticate($username, $password) {
	global $sll_options;
	$password = stripslashes($password);
	
	//Setup adLDAP object
	$adldap = new adLDAP($sll_options);
	
	$username = sanitize_user($username);

	if ( '' == $username )
		return new WP_Error('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));

	if ( '' == $password )
		return new WP_Error('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));

	$user = get_userdatabylogin($username);
	
	if ( !$user || (strtolower($user->user_login) != strtolower($username)) ) 
	{
		//No user, are we supposed to create one?
		switch(get_option('simpleldap_login_mode'))
		{
			case "mode_create_all":
				switch(get_option('simpleldap_directory_type'))
				{
					case "directory_ad":
						//Active Directory create all
						if($adldap->authenticate($username,$password))
						{
							$userinfo = $adldap->user_info($username, array("samaccountname","givenname","sn","mail"));
							//Create WP account
							$userData = array(
								'user_pass'     => microtime(),
								'user_login'    => $userinfo[0][samaccountname][0],
								'user_nicename' => $userinfo[0][givenname][0] .' '.$userinfo[0][sn][0],
								'user_email'    => $userinfo[0][mail][0],
								'display_name'  => $userinfo[0][givenname][0] .' '.$userinfo[0][sn][0],
								'first_name'    => $userinfo[0][givenname][0],
								'last_name'     => $userinfo[0][sn][0],
								'role'			=> strtolower(get_option('simpleldap_account_type'))
								);
							wp_insert_user($userData);
							
						}
						else
						{
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Simple LDAP Login mode allows account creation but the LDAP credentials provided are incorrect.'));
						}
						break;
					case "directory_ol":
						//OpenLDAP create all 
						$ldap = ldap_connect(LDAP_HOST, LDAP_PORT) 
							or die("Can't connect to LDAP server.");
						ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, LDAP_VERSION);
						$ldapbind = @ldap_bind($ldap, LOGIN .'=' . $username . ',' . BASE_DN, $password);
						if ($ldapbind == true) 
						{
							//Seems to authenticate
							//We already bound, foo' so we're going to try a search
							$result = ldap_search($ldap, BASE_DN, '(' . LOGIN . '=' . $username . ')', array(LOGIN, 'sn', 'givenname', 'mail'));
							$ldapuser = ldap_get_entries($ldap, $result);
					
							if ($ldapuser['count'] == 1) {
								//Create user using wp standard include
								$userData = array(
									'user_pass'     => microtime(),
									'user_login'    => $ldapuser[0][LOGIN][0],
									'user_nicename' => $ldapuser[0]['givenname'][0].' '.$ldapuser[0]['sn'][0],
									'user_email'    => $ldapuser[0]['mail'][0],
									'display_name'  => $ldapuser[0]['givenname'][0].' '.$ldapuser[0]['sn'][0],
									'first_name'    => $ldapuser[0]['givenname'][0],
									'last_name'     => $ldapuser[0]['sn'][0],
									'role'			=> strtolower(get_option('simpleldap_account_type'))
									);
							
								//Get ID of new user
								wp_insert_user($userData);								
							}
						}
						else
						{
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Simple LDAP Login mode allows account creation but the LDAP credentials provided are incorrect.'));
						}
						break;
				}
				break;
			case "mode_create_group":
				switch(get_option('simpleldap_directory_type'))
				{
					case "directory_ad":
						//Active Directory create group
						if($adldap->authenticate($username,$password))
						{
							if($adldap->user_ingroup($username,get_option('simpleldap_group')))
							{
								$userinfo = $adldap->user_info($username, array("samaccountname","givenname","sn","mail"));
								//Create WP account
								$userData = array(
									'user_pass'     => microtime(),
									'user_login'    => $userinfo[0][samaccountname][0],
									'user_nicename' => $userinfo[0][givenname][0].' '.$userinfo[0][sn][0],
									'user_email'    => $userinfo[0][mail][0],
									'display_name'  => $userinfo[0][givenname][0].' '.$userinfo[0][sn][0],
									'first_name'    => $userinfo[0][givenname][0],
									'last_name'     => $userinfo[0][sn][0],
									'role'			=> strtolower(get_option('simpleldap_account_type'))
									);
									
									wp_insert_user($userData);
							}
							else
							{
								//User authenticated, but isn't in group!
								do_action( 'wp_login_failed', $username );				
								return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Simple LDAP Login mode allows account creation, the LDAP credentials provided are correct, but the user is not in an allowed group.'));
							}
						}
						else
						{
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Simple LDAP Login mode allows account creation but the LDAP credentials provided are incorrect.'));
						}
						break;
					case "directory_ol":
						//OpenLDAP create group
						$ldap = ldap_connect(LDAP_HOST, LDAP_PORT) 
							or die("Can't connect to LDAP server.");
						ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, LDAP_VERSION);
						$ldapbind = @ldap_bind($ldap, LOGIN .'=' . $username . ',' . BASE_DN, $password);
						if ($ldapbind == true) 
						{
							//Seems to authenticate
							//We already bound, foo' so we're going to try a search for the user
							$result = ldap_search($ldap, BASE_DN, '(' . LOGIN . '=' . $username . ')', array(LOGIN, 'sn', 'givenname', 'mail', 'memberof'));
							$ldapuser = ldap_get_entries($ldap, $result);
						
							if ($ldapuser['count'] == 1) 
							{
								//Ok, we should have the user, all the info, including which groups he is a member of. 
								//Now let's make sure he's in the right group before proceeding.
								$groups = array();
								foreach($ldapuser[0][memberof][0] as $group)
								{
									$temp = substr($group, 0, stripos($group, ","));
								 	// Strip the CN= and change to lowercase for easy handling
								  	$temp = strtolower(str_replace("CN=", "", $temp));
								  	$groups[] .= $temp;
								}	
								if(in_array(get_option('simpleldap_group'),$groups))
								{						
									//Create user using wp standard include
									$userData = array(
										'user_pass'     => microtime(),
										'user_login'    => $ldapuser[0][LOGIN][0],
										'user_nicename' => $ldapuser[0]['givenname'][0].' '.$ldapuser[0]['sn'][0],
										'user_email'    => $ldapuser[0]['mail'][0],
										'display_name'  => $ldapuser[0]['givenname'][0].' '.$ldapuser[0]['sn'][0],
										'first_name'    => $ldapuser[0]['givenname'][0],
										'last_name'     => $ldapuser[0]['sn'][0],
										'role'			=> strtolower(get_option('simpleldap_account_type'))
										);
																	
									wp_insert_user($userData);									
								}
								else
								{
									do_action( 'wp_login_failed', $username );				
									return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Simple LDAP Login mode allows account creation, the LDAP credentials provided are correct, but the user is not in an allowed group.'));
								}
							}
							else
							{
								do_action( 'wp_login_failed', $username );				
								return new WP_Error('invalid_username', __('<strong>ERROR</strong>: You should not see this error. If you do see it, there is a problem with the group check for OpenLDAP. Please report this error as it probably represents a bug. Location: account creation group check.'));
							}
						}
						else
						{
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Simple LDAP Login mode allows account creation but the LDAP credentials provided are incorrect.'));
						}
						break;
				}
				break;
			default:
				do_action( 'wp_login_failed', $username );				
				return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Simple LDAP Login mode does not permit account creation.'));
		}
	}


	$user = get_userdatabylogin($username);
	
	if ( !$user || (strtolower($user->user_login) != strtolower($username)) ) {	
	
		do_action( 'wp_login_failed', $username );
		return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Invalid username.'));
	}

	$user = apply_filters('wp_authenticate_user', $user, $password);
	if ( is_wp_error($user) ) {
		do_action( 'wp_login_failed', $username );
		return $user;
	}
	
	if ( !wp_check_password($password, $user->user_pass, $user->ID) ) {
		switch(get_option("simpleldap_directory_type"))
		{
			case "directory_ad":
				if ($adldap -> authenticate($user->user_login,$password)){
					if(get_option('simpleldap_login_mode') == "mode_create_group")
					{
						if($adldap->user_ingroup($user->user_login,get_option('simpleldap_group')))
						{
							return new WP_User($user->ID);							
						}
						else
						{
							return new WP_Error('invalid_username', __('<strong>ERROR</strong>: User exists and authenticated properly but did not belong to the group required by Simple LDAP Login.'));
						}
					}
					else
					{
						return new WP_User($user->ID);	
					}
				}				
				break;
			case "directory_ol":
					//OpenLDAP create based on group
					$ldap = ldap_connect(LDAP_HOST, LDAP_PORT) 
						or die("Can't connect to LDAP server.");
					ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, LDAP_VERSION);
					$ldapbind = @ldap_bind($ldap, LOGIN .'=' . $username . ',' . BASE_DN, $password);
					if ($ldapbind == true) 
					{
						if(get_option('simpleldap_login_mode') == "mode_create_group")
						{
							$result = ldap_search($ldap, BASE_DN, '(' . LOGIN . '=' . $username . ')', array(LOGIN, 'sn', 'givenname', 'mail', 'memberof'));
							$ldapuser = ldap_get_entries($ldap, $result);
						
							if ($ldapuser['count'] == 1) 
							{
								//Ok, we should have the user, all the info, including which groups he is a member of. 
								//Now let's make sure he's in the right group before proceeding.
								$groups = array();
								foreach($ldapuser[0][memberof][0] as $group)
								{
									$temp = substr($group, 0, stripos($group, ","));
								 	// Strip the CN= and change to lowercase for easy handling
								  	$temp = strtolower(str_replace("CN=", "", $temp));
								  	$groups[] .= $temp;
								}	
								if(in_array(get_option('simpleldap_group'),$groups))
								{	
									return new WP_User($user->ID);
								}
								else
								{
									return new WP_Error('invalid_username', __('<strong>ERROR</strong>: User exists and authenticated properly but did not belong to the group required by Simple LDAP Login.'));
								}
							}
							else
							{
								return new WP_Error('invalid_username', __('<strong>ERROR</strong>: You should not see this error. If you do see it, there is a problem with the group check for OpenLDAP. Please report this error as it probably represents a bug. Location: existing account group check.'));
							}
						}
						else
						{
								return new WP_User($user->ID);
						}
					}
				break;		
		}
		do_action( 'wp_login_failed', $username );
		return new WP_Error('incorrect_password', __('<strong>ERROR</strong>: Incorrect password.'));
	}
	if((get_option("simpleldap_security_mode") == "security_high") && ($username != "admin"))
	{
		return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Simple LDAP Login is set to high security mode. In this mode Wordpress local accounts do not work. You must authenticate using the LDAP password.'));
	}
	else
	{
		return new WP_User($user->ID);
	}
}
endif;
register_activation_hook( __FILE__, 'simpleldap_activation_hook' );
?>
