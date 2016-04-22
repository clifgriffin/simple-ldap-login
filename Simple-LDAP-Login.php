<?php
/*
Plugin Name: Simple LDAP Login
Plugin URI: http://clifgriffin.com/simple-ldap-login/
Description:  Authenticate WordPress against LDAP.
Version: 1.6.0
Author: Clif Griffin Development Inc.
Author URI: http://cgd.io
*/

class SimpleLDAPLogin {
	static $instance = false;
	var $prefix = 'sll_';
	var $settings = array();
	var $adldap;
	var $ldap;
	var $network_version = null;
	var $version = "160";

	public function __construct () {

		$this->settings = $this->get_settings_obj( $this->prefix );

		if( $this->get_setting('directory') == "ad" ) {
			require_once( plugin_dir_path(__FILE__) . "/includes/adLDAP.php" );
			$this->adldap = new adLDAP(
				array (
					"account_suffix"		=>	$this->get_setting('account_suffix'),
					"use_tls"				=>	str_true( $this->get_setting('use_tls') ),
					"base_dn"				=>	$this->get_setting('base_dn'),
					"domain_controllers"	=>	(array)$this->get_setting('domain_controllers'),
					"ad_port"				=>	$this->get_setting('ldap_port')
				)
			);
		}

		add_action('admin_init', array($this, 'save_settings') );

		if ($this->is_network_version()) {
			add_action('network_admin_menu', array($this, 'menu') );
		}
		else {
			add_action('admin_menu', array($this, 'menu') );
		}


		if ( str_true($this->get_setting('enabled')) ) {
			add_filter('authenticate', array($this, 'authenticate'), 1, 3);
		}

		register_activation_hook( __FILE__, array($this, 'activate') );

		// If version is false, and old version detected, run activation
		if( $this->get_setting('version') === false || $this->get_setting('version') != $version ) {
			$this->upgrade_settings();
		}
	}

	public static function getInstance () {
		if ( !self::$instance ) {
		  self::$instance = new self;
		}
		return self::$instance;
	}

	function activate () {
		// Default settings
		$this->add_setting('account_suffix', "@mydomain.org");
		$this->add_setting('base_dn', "DC=mydomain,DC=org");
		$this->add_setting('domain_controllers', array("dc01.mydomain.local") );
		$this->add_setting('directory', "ad");
		$this->add_setting('role', "contributor");
		$this->add_setting('high_security', "true");
		$this->add_setting('ol_login', "uid");
		$this->add_setting('ol_group', "cn");
		$this->add_setting('use_tls', "false");
		$this->add_setting('ldap_port', 389);
		$this->add_setting('ldap_version', 3);
		$this->add_setting('create_users', "false");
		$this->add_setting('enabled', "false");
        $this->add_setting('search_sub_ous', "false");
		$this->add_setting('group_dn', "");
		$this->add_setting('group_uid', "memberUid");

        // User attribute settings
        $this->add_setting('user_first_name_attribute', "givenname");
        $this->add_setting('user_last_name_attribute', "sn");
        $this->add_setting('user_email_attribute', "mail");
        $this->add_setting('user_url_attribute', "wwwhomepage");
        $this->add_setting('user_meta_data', array() );
	}

