<?php
/** LDAP client checks use the library transport fake and real query builders. */

namespace SimpleLdapLogin;

use LdapRecord\Connection;
use LdapRecord\ConnectionException;
use LdapRecord\Testing\LdapFake;

/** Record the library calls without replacing any PHP LDAP function. */
class Sll_Recording_Ldap extends LdapFake {
	public function setOption( int $option, mixed $value ): bool {
		$GLOBALS['sll_test_ldap']['options'][] = array( $option, $value );
		if ( LDAP_OPT_X_TLS_REQUIRE_CERT === $option && ! $GLOBALS['sll_test_ldap']['certificate'] ) {
			throw new ConnectionException( 'The test certificate policy failed.' );
		}
		return parent::setOption( $option, $value );
	}
	public function connect( string|array $hosts = array(), int $port = 389, ?string $protocol = null ): bool {
		$result = parent::connect( $hosts, $port, $protocol );
		$GLOBALS['sll_test_ldap']['uris'][] = $this->getHost();
		return $result;
	}
	public function close(): bool {
		++$GLOBALS['sll_test_ldap']['closed'];
		return parent::close();
	}
	protected function resolveExpectation( string $method, array $args = array() ): mixed {
		if ( in_array( $method, array( 'search', 'read', 'list' ), true ) ) {
			$GLOBALS['sll_test_ldap']['searches'][] = array( 'read' => 'read' === $method, 'base' => $args[0], 'filter' => $args[1], 'attributes' => $args[2], 'size_limit' => $args[4] ?? 0 );
			if ( ( $GLOBALS['sll_test_ldap']['search_error_at'] ?? 0 ) === count( $GLOBALS['sll_test_ldap']['searches'] ) ) { $this->shouldReturnErrorNumber( 1 ); }
			return array_shift( $GLOBALS['sll_test_ldap']['responses'] ) ?: array();
		}
		if ( 'bind' === $method ) {
			$GLOBALS['sll_test_ldap']['binds'][] = array( $args[0] ?? null, $args[1] ?? null );
		}
		$result = parent::resolveExpectation( $method, $args );
		if ( 'bind' === $method ) {
			$this->shouldReturnErrorNumber( $result->errorCode );
		}
		return $result;
	}
}

/** Replace only the connection transport; keep production lookup and policy code. */
trait Sll_Fake_Connection {
	protected function create_connection( array $configuration ): Connection {
		$GLOBALS['sll_test_ldap']['configurations'][] = $configuration;
		$transport = new Sll_Recording_Ldap();
		foreach ( $GLOBALS['sll_test_ldap']['bind_results'] as $bound ) {
			$transport->expect( LdapFake::operation( 'bind' )->once()->andReturnResponse( $bound ? 0 : 49 ) );
		}
		$transport->expect( LdapFake::operation( 'bind' )->andReturnResponse() );
		$transport->expect( $GLOBALS['sll_test_ldap']['tls'] ? LdapFake::operation( 'startTLS' )->andReturnTrue() : LdapFake::operation( 'startTLS' )->andThrow( new ConnectionException( 'The test TLS request failed.' ) ) );
		return new Connection( $configuration, $transport );
	}
}
class Sll_Free_Ldap_Client extends Ldap_Client { use Sll_Fake_Connection; }
class Sll_Pro_Ldap_Client extends Pro\Ldap_Client { use Sll_Fake_Connection; }

