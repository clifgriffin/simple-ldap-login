<?php
/** Run with WP-CLI against the Docker test site and the installed release ZIP. */

use SimpleLdapLogin\Ldap_Client;
use SimpleLdapLogin\Settings;

$edition   = getenv( 'SLL_EDITION' );
$directory = getenv( 'SLL_DIRECTORY' ) ?: 'ol';
$ad        = 'ad' === $directory;
$base      = getenv( 'SLL_BASE_DN' ) ?: 'dc=example,dc=test';
$host      = getenv( 'SLL_LDAP_HOST' ) ?: 'ldap';
$groups    = getenv( 'SLL_GROUP_BASE_DN' ) ?: ( $ad ? 'CN=Users,' : 'ou=groups,' ) . $base;
$reader    = getenv( 'SLL_BIND_USER' ) ?: ( $ad ? 'reader@example.test' : 'cn=reader,' . $base );
$checks    = 0;
$check     = static function ( bool $passed, string $label ) use ( &$checks ): void {
	if ( ! $passed ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$checks;
	echo 'PASS: ' . $label . "\n";
};

$check( in_array( $edition, array( 'free', 'pro' ), true ), 'The test edition is Free or Pro.' );
$check( in_array( $directory, array( 'ol', 'ad' ), true ), 'The test directory is OpenLDAP or Samba AD.' );
$check( defined( 'SLL_EDITION' ) && SLL_EDITION === $edition, 'The installed ZIP has the expected edition.' );
$check( extension_loaded( 'ldap' ), 'The PHP LDAP extension is loaded.' );
$check( class_exists( 'SimpleLdapLogin\\Pro\\Server_Sso' ) === ( 'pro' === $edition ), 'Only the Pro ZIP contains server SSO.' );
$check( ! method_exists( Ldap_Client::class, 'authenticate_sso' ), 'The shared LDAP client has no SSO implementation.' );

$bad_ca = '1' === getenv( 'SLL_BAD_CA' );
$library = 'SimpleLdapLoginVendor\\LdapRecord\\';
$transport_class = $library . 'Ldap';
$connection_class = $library . 'Connection';
$check( class_exists( $transport_class ) && class_exists( $connection_class ), 'The package contains its private LDAP library.' );
$check( ( new $transport_class() )->setOption( LDAP_OPT_X_TLS_CACERTFILE, $bad_ca ? '/certs/wrong-ca.crt' : '/certs/ca.crt' ), 'The test CA is set through the LDAP library.' );
$settings = new Settings();
$check( is_multisite() === ( '1' === getenv( 'SLL_MULTISITE' ) ), 'WordPress uses the requested site or multisite configuration.' );
if ( getenv( 'SLL_ACTIVATION_SCOPE' ) ) {
	$check( $settings->is_network() === ( 'network' === getenv( 'SLL_ACTIVATION_SCOPE' ) ), 'Plugin settings use the actual activation scope.' );
}
$save     = static function ( array $changes ) use ( $settings ): void {
	$result = $settings->save( $changes );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( 'Test settings failed: ' . $result->get_error_code() );
	}
};
$configuration = array(
	'enabled'                   => true,
	'directory'                 => $directory,
	'base_dn'                   => $base,
	'domain_controllers'        => array( $host ),
	'login_domain'              => $ad ? 'EXAMPLE' : '',
	'account_suffix'            => $ad ? '@example.test' : '',
	'ldap_port'                 => 389,
	'ldap_version'              => 3,
	'timeout'                   => 5,
	'transport'                 => 'starttls',
	'allow_plaintext'           => false,
	'search_sub_ous'            => true,
	'sso_search_user'           => $reader,
	'sso_search_user_password'  => 'Reader-Password-42!',
	'group_base_dn'             => $groups,
	'groups'                    => $bad_ca ? array() : array( 'staff' ),
	'ol_login'                  => 'uid',
	'ol_group'                  => 'cn',
	'group_uid'                 => 'memberUid',
	'high_security'             => true,
	'recovery_mode'             => 'legacy',
	'recovery_user_ids'         => array(),
	'create_users'              => true,
	'role'                      => 'subscriber',
	'sso_enabled'               => true,
	'user_first_name_attribute' => 'givenname',
	'user_last_name_attribute'  => 'sn',
	'user_email_attribute'      => 'mail',
	'user_url_attribute'        => 'wwwhomepage',
	'user_meta_data'            => array( array( 'telephoneNumber', 'phone', 'string' ) ),
	'meta_data_suffix_ldap'     => 'ldap',
	'meta_data_suffix_wp'       => 'wp',
);
$save( $configuration );
if ( '1' === getenv( 'SLL_CONFIGURE_ONLY' ) ) {
	echo "Docker test settings saved.\n";
	return;
}
// The runner saves settings in a prior process so normal WordPress startup registers each hook once.
$client   = new Ldap_Client( $settings );
$password = 'Test-Password-42!';

