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
			'recovery-off'    => array( 'recovery_mode' => 'off' ),
			'sso-off'         => array( 'sso_enabled' => false ),
			'password-off'    => array( 'enabled' => false, 'sso_enabled' => true ),
			'group-denied'    => array( 'groups' => array( 'denied' ) ),
			'service-missing' => array( 'sso_search_user' => '' ),
		);
		if ( in_array( SLL_HTTP_TEST_CASE, array( 'recovery-selected', 'recovery-upn', 'recovery-domain', 'recovery-demoted' ), true ) ) {
			if ( 'recovery_mode' === $key ) {
				return 'selected';
			}
			if ( 'recovery_user_ids' === $key ) {
				$user = get_user_by( 'login', 'alice' );
				return $user ? array( $user->ID ) : array();
			}
		}
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
		$identities = array( 'unknown' => 'not-a-directory-user', 'disabled' => 'disabled', 'other-domain' => 'OTHER\\alice', 'recovery-upn' => 'alice@example.test', 'recovery-domain' => 'EXAMPLE\\alice' );
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

add_action( 'sll_account_loaded', static function () { $GLOBALS['sll_http_account_loaded'] = ( $GLOBALS['sll_http_account_loaded'] ?? 0 ) + 1; }, 100 );
if ( in_array( SLL_HTTP_TEST_CASE, array( 'recovery-selected', 'recovery-upn', 'recovery-domain' ), true ) ) {
	// Keep this fixture within one request; do not change real account roles or options.
	add_filter(
		'user_has_cap',
		static function ( array $capabilities, array $requested, array $arguments, WP_User $user ): array {
			if ( 'alice' === $user->user_login ) {
				$capabilities['manage_options'] = true;
			}
			return $capabilities;
		},
		10,
		4
	);
	add_filter( 'pre_site_option_site_admins', static function () { return array( 'alice' ); } );
}
