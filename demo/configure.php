<?php
/** Set the demo defaults with: wp eval-file /path/to/demo/configure.php */
defined( 'WP_CLI' ) && WP_CLI || exit;

$recovery = get_user_by( 'login', 'testadmin' );
if ( ! $recovery instanceof WP_User ) {
	WP_CLI::error( 'Create the testadmin account before configuring the demo.' );
}

$result = ( new SimpleLdapLogin\Settings() )->save(
	array(
		'enabled'                  => true,
		'directory'                => 'ol',
		'domain_controllers'       => array( '127.0.0.1' ),
		'ldap_port'                => 1389,
		'base_dn'                  => 'dc=example,dc=test',
		'transport'                => 'starttls',
		'allow_plaintext'          => false,
		'search_sub_ous'           => true,
		'sso_search_user'          => 'cn=reader,dc=example,dc=test',
		'sso_search_user_password' => 'Reader-Password-42!',
		'group_base_dn'            => 'ou=groups,dc=example,dc=test',
		'groups'                   => array( 'staff' ),
		'ol_login'                 => 'uid',
		'ol_group'                 => 'cn',
		'group_uid'                => 'memberUid',
		'create_users'             => true,
		'high_security'            => true,
		'recovery_mode'            => 'selected',
		'recovery_user_ids'        => array( $recovery->ID ),
		'role'                     => 'subscriber',
		'sso_enabled'              => false,
	)
);
if ( is_wp_error( $result ) ) {
	WP_CLI::error( $result->get_error_message() );
}
update_option( 'sll_demo_configured', 1 );
WP_CLI::success( 'Demo LDAP settings saved.' );
