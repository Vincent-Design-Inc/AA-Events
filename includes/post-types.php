<?php
/**
 * Register Custom Post Types and Taxonomies.
 *
 * @package AA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register a custom post type for events.
 */
function events_register_post_type() {
    $labels = array(
        'name'               => _x( 'Events', 'post type general name', 'aa-events' ),
        'singular_name'      => _x( 'Event', 'post type singular name', 'aa-events' ),
        'menu_name'          => _x( 'Events', 'admin menu', 'aa-events' ),
        'name_admin_bar'     => _x( 'Event', 'add new on admin bar', 'aa-events' ),
        'add_new'            => _x( 'Add New', 'event', 'aa-events' ),
        'add_new_item'       => __( 'Add New Event', 'aa-events' ),
        'new_item'           => __( 'New Event', 'aa-events' ),
        'edit_item'          => __( 'Edit Event', 'aa-events' ),
        'view_item'          => __( 'View Event', 'aa-events' ),
        'all_items'          => __( 'All Events', 'aa-events' ),
        'search_items'       => __( 'Search Events', 'aa-events' ),
        'parent_item_colon'  => __( 'Parent Events:', 'aa-events' ),
        'not_found'          => __( 'No events found.', 'aa-events' ),
        'not_found_in_trash' => __( 'No events found in Trash.', 'aa-events' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'events' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'supports'           => array( 'title', 'editor', 'author', 'thumbnail' ),
        'menu_icon'          => 'dashicons-calendar-alt',
    );

    register_post_type( 'event', $args );
}
add_action( 'init', 'events_register_post_type' );

/**
 * Register a custom taxonomy for event types.
 */
function events_register_event_type_taxonomy() {
    $labels = array(
        'name'              => 'Event Types',
        'singular_name'     => 'Event Type',
        'search_items'      => 'Search Event Types',
        'all_items'         => 'All Event Types',
        'parent_item'       => 'Parent Event Type',
        'parent_item_colon' => 'Parent Event Type:',
        'edit_item'         => 'Edit Event Type',
        'update_item'       => 'Update Event Type',
        'add_new_item'      => 'Add New Event Type',
        'new_item_name'     => 'New Event Type Name',
        'menu_name'         => 'Event Types',
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'event-type' ),
    );

    register_taxonomy( 'event_type', array( 'event' ), $args );
}
add_action( 'init', 'events_register_event_type_taxonomy' );

/**
 * Register a custom taxonomy for event tags.
 */
function events_register_event_tag_taxonomy() {
    $labels = array(
        'name'                       => _x( 'Event Tags', 'taxonomy general name', 'aa-events' ),
        'singular_name'              => _x( 'Event Tag', 'taxonomy singular name', 'aa-events' ),
        'search_items'               => __( 'Search Event Tags', 'aa-events' ),
        'all_items'                  => __( 'All Event Tags', 'aa-events' ),
        'parent_item'                => null,
        'parent_item_colon'          => null,
        'edit_item'                  => __( 'Edit Event Tag', 'aa-events' ),
        'update_item'                => __( 'Update Event Tag', 'aa-events' ),
        'add_new_item'               => __( 'Add New Event Tag', 'aa-events' ),
        'new_item_name'              => __( 'New Event Tag Name', 'aa-events' ),
        'separate_items_with_commas' => __( 'Separate event tags with commas', 'aa-events' ),
        'add_or_remove_items'        => __( 'Add or remove event tags', 'aa-events' ),
        'choose_from_most_used'      => __( 'Choose from the most used event tags', 'aa-events' ),
        'not_found'                  => __( 'No event tags found.', 'aa-events' ),
        'menu_name'                  => __( 'Event Tags', 'aa-events' ),
    );

    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'event-tag' ),
    );

    register_taxonomy( 'event_tag', array( 'event' ), $args );
}
add_action( 'init', 'events_register_event_tag_taxonomy' );

/**
 * Add custom columns to the event list table.
 *
 * @param array $columns The existing columns.
 * @return array The modified columns.
 */
function events_add_admin_columns( $columns ) {
    $new_columns = array();

    foreach ( $columns as $key => $value ) {
        $new_columns[ $key ] = $value;

        if ( 'title' === $key ) {
            $new_columns['event_datetime'] = __( 'Event Date & Time', 'aa-events' );
        }
    }

    return $new_columns;
}
add_filter( 'manage_edit-event_columns', 'events_add_admin_columns' );

