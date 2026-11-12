
<?php
require_once __DIR__ . '/includes/auth.php';

requireAdmin();
require_once __DIR__ . '/config/database.php';

// HAM BAO VE DU LIEU
function eChiTietHD($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// HAM DINH DANG TIEN
function tienChiTietHD($value)
{
    return number_format((float) $value, 0, ',', '.') . ' đ';
}

// LAY ID HOA DON
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    exit('ID hóa đơn không hợp lệ.');
}

// LAY THONG TIN HOA DON
$stmt = $pdo->prepare("
    SELECT
        i.*,

        rt.id AS ticket_id,
        rt.ticket_code,
        rt.issue_description,
        rt.diagnosis,
        rt.solution,
        rt.status AS ticket_status,
        rt.received_at,

        c.full_name AS customer_name,
        c.phone AS customer_phone,
        c.email AS customer_email,
        c.address AS customer_address,

        d.device_type,
        d.brand,
        d.model,
        d.serial_number

    FROM invoices i

    INNER JOIN repair_tickets rt
        ON i.ticket_id = rt.id

    INNER JOIN customers c
        ON rt.customer_id = c.id

    INNER JOIN devices d
        ON rt.device_id = d.id

    WHERE i.id = :id
");

$stmt->execute(['id' => $id]);

$invoice = $stmt->fetch();

if (!$invoice) {
    exit('Không tìm thấy hóa đơn.');
}

// LAY DANH SACH LINH KIEN
$stmt = $pdo->prepare("
    SELECT
        tp.quantity,
        tp.unit_price,
        p.part_name,
        p.sku

    FROM ticket_parts tp

    INNER JOIN parts p
        ON tp.part_id = p.id

    WHERE tp.ticket_id = :ticket_id

    ORDER BY tp.id ASC
");

$stmt->execute([
    'ticket_id' => $invoice['ticket_id']
]);

$parts = $stmt->fetchAll();

// TINH TONG TIEN LINH KIEN TU CHI TIET
$tongLinhKien = 0;

foreach ($parts as $part) {
    $tongLinhKien +=
        (int) $part['quantity']
        * (float) $part['unit_price'];
}

// NHAN TRANG THAI
$trangThaiHoaDon = [
    'paid' => 'Đã thanh toán',
    'unpaid' => 'Chưa thanh toán'
];

$phuongThucThanhToan = [
    'cash' => 'Tiền mặt',
    'bank_transfer' => 'Chuyển khoản'
];

$trangThaiPhieu = [
    'received' => 'Đã tiếp nhận',
    'checking' => 'Đang kiểm tra',
    'waiting_parts' => 'Chờ linh kiện',
    'repairing' => 'Đang sửa chữa',
    'completed' => 'Đã sửa xong',
    'delivered' => 'Đã bàn giao',
    'cancelled' => 'Đã hủy'
];

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Chi tiết hóa đơn</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #222;
            padding: 30px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        h1, h2 {
            margin-top: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .info-item {
            line-height: 1.9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #f0f2f5;
        }

        .money {
            text-align: right;
            line-height: 2;
        }

        .grand-total {
            font-size: 23px;
            font-weight: bold;
            color: #2563eb;
        }

        .paid {
            color: #15803d;
            font-weight: bold;
        }

        .unpaid {
            color: #dc2626;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 5px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .button:hover {
            background: #1d4ed8;
        }

        .print-button {
            background: #15803d;
        }

        .print-button:hover {
            background: #166534;
        }

        .empty {
            text-align: center;
            color: #777;
        }

        @media (max-width: 650px) {

            .info-grid {
                grid-template-columns: 1fr;
            }

            body {
                padding: 12px;
            }

        }

        @media print {

            body {
                background: white;
                padding: 0;
            }

            .actions {
                display: none;
            }

            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <h1>Chi tiết hóa đơn</h1>

    <!-- THONG TIN HOA DON -->

    <div class="card">

        <h2>Thông tin hóa đơn</h2>

        <div class="info-grid">

            <div class="info-item">

                <strong>Mã hóa đơn:</strong>

                HD-<?php echo str_pad(
                    (string) $invoice['id'],
                    5,
                    '0',
                    STR_PAD_LEFT
                ); ?>

                <br>

                <strong>Mã phiếu sửa chữa:</strong>

                <?php echo eChiTietHD($invoice['ticket_code']); ?>

                <br>

                <strong>Ngày lập:</strong>

                <?php echo eChiTietHD($invoice['created_at']); ?>

                <br>

                <strong>Trạng thái phiếu:</strong>

                <?php
                echo eChiTietHD(
                    $trangThaiPhieu[$invoice['ticket_status']]
                    ?? $invoice['ticket_status']
                );
                ?>

            </div>

            <div class="info-item">

                <strong>Phương thức thanh toán:</strong>

                <?php
                echo eChiTietHD(
                    $phuongThucThanhToan[$invoice['payment_method']]
                    ?? $invoice['payment_method']
                );
                ?>

                <br>

                <strong>Trạng thái thanh toán:</strong>

                <?php if ($invoice['payment_status'] === 'paid'): ?>

                    <span class="paid">Đã thanh toán</span>

                <?php else: ?>

                    <span class="unpaid">Chưa thanh toán</span>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- THONG TIN KHACH HANG VA THIET BI -->

    <div class="card">

        <div class="info-grid">

            <div>

                <h2>Thông tin khách hàng</h2>

                <div class="info-item">

                    <strong>Họ tên:</strong>

                    <?php echo eChiTietHD($invoice['customer_name']); ?>

                    <br>

                    <strong>Số điện thoại:</strong>

                    <?php echo eChiTietHD($invoice['customer_phone']); ?>

                    <br>

                    <strong>Email:</strong>

                    <?php echo eChiTietHD($invoice['customer_email'] ?: 'Chưa có'); ?>

                    <br>

                    <strong>Địa chỉ:</strong>

                    <?php echo eChiTietHD($invoice['customer_address'] ?: 'Chưa có'); ?>

                </div>

            </div>

            <div>

                <h2>Thông tin thiết bị</h2>

                <div class="info-item">

                    <strong>Loại thiết bị:</strong>

                    <?php echo eChiTietHD($invoice['device_type']); ?>

                    <br>

                    <strong>Thương hiệu:</strong>

                    <?php echo eChiTietHD($invoice['brand']); ?>

                    <br>

                    <strong>Model:</strong>

                    <?php echo eChiTietHD($invoice['model']); ?>

                    <br>

                    <strong>Số serial:</strong>

                    <?php echo eChiTietHD($invoice['serial_number'] ?: 'Chưa có'); ?>

                </div>

            </div>

        </div>

    </div>

    <!-- NOI DUNG SUA CHUA -->

    <div class="card">

        <h2>Thông tin sửa chữa</h2>

        <p>
            <strong>Mô tả lỗi:</strong><br>
            <?php echo nl2br(eChiTietHD($invoice['issue_description'])); ?>
        </p>

        <p>
            <strong>Chẩn đoán:</strong><br>
            <?php echo nl2br(eChiTietHD($invoice['diagnosis'] ?: 'Chưa cập nhật')); ?>
        </p>

        <p>
            <strong>Hướng xử lý:</strong><br>
            <?php echo nl2br(eChiTietHD($invoice['solution'] ?: 'Chưa cập nhật')); ?>
        </p>

    </div>

    <!-- DANH SACH LINH KIEN -->

    <div class="card">

        <h2>Linh kiện sử dụng</h2>

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

                <?php if (count($parts) > 0): ?>

                    <?php foreach ($parts as $index => $part): ?>

                        <tr>

                            <td>
                                <?php echo $index + 1; ?>
                            </td>

                            <td>
                                <?php echo eChiTietHD($part['part_name']); ?>
                            </td>

                            <td>
                                <?php echo eChiTietHD($part['sku'] ?: 'Chưa có'); ?>
                            </td>

                            <td>
                                <?php echo (int) $part['quantity']; ?>
                            </td>

                            <td>
                                <?php echo tienChiTietHD($part['unit_price']); ?>
                            </td>

                            <td>
                                <?php
                                echo tienChiTietHD(
                                    $part['quantity'] * $part['unit_price']
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="6" class="empty">
                            Phiếu sửa chữa chưa sử dụng linh kiện.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- TONG KET HOA DON -->

    <div class="card">

        <h2>Tổng kết thanh toán</h2>

        <div class="money">

            <p>
                Tiền công sửa chữa:
                <strong>
                    <?php echo tienChiTietHD($invoice['labor_cost']); ?>
                </strong>
            </p>

            <p>
                Tiền linh kiện theo hóa đơn:
                <strong>
                    <?php echo tienChiTietHD($invoice['parts_total']); ?>
                </strong>
            </p>

            <p>
                Tổng tiền linh kiện theo chi tiết hiện tại:
                <strong>
                    <?php echo tienChiTietHD($tongLinhKien); ?>
                </strong>
            </p>

            <p>
                Giảm giá:
                <strong>
                    <?php echo tienChiTietHD($invoice['discount']); ?>
                </strong>
            </p>

            <hr>

            <p class="grand-total">
                Tổng thanh toán:
                <?php echo tienChiTietHD($invoice['total_amount']); ?>
            </p>

        </div>

    </div>

    <!-- CAC NUT CHUC NANG -->

    <div class="actions">

        <a href="hoa_don.php" class="button">
            ← Quay lại danh sách hóa đơn
        </a>

        <button
            type="button"
            class="button print-button"
            onclick="window.print()"
        >
            In hóa đơn
        </button>

    </div>

</div>

</body>

</html>
