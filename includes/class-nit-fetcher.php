<?php
/**
 * NIT_Fetcher — fetches plugin data from the WordPress.org Plugins API.
 *
 * @package Nahnu_Install_Tracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NIT_Fetcher
 */
class NIT_Fetcher {

	/**
	 * Fetch install data for all configured slugs from the WP.org API.
	 * Saves results to the database and returns the full data array.
	 *
	 * @return array Keyed by plugin slug.
	 */
	public static function fetch_all() {
		$slugs = get_option( NIT_OPTION_SLUGS, array() );

		if ( empty( $slugs ) || ! is_array( $slugs ) ) {
			return array();
		}

		$existing = get_option( NIT_OPTION_DATA, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$results = array();

		foreach ( $slugs as $slug ) {
			$slug = sanitize_title( $slug );
			if ( '' === $slug ) {
				continue;
			}

			$data = self::fetch_single( $slug );

			if ( is_wp_error( $data ) ) {
				// Preserve last known data; surface the error message.
				$results[ $slug ]                = isset( $existing[ $slug ] ) ? $existing[ $slug ] : array();
				$results[ $slug ]['fetch_error'] = $data->get_error_message();
			} else {
				$prev = isset( $existing[ $slug ]['active_installs'] ) ? (int) $existing[ $slug ]['active_installs'] : 0;

				$results[ $slug ] = array(
					'name'            => sanitize_text_field( $data['name'] ),
					'slug'            => $slug,
					'active_installs' => (int) $data['active_installs'],
					'installs_delta'  => (int) $data['active_installs'] - $prev,
					'version'         => sanitize_text_field( $data['version'] ),
					'downloaded'      => (int) $data['downloaded'],
					'last_updated'    => sanitize_text_field( $data['last_updated'] ),
					'rating'          => (float) $data['rating'],
					'num_ratings'     => (int) $data['num_ratings'],
					'fetch_error'     => '',
				);
			}
		}

		update_option( NIT_OPTION_DATA,    $results,                false );
		update_option( NIT_OPTION_UPDATED, current_time( 'mysql' ), false );

		return $results;
	}

	/**
	 * Fetch a single plugin's data from the WP.org Plugins API.
	 *
	 * Uses plugins_api() when available (admin context); falls back to a direct
	 * wp_remote_post() call so it works from WP-Cron too.
	 *
	 * @param string $slug Plugin slug.
	 * @return array|WP_Error Associative array of plugin data, or WP_Error on failure.
	 */
	public static function fetch_single( $slug ) {
		// Use the built-in wrapper when we're in an admin context.
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		$fields = array(
			'active_installs' => true,
			'downloaded'      => true,
			'last_updated'    => true,
			'ratings'         => true,
			'num_ratings'     => true,
			'versions'        => false,
			'screenshots'     => false,
			'tags'            => false,
			'sections'        => false,
		);

		$response = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => $fields,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! is_object( $response ) ) {
			return new WP_Error(
				'nit_api_error',
				esc_html__( 'Unexpected response from WordPress.org API.', 'nahnu-install-tracker' )
			);
		}

		return array(
			'name'            => isset( $response->name )            ? $response->name            : $slug,
			'version'         => isset( $response->version )         ? $response->version         : '',
			'active_installs' => isset( $response->active_installs ) ? $response->active_installs : 0,
			'downloaded'      => isset( $response->downloaded )      ? $response->downloaded      : 0,
			'last_updated'    => isset( $response->last_updated )    ? $response->last_updated    : '',
			'rating'          => isset( $response->rating )          ? $response->rating          : 0,
			'num_ratings'     => isset( $response->num_ratings )     ? $response->num_ratings     : 0,
		);
	}

	/**
	 * Format an active install count the same way WordPress.org does (e.g. "1,000+").
	 *
	 * The WP.org API returns 0 when a plugin has fewer than 10 active installs.
	 * In that case we return "Fewer than 10" to match the WP.org directory display.
	 *
	 * @param int $count Raw install count from the API.
	 * @return string Formatted string.
	 */
	public static function format_installs( $count ) {
		$count = (int) $count;

		// WP.org returns 0 to mean "fewer than 10 active installs".
		if ( 0 === $count ) {
			return __( 'Fewer than 10', 'nahnu-install-tracker' );
		}

		$thresholds = array( 1000000, 100000, 10000, 1000, 100, 10 );

		foreach ( $thresholds as $threshold ) {
			if ( $count >= $threshold ) {
				$rounded = (int) floor( $count / $threshold ) * $threshold;
				return number_format_i18n( $rounded ) . '+';
			}
		}

		return number_format_i18n( $count ) . '+';
	}
}