/**
 * Display the content for the custom columns.
 *
 * @param string $column  The name of the column.
 * @param int    $post_id The ID of the post.
 */
function events_display_admin_column_content( $column, $post_id ) {
    if ( 'event_datetime' === $column ) {
        $occurrences = aa_events_get_occurrences( $post_id );
        if ( ! $occurrences ) {
            echo esc_html( '—' );
            return;
        }

        echo esc_html( aa_events_format_occurrence( $occurrences[0] ) );
        if ( count( $occurrences ) > 1 ) {
            echo ' <span class="aa-event-occurrence-count">+' . esc_html( count( $occurrences ) - 1 ) . '</span>';
        }
    }
}
add_action( 'manage_event_posts_custom_column', 'events_display_admin_column_content', 10, 2 );

/**
 * Make the custom column sortable.
 *
 * @param array $columns The existing sortable columns.
 * @return array The modified sortable columns.
 */
function events_make_admin_column_sortable( $columns ) {
    $columns['event_datetime'] = 'event_datetime';
    return $columns;
}
add_filter( 'manage_edit-event_sortable_columns', 'events_make_admin_column_sortable' );

/**
 * Map of sortable meta keys and their types.
 *
 * Key is the meta_key; value is the meta type used for sorting.
 * Supported types include 'NUMERIC', 'CHAR', 'DATE', 'DATETIME'.
 * Extend this list to allow additional meta-key sorting.
 *
 * @return array<string,string>
 */
function events_get_sortable_meta_map() {
    return array(
        'event_datetime'   => 'DATETIME',
        // Alias often used externally; map to same type.
        'event_start_date' => 'DATETIME',
        // Add more allowed meta keys here as needed, for example:
        // 'event_capacity' => 'NUMERIC',
        // 'venue'          => 'CHAR',
    );
}

/**
 * Handle sorting by the custom column.
 *
 * @param WP_Query $query The WordPress query object.
 */
function events_sort_by_custom_column( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || 'event' !== $query->get( 'post_type' ) ) {
        return;
    }

    $orderby = $query->get( 'orderby' );
    $map     = events_get_sortable_meta_map();

    // Special handling for the event_datetime column in admin.
    // Use a custom SQL sort across multiple possible date fields while keeping all posts visible.
    if ( 'event_datetime' === $orderby ) {
        $query->set( 'aa_events_date_sort', 1 );
        $query->set( 'orderby', 'none' );

        $order = strtoupper( (string) $query->get( 'order' ) );
        if ( 'DESC' !== $order ) {
            $order = 'ASC';
        }
        $query->set( 'order', $order );
        return;
    }

    if ( isset( $map[ $orderby ] ) ) {
        $meta_type = strtoupper( $map[ $orderby ] );
        $query->set( 'meta_key', $orderby );
        if ( 'NUMERIC' === $meta_type ) {
            $query->set( 'orderby', 'meta_value_num' );
        } else {
            $query->set( 'orderby', 'meta_value' );
            $query->set( 'meta_type', $meta_type );
        }
    }
}
add_action( 'pre_get_posts', 'events_sort_by_custom_column' );

/**
 * Build a safe SQL expression for supported stored date formats.
 *
 * @param string $column         Static SQL column expression.
 * @param bool   $allow_datetime Whether to accept a full legacy datetime.
 * @return string
 */
function events_admin_date_meta_expression( $column, $allow_datetime = false ) {
    $datetime = $allow_datetime ? "WHEN {$column} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$' THEN STR_TO_DATE({$column}, '%Y-%m-%d %H:%i:%s') " : '';
    return "CASE {$datetime}WHEN {$column} REGEXP '^[0-9]{8}$' THEN STR_TO_DATE({$column}, '%Y%m%d') WHEN {$column} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' THEN STR_TO_DATE({$column}, '%Y-%m-%d') ELSE NULL END";
}

/**
 * Build the deterministic admin occurrence ORDER BY expression.
 *
 * @param string $posts_table Static posts table name.
 * @param string $order       Requested direction.
 * @return string
 */
