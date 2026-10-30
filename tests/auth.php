<?php
/** Authentication regression checks, loaded by run.php. */

use SimpleLdapLogin\Authenticator;
use SimpleLdapLogin\Pro\Ldap_Client;
use SimpleLdapLogin\Pro\Metadata;
use SimpleLdapLogin\Pro\Server_Sso;
use SimpleLdapLogin\Settings;

function home_url() { return 'https://example.test'; }
function network_home_url() { return 'https://network.test'; }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }

class WP_User {
	public int $ID;
	public string $user_login;
	public string $user_email;
	public bool $admin;
	public array $roles = array( 'subscriber' );
	public function set_role( $role ) { $this->roles = $role ? array( $role ) : array(); }
	public bool $spam = false;
	public bool $deleted = false;
	public function __construct( int $id, string $login, string $email = '', bool $admin = false ) {
		$this->ID         = $id;
		$this->user_login = $login;
		$this->user_email = $email;
		$this->admin      = $admin;
	}
}

function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['sll_test_users'] as $user ) {
		$property = array( 'id' => 'ID', 'login' => 'user_login', 'email' => 'user_email' )[ $field ];
		if ( 0 === strcasecmp( (string) $user->$property, (string) $value ) ) {
			return $user;
		}
	}
	return false;
}
function is_email( $email ) { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function user_can( $user, $capability ) { return $user instanceof WP_User && ( 'update_core' === $capability && is_multisite() ? is_super_admin( $user->ID ) : $user->admin ); }
function is_super_admin( $id ) { return in_array( $id, $GLOBALS['sll_test_super_admins'] ?? array(), true ); }
function get_current_blog_id() { return $GLOBALS['sll_test_blog_id'] ?? 1; }
function is_user_member_of_blog( $id, $blog_id = 0 ) { return in_array( $id, $GLOBALS['sll_test_memberships'][ $blog_id ?: get_current_blog_id() ] ?? array(), true ); }
function metadata_exists( $type, $id, $key ) { return array_key_exists( $key, $GLOBALS['sll_test_meta'][ $id ] ?? array() ); }
function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['sll_test_meta'][ $id ][ $key ] ?? ( $single ? '' : array() ); }
function update_user_meta( $id, $key, $value ) { $GLOBALS['sll_test_meta'][ $id ][ $key ] = $value; return true; }
function validate_username( $username ) { return $username === sanitize_user( $username, true ) && '' !== $username; }
function sanitize_user( $username, $strict = false ) { return preg_replace( '/[^a-zA-Z0-9_.@ -]/', '', $username ); }
function sanitize_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : ''; }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function esc_url_raw( $url, $protocols = array() ) { return preg_match( '~^https?://~', $url ) ? $url : ''; }
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	$value = substr( str_repeat( 'aB2!xY9#', $length ), 0, $length );
	return $extra ? ' ' . substr( $value, 1, -1 ) . ' ' : $value;
}
function wp_insert_user( $data ) {
	$id = count( $GLOBALS['sll_test_users'] ) + 1;
	$GLOBALS['sll_test_inserted'] = $data;
	$GLOBALS['sll_test_users'][] = new WP_User( $id, $data['user_login'], $data['user_email'] );
	return $id;
}

class Sll_Test_Client extends Ldap_Client {
	public $result;
	public array $calls = array();
	public function authenticate( string $username, string $password ) {
		$this->calls[] = array( $username, $password );
		return $this->result;
	}
	public function authenticate_sso( string $username ) { $this->calls[] = array( $username, 'sso' ); return $this->result; }
}

