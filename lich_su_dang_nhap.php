
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

// ======================================================
// 1. HÀM HỖ TRỢ
// ======================================================

function eLichSu($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ======================================================
// 2. NHẬN BỘ LỌC TỪ URL
// ======================================================

$tuKhoa = trim($_GET['keyword'] ?? '');
$trangThai = $_GET['status'] ?? '';
$tuNgay = $_GET['from_date'] ?? '';
$denNgay = $_GET['to_date'] ?? '';

$soBanGhiMoiTrang = 20;
$trangHienTai = max(1, (int) ($_GET['page'] ?? 1));

// Kiểm tra trạng thái hợp lệ.
if (!in_array($trangThai, ['', 'success', 'failed'], true)) {
    $trangThai = '';
}

// Kiểm tra ngày hợp lệ.
function ngayHopLe(string $date): bool
{
    if ($date === '') {
        return true;
    }

    $parts = explode('-', $date);

    if (count($parts) !== 3) {
        return false;
    }

    return checkdate(
        (int) $parts[1],
        (int) $parts[2],
        (int) $parts[0]
    );
}

if (!ngayHopLe($tuNgay)) {
    $tuNgay = '';
}

if (!ngayHopLe($denNgay)) {
    $denNgay = '';
}

// Không cho phép ngày bắt đầu lớn hơn ngày kết thúc.
if ($tuNgay !== '' && $denNgay !== '' && $tuNgay > $denNgay) {
    $denNgay = '';
}

// ======================================================
// 3. XÂY DỰNG ĐIỀU KIỆN TÌM KIẾM
// ======================================================

$where = [];
$params = [];

if ($tuKhoa !== '') {
    $where[] = "(
        login_history.username LIKE :keyword
        OR users.full_name LIKE :keyword_name
    )";

    $params['keyword'] = '%' . $tuKhoa . '%';
    $params['keyword_name'] = '%' . $tuKhoa . '%';
}

if ($trangThai !== '') {
    $where[] = 'login_history.status = :status';
    $params['status'] = $trangThai;
}

if ($tuNgay !== '') {
    $where[] = 'login_history.created_at >= :from_date';
    $params['from_date'] = $tuNgay . ' 00:00:00';
}

if ($denNgay !== '') {
    $where[] = 'login_history.created_at <= :to_date';
    $params['to_date'] = $denNgay . ' 23:59:59';
}

$whereSql = '';

if (count($where) > 0) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// ======================================================
// 4. ĐẾM TỔNG SỐ BẢN GHI
// ======================================================

$sqlCount = "
    SELECT COUNT(*)
    FROM login_history
    LEFT JOIN users
        ON login_history.user_id = users.id
    $whereSql
";

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);

$tongBanGhi = (int) $stmtCount->fetchColumn();

$tongSoTrang = max(
    1,
    (int) ceil($tongBanGhi / $soBanGhiMoiTrang)
);

$trangHienTai = min($trangHienTai, $tongSoTrang);

$offset = ($trangHienTai - 1) * $soBanGhiMoiTrang;

// ======================================================
// 5. LẤY LỊCH SỬ ĐĂNG NHẬP
// ======================================================

$sql = "
    SELECT
        login_history.id,
        login_history.user_id,
        login_history.username,
        login_history.status,
        login_history.ip_address,
        login_history.user_agent,
        login_history.created_at,
        users.full_name

    FROM login_history

    LEFT JOIN users
        ON login_history.user_id = users.id

    $whereSql

    ORDER BY login_history.created_at DESC, login_history.id DESC

    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}

