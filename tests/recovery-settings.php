<?php
/** Recovery policy checks, loaded after the WordPress user test helpers. */

$recovery_setup = static function ( array $changes = array() ): SimpleLdapLogin\Settings {
	$GLOBALS['sll_test_filters'] = array();
	$GLOBALS['sll_test_network'] = false;
	$GLOBALS['sll_test_super_admins'] = array();
	$GLOBALS['sll_test_memberships'] = array( 1 => array( 1, 2, 3 ) );
	$GLOBALS['sll_test_blog_id'] = 1;
	$GLOBALS['sll_test_site_options'] = array();
	$GLOBALS['sll_test_options'] = array( 'sll_settings' => array_replace( SimpleLdapLogin\Settings::defaults(), array( 'base_dn' => 'dc=example,dc=com', 'domain_controllers' => array( 'ldap.example.com' ) ), $changes ) );
	$GLOBALS['sll_test_users'] = array( new WP_User( 1, 'member', 'member@example.com' ), new WP_User( 2, 'recovery', 'recovery@example.com', true ), new WP_User( 3, 'other-admin', 'other@example.com', true ) );
	return new SimpleLdapLogin\Settings();
};

$settings = $recovery_setup();
$before = $GLOBALS['sll_test_options'];
$result = $settings->save( array( 'enabled' => true ) );
check( is_wp_error( $result ) && 'sll_recovery_required' === $result->get_error_code() && $before === $GLOBALS['sll_test_options'], 'New LDAP setup needs a recovery account or an explicit Off policy, without saving a partial change.' );
check( true === $settings->save( array( 'enabled' => true, 'recovery_mode' => 'off' ) ) && ! $settings->is_recovery_account( get_user_by( 'id', 2 ) ), 'An explicit Off choice permits LDAP setup without a recovery exception.' );
check( true === $settings->save( array( 'recovery_mode' => 'selected', 'recovery_user_ids' => array( '', '2', 2 ) ) ) && array( 2 ) === $settings->get( 'recovery_user_ids' ), 'Recovery selections accept the empty form marker and store unique integer IDs.' );
check( $settings->is_recovery_account( get_user_by( 'id', 2 ) ) && ! $settings->is_recovery_account( get_user_by( 'id', 3 ) ), 'Only the selected administrator receives the local recovery exception.' );

foreach ( array( array( 1 ), array( 404 ), array( -1 ), array( 0 ), array( true ), array( 2.0 ), array( array( 2 ) ), '2' ) as $ids ) {
	$before = $GLOBALS['sll_test_options'];
	check( is_wp_error( $settings->save( array( 'recovery_user_ids' => $ids ) ) ) && $before === $GLOBALS['sll_test_options'], 'Invalid, missing, or non-administrator recovery accounts cannot change settings.' );
}
check( is_wp_error( $settings->save( array( 'recovery_mode' => 'everyone' ) ) ), 'An unknown recovery policy cannot be saved.' );
check( true === $settings->save( array( 'recovery_mode' => 'legacy' ) ) && array( 2 ) === $settings->get( 'recovery_user_ids' ) && ! $settings->is_recovery_account( get_user_by( 'id', 2 ) ), 'Changing policy keeps saved selections but grants selected-only access only in selected mode.' );
check( true === $settings->save( array( 'recovery_mode' => 'selected' ) ), 'A saved recovery selection can be used again.' );
get_user_by( 'id', 2 )->admin = false;
check( ! $settings->is_recovery_account( get_user_by( 'id', 2 ) ), 'A demoted account immediately loses local recovery access.' );
check( true === $settings->save( array( 'timeout' => 10 ) ), 'A demoted recovery account does not block an unrelated settings save.' );
check( is_wp_error( $settings->save( array( 'recovery_mode' => 'selected' ) ) ), 'A recovery policy change must recheck the selected account privilege.' );

