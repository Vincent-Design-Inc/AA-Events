# Multi-Day Event Occurrences Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Support mixed recurring and multi-day event occurrences, render multi-day bars across the traditional month grid, and provide a chronological mobile agenda.

**Architecture:** Keep `event_dates` as the persisted ACF repeater and normalize all supported field formats through a focused occurrence API. Put date normalization/formatting in `includes/occurrences.php` and pure calendar segmentation/lane logic in `includes/calendar-layout.php`; templates consume those APIs without interpreting raw ACF rows. Desktop rendering uses week containers with a seven-column CSS grid, while mobile renders the same normalized occurrences as an agenda.

**Tech Stack:** PHP 7.4+, WordPress APIs, Advanced Custom Fields, semantic HTML, CSS Grid, dependency-free PHP test runner

---

## File Structure

- Create `includes/occurrences.php`: normalize ACF/legacy values, validate ranges, format occurrence labels, and migrate legacy values on save.
- Create `includes/calendar-layout.php`: calculate month bounds, split occurrences into weekly segments, and assign overlap lanes.
- Create `tests/bootstrap.php`: minimal WordPress/ACF stubs and assertion helpers for dependency-free unit tests.
- Create `tests/test-occurrences.php`: normalization, formatting, validation, and migration tests.
- Create `tests/test-calendar-layout.php`: month intersection, week splitting, and lane-allocation tests.
- Create `tests/run.php`: execute all test files and return a nonzero status on failure.
- Modify `includes/class-aa-events.php`: load the two focused helper modules before post type/template code.
- Modify `includes/acf-fields.php`: extend and relabel the repeater, register validation, and make the legacy field less prominent.
- Modify `assets/js/aa-events-admin.js`: clear/disable time inputs while an occurrence is marked all-day.
- Modify `includes/post-types.php`: replace raw/mismatched repeater reads with normalized occurrences and update admin date sorting.
- Modify `templates/single-event.php`: render formatted occurrence ranges.
- Modify `templates/archive-event.php`: render formatted occurrence ranges.
- Rewrite `templates/calendar.php`: collect intersecting occurrences and render desktop week grids plus mobile agenda markup.
- Modify `assets/css/aa-events.css`: style the week grid, spanning bars, continuations, focus states, and mobile agenda.
- Modify `templates/aa-events.css`: keep the documented theme-copy stylesheet synchronized with the bundled stylesheet.
- Modify `README.md`: document occurrence fields, compatibility, responsive behavior, and theme-override implications.

The current directory is not a Git worktree. Commit steps below are required once this plugin is placed in a repository; until then, record each checkpoint with test output and skip only the `git add`/`git commit` commands.

### Task 1: Add a Dependency-Free PHP Test Harness

**Files:**
- Create: `tests/bootstrap.php`
- Create: `tests/run.php`

- [ ] **Step 1: Create WordPress and ACF test doubles**

Create `tests/bootstrap.php` with mutable field storage and strict assertion helpers:

```php
<?php
define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['aa_events_test_fields'] = array();
$GLOBALS['aa_events_test_updates'] = array();
$GLOBALS['aa_events_test_failures'] = 0;

function get_field( $name, $post_id = false ) {
    return $GLOBALS['aa_events_test_fields'][ (int) $post_id ][ $name ] ?? null;
}

function update_field( $name, $value, $post_id = false ) {
    $GLOBALS['aa_events_test_fields'][ (int) $post_id ][ $name ] = $value;
    $GLOBALS['aa_events_test_updates'][] = array( $name, $value, (int) $post_id );
    return true;
}

function wp_timezone() {
    return new DateTimeZone( 'America/Winnipeg' );
}

function wp_date( $format, $timestamp = null, $timezone = null ) {
    $date = new DateTimeImmutable( '@' . ( null === $timestamp ? time() : $timestamp ) );
    return $date->setTimezone( $timezone ?: wp_timezone() )->format( $format );
}

function __( $text ) { return $text; }
function _x( $text ) { return $text; }

function aa_events_test_reset() {
    $GLOBALS['aa_events_test_fields'] = array();
    $GLOBALS['aa_events_test_updates'] = array();
}

function aa_events_assert_same( $expected, $actual, $message ) {
    if ( $expected !== $actual ) {
        ++$GLOBALS['aa_events_test_failures'];
        fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
    }
}

function aa_events_assert_true( $actual, $message ) {
    aa_events_assert_same( true, (bool) $actual, $message );
}
```

