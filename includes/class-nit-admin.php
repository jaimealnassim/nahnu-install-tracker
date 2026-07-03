<?php
/**
 * NIT_Admin — tabbed settings page.
 *
 * Tabs:
 *   1. install-tracker  — WP.org active install counts + fetch interval
 *   2. wp-version       — WP.org plugin version tracker
 *   3. github-version   — GitHub release / raw JSON version tracker
 *
 * @package Nahnu_Install_Tracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NIT_Admin
 */
class NIT_Admin {

	/** @var string[] Valid tab slugs. */
	private static $tabs = array( 'install-tracker', 'wp-version', 'github-version', 'fetch-settings' );

	/**
	 * Register all admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu',                            array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init',                            array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_nit_manual_fetch',           array( __CLASS__, 'handle_manual_fetch' ) );
		add_action( 'admin_post_nit_save_gh_sources',        array( __CLASS__, 'handle_save_gh_sources' ) );
		add_action( 'admin_enqueue_scripts',                 array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Add settings page under Settings menu.
	 */
	public static function add_menu() {
		add_options_page(
			esc_html__( 'Nahnu Install and Version Tracker', 'nahnu-install-tracker' ),
			esc_html__( 'Install Tracker', 'nahnu-install-tracker' ),
			'manage_options',
			'nahnu-install-tracker',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register Settings API options (tabs 1 & 2; tab 3 uses its own handler).
	 */
	public static function register_settings() {
		// Tab 1 — slugs.
		register_setting( 'nit_group_install', NIT_OPTION_SLUGS, array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_slugs' ),
			'default'           => array(),
			'show_in_rest'      => false,
		) );

		// Tab 1 — interval.
		register_setting( 'nit_group_install', NIT_OPTION_INTERVAL, array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_interval' ),
			'default'           => 'daily',
			'show_in_rest'      => false,
		) );

		// Tab 2 — WP version slugs.
		register_setting( 'nit_group_wp_version', NIT_OPTION_WP_SLUGS, array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_slugs' ),
			'default'           => array(),
			'show_in_rest'      => false,
		) );
	}

	// -------------------------------------------------------------------------
	// Sanitizers
	// -------------------------------------------------------------------------

	/**
	 * Sanitize a newline-separated list of plugin slugs.
	 *
	 * @param mixed $input Raw textarea value.
	 * @return array
	 */
	public static function sanitize_slugs( $input ) {
		$lines = is_array( $input ) ? $input : explode( "\n", (string) $input );
		$slugs = array();
		foreach ( $lines as $line ) {
			$slug = sanitize_title( trim( $line ) );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}
		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Sanitize the fetch interval.
	 *
	 * @param string $input Raw value.
	 * @return string
	 */
	public static function sanitize_interval( $input ) {
		return array_key_exists( $input, NIT_Cron::intervals() ) ? $input : 'daily';
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue admin stylesheet on our settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_nahnu-install-tracker' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'nit-admin', NIT_URL . 'assets/css/admin.css', array(), NIT_VERSION );
	}

	// -------------------------------------------------------------------------
	// Form handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle the "Refresh Now" button — runs all fetch jobs.
	 */
	public static function handle_manual_fetch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nahnu-install-tracker' ) );
		}
		check_admin_referer( 'nit_manual_fetch' );

		NIT_Cron::run_all();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'install-tracker';
		// phpcs:enable

		wp_safe_redirect( add_query_arg( array(
			'page'    => 'nahnu-install-tracker',
			'tab'     => $tab,
			'fetched' => '1',
		), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Handle saving GitHub sources (custom form, not Settings API).
	 */
	public static function handle_save_gh_sources() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nahnu-install-tracker' ) );
		}
		check_admin_referer( 'nit_save_gh_sources' );

		$sources = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$labels = isset( $_POST['nit_gh_label'] ) ? (array) $_POST['nit_gh_label'] : array();
		$urls   = isset( $_POST['nit_gh_url'] )   ? (array) $_POST['nit_gh_url']   : array();
		$keys   = isset( $_POST['nit_gh_key'] )   ? (array) $_POST['nit_gh_key']   : array();
		// phpcs:enable

		foreach ( $labels as $i => $label ) {
			$label = sanitize_text_field( $label );
			$url   = isset( $urls[ $i ] ) ? esc_url_raw( trim( $urls[ $i ] ) ) : '';
			$key   = isset( $keys[ $i ] ) ? sanitize_text_field( trim( $keys[ $i ] ) ) : 'version';

			if ( '' === $label || '' === $url ) {
				continue;
			}

			// Derive a stable ID from the label.
			$id = sanitize_key( $label );
			// Avoid collisions by appending index if needed.
			if ( isset( $sources[ $id ] ) ) {
				$id = $id . '_' . $i;
			}

			$sources[ $id ] = array(
				'label'       => $label,
				'url'         => $url,
				'version_key' => '' !== $key ? $key : 'version',
			);
		}

		update_option( NIT_OPTION_GH_SOURCES, $sources, false );

		wp_safe_redirect( add_query_arg( array(
			'page'            => 'nahnu-install-tracker',
			'tab'             => 'github-version',
			'settings-updated'=> '1',
		), admin_url( 'options-general.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Page rendering
	// -------------------------------------------------------------------------

	/**
	 * Return the active tab slug, validated against known tabs.
	 *
	 * @return string
	 */
	private static function current_tab() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
		// phpcs:enable
		return in_array( $tab, self::$tabs, true ) ? $tab : 'install-tracker';
	}

	/**
	 * Render the full settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = self::current_tab();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$show_fetched = isset( $_GET['fetched'] ) && '1' === $_GET['fetched'];
		$show_saved   = isset( $_GET['settings-updated'] ) && '1' === $_GET['settings-updated'];
		// phpcs:enable

		$base_url = admin_url( 'options-general.php?page=nahnu-install-tracker' );
		?>
		<div class="wrap nit-wrap">
			<h1><?php esc_html_e( 'Nahnu Install and Version Tracker', 'nahnu-install-tracker' ); ?></h1>

			<?php if ( $show_fetched ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Data refreshed successfully.', 'nahnu-install-tracker' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( $show_saved ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'nahnu-install-tracker' ); ?></p>
				</div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper nit-tabs">
				<a href="<?php echo esc_url( $base_url . '&tab=install-tracker' ); ?>"
				   class="nav-tab<?php echo 'install-tracker' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Install Tracker', 'nahnu-install-tracker' ); ?>
				</a>
				<a href="<?php echo esc_url( $base_url . '&tab=wp-version' ); ?>"
				   class="nav-tab<?php echo 'wp-version' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'WP.org Version', 'nahnu-install-tracker' ); ?>
				</a>
				<a href="<?php echo esc_url( $base_url . '&tab=github-version' ); ?>"
				   class="nav-tab<?php echo 'github-version' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'GitHub Version', 'nahnu-install-tracker' ); ?>
				</a>
				<a href="<?php echo esc_url( $base_url . '&tab=fetch-settings' ); ?>"
				   class="nav-tab<?php echo 'fetch-settings' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Fetch Settings', 'nahnu-install-tracker' ); ?>
				</a>
			</nav>

			<div class="nit-tab-content">
				<?php
				switch ( $tab ) {
					case 'wp-version':
						self::render_tab_wp_version();
						break;
					case 'github-version':
						self::render_tab_github_version();
						break;
					case 'fetch-settings':
						self::render_tab_fetch_settings();
						break;
					default:
						self::render_tab_install_tracker();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: Install Tracker
	// -------------------------------------------------------------------------

	/**
	 * Render the Install Tracker tab.
	 */
	private static function render_tab_install_tracker() {
		$slugs       = get_option( NIT_OPTION_SLUGS, array() );
		$data        = get_option( NIT_OPTION_DATA, array() );
		$last_update = get_option( NIT_OPTION_UPDATED, '' );
		$next_cron   = wp_next_scheduled( NIT_CRON_HOOK );

		if ( ! is_array( $slugs ) ) { $slugs = array(); }
		if ( ! is_array( $data )  ) { $data  = array(); }
		?>
		<div class="nit-grid">
			<div class="nit-card">
				<h2><?php esc_html_e( 'Tracked Plugins', 'nahnu-install-tracker' ); ?></h2>
				<p class="description"><?php esc_html_e( 'One WordPress.org plugin slug per line.', 'nahnu-install-tracker' ); ?></p>

				<form method="post" action="options.php">
					<?php settings_fields( 'nit_group_install' ); ?>
					<textarea name="<?php echo esc_attr( NIT_OPTION_SLUGS ); ?>" id="nit-slugs" rows="8"
					          class="large-text code"
					          placeholder="nahnu-asset-scanner"><?php echo esc_textarea( implode( "\n", $slugs ) ); ?></textarea>
					<?php submit_button( __( 'Save Slugs', 'nahnu-install-tracker' ) ); ?>
				</form>

				<hr>
				<?php self::render_refresh_controls( 'install-tracker', $last_update, $next_cron ); ?>
			</div>

			<div class="nit-card">
				<h2><?php esc_html_e( 'Current Data', 'nahnu-install-tracker' ); ?></h2>
				<?php if ( empty( $data ) ) : ?>
					<p><?php esc_html_e( 'No data yet. Add slugs and click "Refresh Now".', 'nahnu-install-tracker' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped nit-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Plugin', 'nahnu-install-tracker' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Active Installs', 'nahnu-install-tracker' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Change', 'nahnu-install-tracker' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Downloads', 'nahnu-install-tracker' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Version', 'nahnu-install-tracker' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data as $slug => $info ) : ?>
								<tr>
									<td>
										<?php if ( ! empty( $info['fetch_error'] ) ) : ?>
											<strong><?php echo esc_html( $slug ); ?></strong>
											<br><span class="nit-error"><?php echo esc_html( $info['fetch_error'] ); ?></span>
										<?php else : ?>
											<a href="<?php echo esc_url( 'https://wordpress.org/plugins/' . $slug . '/' ); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( isset( $info['name'] ) ? $info['name'] : $slug ); ?>
											</a>
										<?php endif; ?>
									</td>
									<td class="nit-count"><?php echo esc_html( NIT_Fetcher::format_installs( isset( $info['active_installs'] ) ? $info['active_installs'] : 0 ) ); ?></td>
									<td>
										<?php
										$delta = isset( $info['installs_delta'] ) ? (int) $info['installs_delta'] : 0;
										if ( $delta > 0 ) {
											echo '<span class="nit-delta nit-up">+' . esc_html( number_format_i18n( $delta ) ) . '</span>';
										} elseif ( $delta < 0 ) {
											echo '<span class="nit-delta nit-down">' . esc_html( number_format_i18n( $delta ) ) . '</span>';
										} else {
											echo '<span class="nit-delta">&mdash;</span>';
										}
										?>
									</td>
									<td><?php echo esc_html( number_format_i18n( isset( $info['downloaded'] ) ? (int) $info['downloaded'] : 0 ) ); ?></td>
									<td><?php echo esc_html( isset( $info['version'] ) ? $info['version'] : '&mdash;' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<hr>
					<h3><?php esc_html_e( 'Shortcode', 'nahnu-install-tracker' ); ?></h3>
					<code>[nit_installs slug="nahnu-asset-scanner"]</code>
					<p class="description"><?php esc_html_e( 'Outputs the bare install count, e.g. 10,000+', 'nahnu-install-tracker' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: WP.org Version
	// -------------------------------------------------------------------------

	/**
	 * Render the WP.org Version tab.
	 */
	private static function render_tab_wp_version() {
		$slugs = get_option( NIT_OPTION_WP_SLUGS, array() );
		$data  = get_option( NIT_OPTION_WP_DATA,  array() );
		$last_update = get_option( NIT_OPTION_UPDATED, '' );
		$next_cron   = wp_next_scheduled( NIT_CRON_HOOK );

		if ( ! is_array( $slugs ) ) { $slugs = array(); }
		if ( ! is_array( $data )  ) { $data  = array(); }
		?>
		<div class="nit-grid">
			<div class="nit-card">
				<h2><?php esc_html_e( 'WP.org Version Tracker', 'nahnu-install-tracker' ); ?></h2>
				<p class="description"><?php esc_html_e( 'One WordPress.org plugin slug per line. Fetches the current stable version.', 'nahnu-install-tracker' ); ?></p>

				<form method="post" action="options.php">
					<?php settings_fields( 'nit_group_wp_version' ); ?>
					<textarea name="<?php echo esc_attr( NIT_OPTION_WP_SLUGS ); ?>" id="nit-wp-slugs" rows="8"
					          class="large-text code"
					          placeholder="nahnu-asset-scanner"><?php echo esc_textarea( implode( "\n", $slugs ) ); ?></textarea>
					<?php submit_button( __( 'Save Slugs', 'nahnu-install-tracker' ) ); ?>
				</form>

				<hr>
				<?php self::render_refresh_controls( 'wp-version', $last_update, $next_cron ); ?>
			</div>

			<div class="nit-card">
				<h2><?php esc_html_e( 'Current Versions', 'nahnu-install-tracker' ); ?></h2>
				<?php if ( empty( $data ) ) : ?>
					<p><?php esc_html_e( 'No data yet. Add slugs and click "Refresh Now".', 'nahnu-install-tracker' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped nit-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Plugin', 'nahnu-install-tracker' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Version', 'nahnu-install-tracker' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Last Updated', 'nahnu-install-tracker' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data as $slug => $info ) : ?>
								<tr>
									<td>
										<?php if ( ! empty( $info['fetch_error'] ) ) : ?>
											<strong><?php echo esc_html( $slug ); ?></strong>
											<br><span class="nit-error"><?php echo esc_html( $info['fetch_error'] ); ?></span>
										<?php else : ?>
											<a href="<?php echo esc_url( 'https://wordpress.org/plugins/' . $slug . '/' ); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( isset( $info['name'] ) ? $info['name'] : $slug ); ?>
											</a>
										<?php endif; ?>
									</td>
									<td class="nit-count"><?php echo esc_html( isset( $info['version'] ) ? $info['version'] : '&mdash;' ); ?></td>
									<td><?php echo esc_html( isset( $info['last_updated'] ) ? $info['last_updated'] : '&mdash;' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<hr>
					<h3><?php esc_html_e( 'Shortcode', 'nahnu-install-tracker' ); ?></h3>
					<code>[nit_wp_version slug="nahnu-asset-scanner"]</code>
					<p class="description"><?php esc_html_e( 'Outputs the bare version number, e.g. 2.1.4', 'nahnu-install-tracker' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: GitHub Version
	// -------------------------------------------------------------------------

	/**
	 * Render the GitHub Version tab.
	 *
	 * Layout: full-width form card on top, then side-by-side results + refresh below.
	 */
	private static function render_tab_github_version() {
		$sources     = get_option( NIT_OPTION_GH_SOURCES, array() );
		$data        = get_option( NIT_OPTION_GH_DATA,    array() );
		$last_update = get_option( NIT_OPTION_UPDATED, '' );
		$next_cron   = wp_next_scheduled( NIT_CRON_HOOK );

		if ( ! is_array( $sources ) ) { $sources = array(); }
		if ( ! is_array( $data )    ) { $data    = array(); }
		?>

		<?php /* Top: full-width entry form */ ?>
		<div class="nit-card nit-gh-form-card">
			<h2><?php esc_html_e( 'GitHub Version Tracker', 'nahnu-install-tracker' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Add one row per source. Paste a GitHub Releases API URL or a raw JSON file URL. The "Version key" column is only used for raw files (ignored for Releases API).', 'nahnu-install-tracker' ); ?>
			</p>

			<div class="nit-gh-url-hints">
				<span><strong><?php esc_html_e( 'Releases API:', 'nahnu-install-tracker' ); ?></strong> <code>https://api.github.com/repos/{owner}/{repo}/releases/latest</code></span>
				<span><strong><?php esc_html_e( 'Raw file:', 'nahnu-install-tracker' ); ?></strong> <code>https://raw.githubusercontent.com/{owner}/{repo}/{branch}/version.json</code></span>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nit_save_gh_sources">
				<?php wp_nonce_field( 'nit_save_gh_sources' ); ?>

				<table class="widefat nit-gh-table" id="nit-gh-sources">
					<thead>
						<tr>
							<th scope="col" class="nit-col-label"><?php esc_html_e( 'Label', 'nahnu-install-tracker' ); ?></th>
							<th scope="col"><?php esc_html_e( 'JSON URL', 'nahnu-install-tracker' ); ?></th>
							<th scope="col" class="nit-col-key"><?php esc_html_e( 'Version key', 'nahnu-install-tracker' ); ?></th>
							<th scope="col" class="nit-col-remove"></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$rows = ! empty( $sources ) ? array_values( $sources ) : array( array( 'label' => '', 'url' => '', 'version_key' => 'version' ) );
						foreach ( $rows as $src ) :
						?>
							<tr class="nit-gh-row">
								<td>
									<input type="text" name="nit_gh_label[]" class="widefat"
									       value="<?php echo esc_attr( $src['label'] ); ?>"
									       placeholder="<?php esc_attr_e( 'My Plugin', 'nahnu-install-tracker' ); ?>">
								</td>
								<td>
									<input type="url" name="nit_gh_url[]" class="widefat"
									       value="<?php echo esc_url( $src['url'] ); ?>"
									       placeholder="https://api.github.com/repos/owner/repo/releases/latest">
								</td>
								<td>
									<input type="text" name="nit_gh_key[]" class="widefat"
									       value="<?php echo esc_attr( isset( $src['version_key'] ) ? $src['version_key'] : 'version' ); ?>"
									       placeholder="version">
								</td>
								<td>
									<button type="button" class="button nit-remove-row" aria-label="<?php esc_attr_e( 'Remove row', 'nahnu-install-tracker' ); ?>">&times;</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="nit-gh-actions">
					<button type="button" class="button" id="nit-add-gh-row">
						&#43; <?php esc_html_e( 'Add row', 'nahnu-install-tracker' ); ?>
					</button>
					<?php submit_button( __( 'Save Sources', 'nahnu-install-tracker' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>

		<?php /* Bottom: results + refresh side by side */ ?>
		<div class="nit-grid nit-gh-bottom">

			<div class="nit-card">
				<h2><?php esc_html_e( 'Current Versions', 'nahnu-install-tracker' ); ?></h2>
				<?php if ( empty( $data ) ) : ?>
					<p><?php esc_html_e( 'No data yet. Save your sources and click "Refresh Now".', 'nahnu-install-tracker' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped nit-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Label', 'nahnu-install-tracker' ); ?></th>
								<th scope="col" style="width:100px"><?php esc_html_e( 'Version', 'nahnu-install-tracker' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Last Fetched', 'nahnu-install-tracker' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data as $id => $info ) : ?>
								<tr>
									<td>
										<?php if ( ! empty( $info['fetch_error'] ) ) : ?>
											<strong><?php echo esc_html( isset( $info['label'] ) ? $info['label'] : $id ); ?></strong>
											<br><span class="nit-error"><?php echo esc_html( $info['fetch_error'] ); ?></span>
										<?php else : ?>
											<?php echo esc_html( isset( $info['label'] ) ? $info['label'] : $id ); ?>
										<?php endif; ?>
									</td>
									<td class="nit-count"><?php echo esc_html( ! empty( $info['version'] ) ? $info['version'] : '—' ); ?></td>
									<td><?php echo esc_html( ! empty( $info['fetched_at'] ) ? get_date_from_gmt( $info['fetched_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '—' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<hr>
					<h3><?php esc_html_e( 'Shortcode', 'nahnu-install-tracker' ); ?></h3>
					<code>[nit_gh_version id="my-plugin"]</code>
					<p class="description"><?php esc_html_e( 'The id is your label, lowercased with hyphens. Returns the bare version string.', 'nahnu-install-tracker' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="nit-card">
				<?php self::render_refresh_controls( 'github-version', $last_update, $next_cron ); ?>
			</div>

		</div>

		<script>
		(function(){
			var tbody = document.getElementById('nit-gh-sources').querySelector('tbody');

			function newRow() {
				var clone = tbody.querySelector('.nit-gh-row').cloneNode(true);
				clone.querySelectorAll('input').forEach(function(el){
					el.value = ( el.placeholder === 'version' ) ? 'version' : '';
				});
				return clone;
			}

			document.getElementById('nit-add-gh-row').addEventListener('click', function(){
				tbody.appendChild( newRow() );
				tbody.lastElementChild.querySelector('input').focus();
			});

			tbody.addEventListener('click', function(e){
				if ( ! e.target.classList.contains('nit-remove-row') ) { return; }
				var rows = tbody.querySelectorAll('.nit-gh-row');
				if ( rows.length > 1 ) {
					e.target.closest('.nit-gh-row').remove();
				} else {
					// Last row: clear values but keep the row.
					rows[0].querySelectorAll('input').forEach(function(el){
						el.value = ( el.name.indexOf('nit_gh_key') !== -1 ) ? 'version' : '';
					});
				}
			});
		})();
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Shared UI helpers
	// -------------------------------------------------------------------------

	/**
	 * Render the auto-fetch frequency selector + manual refresh button.
	 *
	 * Shown on all three tabs. The interval setting is shared across all tabs
	 * since one cron job fetches everything.
	 *
	 * @param string    $tab         Current tab slug (returned as GET param after refresh).
	 * @param string    $last_update Last fetch timestamp (MySQL GMT), or empty string.
	 * @param int|false $next_cron   Unix timestamp of next scheduled run, or false.
	 */
	private static function render_refresh_controls( $tab, $last_update, $next_cron ) {
		?>
		<h3><?php esc_html_e( 'Manual Refresh', 'nahnu-install-tracker' ); ?></h3>

		<?php if ( $last_update ) : ?>
			<p class="description">
				<?php printf(
					/* translators: %s: formatted date/time */
					esc_html__( 'Last fetched: %s', 'nahnu-install-tracker' ),
					esc_html( get_date_from_gmt( $last_update, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
				); ?>
			</p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No data fetched yet.', 'nahnu-install-tracker' ); ?></p>
		<?php endif; ?>

		<?php if ( $next_cron ) : ?>
			<p class="description">
				<?php printf(
					/* translators: %s: formatted date/time of next scheduled run */
					esc_html__( 'Next auto-fetch: %s', 'nahnu-install-tracker' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_cron ) )
				); ?>
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nit_manual_fetch">
			<input type="hidden" name="tab"    value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( 'nit_manual_fetch' ); ?>
			<?php submit_button( __( 'Refresh Now', 'nahnu-install-tracker' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: Fetch Settings
	// -------------------------------------------------------------------------

	/**
	 * Render the Fetch Settings tab.
	 *
	 * Controls the shared auto-fetch interval and the manual refresh button.
	 * One cron job runs all three trackers.
	 */
	private static function render_tab_fetch_settings() {
		$last_update = get_option( NIT_OPTION_UPDATED, '' );
		$next_cron   = wp_next_scheduled( NIT_CRON_HOOK );

		// Preserve existing slugs so the hidden field doesn't blank them.
		$current_slugs = get_option( NIT_OPTION_SLUGS, array() );
		if ( ! is_array( $current_slugs ) ) {
			$current_slugs = array();
		}
		?>
		<div class="nit-grid">
			<div class="nit-card">
				<h2><?php esc_html_e( 'Auto-fetch Frequency', 'nahnu-install-tracker' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Controls how often all three trackers are refreshed automatically. One shared cron job runs Install Tracker, WP.org Version, and GitHub Version together.', 'nahnu-install-tracker' ); ?>
				</p>

				<form method="post" action="options.php">
					<?php settings_fields( 'nit_group_install' ); ?>
					<input type="hidden" name="<?php echo esc_attr( NIT_OPTION_SLUGS ); ?>"
					       value="<?php echo esc_attr( implode( "\n", $current_slugs ) ); ?>">

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="nit-interval-fetch"><?php esc_html_e( 'Fetch every', 'nahnu-install-tracker' ); ?></label>
							</th>
							<td>
								<select name="<?php echo esc_attr( NIT_OPTION_INTERVAL ); ?>" id="nit-interval-fetch">
									<?php foreach ( NIT_Cron::intervals() as $key => $args ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"
										        <?php selected( NIT_Cron::current_interval(), $key ); ?>>
											<?php echo esc_html( $args['display'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Frequency', 'nahnu-install-tracker' ) ); ?>
				</form>
			</div>

			<div class="nit-card">
				<h2><?php esc_html_e( 'Cron Status', 'nahnu-install-tracker' ); ?></h2>

				<?php if ( $last_update ) : ?>
					<p class="description">
						<?php printf(
							/* translators: %s: formatted date/time */
							esc_html__( 'Last fetched: %s', 'nahnu-install-tracker' ),
							esc_html( get_date_from_gmt( $last_update, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
						); ?>
					</p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No data fetched yet.', 'nahnu-install-tracker' ); ?></p>
				<?php endif; ?>

				<?php if ( $next_cron ) : ?>
					<p class="description">
						<?php printf(
							/* translators: %s: formatted date/time of next scheduled run */
							esc_html__( 'Next auto-fetch: %s', 'nahnu-install-tracker' ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_cron ) )
						); ?>
					</p>
				<?php else : ?>
					<p class="description nit-error"><?php esc_html_e( 'No cron event scheduled. Try deactivating and reactivating the plugin.', 'nahnu-install-tracker' ); ?></p>
				<?php endif; ?>

				<hr>

				<h3><?php esc_html_e( 'Manual Refresh', 'nahnu-install-tracker' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Fetches fresh data for all three trackers immediately.', 'nahnu-install-tracker' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nit_manual_fetch">
					<input type="hidden" name="tab"    value="fetch-settings">
					<?php wp_nonce_field( 'nit_manual_fetch' ); ?>
					<?php submit_button( __( 'Refresh Now', 'nahnu-install-tracker' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

}