	function upgrade_settings() {
		if( $this->get_setting('version') === false ) {
			$this->set_setting('enabled', 'true');

			if ($this->is_network_version()) {
				$account_suffix = get_site_option('simpleldap_account_suffix');
				$simpleldap_base_dn = get_site_option('simpleldap_base_dn');
				$simpleldap_domain_controllers = get_site_option('simpleldap_domain_controllers');
				$simpleldap_directory_type = get_site_option('simpleldap_directory_type');
				$simpleldap_group = get_site_option('simpleldap_group');
				$simpleldap_account_type = get_site_option('simpleldap_account_type');
				$simpleldap_ol_login = get_site_option('simpleldap_ol_login');
				$simpleldap_use_tls = get_site_option('simpleldap_use_tls');
				$simpleldap_login_mode = get_site_option('simpleldap_login_mode');
				$simpleldap_security_mode = get_site_option('simpleldap_security_mode');
			}
			else {
				$account_suffix = get_option('simpleldap_account_suffix');
				$simpleldap_base_dn = get_option('simpleldap_base_dn');
				$simpleldap_domain_controllers = get_option('simpleldap_domain_controllers');
				$simpleldap_directory_type = get_option('simpleldap_directory_type');
				$simpleldap_group = get_option('simpleldap_group');
				$simpleldap_account_type = get_option('simpleldap_account_type');
				$simpleldap_ol_login = get_option('simpleldap_ol_login');
				$simpleldap_use_tls = get_option('simpleldap_use_tls');
				$simpleldap_login_mode = get_option('simpleldap_login_mode');
				$simpleldap_security_mode = get_option('simpleldap_security_mode');
			}

			$this->set_setting('account_suffix', $account_suffix );
			$this->set_setting('base_dn', $simpleldap_base_dn);
			$this->set_setting('domain_controllers', $simpleldap_domain_controllers);
			$this->set_setting('groups', (array)$simpleldap_group );
			$this->set_setting('role', $simpleldap_account_type);
			$this->set_setting('ol_login', $simpleldap_ol_login);
			$this->set_setting('use_tls', str_true( $simpleldap_use_tls ) );

			// Directory Type
			if ( $simpleldap_directory_type == "directory_ad" ) {
				$this->set_setting('directory', 'ad');
			} else {
				$this->set_setting('directory', 'ol');
			}

			// Create User Setting
			$create_users = false;
			if ( $simpleldap_login_mode == "mode_create_all" || $simpleldap_login_mode == "mode_create_group" ) {
				$this->set_setting('create_users', true);
			}

			// High Security Setting
			$high_security = false;
			if ( $simpleldap_security_mode == "security_high" ) {
				$this->set_setting('high_security', true);
			}
 		}

		if ( $this->get_setting('version') < $this->version || $this->get_setting('version') === false ) {
			$this->add_setting('search_sub_ous', "false");
			$this->add_setting('group_base_dn', "");
			$this->add_setting('group_uid', "memberUid");

			// User attribute settings
			$this->add_setting('user_first_name_attribute', "givenname");
			$this->add_setting('user_last_name_attribute', "sn");
			$this->add_setting('user_email_attribute', "mail");
			$this->add_setting('user_url_attribute', "wwwhomepage");
			$this->add_setting('user_meta_data', array() );
		}

		// Update version
		$this->set_setting( 'version', $this->version );
	}

	function menu () {
		if ($this->is_network_version()) {
			add_submenu_page(
				"settings.php",
				"Simple LDAP Login",
				"Simple LDAP Login",
				'manage_network_plugins',
				"simple-ldap-login",
				array($this, 'admin_page')
			);
		}
		else {
			add_options_page("Simple LDAP Login", "Simple LDAP Login", 'manage_options', "simple-ldap-login", array($this, 'admin_page') );
		}
	}

	function admin_page () {
		include 'Simple-LDAP-Login-Admin.php';
	}

	function get_settings_obj () {
		if ( $this->is_network_version() ) {
			return get_site_option("{$this->prefix}settings", false);
		}
		else {
			return get_option("{$this->prefix}settings", false);
		}
	}

	function set_settings_obj ( $newobj ) {
		if ( $this->is_network_version() ) {
			return update_site_option("{$this->prefix}settings", $newobj);
		}
		else {
			return update_option("{$this->prefix}settings", $newobj);
		}

	}

	function set_setting ( $option = false, $newvalue ) {
		if( $option === false ) return false;

		$this->settings = $this->get_settings_obj($this->prefix);
		$this->settings[$option] = $newvalue;
		return $this->set_settings_obj($this->settings);
	}

	function get_setting ( $option = false ) {
		if( $option === false || ! isset($this->settings[$option]) ) return false;

		return apply_filters($this->prefix . 'get_setting', $this->settings[$option], $option);
	}

	function add_setting ( $option = false, $newvalue ) {
		if( $option === false ) return false;

		if ( ! isset($this->settings[$option]) ) {
			return $this->set_setting($option, $newvalue);
		} else return false;
	}

