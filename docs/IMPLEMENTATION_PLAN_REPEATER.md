# Multiple Event Dates Implementation Plan

> **Goal:** Add support for events with multiple dates/times using an ACF repeater field, with backward compatibility for legacy `event_datetime` and `date`/`start_time` fields.

> **Architecture:** Add a new ACF repeater field group `event_dates` containing date and start_time sub-fields. Update display logic to prioritize repeater, then fall back to legacy fields. Update sorting to use earliest date from repeater. Add migration helper for data consolidation.

> **Tech Stack:** Advanced Custom Fields (ACF), WordPress query (WP_Query), ACF repeater fields

---

## File Structure Changes

**Modified Files:**
- `includes/acf-fields.php` - Add repeater field to ACF group
- `includes/post-types.php` - Update display and sorting logic
- `templates/calendar.php` - Update to use repeater field (if needed)

**No new files needed initially** - Keep changes focused.

---

## Task 1: Add ACF Repeater Field Group

**Files:**
- Modify: `includes/acf-fields.php`

- [ ] **Step 1: Add repeater field definition to ACF group**

Insert a new field in the `$fields` array that defines a repeater group with `date` and `start_time` sub-fields. The repeater should:
- Be named `event_dates`
- Allow multiple rows
- Contain sub-fields: `date` (date picker) and `start_time` (time picker)
- Be positioned after existing fields but before location fields
- Have conditional logic to display only if no external admin field group exists

Insert this after `event_cost` field and before `event_location_type`.

- [ ] **Step 2: Verify ACF field appears in post editor**

Test that the field group loads and the repeater appears in the post editor for event CPT.

---

## Task 2: Update Admin Column Display

**Files:**
- Modify: `includes/post-types.php` - function `events_display_admin_column_content()`

- [ ] **Step 1: Update display logic to use repeater**

Modify the column display function to:
1. Check if `event_dates` repeater has rows
   - If yes: display first date/time + badge showing total count (e.g., "Jan 5, 2025 2:00 PM (3 dates)")
   - If no: fall back to legacy `event_datetime` field
   - If no legacy field: fall back to new `date`/`start_time` fields

Logic flow:
```
if has repeater rows:
  show first date/time + " (X dates)" badge
elif has event_datetime:
  show event_datetime
elif has date field:
  show date + start_time
else:
  show nothing
```

- [ ] **Step 2: Test display in admin list**

Verify the column shows:
- First date with count badge for events with repeater data
- Legacy format for old events
- Nothing for events with no dates

---

## Task 3: Update Sorting Logic

**Files:**
- Modify: `includes/post-types.php` - functions `events_get_sortable_meta_map()` and `events_sort_by_custom_column()`

- [ ] **Step 1: Add repeater field to sortable map**

Note: ACF repeater fields cannot be directly sorted by `orderby=meta_value`. Instead, we'll sort by the earliest date.

- [ ] **Step 2: Update sort handler**

When sorting by `event_datetime` column:
1. If repeater has data: query by first row's `event_dates_0_date` meta key (ACF stores repeater rows as sequential meta keys)
2. If repeater empty: use legacy sort (event_datetime or date field)

This ensures posts sort by their earliest event date.

- [ ] **Step 3: Test sorting**

Verify that:
- Posts with repeater data sort by earliest date
- Legacy posts sort correctly
- Mixed results display in correct order

---

## Task 4: Update Calendar Template (if needed)

**Files:**
- Check: `templates/calendar.php`

- [ ] **Step 1: Inspect calendar usage**

Review how calendar.php queries and displays events. Determine if it needs updates to handle repeater field.

- [ ] **Step 2: Update if necessary**

If calendar shows single events per date:
- Update query to use earliest date from repeater
- Add note in template comments about repeater structure

---

## Task 5: Document Migration Path (Optional)

**Files:**
- Create reference docs in README or code comments

- [ ] **Step 1: Document field deprecation**

Add comments in `acf-fields.php` explaining:
- Legacy `event_datetime` field is deprecated but still supported
- New events should use `event_dates` repeater
- Old events continue to work via fallback logic

- [ ] **Step 2: Add helper function comment**

Document that a future migration tool could consolidate old data into repeater format, but it's not urgent since fallback works.

---

## Implementation Notes

- **ACF Repeater Meta Storage:** ACF stores repeater rows as numbered meta keys: `event_dates_0_date`, `event_dates_0_start_time`, `event_dates_1_date`, etc.
- **Backward Compatibility:** Never remove old fields—just add fallback logic in display/sort functions.
- **Performance:** Querying repeater fields for sorting can be slow on large datasets. Consider date-based sorting as primary option if performance degrades.
- **Frontend:** Templates on the frontend may also need updates to loop through all dates instead of showing one. This is separate from admin display.

---

## Testing Checklist

- [ ] Post with repeater data displays first date + badge in admin list
- [ ] Post with old `event_datetime` field displays correctly (fallback)
- [ ] Post with new `date`/`start_time` fields displays correctly (second fallback)
- [ ] Admin list sorts correctly by earliest date when column clicked
- [ ] Posts without any date fields show nothing (don't error)
- [ ] Calendar template still works (if uses event dates)
