<?php
/**
 * Admin Settings for AA Events Plugin
 *
 * @package AA_Events
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Get the configured date format, or default.
 *
 * @return string Date format string.
 */
function aa_events_get_date_format() {
	return get_option( 'aa_events_date_format', 'F j, Y' );
}

/**
 * Get the configured time format, or default.
 *
 * @return string Time format string.
 */
function aa_events_get_time_format() {
	return get_option( 'aa_events_time_format', 'g:i A' );
}

/**
 * Register the admin settings menu and page.
 */
function aa_events_register_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=event',
		__( 'AA Events Settings', 'aa-events' ),
		__( 'Settings', 'aa-events' ),
		'manage_options',
		'aa-events-settings',
		'aa_events_settings_page_html'
	);
}
add_action( 'admin_menu', 'aa_events_register_settings_menu' );

/**
 * Register the plugin settings.
 */
function aa_events_register_settings() {
	register_setting(
		'aa_events_settings_group',
		'aa_events_show_recurring_badge',
		array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => function ( $value ) {
				return ! empty( $value );
			},
		)
	);

	register_setting(
		'aa_events_settings_group',
		'aa_events_date_format',
		array(
			'type'              => 'string',
			'default'           => 'F j, Y',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_setting(
		'aa_events_settings_group',
		'aa_events_time_format',
		array(
			'type'              => 'string',
			'default'           => 'g:i A',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
}
add_action( 'admin_init', 'aa_events_register_settings' );

/**
 * Get available preset date formats.
 *
 * @return array Format display name => format string pairs.
 */
function aa_events_get_date_format_presets() {
	$now = current_time( 'timestamp' );
	return array(
		'F j, Y'     => wp_date( 'F j, Y', $now ) . ' (' . esc_html__( 'Default', 'aa-events' ) . ')',
		'm/d/Y'      => wp_date( 'm/d/Y', $now ),
		'd/m/Y'      => wp_date( 'd/m/Y', $now ),
		'Y-m-d'      => wp_date( 'Y-m-d', $now ),
		'M j, Y'     => wp_date( 'M j, Y', $now ),
		'j M Y'      => wp_date( 'j M Y', $now ),
		'custom'     => esc_html__( 'Custom Format', 'aa-events' ),
	);
}

/**
 * Get available preset time formats.
 *
 * @return array Format display name => format string pairs.
 */
function aa_events_get_time_format_presets() {
	$now = current_time( 'timestamp' );
	return array(
		'g:i A'      => wp_date( 'g:i A', $now ) . ' (' . esc_html__( 'Default', 'aa-events' ) . ')',
		'H:i'        => wp_date( 'H:i', $now ),
		'g:i a'      => wp_date( 'g:i a', $now ),
		'h:i A'      => wp_date( 'h:i A', $now ),
		'custom'     => esc_html__( 'Custom Format', 'aa-events' ),
	);
}

/**
 * Render the settings page HTML.
 */
function aa_events_settings_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'aa-events' ) );
	}

	$show_badge    = get_option( 'aa_events_show_recurring_badge', true );
	$date_format   = get_option( 'aa_events_date_format', 'F j, Y' );
	$time_format   = get_option( 'aa_events_time_format', 'g:i A' );
	$date_presets  = aa_events_get_date_format_presets();
	$time_presets  = aa_events_get_time_format_presets();
	$is_custom_date = ! in_array( $date_format, array_keys( $date_presets ), true ) || 'custom' === $date_format;
	$is_custom_time = ! in_array( $time_format, array_keys( $time_presets ), true ) || 'custom' === $time_format;
	?>
	<div class="wrap">
		<h1><?php echo esc_html( __( 'AA Events Settings', 'aa-events' ) ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'aa_events_settings_group' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="aa_events_show_recurring_badge">
							<?php esc_html_e( 'Recurring Badge', 'aa-events' ); ?>
						</label>
					</th>
					<td>
						<input 
							type="checkbox" 
							id="aa_events_show_recurring_badge" 
							name="aa_events_show_recurring_badge" 
							value="1" 
							<?php checked( $show_badge, true ); ?>
						/>
						<label for="aa_events_show_recurring_badge">
							<?php esc_html_e( 'Show "Recurring" badge on event dates', 'aa-events' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, a "Recurring" badge will appear next to recurring event dates in calendar views and exports.', 'aa-events' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="aa_events_date_format">
							<?php esc_html_e( 'Date Format', 'aa-events' ); ?>
						</label>
					</th>
					<td>
						<select id="aa_events_date_format_select" onchange="aa_events_toggle_custom_date()">
							<?php foreach ( $date_presets as $format => $label ) : ?>
								<option value="<?php echo esc_attr( $format ); ?>" <?php selected( $date_format, $format ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input 
							type="hidden" 
							id="aa_events_date_format" 
							name="aa_events_date_format" 
							value="<?php echo esc_attr( $date_format ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Select a date format for event dates.', 'aa-events' ); ?>
						</p>
						<div id="aa_events_custom_date_field" style="<?php echo $is_custom_date ? '' : 'display: none;'; ?> margin-top: 10px;">
							<input 
								type="text" 
								id="aa_events_custom_date_format" 
								value="<?php echo esc_attr( $is_custom_date ? $date_format : '' ); ?>"
								placeholder="F j, Y"
								class="regular-text"
								onchange="aa_events_update_date_format()"
								oninput="aa_events_update_date_format()"
							/>
							<p class="description">
								<?php
								esc_html_e( 'Enter a custom PHP date format string. Examples: F j, Y (January 1, 2024), m/d/Y (01/01/2024), Y-m-d (2024-01-01).', 'aa-events' );
								echo ' <a href="https://www.php.net/manual/en/datetime.format.php" target="_blank">';
								esc_html_e( 'View PHP date format documentation', 'aa-events' );
								echo '</a>.';
								?>
							</p>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="aa_events_time_format">
							<?php esc_html_e( 'Time Format', 'aa-events' ); ?>
						</label>
					</th>
					<td>
						<select id="aa_events_time_format_select" onchange="aa_events_toggle_custom_time()">
							<?php foreach ( $time_presets as $format => $label ) : ?>
								<option value="<?php echo esc_attr( $format ); ?>" <?php selected( $time_format, $format ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input 
							type="hidden" 
							id="aa_events_time_format" 
							name="aa_events_time_format" 
							value="<?php echo esc_attr( $time_format ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Select a time format for event times.', 'aa-events' ); ?>
						</p>
						<div id="aa_events_custom_time_field" style="<?php echo $is_custom_time ? '' : 'display: none;'; ?> margin-top: 10px;">
							<input 
								type="text" 
								id="aa_events_custom_time_format" 
								value="<?php echo esc_attr( $is_custom_time ? $time_format : '' ); ?>"
								placeholder="g:i A"
								class="regular-text"
								onchange="aa_events_update_time_format()"
								oninput="aa_events_update_time_format()"
							/>
							<p class="description">
								<?php
								esc_html_e( 'Enter a custom PHP time format string. Examples: g:i A (1:30 PM), H:i (13:30), g:i a (1:30 pm).', 'aa-events' );
								echo ' <a href="https://www.php.net/manual/en/datetime.format.php" target="_blank">';
								esc_html_e( 'View PHP date format documentation', 'aa-events' );
								echo '</a>.';
								?>
							</p>
						</div>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<script>
		function aa_events_toggle_custom_date() {
			const select = document.getElementById( 'aa_events_date_format_select' );
			const customField = document.getElementById( 'aa_events_custom_date_field' );
			const customInput = document.getElementById( 'aa_events_custom_date_format' );
			const hiddenInput = document.getElementById( 'aa_events_date_format' );
			
			if ( 'custom' === select.value ) {
				customField.style.display = 'block';
				customInput.focus();
				hiddenInput.value = customInput.value || 'F j, Y';
			} else {
				customField.style.display = 'none';
				hiddenInput.value = select.value;
			}
		}

		function aa_events_update_date_format() {
			const customInput = document.getElementById( 'aa_events_custom_date_format' );
			const hiddenInput = document.getElementById( 'aa_events_date_format' );
			hiddenInput.value = customInput.value || 'F j, Y';
		}

		function aa_events_toggle_custom_time() {
			const select = document.getElementById( 'aa_events_time_format_select' );
			const customField = document.getElementById( 'aa_events_custom_time_field' );
			const customInput = document.getElementById( 'aa_events_custom_time_format' );
			const hiddenInput = document.getElementById( 'aa_events_time_format' );
			
			if ( 'custom' === select.value ) {
				customField.style.display = 'block';
				customInput.focus();
				hiddenInput.value = customInput.value || 'g:i A';
			} else {
				customField.style.display = 'none';
				hiddenInput.value = select.value;
			}
		}

		function aa_events_update_time_format() {
			const customInput = document.getElementById( 'aa_events_custom_time_format' );
			const hiddenInput = document.getElementById( 'aa_events_time_format' );
			hiddenInput.value = customInput.value || 'g:i A';
		}

		// Initialize on page load
		document.addEventListener( 'DOMContentLoaded', function() {
			aa_events_toggle_custom_date();
			aa_events_toggle_custom_time();
		} );
	</script>
	<?php
}
