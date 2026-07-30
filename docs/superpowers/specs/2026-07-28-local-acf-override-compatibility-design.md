# Local ACF Override Compatibility Design

## Problem

AA Events suppresses its built-in ACF field group when the MNBC theme's local
field group is enabled. The theme group stores repeating dates under
`multiple_dates` and returns dates and times as `F j, Y` and `g:i a`. The
plugin's occurrence reader only reads `event_dates` and strictly parses
`Y-m-d` dates and 24-hour times. As a result, the plugin detects the local
group as an override but cannot turn its schedule values into occurrences, so
the events do not appear in schedule-driven views.

## Scope

This change makes the plugin compatible with the existing MNBC schedule fields
and normalizes the theme's local ACF JSON to the documented AA Events schedule
contract. It does not rename or otherwise change unrelated cost, location, or
contact fields.

## Plugin Compatibility

`aa_events_get_occurrences()` will use schedule sources in this order:

1. The canonical `event_dates` repeater when it contains rows.
2. The legacy local-override `multiple_dates` repeater when it contains rows.
3. The existing `event_datetime` fallback.
4. The existing standalone `date` and `start_time` fallback.

Canonical rows take precedence when both repeaters contain data. Legacy
`multiple_dates` rows will normalize to the same public occurrence shape as
canonical rows, without adding a new public source type.

The occurrence parsing boundary will accept the plugin's canonical return
formats and the formats emitted by the existing theme override:

- Dates: `Y-m-d`, compact ACF `Ymd`, and `F j, Y`.
- Times: `H:i:s`, `H:i`, and `g:i a`.

All accepted values will be normalized to `Y-m-d` and `H:i:s` before existing
range validation, sorting, and rendering logic runs. Invalid or ambiguous
values will continue to be rejected rather than guessed.

Metadata-change synchronization will recognize both repeater names and their
schedule subfields. ACF value-cache flushing will include `multiple_dates`, so
direct metadata changes cannot leave stale canonical occurrence indexes.

## Theme ACF JSON

The MNBC theme field group at
`wp-content/themes/MNBC/acf/group_69fb58894f83f.json` will be updated as
follows:

- Rename the repeater field from `multiple_dates` to `event_dates`, retaining
  its stable ACF field key.
- Change the standalone and repeater Date fields' return format to `Y-m-d`.
- Change the standalone and repeater Start Time and End Time fields' return
  format to `H:i:s`.

The existing field keys remain unchanged. No unrelated field definitions will
be modified.

## Existing Data

Renaming the ACF field does not rename previously saved WordPress metadata.
Existing rows stored as `multiple_dates_*` therefore remain in place and are
read through the plugin compatibility fallback. Newly edited schedule data
will be saved under canonical `event_dates_*` keys.

No destructive or automatic bulk database migration is included. If both old
and new repeater data exist on an event, canonical `event_dates` data is
authoritative.

## Testing

Regression tests will first reproduce the current theme override:

- `multiple_dates` rows using `F j, Y` dates and `g:i a` times produce
  normalized occurrences.
- Standalone legacy fields in those formats produce an occurrence.
- Canonical `event_dates` rows win when both repeater names contain data.
- Invalid formatted values remain excluded.
- Metadata synchronization recognizes and flushes the legacy repeater.

The focused occurrence tests and complete plugin test suite must pass after
the implementation. The theme JSON will also be parsed to confirm it remains
valid JSON and contains the canonical schedule names and return formats.
