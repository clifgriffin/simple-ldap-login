<?php
/** Load an extracted production package without development dependencies. */

define( 'ABSPATH', __DIR__ . '/' );
$sll_package_dir = $argv[1];
$sll_edition = $argv[2];
$sll_scenario = $argv[3];
$sll_options = array();
$sll_network_options = array();
$sll_hooks = array();
$sll_meta = array();
$sll_activation = null;
$sll_network = 'network' === $sll_scenario;
$sll_existing = 'fresh' !== $sll_scenario;

// Other plugins can load different dependency versions before this plugin.
eval( 'namespace LdapRecord; class Connection {}' );
eval( 'namespace Illuminate\\Support; class Collection {}' );
function data_get() { throw new RuntimeException( 'A private dependency used another plugin\'s helper.' ); }
$GLOBALS['__composer_autoload_files'] = array( 'another-plugin-helper' => true );

class WP_Error {
	public function __construct( $code = '', $message = '' ) {}
}
class WP_User {
	public int $ID = 1;
}
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function is_multisite() { return $GLOBALS['sll_network']; }
function is_admin() { return true; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function get_option( $key, $fallback = false ) { return $GLOBALS['sll_options'][ $key ] ?? $fallback; }
function get_site_option( $key, $fallback = false ) { return $GLOBALS['sll_network_options'][ $key ] ?? $fallback; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['sll_options'][ $key ] = $value; return true; }
function update_site_option( $key, $value ) { $GLOBALS['sll_network_options'][ $key ] = $value; return true; }
function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['sll_hooks'][ $name ][ $priority ][] = array( $callback, $accepted_args ); return true; }
function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ) { return add_filter( $name, $callback, $priority, $accepted_args ); }
function apply_filters( $name, $value, ...$args ) {
	$priorities = $GLOBALS['sll_hooks'][ $name ] ?? array(); ksort( $priorities );
	foreach ( $priorities as $callbacks ) { foreach ( $callbacks as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); } }
	return $value;
}
function do_action( $name, ...$args ) {
	$priorities = $GLOBALS['sll_hooks'][ $name ] ?? array(); ksort( $priorities );
	foreach ( $priorities as $callbacks ) { foreach ( $callbacks as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); } }
}
function register_activation_hook( $file, $callback ) { $GLOBALS['sll_activation'] = $callback; }
function get_role( $name ) { return 'subscriber' === $name ? (object) array() : null; }
function metadata_exists( $type, $id, $key ) { return isset( $GLOBALS['sll_meta'][ $id ][ $key ] ); }
function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['sll_meta'][ $id ][ $key ] ?? ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['sll_meta'][ $id ][ $key ] = $value; return true; }
function wp_remote_get() { throw new RuntimeException( 'Package boot must not make a remote request.' ); }
function wp_remote_post() { throw new RuntimeException( 'Package boot must not make a remote request.' ); }
function wp_remote_request() { throw new RuntimeException( 'Package boot must not make a remote request.' ); }
function package_check( $condition, $label ) { if ( ! $condition ) { throw new RuntimeException( $label ); } }