if ( $bad_ca ) {
	foreach ( array( 'starttls' => 389, 'ldaps' => 636 ) as $transport => $port ) {
		$save( array( 'transport' => $transport, 'ldap_port' => $port ) );
		$result = $client->authenticate( 'bob', $password );
		$check( is_wp_error( $result ) && 'sll_credentials' !== $result->get_error_code() && 'sll_groups' !== $result->get_error_code(), $transport . ' rejects the untrusted certificate.' );
		$before = get_user_by( 'login', 'bob' );
		$result = wp_authenticate( 'bob', $password );
		$after  = get_user_by( 'login', 'bob' );
		$check( is_wp_error( $result ) && ( $before ? $before->ID : 0 ) === ( $after ? $after->ID : 0 ), $transport . ' cannot create an account with an untrusted CA.' );
	}
	$save( array( 'transport' => 'starttls', 'ldap_port' => 389, 'groups' => array( 'staff' ) ) );
	echo 'Untrusted CA checks passed: ' . $edition . ', ' . $checks . ".\n";
	return;
}

// These names are reserved test fixtures in the isolated Docker database.
require_once ABSPATH . 'wp-admin/includes/user.php';
$remove_fixture = static function ( WP_User $fixture ): bool {
	if ( is_multisite() ) {
		require_once ABSPATH . 'wp-admin/includes/ms.php';
		return wpmu_delete_user( $fixture->ID );
	}
	return wp_delete_user( $fixture->ID );
};
foreach ( array( 'alice', 'bob', 'disabled' ) as $login ) {
	$fixture = get_user_by( 'login', $login );
	if ( $fixture ) {
		$check( $remove_fixture( $fixture ), 'The previous ' . $login . ' test fixture is removed.' );
	}
}
$check( ! get_user_by( 'login', 'alice' ) && ! get_user_by( 'login', 'bob' ), 'The WordPress test accounts start empty.' );
$check( true === $client->test_connection(), 'The search account connects with StartTLS.' );
$entry    = $client->authenticate( 'alice', $password );
$alice_dn = ( $ad ? 'CN=alice,CN=Users,' : 'uid=alice,ou=people,' ) . $base;
$check( is_array( $entry ) && 0 === strcasecmp( $alice_dn, $entry['dn'] ), 'A directory lookup finds the exact LDAP user.' );

