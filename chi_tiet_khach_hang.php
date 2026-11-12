
<?php
// ======================================================
// 1. KIỂM TRA QUYỀN VÀ KẾT NỐI DATABASE
// ======================================================

require_once __DIR__ . '/includes/auth.php';
requireAdminOrTechnician();

require_once __DIR__ . '/config/database.php';

// ======================================================
// 2. LẤY ID KHÁCH HÀNG
// ======================================================

$customerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($customerId <= 0) {
    http_response_code(400);
    exit('ID khách hàng không hợp lệ.');
}

// ======================================================
// 3. HÀM AN TOÀN VÀ ĐỊNH DẠNG NGÀY
// ======================================================

function eChiTiet($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatDateCustomer($value): string
{
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp
        ? date('d/m/Y H:i', $timestamp)
        : '—';
}

// ======================================================
// 4. LẤY THÔNG TIN KHÁCH HÀNG
// ======================================================

$sqlCustomer = "
    SELECT
        id,
        full_name,
        phone,
        email,
        address
    FROM customers
    WHERE id = :id
    LIMIT 1
";

$stmtCustomer = $pdo->prepare($sqlCustomer);
$stmtCustomer->execute([
    ':id' => $customerId
]);

$customer = $stmtCustomer->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    http_response_code(404);
    exit('Không tìm thấy khách hàng.');
}

// ======================================================
// 5. LẤY DANH SÁCH THIẾT BỊ CỦA KHÁCH HÀNG
// ======================================================

$sqlDevices = "
    SELECT
        id,
        device_type,
        brand,
        model,
        serial_number,
        description,
        created_at
    FROM devices
    WHERE customer_id = :customer_id
    ORDER BY created_at DESC, id DESC
";

$stmtDevices = $pdo->prepare($sqlDevices);
$stmtDevices->execute([
    ':customer_id' => $customerId
]);

$devices = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);

// ======================================================
// 6. LẤY LỊCH SỬ PHIẾU SỬA CHỮA
// ======================================================

$sqlTickets = "
    SELECT
        rt.id,
        rt.ticket_code,
        rt.device_id,
        rt.issue_description,
        rt.status,
        rt.received_at,
        rt.updated_at,

        d.device_type,
        d.brand,
        d.model,

        u.full_name AS technician_name

    FROM repair_tickets rt

    LEFT JOIN devices d
        ON rt.device_id = d.id

    LEFT JOIN users u
        ON rt.assigned_to = u.id

    WHERE rt.customer_id = :customer_id

    ORDER BY rt.received_at DESC, rt.id DESC
";

$stmtTickets = $pdo->prepare($sqlTickets);
$stmtTickets->execute([
    ':customer_id' => $customerId
]);

$tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

// ======================================================
// 7. THỐNG KÊ
// ======================================================

$totalDevices = count($devices);
$totalTickets = count($tickets);

$latestStatus = $tickets[0]['status'] ?? null;

// ======================================================
// 8. ÁNH XẠ TRẠNG THÁI PHIẾU
// ======================================================

$statusLabels = [
    'received'      => 'Đã tiếp nhận',
    'checking'      => 'Đang kiểm tra',
    'waiting_parts' => 'Chờ linh kiện',
    'repairing'     => 'Đang sửa chữa',
    'completed'     => 'Đã hoàn thành',
    'delivered'     => 'Đã bàn giao',
    'cancelled'     => 'Đã hủy'
];

