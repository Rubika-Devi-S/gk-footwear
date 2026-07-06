# Supplier File List Update

Files included:

- `suppliers.php` - updated Supplier File List page with full front-page listing and modal add/edit form.
- `includes/common-toast.php` - reusable common toast component for all modules.

## Installation

1. Replace your existing project root `suppliers.php` with the new `suppliers.php`.
2. Copy `includes/common-toast.php` into your project `includes` folder.
3. The Supplier page will continue using your existing API endpoint: `api/suppliers-api.php`.
4. To use toast in other modules, add this line after `includes/page-message.php`:

```php
<?php include __DIR__ . '/includes/common-toast.php'; ?>
```

Then in JavaScript:

```js
AppToast.success('Saved successfully.');
AppToast.error('Something went wrong.');
AppToast.warning('Check the entered details.');
AppToast.info('Loading data...');
```
