<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// =====================================================
// 1. HÀM HỖ TRỢ
// =====================================================

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatMoney($amount): string
{
    return number_format((float) $amount, 0, ',', '.') . ' đ';
}

function percent($value): float
{
    return max(0, min(100, (float) $value));
}

function formatDuration($minutes): string
{
    $minutes = max(0, (int) $minutes);

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($hours > 0) {
        return $hours . ' giờ ' . $remainingMinutes . ' phút';
    }

    return $remainingMinutes . ' phút';
}

// =====================================================
// 2. THỐNG KÊ TỔNG QUAN
// =====================================================

$totalCustomers = (int) $pdo->query(
    "SELECT COUNT(*) FROM customers"
)->fetchColumn();

$totalDevices = (int) $pdo->query(
    "SELECT COUNT(*) FROM devices"
)->fetchColumn();

$totalTickets = (int) $pdo->query(
    "SELECT COUNT(*) FROM repair_tickets"
)->fetchColumn();

$openTickets = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM repair_tickets
     WHERE status NOT IN ('delivered', 'cancelled')"
)->fetchColumn();

$totalInvoices = (int) $pdo->query(
    "SELECT COUNT(*) FROM invoices"
)->fetchColumn();

$paidInvoices = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM invoices
     WHERE payment_status = 'paid'"
)->fetchColumn();

$unpaidInvoices = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM invoices
     WHERE payment_status = 'unpaid'"
)->fetchColumn();

$totalTechnicians = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM users
     WHERE role = 'technician'
       AND is_active = 1"
)->fetchColumn();

// =====================================================
// 3. THỐNG KÊ KHO LINH KIỆN
// =====================================================

$totalPartsQuantity = (int) $pdo->query(
    "SELECT COALESCE(SUM(quantity), 0)
     FROM parts"
)->fetchColumn();

$outOfStockCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM parts
     WHERE quantity <= 0"
)->fetchColumn();

$lowStockCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM parts
     WHERE quantity > 0
       AND quantity <= min_quantity"
)->fetchColumn();

$outOfStockParts = $pdo->query(
    "SELECT
        id,
        part_name,
        sku,
        quantity,
        min_quantity
     FROM parts
     WHERE quantity <= 0
     ORDER BY part_name ASC
     LIMIT 10"
)->fetchAll();

$lowStockParts = $pdo->query(
    "SELECT
        id,
        part_name,
        sku,
        quantity,
        min_quantity
     FROM parts
     WHERE quantity > 0
       AND quantity <= min_quantity
     ORDER BY
        (quantity / NULLIF(min_quantity, 0)) ASC,
        quantity ASC,
        part_name ASC
     LIMIT 10"
)->fetchAll();

// =====================================================
// 4. PHIẾU CHƯA PHÂN CÔNG
// =====================================================

$unassignedTicketCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM repair_tickets
     WHERE assigned_to IS NULL
       AND status NOT IN ('delivered', 'cancelled')"
)->fetchColumn();

// =====================================================
// 5. THỐNG KÊ TÀI CHÍNH
// =====================================================

$databaseDate = $pdo->query(
    "SELECT
        CURDATE() AS db_current_date,
        DATE_FORMAT(CURDATE(), '%Y-%m-01') AS month_start,
        DATE_FORMAT(
            DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
            '%Y-%m-01'
        ) AS next_month_start"
)->fetch();

$currentDate = $databaseDate['db_current_date'];
$monthStart = $databaseDate['month_start'];
$nextMonthStart = $databaseDate['next_month_start'];

$totalPaidRevenue = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0)
     FROM invoices
     WHERE payment_status = 'paid'"
)->fetchColumn();

$totalUnpaid = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0)
     FROM invoices
     WHERE payment_status = 'unpaid'"
)->fetchColumn();

// Doanh thu hôm nay
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_amount), 0)
     FROM invoices
     WHERE payment_status = 'paid'
       AND created_at >= :start_date
       AND created_at < DATE_ADD(:end_date, INTERVAL 1 DAY)"
);

$stmt->execute([
    ':start_date' => $currentDate,
    ':end_date' => $currentDate
]);

$revenueToday = (float) $stmt->fetchColumn();

// Doanh thu tháng này
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_amount), 0)
     FROM invoices
     WHERE payment_status = 'paid'
       AND created_at >= :month_start
       AND created_at < :next_month_start"
);

$stmt->execute([
    ':month_start' => $monthStart,
    ':next_month_start' => $nextMonthStart
]);

$revenueThisMonth = (float) $stmt->fetchColumn();

$paidInvoicePercent = $totalInvoices > 0
    ? round(($paidInvoices / $totalInvoices) * 100)
    : 0;

// =====================================================
// 6. TRẠNG THÁI PHIẾU SỬA CHỮA
// =====================================================

$statusLabels = [
    'received' => 'Đã tiếp nhận',
    'checking' => 'Đang kiểm tra',
    'waiting_parts' => 'Chờ linh kiện',
    'repairing' => 'Đang sửa chữa',
    'completed' => 'Đã sửa xong',
    'delivered' => 'Đã bàn giao',
    'cancelled' => 'Đã hủy'
];

$statusCounts = $pdo->query(
    "SELECT status, COUNT(*) AS total
     FROM repair_tickets
     GROUP BY status
     ORDER BY total DESC"
)->fetchAll();

// =====================================================
// 7. THỐNG KÊ CÔNG VIỆC TỒN ĐỌNG
// =====================================================

$activeStatuses = [
    'received',
    'checking',
    'waiting_parts',
    'repairing',
    'completed'
];

$sqlBacklog = "
    SELECT
        status,
        COUNT(*) AS total
    FROM repair_tickets
    WHERE status IN (
        'received',
        'checking',
        'waiting_parts',
        'repairing',
        'completed'
    )
    GROUP BY status
";

$backlogRows = $pdo->query($sqlBacklog)->fetchAll();

$backlogCounts = array_fill_keys($activeStatuses, 0);

foreach ($backlogRows as $row) {
    $backlogCounts[$row['status']] = (int) $row['total'];
}

$backlogTicketCount = array_sum($backlogCounts);

$overdueCondition = "
    rt.status IN (
        'received',
        'checking',
        'waiting_parts',
        'repairing',
        'completed'
    )
    AND rt.updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
";

$sqlOverdueCount = "
    SELECT COUNT(*)
    FROM repair_tickets rt
    WHERE $overdueCondition