foreach ( $ad ? array( 'staff', 'parentstaff', 'Domain Users', 'primaryparents' ) : array( 'staff', 'editors', 'unique' ) as $group ) {
	$save( array( 'groups' => array( $group ) ) );
	$check( is_array( $client->authenticate( 'alice', $password ) ), 'LDAP permits the ' . $group . ' group.' );
}
$save( array( 'groups' => array( 'absent', 'staff' ) ) );
$check( is_array( $client->authenticate( 'alice', $password ) ), 'One allowed group is sufficient when another group does not exist.' );
$save( array( 'groups' => array( 'absent', 'also-absent' ) ) );
$result = $client->authenticate( 'alice', $password );
$check( is_wp_error( $result ) && 'sll_groups' === $result->get_error_code(), 'An absent group list does not grant access.' );
if ( $ad ) {
	$save( array( 'groups' => array( 'staff' ), 'group_base_dn' => 'CN=Computers,' . $base ) );
	$result = $client->authenticate( 'alice', $password );
	$check( is_wp_error( $result ) && 'sll_groups' === $result->get_error_code(), 'AD denies a matching group outside the configured group base.' );
	$save( array( 'group_base_dn' => $groups ) );
}
$save( array( 'groups' => array( 'denied' ) ) );
$result = wp_authenticate( 'alice', $password );
$check( is_wp_error( $result ) && 'sll_groups' === $result->get_error_code() && ! get_user_by( 'login', 'alice' ), 'A denied group cannot create a WordPress account.' );
$save( array( 'groups' => array( 'staff' ) ) );
$result = wp_authenticate( 'bob', $password );
$check( is_wp_error( $result ) && 'sll_groups' === $result->get_error_code() && ! get_user_by( 'login', 'bob' ), 'A user outside the required group is denied.' );
$result = wp_authenticate( 'alice', 'wrong-password' );
$check( is_wp_error( $result ) && ! get_user_by( 'login', 'alice' ), 'An invalid password cannot create an account.' );
$check( is_wp_error( $client->authenticate( 'alice*)(uid=*)', $password ) ), 'An LDAP filter injection is rejected.' );
$check( is_wp_error( $client->authenticate( 'alice', '' ) ) && is_wp_error( $client->authenticate( "alice\0other", $password ) ), 'Empty passwords and null bytes cannot authenticate.' );

$save( array( 'domain_controllers' => array( '127.0.0.1:1', $host ), 'timeout' => 1 ) );
$result = $client->authenticate( 'alice', $password );
$check( 'pro' === SLL_EDITION ? is_array( $result ) : is_wp_error( $result ), 'Only the licensed paid package uses a backup server after the primary fails.' );
$save( array( 'domain_controllers' => array( $host, '127.0.0.1:1' ) ) );
$result = $client->authenticate( 'alice', 'wrong-password' );
$check( is_wp_error( $result ) && 'sll_credentials' === $result->get_error_code(), 'A credential denial is not replaced by a later controller error.' );
$save( array( 'domain_controllers' => array( 'ldaps://' . $host . ':636' ) ) );
$check( is_array( $client->authenticate( 'alice', $password ) ), 'An explicit LDAPS URI keeps encryption when StartTLS is the saved default.' );
$save( array( 'domain_controllers' => array( $host ), 'timeout' => 5 ) );

foreach ( array( 'starttls' => 389, 'ldaps' => 636 ) as $transport => $port ) {
	$save( array( 'domain_controllers' => array( $host . '-wrong-name' ), 'transport' => $transport, 'ldap_port' => $port ) );
	$result = $client->authenticate( 'alice', $password );
	$check( is_wp_error( $result ) && ! in_array( $result->get_error_code(), array( 'sll_credentials', 'sll_groups' ), true ), $transport . ' rejects a certificate for a different host name.' );
	$result = wp_authenticate( 'alice', $password );
	$check( is_wp_error( $result ) && ! get_user_by( 'login', 'alice' ), $transport . ' cannot create an account with a mismatched certificate host name.' );
}
$save( array( 'domain_controllers' => array( $host ), 'transport' => 'starttls', 'ldap_port' => 389 ) );

$save( array( 'sso_search_user' => '' ) );
$check( is_array( $client->authenticate( 'alice', $password ) ), $ad ? 'AD password login needs no search account.' : 'Anonymous OpenLDAP subtree search can resolve a user for password bind.' );
$save( array( 'sso_search_user' => $reader ) );
if ( ! $ad ) {
	$save( array( 'sso_search_user_password' => 'wrong-password' ) );
	$result = $client->authenticate( 'alice', $password );
	$check( is_wp_error( $result ) && 'sll_service_account' === $result->get_error_code(), 'A rejected OpenLDAP search account cannot resolve a user.' );
	$save( array( 'sso_search_user_password' => 'Reader-Password-42!' ) );
}