- [ ] **Step 2: Create the test entry point**

Create `tests/run.php`:

```php
<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/occurrences.php';
require dirname( __DIR__ ) . '/includes/calendar-layout.php';
require __DIR__ . '/test-occurrences.php';
require __DIR__ . '/test-calendar-layout.php';

if ( $GLOBALS['aa_events_test_failures'] > 0 ) {
    fwrite( STDERR, $GLOBALS['aa_events_test_failures'] . " test(s) failed.\n" );
    exit( 1 );
}

fwrite( STDOUT, "All AA Events tests passed.\n" );
```

- [ ] **Step 3: Run the empty harness**

Run: `php tests/run.php`

Expected: FAIL because `includes/occurrences.php` does not exist. This confirms the test command detects missing production code.

- [ ] **Step 4: Commit the harness when Git is available**

```bash
git add tests/bootstrap.php tests/run.php
git commit -m "test: add lightweight event helper harness"
```

### Task 2: Normalize and Format Occurrences

**Files:**
- Create: `tests/test-occurrences.php`
- Create: `includes/occurrences.php`
- Modify: `includes/class-aa-events.php`

- [ ] **Step 1: Write failing normalization tests**

Create `tests/test-occurrences.php` with these cases:

```php
<?php
aa_events_test_reset();
$GLOBALS['aa_events_test_fields'][10]['event_dates'] = array(
    array(
        'date'       => '2026-07-10',
        'start_time' => '18:00:00',
        'end_date'   => '2026-07-12',
        'end_time'   => '14:00:00',
        'all_day'    => false,
    ),
    array(
        'date'       => '2026-07-20',
        'start_time' => '',
        'end_date'   => '',
        'end_time'   => '',
        'all_day'    => true,
    ),
);
$occurrences = aa_events_get_occurrences( 10 );
aa_events_assert_same( 2, count( $occurrences ), 'Repeater rows normalize.' );
aa_events_assert_same( '2026-07-12', $occurrences[0]['end_date'], 'Explicit end date is retained.' );
aa_events_assert_same( '2026-07-20', $occurrences[1]['end_date'], 'Missing end date defaults to start date.' );
aa_events_assert_true( $occurrences[1]['all_day'], 'All-day flag normalizes to boolean.' );

aa_events_test_reset();
$GLOBALS['aa_events_test_fields'][11]['event_datetime'] = '2026-08-03 09:30:00';
$legacy = aa_events_get_occurrences( 11 );
aa_events_assert_same( '2026-08-03', $legacy[0]['start_date'], 'Legacy datetime supplies start date.' );
aa_events_assert_same( '09:30:00', $legacy[0]['start_time'], 'Legacy datetime supplies start time.' );
aa_events_assert_same( 'event_datetime', $legacy[0]['source'], 'Legacy source is identified.' );

aa_events_test_reset();
$GLOBALS['aa_events_test_fields'][12]['date'] = '2026-09-01';
$GLOBALS['aa_events_test_fields'][12]['start_time'] = '11:00:00';
$separate = aa_events_get_occurrences( 12 );
aa_events_assert_same( 'legacy_separate', $separate[0]['source'], 'Separate legacy fields normalize.' );

aa_events_test_reset();
$GLOBALS['aa_events_test_fields'][13]['event_dates'] = array(
    array( 'date' => '', 'end_date' => '2026-07-02' ),
    array( 'date' => 'not-a-date' ),
);
aa_events_assert_same( array(), aa_events_get_occurrences( 13 ), 'Malformed rows are skipped.' );
```

- [ ] **Step 2: Add failing validation and formatting tests**

Append to `tests/test-occurrences.php`:

