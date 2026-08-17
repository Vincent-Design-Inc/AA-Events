<?php
/**
 * Event occurrence normalization, validation, and display helpers.
 *
 * @package AA_Events
 */

/**
 * Safely convert scalar or stringable field input to a trimmed string.
 *
 * @param mixed $value Raw field value.
 * @return string|null
 */
function aa_events_occurrence_string( $value ) {
	if ( is_scalar( $value ) ) {
		return trim( (string) $value );
	}
	if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
		try {
			return trim( (string) $value );
		} catch ( Throwable $error ) {
			return null;
		}
	}
	return null;
}

/**
 * Strictly parse and normalize a date or time value.
 *
 * @param mixed  $value  Value to parse.
 * @param string $format Input format.
 * @param string $output Output format.
 * @return string|null
 */
function aa_events_parse_occurrence_value( $value, $format, $output ) {
	$value = aa_events_occurrence_string( $value );
	if ( null === $value || '' === $value ) {
		return null;
	}

	$date   = DateTimeImmutable::createFromFormat( '!' . $format, $value, wp_timezone() );
	$errors = DateTimeImmutable::getLastErrors();
	if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( $format ) !== $value ) {
		return null;
	}

	return $date->format( $output );
}

/**
 * Normalize an ACF-style boolean without treating the string "false" as true.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function aa_events_occurrence_boolean( $value ) {
	$value = aa_events_occurrence_string( $value );
	return null !== $value && in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
}

/**
 * Parse a date returned by the built-in or supported override ACF fields.
 *
 * @param mixed $value Raw date.
 * @return string|null
 */
function aa_events_parse_occurrence_date( $value ) {
	foreach ( array( 'Y-m-d', 'Ymd', 'F j, Y' ) as $format ) {
		$date = aa_events_parse_occurrence_value( $value, $format, 'Y-m-d' );
		if ( null !== $date ) {
			return $date;
		}
	}
	return null;
}

/**
 * Parse a time with or without seconds.
 *
 * @param mixed $value Raw time.
 * @return string|null
 */
function aa_events_parse_occurrence_time( $value ) {
	foreach ( array( 'H:i:s', 'H:i', 'g:i a' ) as $format ) {
		$time = aa_events_parse_occurrence_value( $value, $format, 'H:i:s' );
		if ( null !== $time ) {
			return $time;
		}
	}
	return null;
}

/**
 * Read a field through its active ACF definition when possible.
 *
 * Hidden ACF metadata references can point to the inactive built-in or
 * override field key when both groups use the same field name. Resolving the
 * active definition by name keeps reads independent of that stale reference.
 *
 * @param string $name    Field name.
 * @param int    $post_id Event post ID.
 * @return mixed
 */
function aa_events_get_active_field_value( $name, $post_id ) {
	if ( function_exists( 'acf_get_field' ) && function_exists( 'acf_get_value' ) && function_exists( 'acf_format_value' ) ) {
		$field = acf_get_field( $name );
		if ( is_array( $field ) && $name === ( $field['name'] ?? '' ) ) {
			$value = acf_format_value( acf_get_value( $post_id, $field ), $post_id, $field );
			if ( is_array( $value ) ) {
				return $value;
			}
			if ( function_exists( 'get_post_meta' ) ) {
				$raw_value = get_post_meta( $post_id, $name, true );
				if ( is_array( $raw_value ) ) {
					return $raw_value;
				}
			}
			return $value;
		}
	}
	return get_field( $name, $post_id );
}

/**
 * Confirm a normalized time exists on its local calendar date.
 *
 * PHP deterministically selects an offset for ambiguous fall-back wall times;
 * exact round-trip checking rejects only nonexistent spring-forward wall times.
 *
 * @param string $date Date in Y-m-d format.
 * @param string $time Time in H:i:s format.
 * @return bool
 */
function aa_events_occurrence_local_time_is_valid( $date, $time ) {
	$value  = $date . ' ' . $time;
	$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, wp_timezone() );
	$errors = DateTimeImmutable::getLastErrors();
	return false !== $parsed && ( ! is_array( $errors ) || ( ! $errors['warning_count'] && ! $errors['error_count'] ) ) && $parsed->format( 'Y-m-d H:i:s' ) === $value;
}

/**
 * Validate already-parsed occurrence values without calling public validation.
 *
 * @param string $start_date Start date.
 * @param string $start_time Optional start time.
 * @param string $end_date   Effective end date.
 * @param string $end_time   Optional end time.
 * @param bool   $all_day    Whether times are ignored.
 * @return bool
 */
function aa_events_occurrence_range_is_valid( $start_date, $start_time, $end_date, $end_time, $all_day ) {
	if ( $end_date < $start_date ) {
		return false;
	}
	if ( $all_day ) {
		return true;
	}
	if ( $start_time && ! aa_events_occurrence_local_time_is_valid( $start_date, $start_time ) ) {
		return false;
	}
	if ( $end_time && ! aa_events_occurrence_local_time_is_valid( $end_date, $end_time ) ) {
		return false;
	}
	return $start_date !== $end_date || ! $start_time || ! $end_time || $end_time >= $start_time;
}

/**
 * Normalize a single occurrence row.
 *
 * The public shape records provenance without temporary keys. start_inferred is
 * true when the start came from a legacy combined/separate source rather than
 * a current repeater row. end_inferred is true when the effective end was
 * defaulted because no explicit end was supplied, and for all legacy sources.
 *
 * @param array  $row     Raw occurrence fields.
 * @param string $source  Source field format.
 * @param int    $post_id Event post ID.
 * @return array|null
 */
