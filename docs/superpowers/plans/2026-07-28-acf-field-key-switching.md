# ACF Field-Key Switching Compatibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make event occurrence rows load and remain editable when switching between the built-in and MNBC local ACF field groups.

**Architecture:** Resolve schedule values through the active ACF field definition so stale hidden reference keys cannot block reads. Add an idempotent legacy repeater migration, then synchronize the existing MNBC JSON group through ACF's import API and migrate live events without deleting old metadata.

**Tech Stack:** PHP 7.4+, WordPress, ACF PRO, WP-CLI, custom PHP test harness

---

### Task 1: Load Through the Active ACF Definition

**Files:**
- Modify: `tests/bootstrap.php`
- Modify: `tests/test-occurrences.php`
- Modify: `includes/occurrences.php`

- [ ] Add test doubles for `acf_get_field()`, `acf_get_value()`, and
  `acf_format_value()` that can model an active field definition.
- [ ] Add a regression test where normal `get_field('event_dates')` returns a
  raw repeater count but the active ACF definition returns rows.
- [ ] Run `php tests/run.php` and verify the regression fails.
- [ ] Add `aa_events_get_active_field_value()` and use it for
  `event_dates`/`multiple_dates` reads.
- [ ] Run `php tests/run.php` and verify all assertions pass.

### Task 2: Migrate Legacy Repeater Rows

**Files:**
- Modify: `tests/test-occurrences.php`
- Modify: `includes/occurrences.php`

- [ ] Add tests proving a legacy-only event writes canonical rows, an event
  with canonical rows is untouched, and legacy metadata is not deleted.
- [ ] Run `php tests/run.php` and verify the new assertions fail.
- [ ] Implement `aa_events_migrate_multiple_dates_occurrences()` and invoke it
  from the existing event-save migration hook before legacy scalar migration.
- [ ] Run `php tests/run.php` and verify all assertions pass.

### Task 3: Synchronize and Migrate the Live Site

**Files:**
- Read: `../../themes/MNBC/acf/group_69fb58894f83f.json`
- Mutate: the local WordPress ACF field-group database records through WP-CLI

- [ ] Import the decoded JSON with `acf_import_field_group()`.
- [ ] Run the legacy repeater migration for every event post.
- [ ] Verify the runtime local repeater is named `event_dates`.
- [ ] Verify event 415 loads canonical occurrence rows and retain a snapshot of
  its legacy schedule metadata for comparison.

### Task 4: Final Verification

**Files:**
- Verify: `includes/occurrences.php`
- Verify: `tests/bootstrap.php`
- Verify: `tests/test-occurrences.php`

- [ ] Run PHP syntax checks on all changed PHP files.
- [ ] Run `php tests/run.php`.
- [ ] Run a read-only live WP-CLI check for field definitions, event 415
  occurrence source, and schedule metadata.