```php
aa_events_assert_same(
    'End date cannot be earlier than start date.',
    aa_events_validate_occurrence_row(
        array( 'date' => '2026-07-12', 'end_date' => '2026-07-10' )
    ),
    'Backwards date range is rejected.'
);
aa_events_assert_same(
    'End time cannot be earlier than start time on the same date.',
    aa_events_validate_occurrence_row(
        array(
            'date'       => '2026-07-10',
            'start_time' => '18:00:00',
            'end_time'   => '14:00:00',
        )
    ),
    'Backwards same-day time range is rejected.'
);
aa_events_assert_same(
    '',
    aa_events_validate_occurrence_row(
        array( 'date' => '2026-07-10', 'end_date' => '2026-07-12', 'all_day' => true )
    ),
    'Valid all-day range passes.'
);

$timed = aa_events_normalize_occurrence_row(
    array(
        'date'       => '2026-07-10',
        'start_time' => '18:00:00',
        'end_date'   => '2026-07-12',
        'end_time'   => '14:00:00',
    ),
    'event_dates'
);
aa_events_assert_same(
    'July 10, 2026, 6:00 PM–July 12, 2026, 2:00 PM',
    aa_events_format_occurrence( $timed ),
    'Timed multi-day range formats both endpoints.'
);

$all_day = aa_events_normalize_occurrence_row(
    array( 'date' => '2026-07-10', 'end_date' => '2026-07-12', 'all_day' => true ),
    'event_dates'
);
aa_events_assert_same(
    'July 10–12, 2026 — All day',
    aa_events_format_occurrence( $all_day ),
    'All-day range uses compact localized dates.'
);
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php tests/run.php`

Expected: FAIL with undefined occurrence function errors.

- [ ] **Step 4: Implement the occurrence API**

Create `includes/occurrences.php`. Implement these exact public functions:

```php
function aa_events_normalize_occurrence_row( array $row, $source = 'event_dates', $post_id = 0 )
function aa_events_get_occurrences( $post_id )
function aa_events_validate_occurrence_row( array $row )
function aa_events_format_occurrence( array $occurrence )
function aa_events_occurrence_start( array $occurrence )
function aa_events_occurrence_end( array $occurrence )
function aa_events_migrate_legacy_occurrence( $post_id )
```

`aa_events_normalize_occurrence_row()` must return `null` for invalid start dates and otherwise return this stable shape:

```php
array(
    'post_id'    => (int) $post_id,
    'start_date' => 'Y-m-d',
    'start_time' => 'H:i:s or empty string',
    'end_date'   => 'Y-m-d',
    'end_time'   => 'H:i:s or empty string',
    'all_day'    => (bool) $all_day,
    'source'     => 'event_dates|event_datetime|legacy_separate',
)
```

Use strict `DateTimeImmutable::createFromFormat()` parsing in `wp_timezone()`, check `DateTimeImmutable::getLastErrors()` when it returns an array, ignore times when `all_day` is true, and sort normalized occurrences by `aa_events_occurrence_start()`.

`aa_events_format_occurrence()` must use `wp_date()` for every visible date/time and cover the five formats specified in the design. Use an en dash between endpoints and an em dash before “All day.”

- [ ] **Step 5: Load occurrence helpers before consumers**

In `AA_Events::includes()`, add this as the first include:

```php
include_once AA_EVENTS_PLUGIN_DIR . 'includes/occurrences.php';
```

- [ ] **Step 6: Run occurrence tests**

Run: `php tests/run.php`

Expected: occurrence assertions pass; execution then fails because calendar-layout files do not yet exist.

- [ ] **Step 7: Commit the normalized occurrence API when Git is available**

```bash
git add includes/occurrences.php includes/class-aa-events.php tests/test-occurrences.php
git commit -m "feat: normalize event occurrences"
```

### Task 3: Extend the ACF Editor, Validate Rows, and Migrate Legacy Data

**Files:**
- Modify: `includes/acf-fields.php`
- Modify: `includes/occurrences.php`
- Modify: `assets/js/aa-events-admin.js`
- Modify: `tests/test-occurrences.php`

- [ ] **Step 1: Write failing migration tests**

Append to `tests/test-occurrences.php`:

```php
aa_events_test_reset();
$GLOBALS['aa_events_test_fields'][20]['event_datetime'] = '2026-10-05 13:15:00';
aa_events_assert_true( aa_events_migrate_legacy_occurrence( 20 ), 'Legacy value migrates.' );
aa_events_assert_same(
    array(
        array(
            'date'       => '2026-10-05',
            'start_time' => '13:15:00',
            'end_date'   => '',
            'end_time'   => '',
            'all_day'    => 0,
        ),
    ),
    $GLOBALS['aa_events_test_fields'][20]['event_dates'],
    'Migration writes one compatible repeater row.'
);
aa_events_assert_same( false, aa_events_migrate_legacy_occurrence( 20 ), 'Second migration is idempotent.' );
aa_events_assert_same( 1, count( $GLOBALS['aa_events_test_updates'] ), 'Second migration writes nothing.' );
```

