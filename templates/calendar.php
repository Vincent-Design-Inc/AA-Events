<?php
/**
 * The template for displaying the events calendar.
 *
 * @package AA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$month_param = 'aa_month';
$year_param  = 'aa_year';
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
			'min_range' => 1970,
			'max_range' => 2200,
		),
	)
);
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

$cur_month = ( false !== $month_input && null !== $month_input ) ? (int) $month_input : (int) wp_date( 'n' );
$cur_year  = ( false !== $year_input && null !== $year_input ) ? (int) $year_input : (int) wp_date( 'Y' );
$tz        = wp_timezone();

$start_of_week = (int) get_option( 'start_of_week', 1 );
if ( $start_of_week < 0 || $start_of_week > 6 ) {
	$start_of_week = 0;
}
$month = aa_events_calendar_month( $cur_year, $cur_month, $start_of_week );

$weekdays         = array(
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
$ordered_weekdays = array_merge( array_slice( $weekdays, $start_of_week ), array_slice( $weekdays, 0, $start_of_week ) );

$calendar_occurrences = array();
$events               = new WP_Query(
	array(
		'post_type'              => 'event',
		'posts_per_page'         => -1,
		'post_status'            => 'publish',
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => false,
		'meta_query'             => array(
			'relation' => 'OR',
			array(
				'relation' => 'AND',
				array(
					'key'     => '_aa_events_occurrence_index_version',
					'value'   => AA_EVENTS_VERSION,
					'compare' => '=',
				),
				array(
					'key'     => '_aa_events_sort_start',
					'value'   => $month['month_end']->format( 'Y-m-d 23:59:59' ),
					'compare' => '<=',
					'type'    => 'DATETIME',
				),
				array(
					'key'     => '_aa_events_sort_end',
					'value'   => $month['month_start']->format( 'Y-m-d 00:00:00' ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => '_aa_events_occurrence_index_version',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_aa_events_occurrence_index_version',
					'value'   => AA_EVENTS_VERSION,
					'compare' => '!=',
				),
			),
		),
	)
);
foreach ( $events->posts as $event_id ) {
	$event_title     = get_the_title( $event_id );
	$event_permalink = get_permalink( $event_id );
	foreach ( aa_events_get_occurrences( $event_id ) as $occurrence ) {
		if ( ! is_array( $occurrence ) || ! aa_events_occurrence_intersects_range( $occurrence, $month['month_start'], $month['month_end'] ) ) {
			continue;
		}
		$occurrence['post_id']   = (int) $event_id;
		$occurrence['title']     = $event_title;
		$occurrence['permalink'] = $event_permalink;
		$calendar_occurrences[]  = $occurrence;
	}
}

$calendar_occurrences = aa_events_sort_calendar_occurrences( $calendar_occurrences );
$calendar_weeks       = aa_events_group_calendar_weeks( $calendar_occurrences, $month );

$today             = new DateTimeImmutable( wp_date( 'Y-m-d' ) . ' 00:00:00', $tz );
$prev              = $month['month_start']->modify( '-1 month' );
$next              = $month['month_start']->modify( '+1 month' );
$heading_id        = wp_unique_id( 'aa-events-calendar-heading-' );
$agenda_heading_id = wp_unique_id( 'aa-events-calendar-agenda-heading-' );
$month_label       = wp_date( 'F Y', $month['month_start']->getTimestamp(), $tz );
$current_url       = add_query_arg( array() );
$calendar_base_url = remove_query_arg( array( $month_param, $year_param, 'month', 'year' ), $current_url );
$calendar_url      = static function ( DateTimeImmutable $date ) use ( $calendar_base_url, $month_param, $year_param ) {
	return add_query_arg(
		array(
			$month_param => (int) $date->format( 'n' ),
			$year_param  => (int) $date->format( 'Y' ),
		),
		$calendar_base_url
	);
};
?>

<div class="aa-events-calendar-wrap container" data-aa-events-calendar>
	<div class="aa-events-calendar-utility">
		<div class="aa-events-calendar-today-link">
			<a href="<?php echo esc_url( $calendar_url( $today ) ); ?>" class="aa-events-calendar-today-btn"><?php esc_html_e( 'Today', 'aa-events' ); ?></a>
		</div>
		<div class="aa-events-calendar-view-switcher" data-aa-events-view-switcher role="group" aria-label="<?php echo esc_attr( __( 'Calendar view', 'aa-events' ) ); ?>">
			<button type="button" class="aa-events-calendar-view-button" data-aa-events-view-button="calendar" aria-pressed="true"><?php esc_html_e( 'Calendar', 'aa-events' ); ?></button>
			<button type="button" class="aa-events-calendar-view-button" data-aa-events-view-button="list" aria-pressed="false"><?php esc_html_e( 'List', 'aa-events' ); ?></button>
		</div>
	</div>

	<div class="aa-events-calendar-header">
		<?php /* translators: %s: localized month and year. */ ?>
		<a href="<?php echo esc_url( $calendar_url( $prev ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Go to %s', 'aa-events' ), wp_date( 'F Y', $prev->getTimestamp(), $tz ) ) ); ?>">&laquo; <?php esc_html_e( 'Previous Month', 'aa-events' ); ?></a>
		<h2 id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( $month_label ); ?></h2>
		<?php /* translators: %s: localized month and year. */ ?>
		<a href="<?php echo esc_url( $calendar_url( $next ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Go to %s', 'aa-events' ), wp_date( 'F Y', $next->getTimestamp(), $tz ) ) ); ?>"><?php esc_html_e( 'Next Month', 'aa-events' ); ?> &raquo;</a>
	</div>

	<div class="aa-events-calendar-desktop" data-aa-events-view-panel="calendar">
		<div class="aa-events-calendar-grid" role="grid" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<div class="aa-events-calendar-weekdays" role="row">
				<?php foreach ( $ordered_weekdays as $weekday ) : ?>
					<div class="aa-events-calendar-weekday" role="columnheader"><abbr title="<?php echo esc_attr( $weekday['label'] ); ?>"><?php echo esc_html( $weekday['abbr'] ); ?></abbr></div>
				<?php endforeach; ?>
			</div>
			<?php foreach ( $calendar_weeks as $week ) : ?>
				<?php
				$segments_by_column = array_fill( 0, 7, array() );
				foreach ( $week['segments'] as $segment ) {
					$segment_column = (int) $segment['start_column'];
					if ( isset( $segments_by_column[ $segment_column ] ) ) {
						$segments_by_column[ $segment_column ][] = $segment;
					}
				}
				?>
				<div class="aa-events-calendar-week" role="row" style="--aa-event-lanes: <?php echo esc_attr( max( 1, (int) $week['lane_count'] ) ); ?>;">
					<?php foreach ( $week['days'] as $day_column => $day ) : ?>
							<?php
							$day_classes = array( 'aa-events-calendar-day' );
							if ( $day < $month['month_start'] || $day > $month['month_end'] ) {
								$day_classes[] = 'is-outside-month';
							}
							if ( $day->format( 'Y-m-d' ) === $today->format( 'Y-m-d' ) ) {
								$day_classes[] = 'today-cell';
							}
							?>
							<div class="<?php echo esc_attr( implode( ' ', $day_classes ) ); ?>" role="gridcell" aria-label="<?php echo esc_attr( wp_date( 'F j, Y', $day->getTimestamp(), $tz ) ); ?>">
								<span class="day-number"><?php echo esc_html( wp_date( 'j', $day->getTimestamp(), $tz ) ); ?></span>
						<?php foreach ( $segments_by_column[ $day_column ] as $segment ) : ?>
							<?php
							$event_classes = array( 'aa-events-calendar-event' );
							if ( ! empty( $segment['continues_before'] ) ) {
								$event_classes[] = 'is-continuing-before';
							}
							if ( ! empty( $segment['continues_after'] ) ) {
								$event_classes[] = 'is-continuing-after';
							}
							/* translators: 1: event title, 2: complete event date and time range. */
							$event_label = sprintf( __( '%1$s: %2$s', 'aa-events' ), $segment['title'], aa_events_format_occurrence( $segment ) );
							?>
							<a class="<?php echo esc_attr( implode( ' ', $event_classes ) ); ?>" style="--aa-event-column: <?php echo esc_attr( (int) $segment['start_column'] ); ?>; --aa-event-span: <?php echo esc_attr( (int) $segment['span'] ); ?>; --aa-event-lane: <?php echo esc_attr( (int) $segment['lane'] ); ?>;" href="<?php echo esc_url( $segment['permalink'] ); ?>" aria-label="<?php echo esc_attr( $event_label ); ?>">
								<?php
								if ( ! empty( $segment['continues_before'] ) ) :
									?>
									<span class="aa-events-calendar-continuation" aria-hidden="true">&larr;</span><?php endif; ?>
								<span aria-hidden="true"><?php echo esc_html( $segment['title'] ); ?></span>
								<?php
								if ( ! empty( $segment['continues_after'] ) ) :
									?>
									<span class="aa-events-calendar-continuation" aria-hidden="true">&rarr;</span><?php endif; ?>
							</a>
						<?php endforeach; ?>
							</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<section class="aa-events-calendar-agenda" data-aa-events-view-panel="list" aria-labelledby="<?php echo esc_attr( $agenda_heading_id ); ?>">
		<?php /* translators: %s: localized month and year. */ ?>
		<h3 id="<?php echo esc_attr( $agenda_heading_id ); ?>"><?php echo esc_html( sprintf( __( 'Events for %s', 'aa-events' ), $month_label ) ); ?></h3>
		<?php if ( $calendar_occurrences ) : ?>
			<ol class="aa-events-calendar-agenda-list">
				<?php foreach ( $calendar_occurrences as $occurrence ) : ?>
					<li class="aa-events-agenda-item"><a href="<?php echo esc_url( $occurrence['permalink'] ); ?>"><span class="aa-events-agenda-title"><?php echo esc_html( $occurrence['title'] ); ?></span> <?php echo aa_events_occurrence_time_markup( $occurrence ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns escaped markup. ?></a></li>
				<?php endforeach; ?>
			</ol>
		<?php else : ?>
			<p class="aa-events-calendar-no-events"><?php esc_html_e( 'No events this month.', 'aa-events' ); ?></p>
		<?php endif; ?>
	</section>
</div>
