
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// =====================================================
// 1. KHỞI TẠO DỮ LIỆU VÀ KIỂM TRA KHOẢNG THỜI GIAN
// =====================================================

$thongBaoLoi = '';

$tuNgay = $_GET['tu_ngay'] ?? date('Y-m-01');
$denNgay = $_GET['den_ngay'] ?? date('Y-m-d');

$dateFrom = DateTime::createFromFormat('Y-m-d', $tuNgay);
$dateTo = DateTime::createFromFormat('Y-m-d', $denNgay);

if (
    !$dateFrom ||
    $dateFrom->format('Y-m-d') !== $tuNgay ||
    !$dateTo ||
    $dateTo->format('Y-m-d') !== $denNgay
) {
    $tuNgay = date('Y-m-01');
    $denNgay = date('Y-m-d');
    $thongBaoLoi = 'Khoảng thời gian không hợp lệ.';
}

if ($tuNgay > $denNgay) {
    $thongBaoLoi = 'Ngày bắt đầu không được lớn hơn ngày kết thúc.';
    $tuNgay = date('Y-m-01');
    $denNgay = date('Y-m-d');
}

// =====================================================
// 2. TỔNG QUAN TIÊU THỤ
// =====================================================

$sqlOverview = "
    SELECT
        COUNT(*) AS total_movements,
        COALESCE(SUM(quantity), 0) AS total_quantity
    FROM stock_movements
    WHERE movement_type = 'export'
      AND created_at >= ?
      AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
";

$stmtOverview = $pdo->prepare($sqlOverview);
$stmtOverview->execute([$tuNgay, $denNgay]);
$overview = $stmtOverview->fetch();

// =====================================================
// 3. TOP 10 LINH KIỆN XUẤT NHIỀU NHẤT
// =====================================================

$sqlTopParts = "
    SELECT
        p.id,
        p.part_name,
        p.sku,
        p.category,
        COALESCE(SUM(sm.quantity), 0) AS total_export
    FROM parts p
    INNER JOIN stock_movements sm
        ON p.id = sm.part_id
    WHERE sm.movement_type = 'export'
      AND sm.created_at >= ?
      AND sm.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY
        p.id,
        p.part_name,
        p.sku,
        p.category
    ORDER BY total_export DESC, p.part_name ASC
    LIMIT 10
";

$stmtTopParts = $pdo->prepare($sqlTopParts);
$stmtTopParts->execute([$tuNgay, $denNgay]);
$topParts = $stmtTopParts->fetchAll();

// =====================================================
// 3.1. DỮ LIỆU BIỂU ĐỒ TRÒN
// =====================================================

$chartParts = [];

foreach ($topParts as $part) {
    $quantity = (int) $part['total_export'];

    if ($quantity > 0) {
        $chartParts[] = [
            'name' => $part['part_name'],
            'quantity' => $quantity
        ];
    }
}

// =====================================================
// 4. TOP 10 LINH KIỆN ÍT ĐƯỢC SỬ DỤNG
// =====================================================

$sqlLeastParts = "
    SELECT
        p.id,
        p.part_name,
        p.sku,
        p.category,
        COALESCE(SUM(sm.quantity), 0) AS total_export
    FROM parts p
    LEFT JOIN stock_movements sm
        ON p.id = sm.part_id
       AND sm.movement_type = 'export'
       AND sm.created_at >= ?
       AND sm.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY
        p.id,
        p.part_name,
        p.sku,
        p.category
    ORDER BY total_export ASC, p.part_name ASC
    LIMIT 10
";

$stmtLeastParts = $pdo->prepare($sqlLeastParts);
$stmtLeastParts->execute([$tuNgay, $denNgay]);
$leastParts = $stmtLeastParts->fetchAll();

// =====================================================
// 5. THỐNG KÊ XUẤT KHO THEO THÁNG
// =====================================================

$sqlMonthly = "
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS month_label,
        SUM(quantity) AS total_quantity
    FROM stock_movements
    WHERE movement_type = 'export'
      AND created_at >= ?
      AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month_label ASC