- [ ] **Step 2: Run migration test to verify it fails**

Run: `php tests/run.php`

Expected: FAIL because migration is not yet implemented or does not write the required row shape.

- [ ] **Step 3: Implement idempotent migration and save hook**

Complete `aa_events_migrate_legacy_occurrence()` so it returns `false` when `event_dates` already has rows or no parseable legacy value exists. Otherwise, call `update_field( 'event_dates', array( $row ), $post_id )` and return its boolean result.

Register a save callback that ignores autosaves/revisions and non-event posts:

```php
function aa_events_migrate_legacy_on_save( $post_id ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }
    if ( 'event' !== get_post_type( $post_id ) ) {
        return;
    }
    aa_events_migrate_legacy_occurrence( $post_id );
}
add_action( 'acf/save_post', 'aa_events_migrate_legacy_on_save', 20 );
```

- [ ] **Step 4: Replace the built-in repeater definition**

In `includes/acf-fields.php`, retain field key `field_61b0c7f0a3e8f` and name `event_dates`, but change its label/button/instructions to:

```php
'label'        => 'Occurrences',
'instructions' => 'Each row is one occurrence. Add an end date for a continuous multi-day event; add more rows when the event recurs.',
'layout'       => 'row',
'button_label' => 'Add Occurrence',
```

Keep `date` and `start_time`, then add subfields with unique stable keys:

```php
array(
    'key'           => 'field_aa_events_end_date',
    'label'         => 'End Date',
    'name'          => 'end_date',
    'type'          => 'date_picker',
    'required'      => 0,
    'display_format'=> 'F j, Y',
    'return_format' => 'Y-m-d',
    'first_day'     => 1,
),
array(
    'key'           => 'field_aa_events_end_time',
    'label'         => 'End Time',
    'name'          => 'end_time',
    'type'          => 'time_picker',
    'required'      => 0,
    'display_format'=> 'g:i a',
    'return_format' => 'H:i:s',
),
array(
    'key'           => 'field_aa_events_all_day',
    'label'         => 'All Day',
    'name'          => 'all_day',
    'type'          => 'true_false',
    'ui'            => 1,
    'default_value' => 0,
),
```

Change the legacy `event_datetime` instructions to “Legacy compatibility field. Use Occurrences for new events.”

- [ ] **Step 5: Register row validation through ACF**

Add `acf/validate_value/key=field_61b0c7f0a3e8f` validation that loops submitted rows, maps ACF subfield keys to semantic names, calls `aa_events_validate_occurrence_row()`, and returns a translated row-numbered error such as `Occurrence 2: End date cannot be earlier than start date.` Return the incoming `$valid` unchanged when it is already an error.

- [ ] **Step 6: Implement all-day editor behavior**

In `assets/js/aa-events-admin.js`, add a delegated `change` handler scoped to `.acf-field[data-name="event_dates"]`. For each repeater row, toggle an `aa-events-is-all-day` class, disable the `start_time` and `end_time` inputs while checked, and clear those inputs when the checkbox changes from unchecked to checked. Run this synchronization on ACF `ready` and `append` actions.

- [ ] **Step 7: Run tests and PHP syntax checks**

Run:

```bash
php tests/run.php
php -l includes/acf-fields.php
php -l includes/occurrences.php
```

Expected: all occurrence tests pass and both files report `No syntax errors detected`.

- [ ] **Step 8: Commit editor and migration behavior when Git is available**

```bash
git add includes/acf-fields.php includes/occurrences.php assets/js/aa-events-admin.js tests/test-occurrences.php
git commit -m "feat: add occurrence ranges to event editor"
```

### Task 4: Build and Test Calendar Segmentation

**Files:**
- Create: `tests/test-calendar-layout.php`
- Create: `includes/calendar-layout.php`
- Modify: `includes/class-aa-events.php`

- [ ] **Step 1: Write failing week-segment tests**

Create `tests/test-calendar-layout.php`:

