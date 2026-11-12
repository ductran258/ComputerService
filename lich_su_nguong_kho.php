
<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

// ==========================================
// 1. HÀM HỖ TRỢ
// ==========================================

function eLichSuNguong($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ==========================================
// 2. LẤY TỪ KHÓA TÌM KIẾM
// ==========================================

$tuKhoa = trim($_GET['keyword'] ?? '');

$whereSql = '';
$params = [];

if ($tuKhoa !== '') {
    $whereSql = "
        WHERE part_name LIKE :keyword
        OR changed_by_name LIKE :keyword
    ";

    $params['keyword'] = '%' . $tuKhoa . '%';
}

// ==========================================
// 3. PHÂN TRANG
// ==========================================

$soDongMoiTrang = 20;

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

if (!$page || $page < 1) {
    $page = 1;
}

// Đếm tổng số bản ghi
$sqlCount = "
    SELECT COUNT(*)
    FROM part_threshold_history
    $whereSql
";

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);

$tongSoBanGhi = (int) $stmtCount->fetchColumn();

$tongSoTrang = max(
    1,
    (int) ceil($tongSoBanGhi / $soDongMoiTrang)
);

if ($page > $tongSoTrang) {
    $page = $tongSoTrang;
}

$offset = ($page - 1) * $soDongMoiTrang;

// ==========================================
// 4. LẤY DANH SÁCH LỊCH SỬ
// ==========================================

$sqlHistory = "
    SELECT
        id,
        part_id,
        part_name,
        old_min_quantity,
        new_min_quantity,
        changed_by,
        changed_by_name,
        changed_at
    FROM part_threshold_history
    $whereSql
    ORDER BY changed_at DESC, id DESC
    LIMIT :limit OFFSET :offset
";

$stmtHistory = $pdo->prepare($sqlHistory);

foreach ($params as $key => $value) {
    $stmtHistory->bindValue(':' . $key, $value, PDO::PARAM_STR);
}

$stmtHistory->bindValue(':limit', $soDongMoiTrang, PDO::PARAM_INT);
$stmtHistory->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmtHistory->execute();

$lichSu = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 5. TÍNH SỐ BẢN GHI HIỂN THỊ
// ==========================================

$batDau = $tongSoBanGhi > 0 ? $offset + 1 : 0;

$ketThuc = min(
    $offset + $soDongMoiTrang,
    $tongSoBanGhi
);

// ==========================================
// 6. HÀM TẠO LINK PHÂN TRANG
// ==========================================

