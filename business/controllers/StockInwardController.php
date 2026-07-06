<?php
require_once __DIR__ . '/../models/StockInward.php';

class StockInwardController
{
    private $model;

    public function __construct(mysqli $conn, int $businessId)
    {
        $this->model = new StockInward($conn, $businessId);
    }

    public function masters(): array
    {
        return ['masters' => $this->model->getMasters()];
    }

    public function list(array $input): array
    {
        return [
            'batches' => $this->model->listBatches($input),
            'stats' => $this->model->stats($input),
        ];
    }

    public function get(array $input): array
    {
        $batchId = (int)($input['batch_id'] ?? 0);
        if ($batchId <= 0) {
            throw new InvalidArgumentException('Invalid stock inward batch selected.');
        }

        $batch = $this->model->getBatch($batchId);
        if (!$batch) {
            throw new RuntimeException('Stock inward batch not found.');
        }

        return ['batch' => $batch];
    }

    public function save(array $input): array
    {
        $batchId = (int)($input['batch_id'] ?? 0);
        $payload = $this->validateStockInward($input);

        if ($batchId > 0) {
            $result = $this->model->updateBatch($batchId, $payload['batch'], $payload['items']);
            return [
                'message' => 'Stock inward updated successfully.',
                'batch_id' => $result['batch_id'],
                'batch_no' => $result['batch_no'],
            ];
        }

        $result = $this->model->createBatch($payload['batch'], $payload['items']);
        return [
            'message' => 'Stock inward saved successfully.',
            'batch_id' => $result['batch_id'],
            'batch_no' => $result['batch_no'],
        ];
    }

    public function changeStatus(array $input, string $status): array
    {
        $batchId = (int)($input['batch_id'] ?? 0);
        if ($batchId <= 0) {
            throw new InvalidArgumentException('Invalid stock inward batch selected.');
        }

        $result = $this->model->changeBatchStatus($batchId, $status);
        return [
            'message' => $status === 'cancelled' ? 'Stock inward cancelled successfully.' : 'Stock inward deleted successfully.',
            'batch_id' => $result['batch_id'],
            'batch_status' => $result['batch_status'],
        ];
    }

    private function validateStockInward(array $input): array
    {
        $branchId = (int)($input['branch_id'] ?? 0);
        $supplierId = (int)($input['supplier_id'] ?? 0);
        $inwardDate = trim((string)($input['inward_date'] ?? ''));
        $invoiceNumber = trim((string)($input['invoice_number'] ?? ''));
        $invoiceDate = trim((string)($input['invoice_date'] ?? ''));
        $remarks = trim((string)($input['remarks'] ?? ''));

        if ($branchId <= 0) {
            throw new InvalidArgumentException('Branch / Firm is required.');
        }
        if (!$this->model->masterExists('branches', 'branch_id', $branchId)) {
            throw new InvalidArgumentException('Selected Branch / Firm is invalid or inactive.');
        }

        if ($supplierId <= 0) {
            throw new InvalidArgumentException('Supplier is required.');
        }
        if (!$this->model->masterExists('suppliers', 'supplier_id', $supplierId)) {
            throw new InvalidArgumentException('Selected supplier is invalid or inactive.');
        }

        if ($inwardDate === '') {
            throw new InvalidArgumentException('Inward date is required.');
        }
        $inwardDate = $this->normalizeDate($inwardDate, 'Inward date', true);

        if ($invoiceDate !== '') {
            $invoiceDate = $this->normalizeDate($invoiceDate, 'Invoice date', true);
        } else {
            $invoiceDate = null;
        }

        if (mb_strlen($invoiceNumber) > 80) {
            throw new InvalidArgumentException('Invoice number should not exceed 80 characters.');
        }

        if (mb_strlen($remarks) > 1000) {
            throw new InvalidArgumentException('Remarks should not exceed 1000 characters.');
        }

        $itemsInput = $this->extractItems($input);
        if (!$itemsInput) {
            throw new InvalidArgumentException('At least one stock item is required.');
        }
        if (count($itemsInput) > 100) {
            throw new InvalidArgumentException('Maximum 100 stock items are allowed in one inward batch.');
        }

        $items = [];
        foreach ($itemsInput as $index => $item) {
            $items[] = $this->validateItem($item, $index + 1);
        }

        return [
            'batch' => [
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'inward_date' => $inwardDate,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'remarks' => $remarks,
            ],
            'items' => $items,
        ];
    }

