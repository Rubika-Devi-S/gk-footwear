<?php
/**
 * GK Footwear POS - Bill Collection Controller
 */

require_once __DIR__ . '/../models/BillCollection.php';

class BillCollectionController
{
    private $model;
    private $businessId;
    private $userId;
    private $isAdmin;

    public function __construct(mysqli $conn, $businessId, $userId, $isAdmin)
    {
        $this->model = new BillCollection($conn);
        $this->businessId = (int)$businessId;
        $this->userId = (int)$userId;
        $this->isAdmin = (bool)$isAdmin;
    }

    private function assertSession()
    {
        if ($this->businessId <= 0) { throw new Exception('Business session missing. Please login again.'); }
        if ($this->userId <= 0) { throw new Exception('User session missing. Please login again.'); }
    }

    public function init(array $input = array())
    {
        $this->assertSession();
        return array(
            'success' => true,
            'branches' => $this->model->getBranches($this->businessId, $this->userId, $this->isAdmin),
            'payment_methods' => $this->model->getPaymentMethods($this->businessId),
            'stats' => $this->model->stats($this->businessId, $this->userId, $this->isAdmin, $input),
            'bills' => $this->model->searchPendingBills($this->businessId, $this->userId, $this->isAdmin, array('limit' => 20)),
            'recent_transactions' => $this->model->recentTransactions($this->businessId, $this->userId, $this->isAdmin, array('limit' => 12)),
        );
    }

    public function searchBills(array $input)
    {
        $this->assertSession();
        return array(
            'success' => true,
            'bills' => $this->model->searchPendingBills($this->businessId, $this->userId, $this->isAdmin, $input),
            'stats' => $this->model->stats($this->businessId, $this->userId, $this->isAdmin, $input),
        );
    }

    public function getBill(array $input)
    {
        $this->assertSession();
        $billId = (int)($input['bill_id'] ?? 0);
        $detail = $this->model->getBill($this->businessId, $this->userId, $this->isAdmin, $billId);
        return array(
            'success' => true,
            'bill' => $detail['bill'],
            'items' => $detail['items'],
            'payments' => $detail['payments'],
        );
    }

    public function collectPayment(array $payload)
    {
        $this->assertSession();
        $saved = $this->model->collectPayment($this->businessId, $this->userId, $this->isAdmin, $payload);
        $detail = $this->model->getBill($this->businessId, $this->userId, $this->isAdmin, (int)$saved['bill_id']);
        return array(
            'success' => true,
            'message' => 'Payment collected successfully.',
            'payment' => $saved,
            'bill' => $detail['bill'],
            'items' => $detail['items'],
            'payments' => $detail['payments'],
            'stats' => $this->model->stats($this->businessId, $this->userId, $this->isAdmin, $payload),
            'recent_transactions' => $this->model->recentTransactions($this->businessId, $this->userId, $this->isAdmin, array('limit' => 12)),
        );
    }

    public function recentTransactions(array $input)
    {
        $this->assertSession();
        return array(
            'success' => true,
            'recent_transactions' => $this->model->recentTransactions($this->businessId, $this->userId, $this->isAdmin, $input),
        );
    }
}
