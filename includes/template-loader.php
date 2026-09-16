<?php
/**
 * Template Loader for AA Events.
 *
 * @package AA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Build safe contact-email markup for event templates.
 *
 * @param mixed $value Raw contact email value.
 * @return string Escaped mailto anchor, or an empty string for invalid input.
 */
function aa_events_contact_email_markup( $value ) {
	$raw   = trim( (string) $value );
	$email = sanitize_email( $raw );
	if ( '' === $email || $email !== $raw ) {
		return '';
	}

	$protected = antispambot( $email );
	return '<a href="' . esc_attr( 'mailto:' . $protected ) . '">' . wp_kses_post( $protected ) . '</a>';
}

/**
 * Build safe contact-phone markup for event templates.
 *
 * @param mixed $value Raw contact phone value.
 * @return string Escaped tel anchor, or an empty string for invalid input.
 */
function aa_events_contact_phone_markup( $value ) {
	$raw = trim( (string) $value );
	if ( '' === $raw || ! preg_match( '/\d/', $raw ) || ! preg_match( '/^[0-9+\s().-]+$/', $raw ) ) {
		return '';
	}

	$telephone = preg_replace( '/[^0-9+]/', '', $raw );
	if ( '' === $telephone ) {
		return '';
	}

	return '<a href="' . esc_attr( 'tel:' . $telephone ) . '">' . esc_html( $raw ) . '</a>';
}

/**
 * Build safe registration-link markup for event templates.
 *
 * @param mixed $value Raw registration link value.
 * @return string Escaped registration link, or an empty string for invalid input.
 */
function aa_events_registration_link_markup( $value ) {
	if ( is_array( $value ) ) {
		if ( ! isset( $value['url'] ) || ! is_string( $value['url'] ) ) {
			return '';
		}

		$url    = trim( $value['url'] );
		$target = isset( $value['target'] ) && is_string( $value['target'] ) ? $value['target'] : '';
	} elseif ( is_string( $value ) ) {
		$url    = trim( $value );
		$target = '';
	} else {
		return '';
	}

	if ( '' === $url || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return '';
	}

	$url_parts = wp_parse_url( $url );
	if (
		! is_array( $url_parts ) ||
		empty( $url_parts['host'] ) ||
		empty( $url_parts['scheme'] ) ||
		! in_array( strtolower( $url_parts['scheme'] ), array( 'http', 'https' ), true )
	) {
		return '';
	}

	$target_attributes = '_blank' === $target ? ' target="_blank" rel="noopener noreferrer"' : '';

	return '<a class="aa-event-registration-link button" href="' . esc_url( $url ) . '"' . $target_attributes . '>' . esc_html( __( 'Register', 'aa-events' ) ) . '</a>';
}

/**
 * Load a template.
 *
 * Handles template usage so that users can override the plugin's templates by using the theme.
 *
 * @param string $template_name Template name.
 * @param array  $args          Arguments. (default: array).
 * @param string $template_path Template path. (default: '').
 * @param string $default_path  Default path. (default: '').
 */
function events_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	if ( ! empty( $args ) && is_array( $args ) ) {
		extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	}

	$located = events_locate_template( $template_name, $template_path, $default_path );

	if ( ! file_exists( $located ) ) {
		_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', esc_html( $located ) ), '1.0' );
		return;
	}

	// Allow 3rd party plugin filter template file from theme.
	$located = apply_filters( 'events_get_template', $located, $template_name, $args, $template_path, $default_path );

	do_action( 'events_before_template_part', $template_name, $template_path, $located, $args );

	include $located;

	do_action( 'events_after_template_part', $template_name, $template_path, $located, $args );
}

/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 *
 * yourtheme/$template_path/$template_name
 * yourtheme/$template_name
 * $default_path/$template_name
 *
 * @param string $template_name Template name.
 * @param string $template_path Template path. (default: '').
 * @param string $default_path  Default path. (default: '').
 * @return string
 */
function events_locate_template( $template_name, $template_path = '', $default_path = '' ) {
	if ( ! $template_path ) {
		$template_path = 'aa-events/';
	}

	if ( ! $default_path ) {
		$default_path = AA_EVENTS_PLUGIN_DIR . 'templates/';
	}

	// Look within passed path within the theme - this is priority.
	$template = locate_template(
		array(
			trailingslashit( $template_path ) . $template_name,
			$template_name,
		)
	);

	// Get default template/
	if ( ! $template ) {
		$template = $default_path . $template_name;
	}

	// Return what we found.
	return apply_filters( 'events_locate_template', $template, $template_name, $template_path );
}

/**
 * Filter the single template.
 *
 * @param string $template Template.
 * @return string
 */
function events_single_template( $template ) {
	if ( get_post_type() === 'event' ) {
		$template = events_locate_template( 'single-event.php' );
	}
	return $template;
}
add_filter( 'single_template', 'events_single_template' );

/**
 * Filter the archive template.
 *
 * @param string $template Template.
 * @return string
 */
function events_archive_template( $template ) {
	if ( is_post_type_archive( 'event' ) ) {
		$template = events_locate_template( 'archive-event.php' );
	}
	return $template;
}
add_filter( 'archive_template', 'events_archive_template' );

/**
 * Register plugin-provided page templates so they appear in the editor.
 *
 * WordPress only scans themes for page templates by default. This filter
 * injects our plugin template into the list shown in the Page Attributes
 * (or Template) dropdown, without requiring the file to live in the theme.
 *
 * @param array        $page_templates Array of page templates.
 * @param WP_Theme     $theme          WP_Theme object.
 * @param WP_Post|null $post           The post being edited (or null).
 * @param string       $post_type      Post type slug.
 * @return array
 */
function events_register_page_templates( $page_templates, $theme, $post, $post_type ) {
    if ( 'page' !== $post_type ) {
        return $page_templates;
    }

    // Key can be any unique string. We'll look for this exact value when loading.
    $page_templates['aa-events-template-calendar.php'] = __( 'Events Calendar', 'aa-events' );

    return $page_templates;
}
add_filter( 'theme_page_templates', 'events_register_page_templates', 10, 4 );

/**
 * Load the selected plugin template on the front end.
 *
 * When a page uses our registered template slug, point WordPress to the
 * template file bundled inside the plugin.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function events_load_plugin_page_template( $template ) {
    if ( is_singular( 'page' ) ) {
        $post_id = get_queried_object_id();
        if ( $post_id ) {
            $selected = get_post_meta( $post_id, '_wp_page_template', true );
            if ( 'aa-events-template-calendar.php' === $selected ) {
                // Allow theme override via aa-events/template-calendar.php, then fall back to plugin file.
                $resolved = events_locate_template( 'template-calendar.php' );
                if ( $resolved && file_exists( $resolved ) ) {
                    return $resolved;
                }
            }
        }
    }

    return $template;
}
add_filter( 'template_include', 'events_load_plugin_page_template', 20 );