$stmt->bindValue(':limit', $soBanGhiMoiTrang, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();

$lichSu = $stmt->fetchAll();

// ======================================================
// 6. TẠO URL PHÂN TRANG
// ======================================================

function taoUrlTrang(int $page): string
{
    $params = $_GET;
    $params['page'] = $page;

    return '?' . http_build_query($params);
}

// ======================================================
// 7. TÍNH THỐNG KÊ TRÊN DỮ LIỆU ĐANG HIỂN THỊ
// ======================================================

$soDangNhapThanhCong = 0;
$soDangNhapThatBai = 0;

foreach ($lichSu as $item) {
    if ($item['status'] === 'success') {
        $soDangNhapThanhCong++;
    } else {
        $soDangNhapThatBai++;
    }
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lịch sử đăng nhập</title>

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
            padding: 25px;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f7fb;
            color: #1e293b;
        }

        .container {
            max-width: 1600px;
            margin: auto;
        }

        /* ==============================
           HEADER
        ============================== */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .page-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: #e8efff;
            color: #2563eb;
            font-size: 22px;
        }

        .page-title h2 {
            margin: 0 0 5px;
            color: #172554;
            font-size: 24px;
            font-weight: 700;
        }

        .description {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }

        .header-note {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 9px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ==============================
           STATISTICS
        ============================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #fff;
            border: 1px solid #edf0f5;
            border-radius: 13px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 19px;
            flex-shrink: 0;
        }

        .stat-icon.blue {
            background: #e8efff;
            color: #2563eb;
        }

        .stat-icon.green {
            background: #dcfce7;
            color: #16a34a;
        }

        .stat-icon.red {
            background: #fee2e2;
            color: #dc2626;
        }

        .stat-info {
            min-width: 0;
        }

        .stat-label {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 13px;
        }

        .stat-value {
            color: #172554;
            font-size: 23px;
            font-weight: 700;
        }

        /* ==============================
           FILTER
        ============================== */

        .filter-box {
            padding: 22px;
            margin-bottom: 20px;
            background: #fff;
            border: 1px solid #edf0f5;
            border-radius: 13px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .section-heading {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 18px;
            color: #1e293b;
            font-size: 15px;
            font-weight: 700;
        }

        .section-heading i {
            color: #2563eb;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
            gap: 13px;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        input,
        select {
            width: 100%;
            height: 42px;
            padding: 0 12px;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            outline: none;
            background: #fff;
            color: #334155;
            font-family: inherit;
            font-size: 13px;
            transition: 0.2s;
        }

        input:focus,
        select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        input::placeholder {
            color: #94a3b8;
        }

        .btn {
            height: 42px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 0 17px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            transition: 0.2s;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-reset {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-reset:hover {
            background: #e2e8f0;
        }

        /* ==============================
           TABLE
        ============================== */

        .table-card {
            overflow: hidden;
            background: #fff;
            border: 1px solid #edf0f5;
            border-radius: 13px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 19px 22px;
            border-bottom: 1px solid #edf0f5;
        }

        .table-header h3 {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0;
            color: #1e293b;
            font-size: 15px;
        }

        .table-header h3 i {
            color: #2563eb;
        }

        .record-count {
            padding: 6px 11px;
            border-radius: 20px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
        }

        th {
            padding: 15px 14px;
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        td {
            padding: 15px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 13px;
            vertical-align: middle;
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

        .id-text {
            color: #94a3b8;
            font-weight: 600;
        }

        .account-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .account-avatar {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 10px;
            background: #e8efff;
            color: #2563eb;
            font-size: 13px;
        }

        .account-name {
            color: #1e293b;
            font-weight: 700;
        }

        .employee-name {
            color: #475569;
            font-weight: 500;
        }

        .unknown-user {
            color: #94a3b8;
            font-style: italic;
        }

        /* ==============================
           STATUS
        ============================== */

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status.success {
            background: #dcfce7;
            color: #15803d;
        }

        .status.failed {
            background: #fee2e2;
            color: #dc2626;
        }

        .status i {
            font-size: 10px;
        }

        /* ==============================
           IP AND DEVICE
        ============================== */

        .ip-address {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 9px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            font-family: Consolas, monospace;
            font-size: 12px;
        }

        .device-info {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            max-width: 280px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .device-info i {
            margin-top: 3px;
            color: #94a3b8;
            flex-shrink: 0;
        }

        .time-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            white-space: nowrap;
        }

        .time-info i {
            color: #94a3b8;
        }

        /* ==============================
           EMPTY
        ============================== */

        .empty {
            padding: 55px 20px;
            text-align: center;
            color: #94a3b8;
        }

        .empty i {
            display: block;
            margin-bottom: 13px;
            font-size: 38px;
            color: #cbd5e1;
        }

        .empty strong {
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-size: 14px;
        }

        .empty span {
            font-size: 12px;
        }

        /* ==============================
           PAGINATION
        ============================== */

        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            padding: 18px 22px;
            border-top: 1px solid #edf0f5;
        }

        .pagination-info {
            color: #64748b;
            font-size: 12px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .pagination a {
            min-width: 34px;
            height: 34px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 0 10px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: #fff;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
        }

        .pagination a:hover:not(.disabled):not(.active) {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #2563eb;
        }

        .pagination a.active {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
        }

        .pagination a.disabled {
            pointer-events: none;
            background: #f8fafc;
            color: #cbd5e1;
        }

        /* ==============================
           RESPONSIVE
        ============================== */

        @media (max-width: 1150px) {
            .filter-form {
                grid-template-columns: repeat(3, 1fr);
            }

            .stats-grid {
                gap: 12px;
            }
        }

        @media (max-width: 750px) {
            body {
                padding: 15px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .header-note {
                white-space: normal;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: repeat(2, 1fr);
            }

            .pagination-wrapper {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .filter-form {
                grid-template-columns: 1fr;
            }

            .page-title h2 {
                font-size: 20px;
            }

            .filter-box {
                padding: 16px;
            }

            .table-header {
                padding: 16px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- ==============================
         HEADER
    =============================== -->

    <div class="page-header">

        <div class="page-title">

            <div class="page-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <div>
                <h2>Lịch sử đăng nhập</h2>

                <p class="description">
                    Theo dõi và kiểm tra hoạt động đăng nhập của các tài khoản trong hệ thống.
                </p>
            </div>

        </div>

        <div class="header-note">
            <i class="fa-solid fa-shield-halved"></i>
            Giám sát hoạt động tài khoản
        </div>

    </div>

    <!-- ==============================
         STATISTICS
    =============================== -->

    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-icon blue">
                <i class="fa-solid fa-list-check"></i>
            </div>

            <div class="stat-info">

                <span class="stat-label">
                    Tổng số bản ghi
                </span>

                <div class="stat-value">
                    <?php echo number_format($tongBanGhi); ?>
                </div>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon green">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <div class="stat-info">

                <span class="stat-label">
                    Thành công trên trang này
                </span>

                <div class="stat-value">
                    <?php echo number_format($soDangNhapThanhCong); ?>
                </div>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon red">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>

            <div class="stat-info">

                <span class="stat-label">
                    Thất bại trên trang này
                </span>

                <div class="stat-value">
                    <?php echo number_format($soDangNhapThatBai); ?>
                </div>

            </div>

        </div>

    </div>

    <!-- ==============================
         FILTER
    =============================== -->

    <div class="filter-box">

        <div class="section-heading">
            <i class="fa-solid fa-filter"></i>
            Bộ lọc lịch sử đăng nhập
        </div>

        <form method="GET" class="filter-form">

            <div class="form-group">

                <label for="keyword">
                    Tìm kiếm tài khoản / nhân viên
                </label>

                <input
                    type="text"
                    id="keyword"
                    name="keyword"
                    value="<?php echo eLichSu($tuKhoa); ?>"
                    placeholder="Nhập tên đăng nhập hoặc họ tên..."
                >

            </div>

            <div class="form-group">

                <label for="status">
                    Trạng thái
                </label>

                <select name="status" id="status">

                    <option value="">Tất cả trạng thái</option>

                    <option
                        value="success"
                        <?php echo $trangThai === 'success' ? 'selected' : ''; ?>
                    >
                        Thành công
                    </option>

                    <option
                        value="failed"
                        <?php echo $trangThai === 'failed' ? 'selected' : ''; ?>
                    >
                        Thất bại
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label for="from_date">
                    Từ ngày
                </label>

                <input
                    type="date"
                    id="from_date"
                    name="from_date"
                    value="<?php echo eLichSu($tuNgay); ?>"
                >

            </div>

            <div class="form-group">

                <label for="to_date">
                    Đến ngày
                </label>

                <input
                    type="date"
                    id="to_date"
                    name="to_date"
                    value="<?php echo eLichSu($denNgay); ?>"
                >

            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-magnifying-glass"></i>
                Tìm kiếm
            </button>

            <a href="lich_su_dang_nhap.php" class="btn btn-reset">
                <i class="fa-solid fa-rotate-left"></i>
                Xóa lọc
            </a>

        </form>

    </div>

    <!-- ==============================
         HISTORY TABLE
    =============================== -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fa-solid fa-table-list"></i>
                Danh sách lịch sử đăng nhập
            </h3>

            <span class="record-count">
                <?php echo number_format($tongBanGhi); ?> bản ghi
            </span>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Tài khoản</th>
                        <th>Họ tên nhân viên</th>
                        <th>Trạng thái</th>
                        <th>Địa chỉ IP</th>
                        <th>Trình duyệt / Thiết bị</th>
                        <th>Thời gian</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (count($lichSu) > 0): ?>

                        <?php foreach ($lichSu as $item): ?>

                            <tr>

                                <td>
                                    <span class="id-text">
                                        #<?php echo eLichSu($item['id']); ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="account-info">

                                        <div class="account-avatar">
                                            <i class="fa-solid fa-user"></i>
                                        </div>

                                        <span class="account-name">
                                            <?php echo eLichSu($item['username']); ?>
                                        </span>

                                    </div>

                                </td>

                                <td>

                                    <?php if (!empty($item['full_name'])): ?>

                                        <span class="employee-name">
                                            <?php echo eLichSu($item['full_name']); ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="unknown-user">
                                            Tài khoản không còn tồn tại
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if ($item['status'] === 'success'): ?>

                                        <span class="status success">
                                            <i class="fa-solid fa-check"></i>
                                            Thành công
                                        </span>

                                    <?php else: ?>

                                        <span class="status failed">
                                            <i class="fa-solid fa-xmark"></i>
                                            Thất bại
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <span class="ip-address">
                                        <i class="fa-solid fa-network-wired"></i>
                                        <?php echo eLichSu($item['ip_address']); ?>
                                    </span>

                                </td>

                                <td>

                                    <div class="device-info">

                                        <i class="fa-solid fa-desktop"></i>

                                        <span>
                                            <?php echo eLichSu($item['user_agent']); ?>
                                        </span>

                                    </div>

                                </td>

                                <td>

                                    <div class="time-info">

                                        <i class="fa-regular fa-clock"></i>

                                        <?php echo eLichSu($item['created_at']); ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="7">

                                <div class="empty">

                                    <i class="fa-solid fa-folder-open"></i>

                                    <strong>
                                        Không tìm thấy lịch sử đăng nhập
                                    </strong>

                                    <span>
                                        Thử thay đổi từ khóa hoặc điều kiện lọc.
                                    </span>

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- ==============================
             PAGINATION
        =============================== -->

        <?php if ($tongSoTrang > 1): ?>

            <div class="pagination-wrapper">

                <div class="pagination-info">

                    Trang
                    <strong><?php echo $trangHienTai; ?></strong>
                    /
                    <strong><?php echo $tongSoTrang; ?></strong>

                    &nbsp; | &nbsp;

                    Tổng cộng
                    <strong><?php echo number_format($tongBanGhi); ?></strong>
                    bản ghi

                </div>

                <div class="pagination">

                    <a
                        href="<?php echo eLichSu(taoUrlTrang(max(1, $trangHienTai - 1))); ?>"
                        class="<?php echo $trangHienTai === 1 ? 'disabled' : ''; ?>"
                    >
                        <i class="fa-solid fa-chevron-left"></i>
                        Trước
                    </a>

                    <?php

                    $batDau = max(1, $trangHienTai - 2);
                    $ketThuc = min($tongSoTrang, $trangHienTai + 2);

                    ?>

                    <?php for ($i = $batDau; $i <= $ketThuc; $i++): ?>

                        <a
                            href="<?php echo eLichSu(taoUrlTrang($i)); ?>"
                            class="<?php echo $i === $trangHienTai ? 'active' : ''; ?>"
                        >
                            <?php echo $i; ?>
                        </a>

                    <?php endfor; ?>

                    <a
                        href="<?php echo eLichSu(taoUrlTrang(min($tongSoTrang, $trangHienTai + 1))); ?>"
                        class="<?php echo $trangHienTai === $tongSoTrang ? 'disabled' : ''; ?>"
                    >
                        Tiếp
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
