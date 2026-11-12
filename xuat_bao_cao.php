
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// ==========================================
// 1. KIỂM TRA KHOẢNG THỜI GIAN
// ==========================================

$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');

$startDate = $_GET['start_date'] ?? $firstDayOfMonth;
$endDate = $_GET['end_date'] ?? $today;

$startObject = DateTime::createFromFormat('!Y-m-d', $startDate);
$endObject = DateTime::createFromFormat('!Y-m-d', $endDate);

if (
    !$startObject ||
    $startObject->format('Y-m-d') !== $startDate ||
    !$endObject ||
    $endObject->format('Y-m-d') !== $endDate ||
    $startDate > $endDate
) {
    http_response_code(400);
    exit('Khoảng thời gian không hợp lệ.');
}

$startDateTime = $startDate . ' 00:00:00';
$endDateTime = date(
    'Y-m-d',
    strtotime($endDate . ' +1 day')
) . ' 00:00:00';

// ==========================================
// 2. HÀM HỖ TRỢ XUẤT EXCEL
// ==========================================

// Chuyển dữ liệu thành HTML an toàn, giữ nguyên tiếng Việt UTF-8
function excelEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Xuất một dòng dữ liệu
function excelRow(array $data, string $class = ''): void
{
    echo '<tr class="' . excelEscape($class) . '">';

    foreach ($data as $value) {
        echo '<td>' . excelEscape($value) . '</td>';
    }

    echo '</tr>';
}

// Xuất tiêu đề một mục báo cáo
function excelSection(string $title): void
{
    echo '<tr class="section">';
    echo '<td colspan="6">' . excelEscape($title) . '</td>';
    echo '</tr>';
}

// Xuất tiêu đề cột
function excelHeader(array $columns): void
{
    echo '<tr class="table-header">';

    foreach ($columns as $column) {
        echo '<th>' . excelEscape($column) . '</th>';
    }

    echo '</tr>';
}

// ==========================================
// 3. THIẾT LẬP FILE EXCEL
// ==========================================

$filename = 'Bao_cao_' . $startDate . '_den_' . $endDate . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');

header(
    'Content-Disposition: attachment; filename="' . $filename . '"'
);

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

header('Expires: 0');

// BOM UTF-8
echo "\xEF\xBB\xBF";

// ==========================================
// 4. KHỞI TẠO GIAO DIỆN EXCEL
// ==========================================

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            border: 1px solid #999999;
            padding: 7px;
            vertical-align: middle;
        }

        .report-title td {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            background: #1e293b;
            color: #ffffff;
            padding: 15px;
        }

        .report-info td {
            background: #f1f5f9;
            font-weight: bold;
        }

        .section td {
            background: #1e40af;
            color: #ffffff;
            font-weight: bold;
            font-size: 12pt;
            padding: 10px;
        }

        .table-header th {
            background: #dbeafe;
            font-weight: bold;
            text-align: center;
        }

        .total td {
            background: #f1f5f9;
            font-weight: bold;
        }

        .number {
            mso-number-format: "#,##0";
        }

        .currency {
            mso-number-format: "#,##0";
        }
    </style>
</head>

<body>

<table>

    <!-- TIÊU ĐỀ -->
    <tr class="report-title">
        <td colspan="6">
            BÁO CÁO THỐNG KÊ TRUNG TÂM SỬA CHỮA MÁY TÍNH
        </td>
    </tr>

    <tr class="report-info">
        <td colspan="2">Từ ngày</td>
        <td colspan="4"><?php echo excelEscape($startDate); ?></td>
    </tr>

    <tr class="report-info">
        <td colspan="2">Đến ngày</td>
        <td colspan="4"><?php echo excelEscape($endDate); ?></td>
    </tr>

    <tr><td colspan="6"></td></tr>

<?php

// ==========================================
// 5. DOANH THU VÀ HÓA ĐƠN
// ==========================================