function aa_events_normalize_occurrence_row( array $row, $source = 'event_dates', $post_id = 0 ) {
	$source         = in_array( $source, array( 'event_dates', 'event_datetime', 'legacy_separate' ), true ) ? $source : 'event_dates';
	$start_inferred = 'event_dates' !== $source;
	$start_date     = aa_events_parse_occurrence_date( $row['start_date'] ?? $row['date'] ?? '' );
	if ( null === $start_date ) {
		return null;
	}

	$end_input = aa_events_occurrence_string( $row['end_date'] ?? '' );
	if ( null === $end_input ) {
		return null;
	}
	$end_date = '' === $end_input ? $start_date : aa_events_parse_occurrence_date( $end_input );
	if ( null === $end_date ) {
		return null;
	}

	$all_day        = aa_events_occurrence_boolean( $row['all_day'] ?? false );
	$end_time_input = aa_events_occurrence_string( $row['end_time'] ?? '' );
	$end_inferred   = $start_inferred || ( '' === $end_input && ( null === $end_time_input || '' === $end_time_input ) );
	$start_time     = '';
	$end_time       = '';
	if ( ! $all_day ) {
		if ( ! empty( $row['start_time'] ) ) {
			$start_time = aa_events_parse_occurrence_time( $row['start_time'] );
			if ( null === $start_time ) {
				return null;
			}
		}
		if ( ! empty( $row['end_time'] ) ) {
			$end_time = aa_events_parse_occurrence_time( $row['end_time'] );
			if ( null === $end_time ) {
				return null;
			}
		}
	}
	if ( ! aa_events_occurrence_range_is_valid( $start_date, $start_time, $end_date, $end_time, $all_day ) ) {
		return null;
	}

	return array(
		'post_id'        => (int) $post_id,
		'start_date'     => $start_date,
		'start_time'     => $start_time ? $start_time : '',
		'end_date'       => $end_date,
		'end_time'       => $end_time ? $end_time : '',
		'all_day'        => (bool) $all_day,
		'source'         => $source,
		'start_inferred' => $start_inferred,
		'end_inferred'   => $end_inferred,
	);
}

/**
 * Read all occurrences for an event, preferring the current repeater format.
 *
 * @param int $post_id Event post ID.
 * @return array
 */
function aa_events_get_occurrences( $post_id ) {
	$post_id     = (int) $post_id;
	$rows        = aa_events_get_active_field_value( 'event_dates', $post_id );
	$occurrences = array();

	if ( ! is_array( $rows ) || ! $rows ) {
		$rows = aa_events_get_active_field_value( 'multiple_dates', $post_id );
	}

	if ( is_array( $rows ) && $rows ) {
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				foreach ( aa_events_expand_recurring_occurrence( $row, $post_id ) as $occurrence ) {
					if ( is_array( $occurrence ) ) {
						$occurrences[] = $occurrence;
					}
				}
			}
		}
	} else {
		$legacy = aa_events_occurrence_string( get_field( 'event_datetime', $post_id ) );
		if ( null !== $legacy && '' !== $legacy ) {
			$date   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $legacy, wp_timezone() );
			$errors = DateTimeImmutable::getLastErrors();
			if ( false !== $date && ( ! is_array( $errors ) || ( ! $errors['warning_count'] && ! $errors['error_count'] ) ) && $date->format( 'Y-m-d H:i:s' ) === $legacy ) {
				$occurrences[] = aa_events_normalize_occurrence_row(
                    array(
						'date'       => $date->format( 'Y-m-d' ),
						'start_time' => $date->format( 'H:i:s' ),
                    ),
                    'event_datetime',
                    $post_id
                );
			}
		} else {
			$date = get_field( 'date', $post_id );
			if ( null !== aa_events_occurrence_string( $date ) && '' !== aa_events_occurrence_string( $date ) ) {
				$occurrence = aa_events_normalize_occurrence_row(
                    array(
						'date'       => $date,
						'start_time' => get_field( 'start_time', $post_id ),
                    ),
                    'legacy_separate',
                    $post_id
                );
				if ( null !== $occurrence ) {
					$occurrences[] = $occurrence;
				}
			}
		}
	}

	$decorated = array();
	foreach ( $occurrences as $index => $occurrence ) {
		$decorated[] = array(
			'occurrence' => $occurrence,
			'index'      => $index,
		);
	}

	usort(
		$decorated,
		function ( $left, $right ) {
			$left_occurrence  = $left['occurrence'];
			$right_occurrence = $right['occurrence'];
			$comparison       = aa_events_occurrence_start( $left_occurrence )->getTimestamp() <=> aa_events_occurrence_start( $right_occurrence )->getTimestamp();
			if ( 0 !== $comparison ) {
				return $comparison;
			}
			foreach ( array( 'end_date', 'end_time' ) as $key ) {
				$comparison = strcmp( $left_occurrence[ $key ], $right_occurrence[ $key ] );
				if ( 0 !== $comparison ) {
					return $comparison;
				}
			}
			$comparison = (int) $left_occurrence['all_day'] <=> (int) $right_occurrence['all_day'];
			if ( 0 !== $comparison ) {
				return $comparison;
			}
			$comparison = strcmp( $left_occurrence['source'], $right_occurrence['source'] );
			return 0 !== $comparison ? $comparison : $left['index'] <=> $right['index'];
		}
	);

	return array_column( $decorated, 'occurrence' );
}

/**
 * Get event dates in the legacy theme-helper shape.
 *
 * @deprecated Use aa_events_get_occurrences() instead.
 *
 * @param int $post_id Event post ID.
 * @return array<int,array{date:string,time:string,end_date:string,end_time:string,all_day:bool}>
 */
function events_get_all_event_dates( $post_id ) {
	return array_map(
		function ( $occurrence ) {
			return array(
				'date'     => $occurrence['start_date'],
				'time'     => $occurrence['start_time'],
				'end_date' => $occurrence['end_date'],
				'end_time' => $occurrence['end_time'],
				'all_day'  => $occurrence['all_day'],
			);
		},
		aa_events_get_occurrences( $post_id )
	);
}

/**
 * Validate a raw or normalized occurrence row.
 *
 * @param array $row Occurrence fields.
 * @return string Empty when valid, otherwise an error message.
 */
