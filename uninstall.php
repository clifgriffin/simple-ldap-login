<?php
/**
 * Remove settings only when an administrator selected data removal.
 *
 * @package SimpleLdapLogin
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$simpleldaplogin_keys        = array(
	'sll_settings',
	'sll_settings_backup_v1',
	'sll_legacy_access',
	'simpleldap_account_suffix',
	'simpleldap_base_dn',
	'simpleldap_domain_controllers',
	'simpleldap_directory_type',
	'simpleldap_group',
	'simpleldap_account_type',
	'simpleldap_ol_login',
	'simpleldap_use_tls',
	'simpleldap_login_mode',
	'simpleldap_security_mode',
);
$simpleldaplogin_remove_site = static function () use ( $simpleldaplogin_keys ): void {
	$settings = get_option( 'sll_settings', array() );
	if ( is_array( $settings ) && true === ( $settings['delete_on_uninstall'] ?? false ) ) {
		foreach ( $simpleldaplogin_keys as $key ) {
			delete_option( $key );
		}
	}
};

if ( is_multisite() ) {
	foreach ( get_networks(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $simpleldaplogin_network_id ) {
		$simpleldaplogin_settings = get_network_option( $simpleldaplogin_network_id, 'sll_settings', array() );
		if ( is_array( $simpleldaplogin_settings ) && true === ( $simpleldaplogin_settings['delete_on_uninstall'] ?? false ) ) {
			foreach ( $simpleldaplogin_keys as $simpleldaplogin_key ) {
				delete_network_option( $simpleldaplogin_network_id, $simpleldaplogin_key );
			}
		}
	}
	$simpleldaplogin_offset = 0;
	do {
		$simpleldaplogin_sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $simpleldaplogin_offset,
			)
		);
		foreach ( $simpleldaplogin_sites as $simpleldaplogin_blog_id ) {
			switch_to_blog( $simpleldaplogin_blog_id );
			$simpleldaplogin_remove_site();
			restore_current_blog();
		}
		$simpleldaplogin_count   = count( $simpleldaplogin_sites );
		$simpleldaplogin_offset += 100;
	} while ( 100 === $simpleldaplogin_count );
} else {
	$simpleldaplogin_remove_site();
}
