GK Footwear POS Create Bill API Integration
==========================================

Upload these files to your project with the same folder structure:

1) bill-create.php
2) api/pos-billing-api.php
3) controllers/PosBillingController.php
4) models/PosBilling.php

What is connected:
- bill-create.php calls api/pos-billing-api.php for bootstrap, customer search, product search, barcode scan, save bill, hold/draft, history, cancellation and offer validation.
- Customer search shows only existing matching customers. If there is no match, no suggestion is shown. The typed name is sent to the API when billing.
- New customers are automatically created by the API during save_bill when the typed customer does not exist.
- Bill save writes into bills and bill_items.
- Stock is reduced in stock_inward_items and recorded in stock_movements.
- Payments are written to bill_payments and cashier_collections.
- Customer outstanding, customer ledger and payment ledger are updated when a customer is linked or auto-created.
- Hold/draft uses pos_bill_holds from your schema.
- Bill no and barcode sequences use number_sequences.

Important:
- Keep your existing includes/db.php, includes/auth.php, includes/functions.php, includes/permissions.php and includes/csrf.php.
- Replace the existing files only after taking a backup.
- This package is PHP 7.2 compatible.