function sll_ldap_setup( array $changes = array(), bool $pro = true ): Ldap_Client {
	$GLOBALS['sll_test_filters'] = array();
	$GLOBALS['sll_test_network'] = false;
	$GLOBALS['sll_test_options'] = array( 'sll_settings' => array_replace( Settings::defaults(), array( 'directory' => 'ol', 'base_dn' => 'dc=example,dc=com', 'domain_controllers' => array( 'ldap.example.com' ) ), $changes ) );
	$GLOBALS['sll_test_options']['sll_pro_license'] = array( 'status' => 'active', 'secret' => 'test-secret', 'domain_id' => 'dom_test', 'site_url' => \home_url() );
	$GLOBALS['sll_test_ldap'] = array( 'uris' => array(), 'options' => array(), 'configurations' => array(), 'tls' => true, 'certificate' => true, 'binds' => array(), 'bind_results' => array(), 'searches' => array(), 'responses' => array(), 'closed' => 0 );
	$settings = new Settings();
	if ( $pro ) {
		( new Pro\Metadata( $settings ) )->register();
		return new Sll_Pro_Ldap_Client( $settings );
	}
	return new Sll_Free_Ldap_Client( $settings );
}
function sll_entries( array $entry ): array { return array( 'count' => 1, 0 => $entry ); }

/** Match an exact filter value; reject raw wildcard and filter control bytes. */
function sll_filter_equals( string $filter, string $attribute, string $expected ): bool {
	preg_match_all( '/\(' . preg_quote( $attribute, '/' ) . '=(?:\\\\[a-f0-9]{2}|[^*()\\\\])*\)/i', $filter, $matches );
	foreach ( $matches[0] as $comparison ) {
		$value = substr( $comparison, strlen( $attribute ) + 2, -1 );
		$value = preg_replace_callback( '/\\\\([a-f0-9]{2})/i', fn( $byte ) => chr( hexdec( $byte[1] ) ), $value );
		if ( $expected === $value ) {
			return true;
		}
	}
	return false;
}

if ( ! \extension_loaded( 'ldap' ) ) {
	throw new \RuntimeException( 'The LDAP checks require the PHP LDAP extension.' );
}

$adapter = new class( new Settings() ) extends Ldap_Client {
	public function production_connection( array $configuration ): Connection {
		return $this->create_connection( $configuration );
	}
};
$connection = $adapter->production_connection( array( 'hosts' => array( '127.0.0.1' ), 'port' => 389, 'use_tls' => true ) );
try {
	// Initialize a handle only. No bind or directory request can open a socket.
	$connection->initialize();
	\check( 'ldaps://127.0.0.1:389' === $connection->getLdapConnection()->getHost(), 'The production adapter retains an explicit LDAPS port of 389.' );
} finally {
	$connection->disconnect();
}

$client = sll_ldap_setup();
$GLOBALS['sll_test_ldap']['tls'] = false;
$result = $client->authenticate( 'alice', 'secret' );
\check( \is_wp_error( $result ) && 'sll_tls' === $result->get_error_code() && array() === $GLOBALS['sll_test_ldap']['binds'], 'A failed TLS request never sends a password.' );
\check( 1 === $GLOBALS['sll_test_ldap']['closed'], 'A failed TLS request closes the connection.' );
\check( in_array( array( LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND ), $GLOBALS['sll_test_ldap']['options'], true ), 'TLS requires a valid server certificate.' );

$client = sll_ldap_setup();
$GLOBALS['sll_test_ldap']['certificate'] = false;
\check( \is_wp_error( $client->authenticate( 'alice', 'secret' ) ) && array() === $GLOBALS['sll_test_ldap']['uris'], 'A certificate policy failure stops the connection.' );

$client = sll_ldap_setup( array( 'transport' => 'plain', 'allow_plaintext' => false ) );
\check( \is_wp_error( $client->authenticate( 'alice', 'secret' ) ) && array() === $GLOBALS['sll_test_ldap']['uris'], 'An unapproved plain connection is blocked.' );

