# Desktop Calendar View Toggle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an accessible desktop Calendar/List switcher that remembers the visitor's preference while keeping mobile permanently in List view.

**Architecture:** Keep the existing grid and agenda rendered from one occurrence collection. Add semantic template hooks and a dependency-free browser controller that applies one `localStorage` preference to every calendar instance, while CSS preserves the current no-JavaScript responsive fallback.

**Tech Stack:** WordPress/PHP templates and enqueue APIs, CSS, browser JavaScript, Node's built-in test runner, and the plugin's custom PHP test harness.

---

## File Map

- Modify `templates/calendar.php`: render the switcher and stable hooks for each view.
- Modify `assets/css/aa-events.css`: style the utility row, switcher, desktop List state, shared agenda cards, and forced mobile state.
- Modify `templates/aa-events.css`: remain byte-identical to the frontend stylesheet.
- Create `assets/js/aa-events-calendar-view.js`: own preference persistence, multi-instance synchronization, accessible state, and breakpoint changes.
- Create `tests/test-calendar-view.js`: exercise the browser controller with dependency-free DOM/storage/media-query doubles.
- Modify `tests/test-calendar-render.php`: verify rendered switcher semantics and instance-safe hooks.
- Modify `tests/test-calendar-css.php`: verify view-switcher and responsive contracts.
- Modify `tests/bootstrap.php`: capture frontend asset enqueues and align the test version constant with the plugin header.
- Create `tests/test-frontend-assets.php`: verify the frontend controller is enqueued.
- Modify `tests/run.php`: include the frontend asset test.
- Modify `includes/class-aa-events.php`: enqueue the new frontend controller.
- Modify `aa-events.php`: align the asset version constant with the existing `1.2.1` plugin header.
- Modify `tests/test-admin-consumers.php`: align its expected release version with `1.2.1`.
- Modify `README.md`: document the switcher, persistence, mobile behavior, and override requirements.

The current workspace is not a Git worktree. Each commit step below is retained for use when the plugin is placed in Git, but must be reported as skipped in this workspace rather than treated as a test failure.

### Task 1: Render the Accessible View Switcher

**Files:**
- Modify: `tests/test-calendar-render.php`
- Modify: `templates/calendar.php`

- [ ] **Step 1: Add failing rendered-markup assertions**

After `$xpath` is created in `tests/test-calendar-render.php`, add:

```php
$calendar_instance = $xpath->query( '//*[@data-aa-events-calendar]' )->item( 0 );
$switcher          = $xpath->query( '//*[@data-aa-events-view-switcher and @role="group"]' )->item( 0 );
$view_buttons      = $xpath->query( '//*[@data-aa-events-view-switcher]//button[@data-aa-events-view-button]' );
$calendar_panel    = $xpath->query( '//*[@data-aa-events-view-panel="calendar"]' )->item( 0 );
$list_panel        = $xpath->query( '//*[@data-aa-events-view-panel="list"]' )->item( 0 );

aa_events_assert_true( $calendar_instance instanceof DOMElement, 'Rendered calendar exposes an instance hook.' );
aa_events_assert_true( $switcher instanceof DOMElement, 'Rendered calendar exposes a grouped view switcher.' );
aa_events_assert_same( 'Calendar view', $switcher instanceof DOMElement ? $switcher->getAttribute( 'aria-label' ) : null, 'View switcher has an accessible name.' );
aa_events_assert_same( 2, $view_buttons->length, 'View switcher contains exactly two buttons.' );
aa_events_assert_same( 'calendar', $view_buttons->item( 0 ) instanceof DOMElement ? $view_buttons->item( 0 )->getAttribute( 'data-aa-events-view-button' ) : null, 'Calendar control is first.' );
aa_events_assert_same( 'true', $view_buttons->item( 0 ) instanceof DOMElement ? $view_buttons->item( 0 )->getAttribute( 'aria-pressed' ) : null, 'Calendar is the initial desktop selection.' );
aa_events_assert_same( 'list', $view_buttons->item( 1 ) instanceof DOMElement ? $view_buttons->item( 1 )->getAttribute( 'data-aa-events-view-button' ) : null, 'List control is second.' );
aa_events_assert_same( 'false', $view_buttons->item( 1 ) instanceof DOMElement ? $view_buttons->item( 1 )->getAttribute( 'aria-pressed' ) : null, 'List is initially unselected.' );
aa_events_assert_true( $calendar_panel instanceof DOMElement, 'Calendar grid exposes its view-panel hook.' );
aa_events_assert_true( $list_panel instanceof DOMElement, 'Agenda exposes its view-panel hook.' );
```