function aa_events_validate_occurrence_row( array $row ) {
	$start = aa_events_parse_occurrence_date( $row['start_date'] ?? $row['date'] ?? '' );
	if ( null === $start ) {
		return __( 'Start date is required and must be valid.', 'aa-events' );
	}
	$end_input = aa_events_occurrence_string( $row['end_date'] ?? '' );
	if ( null === $end_input ) {
		return __( 'End date must be valid.', 'aa-events' );
	}
	$end = '' === $end_input ? $start : aa_events_parse_occurrence_date( $end_input );
	if ( null === $end ) {
		return __( 'End date must be valid.', 'aa-events' );
	}
	if ( $end < $start ) {
		return __( 'End date cannot be earlier than start date.', 'aa-events' );
	}
	if ( aa_events_occurrence_boolean( $row['all_day'] ?? false ) ) {
		return '';
	}

	$start_time = empty( $row['start_time'] ) ? '' : aa_events_parse_occurrence_time( $row['start_time'] );
	$end_time   = empty( $row['end_time'] ) ? '' : aa_events_parse_occurrence_time( $row['end_time'] );
	if ( ! empty( $row['start_time'] ) && null === $start_time ) {
		return __( 'Start time must be valid.', 'aa-events' );
	}
	if ( ! empty( $row['end_time'] ) && null === $end_time ) {
		return __( 'End time must be valid.', 'aa-events' );
	}
	if ( $start_time && ! aa_events_occurrence_local_time_is_valid( $start, $start_time ) ) {
		return __( 'Start time must be valid.', 'aa-events' );
	}
	if ( $end_time && ! aa_events_occurrence_local_time_is_valid( $end, $end_time ) ) {
		return __( 'End time must be valid.', 'aa-events' );
	}
	if ( $start === $end && $start_time && $end_time && $end_time < $start_time ) {
		return __( 'End time cannot be earlier than start time on the same date.', 'aa-events' );
	}
	return '';
}

/**
 * Build an occurrence start value.
 *
 * @param array $occurrence Normalized occurrence.
 * @return DateTimeImmutable
 */
function aa_events_occurrence_start( array $occurrence ) {
	$time = empty( $occurrence['all_day'] ) && ! empty( $occurrence['start_time'] ) ? $occurrence['start_time'] : '00:00:00';
	return new DateTimeImmutable( $occurrence['start_date'] . ' ' . $time, wp_timezone() );
}

/**
 * Build an occurrence end value.
 *
 * @param array $occurrence Normalized occurrence.
 * @return DateTimeImmutable
 */
function aa_events_occurrence_end( array $occurrence ) {
	$end_date = $occurrence['end_date'] ? $occurrence['end_date'] : $occurrence['start_date'];
	if ( ! empty( $occurrence['all_day'] ) ) {
		$time = '00:00:00';
	} elseif ( ! empty( $occurrence['end_time'] ) ) {
		$time = $occurrence['end_time'];
	} elseif ( $end_date === $occurrence['start_date'] && ! empty( $occurrence['start_time'] ) ) {
		$time = $occurrence['start_time'];
	} else {
		$time = '00:00:00';
	}
	return new DateTimeImmutable( $end_date . ' ' . $time, wp_timezone() );
}

/**
 * Format a normalized occurrence for display.
 *
 * @param array $occurrence Normalized occurrence.
 * @return string
 */
function aa_events_format_occurrence( array $occurrence ) {
	$timezone = wp_timezone();
	$start    = aa_events_occurrence_start( $occurrence );
	$end      = aa_events_occurrence_end( $occurrence );
	$same_day = $occurrence['start_date'] === $occurrence['end_date'];

	if ( ! empty( $occurrence['all_day'] ) ) {
		if ( $same_day ) {
			$label = wp_date( 'F j, Y', $start->getTimestamp(), $timezone );
		} elseif ( $start->format( 'Y-m' ) === $end->format( 'Y-m' ) ) {
			$label = wp_date( 'F j', $start->getTimestamp(), $timezone ) . '–' . wp_date( 'j, Y', $end->getTimestamp(), $timezone );
		} elseif ( $start->format( 'Y' ) === $end->format( 'Y' ) ) {
			$label = wp_date( 'F j', $start->getTimestamp(), $timezone ) . '–' . wp_date( 'F j, Y', $end->getTimestamp(), $timezone );
		} else {
			$label = wp_date( 'F j, Y', $start->getTimestamp(), $timezone ) . '–' . wp_date( 'F j, Y', $end->getTimestamp(), $timezone );
		}
		return $label . ' — ' . __( 'All day', 'aa-events' );
	}

	$start_date = wp_date( 'F j, Y', $start->getTimestamp(), $timezone );
	$start_time = empty( $occurrence['start_time'] ) ? '' : wp_date( 'g:i A', $start->getTimestamp(), $timezone );
	$end_time   = empty( $occurrence['end_time'] ) ? '' : wp_date( 'g:i A', $end->getTimestamp(), $timezone );
	if ( $same_day ) {
		if ( $start_time && $end_time ) {
			return $start_date . ', ' . $start_time . '–' . $end_time;
		}
		if ( $end_time ) {
			/* translators: 1: localized occurrence date, 2: localized end time. */
			return sprintf( __( '%1$s, until %2$s', 'aa-events' ), $start_date, $end_time );
		}
		return $start_date . ( $start_time ? ', ' . $start_time : '' );
	}
	$start_label = $start_date . ( $start_time ? ', ' . $start_time : '' );
	$end_label   = wp_date( 'F j, Y', $end->getTimestamp(), $timezone ) . ( $end_time ? ', ' . $end_time : '' );
	return $start_label . '–' . $end_label;
}

/**
 * Render safe, accessible time markup for one normalized occurrence.
 *
 * Points return one visible <time>. Ranges return an aria-hidden visible
 * formatted label plus a screen-reader-only, localized pair of endpoint
 * <time> elements. Each endpoint is date-only unless that endpoint has an
 * explicit time; all returned dynamic text and attributes are escaped.
 *
 * @param array $occurrence Normalized occurrence from aa_events_get_occurrences().
 * @return string Escaped HTML safe to print directly.
 */