";

$stmtMonthly = $pdo->prepare($sqlMonthly);
$stmtMonthly->execute([$tuNgay, $denNgay]);
$monthlyStats = $stmtMonthly->fetchAll();

// =====================================================
// 6. HÀM HỖ TRỢ
// =====================================================

function eTieuThu($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatMonthLabel($month): string
{
    $date = DateTime::createFromFormat('!Y-m', $month);

    return $date ? $date->format('m/Y') : $month;
}

?>

<style>
/* =====================================================
   MODERN PROFESSIONAL - BÁO CÁO TIÊU THỤ KHO
===================================================== */

* {
    box-sizing: border-box;
}

.consumption-container {
    padding: 26px;
    min-height: 100vh;
    background: #f4f7fb;
    font-family: Arial, sans-serif;
    color: #1e293b;
}

/* HEADER */

.consumption-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    margin-bottom: 24px;
}

.consumption-header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.consumption-header-icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #dbeafe;
    color: #2563eb;
    border-radius: 14px;
    font-size: 23px;
}

.consumption-header h2 {
    margin: 0 0 7px;
    font-size: 23px;
    font-weight: 700;
    color: #172554;
}

.consumption-header p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}

.consumption-header-badge {
    padding: 9px 13px;
    border-radius: 8px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

/* FILTER */

.consumption-filter {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 14px;
    padding: 21px;
    margin-bottom: 22px;
    background: #fff;
    border: 1px solid #e8edf5;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
}

.filter-heading {
    width: 100%;
    margin-bottom: 2px;
    color: #334155;
    font-size: 14px;
    font-weight: 700;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}

.filter-group input {
    min-width: 190px;
    padding: 11px 12px;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    outline: none;
    transition: 0.2s;
}

.filter-group input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.btn-filter,
.btn-export {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 17px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: 0.2s;
}

.btn-filter {
    background: #2563eb;
    color: #fff;
}

.btn-filter:hover {
    background: #1d4ed8;
}

.btn-export {
    background: #15803d;
    color: #fff;
}

.btn-export:hover {
    background: #166534;
}

/* ERROR */

.consumption-error {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 16px;
    margin-bottom: 20px;
    border: 1px solid #fecaca;
    border-radius: 9px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 13px;
}

/* STATISTICS */

.consumption-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 22px;
}

.consumption-stat-card {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 23px;
    background: #fff;
    border: 1px solid #e8edf5;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
}

.consumption-stat-card::before {
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    content: "";
    background: #2563eb;
}

.consumption-stat-card.green::before {
    background: #16a34a;
}

.consumption-stat-info span {
    display: block;
    margin-bottom: 11px;
    color: #64748b;
    font-size: 13px;
}

.consumption-stat-info strong {
    display: block;
    color: #172554;
    font-size: 30px;
    font-weight: 700;
}

.consumption-stat-info small {
    display: block;
    margin-top: 7px;
    color: #94a3b8;
    font-size: 11px;
}

.consumption-stat-icon {
    width: 53px;
    height: 53px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 13px;
    background: #dbeafe;
    color: #2563eb;
    font-size: 22px;
}

.consumption-stat-card.green .consumption-stat-icon {
    background: #dcfce7;
    color: #16a34a;
}

/* PANELS */

.consumption-panel {
    min-width: 0;
    padding: 23px;
    margin-bottom: 22px;
    background: #fff;
    border: 1px solid #e8edf5;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
}

.consumption-panel-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}

.consumption-panel-title {
    display: flex;
    align-items: flex-start;
    gap: 11px;
}

.consumption-panel-title > i {
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 9px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 15px;
}

.consumption-panel-title h3 {
    margin: 0 0 5px;
    color: #1e293b;
    font-size: 16px;
    font-weight: 700;
}

.consumption-panel-title p {
    margin: 0;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
}

.panel-label {
    padding: 6px 10px;
    border-radius: 7px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

/* TABLE */

.consumption-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.consumption-table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
}