function linkTrangNguong(int $page, string $tuKhoa): string
{
    return '?' . http_build_query([
        'page' => $page,
        'keyword' => $tuKhoa
    ]);
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lịch sử điều chỉnh ngưỡng kho</title>

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
            max-width: 1450px;
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

        .title-group {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .page-icon {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            background: #fef3c7;
            color: #d97706;
            font-size: 21px;
        }

        .page-title {
            margin: 0;
            color: #172554;
            font-size: 25px;
            font-weight: 700;
        }

        .description {
            margin: 9px 0 0 61px;
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

        /* THẺ GIỚI THIỆU */

        .info-card {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin-bottom: 22px;
            padding: 17px 20px;
            border: 1px solid #fde68a;
            border-radius: 11px;
            background: #fffbeb;
        }

        .info-card i {
            margin-top: 2px;
            color: #d97706;
            font-size: 18px;
        }

        .info-card strong {
            display: block;
            margin-bottom: 5px;
            color: #92400e;
            font-size: 14px;
        }

        .info-card p {
            margin: 0;
            color: #a16207;
            font-size: 13px;
            line-height: 1.6;
        }

        /* PANEL */

        .panel {
            margin-bottom: 22px;
            padding: 23px;
            border: 1px solid #e8edf5;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .panel-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 18px;
            color: #172554;
            font-size: 18px;
            font-weight: 700;
        }

        .panel-heading i {
            color: #2563eb;
        }

        .panel-description {
            margin: -10px 0 19px;
            color: #64748b;
            font-size: 13px;
        }

        /* TÌM KIẾM */

        .search-form {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-group {
            flex: 1;
            min-width: 240px;
        }

        .search-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
        }

        .search-input-wrapper {
            position: relative;
        }

        .search-input-wrapper i {
            position: absolute;
            top: 50%;
            left: 13px;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .search-form input {
            width: 100%;
            height: 43px;
            padding: 0 13px 0 38px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            background: #fff;
            color: #334155;
            font-family: inherit;
            font-size: 13px;
            transition: 0.2s;
        }

        .search-form input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .btn-search,
        .btn-reset {
            height: 43px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 17px;
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
            border: none;
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

        .result-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        .result-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }

        .result-info i {
            color: #2563eb;
        }

        .result-info strong {
            color: #1e293b;
        }

        .result-total {
            padding: 6px 11px;
            border-radius: 7px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
        }

        /* BẢNG DỮ LIỆU */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
            text-align: left;
        }

        thead {
            background: #f1f5f9;
        }

        th {
            padding: 15px 14px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        td {
            padding: 15px 14px;
            border-bottom: 1px solid #edf1f5;
            color: #334155;
            font-size: 13px;
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .history-id {
            color: #64748b;
            font-weight: 600;
        }

        .part-name {
            color: #1e293b;
            font-weight: 600;
        }

        .part-id {
            display: block;
            margin-top: 5px;
            color: #94a3b8;
            font-size: 11px;
        }

        .old-value,
        .new-value {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 43px;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
        }

        .old-value {
            background: #fee2e2;
            color: #dc2626;
        }

        .new-value {
            background: #dcfce7;
            color: #15803d;
        }

        .change-arrow {
            display: inline-block;
            margin: 0 7px;
            color: #94a3b8;
        }

        .employee-name {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            font-weight: 500;
        }

        .employee-icon {
            width: 29px;
            height: 29px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #e0e7ff;
            color: #4f46e5;
            font-size: 12px;
        }

        .date-text {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #64748b;
            white-space: nowrap;
        }

        .date-text i {
            color: #94a3b8;
        }

        .empty {
            padding: 45px 20px;
            text-align: center;
            color: #94a3b8;
        }

        .empty i {
            display: block;
            margin-bottom: 13px;
            font-size: 32px;
        }

        .empty strong {
            display: block;
            margin-bottom: 6px;
            color: #64748b;
            font-size: 14px;
        }

        .empty span {
            font-size: 12px;
        }

        /* PHÂN TRANG */

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
            margin-top: 23px;
        }

        .pagination a,
        .pagination span {
            min-width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
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

        @media (max-width: 700px) {
            body {
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
            }

            .page-title {
                font-size: 21px;
            }

            .description {
                margin-left: 0;
            }

            .panel {
                padding: 16px;
            }

            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-group {
                min-width: 100%;
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
            <div class="title-group">

                <span class="page-icon">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </span>

                <h1 class="page-title">
                    Lịch sử điều chỉnh ngưỡng tồn kho
                </h1>

            </div>

            <p class="description">
                Theo dõi các lần thay đổi ngưỡng cảnh báo của linh kiện.
            </p>
        </div>

        <a
            href="linh_kien.php"
            class="btn-back"
            target="mainContent"
        >
            <i class="fa-solid fa-arrow-left"></i>
            Quay lại quản lý kho
        </a>

    </div>

    <!-- GIỚI THIỆU -->

    <div class="info-card">

        <i class="fa-solid fa-circle-info"></i>

        <div>
            <strong>Lịch sử thay đổi ngưỡng cảnh báo</strong>

            <p>
                Trang này lưu lại giá trị ngưỡng tồn kho cũ, giá trị mới,
                người thực hiện và thời gian điều chỉnh để thuận tiện kiểm tra.
            </p>
        </div>

    </div>

    <!-- TÌM KIẾM -->

    <div class="panel">

        <h2 class="panel-heading">
            <i class="fa-solid fa-magnifying-glass"></i>
            Tìm kiếm lịch sử
        </h2>

        <p class="panel-description">
            Tìm theo tên linh kiện hoặc tên nhân viên thực hiện điều chỉnh.
        </p>

        <form
            method="GET"
            action="lich_su_nguong_kho.php"
            target="mainContent"
            class="search-form"
        >

            <div class="search-group">

                <label for="keyword">
                    Từ khóa tìm kiếm
                </label>

                <div class="search-input-wrapper">

                    <i class="fa-solid fa-search"></i>

                    <input
                        type="text"
                        id="keyword"
                        name="keyword"
                        placeholder="Tìm theo tên linh kiện hoặc nhân viên..."
                        value="<?= eLichSuNguong($tuKhoa) ?>"
                    >

                </div>

            </div>

            <button type="submit" class="btn-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                Tìm kiếm
            </button>

            <a
                href="lich_su_nguong_kho.php"
                class="btn-reset"
                target="mainContent"
            >
                <i class="fa-solid fa-rotate-left"></i>
                Làm mới
            </a>

        </form>

    </div>

    <!-- BẢNG LỊCH SỬ -->

    <div class="panel">

        <h2 class="panel-heading">
            <i class="fa-solid fa-list"></i>
            Danh sách lịch sử điều chỉnh
        </h2>

        <div class="result-summary">

            <p class="result-info">
                <i class="fa-solid fa-circle-info"></i>

                Đang hiển thị
                <strong><?= number_format($batDau) ?> - <?= number_format($ketThuc) ?></strong>
                trên tổng số
                <strong><?= number_format($tongSoBanGhi) ?></strong>
                bản ghi.
            </p>

            <span class="result-total">
                <i class="fa-solid fa-database"></i>
                <?= number_format($tongSoBanGhi) ?> bản ghi
            </span>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Linh kiện</th>
                        <th>Ngưỡng cũ</th>
                        <th>Ngưỡng mới</th>
                        <th>Người thực hiện</th>
                        <th>Thời gian điều chỉnh</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($lichSu)): ?>

                        <tr>
                            <td colspan="6" class="empty">

                                <i class="fa-solid fa-clock"></i>

                                <strong>
                                    Chưa có lịch sử điều chỉnh ngưỡng tồn kho
                                </strong>

                                <span>
                                    Các lần thay đổi ngưỡng cảnh báo sẽ được hiển thị tại đây.
                                </span>

                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($lichSu as $item): ?>

                            <tr>

                                <td>
                                    <span class="history-id">
                                        #<?= (int) $item['id'] ?>
                                    </span>
                                </td>

                                <td>

                                    <span class="part-name">
                                        <?= eLichSuNguong($item['part_name']) ?>
                                    </span>

                                    <span class="part-id">
                                        Mã linh kiện: #<?= (int) $item['part_id'] ?>
                                    </span>

                                </td>

                                <td>
                                    <span class="old-value">
                                        <?= number_format((int) $item['old_min_quantity']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="new-value">
                                        <?= number_format((int) $item['new_min_quantity']) ?>
                                    </span>
                                </td>

                                <td>

                                    <span class="employee-name">

                                        <span class="employee-icon">
                                            <i class="fa-solid fa-user"></i>
                                        </span>

                                        <?= eLichSuNguong($item['changed_by_name']) ?>

                                    </span>

                                </td>

                                <td>

                                    <span class="date-text">
                                        <i class="fa-regular fa-calendar"></i>

                                        <?= eLichSuNguong($item['changed_at']) ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- PHÂN TRANG -->

        <?php if ($tongSoTrang > 1): ?>

            <div class="pagination">

                <?php if ($page > 1): ?>

                    <a
                        href="<?= eLichSuNguong(linkTrangNguong($page - 1, $tuKhoa)) ?>"
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

                <?php for ($i = 1; $i <= $tongSoTrang; $i++): ?>

                    <?php if ($i === $page): ?>

                        <span class="active">
                            <?= $i ?>
                        </span>

                    <?php else: ?>

                        <a
                            href="<?= eLichSuNguong(linkTrangNguong($i, $tuKhoa)) ?>"
                            target="mainContent"
                        >
                            <?= $i ?>
                        </a>

                    <?php endif; ?>

                <?php endfor; ?>

                <?php if ($page < $tongSoTrang): ?>

                    <a
                        href="<?= eLichSuNguong(linkTrangNguong($page + 1, $tuKhoa)) ?>"
                        target="mainContent"
                    >
                        Tiếp
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                <?php else: ?>

                    <span class="disabled">
                        Tiếp
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>

                <?php endif; ?>

            </div>

        <?php endif; ?>

        <p class="pagination-info">
            Trang <?= number_format($page) ?>
            / <?= number_format($tongSoTrang) ?>.
            Hiển thị tối đa <?= $soDongMoiTrang ?> bản ghi mỗi trang.
        </p>

    </div>

</div>

</body>
</html>
