# Multi-Day Calendar Segment Interaction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Synchronize the existing hover appearance across every week segment of one continuous event occurrence, with equivalent keyboard-focus behavior and strict isolation between recurrences and calendar instances.

**Architecture:** PHP assigns each normalized occurrence a key derived from event ID and occurrence index, and every desktop week segment exposes that key in a data attribute. The existing calendar controller groups matching anchors inside each calendar root and maintains independent hover/focus counters, while plugin and MNBC CSS reuse their existing hover colors through one synchronized-state class.

**Tech Stack:** PHP 8, WordPress, vanilla JavaScript, CSS, DOMDocument/XPath contract tests, Node’s built-in test runner, WordPress Coding Standards.

---

## Repository and File Map

### AA Events plugin repository

- Modify `aa-events.php`: release header and runtime asset version `1.2.7`.
- Modify `tests/bootstrap.php`: standalone version fixture.
- Modify `tests/test-admin-consumers.php`: version contract.
- Modify `includes/calendar-layout.php`: canonical occurrence-key helper.
- Modify `templates/calendar.php`: enrich occurrences and emit segment data attributes.
- Modify `tests/test-calendar-layout.php`: occurrence-key and segment-preservation contracts.
- Modify `tests/test-calendar-render.php`: rendered cross-week grouping contract.
- Modify `assets/js/aa-events-calendar-view.js`: occurrence-group interaction controller.
- Modify `tests/test-calendar-view.js`: hover, focus, isolation, and incomplete-markup tests.
- Modify `assets/css/aa-events.css`: synchronized state selector.
- Modify `templates/aa-events.css`: byte-identical stylesheet copy.
- Modify `tests/test-calendar-css.php`: synchronized selector contract.

### MNBC theme repository

- Modify `aa-events/calendar.php`: mirror canonical occurrence keys and data attributes.
- Modify `aa-events/aa-events.css`: reuse MNBC’s existing hover colors for synchronized state.
- Create `tests/test-aa-events-segment-interaction.php`: theme override source and CSS contracts.

## Task 1: Establish the `1.2.7` Version Baseline

**Files:**

- Modify: `tests/test-admin-consumers.php`
- Modify: `aa-events.php`
- Modify: `tests/bootstrap.php`

- [ ] **Step 1: Write the `1.2.7` version expectations**

In `tests/test-admin-consumers.php`, set both hard-coded release assertions to `1.2.7`:

```php
aa_events_assert_same( '1.2.7', $header_match[1] ?? '', 'Plugin header uses the feature release version.' );
aa_events_assert_same( $header_match[1] ?? '', $constant_match[1] ?? '', 'Plugin header and asset version constant match.' );
aa_events_assert_same( '1.2.7', AA_EVENTS_VERSION, 'Standalone runtime uses the feature release version.' );
```

- [ ] **Step 2: Run the PHP suite and verify RED**

Run:

```bash
php tests/run.php
```

Expected: version failures showing production `1.2.6` and standalone `1.2.5` instead of `1.2.7`.

- [ ] **Step 3: Align production and standalone versions**

In `aa-events.php`:

```php
 * Version:           1.2.7
```

```php
define( 'AA_EVENTS_VERSION', '1.2.7' );
```

In `tests/bootstrap.php`:

```php
define( 'AA_EVENTS_VERSION', '1.2.7' );
```

- [ ] **Step 4: Run the PHP suite and verify GREEN**

Run:

```bash
php tests/run.php
```

Expected: `All AA Events tests passed.`

- [ ] **Step 5: Commit the version alignment**

```bash
git add aa-events.php tests/bootstrap.php tests/test-admin-consumers.php
git commit -m "chore: prepare AA Events 1.2.7"
```

## Task 2: Add Canonical Occurrence Identity to Calendar Segments

**Files:**

- Modify: `tests/test-calendar-layout.php`
- Modify: `tests/test-calendar-render.php`
- Modify: `includes/calendar-layout.php`
- Modify: `templates/calendar.php`

- [ ] **Step 1: Write failing occurrence-key layout contracts**

In `tests/test-calendar-layout.php`, before the cross-week occurrence fixture, add:

```php
aa_events_assert_same( '501-0', aa_events_calendar_occurrence_key( 501, 0 ), 'Occurrence key combines event ID and normalized index.' );
aa_events_assert_same( '501-1', aa_events_calendar_occurrence_key( 501, 1 ), 'Separate recurrences of one event receive distinct keys.' );
```

