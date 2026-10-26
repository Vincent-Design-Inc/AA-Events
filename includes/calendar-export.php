<?php
/**
 * Individual occurrence calendar links and iCalendar downloads.
 *
 * @package AA_Events
 */

/**
 * Identify a schedule without depending on its position in the occurrence list.
 *
 * @param array $occurrence Normalized occurrence.
 * @return string
 */
function aa_events_calendar_export_key( array $occurrence ) {
	$parts = array();
	foreach ( array( 'start_date', 'start_time', 'end_date', 'end_time', 'all_day' ) as $field ) {
		$parts[] = (string) $occurrence[ $field ];
	}
	return hash( 'sha256', implode( '|', $parts ) );
}

/**
 * Whether an event can be shared with external calendar services.
 *
 * @param int $post_id Event ID.
 * @return bool
 */
function aa_events_calendar_is_public( $post_id ) {
	$post = get_post( $post_id );
	return $post && 'event' === $post->post_type && 'publish' === $post->post_status
		&& '' === $post->post_password && is_post_publicly_viewable( $post );
}

/**
 * Convert stored content to plain text without running content filters.
 *
 * @param string $text Stored text.
 * @return string
 */
function aa_events_calendar_text( $text ) {
	$text = preg_replace( '/<br\s*\/?>|<\/(?:p|div|li)>/i', "\n", $text );
	return trim( html_entity_decode( wp_strip_all_tags( strip_shortcodes( $text ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/**
 * Build the common data used by every calendar provider.
 *
 * Callers must supply an occurrence obtained from aa_events_get_occurrences().
 *
 * @param int   $post_id    Event ID.
 * @param array $occurrence Normalized occurrence.
 * @return array
 */
function aa_events_calendar_data( $post_id, array $occurrence ) {
	$post      = get_post( $post_id );
	$start     = aa_events_occurrence_start( $occurrence );
	$end       = aa_events_occurrence_end( $occurrence );
	$all_day   = ! empty( $occurrence['all_day'] );
	$date_only = ! $all_day && '' === $occurrence['start_time'] && '' === $occurrence['end_time'];
	$notice    = '';

	if ( $all_day || $date_only ) {
		$all_day = true;
		// iCalendar and provider links use an exclusive all-day end date.
		$end = $end->modify( '+1 day' );
		if ( $date_only ) {
			$notice = __( 'No time is specified. This will be saved as an all-day calendar entry.', 'aa-events' );
		}
	} else {
		$notices = array();
		if ( '' === $occurrence['start_time'] ) {
			$notices[] = __( 'No start time is specified. The calendar entry starts at midnight in the site timezone.', 'aa-events' );
		}
		if ( '' === $occurrence['end_time'] ) {
			if ( $occurrence['end_date'] !== $occurrence['start_date'] ) {
				$end       = $end->setTime( 23, 59, 59 );
				$notices[] = __( 'No end time is specified. The calendar entry uses an estimated end time of 11:59 PM on the final date in the site timezone.', 'aa-events' );
			} else {
				// An elapsed hour, not a wall-clock hour, including across DST.
				$end       = $start->setTimestamp( $start->getTimestamp() + 3600 );
				$notices[] = __( 'No end time is specified. The calendar entry uses an estimated 1-hour duration.', 'aa-events' );
			}
		}
		$notice = implode( ' ', $notices );
	}

	$url          = get_permalink( $post_id );
	$description  = html_entity_decode( wp_trim_words( aa_events_calendar_text( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 55 ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$description  = trim( $description . "\n\n" . $url . ( $notice ? "\n\n" . $notice : '' ) );
	$description .= "\n\n" . __( 'Saved copy only. Check the event page for changes or cancellations.', 'aa-events' );
	$location     = aa_events_get_active_field_value(
		aa_events_occurrence_boolean( aa_events_get_active_field_value( 'event_location_type', $post_id ) ) ? 'event_url' : 'event_address',
		$post_id
	);

	return array(
		'post_id'     => (int) $post_id,
		'key'         => aa_events_calendar_export_key( $occurrence ),
		'title'       => aa_events_calendar_text( get_the_title( $post_id ) ),
		'description' => $description,
		'location'    => aa_events_calendar_text( (string) $location ),
		'url'         => $url,
		'start'       => $start,
		'end'         => $end,
		'all_day'     => $all_day,
		'notice'      => $notice,
	);
}

/**
 * Build provider URLs from the common data.
 *
 * @param array $data Calendar data.
 * @return array
 */
function aa_events_calendar_links( array $data ) {
	$utc          = new DateTimeZone( 'UTC' );
	$start        = $data['start']->setTimezone( $utc );
	$end          = $data['end']->setTimezone( $utc );
	$outlook_args = array_map(
        'rawurlencode',
        array(
			'path'     => '/calendar/action/compose',
			'rru'      => 'addevent',
			'subject'  => $data['title'],
			'startdt'  => $data['all_day'] ? $data['start']->format( 'Y-m-d' ) : $start->format( 'Y-m-d\TH:i:s\Z' ),
			'enddt'    => $data['all_day'] ? $data['end']->format( 'Y-m-d' ) : $end->format( 'Y-m-d\TH:i:s\Z' ),
			'allday'   => $data['all_day'] ? 'true' : 'false',
			'body'     => $data['description'],
			'location' => $data['location'],
        )
    );
	return array(
		'google'      => add_query_arg(
			array_map(
                'rawurlencode',
                array(
					'action'   => 'TEMPLATE',
					'text'     => $data['title'],
					'dates'    => $data['all_day']
						? $data['start']->format( 'Ymd' ) . '/' . $data['end']->format( 'Ymd' )
						: $start->format( 'Ymd\THis\Z' ) . '/' . $end->format( 'Ymd\THis\Z' ),
					'details'  => $data['description'],
					'location' => $data['location'],
                )
            ),
			'https://calendar.google.com/calendar/render'
		),
		'outlook'     => add_query_arg( $outlook_args, 'https://outlook.office.com/calendar/0/deeplink/compose' ),
		'outlook_com' => add_query_arg( $outlook_args, 'https://outlook.live.com/calendar/0/deeplink/compose' ),
		'ics'         => add_query_arg(
			array(
				'action'     => 'aa_events_download_calendar',
				'event'      => $data['post_id'],
				'occurrence' => $data['key'],
			),
			admin_url( 'admin-post.php' )
		),
	);
}

/**
 * Render a single-event date with calendar choices, including paginated dates.
 *
 * @param array $occurrence Normalized occurrence.
 * @return string Escaped HTML safe to print directly.
 */
function aa_events_single_occurrence_markup( array $occurrence ) {
	$markup = aa_events_occurrence_time_markup( $occurrence );
	if ( aa_events_is_recurring_occurrence( $occurrence ) ) {
		$markup .= ' <span class="aa-event-recurring-badge">' . esc_html( __( 'Recurring', 'aa-events' ) ) . '</span>';
	}
	if ( ! aa_events_calendar_is_public( $occurrence['post_id'] ) ) {
		return $markup;
	}
	$data    = aa_events_calendar_data( $occurrence['post_id'], $occurrence );
	$links   = aa_events_calendar_links( $data );
	$labels  = array(
		'google'      => __( 'Google Calendar', 'aa-events' ),
		'outlook_com' => __( 'Outlook.com', 'aa-events' ),
		'outlook'     => __( 'Outlook / Microsoft 365', 'aa-events' ),
		'ics'         => __( 'Apple Calendar / other apps (.ics)', 'aa-events' ),
	);
	$markup .= '<details class="aa-event-add-to-calendar"><summary>'
		. esc_html( __( 'Add to calendar', 'aa-events' ) )
		. '<span class="screen-reader-text">: ' . esc_html( aa_events_format_occurrence( $occurrence ) ) . '</span></summary>';
	if ( $data['notice'] ) {
		$markup .= '<p class="aa-event-calendar-notice">' . esc_html( $data['notice'] ) . '</p>';
	}
	$markup .= '<ul>';
	foreach ( $labels as $provider => $label ) {
		// These URLs have trusted bases and encoded query values; esc_url() strips encoded line breaks.
		$markup .= '<li><a href="' . esc_attr( $links[ $provider ] ) . '">' . esc_html( $label ) . '</a></li>';
	}
	$markup .= '</ul><p class="aa-event-calendar-notice">'
		. esc_html( __( 'Saved copy only. Check the event page for changes or cancellations.', 'aa-events' ) )
		. '</p></details>';
	return $markup;
}

/**
 * Escape an iCalendar TEXT value, including newlines and control characters.
 *
 * @param string $text Text.
 * @return string
 */
function aa_events_calendar_ics_text( $text ) {
	$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
	return str_replace(
		array( '\\', "\r\n", "\r", "\n", ';', ',' ),
		array( '\\\\', '\\n', '\\n', '\\n', '\\;', '\\,' ),
		$text
	);
}

/**
 * Fold an iCalendar content line at 75 octets without splitting UTF-8.
 *
 * @param string $line Logical line.
 * @return string
 */
function aa_events_calendar_ics_fold( $line ) {
	$folded    = '';
	$remaining = strlen( $line );
	while ( $remaining > 75 ) {
		$length = 75;
		while ( ( ord( $line[ $length ] ) & 0xC0 ) === 0x80 ) {
			--$length;
		}
		$folded   .= substr( $line, 0, $length ) . "\r\n";
		$line      = ' ' . substr( $line, $length );
		$remaining = strlen( $line );
	}
	return $folded . $line;
}

/**
 * Generate one RFC 5545 event, with UTC times or date-only all-day values.
 *
 * @param array $data Calendar data.
 * @return string
 */
function aa_events_calendar_ics( array $data ) {
	$utc   = new DateTimeZone( 'UTC' );
	$uid   = hash( 'sha256', home_url( '/' ) . '|' . $data['post_id'] . '|' . $data['key'] ) . '@aa-events';
	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//AA Events//Calendar Export//EN',
		'CALSCALE:GREGORIAN',
		'BEGIN:VEVENT',
		'UID:' . $uid,
		'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
		$data['all_day'] ? 'DTSTART;VALUE=DATE:' . $data['start']->format( 'Ymd' ) : 'DTSTART:' . $data['start']->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
	);
	// RFC 5545 permits an instantaneous timed event by omitting DTEND.
	if ( $data['end'] > $data['start'] ) {
		$lines[] = $data['all_day'] ? 'DTEND;VALUE=DATE:' . $data['end']->format( 'Ymd' ) : 'DTEND:' . $data['end']->setTimezone( $utc )->format( 'Ymd\THis\Z' );
	}
	$lines[] = 'SUMMARY:' . aa_events_calendar_ics_text( $data['title'] );
	$lines[] = 'DESCRIPTION:' . aa_events_calendar_ics_text( $data['description'] );
	$lines[] = 'LOCATION:' . aa_events_calendar_ics_text( $data['location'] );
	$lines[] = 'URL:' . esc_url_raw( $data['url'] );
	$lines[] = 'END:VEVENT';
	$lines[] = 'END:VCALENDAR';
	return implode( "\r\n", array_map( 'aa_events_calendar_ics_fold', $lines ) ) . "\r\n";
}

/**
 * Validate untrusted selectors and resolve against the current public schedule.
 *
 * @param mixed $post_id Event ID from the request.
 * @param mixed $key     Occurrence key from the request.
 * @return array|WP_Error
 */
function aa_events_resolve_calendar_download( $post_id, $key ) {
	if ( ! is_string( $post_id ) || ! preg_match( '/^[1-9][0-9]*$/D', $post_id ) || (string) (int) $post_id !== $post_id
		|| ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{64}$/D', $key ) ) {
		return new WP_Error( 'invalid_calendar_request', __( 'Invalid calendar download link.', 'aa-events' ), array( 'status' => 400 ) );
	}
	if ( ! aa_events_calendar_is_public( (int) $post_id ) ) {
		return new WP_Error( 'calendar_event_not_found', __( 'This event is not available for calendar download.', 'aa-events' ), array( 'status' => 404 ) );
	}
	foreach ( aa_events_get_occurrences( (int) $post_id ) as $occurrence ) {
		if ( hash_equals( aa_events_calendar_export_key( $occurrence ), $key ) ) {
			return aa_events_calendar_data( (int) $post_id, $occurrence );
		}
	}
	return new WP_Error( 'calendar_occurrence_not_found', __( 'This date is no longer available. Please return to the event page and choose a current date.', 'aa-events' ), array( 'status' => 404 ) );
}

/**
 * Serve a public, read-only calendar download without requiring a login or nonce.
 *
 * @return void
 */
function aa_events_download_calendar() {
	// No mutation or private data: validate both selectors against published data.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Resolver strictly allowlists the complete selector strings.
	$data = aa_events_resolve_calendar_download( wp_unslash( $_GET['event'] ?? null ), wp_unslash( $_GET['occurrence'] ?? null ) );
	if ( is_wp_error( $data ) ) {
		wp_die( esc_html( $data->get_error_message() ), esc_html__( 'Calendar download unavailable', 'aa-events' ), array( 'response' => (int) $data->get_error_data()['status'] ) );
	}
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="event-' . $data['post_id'] . '.ics"' );
	header( 'X-Content-Type-Options: nosniff' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- RFC 5545 text, not HTML.
	echo aa_events_calendar_ics( $data );
	exit;
}

add_action( 'admin_post_aa_events_download_calendar', 'aa_events_download_calendar' );
add_action( 'admin_post_nopriv_aa_events_download_calendar', 'aa_events_download_calendar' );
