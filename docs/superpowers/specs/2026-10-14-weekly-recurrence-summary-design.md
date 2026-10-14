# Weekly Recurrence Summary Design

## Goal

Make recurring event summaries retain the interval and selected weekday for
weekly series such as the Wellness Kinship Healing Circle, configured as every
two weeks on Thursday.

## Behavior

- Weekly recurrences with selected weekdays include their cadence and weekday
  in the summary. For example: `Event runs every 2 weeks on Thursday,
  indefinitely.`
- Interval-one weekday recurrences retain their existing wording.
- The weekday summary respects the configured end condition rather than always
  stating that the series is indefinite.
- Other recurrence units and existing monthly ordinal summaries remain
  unchanged.

## Implementation and verification

Update the weekly weekday branch in `aa_events_describe_recurrence()` and add
focused regression tests covering a multi-week interval and the end-condition
wording. Run the recurrence-summary test file.
