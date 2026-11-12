<?php

// 1. KIỂM TRA QUYỀN ADMIN HOẶC KỸ THUẬT VIÊN

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$role = $_SESSION['role'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? 0);

if (!in_array($role, ['admin', 'technician'], true)) {
    http_response_code(403);
    exit('Bạn không có quyền truy cập chức năng này.');
}

require_once __DIR__ . '/config/database.php';

// 2. HÀM BẢO VỆ DỮ LIỆU

function e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// 3. ĐỊNH DẠNG TIỀN TỆ

function dinhDangTien($soTien)
{
    return number_format((float) $soTien, 0, ',', '.') . ' đ';
}

// 4. KIỂM TRA ID PHIẾU

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
}

if (!$id || $id <= 0) {
    exit('ID phiếu sửa chữa không hợp lệ.');
}

// 5. TẠO CSRF TOKEN

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 6. LẤY THÔNG TIN PHIẾU SỬA CHỮA

$stmt = $pdo->prepare("
    SELECT
        rt.*,
        c.full_name AS customer_name,
        c.phone AS customer_phone,
        d.device_type,
        d.brand,
        d.model
    FROM repair_tickets rt
    INNER JOIN customers c ON rt.customer_id = c.id
    INNER JOIN devices d ON rt.device_id = d.id
    WHERE rt.id = :id
");

$stmt->execute([':id' => $id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    exit('Không tìm thấy phiếu sửa chữa.');
}

// KỸ THUẬT VIÊN CHỈ ĐƯỢC XEM PHIẾU ĐƯỢC GIAO CHO MÌNH

if (
    $role === 'technician'
    && (int) $ticket['assigned_to'] !== $userId
) {
    http_response_code(403);
    exit('Bạn không có quyền quản lý linh kiện của phiếu này.');
}

// 7. KHỞI TẠO THÔNG BÁO

$loi = '';

// 8. XỬ LÝ KHI THÊM LINH KIỆN

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    $partId = filter_input(INPUT_POST, 'part_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if (
        !is_string($csrfToken) ||
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $loi = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } elseif (!$partId || $partId <= 0) {
        $loi = 'Vui lòng chọn linh kiện hợp lệ.';
    } elseif ($quantity === false || $quantity === null || $quantity <= 0) {
        $loi = 'Số lượng sử dụng phải lớn hơn 0.';
    } else {

        try {

            // BẮT ĐẦU GIAO DỊCH

            $pdo->beginTransaction();

            // KHÓA PHIẾU SỬA CHỮA

            $stmt = $pdo->prepare("
                SELECT id, assigned_to, status, stock_deducted_at
                FROM repair_tickets
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([':id' => $id]);
            $ticketLocked = $stmt->fetch();

            if (!$ticketLocked) {
                throw new RuntimeException('Không tìm thấy phiếu sửa chữa.');
            }

            // KIỂM TRA LẠI QUYỀN SAU KHI KHÓA PHIẾU
            // TRÁNH TRƯỜNG HỢP PHIẾU ĐÃ ĐƯỢC CHUYỂN CHO KỸ THUẬT VIÊN KHÁC

            if (
                $role === 'technician'
                && (int) $ticketLocked['assigned_to'] !== $userId
            ) {
                throw new RuntimeException(
                    'Phiếu này không còn được giao cho bạn. Không thể thêm linh kiện.'
                );
            }

            // KHÔNG CHO THÊM LINH KIỆN VÀO PHIẾU ĐÃ KẾT THÚC

            if (
                in_array(
                    $ticketLocked['status'],
                    ['completed', 'delivered', 'cancelled'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Phiếu đã sửa xong hoặc đã kết thúc, không thể thêm linh kiện.'
                );
            }

            // KHÔNG CHO THÊM NẾU PHIẾU ĐÃ TRỪ KHO

            if (!empty($ticketLocked['stock_deducted_at'])) {
                throw new RuntimeException(
                    'Phiếu này đã được xuất kho, không thể thêm linh kiện.'
                );
            }

            // KHÓA LINH KIỆN

            $stmt = $pdo->prepare("
                SELECT id, part_name, quantity, selling_price
                FROM parts
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([':id' => $partId]);
            $part = $stmt->fetch();

            if (!$part) {
                throw new RuntimeException('Không tìm thấy linh kiện.');
            }

            // TÍNH SỐ LƯỢNG LINH KIỆN ĐÃ CÓ TRONG PHIẾU

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(quantity), 0)
                FROM ticket_parts
                WHERE ticket_id = :ticket_id
                  AND part_id = :part_id
            ");

            $stmt->execute([
                ':ticket_id' => $id,
                ':part_id' => $partId
            ]);

            $alreadyAdded = (int) $stmt->fetchColumn();

            $totalNeeded = $alreadyAdded + $quantity;

            // KIỂM TRA TỔNG SỐ LƯỢNG SO VỚI TỒN KHO

            if ((int) $part['quantity'] < $totalNeeded) {
                throw new RuntimeException(
                    'Không đủ hàng trong kho cho phiếu này. Linh kiện "' .
                    $part['part_name'] .
                    '" có ' . (int) $part['quantity'] .
                    ' sản phẩm trong kho, phiếu đã ghi nhận ' .
                    $alreadyAdded . ' sản phẩm.'
                );
            }

            // CHỈ LƯU LINH KIỆN VÀO PHIẾU
            // KHÔNG TRỪ TỒN KHO Ở BƯỚC NÀY

            $stmt = $pdo->prepare("
                INSERT INTO ticket_parts (
                    ticket_id,
                    part_id,
                    quantity,
                    unit_price,
                    created_at
                )
                VALUES (
                    :ticket_id,
                    :part_id,
                    :quantity,
                    :unit_price,
                    NOW()
                )
            ");

            $stmt->execute([
                ':ticket_id' => $id,
                ':part_id' => $partId,
                ':quantity' => $quantity,
                ':unit_price' => $part['selling_price']
            ]);

            // HOÀN TẤT GIAO DỊCH

            $pdo->commit();

            header(
                'Location: chi_tiet_linh_kien_phieu.php?id=' .
                $id . '&added=1'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            if ($e instanceof RuntimeException) {
                $loi = $e->getMessage();
            } else {
                $loi = 'Có lỗi xảy ra khi thêm linh kiện. Vui lòng thử lại.';
            }
        }
    }
}

// 9. LẤY DANH SÁCH LINH KIỆN CÒN HÀNG

$stmt = $pdo->query("
    SELECT id, part_name, sku, quantity, selling_price
    FROM parts
    WHERE quantity > 0
    ORDER BY part_name ASC
");

$parts = $stmt->fetchAll();

// 10. LẤY DANH SÁCH LINH KIỆN TRONG PHIẾU

$stmt = $pdo->prepare("
    SELECT
        tp.id,
        tp.quantity,
        tp.unit_price,
        tp.created_at,
        p.part_name,
        p.sku
    FROM ticket_parts tp
    INNER JOIN parts p ON tp.part_id = p.id
    WHERE tp.ticket_id = :ticket_id
    ORDER BY tp.id DESC
");

$stmt->execute([':ticket_id' => $id]);
$ticketParts = $stmt->fetchAll();

// 11. TÍNH TỔNG TIỀN LINH KIỆN

$tongTienLinhKien = 0;

foreach ($ticketParts as $ticketPart) {
    $tongTienLinhKien +=
        (int) $ticketPart['quantity']
        * (float) $ticketPart['unit_price'];
}

// 12. KIỂM TRA THÔNG BÁO

$daThem = isset($_GET['added']) && $_GET['added'] === '1';

$trangThaiLabels = [
    'received' => 'Đã tiếp nhận',
    'checking' => 'Đang kiểm tra',
    'waiting_parts' => 'Chờ linh kiện',
    'repairing' => 'Đang sửa chữa',
    'completed' => 'Đã sửa xong',
    'delivered' => 'Đã bàn giao',
    'cancelled' => 'Đã hủy'
];

$khongDuocThem = in_array(
    $ticket['status'],
    ['completed', 'delivered', 'cancelled'],
    true
);

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Linh kiện sử dụng trong phiếu sửa chữa</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            padding: 30px;
            color: #222;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 8px;
            margin-bottom: 22px;
        }

        h1, h2 {
            margin-top: 0;
        }

        .ticket-info {
            line-height: 1.9;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            margin-top: 18px;
            padding: 11px 18px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
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

        .success {
            padding: 12px;
            background: #dcfce7;
            color: #166534;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .error {
            padding: 12px;
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .info {
            padding: 12px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 5px;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .total {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            margin-top: 20px;
        }

        .back {
            display: inline-block;
            margin-top: 15px;
            color: #2563eb;
            text-decoration: none;
        }

        .empty {
            text-align: center;
            color: #777;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>Linh kiện sử dụng trong phiếu sửa chữa</h1>

    <!-- THÔNG TIN PHIẾU -->

    <div class="card">

        <h2>Thông tin phiếu sửa chữa</h2>

        <div class="ticket-info">

            <strong>Mã phiếu:</strong>
            <?php echo e($ticket['ticket_code']); ?>
            <br>

            <strong>Khách hàng:</strong>
            <?php echo e($ticket['customer_name']); ?>
            <br>

            <strong>Số điện thoại:</strong>
            <?php echo e($ticket['customer_phone']); ?>
            <br>

            <strong>Thiết bị:</strong>
            <?php
            echo e(
                $ticket['brand'] . ' ' .
                $ticket['model'] . ' (' .
                $ticket['device_type'] . ')'
            );
            ?>
            <br>

            <strong>Trạng thái:</strong>
            <?php
            echo e(
                $trangThaiLabels[$ticket['status']]
                ?? $ticket['status']
            );
            ?>

        </div>

        <a
            href="chi_tiet_phieu_sua_chua.php?id=<?php echo (int) $id; ?>"
            class="back"
        >
            ← Quay lại chi tiết phiếu sửa chữa
        </a>

    </div>

    <!-- THÔNG BÁO -->

    <?php if ($daThem): ?>

        <div class="success">
            Đã thêm linh kiện vào phiếu thành công.
        </div>

    <?php endif; ?>

    <?php if ($loi !== ''): ?>

        <div class="error">
            <?php echo e($loi); ?>
        </div>

    <?php endif; ?>

    <div class="info">
        <strong>Lưu ý:</strong>
        Thêm linh kiện vào phiếu chỉ ghi nhận số lượng sử dụng,
        chưa trừ tồn kho. Hệ thống sẽ tự động trừ kho và ghi lịch sử
        xuất kho khi phiếu chuyển sang trạng thái
        <strong>Đã sửa xong</strong>.
    </div>

    <!-- FORM THÊM LINH KIỆN -->

    <div class="card">

        <h2>Thêm linh kiện sử dụng</h2>

        <?php if (!$khongDuocThem): ?>

            <?php if (count($parts) > 0): ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="ticket_id"
                        value="<?php echo (int) $id; ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e($_SESSION['csrf_token']); ?>"
                    >

                    <label>Chọn linh kiện</label>

                    <select name="part_id" required>

                        <option value="">
                            -- Chọn linh kiện --
                        </option>

                        <?php foreach ($parts as $part): ?>

                            <option value="<?php echo (int) $part['id']; ?>">

                                <?php echo e($part['part_name']); ?>

                                - Tồn kho:
                                <?php echo (int) $part['quantity']; ?>

                                - Giá bán:
                                <?php echo dinhDangTien($part['selling_price']); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <label>Số lượng sử dụng</label>

                    <input
                        type="number"
                        name="quantity"
                        min="1"
                        step="1"
                        value="1"
                        required
                    >

                    <button type="submit">
                        + Thêm linh kiện vào phiếu
                    </button>

                </form>

            <?php else: ?>

                <p class="empty">
                    Hiện tại không có linh kiện nào còn hàng trong kho.
                </p>

            <?php endif; ?>

        <?php else: ?>

            <p class="error">
                Phiếu sửa chữa đã sửa xong hoặc đã kết thúc,
                không thể thêm linh kiện.
            </p>

        <?php endif; ?>

    </div>

    <!-- DANH SÁCH LINH KIỆN -->

    <div class="card">

        <h2>Danh sách linh kiện đã sử dụng</h2>

        <table>

            <thead>

                <tr>
                    <th>STT</th>
                    <th>Tên linh kiện</th>
                    <th>Mã SKU</th>
                    <th>Số lượng</th>
                    <th>Đơn giá</th>
                    <th>Thành tiền</th>
                </tr>

            </thead>

            <tbody>

                <?php if (count($ticketParts) > 0): ?>

                    <?php foreach ($ticketParts as $index => $ticketPart): ?>

                        <tr>

                            <td>
                                <?php echo $index + 1; ?>
                            </td>

                            <td>
                                <?php echo e($ticketPart['part_name']); ?>
                            </td>

                            <td>
                                <?php echo e($ticketPart['sku'] ?: 'Chưa có'); ?>
                            </td>

                            <td>
                                <?php echo (int) $ticketPart['quantity']; ?>
                            </td>

                            <td>
                                <?php echo dinhDangTien($ticketPart['unit_price']); ?>
                            </td>

                            <td>
                                <?php
                                echo dinhDangTien(
                                    $ticketPart['quantity'] *
                                    $ticketPart['unit_price']
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="6" class="empty">
                            Phiếu sửa chữa chưa sử dụng linh kiện nào.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

        <div class="total">

            Tổng tiền linh kiện:
            <?php echo dinhDangTien($tongTienLinhKien); ?>

        </div>

    </div>

</div>

</body>

</html>