After `$multi_xpath` is created, add:

```php
aa_events_assert_same( 2, $multi_xpath->query( '//*[@data-aa-events-calendar]' )->length, 'Two calendars expose two independent instance hooks.' );
aa_events_assert_same( 4, $multi_xpath->query( '//*[@data-aa-events-view-button]' )->length, 'Two calendars each render both view controls.' );
aa_events_assert_same( 2, $multi_xpath->query( '//*[@data-aa-events-view-panel="calendar"]' )->length, 'Two calendars each expose a grid panel.' );
aa_events_assert_same( 2, $multi_xpath->query( '//*[@data-aa-events-view-panel="list"]' )->length, 'Two calendars each expose a list panel.' );
```

- [ ] **Step 2: Run the PHP suite and verify the new assertions fail**

Run:

```bash
php tests/run.php
```

Expected: the new instance/switcher assertions fail. The two already-known version-consistency assertions also fail until Task 4.

- [ ] **Step 3: Add the switcher and view hooks**

In `templates/calendar.php`, change the outer and utility markup to:

```php
<div class="aa-events-calendar-wrap container" data-aa-events-calendar>
	<div class="aa-events-calendar-utility">
		<div class="aa-events-calendar-today-link">
			<a href="<?php echo esc_url( $calendar_url( $today ) ); ?>" class="aa-events-calendar-today-btn"><?php esc_html_e( 'Today', 'aa-events' ); ?></a>
		</div>
		<div class="aa-events-calendar-view-switcher" data-aa-events-view-switcher role="group" aria-label="<?php echo esc_attr( __( 'Calendar view', 'aa-events' ) ); ?>">
			<button type="button" class="aa-events-calendar-view-button" data-aa-events-view-button="calendar" aria-pressed="true"><?php esc_html_e( 'Calendar', 'aa-events' ); ?></button>
			<button type="button" class="aa-events-calendar-view-button" data-aa-events-view-button="list" aria-pressed="false"><?php esc_html_e( 'List', 'aa-events' ); ?></button>
		</div>
	</div>
```

Add the panel attributes to the existing view containers:

```php
<div class="aa-events-calendar-desktop" data-aa-events-view-panel="calendar">
```

```php
<section class="aa-events-calendar-agenda" data-aa-events-view-panel="list" aria-labelledby="<?php echo esc_attr( $agenda_heading_id ); ?>">
```

- [ ] **Step 4: Run the rendered tests**

Run:

```bash
php tests/run.php
```

Expected: all calendar rendering assertions pass; only the two pre-existing version assertions remain failing.

- [ ] **Step 5: Commit the template slice when Git is available**

```bash
git add templates/calendar.php tests/test-calendar-render.php
git commit -m "feat: render calendar view switcher"
```

In the current non-Git workspace, verify `git rev-parse --show-toplevel` reports no worktree and record this commit as skipped.

### Task 2: Style Both Desktop Views and Preserve the Mobile Fallback

**Files:**
- Modify: `tests/test-calendar-css.php`
- Modify: `assets/css/aa-events.css`
- Modify: `templates/aa-events.css`

- [ ] **Step 1: Add failing CSS contracts**

Add these entries to `$calendar_css_contracts` in `tests/test-calendar-css.php`:

