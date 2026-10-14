<?php
/**
 * Regression tests for recurrence summaries with selected weekdays.
 *
 * @package AA_Events
 */

require_once __DIR__ . '/../includes/occurrences.php';

/**
 * Record a strict equality assertion failure.
 *
 * @param string $expected Expected summary.
 * @param string $actual   Actual summary.
 * @param string $case_name Test case label.
 */
function aa_events_test_recurrence_summary_assert_same( $expected, $actual, $case_name ) {
	if ( $expected !== $actual ) {
		$GLOBALS['aa_events_test_failures'][] = sprintf(
			"%s\nExpected: %s\nActual:   %s",
			$case_name,
			$expected,
			$actual
		);
	}
}

$weekly_summary_cases = array(
	'interval two with no end'           => array(
		'row'      => array(
			'repeat_enabled'   => true,
			'repeat_interval'  => 2,
			'repeat_unit'      => 'week',
			'repeat_byweekday' => array( 'thu' ),
			'repeat_end_type'  => 'never',
			'start_date'       => '2026-10-15',
		),
		'expected' => 'Event runs every 2 weeks on Thursday, indefinitely.',
	),
	'interval one with occurrence count' => array(
		'row'      => array(
			'repeat_enabled'   => true,
			'repeat_interval'  => 1,
			'repeat_unit'      => 'week',
			'repeat_byweekday' => array( 'thu' ),
			'repeat_end_type'  => 'after_count',
			'repeat_end_count' => 6,
			'start_date'       => '2026-10-15',
		),
		'expected' => 'Event runs every week on Thursday for 6 occurrences.',
	),
	'interval two with end date'         => array(
		'row'      => array(
			'repeat_enabled'   => true,
			'repeat_interval'  => 2,
			'repeat_unit'      => 'week',
			'repeat_byweekday' => array( 'thu' ),
			'repeat_end_type'  => 'on_date',
			'repeat_end_date'  => '2026-12-31',
			'start_date'       => '2026-10-15',
		),
		'expected' => 'Event runs every 2 weeks on Thursday through December 31, 2026.',
	),
);

foreach ( $weekly_summary_cases as $case => $test ) {
	aa_events_test_recurrence_summary_assert_same(
		$test['expected'],
		aa_events_describe_recurrence( $test['row'] ),
		$case
	);
}

if ( ! empty( $GLOBALS['aa_events_test_failures'] ) ) {
	throw new RuntimeException( esc_html( implode( "\n\n", $GLOBALS['aa_events_test_failures'] ) ) );
}