```php
<?php
$month = aa_events_calendar_month( 2026, 7, 1 );
aa_events_assert_same( '2026-06-29', $month['grid_start']->format( 'Y-m-d' ), 'Monday-start grid begins before month.' );
aa_events_assert_same( '2026-08-02', $month['grid_end']->format( 'Y-m-d' ), 'Grid ends after complete final week.' );

$occurrence = aa_events_normalize_occurrence_row(
    array(
        'date'       => '2026-07-10',
        'start_time' => '18:00:00',
        'end_date'   => '2026-07-15',
        'end_time'   => '14:00:00',
    ),
    'event_dates',
    30
);
$segments = aa_events_calendar_segments( $occurrence, $month );
aa_events_assert_same( 2, count( $segments ), 'Occurrence splits at week boundary.' );
aa_events_assert_same( 4, $segments[0]['start_column'], 'Friday is column four in Monday-start grid.' );
aa_events_assert_same( 3, $segments[0]['span'], 'First segment covers Friday through Sunday.' );
aa_events_assert_true( $segments[0]['continues_after'], 'First segment continues next week.' );
aa_events_assert_same( 0, $segments[1]['start_column'], 'Second segment starts Monday.' );
aa_events_assert_same( 3, $segments[1]['span'], 'Second segment covers Monday through Wednesday.' );
aa_events_assert_true( $segments[1]['continues_before'], 'Second segment continued from prior week.' );
```

- [ ] **Step 2: Add failing clipping and lane tests**

Append:

```php
$cross_month = aa_events_normalize_occurrence_row(
    array( 'date' => '2026-06-25', 'end_date' => '2026-07-03', 'all_day' => true ),
    'event_dates',
    31
);
$clipped = aa_events_calendar_segments( $cross_month, $month );
aa_events_assert_true( $clipped[0]['continues_before'], 'Month-clipped segment shows prior continuation.' );

$week_segments = array(
    array( 'start_column' => 0, 'span' => 3, 'post_id' => 1 ),
    array( 'start_column' => 2, 'span' => 2, 'post_id' => 2 ),
    array( 'start_column' => 4, 'span' => 2, 'post_id' => 3 ),
);
$lanes = aa_events_assign_segment_lanes( $week_segments );
aa_events_assert_same( 0, $lanes[0]['lane'], 'First segment uses first lane.' );
aa_events_assert_same( 1, $lanes[1]['lane'], 'Overlapping segment uses second lane.' );
aa_events_assert_same( 0, $lanes[2]['lane'], 'Non-overlapping segment reuses first lane.' );
```

- [ ] **Step 3: Run tests to verify failure**

Run: `php tests/run.php`

Expected: FAIL with undefined calendar-layout functions.

- [ ] **Step 4: Implement pure layout functions**

Create `includes/calendar-layout.php` with these public functions:

```php
function aa_events_calendar_month( $year, $month, $start_of_week )
function aa_events_occurrence_intersects_range( array $occurrence, DateTimeImmutable $start, DateTimeImmutable $end )
function aa_events_calendar_segments( array $occurrence, array $month )
function aa_events_assign_segment_lanes( array $segments )
function aa_events_group_calendar_weeks( array $occurrences, array $month )
```

`aa_events_calendar_month()` must return `month_start`, `month_end`, `grid_start`, `grid_end`, `start_of_week`, and an ordered `weeks` array. Each week contains `start`, `end`, and seven `days`.

`aa_events_calendar_segments()` must clip to `month_start`/`month_end`, split at each configured week boundary, use zero-based columns, and set `continues_before`/`continues_after` relative to the true occurrence interval.

`aa_events_assign_segment_lanes()` must sort by start column, then longest span, then post ID. Assign the first lane whose occupied end column is strictly before the segment start column.

`aa_events_group_calendar_weeks()` must return each week with lane-assigned segments and `lane_count`, preserving complete occurrence data on every segment for template labels.

- [ ] **Step 5: Load the layout helper**

In `AA_Events::includes()`, immediately after the occurrence include, add:

```php
include_once AA_EVENTS_PLUGIN_DIR . 'includes/calendar-layout.php';
```

- [ ] **Step 6: Run layout tests**

Run: `php tests/run.php`

Expected: `All AA Events tests passed.`

- [ ] **Step 7: Commit layout logic when Git is available**

```bash
git add includes/calendar-layout.php includes/class-aa-events.php tests/test-calendar-layout.php
git commit -m "feat: calculate multi-day calendar segments"
```