```php
'/\.aa-events-calendar-utility\s*\{[^}]*(?=.*?align-items\s*:\s*center)(?=.*?display\s*:\s*flex)(?=.*?flex-wrap\s*:\s*wrap)(?=.*?justify-content\s*:\s*space-between)[^}]*\}/s' => 'Today and the view switcher share a wrapping utility row.',
'/\.aa-events-calendar-view-switcher\s*\{[^}]*(?=.*?display\s*:\s*inline-flex)[^}]*\}/s' => 'View buttons render as one segmented control.',
'/\.aa-events-calendar-view-button\s*\{[^}]*(?=.*?border\s*:)(?=.*?cursor\s*:\s*pointer)(?=.*?padding\s*:)[^}]*\}/s' => 'View buttons have a usable control surface.',
'/\.aa-events-calendar-view-button\[aria-pressed="true"\]\s*\{[^}]*(?=.*?background\s*:)(?=.*?color\s*:)(?=.*?font-weight\s*:)[^}]*\}/s' => 'The selected view has a visible state.',
'/\.aa-events-calendar-view-button:focus-visible\s*\{[^}]*(?=.*?outline\s*:)(?=.*?outline-offset\s*:)[^}]*\}/s' => 'View buttons have visible keyboard focus.',
'/\.aa-events-calendar-wrap\.is-list-view\s+\.aa-events-calendar-desktop\s*\{[^}]*display\s*:\s*none[^}]*\}/s' => 'Desktop List state hides the grid.',
'/\.aa-events-calendar-wrap\.is-list-view\s+\.aa-events-calendar-agenda\s*\{[^}]*display\s*:\s*block[^}]*\}/s' => 'Desktop List state exposes the agenda.',
'/\.aa-events-calendar-wrap\s+\[hidden\]\s*\{[^}]*display\s*:\s*none\s*!important[^}]*\}/s' => 'Inactive views are removed visually when hidden.',
'/@media\s*\(max-width\s*:\s*640px\)[\s\S]*?\.aa-events-calendar-view-switcher\s*\{[^}]*display\s*:\s*none[\s\S]*?\.aa-events-calendar-desktop\s*\{[^}]*display\s*:\s*none[\s\S]*?\.aa-events-calendar-agenda\s*\{[^}]*display\s*:\s*block/s' => 'Mobile hides the switcher and always presents the agenda.',
```

Keep the existing agenda-list/card/focus contracts; they will guard moving those rules outside the media query.

- [ ] **Step 2: Run the CSS contract test and verify failure**

Run:

```bash
php tests/run.php
```

Expected: the new utility, switcher, state, and hidden-selector contracts fail.

- [ ] **Step 3: Implement the frontend styles**

In `assets/css/aa-events.css`, add before `.aa-events-calendar-header`:

```css
.aa-events-calendar-utility {
	align-items: center;
	display: flex;
	flex-wrap: wrap;
	gap: 0.625rem;
	justify-content: space-between;
	margin-bottom: 0.625rem;
}

.aa-events-calendar-view-switcher {
	display: inline-flex;
}

.aa-events-calendar-view-button {
	background: #fff;
	border: 1px solid #767676;
	color: #222;
	cursor: pointer;
	font: inherit;
	margin: 0;
	padding: 0.5rem 0.75rem;
}

.aa-events-calendar-view-button + .aa-events-calendar-view-button {
	border-left: 0;
}

.aa-events-calendar-view-button:first-child {
	border-radius: 0.25rem 0 0 0.25rem;
}

.aa-events-calendar-view-button:last-child {
	border-radius: 0 0.25rem 0.25rem 0;
}

.aa-events-calendar-view-button[aria-pressed="true"] {
	background: #245b87;
	color: #fff;
	font-weight: bold;
}

.aa-events-calendar-view-button:hover {
	text-decoration: underline;
}

.aa-events-calendar-view-button:focus-visible {
	outline: 3px solid currentColor;
	outline-offset: 2px;
	position: relative;
	z-index: 1;
}

.aa-events-calendar-wrap [hidden] {
	display: none !important;
}
```

After the base `.aa-events-calendar-agenda { display: none; }` rule, add:

