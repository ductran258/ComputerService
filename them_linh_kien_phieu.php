
<?php

// 1. KIỂM TRA QUYỀN ADMIN

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

$loi = '';
$ticketId = filter_input(INPUT_GET, 'ticket_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
}

// 2. KIỂM TRA PHIẾU SỬA CHỮA

if (!$ticketId || $ticketId <= 0) {
    exit('Mã phiếu sửa chữa không hợp lệ.');
}

$stmt = $pdo->prepare("
    SELECT id, ticket_code, status
    FROM repair_tickets
    WHERE id = :id
");

$stmt->execute([':id' => $ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    exit('Không tìm thấy phiếu sửa chữa.');
}

// Không thêm linh kiện vào phiếu đã kết thúc
if (in_array($ticket['status'], ['completed', 'delivered', 'cancelled'], true)) {
    exit('Không thể thêm linh kiện vào phiếu đã hoàn tất hoặc kết thúc.');
}

// 3. TẠO CSRF TOKEN

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. LẤY DANH SÁCH LINH KIỆN

$stmt = $pdo->query("
    SELECT id, part_name, sku, quantity, selling_price
    FROM parts
    ORDER BY part_name ASC
");

$parts = $stmt->fetchAll();

// 5. XỬ LÝ KHI GỬI FORM

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';
    $partId = filter_input(INPUT_POST, 'part_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $loi = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } elseif (!$partId || $partId <= 0) {
        $loi = 'Vui lòng chọn linh kiện.';
    } elseif (!$quantity || $quantity <= 0) {
        $loi = 'Số lượng phải lớn hơn 0.';
    } else {

        try {

            $pdo->beginTransaction();

            // Khóa dòng linh kiện để tránh cập nhật đồng thời
            $stmt = $pdo->prepare("
                SELECT id, part_name, quantity, selling_price
                FROM parts
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([':id' => $partId]);
            $part = $stmt->fetch();

            if (!$part) {
                throw new Exception('Linh kiện không tồn tại.');
            }

            // Kiểm tra lại trạng thái phiếu
            $stmt = $pdo->prepare("
                SELECT status
                FROM repair_tickets
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([':id' => $ticketId]);
            $ticketCurrent = $stmt->fetch();

            if (
                !$ticketCurrent ||
                in_array($ticketCurrent['status'], ['completed', 'delivered', 'cancelled'], true)
            ) {
                throw new Exception('Phiếu đã kết thúc, không thể thêm linh kiện.');
            }

            // Tính tổng số lượng linh kiện đã thêm vào phiếu
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(quantity), 0)
                FROM ticket_parts
                WHERE ticket_id = :ticket_id
                  AND part_id = :part_id
            ");

            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':part_id' => $partId
            ]);

            $alreadyAdded = (int) $stmt->fetchColumn();

            $available = (int) $part['quantity'] - $alreadyAdded;

            if ($quantity > $available) {
                throw new Exception(
                    'Kho không đủ linh kiện. Số lượng còn có thể thêm vào phiếu: '
                    . max(0, $available)
                );
            }

            // Thêm linh kiện vào phiếu
            $stmt = $pdo->prepare("
                INSERT INTO ticket_parts (
                    ticket_id,
                    part_id,
                    quantity,
                    unit_price
                )
                VALUES (
                    :ticket_id,
                    :part_id,
                    :quantity,
                    :unit_price
                )
            ");

            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':part_id' => $partId,
                ':quantity' => $quantity,
                ':unit_price' => $part['selling_price']
            ]);

            $pdo->commit();

            header(
                'Location: them_linh_kien_phieu.php?ticket_id=' . $ticketId . '&success=1'
            );
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $loi = $e->getMessage();
        }
    }
}

// 6. LẤY DANH SÁCH LINH KIỆN ĐÃ THÊM VÀO PHIẾU

$stmt = $pdo->prepare("
    SELECT
        tp.quantity,
        tp.unit_price,
        tp.created_at,
        p.part_name,
        p.sku
    FROM ticket_parts tp
    INNER JOIN parts p ON p.id = tp.part_id
    WHERE tp.ticket_id = :ticket_id
    ORDER BY tp.id DESC
");

$stmt->execute([':ticket_id' => $ticketId]);
$ticketParts = $stmt->fetchAll();

// 7. HÀM BẢO VỆ DỮ LIỆU

function eThemLinhKien($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Thêm linh kiện vào phiếu</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            padding: 30px;
        }

        .container {
            max-width: 850px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        h1, h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 11px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        button {
            margin-top: 20px;
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .error, .success {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f1f5f9;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .empty {
            color: #777;
            margin-top: 15px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Thêm linh kiện vào phiếu sửa chữa</h1>

    <p>
        Mã phiếu:
        <strong><?php echo eThemLinhKien($ticket['ticket_code']); ?></strong>
    </p>

    <?php if (isset($_GET['success'])): ?>
        <div class="success">
            Đã thêm linh kiện vào phiếu thành công.
        </div>
    <?php endif; ?>

    <?php if ($loi !== ''): ?>
        <div class="error">
            <?php echo eThemLinhKien($loi); ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="ticket_id"
            value="<?php echo (int) $ticketId; ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo eThemLinhKien($_SESSION['csrf_token']); ?>"
        >

        <label>Chọn linh kiện</label>

        <select name="part_id" required>
            <option value="">-- Chọn linh kiện --</option>

            <?php foreach ($parts as $part): ?>
                <option
                    value="<?php echo (int) $part['id']; ?>"
                    <?php echo isset($_POST['part_id']) && (int) $_POST['part_id'] === (int) $part['id'] ? 'selected' : ''; ?>
                >
                    <?php
                    echo eThemLinhKien($part['part_name'])
                        . ' | Tồn kho: ' . (int) $part['quantity']
                        . ' | Giá: ' . number_format((float) $part['selling_price'], 0, ',', '.')
                        . ' VNĐ';
                    ?>
                </option>
            <?php endforeach; ?>

        </select>

        <label>Số lượng sử dụng</label>

        <input
            type="number"
            name="quantity"
            min="1"
            value="<?php echo eThemLinhKien($_POST['quantity'] ?? 1); ?>"
            required
        >

        <button type="submit">
            Thêm linh kiện
        </button>

    </form>

    <hr style="margin: 30px 0;">

    <h2>Linh kiện đã thêm vào phiếu</h2>

    <?php if (empty($ticketParts)): ?>

        <p class="empty">
            Phiếu sửa chữa này chưa có linh kiện nào.
        </p>

    <?php else: ?>

        <table>
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Linh kiện</th>
                    <th>SKU</th>
                    <th>Số lượng</th>
                    <th>Đơn giá</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($ticketParts as $index => $item): ?>

                    <tr>
                        <td><?php echo $index + 1; ?></td>

                        <td>
                            <?php echo eThemLinhKien($item['part_name']); ?>
                        </td>

                        <td>
                            <?php echo eThemLinhKien($item['sku']); ?>
                        </td>

                        <td>
                            <?php echo (int) $item['quantity']; ?>
                        </td>

                        <td>
                            <?php echo number_format((float) $item['unit_price'], 0, ',', '.'); ?> VNĐ
                        </td>

                        <td>
                            <?php
                            echo number_format(
                                (float) $item['unit_price'] * (int) $item['quantity'],
                                0,
                                ',',
                                '.'
                            );
                            ?> VNĐ
                        </td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>

    <a
        class="back"
        href="chi_tiet_phieu_sua_chua.php?id=<?php echo (int) $ticketId; ?>"
    >
        ← Quay lại chi tiết phiếu
    </a>

</div>

</body>
</html>