function aa_events_occurrence_time_markup( array $occurrence ) {
	$start          = aa_events_occurrence_start( $occurrence );
	$end            = aa_events_occurrence_end( $occurrence );
	$all_day        = ! empty( $occurrence['all_day'] );
	$has_start_time = ! $all_day && ! empty( $occurrence['start_time'] );
	$has_end_time   = ! $all_day && ! empty( $occurrence['end_time'] );
	$is_range       = $occurrence['start_date'] !== $occurrence['end_date'] || $has_end_time;
	$visible        = esc_html( aa_events_format_occurrence( $occurrence ) );
	$start_value    = $has_start_time ? $start->format( DATE_W3C ) : $occurrence['start_date'];

	if ( ! $is_range ) {
		return '<time datetime="' . esc_attr( $start_value ) . '">' . $visible . '</time>';
	}

	$end_value   = $has_end_time ? $end->format( DATE_W3C ) : $occurrence['end_date'];
	$start_label = wp_date( $has_start_time ? 'F j, Y, g:i A' : 'F j, Y', $start->getTimestamp(), wp_timezone() );
	$end_label   = wp_date( $has_end_time ? 'F j, Y, g:i A' : 'F j, Y', $end->getTimestamp(), wp_timezone() );

	return '<span aria-hidden="true">' . $visible . '</span>'
		. '<span class="screen-reader-text">' . esc_html( __( 'Starts:', 'aa-events' ) )
		. ' <time datetime="' . esc_attr( $start_value ) . '">' . esc_html( $start_label ) . '</time>. '
		. esc_html( __( 'Ends:', 'aa-events' ) )
		. ' <time datetime="' . esc_attr( $end_value ) . '">' . esc_html( $end_label ) . '</time>.</span>';
}

/**
 * Copy legacy local-override repeater rows into the canonical repeater.
 *
 * Existing legacy metadata is intentionally preserved. Canonical rows always
 * win, making this migration safe to run repeatedly.
 *
 * @param int $post_id Event post ID.
 * @return bool Whether canonical rows were written.
 */
function aa_events_migrate_multiple_dates_occurrences( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || 'event' !== get_post_type( $post_id ) ) {
		return false;
	}

	$write_rows = static function ( $source_rows ) use ( $post_id ) {
		$rows = array();
		foreach ( $source_rows as $source_row ) {
			if ( ! is_array( $source_row ) ) {
				continue;
			}
			$occurrence = aa_events_normalize_occurrence_row( $source_row, 'event_dates', $post_id );
			if ( null === $occurrence ) {
				continue;
			}
			$rows[] = array(
				'date'       => $occurrence['start_date'],
				'start_time' => $occurrence['start_time'],
				'end_date'   => empty( $source_row['end_date'] ) ? '' : $occurrence['end_date'],
				'end_time'   => $occurrence['end_time'],
				'all_day'    => $occurrence['all_day'] ? 1 : 0,
			);
		}
		if ( ! $rows ) {
			return false;
		}

		$selector = 'event_dates';
		if ( function_exists( 'acf_get_field' ) ) {
			$field = acf_get_field( 'event_dates' );
			if ( is_array( $field ) && ! empty( $field['key'] ) ) {
				$selector = $field['key'];
			}
		}
		return (bool) update_field( $selector, $rows, $post_id );
	};

	if ( function_exists( 'get_post_meta' ) ) {
		$raw_canonical_rows = get_post_meta( $post_id, 'event_dates', true );
		if ( is_array( $raw_canonical_rows ) && $raw_canonical_rows ) {
			return $write_rows( $raw_canonical_rows );
		}
	}

	$canonical_rows = aa_events_get_active_field_value( 'event_dates', $post_id );
	if ( is_array( $canonical_rows ) && $canonical_rows ) {
		return false;
	}

	$legacy_rows = aa_events_get_active_field_value( 'multiple_dates', $post_id );
	if ( ! is_array( $legacy_rows ) || ! $legacy_rows ) {
		return false;
	}

	return $write_rows( $legacy_rows );
}

/**
 * Migrate the first parseable legacy occurrence into the repeater.
 *
 * @param int $post_id Event post ID.
 * @return bool Whether a repeater row was written.
 */
function aa_events_migrate_legacy_occurrence( $post_id ) {
	$post_id = (int) $post_id;
	$rows    = get_field( 'event_dates', $post_id );
	if ( is_array( $rows ) && $rows ) {
		return false;
	}

	$occurrences = aa_events_get_occurrences( $post_id );
	if ( ! isset( $occurrences[0] ) || ! in_array( $occurrences[0]['source'], array( 'event_datetime', 'legacy_separate' ), true ) ) {
		return false;
	}

	$occurrence = $occurrences[0];
	$row        = array(
		'date'       => $occurrence['start_date'],
		'start_time' => $occurrence['start_time'],
		'end_date'   => '',
		'end_time'   => '',
		'all_day'    => 0,
	);

	return (bool) update_field( 'event_dates', array( $row ), $post_id );
}

/**
 * Migrate legacy occurrence data after ACF has saved an event.
 *
 * @param int $post_id Saved post ID.
 * @return void
 */
function aa_events_migrate_legacy_on_save( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || 'event' !== get_post_type( $post_id ) ) {
		return;
	}
	aa_events_migrate_multiple_dates_occurrences( $post_id );
	aa_events_migrate_legacy_occurrence( $post_id );
}

/**
 * Maintain the canonical earliest occurrence value used for event sorting.
 *
 * @param int $post_id Saved post ID.
 * @return void
 */
function aa_events_sync_sort_start_on_save( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || 'event' !== get_post_type( $post_id ) ) {
		return;
	}

	$occurrences = aa_events_get_occurrences( $post_id );
	if ( ! isset( $occurrences[0] ) ) {
		delete_post_meta( $post_id, '_aa_events_sort_start' );
		delete_post_meta( $post_id, '_aa_events_sort_end' );
		update_post_meta( $post_id, '_aa_events_occurrence_index_version', AA_EVENTS_VERSION );
		return;
	}

	$occurrence = $occurrences[0];
	$sort_start = $occurrence['start_date'] . ' ' . ( $occurrence['start_time'] ? $occurrence['start_time'] : '00:00:00' );
	$sort_end   = aa_events_occurrence_end( $occurrence );
	foreach ( $occurrences as $candidate ) {
		$candidate_end = aa_events_occurrence_end( $candidate );
		if ( $candidate_end > $sort_end ) {
			$sort_end = $candidate_end;
		}
	}
	update_post_meta( $post_id, '_aa_events_sort_start', $sort_start );
	update_post_meta( $post_id, '_aa_events_sort_end', $sort_end->format( 'Y-m-d H:i:s' ) );
	update_post_meta( $post_id, '_aa_events_occurrence_index_version', AA_EVENTS_VERSION );
}