```css
.aa-events-calendar-wrap.is-list-view .aa-events-calendar-desktop {
	display: none;
}

.aa-events-calendar-wrap.is-list-view .aa-events-calendar-agenda {
	display: block;
}
```

Move these existing agenda rules out of `@media (max-width: 640px)` so the desktop List view receives the same presentation:

```css
.aa-events-calendar-agenda-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.aa-events-agenda-item + .aa-events-agenda-item { margin-top: 0.75rem; }

.aa-events-agenda-item > a {
	border: 1px solid #aaa;
	border-left: 0.25rem solid #245b87;
	border-radius: 0.25rem;
	display: block;
	min-width: 0;
	overflow-wrap: anywhere;
	padding: 0.75rem;
	text-decoration: underline;
}

.aa-events-agenda-item > a:hover { background: #f5f5f5; }

.aa-events-agenda-item > a:focus-visible {
	outline: 3px solid currentColor;
	outline-offset: 2px;
}

.aa-events-agenda-title,
.aa-events-agenda-item time {
	display: block;
	overflow-wrap: anywhere;
}

.aa-events-agenda-title { font-weight: bold; }

.aa-events-agenda-item .screen-reader-text {
	border: 0;
	clip: rect(1px, 1px, 1px, 1px);
	clip-path: inset(50%);
	height: 1px;
	margin: -1px;
	overflow: hidden;
	padding: 0;
	position: absolute;
	width: 1px;
	word-wrap: normal !important;
}
```

At the start of the existing 640-pixel media query, add:

```css
.aa-events-calendar-view-switcher { display: none; }
```

Keep the existing mobile grid/agenda rules in their current order.

- [ ] **Step 4: Synchronize the bundled CSS copy**

Run:

```bash
cp assets/css/aa-events.css templates/aa-events.css
```

Expected: `cmp -s assets/css/aa-events.css templates/aa-events.css` exits with status 0.

- [ ] **Step 5: Run the PHP suite**

Run:

```bash
php tests/run.php
```

Expected: all calendar rendering and CSS assertions pass; only the two pre-existing version assertions remain failing.

- [ ] **Step 6: Commit the styling slice when Git is available**

```bash
git add assets/css/aa-events.css templates/aa-events.css tests/test-calendar-css.php
git commit -m "style: support desktop calendar list view"
```

In the current non-Git workspace, record the commit as skipped.

### Task 3: Implement and Test the Persistent View Controller

**Files:**
- Create: `tests/test-calendar-view.js`
- Create: `assets/js/aa-events-calendar-view.js`

- [ ] **Step 1: Create the failing browser-controller test**

Create `tests/test-calendar-view.js`:

