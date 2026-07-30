# Event Contact Email Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional event contact email field and display valid values as protected email links on single-event pages.

**Architecture:** Register `event_contact_email` in the built-in ACF group and read the same field name from built-in or admin-created groups. Keep output in the single-event template, sanitizing before conditional rendering and applying WordPress escaping at the attribute and visible-HTML boundaries.

**Tech Stack:** PHP 7.4+, WordPress template APIs, ACF PRO, existing dependency-free PHP tests

---

### Task 1: Register the Contact Email Field

**Files:**
- Modify: `includes/acf-fields.php`
- Modify: `tests/bootstrap.php`
- Modify: `tests/test-acf-fields.php`

- [ ] **Step 1: Write the failing registration test**

Extend the ACF test double to capture `acf_add_local_field_group()` arguments. In `tests/test-acf-fields.php`, clear admin groups, call `events_register_acf_fields()`, locate the field named `event_contact_email`, and assert this exact contract:

```php
array(
	'key'      => 'field_aa_events_contact_email',
	'label'    => 'Contact Email',
	'name'     => 'event_contact_email',
	'type'     => 'email',
	'required' => 0,
)
```

- [ ] **Step 2: Run the suite and verify RED**

Run: `php tests/run.php`

Expected: FAIL because no field named `event_contact_email` is registered.

- [ ] **Step 3: Add the built-in field**

Add the optional ACF email field after location/URL/address details in `includes/acf-fields.php`, using the stable key and contract above plus standard empty wrapper/default/placeholder settings.

- [ ] **Step 4: Verify GREEN**

Run:

```bash
php tests/run.php
php -l includes/acf-fields.php
```

Expected: all tests pass and syntax is valid.

### Task 2: Render Contact Email on Single Events

**Files:**
- Modify: `templates/single-event.php`
- Modify: `tests/bootstrap.php`
- Create: `tests/test-contact-email.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Write failing output tests**

Add minimal `sanitize_email()` and `antispambot()` doubles if absent. Test a focused helper named `aa_events_contact_email_markup()` with:

```php
aa_events_assert_same( '', aa_events_contact_email_markup( '' ), 'Empty email renders nothing.' );
aa_events_assert_same( '', aa_events_contact_email_markup( 'not an email' ), 'Invalid email renders nothing.' );
$markup = aa_events_contact_email_markup( 'events@example.com' );
aa_events_assert_true( false !== strpos( $markup, 'mailto:' ), 'Valid email has a mailto link.' );
aa_events_assert_true( false !== strpos( $markup, 'events@example.com' ), 'Valid email remains readable.' );
```

Also assert `templates/single-event.php` reads `event_contact_email`, calls the helper, and renders the `Contact Email:` label only when markup is non-empty.

- [ ] **Step 2: Run the suite and verify RED**

Run: `php tests/run.php`

Expected: FAIL because `aa_events_contact_email_markup()` does not exist.

- [ ] **Step 3: Implement safe markup and template output**

Add this focused template helper to `includes/template-loader.php`:

```php
function aa_events_contact_email_markup( $value ) {
	$email = sanitize_email( (string) $value );
	if ( '' === $email || $email !== trim( (string) $value ) ) {
		return '';
	}
	$protected = antispambot( $email );
	return '<a href="' . esc_attr( 'mailto:' . $protected ) . '">' . wp_kses_post( $protected ) . '</a>';
}
```

In `templates/single-event.php`, call the helper with `get_field( 'event_contact_email' )`. When non-empty, render an `.aa-event-contact-email` detail row with localized label `Contact Email:` and output only the helper's already constrained anchor through `wp_kses_post()`.

- [ ] **Step 4: Run complete verification**

Run:

```bash
php tests/run.php
php -l includes/template-loader.php
php -l templates/single-event.php
phpcs --standard=WordPress includes/acf-fields.php includes/template-loader.php templates/single-event.php
```

Expected: tests and syntax pass. Record unrelated pre-existing PHPCS findings separately rather than changing unrelated template code.

### Task 3: Documentation and Final Check

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Document the optional field**

Add Contact Email to the Event Details list and state that it appears only on single-event pages when valid.

- [ ] **Step 2: Run final verification**

Run:

```bash
php tests/run.php
find . -path './.superpowers' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: all tests and PHP syntax checks pass.

The workspace is not a Git repository, so commit steps are unavailable.