### Task 5: Move All Existing Consumers to the Occurrence API

**Files:**
- Modify: `includes/post-types.php`
- Modify: `templates/single-event.php`
- Modify: `templates/archive-event.php`

- [ ] **Step 1: Replace the compatibility helper**

Keep `events_get_all_event_dates()` as a deprecated compatibility wrapper so theme overrides do not fatally break:

```php
function events_get_all_event_dates( $post_id ) {
    $dates = array();
    foreach ( aa_events_get_occurrences( $post_id ) as $occurrence ) {
        $dates[] = array(
            'date'       => $occurrence['start_date'],
            'time'       => $occurrence['start_time'],
            'end_date'   => $occurrence['end_date'],
            'end_time'   => $occurrence['end_time'],
            'all_day'    => $occurrence['all_day'],
        );
    }
    return $dates;
}
```

Remove direct reads of the nonexistent/mismatched `multiple_dates` field from admin display code.

- [ ] **Step 2: Update the admin column display**

Call `aa_events_get_occurrences( $post_id )`, display `aa_events_format_occurrence( $occurrences[0] )`, and retain the existing `+N` badge with escaped count. If no normalized occurrence exists, render an em dash.

- [ ] **Step 3: Correct admin SQL sorting keys**

Change the first repeater date join from `multiple_dates_0_date` to `event_dates_0_date`. Keep the `COALESCE` fallback order `event_dates_0_date`, `event_datetime`, `date`, post date. Add `$wpdb->posts.ID` as a final deterministic ordering key.

- [ ] **Step 4: Update archive and single templates**

In both templates, replace local timestamp construction with:

```php
$occurrences = aa_events_get_occurrences( get_the_ID() );
foreach ( $occurrences as $index => $occurrence ) {
    $start = aa_events_occurrence_start( $occurrence );
    printf(
        '<time datetime="%1$s">%2$s</time>%3$s',
        esc_attr( $start->format( DATE_W3C ) ),
        esc_html( aa_events_format_occurrence( $occurrence ) ),
        $index < count( $occurrences ) - 1 ? '<br />' : ''
    );
}
```

Change the visible label from “Date:” to the plural-aware `Dates:` only when more than one occurrence exists.

- [ ] **Step 5: Run tests and syntax checks**

Run:

```bash
php tests/run.php
php -l includes/post-types.php
php -l templates/single-event.php
php -l templates/archive-event.php
```

Expected: tests pass and every PHP file reports no syntax errors.

- [ ] **Step 6: Commit consumer updates when Git is available**

```bash
git add includes/post-types.php templates/single-event.php templates/archive-event.php
git commit -m "refactor: render normalized event occurrences"
```

### Task 6: Render the Desktop Month Grid and Mobile Agenda

**Files:**
- Modify: `templates/calendar.php`

- [ ] **Step 1: Replace per-day event collection**

Retain validated navigation parameters and localized weekday construction. Replace `$events_by_day` with `$calendar_occurrences`. For each published event, append every normalized occurrence that intersects `$month['month_start']` through `$month['month_end']`, adding `title`, `permalink`, and `post_id` to the normalized array.

Sort the mobile list with start timestamp, then title:

```php
usort(
    $calendar_occurrences,
    function ( $a, $b ) {
        $comparison = aa_events_occurrence_start( $a ) <=> aa_events_occurrence_start( $b );
        return 0 !== $comparison ? $comparison : strcasecmp( $a['title'], $b['title'] );
    }
);
```

Pass the occurrences into `aa_events_group_calendar_weeks()` for desktop rendering.

- [ ] **Step 2: Render semantic desktop week grids**

Replace the event-bearing table body with `.aa-events-calendar-grid`, while retaining a visually aligned weekday header. For each week render:

```php
<div class="aa-events-calendar-week" role="row" style="--aa-event-lanes: <?php echo esc_attr( max( 1, $week['lane_count'] ) ); ?>">
    <div class="aa-events-calendar-days">
        <?php foreach ( $week['days'] as $day ) : ?>
            <div role="gridcell" class="aa-events-calendar-day ..." aria-label="...">
                <span class="day-number"><?php echo esc_html( $day->format( 'j' ) ); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="aa-events-calendar-bars">
        <!-- segment links -->
    </div>
</div>
```