```js
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { init } = require( '../assets/js/aa-events-calendar-view.js' );

class FakeClassList {
	constructor() {
		this.values = new Set();
	}
	add( value ) {
		this.values.add( value );
	}
	remove( value ) {
		this.values.delete( value );
	}
	toggle( value, force ) {
		force ? this.add( value ) : this.remove( value );
	}
	contains( value ) {
		return this.values.has( value );
	}
}

class FakeElement {
	constructor( attributes = {} ) {
		this.attributes = { ...attributes };
		this.classList = new FakeClassList();
		this.hidden = false;
		this.listeners = {};
	}
	getAttribute( name ) {
		return this.attributes[ name ] ?? null;
	}
	setAttribute( name, value ) {
		this.attributes[ name ] = String( value );
	}
	addEventListener( type, callback ) {
		this.listeners[ type ] = callback;
	}
	click() {
		this.listeners.click();
	}
}

function makeCalendar() {
	const calendarButton = new FakeElement( { 'data-aa-events-view-button': 'calendar', 'aria-pressed': 'true' } );
	const listButton = new FakeElement( { 'data-aa-events-view-button': 'list', 'aria-pressed': 'false' } );
	const calendarPanel = new FakeElement();
	const listPanel = new FakeElement();
	const root = new FakeElement();

	root.querySelectorAll = ( selector ) => selector === '[data-aa-events-view-button]' ? [ calendarButton, listButton ] : [];
	root.querySelector = ( selector ) => {
		if ( selector === '[data-aa-events-view-panel="calendar"]' ) {
			return calendarPanel;
		}
		if ( selector === '[data-aa-events-view-panel="list"]' ) {
			return listPanel;
		}
		return null;
	};

	return { root, calendarButton, listButton, calendarPanel, listPanel };
}

function makeDocument( calendars ) {
	return {
		querySelectorAll( selector ) {
			return selector === '[data-aa-events-calendar]' ? calendars.map( ( calendar ) => calendar.root ) : [];
		},
	};
}

function makeMediaQuery( matches = false ) {
	return {
		matches,
		listener: null,
		addEventListener( type, callback ) {
			if ( type === 'change' ) {
				this.listener = callback;
			}
		},
		change( matchesMobile ) {
			this.matches = matchesMobile;
			this.listener( { matches: matchesMobile } );
		},
	};
}

function makeStorage( initialValue = null ) {
	return {
		value: initialValue,
		getItem() {
			return this.value;
		},
		setItem( key, value ) {
			assert.equal( key, 'aa-events-calendar-view' );
			this.value = value;
		},
	};
}

test( 'defaults to Calendar and synchronizes List across instances', () => {
	const calendars = [ makeCalendar(), makeCalendar() ];
	const storage = makeStorage();
	const mediaQuery = makeMediaQuery();

	init( makeDocument( calendars ), storage, mediaQuery );
	assert.equal( calendars[ 0 ].calendarPanel.hidden, false );
	assert.equal( calendars[ 0 ].listPanel.hidden, true );

	calendars[ 0 ].listButton.click();
	for ( const calendar of calendars ) {
		assert.equal( calendar.root.classList.contains( 'is-list-view' ), true );
		assert.equal( calendar.calendarPanel.hidden, true );
		assert.equal( calendar.listPanel.hidden, false );
		assert.equal( calendar.calendarButton.getAttribute( 'aria-pressed' ), 'false' );
		assert.equal( calendar.listButton.getAttribute( 'aria-pressed' ), 'true' );
	}
	assert.equal( storage.value, 'list' );
} );

test( 'restores a valid preference and rejects an invalid value', () => {
	const savedList = makeCalendar();
	init( makeDocument( [ savedList ] ), makeStorage( 'list' ), makeMediaQuery() );
	assert.equal( savedList.listPanel.hidden, false );

	const invalid = makeCalendar();
	init( makeDocument( [ invalid ] ), makeStorage( 'tiles' ), makeMediaQuery() );
	assert.equal( invalid.calendarPanel.hidden, false );
	assert.equal( invalid.listPanel.hidden, true );
} );

test( 'forces List on mobile without overwriting the saved desktop preference', () => {
	const calendar = makeCalendar();
	const storage = makeStorage( 'calendar' );
	const mediaQuery = makeMediaQuery();
	init( makeDocument( [ calendar ] ), storage, mediaQuery );

	mediaQuery.change( true );
	assert.equal( calendar.calendarPanel.hidden, true );
	assert.equal( calendar.listPanel.hidden, false );
	assert.equal( storage.value, 'calendar' );

	mediaQuery.change( false );
	assert.equal( calendar.calendarPanel.hidden, false );
	assert.equal( calendar.listPanel.hidden, true );
} );

test( 'switches the current page when storage access fails', () => {
	const calendar = makeCalendar();
	const brokenStorage = {
		getItem() {
			throw new Error( 'blocked' );
		},
		setItem() {
			throw new Error( 'blocked' );
		},
	};

	assert.doesNotThrow( () => init( makeDocument( [ calendar ] ), brokenStorage, makeMediaQuery() ) );
	calendar.listButton.click();
	assert.equal( calendar.listPanel.hidden, false );
	assert.equal( calendar.listButton.getAttribute( 'aria-pressed' ), 'true' );
} );

test( 'does nothing on pages without calendars and supports legacy media listeners', () => {
	const mediaQuery = {
		matches: false,
		listener: null,
		addListener( callback ) {
			this.listener = callback;
		},
	};
	assert.doesNotThrow( () => init( makeDocument( [] ), makeStorage(), mediaQuery ) );

	const calendar = makeCalendar();
	init( makeDocument( [ calendar ] ), makeStorage(), mediaQuery );
	mediaQuery.matches = true;
	mediaQuery.listener( { matches: true } );
	assert.equal( calendar.listPanel.hidden, false );
} );
```

