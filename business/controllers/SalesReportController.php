<?php
/**
 * Universal Footwear POS - Sales Report Controller
 */

require_once __DIR__ . '/../models/SalesReport.php';

class SalesReportController
{
    private $model;

    public function __construct(mysqli $conn, int $businessId, int $userId = 0, bool $isAdmin = false)
    {
        $this->model = new SalesReport($conn, $businessId, $userId, $isAdmin);
    }

    public function init(array $filters): array
    {
        return array(
            'success' => true,
            'masters' => $this->model->masters(),
            'summary' => $this->model->summary($filters),
            'analytics' => $this->model->analytics($filters),
        );
    }

    public function summary(array $filters): array
    {
        return array('success' => true, 'summary' => $this->model->summary($filters));
    }

    public function list(array $filters): array
    {
        $data = $this->model->listBills($filters);
        return array('success' => true, 'rows' => $data['rows'], 'pagination' => $data['pagination'], 'summary' => $this->model->summary($filters));
    }

    public function items(array $filters): array
    {
        $data = $this->model->itemSales($filters);
        return array('success' => true, 'rows' => $data['rows'], 'pagination' => $data['pagination'], 'summary' => $this->model->summary($filters));
    }

    public function dailyTrend(array $filters): array
    {
        $data = $this->model->dailyTrend($filters);
        return array('success' => true, 'rows' => $data['rows'], 'pagination' => $data['pagination'], 'summary' => $this->model->summary($filters));
    }

    public function branchSummary(array $filters): array
    {
        $data = $this->model->branchSummary($filters);
        return array('success' => true, 'rows' => $data['rows'], 'pagination' => $data['pagination'], 'summary' => $this->model->summary($filters));
    }

    public function paymentSummary(array $filters): array
    {
        $data = $this->model->paymentSummary($filters);
        return array('success' => true, 'rows' => $data['rows'], 'pagination' => $data['pagination'], 'summary' => $this->model->summary($filters));
    }

    public function topProducts(array $filters): array
    {
        $data = $this->model->topProducts($filters);
        return array('success' => true, 'rows' => $data['rows'], 'pagination' => $data['pagination'], 'summary' => $this->model->summary($filters));
    }

    public function customerSummary(array $filters): array
    {
        $data = $this->model->customerSummary($filters);
        return array('success' => true, 'rows' => $data['rows'], 'pagination' => $data['pagination'], 'summary' => $this->model->summary($filters));
    }

    public function salesUserSummary(array $filters): array
    {
        $data = $this->model->salesUserSummary($filters);
        return array('success' => true, 'rows' => $data['rows'], 'pagination' => $data['pagination'], 'summary' => $this->model->summary($filters));
    }

    public function categorySummary(array $filters): array
    {
        $data = $this->model->categorySummary($filters);
        return array('success' => true, 'rows' => $data['rows'], 'pagination' => $data['pagination'], 'summary' => $this->model->summary($filters));
    }

    public function hourlySummary(array $filters): array
    {
        $data = $this->model->hourlySummary($filters);
        return array('success' => true, 'rows' => $data['rows'], 'pagination' => $data['pagination'], 'summary' => $this->model->summary($filters));
    }

    public function analytics(array $filters): array
    {
        return array('success' => true, 'analytics' => $this->model->analytics($filters));
    }

    public function exportMatrix(string $type, array $filters): array
    {
        return $this->model->exportMatrix($type, $filters);
    }
}
