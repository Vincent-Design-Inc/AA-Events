# ACF Field-Key Switching Compatibility Design

## Problem

The built-in and theme-local field groups use different ACF field keys. Event
415 currently has canonical `event_dates` metadata whose hidden reference
points to the built-in repeater key, while the active database copy of the
theme group still defines its repeater as `multiple_dates`. ACF cannot resolve
the inactive key through `get_field()`, so normal field loading can return only
the raw repeater count instead of its rows. The front end remains visible
because AA Events falls back to the standalone `date` field.

The updated theme JSON has not yet been synchronized into ACF's database field
definitions.

## Field-Group Synchronization

Import the existing theme JSON group through ACF's field-group import API. The
import retains every stable theme field key while updating the database copy
to use the canonical `event_dates`, `Y-m-d`, and `H:i:s` contract.

The import is a one-time site synchronization, not an automatic plugin
mutation of arbitrary external ACF groups.

## Runtime Field Loading

AA Events will resolve a supported field through the currently registered ACF
field definition when one is available, rather than relying only on the hidden
metadata reference. This lets `event_dates` load through whichever field key
is active:

- Built-in: `field_61b0c7f0a3e8f`.
- MNBC local group: `field_69fb5b1aac54a`.

The existing `get_field()` path remains the fallback for tests, legacy fields,
and installations where the lower-level ACF APIs are unavailable.

## Legacy Repeater Migration

Add an idempotent migration helper for event posts:

1. Do nothing when canonical `event_dates` contains rows.
2. Read and normalize legacy `multiple_dates` rows.
3. Write equivalent canonical rows to the currently registered
   `event_dates` field.
4. Preserve the legacy metadata rather than deleting it.

Run this migration once for the live MNBC events after synchronizing the field
group. Keep the helper available on event saves so a legacy-only event becomes
canonical when edited.

Canonical rows remain authoritative if both formats exist.

## Verification

- Add regression tests for loading a canonical repeater through an active ACF
  definition even when its hidden reference belongs to the inactive group.
- Add idempotent migration tests for legacy-only and already-canonical events.
- Run the complete plugin test suite and PHP syntax checks.
- Confirm the live ACF database field definition is named `event_dates`.
- Confirm event 415 returns an occurrence from `event_dates`.
- Inspect the raw metadata and verify no legacy schedule metadata was deleted.
