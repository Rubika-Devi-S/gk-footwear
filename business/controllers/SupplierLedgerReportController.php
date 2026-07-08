<?php
/**
 * Universal Footwear POS - Supplier Ledger Report Controller
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/SupplierLedgerReport.php';

class SupplierLedgerReportController
{
    private mysqli $conn;
    private SupplierLedgerReport $model;
    private int $businessId;
    private int $userId;
    private bool $isAdmin;

    public function __construct(mysqli $conn, int $businessId, int $userId = 0, bool $isAdmin = false)
    {
        $this->conn = $conn;
        $this->model = new SupplierLedgerReport($conn);
        $this->businessId = $businessId;
        $this->userId = $userId;
        $this->isAdmin = $isAdmin;
    }

    private function filters(array $input): array
    {
        $sortDir = strtolower((string)($input['sort_dir'] ?? 'desc'));

        return [
            'from_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($input['from_date'] ?? '')) ? (string)$input['from_date'] : '',
            'to_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($input['to_date'] ?? '')) ? (string)$input['to_date'] : '',
            'branch_id' => max(0, (int)($input['branch_id'] ?? 0)),
            'supplier_id' => max(0, (int)($input['supplier_id'] ?? 0)),
            'supplier_status' => array_key_exists('supplier_status', $input) && $input['supplier_status'] !== '' ? (string)(int)$input['supplier_status'] : '',
            'ledger_status' => in_array((string)($input['ledger_status'] ?? ''), ['outstanding', 'clear'], true) ? (string)$input['ledger_status'] : '',
            'purchase_status' => in_array((string)($input['purchase_status'] ?? ''), ['active', 'cancelled', 'deleted'], true) ? (string)$input['purchase_status'] : '',
            'reference_type' => preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($input['reference_type'] ?? '')),
            'search' => trim((string)($input['search'] ?? '')),
            'page' => max(1, (int)($input['page'] ?? 1)),
            'per_page' => max(10, min(200, (int)($input['per_page'] ?? 25))),
            'sort_by' => preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($input['sort_by'] ?? '')),
            'sort_dir' => $sortDir === 'asc' ? 'asc' : 'desc',
        ];
    }

    public function init(array $input): array
    {
        $filters = $this->filters($input);

        return [
            'success' => true,
            'masters' => $this->model->masters($this->businessId, $this->userId, $this->isAdmin),
            'summary' => $this->model->summary($this->businessId, $this->userId, $this->isAdmin, $filters),
        ];
    }

    public function summary(array $input): array
    {
        $filters = $this->filters($input);

        return [
            'success' => true,
            'summary' => $this->model->summary($this->businessId, $this->userId, $this->isAdmin, $filters),
        ];
    }

    public function suppliers(array $input): array
    {
        $filters = $this->filters($input);
        $data = $this->model->supplierSummary($this->businessId, $this->userId, $this->isAdmin, $filters);

        return ['success' => true, 'summary' => $this->model->summary($this->businessId, $this->userId, $this->isAdmin, $filters)] + $data;
    }

    public function ledger(array $input): array
    {
        $filters = $this->filters($input);
        return ['success' => true] + $this->model->ledgerEntries($this->businessId, $this->userId, $this->isAdmin, $filters);
    }

    public function purchases(array $input): array
    {
        $filters = $this->filters($input);
        return ['success' => true] + $this->model->purchaseHistory($this->businessId, $this->userId, $this->isAdmin, $filters);
    }

    public function outstanding(array $input): array
    {
        $filters = $this->filters($input);
        return ['success' => true] + $this->model->outstanding($this->businessId, $this->userId, $this->isAdmin, $filters);
    }

    public function statement(array $input): array
    {
        $filters = $this->filters($input);
        $supplierId = max(0, (int)($input['supplier_id'] ?? 0));

        if ($supplierId <= 0) {
            return ['success' => false, 'message' => 'Please select a supplier.'];
        }

        return ['success' => true] + $this->model->statement($this->businessId, $this->userId, $this->isAdmin, $supplierId, $filters);
    }

    public function verify(array $input): array
    {
        return [
            'success' => true,
            'rows' => $this->model->verificationRows($this->businessId),
        ];
    }

    public function export(array $input): array
    {
        $filters = $this->filters($input);
        $type = (string)($input['export_type'] ?? 'suppliers');
        $filters['per_page'] = 200;

        if ($type === 'ledger') {
            $data = $this->model->ledgerEntries($this->businessId, $this->userId, $this->isAdmin, $filters);

            return [
                'filename' => 'supplier-ledger-entries.csv',
                'type' => 'ledger',
                'rows' => $data['rows'],
                'headers' => ['Date', 'Supplier', 'Mobile', 'Type', 'Purpose', 'Reference', 'Branch', 'Debit', 'Credit', 'Balance', 'Remarks'],
            ];
        }

        if ($type === 'purchases') {
            $data = $this->model->purchaseHistory($this->businessId, $this->userId, $this->isAdmin, $filters);

            return [
                'filename' => 'supplier-purchase-history.csv',
                'type' => 'purchases',
                'rows' => $data['rows'],
                'headers' => ['Inward Date', 'Batch No', 'Invoice No', 'Supplier', 'Branch', 'Qty', 'Purchase Value', 'MRP Value', 'Selling Value', 'Status', 'Created By'],
            ];
        }

        if ($type === 'statement') {
            $supplierId = max(0, (int)($input['supplier_id'] ?? 0));
            $data = $this->model->statement($this->businessId, $this->userId, $this->isAdmin, $supplierId, $filters);

            return [
                'filename' => 'supplier-statement.csv',
                'type' => 'statement',
                'rows' => $data['rows'],
                'supplier' => $data['supplier'],
                'summary' => $data['summary'],
                'headers' => ['Date', 'Type', 'Purpose', 'Reference', 'Branch', 'Description', 'Debit', 'Credit', 'Balance'],
            ];
        }

        if ($type === 'outstanding') {
            $filters['ledger_status'] = 'outstanding';
        }

        $data = $this->model->supplierSummary($this->businessId, $this->userId, $this->isAdmin, $filters);

        return [
            'filename' => $type === 'outstanding' ? 'supplier-outstanding.csv' : 'supplier-ledger-summary.csv',
            'type' => 'suppliers',
            'rows' => $data['rows'],
            'headers' => ['Supplier', 'Mobile', 'GSTIN', 'Opening', 'Purchase/Credit Additions', 'Payment/Debit Decreases', 'Calculated Balance', 'Last Purchase'],
        ];
    }
}
?>