if ( $sll_network ) {
	$sll_network_options['active_sitewide_plugins'] = array( 'simple-ldap-login/Simple-LDAP-Login.php' => 1 );
}
$sll_old = array( 'schema_version' => 2, 'enabled' => false, 'sso_enabled' => true, 'user_meta_data' => array( array( 'telephoneNumber', 'phone', 'string' ) ), 'custom_extension' => 'keep' );
if ( $sll_existing ) {
	if ( $sll_network ) { $sll_network_options['sll_settings'] = $sll_old; } else { $sll_options['sll_settings'] = $sll_old; }
}
require $sll_package_dir . '/Simple-LDAP-Login.php';
package_check( array( 'another-plugin-helper' => true ) === $GLOBALS['__composer_autoload_files'], 'Private dependency loading preserves other Composer helper files.' );
$sll_psr4 = require $sll_package_dir . '/vendor/composer/autoload_psr4.php';
foreach ( array_keys( $sll_psr4 ) as $sll_namespace ) {
	package_check( 'SimpleLdapLogin\\' === $sll_namespace || str_starts_with( $sll_namespace, 'SimpleLdapLoginVendor\\' ), 'Every dependency namespace is private.' );
}
package_check( class_exists( 'SimpleLdapLoginVendor\\LdapRecord\\Connection' ), 'The private LDAP dependency loads.' );
$sll_connection_class = 'SimpleLdapLoginVendor\\LdapRecord\\Connection';
$sll_connection = new $sll_connection_class( array( 'hosts' => array( 'localhost' ), 'base_dn' => 'dc=example,dc=test' ) );
package_check( '(cn=\\41\\6c\\69\\63\\65)' === $sll_connection->query()->whereEquals( 'cn', 'Alice' )->getQuery(), 'The private query API works beside another LdapRecord version.' );
$sll_collection_class = 'SimpleLdapLoginVendor\\Illuminate\\Support\\Collection';
$sll_collection = new $sll_collection_class( array( array( 'name' => 'Alice' ) ) );
package_check( array( 'Alice' ) === $sll_collection->pluck( 'name' )->all(), 'Private collection helpers do not call another plugin\'s globals.' );
package_check( ! function_exists( 'collect' ) && function_exists( 'SimpleLdapLoginVendor\\collect' ), 'Dependency helpers stay private.' );
$sll_container_class = 'SimpleLdapLoginVendor\\LdapRecord\\Container';
$sll_container_class::addConnection( $sll_connection );
$sll_user_class = 'SimpleLdapLoginVendor\\LdapRecord\\Models\\ActiveDirectory\\User';
$sll_group_class = 'SimpleLdapLoginVendor\\LdapRecord\\Models\\ActiveDirectory\\Group';
$sll_entry_class = 'SimpleLdapLoginVendor\\LdapRecord\\Models\\ActiveDirectory\\Entry';
$sll_sid_class = 'SimpleLdapLoginVendor\\LdapRecord\\Models\\Attributes\\Sid';
$sll_user = ( new $sll_user_class() )->setRawAttributes( array( 'objectclass' => array( 'top', 'person', 'organizationalperson', 'user' ), 'objectsid' => array( ( new $sll_sid_class( 'S-1-5-21-1-2-3-1001' ) )->getBinary() ), 'primarygroupid' => array( '513' ) ) )->setDn( 'cn=Alice,dc=example,dc=test' );
$sll_groups = $sll_user->groups()->recursive();
package_check( array( $sll_group_class ) === $sll_groups->getRelated() && array( $sll_group_class ) === $sll_user->primaryGroup()->getRelated(), 'Recursive and primary group relations use private models.' );
$sll_group = ( new $sll_entry_class() )->setRawAttributes( array( 'objectclass' => array( 'top', 'group' ), 'objectsid' => array( ( new $sll_sid_class( 'S-1-5-21-1-2-3-513' ) )->getBinary() ) ) )->setDn( 'cn=Staff,dc=example,dc=test' )->morphInto( $sll_groups->getRelated() );
package_check( $sll_group instanceof $sll_group_class && '513' === $sll_group->getFirstAttribute( 'rid' ), 'Dynamic model conversion and binary SID helpers stay private.' );
$sll_logger = new class() extends SimpleLdapLoginVendor\Psr\Log\AbstractLogger {
	public int $count = 0;
	public function log( $level, string|Stringable $message, array $context = array() ): void { ++$this->count; }
};
$sll_manager = $sll_container_class::getInstance()->getConnectionManager();
$sll_manager->setLogger( $sll_logger );
$sll_event_class = 'SimpleLdapLoginVendor\\LdapRecord\\Auth\\Events\\Binding';
$sll_manager->getDispatcher()->fire( new $sll_event_class( $sll_connection->getLdapConnection(), 'Alice' ) );
package_check( 1 === $sll_logger->count, 'Wildcard event listeners match private event names.' );
$sll_manager->unsetLogger();
package_check( ! class_exists( 'Carbon\\Carbon', false ) && ! interface_exists( 'Illuminate\\Contracts\\Support\\Arrayable', false ), 'Dependency classes are not exposed under public namespaces.' );
package_check( 1 === preg_match( '/^ \* Requires PHP: 8\.1$/m', file_get_contents( $sll_package_dir . '/Simple-LDAP-Login.php' ) ), 'The production header requires PHP 8.1.' );
package_check( SLL_EDITION === $sll_edition, 'The package has the correct edition.' );
package_check( is_callable( $sll_activation ), 'The real entry file registers activation.' );
call_user_func( $sll_activation, $sll_network );
do_action( 'plugins_loaded' );
$sll_settings = new SimpleLdapLogin\Settings();
$sll_full = 'free' !== $sll_edition;
package_check( class_exists( SimpleLdapLogin\Settings::class ), 'Shared PSR-4 class loading works.' );
package_check( 3 === $sll_settings->get( 'schema_version' ), 'Package startup applies the current settings schema.' );
package_check( ( $sll_existing ? 'legacy' : 'selected' ) === $sll_settings->recovery_mode(), 'Fresh packages use selected recovery accounts and old packages retain the administrator rule.' );
package_check( defined( 'SLL_FEATURES_LOADED' ) === $sll_full, 'Full package features load only in a full package.' );
foreach ( array( 'Metadata', 'Server_Sso', 'Admin', 'Ldap_Client', 'Upgrade_Guard' ) as $sll_class ) {
	package_check( class_exists( 'SimpleLdapLogin\\Pro\\' . $sll_class ) === $sll_full, 'The package has the correct Pro PSR-4 classes.' );
}
package_check( ! method_exists( SimpleLdapLogin\Ldap_Client::class, 'authenticate_sso' ), 'The shared LDAP client has no SSO implementation.' );
package_check( ! empty( $sll_hooks['sll_account_loaded'] ) === $sll_full, 'The full package registers custom metadata.' );
package_check( ! empty( $sll_hooks['sll_settings_tabs'] ) === $sll_full, 'The full package registers its settings controls.' );
package_check( ! empty( $sll_hooks['init'] ) === ( $sll_full && $sll_existing ), 'SSO registers only when its code and setting are present.' );
$sll_expected_access = $sll_existing || 'transition' === $sll_edition;
package_check( $sll_expected_access === $sll_settings->has_legacy_access(), 'Legacy access has the correct value.' );
$sll_settings->migrate();
package_check( $sll_expected_access === $sll_settings->has_legacy_access(), 'A second boot keeps the legacy access value.' );
if ( $sll_network ) { package_check( array() === $sll_options, 'Network settings stay out of site options.' ); }
if ( $sll_existing ) {
	package_check( 'keep' === $sll_settings->get( 'custom_extension' ) && true === $sll_settings->get( 'sso_enabled' ), 'Old settings remain unchanged.' );
}
package_check( true === $sll_settings->save( array( 'user_meta_data' => array( array( 'telephoneNumber', 'phone', 'string' ) ) ) ), 'The settings store retains metadata definitions.' );
do_action( 'sll_account_loaded', new WP_User(), array( 'telephonenumber' => array( '5551002000' ) ) );
package_check( isset( $sll_meta[1]['phone_wp'] ) === $sll_full, 'Only a full package copies custom metadata.' );
if ( $sll_full ) {
	package_check( '5551002000' === $sll_meta[1]['phone_wp'] && '5551002000' === $sll_meta[1]['phone_ldap'], 'The real metadata module keeps both old keys.' );
}
$sll_settings->migrate();
package_check( $sll_expected_access === $sll_settings->has_legacy_access(), 'New settings do not create a legacy grant.' );
echo 'Production package runtime passed: ' . $sll_edition . '/' . $sll_scenario . ".\n";
