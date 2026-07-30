# Event Contact Email Design

## Objective

Add an optional contact email to event editing and display it as a clickable email link on single-event pages only.

## Field Model

The built-in Event Details ACF group adds an optional field with:

- Label: `Contact Email`
- Name: `event_contact_email`
- Type: `email`

The field is stored at event level because it applies to the event rather than an individual occurrence. Admin-created override groups may add a field with the same name to use the same front-end output.

## Front-End Output

`templates/single-event.php` adds a Contact Email row within the existing event details. The row is omitted when the value is empty or invalid. Valid addresses render as a `mailto:` link. The visible address is protected with WordPress's `antispambot()` helper, and the address and URL are escaped for their output contexts.

Archive cards and calendar views do not display the contact email.

## Compatibility and Testing

Existing events require no migration because the field is optional. Tests verify the built-in field registration contract and the single-event template's conditional, sanitized email output. PHP syntax and the complete test suite must pass.

## Out of Scope

- Contact names, phone numbers, or reusable contact records.
- Sending email through WordPress.
- Displaying contact email on archives or calendars.