$before = $settings->all();
$result = $settings->save( array( 'transport' => 'plain', 'allow_plaintext' => false ) );
$check( is_wp_error( $result ) && 'sll_plaintext' === $result->get_error_code() && $before === $settings->all(), 'Plain LDAP requires explicit permission and a rejected save changes nothing.' );
$plain_without_permission = static function ( $value, $key ) { return 'transport' === $key ? 'plain' : $value; };
add_filter( 'sll_get_setting', $plain_without_permission, 10, 2 );
try {
	$result = $client->authenticate( 'alice', $password );
} finally {
	remove_filter( 'sll_get_setting', $plain_without_permission, 10 );
}
$check( is_wp_error( $result ) && 'sll_transport' === $result->get_error_code(), 'The runtime blocks plain LDAP when permission is absent.' );
$save( array( 'transport' => 'plain', 'allow_plaintext' => true ) );
$result = $client->authenticate( 'alice', $password );
$check( $ad ? is_wp_error( $result ) : is_array( $result ), $ad ? 'Samba rejects an unencrypted bind even when the client permits it.' : 'A legacy OpenLDAP plain connection works only after explicit permission.' );
$save( array( 'transport' => 'starttls', 'allow_plaintext' => false ) );

$save( array( 'create_users' => false ) );
$result = wp_authenticate( 'alice', $password );
$check( is_wp_error( $result ) && 'sll_account' === $result->get_error_code() && ! get_user_by( 'login', 'alice' ), 'Valid LDAP credentials cannot create an account when creation is off.' );
$save( array( 'create_users' => true ) );

if ( $ad ) {
	foreach ( array( 'alice@example.test', 'EXAMPLE\\alice' ) as $login ) {
		$result = $client->authenticate( $login, $password );
		$check( is_array( $result ) && 0 === strcasecmp( $alice_dn, $result['dn'] ), 'AD accepts the ' . $login . ' login form.' );
	}
	$result = wp_authenticate( 'disabled', $password );
	$check( is_wp_error( $result ) && ! get_user_by( 'login', 'disabled' ), 'A disabled AD user cannot create a WordPress account.' );
} else {
	$save( array( 'base_dn' => 'ou=people,' . $base, 'search_sub_ous' => false ) );
	$check( is_array( $client->authenticate( 'alice', $password ) ), 'A direct user DN bind succeeds.' );
	$check( is_wp_error( $client->authenticate( 'alice,ou=people,' . $base, $password ) ), 'An LDAP DN injection is rejected.' );
	$save( array( 'base_dn' => $base, 'search_sub_ous' => true ) );
}
$save( array( 'transport' => 'ldaps', 'ldap_port' => 636 ) );
$check( is_array( $client->authenticate( 'alice', $password ) ), 'LDAPS accepts the trusted test certificate.' );
$save( array( 'transport' => 'starttls', 'ldap_port' => 389 ) );

$user = wp_authenticate( 'alice', $password );
$check( $user instanceof WP_User, 'WordPress authenticates and creates the LDAP account.' );
$check( 'alice@example.test' === $user->user_email && 'Alice' === $user->first_name && 'Example' === $user->last_name, 'The new account has its LDAP profile.' );
$check( array( 'subscriber' ) === $user->roles && ! user_can( $user, 'manage_options' ), 'The new account has only the safe configured role.' );
$check( ! wp_check_password( $password, $user->user_pass, $user->ID ), 'WordPress does not save the LDAP password as its local password.' );
$save( array( 'create_users' => false ) );
$result = wp_authenticate( 'alice', $password );
$check( $result instanceof WP_User && $user->ID === $result->ID, 'An existing LDAP user can log in when account creation is off.' );
$save( array( 'create_users' => true ) );
if ( $ad ) {
	foreach ( array( 'alice@example.test', 'EXAMPLE\\alice' ) as $login ) {
		$result = wp_authenticate( $login, $password );
		$check( $result instanceof WP_User && $user->ID === $result->ID, 'AD login ' . $login . ' uses the same WordPress account.' );
	}
}
wp_set_password( 'Local-Password-42!', $user->ID );
$result = wp_authenticate( 'alice', 'Local-Password-42!' );
$check( is_wp_error( $result ) && 'sll_credentials' === $result->get_error_code(), 'Exclusive LDAP mode blocks a local password fallback.' );