function events_admin_date_sort_orderby_sql( $posts_table, $order ) {
    $order                 = 'DESC' === strtoupper( (string) $order ) ? 'DESC' : 'ASC';
    $postmeta_table        = events_date_sort_postmeta_table();
    $canonical_expression  = events_admin_date_meta_expression( 'aa_sort.meta_value', true );
    $date_expression       = events_admin_date_meta_expression( 'aa_er.meta_value' );
    $datetime_expression   = events_admin_date_meta_expression( 'aa_edt.meta_value', true );
    $legacy_expression     = events_admin_date_meta_expression( 'aa_d.meta_value' );
    $start_time_value      = "(SELECT aa_time.meta_value FROM {$postmeta_table} AS aa_time WHERE aa_time.post_id = aa_er.post_id AND aa_time.meta_key = CONCAT(LEFT(aa_er.meta_key, LENGTH(aa_er.meta_key) - 4), 'start_time') ORDER BY aa_time.meta_id ASC LIMIT 1)";
    $all_day_value         = "(SELECT aa_all_day.meta_value FROM {$postmeta_table} AS aa_all_day WHERE aa_all_day.post_id = aa_er.post_id AND aa_all_day.meta_key = CONCAT(LEFT(aa_er.meta_key, LENGTH(aa_er.meta_key) - 4), 'all_day') ORDER BY aa_all_day.meta_id ASC LIMIT 1)";
    $start_time_expression = "CASE WHEN LOWER(TRIM(COALESCE({$all_day_value}, ''))) IN ('1', 'true', 'yes', 'on') THEN STR_TO_DATE('00:00:00', '%H:%i:%s') WHEN {$start_time_value} REGEXP '^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$' THEN CASE WHEN CHAR_LENGTH({$start_time_value}) = 5 THEN STR_TO_DATE({$start_time_value}, '%H:%i') ELSE STR_TO_DATE({$start_time_value}, '%H:%i:%s') END ELSE STR_TO_DATE('00:00:00', '%H:%i:%s') END";
    $repeater              = "(SELECT MIN(TIMESTAMP({$date_expression}, {$start_time_expression})) FROM {$postmeta_table} AS aa_er WHERE aa_er.post_id = {$posts_table}.ID AND aa_er.meta_key REGEXP '^event_dates_[0-9]+_date$')";
    $legacy_repeater_date  = events_admin_date_meta_expression( 'aa_mr.meta_value' );
    $legacy_time_value     = "(SELECT aa_mtime.meta_value FROM {$postmeta_table} AS aa_mtime WHERE aa_mtime.post_id = aa_mr.post_id AND aa_mtime.meta_key = CONCAT(LEFT(aa_mr.meta_key, LENGTH(aa_mr.meta_key) - 4), 'start_time') ORDER BY aa_mtime.meta_id ASC LIMIT 1)";
    $legacy_all_day_value  = "(SELECT aa_mall.meta_value FROM {$postmeta_table} AS aa_mall WHERE aa_mall.post_id = aa_mr.post_id AND aa_mall.meta_key = CONCAT(LEFT(aa_mr.meta_key, LENGTH(aa_mr.meta_key) - 4), 'all_day') ORDER BY aa_mall.meta_id ASC LIMIT 1)";
    $legacy_time_expression = "CASE WHEN LOWER(TRIM(COALESCE({$legacy_all_day_value}, ''))) IN ('1', 'true', 'yes', 'on') THEN STR_TO_DATE('00:00:00', '%H:%i:%s') WHEN {$legacy_time_value} REGEXP '^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$' THEN CASE WHEN CHAR_LENGTH({$legacy_time_value}) = 5 THEN STR_TO_DATE({$legacy_time_value}, '%H:%i') ELSE STR_TO_DATE({$legacy_time_value}, '%H:%i:%s') END ELSE STR_TO_DATE('00:00:00', '%H:%i:%s') END";
    $legacy_repeater       = "(SELECT MIN(TIMESTAMP({$legacy_repeater_date}, {$legacy_time_expression})) FROM {$postmeta_table} AS aa_mr WHERE aa_mr.post_id = {$posts_table}.ID AND aa_mr.meta_key REGEXP '^multiple_dates_[0-9]+_date$')";
    $legacy_datetime       = "(SELECT MIN({$datetime_expression}) FROM {$postmeta_table} AS aa_edt WHERE aa_edt.post_id = {$posts_table}.ID AND aa_edt.meta_key = 'event_datetime')";
    $legacy_date           = "(SELECT MIN({$legacy_expression}) FROM {$postmeta_table} AS aa_d WHERE aa_d.post_id = {$posts_table}.ID AND aa_d.meta_key = 'date')";
    return "COALESCE({$canonical_expression}, {$repeater}, {$legacy_repeater}, {$legacy_datetime}, {$legacy_date}, {$posts_table}.post_date) {$order}, {$posts_table}.post_date {$order}, {$posts_table}.ID {$order}";
}