$client = sll_ldap_setup();
$GLOBALS['sll_test_ldap']['responses'][] = sll_entries( array( 'dn' => 'uid=a\\2cb\\2b\\5cx,dc=example,dc=com' ) );
$result = $client->authenticate( 'a,b+\\x', "p\\a'ss" );
\check( is_array( $result ) && array( 'uid=a\\2cb\\2b\\5cx,dc=example,dc=com', "p\\a'ss" ) === $GLOBALS['sll_test_ldap']['binds'][0], 'OpenLDAP escapes a login DN and retains exact password bytes.' );
\check( array( 'ldap://ldap.example.com:389' ) === $GLOBALS['sll_test_ldap']['uris'], 'The library receives a valid LDAP server address.' );
\check( $GLOBALS['sll_test_ldap']['searches'][0]['read'] && 2 === $GLOBALS['sll_test_ldap']['searches'][0]['size_limit'] && in_array( array( LDAP_OPT_TIMELIMIT, 5 ), $GLOBALS['sll_test_ldap']['options'], true ), 'Account reads have result and time limits.' );
\check( ! str_contains( strtolower( $GLOBALS['sll_test_ldap']['searches'][0]['filter'] ), 'pwdaccountlockedtime' ), 'Generic OpenLDAP lookup does not require a password policy schema.' );
\check( 1 === $GLOBALS['sll_test_ldap']['closed'], 'A successful LDAP request closes the connection.' );

$client = sll_ldap_setup( array( 'sso_enabled' => true, 'user_meta_data' => array( array( 'telephoneNumber', 'phone', 'string' ) ) ), false );
$GLOBALS['sll_test_ldap']['responses'][] = sll_entries( array( 'dn' => 'uid=alice,dc=example,dc=com' ) );
$result = $client->authenticate( 'alice', 'secret' );
\check( is_array( $result ) && ! method_exists( $client, 'authenticate_sso' ) && ! in_array( '*', $GLOBALS['sll_test_ldap']['searches'][0]['attributes'], true ) && ! in_array( 'telephonenumber', array_map( 'strtolower', $GLOBALS['sll_test_ldap']['searches'][0]['attributes'] ), true ), 'The free LDAP client has no SSO method and does not request custom metadata attributes.' );

$client = sll_ldap_setup( array( 'user_meta_data' => array( array( 'telephoneNumber', 'phone', 'string' ) ) ) );
$GLOBALS['sll_test_ldap']['responses'][] = sll_entries( array( 'dn' => 'uid=alice,dc=example,dc=com' ) );
$client->authenticate( 'alice', 'secret' );
\check( in_array( 'telephonenumber', array_map( 'strtolower', $GLOBALS['sll_test_ldap']['searches'][0]['attributes'] ), true ), 'The full package requests its configured metadata attributes.' );

$client = sll_ldap_setup( array( 'search_sub_ous' => true ) );
$entry = array( 'dn' => 'uid=resolved,ou=staff,dc=example,dc=com' );
$GLOBALS['sll_test_ldap']['responses'] = array( sll_entries( $entry ), sll_entries( $entry ) );
$result = $client->authenticate( 'a*)(uid=*)', 'secret' );
\check( is_array( $result ) && sll_filter_equals( $GLOBALS['sll_test_ldap']['searches'][0]['filter'], 'uid', 'a*)(uid=*)' ), 'An LDAP user search escapes filter control characters.' );
\check( array( $entry['dn'], 'secret' ) === $GLOBALS['sll_test_ldap']['binds'][1], 'An OpenLDAP sub-OU login binds to the resolved DN.' );

$client = sll_ldap_setup( array( 'groups' => array( 'staff*()' ) ) );
$entry = array( 'dn' => 'uid=a*(x),dc=example,dc=com' );
$GLOBALS['sll_test_ldap']['responses'] = array( sll_entries( $entry ), sll_entries( array( 'dn' => 'cn=staff,dc=example,dc=com' ) ) );
$result = $client->authenticate( 'alice', 'secret' );
$group_filter = $GLOBALS['sll_test_ldap']['searches'][1]['filter'];
\check( is_array( $result ) && sll_filter_equals( $group_filter, 'cn', 'staff*()' ) && sll_filter_equals( $group_filter, 'member', $entry['dn'] ), 'OpenLDAP group checks escape group names and member DNs.' );