/**
 * Schedule one bounded occurrence-index backfill batch when none is pending.
 *
 * @return void
 */
function aa_events_schedule_occurrence_index_backfill() {
	if ( AA_EVENTS_VERSION !== get_option( 'aa_events_occurrence_index_version', '' ) && ! wp_next_scheduled( 'aa_events_occurrence_index_backfill' ) ) {
		wp_schedule_single_event( time() + 60, 'aa_events_occurrence_index_backfill' );
	}
}

/**
 * Backfill one bounded batch of published events with missing/outdated indexes.
 *
 * @return void
 */
function aa_events_run_occurrence_index_backfill() {
	$batch = new WP_Query(
		array(
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_aa_events_occurrence_index_version',
					'value'   => AA_EVENTS_VERSION,
					'compare' => '!=',
				),
				array(
					'key'     => '_aa_events_occurrence_index_version',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
	foreach ( $batch->posts as $post_id ) {
		aa_events_sync_sort_start_on_save( $post_id );
	}
	if ( 50 === count( $batch->posts ) ) {
		aa_events_schedule_occurrence_index_backfill();
	} else {
		update_option( 'aa_events_occurrence_index_version', AA_EVENTS_VERSION );
	}
}

/**
 * Check whether a metadata key can affect an event occurrence start.
 *
 * @param string $meta_key Metadata key.
 * @return bool
 */
function aa_events_sort_source_meta_key( $meta_key ) {
	if ( in_array( $meta_key, array( 'event_dates', 'multiple_dates', 'event_datetime', 'date', 'start_time' ), true ) ) {
		return true;
	}

	return (bool) preg_match( '/^(event_dates|multiple_dates)_[0-9]+_(date|start_time|end_date|end_time|all_day)$/', $meta_key );
}

/**
 * Queue an event for canonical sort synchronization after a metadata mutation.
 *
 * ACF and direct WordPress metadata updates can emit several hooks per logical
 * change. The queue coalesces those writes so fresh values are read once at
 * request shutdown, after all related row mutations have completed.
 *
 * @param mixed  $meta_id    Metadata ID or IDs.
 * @param int    $object_id  Post ID.
 * @param string $meta_key   Metadata key.
 * @param mixed  $meta_value Metadata value.
 * @return void
 */
function aa_events_queue_sort_sync_for_meta_change( $meta_id, $object_id, $meta_key, $meta_value ) {
	unset( $meta_id, $meta_value );
	$object_id = (int) $object_id;
	if ( ! aa_events_sort_source_meta_key( (string) $meta_key ) || ! $object_id || 'event' !== get_post_type( $object_id ) || wp_is_post_autosave( $object_id ) || wp_is_post_revision( $object_id ) ) {
		return;
	}

	if ( ! isset( $GLOBALS['aa_events_sort_sync_queue'] ) || ! is_array( $GLOBALS['aa_events_sort_sync_queue'] ) ) {
		$GLOBALS['aa_events_sort_sync_queue'] = array();
	}
	$GLOBALS['aa_events_sort_sync_queue'][ $object_id ] = true;
}

/**
 * Synchronize all canonical event start values queued during this request.
 *
 * @return void
 */
function aa_events_run_queued_sort_sync() {
	$queue                                = isset( $GLOBALS['aa_events_sort_sync_queue'] ) && is_array( $GLOBALS['aa_events_sort_sync_queue'] ) ? $GLOBALS['aa_events_sort_sync_queue'] : array();
	$GLOBALS['aa_events_sort_sync_queue'] = array();

	foreach ( array_keys( $queue ) as $post_id ) {
		if ( function_exists( 'acf_flush_value_cache' ) ) {
			foreach ( array( 'event_dates', 'multiple_dates', 'event_datetime', 'date', 'start_time' ) as $field_name ) {
				acf_flush_value_cache( $post_id, $field_name );
			}
		}
		aa_events_sync_sort_start_on_save( $post_id );
	}
}

/**
 * Determine whether an ACF occurrence repeater belongs to an event edit form.
 *
 * @param array $field ACF field definition.
 * @return bool
 */
function aa_events_occurrence_validation_applies( $field ) {
	if ( ! is_array( $field ) || ( isset( $field['type'] ) && 'repeater' !== $field['type'] ) ) {
		return false;
	}

	if ( 'field_61b0c7f0a3e8f' === ( $field['key'] ?? '' ) ) {
		return true;
	}
	if ( 'event_dates' !== ( $field['name'] ?? '' ) ) {
		return false;
	}

	$post_id = function_exists( 'acf_get_form_data' ) ? acf_get_form_data( 'post_id' ) : 0;
	if ( is_string( $post_id ) && 0 === strpos( $post_id, 'post_' ) ) {
		$post_id = substr( $post_id, 5 );
	}

	return (int) $post_id > 0 && 'event' === get_post_type( (int) $post_id );
}

/**
 * Normalize a submitted ACF date picker value for occurrence validation.
 *
 * @param mixed $value Submitted date value.
 * @return mixed
 */
function aa_events_normalize_submitted_date( $value ) {
	$string = aa_events_occurrence_string( $value );
	if ( null === $string || ! preg_match( '/^[0-9]{8}$/', $string ) ) {
		return $value;
	}

	$normalized = aa_events_parse_occurrence_value( $string, 'Ymd', 'Y-m-d' );
	return null === $normalized ? $value : $normalized;
}

/**
 * Validate submitted ACF repeater rows using stable subfield keys.
 *
 * @param mixed  $valid Existing validation state.
 * @param mixed  $value Submitted repeater value.
 * @param array  $field ACF field definition.
 * @param string $input Submitted input name.
 * @return mixed
 */
function aa_events_validate_occurrence_repeater( $valid, $value, $field, $input ) {
	unset( $input );
	if ( true !== $valid || ! aa_events_occurrence_validation_applies( $field ) ) {
		return $valid;
	}
	if ( '' === $value || null === $value ) {
		return true;
	}
	if ( ! is_array( $value ) ) {
		return __( 'Occurrences data is malformed.', 'aa-events' );
	}
	unset( $value['acfcloneindex'] );

	$key_map = array(
		'field_61b0c7f1a3e90'      => 'date',
		'field_61b0c7f2a3e91'      => 'start_time',
		'field_aa_events_end_date' => 'end_date',
		'field_aa_events_end_time' => 'end_time',
		'field_aa_events_all_day'  => 'all_day',
	);
	foreach ( $field['sub_fields'] ?? array() as $sub_field ) {
		if ( is_array( $sub_field ) && ! empty( $sub_field['key'] ) && ! empty( $sub_field['name'] ) ) {
			$key_map[ $sub_field['key'] ] = $sub_field['name'];
		}
	}
	foreach ( array( 'date', 'start_time', 'end_date', 'end_time', 'all_day' ) as $name ) {
		$key_map[ $name ] = $name;
	}
	foreach ( array_values( $value ) as $index => $submitted_row ) {
		if ( ! is_array( $submitted_row ) ) {
			/* translators: %d: one-based occurrence row number. */
			return sprintf( __( 'Occurrence %d: Occurrence data is malformed.', 'aa-events' ), $index + 1 );
		}
		$row = array(
			'date'       => '',
			'start_time' => '',
			'end_date'   => '',
			'end_time'   => '',
			'all_day'    => '',
		);
		foreach ( $key_map as $key => $name ) {
			if ( array_key_exists( $key, $submitted_row ) ) {
				$row[ $name ] = $submitted_row[ $key ];
			}
		}
		$row['date']     = aa_events_normalize_submitted_date( $row['date'] );
		$row['end_date'] = aa_events_normalize_submitted_date( $row['end_date'] );
		$error           = aa_events_validate_occurrence_row( $row );
		if ( '' !== $error ) {
			/* translators: 1: one-based occurrence row number, 2: validation error. */
			return sprintf( __( 'Occurrence %1$d: %2$s', 'aa-events' ), $index + 1, $error );
		}
	}

	return true;
}

/**
 * Expand a single repeater row into a series of normalized occurrences.
 *
 * Returns an array whose first element is the in-place normalized row.
 * Subsequent elements are children generated from the parent's pattern.
 * Non-recurring rows and rows with invalid patterns return a single element.
 *
 * @param array $row     Raw repeater row (may include recurrence fields).
 * @param int   $post_id Event post ID.
 * @return array<int,array> Normalized occurrences.
 */
function aa_events_expand_recurring_occurrence( array $row, int $post_id = 0 ) {
	$parent = aa_events_normalize_occurrence_row( $row, 'event_dates', $post_id );
	if ( null === $parent ) {
		return array();
	}

	if ( ! aa_events_occurrence_boolean( $row['repeat_enabled'] ?? false ) ) {
		return array( $parent );
	}

	$interval = aa_events_repeat_positive_integer( $row['repeat_interval'] ?? 1 );
	$unit     = aa_events_repeat_unit( $row['repeat_unit'] ?? 'week' );
	$end_type = aa_events_repeat_end_type( $row['repeat_end_type'] ?? 'never' );
	if ( null === $interval || null === $unit || null === $end_type ) {
		return array( $parent );
	}

	$end_date  = 'on_date' === $end_type ? aa_events_repeat_end_date( $row['repeat_end_date'] ?? '' ) : null;
	$end_count = 'after_count' === $end_type ? aa_events_repeat_positive_integer( $row['repeat_end_count'] ?? 12, 12 ) : null;
	if ( 'on_date' === $end_type && ( null === $end_date || $end_date < $parent['start_date'] ) ) {
		return array( $parent );
	}
	if ( 'after_count' === $end_type && null === $end_count ) {
		return array( $parent );
	}

	$byweekday = aa_events_repeat_byweekday( $row['repeat_byweekday'] ?? '' );

	$results      = array( $parent );
	$safety_start = new DateTimeImmutable( $parent['start_date'] . ' 00:00:00', wp_timezone() );
	$safety_end   = $safety_start->modify( '+5 years' )->format( 'Y-m-d' );
	$cap          = 730;

	$candidate_start_date = $parent['start_date'];
	$step_index           = 0;

	while ( count( $results ) < $cap ) {
		++$step_index;
		$candidate_start_date = aa_events_repeat_next_start_date( $parent['start_date'], $interval, $unit, $byweekday, $step_index );
		if ( null === $candidate_start_date ) {
			break;
		}
		$start_time = $parent['start_time'] ? $parent['start_time'] : '00:00:00';
		$candidate_start = new DateTimeImmutable( $candidate_start_date . ' ' . $start_time, wp_timezone() );
		if ( $candidate_start_date > $safety_end ) {
			break;
		}
		if ( 'on_date' === $end_type && $candidate_start_date > $end_date ) {
			break;
		}

		$child = aa_events_repeat_build_child( $parent, $candidate_start_date, $post_id );
		if ( null === $child ) {
			continue;
		}
		$results[] = $child;

		if ( 'after_count' === $end_type && count( $results ) >= $end_count ) {
			break;
		}
	}

	return $results;
}

/**
 * Coerce a value to a positive integer or null.
 *
 * @param mixed $value   Raw value.
 * @param int   $default Default when value is empty.
 * @return int|null
 */
function aa_events_repeat_positive_integer( $value, int $default = 1 ) {
	if ( is_array( $value ) ) { return null; }
	$string = aa_events_occurrence_string( $value );
	if ( null === $string || '' === $string ) {
		return $default > 0 ? $default : null;
	}
	if ( ! preg_match( '/^[0-9]+$/', $string ) ) {
		return null;
	}
	$int = (int) $string;
	return $int > 0 ? $int : null;
}

/**
 * Coerce a repeat unit value to one of the supported values or null.
 *
 * @param mixed $value Raw value.
 * @return string|null
 */
function aa_events_repeat_unit( $value ) {
	$string = strtolower( (string) aa_events_occurrence_string( $value ) );
	if ( in_array( $string, array( 'day', 'week', 'month', 'year' ), true ) ) {
		return $string;
	}
	if ( '' === $string ) {
		return 'week';
	}
	return null;
}

/**
 * Coerce a repeat end type to one of the supported values or null.
 *
 * @param mixed $value Raw value.
 * @return string|null
 */
function aa_events_repeat_end_type( $value ) {
	$string = strtolower( (string) aa_events_occurrence_string( $value ) );
	if ( in_array( $string, array( 'never', 'on_date', 'after_count' ), true ) ) {
		return $string;
	}
	if ( '' === $string ) {
		return 'never';
	}
	return null;
}

/**
 * Parse a repeat end date in Y-m-d format.
 *
 * @param mixed $value Raw date.
 * @return string|null
 */
function aa_events_repeat_end_date( $value ) {
	return aa_events_parse_occurrence_date( $value );
}

/**
 * Normalize a byweekday value to a list of lowercase tokens.
 *
 * @param mixed $value Raw value (string with comma separators or array).
 * @return array<int,string>
 */
function aa_events_repeat_byweekday( $value ) {
	$tokens = array();
	if ( is_array( $value ) ) {
		foreach ( $value as $item ) {
			$token = strtolower( (string) aa_events_occurrence_string( $item ) );
			if ( '' !== $token ) {
				$tokens[] = $token;
			}
		}
	} else {
		$string = (string) aa_events_occurrence_string( $value );
		foreach ( preg_split( '/[,\s]+/', $string ) as $token ) {
			$token = strtolower( trim( $token ) );
			if ( '' !== $token ) {
				$tokens[] = $token;
			}
		}
	}
	$valid  = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
	$tokens = array_values(
		array_unique(
			array_filter(
				$tokens,
				function ( $t ) use ( $valid ) {
					return in_array( $t, $valid, true );
				}
			)
		)
	);
	sort( $tokens );
	return $tokens;
}

/**
 * Compute the next start date for a recurring row.
 *
 * @param string $start_date Parent start date in Y-m-d.
 * @param int    $interval   Positive integer.
 * @param string $unit       One of day/week/month/year.
 * @param array  $byweekday  Weekday tokens; only used when unit=week.
 * @param int    $step_index 1-based step index (parent is step 0).
 * @return string|null Y-m-d date or null when no further date is possible.
 */
function aa_events_repeat_next_start_date( $start_date, int $interval, string $unit, array $byweekday, int $step_index ) {
	$base = new DateTimeImmutable( $start_date . ' 00:00:00', wp_timezone() );

	if ( 'day' === $unit ) {
		return $base->modify( '+' . ( $interval * $step_index ) . ' days' )->format( 'Y-m-d' );
	}
	if ( 'week' === $unit ) {
		if ( ! $byweekday ) {
			return $base->modify( '+' . ( $interval * 7 * $step_index ) . ' days' )->format( 'Y-m-d' );
		}
		$weekday_keys = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
		$byweekday    = array_flip( $byweekday );
		$day          = $base->modify( '+1 day' );
		$horizon      = $day->modify( '+' . ( 730 * 7 ) . ' days' );
		$count        = 0;
		while ( $day <= $horizon ) {
			$token = $weekday_keys[ ( (int) $day->format( 'N' ) ) - 1 ];
			if ( isset( $byweekday[ $token ] ) ) {
				++$count;
				if ( $count === $step_index ) {
					return $day->format( 'Y-m-d' );
				}
			}
			$day = $day->modify( '+1 day' );
		}
		return null;
	}
	if ( 'month' === $unit ) {
		return aa_events_add_months( $base, $interval * $step_index )->format( 'Y-m-d' );
	}
	if ( 'year' === $unit ) {
		$year          = (int) $base->format( 'Y' ) + ( $interval * $step_index );
		$month         = (int) $base->format( 'n' );
		$day           = (int) $base->format( 'j' );
		$days_in_target = (int) ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), wp_timezone() ) )->format( 't' );
		$effective_day  = $day > $days_in_target ? $days_in_target : $day;
		return sprintf( '%04d-%02d-%02d', $year, $month, $effective_day );
	}
	return null;
}

