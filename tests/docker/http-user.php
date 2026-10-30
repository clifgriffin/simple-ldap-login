<?php
/** Test probe. Copy only into the Docker test site, never into a release ZIP. */
$scenario = $_GET['sll_case'] ?? 'normal';
$allowed  = array( 'normal', 'proof-whitespace', 'sso-off', 'password-off', 'group-denied', 'service-missing', 'service-invalid', 'unknown', 'disabled', 'other-domain', 'user-denied', 'later-denied', 'login-page', 'ajax', 'cron', 'xmlrpc', 'recovery', 'recovery-selected', 'recovery-upn', 'recovery-domain', 'recovery-off', 'recovery-demoted' );
if ( ! is_string( $scenario ) || ! in_array( $scenario, $allowed, true ) ) {
	http_response_code( 400 );
	exit;
}
define( 'SLL_HTTP_TEST_CASE', $scenario );
$flags = array( 'ajax' => 'DOING_AJAX', 'cron' => 'DOING_CRON', 'xmlrpc' => 'XMLRPC_REQUEST', 'recovery' => 'SLL_DISABLE_LDAP' );
if ( isset( $flags[ $scenario ] ) ) {
	define( $flags[ $scenario ], true );
}
require __DIR__ . '/wp-load.php';
$has_hook = static function ( string $class ): bool {
	foreach ( $GLOBALS['wp_filter']['authenticate']->callbacks ?? array() as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = $callback['function'];
			if ( is_array( $function ) && is_object( $function[0] ) && $class === get_class( $function[0] ) && 'authenticate' === $function[1] ) {
				return true;
			}
		}
	}
	return false;
};
header( 'Content-Type: application/json' );
echo wp_json_encode(
	array(
		'user'          => (string) wp_get_current_user()->user_login,
		'edition'       => SLL_EDITION,
		'scenario'      => $scenario,
		'password_hook' => $has_hook( SimpleLdapLogin\Authenticator::class ),
		'sso_hook'      => $has_hook( 'SimpleLdapLogin\\Pro\\Server_Sso' ),
		'account_loaded' => (int) ( $GLOBALS['sll_http_account_loaded'] ?? 0 ),
	)
);
