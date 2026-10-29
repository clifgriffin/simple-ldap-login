<?php
/** Standalone data retention checks. Run: php tests/uninstall.php. */

define( 'WP_UNINSTALL_PLUGIN', true );
$network = false;
$site_id = 1;
$sites = array();
$networks = array();
$keys = array( 'sll_settings', 'sll_settings_backup_v1', 'sll_legacy_access', 'simpleldap_base_dn' );
function is_multisite() { return $GLOBALS['network']; }
function get_option( $key, $default = false ) { return $GLOBALS['sites'][ $GLOBALS['site_id'] ][ $key ] ?? $default; }
function delete_option( $key ) { unset( $GLOBALS['sites'][ $GLOBALS['site_id'] ][ $key ] ); }
function get_networks( $args ) { return array_keys( $GLOBALS['networks'] ); }
function get_network_option( $id, $key, $default = false ) { return $GLOBALS['networks'][ $id ][ $key ] ?? $default; }
function delete_network_option( $id, $key ) { unset( $GLOBALS['networks'][ $id ][ $key ] ); }
function get_sites( $args ) { return array_slice( array_keys( $GLOBALS['sites'] ), $args['offset'], $args['number'] ); }
function switch_to_blog( $id ) { $GLOBALS['site_id'] = $id; }
function restore_current_blog() { $GLOBALS['site_id'] = 1; }
function verify( $value ) { if ( ! $value ) { throw new RuntimeException( 'Data retention check failed.' ); } }

$sites = array( 1 => array_fill_keys( $keys, 'keep' ) );
$sites[1]['sll_settings'] = array( 'delete_on_uninstall' => 'false' );
$before = $sites;
require dirname( __DIR__ ) . '/uninstall.php';
verify( $before === $sites );
$sites[1]['sll_settings']['delete_on_uninstall'] = true;
$sites[1]['other_plugin'] = 'keep';
require dirname( __DIR__ ) . '/uninstall.php';
verify( array( 'other_plugin' => 'keep' ) === $sites[1] );

$network = true;
$sites = array( 1 => array( 'sll_settings' => array( 'delete_on_uninstall' => false ) ), 2 => array( 'sll_settings' => array( 'delete_on_uninstall' => true ) ) );
$networks = array( 1 => array( 'sll_settings' => array( 'delete_on_uninstall' => true ) ), 2 => array( 'sll_settings' => array( 'delete_on_uninstall' => false ) ) );
require dirname( __DIR__ ) . '/uninstall.php';
verify( isset( $sites[1]['sll_settings'], $networks[2]['sll_settings'] ) );
verify( ! isset( $sites[2]['sll_settings'], $networks[1]['sll_settings'] ) );
echo "Uninstall checks passed: 4.\n";
