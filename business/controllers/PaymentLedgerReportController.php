<?php
/**
 * GK Footwear POS - Payment Ledger Report Controller
 */

require_once __DIR__ . '/../models/PaymentLedgerReport.php';

class PaymentLedgerReportController
{
    private $conn;
    private $model;
    private $businessId;
    private $userId;
    private $isAdmin;

    public function __construct(mysqli $conn, int $businessId, int $userId = 0, bool $isAdmin = false)
    {
        $this->conn = $conn;
        $this->model = new PaymentLedgerReport($conn);
        $this->businessId = $businessId;
        $this->userId = $userId;
        $this->isAdmin = $isAdmin;
    }

    private function filters(array $input): array
    {
        return $this->model->normalizeFilters($input);
    }

    public function init(array $input): array
    {
        $filters = $this->filters($input);
        return array(
            'success' => true,
            'masters' => $this->model->masters($this->businessId, $this->userId, $this->isAdmin),
            'summary' => $this->model->paymentSummary($this->businessId, $this->userId, $this->isAdmin, $filters),
        );
    }

    public function summary(array $input): array
    {
        $filters = $this->filters($input);
        return array(
            'success' => true,
            'summary' => $this->model->paymentSummary($this->businessId, $this->userId, $this->isAdmin, $filters),
        );
    }

    public function payments(array $input): array
    {
        $filters = $this->filters($input);
        $data = $this->model->payments($this->businessId, $this->userId, $this->isAdmin, $filters, true);
        return array(
            'success' => true,
            'summary' => $this->model->paymentSummary($this->businessId, $this->userId, $this->isAdmin, $filters),
        ) + $data;
    }

    public function ledger(array $input): array
    {
        $filters = $this->filters($input);
        $data = $this->model->ledgerEntries($this->businessId, $this->userId, $this->isAdmin, $filters, true);
        return array(
            'success' => true,
            'summary' => $this->model->paymentSummary($this->businessId, $this->userId, $this->isAdmin, $filters),
        ) + $data;
    }

    public function outstanding(array $input): array
    {
        $filters = $this->filters($input);
        $data = $this->model->outstanding($this->businessId, $this->userId, $this->isAdmin, $filters, true);
        return array(
            'success' => true,
            'summary' => $this->model->paymentSummary($this->businessId, $this->userId, $this->isAdmin, $filters),
        ) + $data;
    }

    public function daily(array $input): array
    {
        $filters = $this->filters($input);
        $rows = $this->model->dailySummary($this->businessId, $this->userId, $this->isAdmin, $filters);
        return array(
            'success' => true,
            'summary' => $this->model->paymentSummary($this->businessId, $this->userId, $this->isAdmin, $filters),
            'rows' => $rows,
            'total' => count($rows),
            'page' => 1,
            'per_page' => count($rows),
            'total_pages' => 1,
        );
    }

    public function method(array $input): array
    {
        $filters = $this->filters($input);
        $rows = $this->model->methodSummary($this->businessId, $this->userId, $this->isAdmin, $filters);
        return array(
            'success' => true,
            'summary' => $this->model->paymentSummary($this->businessId, $this->userId, $this->isAdmin, $filters),
            'rows' => $rows,
            'total' => count($rows),
            'page' => 1,
            'per_page' => count($rows),
            'total_pages' => 1,
        );
    }

    public function cashier(array $input): array
    {
        $filters = $this->filters($input);
        $rows = $this->model->cashierSummary($this->businessId, $this->userId, $this->isAdmin, $filters);
        return array(
            'success' => true,
            'summary' => $this->model->paymentSummary($this->businessId, $this->userId, $this->isAdmin, $filters),
            'rows' => $rows,
            'total' => count($rows),
            'page' => 1,
            'per_page' => count($rows),
            'total_pages' => 1,
        );
    }

    public function history(array $input): array
    {
        $filters = $this->filters($input);
        $customerId = (int)($input['customer_id'] ?? 0);
        $data = $this->model->customerHistory($this->businessId, $this->userId, $this->isAdmin, $filters, $customerId);
        return array('success' => true) + $data;
    }

    public function rowsForReport(string $report, array $input, bool $paginate = false): array
    {
        $filters = $this->filters($input);
        return $this->model->reportRows($report, $this->businessId, $this->userId, $this->isAdmin, $filters, $paginate);
    }

    public function export(string $report, string $format, array $input): void
    {
        $filters = $this->filters($input);
        $result = $this->model->reportRows($report, $this->businessId, $this->userId, $this->isAdmin, $filters, false);
        $rows = $this->model->enrichRowsForExport($result['rows'] ?? array());
        $columns = $this->model->exportColumns($report);
        $fileBase = 'payment-ledger-' . $report . '-' . date('Ymd-His');

        if ($format === 'excel' || $format === 'xls') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $fileBase . '.xls"');
            echo "\xEF\xBB\xBF";
            echo '<table border="1"><thead><tr>';
            foreach ($columns as $title => $key) {
                echo '<th>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($columns as $title => $key) {
                    echo '<td>' . htmlspecialchars((string)($row[$key] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileBase . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($columns));
        foreach ($rows as $row) {
            $line = array();
            foreach ($columns as $title => $key) {
                $line[] = $row[$key] ?? '';
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    public function exportColumns(string $report): array
    {
        return $this->model->exportColumns($report);
    }

    public function enrichRowsForExport(array $rows): array
    {
        return $this->model->enrichRowsForExport($rows);
    }
}
