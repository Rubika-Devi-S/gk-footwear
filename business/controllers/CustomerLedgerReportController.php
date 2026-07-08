<?php
/**
 * Universal Footwear POS - Customer Ledger Report Controller
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/CustomerLedgerReport.php';

class CustomerLedgerReportController
{
    private mysqli $conn;
    private int $businessId;
    private int $userId;
    private bool $isAdmin;
    private CustomerLedgerReport $model;

    public function __construct(mysqli $conn, int $businessId, int $userId, bool $isAdmin)
    {
        $this->conn = $conn;
        $this->businessId = $businessId;
        $this->userId = $userId;
        $this->isAdmin = $isAdmin;
        $this->model = new CustomerLedgerReport($conn);
    }

    private function filters(array $request): array
    {
        return [
            'from_date' => trim((string)($request['from_date'] ?? '')),
            'to_date' => trim((string)($request['to_date'] ?? '')),
            'branch_id' => trim((string)($request['branch_id'] ?? '')),
            'customer_id' => trim((string)($request['customer_id'] ?? '')),
            'ledger_status' => trim((string)($request['ledger_status'] ?? '')),
            'payment_status' => trim((string)($request['payment_status'] ?? '')),
            'reference_type' => trim((string)($request['reference_type'] ?? '')),
            'search' => trim((string)($request['search'] ?? '')),
            'page' => max(1, (int)($request['page'] ?? 1)),
            'per_page' => max(10, min(200, (int)($request['per_page'] ?? 25))),
            'sort_by' => trim((string)($request['sort_by'] ?? '')),
            'sort_dir' => strtolower((string)($request['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }

    public function init(array $request): array
    {
        $filters = $this->filters($request);

        return [
            'success' => true,
            'masters' => $this->model->masters($this->businessId, $this->userId, $this->isAdmin),
            'summary' => $this->model->summary($this->businessId, $this->userId, $this->isAdmin, $filters),
        ];
    }

    public function summary(array $request): array
    {
        $filters = $this->filters($request);

        return [
            'success' => true,
            'summary' => $this->model->summary($this->businessId, $this->userId, $this->isAdmin, $filters),
        ];
    }

    public function customers(array $request): array
    {
        $filters = $this->filters($request);
        $data = $this->model->customerSummary($this->businessId, $this->userId, $this->isAdmin, $filters);
        $data['success'] = true;
        $data['summary'] = $this->model->summary($this->businessId, $this->userId, $this->isAdmin, $filters);

        return $data;
    }

    public function ledger(array $request): array
    {
        $filters = $this->filters($request);
        $data = $this->model->ledgerEntries($this->businessId, $this->userId, $this->isAdmin, $filters);
        $data['success'] = true;
        $data['summary'] = $this->model->summary($this->businessId, $this->userId, $this->isAdmin, $filters);

        return $data;
    }

    public function bills(array $request): array
    {
        $filters = $this->filters($request);
        $data = $this->model->billHistory($this->businessId, $this->userId, $this->isAdmin, $filters);
        $data['success'] = true;
        $data['summary'] = $this->model->summary($this->businessId, $this->userId, $this->isAdmin, $filters);

        return $data;
    }

    public function outstanding(array $request): array
    {
        $filters = $this->filters($request);
        $data = $this->model->outstanding($this->businessId, $this->userId, $this->isAdmin, $filters);
        $data['success'] = true;
        $data['summary'] = $this->model->summary($this->businessId, $this->userId, $this->isAdmin, $filters);

        return $data;
    }

    public function statement(array $request): array
    {
        $filters = $this->filters($request);
        $customerId = (int)($request['customer_id'] ?? 0);

        if ($customerId <= 0) {
            return ['success' => false, 'message' => 'Invalid customer selected.'];
        }

        $data = $this->model->statement($this->businessId, $this->userId, $this->isAdmin, $customerId, $filters);
        $data['success'] = true;

        return $data;
    }

    public function verify(array $request): array
    {
        return [
            'success' => true,
            'rows' => $this->model->verificationRows($this->businessId),
        ];
    }

    public function export(array $request): array
    {
        $filters = $this->filters($request);
        $type = (string)($request['export_type'] ?? 'customers');

        if ($type === 'statement') {
            $customerId = (int)($request['customer_id'] ?? 0);
            $statement = $this->model->statement($this->businessId, $this->userId, $this->isAdmin, $customerId, $filters);

            return [
                'type' => 'statement',
                'filename' => 'customer-statement-' . $customerId . '.csv',
                'headers' => ['Date', 'Customer', 'Mobile', 'Type', 'Reference', 'Branch', 'Debit', 'Credit', 'Balance', 'Remarks'],
                'rows' => $statement['rows'] ?? [],
            ];
        }

        if ($type === 'ledger') {
            $data = $this->model->ledgerEntries($this->businessId, $this->userId, $this->isAdmin, array_merge($filters, ['page' => 1, 'per_page' => 200]));
            return [
                'type' => 'ledger',
                'filename' => 'customer-ledger-entries.csv',
                'headers' => ['Date', 'Customer', 'Mobile', 'Type', 'Reference', 'Branch', 'Debit', 'Credit', 'Balance', 'Remarks'],
                'rows' => $data['rows'] ?? [],
            ];
        }

        if ($type === 'bills') {
            $data = $this->model->billHistory($this->businessId, $this->userId, $this->isAdmin, array_merge($filters, ['page' => 1, 'per_page' => 200]));
            return [
                'type' => 'bills',
                'filename' => 'customer-bill-history.csv',
                'headers' => ['Date', 'Bill No', 'Order No', 'Customer', 'Branch', 'Bill Amount', 'Paid', 'Balance', 'Payment Status', 'Created By'],
                'rows' => $data['rows'] ?? [],
            ];
        }

        $data = $this->model->customerSummary($this->businessId, $this->userId, $this->isAdmin, array_merge($filters, ['page' => 1, 'per_page' => 200]));

        return [
            'type' => 'customers',
            'filename' => 'customer-ledger-summary.csv',
            'headers' => ['Customer', 'Mobile', 'GSTIN', 'Opening', 'Bills', 'Bill Amount', 'Paid', 'Outstanding', 'Last Bill'],
            'rows' => $data['rows'] ?? [],
        ];
    }
}
?>
