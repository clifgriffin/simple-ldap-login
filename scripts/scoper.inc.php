<?php
/** Keep bundled dependencies private while retaining the plugin's public classes. */

use Symfony\Component\Finder\Finder;

$files = array( 'composer.json', 'src/Ldap_Client.php', 'src/License.php', 'src/PaidCommunities_Client.php', 'src/Commerce.php' );
$finders = array( Finder::create()->files()->in( 'vendor' ) );
if ( is_file( 'pro/src/Ldap_Client.php' ) ) {
	$files   = array_merge( $files, array( 'pro/composer.json', 'pro/src/Ldap_Client.php' ) );
	$finders[] = Finder::create()->files()->in( 'pro/vendor' );
}

return array(
	'prefix'                  => 'SimpleLdapLoginVendor',
	'php-version'             => '8.1',
	'finders'                 => array_merge( $finders, array( Finder::create()->append( $files ) ) ),
	'exclude-namespaces'      => array( 'SimpleLdapLogin' ),
	'exclude-constants'       => array( 'SLL_FILE', 'SLL_VERSION', 'SLL_EDITION', 'SLL_FEATURES_LOADED', 'SLL_DISABLE_LDAP', 'SLL_ENCRYPTION_KEY' ),
	'exclude-classes'         => array( 'WP_Error', 'WP_User', 'WP_Http' ),
	'exclude-functions'       => array( '__', 'is_wp_error', 'apply_filters', 'wp_parse_url' ),
	'expose-global-classes'   => false,
	'expose-global-functions' => false,
	'expose-global-constants' => false,
	'patchers'                => array(
		static function ( string $file_path, string $prefix, string $contents ): string {
			if ( str_ends_with( $file_path, '/ConnectionManager.php' ) ) {
				foreach ( array( '\\', '\\\\' ) as $separator ) {
					foreach ( array( 'Auth', 'Query', 'Models' ) as $event ) {
						$pattern  = implode( $separator, array( 'LdapRecord', $event, 'Events', '*' ) );
						$contents = str_replace( $pattern, $prefix . $separator . $pattern, $contents );
					}
				}
			}

			$helpers = array();
			if ( str_contains( $contents, 'namespace ' . $prefix . '\\Illuminate\\' ) || str_contains( $contents, 'namespace ' . $prefix . '\\LdapRecord' ) ) {
				$helpers = array( 'collect', 'data_fill', 'data_get', 'data_set', 'data_forget', 'head', 'last', 'value' );
			} elseif ( str_contains( $contents, 'namespace ' . $prefix . '\\Symfony\\' ) ) {
				$helpers = array( 'trigger_deprecation' );
			}
			if ( empty( $helpers ) ) {
				return $contents;
			}

			// PHP-Scoper leaves ambiguous function calls open to PHP's global fallback.
			$imports = '';
			foreach ( $helpers as $helper ) {
				$import = 'use function ' . $prefix . '\\' . $helper . ';';
				if ( ! str_contains( $contents, $import ) ) {
					$imports .= "\n" . $import;
				}
			}

			return preg_replace_callback(
				'/^namespace [^;]+;/m',
				static fn( array $match ): string => $match[0] . "\n" . $imports,
				$contents,
				1
			);
		},
	),
);
