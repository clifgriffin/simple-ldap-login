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
$sll_network = in_array( $sll_scenario, array( 'network', 'v1-network', 'pro-network' ), true );
$sll_existing = 'fresh' !== $sll_scenario;
$sll_licensed = ! in_array( $sll_scenario, array( 'unlicensed', 'expired', 'wrong-tier' ), true );
define( 'HOUR_IN_SECONDS', 3600 );

// Other plugins can load different dependency versions before this plugin.
eval( 'namespace LdapRecord; class Connection {}' );
eval( 'namespace Illuminate\\Support; class Collection {}' );
eval( 'namespace PaidCommunities\\WordPress\\HttpClient; class WordPressClient {}' );
function data_get() { throw new RuntimeException( 'A private dependency used another plugin\'s helper.' ); }
$GLOBALS['__composer_autoload_files'] = array( 'another-plugin-helper' => true );

class WP_Error {
	public function __construct( public $code = '', $message = '' ) {}
	public function get_error_code() { return $this->code; }
}
class WP_User {
	public int $ID = 1;
}
function __( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
function selected( $value, $expected ) { if ( $value === $expected ) { echo 'selected="selected"'; } }
function wp_nonce_field( $action ) { echo '<input name="_wpnonce" value="test-nonce" />'; }
function admin_url( $path = '' ) { return '/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return '/wp-admin/network/' . $path; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function is_multisite() { return $GLOBALS['sll_network']; }
function is_admin() { return true; }
function has_action( $name ) { return ! empty( $GLOBALS['sll_hooks'][ $name ] ); }
function is_main_site() { return true; }
function wp_next_scheduled( $name ) { return false; }
function wp_schedule_event( $time, $schedule, $hook ) {}
function wp_clear_scheduled_hook( $hook ) {}
function wp_salt( $scheme ) { return 'package-test-key'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_create_nonce( $action ) { return 'test-nonce'; }
function _wp_specialchars( $value, ...$args ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function home_url() { return 'https://example.test'; }
function network_home_url() { return 'https://network.test'; }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function plugin_dir_url( $file ) { return '/wp-content/plugins/simple-ldap-login/vendor/paidcommunities/wordpress-sdk/'; }
function get_file_data( $file, $headers, $context ) { return array( 'Version' => SLL_VERSION ); }
function remove_filter( $name, $callback, $priority = 10 ) {
	foreach ( $GLOBALS['sll_hooks'][ $name ][ $priority ] ?? array() as $key => $entry ) {
		if ( $callback === $entry[0] ) { unset( $GLOBALS['sll_hooks'][ $name ][ $priority ][ $key ] ); }
	}
}
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
function register_deactivation_hook( $file, $callback ) { package_check( is_callable( $callback ), 'Pro has a valid deactivation callback.' ); }
function get_role( $name ) { return in_array( $name, array( 'subscriber', 'contributor', 'editor', 'administrator' ), true ) ? (object) array() : null; }
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
if ( str_starts_with( $sll_scenario, 'v1' ) ) {
	unset( $sll_old['schema_version'] );
	$sll_old['version'] = '180';
	$sll_old['sso_enabled'] = 'true';
	$sll_old['meta_data_suffix_ldap'] = 'directory';
	$sll_old['meta_data_suffix_wp'] = 'local';
}
if ( $sll_existing ) {
	if ( $sll_network ) { $sll_network_options['sll_settings'] = $sll_old; } else { $sll_options['sll_settings'] = $sll_old; }
}
// A test activation record. No credentials or license bypass ship in either ZIP.
$sll_pro_license = 'pro' === $sll_edition;
if ( 'wrong-tier' === $sll_scenario ) { $sll_pro_license = ! $sll_pro_license; }
$sll_test_license = array( 'product_id' => $sll_pro_license ? 'prd_9QyrKxQpvZGrcCW8' : 'prd_9R92jB1prxMfJR36', 'status' => 'expired' === $sll_scenario ? 'expired' : 'active', 'secret' => 'test-secret', 'domain_id' => 'dom_test', 'site_url' => $sll_network ? network_home_url() : home_url() );
require $sll_package_dir . '/Simple-LDAP-Login.php';
$sll_test_license['secret'] = SimpleLdapLogin\Secrets::encrypt( 'test-secret' );
if ( 'unlicensed' !== $sll_scenario ) {
	if ( $sll_network ) { $sll_network_options['sll_pro_license'] = $sll_test_license; } else { $sll_options['sll_pro_license'] = $sll_test_license; }
}
package_check( array( 'another-plugin-helper' => true ) === $GLOBALS['__composer_autoload_files'], 'Private dependency loading preserves other Composer helper files.' );
$sll_classmap = require $sll_package_dir . '/vendor/composer/autoload_classmap.php';
package_check( isset( $sll_classmap['DateException'], $sll_classmap['DateMalformedIntervalStringException'] ), 'Composer can load the PHP 8.3 date exception stubs by their global names.' );
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
$sll_license = new SimpleLdapLogin\License( $sll_settings );
package_check( ( $sll_licensed ? ( $sll_pro_license ? 'pro' : 'enterprise' ) : '' ) === $sll_license->plan(), 'The built package reads only the verified product entitlement.' );
package_check( class_exists( SimpleLdapLogin\Settings::class ), 'Shared PSR-4 class loading works.' );
package_check( 3 === $sll_settings->get( 'schema_version' ), 'Package startup applies the current settings schema.' );
package_check( ( $sll_existing ? 'legacy' : 'selected' ) === $sll_settings->recovery_mode(), 'Fresh packages use selected recovery accounts and old packages retain the administrator rule.' );
package_check( defined( 'SLL_FEATURES_LOADED' ) === $sll_full, 'Full package features load only in a full package.' );
$sll_scope_options = $sll_network ? 'sll_network_options' : 'sll_options';
$sll_saved_hosts = $GLOBALS[ $sll_scope_options ]['sll_settings']['domain_controllers'];
$GLOBALS[ $sll_scope_options ]['sll_settings']['domain_controllers'] = array( 'primary.example.test', 'backup.example.test' );
$sll_probe = new class( $sll_settings ) extends SimpleLdapLogin\Ldap_Client {
	public array $attempted = array();
	protected function create_connection( array $configuration ): SimpleLdapLoginVendor\LdapRecord\Connection {
		$this->attempted[] = $configuration['hosts'][0];
		throw new RuntimeException( 'The test server is unavailable.' );
	}
};
$sll_probe->test_connection();
package_check( ( $sll_full && $sll_licensed ? array( 'primary.example.test', 'backup.example.test' ) : array( 'primary.example.test' ) ) === $sll_probe->attempted, 'Only an active paid package uses backup servers, including with retained keys in Free.' );
$GLOBALS[ $sll_scope_options ]['sll_settings']['domain_controllers'] = $sll_saved_hosts;
foreach ( array( 'Metadata', 'Server_Sso', 'Admin', 'Ldap_Client', 'Upgrade_Guard', 'Roles', 'Profile', 'Accounts', 'Settings_Rules', 'Multisite', 'Audit' ) as $sll_class ) {
	package_check( class_exists( 'SimpleLdapLogin\\Pro\\' . $sll_class ) === $sll_full, 'The package has the correct Pro PSR-4 classes.' );
}
foreach ( array( 'License', 'Commerce' ) as $sll_class ) {
	package_check( class_exists( 'SimpleLdapLogin\\' . $sll_class ), 'Both packages include the SDK purchase and activation code.' );
}
package_check( class_exists( 'SimpleLdapLoginVendor\\PaidCommunities\\WordPress\\HttpClient\\WordPressClient' ), 'The private license SDK loads beside another version.' );
if ( $sll_full ) {
	$sll_root      = realpath( $sll_package_dir );
	$sll_loaders   = Composer\Autoload\ClassLoader::getRegisteredLoaders();
	$sll_pro_paths = $sll_loaders[ $sll_root . '/vendor' ]->getPrefixesPsr4()['SimpleLdapLogin\\Pro\\'] ?? array();
	package_check( array( $sll_root . '/pro/src' ) === $sll_pro_paths && ! isset( $sll_loaders[ $sll_root . '/pro/vendor' ] ), 'The shared Composer loader serves Pro classes.' );
}
package_check( ! method_exists( SimpleLdapLogin\Ldap_Client::class, 'authenticate_sso' ), 'The shared LDAP client has no SSO implementation.' );
package_check( ! empty( $sll_hooks['sll_account_loaded'] ) === $sll_full, 'The full package registers custom metadata.' );
package_check( ! empty( $sll_hooks['sll_settings_tabs'] ), 'Both packages register purchase and license controls.' );
if ( $sll_full ) {
	$sll_group_data = ( new SimpleLdapLogin\Pro\Roles( $sll_settings ) )->groups( array(), $sll_connection, $sll_user, 'alice' );
	package_check( is_array( $sll_group_data ), 'Pro role callbacks accept the private LDAP connection and model types.' );
}
package_check( $sll_full === class_exists( 'SimpleLdapLoginVendor\\PaidCommunities\\WordPress\\Admin\\AdminAjaxController', false ), 'Only the paid package initializes the SDK admin handlers.' );
package_check( $sll_full === isset( $sll_hooks['update_plugins_paidcommunities.com'] ), 'Only the paid package registers the SDK update hook.' );
$sll_commerce = new SimpleLdapLogin\Commerce( $sll_settings );
ob_start();
$sll_commerce->license( new SimpleLdapLogin\Admin( $sll_settings, new SimpleLdapLogin\Ldap_Client( $sll_settings ) ) );
$sll_license_html = ob_get_clean();
package_check( str_contains( $sll_license_html, 'Use the ZIP for your plan' ) === ! $sll_full, 'Only Free shows installation instructions for the paid ZIP.' );
package_check( str_contains( $sll_license_html, 'sll-license-component' ) === $sll_full, 'Only the paid package renders the license activation component.' );
package_check( 'https://checkout.paidcommunities.com/checkout/prd_9QyrKxQpvZGrcCW8?plan=plan_9R8xkMlq1nmCekmG' === SimpleLdapLogin\Commerce::checkout_url() && 'https://checkout.paidcommunities.com/checkout/prd_9R92jB1prxMfJR36?plan=plan_9R937DcDDy4wMH4x' === SimpleLdapLogin\Commerce::checkout_url( 'enterprise' ), 'The built package maps each edition to its own product and annual plan.' );
if ( ! $sll_full ) {
	package_check( str_contains( $sll_license_html, 'Pro — $99/year' ) && str_contains( $sll_license_html, 'Enterprise — $499/year' ), 'Free shows both annual feature tiers.' );
} else {
	package_check( ! str_contains( $sll_license_html, 'sll_license_plan' ), 'Paid activation needs only a key.' );
	package_check( str_contains( $sll_license_html, 'Get Enterprise' ) === ( $sll_licensed && $sll_pro_license ), 'Only an active Pro license has an Enterprise upgrade offer.' );
}
if ( 'unlicensed' === $sll_scenario && $sll_full ) {
	ob_start(); $sll_commerce->license_notice(); $sll_notice_html = ob_get_clean();
	package_check( str_contains( $sll_notice_html, 'Activate your Pro or Enterprise license' ), 'The paid package shows its activation notice without a key.' );
}
if ( $sll_full && ! $sll_license->is_enterprise() ) {
	$sll_before = $sll_settings->all();
	package_check( is_wp_error( $sll_settings->save( array( 'scheduled_import' => true, 'audit_days' => 60 ) ) ) && $sll_before === $sll_settings->all(), 'A license without Enterprise access cannot change Enterprise settings in the built ZIP.' );
	$sll_client = new SimpleLdapLogin\Pro\Ldap_Client( $sll_settings );
	package_check( is_wp_error( $sll_client->import_users( static function () { throw new RuntimeException( 'A license without Enterprise access started an import.' ); } ) ) && is_wp_error( $sll_client->account_access( array() ) ), 'The built ZIP blocks Enterprise LDAP methods before a directory request.' );
	$sll_audit = new SimpleLdapLogin\Pro\Audit( $sll_settings );
	$sll_stream = fopen( 'php://temp', 'w+' );
	$sll_audit->write_csv( $sll_stream );
	rewind( $sll_stream );
	package_check( array() === $sll_audit->records() && '' === stream_get_contents( $sll_stream ), 'The built ZIP blocks audit reads and CSV export without Enterprise access.' );
	fclose( $sll_stream );
}

$sll_sso_init = array_filter( $sll_hooks['init'][20] ?? array(), static fn( $entry ) => is_array( $entry[0] ) && $entry[0][0] instanceof SimpleLdapLogin\Pro\Server_Sso );
package_check( ! empty( $sll_sso_init ) === ( $sll_full && $sll_existing && $sll_licensed ), 'SSO registers only when its code, setting, and active license are present.' );
package_check( isset( $sll_hooks['init'][10] ), 'Bundled translations register on init.' );
$sll_before = $sll_settings->all();
$sll_settings->migrate();
if ( str_starts_with( $sll_scenario, 'v1' ) ) {
	package_check( ( $sll_network ? $sll_network_options : $sll_options )['sll_settings_backup_v1'] === $sll_old, 'The 1.x backup keeps the original settings.' );
}
package_check( $sll_before === $sll_settings->all(), 'A second boot preserves current settings.' );
if ( $sll_network ) { package_check( array() === $sll_options, 'Network settings stay out of site options.' ); }
if ( $sll_existing ) {
	package_check( 'keep' === $sll_settings->get( 'custom_extension' ) && true === $sll_settings->get( 'sso_enabled' ), 'Old settings remain unchanged.' );
}
package_check( true === $sll_settings->save( array( 'user_meta_data' => array( array( 'telephoneNumber', 'phone', 'string' ) ) ) ), 'The settings store retains metadata definitions.' );
do_action( 'sll_account_loaded', new WP_User(), array( 'telephonenumber' => array( '5551002000' ) ) );
package_check( isset( $sll_meta[1][ str_starts_with( $sll_scenario, 'v1' ) ? 'phone_local' : 'phone_wp' ] ) === ( $sll_full && $sll_licensed ), 'Only a licensed Pro package copies custom metadata.' );
if ( $sll_full && $sll_licensed ) {
	package_check( '5551002000' === $sll_meta[1][ str_starts_with( $sll_scenario, 'v1' ) ? 'phone_local' : 'phone_wp' ] && '5551002000' === $sll_meta[1][ str_starts_with( $sll_scenario, 'v1' ) ? 'phone_directory' : 'phone_ldap' ], 'The real metadata module keeps both old keys.' );
}
echo 'Production package runtime passed: ' . $sll_edition . '/' . $sll_scenario . ".\n";