/**
 * Return the postmeta table used by date-sort SQL helpers.
 *
 * @return string
 */
function events_date_sort_postmeta_table() {
    global $wpdb;
    return isset( $wpdb->postmeta ) ? $wpdb->postmeta : 'wp_postmeta';
}

/**
 * Build grouped joins for normalized event date sort values.
 *
 * @param string $postmeta_table Static postmeta table name.
 * @param string $posts_table    Static posts table name.
 * @return string
 */
function events_admin_date_sort_join_sql( $postmeta_table, $posts_table ) {
    return " LEFT JOIN {$postmeta_table} AS aa_sort ON aa_sort.meta_id = (SELECT MIN(aa_sort_pick.meta_id) FROM {$postmeta_table} AS aa_sort_pick WHERE aa_sort_pick.post_id = {$posts_table}.ID AND aa_sort_pick.meta_key = '_aa_events_sort_start')";
}

/**
 * Check whether an occurrence sort flag belongs to a supported event query.
 *
 * @param WP_Query $query Query object.
 * @return bool
 */
function events_date_sort_query_applies( $query ) {
    if ( ! $query->is_main_query() || ! $query->get( 'aa_events_date_sort' ) ) {
        return false;
    }
    if ( is_admin() ) {
        return 'event' === $query->get( 'post_type' );
    }
    return $query->is_post_type_archive( 'event' ) || $query->is_tax( 'event_type' ) || $query->is_tax( 'event_tag' );
}

/**
 * Add occurrence date sort joins to a flagged main query.
 *
 * @param string   $join  SQL JOIN clause.
 * @param WP_Query $query Query object.
 * @return string
 */
function events_date_sort_posts_join( $join, $query ) {
    global $wpdb;

    if ( ! events_date_sort_query_applies( $query ) ) {
        return $join;
    }

    return $join . events_admin_date_sort_join_sql( $wpdb->postmeta, $wpdb->posts );
}
add_filter( 'posts_join', 'events_date_sort_posts_join', 10, 2 );

/**
 * Apply deterministic occurrence date ordering to a flagged main query.
 *
 * @param string   $orderby SQL ORDER BY clause.
 * @param WP_Query $query   Query object.
 * @return string
 */
function events_date_sort_posts_orderby( $orderby, $query ) {
    global $wpdb;

    if ( ! events_date_sort_query_applies( $query ) ) {
        return $orderby;
    }

    // Priority: earliest repeater date -> legacy datetime -> legacy date -> post date.
    return events_admin_date_sort_orderby_sql( $wpdb->posts, $query->get( 'order' ) );
}
add_filter( 'posts_orderby', 'events_date_sort_posts_orderby', 10, 2 );

/**
 * Extend REST API to allow ordering events by meta value.
 * Adds support for `orderby=meta_value|meta_value_num` and `meta_key`.
 *
 * @param array $params The existing REST API collection parameters.
 * @return array The modified REST API collection parameters.
 */
function events_rest_extend_collection_params( $params ) {
    if ( isset( $params['orderby']['enum'] ) && is_array( $params['orderby']['enum'] ) ) {
        $params['orderby']['enum'][] = 'meta_value';
        $params['orderby']['enum'][] = 'meta_value_num';
    }

    $params['meta_key'] = array(
        'description'       => __( 'Meta key to order by.', 'aa-events' ),
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_key',
        'validate_callback' => function ( $value ) {
            return is_string( $value ) && '' !== $value;
        },
    );

    // Optional: allow specifying meta_type when using orderby=meta_value.
    $params['meta_type'] = array(
        'description' => __( 'Meta type used for ordering.', 'aa-events' ),
        'type'        => 'string',
        'enum'        => array( 'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED' ),
    );

    return $params;
}
add_filter( 'rest_event_collection_params', 'events_rest_extend_collection_params' );