$sql = "
    SELECT
        COUNT(
            CASE
                WHEN i.created_at >= dates.start_date
                 AND i.created_at < dates.end_date
                THEN 1
            END
        ) AS total_invoices,

        COALESCE(SUM(
            CASE
                WHEN i.payment_status = 'paid'
                 AND i.paid_at >= dates.start_date
                 AND i.paid_at < dates.end_date
                THEN 1 ELSE 0
            END
        ), 0) AS paid_invoices,

        COALESCE(SUM(
            CASE
                WHEN i.payment_status = 'unpaid'
                 AND i.created_at >= dates.start_date
                 AND i.created_at < dates.end_date
                THEN 1 ELSE 0
            END
        ), 0) AS unpaid_invoices,

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

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':start_date' => $startDateTime,
    ':end_date' => $endDateTime
]);

$invoice = $stmt->fetch();

excelSection('I. BÁO CÁO DOANH THU');

excelHeader(['Chỉ tiêu', 'Giá trị']);

excelRow(['Doanh thu đã thanh toán (VNĐ)', $invoice['paid_revenue']]);
excelRow(['Tiền chưa thanh toán (VNĐ)', $invoice['unpaid_amount']]);
excelRow(['Tiền công đã thanh toán (VNĐ)', $invoice['labor_revenue']]);
excelRow(['Tiền linh kiện đã thanh toán (VNĐ)', $invoice['parts_revenue']]);
excelRow(['Tổng tiền giảm giá (VNĐ)', $invoice['total_discount']]);
excelRow(['Tổng số hóa đơn', $invoice['total_invoices']]);
excelRow(['Hóa đơn đã thanh toán', $invoice['paid_invoices']]);
excelRow(['Hóa đơn chưa thanh toán', $invoice['unpaid_invoices']]);

echo '<tr><td colspan="6"></td></tr>';

// ==========================================
// 6. DOANH THU THEO NGÀY
// ==========================================

excelSection('II. DOANH THU THEO NGÀY');

excelHeader(['Ngày thanh toán', 'Doanh thu (VNĐ)']);

