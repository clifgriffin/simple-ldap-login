<?php
/** Native PHP dependency failures must leave settings and recovery controls usable. */
use SimpleLdapLogin\Admin;
use SimpleLdapLogin\Ldap_Client;
use SimpleLdapLogin\Secrets;
use SimpleLdapLogin\Settings;

$check = static function ( bool $passed, string $label ): void {
	if ( ! $passed ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	echo 'PASS: ' . $label . "\n";
};
$settings = new Settings();
$client   = new Ldap_Client( $settings );
$before   = $settings->all();
if ( 'ldap' === ( $args[0] ?? '' ) ) {
	$check( ! extension_loaded( 'ldap' ), 'This request has no PHP LDAP extension.' );
	foreach ( array( $client->authenticate( 'alice', 'Test-Password-42!' ), $client->test_connection() ) as $result ) {
		$check( is_wp_error( $result ) && 'sll_ldap_missing' === $result->get_error_code(), 'Missing LDAP returns a controlled error.' );
	}
} else {
	$check( ! function_exists( 'openssl_encrypt' ) && ! function_exists( 'openssl_decrypt' ), 'This request has no OpenSSL encryption functions.' );
	$check( is_wp_error( Secrets::encrypt( 'test-value' ) ) && is_wp_error( Secrets::decrypt( $before['sso_search_user_password'] ) ), 'Missing OpenSSL cannot read or write the saved secret.' );
	$check( is_wp_error( $settings->save( array( 'sso_search_user_password' => 'new-test-value' ) ) ), 'A failed encryption request does not save a replacement secret.' );
}
$admin = get_user_by( 'login', 'testadmin' );
wp_set_current_user( $admin->ID );
$_GET['tab'] = 'connection';
ob_start();
( new Admin( $settings, $client ) )->render();
$html = ob_get_clean();
$check( false !== strpos( $html, 'sll_settings[enabled]' ), 'The administrator can still open the controls to disable LDAP.' );
$check( false === strpos( $html, $before['sso_search_user_password'] ) && false === strpos( $html, 'Reader-Password-42!' ), 'The settings screen does not show the stored secret.' );
$check( $before === $settings->all(), 'A dependency failure leaves the saved configuration unchanged.' );