.consumption-table th,
.consumption-table td {
    padding: 14px 13px;
    border-bottom: 1px solid #edf1f5;
    text-align: left;
    font-size: 13px;
}

.consumption-table th {
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.consumption-table td {
    color: #475569;
}

.consumption-table tbody tr {
    transition: background 0.15s;
}

.consumption-table tbody tr:hover {
    background: #f8fbff;
}

.consumption-table tbody tr:last-child td {
    border-bottom: none;
}

.consumption-table td:last-child,
.consumption-table th:last-child {
    text-align: right;
}

.consumption-table .number-cell {
    color: #2563eb;
    font-weight: 700;
}

.consumption-table .percentage-cell {
    color: #15803d;
    font-weight: 700;
}

.table-index {
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 700;
}

.table-index.top-three {
    background: #fef3c7;
    color: #b45309;
}

.part-name {
    color: #1e293b;
    font-weight: 600;
}

.sku-label {
    padding: 5px 8px;
    border-radius: 5px;
    background: #f1f5f9;
    color: #64748b;
    font-family: monospace;
    font-size: 11px;
}

.category-label {
    color: #64748b;
}

.consumption-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 35px 20px;
    border: 1px dashed #dbe2ea;
    border-radius: 10px;
    background: #f8fafc;
    color: #94a3b8;
    font-size: 13px;
    text-align: center;
}

.consumption-empty i {
    font-size: 28px;
    color: #cbd5e1;
}

/* PIE CHART */

.consumption-pie-layout {
    display: grid;
    grid-template-columns: 300px minmax(0, 1fr);
    align-items: center;
    gap: 35px;
    padding: 10px 0;
}

.consumption-pie-chart {
    display: flex;
    align-items: center;
    justify-content: center;
}

.consumption-pie {
    width: 250px;
    height: 250px;
    border-radius: 50%;
    border: 7px solid #fff;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.12);
}

