<?php
/** Local demo: trust the test CA and stop outgoing email. */
defined( 'ABSPATH' ) || exit;

if ( extension_loaded( 'ldap' ) ) {
	ldap_set_option( null, LDAP_OPT_X_TLS_CACERTFILE, ABSPATH . '.sll-demo-ca.crt' );
}

add_filter( 'pre_wp_mail', '__return_true' );