function sll_auth_setup( array $changes = array(), bool $register = true ): array {
	$GLOBALS['sll_test_filters'] = array();
	$GLOBALS['sll_test_network'] = false;
	$GLOBALS['sll_test_super_admins'] = array();
	$GLOBALS['sll_test_memberships'] = array( 1 => array( 1, 2 ) );
	$GLOBALS['sll_test_blog_id'] = 1;
	$GLOBALS['sll_test_local_passwords'] = array();
	$GLOBALS['sll_test_options'] = array( 'sll_settings' => array_replace( Settings::defaults(), array( 'enabled' => true, 'high_security' => true, 'recovery_mode' => 'legacy' ), $changes ) );
	$GLOBALS['sll_test_options']['sll_pro_license'] = array( 'status' => 'active', 'secret' => 'test-secret', 'domain_id' => 'dom_test', 'site_url' => home_url() );
	$GLOBALS['sll_test_meta']    = array();
	$GLOBALS['sll_test_users']   = array( new WP_User( 1, 'alice', 'alice@example.com' ), new WP_User( 2, 'admin', 'admin@example.com', true ) );
	unset( $GLOBALS['sll_test_cookie_user'], $GLOBALS['sll_test_auth_observer'] );
	$settings = new Settings();
	$client   = new Sll_Test_Client( $settings );
	$client->result = array( 'dn' => 'uid=alice,dc=example,dc=com' );
	$auth = new Authenticator( $settings, $client );
	$sso  = new Server_Sso( $settings, $client, $auth );
	if ( $register ) {
		$auth->register();
		( new Metadata( $settings ) )->register();
		$sso->register();
	}
	return array( $settings, $client, $auth, $sso );
}

// Represent the three core fallback paths at their normal hook priorities.
function sll_add_fallback( string $path ): void {
	$priority = 'application' === $path ? 20 : 20;
	add_filter(
		'authenticate',
		static function ( $user, $username, $password ) use ( $path ) {
			if ( $user instanceof WP_User ) {
				return $user;
			}
			$local = get_user_by( 'email' === $path ? 'email' : 'login', $username );
			if ( ! $local ) { return $user; }
			if ( isset( $GLOBALS['sll_test_local_passwords'][ $local->ID ] ) && $password !== $GLOBALS['sll_test_local_passwords'][ $local->ID ] ) { return new WP_Error( 'incorrect_password', 'The local password is not valid.' ); }
			return apply_filters( 'wp_authenticate_user', $local, $password );
		},
		$priority,
		3
	);
}

list( , $client ) = sll_auth_setup();
$password = "a\\b'c\"d";
$result   = apply_filters( 'authenticate', null, 'alice', $password );
check( $result instanceof WP_User && array( 'alice', $password ) === $client->calls[0], 'LDAP receives the exact password bytes.' );

foreach ( array( 'username', 'email', 'application' ) as $path ) {
	list( , $client ) = sll_auth_setup();
	$client->result = new WP_Error( 'sll_credentials', 'Directory rejected the password.' );
	sll_add_fallback( $path );
	$username = 'email' === $path ? 'alice@example.com' : 'alice';
	$result = apply_filters( 'authenticate', null, $username, 'local-password' );
	check( is_wp_error( $result ) && 'sll_credentials' === $result->get_error_code(), 'Exclusive mode blocks ' . $path . ' password fallback.' );
}

list( , $client ) = sll_auth_setup();
$client->result = new WP_Error( 'sll_credentials', 'Directory is unavailable.' );
sll_add_fallback( 'username' );
$result = apply_filters( 'authenticate', null, 'admin', 'local-password' );
check( $result instanceof WP_User && 2 === $result->ID, 'A local administrator can use the recovery password.' );
add_filter( 'sll_force_ldap', static function () { return false; } );
$result = apply_filters( 'authenticate', null, 'admin', 'local-password' );
check( is_wp_error( $result ), 'The original filter can require LDAP for administrators.' );

