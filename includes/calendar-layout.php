<?php
/**
 * Pure calendar layout helpers.
 *
 * @package AA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the complete week grid for one month.
 *
 * @param mixed $year          Four-digit year.
 * @param mixed $month         Month number.
 * @param mixed $start_of_week WordPress weekday number (0 Sunday through 6 Saturday).
 * @return array
 * @throws InvalidArgumentException For invalid arguments.
 */
function aa_events_calendar_month( $year, $month, $start_of_week ) {
	if ( ! is_int( $year ) || ! is_int( $month ) || ! is_int( $start_of_week ) ) {
		throw new InvalidArgumentException( 'Calendar year, month, and start of week must be integers.' );
	}
	if ( $year < 1 || $year > 9999 || $month < 1 || $month > 12 || $start_of_week < 0 || $start_of_week > 6 ) {
		throw new InvalidArgumentException( 'Invalid calendar year, month, or start of week.' );
	}

	$month_value = sprintf( '%04d-%02d-01', $year, $month );
	$month_start = DateTimeImmutable::createFromFormat( '!Y-m-d', $month_value, wp_timezone() );
	$errors      = DateTimeImmutable::getLastErrors();
	if ( false === $month_start || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $month_start->format( 'Y-m-d' ) !== $month_value ) {
		throw new InvalidArgumentException( 'Invalid calendar month.' );
	}
	$month_end     = $month_start->modify( 'last day of this month' );
	$leading_days  = ( (int) $month_start->format( 'w' ) - $start_of_week + 7 ) % 7;
	$grid_start    = $month_start->modify( '-' . $leading_days . ' days' );
	$trailing_days = ( $start_of_week + 6 - (int) $month_end->format( 'w' ) + 7 ) % 7;
	$grid_end      = $month_end->modify( '+' . $trailing_days . ' days' );
	$weeks         = array();
	$week_start    = $grid_start;
	$week_index    = 0;
	while ( $week_start <= $grid_end ) {
		$days = array();
		for ( $day = 0; $day < 7; ++$day ) {
			$days[] = $week_start->modify( '+' . $day . ' days' );
		}
		$weeks[]    = array(
			'index' => $week_index,
			'key'   => $week_start->format( 'Y-m-d' ),
			'start' => $week_start,
			'end'   => $week_start->modify( '+6 days' ),
			'days'  => $days,
		);
		$week_start = $week_start->modify( '+7 days' );
		++$week_index;
	}

	return compact( 'month_start', 'month_end', 'grid_start', 'grid_end', 'start_of_week', 'weeks' );
}

/**
 * Determine whether an occurrence overlaps an inclusive date range.
 *
 * @param array             $occurrence Occurrence metadata.
 * @param DateTimeImmutable $start      Range start.
 * @param DateTimeImmutable $end        Range end.
 * @return bool
 */
function aa_events_occurrence_intersects_range( array $occurrence, DateTimeImmutable $start, DateTimeImmutable $end ) {
	$parse_date       = function ( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$date   = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		$errors = DateTimeImmutable::getLastErrors();
		return false !== $date && ( ! is_array( $errors ) || ( ! $errors['warning_count'] && ! $errors['error_count'] ) ) && $date->format( 'Y-m-d' ) === $value ? $date : null;
	};
	$occurrence_start = $parse_date( $occurrence['start_date'] ?? null );
	$occurrence_end   = $parse_date( $occurrence['end_date'] ?? ( $occurrence['start_date'] ?? null ) );
	return null !== $occurrence_start && null !== $occurrence_end && $occurrence_end >= $occurrence_start && $end >= $start && $occurrence_start <= $end && $occurrence_end >= $start;
}

/**
 * Clip an occurrence to a month and split it at week boundaries.
 *
 * @param array $occurrence Occurrence metadata.
 * @param array $month      Calendar month shape.
 * @return array
 */
function aa_events_calendar_segments( array $occurrence, array $month ) {
	if ( ! isset( $month['month_start'], $month['month_end'], $month['weeks'] ) || ! $month['month_start'] instanceof DateTimeImmutable || ! $month['month_end'] instanceof DateTimeImmutable || ! aa_events_occurrence_intersects_range( $occurrence, $month['month_start'], $month['month_end'] ) ) {
		return array();
	}
	$parse_date    = function ( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$date   = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		$errors = DateTimeImmutable::getLastErrors();
		return false !== $date && ( ! is_array( $errors ) || ( ! $errors['warning_count'] && ! $errors['error_count'] ) ) && $date->format( 'Y-m-d' ) === $value ? $date : null;
	};
	$true_start    = $parse_date( $occurrence['start_date'] );
	$true_end      = $parse_date( $occurrence['end_date'] ?? $occurrence['start_date'] );
	$visible_start = max( $true_start, $month['month_start'] );
	$visible_end   = min( $true_end, $month['month_end'] );
	$segments      = array();

	foreach ( $month['weeks'] as $week_index => $week ) {
		if ( $visible_start > $week['end'] || $visible_end < $week['start'] ) {
			continue;
		}
		$segment_start               = max( $visible_start, $week['start'] );
		$segment_end                 = min( $visible_end, $week['end'] );
		$segment                     = $occurrence;
		$segment['week_index']       = (int) $week_index;
		$segment['week_key']         = $week['key'] ?? $week['start']->format( 'Y-m-d' );
		$segment['segment_start']    = $segment_start;
		$segment['segment_end']      = $segment_end;
		$segment['start_column']     = (int) $week['start']->diff( $segment_start )->days;
		$segment['span']             = (int) $segment_start->diff( $segment_end )->days + 1;
		$segment['continues_before'] = $true_start < $segment_start;
		$segment['continues_after']  = $true_end > $segment_end;
		$segments[]                  = $segment;
	}
	return $segments;
}

