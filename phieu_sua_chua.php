
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// =====================================================
// 1. LẤY DANH SÁCH PHIẾU SỬA CHỮA
// =====================================================

$sql = "
    SELECT
        repair_tickets.id,
        repair_tickets.ticket_code,
        repair_tickets.issue_description,
        repair_tickets.diagnosis,
        repair_tickets.solution,
        repair_tickets.status,
        repair_tickets.received_at,
        repair_tickets.updated_at,

        customers.full_name AS customer_name,
        customers.phone AS customer_phone,

        devices.device_type,
        devices.brand,
        devices.model,
        devices.serial_number,

        users.full_name AS technician_name

    FROM repair_tickets

    INNER JOIN customers
        ON repair_tickets.customer_id = customers.id

    INNER JOIN devices
        ON repair_tickets.device_id = devices.id

    LEFT JOIN users
        ON repair_tickets.assigned_to = users.id

    ORDER BY repair_tickets.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$tickets = $stmt->fetchAll();

// =====================================================
// 2. CHUYỂN TRẠNG THÁI SANG TIẾNG VIỆT
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

// =====================================================
// 3. HÀM BẢO VỆ DỮ LIỆU HIỂN THỊ
// =====================================================

function e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// =====================================================
// 4. THỐNG KÊ SỐ LƯỢNG PHIẾU THEO TRẠNG THÁI
// =====================================================

$totalTickets = count($tickets);

$inProgressCount = 0;
$completedCount = 0;
$waitingCount = 0;

foreach ($tickets as $ticket) {

    if (in_array($ticket['status'], ['checking', 'repairing'], true)) {
        $inProgressCount++;
    }

    if (in_array($ticket['status'], ['completed', 'delivered'], true)) {
        $completedCount++;
    }

    if ($ticket['status'] === 'waiting_parts') {
        $waitingCount++;
    }
}

// =====================================================
// 5. HÀM LẤY CLASS TRẠNG THÁI
// =====================================================

function getStatusClass($status)
{
    return match ($status) {
        'received' => 'status-received',
        'checking' => 'status-checking',
        'waiting_parts' => 'status-waiting',
        'repairing' => 'status-repairing',
        'completed' => 'status-completed',
        'delivered' => 'status-delivered',
        'cancelled' => 'status-cancelled',
        default => 'status-default'
    };
}

// =====================================================
// 6. HÀM LẤY TÊN THIẾT BỊ
// =====================================================

