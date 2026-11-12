<?php
// 1. KIỂM TRA ĐĂNG NHẬP
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// 2. KẾT NỐI DATABASE
require_once __DIR__ . '/config/database.php';

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// 3. KIỂM TRA ID PHIẾU
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$ticketId || $ticketId <= 0) {
    exit('Mã phiếu sửa chữa không hợp lệ.');
}

// 4. LẤY THÔNG TIN PHIẾU
$sql = "
    SELECT
        repair_tickets.*,
        customers.full_name AS customer_name,
        customers.phone AS customer_phone,
        customers.email AS customer_email,
        customers.address AS customer_address,
        devices.device_type,
        devices.brand,
        devices.model,
        devices.serial_number,
        devices.description AS device_description,
        users.full_name AS technician_name
    FROM repair_tickets
    INNER JOIN customers
        ON repair_tickets.customer_id = customers.id
    INNER JOIN devices
        ON repair_tickets.device_id = devices.id
    LEFT JOIN users
        ON repair_tickets.assigned_to = users.id
    WHERE repair_tickets.id = :id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':id' => $ticketId
]);

$ticket = $stmt->fetch();

if (!$ticket) {
    exit('Không tìm thấy phiếu sửa chữa.');
}

// 5. KIỂM TRA QUYỀN XEM PHIẾU
if ($role === 'technician') {
    if ((int) $ticket['assigned_to'] !== $userId) {
        http_response_code(403);
        exit('Bạn không có quyền xem phiếu sửa chữa này.');
    }
} elseif ($role !== 'admin') {
    http_response_code(403);
    exit('Bạn không có quyền truy cập.');
}