/**
 * Build a normalized child occurrence from a parent and a candidate date.
 *
 * @param array  $parent         Parent normalized occurrence.
 * @param string $candidate_date Y-m-d candidate date.
 * @param int    $post_id        Post ID.
 * @return array|null
 */
function aa_events_repeat_build_child( array $parent, string $candidate_date, int $post_id ) {
	$parent_end   = new DateTimeImmutable( $parent['end_date'] . ' 00:00:00', wp_timezone() );
	$parent_start = new DateTimeImmutable( $parent['start_date'] . ' 00:00:00', wp_timezone() );
	$diff         = $parent_start->diff( $parent_end );
	$duration_days = (int) $diff->days;

	$child_start_date = $candidate_date;
	$child_end_date   = ( new DateTimeImmutable( $candidate_date . ' 00:00:00', wp_timezone() ) )
		->modify( '+' . $duration_days . ' days' )
		->format( 'Y-m-d' );

	$child = array(
		'post_id'        => $post_id,
		'start_date'     => $child_start_date,
		'start_time'     => $parent['start_time'],
		'end_date'       => $child_end_date,
		'end_time'       => $parent['end_time'],
		'all_day'        => $parent['all_day'],
		'source'         => 'event_dates_recurring',
		'start_inferred' => false,
		'end_inferred'   => $parent['end_inferred'],
	);

	if ( ! aa_events_occurrence_range_is_valid( $child['start_date'], $child['start_time'], $child['end_date'], $child['end_time'], $child['all_day'] ) ) {
		return null;
	}
	return $child;
}