/**
 * Sort segments and assign the first non-overlapping lane.
 *
 * @param array $segments Week segments.
 * @return array
 */
function aa_events_assign_segment_lanes( array $segments ) {
	$decorated = array();
	foreach ( $segments as $index => $segment ) {
		$decorated[] = array(
			'segment' => $segment,
			'order'   => $index,
		);
	}
	usort(
		$decorated,
		function ( $left, $right ) {
			$comparison = (int) ( $left['segment']['start_column'] ?? 0 ) <=> (int) ( $right['segment']['start_column'] ?? 0 );
			if ( 0 === $comparison ) {
				$comparison = (int) ( $right['segment']['span'] ?? 0 ) <=> (int) ( $left['segment']['span'] ?? 0 );
			}
			if ( 0 === $comparison ) {
				$comparison = (int) ( $left['segment']['post_id'] ?? 0 ) <=> (int) ( $right['segment']['post_id'] ?? 0 );
			}
			return 0 === $comparison ? $left['order'] <=> $right['order'] : $comparison;
		}
	);

	$lane_ends = array();
	$segments  = array();
	foreach ( $decorated as $item ) {
		$segment = $item['segment'];
		$start   = (int) ( $segment['start_column'] ?? 0 );
		$end     = $start + max( 1, (int) ( $segment['span'] ?? 1 ) ) - 1;
		$lane    = 0;
		while ( isset( $lane_ends[ $lane ] ) && $lane_ends[ $lane ] >= $start ) {
			++$lane;
		}
		$segment['lane']    = $lane;
		$lane_ends[ $lane ] = $end;
		$segments[]         = $segment;
	}
	return $segments;
}

/**
 * Group all occurrence segments into the month's complete week list.
 *
 * @param array $occurrences Occurrences to lay out.
 * @param array $month       Calendar month shape.
 * @return array
 */
function aa_events_group_calendar_weeks( array $occurrences, array $month ) {
	$weeks = array();
	foreach ( $month['weeks'] ?? array() as $index => $week ) {
		$week['segments']   = array();
		$week['lane_count'] = 0;
		$weeks[ $index ]    = $week;
	}
	foreach ( $occurrences as $occurrence ) {
		if ( ! is_array( $occurrence ) ) {
			continue;
		}
		foreach ( aa_events_calendar_segments( $occurrence, $month ) as $segment ) {
			if ( isset( $weeks[ $segment['week_index'] ] ) ) {
				$weeks[ $segment['week_index'] ]['segments'][] = $segment;
			}
		}
	}
	foreach ( $weeks as &$week ) {
		$week['segments'] = aa_events_assign_segment_lanes( $week['segments'] );
		if ( $week['segments'] ) {
			$week['lane_count'] = max( array_column( $week['segments'], 'lane' ) ) + 1;
		}
	}
	unset( $week );
	return array_values( $weeks );
}

/**
 * Sort calendar occurrences for the mobile agenda.
 *
 * @param array $occurrences Enriched normalized occurrences.
 * @return array
 */
function aa_events_sort_calendar_occurrences( array $occurrences ) {
	$decorated = array();
	foreach ( $occurrences as $index => $occurrence ) {
		if ( ! is_array( $occurrence ) ) {
			continue;
		}
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
			if ( 0 === $comparison ) {
				$comparison = strcasecmp( (string) ( $left_occurrence['title'] ?? '' ), (string) ( $right_occurrence['title'] ?? '' ) );
			}
			if ( 0 === $comparison ) {
				$comparison = (int) ( $left_occurrence['post_id'] ?? 0 ) <=> (int) ( $right_occurrence['post_id'] ?? 0 );
			}
			foreach ( array( 'end_date', 'end_time' ) as $key ) {
				if ( 0 !== $comparison ) {
					break;
				}
				$comparison = strcmp( (string) ( $left_occurrence[ $key ] ?? '' ), (string) ( $right_occurrence[ $key ] ?? '' ) );
			}
			return 0 === $comparison ? $left['index'] <=> $right['index'] : $comparison;
		}
	);

	return array_column( $decorated, 'occurrence' );
}
