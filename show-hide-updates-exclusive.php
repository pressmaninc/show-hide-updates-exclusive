<?php
/*
Plugin Name: Show/Hide Updates Exclusive
Description: This plugin hides all update notifications for Wordpress core, plugin and theme updates in Wordpress admin for all users except users whom administrators select as updater.
Plugin URI:
Version: 1.0.0
Author: PRESSMAN
Author URI: https://www.pressman.ne.jp/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Show_Hide_Updates_Exclusive class
 *
 * @package       WordPress_Plugins
 * @subpackage    Show_Hide_Updates_Exclusive
 * @author        sekikawa(satoshi_sekikawa@pressman.ne.jp)
 * @copyright     Pressman inc.
 */
class Show_Hide_Updates_Exclusive {

	private static $instance;

	/**
	 * Get instance
	 *
	 * @return Show_Hide_Updates_Exclusive
	 */
	public static function get_instance() {
		if ( !isset( self::$instance ) ) {
			self::$instance = new Show_Hide_Updates_Exclusive();
		}

		return self::$instance;
	}

	/**
	 * Show_Hide_Updates_Exclusive constructor.
	 */
	private function __construct() {
		add_filter( 'init', [ $this, 'is_update_user' ] );
		add_action( 'show_user_profile', [ $this, 'updater_field' ] );
		add_action( 'edit_user_profile', [ $this, 'updater_field' ] );
		add_action( 'personal_options_update', [ $this, 'save_custom_user_profile_fields' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_custom_user_profile_fields' ] );
	}

	/**
	 * Check whether updater or not
	 */
	public function is_update_user() {
		global $current_user;
		$updater = get_user_meta( $current_user->ID, 'updater', true );
		if ( '1' != $updater ) {
			$this->construct();
		}
	}

	/**
	 * Disable all updates
	 */
	public function construct() {
		add_action( 'admin_init', [ $this, 'admin_init' ] );

		/*
		 * Disable Theme Updates
		 */
		add_filter( 'pre_site_transient_update_themes', [ $this, 'last_checked_atm' ] );

		/*
		 * Disable Plugin Updates
		 */
		add_filter( 'pre_site_transient_update_plugins', [ $this, 'last_checked_atm' ] );

		/*
		 * Disable Core Updates
		 */
		add_filter( 'pre_site_transient_update_core', [ $this, 'last_checked_atm' ] );

		/*
		 * Filter schedule checks
		 */
		add_action( 'schedule_event', [ $this, 'filter_cron_events' ] );

		/*
		 * Disable All Automatic Updates
		 */
		add_filter( 'auto_update_translation', '__return_false' );
		add_filter( 'automatic_updater_disabled', '__return_true' );
		add_filter( 'allow_minor_auto_core_updates', '__return_false' );
		add_filter( 'allow_major_auto_core_updates', '__return_false' );
		add_filter( 'allow_dev_auto_core_updates', '__return_false' );
		add_filter( 'auto_update_core', '__return_false' );
		add_filter( 'wp_auto_update_core', '__return_false' );
		add_filter( 'auto_core_update_send_email', '__return_false' );
		add_filter( 'send_core_update_notification_email', '__return_false' );
		add_filter( 'auto_update_plugin', '__return_false' );
		add_filter( 'auto_update_theme', '__return_false' );
		add_filter( 'automatic_updates_send_debug_email', '__return_false' );
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_true' );

		add_filter( 'automatic_updates_send_debug_email ', '__return_false', 1 );
		if ( !defined( 'AUTOMATIC_UPDATER_DISABLED' ) ) {
			define( 'AUTOMATIC_UPDATER_DISABLED', true );
		}
		if ( !defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			define( 'WP_AUTO_UPDATE_CORE', false );
		}
		add_filter( 'pre_http_request', [ $this, 'block_request' ], 10, 3 );
	}


	/**
	 * Initialize and load the plugin stuff
	 */
	public function admin_init() {
		if ( !function_exists( "remove_action" ) ) {
			return;
		}

		/*
		 * Remove 'update plugins' option from bulk operations select list
		 */
		global $current_user;
		$current_user->allcaps['update_plugins'] = 0;

		/*
		 * Hide maintenance and update nag
		 */
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'network_admin_notices', 'update_nag', 3 );
		remove_action( 'admin_notices', 'maintenance_nag' );
		remove_action( 'network_admin_notices', 'maintenance_nag' );

		/*
		 * Disable Theme Updates
		 */
		remove_action( 'load-update-core.php', 'wp_update_themes' );
		wp_clear_scheduled_hook( 'wp_update_themes' );

		/*
		 * Disable Plugin Updates
		 */
		remove_action( 'load-update-core.php', 'wp_update_plugins' );
		wp_clear_scheduled_hook( 'wp_update_plugins' );

		/*
		 * Disable Core Updates
		 */
		wp_clear_scheduled_hook( 'wp_version_check' );

		remove_action( 'wp_maybe_auto_update', 'wp_maybe_auto_update' );
		remove_action( 'admin_init', 'wp_maybe_auto_update' );
		remove_action( 'admin_init', 'wp_auto_update_core' );
		wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
	}

	/**
	 * Check the outgoing request
	 */
	public function block_request( $pre, $args, $url ) {
		/* Empty url */
		if ( empty( $url ) ) {
			return $pre;
		}

		/* Invalid host */
		if ( !$host = parse_url( $url, PHP_URL_HOST ) ) {
			return $pre;
		}

		$url_data = parse_url( $url );

		/* block request */
		if ( false !== stripos( $host, 'api.wordpress.org' ) && ( false !== stripos( $url_data['path'], 'update-check' ) || false !== stripos( $url_data['path'], 'browse-happy' ) ) ) {
			return true;
		}

		return $pre;
	}

	/**
	 * Filter cron events
	 */
	public function filter_cron_events( $event ) {
		switch ( $event->hook ) {
			case 'wp_version_check':
			case 'wp_update_plugins':
			case 'wp_update_themes':
			case 'wp_maybe_auto_update':
				$event = false;
				break;
		}

		return $event;
	}

	/**
	 * Override version check info
	 */
	public function last_checked_atm( $t ) {
		include( ABSPATH . WPINC . '/version.php' );

		$current                  = new stdClass;
		$current->updates         = [];
		$current->version_checked = $wp_version;
		$current->last_checked    = time();

		return $current;
	}

	/**
	 * Display the checkbox
	 */
	public function updater_field( $user ) {
		$updater = get_user_meta( $user->ID, 'updater', true );
		$user    = get_userdata( $user->ID );
		if ( current_user_can( 'administrator' ) && ( isset( $user->caps['administrator'] ) ) ) {
			?>
			<table class="form-table">
				<tr>
					<th>
						<label for="updater"><?php _e( 'Show Update' ); ?></label>
					</th>
					<td>
						<label><input type="checkbox" name="updater" <?php if ( $updater == '1' ){ ?> checked="checked" <?php } ?>value="1">Show</label>
					</td>
				</tr>
			</table>

			<?php
		}
	}

	/**
	 * Save updater info
	 */
	public function save_custom_user_profile_fields( $user_id ) {
		if ( !current_user_can( 'administrator', $user_id ) ) {
			return;
		}
		update_user_meta( $user_id, 'updater', $_POST['updater'] );
	}

}

if ( class_exists( 'Show_Hide_Updates_Exclusive' ) ) {
	Show_Hide_Updates_Exclusive::get_instance();
}
