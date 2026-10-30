<?php
/** Check old settings against real WordPress storage, then restore the test site. */

use SimpleLdapLogin\Secrets;
use SimpleLdapLogin\Settings;

$checks = 0;
$expected_network = ( new Settings() )->is_network();
$check  = static function ( bool $passed, string $label ) use ( &$checks ): void {
	if ( ! $passed ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$checks;
	echo 'PASS: ' . $label . "\n";
};
$keys = array(
	'sll_settings', 'sll_settings_backup_v1', 'sll_legacy_access',
	'simpleldap_account_suffix', 'simpleldap_base_dn', 'simpleldap_domain_controllers',
	'simpleldap_group', 'simpleldap_account_type', 'simpleldap_ol_login',
	'simpleldap_use_tls', 'simpleldap_directory_type', 'simpleldap_login_mode',
	'simpleldap_security_mode',
);
$scopes = is_multisite() ? array( false, true ) : array( false );
$read   = static function ( bool $network, string $key, $default = false ) {
	return $network ? get_site_option( $key, $default ) : get_option( $key, $default );
};
$write = static function ( bool $network, string $key, $value ) use ( $read ): void {
	$missing = new stdClass();
	if ( $missing === $read( $network, $key, $missing ) ) {
		$network ? add_site_option( $key, $value ) : add_option( $key, $value, '', false );
		return;
	}
	if ( $network ) {
		update_site_option( $key, $value );
	} else {
		update_option( $key, $value, false );
	}
};
$remove = static function ( bool $network, string $key ): void {
	if ( $network ) {
		delete_site_option( $key );
	} else {
		delete_option( $key );
	}
};
$snapshot = array();
foreach ( $scopes as $network ) {
	foreach ( $keys as $key ) {
		$missing = new stdClass();
		$value   = $read( $network, $key, $missing );
		$snapshot[ (int) $network ][ $key ] = array( $value !== $missing, $value );
	}
}
$reset = static function ( bool $network ) use ( $keys, $remove ): void {
	foreach ( $keys as $key ) {
		$remove( $network, $key );
	}
};
$child_id = 0;

try {
	foreach ( $scopes as $network ) {
		$scope = $network ? 'network' : 'site';
		$reset( $network );
		$settings = new Settings( $network );
		$settings->migrate();
		$first = $read( $network, 'sll_settings' );
		$check( is_array( $first ) && 3 === $first['schema_version'] && false === $first['enabled'] && 'starttls' === $first['transport'], $scope . ': a fresh site keeps LDAP off and requires TLS.' );
		$check( 'selected' === $settings->recovery_mode() && array() === $settings->get( 'recovery_user_ids' ), $scope . ': fresh settings require selected recovery accounts.' );
		$settings->migrate();
		$check( $first === $read( $network, 'sll_settings' ), $scope . ': a second startup keeps fresh settings unchanged.' );

		$reset( $network );
		$secret = "Migration-'\\-Password";
		$iv     = str_repeat( 'a', 16 );
		$cipher = openssl_encrypt( $secret, 'AES-256-CBC', gethostname(), 0, $iv ) . ';' . bin2hex( $iv );
		$old    = array(
			'version'                   => '128',
			'enabled'                   => false,
			'directory'                 => 'ol',
			'base_dn'                   => 'dc=example,dc=test',
			'domain_controllers'        => "ldap-one.test\nldap-two.test",
			'groups'                    => 'staff;editors',
			'role'                      => 'subscriber',
			'create_users'              => true,
			'use_tls'                   => false,
			'sso_enabled'               => true,
			'sso_search_user_password'   => $cipher,
			'user_meta_data'            => array( array( 'telephoneNumber', 'phone', 'string' ) ),
			'meta_data_suffix_ldap'      => 'directory',
			'meta_data_suffix_wp'        => 'local',
			'extension_setting'          => array( 'keep' => true ),
		);
		$write( $network, 'sll_settings', $old );
		$settings->migrate();
		$current = $read( $network, 'sll_settings' );
		$check( 3 === $current['schema_version'] && '200' === $current['version'], $scope . ': old settings receive the current schema.' );
		$check( $old === $read( $network, 'sll_settings_backup_v1' ), $scope . ': the backup keeps the complete original option.' );
		$check( false === $current['enabled'] && true === $current['sso_enabled'] && true === $current['create_users'], $scope . ': migration retains explicit feature choices.' );
		$check( array( 'ldap-one.test', 'ldap-two.test' ) === $current['domain_controllers'] && array( 'staff', 'editors' ) === $current['groups'], $scope . ': old list formats retain all controllers and groups.' );
		$check( 'plain' === $current['transport'] && true === $current['allow_plaintext'], $scope . ': migration preserves the old transport policy.' );
		$check( $old['extension_setting'] === $current['extension_setting'] && $old['user_meta_data'] === $current['user_meta_data'] && 'directory' === $current['meta_data_suffix_ldap'] && 'local' === $current['meta_data_suffix_wp'], $scope . ': custom settings and metadata mappings are retained.' );
		$check( str_starts_with( $current['sso_search_user_password'], 'v2:' ) && $secret === Secrets::decrypt( $current['sso_search_user_password'] ), $scope . ': the legacy password is re-encrypted without changing its bytes.' );
		$check( 'legacy' === $settings->recovery_mode(), $scope . ': old settings retain administrator recovery.' );
		$settings->migrate();
		$check( $current === $read( $network, 'sll_settings' ) && $old === $read( $network, 'sll_settings_backup_v1' ), $scope . ': repeat migration changes neither settings nor backup.' );
		$remove( $network, 'sll_settings_backup_v1' );
		$settings->migrate();
		$check( false === $read( $network, 'sll_settings_backup_v1' ) && $current === $read( $network, 'sll_settings' ), $scope . ': backup removal does not change migrated settings or create a new backup.' );

		$reset( $network );
		$schema_two = array_replace( Settings::defaults(), array( 'schema_version' => 2, 'enabled' => true, 'base_dn' => 'dc=example,dc=test', 'domain_controllers' => array( 'ldap.test' ), 'sso_search_user_password' => $current['sso_search_user_password'], 'extension_setting' => array( 'retain' => true ) ) );
		unset( $schema_two['recovery_mode'], $schema_two['recovery_user_ids'] );
		$write( $network, 'sll_settings', $schema_two );
		$backup = array( 'retain' => 'the first backup' );
		$write( $network, 'sll_settings_backup_v1', $backup );
		$check( 'legacy' === $settings->recovery_mode(), $scope . ': schema version 2 keeps administrator recovery before migration.' );
		$settings->migrate();
		$expected = array_replace( $schema_two, array( 'schema_version' => 3, 'recovery_mode' => 'legacy', 'recovery_user_ids' => array() ) );
		$check( $expected === $read( $network, 'sll_settings' ) && $backup === $read( $network, 'sll_settings_backup_v1' ), $scope . ': schema version 2 gains only the recovery policy and keeps its backup and secret.' );
		$check( true === $settings->save( array( 'recovery_mode' => 'off' ) ), $scope . ': an administrator can explicitly turn off the recovery exception.' );
		$current = $read( $network, 'sll_settings' );
		$settings->migrate();
		$check( $current === $read( $network, 'sll_settings' ) && 'off' === $settings->recovery_mode(), $scope . ': repeat migration preserves an explicit recovery policy.' );

		$reset( $network );
		$old_options = array(
			'simpleldap_base_dn'            => 'dc=example,dc=test',
			'simpleldap_domain_controllers' => 'ldap-one.test;ldap-two.test',
			'simpleldap_group'              => "staff\neditors",
			'simpleldap_account_type'       => 'contributor',
			'simpleldap_ol_login'           => 'uid',
			'simpleldap_use_tls'            => false,
			'simpleldap_directory_type'     => 'directory_openldap',
			'simpleldap_login_mode'         => 'mode_create_group',
			'simpleldap_security_mode'      => 'security_low',
		);
		foreach ( $old_options as $key => $value ) {
			$write( $network, $key, $value );
			// WordPress stores scalar false as an empty database string.
			$missing = new stdClass();
			$stored  = $read( $network, $key, $missing );
			$check( $missing !== $stored, $scope . ': the old ' . $key . ' fixture is stored.' );
			$old_options[ $key ] = $stored;
		}
		$settings->migrate();
		$current = $read( $network, 'sll_settings' );
		$check( true === $current['enabled'] && 'ol' === $current['directory'] && true === $current['create_users'] && false === $current['high_security'], $scope . ': the oldest options retain their login mode and directory type.' );
		$check( 'legacy' === $settings->recovery_mode(), $scope . ': the oldest options retain administrator recovery.' );
		$check( 'contributor' === $current['role'] && 'uid' === $current['ol_login'] && array( 'staff', 'editors' ) === $current['groups'], $scope . ': the oldest options retain the role, login attribute, and groups.' );
		$backup = $read( $network, 'sll_settings_backup_v1' );
		$check( $old_options === $backup && array_key_exists( 'simpleldap_use_tls', $backup ) && ! Settings::is_true( $backup['simpleldap_use_tls'] ), $scope . ': the oldest option backup retains the stored false TLS value.' );
		foreach ( $old_options as $key => $value ) {
			$missing = new stdClass();
			$stored  = $read( $network, $key, $missing );
			$check( $missing !== $stored && $value === $stored, $scope . ': the old ' . $key . ' option is not deleted.' );
		}
		$settings->migrate();
		$check( $current === $read( $network, 'sll_settings' ), $scope . ': the oldest migration is idempotent.' );

		$reset( $network );
		$write( $network, 'sll_settings', 'invalid old data' );
		$settings->migrate();
		$check( 'invalid old data' === $read( $network, 'sll_settings' ) && false === $read( $network, 'sll_settings_backup_v1' ), $scope . ': malformed stored data is not replaced by defaults.' );
	}

	if ( is_multisite() ) {
		$plugin          = plugin_basename( SLL_FILE );
		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );
		$site_plugins    = get_option( 'active_plugins', array() );
		$check( $expected_network ? isset( $network_plugins[ $plugin ] ) : ! isset( $network_plugins[ $plugin ] ) && in_array( $plugin, $site_plugins, true ), $expected_network ? 'The native activation record confirms network activation.' : 'The native activation records confirm site-only activation.' );
		$site_value    = array_replace( Settings::defaults(), array( 'base_dn' => 'dc=site,dc=test' ) );
		$network_value = array_replace( Settings::defaults(), array( 'base_dn' => 'dc=network,dc=test' ) );
		$write( false, 'sll_settings', $site_value );
		$write( true, 'sll_settings', $network_value );
		$check( $expected_network === ( new Settings() )->is_network() && ( $expected_network ? 'dc=network,dc=test' : 'dc=site,dc=test' ) === ( new Settings() )->get( 'base_dn' ), 'Implicit settings use the actual activation scope on the main site.' );
		$check( 'dc=site,dc=test' === ( new Settings( false ) )->get( 'base_dn' ), 'An explicit site scope can read its separate settings.' );
		$check( true === ( new Settings( false ) )->save( array( 'base_dn' => 'dc=changed-site,dc=test' ) ) && $network_value === $read( true, 'sll_settings' ), 'A site save leaves network settings unchanged.' );
		$site_value = $read( false, 'sll_settings' );
		$check( true === ( new Settings( true ) )->save( array( 'base_dn' => 'dc=changed-network,dc=test' ) ) && $site_value === $read( false, 'sll_settings' ), 'A network save leaves site settings unchanged.' );

		require_once ABSPATH . 'wp-admin/includes/ms.php';
		$main_site = get_site();
		$admin     = get_user_by( 'login', 'testadmin' );
		$check( $admin instanceof WP_User, 'The test administrator exists for the child site.' );
		$skip_mail = static function () { return true; };
		add_filter( 'pre_wp_mail', $skip_mail );
		try {
			$child_id = wpmu_create_blog( $main_site->domain, trailingslashit( $main_site->path ) . 'sll-migration-' . strtolower( wp_generate_password( 8, false, false ) ) . '/', 'SLL migration fixture', $admin->ID, array( 'public' => 0 ), get_current_network_id() );
		} finally {
			remove_filter( 'pre_wp_mail', $skip_mail );
		}
		$check( is_int( $child_id ) && $child_id > 0, 'The temporary child site is created.' );
		$network_value = $read( true, 'sll_settings' );
		switch_to_blog( $child_id );
		try {
			update_option( 'sll_settings', array_replace( Settings::defaults(), array( 'base_dn' => 'dc=child,dc=test' ) ), false );
			$check( $expected_network === ( new Settings() )->is_network() && ( $expected_network ? 'dc=changed-network,dc=test' : 'dc=child,dc=test' ) === ( new Settings() )->get( 'base_dn' ), 'Implicit settings use the actual activation scope after a child site switch.' );
			$check( 'dc=child,dc=test' === ( new Settings( false ) )->get( 'base_dn' ), 'An explicit site scope selects the child settings after a blog switch.' );
			$check( true === ( new Settings( false ) )->save( array( 'base_dn' => 'dc=changed-child,dc=test' ) ), 'A child site can save its separate settings.' );
		} finally {
			restore_current_blog();
		}
		$check( $site_value === $read( false, 'sll_settings' ) && $network_value === $read( true, 'sll_settings' ), 'The child save changes neither the main site nor network settings.' );
	}
} finally {
	if ( is_int( $child_id ) && $child_id > 0 ) {
		wpmu_delete_blog( $child_id, true );
	}
	foreach ( $scopes as $network ) {
		foreach ( $snapshot[ (int) $network ] as $key => $saved ) {
			if ( $saved[0] ) {
				$write( $network, $key, $saved[1] );
			} else {
				$remove( $network, $key );
			}
		}
	}
}
echo 'Native migration checks passed: ' . $checks . ".\n";
