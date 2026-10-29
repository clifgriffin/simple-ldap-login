<?php
/**
 * Plugin Name: Simple LDAP Login
 * Plugin URI: https://github.com/clifgriffin/simple-ldap-login
 * Description: Connect WordPress accounts to Active Directory or OpenLDAP.
 * Version: 2.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Clif Griffin Development Inc.
 * Author URI: https://clifgriffin.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-ldap-login
 *
 * @package SimpleLdapLogin
 */

defined( 'ABSPATH' ) || exit;

define( 'SLL_FILE', __FILE__ );
define( 'SLL_VERSION', '2.0.0' );
define( 'SLL_EDITION', 'free' );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Simple LDAP Login needs PHP 8.1 or later.', 'simple-ldap-login' ) . '</p></div>';
		}
	);
	return;
}

if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Simple LDAP Login is incomplete. Install the release ZIP, or run Composer install in the plugin folder.', 'simple-ldap-login' ) . '</p></div>';
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, array( SimpleLdapLogin\Plugin::class, 'activate' ) );
add_action( 'plugins_loaded', array( SimpleLdapLogin\Plugin::class, 'boot' ) );
