<?php
/**
 * The core plugin class.
 *
 * @package AA_Events
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 */
final class AA_Events {

	/**
	 * The single instance of the class.
	 *
	 * @var AA_Events
	 */
	protected static $instance = null;

	/**
	 * Main AA_Events Instance.
	 *
	 * Ensures only one instance of AA_Events is loaded or can be loaded.
	 *
	 * @static
	 * @return AA_Events - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * AA_Events Constructor.
	 */
	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required core files.
	 */
	public function includes() {
		include_once AA_EVENTS_PLUGIN_DIR . 'includes/occurrences.php';
		include_once AA_EVENTS_PLUGIN_DIR . 'includes/calendar-layout.php';
		include_once AA_EVENTS_PLUGIN_DIR . 'includes/post-types.php';
		include_once AA_EVENTS_PLUGIN_DIR . 'includes/acf-fields.php';
		include_once AA_EVENTS_PLUGIN_DIR . 'includes/template-loader.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		// Ensure the active theme supports thumbnails for the event post type.
		add_action( 'after_setup_theme', array( $this, 'ensure_post_thumbnails_support' ) );
		add_filter( 'query_vars', array( $this, 'register_calendar_query_vars' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_styles() {
		$theme_stylesheet = get_stylesheet_directory() . '/aa-events/aa-events.css';

		if ( file_exists( $theme_stylesheet ) ) {
			wp_enqueue_style(
				'aa-events-theme',
				get_stylesheet_directory_uri() . '/aa-events/aa-events.css',
				array(),
				filemtime( $theme_stylesheet )
			);
		} else {
			wp_enqueue_style(
				'aa-events',
				AA_EVENTS_PLUGIN_URL . 'assets/css/aa-events.css',
				array(),
				AA_EVENTS_VERSION
			);
		}

		wp_enqueue_script(
			'aa-events-calendar-view',
			AA_EVENTS_PLUGIN_URL . 'assets/js/aa-events-calendar-view.js',
			array(),
			AA_EVENTS_VERSION,
			true
		);
	}

	/**
	 * Enqueue admin assets for event editor UX fixes.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'event' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'aa-events-admin',
			AA_EVENTS_PLUGIN_URL . 'assets/js/aa-events-admin.js',
			array( 'jquery' ),
			AA_EVENTS_VERSION,
			true
		);
	}

	/**
	 * Load Localisation files.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'aa-events',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}

	/**
	 * Ensure the theme supports post thumbnails for events.
	 *
	 * Some themes do not declare `add_theme_support( 'post-thumbnails' )`,
	 * which hides the Featured Image panel. This enables thumbnails at least
	 * for the `event` post type without impacting other types.
	 */
	public function ensure_post_thumbnails_support() {
		$support = get_theme_support( 'post-thumbnails' );

		// If theme has no support at all, enable it for events only.
		if ( false === $support ) {
			add_theme_support( 'post-thumbnails', array( 'event' ) );
			return;
		}

		// If theme supports specific post types, ensure 'event' is included.
		if ( is_array( $support ) && isset( $support[0] ) && is_array( $support[0] ) ) {
			$post_types = $support[0];
			if ( ! in_array( 'event', $post_types, true ) ) {
				$post_types[] = 'event';
				add_theme_support( 'post-thumbnails', $post_types );
			}
		}
		// If theme support is boolean true (all types), nothing to do.
	}

	/**
	 * Allow custom AA Events calendar query vars to persist through canonical redirects.
	 *
	 * @param array $vars Public query vars.
	 * @return array
	 */
	public function register_calendar_query_vars( $vars ) {
		$vars[] = 'aa_month';
		$vars[] = 'aa_year';
		$vars[] = 'aa_day';

		return $vars;
	}
}