";

$overdueTicketCount = (int) $pdo->query(
    $sqlOverdueCount
)->fetchColumn();

$sqlAttentionTickets = "
    SELECT
        rt.id,
        rt.ticket_code,
        rt.status,
        rt.updated_at,
        c.full_name AS customer_name,
        d.device_type,
        d.brand,
        d.model,
        u.full_name AS technician_name
    FROM repair_tickets rt
    INNER JOIN customers c
        ON c.id = rt.customer_id
    INNER JOIN devices d
        ON d.id = rt.device_id
    LEFT JOIN users u
        ON u.id = rt.assigned_to
    WHERE $overdueCondition
    ORDER BY rt.updated_at ASC
    LIMIT 20
";

$attentionTickets = $pdo->query(
    $sqlAttentionTickets
)->fetchAll();

$attentionTicketCount = $overdueTicketCount;

// =====================================================
// 8. DOANH THU 6 THÁNG GẦN NHẤT
// =====================================================

$sqlMonthlyRevenue = "
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        SUM(total_amount) AS revenue
    FROM invoices
    WHERE payment_status = 'paid'
      AND created_at >= DATE_SUB(
          :month_start,
          INTERVAL 5 MONTH
      )
      AND created_at < :next_month_start
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month_key ASC
";

$stmt = $pdo->prepare($sqlMonthlyRevenue);

$stmt->execute([
    ':month_start' => $monthStart,
    ':next_month_start' => $nextMonthStart
]);

$revenueRows = $stmt->fetchAll();

$revenueByMonth = [];

foreach ($revenueRows as $row) {
    $revenueByMonth[$row['month_key']] = (float) $row['revenue'];
}

$monthlyRevenue = [];
$maxRevenue = 0;

$startMonth = DateTime::createFromFormat(
    '!Y-m-d',
    $monthStart
);

$startMonth->modify('-5 months');

for ($i = 0; $i < 6; $i++) {

    $monthKey = $startMonth->format('Y-m');
    $revenue = $revenueByMonth[$monthKey] ?? 0;

    $monthlyRevenue[] = [
        'label' => $startMonth->format('m/Y'),
        'revenue' => $revenue
    ];

    $maxRevenue = max($maxRevenue, $revenue);

    $startMonth->modify('+1 month');
}

// =====================================================
// 9. HIỆU SUẤT NHÂN VIÊN KỸ THUẬT
// =====================================================

