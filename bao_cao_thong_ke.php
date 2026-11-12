
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// =====================================================
// 1. HÀM HỖ TRỢ
// =====================================================

function eBaoCao($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatBaoCaoMoney($amount): string
{
    return number_format((float) $amount, 0, ',', '.') . ' đ';
}

// =====================================================
// 2. LẤY KHOẢNG THỜI GIAN
// =====================================================

$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');

$startDate = $_GET['start_date'] ?? $firstDayOfMonth;
$endDate = $_GET['end_date'] ?? $today;

$dateError = '';

$dateObjectStart = DateTime::createFromFormat('!Y-m-d', $startDate);
$dateObjectEnd = DateTime::createFromFormat('!Y-m-d', $endDate);

if (
    !$dateObjectStart ||
    $dateObjectStart->format('Y-m-d') !== $startDate ||
    !$dateObjectEnd ||
    $dateObjectEnd->format('Y-m-d') !== $endDate
) {
    $dateError = 'Ngày bắt đầu hoặc ngày kết thúc không hợp lệ.';
    $startDate = $firstDayOfMonth;
    $endDate = $today;
} elseif ($startDate > $endDate) {
    $dateError = 'Ngày bắt đầu không được lớn hơn ngày kết thúc.';
    $startDate = $firstDayOfMonth;
    $endDate = $today;
}

// Điều kiện ngày: lấy cả ngày kết thúc
$startDateTime = $startDate . ' 00:00:00';
$endDateTime = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';

// =====================================================
// 3. BÁO CÁO DOANH THU VÀ HÓA ĐƠN
// =====================================================

// =====================================================
// 3. BÁO CÁO DOANH THU VÀ HÓA ĐƠN
// =====================================================

$sqlInvoiceStats = "
    SELECT
        COUNT(
            CASE
                WHEN i.created_at >= dates.start_date
                 AND i.created_at < dates.end_date
                THEN 1
            END
        ) AS total_invoices,

        SUM(
            CASE
                WHEN i.payment_status = 'paid'
                 AND i.paid_at >= dates.start_date
                 AND i.paid_at < dates.end_date
                THEN 1 ELSE 0
            END
        ) AS paid_invoices,

        SUM(
            CASE
                WHEN i.payment_status = 'unpaid'
                 AND i.created_at >= dates.start_date
                 AND i.created_at < dates.end_date
                THEN 1 ELSE 0
            END
        ) AS unpaid_invoices,

        COALESCE(SUM(
            CASE
                WHEN i.payment_status = 'paid'
                 AND i.paid_at >= dates.start_date
                 AND i.paid_at < dates.end_date
                THEN i.total_amount ELSE 0
            END
        ), 0) AS paid_revenue,

        COALESCE(SUM(
            CASE
                WHEN i.payment_status = 'unpaid'
                 AND i.created_at >= dates.start_date
                 AND i.created_at < dates.end_date
                THEN i.total_amount ELSE 0
            END
        ), 0) AS unpaid_amount,

        COALESCE(SUM(
            CASE
                WHEN i.payment_status = 'paid'
                 AND i.paid_at >= dates.start_date
                 AND i.paid_at < dates.end_date
                THEN i.labor_cost ELSE 0
            END
        ), 0) AS labor_revenue,

        COALESCE(SUM(
            CASE
                WHEN i.payment_status = 'paid'
                 AND i.paid_at >= dates.start_date
                 AND i.paid_at < dates.end_date
                THEN i.parts_total ELSE 0
            END
        ), 0) AS parts_revenue,

        COALESCE(SUM(
            CASE
                WHEN i.payment_status = 'paid'
                 AND i.paid_at >= dates.start_date
                 AND i.paid_at < dates.end_date
                THEN i.discount ELSE 0
            END
        ), 0) AS total_discount

    FROM invoices i

    CROSS JOIN (
        SELECT
            :start_date AS start_date,
            :end_date AS end_date
    ) dates

    WHERE (
        i.created_at >= dates.start_date
        AND i.created_at < dates.end_date
    )
    OR (
        i.paid_at >= dates.start_date
        AND i.paid_at < dates.end_date
    )
";

$stmt = $pdo->prepare($sqlInvoiceStats);

$stmt->execute([
    ':start_date' => $startDateTime,
    ':end_date' => $endDateTime
]);

$invoiceStats = $stmt->fetch();

// =====================================================
// 4. DOANH THU THEO TỪNG NGÀY
// =====================================================

// =====================================================
// 4. DOANH THU THEO TỪNG NGÀY
// =====================================================

$sqlDailyRevenue = "
    SELECT
        DATE(paid_at) AS revenue_date,
        SUM(total_amount) AS revenue
    FROM invoices
    WHERE payment_status = 'paid'
      AND paid_at >= :start_date
      AND paid_at < :end_date
    GROUP BY DATE(paid_at)
    ORDER BY revenue_date ASC
";

$stmt = $pdo->prepare($sqlDailyRevenue);

$stmt->execute([
    ':start_date' => $startDateTime,
    ':end_date' => $endDateTime
]);

$dailyRevenueRows = $stmt->fetchAll();

$maxDailyRevenue = 0;

foreach ($dailyRevenueRows as $row) {
    if ((float) $row['revenue'] > $maxDailyRevenue) {
        $maxDailyRevenue = (float) $row['revenue'];
    }
}

// =====================================================
// 5. THỐNG KÊ PHIẾU SỬA CHỮA
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

$sqlTicketStats = "
    SELECT status, COUNT(*) AS total
    FROM repair_tickets
    WHERE received_at >= :start_date
      AND received_at < :end_date
    GROUP BY status
    ORDER BY total DESC
";

$stmt = $pdo->prepare($sqlTicketStats);

$stmt->execute([
    ':start_date' => $startDateTime,
    ':end_date' => $endDateTime
]);

$ticketStats = $stmt->fetchAll();

$totalPeriodTickets = 0;
$deliveredPeriodTickets = 0;

foreach ($ticketStats as $row) {
    $totalPeriodTickets += (int) $row['total'];

    if ($row['status'] === 'delivered') {
        $deliveredPeriodTickets = (int) $row['total'];
    }
}

$deliveredPercent = $totalPeriodTickets > 0
    ? round(($deliveredPeriodTickets / $totalPeriodTickets) * 100)
    : 0;

// =====================================================
// 6. HIỆU SUẤT KỸ THUẬT VIÊN
// =====================================================

$sqlTechnicians = "
    SELECT
        u.id,
        u.full_name,

        COUNT(rt.id) AS assigned_tickets,

        SUM(
            CASE
                WHEN rt.status = 'delivered' THEN 1
                ELSE 0
            END
        ) AS delivered_tickets

    FROM users u

    LEFT JOIN repair_tickets rt
        ON rt.assigned_to = u.id
        AND rt.received_at >= :start_date
        AND rt.received_at < :end_date

    WHERE u.role = 'technician'

    GROUP BY u.id, u.full_name

    ORDER BY assigned_tickets DESC
";

$stmt = $pdo->prepare($sqlTechnicians);

$stmt->execute([
    ':start_date' => $startDateTime,
    ':end_date' => $endDateTime
]);

$technicians = $stmt->fetchAll();

// =====================================================
// 7. THỐNG KÊ NHẬP KHO VÀ XUẤT KHO
// =====================================================

$sqlStockStats = "
    SELECT
        COALESCE(SUM(
            CASE
                WHEN movement_type = 'import' THEN quantity
                ELSE 0
            END
        ), 0) AS imported_quantity,

        COALESCE(SUM(
            CASE
                WHEN movement_type = 'export' THEN quantity
                ELSE 0
            END
        ), 0) AS exported_quantity

    FROM stock_movements

    WHERE created_at >= :start_date
      AND created_at < :end_date
";

$stmt = $pdo->prepare($sqlStockStats);

$stmt->execute([
    ':start_date' => $startDateTime,
    ':end_date' => $endDateTime
]);

$stockStats = $stmt->fetch();

// =====================================================
// 8. LINH KIỆN DƯỚI NGƯỠNG TỒN KHO
// =====================================================

$sqlLowStock = "
    SELECT
        id,
        part_name,
        sku,
        quantity,
        min_quantity

    FROM parts

    WHERE quantity <= min_quantity

    ORDER BY quantity ASC, part_name ASC
";

$lowStockParts = $pdo->query($sqlLowStock)->fetchAll();

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Báo cáo thống kê</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        .page-title {
            margin: 0 0 8px;
            font-size: 24px;
            color: #1e293b;
        }

        .page-description {
            margin: 0 0 22px;
            color: #64748b;
            font-size: 14px;
        }

        /* BỘ LỌC */

        .filter-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }

        .filter-box h3 {
            margin: 0 0 15px;
            font-size: 16px;
            color: #1e293b;
        }

        .filter-form {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: bold;
            color: #475569;
        }

        .form-group input {
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        .btn-filter {
            padding: 11px 18px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-filter:hover {
            background: #1d4ed8;
        }

        .btn-reset {
            padding: 11px 18px;
            background: #64748b;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
        }

        .date-error {
            margin-top: 12px;
            color: #dc2626;
            font-size: 13px;
        }

        /* THẺ THỐNG KÊ */

        .section-title {
            margin: 25px 0 15px;
            font-size: 18px;
            color: #1e293b;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .stat-card {
            background: white;
            padding: 19px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            border-left: 4px solid #2563eb;
            min-width: 0;
        }

        .stat-card:nth-child(2) {
            border-left-color: #16a34a;
        }

        .stat-card:nth-child(3) {
            border-left-color: #d97706;
        }

        .stat-card:nth-child(4) {
            border-left-color: #0891b2;
        }

        .stat-card:nth-child(5) {
            border-left-color: #7c3aed;
        }

        .stat-card:nth-child(6) {
            border-left-color: #dc2626;
        }

        .stat-card:nth-child(7) {
            border-left-color: #0d9488;
        }

        .stat-card:nth-child(8) {
            border-left-color: #64748b;
        }

        .stat-card p {
            margin: 0 0 12px;
            color: #64748b;
            font-size: 13px;
        }

        .stat-card h2 {
            margin: 0;
            color: #1e293b;
            font-size: 21px;
            overflow-wrap: anywhere;
        }

        /* KHUNG NỘI DUNG */

        .content-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .panel {
            background: white;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            min-width: 0;
        }

        .panel h3 {
            margin: 0 0 20px;
            color: #1e293b;
            font-size: 17px;
        }

        /* BIỂU ĐỒ */

        .chart-item {
            margin-bottom: 18px;
        }

        .chart-info {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .chart-info strong {
            color: #334155;
        }

        .chart-info span {
            color: #64748b;
            text-align: right;
        }

        .bar-background {
            height: 12px;
            width: 100%;
            background: #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
        }

        .bar {
            height: 100%;
            background: #2563eb;
            border-radius: 20px;
        }

        .bar-green {
            background: #16a34a;
        }

        .bar-orange {
            background: #d97706;
        }

        .bar-cyan {
            background: #0891b2;
        }

        /* BẢNG */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 420px;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            color: #475569;
        }

        .number {
            font-weight: bold;
            color: #2563eb;
        }

        .empty {
            padding: 12px;
            background: #f8fafc;
            color: #64748b;
            border-radius: 6px;
            font-size: 13px;
        }

        .warning {
            color: #dc2626;
            font-weight: bold;
        }

        .normal {
            color: #16a34a;
            font-weight: bold;
        }

        .completion {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            line-height: 1.8;
        }
/* BIỂU ĐỒ CHART.JS */

.chart-panel {
    background: white;
    padding: 22px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    min-width: 0;
}

.chart-panel h3 {
    margin: 0 0 18px;
    color: #1e293b;
    font-size: 17px;
}

.chart-container {
    position: relative;
    width: 100%;
    height: 320px;
}

.chart-container canvas {
    max-width: 100%;
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

        @media (max-width: 550px) {
            body {
                padding: 15px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 21px;
            }

            .filter-form {
                align-items: stretch;
                flex-direction: column;
            }

            .form-group input,
            .btn-filter,
            .btn-reset {
                width: 100%;
            }
        }
.btn-export {
    padding: 11px 18px;
    background: #16a34a;
    color: white;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
}

.btn-export:hover {
    background: #15803d;
}
    </style>

</head>

<body>

    <h1 class="page-title">Báo cáo thống kê</h1>

    <p class="page-description">
        Theo dõi doanh thu, hoạt động sửa chữa và tình hình kho linh kiện.
    </p>

    <!-- BỘ LỌC THỜI GIAN -->

    <section class="filter-box">

        <h3>Chọn khoảng thời gian báo cáo</h3>

        <form method="GET" class="filter-form">

            <div class="form-group">
                <label for="start_date">Từ ngày</label>
                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?php echo eBaoCao($startDate); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="end_date">Đến ngày</label>
                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?php echo eBaoCao($endDate); ?>"
                    required
                >
            </div>

            <button type="submit" class="btn-filter">
    Lọc báo cáo
</button>

<a
    href="xuat_bao_cao.php?start_date=<?php echo eBaoCao($startDate); ?>&end_date=<?php echo eBaoCao($endDate); ?>"
    class="btn-export"
>
    Xuất Excel
</a>

<a href="bao_cao_thong_ke.php" class="btn-reset">
    Đặt lại
</a>

        </form>

        <?php if ($dateError !== ''): ?>
            <p class="date-error">
                <?php echo eBaoCao($dateError); ?>
            </p>
        <?php endif; ?>

    </section>


    <!-- DOANH THU -->

    <h2 class="section-title">1. Báo cáo doanh thu</h2>

    <section class="stats">

        <div class="stat-card">
            <p>Doanh thu đã thanh toán</p>
            <h2><?php echo formatBaoCaoMoney($invoiceStats['paid_revenue']); ?></h2>
        </div>

        <div class="stat-card">
            <p>Tiền chưa thanh toán</p>
            <h2><?php echo formatBaoCaoMoney($invoiceStats['unpaid_amount']); ?></h2>
        </div>

        <div class="stat-card">
            <p>Tổng tiền công đã thanh toán</p>
            <h2><?php echo formatBaoCaoMoney($invoiceStats['labor_revenue']); ?></h2>
        </div>

        <div class="stat-card">
            <p>Tổng tiền linh kiện đã thanh toán</p>
            <h2><?php echo formatBaoCaoMoney($invoiceStats['parts_revenue']); ?></h2>
        </div>

        <div class="stat-card">
            <p>Tổng tiền giảm giá</p>
            <h2><?php echo formatBaoCaoMoney($invoiceStats['total_discount']); ?></h2>
        </div>

        <div class="stat-card">
            <p>Tổng số hóa đơn</p>
            <h2><?php echo number_format($invoiceStats['total_invoices']); ?></h2>
        </div>

        <div class="stat-card">
            <p>Hóa đơn đã thanh toán</p>
            <h2><?php echo number_format($invoiceStats['paid_invoices']); ?></h2>
        </div>

        <div class="stat-card">
            <p>Hóa đơn chưa thanh toán</p>
            <h2><?php echo number_format($invoiceStats['unpaid_invoices']); ?></h2>
        </div>

    </section>

<!-- CÁC BIỂU ĐỒ THỐNG KÊ -->

<h2 class="section-title">Biểu đồ phân tích tổng quan</h2>

<section class="content-grid">

    <!-- BIỂU ĐỒ 1: DOANH THU -->

    <div class="chart-panel">
        <h3>1. Doanh thu theo ngày</h3>
        <div class="chart-container">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- BIỂU ĐỒ 2: TRẠNG THÁI PHIẾU -->

    <div class="chart-panel">
        <h3>2. Trạng thái phiếu sửa chữa</h3>
        <div class="chart-container">
            <canvas id="ticketStatusChart"></canvas>
        </div>
    </div>

    <!-- BIỂU ĐỒ 3: HIỆU SUẤT KỸ THUẬT VIÊN -->

    <div class="chart-panel">
        <h3>3. Hiệu suất kỹ thuật viên</h3>
        <div class="chart-container">
            <canvas id="technicianChart"></canvas>
        </div>
    </div>

    <!-- BIỂU ĐỒ 4: NHẬP XUẤT KHO -->

    <div class="chart-panel">
        <h3>4. Thống kê nhập kho và xuất kho</h3>
        <div class="chart-container">
            <canvas id="stockChart"></canvas>
        </div>
    </div>

</section>
    <!-- DOANH THU THEO NGÀY -->

    <section class="content-grid">

        <div class="panel">

            <h3>Doanh thu theo ngày</h3>

            <?php if (empty($dailyRevenueRows)): ?>

                <p class="empty">
                    Không có doanh thu đã thanh toán trong khoảng thời gian này.
                </p>

            <?php else: ?>

                <?php foreach ($dailyRevenueRows as $row): ?>

                    <?php
                    $width = $maxDailyRevenue > 0
                        ? ((float) $row['revenue'] / $maxDailyRevenue) * 100
                        : 0;
                    ?>

                    <div class="chart-item">

                        <div class="chart-info">

                            <strong>
                                <?php echo date('d/m/Y', strtotime($row['revenue_date'])); ?>
                            </strong>

                            <span>
                                <?php echo formatBaoCaoMoney($row['revenue']); ?>
                            </span>

                        </div>

                        <div class="bar-background">
                            <div class="bar" style="width: <?php echo $width; ?>%;"></div>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>


        <div class="panel">

            <h3>Tỷ lệ thanh toán hóa đơn</h3>

            <div class="chart-item">

                <div class="chart-info">
                    <strong>Đã thanh toán</strong>
                    <span><?php echo number_format($invoiceStats['paid_invoices']); ?> hóa đơn</span>
                </div>

                <div class="bar-background">
                    <div
                        class="bar bar-green"
                        style="width: <?php
                            echo $invoiceStats['total_invoices'] > 0
                                ? ($invoiceStats['paid_invoices'] / $invoiceStats['total_invoices']) * 100
                                : 0;
                        ?>%;">
                    </div>
                </div>

            </div>

            <div class="chart-item">

                <div class="chart-info">
                    <strong>Chưa thanh toán</strong>
                    <span><?php echo number_format($invoiceStats['unpaid_invoices']); ?> hóa đơn</span>
                </div>

                <div class="bar-background">
                    <div
                        class="bar bar-orange"
                        style="width: <?php
                            echo $invoiceStats['total_invoices'] > 0
                                ? ($invoiceStats['unpaid_invoices'] / $invoiceStats['total_invoices']) * 100
                                : 0;
                        ?>%;">
                    </div>
                </div>

            </div>

            <p class="empty">
    Hóa đơn đã thanh toán được thống kê theo ngày thanh toán thực tế.
    Hóa đơn chưa thanh toán được thống kê theo ngày tạo hóa đơn.
</p>

        </div>

    </section>


    <!-- PHIẾU SỬA CHỮA -->

    <h2 class="section-title">2. Báo cáo sửa chữa</h2>

    <section class="content-grid">

        <div class="panel">

            <h3>Trạng thái phiếu sửa chữa</h3>

            <?php if (empty($ticketStats)): ?>

                <p class="empty">Không có phiếu sửa chữa trong khoảng thời gian này.</p>

            <?php else: ?>

                <?php foreach ($ticketStats as $row): ?>

                    <?php
                    $percent = $totalPeriodTickets > 0
                        ? ((int) $row['total'] / $totalPeriodTickets) * 100
                        : 0;
                    ?>

                    <div class="chart-item">

                        <div class="chart-info">

                            <strong>
                                <?php echo eBaoCao($statusLabels[$row['status']] ?? $row['status']); ?>
                            </strong>

                            <span>
                                <?php echo number_format($row['total']); ?> phiếu
                            </span>

                        </div>

                        <div class="bar-background">
                            <div class="bar bar-cyan" style="width: <?php echo $percent; ?>%;"></div>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>


        <div class="panel">

            <h3>Tỷ lệ bàn giao</h3>

            <div class="completion">

                <p>
                    Tổng số phiếu:
                    <strong><?php echo number_format($totalPeriodTickets); ?></strong>
                </p>

                <p>
                    Đã bàn giao:
                    <strong><?php echo number_format($deliveredPeriodTickets); ?></strong>
                </p>

                <p>
                    Tỷ lệ bàn giao:
                    <strong class="normal"><?php echo $deliveredPercent; ?>%</strong>
                </p>

                <div class="bar-background">
                    <div
                        class="bar bar-green"
                        style="width: <?php echo $deliveredPercent; ?>%;">
                    </div>
                </div>

            </div>

            <p class="empty">
                Phiếu được thống kê theo ngày tiếp nhận.
            </p>

        </div>

    </section>


    <!-- HIỆU SUẤT KỸ THUẬT VIÊN -->

    <h2 class="section-title">3. Hiệu suất nhân viên kỹ thuật</h2>

    <section class="panel">

        <h3>Thống kê theo kỹ thuật viên</h3>

        <?php if (empty($technicians)): ?>

            <p class="empty">Chưa có nhân viên kỹ thuật.</p>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Nhân viên</th>
                            <th>Phiếu được giao</th>
                            <th>Đã bàn giao</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($technicians as $index => $technician): ?>

                            <tr>
                                <td><?php echo $index + 1; ?></td>

                                <td>
                                    <?php echo eBaoCao($technician['full_name']); ?>
                                </td>

                                <td class="number">
                                    <?php echo number_format($technician['assigned_tickets']); ?>
                                </td>

                                <td class="number">
                                    <?php echo number_format($technician['delivered_tickets'] ?? 0); ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


    <!-- KHO LINH KIỆN -->

    <h2 class="section-title">4. Báo cáo kho linh kiện</h2>

    <section class="stats">

        <div class="stat-card">
            <p>Tổng linh kiện nhập kho</p>
            <h2><?php echo number_format($stockStats['imported_quantity']); ?></h2>
        </div>

        <div class="stat-card">
            <p>Tổng linh kiện xuất kho</p>
            <h2><?php echo number_format($stockStats['exported_quantity']); ?></h2>
        </div>

        <div class="stat-card">
            <p>Loại linh kiện dưới ngưỡng tồn</p>
            <h2><?php echo number_format(count($lowStockParts)); ?></h2>
        </div>

    </section>

    <section class="panel" style="margin-top: 20px;">

        <h3>Danh sách linh kiện cần nhập thêm</h3>

        <?php if (empty($lowStockParts)): ?>

            <p class="empty">
                Hiện tại không có linh kiện nào dưới ngưỡng tồn kho tối thiểu.
            </p>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Tên linh kiện</th>
                            <th>Mã linh kiện</th>
                            <th>Số lượng hiện tại</th>
                            <th>Ngưỡng tối thiểu</th>
                            <th>Tình trạng</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($lowStockParts as $index => $part): ?>

                            <tr>

                                <td><?php echo $index + 1; ?></td>

                                <td><?php echo eBaoCao($part['part_name']); ?></td>

                                <td><?php echo eBaoCao($part['sku'] ?: 'Chưa có'); ?></td>

                                <td class="warning">
                                    <?php echo number_format($part['quantity']); ?>
                                </td>

                                <td>
                                    <?php echo number_format($part['min_quantity']); ?>
                                </td>

                                <td class="warning">
                                    <?php echo (int) $part['quantity'] === 0 ? 'Hết hàng' : 'Sắp hết hàng'; ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>
<script>
    // ==========================================
    // 1. LẤY DỮ LIỆU TỪ PHP
    // ==========================================

    const revenueLabels = <?php echo json_encode(
        array_map(
            fn($row) => date('d/m/Y', strtotime($row['revenue_date'])),
            $dailyRevenueRows
        ),
        JSON_UNESCAPED_UNICODE
    ); ?>;

    const revenueData = <?php echo json_encode(
        array_map(
            fn($row) => (float) $row['revenue'],
            $dailyRevenueRows
        )
    ); ?>;

    const ticketLabels = <?php echo json_encode(
        array_map(
            fn($row) => $statusLabels[$row['status']] ?? $row['status'],
            $ticketStats
        ),
        JSON_UNESCAPED_UNICODE
    ); ?>;

    const ticketData = <?php echo json_encode(
        array_map(
            fn($row) => (int) $row['total'],
            $ticketStats
        )
    ); ?>;

    const technicianLabels = <?php echo json_encode(
        array_column($technicians, 'full_name'),
        JSON_UNESCAPED_UNICODE
    ); ?>;

    const technicianAssigned = <?php echo json_encode(
        array_map(
            fn($row) => (int) $row['assigned_tickets'],
            $technicians
        )
    ); ?>;

    const technicianDelivered = <?php echo json_encode(
        array_map(
            fn($row) => (int) ($row['delivered_tickets'] ?? 0),
            $technicians
        )
    ); ?>;

    const importedQuantity = <?php echo (int) $stockStats['imported_quantity']; ?>;
    const exportedQuantity = <?php echo (int) $stockStats['exported_quantity']; ?>;


    // ==========================================
    // 2. CẤU HÌNH CHUNG
    // ==========================================

    Chart.defaults.font.family = 'Arial';
    Chart.defaults.color = '#64748b';

    const moneyFormat = new Intl.NumberFormat('vi-VN');


    // ==========================================
    // 3. BIỂU ĐỒ DOANH THU THEO NGÀY
    // ==========================================

    new Chart(document.getElementById('revenueChart'), {
        type: 'line',

        data: {
            labels: revenueLabels,

            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: revenueData,

                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.15)',

                borderWidth: 3,
                fill: true,
                tension: 0.35,

                pointRadius: 4,
                pointHoverRadius: 7
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: true
                },

                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Doanh thu: ' +
                                moneyFormat.format(context.raw) + ' đ';
                        }
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,

                    ticks: {
                        callback: function(value) {
                            return moneyFormat.format(value);
                        }
                    }
                }
            }
        }
    });


    // ==========================================
    // 4. BIỂU ĐỒ TRẠNG THÁI PHIẾU SỬA CHỮA
    // ==========================================

    new Chart(document.getElementById('ticketStatusChart'), {
        type: 'doughnut',

        data: {
            labels: ticketLabels,

            datasets: [{
                data: ticketData,

                backgroundColor: [
                    '#2563eb',
                    '#0891b2',
                    '#d97706',
                    '#7c3aed',
                    '#16a34a',
                    '#0d9488',
                    '#dc2626'
                ],

                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    position: 'bottom'
                },

                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' +
                                context.raw + ' phiếu';
                        }
                    }
                }
            }
        }
    });


    // ==========================================
    // 5. BIỂU ĐỒ HIỆU SUẤT KỸ THUẬT VIÊN
    // ==========================================

    new Chart(document.getElementById('technicianChart'), {
        type: 'bar',

        data: {
            labels: technicianLabels,

            datasets: [
                {
                    label: 'Phiếu được giao',
                    data: technicianAssigned,

                    backgroundColor: '#2563eb',
                    borderRadius: 5
                },

                {
                    label: 'Đã bàn giao',
                    data: technicianDelivered,

                    backgroundColor: '#16a34a',
                    borderRadius: 5
                }
            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    position: 'bottom'
                }
            },

            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });


    // ==========================================
    // 6. BIỂU ĐỒ NHẬP KHO VÀ XUẤT KHO
    // ==========================================

    new Chart(document.getElementById('stockChart'), {
        type: 'bar',

        data: {
            labels: ['Thống kê kho'],

            datasets: [
                {
                    label: 'Nhập kho',
                    data: [importedQuantity],

                    backgroundColor: '#16a34a',
                    borderRadius: 6
                },

                {
                    label: 'Xuất kho',
                    data: [exportedQuantity],

                    backgroundColor: '#f59e0b',
                    borderRadius: 6
                }
            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    position: 'bottom'
                }
            },

            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });

</script>
</body>
</html>
