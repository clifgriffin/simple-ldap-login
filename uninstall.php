<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

require_once( plugin_dir_path(__FILE__) . "/Simple-LDAP-Login.php" );
delete_option(SimpleLDAPLogin::get_field_settings_s());
delete_site_option(SimpleLDAPLogin::get_field_settings_s());
