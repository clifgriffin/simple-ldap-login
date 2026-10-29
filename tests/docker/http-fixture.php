<?php
/** Request fixtures for the Docker probe only. This file is not product code. */
if ( ! defined( 'SLL_HTTP_TEST_CASE' ) ) {
	return;
}

if ( 'proof-whitespace' === SLL_HTTP_TEST_CASE ) {
	add_filter(
		'random_password',
		static function ( string $password, int $length ): string {
			return 64 === $length ? ' ' . str_repeat( 'p', 62 ) . ' ' : $password;
		},
		10,
		2
	);
}

add_filter(
	'sll_get_setting',
	static function ( $value, string $key ) {
		$changes = array(
			'sso-off'         => array( 'sso_enabled' => false ),
			'password-off'    => array( 'enabled' => false, 'sso_enabled' => true ),
			'group-denied'    => array( 'groups' => array( 'denied' ) ),
			'service-missing' => array( 'sso_search_user' => '' ),
		);
		if ( 'service-invalid' === SLL_HTTP_TEST_CASE && 'sso_search_user_password' === $key ) {
			return SimpleLdapLogin\Secrets::encrypt( 'wrong-service-password' );
		}
		return $changes[ SLL_HTTP_TEST_CASE ][ $key ] ?? $value;
	},
	10,
	2
);

add_action(
	'init',
	static function () {
		$identities = array( 'unknown' => 'not-a-directory-user', 'disabled' => 'disabled', 'other-domain' => 'OTHER\\alice' );
		if ( isset( $identities[ SLL_HTTP_TEST_CASE ] ) && 'alice' === ( $_SERVER['REMOTE_USER'] ?? '' ) ) {
			$_SERVER['REMOTE_USER'] = wp_slash( $identities[ SLL_HTTP_TEST_CASE ] );
		}
		if ( 'login-page' === SLL_HTTP_TEST_CASE ) {
			$GLOBALS['pagenow'] = 'wp-login.php';
		}
	},
	1
);

if ( 'user-denied' === SLL_HTTP_TEST_CASE ) {
	add_filter( 'wp_authenticate_user', static function () { return new WP_Error( 'sll_http_user_denied', 'Test user check denied access.' ); }, 100 );
}
if ( 'later-denied' === SLL_HTTP_TEST_CASE ) {
	add_filter( 'authenticate', static function () { return new WP_Error( 'sll_http_later_denied', 'Test login filter denied access.' ); }, 100 );
}