$statusClasses = [
    'received'      => 'status-received',
    'checking'      => 'status-checking',
    'waiting_parts' => 'status-waiting',
    'repairing'     => 'status-repairing',
    'completed'     => 'status-completed',
    'delivered'     => 'status-delivered',
    'cancelled'     => 'status-cancelled'
];

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Chi tiết khách hàng</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            padding: 30px;
            margin: 0;
            color: #222;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0;
            font-size: 26px;
        }

        .back-button {
            display: inline-block;
            padding: 10px 16px;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
        }

        .back-button:hover {
            background: #475569;
        }

        .section {
            background: white;
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .section h2 {
            font-size: 19px;
            margin: 0 0 20px;
            padding-bottom: 13px;
            border-bottom: 1px solid #eee;
        }

        .customer-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 14px;
            background: #f8fafc;
            border-radius: 7px;
        }

        .info-label {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 7px;
        }

        .info-value {
            font-size: 15px;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .statistics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .stat-title {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 26px;
            font-weight: bold;
            color: #2563eb;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th,
        td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            vertical-align: middle;
        }

        th {
            background: #f1f5f9;
            font-weight: bold;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-received {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-checking {
            background: #fef3c7;
            color: #92400e;
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

        .empty {
            text-align: center;
            padding: 25px;
            color: #64748b;
        }

        .latest-status {
            font-size: 14px;
            color: #334155;
        }

        @media (max-width: 700px) {
            body {
                padding: 15px;
            }

            .customer-info {
                grid-template-columns: 1fr;
            }

            .statistics {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 22px;
            }

            .section {
                padding: 16px;
            }
        }
        .ticket-link {
    color: #2563eb;
    font-weight: bold;
    text-decoration: none;
}

.ticket-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}
    </style>
</head>

<body>

<div class="container">

    <!-- TIÊU ĐỀ -->
    <div class="page-header">

        <h1>Chi tiết khách hàng</h1>

        <a href="khach_hang.php" class="back-button">
            ← Quay lại danh sách
        </a>

    </div>

    <!-- THỐNG KÊ -->
    <div class="statistics">

        <div class="stat-card">
            <div class="stat-title">Mã khách hàng</div>

            <div class="stat-number">
                #<?php echo (int) $customer['id']; ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Tổng số thiết bị</div>

            <div class="stat-number">
                <?php echo $totalDevices; ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Tổng số phiếu sửa chữa</div>

            <div class="stat-number">
                <?php echo $totalTickets; ?>
            </div>
        </div>

    </div>

    <!-- THÔNG TIN KHÁCH HÀNG -->
    <div class="section">

        <h2>Thông tin khách hàng</h2>

        <div class="customer-info">

            <div class="info-item">
                <div class="info-label">Họ và tên</div>

                <div class="info-value">
                    <?php echo eChiTiet($customer['full_name']); ?>
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Số điện thoại</div>

                <div class="info-value">
                    <?php echo eChiTiet($customer['phone']); ?>
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Email</div>

                <div class="info-value">
                    <?php echo eChiTiet($customer['email'] ?: 'Chưa cập nhật'); ?>
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Địa chỉ</div>

                <div class="info-value">
                    <?php echo eChiTiet($customer['address'] ?: 'Chưa cập nhật'); ?>
                </div>
            </div>

        </div>

    </div>

    <!-- DANH SÁCH THIẾT BỊ -->
    <div class="section">

        <h2>Danh sách thiết bị</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Loại thiết bị</th>
                        <th>Hãng</th>
                        <th>Model</th>
                        <th>Serial Number</th>
                        <th>Ngày đăng ký</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($devices)): ?>

                        <?php foreach ($devices as $device): ?>

                            <tr>

                                <td>
                                    <?php echo (int) $device['id']; ?>
                                </td>

                                <td>
                                    <?php echo eChiTiet($device['device_type']); ?>
                                </td>

                                <td>
                                    <?php echo eChiTiet($device['brand'] ?: '—'); ?>
                                </td>

                                <td>
                                    <?php echo eChiTiet($device['model'] ?: '—'); ?>
                                </td>

                                <td>
                                    <?php echo eChiTiet($device['serial_number'] ?: '—'); ?>
                                </td>

                                <td>
                                    <?php echo formatDateCustomer($device['created_at']); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="empty">
                                Khách hàng chưa có thiết bị nào.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- LỊCH SỬ SỬA CHỮA -->
    <div class="section">

        <h2>Lịch sử sửa chữa</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Mã phiếu</th>
                        <th>Thiết bị</th>
                        <th>Mô tả lỗi</th>
                        <th>Kỹ thuật viên</th>
                        <th>Trạng thái</th>
                        <th>Ngày tiếp nhận</th>
                        <th>Cập nhật gần nhất</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($tickets)): ?>

                        <?php foreach ($tickets as $ticket): ?>

                            <?php
                                $deviceName = trim(
                                    ($ticket['device_type'] ?? '') . ' ' .
                                    ($ticket['brand'] ?? '') . ' ' .
                                    ($ticket['model'] ?? '')
                                );

                                $status = $ticket['status'];

                                $statusLabel = $statusLabels[$status] ?? $status;

                                $statusClass = $statusClasses[$status] ?? '';
                            ?>

                            <tr>

                                <td>
    <a
        href="chi_tiet_phieu_sua_chua.php?id=<?php echo (int) $ticket['id']; ?>"
        class="ticket-link"
    >
        <?php echo eChiTiet($ticket['ticket_code']); ?>
    </a>
</td>   

                                <td>
                                    <?php echo eChiTiet($deviceName ?: 'Không xác định'); ?>
                                </td>

                                <td>
                                    <?php echo eChiTiet($ticket['issue_description']); ?>
                                </td>

                                <td>
                                    <?php echo eChiTiet($ticket['technician_name'] ?: 'Chưa phân công'); ?>
                                </td>

                                <td>
                                    <span class="status <?php echo eChiTiet($statusClass); ?>">
                                        <?php echo eChiTiet($statusLabel); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php echo formatDateCustomer($ticket['received_at']); ?>
                                </td>

                                <td>
                                    <?php echo formatDateCustomer($ticket['updated_at']); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="empty">
                                Khách hàng chưa có phiếu sửa chữa nào.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
