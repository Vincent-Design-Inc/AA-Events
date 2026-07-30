# Multi-Day Event Occurrences Design

## Objective

Add an intuitive event-occurrence model that supports single-day events, continuous multi-day events, recurring events, and combinations of those patterns. Display multi-day occurrences as continuous bars across a traditional month-grid calendar and use a chronological agenda on phone-sized screens.

## Existing Behavior

The plugin stores discrete dates in the optional `event_dates` ACF repeater and falls back to the legacy `event_datetime` field. Calendar rendering places the event independently inside each matching day cell. Treating a multi-day event as several discrete dates therefore produces repeated links rather than one visually continuous event.

## Occurrence Data Model

Keep the `event_dates` field name for storage compatibility, but change its editor label to **Occurrences**. Each repeater row represents one occurrence and contains:

- `date`: required start date in `Y-m-d` format.
- `start_time`: optional start time.
- `end_date`: optional end date in `Y-m-d` format.
- `end_time`: optional end time.
- `all_day`: boolean toggle.

An omitted `end_date` means the occurrence ends on its start date. An `end_time` without an `end_date` therefore applies to the start date. When `all_day` is enabled, the time controls are hidden and stored time values are ignored by readers.

Every row is independent. A single event may contain any mixture of single-day and multi-day occurrences, allowing clients to represent recurrence and duration together.

### Validation

- A start date is required for every row.
- An end date cannot precede the start date.
- When both times apply to the same date, the end time cannot precede the start time.
- Time comparison uses the WordPress site timezone.
- Missing optional end values are valid and must not prevent saving.

Validation messages identify the affected occurrence row and field. Invalid ranges are rejected rather than silently reordered.

## Normalization and Compatibility

Introduce a normalized occurrence helper as the sole read interface used by plugin templates and admin features. Each normalized item contains the post ID, start date/time, effective end date/time, all-day status, and whether the start or end was inferred from legacy data.

The read priority is:

1. Rows in `event_dates`.
2. The legacy `event_datetime` field.
3. Older standalone `date` and `start_time` fields, where present.

Existing `event_dates` rows remain valid because all new subfields are optional. When an editor saves an event that has no repeater rows but does have legacy date data, the plugin creates one equivalent occurrence row. Untouched events continue to render through the read fallback, so migration does not require a bulk operation.

The legacy fields remain registered for compatibility but should be visually de-emphasized in the built-in editor once occurrence editing is available. Migration must be idempotent and must never append a duplicate row on subsequent saves.

Sites using their own admin-created ACF event field group are responsible for adding the new optional subfields with the documented names. Normalization tolerates absent subfields.

## Editor Experience

The built-in ACF repeater is labeled **Occurrences**, with an **Add Occurrence** button. Rows expose start and end values together so editors understand that a row is one continuous occurrence rather than one day.

The `all_day` toggle conditionally hides `start_time` and `end_time`. The end controls remain optional. Concise field instructions explain that additional rows create recurrence and that an end date creates a continuous multi-day occurrence.

## Calendar Architecture

Desktop and tablet retain a traditional month grid: seven weekday columns, date-numbered cells, and one row per calendar week. Event bars are rendered in week-level lanes layered across the date columns rather than independently inside each cell.

For each normalized occurrence that intersects the viewed month:

1. Clip its visible interval to the month-grid range.
2. Split the visible interval at week boundaries.
3. Convert each weekly segment into a start column and column span.
4. Assign segments to the first available lane that does not overlap another segment in that week.
5. Render the segment as a linked bar spanning the relevant date columns.

Each weekly segment repeats the event title. A segment has rounded edges at the occurrence's true visible start or end. An edge is squared and displays a continuation indicator when the occurrence continues before or after that weekly segment. Month-boundary clipping also uses a continuation indicator so the user can see that the event extends beyond the current view.

Single-day occurrences use the same bar component with a one-column span. Multiple occurrences belonging to the same event are treated independently. Overlapping occurrences occupy separate lanes and never cover one another.