// Explicit recovery accounts use core authentication without a directory request.
foreach ( array( 'username' => 'admin', 'email' => 'admin@example.com' ) as $path => $login ) {
	list( , $client ) = sll_auth_setup( array( 'recovery_mode' => 'selected', 'recovery_user_ids' => array( 2 ) ) );
	$GLOBALS['sll_test_local_passwords'][2] = 'local-password';
	sll_add_fallback( $path );
	$result = apply_filters( 'authenticate', null, $login, 'local-password' );
	check( $result instanceof WP_User && 2 === $result->ID && array() === $client->calls, 'Selected recovery uses local ' . $path . ' authentication without LDAP.' );
	$result = apply_filters( 'authenticate', null, $login, 'wrong-password' );
	check( is_wp_error( $result ) && array() === $client->calls, 'Selected recovery rejects a wrong local ' . $path . ' password without LDAP.' );
	$denial = new WP_Error( 'recovery_mfa', 'A second factor is required.' );
	add_filter( 'wp_authenticate_user', static function () use ( $denial ) { return $denial; } );
	check( $denial === apply_filters( 'authenticate', null, $login, 'local-password' ), 'Core user checks can deny selected recovery by ' . $path . '.' );
}
foreach ( array( 'off', 'selected' ) as $mode ) {
	list( , $client ) = sll_auth_setup( array( 'recovery_mode' => $mode ) );
	$client->result = new WP_Error( 'sll_credentials', 'LDAP rejected this request.' );
	sll_add_fallback( 'username' );
	check( is_wp_error( apply_filters( 'authenticate', null, 'admin', 'local-password' ) ) && 1 === count( $client->calls ), $mode . ' mode grants no automatic administrator exception.' );
	add_filter( 'sll_force_ldap', static function () { return true; } );
	check( is_wp_error( apply_filters( 'authenticate', null, 'admin', 'local-password' ) ), 'The legacy exception filter cannot grant recovery in ' . $mode . ' mode.' );
}
list( , $client ) = sll_auth_setup( array( 'recovery_mode' => 'off', 'high_security' => false ) );
$client->result = new WP_Error( 'sll_credentials', 'LDAP rejected this request.' );
sll_add_fallback( 'username' );
check( apply_filters( 'authenticate', null, 'admin', 'local-password' ) instanceof WP_User, 'Off removes the recovery exception but keeps the configured normal fallback policy.' );

// A verified directory name cannot map to a selected local recovery account.
foreach ( array( 'DOMAIN\\admin', 'directory-admin@example.com' ) as $login ) {
	list( , $client ) = sll_auth_setup( array( 'directory' => 'ad', 'high_security' => false, 'recovery_mode' => 'selected', 'recovery_user_ids' => array( 2 ), 'user_meta_data' => array( array( 'telephoneNumber', 'phone', 'string' ) ) ) );
	$client->result = array( 'dn' => 'cn=admin,dc=example,dc=com', 'samaccountname' => array( 'admin' ), 'telephonenumber' => array( '5551234' ) );
	$result = apply_filters( 'authenticate', null, $login, 'directory-password' );
	check( is_wp_error( $result ) && 'sll_local_recovery' === $result->get_error_code() && array() === $GLOBALS['sll_test_meta'], 'LDAP alias ' . $login . ' cannot sign in as or update a local recovery account.' );
}
list( $settings, , $auth ) = sll_auth_setup( array( 'recovery_mode' => 'selected', 'recovery_user_ids' => array( 2 ), 'user_meta_data' => array( array( 'telephoneNumber', 'phone', 'string' ) ) ) );
$result = $auth->authenticate_entry( 'admin', array( 'dn' => 'uid=admin,dc=example,dc=com' ), 'proof' );
check( is_wp_error( $result ) && 'sll_local_recovery' === $result->get_error_code(), 'The shared directory account path rejects a selected recovery account.' );
( new Metadata( $settings ) )->sync( get_user_by( 'login', 'admin' ), array( 'telephonenumber' => array( '5551234' ) ) );
check( array() === $GLOBALS['sll_test_meta'], 'Metadata sync leaves selected local recovery accounts unchanged.' );

list( , $client ) = sll_auth_setup( array( 'high_security' => false ) );
$client->result = new WP_Error( 'sll_groups', 'The account is outside the required group.' );
sll_add_fallback( 'username' );
check( is_wp_error( apply_filters( 'authenticate', null, 'alice', 'local-password' ) ), 'A group denial blocks local fallback when exclusive mode is off.' );

list( , $client ) = sll_auth_setup();
$mfa = new WP_Error( 'mfa_required', 'A second factor is required.' );
$result = apply_filters( 'authenticate', $mfa, 'alice', 'password' );
check( $mfa === $result && array() === $client->calls, 'An earlier login error is retained without an LDAP request.' );
add_filter( 'wp_authenticate_user', static function () use ( $mfa ) { return $mfa; } );
check( $mfa === apply_filters( 'authenticate', null, 'alice', 'password' ), 'A user security check can reject a valid LDAP password.' );

list( , $client ) = sll_auth_setup();
add_filter( 'authenticate', static function () use ( $mfa ) { return $mfa; }, 50 );
check( $mfa === apply_filters( 'authenticate', null, 'alice', 'password' ), 'A later MFA filter can reject a valid LDAP login.' );

