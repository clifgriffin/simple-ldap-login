<?php
/**
 * Check HTTP responses before the SDK reads license data.
 *
 * @package SimpleLdapLogin
 */

namespace SimpleLdapLogin;

use PaidCommunities\WordPress\HttpClient\WordPressClient;

/** Use the SDK services with strict WordPress HTTP checks. */
final class PaidCommunities_Client extends WordPressClient {

	/**
	 * Send one request. Never log credentials or service error bodies.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   SDK endpoint.
	 * @param array  $request Request data.
	 * @param array  $opts   SDK request options.
	 * @throws \RuntimeException When the service does not return valid data.
	 * @return array Response data.
	 */
	public function request( $method, $path, $request = array(), $opts = array() ): array {
		$url  = $this->buildUrl( $path );
		$args = array(
			'method'              => strtoupper( $method ),
			'headers'             => array_merge( $this->getHeaders(), $opts['headers'] ?? array() ),
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => 65536,
		);
		if ( $request ) {
			if ( 'get' === strtolower( $method ) ) {
				$url = add_query_arg( $request, $url );
			} else {
				$args['body'] = wp_json_encode( $request );
			}
		}
		$response = wp_safe_remote_request( $url, $args );
		$code     = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || ! in_array( $code, array( 200, 201, 204 ), true ) ) {
			throw new \RuntimeException( 'The license request failed.' );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( 'delete' === strtolower( $method ) && ( 204 === $code || in_array( trim( $body ), array( '', '{}' ), true ) ) ) {
			return array();
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || array_is_list( $data ) || isset( $data['error'] ) || isset( $data['errors'] ) ) {
			throw new \RuntimeException( 'The license response is invalid.' );
		}
		return $data;
	}
}
