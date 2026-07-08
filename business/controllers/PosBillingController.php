<?php
/**
 * GK Footwear POS Billing Controller
 */
require_once __DIR__ . '/../models/PosBilling.php';

class PosBillingController
{
    private $conn;
    private $model;
    private $businessId;
    private $userId;
    private $isAdmin;

    public function __construct(mysqli $conn, $businessId, $userId = 0, $isAdmin = false)
    {
        $this->conn = $conn;
        $this->model = new PosBilling($conn);
        $this->businessId = (int)$businessId;
        $this->userId = (int)$userId;
        $this->isAdmin = (bool)$isAdmin;
    }

    private function branchId(array $input)
    {
        return (int)($input['branch_id'] ?? 0);
    }

    private function ensureBranch($branchId)
    {
        if ((int)$branchId <= 0) {
            throw new Exception('Please select branch / firm.');
        }
        if (!$this->model->userCanAccessBranch($this->businessId, $this->userId, (int)$branchId, $this->isAdmin)) {
            throw new Exception('You do not have access to this branch / firm.');
        }
    }

    public function bootstrap(array $input)
    {
        $branches = $this->model->getBranches($this->businessId, $this->userId, $this->isAdmin);
        $selectedBranchId = $this->branchId($input);
        if ($selectedBranchId <= 0) {
            $selectedBranchId = (int)($branches[0]['branch_id'] ?? 0);
        }
        if ($selectedBranchId > 0) {
            $this->ensureBranch($selectedBranchId);
        }

        return array(
            'success' => true,
            'business' => $this->model->getBusiness($this->businessId),
            'business_settings' => $this->model->getBusinessSettings($this->businessId),
            'invoice_settings' => $selectedBranchId > 0 ? $this->model->getInvoiceSettings($this->businessId, $selectedBranchId) : array(),
            'barcode_settings' => $selectedBranchId > 0 ? $this->model->getBarcodeSettings($this->businessId, $selectedBranchId) : array(),
            'branches' => $branches,
            'selected_branch_id' => $selectedBranchId,
            'next_bill_no' => $this->model->displayNextBillNo($this->businessId),
            'next_bill_barcode' => $this->model->displayNextBillBarcode($this->businessId),
            'payment_methods' => $this->model->getPaymentMethods($this->businessId),
            'held_bills' => $selectedBranchId > 0 ? $this->model->billHistory($this->businessId, $selectedBranchId, 'hold') : array(),
            'bill_history' => $selectedBranchId > 0 ? $this->model->billHistory($this->businessId, $selectedBranchId, 'all') : array(),
        );
    }

    public function searchProducts(array $input)
    {
        $branchId = $this->branchId($input);
        $this->ensureBranch($branchId);
        return array(
            'success' => true,
            'products' => $this->model->searchProducts($this->businessId, $branchId, (string)($input['q'] ?? $input['search'] ?? ''), (int)($input['limit'] ?? 30)),
        );
    }

    public function productOptions(array $input)
    {
        $branchId = $this->branchId($input);
        $this->ensureBranch($branchId);
        $stockItemId = (int)($input['stock_item_id'] ?? 0);
        return array('success' => true, 'options' => $this->model->getProductOptions($this->businessId, $branchId, $stockItemId));
    }

    public function scan(array $input)
    {
        $branchId = $this->branchId($input);
        $this->ensureBranch($branchId);
        $scan = $this->model->scanProduct($this->businessId, $branchId, (string)($input['code'] ?? ''));
        if (!$scan) {
            return array('success' => false, 'message' => 'No active product or bill found for this code.');
        }
        return array('success' => true, 'scan' => $scan);
    }

    public function searchCustomers(array $input)
    {
        return array(
            'success' => true,
            'customers' => $this->model->searchCustomers($this->businessId, (string)($input['q'] ?? $input['search'] ?? ''), (int)($input['limit'] ?? 5)),
        );
    }

    public function saveCustomer(array $payload)
    {
        $customer = $this->model->saveCustomer($this->businessId, $payload);
        return array('success' => true, 'message' => 'Customer saved.', 'customer' => $customer);
    }