Add a key to the existing cross-week `$occurrence`:

```php
'calendar_occurrence_key' => '1-0',
```

After its segment assertions, add:

```php
aa_events_assert_same(
	array( '1-0', '1-0' ),
	array_column( $segments, 'calendar_occurrence_key' ),
	'Every cross-week segment preserves its occurrence key.'
);
```

- [ ] **Step 2: Write the failing rendered-key contract**

In `tests/test-calendar-render.php`, replace the single event-link lookup with a node-list contract:

```php
$event_links = $xpath->query( './/a[contains(concat(" ", normalize-space(@class), " "), " aa-events-calendar-event ")]', $grid );
aa_events_assert_same( 2, $event_links->length, 'Cross-week occurrence renders two event segments.' );
$event_link = $event_links->item( 0 );
$occurrence_keys = array();
foreach ( $event_links as $rendered_event_link ) {
	$occurrence_keys[] = $rendered_event_link->getAttribute( 'data-aa-events-occurrence' );
}
aa_events_assert_same( array( '501-0', '501-0' ), $occurrence_keys, 'Cross-week segments expose one shared occurrence key.' );
```

Retain the existing assertions that use `$event_link`.

- [ ] **Step 3: Run the PHP suite and verify RED**

Run:

```bash
php tests/run.php
```

Expected: failure because `aa_events_calendar_occurrence_key()` and the rendered data attributes do not exist.

- [ ] **Step 4: Implement the canonical key helper**

Add before `aa_events_calendar_segments()` in `includes/calendar-layout.php`:

```php
/**
 * Build the request-stable key shared by one occurrence's calendar segments.
 *
 * @param int $post_id          Event post ID.
 * @param int $occurrence_index Zero-based normalized occurrence index.
 * @return string Occurrence key safe for an HTML data attribute.
 */
function aa_events_calendar_occurrence_key( $post_id, $occurrence_index ) {
	return (int) $post_id . '-' . (int) $occurrence_index;
}
```

- [ ] **Step 5: Enrich occurrences with their key**

In `templates/calendar.php`, enumerate normalized occurrences:

```php
foreach ( aa_events_get_occurrences( $event_id ) as $occurrence_index => $occurrence ) {
```

Before appending to `$calendar_occurrences`, add:

```php
$occurrence['calendar_occurrence_key'] = aa_events_calendar_occurrence_key( $event_id, $occurrence_index );
```

- [ ] **Step 6: Emit the occurrence attribute**

Add this attribute to the desktop segment anchor in `templates/calendar.php`:

```php
data-aa-events-occurrence="<?php echo esc_attr( $segment['calendar_occurrence_key'] ); ?>"
```

The anchor must retain its classes, inline layout variables, `href`, and `aria-label`.

- [ ] **Step 7: Run the PHP suite and verify GREEN**

Run:

```bash
php tests/run.php
```

Expected: `All AA Events tests passed.`

- [ ] **Step 8: Commit occurrence identity**

```bash
git add includes/calendar-layout.php templates/calendar.php tests/test-calendar-layout.php tests/test-calendar-render.php
git commit -m "feat: identify continuous calendar segments"
```

## Task 3: Synchronize Hover and Focus in the Controller

**Files:**

- Modify: `tests/test-calendar-view.js`
- Modify: `assets/js/aa-events-calendar-view.js`

- [ ] **Step 1: Extend the DOM test double**

In `tests/test-calendar-view.js`, give `FakeElement` an `allMatches` map, a `querySelectorAll()` method, and a generic dispatcher:

```js
this.allMatches = {};
```

```js
querySelectorAll(selector) {
  return this.allMatches[selector] || [];
}
```

```js
dispatch(type) {
  for (const listener of this.listeners[type] || []) {
    listener({ currentTarget: this });
  }
}
```

In `makeCalendar()`, create segment fixtures from `options.segmentKeys`:

```js
const occurrenceSegments = (options.segmentKeys || []).map((key) => {
  const segment = new FakeElement();
  segment.setAttribute('data-aa-events-occurrence', key);
  return segment;
});
root.allMatches['[data-aa-events-occurrence]'] = occurrenceSegments;
```

