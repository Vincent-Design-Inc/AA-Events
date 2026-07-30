# Desktop Calendar View Toggle Design

## Goal

Let visitors switch between the traditional month grid and the existing chronological agenda on desktop and tablet. Remember the visitor's selection across month navigation and later visits while keeping phone-sized screens permanently in agenda view.

## Scope

This change applies to the plugin's bundled calendar template. It reuses the grid and agenda markup already rendered from the same normalized occurrence data.

The feature includes:

- A desktop "Calendar / List" view switcher.
- Browser-local preference persistence.
- Accessible view state and keyboard operation.
- Responsive behavior that preserves the existing mobile agenda.
- Automated coverage and README updates.

The feature does not add a new event query, server-side user preference, URL parameter, WordPress setting, or admin control.

## User Interface

The switcher sits in the existing utility row opposite the Today button. It contains two text buttons:

- Calendar
- List

The active button is visually distinct and exposes `aria-pressed="true"`; the inactive button exposes `aria-pressed="false"`. Both controls have visible keyboard focus styles. The utility row can wrap when space is constrained.

Above 640 pixels, the selected view is visible and the other view is hidden. At 640 pixels and below, the switcher is hidden, the grid is hidden, and the list is always visible regardless of the saved desktop preference.

## Rendering and State

`templates/calendar.php` continues to render both views from `$calendar_occurrences`. The template adds:

- A switcher associated with its containing calendar instance.
- Stable data attributes that identify the Calendar and List controls.
- Stable data attributes on the grid and agenda view containers.
- Initial accessible button state matching the no-JavaScript desktop default.

A small dependency-free frontend script finds every bundled calendar instance on the page. It reads one plugin-wide preference from `localStorage`, so a visitor's selection applies consistently to every AA Events calendar.

The stored values are limited to `calendar` and `list`. A missing or invalid value defaults to `calendar`. Selecting a control updates all instances on the page, adjusts each button's `aria-pressed` state, hides the inactive view with the `hidden` attribute, and stores the new preference.

Month navigation remains unchanged. Because the preference is browser-local rather than encoded in the URL, existing Previous Month, Next Month, and Today links retain their current URLs and the saved preference is reapplied after navigation.

## Responsive Behavior

CSS remains the authoritative mobile fallback:

- At 640 pixels and below, the desktop grid and switcher are hidden and the agenda is shown.
- Above 640 pixels, JavaScript applies the saved desktop preference.

The script listens for changes to the 640-pixel media query. Crossing into mobile ensures the agenda is exposed even when Calendar is saved. Crossing back to desktop reapplies the saved selection. Resizing does not overwrite the visitor's preference.

## Progressive Enhancement and Failure Handling

Without JavaScript, behavior remains unchanged: desktop and tablet show the month grid, while phone-sized screens show the agenda.

Access to `localStorage` can fail in restricted browsing contexts. Reads and writes are wrapped in error handling. If reading fails, Calendar is the initial desktop view. The switcher still changes the current page's view when saving fails.

The frontend script is safe to enqueue on pages without a bundled calendar and exits without side effects when no matching instance exists. A theme-provided `aa-events/calendar.php` remains authoritative and does not automatically gain the switcher unless it adopts the new markup contract.

## Assets and Compatibility

Add a dedicated frontend calendar script and enqueue it without external dependencies. Keep `assets/css/aa-events.css` and the bundled `templates/aa-events.css` copy synchronized.

The new markup and script must support multiple calendar instances without duplicate IDs or state divergence. Existing calendar query, occurrence sorting, grid layout, month navigation, and mobile agenda content are unchanged.

## Testing

Automated tests will verify:

- The rendered switcher contains Calendar and List buttons with correct initial accessible state.
- Calendar instances and their two views expose the expected script hooks without duplicate IDs.
- CSS places and styles the utility-row switcher, provides selected and focus states, and preserves the 640-pixel forced-list behavior.
- The frontend script defaults to Calendar for missing or invalid stored values.
- Selecting either control updates every rendered calendar, view visibility, and `aria-pressed` state.
- The preference survives script initialization and is not overwritten by the forced mobile layout.
- Storage read and write failures do not prevent current-page switching.
- Pages without calendar markup are unaffected.

Run the existing PHP test suite, PHP syntax checks for changed PHP files, and the JavaScript tests introduced for the view controller. Manual verification should cover keyboard use, month navigation, page reload, multiple calendars, resizing immediately above and below 640 pixels, and a browser context where storage is unavailable.

## Documentation

Update the README to state that desktop and tablet visitors can switch between Calendar and List, that the selection persists in that browser, and that screens at 640 pixels and below always use List.

Document that theme template and stylesheet overrides remain authoritative and need to adopt the new markup and styles to receive the switcher.