- [ ] **Step 2: Run the Node test and verify module failure**

Run:

```bash
node --test tests/test-calendar-view.js
```

Expected: FAIL because `assets/js/aa-events-calendar-view.js` does not exist.

- [ ] **Step 3: Implement the controller**

Create `assets/js/aa-events-calendar-view.js`:

```js
( function ( globalObject, factory ) {
	'use strict';

	const api = factory();

	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	}

	if ( ! globalObject || ! globalObject.document ) {
		return;
	}

	const start = function () {
		let storage = null;

		try {
			storage = globalObject.localStorage;
		} catch ( error ) {
			storage = null;
		}

		api.init(
			globalObject.document,
			storage,
			globalObject.matchMedia( '(max-width: 640px)' )
		);
	};

	if ( 'loading' === globalObject.document.readyState ) {
		globalObject.document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}( typeof window === 'undefined' ? null : window, function () {
	'use strict';

	const storageKey = 'aa-events-calendar-view';
	const validViews = [ 'calendar', 'list' ];

	const readPreference = function ( storage ) {
		try {
			const storedView = storage && storage.getItem( storageKey );
			return validViews.includes( storedView ) ? storedView : 'calendar';
		} catch ( error ) {
			return 'calendar';
		}
	};

	const writePreference = function ( storage, view ) {
		try {
			if ( storage ) {
				storage.setItem( storageKey, view );
			}
		} catch ( error ) {
			// Persistence is optional; current-page switching still works.
		}
	};

	const init = function ( documentObject, storage, mediaQuery ) {
		const calendars = Array.from( documentObject.querySelectorAll( '[data-aa-events-calendar]' ) );

		if ( ! calendars.length ) {
			return;
		}

		let preference = readPreference( storage );

		const applyView = function () {
			const isMobile = Boolean( mediaQuery && mediaQuery.matches );

			calendars.forEach( function ( calendar ) {
				const buttons = Array.from( calendar.querySelectorAll( '[data-aa-events-view-button]' ) );
				const calendarPanel = calendar.querySelector( '[data-aa-events-view-panel="calendar"]' );
				const listPanel = calendar.querySelector( '[data-aa-events-view-panel="list"]' );

				if ( ! calendarPanel || ! listPanel ) {
					return;
				}

				buttons.forEach( function ( button ) {
					const isSelected = button.getAttribute( 'data-aa-events-view-button' ) === preference;
					button.setAttribute( 'aria-pressed', isSelected ? 'true' : 'false' );
				} );

				if ( isMobile ) {
					calendar.classList.remove( 'is-list-view' );
					calendarPanel.hidden = true;
					listPanel.hidden = false;
					return;
				}

				const showList = 'list' === preference;
				calendar.classList.toggle( 'is-list-view', showList );
				calendarPanel.hidden = showList;
				listPanel.hidden = ! showList;
			} );
		};

		const selectView = function ( view ) {
			if ( ! validViews.includes( view ) ) {
				return;
			}

			preference = view;
			writePreference( storage, preference );
			applyView();
		};

		calendars.forEach( function ( calendar ) {
			Array.from( calendar.querySelectorAll( '[data-aa-events-view-button]' ) ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					selectView( button.getAttribute( 'data-aa-events-view-button' ) );
				} );
			} );
		} );

		if ( mediaQuery ) {
			if ( 'function' === typeof mediaQuery.addEventListener ) {
				mediaQuery.addEventListener( 'change', applyView );
			} else if ( 'function' === typeof mediaQuery.addListener ) {
				mediaQuery.addListener( applyView );
			}
		}

		applyView();
	};

	return { init };
} ) );
```