$client = sll_ldap_setup( array( 'groups' => array( 'staff' ) ) );
$GLOBALS['sll_test_ldap']['responses'] = array( sll_entries( array( 'dn' => 'uid=alice,dc=example,dc=com' ) ), array( 'count' => 0 ) );
$result = $client->authenticate( 'alice', 'secret' );
\check( \is_wp_error( $result ) && 'sll_groups' === $result->get_error_code(), 'A directory user outside the required group is denied.' );

$client = sll_ldap_setup();
$result = $client->authenticate_sso( 'alice' );
\check( \is_wp_error( $result ) && 'sll_service_account' === $result->get_error_code() && array() === $GLOBALS['sll_test_ldap']['binds'], 'Server SSO requires a service account.' );
$client = sll_ldap_setup( array( 'sso_search_user' => 'cn=reader,dc=example,dc=com' ) );
$result = $client->authenticate_sso( 'alice' );
\check( \is_wp_error( $result ) && array() === $GLOBALS['sll_test_ldap']['binds'], 'Server SSO never uses an empty service account password.' );

$client = sll_ldap_setup( array( 'directory' => 'ad', 'login_domain' => 'EXAMPLE', 'sso_search_user' => 'reader@example.com', 'sso_search_user_password' => Secrets::encrypt( 'service-secret' ) ) );
$result = $client->authenticate_sso( 'OTHER\\alice' );
\check( \is_wp_error( $result ) && 'sll_sso_domain' === $result->get_error_code() && array() === $GLOBALS['sll_test_ldap']['uris'], 'Server SSO rejects another domain before a lookup.' );
$GLOBALS['sll_test_ldap']['responses'][] = sll_entries( array( 'dn' => 'cn=alice,dc=example,dc=com' ) );
$result = $client->authenticate_sso( 'EXAMPLE\\alice' );
\check( is_array( $result ) && array( array( 'reader@example.com', 'service-secret' ) ) === $GLOBALS['sll_test_ldap']['binds'], 'Server SSO binds only with the service account credentials.' );
\check( sll_filter_equals( $GLOBALS['sll_test_ldap']['searches'][0]['filter'], 'sAMAccountName', 'alice' ) && false !== stripos( $GLOBALS['sll_test_ldap']['searches'][0]['filter'], '(!(userAccountControl:1.2.840.113556.1.4.803:=2))' ), 'Server SSO uses the permitted domain name and excludes disabled AD accounts.' );

$client = sll_ldap_setup( array( 'sso_search_user' => 'cn=reader,dc=example,dc=com', 'sso_search_user_password' => Secrets::encrypt( 'service-secret' ) ) );
$GLOBALS['sll_test_ldap']['responses'][] = array( 'count' => 2, 0 => array( 'dn' => 'uid=one,dc=example,dc=com' ), 1 => array( 'dn' => 'uid=two,dc=example,dc=com' ) );
\check( \is_wp_error( $client->authenticate_sso( 'alice' ) ), 'Server SSO rejects an ambiguous directory identity.' );

$client = sll_ldap_setup( array( 'groups' => array( 'staff' ), 'sso_search_user' => 'cn=reader,dc=example,dc=com', 'sso_search_user_password' => Secrets::encrypt( 'service-secret' ) ) );
$GLOBALS['sll_test_ldap']['responses'] = array( sll_entries( array( 'dn' => 'uid=alice,dc=example,dc=com' ) ), array( 'count' => 0 ) );
$result = $client->authenticate_sso( 'alice' );
\check( \is_wp_error( $result ) && 'sll_groups' === $result->get_error_code(), 'Server SSO denies an identity outside the required group.' );

$client = sll_ldap_setup( array( 'directory' => 'ad' ) );
$GLOBALS['sll_test_ldap']['responses'][] = array( 'count' => 2, 0 => array( 'dn' => 'cn=one,dc=example,dc=com' ), 1 => array( 'dn' => 'cn=two,dc=example,dc=com' ) );
\check( \is_wp_error( $client->authenticate( 'alice', 'secret' ) ), 'An ambiguous directory search cannot select an account.' );

