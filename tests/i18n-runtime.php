<?php
/** Check bundled catalogs with WP-CLI eval-file on an active test site. */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( SimpleLdapLogin\Plugin::class ) ) {
	throw new RuntimeException( 'Run this file with WP-CLI and Simple LDAP Login active.' );
}

$checks = 0;
$files = glob( dirname( __DIR__ ) . '/languages/*.l10n.php' );
if ( ! $files ) {
	throw new RuntimeException( 'Build the translation catalogs first.' );
}
foreach ( $files as $file ) {
	$catalog = require $file;
	$locale = $catalog['language'];
	$locale_filter = static fn() => $locale;
	add_filter( 'locale', $locale_filter );
	add_filter( 'determine_locale', $locale_filter );
	foreach ( array( 'php', 'mo' ) as $format ) {
		$format_filter = static fn() => $format;
		add_filter( 'translation_file_format', $format_filter );
		unload_textdomain( 'simple-ldap-login', true );
		SimpleLdapLogin\Plugin::load_translations();
		foreach ( $catalog['messages'] as $source => $expected ) {
			if ( translate( $source, 'simple-ldap-login' ) !== $expected ) {
				throw new RuntimeException( "Translation failed: $locale ($format): $source" );
			}
			++$checks;
		}
		remove_filter( 'translation_file_format', $format_filter );
	}
	remove_filter( 'locale', $locale_filter );
	remove_filter( 'determine_locale', $locale_filter );
}
unload_textdomain( 'simple-ldap-login', true );
SimpleLdapLogin\Plugin::load_translations();
WP_CLI::success( "$checks bundled translation checks passed." );
