GK FOOTWEAR POS - FPDF PRINT SETUP
==================================

FILES INCLUDED
--------------
1) pos-create-bill.php
   - Save & Print button opens bill-print.php after saving bill.
   - Print button opens last saved bill in FPDF receipt.

2) bill-print.php
   - Separate FPDF print file.
   - URL format: bill-print.php?bill_id=1&auto_print=1
   - Reads bills, bill_items, branches, businesses, invoice_settings, bill_barcodes and bill_payments.
   - Updates bills.print_count.
   - Adds print activity log in business_activity_logs.

3) api/pos-billing-api.php
   - Includes duplicate-safe bill barcode save using INSERT IGNORE + retry.

INSTALL USING COMPOSER
----------------------
Open terminal in your project root and run:

composer require setasign/fpdf

Then make sure this path exists:

vendor/autoload.php

The bill-print.php file will automatically load Composer autoload.

WITHOUT COMPOSER
----------------
Download FPDF manually from fpdf.org and place fpdf.php here:

libs/fpdf.php

The bill-print.php file checks these paths:
- vendor/autoload.php
- libs/fpdf.php
- includes/fpdf.php
- fpdf.php

UPLOAD LOCATION
---------------
Upload files like this:

project-root/pos-create-bill.php
project-root/bill-print.php
project-root/api/pos-billing-api.php

TEST URL
--------
After creating one bill, open:

bill-print.php?bill_id=1&auto_print=1

Replace 1 with your real bill_id.

IMPORTANT
---------
If you still get Duplicate entry BILL-000002, your server is still using the old api/pos-billing-api.php.
Replace the API file again and clear browser cache / hosting cache.