- [ ] **Step 4: Run controller tests**

Run:

```bash
node --test tests/test-calendar-view.js
```

Expected: 9 tests pass.

- [ ] **Step 5: Run a JavaScript syntax check**

Run:

```bash
node --check assets/js/aa-events-calendar-view.js
```

Expected: exit status 0 with no output.

- [ ] **Step 6: Commit the controller slice when Git is available**

```bash
git add assets/js/aa-events-calendar-view.js tests/test-calendar-view.js
git commit -m "feat: persist calendar view preference"
```

In the current non-Git workspace, record the commit as skipped.

### Task 4: Enqueue the Controller and Restore Version Consistency

**Files:**
- Modify: `tests/bootstrap.php`
- Create: `tests/test-frontend-assets.php`
- Modify: `tests/run.php`
- Modify: `includes/class-aa-events.php`
- Modify: `aa-events.php`
- Modify: `tests/test-admin-consumers.php`

- [ ] **Step 1: Add enqueue test doubles**

In `tests/bootstrap.php`, change the test version and add capture globals near the top:

```php
define( 'AA_EVENTS_VERSION', '1.2.1' );

$GLOBALS['aa_events_test_styles']  = array();
$GLOBALS['aa_events_test_scripts'] = array();
```

Add these functions before the assertion helpers:

```php
function get_stylesheet_directory() {
	return __DIR__ . '/fixtures/theme-without-aa-events-override';
}

function get_stylesheet_directory_uri() {
	return 'https://example.test/theme';
}

function wp_enqueue_style( $handle, $src, $dependencies = array(), $version = false ) {
	$GLOBALS['aa_events_test_styles'][ $handle ] = array( $src, $dependencies, $version );
}

function wp_enqueue_script( $handle, $src, $dependencies = array(), $version = false, $in_footer = false ) {
	$GLOBALS['aa_events_test_scripts'][ $handle ] = array( $src, $dependencies, $version, $in_footer );
}
```

- [ ] **Step 2: Create the failing frontend enqueue test**

Create `tests/test-frontend-assets.php`:

```php
<?php

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'tests' );
}
if ( ! defined( 'AA_EVENTS_PLUGIN_DIR' ) ) {
	define( 'AA_EVENTS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'AA_EVENTS_PLUGIN_URL' ) ) {
	define( 'AA_EVENTS_PLUGIN_URL', 'https://example.test/plugins/aa-events/' );
}

require_once __DIR__ . '/../includes/class-aa-events.php';

$GLOBALS['aa_events_test_styles']  = array();
$GLOBALS['aa_events_test_scripts'] = array();

AA_Events::instance()->enqueue_styles();

aa_events_assert_same(
	array(
		'https://example.test/plugins/aa-events/assets/js/aa-events-calendar-view.js',
		array(),
		AA_EVENTS_VERSION,
		true,
	),
	$GLOBALS['aa_events_test_scripts']['aa-events-calendar-view'] ?? null,
	'Frontend assets enqueue the dependency-free calendar view controller in the footer.'
);
```

Add this line to `tests/run.php` after `test-calendar-css.php`:

```php
require_once __DIR__ . '/test-frontend-assets.php';
```

- [ ] **Step 3: Run the PHP suite and verify the enqueue assertion fails**

Run:

```bash
php tests/run.php
```

Expected: FAIL because `aa-events-calendar-view` has not been enqueued. The two known version-consistency assertions also remain failing until Step 5.

- [ ] **Step 4: Enqueue the browser controller**

At the end of `AA_Events::enqueue_styles()` in `includes/class-aa-events.php`, add:

```php
wp_enqueue_script(
	'aa-events-calendar-view',
	AA_EVENTS_PLUGIN_URL . 'assets/js/aa-events-calendar-view.js',
	array(),
	AA_EVENTS_VERSION,
	true
);
```

