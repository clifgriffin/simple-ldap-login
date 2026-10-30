<?php
/** Check the live demo with: wp eval-file /path/to/demo/check.php */
defined( 'WP_CLI' ) && WP_CLI || exit;

$check = static function ( bool $passed, string $label ): void {
	if ( ! $passed ) {
		WP_CLI::error( $label );
	}
	WP_CLI::success( $label );
};
$client = new SimpleLdapLogin\Ldap_Client( new SimpleLdapLogin\Settings() );
$check( true === $client->test_connection(), 'The directory search account connects with TLS.' );
$check( is_array( $client->authenticate( 'alice', 'Test-Password-42!' ) ), 'LDAP accepts Alice.' );
$result = $client->authenticate( 'alice', 'wrong-password' );
$check( is_wp_error( $result ) && 'sll_credentials' === $result->get_error_code(), 'LDAP rejects an incorrect password.' );
$result = wp_authenticate( 'bob', 'Test-Password-42!' );
$check( is_wp_error( $result ) && 'sll_groups' === $result->get_error_code(), 'WordPress rejects Bob because he is not in staff.' );
$result = wp_authenticate( 'alice', 'Test-Password-42!' );
$check( $result instanceof WP_User && in_array( 'subscriber', $result->roles, true ), 'WordPress accepts Alice as a subscriber.' );
$check( 'alice@example.test' === $result->user_email && 'Alice' === $result->first_name && 'Example' === $result->last_name, 'Alice has the LDAP profile values.' );
$result = wp_authenticate( 'alice', 'wrong-password' );
$check( is_wp_error( $result ), 'WordPress rejects an incorrect password after account creation.' );
$result = wp_authenticate( 'testadmin', 'Test-Admin-42!' );
$check( $result instanceof WP_User && user_can( $result, 'manage_options' ), 'The local administrator can log in.' );
