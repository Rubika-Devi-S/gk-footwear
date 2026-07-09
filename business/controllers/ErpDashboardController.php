<?php
/**
 * GK Footwear POS - ERP Dashboard Controller
 * Place this file at: controllers/ErpDashboardController.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/ErpDashboard.php';

class ErpDashboardController
{
    private $model;
    private $businessId;
    private $userId;
    private $isAdmin;

    public function __construct(mysqli $conn, int $businessId, int $userId = 0, bool $isAdmin = false)
    {
        $this->model = new ErpDashboard($conn, $businessId, $userId, $isAdmin);
        $this->businessId = $businessId;
        $this->userId = $userId;
        $this->isAdmin = $isAdmin;
    }

    public function summary(array $input): array
    {
        $filters = $this->normaliseFilters($input);
        return $this->model->dashboardSummary($filters);
    }

    private function normaliseFilters(array $input): array
    {
        $period = strtolower(trim((string)($input['period'] ?? 'month')));
        $allowed = ['today', 'month', '30', 'custom'];
        if (!in_array($period, $allowed, true)) {
            $period = 'month';
        }

        $today = new DateTime('today');
        $from = clone $today;
        $to = clone $today;

        if ($period === 'today') {
            $from = clone $today;
            $to = clone $today;
        } elseif ($period === '30') {
            $from = (clone $today)->modify('-29 days');
            $to = clone $today;
        } elseif ($period === 'custom') {
            $fromInput = trim((string)($input['date_from'] ?? ''));
            $toInput = trim((string)($input['date_to'] ?? ''));
            $from = $this->safeDate($fromInput) ?: (clone $today)->modify('first day of this month');
            $to = $this->safeDate($toInput) ?: clone $today;
        } else {
            $from = (clone $today)->modify('first day of this month');
            $to = clone $today;
        }

        if ($from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period, $from, $to),
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'branch_id' => max(0, (int)($input['branch_id'] ?? 0)),
        ];
    }

    private function safeDate(string $value): ?DateTime
    {
        if ($value === '') {
            return null;
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date instanceof DateTime ? $date : null;
    }

    private function periodLabel(string $period, DateTime $from, DateTime $to): string
    {
        if ($period === 'today') {
            return 'Today';
        }
        if ($period === '30') {
            return 'Last 30 Days';
        }
        if ($period === 'month') {
            return 'This Month';
        }
        return $from->format('d M Y') . ' - ' . $to->format('d M Y');
    }
}
