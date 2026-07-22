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

    private function formatDateOnly($value): string
    {
        if (empty($value)) {
            return '';
        }

        $time = strtotime((string)$value);

        return $time ? date('d-m-Y', $time) : (string)$value;
    }

    private function removeTimeFromRows(array $data): array
    {
        if (!empty($data['rows']) && is_array($data['rows'])) {
            foreach ($data['rows'] as &$row) {

                if (isset($row['entry_datetime'])) {
                    $row['entry_datetime'] = $this->formatDateOnly($row['entry_datetime']);
                }

                if (isset($row['entry_display'])) {
                    $row['entry_display'] = $this->formatDateOnly($row['entry_display']);
                }

                if (isset($row['inward_date'])) {
                    $row['inward_date'] = $this->formatDateOnly($row['inward_date']);
                }

                if (isset($row['inward_display'])) {

                    if (!empty($row['inward_display'])) {

                        $time = strtotime((string)$row['inward_display']);

                        $row['inward_display'] = $time
                            ? date('d-m-Y h:i A', $time)
                            : $row['inward_display'];
                    }
                }

                if (isset($row['last_purchase_display'])) {
                    $row['last_purchase_display'] = $this->formatDateOnly($row['last_purchase_display']);
                }
            }
            unset($row);
        }

        return $data;
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
        return [
            'success' => true,
            'summary' => $this->model->summary($this->businessId, $this->userId, $this->isAdmin, $this->filters($input)),
        ];
    }

    public function suppliers(array $input): array
    {
        $data = $this->model->supplierSummary($this->businessId, $this->userId, $this->isAdmin, $this->filters($input));
        $data = $this->removeTimeFromRows($data);

        return ['success'=>true,'summary'=>$this->model->summary($this->businessId,$this->userId,$this->isAdmin,$this->filters($input))] + $data;
    }

    public function ledger(array $input): array
    {
        $data = $this->model->ledgerEntries($this->businessId,$this->userId,$this->isAdmin,$this->filters($input));
        return ['success'=>true] + $this->removeTimeFromRows($data);
    }

    public function purchases(array $input): array
    {
        $data = $this->model->purchaseHistory($this->businessId,$this->userId,$this->isAdmin,$this->filters($input));
        return ['success'=>true] + $this->removeTimeFromRows($data);
    }

    public function outstanding(array $input): array
    {
        $data = $this->model->outstanding($this->businessId,$this->userId,$this->isAdmin,$this->filters($input));
        return ['success'=>true] + $this->removeTimeFromRows($data);
    }

    public function statement(array $input): array
    {
        $supplierId = max(0,(int)($input['supplier_id']??0));

        if($supplierId<=0){
            return ['success'=>false,'message'=>'Please select a supplier.'];
        }

        $data = $this->model->statement(
            $this->businessId,
            $this->userId,
            $this->isAdmin,
            $supplierId,
            $this->filters($input)
        );

        return ['success'=>true] + $this->removeTimeFromRows($data);
    }

    public function verify(array $input): array
    {
        return ['success'=>true,'rows'=>$this->model->verificationRows($this->businessId)];
    }

    public function export(array $input): array
    {
        $filters=$this->filters($input);
        $type=(string)($input['export_type']??'suppliers');
        $filters['per_page']=200;

        if($type==='ledger'){
            $data=$this->removeTimeFromRows($this->model->ledgerEntries($this->businessId,$this->userId,$this->isAdmin,$filters));
            return ['filename'=>'supplier-ledger-entries.csv','type'=>'ledger','rows'=>$data['rows'],'headers'=>['Date','Supplier','Mobile','Type','Purpose','Reference','Branch','Debit','Credit','Balance','Remarks']];
        }

        if($type==='purchases'){
            $data=$this->removeTimeFromRows($this->model->purchaseHistory($this->businessId,$this->userId,$this->isAdmin,$filters));
            return ['filename'=>'supplier-purchase-history.csv','type'=>'purchases','rows'=>$data['rows'],'headers'=>['Inward Date','Batch No','Invoice No','Supplier','Branch','Qty','Purchase Value','MRP Value','Selling Value','Status','Created By']];
        }

        return [
            'filename'=>'supplier-ledger-summary.csv',
            'type'=>'suppliers',
            'rows'=>($this->model->supplierSummary($this->businessId,$this->userId,$this->isAdmin,$filters))['rows'],
            'headers'=>['Supplier','Mobile','GSTIN','Opening','Purchase/Credit Additions','Payment/Debit Decreases','Calculated Balance','Last Purchase']
        ];
    }
}
?>