    private function normalizeDate(string $dateValue, string $label, bool $blockFuture): string
    {
        $tz = new DateTimeZone('Asia/Kolkata');
        $dateValue = trim($dateValue);
        $acceptedFormats = ['!Y-m-d', '!d-m-Y', '!d/m/Y'];
        $date = null;

        foreach ($acceptedFormats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $dateValue, $tz);
            $errors = DateTimeImmutable::getLastErrors();
            $hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
            if ($parsed instanceof DateTimeImmutable && !$hasErrors) {
                $date = $parsed;
                break;
            }
        }

        if (!$date) {
            throw new InvalidArgumentException('Enter a valid ' . strtolower($label) . '.');
        }

        if ($blockFuture) {
            $today = new DateTimeImmutable('today', $tz);
            if ($date > $today) {
                throw new InvalidArgumentException($label . ' cannot be a future date.');
            }
        }

        return $date->format('Y-m-d');
    }

    private function extractItems(array $input): array
    {
        if (isset($input['items_json'])) {
            $decoded = json_decode((string)$input['items_json'], true);
            return is_array($decoded) ? $decoded : [];
        }

        if (isset($input['items']) && is_array($input['items'])) {
            return $input['items'];
        }

        return [];
    }

    private function validateItem(array $input, int $rowNo): array
    {
        $categoryId = (int)($input['category_id'] ?? 0);
        $brandId = (int)($input['brand_id'] ?? 0);
        $articleNo = trim((string)($input['article_no'] ?? ''));
        $articleName = trim((string)($input['article_name'] ?? ''));
        $size = trim((string)($input['size'] ?? ''));
        $color = trim((string)($input['color'] ?? ''));
        $qty = (float)($input['qty'] ?? 0);
        $purchaseRate = (float)($input['purchase_rate'] ?? 0);
        $mrpRate = (float)($input['mrp_rate'] ?? 0);
        $discountType = (string)($input['product_discount_type'] ?? 'none');
        $discountValue = (float)($input['product_discount_value'] ?? 0);
        $barcodeRequired = (int)($input['barcode_required'] ?? 1);

        if ($categoryId <= 0) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Category is required.');
        }
        if (!$this->model->masterExists('categories', 'category_id', $categoryId)) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Selected category is invalid or inactive.');
        }

        if ($brandId <= 0) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Brand is required.');
        }
        if (!$this->model->masterExists('brands', 'brand_id', $brandId)) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Selected brand is invalid or inactive.');
        }

        if ($articleNo === '') {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Article number is required.');
        }
        if (mb_strlen($articleNo) > 100) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Article number should not exceed 100 characters.');
        }
        if (mb_strlen($articleName) > 200) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Article name should not exceed 200 characters.');
        }
        if ($size === '') {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Size is required.');
        }
        if (mb_strlen($size) > 50) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Size should not exceed 50 characters.');
        }
        if (mb_strlen($color) > 80) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Color should not exceed 80 characters.');
        }

        if ($qty <= 0) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Quantity must be greater than zero.');
        }

        if ($purchaseRate <= 0) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Purchase rate must be greater than zero.');
        }
        if ($mrpRate <= 0) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': MRP must be greater than zero.');
        }
        if ($mrpRate < $purchaseRate) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': MRP must be greater than or equal to purchase rate.');
        }

        if (!in_array($discountType, ['none', 'percent', 'amount'], true)) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Invalid product discount type.');
        }
        if ($discountValue < 0) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Product discount cannot be negative.');
        }
        if ($discountType === 'percent' && $discountValue > 100) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Product discount percent cannot exceed 100.');
        }
        if ($discountType === 'amount' && $discountValue > $mrpRate) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Product discount amount cannot exceed MRP.');
        }
        if ($discountType === 'none') {
            $discountValue = 0.00;
        }

        $item = $this->model->calculateItem([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'article_no' => $articleNo,
            'article_name' => $articleName,
            'size' => $size,
            'color' => $color,
            'qty' => $qty,
            'purchase_rate' => $purchaseRate,
            'mrp_rate' => $mrpRate,
            'product_discount_type' => $discountType,
            'product_discount_value' => $discountValue,
            'barcode_required' => $barcodeRequired === 1 ? 1 : 0,
        ]);

        if ($item['selling_rate'] <= 0) {
            throw new InvalidArgumentException('Row ' . $rowNo . ': Selling rate must be greater than zero after discount.');
        }

        return $item;
    }
}
