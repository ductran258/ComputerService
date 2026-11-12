<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// Chỉ tiếp nhận dữ liệu POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Yêu cầu không hợp lệ.');
}

// Kiểm tra mã bảo vệ biểu mẫu
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    exit('Phiên làm việc không hợp lệ. Vui lòng quay lại và thử lại.');
}

// Lấy danh sách ID hóa đơn
$invoiceIds = $_POST['invoice_ids'] ?? [];

if (!is_array($invoiceIds) || count($invoiceIds) === 0) {
    exit('Bạn chưa chọn hóa đơn nào để in.');
}

// Kiểm tra và lọc ID
$validIds = [];

foreach ($invoiceIds as $id) {

    $id = filter_var($id, FILTER_VALIDATE_INT);

    if ($id !== false && $id > 0) {
        $validIds[] = $id;
    }
}

// Loại bỏ ID trùng nhau
$validIds = array_values(array_unique($validIds));

if (count($validIds) === 0) {
    exit('Danh sách hóa đơn không hợp lệ.');
}

// Tạo dấu ? tương ứng với số lượng ID
$placeholders = implode(
    ',',
    array_fill(0, count($validIds), '?')
);

// Lấy thông tin hóa đơn
$sql = "
    SELECT
        i.id,
        i.ticket_id,
        i.labor_cost,
        i.parts_total,
        i.discount,
        i.total_amount,
        i.payment_method,
        i.payment_status,
        i.created_at,

        rt.ticket_code,
        rt.issue_description,
        rt.diagnosis,
        rt.solution,

        c.full_name AS customer_name,
        c.phone AS customer_phone,
        c.email AS customer_email,
        c.address AS customer_address

    FROM invoices i

    INNER JOIN repair_tickets rt
        ON i.ticket_id = rt.id

    INNER JOIN customers c
        ON rt.customer_id = c.id

    WHERE i.id IN ($placeholders)

    ORDER BY i.created_at DESC, i.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($validIds);

$invoices = $stmt->fetchAll();

if (count($invoices) === 0) {
    exit('Không tìm thấy hóa đơn cần in.');
}

// Lấy danh sách linh kiện của các phiếu
$ticketIds = array_values(
    array_unique(
        array_column($invoices, 'ticket_id')
    )
);

$partsByTicket = [];

