<?php

class PaymentMethodController
{
    private $conn;
    private $businessId;

    public function __construct(mysqli $conn, $businessId)
    {
        $this->conn = $conn;
        $this->businessId = (int)$businessId;
    }

    public function handle()
    {
        if ($this->businessId <= 0) {
            $this->response(false, 'Business session missing. Please login again.', [], 401);
        }

        $action = $_POST['action'] ?? $_GET['action'] ?? 'list';

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->postCsrfCheck();
            }

            if ($action === 'list') {
                $this->list();
            }

            if ($action === 'get') {
                $this->get();
            }

            if ($action === 'save_payment_method') {
                $this->save();
            }

            if ($action === 'toggle_status') {
                $this->toggleStatus();
            }

            if ($action === 'delete_payment_method') {
                $this->delete();
            }

            if ($action === 'seed_defaults') {
                $this->seedDefaults();
            }

            $this->response(false, 'Invalid API action.', [], 400);
        } catch (InvalidArgumentException $e) {
            $this->response(false, $e->getMessage(), [], 422);
        } catch (RuntimeException $e) {
            $this->response(false, $e->getMessage(), [], 409);
        } catch (Throwable $e) {
            $this->response(false, 'Server error: ' . $e->getMessage(), [], 500);
        }
    }

    private function response($success, $message = '', array $data = [], $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $data), JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function postCsrfCheck()
    {
        if (function_exists('verify_csrf')) {
            verify_csrf();
            return;
        }

        if (function_exists('csrf_verify')) {
            csrf_verify();
            return;
        }

        if (function_exists('check_csrf')) {
            check_csrf();
            return;
        }
    }

    private function currentUserId()
    {
        if (function_exists('current_user_id')) {
            $id = (int) current_user_id();
            return $id > 0 ? $id : null;
        }

        $id = (int)($_SESSION['user_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    private function currentRoleId()
    {
        if (function_exists('current_role_id')) {
            $id = (int) current_role_id();
            return $id > 0 ? $id : null;
        }

        $id = (int)($_SESSION['role_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    private function currentBranchId()
    {
        if (function_exists('current_branch_id')) {
            $id = (int) current_branch_id();
            return $id > 0 ? $id : null;
        }

        $id = (int)($_SESSION['branch_id'] ?? ($_SESSION['default_branch_id'] ?? 0));
        return $id > 0 ? $id : null;
    }

    private function activityLog($actionType, $recordId, $oldValue = null, $newValue = null)
    {
        $logTable = null;

        if (PaymentMethod::tableExists($this->conn, 'business_activity_logs')) {
            $logTable = 'business_activity_logs';
        } elseif (PaymentMethod::tableExists($this->conn, 'activity_logs')) {
            $logTable = 'activity_logs';
        }

        if ($logTable === null) {
            return;
        }

        $userId = $this->currentUserId();
        $roleId = $this->currentRoleId();
        $branchId = $this->currentBranchId();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $deviceDetails = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $oldJson = $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;

        $sql = "
            INSERT INTO {$logTable}
                (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details, created_at)
            VALUES
                (?, ?, ?, ?, 'Payment Methods', ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'iiiisissss',
            $this->businessId,
            $branchId,
            $userId,
            $roleId,
            $actionType,
            $recordId,
            $oldJson,
            $newJson,
            $ipAddress,
            $deviceDetails
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    private function list()
    {
        $search = trim($_GET['search'] ?? '');
        $methodType = strtolower(trim($_GET['method_type'] ?? ''));
        $status = $_GET['status'] ?? '';

        $methods = PaymentMethod::list($this->conn, $this->businessId, $search, $methodType, $status);
        $stats = PaymentMethod::stats($this->conn, $this->businessId);

        $this->response(true, '', [
            'payment_methods' => $methods,
            'stats' => $stats,
            'allowed_types' => PaymentMethod::allowedTypeLabels(),
        ]);
    }

    private function get()
    {
        $paymentMethodId = (int)($_GET['payment_method_id'] ?? 0);

        if ($paymentMethodId <= 0) {
            $this->response(false, 'Invalid payment method selected.', [], 422);
        }

        $method = PaymentMethod::getById($this->conn, $this->businessId, $paymentMethodId);

        if (!$method) {
            $this->response(false, 'Payment method not found.', [], 404);
        }

        $method['method_type_label'] = PaymentMethod::typeLabel($method['method_type']);
        $method['used_count'] = PaymentMethod::usageCount($this->conn, $this->businessId, (int)$method['payment_method_id']);

        $this->response(true, '', ['payment_method' => $method]);
    }

    private function save()
    {
        $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);
        $data = PaymentMethod::validate($_POST);

        if ($paymentMethodId > 0) {
            $oldMethod = PaymentMethod::getById($this->conn, $this->businessId, $paymentMethodId);

            if (!$oldMethod) {
                $this->response(false, 'Payment method not found.', [], 404);
            }

            PaymentMethod::update($this->conn, $this->businessId, $paymentMethodId, $data);

            $this->activityLog('update', $paymentMethodId, $oldMethod, $data);

            $this->response(true, 'Payment method updated successfully.', [
                'payment_method_id' => $paymentMethodId,
            ]);
        }

        $newPaymentMethodId = PaymentMethod::create($this->conn, $this->businessId, $data);

        $this->activityLog('create', $newPaymentMethodId, null, $data);

        $this->response(true, 'Payment method created successfully.', [
            'payment_method_id' => $newPaymentMethodId,
        ]);
    }

    private function toggleStatus()
    {
        $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);

        if ($paymentMethodId <= 0) {
            $this->response(false, 'Invalid payment method selected.', [], 422);
        }

        $oldMethod = PaymentMethod::getById($this->conn, $this->businessId, $paymentMethodId);

        if (!$oldMethod) {
            $this->response(false, 'Payment method not found.', [], 404);
        }

        $newStatus = ((int)$oldMethod['status'] === 1) ? 0 : 1;

        PaymentMethod::toggleStatus($this->conn, $this->businessId, $paymentMethodId, $newStatus);

        $this->activityLog($newStatus === 1 ? 'activate' : 'deactivate', $paymentMethodId, $oldMethod, [
            'status' => $newStatus,
        ]);

        $this->response(true, 'Payment method status updated successfully.', [
            'status' => $newStatus,
        ]);
    }

    private function delete()
    {
        $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);

        if ($paymentMethodId <= 0) {
            $this->response(false, 'Invalid payment method selected.', [], 422);
        }

        $oldMethod = PaymentMethod::getById($this->conn, $this->businessId, $paymentMethodId);

        if (!$oldMethod) {
            $this->response(false, 'Payment method not found.', [], 404);
        }

        if (PaymentMethod::isUsed($this->conn, $this->businessId, $paymentMethodId)) {
            $this->response(false, 'This payment method is already used in bills, cashier collection, or payment ledger. Please deactivate instead of deleting.', [], 409);
        }

        PaymentMethod::delete($this->conn, $this->businessId, $paymentMethodId);

        $this->activityLog('delete', $paymentMethodId, $oldMethod, null);

        $this->response(true, 'Payment method deleted successfully.');
    }

    private function seedDefaults()
    {
        PaymentMethod::seedDefaults($this->conn, $this->businessId);

        $this->activityLog('seed_defaults', 0, null, [
            'methods' => PaymentMethod::allowedTypeLabels(),
        ]);

        $this->response(true, 'Default payment methods verified successfully.');
    }
}