$sqlTechnicians = "
    SELECT
        u.id,
        u.full_name,

        COUNT(rt.id) AS assigned_tickets,

        COALESCE(
            SUM(
                CASE
                    WHEN rt.status IN (
                        'received',
                        'checking',
                        'waiting_parts',
                        'repairing',
                        'completed'
                    )
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS processing_tickets,

        COALESCE(
            SUM(
                CASE
                    WHEN rt.status = 'delivered'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS delivered_tickets

    FROM users u

    LEFT JOIN repair_tickets rt
        ON rt.assigned_to = u.id
        AND rt.received_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        AND rt.received_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        AND rt.status <> 'cancelled'

    WHERE u.role = 'technician'
      AND u.is_active = 1

    GROUP BY u.id, u.full_name

    ORDER BY assigned_tickets DESC, u.full_name ASC
";

$technicians = $pdo->query(
    $sqlTechnicians
)->fetchAll();

// =====================================================
// 10. TỶ LỆ PHIẾU ĐÃ BÀN GIAO
// =====================================================

$validTickets = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM repair_tickets
     WHERE status <> 'cancelled'"
)->fetchColumn();

$deliveredTickets = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM repair_tickets
     WHERE status = 'delivered'"
)->fetchColumn();

$deliveredPercent = $validTickets > 0
    ? round(($deliveredTickets / $validTickets) * 100)
    : 0;

$remainingTickets = max(
    0,
    $validTickets - $deliveredTickets
);

// =====================================================
// 11. PHÂN TÍCH THỜI GIAN XỬ LÝ PHIẾU
// =====================================================

$deliveryHistorySQL = "
    SELECT
        ticket_id,
        MAX(created_at) AS delivered_at
    FROM ticket_status_history
    WHERE new_status = 'delivered'
    GROUP BY ticket_id
";

$sqlProcessingStats = "
    SELECT
        COUNT(*) AS total_delivered,

        COALESCE(
            AVG(
                TIMESTAMPDIFF(
                    MINUTE,
                    rt.received_at,
                    history.delivered_at
                )
            ),
            0
        ) AS average_minutes,

        COALESCE(
            MAX(
                TIMESTAMPDIFF(
                    MINUTE,
                    rt.received_at,
                    history.delivered_at
                )
            ),
            0
        ) AS longest_minutes

    FROM repair_tickets rt

    INNER JOIN ($deliveryHistorySQL) history
        ON history.ticket_id = rt.id

    WHERE history.delivered_at >= rt.received_at
";

$processingStats = $pdo->query(
    $sqlProcessingStats
)->fetch();

$totalDeliveredWithHistory = (int) $processingStats['total_delivered'];

$averageProcessingMinutes = (int) round(
    $processingStats['average_minutes']
);

$longestProcessingMinutes = (int) $processingStats['longest_minutes'];

$sqlLongestTickets = "
    SELECT
        rt.id,
        rt.ticket_code,
        rt.received_at,
        history.delivered_at,

        TIMESTAMPDIFF(
            MINUTE,
            rt.received_at,
            history.delivered_at
        ) AS processing_minutes

    FROM repair_tickets rt

    INNER JOIN ($deliveryHistorySQL) history
        ON history.ticket_id = rt.id

    WHERE history.delivered_at >= rt.received_at

    ORDER BY processing_minutes DESC

    LIMIT 5
";

$longestTickets = $pdo->query(
    $sqlLongestTickets
)->fetchAll();

// =====================================================
// 12. TỔNG HỢP CẢNH BÁO
// =====================================================

$totalWarnings =
    $unassignedTicketCount +
    $outOfStockCount +
    $lowStockCount;

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tổng quan hệ thống</title>

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --navy: #172554;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --background: #f5f7fb;
            --white: #ffffff;
            --green: #16a34a;
            --orange: #ea580c;
            --red: #dc2626;
        }

        body {
            margin: 0;
            padding: 26px;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--background);
            color: var(--text);
            font-size: 14px;
        }

        a {
            color: inherit;
        }

        /* HEADER */

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 13px;
            background: #dbeafe;
            color: var(--primary);
        }

        .header-icon svg {
            width: 24px;
            height: 24px;
        }

        .page-title {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 25px;
            font-weight: 700;
        }

        .page-description {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .refresh-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 15px;
            border: 1px solid #bfdbfe;
            border-radius: 9px;
            background: #eff6ff;
            color: var(--primary-dark);
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .refresh-link:hover {
            background: #dbeafe;
            border-color: #93c5fd;
        }

        .refresh-link svg {
            width: 16px;
            height: 16px;
        }

        /* SECTION */

        .section-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 30px 0 15px;
        }

        .section-heading svg {
            width: 19px;
            height: 19px;
            color: var(--primary);
        }

        .section-heading h2 {
            margin: 0;
            color: var(--navy);
            font-size: 16px;
            font-weight: 700;
        }

        /* STAT CARDS */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .stat-card {
            --accent: #2563eb;

            position: relative;
            display: block;
            min-width: 0;
            padding: 18px;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--white);
            box-shadow: 0 2px 8px rgba(15, 23, 42, .035);
            text-decoration: none;
            transition: transform .2s, box-shadow .2s;
        }

        .stat-card::after {
            position: absolute;
            top: 0;
            right: 0;
            width: 65px;
            height: 65px;
            border-radius: 0 0 0 65px;
            background: var(--accent);
            opacity: .055;
            content: "";
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, .075);
        }

        .stat-card.blue { --accent: #2563eb; }
        .stat-card.cyan { --accent: #0891b2; }
        .stat-card.orange { --accent: #ea580c; }
        .stat-card.green { --accent: #16a34a; }
        .stat-card.purple { --accent: #7c3aed; }
        .stat-card.red { --accent: #dc2626; }
        .stat-card.teal { --accent: #0d9488; }
        .stat-card.gray { --accent: #64748b; }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-label {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 39px;
            height: 39px;
            flex-shrink: 0;
            border-radius: 10px;
            background: color-mix(in srgb, var(--accent) 10%, white);
            color: var(--accent);
        }

        .stat-icon svg {
            width: 19px;
            height: 19px;
        }

        .stat-card h2 {
            margin: 0;
            color: var(--navy);
            font-size: 26px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .stat-card .money {
            font-size: 19px;
        }

        .stat-note {
            display: block;
            margin-top: 9px;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.5;
        }

        .stat-action {
            display: block;
            margin-top: 10px;
            color: var(--accent);
            font-size: 11px;
            font-weight: 700;
        }

        /* WARNING CARDS */

        .warning-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .warning-card {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--white);
            text-decoration: none;
            transition: .2s;
        }

        .warning-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(15, 23, 42, .06);
        }

        .warning-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            border-radius: 11px;
        }

        .warning-icon svg {
            width: 20px;
            height: 20px;
        }

        .warning-icon.red {
            background: #fef2f2;
            color: #dc2626;
        }

        .warning-icon.orange {
            background: #fff7ed;
            color: #ea580c;
        }

        .warning-icon.blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .warning-info {
            min-width: 0;
        }

        .warning-info strong {
            display: block;
            margin-bottom: 5px;
            color: var(--navy);
            font-size: 21px;
        }

        .warning-info span {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        /* PANEL */

        .panel {
            min-width: 0;
            padding: 21px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--white);
            box-shadow: 0 2px 8px rgba(15, 23, 42, .035);
        }

        .panel h3,
        .stock-alert-panel h3 {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0 0 16px;
            color: var(--navy);
            font-size: 14px;
            font-weight: 700;
        }

        .panel h3 svg,
        .stock-alert-panel h3 svg {
            width: 19px;
            height: 19px;
            flex-shrink: 0;
            color: var(--primary);
        }

        .panel-description,
        .attention-note {
            margin: -5px 0 16px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .empty {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .empty-message {
            padding: 15px;
            border: 1px dashed var(--border);
            border-radius: 9px;
            background: #f8fafc;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        /* CONTENT GRID */

        .content-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
            margin-top: 17px;
        }

        /* BAR CHART */

        .chart-item {
            margin-bottom: 19px;
        }

        .chart-item:last-child {
            margin-bottom: 0;
        }

        .chart-info {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .chart-info strong {
            color: var(--text);
        }

        .chart-info span {
            color: var(--muted);
            text-align: right;
        }

        .bar-background {
            width: 100%;
            height: 10px;
            overflow: hidden;
            border-radius: 20px;
            background: #e8edf5;
        }

        .bar {
            height: 100%;
            border-radius: 20px;
            background: var(--primary);
        }

        .status-bar {
            background: #0891b2;
        }

        .paid-bar {
            background: #16a34a;
        }

        /* REVENUE CHART */

        .revenue-chart {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            align-items: end;
            gap: 12px;
            min-height: 220px;
            padding-top: 8px;
        }

        .revenue-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            min-width: 0;
            height: 210px;
            gap: 8px;
        }

        .revenue-value {
            min-height: 24px;
            color: var(--muted);
            font-size: 10px;
            text-align: center;
            overflow-wrap: anywhere;
        }

        .revenue-bar-area {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            width: 100%;
            height: 145px;
            border-bottom: 1px solid var(--border);
        }

        .revenue-bar {
            width: min(42px, 75%);
            min-height: 0;
            border-radius: 7px 7px 0 0;
            background: linear-gradient(180deg, #60a5fa, #2563eb);
            transition: height .3s;
        }

        .revenue-month {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .revenue-legend {
            margin: 13px 0 0;
            color: #94a3b8;
            font-size: 11px;
            text-align: center;
        }

        /* TABLE */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 450px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #edf1f7;
            text-align: left;
            font-size: 12px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            white-space: nowrap;
        }

        td {
            color: #475569;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .number {
            color: var(--primary);
            font-weight: 700;
        }

        .attention-table {
            min-width: 850px;
        }

        .attention-ticket-code {
            color: var(--primary);
            font-weight: 700;
            white-space: nowrap;
        }

        /* ATTENTION TICKETS */

        .attention-panel {
            margin-top: 18px;
        }

        .attention-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .attention-heading h3 {
            margin: 0;
        }

        .attention-count {
            padding: 6px 10px;
            border-radius: 20px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 700;
        }

        .attention-status {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 6px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .overdue-badge {
            display: inline-block;
            margin-top: 5px;
            padding: 5px 8px;
            border-radius: 6px;
            background: #fef2f2;
            color: #dc2626;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .attention-link {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 7px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            transition: .2s;
        }

        .attention-link:hover {
            background: var(--primary-dark);
        }

        .attention-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
        }

        .view-all-link {
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .view-all-link:hover {
            text-decoration: underline;
        }

        /* STOCK ALERT */

        .stock-alert-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
            margin-top: 17px;
        }

        .stock-alert-panel {
            min-width: 0;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--white);
            box-shadow: 0 2px 8px rgba(15, 23, 42, .035);
        }

        .stock-alert-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stock-alert-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #fff;
        }

        .stock-alert-name {
            min-width: 0;
        }

        .stock-alert-name strong {
            display: block;
            color: var(--text);
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .stock-alert-name span {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .stock-alert-quantity {
            flex-shrink: 0;
            text-align: right;
        }

        .stock-alert-quantity > strong {
            display: block;
            color: var(--red);
            font-size: 14px;
        }

        .stock-alert-quantity > span {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .stock-alert-action {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 9px;
            border-radius: 6px;
            background: #eff6ff;
            color: var(--primary);
            text-decoration: none;
            font-size: 10px;
            font-weight: 700;
        }

        .stock-alert-action:hover {
            background: #dbeafe;
        }

        .stock-alert-empty {
            padding: 12px;
            border-radius: 8px;
            background: #f8fafc;
            color: var(--muted);
            font-size: 12px;
        }

        .stock-priority {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 7px;
            border-radius: 5px;
            font-size: 9px;
            font-weight: 700;
        }

        .priority-critical {
            background: #fee2e2;
            color: #b91c1c !important;
        }

        .priority-high {
            background: #ffedd5;
            color: #c2410c !important;
        }

        .priority-medium {
            background: #fef3c7;
            color: #92400e !important;
        }

        .stock-restock-suggestion {
            display: block;
            margin-top: 6px;
            padding: 5px 7px;
            border-radius: 5px;
            background: #eff6ff;
            color: #1d4ed8 !important;
            font-size: 10px !important;
            font-weight: 700;
            white-space: nowrap;
        }

        /* COMPLETION */

        .completion-box {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .completion-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 118px;
            height: 118px;
            flex-shrink: 0;
            border-radius: 50%;
            background: conic-gradient(
                #16a34a <?php echo percent($deliveredPercent); ?>%,
                #e2e8f0 <?php echo percent($deliveredPercent); ?>%
            );
        }

        .completion-inner {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: white;
            color: var(--green);
            font-size: 22px;
            font-weight: 700;
        }

        .completion-text strong {
            color: var(--navy);
            font-size: 16px;
        }

        .completion-text p {
            margin: 7px 0;
            color: var(--muted);
            font-size: 12px;
        }

        /* RESPONSIVE */

        @media (max-width: 1100px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 750px) {
            body {
                padding: 17px;
            }

            .warning-grid,
            .stock-alert-grid {
                grid-template-columns: 1fr;
            }

            .revenue-chart {
                gap: 6px;
            }

            .revenue-value {
                font-size: 9px;
            }
        }

        @media (max-width: 500px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 21px;
            }

            .header-icon {
                width: 42px;
                height: 42px;
            }

            .panel,
            .stock-alert-panel {
                padding: 15px;
            }

            .completion-box {
                flex-direction: column;
                align-items: flex-start;
            }

            .revenue-month {
                font-size: 9px;
            }
        }
    </style>
</head>

<body>

    <!-- HEADER -->

    <header class="page-header">

        <div class="header-left">

            <div class="header-icon">
                <i data-lucide="layout-dashboard"></i>
            </div>

            <div>
                <h1 class="page-title">Tổng quan hệ thống</h1>

                <p class="page-description">
                    Theo dõi hoạt động, tài chính, nhân viên và tình trạng
                    vận hành của Computer Service.
                </p>
            </div>

        </div>

        <a href="tong_quan.php" class="refresh-link">
            <i data-lucide="refresh-cw"></i>
            Làm mới dữ liệu
        </a>

    </header>

    <!-- 1. TỔNG QUAN HOẠT ĐỘNG -->

    <div class="section-heading">
        <i data-lucide="layout-dashboard"></i>
        <h2>Tổng quan hoạt động</h2>
    </div>

    <section class="stats">

        <a href="khach_hang.php" target="mainContent" class="stat-card blue">
            <div class="stat-top">
                <p class="stat-label">Tổng khách hàng</p>
                <span class="stat-icon"><i data-lucide="users"></i></span>
            </div>
            <h2><?php echo number_format($totalCustomers); ?></h2>
            <span class="stat-action">Quản lý khách hàng →</span>
        </a>

        <a href="thiet_bi.php" target="mainContent" class="stat-card cyan">
            <div class="stat-top">
                <p class="stat-label">Tổng thiết bị</p>
                <span class="stat-icon"><i data-lucide="monitor"></i></span>
            </div>
            <h2><?php echo number_format($totalDevices); ?></h2>
            <span class="stat-action">Quản lý thiết bị →</span>
        </a>

        <a href="phieu_sua_chua.php" target="mainContent" class="stat-card orange">
            <div class="stat-top">
                <p class="stat-label">Tổng phiếu sửa chữa</p>
                <span class="stat-icon"><i data-lucide="clipboard-list"></i></span>
            </div>
            <h2><?php echo number_format($totalTickets); ?></h2>
            <span class="stat-action">Quản lý phiếu sửa chữa →</span>
        </a>

        <a href="phieu_sua_chua.php" target="mainContent" class="stat-card green">
            <div class="stat-top">
                <p class="stat-label">Phiếu chưa hoàn tất</p>
                <span class="stat-icon"><i data-lucide="clock"></i></span>
            </div>
            <h2><?php echo number_format($openTickets); ?></h2>
            <span class="stat-action">Theo dõi phiếu sửa chữa →</span>
        </a>

        <a href="linh_kien.php" target="mainContent" class="stat-card purple">
            <div class="stat-top">
                <p class="stat-label">Tổng số lượng linh kiện trong kho</p>
                <span class="stat-icon"><i data-lucide="package"></i></span>
            </div>
            <h2><?php echo number_format($totalPartsQuantity); ?></h2>
            <span class="stat-action">Quản lý kho linh kiện →</span>
        </a>

        <a href="nhan_vien.php" target="mainContent" class="stat-card red">
            <div class="stat-top">
                <p class="stat-label">Nhân viên kỹ thuật đang hoạt động</p>
                <span class="stat-icon"><i data-lucide="user-cog"></i></span>
            </div>
            <h2><?php echo number_format($totalTechnicians); ?></h2>
            <span class="stat-action">Quản lý nhân viên →</span>
        </a>

        <a href="hoa_don.php" target="mainContent" class="stat-card teal">
            <div class="stat-top">
                <p class="stat-label">Tổng số hóa đơn</p>
                <span class="stat-icon"><i data-lucide="receipt"></i></span>
            </div>
            <h2><?php echo number_format($totalInvoices); ?></h2>
            <span class="stat-action">Quản lý hóa đơn →</span>
        </a>

        <a href="hoa_don.php" target="mainContent" class="stat-card gray">
            <div class="stat-top">
                <p class="stat-label">Hóa đơn chưa thanh toán</p>
                <span class="stat-icon"><i data-lucide="file-clock"></i></span>
            </div>
            <h2><?php echo number_format($unpaidInvoices); ?></h2>
            <span class="stat-action">Kiểm tra hóa đơn →</span>
        </a>

    </section>

    <!-- 2. CẢNH BÁO -->

    <div class="section-heading">
        <i data-lucide="alert-triangle"></i>
        <h2>Cảnh báo cần xử lý</h2>
    </div>

    <section class="warning-grid">

        <a href="phieu_sua_chua.php" target="mainContent" class="warning-card">
            <span class="warning-icon red">
                <i data-lucide="user-x"></i>
            </span>
            <div class="warning-info">
                <strong><?php echo number_format($unassignedTicketCount); ?></strong>
                <span>Phiếu chưa phân công kỹ thuật viên</span>
            </div>
        </a>

        <a href="linh_kien.php" target="mainContent" class="warning-card">
            <span class="warning-icon red">
                <i data-lucide="package-x"></i>
            </span>
            <div class="warning-info">
                <strong><?php echo number_format($outOfStockCount); ?></strong>
                <span>Loại linh kiện đã hết hàng</span>
            </div>
        </a>

        <a href="linh_kien.php" target="mainContent" class="warning-card">
            <span class="warning-icon orange">
                <i data-lucide="alert-circle"></i>
            </span>
            <div class="warning-info">
                <strong><?php echo number_format($lowStockCount); ?></strong>
                <span>Loại linh kiện sắp hết hàng</span>
            </div>
        </a>

    </section>

    <!-- 3. TÀI CHÍNH -->

    <div class="section-heading">
        <i data-lucide="wallet"></i>
        <h2>Thống kê tài chính</h2>
    </div>

    <section class="stats">

        <div class="stat-card blue">
            <div class="stat-top">
                <p class="stat-label">Doanh thu hôm nay</p>
                <span class="stat-icon"><i data-lucide="calendar-check"></i></span>
            </div>
            <h2 class="money"><?php echo formatMoney($revenueToday); ?></h2>
            <span class="stat-note">Từ hóa đơn đã thanh toán tạo hôm nay</span>
        </div>

        <div class="stat-card cyan">
            <div class="stat-top">
                <p class="stat-label">Doanh thu tháng này</p>
                <span class="stat-icon"><i data-lucide="calendar-days"></i></span>
            </div>
            <h2 class="money"><?php echo formatMoney($revenueThisMonth); ?></h2>
            <span class="stat-note">Từ hóa đơn đã thanh toán trong tháng</span>
        </div>

        <div class="stat-card green">
            <div class="stat-top">
                <p class="stat-label">Tổng doanh thu đã thanh toán</p>
                <span class="stat-icon"><i data-lucide="banknote"></i></span>
            </div>
            <h2 class="money"><?php echo formatMoney($totalPaidRevenue); ?></h2>
            <span class="stat-note">Tổng tiền của các hóa đơn đã thanh toán</span>
        </div>

        <div class="stat-card orange">
            <div class="stat-top">
                <p class="stat-label">Tổng tiền chưa thanh toán</p>
                <span class="stat-icon"><i data-lucide="wallet-cards"></i></span>
            </div>
            <h2 class="money"><?php echo formatMoney($totalUnpaid); ?></h2>
            <span class="stat-note">Tổng tiền của các hóa đơn chưa thanh toán</span>
        </div>

        <div class="stat-card purple">
            <div class="stat-top">
                <p class="stat-label">Hóa đơn đã thanh toán</p>
                <span class="stat-icon"><i data-lucide="badge-check"></i></span>
            </div>
            <h2><?php echo number_format($paidInvoices); ?></h2>
            <span class="stat-note">Hóa đơn có trạng thái đã thanh toán</span>
        </div>

        <div class="stat-card red">
            <div class="stat-top">
                <p class="stat-label">Tỷ lệ thanh toán</p>
                <span class="stat-icon"><i data-lucide="chart-no-axes-combined"></i></span>
            </div>
            <h2><?php echo $paidInvoicePercent; ?>%</h2>
            <span class="stat-note">
                <?php echo number_format($paidInvoices); ?>
                / <?php echo number_format($totalInvoices); ?> hóa đơn
            </span>
        </div>

    </section>

    <!-- 4. DOANH THU 6 THÁNG -->

    <section class="panel attention-panel">

        <h3>
            <i data-lucide="chart-column-increasing"></i>
            Doanh thu 6 tháng gần nhất
        </h3>

        <p class="panel-description">
            Thống kê tổng tiền các hóa đơn đã thanh toán theo từng tháng.
        </p>

        <div class="revenue-chart">

            <?php foreach ($monthlyRevenue as $month): ?>

                <?php
                $revenue = (float) $month['revenue'];

                $barHeight = $maxRevenue > 0
                    ? ($revenue / $maxRevenue) * 100
                    : 0;
                ?>

                <div class="revenue-column">

                    <div class="revenue-value">
                        <?php
                        echo $revenue > 0
                            ? e(number_format($revenue / 1000000, 1, ',', '.') . ' tr')
                            : '0 đ';
                        ?>
                    </div>

                    <div class="revenue-bar-area">
                        <div
                            class="revenue-bar"
                            style="height: <?php echo $barHeight; ?>%;">
                        </div>
                    </div>

                    <span class="revenue-month">
                        <?php echo e($month['label']); ?>
                    </span>

                </div>

            <?php endforeach; ?>

        </div>

        <p class="revenue-legend">
            Đơn vị hiển thị trên biểu đồ: triệu đồng.
            Giá trị doanh thu được tính từ hóa đơn đã thanh toán.
        </p>

    </section>

    <!-- 5. CÔNG VIỆC TỒN ĐỌNG -->

    <div class="section-heading">
        <i data-lucide="clipboard-list"></i>
        <h2>Công việc tồn đọng</h2>
    </div>

    <section class="panel">

        <h3>
            <i data-lucide="layers"></i>
            Số phiếu theo trạng thái chưa hoàn tất
        </h3>

        <p class="panel-description">
            Không bao gồm phiếu đã bàn giao và phiếu đã hủy.
        </p>

        <div class="warning-grid">

            <?php foreach ($activeStatuses as $status): ?>

                <div class="warning-card">
                    <span class="warning-icon blue">
                        <i data-lucide="clipboard"></i>
                    </span>

                    <div class="warning-info">
                        <strong>
                            <?php echo number_format($backlogCounts[$status] ?? 0); ?>
                        </strong>
                        <span><?php echo e($statusLabels[$status]); ?></span>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>

        <div class="content-grid">

            <div class="warning-card">
                <span class="warning-icon blue">
                    <i data-lucide="layers"></i>
                </span>
                <div class="warning-info">
                    <strong><?php echo number_format($backlogTicketCount); ?></strong>
                    <span>Tổng phiếu chưa hoàn tất</span>
                </div>
            </div>

            <div class="warning-card">
                <span class="warning-icon red">
                    <i data-lucide="clock-alert"></i>
                </span>
                <div class="warning-info">
                    <strong><?php echo number_format($overdueTicketCount); ?></strong>
                    <span>Phiếu quá 3 ngày chưa cập nhật</span>
                </div>
            </div>

        </div>

    </section>

    <!-- 6. CHI TIẾT CẢNH BÁO KHO -->

    <div class="section-heading">
        <i data-lucide="package-search"></i>
        <h2>Chi tiết tình trạng kho linh kiện</h2>
    </div>

    <section class="stock-alert-grid">

        <!-- LINH KIỆN HẾT HÀNG -->

        <div class="stock-alert-panel">

            <h3>
                <i data-lucide="package-x"></i>
                Danh sách linh kiện hết hàng
            </h3>

            <?php if (empty($outOfStockParts)): ?>

                <div class="stock-alert-empty">
                    Hiện không có linh kiện nào hết hàng.
                </div>

            <?php else: ?>

                <div class="stock-alert-list">

                    <?php foreach ($outOfStockParts as $part): ?>

                        <?php
                        $suggestedRestock = max(
                            0,
                            (int) $part['min_quantity'] - (int) $part['quantity']
                        );
                        ?>

                        <div class="stock-alert-item">

                            <div class="stock-alert-name">
                                <strong><?php echo e($part['part_name']); ?></strong>

                                <span>
                                    SKU:
                                    <?php echo e($part['sku'] ?: 'Chưa có mã'); ?>
                                </span>
                            </div>

                            <div class="stock-alert-quantity">

                                <strong>
                                    <?php echo number_format((int) $part['quantity']); ?>
                                </strong>

                                <span class="stock-priority priority-critical">
                                    KHẨN CẤP
                                </span>

                                <span class="stock-restock-suggestion">
                                    Đề xuất nhập:
                                    <?php echo number_format($suggestedRestock); ?>
                                    chiếc
                                </span>

                                <span>
                                    Ngưỡng:
                                    <?php echo number_format((int) $part['min_quantity']); ?>
                                </span>

                                <a
                                    class="stock-alert-action"
                                    href="nhap_kho.php?part_id=<?php echo (int) $part['id']; ?>"
                                    target="mainContent">
                                    Nhập kho
                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <?php if ($outOfStockCount > 10): ?>
                <div class="attention-footer">
                    <a href="linh_kien.php" target="mainContent" class="view-all-link">
                        Xem tất cả linh kiện →
                    </a>
                </div>
            <?php endif; ?>

        </div>

        <!-- LINH KIỆN SẮP HẾT -->

        <div class="stock-alert-panel">

            <h3>
                <i data-lucide="alert-circle"></i>
                Danh sách linh kiện sắp hết hàng
            </h3>

            <?php if (empty($lowStockParts)): ?>

                <div class="stock-alert-empty">
                    Hiện không có linh kiện nào sắp hết hàng.
                </div>

            <?php else: ?>

                <div class="stock-alert-list">

                    <?php foreach ($lowStockParts as $part): ?>

                        <?php
                        $suggestedRestock = max(
                            0,
                            (int) $part['min_quantity'] - (int) $part['quantity']
                        );

                        $minimumQuantity = (int) $part['min_quantity'];

                        $stockRatio = $minimumQuantity > 0
                            ? (int) $part['quantity'] / $minimumQuantity
                            : 0;

                        if ($stockRatio <= 0.25) {
                            $priorityLabel = 'RẤT CAO';
                            $priorityClass = 'priority-critical';
                        } elseif ($stockRatio <= 0.5) {
                            $priorityLabel = 'CAO';
                            $priorityClass = 'priority-high';
                        } else {
                            $priorityLabel = 'THEO DÕI';
                            $priorityClass = 'priority-medium';
                        }
                        ?>

                        <div class="stock-alert-item">

                            <div class="stock-alert-name">
                                <strong><?php echo e($part['part_name']); ?></strong>

                                <span>
                                    SKU:
                                    <?php echo e($part['sku'] ?: 'Chưa có mã'); ?>
                                </span>
                            </div>

                            <div class="stock-alert-quantity">

                                <strong>
                                    <?php echo number_format((int) $part['quantity']); ?>
                                </strong>

                                <span class="stock-priority <?php echo $priorityClass; ?>">
                                    <?php echo e($priorityLabel); ?>
                                </span>

                                <span class="stock-restock-suggestion">
                                    Đề xuất nhập:
                                    <?php echo number_format($suggestedRestock); ?>
                                    chiếc
                                </span>

                                <span>
                                    Ngưỡng:
                                    <?php echo number_format($minimumQuantity); ?>
                                </span>

                                <a
                                    class="stock-alert-action"
                                    href="nhap_kho.php?part_id=<?php echo (int) $part['id']; ?>"
                                    target="mainContent">
                                    Nhập kho
                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <?php if ($lowStockCount > 10): ?>
                <div class="attention-footer">
                    <a href="linh_kien.php" target="mainContent" class="view-all-link">
                        Xem tất cả linh kiện →
                    </a>
                </div>
            <?php endif; ?>

        </div>

    </section>

    <!-- 7. PHIẾU QUÁ 3 NGÀY CHƯA CẬP NHẬT -->

    <section class="panel attention-panel">

        <div class="attention-heading">
            <h3>
                <i data-lucide="alert-circle"></i>
                Phiếu sửa chữa cần chú ý
            </h3>

            <span class="attention-count">
                Tổng cộng <?php echo number_format($attentionTicketCount); ?> phiếu
            </span>
        </div>

        <p class="attention-note">
            Danh sách các phiếu đang xử lý nhưng đã quá 3 ngày
            chưa được cập nhật. Hiển thị tối đa 20 phiếu,
            ưu tiên những phiếu lâu chưa được cập nhật nhất.
        </p>

        <?php if (empty($attentionTickets)): ?>

            <div class="empty-message">
                Hiện tại không có phiếu sửa chữa nào cần chú ý.
            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table class="attention-table">

                    <thead>
                        <tr>
                            <th>Mã phiếu</th>
                            <th>Khách hàng</th>
                            <th>Thiết bị</th>
                            <th>Kỹ thuật viên</th>
                            <th>Trạng thái</th>
                            <th>Cập nhật lần cuối</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($attentionTickets as $ticket): ?>

                            <tr>

                                <td>
                                    <span class="attention-ticket-code">
                                        <?php echo e($ticket['ticket_code']); ?>
                                    </span>
                                </td>

                                <td><?php echo e($ticket['customer_name']); ?></td>

                                <td>
                                    <?php
                                    echo e(
                                        trim(
                                            ($ticket['device_type'] ?? '') . ' ' .
                                            ($ticket['brand'] ?? '') . ' ' .
                                            ($ticket['model'] ?? '')
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($ticket['technician_name'])
                                        ? e($ticket['technician_name'])
                                        : 'Chưa phân công';
                                    ?>
                                </td>

                                <td>
                                    <span class="attention-status">
                                        <?php
                                        echo e(
                                            $statusLabels[$ticket['status']]
                                            ?? $ticket['status']
                                        );
                                        ?>
                                    </span>

                                    <br>

                                    <span class="overdue-badge">
                                        Quá 3 ngày chưa cập nhật
                                    </span>
                                </td>

                                <td>
                                    <?php
                                    echo e(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime($ticket['updated_at'])
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <a
                                        class="attention-link"
                                        href="chi_tiet_phieu_sua_chua.php?id=<?php echo (int) $ticket['id']; ?>"
                                        target="mainContent">
                                        Chi tiết
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

        <?php if ($attentionTicketCount > 20): ?>
            <div class="attention-footer">
                <a
                    href="phieu_sua_chua.php"
                    target="mainContent"
                    class="view-all-link">
                    Xem tất cả phiếu sửa chữa →
                </a>
            </div>
        <?php endif; ?>

    </section>

    <!-- 8. PHÂN TÍCH THỜI GIAN XỬ LÝ -->

    <div class="section-heading">
        <i data-lucide="timer"></i>
        <h2>Phân tích thời gian xử lý phiếu</h2>
    </div>

    <section class="stats">

        <div class="stat-card blue">
            <div class="stat-top">
                <p class="stat-label">Phiếu đã bàn giao có lịch sử</p>
                <span class="stat-icon"><i data-lucide="clipboard-check"></i></span>
            </div>
            <h2><?php echo number_format($totalDeliveredWithHistory); ?></h2>
            <span class="stat-note">Có ghi nhận thời điểm bàn giao</span>
        </div>

        <div class="stat-card green">
            <div class="stat-top">
                <p class="stat-label">Thời gian xử lý trung bình</p>
                <span class="stat-icon"><i data-lucide="clock"></i></span>
            </div>
            <h2 class="money"><?php echo e(formatDuration($averageProcessingMinutes)); ?></h2>
            <span class="stat-note">Tính từ tiếp nhận đến bàn giao</span>
        </div>

        <div class="stat-card orange">
            <div class="stat-top">
                <p class="stat-label">Thời gian xử lý lâu nhất</p>
                <span class="stat-icon"><i data-lucide="clock-alert"></i></span>
            </div>
            <h2 class="money"><?php echo e(formatDuration($longestProcessingMinutes)); ?></h2>
            <span class="stat-note">Trong các phiếu có lịch sử bàn giao</span>
        </div>

    </section>

    <!-- TOP 5 PHIẾU XỬ LÝ LÂU NHẤT -->

    <section class="panel attention-panel">

        <h3>
            <i data-lucide="chart-no-axes-combined"></i>
            Top 5 phiếu có thời gian xử lý lâu nhất
        </h3>

        <p class="attention-note">
            Danh sách được sắp xếp theo thời gian từ lúc tiếp nhận
            đến lúc bàn giao, từ lâu nhất đến ngắn nhất.
        </p>

        <?php if (empty($longestTickets)): ?>

            <div class="empty-message">
                Chưa có dữ liệu thời gian xử lý phiếu.
            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Mã phiếu</th>
                            <th>Ngày tiếp nhận</th>
                            <th>Ngày bàn giao</th>
                            <th>Thời gian xử lý</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($longestTickets as $index => $ticket): ?>

                            <tr>
                                <td><?php echo $index + 1; ?></td>

                                <td>
                                    <span class="attention-ticket-code">
                                        <?php echo e($ticket['ticket_code']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php
                                    echo e(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime($ticket['received_at'])
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo e(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime($ticket['delivered_at'])
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <strong class="number">
                                        <?php
                                        echo e(
                                            formatDuration(
                                                $ticket['processing_minutes']
                                            )
                                        );
                                        ?>
                                    </strong>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <!-- 9. HIỆU SUẤT KỸ THUẬT VIÊN -->

    <div class="section-heading">
        <i data-lucide="users"></i>
        <h2>Hiệu suất nhân viên kỹ thuật</h2>
    </div>

    <section class="panel">

        <h3>
            <i data-lucide="user-cog"></i>
            Kết quả công việc của nhân viên
        </h3>

        <p class="panel-description">
            Thống kê các phiếu được tiếp nhận trong 3 tháng gần nhất,
            theo kỹ thuật viên đang được phân công hiện tại.
        </p>

        <?php if (empty($technicians)): ?>

            <div class="empty-message">
                Chưa có nhân viên kỹ thuật đang hoạt động.
            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Phiếu được giao</th>
                            <th>Đang xử lý</th>
                            <th>Đã bàn giao</th>
                            <th>Tỷ lệ hoàn thành</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($technicians as $technician): ?>

                            <?php
                            $assigned = (int) $technician['assigned_tickets'];
                            $processing = (int) $technician['processing_tickets'];
                            $delivered = (int) $technician['delivered_tickets'];

                            $completionRate = $assigned > 0
                                ? round(($delivered / $assigned) * 100, 1)
                                : 0;
                            ?>

                            <tr>

                                <td><?php echo e($technician['full_name']); ?></td>

                                <td class="number">
                                    <?php echo number_format($assigned); ?>
                                </td>

                                <td><?php echo number_format($processing); ?></td>

                                <td class="number">
                                    <?php echo number_format($delivered); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo number_format($completionRate, 1); ?>%
                                    </strong>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

        <div class="attention-footer">
            <a
                href="nhan_vien.php"
                target="mainContent"
                class="view-all-link">
                Quản lý nhân viên →
            </a>
        </div>

    </section>

    <!-- 10. TRẠNG THÁI PHIẾU VÀ THANH TOÁN -->

    <div class="section-heading">
        <i data-lucide="chart-pie"></i>
        <h2>Phân tích hoạt động</h2>
    </div>

    <section class="content-grid">

        <!-- TRẠNG THÁI PHIẾU -->

        <div class="panel">

            <h3>
                <i data-lucide="chart-pie"></i>
                Trạng thái phiếu sửa chữa
            </h3>

            <?php if (empty($statusCounts)): ?>

                <p class="empty">Chưa có phiếu sửa chữa.</p>

            <?php else: ?>

                <?php foreach ($statusCounts as $status): ?>

                    <?php
                    $statusPercent = $totalTickets > 0
                        ? percent(($status['total'] / $totalTickets) * 100)
                        : 0;
                    ?>

                    <div class="chart-item">

                        <div class="chart-info">
                            <strong>
                                <?php
                                echo e(
                                    $statusLabels[$status['status']]
                                    ?? $status['status']
                                );
                                ?>
                            </strong>

                            <span>
                                <?php echo number_format($status['total']); ?>
                                phiếu
                            </span>
                        </div>

                        <div class="bar-background">
                            <div
                                class="bar status-bar"
                                style="width: <?php echo $statusPercent; ?>%;">
                            </div>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <!-- THANH TOÁN -->

        <div class="panel">

            <h3>
                <i data-lucide="credit-card"></i>
                Tình hình thanh toán hóa đơn
            </h3>

            <div class="chart-item">
                <div class="chart-info">
                    <strong>Tổng hóa đơn</strong>
                    <span><?php echo number_format($totalInvoices); ?></span>
                </div>
            </div>

            <div class="chart-item">

                <div class="chart-info">
                    <strong>Đã thanh toán</strong>
                    <span><?php echo number_format($paidInvoices); ?></span>
                </div>

                <div class="bar-background">
                    <div
                        class="bar paid-bar"
                        style="width: <?php echo percent($paidInvoicePercent); ?>%;">
                    </div>
                </div>

            </div>

            <div class="chart-item">

                <div class="chart-info">
                    <strong>Chưa thanh toán</strong>
                    <span><?php echo number_format($unpaidInvoices); ?></span>
                </div>

                <div class="bar-background">
                    <div
                        class="bar status-bar"
                        style="width: <?php
                            echo $totalInvoices > 0
                                ? percent(($unpaidInvoices / $totalInvoices) * 100)
                                : 0;
                        ?>%;">
                    </div>
                </div>

            </div>

            <p class="empty">
                Tỷ lệ được tính trên tổng số hóa đơn hiện có.
            </p>

        </div>

    </section>

    <!-- 11. TỶ LỆ BÀN GIAO VÀ TÌNH HÌNH KHO -->

    <section class="content-grid">

        <!-- TỶ LỆ BÀN GIAO -->

        <div class="panel">

            <h3>
                <i data-lucide="circle-check"></i>
                Tỷ lệ phiếu đã bàn giao
            </h3>

            <div class="completion-box">

                <div class="completion-circle">
                    <div class="completion-inner">
                        <?php echo $deliveredPercent; ?>%
                    </div>
                </div>

                <div class="completion-text">

                    <strong>
                        <?php echo number_format($deliveredTickets); ?>
                        / <?php echo number_format($validTickets); ?>
                        phiếu
                    </strong>

                    <p>Đã bàn giao cho khách hàng</p>

                    <p>
                        Còn lại:
                        <?php echo number_format($remainingTickets); ?>
                        phiếu
                    </p>

                    <p>Không tính phiếu đã hủy.</p>

                </div>

            </div>

        </div>

        <!-- TÌNH HÌNH KHO -->

        <div class="panel">

            <h3>
                <i data-lucide="package"></i>
                Tình hình kho linh kiện
            </h3>

            <div class="chart-item">
                <div class="chart-info">
                    <strong>Tổng số lượng linh kiện</strong>
                    <span><?php echo number_format($totalPartsQuantity); ?></span>
                </div>
            </div>

            <div class="chart-item">
                <div class="chart-info">
                    <strong>Linh kiện hết hàng</strong>
                    <span><?php echo number_format($outOfStockCount); ?></span>
                </div>
            </div>

            <div class="chart-item">
                <div class="chart-info">
                    <strong>Linh kiện sắp hết hàng</strong>
                    <span><?php echo number_format($lowStockCount); ?></span>
                </div>
            </div>

            <p class="empty">
                Linh kiện được cảnh báo sắp hết khi số lượng tồn kho
                lớn hơn 0 nhưng bằng hoặc thấp hơn mức tồn kho tối thiểu
                đã thiết lập.
            </p>

            <div class="attention-footer">
                <a
                    href="linh_kien.php"
                    target="mainContent"
                    class="view-all-link">
                    Kiểm tra kho linh kiện →
                </a>
            </div>

        </div>

    </section>

    <script>
        if (window.lucide) {
            lucide.createIcons();
        }
    </script>

</body>
</html>