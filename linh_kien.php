
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// ==========================================
// 1. KHỞI TẠO CSRF TOKEN
// ==========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// ==========================================
// 2. XỬ LÝ CẬP NHẬT NGƯỠNG CẢNH BÁO
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_threshold'])) {

    $token = $_POST['csrf_token'] ?? '';
    $partId = filter_input(INPUT_POST, 'part_id', FILTER_VALIDATE_INT);
    $minQuantity = filter_input(INPUT_POST, 'min_quantity', FILTER_VALIDATE_INT);

    // Kiểm tra CSRF
    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        header('Location: linh_kien.php?error=invalid_token');
        exit;
    }

    // Kiểm tra dữ liệu đầu vào
    if (
        !$partId ||
        $partId < 1 ||
        $minQuantity === false ||
        $minQuantity === null ||
        $minQuantity < 0
    ) {
        header('Location: linh_kien.php?error=invalid_threshold');
        exit;
    }

    try {

        $pdo->beginTransaction();

        // Lấy thông tin linh kiện
        $stmtPart = $pdo->prepare("
            SELECT id, part_name, min_quantity
            FROM parts
            WHERE id = :id
            FOR UPDATE
        ");

        $stmtPart->execute([
            'id' => $partId
        ]);

        $part = $stmtPart->fetch(PDO::FETCH_ASSOC);

        if (!$part) {
            $pdo->rollBack();

            header('Location: linh_kien.php?error=part_not_found');
            exit;
        }

        // Lấy ngưỡng cũ và ngưỡng mới
        $oldMinQuantity = (int) $part['min_quantity'];
        $newMinQuantity = (int) $minQuantity;

        // Không ghi lịch sử nếu ngưỡng không thay đổi
        if ($oldMinQuantity === $newMinQuantity) {
            $pdo->rollBack();

            header('Location: linh_kien.php?threshold_unchanged=1');
            exit;
        }

        // Lấy thông tin nhân viên đang đăng nhập
        $changedBy = (int) ($_SESSION['user_id'] ?? 0);

        $stmtUser = $pdo->prepare("
            SELECT full_name
            FROM users
            WHERE id = :id
        ");

        $stmtUser->execute([
            'id' => $changedBy
        ]);

        $changedByName = $stmtUser->fetchColumn();

        if (!$changedByName) {
            $changedByName = 'Không xác định';
        }

        // Cập nhật ngưỡng tồn kho mới
        $stmtUpdate = $pdo->prepare("
            UPDATE parts
            SET min_quantity = :min_quantity
            WHERE id = :id
        ");

        $stmtUpdate->execute([
            'min_quantity' => $newMinQuantity,
            'id' => $partId
        ]);

        // Ghi lịch sử điều chỉnh ngưỡng
        $stmtHistory = $pdo->prepare("
            INSERT INTO part_threshold_history (
                part_id,
                part_name,
                old_min_quantity,
                new_min_quantity,
                changed_by,
                changed_by_name
            )
            VALUES (
                :part_id,
                :part_name,
                :old_min_quantity,
                :new_min_quantity,
                :changed_by,
                :changed_by_name
            )
        ");

        $stmtHistory->execute([
            'part_id' => $partId,
            'part_name' => $part['part_name'],
            'old_min_quantity' => $oldMinQuantity,
            'new_min_quantity' => $newMinQuantity,
            'changed_by' => $changedBy > 0 ? $changedBy : null,
            'changed_by_name' => $changedByName
        ]);

        // Hoàn tất giao dịch
        $pdo->commit();

        header('Location: linh_kien.php?threshold_updated=1');
        exit;

    } catch (Throwable $e) {

        // Nếu xảy ra lỗi thì hoàn tác toàn bộ
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header('Location: linh_kien.php?error=threshold_failed');
        exit;
    }
}

// ==========================================
// 3. LẤY DANH SÁCH LINH KIỆN
// ==========================================

$stmt = $pdo->query("
    SELECT *
    FROM parts
    ORDER BY id DESC
");

$parts = $stmt->fetchAll();

// ==========================================
// 4. TÍNH TOÁN TỒN KHO VÀ CẢNH BÁO
// ==========================================

$tongSoLinhKien = count($parts);
$tongTonKho = 0;

$linhKienHetHang = [];
$linhKienSapHet = [];

foreach ($parts as $part) {

    $soLuong = (int) $part['quantity'];
    $nguongCanhBao = (int) $part['min_quantity'];

    $tongTonKho += $soLuong;

    if ($soLuong <= 0) {

        $linhKienHetHang[] = $part;

    } elseif ($soLuong <= $nguongCanhBao) {

        $linhKienSapHet[] = $part;

    }
}

$soLinhKienSapHet = count($linhKienSapHet);

// ==========================================
// 5. ĐỊNH DẠNG TIỀN TỆ
// ==========================================

function dinhDangTien($soTien)
{
    return number_format((float) $soTien, 0, ',', '.') . ' đ';
}

// ==========================================
// 6. DANH MỤC LINH KIỆN
// ==========================================

$categoryLabels = [
    'storage' => 'Ổ cứng',
    'ram' => 'RAM',
    'cpu' => 'CPU',
    'gpu' => 'Card đồ họa',
    'accessory' => 'Phụ kiện',
    'other' => 'Khác'
];

// ==========================================
// 7. HÀM BẢO VỆ DỮ LIỆU
// ==========================================

function e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ==========================================
// 8. THÔNG BÁO
// ==========================================

$daCapNhat = isset($_GET['updated']) && $_GET['updated'] === '1';
$daXoa = isset($_GET['deleted']) && $_GET['deleted'] === '1';
$daXuatKho = isset($_GET['exported']) && $_GET['exported'] === '1';

$daCapNhatNguong = isset($_GET['threshold_updated'])
    && $_GET['threshold_updated'] === '1';

$nguongKhongThayDoi = isset($_GET['threshold_unchanged'])
    && $_GET['threshold_unchanged'] === '1';

$loiXoa = $_GET['error'] ?? '';

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quản lý kho linh kiện</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1e293b;
            padding: 26px;
        }

        .container {
            max-width: 1500px;
            margin: auto;
        }

        /* HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .page-title h1 {
            font-size: 26px;
            color: #172554;
            margin-bottom: 8px;
        }

        .page-title p {
            font-size: 14px;
            color: #64748b;
        }

        .header-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eaf2ff;
            color: #2563eb;
            border-radius: 12px;
            font-size: 21px;
        }

        /* THONG BAO */

        .alert-message {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 17px;
            margin-bottom: 15px;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.5;
        }

        .success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* STATISTICS */

        .statistics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin: 24px 0;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 17px;
            background: #ffffff;
            border: 1px solid #e5eaf2;
            border-radius: 12px;
            padding: 21px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.03);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-icon.blue {
            background: #eaf2ff;
            color: #2563eb;
        }

        .stat-icon.green {
            background: #dcfce7;
            color: #15803d;
        }

        .stat-icon.orange {
            background: #ffedd5;
            color: #ea580c;
        }

        .stat-info p {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-info strong {
            display: block;
            font-size: 26px;
            color: #172554;
        }

        /* STOCK ALERT */

        .stock-alert {
            background: #ffffff;
            border: 1px solid #e5eaf2;
            border-radius: 12px;
            padding: 23px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.03);
        }

        .stock-alert-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 17px;
            border-bottom: 1px solid #edf0f5;
        }

        .stock-alert-header i {
            color: #f59e0b;
            font-size: 19px;
        }

        .stock-alert-header h2 {
            font-size: 18px;
            color: #1e293b;
        }

        .alert-group {
            margin-top: 22px;
        }

        .alert-title {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 15px;
            margin-bottom: 14px;
        }

        .empty-title {
            color: #dc2626;
        }

        .low-title {
            color: #d97706;
        }

        .alert-count {
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 12px;
            background: #f1f5f9;
            color: #475569;
        }

        .alert-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 13px;
        }

        .alert-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 16px;
            border-radius: 9px;
            min-width: 0;
        }

        .empty-item {
            background: #fff7f7;
            border: 1px solid #fecaca;
            border-left: 4px solid #dc2626;
        }

        .low-item {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #d97706;
        }

        .alert-info {
            min-width: 0;
        }

        .alert-info strong {
            display: block;
            font-size: 14px;
            color: #1e293b;
            margin-bottom: 7px;
            overflow-wrap: anywhere;
        }

        .alert-info small {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-top: 5px;
        }

        .alert-quantity {
            font-size: 13px;
            font-weight: bold;
            margin-top: 8px;
        }

        .empty-quantity {
            color: #dc2626;
        }

        .low-quantity {
            color: #b45309;
        }

        .empty-alert {
            padding: 14px 16px;
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            font-size: 13px;
        }

        .btn-alert-import {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 9px 12px;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 7px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
            transition: 0.2s;
        }

        .btn-alert-import:hover {
            background: #1d4ed8;
        }

        /* TABLE CARD */

        .table-container {
            background: #ffffff;
            border: 1px solid #e5eaf2;
            border-radius: 12px;
            padding: 23px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.03);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .table-header h2 {
            font-size: 18px;
            color: #1e293b;
        }

        .table-header p {
            color: #64748b;
            font-size: 13px;
            margin-top: 6px;
        }

        /* ACTION BUTTONS */

        .inventory-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-bottom: 20px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 8px;
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            transition: 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            filter: brightness(0.95);
        }

        .btn-add {
            background: #16a34a;
        }

        .btn-import {
            background: #2563eb;
        }

        .btn-export {
            background: #dc2626;
        }

        .btn-history {
            background: #64748b;
        }

        .btn-threshold-history {
            background: #7c3aed;
        }

        /* TABLE */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #edf0f5;
            border-radius: 9px;
        }

        table {
            width: 100%;
            min-width: 1350px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px 13px;
            border-bottom: 1px solid #edf0f5;
            text-align: left;
            font-size: 13px;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        tbody tr {
            transition: background 0.15s;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .part-id {
            color: #64748b;
            font-weight: bold;
        }

        .part-name {
            color: #1e293b;
            font-weight: bold;
            min-width: 150px;
        }

        .sku {
            color: #64748b;
            font-family: monospace;
        }

        .quantity {
            font-weight: bold;
            font-size: 14px;
        }

        /* STATUS */

        .stock-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-empty {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-low {
            background: #fef3c7;
            color: #b45309;
        }

        .status-normal {
            background: #dcfce7;
            color: #15803d;
        }

        /* THRESHOLD FORM */

        .threshold-form {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .threshold-form input {
            width: 65px;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            outline: none;
            font-size: 13px;
        }

        .threshold-form input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        .btn-save-threshold {
            padding: 8px 10px;
            background: #0f766e;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-save-threshold:hover {
            background: #115e59;
        }

        /* ROW ACTIONS */

        .row-actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .btn-edit,
        .btn-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 11px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
        }

        .btn-edit {
            background: #eff6ff;
            color: #2563eb;
        }

        .btn-edit:hover {
            background: #dbeafe;
        }

        .btn-delete {
            background: #fef2f2;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #fee2e2;
        }

        .empty-row {
            text-align: center;
            color: #94a3b8;
            padding: 35px !important;
        }

        .empty-row i {
            display: block;
            font-size: 30px;
            margin-bottom: 12px;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {

            body {
                padding: 18px;
            }

            .statistics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .alert-list {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 600px) {

            body {
                padding: 12px;
            }

            .page-title h1 {
                font-size: 22px;
            }

            .statistics {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-card {
                padding: 17px;
            }

            .stock-alert,
            .table-container {
                padding: 16px;
            }

            .inventory-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .action-btn {
                font-size: 12px;
                padding: 10px 8px;
            }

            .alert-item {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .btn-alert-import {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- ========================================== -->
    <!-- HEADER -->
    <!-- ========================================== -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                <i class="fa-solid fa-boxes-stacked"></i>
                Quản lý kho linh kiện
            </h1>

            <p>
                Theo dõi số lượng, tình trạng tồn kho và quản lý linh kiện trong hệ thống.
            </p>

        </div>

        <div class="header-icon">
            <i class="fa-solid fa-warehouse"></i>
        </div>

    </div>

    <!-- ========================================== -->
    <!-- THÔNG BÁO -->
    <!-- ========================================== -->

    <?php if ($daCapNhat): ?>

        <div class="alert-message success">
            <i class="fa-solid fa-circle-check"></i>
            Cập nhật thông tin linh kiện thành công!
        </div>

    <?php endif; ?>

    <?php if ($daXoa): ?>

        <div class="alert-message success">
            <i class="fa-solid fa-circle-check"></i>
            Xóa linh kiện thành công!
        </div>

    <?php endif; ?>

    <?php if ($daXuatKho): ?>

        <div class="alert-message success">
            <i class="fa-solid fa-circle-check"></i>
            Xuất kho linh kiện thành công! Số lượng tồn kho đã được cập nhật.
        </div>

    <?php endif; ?>

    <?php if ($daCapNhatNguong): ?>

        <div class="alert-message success">
            <i class="fa-solid fa-circle-check"></i>
            Cập nhật ngưỡng cảnh báo tồn kho thành công! Lịch sử điều chỉnh đã được lưu.
        </div>

    <?php endif; ?>

    <?php if ($nguongKhongThayDoi): ?>

        <div class="alert-message success">
            <i class="fa-solid fa-circle-info"></i>
            Ngưỡng cảnh báo không thay đổi. Không phát sinh bản ghi lịch sử mới.
        </div>

    <?php endif; ?>

    <?php if ($loiXoa === 'in_use'): ?>

        <div class="alert-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Không thể xóa linh kiện vì linh kiện đã được sử dụng trong phiếu sửa chữa.
        </div>

    <?php elseif ($loiXoa === 'not_found'): ?>

        <div class="alert-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Không tìm thấy linh kiện cần xóa.
        </div>

    <?php elseif ($loiXoa === 'invalid_id'): ?>

        <div class="alert-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            ID linh kiện không hợp lệ.
        </div>

    <?php elseif ($loiXoa === 'delete_failed'): ?>

        <div class="alert-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Có lỗi xảy ra khi xóa linh kiện. Vui lòng thử lại.
        </div>

    <?php elseif ($loiXoa === 'invalid_threshold'): ?>

        <div class="alert-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Ngưỡng cảnh báo không hợp lệ. Vui lòng nhập số nguyên từ 0 trở lên.
        </div>

    <?php elseif ($loiXoa === 'invalid_token'): ?>

        <div class="alert-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.
        </div>

    <?php elseif ($loiXoa === 'part_not_found'): ?>

        <div class="alert-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Không tìm thấy linh kiện cần cập nhật.
        </div>

    <?php elseif ($loiXoa === 'threshold_failed'): ?>

        <div class="alert-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Không thể cập nhật ngưỡng hoặc lưu lịch sử điều chỉnh. Vui lòng thử lại.
        </div>

    <?php endif; ?>

    <!-- ========================================== -->
    <!-- THỐNG KÊ KHO -->
    <!-- ========================================== -->

    <div class="statistics">

        <div class="stat-card">

            <div class="stat-icon blue">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>

            <div class="stat-info">
                <p>Tổng số loại linh kiện</p>
                <strong><?php echo $tongSoLinhKien; ?></strong>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon green">
                <i class="fa-solid fa-cubes"></i>
            </div>

            <div class="stat-info">
                <p>Tổng số lượng tồn kho</p>
                <strong><?php echo $tongTonKho; ?></strong>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon orange">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="stat-info">
                <p>Linh kiện sắp hết hàng</p>
                <strong><?php echo $soLinhKienSapHet; ?></strong>
            </div>

        </div>

    </div>

    <!-- ========================================== -->
    <!-- CẢNH BÁO TỒN KHO -->
    <!-- ========================================== -->

    <div class="stock-alert">

        <div class="stock-alert-header">

            <i class="fa-solid fa-bell"></i>

            <h2>Cảnh báo tồn kho</h2>

        </div>

        <!-- LINH KIỆN HẾT HÀNG -->

        <div class="alert-group">

            <h3 class="alert-title empty-title">

                <i class="fa-solid fa-circle-xmark"></i>

                Linh kiện hết hàng

                <span class="alert-count">
                    <?php echo count($linhKienHetHang); ?>
                </span>

            </h3>

            <?php if (count($linhKienHetHang) > 0): ?>

                <div class="alert-list">

                    <?php foreach ($linhKienHetHang as $part): ?>

                        <div class="alert-item empty-item">

                            <div class="alert-info">

                                <strong><?php echo e($part['part_name']); ?></strong>

                                <small>
                                    SKU: <?php echo e($part['sku'] ?: 'Chưa có'); ?>
                                </small>

                                <div class="alert-quantity empty-quantity">
                                    Số lượng: 0
                                </div>

                                <small>
                                    Ngưỡng cảnh báo: <?php echo (int) $part['min_quantity']; ?>
                                </small>

                            </div>

                            <a
                                href="nhap_kho.php?part_id=<?php echo (int) $part['id']; ?>"
                                class="btn-alert-import"
                                target="mainContent"
                            >
                                <i class="fa-solid fa-plus"></i>
                                Nhập kho
                            </a>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-alert">
                    <i class="fa-solid fa-circle-check"></i>
                    Không có linh kiện nào hết hàng.
                </div>

            <?php endif; ?>

        </div>

        <!-- LINH KIỆN SẮP HẾT HÀNG -->

        <div class="alert-group">

            <h3 class="alert-title low-title">

                <i class="fa-solid fa-triangle-exclamation"></i>

                Linh kiện sắp hết hàng

                <span class="alert-count">
                    <?php echo count($linhKienSapHet); ?>
                </span>

            </h3>

            <?php if (count($linhKienSapHet) > 0): ?>

                <div class="alert-list">

                    <?php foreach ($linhKienSapHet as $part): ?>

                        <div class="alert-item low-item">

                            <div class="alert-info">

                                <strong><?php echo e($part['part_name']); ?></strong>

                                <small>
                                    SKU: <?php echo e($part['sku'] ?: 'Chưa có'); ?>
                                </small>

                                <div class="alert-quantity low-quantity">
                                    Còn lại: <?php echo (int) $part['quantity']; ?>
                                </div>

                                <small>
                                    Ngưỡng cảnh báo: <?php echo (int) $part['min_quantity']; ?>
                                </small>

                            </div>

                            <a
                                href="nhap_kho.php?part_id=<?php echo (int) $part['id']; ?>"
                                class="btn-alert-import"
                                target="mainContent"
                            >
                                <i class="fa-solid fa-plus"></i>
                                Nhập kho
                            </a>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-alert">
                    <i class="fa-solid fa-circle-check"></i>
                    Không có linh kiện nào sắp hết hàng.
                </div>

            <?php endif; ?>

        </div>

    </div>

    <!-- ========================================== -->
    <!-- DANH SÁCH LINH KIỆN -->
    <!-- ========================================== -->

    <div class="table-container">

        <div class="table-header">

            <div>
                <h2>
                    <i class="fa-solid fa-list"></i>
                    Danh sách linh kiện
                </h2>

                <p>
                    Quản lý thông tin và điều chỉnh tồn kho linh kiện.
                </p>
            </div>

        </div>

        <!-- CÁC NÚT CHỨC NĂNG -->

        <div class="inventory-actions">

            <a
                href="them_linh_kien.php"
                class="action-btn btn-add"
                target="mainContent"
            >
                <i class="fa-solid fa-plus"></i>
                Thêm linh kiện
            </a>

            <a
                href="nhap_kho.php"
                class="action-btn btn-import"
                target="mainContent"
            >
                <i class="fa-solid fa-arrow-down"></i>
                Nhập kho
            </a>

            <a
                href="xuat_kho.php"
                class="action-btn btn-export"
                target="mainContent"
            >
                <i class="fa-solid fa-arrow-up"></i>
                Xuất kho
            </a>

            <a
                href="lich_su_kho.php"
                class="action-btn btn-history"
                target="mainContent"
            >
                <i class="fa-solid fa-clock-rotate-left"></i>
                Lịch sử kho
            </a>

            <a
                href="lich_su_nguong_kho.php"
                class="action-btn btn-threshold-history"
                target="mainContent"
            >
                <i class="fa-solid fa-sliders"></i>
                Lịch sử điều chỉnh ngưỡng
            </a>

        </div>

        <!-- BẢNG DỮ LIỆU -->

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Tên linh kiện</th>
                        <th>Mã SKU</th>
                        <th>Danh mục</th>
                        <th>Số lượng</th>
                        <th>Ngưỡng cảnh báo</th>
                        <th>Giá nhập</th>
                        <th>Giá bán</th>
                        <th>Tình trạng</th>
                        <th>Thao tác</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (count($parts) > 0): ?>

                        <?php foreach ($parts as $part): ?>

                            <tr>

                                <td class="part-id">
                                    #<?php echo e($part['id']); ?>
                                </td>

                                <td class="part-name">
                                    <?php echo e($part['part_name']); ?>
                                </td>

                                <td class="sku">
                                    <?php echo e($part['sku'] ?: 'Chưa có'); ?>
                                </td>

                                <td>
                                    <?php
                                    echo e(
                                        $categoryLabels[$part['category']]
                                        ?? $part['category']
                                    );
                                    ?>
                                </td>

                                <td class="quantity">
                                    <?php echo e($part['quantity']); ?>
                                </td>

                                <!-- NGƯỠNG CẢNH BÁO -->

                                <td>

                                    <form
                                        action="linh_kien.php"
                                        method="POST"
                                        class="threshold-form"
                                        target="mainContent"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo e($csrfToken); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="part_id"
                                            value="<?php echo (int) $part['id']; ?>"
                                        >

                                        <input
                                            type="number"
                                            name="min_quantity"
                                            min="0"
                                            step="1"
                                            required
                                            value="<?php echo (int) $part['min_quantity']; ?>"
                                            title="Nhập ngưỡng cảnh báo"
                                        >

                                        <button
                                            type="submit"
                                            name="update_threshold"
                                            value="1"
                                            class="btn-save-threshold"
                                            title="Lưu ngưỡng cảnh báo"
                                        >
                                            <i class="fa-solid fa-check"></i>
                                        </button>

                                    </form>

                                </td>

                                <td>
                                    <?php echo dinhDangTien($part['import_price']); ?>
                                </td>

                                <td>
                                    <?php echo dinhDangTien($part['selling_price']); ?>
                                </td>

                                <td>

                                    <?php if ((int) $part['quantity'] <= 0): ?>

                                        <span class="stock-status status-empty">
                                            <i class="fa-solid fa-circle-xmark"></i>
                                            Hết hàng
                                        </span>

                                    <?php elseif ((int) $part['quantity'] <= (int) $part['min_quantity']): ?>

                                        <span class="stock-status status-low">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                            Sắp hết hàng
                                        </span>

                                    <?php else: ?>

                                        <span class="stock-status status-normal">
                                            <i class="fa-solid fa-circle-check"></i>
                                            Còn hàng
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- THAO TÁC -->

                                <td>

                                    <div class="row-actions">

                                        <a
                                            href="sua_linh_kien.php?id=<?php echo (int) $part['id']; ?>"
                                            class="btn-edit"
                                            target="mainContent"
                                            title="Sửa linh kiện"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                            Sửa
                                        </a>

                                        <form
                                            action="xoa_linh_kien.php"
                                            method="POST"
                                            onsubmit="return confirm('Bạn có chắc chắn muốn xóa linh kiện này không?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int) $part['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-delete"
                                                title="Xóa linh kiện"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                                Xóa
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="10" class="empty-row">

                                <i class="fa-solid fa-box-open"></i>

                                Chưa có linh kiện nào trong hệ thống.

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
