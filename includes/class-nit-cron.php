<?php
/**
 * NIT_Cron — WP-Cron scheduling for all fetch jobs.
 *
 * @package Nahnu_Install_Tracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NIT_Cron
 */
class NIT_Cron {

	/**
	 * Valid schedule keys mapped to interval/display pairs.
	 *
	 * @return array
	 */
	public static function intervals() {
		return array(
			'nit_1hour'   => array(
				'interval' => HOUR_IN_SECONDS,
				'display'  => __( 'Every hour', 'nahnu-install-tracker' ),
			),
			'nit_6hours'  => array(
				'interval' => 6 * HOUR_IN_SECONDS,
				'display'  => __( 'Every 6 hours', 'nahnu-install-tracker' ),
			),
			'nit_12hours' => array(
				'interval' => 12 * HOUR_IN_SECONDS,
				'display'  => __( 'Every 12 hours', 'nahnu-install-tracker' ),
			),
			'daily'       => array(
				'interval' => DAY_IN_SECONDS,
				'display'  => __( 'Every 24 hours', 'nahnu-install-tracker' ),
			),
		);
	}

	/**
	 * Return the currently saved schedule key, defaulting to 'daily'.
	 *
	 * @return string
	 */
	public static function current_interval() {
		$saved = get_option( NIT_OPTION_INTERVAL, 'daily' );
		return array_key_exists( $saved, self::intervals() ) ? $saved : 'daily';
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules',                        array( __CLASS__, 'add_schedules' ) );
		add_action( NIT_CRON_HOOK,                           array( __CLASS__, 'run_all' ) );
		add_action( 'update_option_' . NIT_OPTION_INTERVAL,  array( __CLASS__, 'reschedule' ), 10, 0 );
	}

	/**
	 * Register custom cron intervals.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_schedules( $schedules ) {
		foreach ( self::intervals() as $key => $args ) {
			if ( ! isset( $schedules[ $key ] ) ) {
				$schedules[ $key ] = $args;
			}
		}
		return $schedules;
	}

	/**
	 * Run all fetch jobs — called by cron and the manual refresh button.
	 */
	public static function run_all() {
		NIT_Fetcher::fetch_all();
		NIT_WP_Version::fetch_all();
		NIT_GH_Version::fetch_all();
	}

	/**
	 * Reschedule the cron event using the currently saved interval.
	 * Called on activation and whenever the interval option changes.
	 */
	public static function reschedule() {
		wp_unschedule_hook( NIT_CRON_HOOK );
		wp_schedule_event( time(), self::current_interval(), NIT_CRON_HOOK );
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		self::reschedule();
	}

	/**
	 * Plugin deactivation — clear all scheduled instances.
	 */
	public static function deactivate() {
		wp_unschedule_hook( NIT_CRON_HOOK );
	}
}