Render every segment link with CSS variables `--aa-event-column`, `--aa-event-span`, and `--aa-event-lane`. Add `is-continuing-before` and `is-continuing-after` classes. Repeat the visible title on every segment and build `aria-label` from title plus the complete `aa_events_format_occurrence()` result. Mark visible arrows `aria-hidden="true"`.

Days outside the selected month remain visible as muted grid cells so week geometry stays intact. Keep the current-day class based on site timezone.

- [ ] **Step 3: Render mobile agenda markup**

After the desktop grid, render `.aa-events-calendar-agenda` with a heading and `<ol>`. Each intersecting occurrence appears once:

```php
<li class="aa-events-agenda-item">
    <a href="<?php echo esc_url( $occurrence['permalink'] ); ?>">
        <span class="aa-events-agenda-title"><?php echo esc_html( $occurrence['title'] ); ?></span>
        <time datetime="<?php echo esc_attr( aa_events_occurrence_start( $occurrence )->format( DATE_W3C ) ); ?>">
            <?php echo esc_html( aa_events_format_occurrence( $occurrence ) ); ?>
        </time>
    </a>
</li>
```

Render a localized “No events this month.” message instead of an empty list.

- [ ] **Step 4: Remove dead output-buffer code**

Delete `$originalEchoDayCell`, `$calendarRows`, `$calendarOutput`, and the unused `preg_replace()` block. Confirm no output buffering remains in the template.

- [ ] **Step 5: Run syntax and helper tests**

Run:

```bash
php tests/run.php
php -l templates/calendar.php
```

Expected: tests pass and calendar template has no syntax errors.

- [ ] **Step 6: Commit calendar markup when Git is available**

```bash
git add templates/calendar.php
git commit -m "feat: render spanning calendar event bars"
```

### Task 7: Style Spanning Bars and the Mobile Agenda

**Files:**
- Modify: `assets/css/aa-events.css`
- Modify: `templates/aa-events.css`

- [ ] **Step 1: Replace table-only calendar styling**

Implement these layout contracts in `assets/css/aa-events.css`:

```css
.aa-events-calendar-grid { border-left: 1px solid #aaa; border-top: 1px solid #aaa; }
.aa-events-calendar-week { position: relative; }
.aa-events-calendar-days,
.aa-events-calendar-weekdays { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
.aa-events-calendar-day {
    border-bottom: 1px solid #aaa;
    border-right: 1px solid #aaa;
    min-height: calc(4.5rem + var(--aa-event-lanes, 1) * 2rem);
    padding: .625rem;
}
.aa-events-calendar-bars {
    display: grid;
    gap: .25rem 0;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    grid-template-rows: repeat(var(--aa-event-lanes), 1.75rem);
    left: 0;
    padding: 2.25rem .2rem .5rem;
    pointer-events: none;
    position: absolute;
    right: 0;
    top: 0;
}
.aa-events-calendar-event {
    background: #0969da;
    border-radius: .25rem;
    color: #fff;
    grid-column: calc(var(--aa-event-column) + 1) / span var(--aa-event-span);
    grid-row: calc(var(--aa-event-lane) + 1);
    overflow: hidden;
    padding: .2rem .45rem;
    pointer-events: auto;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.aa-events-calendar-event.is-continuing-before { border-bottom-left-radius: 0; border-top-left-radius: 0; }
.aa-events-calendar-event.is-continuing-after { border-bottom-right-radius: 0; border-top-right-radius: 0; }
.aa-events-calendar-event:focus-visible { outline: 3px solid #111; outline-offset: 2px; z-index: 2; }
.aa-events-calendar-day.is-outside-month { background: #f6f7f7; color: #646970; }
.aa-events-calendar-day.today-cell { background: #ffe9b3; box-shadow: inset 0 0 0 2px #f7b500; }
.aa-events-calendar-agenda { display: none; }
```

Adjust the week minimum height from `--aa-event-lanes` so absolute bars cannot overlap the following week. Preserve existing header, Today button, archive, and card-grid styles.

- [ ] **Step 2: Add the mobile breakpoint**

At `max-width: 640px`, hide `.aa-events-calendar-desktop`, show `.aa-events-calendar-agenda`, and style agenda links as full-width blocks with a visible left border, readable title/date hierarchy, and `:focus-visible`. Do not rely on color alone to identify links.