Update the method docblock summary from `Enqueue styles.` to:

```php
/**
 * Enqueue frontend assets.
 */
```

- [ ] **Step 5: Align the existing release version**

In `aa-events.php`, change:

```php
define( 'AA_EVENTS_VERSION', '1.2.1' );
```

In `tests/test-admin-consumers.php`, change the hard-coded release assertion to:

```php
aa_events_assert_same( '1.2.1', $header_match[1] ?? '', 'Plugin header uses the feature release version.' );
```

This resolves the baseline mismatch where the existing plugin header is already `1.2.1` but the asset constant and test fixtures are still `1.2.0`.

- [ ] **Step 6: Run the PHP suite**

Run:

```bash
php tests/run.php
```

Expected: `All AA Events tests passed.`

- [ ] **Step 7: Commit the integration slice when Git is available**

```bash
git add tests/bootstrap.php tests/test-frontend-assets.php tests/run.php includes/class-aa-events.php aa-events.php tests/test-admin-consumers.php
git commit -m "feat: load calendar view controller"
```

In the current non-Git workspace, record the commit as skipped.

### Task 5: Document and Verify the Feature

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update frontend-view documentation**

Replace the desktop/mobile calendar bullets in `README.md` with:

```markdown
- On desktop and tablet, visitors can switch between Calendar and List. Calendar is the initial default; the selected view is saved in that browser and restored after month navigation or a later visit.
- Calendar view uses a traditional month grid. Multi-day occurrences render as bars spanning each week they cross, with the event title repeated on each weekly segment.
- At `640px` wide and below, the switcher is hidden and the calendar always uses the chronological List view, regardless of the saved desktop preference.
```

Replace the final theme-override paragraph with:

```markdown
An existing `your-theme/aa-events/calendar.php` remains authoritative. It does not automatically acquire the plugin template’s week-spanning bars, List markup, or desktop view switcher. Theme authors can update overrides using `aa_events_get_occurrences()`, `aa_events_format_occurrence()`, and `aa_events_occurrence_time_markup()`. A CSS override must also account for any calendar, agenda, and switcher markup it adopts.
```

- [ ] **Step 2: Run all automated verification**

Run:

```bash
php tests/run.php
node --test tests/test-calendar-view.js
php -l templates/calendar.php
php -l includes/class-aa-events.php
node --check assets/js/aa-events-calendar-view.js
cmp -s assets/css/aa-events.css templates/aa-events.css
```

Expected:

- PHP suite prints `All AA Events tests passed.`
- Node reports 9 passing tests and 0 failures.
- Both PHP syntax checks report no syntax errors.
- JavaScript syntax check exits 0.
- CSS comparison exits 0.

- [ ] **Step 3: Perform focused source checks**

Run:

```bash
rg -n "data-aa-events-(calendar|view-switcher|view-button|view-panel)" templates/calendar.php
rg -n "aa-events-calendar-view|localStorage|matchMedia" assets/js/aa-events-calendar-view.js includes/class-aa-events.php
rg -n "switch|saved|640px|override" README.md
```

Expected: template hooks, persistence/breakpoint code, enqueue path, and documentation are all present.

- [ ] **Step 4: Manually verify in WordPress**

On a page using the Events Calendar template:

1. At a width above 640 pixels, confirm Calendar is initially selected.
2. Use the keyboard to select List and confirm focus remains visible.
3. Navigate to the next month and reload; confirm List remains selected.
4. Return to Calendar and confirm that preference persists.
5. Resize to exactly 640 pixels; confirm the switcher and grid are hidden and List is visible.
6. Resize above 640 pixels; confirm the saved desktop choice returns.
7. Render two calendars on one page if the host theme permits it; selecting either switcher must update both.
8. Disable site storage or make it unavailable; confirm switching still works for the current page without console errors.

- [ ] **Step 5: Commit documentation when Git is available**

```bash
git add README.md
git commit -m "docs: explain persistent calendar views"
```

In the current non-Git workspace, record the commit as skipped.