// 6. LẤY LỊCH SỬ TRẠNG THÁI
$stmt = $pdo->prepare("
    SELECT
        ticket_status_history.*,
        users.full_name AS changed_by_name
    FROM ticket_status_history
    LEFT JOIN users
        ON ticket_status_history.changed_by = users.id
    WHERE ticket_status_history.ticket_id = :ticket_id
    ORDER BY ticket_status_history.created_at ASC,
             ticket_status_history.id ASC
");

$stmt->execute([
    ':ticket_id' => $ticketId
]);

$history = $stmt->fetchAll();

// 7. DỊCH TRẠNG THÁI
$statusLabels = [
    'received' => 'Đã tiếp nhận',
    'checking' => 'Đang kiểm tra',
    'waiting_parts' => 'Chờ linh kiện',
    'repairing' => 'Đang sửa chữa',
    'completed' => 'Đã sửa xong',
    'delivered' => 'Đã bàn giao',
    'cancelled' => 'Đã hủy'
];

// 8. HÀM HIỂN THỊ AN TOÀN
function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// 9. ĐƯỜNG DẪN QUAY LẠI
$backUrl = $role === 'technician'
    ? 'technician/index.php'
    : 'phieu_sua_chua.php';

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Chi tiết phiếu sửa chữa</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            padding: 30px;
            color: #222;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .section {
            background: white;
            padding: 22px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        h1 {
            margin-top: 0;
        }

        h2 {
            font-size: 19px;
            border-bottom: 1px solid #eee;
            padding-bottom: 12px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .info-item {
            margin-bottom: 8px;
            line-height: 1.6;
        }

        .info-item strong {
            display: inline-block;
            min-width: 145px;
        }

        .description {
            background: #f8fafc;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-wrap;
            line-height: 1.6;
        }

        .status {
            display: inline-block;
            background: #eaf2ff;
            color: #2563eb;
            padding: 7px 12px;
            border-radius: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #f0f2f5;
        }

        .back {
            display: inline-block;
            margin: 0 15px 20px 0;
            color: #2563eb;
            text-decoration: none;
        }

        .empty {
            color: #777;
            text-align: center;
        }

        @media (max-width: 650px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            body {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a class="back" href="<?php echo e($backUrl); ?>">
        ← Quay lại danh sách phiếu
    </a>

    <a class="back"
       href="cap_nhat_phieu_sua_chua.php?id=<?php echo $ticketId; ?>">
        Cập nhật phiếu sửa chữa
    </a>

    <?php if ($role === 'admin'): ?>

    <a class="back"
       href="them_linh_kien_phieu.php?ticket_id=<?php echo (int) $ticketId; ?>">
        + Thêm linh kiện
    </a>

    <a class="back"
       href="chi_tiet_linh_kien_phieu.php?id=<?php echo (int) $ticketId; ?>">
        Quản lý linh kiện sử dụng
    </a>

<?php elseif ($role === 'technician'): ?>

    <a class="back"
       href="chi_tiet_linh_kien_phieu.php?id=<?php echo (int) $ticketId; ?>">
        + Quản lý linh kiện sử dụng
    </a>

<?php endif; ?>

    <div class="section">

        <h1>Chi tiết phiếu sửa chữa</h1>

        <div class="info-grid">

            <div class="info-item">
                <strong>Mã phiếu:</strong>
                <?php echo e($ticket['ticket_code']); ?>
            </div>

            <div class="info-item">
                <strong>Ngày tiếp nhận:</strong>
                <?php echo e($ticket['received_at']); ?>
            </div>

            <div class="info-item">
                <strong>Trạng thái:</strong>
                <span class="status">
                    <?php
                    echo e(
                        $statusLabels[$ticket['status']]
                        ?? $ticket['status']
                    );
                    ?>
                </span>
            </div>

            <div class="info-item">
                <strong>Kỹ thuật viên:</strong>
                <?php echo e($ticket['technician_name'] ?? 'Chưa phân công'); ?>
            </div>

        </div>

    </div>

    <div class="section">

        <h2>1. Thông tin khách hàng</h2>

        <div class="info-item">
            <strong>Họ và tên:</strong>
            <?php echo e($ticket['customer_name']); ?>
        </div>

        <div class="info-item">
            <strong>Số điện thoại:</strong>
            <?php echo e($ticket['customer_phone']); ?>
        </div>

        <div class="info-item">
            <strong>Email:</strong>
            <?php echo e($ticket['customer_email'] ?: 'Chưa cập nhật'); ?>
        </div>

        <div class="info-item">
            <strong>Địa chỉ:</strong>
            <?php echo e($ticket['customer_address'] ?: 'Chưa cập nhật'); ?>
        </div>

    </div>

    <div class="section">

        <h2>2. Thông tin thiết bị</h2>

        <div class="info-item">
            <strong>Loại thiết bị:</strong>
            <?php echo e($ticket['device_type']); ?>
        </div>

        <div class="info-item">
            <strong>Thương hiệu:</strong>
            <?php echo e($ticket['brand']); ?>
        </div>

        <div class="info-item">
            <strong>Model:</strong>
            <?php echo e($ticket['model']); ?>
        </div>

        <div class="info-item">
            <strong>Số serial:</strong>
            <?php echo e($ticket['serial_number'] ?: 'Chưa cập nhật'); ?>
        </div>

        <div class="info-item">
            <strong>Mô tả thiết bị:</strong>
            <?php echo e($ticket['device_description'] ?: 'Không có'); ?>
        </div>

    </div>

    <div class="section">

        <h2>3. Thông tin sửa chữa</h2>

        <h3>Mô tả tình trạng lỗi</h3>
        <div class="description">
            <?php echo e($ticket['issue_description']); ?>
        </div>

        <h3>Chẩn đoán kỹ thuật</h3>
        <div class="description">
            <?php echo e($ticket['diagnosis'] ?: 'Chưa có chẩn đoán.'); ?>
        </div>

        <h3>Phương án sửa chữa</h3>
        <div class="description">
            <?php echo e($ticket['solution'] ?: 'Chưa cập nhật phương án sửa chữa.'); ?>
        </div>

    </div>

    <div class="section">

        <h2>4. Lịch sử trạng thái</h2>

        <table>

            <thead>
                <tr>
                    <th>Thời gian</th>
                    <th>Trạng thái cũ</th>
                    <th>Trạng thái mới</th>
                    <th>Người thực hiện</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>

            <tbody>

                <?php if (count($history) > 0): ?>

                    <?php foreach ($history as $item): ?>

                        <tr>

                            <td><?php echo e($item['created_at']); ?></td>

                            <td>
                                <?php
                                echo e(
                                    $statusLabels[$item['old_status']]
                                    ?? ($item['old_status'] ?: '—')
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo e(
                                    $statusLabels[$item['new_status']]
                                    ?? $item['new_status']
                                );
                                ?>
                            </td>

                            <td>
                                <?php echo e($item['changed_by_name'] ?? 'Hệ thống'); ?>
                            </td>

                            <td><?php echo e($item['note'] ?: '—'); ?></td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty">
                            Chưa có lịch sử trạng thái.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>