- [ ] **Step 3: Synchronize the theme-copy stylesheet**

Run:

```bash
cp assets/css/aa-events.css templates/aa-events.css
cmp assets/css/aa-events.css templates/aa-events.css
```

Expected: `cmp` exits with status 0.

- [ ] **Step 4: Verify CSS structure**

Run:

```bash
rg -n "aa-events-calendar-(grid|week|event|agenda)" assets/css/aa-events.css
cmp assets/css/aa-events.css templates/aa-events.css
```

Expected: all four component selectors are present and both stylesheets are identical.

- [ ] **Step 5: Commit responsive styling when Git is available**

```bash
git add assets/css/aa-events.css templates/aa-events.css
git commit -m "style: add responsive multi-day calendar layout"
```

### Task 8: Document the New Editing and Theme Behavior

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Rewrite the multiple-date documentation**

Rename “Multiple Event Dates” to “Event Occurrences.” Document that each row supports a required start date, optional times/end date, and all-day mode. Include one example combining a Friday–Sunday occurrence with a separate later occurrence.

- [ ] **Step 2: Document responsive calendar behavior**

State explicitly that desktop/tablet retain the traditional month grid with week-spanning bars and screens at or below 640px use an agenda list.

- [ ] **Step 3: Document custom ACF groups and theme overrides**

List required repeater/subfield names: `event_dates`, `date`, `start_time`, `end_date`, `end_time`, `all_day`. Explain that an existing theme override of `aa-events/calendar.php` remains authoritative and must be updated to opt into spanning bars, while it can use `aa_events_get_occurrences()` immediately.

- [ ] **Step 4: Check documentation terminology**

Run:

```bash
rg -n "multiple_dates|Event Dates repeater|multi-day|Occurrences|640px" README.md
```

Expected: no instructions tell editors to use `multiple_dates`; occurrence and responsive behavior are documented.

- [ ] **Step 5: Commit documentation when Git is available**

```bash
git add README.md
git commit -m "docs: explain event occurrence ranges"
```

### Task 9: Final Verification

**Files:**
- Verify all modified PHP, JavaScript, CSS, tests, templates, and documentation.

- [ ] **Step 1: Run the complete automated suite**

Run:

```bash
php tests/run.php
find . -path './.superpowers' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
cmp assets/css/aa-events.css templates/aa-events.css
```

Expected: all AA Events tests pass, every PHP file reports no syntax errors, and CSS files compare equal.

- [ ] **Step 2: Verify field-name consistency**

Run:

```bash
rg -n "multiple_dates" --glob '!docs/IMPLEMENTATION_PLAN_REPEATER.md' .
rg -n "event_dates|end_date|end_time|all_day" includes templates README.md
```

Expected: no production references to `multiple_dates`; all new fields are present in registration, normalization, templates, and documentation.

- [ ] **Step 3: Perform WordPress editor checks**

In the local WordPress site:

1. Create one event with a timed Friday 6 PM through Sunday 2 PM occurrence.
2. Add a second all-day occurrence to the same event.
3. Confirm all-day disables/clears time inputs.
4. Attempt an end before the start and confirm a row-numbered save error.
5. Save a legacy-only event and confirm exactly one occurrence row is created.
6. Save it again and confirm no duplicate row appears.

Expected: editing, validation, and migration match the design.

- [ ] **Step 4: Perform calendar and accessibility checks**

1. View a month where an occurrence crosses a week and confirm the title repeats on both segments.
2. View occurrences crossing previous/next month boundaries and confirm continuation indicators.
3. Create overlapping ranges and confirm separate lanes with no covering.
4. Change WordPress “Week Starts On” between Sunday and Monday and confirm segment columns remain correct.
5. Keyboard-tab through event bars and confirm visible focus and complete accessible labels.
6. At 641px confirm the traditional grid; at 640px confirm the agenda and one item per occurrence.
7. Confirm Today and previous/next navigation update both presentations.

Expected: all visual, responsive, and keyboard behaviors match the approved specification.

- [ ] **Step 5: Commit final corrections when Git is available**

```bash
git add -A
git commit -m "fix: complete multi-day occurrence verification"
```

Only create this commit if verification required corrections; otherwise leave the previous focused commits as the complete history.
