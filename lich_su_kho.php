
<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// Hàm bảo vệ dữ liệu
function eLichSuKho($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ==========================================
// 1. LẤY GIÁ TRỊ BỘ LỌC
// ==========================================

$tuKhoa = trim($_GET['keyword'] ?? '');

$loaiGiaoDich = $_GET['type'] ?? 'all';

if (!in_array($loaiGiaoDich, ['all', 'import', 'export'], true)) {
    $loaiGiaoDich = 'all';
}

$nhanVienId = filter_input(
    INPUT_GET,
    'employee',
    FILTER_VALIDATE_INT
);

if (!$nhanVienId || $nhanVienId < 1) {
    $nhanVienId = 0;
}

// ==========================================
// 2. CẤU HÌNH PHÂN TRANG
// ==========================================

$soDongMoiTrang = 20;

$trangHienTai = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT
);

if (!$trangHienTai || $trangHienTai < 1) {
    $trangHienTai = 1;
}

// ==========================================
// 3. LẤY DANH SÁCH NHÂN VIÊN
// ==========================================

$stmtNhanVien = $pdo->query("
    SELECT DISTINCT
        u.id,
        u.full_name
    FROM stock_movements sm
    INNER JOIN users u ON sm.created_by = u.id
    ORDER BY u.full_name ASC
");

$danhSachNhanVien = $stmtNhanVien->fetchAll();

// ==========================================
// 4. THỐNG KÊ TOÀN BỘ GIAO DỊCH
// Không bị ảnh hưởng bởi bộ lọc
// ==========================================

$stmtThongKe = $pdo->query("
    SELECT
        COUNT(*) AS tong_giao_dich,
        COALESCE(
            SUM(CASE WHEN movement_type = 'import' THEN quantity ELSE 0 END),
            0
        ) AS tong_nhap,
        COALESCE(
            SUM(CASE WHEN movement_type = 'export' THEN quantity ELSE 0 END),
            0
        ) AS tong_xuat
    FROM stock_movements
");

$thongKe = $stmtThongKe->fetch();

$tongGiaoDich = (int) $thongKe['tong_giao_dich'];
$tongNhap = (int) $thongKe['tong_nhap'];
$tongXuat = (int) $thongKe['tong_xuat'];

// ==========================================
// 5. XÂY DỰNG ĐIỀU KIỆN LỌC
// ==========================================

$whereSql = "
    FROM stock_movements sm
    INNER JOIN parts p ON sm.part_id = p.id
    LEFT JOIN users u ON sm.created_by = u.id
    WHERE 1 = 1
";

$params = [];

// Tìm kiếm tên linh kiện hoặc SKU
if ($tuKhoa !== '') {
    $whereSql .= "
        AND (
            p.part_name LIKE :keyword_name
            OR p.sku LIKE :keyword_sku
        )
    ";

    $params['keyword_name'] = '%' . $tuKhoa . '%';
    $params['keyword_sku'] = '%' . $tuKhoa . '%';
}

// Lọc loại giao dịch
if ($loaiGiaoDich !== 'all') {
    $whereSql .= " AND sm.movement_type = :movement_type";
    $params['movement_type'] = $loaiGiaoDich;
}

// Lọc nhân viên
if ($nhanVienId > 0) {
    $whereSql .= " AND sm.created_by = :employee_id";
    $params['employee_id'] = $nhanVienId;
}

// ==========================================
// 6. ĐẾM TỔNG SỐ KẾT QUẢ SAU KHI LỌC
// ==========================================

$sqlDem = "
    SELECT COUNT(*)
    " . $whereSql;

$stmtDem = $pdo->prepare($sqlDem);
$stmtDem->execute($params);

$soKetQua = (int) $stmtDem->fetchColumn();

// Tính tổng số trang
$tongSoTrang = max(
    1,
    (int) ceil($soKetQua / $soDongMoiTrang)
);

// Không cho phép truy cập trang vượt quá tổng số trang
if ($trangHienTai > $tongSoTrang) {
    $trangHienTai = $tongSoTrang;
}

// Tính vị trí bắt đầu lấy dữ liệu
$viTriBatDau = ($trangHienTai - 1) * $soDongMoiTrang;

// ==========================================
// 7. LẤY GIAO DỊCH CỦA TRANG HIỆN TẠI
// ==========================================

$sql = "
    SELECT
        sm.id,
        sm.movement_type,
        sm.quantity,
        sm.note,
        sm.created_at,
        p.part_name,
        p.sku,
        p.quantity AS current_stock,
        u.full_name AS employee_name
    " . $whereSql . "
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

// Gắn các tham số bộ lọc
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}

// Gắn tham số phân trang dưới dạng số nguyên
$stmt->bindValue(':limit', $soDongMoiTrang, PDO::PARAM_INT);
$stmt->bindValue(':offset', $viTriBatDau, PDO::PARAM_INT);

$stmt->execute();

$movements = $stmt->fetchAll();

// ==========================================
// 8. TẠO ĐƯỜNG DẪN GIỮ NGUYÊN BỘ LỌC
// ==========================================

$thamSoPhanTrang = [
    'keyword' => $tuKhoa,
    'type' => $loaiGiaoDich,
    'employee' => $nhanVienId
];

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lịch sử kho linh kiện</title>

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
            padding: 28px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f6fb;
            color: #1e293b;
        }

        .container {
            max-width: 1500px;
            margin: 0 auto;
        }

        /* HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 13px;
            margin: 0 0 9px;
            color: #172554;
            font-size: 27px;
            font-weight: 700;
        }

        .page-title-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            background: #dbeafe;
            color: #2563eb;
            font-size: 21px;
        }

        .description {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 11px 16px;
            border: 1px solid #dbe3ef;
            border-radius: 9px;
            background: #fff;
            color: #334155;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            transition: 0.2s;
        }

        .btn-back:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        /* THỐNG KÊ */

        .statistics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 17px;
            padding: 22px;
            overflow: hidden;
            background: #fff;
            border: 1px solid #e8edf5;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            font-size: 21px;
        }

        .stat-icon.blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .stat-icon.green {
            background: #dcfce7;
            color: #15803d;
        }

        .stat-icon.red {
            background: #fee2e2;
            color: #dc2626;
        }

        .stat-info p {
            margin: 0 0 8px;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }

        .stat-info strong {
            display: block;
            color: #172554;
            font-size: 26px;
            font-weight: 700;
        }

        /* KHUNG NỘI DUNG */

        .content-card {
            padding: 24px;
            background: #fff;
            border: 1px solid #e8edf5;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .section-heading h2 {
            margin: 0;
            color: #172554;
            font-size: 19px;
        }

        .section-heading p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        /* BỘ LỌC */

        .filter-box {
            margin-bottom: 22px;
            padding: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0 0 17px;
            color: #334155;
            font-size: 15px;
            font-weight: 700;
        }

        .filter-title i {
            color: #2563eb;
        }

        .filter-form {
            display: grid;
            grid-template-columns: minmax(200px, 2fr) minmax(150px, 1fr) minmax(180px, 1fr) auto auto;
            gap: 13px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            height: 42px;
            padding: 0 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            background: #fff;
            color: #334155;
            font-family: inherit;
            font-size: 13px;
            transition: 0.2s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .btn-search,
        .btn-reset {
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            transition: 0.2s;
        }

        .btn-search {
            background: #2563eb;
            color: #fff;
        }

        .btn-search:hover {
            background: #1d4ed8;
        }

        .btn-reset {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #475569;
        }

        .btn-reset:hover {
            background: #f1f5f9;
        }

        /* THÔNG TIN KẾT QUẢ */

        .result-count {
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 0 0 15px;
            color: #64748b;
            font-size: 13px;
        }

        .result-count strong {
            color: #1e293b;
        }

        /* BẢNG DỮ LIỆU */

        .table-container {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
            text-align: left;
        }

        thead {
            background: #f1f5f9;
        }

        th {
            padding: 15px 13px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 14px 13px;
            color: #334155;
            font-size: 13px;
            border-bottom: 1px solid #edf1f5;
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .id-text {
            color: #64748b;
            font-weight: 600;
        }

        .part-name {
            color: #1e293b;
            font-weight: 600;
        }

        .sku-text {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 5px;
            background: #f1f5f9;
            color: #475569;
            font-family: monospace;
            font-size: 12px;
        }

        .movement-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .movement-badge.import {
            background: #dcfce7;
            color: #15803d;
        }

        .movement-badge.export {
            background: #fee2e2;
            color: #dc2626;
        }

        .quantity {
            font-weight: 700;
            color: #1e293b;
        }

        .current-stock {
            color: #2563eb;
            font-weight: 700;
        }

        .employee-name {
            color: #475569;
        }

        .date-text {
            color: #64748b;
            white-space: nowrap;
            font-size: 12px;
        }

        .note-text {
            max-width: 220px;
            color: #64748b;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .empty {
            padding: 45px 20px;
            text-align: center;
            color: #94a3b8;
        }

        .empty i {
            display: block;
            margin-bottom: 12px;
            font-size: 30px;
        }

        /* PHÂN TRANG */

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 25px;
        }

        .pagination a,
        .pagination span {
            min-width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 11px;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            background: #fff;
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .pagination a:hover {
            border-color: #2563eb;
            background: #eff6ff;
            color: #2563eb;
        }

        .pagination .active {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
        }

        .pagination .disabled {
            background: #f8fafc;
            color: #cbd5e1;
            cursor: not-allowed;
        }

        .pagination-info {
            margin: 15px 0 0;
            color: #94a3b8;
            text-align: center;
            font-size: 12px;
        }

        /* RESPONSIVE */

        @media (max-width: 1100px) {
            .filter-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            body {
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
            }

            .page-title {
                font-size: 22px;
            }

            .statistics {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .content-card {
                padding: 16px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .btn-search,
            .btn-reset {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="page-header">

        <div>
            <h1 class="page-title">
                <span class="page-title-icon">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </span>

                Lịch sử nhập/xuất kho
            </h1>

            <p class="description">
                Theo dõi toàn bộ giao dịch nhập và xuất linh kiện trong hệ thống.
            </p>
        </div>

        <a
            href="linh_kien.php"
            class="btn-back"
            target="mainContent"
        >
            <i class="fa-solid fa-arrow-left"></i>
            Quay lại quản lý linh kiện
        </a>

    </div>

    <!-- THỐNG KÊ -->

    <div class="statistics">

        <div class="stat-card">

            <div class="stat-icon blue">
                <i class="fa-solid fa-receipt"></i>
            </div>

            <div class="stat-info">
                <p>Tổng số giao dịch</p>
                <strong>
                    <?php echo number_format($tongGiaoDich); ?>
                </strong>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon green">
                <i class="fa-solid fa-arrow-down"></i>
            </div>

            <div class="stat-info">
                <p>Tổng số lượng đã nhập</p>
                <strong>
                    <?php echo number_format($tongNhap); ?>
                </strong>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon red">
                <i class="fa-solid fa-arrow-up"></i>
            </div>

            <div class="stat-info">
                <p>Tổng số lượng đã xuất</p>
                <strong>
                    <?php echo number_format($tongXuat); ?>
                </strong>
            </div>

        </div>

    </div>

    <!-- NỘI DUNG CHÍNH -->

    <div class="content-card">

        <div class="section-heading">
            <div>
                <h2>Danh sách giao dịch</h2>
                <p>Thông tin chi tiết các lần nhập và xuất linh kiện.</p>
            </div>
        </div>

        <!-- BỘ LỌC TÌM KIẾM -->

        <div class="filter-box">

            <h3 class="filter-title">
                <i class="fa-solid fa-filter"></i>
                Tìm kiếm và lọc giao dịch
            </h3>

            <form
                method="GET"
                action="lich_su_kho.php"
                target="mainContent"
                class="filter-form"
            >

                <div class="filter-group">

                    <label for="keyword">
                        Tên linh kiện hoặc SKU
                    </label>

                    <input
                        type="text"
                        id="keyword"
                        name="keyword"
                        placeholder="Nhập tên hoặc mã SKU..."
                        value="<?php echo eLichSuKho($tuKhoa); ?>"
                    >

                </div>

                <div class="filter-group">

                    <label for="type">
                        Loại giao dịch
                    </label>

                    <select name="type" id="type">

                        <option
                            value="all"
                            <?php echo $loaiGiaoDich === 'all' ? 'selected' : ''; ?>
                        >
                            Tất cả
                        </option>

                        <option
                            value="import"
                            <?php echo $loaiGiaoDich === 'import' ? 'selected' : ''; ?>
                        >
                            Nhập kho
                        </option>

                        <option
                            value="export"
                            <?php echo $loaiGiaoDich === 'export' ? 'selected' : ''; ?>
                        >
                            Xuất kho
                        </option>

                    </select>

                </div>

                <div class="filter-group">

                    <label for="employee">
                        Nhân viên thực hiện
                    </label>

                    <select name="employee" id="employee">

                        <option value="0">Tất cả nhân viên</option>

                        <?php foreach ($danhSachNhanVien as $nhanVien): ?>

                            <option
                                value="<?php echo (int) $nhanVien['id']; ?>"
                                <?php echo $nhanVienId === (int) $nhanVien['id'] ? 'selected' : ''; ?>
                            >
                                <?php echo eLichSuKho($nhanVien['full_name']); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button type="submit" class="btn-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Tìm kiếm
                </button>

                <a
                    href="lich_su_kho.php"
                    class="btn-reset"
                    target="mainContent"
                >
                    <i class="fa-solid fa-rotate-left"></i>
                    Xóa bộ lọc
                </a>

            </form>

        </div>

        <!-- THÔNG TIN KẾT QUẢ -->

        <p class="result-count">
            <i class="fa-solid fa-circle-info"></i>

            Tìm thấy
            <strong><?php echo number_format($soKetQua); ?></strong>
            giao dịch phù hợp.
        </p>

        <!-- BẢNG DỮ LIỆU -->

        <div class="table-container">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên linh kiện</th>
                        <th>SKU</th>
                        <th>Loại giao dịch</th>
                        <th>Số lượng</th>
                        <th>Tồn kho hiện tại</th>
                        <th>Người thực hiện</th>
                        <th>Thời gian</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($movements)): ?>

                        <?php foreach ($movements as $movement): ?>

                            <tr>

                                <td>
                                    <span class="id-text">
                                        #<?php echo (int) $movement['id']; ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="part-name">
                                        <?php echo eLichSuKho($movement['part_name']); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="sku-text">
                                        <?php echo eLichSuKho($movement['sku'] ?: 'Chưa có'); ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if ($movement['movement_type'] === 'import'): ?>

                                        <span class="movement-badge import">
                                            <i class="fa-solid fa-arrow-down"></i>
                                            Nhập kho
                                        </span>

                                    <?php else: ?>

                                        <span class="movement-badge export">
                                            <i class="fa-solid fa-arrow-up"></i>
                                            Xuất kho
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <span class="quantity">
                                        <?php echo number_format((int) $movement['quantity']); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="current-stock">
                                        <?php echo number_format((int) $movement['current_stock']); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="employee-name">
                                        <?php
                                        echo eLichSuKho(
                                            $movement['employee_name'] ?: 'Không xác định'
                                        );
                                        ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="date-text">
                                        <?php
                                        echo eLichSuKho(
                                            date(
                                                'd/m/Y H:i:s',
                                                strtotime($movement['created_at'])
                                            )
                                        );
                                        ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="note-text">
                                        <?php echo eLichSuKho($movement['note'] ?: 'Không có'); ?>
                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="9" class="empty">
                                <i class="fa-solid fa-box-open"></i>
                                Không tìm thấy giao dịch phù hợp.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- PHÂN TRANG -->

        <?php if ($tongSoTrang > 1): ?>

            <div class="pagination">

                <!-- TRANG TRƯỚC -->

                <?php if ($trangHienTai > 1): ?>

                    <a
                        href="lich_su_kho.php?<?php
                            echo eLichSuKho(http_build_query(
                                array_merge(
                                    $thamSoPhanTrang,
                                    ['page' => $trangHienTai - 1]
                                )
                            ));
                        ?>"
                        target="mainContent"
                    >
                        <i class="fa-solid fa-chevron-left"></i>
                        Trước
                    </a>

                <?php else: ?>

                    <span class="disabled">
                        <i class="fa-solid fa-chevron-left"></i>
                        Trước
                    </span>

                <?php endif; ?>

                <!-- CÁC SỐ TRANG -->

                <?php for ($i = 1; $i <= $tongSoTrang; $i++): ?>

                    <?php if ($i === $trangHienTai): ?>

                        <span class="active">
                            <?php echo $i; ?>
                        </span>

                    <?php else: ?>

                        <a
                            href="lich_su_kho.php?<?php
                                echo eLichSuKho(http_build_query(
                                    array_merge(
                                        $thamSoPhanTrang,
                                        ['page' => $i]
                                    )
                                ));
                            ?>"
                            target="mainContent"
                        >
                            <?php echo $i; ?>
                        </a>

                    <?php endif; ?>

                <?php endfor; ?>

                <!-- TRANG SAU -->

                <?php if ($trangHienTai < $tongSoTrang): ?>

                    <a
                        href="lich_su_kho.php?<?php
                            echo eLichSuKho(http_build_query(
                                array_merge(
                                    $thamSoPhanTrang,
                                    ['page' => $trangHienTai + 1]
                                )
                            ));
                        ?>"
                        target="mainContent"
                    >
                        Sau
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                <?php else: ?>

                    <span class="disabled">
                        Sau
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>

                <?php endif; ?>

            </div>

        <?php endif; ?>

        <p class="pagination-info">
            Trang <?php echo number_format($trangHienTai); ?>
            / <?php echo number_format($tongSoTrang); ?>.
            Hiển thị tối đa <?php echo $soDongMoiTrang; ?> giao dịch mỗi trang.
        </p>

    </div>

</div>

</body>
</html>
