<?php
/** Standalone regression checks. Run: composer test. */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'SLL_TEST_PAID', 'paid' === ( $argv[1] ?? '' ) );
if ( SLL_TEST_PAID && ! is_file( dirname( __DIR__ ) . '/pro/vendor/autoload.php' ) ) {
	throw new RuntimeException( 'Install the paid repo at pro/ and run composer install --working-dir=pro.' );
}

error_reporting( E_ALL );
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		if ( error_reporting() & $severity ) {
			throw new ErrorException( $message, 0, $severity, $file, $line );
		}
		return false;
	}
);

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

$GLOBALS['sll_test_options']      = array();
$GLOBALS['sll_test_site_options'] = array();
$GLOBALS['sll_test_filters']      = array();
$GLOBALS['sll_test_network']      = false;
$GLOBALS['sll_test_write_fail']   = false;
$GLOBALS['sll_test_count']        = 0;

function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function is_multisite() { return $GLOBALS['sll_test_network']; }
function plugin_basename( $file ) { return 'simple-ldap-login/' . basename( $file ); }
function get_option( $key, $default = false ) { return $GLOBALS['sll_test_options'][ $key ] ?? $default; }
function get_site_option( $key, $default = false ) { return $GLOBALS['sll_test_site_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) {
	if ( $GLOBALS['sll_test_write_fail'] || ( array_key_exists( $key, $GLOBALS['sll_test_options'] ) && $GLOBALS['sll_test_options'][ $key ] === $value ) ) {
		return false;
	}
	$GLOBALS['sll_test_options'][ $key ] = $value;
	return true;
}
function update_site_option( $key, $value ) {
	if ( $GLOBALS['sll_test_write_fail'] || ( array_key_exists( $key, $GLOBALS['sll_test_site_options'] ) && $GLOBALS['sll_test_site_options'][ $key ] === $value ) ) {
		return false;
	}
	$GLOBALS['sll_test_site_options'][ $key ] = $value;
	return true;
}
function wp_salt( $scheme ) { return 'fixed-salt-for-regression-checks'; }
function get_role( $role ) { return in_array( $role, array( 'subscriber', 'contributor', 'editor', 'administrator' ), true ) ? (object) array() : null; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['sll_test_filters'][ $name ][ $priority ][] = array( $callback, $accepted_args );
	return true;
}
function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ) { return add_filter( $name, $callback, $priority, $accepted_args ); }
function remove_filter( $name, $callback, $priority = 10 ) {
	foreach ( $GLOBALS['sll_test_filters'][ $name ][ $priority ] ?? array() as $index => $entry ) {
		if ( $entry[0] === $callback ) {
			unset( $GLOBALS['sll_test_filters'][ $name ][ $priority ][ $index ] );
			return true;
		}
	}
	return false;
}
function apply_filters( $name, $value, ...$args ) {
	$priorities = $GLOBALS['sll_test_filters'][ $name ] ?? array();
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) );
		}
	}
	return $value;
}
function do_action( $name, ...$args ) {
	$priorities = $GLOBALS['sll_test_filters'][ $name ] ?? array();
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) );
		}
	}
}
function check( $condition, $label ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$GLOBALS['sll_test_count'];
	echo 'PASS: ' . $label . "\n";
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
if ( SLL_TEST_PAID ) { require dirname( __DIR__ ) . '/pro/vendor/autoload.php'; }

use SimpleLdapLogin\Secrets;
use SimpleLdapLogin\Settings;

$settings = new Settings();
$settings->migrate();
check( false === $settings->get( 'enabled' ), 'A new installation has LDAP login off.' );
check( 3 === $settings->get( 'schema_version' ) && 'selected' === $settings->recovery_mode() && array() === $settings->get( 'recovery_user_ids' ), 'A new installation selects explicit local recovery accounts.' );
check( 'starttls' === $settings->get( 'transport' ) && false === $settings->get( 'allow_plaintext' ), 'A new installation requires TLS.' );
check( ! isset( $GLOBALS['sll_test_options']['sll_settings_backup_v1'] ), 'A new installation does not create an empty backup.' );
$settings->migrate();
check( ! isset( $GLOBALS['sll_test_options']['sll_settings_backup_v1'] ), 'Repeat migration does not create a backup for current settings.' );

$schema_two = Settings::defaults();
$schema_two['schema_version'] = 2;
unset( $schema_two['recovery_mode'], $schema_two['recovery_user_ids'] );
$GLOBALS['sll_test_options'] = array( 'sll_settings' => $schema_two, 'sll_settings_backup_v1' => array( 'keep' => 'original backup' ) );
check( 'legacy' === $settings->recovery_mode(), 'Old stored settings retain administrator recovery before migration.' );
$settings->migrate();
$expected = array_replace( $schema_two, array( 'schema_version' => 3, 'recovery_mode' => 'legacy', 'recovery_user_ids' => array() ) );
check( $expected === $GLOBALS['sll_test_options']['sll_settings'] && array( 'keep' => 'original backup' ) === $GLOBALS['sll_test_options']['sll_settings_backup_v1'], 'Schema version 2 migration adds the recovery policy and retains all other data and the backup.' );
$GLOBALS['sll_test_options'] = array( 'sll_settings' => $schema_two );
$GLOBALS['sll_test_write_fail'] = true;
$settings->migrate();
check( $schema_two === $GLOBALS['sll_test_options']['sll_settings'] && 'legacy' === $settings->recovery_mode(), 'A failed policy migration retains the original administrator recovery rule.' );
$GLOBALS['sll_test_write_fail'] = false;
$settings->migrate();
unset( $GLOBALS['sll_test_options']['sll_settings_backup_v1'] );
$settings->migrate();
check( ! isset( $GLOBALS['sll_test_options']['sll_settings_backup_v1'] ) && 3 === $settings->get( 'schema_version' ), 'Removing a backup does not repeat a completed migration.' );

$GLOBALS['sll_test_options'] = array( 'sll_settings' => Settings::defaults(), 'simpleldap_use_tls' => false );
$settings->migrate();
check( false === $settings->get( 'enabled' ) && 'starttls' === $settings->get( 'transport' ), 'Old option names do not overwrite current settings.' );

$GLOBALS['sll_test_options'] = array( 'simpleldap_use_tls' => false );
$settings->migrate();
check( 'plain' === $settings->get( 'transport' ) && array_key_exists( 'simpleldap_use_tls', $GLOBALS['sll_test_options']['sll_settings_backup_v1'] ), 'Migration keeps an old false TLS value and its backup.' );

$GLOBALS['sll_test_options'] = array();
$GLOBALS['sll_test_write_fail'] = true;
$settings->migrate();
check( array() === $GLOBALS['sll_test_options'], 'A failed settings write leaves storage unchanged.' );
$GLOBALS['sll_test_write_fail'] = false;
$settings->migrate();
check( false === $settings->get( 'enabled' ) && 3 === $settings->get( 'schema_version' ), 'A failed new installation can retry migration safely.' );

$legacy = array(
	'version'                  => '180',
	'enabled'                  => 'true',
	'use_tls'                  => 'false',
	'directory'                => 'ol',
	'base_dn'                  => 'dc=example,dc=com',
	'domain_controllers'       => array( 'ldap.example.com' ),
	'role'                     => 'contributor',
	'custom_setting'           => 'keep this value',
	'sso_search_user_password' => '',
);
$GLOBALS['sll_test_options'] = array( 'sll_settings' => $legacy );
$settings->migrate();
check( 'plain' === $settings->get( 'transport' ) && true === $settings->get( 'allow_plaintext' ), 'Migration retains an existing unencrypted connection.' );
check( 'legacy' === $settings->recovery_mode(), 'Old option arrays retain administrator recovery.' );
check( $legacy === $GLOBALS['sll_test_options']['sll_settings_backup_v1'], 'Migration saves the original settings.' );
check( 'keep this value' === $settings->get( 'custom_setting' ), 'Migration retains custom settings.' );
$before = $GLOBALS['sll_test_options'];
$settings->migrate();
check( $before === $GLOBALS['sll_test_options'], 'A second migration makes no change.' );

check( true === $settings->save( array( 'groups' => "staff;admins\neditors", 'user_meta_data' => "telephoneNumber:phone:number\nmail:contact:string" ) ), 'The settings accept group lists and user data mappings.' );
check( array( 'staff', 'admins', 'editors' ) === $settings->get( 'groups' ), 'Group lists accept new lines and semicolons.' );
check( 'keep this value' === $settings->get( 'custom_setting' ), 'A tab save retains custom settings.' );
check( 'dc=example,dc=com' === $settings->get( 'base_dn' ), 'A tab save retains fields from other tabs.' );
check( true === $settings->save( array( 'user_meta_data' => array( 4 => array( 'telephoneNumber', 'phone', 'number' ), 9 => array( 'mail', 'contact', 'string' ), 12 => array( '', '', '' ) ) ) ) && array( array( 'telephoneNumber', 'phone', 'number' ), array( 'mail', 'contact', 'string' ) ) === $settings->get( 'user_meta_data' ), 'Mapping rows retain their values and order while empty rows are ignored.' );
$before_mappings = $settings->all();
check( is_wp_error( $settings->save( array( 'user_meta_data' => array( array( 'mail', '', 'string' ) ) ) ) ) && $before_mappings === $settings->all(), 'An incomplete mapping row cannot replace saved settings.' );
check( true === $settings->save( array( 'user_meta_data' => array( array( '', '', '' ) ) ) ) && array() === $settings->get( 'user_meta_data' ), 'Clearing all mapping fields removes the mappings.' );
check( true === $settings->save( array( 'user_meta_data' => '' ) ) && array() === $settings->get( 'user_meta_data' ), 'Removing every mapping row submits an empty setting.' );
$before = $GLOBALS['sll_test_options'];
check( is_wp_error( $settings->save( array( 'base_dn' => 'changed', 'user_meta_data' => 'mail:wp:capabilities' ) ) ), 'Invalid mapping types return an error.' );
check( $before === $GLOBALS['sll_test_options'], 'A validation error saves no settings.' );
check( is_wp_error( $settings->save( array( 'unknown_input' => 'value' ) ) ), 'A form cannot add an unknown setting.' );
check( is_wp_error( $settings->save( array( 'domain_controllers' => 'ldap://user@ldap.example.com' ) ) ), 'A server URL cannot contain a user name.' );
check( is_wp_error( $settings->save( array( 'domain_controllers' => 'ldap://user:secret@ldap.example.com' ) ) ), 'A server URL cannot contain credentials.' );
check( is_wp_error( $settings->save( array( 'timeout' => 0 ) ) ), 'A zero timeout is invalid.' );
check( is_wp_error( $settings->save( array( 'ldap_version' => 2 ) ) ), 'A new setting cannot select LDAP version 2.' );
check( is_wp_error( $settings->save( array( 'ol_login' => 'uid)(objectClass=*' ) ) ), 'LDAP attribute names cannot contain a search filter.' );

$protected = array( 'wp_capabilities', 'wp_2_capabilities', 'wp_user_level', 'wp_2_user_level', 'session_tokens', 'application_passwords', 'user_pass', 'user_login', 'user_email', 'user_activation_key', 'primary_blog', 'source_domain', 'use_ssl', 'user_status' );
foreach ( $protected as $key ) {
	$split = strrpos( $key, '_' );
	$meta  = substr( $key, 0, $split );
	$suffix = substr( $key, $split + 1 );
	check( ! Settings::safe_meta_key( $key ), 'The runtime blocks protected metadata: ' . $key );
	foreach ( array( 'meta_data_suffix_ldap', 'meta_data_suffix_wp' ) as $suffix_setting ) {
		check( is_wp_error( $settings->save( array( $suffix_setting => $suffix, 'user_meta_data' => 'mail:' . $meta . ':string' ) ) ), 'The form blocks ' . $key . ' through ' . $suffix_setting . '.' );
	}
}
check( ! Settings::safe_meta_key( '_application_passwords' ) && ! Settings::safe_meta_key( '_session_tokens' ), 'The runtime blocks private metadata.' );
check( Settings::safe_meta_key( 'phone_wp' ), 'The runtime accepts a normal custom metadata key.' );

$GLOBALS['sll_test_write_fail'] = true;
$before = $GLOBALS['sll_test_options'];
check( is_wp_error( $settings->save( array( 'timeout' => 10 ) ) ), 'A failed database write returns an error.' );
check( $before === $GLOBALS['sll_test_options'], 'A failed database write retains the settings.' );
$GLOBALS['sll_test_write_fail'] = false;

check( true === $settings->save( array( 'sso_search_user_password' => "a\\b'c" ) ), 'The service account password can be saved.' );
$ciphertext = $settings->get( 'sso_search_user_password' );
check( "a\\b'c" === Secrets::decrypt( $ciphertext ), 'Password encryption retains quotes and backslashes.' );
check( true === $settings->save( array( 'sso_search_user_password' => '' ) ) && $ciphertext === $settings->get( 'sso_search_user_password' ), 'A blank password field retains the saved password.' );
$bytes     = base64_decode( substr( $ciphertext, 3 ) );
$bytes[20] = chr( ord( $bytes[20] ) ^ 1 );
check( is_wp_error( Secrets::decrypt( 'v2:' . base64_encode( $bytes ) ) ), 'A changed authentication tag cannot be decrypted.' );
check( is_wp_error( Secrets::decrypt( 'v2:invalid' ) ), 'A broken encrypted value returns an error.' );
$iv            = random_bytes( 16 );
$old_encrypted = openssl_encrypt( 'old-secret', 'AES-256-CBC', gethostname(), 0, $iv ) . ';' . bin2hex( $iv );
check( 'old-secret' === Secrets::decrypt( $old_encrypted ), 'The old password format can be decrypted.' );
$GLOBALS['sll_test_options'] = array( 'sll_settings' => array_replace( $legacy, array( 'sso_search_user_password' => $old_encrypted ) ) );
$settings->migrate();
check( 0 === strpos( $settings->get( 'sso_search_user_password' ), 'v2:' ) && 'old-secret' === Secrets::decrypt( $settings->get( 'sso_search_user_password' ) ), 'Migration upgrades the old password encryption.' );
check( $old_encrypted === $GLOBALS['sll_test_options']['sll_settings_backup_v1']['sso_search_user_password'], 'The backup retains the original encrypted password.' );
$GLOBALS['sll_test_options'] = array( 'sll_settings' => array_replace( $legacy, array( 'sso_search_user_password' => 'unreadable legacy value' ) ) );
$settings->migrate();
check( 'unreadable legacy value' === $settings->get( 'sso_search_user_password' ), 'Migration retains a password that needs repair.' );

$GLOBALS['sll_test_options']      = array();
$GLOBALS['sll_test_network']      = true;
$GLOBALS['sll_test_site_options'] = array(
	'active_sitewide_plugins'         => array( 'simple-ldap-login/Simple-LDAP-Login.php' => 1 ),
	'simpleldap_directory_type'       => 'directory_ad',
	'simpleldap_domain_controllers'   => array( 'ad.example.com' ),
	'simpleldap_base_dn'              => 'dc=example,dc=com',
	'simpleldap_login_mode'           => 'mode_create_group',
	'simpleldap_security_mode'        => 'security_high',
);
$settings->migrate();
check( $settings->is_network(), 'Network activation selects network settings.' );
check( $settings->get( 'enabled' ) && $settings->get( 'create_users' ) && $settings->get( 'high_security' ) && 'ad' === $settings->get( 'directory' ), 'The oldest network settings retain their login mode.' );
check( 'legacy' === $settings->recovery_mode(), 'The oldest network options retain administrator recovery.' );
check( array() === $GLOBALS['sll_test_options'], 'A network migration does not change site options.' );
check( isset( $GLOBALS['sll_test_site_options']['sll_settings_backup_v1']['simpleldap_base_dn'] ), 'The oldest network settings have a backup.' );
$GLOBALS['sll_test_network'] = false;
check( ! $settings->is_network() && false === $settings->get( 'enabled' ), 'Site settings remain separate from network settings.' );

$filter = static function ( $value, $key ) { return 'base_dn' === $key ? 'dc=filtered' : $value; };
add_filter( 'sll_get_setting', $filter, 10, 2 );
check( 'dc=filtered' === $settings->get( 'base_dn' ), 'The original setting filter still works.' );
remove_filter( 'sll_get_setting', $filter );

$GLOBALS['sll_test_network'] = true;
$GLOBALS['sll_test_options'] = array();
$GLOBALS['sll_test_site_options'] = array();
( new Settings( true ) )->migrate();
check( isset( $GLOBALS['sll_test_site_options']['sll_settings'] ) && array() === $GLOBALS['sll_test_options'], 'Network activation writes network settings before WordPress marks the plugin active.' );
$GLOBALS['sll_test_network'] = false;

$settings->save( array( 'sso_search_user_password' => 'compatibility-secret' ) );
$compat = new SimpleLDAPLogin( $settings );
check( true === $compat->set_settings_obj( $compat->get_settings_obj() ) && 'compatibility-secret' === Secrets::decrypt( $settings->get( 'sso_search_user_password' ) ), 'The legacy settings round trip does not encrypt a saved password twice.' );
check( false === $compat->add_setting( 'enabled', true ) && false === $settings->get( 'enabled' ), 'The legacy add method retains an explicit false setting.' );

echo 'Settings and secret checks passed: ' . $GLOBALS['sll_test_count'] . ".\n";

require __DIR__ . '/auth.php';
require __DIR__ . '/recovery-settings.php';
require __DIR__ . '/ldap.php';
if ( SLL_TEST_PAID ) { require dirname( __DIR__ ) . '/pro/tests/features.php'; }
define( 'SLL_DISABLE_LDAP', true );
sll_auth_setup( array( 'enabled' => true, 'sso_enabled' => true ) );
check( empty( $GLOBALS['sll_test_filters']['authenticate'] ) && empty( $GLOBALS['sll_test_filters']['init'] ), 'The recovery constant disables password and SSO hooks.' );
foreach ( SLL_TEST_PAID ? array( '/src/Ldap_Client.php', '/pro/src/Ldap_Client.php' ) : array( '/src/Ldap_Client.php' ) as $client_file ) {
	check( ! preg_match( '/\bldap_[a-z_]+\s*\(/i', file_get_contents( dirname( __DIR__ ) . $client_file ) ), 'The application LDAP client uses library APIs: ' . $client_file );
}
echo 'All checks passed: ' . $GLOBALS['sll_test_count'] . ".\n";
