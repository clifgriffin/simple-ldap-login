<?php
/** Run the packaged license client in WordPress without a paid service account. */
use SimpleLdapLogin\License;
use SimpleLdapLogin\Pro\Ldap_Client;
use SimpleLdapLogin\Settings;
$settings = new Settings();
$license = new License( $settings );
$mode = 'active';
$requests = 0;
$checks = 0;
$check = static function ( bool $passed, string $label ) use ( &$checks ): void {
	if ( ! $passed ) { throw new RuntimeException( $label ); }
	++$checks;
};
$http = static function ( $pre, array $args, string $url ) use ( &$mode, &$requests ) {
	if ( ! str_starts_with( $url, 'https://api.paidcommunities.com/wordpress/v1/' ) ) { return $pre; }
	++$requests;
	if ( 'timeout' === $mode ) { return new WP_Error( 'http_request_failed', 'Test timeout.' ); }
	if ( str_ends_with( $url, '/domains' ) ) {
		$body = array( 'id' => 'dom_dockertest', 'domain' => wp_parse_url( home_url(), PHP_URL_HOST ), 'secret' => 'docker-license-secret' );
	} else {
		if ( 'Bearer docker-license-secret' !== ( $args['headers']['Authorization'] ?? '' ) ) { throw new RuntimeException( 'The private SDK did not send its activation secret.' ); }
		$body = array( 'license' => array( 'status' => $mode ) );
	}
	return array( 'headers' => array(), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => wp_json_encode( $body ), 'cookies' => array() );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
	$check( true === $license->activate( 'docker-test-license' ) && $license->is_active(), 'The packaged SDK activates through the WordPress HTTP API.' );
	if ( 'setup' === ( $args[0] ?? '' ) ) { echo "Docker test license activated.\n"; return; }
	$user = get_user_by( 'login', 'alice' );
	$before = $settings->all();
	$fields = get_user_meta( $user->ID, 'phone_wp', true );
	$mode = 'expired';
	$check( true === $license->refresh() && ! $license->is_active(), 'A confirmed expiry turns off Pro in the installed package.' );
	$client = 'pro' === SLL_EDITION ? new Ldap_Client( $settings ) : new SimpleLdapLogin\Ldap_Client( $settings );
	if ( 'pro' === SLL_EDITION ) {
		$check( 'sll_license_required' === $client->authenticate_sso( 'alice' )->get_error_code(), 'Expired licenses cannot use the public SSO directory method.' );
	}
	$result = wp_authenticate( 'alice', 'Test-Password-42!' );
	$check( $result instanceof WP_User && $result->ID === $user->ID, 'LDAP password login works after license expiry.' );
	$check( $fields === get_user_meta( $user->ID, 'phone_wp', true ), 'LDAP login keeps the saved custom user field after expiry.' );
	$check( wp_authenticate( 'testadmin', 'Test-Admin-42!' ) instanceof WP_User, 'Local administrator recovery works after expiry.' );
	$before_requests = $requests;
	$license->is_active();
	if ( 'pro' === SLL_EDITION ) { $client->authenticate_sso( 'alice' ); }
	wp_authenticate( 'alice', 'Test-Password-42!' );
	$check( $requests === $before_requests, 'Password and SSO login paths do not call the license server.' );
	$mode = 'timeout';
	$check( is_wp_error( $license->refresh() ) && ! $license->is_active(), 'A service outage does not unlock an expired license.' );
	$mode = 'active';
	$check( true === $license->refresh() && $license->is_active(), 'A verified renewal enables Pro again.' );
	$mode = 'timeout';
	$check( is_wp_error( $license->refresh() ) && $license->is_active(), 'A service outage keeps the last verified active status.' );
	$mode = 'active';
	$license->refresh();
	$check( $before === $settings->all(), 'Expiry and renewal do not change LDAP settings.' );
	echo 'Packaged license checks passed: ' . $checks . ".\n";
} finally {
	remove_filter( 'pre_http_request', $http, 10 );
}
