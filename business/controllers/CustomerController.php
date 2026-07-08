<?php
require_once dirname(__DIR__) . '/models/Customer.php';

class CustomerController
{
    private $model;

    public function __construct(mysqli $conn, int $businessId, int $userId = 0)
    {
        $this->model = new Customer($conn, $businessId, $userId);
    }

    public function get(array $query): array
    {
        $action = (string)($query['action'] ?? 'list');
        if ($action === 'list') {
            return [
                'success' => true,
                'stats' => $this->model->stats(),
                'customers' => $this->model->list($query)
            ];
        }

        if ($action === 'get' || $action === 'details' || $action === 'purchases') {
            $customerId = (int)($query['customer_id'] ?? 0);
            if ($customerId <= 0) {
                return ['success' => false, 'message' => 'Customer id is required.'];
            }
            $customer = $this->model->get($customerId);
            if (!$customer) {
                return ['success' => false, 'message' => 'Customer not found.'];
            }
            return [
                'success' => true,
                'customer' => $customer,
                'bills' => $this->model->bills($customerId),
                'ledger' => $this->model->ledger($customerId),
                'purchased_articles' => $this->model->purchasedArticles($customerId)
            ];
        }

        return ['success' => false, 'message' => 'Invalid API action.'];
    }

    public function post(array $post): array
    {
        $action = (string)($post['action'] ?? '');
        if ($action === 'save_customer') {
            return $this->model->save($post);
        }
        if ($action === 'toggle_status') {
            return $this->model->toggleStatus((int)($post['customer_id'] ?? 0));
        }
        if ($action === 'delete_customer') {
            return $this->model->delete((int)($post['customer_id'] ?? 0));
        }
        return ['success' => false, 'message' => 'Invalid API action.'];
    }
}