/**
 * Add a positive month count to a date, anchoring on day-of-month.
 *
 * Jan 31 + 1 month = Feb 28 (or Feb 29 in a leap year), not Mar 3.
 *
 * @param DateTimeImmutable $date   Base date.
 * @param int               $months Number of months to add (positive integer).
 * @return DateTimeImmutable
 */
function aa_events_add_months( DateTimeImmutable $date, int $months ) {
	$months = max( 0, $months );
	$year   = (int) $date->format( 'Y' );
	$month  = (int) $date->format( 'n' );
	$day    = (int) $date->format( 'j' );

	$total_months = ( $year * 12 + ( $month - 1 ) ) + $months;
	$target_year  = intdiv( $total_months, 12 );
	$target_month = ( $total_months % 12 ) + 1;

	$days_in_target = (int) ( new DateTimeImmutable(
		sprintf( '%04d-%02d-01', $target_year, $target_month ),
		wp_timezone()
	) )->format( 't' );
	$candidate      = (object) array(
		'year'  => $target_year,
		'month' => $target_month,
		'day'   => $day,
	);
	if ( $candidate->day > $days_in_target ) {
		$candidate->day = $days_in_target;
	}

	$result = DateTimeImmutable::createFromFormat(
		'!Y-m-d H:i:s',
		sprintf( '%04d-%02d-%02d %s', $candidate->year, $candidate->month, $candidate->day, $date->format( 'H:i:s' ) ),
		wp_timezone()
	);
	$errors = DateTimeImmutable::getLastErrors();
	if ( false === $result || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
		return $date;
	}
	return $result;
}