$client = sll_ldap_setup( array( 'directory' => 'ad' ) );
$entry = array( 'dn' => 'cn=alice,dc=example,dc=com' );
$GLOBALS['sll_test_ldap']['responses'][] = sll_entries( $entry );
$GLOBALS['sll_test_ldap']['bind_results'] = array( true, false );
$result = $client->authenticate( 'TRUSTED\\alice', 'trusted-password' );
\check( \is_wp_error( $result ) && 'sll_credentials' === $result->get_error_code() && array( $entry['dn'], 'trusted-password' ) === $GLOBALS['sll_test_ldap']['binds'][1], 'An AD password must also bind to the exact account used by WordPress.' );

$client = sll_ldap_setup( array( 'directory' => 'ad', 'groups' => array( 'staff' ) ) );
$entry = array( 'dn' => 'cn=alice,dc=example,dc=com' );
$child = array( 'dn' => 'cn=child,dc=example,dc=com', 'cn' => array( 'child' ), 'objectclass' => array( 'top', 'group' ) );
$staff = array( 'dn' => 'cn=staff,dc=example,dc=com', 'cn' => array( 'staff' ), 'objectclass' => array( 'top', 'group' ) );
$GLOBALS['sll_test_ldap']['responses'] = array( sll_entries( $entry ), sll_entries( $child ), sll_entries( $staff ), array() );
$result = $client->authenticate( 'alice', 'secret' );
\check( is_array( $result ) && sll_filter_equals( $GLOBALS['sll_test_ldap']['searches'][2]['filter'], 'member', $child['dn'] ), 'AD access checks include nested groups.' );

$client = sll_ldap_setup( array( 'directory' => 'ad', 'groups' => array( 'absent', 'staff' ) ) );
$GLOBALS['sll_test_ldap']['responses'] = array( sll_entries( $entry ), sll_entries( $staff ), array() );
\check( is_array( $client->authenticate( 'alice', 'secret' ) ), 'AD permits a user in any one of the configured groups.' );

$client = sll_ldap_setup( array( 'directory' => 'ad', 'groups' => array( 'staff' ), 'group_base_dn' => 'ou=allowed,dc=example,dc=com' ) );
$GLOBALS['sll_test_ldap']['responses'] = array( sll_entries( $entry ), sll_entries( $staff ), array() );
$result = $client->authenticate( 'alice', 'secret' );
\check( \is_wp_error( $result ) && 'sll_groups' === $result->get_error_code(), 'AD denies a matching group outside the configured group base.' );

$entry['objectsid'] = array( pack( 'C*', 1, 2, 0, 0, 0, 0, 0, 5, 32, 0, 0, 0, 10, 0, 0, 0 ) );
$entry['primarygroupid'] = array( '513' );
$primary = array( 'dn' => 'cn=Domain Users,dc=example,dc=com', 'cn' => array( 'Domain Users' ), 'objectclass' => array( 'top', 'group' ) );
foreach ( array( 'Domain Users', 'staff' ) as $allowed_group ) {
	$client = sll_ldap_setup( array( 'directory' => 'ad', 'groups' => array( $allowed_group ) ) );
	$GLOBALS['sll_test_ldap']['responses'] = array( sll_entries( $entry ), array(), sll_entries( $primary ), sll_entries( $primary ), 'staff' === $allowed_group ? sll_entries( $staff ) : array(), array() );
	$result = $client->authenticate( 'alice', 'secret' );
	$filters = array_column( $GLOBALS['sll_test_ldap']['searches'], 'filter' );
	if ( 'Domain Users' === $allowed_group ) {
		\check( is_array( $result ) && array_filter( $filters, fn( $filter ) => sll_filter_equals( $filter, 'objectsid', 'S-1-5-32-513' ) ), 'AD primary group lookup uses the account SID and primary group ID.' );
	} else {
		\check( is_array( $result ) && array_filter( $filters, fn( $filter ) => sll_filter_equals( $filter, 'member', $primary['dn'] ) ), 'AD access checks include parent groups of the primary group without direct memberships.' );
	}
}

echo "LDAP connection checks passed.\n";