$save( array( 'high_security' => false ) );
foreach ( array( 'alice', 'alice@example.test' ) as $login ) {
	$result = wp_authenticate( $login, 'Local-Password-42!' );
	$check( $result instanceof WP_User && $user->ID === $result->ID, 'Permitted local fallback works for ' . $login . '.' );
}
// Make the local password valid for this LDAP attempt, so fallback would expose a bypass.
wp_set_password( $password, $user->ID );
$save( array( 'groups' => array( 'denied' ) ) );
$result = wp_authenticate( 'alice', $password );
$check( is_wp_error( $result ) && 'sll_groups' === $result->get_error_code(), 'A group denial blocks a valid local password when fallback is enabled.' );
$save( array( 'high_security' => true, 'groups' => array( 'staff' ) ) );
wp_set_password( 'Local-Password-42!', $user->ID );

$admin = get_user_by( 'login', 'testadmin' );
$check( $admin instanceof WP_User && ( user_can( $admin, 'update_core' ) || is_super_admin( $admin->ID ) ), 'The test administrator has the recovery capability.' );
$save( array( 'domain_controllers' => array( '127.0.0.1:1' ), 'timeout' => 1 ) );
$result = wp_authenticate( 'testadmin', 'Test-Admin-42!' );
$check( $result instanceof WP_User && $admin->ID === $result->ID, 'A local administrator can recover access while the directory is unavailable.' );
$save( array( 'domain_controllers' => array( $host ), 'timeout' => 5 ) );

$save( array( 'groups' => array() ) );
$check( is_array( $client->authenticate( 'bob', $password ) ), 'An empty group list permits a valid directory user outside staff.' );
$result = $settings->save( array( 'role' => 'sll_removed_role' ) );
$check( is_wp_error( $result ) && 'subscriber' === $settings->get( 'role' ), 'An invalid role cannot replace the saved default role.' );
$invalid_role = static function ( $value, $key ) { return 'role' === $key ? 'sll_removed_role' : $value; };
add_filter( 'sll_get_setting', $invalid_role, 10, 2 );
try {
	$result = wp_authenticate( 'bob', $password );
} finally {
	remove_filter( 'sll_get_setting', $invalid_role, 10 );
}
$check( is_wp_error( $result ) && 'sll_role' === $result->get_error_code() && ! get_user_by( 'login', 'bob' ), 'A missing runtime role fails closed without creating an account.' );

remove_role( 'sll_test_member' );
add_role( 'sll_test_member', 'Test member', array( 'read' => true ) );
$save( array( 'role' => 'sll_test_member', 'user_first_name_attribute' => 'cn', 'user_last_name_attribute' => 'givenName' ) );
$member = wp_authenticate( 'bob', $password );
$check( $member instanceof WP_User && array( 'sll_test_member' ) === $member->roles && ! user_can( $member, 'manage_options' ), 'A new account receives the configured custom role without administrator access.' );
$check( ( $ad ? 'bob' : 'Bob Example' ) === $member->first_name && 'Bob' === $member->last_name, 'A new account uses the configured LDAP profile attribute names.' );
$result = wp_authenticate( 'alice', $password );
$check( $result instanceof WP_User && array( 'subscriber' ) === $result->roles, 'A changed default role does not change an existing account role.' );
$check( $remove_fixture( $member ), 'The custom-role test account is removed.' );
$save( $configuration );
remove_role( 'sll_test_member' );

if ( ! $ad ) {
	$connection = new $connection_class(
		array(
			'hosts'        => array( $host ),
			'username'     => $alice_dn,
			'password'     => $password,
			'use_starttls' => true,
			'options'      => array( LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_DEMAND ),
		)
	);
	$connection->connect();
	$check( $connection->isConnected(), 'The password-byte fixture uses a trusted TLS connection through the LDAP library.' );
	try {
		foreach ( array( '0', "p\\ass'word", 'p&amp;word' ) as $exact_password ) {
			$check( $connection->getLdapConnection()->modReplace( $alice_dn, array( 'userPassword' => $exact_password ) ), 'The directory accepts the password-byte test value.' );
			$result = wp_authenticate( 'alice', $exact_password );
			$check( $result instanceof WP_User && $user->ID === $result->ID, 'LDAP login retains the exact password bytes.' );
			$check( is_wp_error( wp_authenticate( 'alice', $exact_password . 'changed' ) ), 'A changed password-byte test value is rejected.' );
		}
	} finally {
		$restored = $connection->getLdapConnection()->modReplace( $alice_dn, array( 'userPassword' => $password ) );
		$connection->disconnect();
		$check( $restored, 'The original directory fixture password is restored.' );
	}
}