Include `occurrenceSegments` in the returned calendar object.

- [ ] **Step 2: Write failing synchronized interaction tests**

Append:

```js
test('hover synchronizes only matching occurrence segments', () => {
  const calendar = makeCalendar({
    segmentKeys: ['501-0', '501-0', '501-1'],
  });

  init(makeDocument([calendar]), makeStorage(), makeMediaQuery());
  calendar.occurrenceSegments[0].dispatch('mouseenter');

  assert.equal(calendar.occurrenceSegments[0].classList.contains('is-occurrence-active'), true);
  assert.equal(calendar.occurrenceSegments[1].classList.contains('is-occurrence-active'), true);
  assert.equal(calendar.occurrenceSegments[2].classList.contains('is-occurrence-active'), false);

  calendar.occurrenceSegments[0].dispatch('mouseleave');
  assert.equal(calendar.occurrenceSegments[0].classList.contains('is-occurrence-active'), false);
  assert.equal(calendar.occurrenceSegments[1].classList.contains('is-occurrence-active'), false);
});

test('hover and keyboard focus keep independent group state', () => {
  const calendar = makeCalendar({ segmentKeys: ['501-0', '501-0'] });

  init(makeDocument([calendar]), makeStorage(), makeMediaQuery());
  calendar.occurrenceSegments[0].dispatch('focus');
  calendar.occurrenceSegments[1].dispatch('mouseenter');
  calendar.occurrenceSegments[0].dispatch('blur');

  assert.equal(calendar.occurrenceSegments[0].classList.contains('is-occurrence-active'), true);
  assert.equal(calendar.occurrenceSegments[1].classList.contains('is-occurrence-active'), true);

  calendar.occurrenceSegments[1].dispatch('mouseleave');
  assert.equal(calendar.occurrenceSegments[0].classList.contains('is-occurrence-active'), false);
  assert.equal(calendar.occurrenceSegments[1].classList.contains('is-occurrence-active'), false);
});

test('matching keys remain isolated between calendar instances', () => {
  const first = makeCalendar({ segmentKeys: ['501-0', '501-0'] });
  const second = makeCalendar({ segmentKeys: ['501-0', '501-0'] });

  init(makeDocument([first, second]), makeStorage(), makeMediaQuery());
  first.occurrenceSegments[0].dispatch('focus');

  assert.equal(first.occurrenceSegments[1].classList.contains('is-occurrence-active'), true);
  assert.equal(second.occurrenceSegments[0].classList.contains('is-occurrence-active'), false);
  assert.equal(second.occurrenceSegments[1].classList.contains('is-occurrence-active'), false);
});
```

Extend the existing incomplete-markup test with segment keys and assert initialization does not throw.

- [ ] **Step 3: Run the JavaScript tests and verify RED**

Run:

```bash
node --test tests/test-calendar-view.js
```

Expected: new synchronized-state assertions fail because no occurrence listeners exist.

- [ ] **Step 4: Implement occurrence-group initialization**

Add before `getInstance()` in `assets/js/aa-events-calendar-view.js`:

```js
function initOccurrenceGroups(root) {
  var segments = root.querySelectorAll('[data-aa-events-occurrence]');
  var groups = Object.create(null);

  function updateGroup(group) {
    var active = group.hoverCount > 0 || group.focusCount > 0;

    for (var memberIndex = 0; memberIndex < group.segments.length; memberIndex += 1) {
      group.segments[memberIndex].classList.toggle(
        'is-occurrence-active',
        active
      );
    }
  }

  for (var segmentIndex = 0; segmentIndex < segments.length; segmentIndex += 1) {
    var segment = segments[segmentIndex];
    var key = segment.getAttribute('data-aa-events-occurrence');

    if (!key) {
      continue;
    }

    if (!groups[key]) {
      groups[key] = {
        segments: [],
        hoverCount: 0,
        focusCount: 0,
      };
    }

    groups[key].segments.push(segment);
  }

  Object.keys(groups).forEach(function (key) {
    var group = groups[key];

    group.segments.forEach(function (segment) {
      var hovered = false;
      var focused = false;

      segment.addEventListener('mouseenter', function () {
        if (!hovered) {
          hovered = true;
          group.hoverCount += 1;
          updateGroup(group);
        }
      });

      segment.addEventListener('mouseleave', function () {
        if (hovered) {
          hovered = false;
          group.hoverCount -= 1;
          updateGroup(group);
        }
      });

      segment.addEventListener('focus', function () {
        if (!focused) {
          focused = true;
          group.focusCount += 1;
          updateGroup(group);
        }
      });

      segment.addEventListener('blur', function () {
        if (focused) {
          focused = false;
          group.focusCount -= 1;
          updateGroup(group);
        }
      });
    });
  });
}
```