.consumption-pie-legend {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.consumption-pie-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.consumption-pie-color {
    width: 13px;
    height: 13px;
    min-width: 13px;
    margin-top: 3px;
    border-radius: 4px;
}

.consumption-pie-info {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.consumption-pie-info strong {
    color: #334155;
    font-size: 13px;
    overflow-wrap: anywhere;
}

.consumption-pie-info span {
    color: #94a3b8;
    font-size: 12px;
}

/* MONTHLY CHART */

.consumption-chart {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 5px 0;
}

.consumption-chart-item {
    display: grid;
    grid-template-columns: 75px minmax(0, 1fr) 65px;
    align-items: center;
    gap: 14px;
}

.consumption-chart-label {
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
}

.consumption-chart-track {
    height: 15px;
    overflow: hidden;
    border-radius: 20px;
    background: #edf2f7;
}

.consumption-chart-bar {
    height: 100%;
    min-width: 2px;
    border-radius: 20px;
    background: linear-gradient(90deg, #2563eb, #60a5fa);
    transition: width 0.3s;
}

.consumption-chart-value {
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    text-align: right;
}

/* FOOTER NOTE */

.consumption-note {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px 16px;
    margin-bottom: 22px;
    border: 1px solid #dbeafe;
    border-radius: 10px;
    background: #eff6ff;
    color: #475569;
    font-size: 12px;
    line-height: 1.6;
}

.consumption-note i {
    margin-top: 2px;
    color: #2563eb;
}

/* RESPONSIVE */

@media (max-width: 900px) {
    .consumption-container {
        padding: 18px;
    }

    .consumption-pie-layout {
        grid-template-columns: 1fr;
        gap: 25px;
    }
}

@media (max-width: 650px) {
    .consumption-container {
        padding: 13px;
    }

    .consumption-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .consumption-header-badge {
        margin-left: 67px;
    }

    .consumption-filter {
        align-items: stretch;
        flex-direction: column;
        padding: 16px;
    }

    .filter-group input {
        width: 100%;
        min-width: 0;
    }

    .btn-filter,
    .btn-export {
        width: 100%;
    }

    .consumption-stats {
        grid-template-columns: 1fr;
    }

    .consumption-stat-card {
        padding: 19px;
    }

    .consumption-stat-info strong {
        font-size: 25px;
    }

    .consumption-panel {
        padding: 16px;
    }

    .consumption-panel-header {
        flex-direction: column;
    }

    .consumption-chart-item {
        grid-template-columns: 55px minmax(0, 1fr) 48px;
        gap: 8px;
    }

    .consumption-pie {
        width: 220px;
        height: 220px;
    }
}
</style>

<div class="consumption-container">

    <!-- HEADER -->
    <div class="consumption-header">

        <div class="consumption-header-left">

            <div class="consumption-header-icon">
                <i class="fa-solid fa-chart-pie"></i>
            </div>

            <div>
                <h2>Phân tích tiêu thụ linh kiện</h2>

                <p>
                    Theo dõi tình hình xuất kho và mức độ sử dụng linh kiện
                </p>
            </div>

        </div>

        <div class="consumption-header-badge">
            <i class="fa-solid fa-chart-line"></i>
            Báo cáo kho
        </div>

    </div>

    <!-- THÔNG BÁO LỖI -->
    <?php if ($thongBaoLoi): ?>

        <div class="consumption-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?php echo eTieuThu($thongBaoLoi); ?>
        </div>

    <?php endif; ?>

    <!-- BỘ LỌC -->
    <form method="GET" class="consumption-filter" target="mainContent">

        <div class="filter-heading">
            <i class="fa-solid fa-filter"></i>
            Lọc dữ liệu báo cáo
        </div>

        <div class="filter-group">

            <label for="tu_ngay">Từ ngày</label>

            <input
                type="date"
                id="tu_ngay"
                name="tu_ngay"
                value="<?php echo eTieuThu($tuNgay); ?>"
                required
            >

        </div>

        <div class="filter-group">

            <label for="den_ngay">Đến ngày</label>

            <input
                type="date"
                id="den_ngay"
                name="den_ngay"
                value="<?php echo eTieuThu($denNgay); ?>"
                required
            >

        </div>

        <button type="submit" class="btn-filter">
            <i class="fa-solid fa-magnifying-glass"></i>
            Lọc báo cáo
        </button>

        <a
            href="xuat_bao_cao_tieu_thu_kho.php?tu_ngay=<?php echo urlencode($tuNgay); ?>&den_ngay=<?php echo urlencode($denNgay); ?>"
            class="btn-export"
            target="mainContent"
        >
            <i class="fa-solid fa-file-excel"></i>
            Xuất Excel
        </a>

    </form>

    <!-- TỔNG QUAN -->
    <div class="consumption-stats">

        <div class="consumption-stat-card">

            <div class="consumption-stat-info">

                <span>Tổng số giao dịch xuất kho</span>

                <strong>
                    <?php echo number_format((int) $overview['total_movements']); ?>
                </strong>

                <small>
                    Trong khoảng thời gian đã chọn
                </small>

            </div>

            <div class="consumption-stat-icon">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </div>

        </div>

        <div class="consumption-stat-card green">

            <div class="consumption-stat-info">

                <span>Tổng số linh kiện đã xuất</span>

                <strong>
                    <?php echo number_format((int) $overview['total_quantity']); ?>
                </strong>

                <small>
                    Tổng số lượng linh kiện đã xuất kho
                </small>

            </div>

            <div class="consumption-stat-icon">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>

        </div>

    </div>

    <!-- TOP 10 LINH KIỆN XUẤT NHIỀU NHẤT -->
    <div class="consumption-panel">

        <div class="consumption-panel-header">

            <div class="consumption-panel-title">

                <i class="fa-solid fa-arrow-trend-up"></i>

                <div>
                    <h3>Top 10 linh kiện tiêu thụ nhiều nhất</h3>
                    <p>Danh sách linh kiện có số lượng xuất kho cao nhất</p>
                </div>

            </div>

            <span class="panel-label">TOP 10</span>

        </div>

        <?php if (empty($topParts)): ?>

            <div class="consumption-empty">
                <i class="fa-solid fa-box-open"></i>
                Chưa có dữ liệu xuất kho trong khoảng thời gian này.
            </div>

        <?php else: ?>

            <div class="consumption-table-wrapper">

                <table class="consumption-table">

                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Tên linh kiện</th>
                            <th>Mã SKU</th>
                            <th>Danh mục</th>
                            <th>Số lượng xuất</th>
                            <th>Tỷ lệ tiêu thụ</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($topParts as $index => $part): ?>

                            <?php
                            $consumptionRate = (int) $overview['total_quantity'] > 0
                                ? ((int) $part['total_export'] / (int) $overview['total_quantity']) * 100
                                : 0;
                            ?>

                            <tr>

                                <td>
                                    <span class="table-index <?php echo $index < 3 ? 'top-three' : ''; ?>">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="part-name">
                                        <?php echo eTieuThu($part['part_name']); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="sku-label">
                                        <?php echo eTieuThu($part['sku'] ?: 'Chưa có mã'); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="category-label">
                                        <?php echo eTieuThu($part['category'] ?: 'Chưa phân loại'); ?>
                                    </span>
                                </td>

                                <td class="number-cell">
                                    <?php echo number_format((int) $part['total_export']); ?>
                                </td>

                                <td class="percentage-cell">
                                    <?php echo number_format($consumptionRate, 2); ?>%
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

    <!-- TOP 10 LINH KIỆN ÍT ĐƯỢC SỬ DỤNG -->
    <div class="consumption-panel">

        <div class="consumption-panel-header">

            <div class="consumption-panel-title">

                <i class="fa-solid fa-arrow-trend-down"></i>

                <div>
                    <h3>Top 10 linh kiện ít được sử dụng</h3>
                    <p>Danh sách linh kiện có số lượng xuất kho thấp nhất</p>
                </div>

            </div>

            <span class="panel-label">TOP 10</span>

        </div>

        <?php if (empty($leastParts)): ?>

            <div class="consumption-empty">
                <i class="fa-solid fa-box-open"></i>
                Chưa có linh kiện nào trong hệ thống.
            </div>

        <?php else: ?>

            <div class="consumption-table-wrapper">

                <table class="consumption-table">

                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Tên linh kiện</th>
                            <th>Mã SKU</th>
                            <th>Danh mục</th>
                            <th>Số lượng xuất</th>
                            <th>Tỷ lệ tiêu thụ</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($leastParts as $index => $part): ?>

                            <?php
                            $consumptionRate = (int) $overview['total_quantity'] > 0
                                ? ((int) $part['total_export'] / (int) $overview['total_quantity']) * 100
                                : 0;
                            ?>

                            <tr>

                                <td>
                                    <span class="table-index">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="part-name">
                                        <?php echo eTieuThu($part['part_name']); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="sku-label">
                                        <?php echo eTieuThu($part['sku'] ?: 'Chưa có mã'); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="category-label">
                                        <?php echo eTieuThu($part['category'] ?: 'Chưa phân loại'); ?>
                                    </span>
                                </td>

                                <td class="number-cell">
                                    <?php echo number_format((int) $part['total_export']); ?>
                                </td>

                                <td class="percentage-cell">
                                    <?php echo number_format($consumptionRate, 2); ?>%
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

    <!-- BIỂU ĐỒ TRÒN CƠ CẤU TIÊU THỤ -->
    <div class="consumption-panel">

        <div class="consumption-panel-header">

            <div class="consumption-panel-title">

                <i class="fa-solid fa-chart-pie"></i>

                <div>
                    <h3>Cơ cấu tiêu thụ linh kiện</h3>
                    <p>Tỷ trọng số lượng xuất kho của Top 10 linh kiện</p>
                </div>

            </div>

            <span class="panel-label">BIỂU ĐỒ TRÒN</span>

        </div>

        <?php if (empty($chartParts)): ?>

            <div class="consumption-empty">
                <i class="fa-solid fa-chart-pie"></i>
                Chưa có dữ liệu để hiển thị biểu đồ.
            </div>

        <?php else: ?>

            <div class="consumption-pie-layout">

                <div class="consumption-pie-chart">

                    <?php
                    $chartTotal = array_sum(
                        array_column($chartParts, 'quantity')
                    );

                    $currentAngle = 0;

                    $chartColors = [
                        '#3b82f6',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#8b5cf6',
                        '#06b6d4',
                        '#ec4899',
                        '#84cc16',
                        '#f97316',
                        '#64748b'
                    ];

                    $gradientParts = [];

                    foreach ($chartParts as $index => $part):

                        $percentage = ($part['quantity'] / $chartTotal) * 100;
                        $angle = ($percentage / 100) * 360;
                        $nextAngle = $currentAngle + $angle;
                        $color = $chartColors[$index % count($chartColors)];

                        $gradientParts[] = "{$color} {$currentAngle}deg {$nextAngle}deg";

                        $currentAngle = $nextAngle;

                    endforeach;

                    $pieGradient = implode(', ', $gradientParts);
                    ?>

                    <div
                        class="consumption-pie"
                        style="background: conic-gradient(<?php echo eTieuThu($pieGradient); ?>);"
                    ></div>

                </div>

                <div class="consumption-pie-legend">

                    <?php foreach ($chartParts as $index => $part): ?>

                        <?php
                        $percentage = ($part['quantity'] / $chartTotal) * 100;
                        $color = $chartColors[$index % count($chartColors)];
                        ?>

                        <div class="consumption-pie-item">

                            <span
                                class="consumption-pie-color"
                                style="background: <?php echo eTieuThu($color); ?>;"
                            ></span>

                            <div class="consumption-pie-info">

                                <strong>
                                    <?php echo eTieuThu($part['name']); ?>
                                </strong>

                                <span>
                                    <?php echo number_format($part['quantity']); ?> chiếc
                                    (<?php echo number_format($percentage, 2); ?>%)
                                </span>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

    <!-- BIỂU ĐỒ TIÊU THỤ THEO THÁNG -->
    <div class="consumption-panel">

        <div class="consumption-panel-header">

            <div class="consumption-panel-title">

                <i class="fa-solid fa-chart-column"></i>

                <div>
                    <h3>Thống kê xuất kho theo tháng</h3>
                    <p>So sánh số lượng linh kiện xuất kho giữa các tháng</p>
                </div>

            </div>

            <span class="panel-label">THEO THÁNG</span>

        </div>

        <?php if (empty($monthlyStats)): ?>

            <div class="consumption-empty">
                <i class="fa-solid fa-chart-column"></i>
                Chưa có dữ liệu thống kê theo tháng.
            </div>

        <?php else: ?>

            <?php
            $maxMonthlyQuantity = max(
                array_map(
                    'intval',
                    array_column($monthlyStats, 'total_quantity')
                )
            );

            $maxMonthlyQuantity = max(1, $maxMonthlyQuantity);
            ?>

            <div class="consumption-chart">

                <?php foreach ($monthlyStats as $month): ?>

                    <?php
                    $quantity = (int) $month['total_quantity'];
                    $barWidth = ($quantity / $maxMonthlyQuantity) * 100;
                    ?>

                    <div class="consumption-chart-item">

                        <div class="consumption-chart-label">
                            <?php echo eTieuThu(formatMonthLabel($month['month_label'])); ?>
                        </div>

                        <div class="consumption-chart-track">

                            <div
                                class="consumption-chart-bar"
                                style="width: <?php echo $barWidth; ?>%;"
                            ></div>

                        </div>

                        <div class="consumption-chart-value">
                            <?php echo number_format($quantity); ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

    <!-- GHI CHÚ -->
    <div class="consumption-note">

        <i class="fa-solid fa-circle-info"></i>

        <div>
            <strong>Lưu ý:</strong>
            Các số liệu trong báo cáo được tổng hợp từ lịch sử xuất kho.
            Tỷ lệ tiêu thụ được tính dựa trên tổng số lượng linh kiện đã xuất
            trong khoảng thời gian được chọn.
        </div>

    </div>

</div>