    public function saveBill(array $payload)
    {
        $branchId = (int)($payload['branch_id'] ?? 0);
        $this->ensureBranch($branchId);
        $saved = $this->model->saveBillFromPayload($this->businessId, $branchId, $this->userId, $payload);
        return array(
            'success' => true,
            'message' => 'Bill saved as Pending. Print the invoice and collect payment from Pending Bills using barcode scan.',
            'saved' => array('bill_id' => $saved['bill_id'], 'bill' => $saved),
            'bill_history' => $this->model->billHistory($this->businessId, $branchId, 'all'),
            'next_bill_no' => $this->model->displayNextBillNo($this->businessId),
            'next_bill_barcode' => $this->model->displayNextBillBarcode($this->businessId),
        );
    }

    public function saveWorkflow(array $payload, $type)
    {
        $branchId = (int)($payload['branch_id'] ?? 0);
        $this->ensureBranch($branchId);
        $workflow = $this->model->saveWorkflow($this->businessId, $branchId, $this->userId, (string)$type, $payload);
        return array(
            'success' => true,
            'message' => $type === 'draft' ? 'Bill saved as draft.' : ($type === 'cancelled' ? 'Bill cancelled and added to history.' : 'Bill held successfully.'),
            'workflow' => $workflow,
            'held_bills' => $this->model->billHistory($this->businessId, $branchId, 'hold'),
            'history' => $this->model->billHistory($this->businessId, $branchId, 'all'),
        );
    }

    public function resumeWorkflow(array $input)
    {
        $branchId = $this->branchId($input);
        $this->ensureBranch($branchId);
        $workflowId = (int)($input['workflow_id'] ?? $input['hold_id'] ?? 0);
        $row = $this->model->getWorkflow($this->businessId, $branchId, $workflowId);
        if (!$row) {
            return array('success' => false, 'message' => 'Bill history entry not found.');
        }
        $payload = json_decode((string)($row['hold_data'] ?? '{}'), true);
        if (!is_array($payload)) { $payload = array(); }
        $payload['hold_id'] = $workflowId;
        $payload['workflow_id'] = $workflowId;
        return array('success' => true, 'hold_data' => $payload, 'workflow' => $row);
    }

    public function cancelWorkflow(array $payload)
    {
        $branchId = (int)($payload['branch_id'] ?? 0);
        $this->ensureBranch($branchId);
        $workflowId = (int)($payload['workflow_id'] ?? $payload['hold_id'] ?? 0);
        $this->model->cancelWorkflow($this->businessId, $branchId, $workflowId, $this->userId);
        return array('success' => true, 'message' => 'Bill history entry cancelled.', 'history' => $this->model->billHistory($this->businessId, $branchId, 'all'));
    }

    public function history(array $input)
    {
        $branchId = $this->branchId($input);
        $this->ensureBranch($branchId);
        $filter = (string)($input['status_filter'] ?? $input['filter'] ?? 'all');
        $q = (string)($input['q'] ?? $input['search'] ?? '');
        return array('success' => true, 'history' => $this->model->billHistory($this->businessId, $branchId, $filter, $q));
    }

    public function cancelSavedBill(array $payload)
    {
        $branchId = (int)($payload['branch_id'] ?? 0);
        $this->ensureBranch($branchId);
        $billId = (int)($payload['bill_id'] ?? 0);
        $this->model->cancelSavedBill($this->businessId, $branchId, $billId, $this->userId, (string)($payload['reason'] ?? ''));
        return array('success' => true, 'message' => 'Bill cancelled and stock restored.', 'history' => $this->model->billHistory($this->businessId, $branchId, 'all'));
    }

    public function returnSavedBill(array $payload)
    {
        $branchId = (int)($payload['branch_id'] ?? 0);
        $this->ensureBranch($branchId);
        $billId = (int)($payload['bill_id'] ?? 0);
        $this->model->returnSavedBill($this->businessId, $branchId, $billId, $this->userId, (string)($payload['reason'] ?? ''));
        return array('success' => true, 'message' => 'Bill returned/cancelled and stock restored.', 'history' => $this->model->billHistory($this->businessId, $branchId, 'all'));
    }

    public function validateOffer(array $input)
    {
        $offer = $this->model->validateOffer($this->businessId, (string)($input['code'] ?? ''));
        if (!$offer) { return array('success' => false, 'message' => 'Invalid or expired offer code.'); }
        return array('success' => true, 'offer' => $offer);
    }
}