/**
 * Apply REST meta ordering to the WP_Query args for events.
 *
 * @param array           $args    The prepared query args.
 * @param WP_REST_Request $request The REST request.
 * @return array
 */
function events_rest_adjust_query_for_meta_ordering( $args, $request ) {
    $orderby   = $request->get_param( 'orderby' );
    $meta_key  = $request->get_param( 'meta_key' );
    $meta_type = $request->get_param( 'meta_type' );

    if ( in_array( $orderby, array( 'meta_value', 'meta_value_num' ), true ) && $meta_key ) {
        // Allow simple aliasing of requested meta keys to real keys used in DB.
        $aliases = array(
            'event_start_date' => 'event_datetime',
        );
        if ( isset( $aliases[ $meta_key ] ) ) {
            $meta_key = $aliases[ $meta_key ];
        }

        $args['meta_key'] = $meta_key;
        $args['orderby']  = $orderby;

        // If meta_type explicitly provided (and using meta_value), apply it.
        if ( $meta_type && 'meta_value' === $orderby ) {
            $args['meta_type'] = strtoupper( $meta_type );
        } elseif ( function_exists( 'events_get_sortable_meta_map' ) ) {
            // Otherwise, try to infer from our whitelist map for convenience.
            $map = events_get_sortable_meta_map();
            if ( isset( $map[ $meta_key ] ) && 'meta_value' === $orderby ) {
                $args['meta_type'] = strtoupper( $map[ $meta_key ] );
            }
        }
    }

    return $args;
}
add_filter( 'rest_event_query', 'events_rest_adjust_query_for_meta_ordering', 10, 2 );

/**
 * Set occurrence-aware default sort order for event list queries.
 *
 * @param WP_Query $query The WordPress query object.
 */
function events_set_default_sort_order( $query ) {
    // Only modify main queries for event post type
    if ( ! $query->is_main_query() ) {
        return;
    }

    // Admin list table: default to event start-date sorting.
    if ( is_admin() && $query->get( 'post_type' ) === 'event' ) {
        if ( ! $query->get( 'orderby' ) ) {
            $query->set( 'aa_events_date_sort', 1 );
            $query->set( 'orderby', 'none' );
            $query->set( 'order', 'ASC' );
        }
    }

    // Frontend: event archives and taxonomies. Taxonomy queries do not always expose post_type.
    $is_event_archive  = ! is_admin() && $query->is_post_type_archive( 'event' );
    $is_event_taxonomy = ! is_admin() && ( $query->is_tax( 'event_type' ) || $query->is_tax( 'event_tag' ) );
    if ( $is_event_archive || $is_event_taxonomy ) {
        $map     = events_get_sortable_meta_map();
        $orderby = $query->get( 'orderby' );

        // If a known meta key is explicitly requested via orderby, honor it.
        if ( isset( $map[ $orderby ] ) ) {
            $meta_type = strtoupper( $map[ $orderby ] );
            $query->set( 'meta_key', $orderby );
            if ( 'NUMERIC' === $meta_type ) {
                $query->set( 'orderby', 'meta_value_num' );
            } else {
                $query->set( 'orderby', 'meta_value' );
                $query->set( 'meta_type', $meta_type );
            }
            return; // Do not apply defaults if explicit meta sort requested.
        }

        // Main event archive: newest first (descending by date/time)
        if ( $is_event_archive ) {
            if ( ! $query->get( 'orderby' ) ) {
                $query->set( 'aa_events_date_sort', 1 );
                $query->set( 'orderby', 'none' );
                $query->set( 'order', 'DESC' );
            }
        }

        // Taxonomy views: upcoming first (ascending)
        if ( $is_event_taxonomy && ! $query->get( 'orderby' ) ) {
            $query->set( 'aa_events_date_sort', 1 );
            $query->set( 'orderby', 'none' );
            $query->set( 'order', 'ASC' );
        }
    }
}
add_action( 'pre_get_posts', 'events_set_default_sort_order' );