Day cells grow vertically to accommodate the required event lanes. The current-day highlight remains behind the date cell and does not obscure event bars.

## Mobile Agenda

At a maximum width of approximately 640 pixels, matching the plugin's existing responsive breakpoint, hide the month grid and show a chronological agenda for the selected month.

Each occurrence intersecting the selected month appears once, including occurrences that start in an earlier month or end in a later month. The agenda item displays the complete start/end range, not one item per day. Items sort by occurrence start, then event title for deterministic ordering. Calendar month navigation and the Today action control both desktop and mobile views.

## Date and Time Presentation

Formatting uses WordPress localization functions and the configured site timezone.

- All-day, one-day: one localized date followed by “All day.”
- All-day, multi-day: localized start and end dates followed by “All day.”
- Timed, same-day with an end: date plus start–end times.
- Timed, same-day without an end: date plus start time.
- Timed, multi-day: full start date/time through full end date/time.

Archive and single-event templates render one formatted range per occurrence. They do not expand a multi-day occurrence into one line per day.

## Accessibility

- Each event bar is a normal keyboard-focusable link.
- The accessible label includes the event title and complete occurrence range, even when the visible segment is clipped.
- Continuation arrows are decorative and hidden from assistive technology.
- The grid retains weekday headings and meaningful date labels.
- The mobile agenda uses semantic list markup and headings or time elements as appropriate.
- Color is not the only indication that an event continues across a boundary.

## Admin and Query Behavior

The Events admin column displays the earliest normalized occurrence and a count of additional occurrences. Admin sorting uses the earliest occurrence start, then falls back to legacy values and post date. Existing REST ordering behavior remains compatible; the occurrence normalization work does not introduce a public recurrence-generation API.

Calendar data collection continues to include published events. Filtering occurs against occurrence intervals so an event is included when any part of an occurrence intersects the selected month.

## Theme Compatibility

The plugin's bundled templates and stylesheet implement the new layout. Theme overrides continue to resolve through the existing loader. A theme-provided `aa-events/calendar.php` remains authoritative and will not automatically acquire week-spanning bars. This limitation must be documented in the release notes or README.

The normalized occurrence helper is public plugin code so theme overrides can adopt the same data model without duplicating compatibility logic.

## Error Handling

- Malformed or incomplete repeater rows are skipped safely during rendering and do not break the calendar.
- Save-time validation reports actionable row-level errors.
- Legacy values that cannot be parsed are left unchanged and are not migrated.
- A calendar with no valid occurrences renders its normal empty state.
- Duplicate rows are preserved because clients may intentionally create simultaneous occurrences; only automatic legacy migration performs duplicate prevention.

## Testing Strategy

Unit-level tests should cover:

- Normalization of new, existing-repeater, and legacy field formats.
- Idempotent legacy conversion on save.
- Optional ends, all-day behavior, and invalid range rejection.
- Same-day and multi-day range formatting.
- Month intersection and clipping at both boundaries.
- Week splitting with configurable WordPress week starts.
- Deterministic lane assignment for overlapping segments.
- Site-timezone and daylight-saving boundaries.
- Admin earliest-occurrence selection and sorting fallbacks.

Rendering tests should cover:

- One-column and multi-column bars.
- Week and month continuation indicators.
- Repeated titles on every weekly segment.
- Escaped titles and accessible full-range labels.
- Desktop month-grid semantics.
- Mobile agenda ordering and one-entry-per-occurrence behavior.

Manual verification should include the built-in editor's conditional controls, a mixture of recurring and multi-day rows on one event, keyboard navigation, a theme override, and layouts at widths immediately above and below the mobile breakpoint.

## Out of Scope

- Rule-based recurrence generation such as “every Tuesday until September.” Editors continue adding occurrence rows explicitly.
- Drag-and-drop calendar editing.
- Per-event colors or category-based color configuration.
- A bulk migration command for all legacy events.
- Changes to calendar month-navigation URLs.