list( , $client, $auth ) = sll_auth_setup( array( 'sso_enabled' => true, 'user_meta_data' => array( array( 'telephoneNumber', 'phone', 'string' ) ) ), false );
$auth->register();
$client->result['telephonenumber'] = array( '5551002000' );
$result = apply_filters( 'authenticate', null, 'alice', 'password' );
check( $result instanceof WP_User && empty( $GLOBALS['sll_test_filters']['init'] ) && empty( $GLOBALS['sll_test_filters']['sll_account_loaded'] ) && empty( $GLOBALS['sll_test_meta'] ), 'Free password login works without SSO or metadata hooks, even when their old settings remain stored.' );

list( , $client ) = sll_auth_setup( array( 'user_meta_data' => array( array( 'telephoneNumber', 'phone', 'number' ) ) ) );
$client->result['telephonenumber'] = array( '(555) 100-2000' );
apply_filters( 'authenticate', null, 'alice', 'password' );
check( '5551002000' === get_user_meta( 1, 'phone_ldap', true ) && '5551002000' === get_user_meta( 1, 'phone_wp', true ), 'A login saves both legacy user data keys.' );
update_user_meta( 1, 'phone_wp', 'local edit' );
apply_filters( 'authenticate', null, 'alice', 'password' );
check( 'local edit' === get_user_meta( 1, 'phone_wp', true ), 'A login retains local edits while LDAP data is unchanged.' );
$client->result['telephonenumber'] = array( '555 300 4000' );
apply_filters( 'authenticate', null, 'alice', 'password' );
check( '5553004000' === get_user_meta( 1, 'phone_wp', true ), 'A changed LDAP value updates the local user data copy.' );

list( , $client ) = sll_auth_setup( array( 'meta_data_suffix_wp' => 'capabilities' ) );
add_filter( 'sll_user_meta_data', static function () { return array( 'wp' => 'administrator' ); } );
apply_filters( 'authenticate', null, 'alice', 'password' );
check( ! metadata_exists( 'user', 1, 'wp_capabilities' ), 'A metadata filter cannot write capabilities.' );

list( , $client ) = sll_auth_setup( array( 'create_users' => true ) );
$client->result = array( 'dn' => 'uid=new-user,dc=example,dc=com', 'givenname' => array( 'New' ), 'sn' => array( 'User' ), 'mail' => array( 'new@example.com' ) );
$result = apply_filters( 'authenticate', null, 'new-user', 'directory-password' );
check( $result instanceof WP_User && 'directory-password' !== $GLOBALS['sll_test_inserted']['user_pass'] && 'subscriber' === $GLOBALS['sll_test_inserted']['role'], 'New accounts use a random local password and the selected role.' );

list( , $client, $auth ) = sll_auth_setup( array( 'enabled' => false ) );
check( empty( $GLOBALS['sll_test_filters']['authenticate'] ), 'Disabled LDAP adds no password hooks.' );
check( null === $auth->authenticate( null, 'alice', 'password' ) && array() === $client->calls, 'Disabled LDAP performs no password check.' );

list( , $client ) = sll_auth_setup( array( 'directory' => 'ad' ) );
$client->result['samaccountname'] = array( 'alice' );
check( get_user_by( 'login', 'alice' ) === apply_filters( 'authenticate', null, 'DOMAIN\\alice', 'password' ), 'A verified AD domain login uses its existing account name.' );

