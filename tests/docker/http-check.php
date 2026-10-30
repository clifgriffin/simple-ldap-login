<?php
/** HTTP checks use real Apache requests, WordPress, and plugin hooks. */
$edition = getenv( 'SLL_EDITION' );
$site    = getenv( 'SLL_SITE_URL' ) ?: 'http://localhost';
$parts   = parse_url( $site );
$host    = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
$checks  = 0;
$check   = static function ( bool $passed, string $label ) use ( &$checks ): void {
	if ( ! $passed ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$checks;
	echo 'PASS: ' . $label . "\n";
};
$request = static function ( string $path, array $headers = array(), bool $auth = false, string $method = 'GET' ) use ( $host ): array {
	$cookies = array();
	$curl    = curl_init( 'http://localhost/' . $path );
	curl_setopt_array(
		$curl,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => array_merge( array( 'Host: ' . $host ), $headers ),
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_HEADERFUNCTION => static function ( $connection, string $line ) use ( &$cookies ): int {
				if ( preg_match( '/^Set-Cookie:\s*wordpress(?:_logged_in|_sec)?_[a-f0-9]{32}=/i', $line ) ) {
					$cookies[] = $line;
				}
				return strlen( $line );
			},
		)
	);
	if ( $auth ) {
		curl_setopt( $curl, CURLOPT_USERPWD, 'alice:Test-Password-42!' );
	}
	$body = curl_exec( $curl );
	if ( false === $body ) {
		throw new RuntimeException( curl_error( $curl ) );
	}
	$code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
	curl_close( $curl );
	return array( 'code' => $code, 'body' => json_decode( $body, true ), 'cookies' => $cookies );
};
$cli = static function ( string $code, bool $identity = false ) use ( $site ): array {
	$command = array( 'wp', '--url=' . $site );
	if ( $identity ) {
		$command[] = '--exec=$_SERVER["REMOTE_USER"] = "alice"; $_SERVER["REQUEST_METHOD"] = "GET";';
	}
	$command[] = 'eval';
	$command[] = $code;
	$process   = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, '/var/www/html' );
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'The WP-CLI test process could not start.' );
	}
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$status = proc_close( $process );
	$result = json_decode( $output, true );
	if ( 0 !== $status || ! is_array( $result ) ) {
		throw new RuntimeException( 'WP-CLI test failed: ' . $error );
	}
	return $result;
};

$response = $request( 'sll-test-user.php', array( 'Remote-User: alice', 'X-Remote-User: alice', 'X-Forwarded-User: alice' ) );
$check( 200 === $response['code'] && '' === ( $response['body']['user'] ?? null ) && array() === $response['cookies'], 'Forged user headers cannot sign in or set an auth cookie.' );
$check( 401 === $request( 'sll-test-sso.php' )['code'], 'Apache requires authentication for the trusted SSO test path.' );
$check( 400 === $request( 'sll-test-user.php?sll_case=invalid' )['code'], 'The test probe rejects an unknown fixture.' );

$cases = array(
	'normal'          => array( 'GET', array(), 'pro' === $edition ),
	'license-expired' => array( 'GET', array(), false ),
	'license-inactive' => array( 'GET', array(), false ),
	'license-missing' => array( 'GET', array(), false ),
	'license-outage' => array( 'GET', array(), 'pro' === $edition ),
	'proof-whitespace' => array( 'GET', array(), 'pro' === $edition ),
	'password-off'    => array( 'GET', array(), 'pro' === $edition ),
	'sso-off'         => array( 'GET', array(), false ),
	'group-denied'    => array( 'GET', array(), false ),
	'service-missing' => array( 'GET', array(), false ),
	'service-invalid' => array( 'GET', array(), false ),
	'unknown'         => array( 'GET', array(), false ),
	'disabled'        => array( 'GET', array(), false ),
	'other-domain'    => array( 'GET', array(), false ),
	'user-denied'     => array( 'GET', array(), false ),
	'later-denied'    => array( 'GET', array(), false ),
	'login-page'      => array( 'GET', array(), false ),
	'ajax'            => array( 'GET', array(), false ),
	'cron'            => array( 'GET', array(), false ),
	'xmlrpc'          => array( 'GET', array(), false ),
	'recovery'        => array( 'GET', array(), false ),
	'recovery-selected' => array( 'GET', array(), false ),
	'recovery-upn'      => array( 'GET', array(), false ),
	'recovery-domain'   => array( 'GET', array(), false ),
	'recovery-off'      => array( 'GET', array(), 'pro' === $edition ),
	'recovery-demoted'  => array( 'GET', array(), 'pro' === $edition ),
);
foreach ( $cases as $scenario => $case ) {
	$response = $request( 'sll-test-sso.php?sll_case=' . $scenario, $case[1], true, $case[0] );
	$body     = $response['body'];
	$check( 200 === $response['code'] && $edition === ( $body['edition'] ?? null ) && $scenario === ( $body['scenario'] ?? null ), $scenario . ': the HTTP request loads the expected test configuration.' );
	$check( ( $case[2] ? 'alice' : '' ) === ( $body['user'] ?? null ) && $case[2] === ( array() !== $response['cookies'] ), $scenario . ': login and auth cookies follow the access result.' );
	if ( in_array( $scenario, array( 'recovery-selected', 'recovery-upn', 'recovery-domain' ), true ) ) {
		$check( 0 === ( $body['account_loaded'] ?? null ), $scenario . ': a directory identity cannot run recovery account update hooks.' );
	}
	if ( 'password-off' === $scenario ) {
		$check( false === $body['password_hook'] && ( 'pro' === $edition ) === $body['sso_hook'], 'SSO can run while the LDAP password hook is off.' );
	}
	if ( in_array( $scenario, array( 'sso-off', 'recovery', 'license-expired', 'license-inactive', 'license-missing' ), true ) ) {
		$check( false === $body['sso_hook'], $scenario . ': no server SSO hook is registered.' );
	}
	if ( 'recovery' === $scenario ) {
		$check( false === $body['password_hook'], 'The recovery constant also disables LDAP password hooks.' );
	}
}
foreach ( array( 'POST request' => array( 'POST', array() ), 'JSON request' => array( 'GET', array( 'Accept: application/json' ) ) ) as $label => $case ) {
	$response = $request( 'sll-test-sso.php', $case[1], true, $case[0] );
	$check( 200 === $response['code'] && '' === ( $response['body']['user'] ?? null ) && array() === $response['cookies'], $label . ' cannot start server SSO.' );
}

$cookie   = $cli( 'echo wp_json_encode(array("cookie" => LOGGED_IN_COOKIE . "=" . wp_generate_auth_cookie(get_user_by("login", "testadmin")->ID, time() + 600, "logged_in")));' );
$response = $request( 'sll-test-sso.php', array( 'Cookie: ' . $cookie['cookie'] ), true );
$check( 200 === $response['code'] && 'testadmin' === ( $response['body']['user'] ?? null ) && array() === $response['cookies'], 'SSO cannot replace an existing WordPress login.' );

$result = $cli( 'echo wp_json_encode(array("cli" => WP_CLI, "user" => (string) wp_get_current_user()->user_login));', true );
$check( true === $result['cli'] && '' === $result['user'], 'Server SSO does not start a WP-CLI session.' );
echo 'HTTP SSO checks passed: ' . $edition . ', ' . $checks . ".\n";
