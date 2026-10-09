<?php
/**
 * Plugin Name: AA Events
 * Plugin URI:  https://github.com/Vincent-Design-Inc/aa-events
 * Description: An Events management plugin for WordPress, with accessible calendar (WCAG 2.2AA) and card grid views.
 * Version:     1.6.2
 * Author:      Keith Solomon
 * Author URI:  https://vincentdesign.ca
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: aa-events
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 */
function activate_aa_events() {
	// Ensure the CPT and taxonomies are registered.
	include_once plugin_dir_path( __FILE__ ) . 'includes/post-types.php';
	events_register_post_type();
	events_register_event_type_taxonomy();
	events_register_event_tag_taxonomy();
	if ( function_exists( 'aa_events_schedule_occurrence_index_backfill' ) ) {
		aa_events_schedule_occurrence_index_backfill();
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_aa_events() {
	// Flush rewrite rules.
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'activate_aa_events' );
register_deactivation_hook( __FILE__, 'deactivate_aa_events' );

/**
 * Define Constants
 */
define( 'AA_EVENTS_PLUGIN_FILE', __FILE__ );
define( 'AA_EVENTS_VERSION', '1.6.2' );
define( 'AA_EVENTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AA_EVENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Include the main class.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-aa-events.php';

/**
 * Returns the main instance of AA_Events.
 *
 * @return AA_Events
 */
function AA_Events() {
	return AA_Events::instance();
}

// Get AA_Events Running.
AA_Events();