In the root loop inside `init()`, initialize segment groups before validating view-switcher markup:

```js
initOccurrenceGroups(roots[index]);
```

- [ ] **Step 5: Run JavaScript and PHP suites**

Run:

```bash
node --test tests/test-calendar-view.js
php tests/run.php
```

Expected: all JavaScript tests and the PHP suite pass.

- [ ] **Step 6: Commit controller synchronization**

```bash
git add assets/js/aa-events-calendar-view.js tests/test-calendar-view.js
git commit -m "feat: synchronize calendar segment interaction"
```

## Task 4: Reuse Plugin Hover Styling for Active Groups

**Files:**

- Modify: `tests/test-calendar-css.php`
- Modify: `assets/css/aa-events.css`
- Modify: `templates/aa-events.css`

- [ ] **Step 1: Change the CSS contract first**

In `tests/test-calendar-css.php`, replace the event hover contract with:

```php
'/\.aa-events-calendar-event:hover\s*,\s*\.aa-events-calendar-event\.is-occurrence-active\s*\{(?=[^}]*background\s*:\s*#163d5d)(?=[^}]*text-decoration\s*:\s*underline)[^}]*\}/s' => 'Hovered and synchronized event segments share the established hover appearance.',
```

- [ ] **Step 2: Run the PHP suite and verify RED**

Run:

```bash
php tests/run.php
```

Expected: synchronized selector contract fails.

- [ ] **Step 3: Add the synchronized selector**

In both `assets/css/aa-events.css` and `templates/aa-events.css`, change:

```css
.aa-events-calendar-event:hover {
```

to:

```css
.aa-events-calendar-event:hover,
.aa-events-calendar-event.is-occurrence-active {
```

Do not change the declaration values or the separate `:focus-visible` rule.

- [ ] **Step 4: Verify CSS behavior and byte identity**

Run:

```bash
php tests/run.php
cmp -s assets/css/aa-events.css templates/aa-events.css
```

Expected: PHP suite passes and `cmp` exits zero.

- [ ] **Step 5: Commit plugin styling**

```bash
git add assets/css/aa-events.css templates/aa-events.css tests/test-calendar-css.php
git commit -m "style: highlight continuous event segments"
```

## Task 5: Integrate the MNBC Theme Override

**Files:**

- Modify: `/Users/keith/Local Sites/mnbc/app/public/wp-content/themes/MNBC/aa-events/calendar.php`
- Modify: `/Users/keith/Local Sites/mnbc/app/public/wp-content/themes/MNBC/aa-events/aa-events.css`
- Create: `/Users/keith/Local Sites/mnbc/app/public/wp-content/themes/MNBC/tests/test-aa-events-segment-interaction.php`

- [ ] **Step 1: Work on an isolated theme branch**

Create an MNBC worktree from current `main` without touching the parent checkout’s existing `views/blocks/section/section.php` change. Use branch:

```text
keith/multi-day-segment-interaction
```

- [ ] **Step 2: Write the failing theme contract**

Create `tests/test-aa-events-segment-interaction.php`:

