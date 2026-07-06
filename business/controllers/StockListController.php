<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/StockList.php';

class StockListController
{
    private $conn;
    private $model;
    private $businessId;
    private $userId;
    private $isAdmin;

    public function __construct(mysqli $conn, int $businessId, int $userId = 0, bool $isAdmin = false)
    {
        $this->conn = $conn;
        $this->model = new StockList($conn);
        $this->businessId = $businessId;
        $this->userId = $userId;
        $this->isAdmin = $isAdmin;
    }

    public function init(array $filters = []): array
    {
        return [
            'success' => true,
            'branches' => $this->model->getBranches($this->businessId, $this->userId, $this->isAdmin),
            'categories' => $this->model->getCategories($this->businessId),
            'brands' => $this->model->getBrands($this->businessId),
            'suppliers' => $this->model->getSuppliers($this->businessId),
            'stats' => $this->model->stats($this->businessId, $this->userId, $this->isAdmin, $filters),
            'stock' => $this->model->listStock($this->businessId, $this->userId, $this->isAdmin, $filters),
        ];
    }

    public function list(array $filters = []): array
    {
        return [
            'success' => true,
            'stats' => $this->model->stats($this->businessId, $this->userId, $this->isAdmin, $filters),
            'stock' => $this->model->listStock($this->businessId, $this->userId, $this->isAdmin, $filters),
        ];
    }

    public function get(int $stockItemId): array
    {
        if ($stockItemId <= 0) {
            return ['success' => false, 'message' => 'Invalid stock item selected.'];
        }

        $item = $this->model->getStockItem($this->businessId, $stockItemId);
        if (!$item) {
            return ['success' => false, 'message' => 'Stock item not found.'];
        }

        $branchId = (int)($item['branch_id'] ?? 0);
        if (!$this->model->userCanAccessBranch($this->businessId, $this->userId, $branchId, $this->isAdmin)) {
            return ['success' => false, 'message' => 'You do not have access to this branch stock.'];
        }

        return [
            'success' => true,
            'item' => $item,
            'movements' => $this->model->movements($this->businessId, $stockItemId),
        ];
    }

    public function barcodeLookup(int $branchId, string $barcode): array
    {
        if ($branchId <= 0) {
            return ['success' => false, 'message' => 'Please select branch/firm before barcode scan.'];
        }

        if (!$this->model->userCanAccessBranch($this->businessId, $this->userId, $branchId, $this->isAdmin)) {
            return ['success' => false, 'message' => 'You do not have access to this branch.'];
        }

        $item = $this->model->barcodeLookup($this->businessId, $branchId, $barcode);
        if (!$item) {
            return ['success' => false, 'message' => 'No active stock found for this barcode.'];
        }

        return ['success' => true, 'item' => $item];
    }
}
