<?php
/**
 * NIT_WP_Version — fetches current plugin versions from the WordPress.org API.
 *
 * @package Nahnu_Install_Tracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NIT_WP_Version
 */
class NIT_WP_Version {

	/**
	 * Fetch version data for all configured WP.org slugs.
	 * Saves to DB and returns the data array.
	 *
	 * @return array
	 */
	public static function fetch_all() {
		$slugs = get_option( NIT_OPTION_WP_SLUGS, array() );

		if ( empty( $slugs ) || ! is_array( $slugs ) ) {
			return array();
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		$results = array();

		foreach ( $slugs as $slug ) {
			$slug = sanitize_title( $slug );
			if ( '' === $slug ) {
				continue;
			}

			$response = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array(
						'version'      => true,
						'last_updated' => true,
						'sections'     => false,
						'screenshots'  => false,
						'tags'         => false,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$results[ $slug ] = array(
					'slug'        => $slug,
					'name'        => $slug,
					'version'     => '',
					'last_updated'=> '',
					'fetch_error' => $response->get_error_message(),
					'fetched_at'  => current_time( 'mysql' ),
				);
			} else {
				$results[ $slug ] = array(
					'slug'         => $slug,
					'name'         => isset( $response->name )         ? sanitize_text_field( $response->name )         : $slug,
					'version'      => isset( $response->version )      ? sanitize_text_field( $response->version )      : '',
					'last_updated' => isset( $response->last_updated ) ? sanitize_text_field( $response->last_updated ) : '',
					'fetch_error'  => '',
					'fetched_at'   => current_time( 'mysql' ),
				);
			}
		}

		update_option( NIT_OPTION_WP_DATA, $results, false );

		return $results;
	}
}
