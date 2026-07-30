# Multi-Day Calendar Segment Interaction Design

## Objective

When one continuous event occurrence is split into multiple desktop calendar segments at week boundaries, hovering or keyboard-focusing any segment will apply the existing hover appearance to every segment belonging to that occurrence.

## Occurrence Identity

Calendar segments need an occurrence-level identity because one event post can contain several distinct recurring occurrences. Grouping by event ID or permalink would incorrectly combine those separate dates.

During calendar enrichment, each normalized occurrence receives a key derived from:

- The event post ID.
- The occurrence’s zero-based index within that event’s normalized occurrence list.

The key is stable for the rendered request, contains only integers and a separator, and is unique within a calendar instance. `aa_events_calendar_segments()` already copies complete occurrence metadata into each week segment, so every segment of the continuous occurrence retains the same key.

Each desktop segment anchor exposes the key through `data-aa-events-occurrence`. Mobile list items are not split and do not require synchronized interaction.

## Interaction Controller

The existing calendar-view controller will also initialize occurrence-segment groups within each `[data-aa-events-calendar]` root.

For each group, the controller will:

- Add `is-occurrence-active` to every segment when any group segment receives pointer hover.
- Keep the group active while any segment remains hovered.
- Add the same active class when any group segment receives keyboard focus.
- Keep hover and focus state independent, so pointer exit does not cancel a keyboard-focused group and blur does not cancel a still-hovered group.
- Remove the active class from the group only when neither hover nor focus remains.

Grouping is scoped to one calendar root. Identical occurrence keys in two independently rendered calendars will not affect each other.

Per-element listeners and group counters will be used because the number of rendered segments is bounded and small. This avoids dynamic selector escaping and correctly handles movement between segments in the same group.

If JavaScript is unavailable, the existing `:hover` and `:focus-visible` rules continue to style the directly interacted segment.

## Visual Behavior

The synchronized class will share the existing hover appearance:

- Darker background.
- Underlined event title.

The actual keyboard-focused anchor retains its existing focus outline and higher stacking order. Other segments in the same group receive the hover appearance without showing a false focus outline.

Both plugin stylesheet copies and MNBC’s theme override stylesheet will add `is-occurrence-active` alongside their existing hover selector. Colors and other presentation remain native to each stylesheet.

## Template Compatibility

The plugin calendar template and MNBC calendar override will both:

- Enumerate normalized occurrences so distinct recurring rows receive distinct keys.
- Preserve the key through calendar layout.
- Add the escaped `data-aa-events-occurrence` attribute to desktop segment anchors.

No archive, agenda-list, or single-event markup changes are required.

## Versioning

The plugin header, `AA_EVENTS_VERSION`, standalone test bootstrap, and version contract will move from `1.2.5` to `1.2.6`. This patch release ensures browsers request the updated calendar controller and plugin stylesheet instead of reusing cached `1.2.5` assets.

Because the occurrence index currently uses `AA_EVENTS_VERSION` as its schema marker, the bump will schedule the existing bounded index backfill. No occurrence data migration or schema change is otherwise required.

## Testing

Plugin tests will verify:

- Version references agree on `1.2.6`.
- Continuous occurrences preserve one key across all week segments.
- Separate normalized occurrences for the same event receive different keys.
- Rendered cross-week anchors expose matching occurrence attributes.
- Hovering one segment activates every matching segment.
- Pointer exit removes synchronized styling only when no group segment remains hovered or focused.
- Keyboard focus activates every matching segment and blur clears it when appropriate.
- Hover and focus state do not cancel each other.
- Different occurrence groups and different calendar instances remain isolated.
- Incomplete markup remains safe.
- Plugin CSS applies the established hover appearance to `is-occurrence-active`.
- Both plugin stylesheet copies remain byte-identical.

Theme contract tests will verify:

- MNBC emits the occurrence attribute using the canonical key.
- MNBC’s active-group selector shares the existing theme hover appearance.
- The existing calendar/list switching behavior remains unchanged.

PHP syntax, plugin PHP tests, JavaScript tests, plugin coding standards for changed feature files, theme contracts, theme coding standards, and whitespace checks must pass.

## Out of Scope

- Synchronizing agenda-list items with desktop segments.
- Highlighting separate recurring occurrences of the same event.
- Persisting hover or focus state across page loads or month navigation.
- Changing the event bar colors, dimensions, or focus-outline design.
- Highlighting segments across separate calendar instances.