```php
<?php
/**
 * Standalone contract tests for synchronized AA Events segments.
 */

$failures = 0;
$assert   = static function ( $condition, $message ) use ( &$failures ) {
	if ( $condition ) {
		return;
	}

	++$failures;
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Standalone CLI test reports failures.
	fwrite( STDERR, "FAIL: {$message}\n" );
};

$theme_dir = dirname( __DIR__ );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Standalone source contract.
$template = file_get_contents( $theme_dir . '/aa-events/calendar.php' );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Standalone source contract.
$css = file_get_contents( $theme_dir . '/aa-events/aa-events.css' );

$assert( false !== $template, 'Calendar override must be readable.' );
$assert( false !== $css, 'Calendar override stylesheet must be readable.' );
$assert(
	false !== strpos( $template, 'aa_events_calendar_occurrence_key( $event_id, $occurrence_index )' ),
	'Calendar override must assign the canonical occurrence key.'
);
$assert(
	false !== strpos( $template, 'data-aa-events-occurrence="<?php echo esc_attr( $segment[\'calendar_occurrence_key\'] ); ?>"' ),
	'Calendar override must expose the escaped occurrence key on each segment.'
);
$assert(
	1 === preg_match(
		'/\.aa-events-calendar-event:hover\s*,\s*\.aa-events-calendar-event\.is-occurrence-active\s*\{(?=[^}]*background\s*:\s*var\(--color-light-blue\))(?=[^}]*color\s*:\s*var\(--color-dark-blue\))[^}]*\}/s',
		$css
	),
	'MNBC synchronized segments must retain the established theme hover colors.'
);

if ( $failures ) {
	exit( 1 );
}

echo "All AA Events segment interaction theme contracts passed.\n";
```

- [ ] **Step 3: Run the theme contract and verify RED**

Run:

```bash
php tests/test-aa-events-segment-interaction.php
```

Expected: key, data attribute, and active selector assertions fail.

- [ ] **Step 4: Mirror occurrence identity in the theme template**

In `aa-events/calendar.php`, enumerate occurrences with `$occurrence_index`, set:

```php
$occurrence['calendar_occurrence_key'] = aa_events_calendar_occurrence_key( $event_id, $occurrence_index );
```

and add to every desktop segment anchor:

```php
data-aa-events-occurrence="<?php echo esc_attr( $segment['calendar_occurrence_key'] ); ?>"
```

- [ ] **Step 5: Reuse MNBC’s hover appearance**

In `aa-events/aa-events.css`, change:

```css
.aa-events-calendar-event:hover {
```

to:

```css
.aa-events-calendar-event:hover,
.aa-events-calendar-event.is-occurrence-active {
```

Keep MNBC’s existing light-blue background and dark-blue text.

- [ ] **Step 6: Run theme verification**

Run:

```bash
php tests/test-aa-events-segment-interaction.php
composer lint
git diff --check
```

Expected: the segment contract passes, PHPCS completes with zero violations, and no whitespace errors are reported.

- [ ] **Step 7: Commit the theme integration**

```bash
git add aa-events/calendar.php aa-events/aa-events.css tests/test-aa-events-segment-interaction.php
git commit -m "feat: synchronize multi-day event segments"
```

## Task 6: Full Verification

**Files:**

- Verify all plugin and theme changes.

- [ ] **Step 1: Run plugin behavior suites**

```bash
php tests/run.php
node --test tests/test-calendar-view.js
```

Expected: all PHP and JavaScript tests pass.

- [ ] **Step 2: Run plugin syntax and focused coding standards**

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpcs --standard=.phpcs.xml aa-events.php includes/calendar-layout.php templates/calendar.php tests/test-calendar-layout.php tests/test-calendar-render.php tests/test-admin-consumers.php
```

If the plugin repository has no `vendor/bin/phpcs`, use the MNBC worktree’s installed `vendor/bin/phpcs` with the plugin `.phpcs.xml`.

Expected: syntax passes for every plugin PHP file and changed feature files have no PHPCS violations.

- [ ] **Step 3: Verify plugin source boundaries**

```bash
cmp -s assets/css/aa-events.css templates/aa-events.css
rg -n "1\\.2\\.[0-9]+|calendar_occurrence_key|data-aa-events-occurrence|is-occurrence-active" aa-events.php includes templates assets tests
git diff --check main..HEAD
git status --short --branch
```

Expected: CSS copies are identical; release references are `1.2.7`; occurrence interaction appears only in intended calendar consumers; the feature branch is clean.

- [ ] **Step 4: Run theme verification**

From the MNBC feature worktree:

```bash
php tests/test-aa-events-segment-interaction.php
composer lint
git diff --check main..HEAD
git status --short --branch
```

Expected: contracts and lint pass, whitespace is clean, and only intended commits exist.

- [ ] **Step 5: Review deployment order**

Confirm the plugin is deployed before or atomically with the theme because the theme template calls `aa_events_calendar_occurrence_key()`. Confirm WordPress caches receive plugin version `1.2.7`, and note that the existing occurrence-index backfill will run in bounded batches after the version change.