if (count($ticketIds) > 0) {

    $partPlaceholders = implode(
        ',',
        array_fill(0, count($ticketIds), '?')
    );

    $stmtParts = $pdo->prepare("
        SELECT
            tp.ticket_id,
            tp.quantity,
            tp.unit_price,
            p.part_name

        FROM ticket_parts tp

        INNER JOIN parts p
            ON tp.part_id = p.id

        WHERE tp.ticket_id IN ($partPlaceholders)

        ORDER BY tp.id ASC
    ");

    $stmtParts->execute($ticketIds);

    $parts = $stmtParts->fetchAll();

    foreach ($parts as $part) {

        $ticketId = (int) $part['ticket_id'];

        if (!isset($partsByTicket[$ticketId])) {
            $partsByTicket[$ticketId] = [];
        }

        $partsByTicket[$ticketId][] = $part;
    }
}

// Hàm bảo vệ dữ liệu
function eInHoaDon($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Hàm định dạng tiền
function tienInHoaDon($value)
{
    return number_format(
        (float) $value,
        0,
        ',',
        '.'
    ) . ' đ';
}

// Nhãn thanh toán
$paymentMethods = [
    'cash' => 'Tiền mặt',
    'bank_transfer' => 'Chuyển khoản'
];

$paymentStatuses = [
    'paid' => 'Đã thanh toán',
    'unpaid' => 'Chưa thanh toán'
];

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>In hóa đơn</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #e5e7eb;
            font-family: Arial, sans-serif;
            color: #222;
        }

        .toolbar {
            max-width: 850px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .toolbar button,
        .toolbar a {
            display: inline-block;
            padding: 11px 18px;
            border: none;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .print-button {
            background: #15803d;
        }

        .back-button {
            background: #2563eb;
        }

        .invoice-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 25px;
            padding: 20mm;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
        }

        .invoice-header {
            text-align: center;
            border-bottom: 2px solid #222;
            padding-bottom: 18px;
            margin-bottom: 25px;
        }

        .invoice-header h1 {
            margin: 0 0 10px;
            font-size: 26px;
            text-transform: uppercase;
        }

        .invoice-header p {
            margin: 5px 0;
            color: #555;
        }

        .invoice-code {
            text-align: right;
            margin-bottom: 20px;
        }

        .invoice-code strong {
            font-size: 18px;
        }

        .section-title {
            font-size: 16px;
            margin: 22px 0 12px;
            padding-bottom: 7px;
            border-bottom: 1px solid #ddd;
        }

        .customer-info p {
            margin: 8px 0;
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th,
        td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #f1f5f9;
        }

        .text-right {
            text-align: right;
        }

        .total-table {
            width: 65%;
            margin-left: auto;
        }

        .total-table td {
            border: none;
            padding: 8px;
        }

        .grand-total {
            font-size: 17px;
            font-weight: bold;
            border-top: 2px solid #222 !important;
        }

        .payment-info {
            margin-top: 20px;
            line-height: 1.7;
        }

        .footer {
            margin-top: 55px;
            display: flex;
            justify-content: space-between;
            text-align: center;
        }

        .signature {
            width: 45%;
        }

        .signature p {
            margin: 8px 0;
        }

        .signature-space {
            height: 80px;
        }

        .thanks {
            text-align: center;
            margin-top: 30px;
            font-style: italic;
        }

        @page {
            size: A4;
            margin: 0;
        }

        @media print {

            body {
                padding: 0;
                background: white;
            }

            .toolbar {
                display: none;
            }

            .invoice-page {
                width: 210mm;
                min-height: 297mm;
                margin: 0;
                padding: 20mm;
                box-shadow: none;
                break-after: page;
                page-break-after: always;
            }

            .invoice-page:last-child {
                break-after: auto;
                page-break-after: auto;
            }

        }

    </style>

</head>

<body>

    <div class="toolbar">

        <div>
            Đã chọn
            <strong><?php echo count($invoices); ?></strong>
            hóa đơn để in
        </div>

        <div>

            <a
                href="hoa_don.php"
                class="back-button"
                target="mainContent"
            >
                ← Quay lại
            </a>

            <button
                type="button"
                class="print-button"
                onclick="window.print()"
            >
                In hóa đơn
            </button>

        </div>

    </div>

    <?php foreach ($invoices as $invoice): ?>

        <?php
        $ticketId = (int) $invoice['ticket_id'];
        $invoiceId = (int) $invoice['id'];
        $invoiceCode = 'HD-' . str_pad(
            (string) $invoiceId,
            5,
            '0',
            STR_PAD_LEFT
        );

        $invoiceParts = $partsByTicket[$ticketId] ?? [];
        ?>

        <div class="invoice-page">

            <div class="invoice-header">

                <h1>HÓA ĐƠN SỬA CHỮA</h1>

                <p>COMPUTER SERVICE</p>

                <p>Dịch vụ sửa chữa và bảo trì máy tính</p>

            </div>

            <div class="invoice-code">

                <p>
                    Mã hóa đơn:
                    <strong><?php echo eInHoaDon($invoiceCode); ?></strong>
                </p>

                <p>
                    Ngày lập:
                    <?php echo eInHoaDon($invoice['created_at']); ?>
                </p>

            </div>

            <h3 class="section-title">
                I. THÔNG TIN KHÁCH HÀNG
            </h3>

            <div class="customer-info">

                <p>
                    <strong>Họ tên:</strong>
                    <?php echo eInHoaDon($invoice['customer_name']); ?>
                </p>

                <p>
                    <strong>Số điện thoại:</strong>
                    <?php echo eInHoaDon($invoice['customer_phone']); ?>
                </p>

                <p>
                    <strong>Email:</strong>
                    <?php echo eInHoaDon($invoice['customer_email'] ?: 'Không có'); ?>
                </p>

                <p>
                    <strong>Địa chỉ:</strong>
                    <?php echo eInHoaDon($invoice['customer_address'] ?: 'Không có'); ?>
                </p>

                <p>
                    <strong>Mã phiếu sửa chữa:</strong>
                    <?php echo eInHoaDon($invoice['ticket_code']); ?>
                </p>

            </div>

            <h3 class="section-title">
                II. NỘI DUNG SỬA CHỮA
            </h3>

            <div class="customer-info">

                <p>
                    <strong>Mô tả lỗi:</strong>
                    <?php echo nl2br(eInHoaDon($invoice['issue_description'])); ?>
                </p>

                <p>
                    <strong>Chẩn đoán:</strong>
                    <?php echo nl2br(eInHoaDon($invoice['diagnosis'] ?: 'Không có')); ?>
                </p>

                <p>
                    <strong>Phương án sửa chữa:</strong>
                    <?php echo nl2br(eInHoaDon($invoice['solution'] ?: 'Không có')); ?>
                </p>

            </div>

            <h3 class="section-title">
                III. CHI PHÍ SỬA CHỮA
            </h3>

            <table>

                <thead>

                    <tr>
                        <th>STT</th>
                        <th>Nội dung</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Thành tiền</th>
                    </tr>

                </thead>

                <tbody>

                    <tr>
                        <td>1</td>
                        <td>Tiền công sửa chữa</td>
                        <td>1</td>
                        <td><?php echo tienInHoaDon($invoice['labor_cost']); ?></td>
                        <td><?php echo tienInHoaDon($invoice['labor_cost']); ?></td>
                    </tr>

                    <?php if (count($invoiceParts) > 0): ?>

                        <?php foreach ($invoiceParts as $index => $part): ?>

                            <tr>

                                <td><?php echo $index + 2; ?></td>

                                <td>
                                    <?php echo eInHoaDon($part['part_name']); ?>
                                </td>

                                <td>
                                    <?php echo (int) $part['quantity']; ?>
                                </td>

                                <td>
                                    <?php echo tienInHoaDon($part['unit_price']); ?>
                                </td>

                                <td>
                                    <?php
                                    echo tienInHoaDon(
                                        (float) $part['quantity'] *
                                        (float) $part['unit_price']
                                    );
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td>2</td>
                            <td>Chi phí linh kiện</td>
                            <td>1</td>
                            <td><?php echo tienInHoaDon($invoice['parts_total']); ?></td>
                            <td><?php echo tienInHoaDon($invoice['parts_total']); ?></td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

            <table class="total-table">

                <tr>
                    <td>Tiền công:</td>
                    <td class="text-right">
                        <?php echo tienInHoaDon($invoice['labor_cost']); ?>
                    </td>
                </tr>

                <tr>
                    <td>Tiền linh kiện:</td>
                    <td class="text-right">
                        <?php echo tienInHoaDon($invoice['parts_total']); ?>
                    </td>
                </tr>

                <tr>
                    <td>Giảm giá:</td>
                    <td class="text-right">
                        <?php echo tienInHoaDon($invoice['discount']); ?>
                    </td>
                </tr>

                <tr>
                    <td class="grand-total">TỔNG THANH TOÁN:</td>
                    <td class="text-right grand-total">
                        <?php echo tienInHoaDon($invoice['total_amount']); ?>
                    </td>
                </tr>

            </table>

            <div class="payment-info">

                <p>
                    <strong>Phương thức thanh toán:</strong>
                    <?php
                    echo eInHoaDon(
                        $paymentMethods[$invoice['payment_method']]
                        ?? $invoice['payment_method']
                    );
                    ?>
                </p>

                <p>
                    <strong>Trạng thái thanh toán:</strong>
                    <?php
                    echo eInHoaDon(
                        $paymentStatuses[$invoice['payment_status']]
                        ?? $invoice['payment_status']
                    );
                    ?>
                </p>

            </div>

            <div class="footer">

                <div class="signature">

                    <strong>Khách hàng</strong>

                    <p><em>(Ký và ghi rõ họ tên)</em></p>

                    <div class="signature-space"></div>

                </div>

                <div class="signature">

                    <strong>Nhân viên</strong>

                    <p><em>(Ký và ghi rõ họ tên)</em></p>

                    <div class="signature-space"></div>

                </div>

            </div>

            <p class="thanks">
                Cảm ơn quý khách đã sử dụng dịch vụ của Computer Service!
            </p>

        </div>

    <?php endforeach; ?>

</body>

</html>