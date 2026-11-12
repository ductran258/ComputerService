
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// ==========================================
// 1. LẤY VÀ KIỂM TRA KHOẢNG THỜI GIAN
// ==========================================

$tuNgay = $_GET['tu_ngay'] ?? date('Y-m-01');
$denNgay = $_GET['den_ngay'] ?? date('Y-m-d');

$dateFrom = DateTime::createFromFormat('!Y-m-d', $tuNgay);
$dateTo = DateTime::createFromFormat('!Y-m-d', $denNgay);

if (
    !$dateFrom ||
    $dateFrom->format('Y-m-d') !== $tuNgay ||
    !$dateTo ||
    $dateTo->format('Y-m-d') !== $denNgay ||
    $tuNgay > $denNgay
) {
    http_response_code(400);
    exit('Khoảng thời gian không hợp lệ.');
}

// ==========================================
// 2. LẤY DỮ LIỆU TIÊU THỤ
// ==========================================

$sql = "
    SELECT
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
    ORDER BY
        total_export DESC,
        p.part_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$tuNgay, $denNgay]);
$parts = $stmt->fetchAll();

// ==========================================
// 3. TÍNH TỔNG SỐ LƯỢNG XUẤT
// ==========================================

$totalQuantity = 0;

foreach ($parts as $part) {
    $totalQuantity += (int) $part['total_export'];
}

// ==========================================
// 4. HÀM HỖ TRỢ XUẤT EXCEL
// ==========================================

function excelEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function excelRow(array $values): void
{
    echo '<tr>';

    foreach ($values as $value) {
        echo '<td>' . excelEscape($value) . '</td>';
    }

    echo '</tr>';
}

// ==========================================
// 5. CHUẨN BỊ FILE EXCEL
// ==========================================

$filename = 'Bao_cao_tieu_thu_kho_' . $tuNgay . '_' . $denNgay . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header(
    'Content-Disposition: attachment; filename="' . $filename . '"'
);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// BOM hỗ trợ Excel nhận diện tiếng Việt UTF-8
echo "\xEF\xBB\xBF";

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">

    <title>Báo cáo tiêu thụ kho</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        h2 {
            text-align: center;
            color: #1f2937;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td, th {
            border: 1px solid #999;
            padding: 8px;
        }

        th {
            background-color: #dbeafe;
            font-weight: bold;
            text-align: center;
        }

        .info {
            background-color: #f3f4f6;
        }

        .number {
            text-align: center;
        }

        .total {
            font-weight: bold;
            background-color: #dcfce7;
        }
    </style>
</head>

<body>

<h2>BÁO CÁO TIÊU THỤ LINH KIỆN</h2>

<table>
    <tr class="info">
        <td><strong>Từ ngày</strong></td>
        <td><?php echo excelEscape($tuNgay); ?></td>
    </tr>

    <tr class="info">
        <td><strong>Đến ngày</strong></td>
        <td><?php echo excelEscape($denNgay); ?></td>
    </tr>

    <tr class="total">
        <td><strong>Tổng số linh kiện đã xuất</strong></td>
        <td><?php echo $totalQuantity; ?></td>
    </tr>
</table>

<br>

<table>
    <thead>
        <tr>
            <th>STT</th>
            <th>Tên linh kiện</th>
            <th>Mã SKU</th>
            <th>Danh mục</th>
            <th>Số lượng xuất</th>
            <th>Tỷ lệ tiêu thụ (%)</th>
        </tr>
    </thead>

    <tbody>

        <?php foreach ($parts as $index => $part): ?>

            <?php
                $quantity = (int) $part['total_export'];

                $percentage = $totalQuantity > 0
                    ? ($quantity / $totalQuantity) * 100
                    : 0;
            ?>

            <tr>
                <td class="number">
                    <?php echo $index + 1; ?>
                </td>

                <td>
                    <?php echo excelEscape($part['part_name']); ?>
                </td>

                <td>
                    <?php echo excelEscape($part['sku'] ?: 'Chưa có mã'); ?>
                </td>

                <td>
                    <?php echo excelEscape($part['category'] ?: 'Chưa phân loại'); ?>
                </td>

                <td class="number">
                    <?php echo $quantity; ?>
                </td>

                <td class="number">
                    <?php echo number_format($percentage, 2, '.', ''); ?>%
                </td>
            </tr>

        <?php endforeach; ?>

        <?php if (empty($parts)): ?>
            <tr>
                <td colspan="6">Không có dữ liệu tiêu thụ trong khoảng thời gian này.</td>
            </tr>
        <?php endif; ?>

    </tbody>
</table>

</body>
</html>

<?php
exit;
?>