$deny = static function () { return new WP_Error( 'sll_test_user_check', 'Test user check denied access.' ); };
add_filter( 'wp_authenticate_user', $deny, 100 );
$result = wp_authenticate( 'alice', $password );
remove_filter( 'wp_authenticate_user', $deny, 100 );
$check( is_wp_error( $result ) && 'sll_test_user_check' === $result->get_error_code(), 'WordPress user checks can deny LDAP login.' );

if ( 'pro' === $edition ) {
	$check( '+15550100' === get_user_meta( $user->ID, 'phone_ldap', true ) && '+15550100' === get_user_meta( $user->ID, 'phone_wp', true ), 'Pro copies custom LDAP metadata into both legacy keys.' );
	update_user_meta( $user->ID, 'phone_wp', 'local edit' );
	wp_authenticate( 'alice', $password );
	$check( 'local edit' === get_user_meta( $user->ID, 'phone_wp', true ), 'Pro keeps a local edit when the LDAP source value is unchanged.' );
	update_user_meta( $user->ID, 'phone_wp', '+15550100' );
	$save( array( 'meta_data_suffix_ldap' => 'directory', 'meta_data_suffix_wp' => 'local', 'user_meta_data' => array( array( 'telephoneNumber', 'telephone', 'number' ) ) ) );
	wp_authenticate( 'alice', $password );
	$check( '15550100' === get_user_meta( $user->ID, 'telephone_directory', true ) && '15550100' === get_user_meta( $user->ID, 'telephone_local', true ), 'Pro number mappings use digits and the configured suffixes.' );
	update_user_meta( $user->ID, 'telephone_local', 'local edit' );
	wp_authenticate( 'alice', $password );
	$check( 'local edit' === get_user_meta( $user->ID, 'telephone_local', true ), 'Custom suffix mappings keep a local edit while the mapped value is unchanged.' );
	$save( array( 'user_meta_data' => array( array( 'sn', 'telephone', 'string' ) ) ) );
	wp_authenticate( 'alice', $password );
	$check( 'Example' === get_user_meta( $user->ID, 'telephone_directory', true ) && 'Example' === get_user_meta( $user->ID, 'telephone_local', true ), 'A changed mapping replaces both copies with the new LDAP value.' );

	global $wpdb;
	$prefix        = $wpdb->get_blog_prefix();
	$capabilities  = get_user_meta( $user->ID, $prefix . 'capabilities', true );
	$unsafe_value  = static function () use ( $prefix ) { return array( rtrim( $prefix, '_' ) => 'administrator' ); };
	$unsafe_suffix = static function ( $value, $key ) { return 'meta_data_suffix_wp' === $key ? 'capabilities' : $value; };
	add_filter( 'sll_user_meta_data', $unsafe_value );
	add_filter( 'sll_get_setting', $unsafe_suffix, 10, 2 );
	try {
		wp_authenticate( 'alice', $password );
	} finally {
		remove_filter( 'sll_user_meta_data', $unsafe_value );
		remove_filter( 'sll_get_setting', $unsafe_suffix, 10 );
	}
	$check( $capabilities === get_user_meta( $user->ID, $prefix . 'capabilities', true ) && ! user_can( new WP_User( $user->ID ), 'manage_options' ), 'A runtime metadata filter cannot replace the real WordPress capability key.' );
	$save( $configuration );
	$pro    = new SimpleLdapLogin\Pro\Ldap_Client( $settings );
	$result = $pro->authenticate_sso( 'alice' );
	$check( is_array( $result ) && $entry['dn'] === $result['dn'], 'Pro SSO resolves a user through the search account.' );
	if ( $ad ) {
		foreach ( array( 'alice@example.test', 'EXAMPLE\\alice' ) as $login ) {
			$result = $pro->authenticate_sso( $login );
			$check( is_array( $result ) && 0 === strcasecmp( $alice_dn, $result['dn'] ), 'Pro SSO resolves the ' . $login . ' AD identity.' );
		}
		$result = $pro->authenticate_sso( 'OTHER\\alice' );
		$check( is_wp_error( $result ) && 'sll_sso_domain' === $result->get_error_code(), 'Pro SSO rejects an identity from another AD domain.' );
		$save( array( 'groups' => array() ) );
		$check( is_wp_error( $pro->authenticate_sso( 'disabled' ) ), 'Pro SSO excludes a disabled AD account even without a group rule.' );
		$save( array( 'groups' => array( 'staff' ) ) );
	}
	$save( array( 'sso_search_user_password' => 'wrong-password' ) );
	$check( is_wp_error( $pro->authenticate_sso( 'alice' ) ), 'Pro SSO rejects an invalid search account password.' );
	$save( array( 'sso_search_user_password' => 'Reader-Password-42!' ) );
	$save( array( 'groups' => array( 'denied' ) ) );
	$result = $pro->authenticate_sso( 'alice' );
	$check( is_wp_error( $result ) && 'sll_groups' === $result->get_error_code(), 'Pro SSO applies the required group rule.' );
	$save( array( 'groups' => array( 'staff' ) ) );
} else {
	$check( ! metadata_exists( 'user', $user->ID, 'phone_ldap' ) && ! metadata_exists( 'user', $user->ID, 'phone_wp' ), 'Free keeps metadata settings without running the Pro feature.' );
	$check( ! class_exists( 'SimpleLdapLogin\\Pro\\Metadata' ), 'The Free ZIP contains no Pro metadata code.' );
}