$stmt = $pdo->prepare("
    SELECT
        DATE(paid_at) AS revenue_date,
        SUM(total_amount) AS revenue
    FROM invoices
    WHERE payment_status = 'paid'
      AND paid_at >= :start_date
      AND paid_at < :end_date
    GROUP BY DATE(paid_at)
    ORDER BY revenue_date ASC
");

$stmt->execute([
    ':start_date' => $startDateTime,
    ':end_date' => $endDateTime
]);

foreach ($stmt->fetchAll() as $row) {
    excelRow([
        $row['revenue_date'],
        $row['revenue']
    ]);
}

echo '<tr><td colspan="6"></td></tr>';

// ==========================================
// 7. TRẠNG THÁI PHIẾU SỬA CHỮA
// ==========================================

excelSection('III. TRẠNG THÁI PHIẾU SỬA CHỮA');

excelHeader(['Trạng thái', 'Số lượng']);

$statusLabels = [
    'received' => 'Đã tiếp nhận',
    'checking' => 'Đang kiểm tra',
    'waiting_parts' => 'Chờ linh kiện',
    'repairing' => 'Đang sửa chữa',
    'completed' => 'Đã sửa xong',
    'delivered' => 'Đã bàn giao',
    'cancelled' => 'Đã hủy'
];

$stmt = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM repair_tickets
    WHERE received_at >= :start_date
      AND received_at < :end_date
    GROUP BY status
    ORDER BY total DESC
");

$stmt->execute([
    ':start_date' => $startDateTime,
    ':end_date' => $endDateTime
]);

$ticketRows = $stmt->fetchAll();

$totalTickets = 0;
$deliveredTickets = 0;

foreach ($ticketRows as $row) {

    $totalTickets += (int) $row['total'];

    if ($row['status'] === 'delivered') {
        $deliveredTickets = (int) $row['total'];
    }

    excelRow([
        $statusLabels[$row['status']] ?? $row['status'],
        $row['total']
    ]);
}

$deliveredPercent = $totalTickets > 0
    ? round($deliveredTickets / $totalTickets * 100, 2)
    : 0;

excelRow(['Tổng số phiếu', $totalTickets], 'total');
excelRow(['Số phiếu đã bàn giao', $deliveredTickets], 'total');
excelRow(['Tỷ lệ bàn giao (%)', $deliveredPercent], 'total');

echo '<tr><td colspan="6"></td></tr>';

// ==========================================
// 8. HIỆU SUẤT KỸ THUẬT VIÊN
// ==========================================

excelSection('IV. HIỆU SUẤT KỸ THUẬT VIÊN');

excelHeader([
    'STT',
    'Nhân viên',
    'Phiếu được giao',
    'Đã bàn giao'
]);

$stmt = $pdo->prepare("
    SELECT
        u.full_name,
        COUNT(rt.id) AS assigned_tickets,
        COALESCE(SUM(
            CASE WHEN rt.status = 'delivered' THEN 1 ELSE 0 END
        ), 0) AS delivered_tickets
    FROM users u
    LEFT JOIN repair_tickets rt
        ON rt.assigned_to = u.id
        AND rt.received_at >= :start_date
        AND rt.received_at < :end_date
    WHERE u.role = 'technician'
    GROUP BY u.id, u.full_name
    ORDER BY assigned_tickets DESC
");

$stmt->execute([
    ':start_date' => $startDateTime,
    ':end_date' => $endDateTime
]);

foreach ($stmt->fetchAll() as $index => $row) {

    excelRow([
        $index + 1,
        $row['full_name'],
        $row['assigned_tickets'],
        $row['delivered_tickets']
    ]);
}

echo '<tr><td colspan="6"></td></tr>';

// ==========================================
// 9. NHẬP KHO VÀ XUẤT KHO
// ==========================================

excelSection('V. BÁO CÁO NHẬP XUẤT KHO');

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(
            CASE
                WHEN movement_type = 'import'
                THEN quantity ELSE 0
            END
        ), 0) AS imported_quantity,

        COALESCE(SUM(
            CASE
                WHEN movement_type = 'export'
                THEN quantity ELSE 0
            END
        ), 0) AS exported_quantity

    FROM stock_movements
    WHERE created_at >= :start_date
      AND created_at < :end_date
");

$stmt->execute([
    ':start_date' => $startDateTime,
    ':end_date' => $endDateTime
]);

$stock = $stmt->fetch();

excelHeader(['Chỉ tiêu', 'Số lượng']);

excelRow(['Tổng linh kiện nhập kho', $stock['imported_quantity']]);
excelRow(['Tổng linh kiện xuất kho', $stock['exported_quantity']]);

echo '<tr><td colspan="6"></td></tr>';

// ==========================================
// 10. LINH KIỆN DƯỚI NGƯỠNG TỒN KHO
// ==========================================

excelSection('VI. LINH KIỆN DƯỚI NGƯỠNG TỒN KHO');

excelHeader([
    'STT',
    'Tên linh kiện',
    'Mã linh kiện',
    'Số lượng hiện tại',
    'Ngưỡng tối thiểu',
    'Tình trạng'
]);

$stmt = $pdo->query("
    SELECT
        id,
        part_name,
        sku,
        quantity,
        min_quantity
    FROM parts
    WHERE quantity <= min_quantity
    ORDER BY quantity ASC, part_name ASC
");

foreach ($stmt->fetchAll() as $index => $part) {

    $status = (int) $part['quantity'] === 0
        ? 'Hết hàng'
        : 'Sắp hết hàng';

    excelRow([
        $index + 1,
        $part['part_name'],
        $part['sku'] ?: 'Chưa có',
        $part['quantity'],
        $part['min_quantity'],
        $status
    ]);
}

?>

</table>

</body>
</html>
<?php
exit;
