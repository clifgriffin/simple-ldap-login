<?php
/**
 * Remove settings only when an administrator selected data removal.
 *
 * @package SimpleLdapLogin
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$sll_keys        = array(
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
$sll_remove_site = static function () use ( $sll_keys ): void {
	$settings = get_option( 'sll_settings', array() );
	if ( is_array( $settings ) && true === ( $settings['delete_on_uninstall'] ?? false ) ) {
		foreach ( $sll_keys as $key ) {
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
	) as $sll_network_id ) {
		$sll_settings = get_network_option( $sll_network_id, 'sll_settings', array() );
		if ( is_array( $sll_settings ) && true === ( $sll_settings['delete_on_uninstall'] ?? false ) ) {
			foreach ( $sll_keys as $sll_key ) {
				delete_network_option( $sll_network_id, $sll_key );
			}
		}
	}
	$sll_offset = 0;
	do {
		$sll_sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $sll_offset,
			)
		);
		foreach ( $sll_sites as $sll_blog_id ) {
			switch_to_blog( $sll_blog_id );
			$sll_remove_site();
			restore_current_blog();
		}
		$sll_count   = count( $sll_sites );
		$sll_offset += 100;
	} while ( 100 === $sll_count );
} else {
	$sll_remove_site();
}
