<?php
/** Run native SDK activation and update checks in a real WordPress install. */
use SimpleLdapLogin\License;
use SimpleLdapLogin\Pro\Ldap_Client;
use SimpleLdapLogin\Settings;
$settings = new Settings();
$license = new License( $settings );
$paid = 'free' !== SLL_EDITION;
$mode = 'active';
$requests = $checks = 0;
$check = static function ( bool $passed, string $label ) use ( &$checks ): void {
	if ( ! $passed ) { throw new RuntimeException( $label ); }
	++$checks;
};
$http = static function ( $pre, array $args, string $url ) use ( &$mode, &$requests ) {
	if ( ! str_starts_with( $url, 'https://api.paidcommunities.com/wordpress/v1/' ) ) { return $pre; }
	++$requests;
	if ( 'timeout' === $mode ) { return new WP_Error( 'http_request_failed', 'Test timeout.' ); }
	$code = 200;
	if ( str_ends_with( $url, '/domains' ) ) {
		$body = array( 'id' => 'dom_dockertest', 'domain' => wp_parse_url( home_url(), PHP_URL_HOST ), 'secret' => 'docker-license-secret', 'created_at' => gmdate( 'c' ) );
	} elseif ( 'DELETE' === $args['method'] ) {
		$body = array(); $code = 204;
	} else {
		if ( 'Bearer docker-license-secret' !== ( $args['headers']['Authorization'] ?? '' ) ) { throw new RuntimeException( 'The native SDK did not send its activation secret.' ); }
		$request = json_decode( $args['body'] ?? '{}', true );
		$body = array( 'slug' => $request['product_id'] ?? '', 'version' => SLL_VERSION, 'new_version' => SLL_VERSION, 'package' => '', 'last_check' => gmdate( 'c' ), 'code' => 'no_release', 'license' => array( 'status' => $mode, 'limit' => 1 ) );
	}
	$response = array( 'headers' => array(), 'response' => array( 'code' => $code, 'message' => 'OK' ), 'body' => wp_json_encode( $body ), 'cookies' => array() );
	// pre_http_request skips the transport response hook. Apply it to this fixture.
	return apply_filters( 'http_response', $response, $args, $url );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
	if ( $paid ) {
		// wp_send_json exits through wp_die during AJAX. Keep its JSON in this test.
		if ( ! defined( 'DOING_AJAX' ) ) { define( 'DOING_AJAX', true ); }
		if ( ! class_exists( 'SllSdkJsonStop', false ) ) { class SllSdkJsonStop extends Error {} }
		$stop = static fn() => static function () { throw new SllSdkJsonStop(); };
		add_filter( 'wp_die_ajax_handler', $stop );
		wp_set_current_user( get_user_by( 'login', 'testadmin' )->ID );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['SERVER_NAME'] = wp_parse_url( $settings->is_network() ? network_home_url() : home_url(), PHP_URL_HOST );
		$_POST = array( 'license_key' => 'docker-test-license-key' );
		$_REQUEST = array( 'nonce' => wp_create_nonce( plugin_basename( SLL_FILE ) . '-action' ) );
		ob_start();
		try { do_action( 'wp_ajax_activate_' . plugin_basename( SLL_FILE ) ); } catch ( SllSdkJsonStop $stop_request ) {}
		$result = json_decode( ob_get_clean(), true );
		remove_filter( 'wp_die_ajax_handler', $stop );
		wp_set_current_user( 0 );
		$check( ! empty( $result['success'] ) && $license->is_active() && 1 === $requests, 'The native SDK handler activates through one WordPress HTTP request.' );
	} else {
		// Free can retain a paid record, but it has no activation UI or SDK controller.
		$license->setDomainId( 'dom_dockertest' );
		$license->setSecret( 'docker-license-secret' );
		$license->setLicenseKey( '********test' );
		$license->setStatus( License::ACTIVE );
		$license->save();
		$check( $license->is_active() && 0 === $requests, 'Free can display a retained SDK license without contacting the service.' );
	}
	$check( ! wp_next_scheduled( License::CHECK ), 'The plugin does not add its own license schedule.' );
	if ( 'setup' === ( $args[0] ?? '' ) ) { echo "Docker test SDK license is ready.\n"; return; }
	$user = get_user_by( 'login', 'alice' );
	$before = $settings->all();
	$fields = get_user_meta( $user->ID, 'phone_wp', true );
	$mode = 'expired';
	if ( $paid ) {
		$check( true === $license->refresh() && ! $license->is_active(), 'The SDK update controller applies a confirmed expiry.' );
	} else {
		$license->setStatus( 'expired' ); $license->save();
	}
	$client = $paid ? new Ldap_Client( $settings ) : new SimpleLdapLogin\Ldap_Client( $settings );
	if ( $paid ) { $check( 'sll_license_required' === $client->authenticate_sso( 'alice' )->get_error_code(), 'Expired licenses cannot use SSO.' ); }
	$result = wp_authenticate( 'alice', 'Test-Password-42!' );
	$check( $result instanceof WP_User && $result->ID === $user->ID, 'LDAP password login works after SDK license expiry.' );
	$check( $fields === get_user_meta( $user->ID, 'phone_wp', true ), 'Expiry keeps custom user fields.' );
	$check( wp_authenticate( 'testadmin', 'Test-Admin-42!' ) instanceof WP_User, 'Local recovery works after expiry.' );
	$before_requests = $requests;
	$license->is_active();
	if ( $paid ) { $client->authenticate_sso( 'alice' ); }
	wp_authenticate( 'alice', 'Test-Password-42!' );
	$check( $requests === $before_requests, 'Login and feature checks never call the license service.' );
	if ( $paid ) {
		$mode = 'timeout';
		$check( is_wp_error( $license->refresh() ) && ! $license->is_active(), 'A service outage cannot renew an expired SDK license.' );
		$mode = 'active';
		$check( true === $license->refresh() && $license->is_active(), 'The SDK restores a verified renewal.' );
		$mode = 'timeout';
		$check( is_wp_error( $license->refresh() ) && $license->is_active(), 'SDK request failure keeps the last active status.' );
		$mode = 'active'; $license->refresh();
	}
	$check( $before === $settings->all(), 'SDK license checks do not change LDAP settings.' );
	echo 'Native SDK WordPress checks passed: ' . $checks . ".\n";
} finally {
	remove_filter( 'pre_http_request', $http, 10 );
}