// Recovery accounts use WordPress directly. Directory aliases must not select them.
$recovery_roles = $user->roles;
$recovery_super = is_multisite() && is_super_admin( $user->ID );
$temporary_recovery = null;
$directory_reads = 0;
$watch_directory = static function ( $value, $key ) use ( &$directory_reads ) {
	if ( 'domain_controllers' === $key ) {
		++$directory_reads;
	}
	return $value;
};
try {
	$user->set_role( 'administrator' );
	if ( $settings->is_network() && ! $recovery_super ) {
		grant_super_admin( $user->ID );
	}
	wp_set_password( 'Local-Password-42!', $user->ID );
	$save( array( 'recovery_mode' => 'selected', 'recovery_user_ids' => array( $user->ID ), 'domain_controllers' => array( '127.0.0.1:1' ), 'timeout' => 1 ) );
	add_filter( 'sll_get_setting', $watch_directory, 10, 2 );
	foreach ( array( 'alice', 'alice@example.test' ) as $login ) {
		$result = wp_authenticate( $login, 'Local-Password-42!' );
		$check( $result instanceof WP_User && $user->ID === $result->ID, 'A selected recovery account accepts its local password through ' . $login . '.' );
	}
	$check( is_wp_error( wp_authenticate( 'alice', 'wrong-local-password' ) ), 'A selected recovery account rejects an invalid local password.' );
	$check( 0 === $directory_reads, 'Selected recovery password login makes no directory connection attempt.' );
	remove_filter( 'sll_get_setting', $watch_directory, 10 );
	$result = wp_authenticate( 'testadmin', 'Test-Admin-42!' );
	$check( is_wp_error( $result ), 'A nonselected administrator follows the exclusive LDAP policy.' );

	add_filter( 'wp_authenticate_user', $deny, 100 );
	$result = wp_authenticate( 'alice', 'Local-Password-42!' );
	remove_filter( 'wp_authenticate_user', $deny, 100 );
	$check( is_wp_error( $result ) && 'sll_test_user_check' === $result->get_error_code(), 'WordPress user checks can deny local recovery login.' );
	add_filter( 'authenticate', $deny, 100 );
	$result = wp_authenticate( 'alice', 'Local-Password-42!' );
	remove_filter( 'authenticate', $deny, 100 );
	$check( is_wp_error( $result ) && 'sll_test_user_check' === $result->get_error_code(), 'A later authentication filter can deny local recovery login.' );

	$save( array( 'domain_controllers' => array( $host ), 'timeout' => 5 ) );
	$check( is_wp_error( wp_authenticate( 'alice', $password ) ), 'A selected recovery account cannot use its directory password.' );
	$account_loaded = 0;
	$watch_account = static function () use ( &$account_loaded ) { ++$account_loaded; };
	add_action( 'sll_account_loaded', $watch_account, 100 );
	$account_auth = new SimpleLdapLogin\Authenticator( $settings, $client );
	foreach ( $ad ? array( 'alice', 'alice@example.test', 'EXAMPLE\\alice' ) : array( 'alice' ) as $login ) {
		$check( is_wp_error( $account_auth->authenticate_entry( $login, $entry, 'test-proof' ) ), 'A verified directory identity cannot select recovery account ' . $login . '.' );
	}
	remove_action( 'sll_account_loaded', $watch_account, 100 );
	$check( 0 === $account_loaded, 'A denied directory identity cannot run recovery account profile or metadata hooks.' );

	$save( array( 'recovery_mode' => 'off', 'domain_controllers' => array( '127.0.0.1:1' ), 'timeout' => 1 ) );
	$check( is_wp_error( wp_authenticate( 'alice', 'Local-Password-42!' ) ), 'Off removes the selected administrator exception.' );
	$save( array( 'recovery_mode' => 'selected', 'recovery_user_ids' => array( $user->ID ) ) );
	if ( $settings->is_network() ) {
		revoke_super_admin( $user->ID );
	} else {
		$user->set_role( 'subscriber' );
	}
	$check( is_wp_error( wp_authenticate( 'alice', 'Local-Password-42!' ) ), 'A demoted recovery account loses its local exception.' );
	$user->set_role( 'administrator' );
	if ( $settings->is_network() ) {
		grant_super_admin( $user->ID );
	}
	$save( array( 'recovery_user_ids' => array( $admin->ID ) ) );
	$check( is_wp_error( wp_authenticate( 'alice', 'Local-Password-42!' ) ), 'Removing an account from the selected list removes its local exception.' );

	$temporary_id = wp_insert_user( array( 'user_login' => 'sll-recovery-delete', 'user_pass' => 'Recovery-Delete-42!', 'user_email' => 'recovery-delete@example.test', 'role' => 'administrator' ) );
	$check( ! is_wp_error( $temporary_id ), 'The disposable local recovery account is created.' );
	$temporary_recovery = get_user_by( 'id', $temporary_id );
	if ( $settings->is_network() ) {
		grant_super_admin( $temporary_id );
	}
	$save( array( 'recovery_user_ids' => array( $temporary_id ) ) );
	if ( $settings->is_network() ) {
		revoke_super_admin( $temporary_id );
	}
	$check( $remove_fixture( $temporary_recovery ), 'The disposable recovery account is deleted.' );
	$temporary_recovery = null;
	$check( is_wp_error( wp_authenticate( 'sll-recovery-delete', 'Recovery-Delete-42!' ) ), 'A deleted recovery account cannot retain local access.' );
} finally {
	remove_filter( 'sll_get_setting', $watch_directory, 10 );
	remove_filter( 'wp_authenticate_user', $deny, 100 );
	remove_filter( 'authenticate', $deny, 100 );
	if ( isset( $watch_account ) ) {
		remove_action( 'sll_account_loaded', $watch_account, 100 );
	}
	if ( $temporary_recovery instanceof WP_User ) {
		if ( $settings->is_network() ) {
			revoke_super_admin( $temporary_recovery->ID );
		}
		$remove_fixture( $temporary_recovery );
	}
	if ( is_multisite() && ! $recovery_super ) {
		revoke_super_admin( $user->ID );
	}
	$user->set_role( $recovery_roles[0] );
	$save( $configuration );
}

$result = $settings->save( array( 'meta_data_suffix_wp' => 'capabilities', 'user_meta_data' => array( array( 'telephoneNumber', 'wp', 'string' ) ) ) );
$check( is_wp_error( $result ) && ! user_can( $user, 'manage_options' ), 'A metadata mapping cannot target WordPress account permissions.' );
$check( 'wp' === $settings->get( 'meta_data_suffix_wp' ), 'A rejected settings change leaves the saved configuration in place.' );
$save( $configuration );

echo 'Docker WordPress and ' . ( $ad ? 'Samba AD' : 'OpenLDAP' ) . ' checks passed: ' . $edition . ', ' . $checks . ".\n";
