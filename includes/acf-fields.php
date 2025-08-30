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
 * Register the ACF field group.
 */
function events_register_acf_fields() {
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
						'instructions'      => '',
						'required'          => 1,
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
				'position'              => 'normal',
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