	function get_field_name($setting, $type = 'string') {
		return "{$this->prefix}setting[$setting][$type]";
	}

	function save_settings()
	{
		if( isset($_REQUEST["{$this->prefix}setting"]) && check_admin_referer('save_sll_settings','save_the_sll') ) {
			$new_settings = $_REQUEST["{$this->prefix}setting"];

			foreach( $new_settings as $setting_name => $setting_value  ) {
				foreach( $setting_value as $type => $value ) {
					if( $setting_name == 'user_meta_data') {
						$this->set_setting($setting_name,
								array_map( function ($attr) { return explode(':', $attr); },
								array_filter(preg_split('/\r\n|\n|\r|;/', $value))));
					}
					elseif( $type == "array") {
						$this->set_setting($setting_name, explode(";", $value));
					} else {
						$this->set_setting($setting_name, $value);
					}
				}
			}

			add_action('admin_notices', array($this, 'saved_admin_notice') );
		}
	}

	function saved_admin_notice(){
	    echo '<div class="updated">
	       <p>Simple LDAP Login settings have been saved.</p>
	    </div>';

	    if( ! str_true($this->get_setting('enabled')) ) {
			echo '<div class="error">
				<p>Simple LDAP Login is disabled.</p>
			</div>';
	    }
	}

	function authenticate ($user, $username, $password) {
		// If previous authentication succeeded, respect that
		if ( is_a($user, 'WP_User') ) { return $user; }

		// Determine if user a local admin
		$local_admin = false;
		$user_obj = get_user_by('login', $username);
		if( user_can($user_obj, 'update_core') ) $local_admin = true;

		$local_admin = apply_filters( 'sll_force_ldap', $local_admin );
		$password = stripslashes($password);

		// To force LDAP authentication, the filter should return boolean false

		if ( empty($username) || empty($password) ) {
			$error = new WP_Error();

			if ( empty($username) )
				$error->add('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));

			if ( empty($password) )
				$error->add('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));

			return $error;
		}

		// If high security mode is enabled, remove default WP authentication hook
		if ( apply_filters('sll_remove_default_authentication_hook', str_true( $this->get_setting('high_security') ) && ! $local_admin ) ) {
			remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
		}

		// Sweet, let's try to authenticate our user and pass against LDAP
		$auth_result = $this->ldap_auth($username, $password, $this->get_setting('directory') );

		if( $auth_result ) {
			// Authenticated, does user have required groups, if any?
			if( $this->user_has_groups( $username, $this->get_setting('directory') ) ) {

				$user = get_user_by('login', $username);

				if ( ! $user || ( strtolower($user->user_login) !== strtolower($username) ) )  {
					if( ! str_true($this->get_setting('create_users')) ) {
						do_action( 'wp_login_failed', $username );
						return $this->ldap_auth_error('invalid_username', __('<strong>Simple LDAP Login Error</strong>: LDAP credentials are correct, but there is no matching WordPress user and user creation is not enabled.'));
					}

					$new_user = wp_insert_user( $this->get_user_data( $username, $this->get_setting('directory') ) );

					if( ! is_wp_error($new_user) )
					{
						// Add user meta data
						$user_meta_data = $this->get_user_meta_data( $username, $this->get_setting('directory'));
						foreach( $user_meta_data as $meta_key => $meta_value ) {
							add_user_meta($new_user, $meta_key, $meta_value);
						}

						// Successful Login
						$new_user = new WP_User($new_user);
						do_action_ref_array($this->prefix . 'auth_success', array($new_user) );

						return $new_user;
					}
					else
					{
						do_action( 'wp_login_failed', $username );
						return $this->ldap_auth_error("{$this->prefix}login_error", __('<strong>Simple LDAP Login Error</strong>: LDAP credentials are correct and user creation is allowed but an error occurred creating the user in WordPress. Actual error: '.$new_user->get_error_message() ));
					}

				} else {
					return new WP_User($user->ID);
				}
			} else {
				return $this->ldap_auth_error("{$this->prefix}login_error", __('<strong>Simple LDAP Login Error</strong>: Your LDAP credentials are correct, but you are not in an authorized LDAP group.'));
			}

		} elseif ( str_true($this->get_setting('high_security')) ) {
			return $this->ldap_auth_error('invalid_username', __('<strong>Simple LDAP Login</strong>: Simple LDAP Login could not authenticate your credentials. The security settings do not permit trying the WordPress user database as a fallback.'));
		}

		do_action($this->prefix . 'auth_failure');
		return false;
	}

	function get_domain_username( $username ) {
		// Format username with domain prefix, if login_domain is set
		$login_domain = $this->get_setting('login_domain');

		if ( ! empty($login_domain) ) {
			return $login_domain . '\\' . $username;
		}

		return $username;
	}

	function ldap_auth( $username, $password, $directory ) {
		$result = false;

		if ( $directory == "ad" ) {
			$result = $this->adldap->authenticate( $this->get_domain_username($username), $password );
		} elseif ( $directory == "ol" ) {
			$this->ldap = ldap_connect( join(' ', (array)$this->get_setting('domain_controllers')), (int)$this->get_setting('ldap_port') );
			ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$this->get_setting('ldap_version'));
			if ( str_true($this->get_setting('use_tls')) ) {
				ldap_start_tls($this->ldap);
			}
                        $dn = $this->get_setting('ol_login') .'=' . $username . ',' . $this->get_setting('base_dn');
                        if (str_true($this->get_setting('search_sub_ous'))) {
                            // search for user's DN in the base DN and below
                            $filter = $this->get_setting('ol_login') .'=' . $username;
                            $sr = @ldap_search($this->ldap, $this->get_setting('base_dn'), $filter, array('cn'));
                            if ($sr !== FALSE) {
                                $info = @ldap_get_entries($this->ldap, $sr);
                                if ($info !== FALSE && $info['count'] > 0) {
                                    $dn = $info[0]['dn'];
                                }
                            }
                        }
                        $ldapbind = @ldap_bind($this->ldap, $dn, $password);
                        $this->dn = $dn;
						$result = $ldapbind;
		}

		return apply_filters($this->prefix . 'ldap_auth', $result);
	}

	/**
	 * Prevent modification of the error message by other authenticate hooks
	 * before it is shown to the user
	 *
	 * @param string $code
	 * @param string $message
	 * @return WP_Error
	 */
	function ldap_auth_error( $code, $message ) {
		remove_all_filters( 'authenticate' );
		return new WP_Error( $code, $message );
	}

	function user_has_groups( $username = false, $directory ) {
		$result = false;
		$groups = (array)$this->get_setting('groups');
		$groups = array_filter($groups);

		if ( ! $username ) return $result;
		if ( count( $groups ) == 0 ) return true;

		if ( $directory == "ad" ) {
			foreach ($groups as $gp) {
				if ( $this->adldap->user_ingroup ($username, $gp ) ) {
					$result = true;
					break;
				}
			}
		} elseif ( $directory == "ol" ) {
			if( $this->ldap === false ) return false;

			$group_base_dn = $this->get_setting('group_base_dn') !== false ? $this->get_setting('group_base_dn') : $this->get_setting('base_dn');
			$result = ldap_search($this->ldap, $group_base_dn, '(|(&(objectClass=groupOfUniqueNames)(uniquemember=' . $this->dn . '))(&(objectClass=groupOfNames)(member=' . $this->dn . '))(' . $this->get_setting('group_uid') . '=' . $username . '))', array($this->get_setting('ol_group')));
			$ldapgroups = ldap_get_entries($this->ldap, $result);

			// Ok, we should have the user, all the info, including which groups he is a member of.
			// Let's make sure he's in the right group before proceeding.
			$user_groups = array();
			for ( $i = 0; $i < $ldapgroups['count']; $i++) {
				$user_groups[] = $ldapgroups[$i][$this->get_setting('ol_group')][0];
			}

			$result =  (bool)(count( array_intersect($user_groups, $groups) ) > 0);
		}

		return apply_filters($this->prefix . 'user_has_groups', $result);
	}

	function get_user_data( $username, $directory ) {
		$user_data = array(
			'user_pass' => md5( microtime() ),
			'user_login' => $username,
			'user_nicename' => '',
			'user_email' => '',
			'display_name' => '',
			'first_name' => '',
			'last_name' => '',
			'user_url' => '',
			'role' => $this->get_setting('role')
		);

		if ( $directory == "ad" ) {
			$userinfo = $this->adldap->user_info($username, array("samaccountname","givenname","sn","mail"));
			$userinfo = $userinfo[0];
		} elseif ( $directory == "ol" ) {
			if ( $this->ldap == null ) {return false;}

			$attributes = array(
				$this->get_setting('ol_login'),
				$this->get_setting('user_last_name_attribute'),
				$this->get_setting('user_first_name_attribute'),
				$this->get_setting('user_email_attribute'),
				$this->get_setting('user_url_attribute')
			);

			$result = ldap_search($this->ldap, $this->get_setting('base_dn'), '(' . $this->get_setting('ol_login') . '=' . $username . ')', $attributes);
			$userinfo = ldap_get_entries($this->ldap, $result);

			if ($userinfo['count'] == 1) {
				$userinfo = $userinfo[0];
			}
		} else return false;

		if( is_array($userinfo) ) {
			$user_data['user_nicename'] = strtolower($userinfo[$this->get_setting('user_first_name_attribute')][0]) . '-' . strtolower($userinfo[$this->get_setting('user_last_name_attribute')][0]);
			$user_data['user_email'] 	= $userinfo[$this->get_setting('user_email_attribute')][0];
			$user_data['display_name']	= $userinfo[$this->get_setting('user_first_name_attribute')][0] . ' ' . $userinfo[$this->get_setting('user_last_name_attribute')][0];
			$user_data['first_name']	= $userinfo[$this->get_setting('user_first_name_attribute')][0];
			$user_data['last_name'] 	= $userinfo[$this->get_setting('user_last_name_attribute')][0];
			$user_data['user_url'] 		= $userinfo[$this->get_setting('user_url_attribute')][0];
		}

		return apply_filters($this->prefix . 'user_data', $user_data);
	}

    function get_user_meta_data( $username, $directory ) {
		if ( $directory == "ad" ) {
			// TODO: get user meta data for ad
			return false;
		} elseif ( $directory == "ol" ) {
			if ( $this->ldap == null ) {return false;}

			$attributes = array();
			foreach( $this->get_setting('user_meta_data') as $attr ) {
				$attributes[] = $attr[0];
			}
		    $result = ldap_search($this->ldap, $this->get_setting('base_dn'), '(' . $this->get_setting('ol_login') . '=' . $username . ')', $attributes);
			$userinfo = ldap_get_entries($this->ldap, $result);

			if ($userinfo['count'] == 1) {
				$userinfo = $userinfo[0];
			}
		} else return false;

		$user_meta_data = array();
        foreach( $this->get_setting('user_meta_data') as $attr ) {
			$user_meta_data[$attr[1]] = $userinfo[$attr[0]][0];
		}

		return apply_filters($this->prefix . 'user_meta_data', $user_meta_data);
	}

	/**
	 * Returns whether this plugin is currently network activated
	 */
	function is_network_version() {
		if ( $this->network_version !== null) {
			return $this->network_version;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		if ( is_plugin_active_for_network( plugin_basename(__FILE__) ) ) {
			$this->network_version = true;
		}
		else {
			$this->network_version = false;

		}
		return $this->network_version;
	}
}

if ( ! function_exists('str_true') ) {
	/**
	 * Evaluates natural language strings to boolean equivalent
	 *
	 * Used primarily for handling boolean text provided in shopp() tag options.
	 * All values defined as true will return true, anything else is false.
	 *
	 * Boolean values will be passed through.
	 *
	 * Replaces the 1.0-1.1 value_is_true()
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param string $string The natural language value
	 * @param array $istrue A list strings that are true
	 * @return boolean The boolean value of the provided text
	 **/
	function str_true ( $string, $istrue = array('yes', 'y', 'true','1','on','open') ) {
		if (is_array($string)) return false;
		if (is_bool($string)) return $string;
		return in_array(strtolower($string),$istrue);
	}
}

$SimpleLDAPLogin = SimpleLDAPLogin::getInstance();
