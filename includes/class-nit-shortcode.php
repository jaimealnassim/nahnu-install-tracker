<?php
/**
 * NIT_Shortcode — all frontend shortcodes.
 *
 * [nit_installs slug="..."]    — WP.org active install count, e.g. "10,000+"
 * [nit_wp_version slug="..."]  — WP.org plugin version, e.g. "2.1.4"
 * [nit_gh_version id="..."]    — GitHub version, e.g. "1.0.3"
 *
 * All shortcodes return plain text only — no wrapper HTML.
 *
 * @package Nahnu_Install_Tracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NIT_Shortcode
 */
class NIT_Shortcode {

	/**
	 * Register all shortcodes.
	 */
	public static function init() {
		add_shortcode( 'nit_installs',   array( __CLASS__, 'render_installs' ) );
		add_shortcode( 'nit_wp_version', array( __CLASS__, 'render_wp_version' ) );
		add_shortcode( 'nit_gh_version', array( __CLASS__, 'render_gh_version' ) );
	}

	/**
	 * [nit_installs slug="plugin-slug"]
	 *
	 * Returns the formatted active install count, e.g. "10,000+".
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_installs( $atts ) {
		$atts = shortcode_atts( array( 'slug' => '' ), $atts, 'nit_installs' );
		$slug = sanitize_title( $atts['slug'] );

		if ( '' === $slug ) {
			return '';
		}

		$data = get_option( NIT_OPTION_DATA, array() );
		if ( ! is_array( $data ) || ! array_key_exists( $slug, $data ) ) {
			return '';
		}

		$entry = $data[ $slug ];
		if ( ! empty( $entry['fetch_error'] ) || ! isset( $entry['active_installs'] ) ) {
			return '';
		}

		return esc_html( NIT_Fetcher::format_installs( (int) $entry['active_installs'] ) );
	}

	/**
	 * [nit_wp_version slug="plugin-slug"]
	 *
	 * Returns the current WP.org plugin version string, e.g. "2.1.4".
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_wp_version( $atts ) {
		$atts = shortcode_atts( array( 'slug' => '' ), $atts, 'nit_wp_version' );
		$slug = sanitize_title( $atts['slug'] );

		if ( '' === $slug ) {
			return '';
		}

		$data = get_option( NIT_OPTION_WP_DATA, array() );
		if ( ! is_array( $data ) || ! array_key_exists( $slug, $data ) ) {
			return '';
		}

		$entry = $data[ $slug ];
		if ( ! empty( $entry['fetch_error'] ) || empty( $entry['version'] ) ) {
			return '';
		}

		return esc_html( $entry['version'] );
	}

	/**
	 * [nit_gh_version id="source-id"]
	 *
	 * Returns the GitHub version string, e.g. "1.0.3".
	 * The `id` is the sanitized label set in the GitHub Version tab.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_gh_version( $atts ) {
		$atts = shortcode_atts( array( 'id' => '' ), $atts, 'nit_gh_version' );
		$id   = sanitize_key( $atts['id'] );

		if ( '' === $id ) {
			return '';
		}

		$data = get_option( NIT_OPTION_GH_DATA, array() );
		if ( ! is_array( $data ) || ! array_key_exists( $id, $data ) ) {
			return '';
		}

		$entry = $data[ $id ];
		if ( ! empty( $entry['fetch_error'] ) || empty( $entry['version'] ) ) {
			return '';
		}

		return esc_html( $entry['version'] );
	}
}
