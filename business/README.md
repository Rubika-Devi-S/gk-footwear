# Stock Inward API Module

Files included:

- `stock-inward.php`
- `api/stock-inward-api.php`
- `models/StockInward.php`
- `controllers/StockInwardController.php`
- `database/stock_inward_module.sql`

## Placement

Copy files into your project root using the same folder structure:

```text
stock-inward.php
api/stock-inward-api.php
models/StockInward.php
controllers/StockInwardController.php
database/stock_inward_module.sql
```

## Features

- Firm/branch-wise stock inward
- Batch number auto-generation through `number_sequences`
- Multiple items per batch
- Category, brand, supplier, branch master validation
- Server-side selling rate calculation: `MRP - discount`
- Stock barcode auto-generation through `number_sequences`
- Stock movements on inward/cancel/delete
- Vendor ledger and supplier outstanding update
- Soft cancel/delete when stock is not used in billing
- Activity logs
- API JSON responses
- CSRF support
- Live search/filter/list

## API Actions

```text
GET  api/stock-inward-api.php?action=masters
GET  api/stock-inward-api.php?action=list
GET  api/stock-inward-api.php?action=get&batch_id=1
POST api/stock-inward-api.php action=save_stock_inward
POST api/stock-inward-api.php action=cancel_stock_inward
POST api/stock-inward-api.php action=delete_stock_inward
```

## Important

The API blocks update/cancel/delete when the stock has already been used in billing.
