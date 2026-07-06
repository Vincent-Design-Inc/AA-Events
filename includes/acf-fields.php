<?php
/**
 * Register ACF Fields.
 *
 * @package AA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Check whether an ACF field group created in the admin targets the event post type.
 *
 * @return bool True if an external field group is assigned to events.
 */
function events_has_admin_field_group() {
	if ( ! function_exists( 'acf_get_field_groups' ) ) {
		return false;
	}

	$groups = acf_get_field_groups( array( 'post_type' => 'event' ) );

	foreach ( $groups as $group ) {
		if ( isset( $group['active'] ) && ! $group['active'] ) {
			continue;
		}

		if (
			isset( $group['key'], $group['local'] )
			&& 'group_61b0c5f5a3e7e' === $group['key']
			&& 'php' === $group['local']
		) {
			continue;
		}

		$targets_events = false;
		foreach ( $group['location'] ?? array() as $location_group ) {
			foreach ( $location_group as $rule ) {
				if (
					isset( $rule['param'], $rule['operator'], $rule['value'] )
					&& 'post_type' === $rule['param']
					&& '==' === $rule['operator']
					&& 'event' === $rule['value']
				) {
					$targets_events = true;
					break 2;
				}
			}
		}

		if ( ! $targets_events || ! function_exists( 'acf_get_fields' ) ) {
			continue;
		}

		foreach ( (array) acf_get_fields( $group ) as $field ) {
			if (
				isset( $field['name'] )
				&& in_array( $field['name'], array( 'event_datetime', 'event_dates', 'multiple_dates', 'date' ), true )
			) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Register the ACF field group.
 *
 * Skips registration when an admin-created field group is already
 * assigned to the event post type, allowing full editorial control.
 *
 * Skips registration when an admin-created field group is already
 * assigned to the event post type, allowing full editorial control.
 */
function events_register_acf_fields() {
	if ( events_has_admin_field_group() ) {
		return;
	}

	if ( events_has_admin_field_group() ) {
		return;
	}

	if ( function_exists( 'acf_add_local_field_group' ) ) :

		acf_add_local_field_group(
			array(
				'key'                   => 'group_61b0c5f5a3e7e',
				'title'                 => 'Event Details',
				'fields'                => array(
					array(
						'key'               => 'field_61b0c609a3e7f',
						'label'             => 'Event Date & Time',
						'name'              => 'event_datetime',
						'type'              => 'date_time_picker',
						'instructions'      => 'Legacy compatibility field. Use Occurrences for new events.',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'display_format'    => 'F j, Y g:i a',
						'return_format'     => 'Y-m-d H:i:s',
						'first_day'         => 1,
					),
					array(
						'key'               => 'field_61b0c6a5a3e82',
						'label'             => 'Event Cost',
						'name'              => 'event_cost',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => '',
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
						'maxlength'         => '',
					),
					array(
						'key'               => 'field_61b0c7f0a3e8f',
						'label'             => 'Occurrences',
						'name'              => 'event_dates',
						'type'              => 'repeater',
						'instructions'      => 'Each row is one occurrence. Add an end date for a continuous multi-day event; add more rows when the event recurs.',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'collapsed'         => '',
						'min'               => 0,
						'max'               => 0,
						'layout'            => 'row',
						'button_label'      => 'Add Occurrence',
						'sub_fields'        => array(
							array(
								'key'               => 'field_61b0c7f1a3e90',
								'label'             => 'Date',
								'name'              => 'date',
								'type'              => 'date_picker',
								'instructions'      => '',
								'required'          => 1,
								'conditional_logic' => 0,
								'wrapper'           => array(
									'width' => '25',
									'class' => '',
									'id'    => '',
								),
								'display_format'    => 'F j, Y',
								'return_format'     => 'Y-m-d',
								'first_day'         => 1,
							),
							array(
								'key'               => 'field_61b0c7f2a3e91',
								'label'             => 'Start Time',
								'name'              => 'start_time',
								'type'              => 'time_picker',
								'instructions'      => '',
								'required'          => 0,
								'conditional_logic' => 0,
								'wrapper'           => array(
									'width' => '20',
									'class' => '',
									'id'    => '',
								),
								'display_format'    => 'g:i a',
								'return_format'     => 'H:i:s',
							),
							array(
								'key'               => 'field_aa_events_end_date',
								'label'             => 'End Date',
								'name'              => 'end_date',
								'type'              => 'date_picker',
								'instructions'      => '',
								'required'          => 0,
								'conditional_logic' => 0,
								'wrapper'           => array(
									'width' => '25',
									'class' => '',
									'id'    => '',
								),
								'display_format'    => 'F j, Y',
								'return_format'     => 'Y-m-d',
								'first_day'         => 1,
							),
							array(
								'key'               => 'field_aa_events_end_time',
								'label'             => 'End Time',
								'name'              => 'end_time',
								'type'              => 'time_picker',
								'instructions'      => '',
								'required'          => 0,
								'conditional_logic' => 0,
								'wrapper'           => array(
									'width' => '20',
									'class' => '',
									'id'    => '',
								),
								'display_format'    => 'g:i a',
								'return_format'     => 'H:i:s',
							),
							array(
								'key'               => 'field_aa_events_all_day',
								'label'             => 'All Day',
								'name'              => 'all_day',
								'type'              => 'true_false',
								'instructions'      => '',
								'required'          => 0,
								'conditional_logic' => 0,
								'wrapper'           => array(
									'width' => '10',
									'class' => '',
									'id'    => '',
								),
								'default_value'     => 0,
								'ui'                => 1,
							),
						),
					),
					array(
						'key'               => 'field_61b0c6cba3e83',
						'label'             => 'Online/In-Person',
						'name'              => 'event_location_type',
						'type'              => 'true_false',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'message'           => '',
						'default_value'     => 0,
						'ui'                => 1,
						'ui_on_text'        => 'Online',
						'ui_off_text'       => 'In-Person',
					),
					array(
						'key'               => 'field_61b0c702a3e84',
						'label'             => 'Event URL',
						'name'              => 'event_url',
						'type'              => 'url',
						'instructions'      => '',
						'required'          => 1,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_61b0c6cba3e83',
									'operator' => '==',
									'value'    => '1',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => '',
						'placeholder'       => '',
					),
					array(
						'key'               => 'field_61b0c72fa3e85',
						'label'             => 'Event Address',
						'name'              => 'event_address',
						'type'              => 'textarea',
						'instructions'      => '',
						'required'          => 1,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_61b0c6cba3e83',
									'operator' => '!=',
									'value'    => '1',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => '',
						'placeholder'       => '',
						'maxlength'         => '',
						'rows'              => '',
						'new_lines'         => '',
					),
					array(
						'key'               => 'field_aa_events_contact_email',
						'label'             => 'Contact Email',
						'name'              => 'event_contact_email',
						'type'              => 'email',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => '',
						'placeholder'       => '',
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'event',
						),
					),
				),
				'menu_order'            => 0,
				'position'              => 'side',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen'        => '',
				'active'                => true,
				'description'           => '',
			)
		);

	endif;
}
add_action( 'acf/init', 'events_register_acf_fields' );