/**
 * Determine whether an occurrence was generated from a recurring row.
 *
 * @param array $occurrence Normalized occurrence.
 * @return bool
 */
function aa_events_is_recurring_occurrence( array $occurrence ) {
	return isset( $occurrence['source'] ) && 'event_dates_recurring' === $occurrence['source'];
}

/**
 * Coerce a repeat interval value through the ACF validation pipeline.
 *
 * Returns the original value when invalid so the row degrades to a single
 * occurrence rather than blocking save.
 *
 * @param mixed $valid Existing validation state.
 * @param mixed $value Submitted value.
 * @return mixed
 */
function aa_events_repeat_validate_interval( $valid, $value ) {
	unset( $valid );
	return null === aa_events_repeat_positive_integer( $value ) ? $value : (string) aa_events_repeat_positive_integer( $value );
}

/**
 * Coerce a repeat end count value through the ACF validation pipeline.
 *
 * @param mixed $valid Existing validation state.
 * @param mixed $value Submitted value.
 * @return mixed
 */
function aa_events_repeat_validate_end_count( $valid, $value ) {
	unset( $valid );
	return null === aa_events_repeat_positive_integer( $value ) ? $value : (string) aa_events_repeat_positive_integer( $value );
}

/**
 * Coerce a repeat end date through the ACF validation pipeline.
 *
 * @param mixed $valid Existing validation state.
 * @param mixed $value Submitted value.
 * @return mixed
 */
function aa_events_repeat_validate_end_date( $valid, $value ) {
	unset( $valid );
	$normalized = aa_events_repeat_end_date( $value );
	return null === $normalized ? $value : $normalized;
}

/**
 * Coerce a repeat unit through the ACF validation pipeline.
 *
 * @param mixed $valid Existing validation state.
 * @param mixed $value Submitted value.
 * @return mixed
 */
function aa_events_repeat_validate_unit( $valid, $value ) {
	unset( $valid );
	return null === aa_events_repeat_unit( $value ) ? $value : aa_events_repeat_unit( $value );
}

/**
 * Coerce a repeat end type through the ACF validation pipeline.
 *
 * @param mixed $valid Existing validation state.
 * @param mixed $value Submitted value.
 * @return mixed
 */
function aa_events_repeat_validate_end_type( $valid, $value ) {
	unset( $valid );
	return null === aa_events_repeat_end_type( $value ) ? $value : aa_events_repeat_end_type( $value );
}

/**
 * Normalize a byweekday value through the ACF validation pipeline.
 *
 * @param mixed $valid Existing validation state.
 * @param mixed $value Submitted value.
 * @return mixed
 */
function aa_events_repeat_validate_byweekday( $valid, $value ) {
	unset( $valid );
	return aa_events_repeat_byweekday( $value );
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'acf/save_post', 'aa_events_migrate_legacy_on_save', 20 );
	add_action( 'acf/save_post', 'aa_events_sync_sort_start_on_save', 30 );
	add_action( 'added_post_meta', 'aa_events_queue_sort_sync_for_meta_change', 10, 4 );
	add_action( 'updated_post_meta', 'aa_events_queue_sort_sync_for_meta_change', 10, 4 );
	add_action( 'deleted_post_meta', 'aa_events_queue_sort_sync_for_meta_change', 10, 4 );
	add_action( 'shutdown', 'aa_events_run_queued_sort_sync' );
	add_action( 'init', 'aa_events_schedule_occurrence_index_backfill' );
	add_action( 'aa_events_occurrence_index_backfill', 'aa_events_run_occurrence_index_backfill' );
}
if ( function_exists( 'add_filter' ) ) {
	add_filter( 'acf/validate_value/key=field_61b0c7f0a3e8f', 'aa_events_validate_occurrence_repeater', 10, 4 );
	add_filter( 'acf/validate_value/name=event_dates', 'aa_events_validate_occurrence_repeater', 10, 4 );
	add_filter( 'acf/validate_value/name=repeat_interval', 'aa_events_repeat_validate_interval', 10, 4 );
	add_filter( 'acf/validate_value/name=repeat_end_count', 'aa_events_repeat_validate_end_count', 10, 4 );
	add_filter( 'acf/validate_value/name=repeat_end_date', 'aa_events_repeat_validate_end_date', 10, 4 );
	add_filter( 'acf/validate_value/name=repeat_unit', 'aa_events_repeat_validate_unit', 10, 4 );
	add_filter( 'acf/validate_value/name=repeat_end_type', 'aa_events_repeat_validate_end_type', 10, 4 );
	add_filter( 'acf/validate_value/name=repeat_byweekday', 'aa_events_repeat_validate_byweekday', 10, 4 );
}
