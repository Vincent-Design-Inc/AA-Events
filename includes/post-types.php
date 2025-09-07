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
        $datetime = get_field( 'event_datetime', $post_id );

        if ( $datetime ) {
            echo esc_html( gmdate( 'F j, Y g:i a', strtotime( $datetime ) ) );
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
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $orderby = $query->get( 'orderby' );
    $map     = events_get_sortable_meta_map();

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
 * Set default sort order for event post type by event_datetime ascending.
 * Applies to both admin and frontend queries.
 *
 * @param WP_Query $query The WordPress query object.
 */
function events_set_default_sort_order( $query ) {
    // Only modify main queries for event post type
    if ( ! $query->is_main_query() ) {
        return;
    }

    // Admin list table
    if ( is_admin() && $query->get( 'post_type' ) === 'event' ) {
        if ( ! $query->get( 'orderby' ) ) {
            $query->set( 'meta_key', 'event_datetime' );
            $query->set( 'orderby', 'meta_value' );
            $query->set( 'order', 'ASC' );
        }
    }

    // Frontend: event archives and taxonomies
    if ( ! is_admin() && $query->get( 'post_type' ) === 'event' ) {
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
        if ( $query->is_post_type_archive( 'event' ) ) {
            if ( ! $query->get( 'orderby' ) ) {
                $query->set( 'meta_key', 'event_datetime' );
                $query->set( 'meta_type', 'DATETIME' );
                $query->set( 'orderby', 'meta_value' );
                $query->set( 'order', 'DESC' );
            }
        }

        // Taxonomy views: upcoming first (ascending)
        if ( ( $query->is_tax( 'event_type' ) || $query->is_tax( 'event_tag' ) ) && ! $query->get( 'orderby' ) ) {
            $query->set( 'meta_key', 'event_datetime' );
            $query->set( 'meta_type', 'DATETIME' );
            $query->set( 'orderby', 'meta_value' );
            $query->set( 'order', 'ASC' );
        }
    }
}
add_action( 'pre_get_posts', 'events_set_default_sort_order' );
