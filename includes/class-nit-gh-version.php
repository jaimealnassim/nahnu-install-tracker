<?php
/**
 * NIT_GH_Version — fetches version strings from GitHub.
 *
 * Supports two URL types:
 *   1. GitHub Releases API  — https://api.github.com/repos/{owner}/{repo}/releases/latest
 *      Reads the `tag_name` field, strips a leading "v" (e.g. "v1.2.3" → "1.2.3").
 *
 *   2. Raw file URL         — https://raw.githubusercontent.com/{owner}/{repo}/{branch}/{file}
 *      Expects valid JSON. Reads the field named in `version_key` (default: "version").
 *
 * @package Nahnu_Install_Tracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NIT_GH_Version
 */
class NIT_GH_Version {

	/**
	 * Fetch version data for all configured GitHub sources.
	 * Saves to DB and returns the data array.
	 *
	 * @return array
	 */
	public static function fetch_all() {
		$sources = get_option( NIT_OPTION_GH_SOURCES, array() );

		if ( empty( $sources ) || ! is_array( $sources ) ) {
			return array();
		}

		$results = array();

		foreach ( $sources as $id => $source ) {
			$id    = sanitize_key( $id );
			$label = isset( $source['label'] )       ? sanitize_text_field( $source['label'] )   : $id;
			$url   = isset( $source['url'] )         ? esc_url_raw( $source['url'] )              : '';
			$key   = isset( $source['version_key'] ) ? sanitize_text_field( $source['version_key'] ) : 'version';

			if ( '' === $url ) {
				continue;
			}

			$version = self::fetch_single( $url, $key );

			if ( is_wp_error( $version ) ) {
				$results[ $id ] = array(
					'id'          => $id,
					'label'       => $label,
					'url'         => $url,
					'version'     => '',
					'fetch_error' => $version->get_error_message(),
					'fetched_at'  => current_time( 'mysql' ),
				);
			} else {
				$results[ $id ] = array(
					'id'          => $id,
					'label'       => $label,
					'url'         => $url,
					'version'     => sanitize_text_field( $version ),
					'fetch_error' => '',
					'fetched_at'  => current_time( 'mysql' ),
				);
			}
		}

		update_option( NIT_OPTION_GH_DATA, $results, false );

		return $results;
	}

	/**
	 * Fetch a single version string from a URL.
	 *
	 * Auto-detects GitHub Releases API vs raw file URL.
	 *
	 * @param string $url         Full URL to fetch.
	 * @param string $version_key JSON key to read for raw files (default: "version").
	 * @return string|WP_Error Version string on success, WP_Error on failure.
	 */
	public static function fetch_single( $url, $version_key = 'version' ) {
		$is_releases_api = ( false !== strpos( $url, 'api.github.com' ) );

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			),
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'nit_gh_http_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'HTTP %d from GitHub.', 'nahnu-install-tracker' ),
					$code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data || ! is_array( $data ) ) {
			return new WP_Error(
				'nit_gh_parse_error',
				__( 'Could not parse JSON response.', 'nahnu-install-tracker' )
			);
		}

		if ( $is_releases_api ) {
			// GitHub Releases API: version is in `tag_name`, strip leading "v".
			if ( ! isset( $data['tag_name'] ) ) {
				return new WP_Error(
					'nit_gh_missing_key',
					__( 'No tag_name field in GitHub Releases response.', 'nahnu-install-tracker' )
				);
			}
			return ltrim( $data['tag_name'], 'vV' );
		}

		// Raw file: read user-specified key.
		if ( ! isset( $data[ $version_key ] ) ) {
			return new WP_Error(
				'nit_gh_missing_key',
				sprintf(
					/* translators: %s: JSON key name */
					__( 'Key "%s" not found in JSON response.', 'nahnu-install-tracker' ),
					$version_key
				)
			);
		}

		return (string) $data[ $version_key ];
	}
}
