<?php
/** Run each phase in a new WP-CLI request. Never use this script on a useful site. */

use SimpleLdapLogin\Settings;

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once WP_PLUGIN_DIR . '/simple-ldap-login/vendor/autoload.php';
if ( ! defined( 'FS_METHOD' ) ) {
	define( 'FS_METHOD', 'direct' );
}
$plugin = 'simple-ldap-login/Simple-LDAP-Login.php';
$phase  = $args[0] ?? '';
$checks = 0;
$check  = static function ( bool $passed, string $label ) use ( &$checks ): void {
	if ( ! $passed ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$checks;
	echo 'PASS: ' . $label . "\n";
};
$package = static function ( string $edition ): string {
	if ( ! in_array( $edition, array( 'free', 'pro' ), true ) ) {
		throw new RuntimeException( 'Unknown test edition.' );
	}
	return '/packages/simple-ldap-login' . ( 'free' === $edition ? '' : '-' . $edition ) . '.zip';
};
$active = static function ( bool $network ) use ( $plugin ): bool {
	return $network ? is_plugin_active_for_network( $plugin ) : in_array( $plugin, get_option( 'active_plugins', array() ), true );
};
$read_state = static function ( bool $network ): array {
	$result = array();
	foreach ( array( 'sll_settings', 'sll_settings_backup_v1', 'sll_legacy_access', 'sll_pro_license' ) as $key ) {
		$missing = new stdClass();
		$value   = $network ? get_site_option( $key, $missing ) : get_option( $key, $missing );
		$result[ $key ] = array( $missing !== $value, $missing === $value ? null : $value );
	}
	return $result;
};
$user_state = static function ( int $id ): array {
	$user = get_userdata( $id );
	return array( 'data' => $user ? $user->to_array() : false, 'meta' => get_user_meta( $id ) );
};
$same_data = static function ( array $state ) use ( $read_state, $user_state ): bool {
	$current = $read_state( $state['network'] );
	// A package replacement must not change saved settings or the backup.
	return $current === $state['options'] && $state['user'] === $user_state( $state['user_id'] );
};
$install = static function ( string $zip ) {
	$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
	$result   = $upgrader->install( $zip, array( 'overwrite_package' => true ) );
	return array( $result, implode( ' ', $upgrader->skin->get_upgrade_messages() ) );
};
$upgrade = static function ( string $zip ) use ( $plugin ) {
	set_site_transient( 'update_plugins', (object) array( 'last_checked' => time(), 'checked' => array( $plugin => '2.0.0' ), 'response' => array( $plugin => (object) array( 'package' => $zip, 'new_version' => '2.0.1', 'slug' => 'simple-ldap-login' ) ) ) );
	$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
	$result   = $upgrader->upgrade( $plugin );
	return array( $result, implode( ' ', $upgrader->skin->get_upgrade_messages() ) );
};
$on_disk = static function ( string $edition ): bool {
	return false !== strpos( file_get_contents( WP_PLUGIN_DIR . '/simple-ldap-login/Simple-LDAP-Login.php' ), "define( 'SLL_EDITION', '" . $edition . "' );" );
};

if ( 'prepare' === $phase ) {
	$check( defined( 'SLL_EDITION' ) && in_array( SLL_EDITION, array( 'free', 'pro' ), true ), 'The upgrade test starts from Free or Pro.' );
	$network = ( new Settings() )->is_network();
	$check( $active( $network ), 'The original package is active in its expected scope.' );
	$login = 'sll-upgrade-' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 64, true, true ), 'user_email' => $login . '@example.test', 'role' => 'editor' ) );
	$check( is_int( $id ), 'A user fixture exists before the package changes.' );
	update_user_meta( $id, 'sll_upgrade_fixture', array( 'retain' => 'user data' ) );
	$state = array( 'edition' => SLL_EDITION, 'network' => $network, 'options' => $read_state( $network ), 'user_id' => $id, 'user' => $user_state( $id ), 'update_transient' => get_site_transient( 'update_plugins' ) );
	update_option( 'sll_test_upgrade_state', $state, false );
	list( $result ) = $install( $package( 'pro' ) );
	$check( true === $result && $on_disk( 'pro' ), 'A native ZIP replacement installs Pro at the stable plugin path.' );
	$check( $active( $network ) && $same_data( $state ), 'The Pro replacement keeps activation, settings, backup, and stored credentials.' );
} else {
	$state = get_option( 'sll_test_upgrade_state' );
	$check( is_array( $state ) && isset( $state['edition'], $state['network'], $state['options'] ), 'The upgrade snapshot exists.' );
	$network = $state['network'];
	if ( 'seed-v1' === $phase ) {
		$check( 'pro' === SLL_EDITION && $active( $network ), 'Pro is installed before the 1.x settings test.' );
		$old = ( new Settings() )->all();
		unset( $old['schema_version'], $old['recovery_mode'], $old['recovery_user_ids'] );
		$old['version'] = '180';
		$old['enabled'] = 'true';
		$old['sso_enabled'] = 'true';
		$old['user_meta_data'] = array( array( 'telephoneNumber', 'phone', 'string' ) );
		$old['meta_data_suffix_ldap'] = 'directory';
		$old['meta_data_suffix_wp'] = 'local';
		if ( $network ) {
			delete_site_option( 'sll_settings_backup_v1' );
			update_site_option( 'sll_settings', $old );
		} else {
			delete_option( 'sll_settings_backup_v1' );
			update_option( 'sll_settings', $old, false );
		}
		update_option( 'sll_test_upgrade_v1', $old, false );
	} elseif ( 'verify-v1' === $phase ) {
		$old = get_option( 'sll_test_upgrade_v1' );
		$settings = new Settings();
		$backup = $network ? get_site_option( 'sll_settings_backup_v1' ) : get_option( 'sll_settings_backup_v1' );
		$check( 'pro' === SLL_EDITION && $backup === $old && 3 === $settings->get( 'schema_version' ), 'Pro migrates 1.x on startup and keeps the original settings backup.' );
		$check( $old['sso_search_user_password'] === $settings->get( 'sso_search_user_password' ) && 'legacy' === $settings->recovery_mode(), 'The upgrade retains the search password and administrator recovery.' );
		$client = new SimpleLdapLogin\Pro\Ldap_Client( $settings );
		$check( is_array( $client->authenticate_sso( 'alice' ) ), 'SSO lookup works with the migrated 1.x settings.' );
		$user = wp_authenticate( 'alice', 'Test-Password-42!' );
		$check( $user instanceof WP_User, 'LDAP password login works after the 1.x upgrade.' );
		$check( '+15550100' === get_user_meta( $user->ID, 'phone_directory', true ) && '+15550100' === get_user_meta( $user->ID, 'phone_local', true ), 'Custom fields keep the 1.x metadata suffixes after login.' );
		foreach ( $state['options'] as $key => $saved ) {
			if ( $network ) {
				$saved[0] ? update_site_option( $key, $saved[1] ) : delete_site_option( $key );
			} else {
				$saved[0] ? update_option( $key, $saved[1], false ) : delete_option( $key );
			}
		}
		delete_option( 'sll_test_upgrade_v1' );
	} elseif ( 'guard' === $phase ) {
		$expected  = $args[1] ?? '';
		$next      = $args[2] ?? '';
		$operation = $args[3] ?? '';
		$check( defined( 'SLL_EDITION' ) && $expected === SLL_EDITION && $active( $network ), 'The expected full package is active before replacement.' );
		$check( $on_disk( $expected ) && $same_data( $state ), 'The previous request retained its package, settings, credentials, and user data after shutdown.' );
		$hash = hash_file( 'sha256', WP_PLUGIN_DIR . '/' . $plugin );
		if ( in_array( $operation, array( 'reject-install', 'reject-update' ), true ) ) {
			$method = 'reject-install' === $operation ? 'install' : 'update';
			list( $result, $messages ) = 'install' === $method ? $install( $package( 'free' ) ) : $upgrade( $package( 'free' ) );
			$check( true !== $result && str_contains( $messages, 'Update blocked:' ), $expected . ': native ' . $method . ' rejects reduced Free.' );
			$check( $active( $network ) && $hash === hash_file( 'sha256', WP_PLUGIN_DIR . '/' . $plugin ) && file_exists( WP_PLUGIN_DIR . '/simple-ldap-login/pro/src/Server_Sso.php' ), $expected . ': rejected ' . $method . ' keeps the original activation and feature files.' );
			$check( $same_data( $state ), $expected . ': rejected ' . $method . ' keeps settings and credentials.' );
		} elseif ( 'update' === $operation ) {
			// Native background updates do not use the browser reactivation step.
			add_filter( 'wp_doing_cron', '__return_true' );
			try {
				list( $result ) = $upgrade( $package( $expected ) );
			} finally {
				remove_filter( 'wp_doing_cron', '__return_true' );
			}
			$check( true === $result && $active( $network ) && $same_data( $state ), $expected . ': a full background update keeps activation and settings.' );
		} elseif ( 'replace' === $operation ) {
			list( $result ) = $install( $package( $next ) );
			$check( true === $result && $on_disk( $next ) && $active( $network ) && $same_data( $state ), $expected . ': a full ZIP replacement keeps activation and settings.' );
		} else {
			throw new RuntimeException( 'Unknown upgrade test operation.' );
		}
	} elseif ( 'deactivate' === $phase ) {
		$check( defined( 'SLL_EDITION' ) && 'pro' === SLL_EDITION, 'The full-package cycle ends on Pro.' );
		deactivate_plugins( $plugin, true, $network );
		$check( ! $active( $network ) && $same_data( $state ), 'An explicit deactivation keeps saved settings.' );
	} elseif ( 'choose-free' === $phase ) {
		$check( ! defined( 'SLL_EDITION' ) && ! $active( $network ), 'The voluntary downgrade uses a new request after deactivation.' );
		list( $result ) = $install( $package( 'free' ) );
		$check( true === $result && $on_disk( 'free' ) && ! is_dir( WP_PLUGIN_DIR . '/simple-ldap-login/pro' ), 'A deliberate inactive replacement permits Free and removes Pro source.' );
		$result = activate_plugin( $plugin, '', $network );
		$check( ! is_wp_error( $result ) && $active( $network ) && $same_data( $state ), 'Free can be activated with the same saved settings and credentials.' );
	} elseif ( 'restore' === $phase ) {
		$check( defined( 'SLL_EDITION' ) && 'free' === SLL_EDITION, 'The new request loads the chosen Free package.' );
		list( $result ) = $install( $package( $state['edition'] ) );
		$check( true === $result && $active( $network ) && $on_disk( $state['edition'] ), 'The test returns to the original package and activation scope.' );
		foreach ( $state['options'] as $key => $saved ) {
			if ( $network ) {
				$saved[0] ? update_site_option( $key, $saved[1] ) : delete_site_option( $key );
			} else {
				$saved[0] ? update_option( $key, $saved[1], false ) : delete_option( $key );
			}
		}
	} elseif ( 'verify' === $phase ) {
		$check( defined( 'SLL_EDITION' ) && $state['edition'] === SLL_EDITION && $active( $network ), 'A new request loads the original edition in the original scope.' );
		$check( $state['options'] === $read_state( $network ), 'The original settings, credentials, backup, and stored options are restored.' );
		$check( $state['user'] === $user_state( $state['user_id'] ), 'The user password hash, role, and metadata survive the complete package cycle.' );
		if ( is_multisite() ) {
			require_once ABSPATH . 'wp-admin/includes/ms.php';
			wpmu_delete_user( $state['user_id'] );
		} else {
			wp_delete_user( $state['user_id'] );
		}
		$state['update_transient'] ? set_site_transient( 'update_plugins', $state['update_transient'] ) : delete_site_transient( 'update_plugins' );
		delete_option( 'sll_test_upgrade_state' );
	} else {
		throw new RuntimeException( 'Unknown upgrade test phase.' );
	}
}
echo 'Native upgrade checks passed: ' . $phase . ', ' . $checks . ".\n";
