# Stock List API Module

## Files

- `stock-list.php`
- `api/stock-list-api.php`
- `models/StockList.php`
- `controllers/StockListController.php`
- `database/stock_list_module.sql`

## API Actions

- `action=masters` - load branches, suppliers, categories, brands
- `action=list` - firm-wise stock list with filters
- `action=get&stock_item_id=ID` - stock item details, barcodes, movements
- `action=barcode_lookup&barcode=VALUE` - lookup by stock barcode
- `action=update_status` - POST item status update with CSRF

## Features

- Firm / branch-wise stock visibility
- Batch-wise stock listing
- Live search by article, batch, branch, supplier, category, brand, or barcode
- Barcode lookup
- KPI summary
- Stock movement history
- Available / low stock / out of stock filters
- Safe status update with activity log

## Install

Copy files to the matching folders in your project:

```text
stock-list.php
api/stock-list-api.php
models/StockList.php
controllers/StockListController.php
database/stock_list_module.sql
```

This module uses existing schema tables and does not create new mandatory tables.
