# Local ACF Override Compatibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep existing MNBC events visible with the legacy local ACF override while normalizing that override for future edits.

**Architecture:** Extend occurrence input normalization at the plugin boundary, then add `multiple_dates` as a lower-priority repeater source. Extend metadata synchronization to observe that legacy source. Finally, update the theme ACF JSON to the canonical AA Events names and formats without changing stable field keys or migrating stored metadata.

**Tech Stack:** PHP 7.4+, WordPress, ACF PRO, JSON, the plugin's custom PHP test harness

---

### Task 1: Parse Existing Override Values

**Files:**
- Modify: `tests/test-occurrences.php`
- Modify: `includes/occurrences.php`

- [ ] **Step 1: Write failing parser regression tests**

Add assertions showing that `aa_events_normalize_occurrence_row()` accepts
`July 30, 2026` and `2:15 pm`, normalizes them to `2026-07-30` and
`14:15:00`, and rejects invalid calendar/time strings.

- [ ] **Step 2: Run the focused test and verify RED**

Run: `php tests/run.php`

Expected: failures report that the formatted override row was not normalized.

- [ ] **Step 3: Implement minimal multi-format parsing**

Add focused date and time parsing helpers. Dates must try `Y-m-d`, `Ymd`, and
`F j, Y` with exact round-trip validation. Times must try `H:i:s`, `H:i`, and
`g:i a`, normalizing accepted values to `H:i:s`. Route occurrence-row date
parsing through the date helper while retaining existing strict validation.

- [ ] **Step 4: Run tests and verify GREEN**

Run: `php tests/run.php`

Expected: all assertions pass.

### Task 2: Read Legacy `multiple_dates` Rows

**Files:**
- Modify: `tests/test-occurrences.php`
- Modify: `includes/occurrences.php`

- [ ] **Step 1: Write failing source-precedence tests**

Create an event containing only `multiple_dates` formatted rows and assert that
`aa_events_get_occurrences()` returns normalized occurrences. Create another
event containing both repeaters and assert that `event_dates` is authoritative.
Also assert the formatted standalone `date`/`start_time` fallback works.

- [ ] **Step 2: Run the focused test and verify RED**

Run: `php tests/run.php`

Expected: the `multiple_dates` event returns no occurrence.

- [ ] **Step 3: Implement repeater fallback**

Read `event_dates` first. Only when it is empty, read `multiple_dates`. Feed
either repeater through the existing row normalization path and preserve the
existing public `event_dates` source label.

- [ ] **Step 4: Run tests and verify GREEN**

Run: `php tests/run.php`

Expected: all assertions pass.

### Task 3: Synchronize Legacy Repeater Metadata

**Files:**
- Modify: `tests/test-occurrences.php`
- Modify: `includes/occurrences.php`
- Modify: `includes/post-types.php`

- [ ] **Step 1: Write failing synchronization tests**

Assert that `aa_events_sort_source_meta_key()` accepts `multiple_dates`, its
supported row subfield keys, and rejects unrelated keys. Queue a legacy-row
change and assert that `aa_events_run_queued_sort_sync()` flushes the
`multiple_dates` ACF cache and writes the canonical sort metadata.

- [ ] **Step 2: Run the focused test and verify RED**

Run: `php tests/run.php`

Expected: the legacy key is ignored or its ACF cache is not flushed.

- [ ] **Step 3: Extend metadata recognition and SQL fallback**

Add `multiple_dates` to exact-key checks, row-key patterns, and cache-flush
lists. Extend the unsynchronized SQL sorting fallback to consider
`multiple_dates_N_date` rows after canonical `event_dates` rows while keeping
the canonical indexed metadata first.

- [ ] **Step 4: Run tests and verify GREEN**

Run: `php tests/run.php`

Expected: all assertions pass.

### Task 4: Normalize the MNBC ACF Field Group

**Files:**
- Modify: `../../themes/MNBC/acf/group_69fb58894f83f.json`

- [ ] **Step 1: Update only the schedule contract**

Keep all field keys stable. Change the repeater name from `multiple_dates` to
`event_dates`; change both date-picker return formats to `Y-m-d`; change
the standalone and repeater start/end time return formats to `H:i:s`.

- [ ] **Step 2: Validate the JSON and contract**

Run:

```bash
php -r '$p="../../themes/MNBC/acf/group_69fb58894f83f.json"; $j=json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); $fields=$j["fields"]; $repeater=array_values(array_filter($fields, fn($f) => ($f["key"] ?? "") === "field_69fb5b1aac54a"))[0]; if (($repeater["name"] ?? "") !== "event_dates") { exit(1); } echo "valid\n";'
```

from `wp-content/plugins/AA-Events`.

Expected: `valid`

### Task 5: Complete Verification

**Files:**
- Verify: `includes/occurrences.php`
- Verify: `includes/post-types.php`
- Verify: `tests/test-occurrences.php`
- Verify: `../../themes/MNBC/acf/group_69fb58894f83f.json`

- [ ] **Step 1: Lint changed PHP**

Run:

```bash
php -l includes/occurrences.php
php -l includes/post-types.php
php -l tests/test-occurrences.php
```

Expected: no syntax errors.

- [ ] **Step 2: Run the complete test suite**

Run: `php tests/run.php`

Expected: `All tests passed.`

- [ ] **Step 3: Review the final diff without Git**

Because this installation is not a Git worktree, inspect the exact changed
sections and confirm that no unrelated theme fields or plugin behavior changed.