$settings = $recovery_setup();
check( true === $settings->save( array( 'recovery_mode' => 'selected', 'recovery_user_ids' => array() ) ), 'LDAP and SSO can stay off while a recovery selection is being prepared.' );
$result = $settings->save( array( 'sso_enabled' => true ) );
check( is_wp_error( $result ) && 'sll_recovery_required' === $result->get_error_code(), 'Server SSO setup also requires a recovery selection or an explicit Off choice.' );
check( true === $settings->save( array( 'sso_enabled' => true, 'recovery_user_ids' => array( 2 ) ) ), 'Server SSO can be enabled with an eligible recovery account.' );
$GLOBALS['sll_test_options']['sll_settings']['recovery_user_ids'] = array( '2' );
check( ! $settings->is_recovery_account( get_user_by( 'id', 2 ) ), 'Malformed stored recovery IDs do not grant an exception.' );
$GLOBALS['sll_test_options']['sll_settings']['recovery_mode'] = array( 'legacy' );
check( 'off' === $settings->recovery_mode() && ! $settings->is_recovery_account( get_user_by( 'id', 2 ) ), 'A malformed stored recovery policy fails closed.' );

$settings = $recovery_setup( array( 'enabled' => true, 'recovery_mode' => 'off' ) );
$settings->migrate();
check( 'off' === $settings->recovery_mode(), 'Repeat migration keeps an explicit Off policy.' );
$settings = $recovery_setup( array( 'enabled' => true, 'recovery_user_ids' => array( 2 ) ) );
$before = $GLOBALS['sll_test_options']['sll_settings'];
$settings->migrate();
check( $before === $GLOBALS['sll_test_options']['sll_settings'], 'Repeat migration keeps the complete selected-account policy.' );

$settings = $recovery_setup( array( 'enabled' => true, 'recovery_user_ids' => array( 2 ) ) );
$selected = get_user_by( 'id', 2 );
$selected->spam = true;
check( ! $settings->is_recovery_account( $selected ), 'A spam account cannot receive local recovery access.' );
$selected->spam = false;
$selected->deleted = true;
check( ! $settings->is_recovery_account( $selected ), 'A deleted account cannot receive local recovery access.' );
$selected->deleted = false;
$GLOBALS['sll_test_users'] = array_filter( $GLOBALS['sll_test_users'], static fn( $user ) => 2 !== $user->ID );
check( true === $settings->save( array( 'timeout' => 10 ) ) && true === $settings->save( array( 'enabled' => false ) ), 'A deleted recovery account does not block another tab or prevent LDAP from being disabled.' );
check( is_wp_error( $settings->save( array( 'enabled' => true ) ) ), 'Enabling LDAP again must recheck whether the recovery account still exists.' );

$settings = $recovery_setup( array( 'recovery_user_ids' => array( 2 ) ) );
$GLOBALS['sll_test_network'] = true;
$GLOBALS['sll_test_super_admins'] = array( 3 );
$GLOBALS['sll_test_memberships'] = array( 1 => array( 1, 2 ), 2 => array( 1, 3 ) );
check( $settings->can_recover( get_user_by( 'id', 2 ) ) && ! $settings->can_recover( get_user_by( 'id', 3 ) ), 'Site recovery choices require administrator access and membership of the current site.' );
$GLOBALS['sll_test_blog_id'] = 2;
check( ! $settings->is_recovery_account( get_user_by( 'id', 2 ) ), 'A selected administrator from another site cannot recover the current site.' );
$GLOBALS['sll_test_blog_id'] = 1;
$GLOBALS['sll_test_site_options']['sll_settings'] = array_replace( SimpleLdapLogin\Settings::defaults(), array( 'recovery_user_ids' => array( 2 ) ) );
$network_settings = new SimpleLdapLogin\Settings( true );
check( ! $network_settings->can_recover( get_user_by( 'id', 2 ) ) && $network_settings->can_recover( get_user_by( 'id', 3 ) ), 'Network recovery choices are limited to super administrators.' );
$before = $GLOBALS['sll_test_site_options'];
check( is_wp_error( $network_settings->save( array( 'recovery_user_ids' => array( 2 ) ) ) ) && $before === $GLOBALS['sll_test_site_options'], 'A site administrator cannot be selected for network recovery.' );
check( true === $network_settings->save( array( 'recovery_user_ids' => array( 3 ) ) ) && $network_settings->is_recovery_account( get_user_by( 'id', 3 ) ), 'An existing super administrator can be selected for network recovery.' );
$GLOBALS['sll_test_super_admins'] = array();
check( ! $network_settings->is_recovery_account( get_user_by( 'id', 3 ) ), 'Removing super administrator access immediately removes network recovery access.' );
$GLOBALS['sll_test_network'] = false;

echo "Recovery settings checks passed.\n";