function getDeviceName($ticket)
{
    return trim(
        ($ticket['device_type'] ?? '') . ' ' .
        ($ticket['brand'] ?? '') . ' ' .
        ($ticket['model'] ?? '')
    );
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quản lý phiếu sửa chữa</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 22px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6fa;
            color: #1e293b;
            overflow-x: hidden;
        }

        .page-container {
            width: 100%;
            max-width: 1800px;
            margin: 0 auto;
            min-width: 0;
        }

        /* ==============================
           HEADER
        ============================== */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .page-title {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: #172554;
        }

        .page-title i {
            margin-right: 7px;
            color: #2563eb;
        }

        .page-subtitle {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .add-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
            white-space: nowrap;
        }

        .add-button:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        /* ==============================
           THỐNG KÊ
        ============================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
            background: #fff;
            padding: 16px;
            border-radius: 10px;
            border: 1px solid #e8edf5;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.035);
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 18px;
            flex-shrink: 0;
        }

        .icon-blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .icon-orange {
            background: #ffedd5;
            color: #ea580c;
        }

        .icon-green {
            background: #dcfce7;
            color: #16a34a;
        }

        .icon-purple {
            background: #f3e8ff;
            color: #9333ea;
        }

        .stat-label {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .stat-value {
            color: #172554;
            font-size: 22px;
            font-weight: 700;
        }

        /* ==============================
           KHUNG DANH SÁCH
        ============================== */

        .content-card {
            width: 100%;
            min-width: 0;
            background: #fff;
            border: 1px solid #e8edf5;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.035);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 16px 18px;
            border-bottom: 1px solid #edf0f5;
        }

        .card-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
        }

        .card-title i {
            margin-right: 5px;
            color: #2563eb;
        }

        .ticket-count {
            color: #64748b;
            font-size: 12px;
        }

        /* ==============================
           BẢNG PHIẾU SỬA CHỮA
        ============================== */

        .table-wrapper {
            width: 100%;
            overflow: hidden;
        }

        table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        th {
            padding: 11px 7px;
            background: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-align: left;
            border-bottom: 1px solid #e8edf5;
            overflow-wrap: anywhere;
        }

        td {
            padding: 11px 7px;
            border-bottom: 1px solid #edf0f5;
            color: #334155;
            font-size: 12px;
            vertical-align: middle;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        tbody tr {
            transition: background 0.15s;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .ticket-id {
            color: #64748b;
            font-weight: 600;
        }

        .ticket-code {
            color: #2563eb;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .customer-name {
            color: #1e293b;
            font-weight: 600;
        }

        .phone-number {
            white-space: nowrap;
            font-size: 11px;
        }

        .device-name {
            font-weight: 600;
            color: #334155;
        }

        .description {
            color: #64748b;
            line-height: 1.4;
            overflow-wrap: anywhere;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .technician-name {
            color: #475569;
        }

        .unassigned {
            display: inline-block;
            padding: 4px 6px;
            border-radius: 5px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 10px;
            line-height: 1.4;
        }

        .received-date {
            font-size: 11px;
            color: #64748b;
            overflow-wrap: anywhere;
        }

        /* ==============================
           TRẠNG THÁI
        ============================== */

        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 7px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            line-height: 1.3;
            text-align: center;
            overflow-wrap: anywhere;
        }

        .status-received {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-checking {
            background: #fef3c7;
            color: #b45309;
        }

        .status-waiting {
            background: #ffedd5;
            color: #c2410c;
        }

        .status-repairing {
            background: #ede9fe;
            color: #6d28d9;
        }

        .status-completed {
            background: #dcfce7;
            color: #15803d;
        }

        .status-delivered {
            background: #ccfbf1;
            color: #0f766e;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-default {
            background: #f1f5f9;
            color: #475569;
        }

        /* ==============================
           NÚT THAO TÁC
        ============================== */

        .actions {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 5px;
            min-width: 0;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 5px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
            transition: 0.2s;
        }

        .detail-button {
            background: #e0f2fe;
            color: #0369a1;
        }

        .detail-button:hover {
            background: #bae6fd;
        }

        .edit-button {
            background: #2563eb;
            color: #fff;
        }

        .edit-button:hover {
            background: #1d4ed8;
        }

        /* ==============================
           DÒNG TRỐNG
        ============================== */

        .empty {
            padding: 45px 15px;
            text-align: center;
            color: #94a3b8;
        }

        .empty i {
            display: block;
            font-size: 32px;
            margin-bottom: 12px;
        }

        .empty-text {
            font-size: 13px;
        }

        /* ==============================
           THẺ PHIẾU TRÊN MÀN HÌNH NHỎ
        ============================== */

        .ticket-cards {
            display: none;
            padding: 12px;
        }

        .ticket-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            padding: 14px;
            margin-bottom: 12px;
        }

        .ticket-card:last-child {
            margin-bottom: 0;
        }

        .ticket-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid #edf0f5;
        }

        .ticket-card-code {
            color: #2563eb;
            font-size: 14px;
            font-weight: 700;
        }

        .ticket-card-id {
            color: #94a3b8;
            font-size: 11px;
            margin-top: 4px;
        }

        .ticket-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 11px;
        }

        .ticket-info-item {
            min-width: 0;
        }

        .ticket-info-item.full {
            grid-column: 1 / -1;
        }

        .info-label {
            display: block;
            color: #94a3b8;
            font-size: 11px;
            margin-bottom: 4px;
        }

        .info-value {
            color: #334155;
            font-size: 12px;
            font-weight: 600;
            overflow-wrap: anywhere;
            line-height: 1.5;
        }

        .ticket-card-actions {
            display: flex;
            gap: 8px;
            margin-top: 13px;
            padding-top: 11px;
            border-top: 1px solid #edf0f5;
        }

        .ticket-card-actions .action-button {
            flex: 1;
            padding: 9px 8px;
            font-size: 12px;
        }

        /* ==============================
           RESPONSIVE
        ============================== */

        @media (max-width: 1200px) {
            body {
                padding: 16px;
            }

            .table-wrapper {
                display: none;
            }

            .ticket-cards {
                display: block;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .page-header {
                align-items: stretch;
                margin-bottom: 15px;
            }

            .page-title {
                font-size: 20px;
            }

            .page-subtitle {
                font-size: 12px;
                line-height: 1.5;
            }

            .add-button {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                margin-bottom: 14px;
            }

            .stat-card {
                gap: 9px;
                padding: 11px 9px;
            }

            .stat-icon {
                width: 34px;
                height: 34px;
                font-size: 15px;
            }

            .stat-label {
                font-size: 10px;
                line-height: 1.4;
            }

            .stat-value {
                font-size: 19px;
            }

            .card-header {
                padding: 13px;
            }

            .card-title {
                font-size: 14px;
            }

            .ticket-count {
                font-size: 11px;
            }

            .ticket-cards {
                padding: 9px;
            }

            .ticket-card {
                padding: 11px;
            }

            .ticket-info {
                gap: 10px 8px;
            }
        }
    </style>
</head>

<body>

<div class="page-container">

    <!-- HEADER -->

    <div class="page-header">

        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-screwdriver-wrench"></i>
                Quản lý phiếu sửa chữa
            </h1>

            <p class="page-subtitle">
                Theo dõi thông tin và tình trạng các phiếu sửa chữa trong hệ thống.
            </p>
        </div>

        <a href="them_phieu_sua_chua.php" class="add-button">
            <i class="fa-solid fa-plus"></i>
            Tạo phiếu sửa chữa
        </a>

    </div>

    <!-- THỐNG KÊ -->

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon icon-blue">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>

            <div>
                <div class="stat-label">Tổng số phiếu</div>
                <div class="stat-value">
                    <?php echo $totalTickets; ?>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-orange">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>

            <div>
                <div class="stat-label">Đang xử lý</div>
                <div class="stat-value">
                    <?php echo $inProgressCount; ?>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-green">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <div>
                <div class="stat-label">Đã hoàn thành / bàn giao</div>
                <div class="stat-value">
                    <?php echo $completedCount; ?>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-purple">
                <i class="fa-solid fa-box-open"></i>
            </div>

            <div>
                <div class="stat-label">Chờ linh kiện</div>
                <div class="stat-value">
                    <?php echo $waitingCount; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- DANH SÁCH PHIẾU -->

    <div class="content-card">

        <div class="card-header">

            <h2 class="card-title">
                <i class="fa-solid fa-list"></i>
                Danh sách phiếu sửa chữa
            </h2>

            <span class="ticket-count">
                Hiển thị <?php echo $totalTickets; ?> phiếu sửa chữa
            </span>

        </div>

        <!-- BẢNG DÀNH CHO MÀN HÌNH LỚN -->

        <div class="table-wrapper">

            <table>

                <colgroup>
                    <col style="width: 4%">
                    <col style="width: 9%">
                    <col style="width: 10%">
                    <col style="width: 9%">
                    <col style="width: 11%">
                    <col style="width: 14%">
                    <col style="width: 10%">
                    <col style="width: 10%">
                    <col style="width: 11%">
                    <col style="width: 12%">
                </colgroup>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mã phiếu</th>
                        <th>Khách hàng</th>
                        <th>Số điện thoại</th>
                        <th>Thiết bị</th>
                        <th>Mô tả lỗi</th>
                        <th>Kỹ thuật viên</th>
                        <th>Trạng thái</th>
                        <th>Ngày tiếp nhận</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($totalTickets > 0): ?>

                        <?php foreach ($tickets as $ticket): ?>

                            <?php
                            $status = $ticket['status'];
                            $statusClass = getStatusClass($status);
                            $deviceName = getDeviceName($ticket);
                            ?>

                            <tr>

                                <td class="ticket-id">
                                    #<?php echo e($ticket['id']); ?>
                                </td>

                                <td class="ticket-code">
                                    <?php echo e($ticket['ticket_code']); ?>
                                </td>

                                <td class="customer-name">
                                    <?php echo e($ticket['customer_name']); ?>
                                </td>

                                <td class="phone-number">
                                    <?php echo e($ticket['customer_phone']); ?>
                                </td>

                                <td>
                                    <span class="device-name">
                                        <?php echo e($deviceName); ?>
                                    </span>
                                </td>

                                <td>
                                    <div
                                        class="description"
                                        title="<?php echo e($ticket['issue_description']); ?>"
                                    >
                                        <?php echo e($ticket['issue_description']); ?>
                                    </div>
                                </td>

                                <td class="technician-name">

                                    <?php if (!empty($ticket['technician_name'])): ?>

                                        <?php echo e($ticket['technician_name']); ?>

                                    <?php else: ?>

                                        <span class="unassigned">
                                            <i class="fa-solid fa-user-clock"></i>
                                            Chưa phân công
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <span class="status <?php echo e($statusClass); ?>">
                                        <?php
                                        echo e(
                                            $statusLabels[$status] ?? $status
                                        );
                                        ?>
                                    </span>
                                </td>

                                <td class="received-date">
                                    <?php echo e($ticket['received_at']); ?>
                                </td>

                                <td>

                                    <div class="actions">

                                        <a
                                            href="chi_tiet_phieu_sua_chua.php?id=<?php echo (int) $ticket['id']; ?>"
                                            class="action-button detail-button"
                                            target="mainContent"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                            Chi tiết
                                        </a>

                                        <a
                                            href="cap_nhat_phieu_sua_chua.php?id=<?php echo (int) $ticket['id']; ?>"
                                            class="action-button edit-button"
                                            target="mainContent"
                                        >
                                            <i class="fa-solid fa-pen-to-square"></i>
                                            Cập nhật
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="10" class="empty">

                                <i class="fa-solid fa-folder-open"></i>

                                <div class="empty-text">
                                    Chưa có phiếu sửa chữa nào.
                                </div>

                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- DẠNG THẺ DÀNH CHO MÀN HÌNH NHỎ -->

        <div class="ticket-cards">

            <?php if ($totalTickets > 0): ?>

                <?php foreach ($tickets as $ticket): ?>

                    <?php
                    $status = $ticket['status'];
                    $statusClass = getStatusClass($status);
                    $deviceName = getDeviceName($ticket);
                    ?>

                    <div class="ticket-card">

                        <div class="ticket-card-header">

                            <div>
                                <div class="ticket-card-code">
                                    <?php echo e($ticket['ticket_code']); ?>
                                </div>

                                <div class="ticket-card-id">
                                    ID: #<?php echo e($ticket['id']); ?>
                                </div>
                            </div>

                            <span class="status <?php echo e($statusClass); ?>">
                                <?php
                                echo e(
                                    $statusLabels[$status] ?? $status
                                );
                                ?>
                            </span>

                        </div>

                        <div class="ticket-info">

                            <div class="ticket-info-item">
                                <span class="info-label">Khách hàng</span>
                                <div class="info-value">
                                    <?php echo e($ticket['customer_name']); ?>
                                </div>
                            </div>

                            <div class="ticket-info-item">
                                <span class="info-label">Số điện thoại</span>
                                <div class="info-value">
                                    <?php echo e($ticket['customer_phone']); ?>
                                </div>
                            </div>

                            <div class="ticket-info-item full">
                                <span class="info-label">Thiết bị</span>
                                <div class="info-value">
                                    <?php echo e($deviceName); ?>
                                </div>
                            </div>

                            <div class="ticket-info-item full">
                                <span class="info-label">Mô tả lỗi</span>
                                <div class="info-value">
                                    <?php echo e($ticket['issue_description']); ?>
                                </div>
                            </div>

                            <div class="ticket-info-item">
                                <span class="info-label">Kỹ thuật viên</span>
                                <div class="info-value">
                                    <?php if (!empty($ticket['technician_name'])): ?>
                                        <?php echo e($ticket['technician_name']); ?>
                                    <?php else: ?>
                                        Chưa phân công
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="ticket-info-item">
                                <span class="info-label">Ngày tiếp nhận</span>
                                <div class="info-value">
                                    <?php echo e($ticket['received_at']); ?>
                                </div>
                            </div>

                        </div>

                        <div class="ticket-card-actions">

                            <a
                                href="chi_tiet_phieu_sua_chua.php?id=<?php echo (int) $ticket['id']; ?>"
                                class="action-button detail-button"
                                target="mainContent"
                            >
                                <i class="fa-solid fa-eye"></i>
                                Chi tiết
                            </a>

                            <a
                                href="cap_nhat_phieu_sua_chua.php?id=<?php echo (int) $ticket['id']; ?>"
                                class="action-button edit-button"
                                target="mainContent"
                            >
                                <i class="fa-solid fa-pen-to-square"></i>
                                Cập nhật
                            </a>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">
                    <i class="fa-solid fa-folder-open"></i>

                    <div class="empty-text">
                        Chưa có phiếu sửa chữa nào.
                    </div>
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>
</html>
