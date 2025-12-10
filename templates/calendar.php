<?php
/**
 * The template for displaying the events calendar.
 *
 * @package AA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Use custom query vars (avoid WP date archive/canonical behavior on `year`)
$month_param = 'aa_month';
$year_param  = 'aa_year';

// Get current month and year (read-only query vars; validate instead of nonce)
$month_input = filter_input(
	INPUT_GET,
	$month_param,
	FILTER_VALIDATE_INT,
	array(
		'options' => array(
			'min_range' => 1,
			'max_range' => 12,
		),
	)
);

// Back-compat for legacy `month` query arg if present.
if ( false === $month_input || null === $month_input ) {
	$month_input = filter_input(
		INPUT_GET,
		'month',
		FILTER_VALIDATE_INT,
		array(
			'options' => array(
				'min_range' => 1,
				'max_range' => 12,
			),
		)
	);
}

$year_input = filter_input(
	INPUT_GET,
	$year_param,
	FILTER_VALIDATE_INT,
	array(
		'options' => array(
			// Choose a sane range for years.
			'min_range' => 1970,
			'max_range' => 2200,
		),
	)
);

// Back-compat for legacy `year` query arg if present.
if ( false === $year_input || null === $year_input ) {
	$year_input = filter_input(
		INPUT_GET,
		'year',
		FILTER_VALIDATE_INT,
		array(
			'options' => array(
				'min_range' => 1970,
				'max_range' => 2200,
			),
		)
	);
}
$curMonth = ( false !== $month_input && null !== $month_input ) ? (int) $month_input : (int) wp_date( 'n' );
$curYear  = ( false !== $year_input && null !== $year_input ) ? (int) $year_input : (int) wp_date( 'Y' );

// Get previous and next month and year.
$prev_month = $curMonth - 1;
$prev_year  = $curYear;

if ( $prev_month < 1 ) {
    $prev_month = 12;
    --$prev_year;
}

$next_month = $curMonth + 1;
$next_year  = $curYear;
if ( $next_month > 12 ) {
    $next_month = 1;
    ++$next_year;
}

// Get the number of days in the month.
$days_in_month = cal_days_in_month( CAL_GREGORIAN, $curMonth, $curYear );

// Get the first day of the month in site timezone.
$tz       = wp_timezone();
$first_ts = ( new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $curYear, $curMonth ), $tz ) )->getTimestamp();

// Respect the site's chosen week start (0 = Sunday, 6 = Saturday).
$start_of_week = (int) get_option( 'start_of_week', 1 );
if ( $start_of_week < 0 || $start_of_week > 6 ) {
	$start_of_week = 0;
}

// Calculate how many empty cells to prepend before day 1 based on the start day.
$first_weekday_of_month = (int) wp_date( 'w', $first_ts ); // 0 (Sun) - 6 (Sat).
$leading_empty_cells    = ( $first_weekday_of_month - $start_of_week + 7 ) % 7;

// Build weekday labels in the configured order.
$weekdays = array(
	array(
		'abbr'  => __( 'Sun', 'aa-events' ),
		'label' => __( 'Sunday', 'aa-events' ),
	),
	array(
		'abbr'  => __( 'Mon', 'aa-events' ),
		'label' => __( 'Monday', 'aa-events' ),
	),
	array(
		'abbr'  => __( 'Tue', 'aa-events' ),
		'label' => __( 'Tuesday', 'aa-events' ),
	),
	array(
		'abbr'  => __( 'Wed', 'aa-events' ),
		'label' => __( 'Wednesday', 'aa-events' ),
	),
	array(
		'abbr'  => __( 'Thu', 'aa-events' ),
		'label' => __( 'Thursday', 'aa-events' ),
	),
	array(
		'abbr'  => __( 'Fri', 'aa-events' ),
		'label' => __( 'Friday', 'aa-events' ),
	),
	array(
		'abbr'  => __( 'Sat', 'aa-events' ),
		'label' => __( 'Saturday', 'aa-events' ),
	),
);

$ordered_weekdays = array_merge(
	array_slice( $weekdays, $start_of_week ),
	array_slice( $weekdays, 0, $start_of_week )
);

// Get events for the current month.
$events = new WP_Query(
    array(
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => 'event_datetime',
                'value'   => array( "$curYear-$curMonth-01 00:00:00", "$curYear-$curMonth-$days_in_month 23:59:59" ),
                'compare' => 'BETWEEN',
                'type'    => 'DATETIME',
            ),
        ),
    )
);

$events_by_day = array();

if ( $events->have_posts() ) {
    while ( $events->have_posts() ) {
        $events->the_post();
        $event_date              = get_field( 'event_datetime' );
        $event_ts                = ( new DateTimeImmutable( $event_date, $tz ) )->getTimestamp();
        $day                     = (int) wp_date( 'j', $event_ts );
        $events_by_day[ $day ][] = '<a href="' . get_permalink() . '">' . get_the_title() . '</a>';
    }

    wp_reset_postdata();
}

// Get today's date.
$todayDay   = intval( wp_date( 'j' ) );
$todayMonth = intval( wp_date( 'n' ) );
$todayYear  = intval( wp_date( 'Y' ) );

// Helper to check if today is in current view.
$isTodayInCurrentMonth = ( $curMonth === $todayMonth && $curYear === $todayYear );
?>

<?php
// Modify the day cell output to add highlight for today.
$originalEchoDayCell = function ( $day, $firstDay, $eventsByDay ) use ( $isTodayInCurrentMonth, $todayDay, $todayMonth, $todayYear, $curMonth, $curYear ) {
    $cellClass = '';
    if ( $day === $todayDay && $curMonth === $todayMonth && $curYear === $todayYear ) {
        $cellClass = 'today-cell';
    }
    echo '<td' . ( $cellClass ? ' class="' . esc_attr( $cellClass ) . '"' : '' ) . '>';
    echo '<div class="day-number">' . esc_html( $day ) . '</div>';
    if ( isset( $eventsByDay[ $day ] ) ) {
        echo '<ul class="events-list">';
        foreach ( $eventsByDay[ $day ] as $event ) {
            echo '<li>' . wp_kses_post( $event ) . '</li>';
        }
        echo '</ul>';
    }
    echo '</td>';
};

// Overwrite the default day cell output in the table loop.
ob_start();
for ( $day = 1; $day <= $days_in_month; $day++ ) {
    if ( ( ( $day + $leading_empty_cells - 1 ) % 7 ) === 0 && $day > 1 ) {
        echo '</tr><tr>';
    }
    $originalEchoDayCell( $day, $leading_empty_cells, $events_by_day );
}
$calendarRows = ob_get_clean();

// Replace the original loop output with the new one.
$calendarOutput = preg_replace(
    '/for\s*\(\s*\$day\s*=\s*1;\s*\$day\s*<=\s*\$days_in_month;\s*\$day\+\+\s*\)\s*\{.*?\}/s',
    $calendarRows,
    ob_get_contents()
);
?>

<div class="aa-events-calendar-wrap container">
	<div class="aa-events-calendar-today-link" style="margin-bottom: 1em;">
		<a href="?aa_month=<?php echo esc_attr( $todayMonth ); ?>&aa_year=<?php echo esc_attr( $todayYear ); ?>" class="aa-events-calendar-today-btn">
		<?php esc_html_e( 'Today', 'aa-events' ); ?>
		</a>
	</div>

    <div class="aa-events-calendar-header">
        <?php
        // Calculate previous and next month names and years for aria-labels (site timezone)
        $prev_ts       = ( new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $prev_year, $prev_month ), $tz ) )->getTimestamp();
        $next_ts       = ( new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $next_year, $next_month ), $tz ) )->getTimestamp();
        $prevMonthName = wp_date( 'F', $prev_ts );
        $nextMonthName = wp_date( 'F', $next_ts );
        ?>
        <a
		href="?aa_month=<?php echo esc_attr( $prev_month ); ?>&aa_year=<?php echo esc_attr( $prev_year ); ?>"
		aria-label="<?php echo esc_attr( "Go to $prevMonthName $prev_year" ); ?>"
		>&laquo; <?php esc_html_e( 'Previous Month', 'aa-events' ); ?></a>

			<h2><?php echo esc_html( wp_date( 'F Y', $first_ts ) ); ?></h2>

		<a
		href="?aa_month=<?php echo esc_attr( $next_month ); ?>&aa_year=<?php echo esc_attr( $next_year ); ?>"
		aria-label="<?php echo esc_attr( "Go to $nextMonthName $next_year" ); ?>"
		><?php esc_html_e( 'Next Month', 'aa-events' ); ?> &raquo;</a>
    </div>

    <table class="aa-events-calendar" role="grid" aria-labelledby="calendar-heading">
        <caption id="calendar-heading" class="screen-reader-text"><?php echo esc_html( gmdate( 'F Y', strtotime( "$curYear-$curMonth-01" ) ) ); ?> <?php esc_html_e( 'Events Calendar', 'aa-events' ); ?></caption>
        <thead>
            <tr>
				<?php foreach ( $ordered_weekdays as $weekday ) : ?>
					<th scope="col" abbr="<?php echo esc_attr( $weekday['abbr'] ); ?>"><?php echo esc_html( $weekday['label'] ); ?></th>
				<?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php
                // Add empty cells for the first week.
                for ( $i = 0; $i < $leading_empty_cells; $i++ ) {
                    echo '<td role="gridcell" class="empty-cell"></td>';
                }

                // Loop through the days of the month.
                for ( $day = 1; $day <= $days_in_month; $day++ ) {
                    if ( ( ( $day + $leading_empty_cells - 1 ) % 7 ) === 0 && $day > 1 ) {
                        echo '</tr><tr>';
                    }

                    $cellClass = '';

                    if ( $isTodayInCurrentMonth && $day === $todayDay ) {
                        $cellClass = 'today-cell';
                    }

                    $cell_ts = ( new DateTimeImmutable( sprintf( '%04d-%02d-%02d 00:00:00', $curYear, $curMonth, $day ), $tz ) )->getTimestamp();
                    echo '<td role="gridcell" aria-label="' . esc_attr( wp_date( 'F j, Y', $cell_ts ) ) . '" ' . ( $cellClass ? ' class="' . esc_attr( $cellClass ) . '"' : '' ) . '>';

                    echo '<div class="day-number">' . esc_html( $day ) . '</div>';

                    if ( isset( $events_by_day[ $day ] ) ) {
                        echo '<ul class="events-list">';

                        foreach ( $events_by_day[ $day ] as $event ) {
                            echo '<li>' . wp_kses_post( $event ) . '</li>';
                        }

                        echo '</ul>';
                    }
                    echo '</td>';
                }

                // Add empty cells for the last week.
                $remaining_days = ( 7 - ( ( $days_in_month + $leading_empty_cells ) % 7 ) ) % 7;

                if ( $remaining_days < 7 ) {
                    for ( $i = 0; $i < $remaining_days; $i++ ) {
                        echo '<td class="empty-cell"></td>';
                    }
                }
                ?>
            </tr>
        </tbody>
    </table>
</div>