function is_user_logged_in() { return false; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function wp_is_json_request() { return false; }
function wp_unslash( $value ) { return stripslashes( $value ); }
function wp_authenticate( $username, $password ) {
	$password = trim( $password );
	if ( isset( $GLOBALS['sll_test_auth_observer'] ) ) { ( $GLOBALS['sll_test_auth_observer'] )( $username, $password ); }
	return apply_filters( 'authenticate', null, $username, $password );
}
function wp_set_current_user( $id ) { $GLOBALS['sll_test_current_user'] = $id; }
function wp_set_auth_cookie( $id, $remember = false, $secure = false ) { $GLOBALS['sll_test_cookie_user'] = $id; }
function is_ssl() { return true; }

list( , $client, , $sso ) = sll_auth_setup( array( 'directory' => 'ad', 'sso_enabled' => true ) );
$client->result['samaccountname'] = array( 'alice' );
$_SERVER['REMOTE_USER'] = addslashes( 'DOMAIN\\alice' );
$_SERVER['REQUEST_METHOD'] = 'GET';
$GLOBALS['sll_test_auth_observer'] = static function ( $username, $proof ) use ( $sso ) {
	$GLOBALS['sll_test_last_sso_proof'] = $proof;
	check( null === $sso->authenticate( null, 'admin', $proof ), 'An SSO entry cannot authenticate another login name.' );
	check( null === $sso->authenticate( null, $username, 'wrong-proof' ), 'An SSO entry cannot authenticate a different password call.' );
};
$sso->login();
unset( $GLOBALS['sll_test_auth_observer'] );
check( array( 'DOMAIN\\alice', 'sso' ) === $client->calls[0] && 1 === ( $GLOBALS['sll_test_cookie_user'] ?? 0 ), 'SSO removes WordPress server slashes and sets a session only for the checked account.' );
check( null === $sso->authenticate( null, 'DOMAIN\\alice', $GLOBALS['sll_test_last_sso_proof'] ), 'A finished SSO attempt cannot reuse its verified entry.' );
unset( $GLOBALS['sll_test_cookie_user'] );
add_filter( 'authenticate', static function () use ( $mfa ) { return $mfa; }, 50 );
$sso->login();
check( ! isset( $GLOBALS['sll_test_cookie_user'] ), 'A second-factor denial stops the server SSO cookie.' );

list( , $client, , $sso ) = sll_auth_setup( array( 'directory' => 'ad', 'enabled' => false, 'sso_enabled' => true ) );
$client->result['samaccountname'] = array( 'alice' );
sll_add_fallback( 'username' );
check( apply_filters( 'authenticate', null, 'alice', 'local-password' ) instanceof WP_User && array() === $client->calls, 'SSO alone does not intercept normal local password login.' );
$sso->login();
check( 1 === $GLOBALS['sll_test_cookie_user'] && array( array( 'DOMAIN\\alice', 'sso' ) ) === $client->calls, 'SSO works while LDAP password login is off.' );

list( , $client, , $sso ) = sll_auth_setup( array( 'directory' => 'ad', 'enabled' => false, 'sso_enabled' => true ) );
$client->result['samaccountname'] = array( 'alice' );
add_filter( 'wp_authenticate_user', static function () use ( $mfa ) { return $mfa; } );
sll_add_fallback( 'username' );
$sso->login();
check( ! isset( $GLOBALS['sll_test_cookie_user'] ), 'A failed SSO user check cannot become a local password login.' );

list( , $client, , $sso ) = sll_auth_setup( array( 'sso_enabled' => true ) );
$client->result = new WP_Error( 'sll_groups', 'The account is outside the required group.' );
$sso->login();
check( ! isset( $GLOBALS['sll_test_cookie_user'] ) && array( array( 'DOMAIN\\alice', 'sso' ) ) === $client->calls, 'A failed SSO group check cannot set a login cookie.' );
foreach ( array( 'admin', 'admin@example.com', 'DOMAIN\\admin', 'directory-admin@example.com' ) as $login ) {
	list( , $client, , $sso ) = sll_auth_setup( array( 'directory' => 'ad', 'sso_enabled' => true, 'recovery_mode' => 'selected', 'recovery_user_ids' => array( 2 ), 'user_meta_data' => array( array( 'telephoneNumber', 'phone', 'string' ) ) ) );
	$client->result = array( 'dn' => 'cn=admin,dc=example,dc=com', 'samaccountname' => array( 'admin' ), 'telephonenumber' => array( '5551234' ) );
	$_SERVER['REMOTE_USER'] = addslashes( $login );
	$sso->login();
	check( ! isset( $GLOBALS['sll_test_cookie_user'] ) && array() === $GLOBALS['sll_test_meta'], 'Server SSO cannot sign in as or update a recovery account through ' . $login . '.' );
	if ( in_array( $login, array( 'admin', 'admin@example.com' ), true ) ) {
		check( array() === $client->calls, 'A direct recovery identity skips the SSO directory request.' );
	}
}

unset( $_SERVER['REMOTE_USER'] );

define( 'SLL_DISABLE_LDAP', true );
sll_auth_setup( array( 'enabled' => true, 'sso_enabled' => true ) );
check( empty( $GLOBALS['sll_test_filters']['authenticate'] ) && empty( $GLOBALS['sll_test_filters']['init'] ), 'The recovery constant disables password and SSO hooks.' );

echo "Authentication checks passed.\n";
