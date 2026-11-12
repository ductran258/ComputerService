
<?php

// ======================================================
// 1. KIỂM TRA QUYỀN VÀ KẾT NỐI DATABASE
// ======================================================

require_once __DIR__ . '/includes/auth.php';
requireAdminOrTechnician();

require_once __DIR__ . '/config/database.php';


// ======================================================
// 2. LẤY TỪ KHÓA TÌM KIẾM, BỘ LỌC VÀ TRANG HIỆN TẠI
// ======================================================

$keyword = trim($_GET['keyword'] ?? '');

$ticketFilter = $_GET['ticket_filter'] ?? 'all';

$allowedFilters = ['all', 'has_tickets', 'no_tickets'];

if (!in_array($ticketFilter, $allowedFilters, true)) {
    $ticketFilter = 'all';
}

$perPage = 10;

$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;

if ($currentPage < 1) {
    $currentPage = 1;
}


// ======================================================
// 3. XÂY DỰNG ĐIỀU KIỆN TÌM KIẾM VÀ LỌC
// ======================================================

$whereSql = " WHERE 1 = 1 ";

$params = [];

if ($keyword !== '') {

    $whereSql .= "
        AND (
            c.full_name LIKE :keyword_name
            OR c.phone LIKE :keyword_phone
        )
    ";

    $params[':keyword_name'] = '%' . $keyword . '%';
    $params[':keyword_phone'] = '%' . $keyword . '%';
}

if ($ticketFilter === 'has_tickets') {

    $whereSql .= "
        AND EXISTS (
            SELECT 1
            FROM repair_tickets rt
            WHERE rt.customer_id = c.id
        )
    ";

} elseif ($ticketFilter === 'no_tickets') {

    $whereSql .= "
        AND NOT EXISTS (
            SELECT 1
            FROM repair_tickets rt
            WHERE rt.customer_id = c.id
        )
    ";
}


// ======================================================
// 4. ĐẾM TỔNG SỐ KHÁCH HÀNG
// ======================================================

$sqlCount = "
    SELECT COUNT(*)
    FROM customers c
    $whereSql
";

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);

$totalCustomers = (int) $stmtCount->fetchColumn();

$totalPages = max(1, (int) ceil($totalCustomers / $perPage));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $perPage;


// ======================================================
// 5. LẤY DANH SÁCH KHÁCH HÀNG
// ======================================================

$sql = "
    SELECT
        c.*,

        (
            SELECT COUNT(*)
            FROM repair_tickets rt
            WHERE rt.customer_id = c.id
        ) AS total_tickets

    FROM customers c

    $whereSql

    ORDER BY c.id DESC

    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 6. HÀM HIỂN THỊ AN TOÀN
// ======================================================

function eCustomer($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


// Giữ lại từ khóa và bộ lọc khi chuyển trang

$paginationParams = [
    'keyword' => $keyword,
    'ticket_filter' => $ticketFilter
];

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quản lý khách hàng</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            padding: 26px;
            margin: 0;
        }

        /* =========================================
           TIÊU ĐỀ TRANG
        ========================================= */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .page-title {
            margin: 0;
            color: #1e293b;
            font-size: 25px;
            font-weight: 700;
        }

        .page-description {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 11px 17px;
            background: #2563eb;
            color: white;
            border-radius: 9px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
            box-shadow: 0 3px 8px rgba(37, 99, 235, 0.15);
        }

        .btn-add:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        /* =========================================
           THẺ THỐNG KÊ
        ========================================= */

        .summary-card {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 19px 21px;
            border-radius: 13px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.035);
            margin-bottom: 20px;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #dbeafe;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .summary-label {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .summary-number {
            color: #1e293b;
            font-size: 23px;
            font-weight: 700;
        }

        /* =========================================
           TÌM KIẾM VÀ BỘ LỌC
        ========================================= */

        .filter-box {
            background: white;
            padding: 20px;
            border-radius: 13px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.035);
            margin-bottom: 20px;
        }

        .filter-heading {
            font-size: 15px;
            font-weight: 700;
            color: #334155;
            margin: 0 0 15px;
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-input,
        .filter-select {
            height: 43px;
            padding: 0 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            background: white;
            color: #334155;
            outline: none;
            transition: 0.2s;
        }

        .search-input {
            flex: 1;
            min-width: 220px;
        }

        .filter-select {
            min-width: 220px;
            cursor: pointer;
        }

        .search-input:focus,
        .filter-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-search {
            background: #2563eb;
            color: white;
            border: none;
        }

        .btn-search:hover {
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

        /* =========================================
           BẢNG KHÁCH HÀNG
        ========================================= */

        .table-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 13px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.035);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
        }

        .table-subtitle {
            margin: 5px 0 0;
            font-size: 12px;
            color: #64748b;
        }

        .result-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
            padding: 15px 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 15px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            font-size: 13px;
            vertical-align: middle;
        }

        tbody tr {
            transition: background 0.15s;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .customer-id {
            color: #64748b;
            font-weight: 600;
        }

        .customer-name {
            color: #1e293b;
            font-weight: 600;
        }

        .customer-phone {
            color: #334155;
            white-space: nowrap;
        }

        .customer-email {
            color: #64748b;
        }

        .customer-address {
            max-width: 220px;
            line-height: 1.5;
        }

        /* =========================================
           SỐ PHIẾU SỬA CHỮA
        ========================================= */

        .ticket-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 29px;
            padding: 0 9px;
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
        }

        .ticket-count.zero {
            background: #f1f5f9;
            color: #64748b;
        }

        /* =========================================
           CÁC NÚT THAO TÁC
        ========================================= */

        .action {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        .action form {
            margin: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 10px;
            border-radius: 7px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            transition: 0.2s;
        }

        .btn-detail {
            background: #dcfce7;
            color: #15803d;
        }

        .btn-detail:hover {
            background: #bbf7d0;
        }

        .btn-edit {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .btn-edit:hover {
            background: #bfdbfe;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #fecaca;
        }

        /* =========================================
           KHÔNG CÓ DỮ LIỆU
        ========================================= */

        .empty {
            text-align: center;
            padding: 45px 20px;
            color: #64748b;
        }

        .empty i {
            display: block;
            font-size: 32px;
            color: #94a3b8;
            margin-bottom: 12px;
        }

        .empty-text {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
        }

        /* =========================================
           PHÂN TRANG
        ========================================= */

        .pagination-container {
            padding: 18px 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .pagination a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            text-align: center;
            text-decoration: none;
            background: white;
            color: #475569;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            font-size: 13px;
            transition: 0.2s;
        }

        .pagination a:hover {
            background: #eff6ff;
            border-color: #2563eb;
            color: #2563eb;
        }

        .pagination a.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
            font-weight: 700;
        }

        .pagination a.disabled {
            pointer-events: none;
            opacity: 0.4;
        }

        .pagination-info {
            color: #64748b;
            font-size: 12px;
        }

        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 768px) {

            body {
                padding: 16px;
            }

            .page-title {
                font-size: 21px;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input,
            .filter-select,
            .btn-search,
            .btn-reset {
                width: 100%;
            }

            .pagination-container {
                flex-direction: column;
                align-items: center;
            }

        }

        @media (max-width: 480px) {

            body {
                padding: 12px;
            }

            .page-header {
                align-items: stretch;
            }

            .btn-add {
                width: 100%;
            }

            .summary-card {
                padding: 15px;
            }

            .filter-box {
                padding: 15px;
            }

            .table-header {
                padding: 15px;
            }

        }

    </style>

</head>

<body>

    <!-- =========================================
         1. TIÊU ĐỀ TRANG
    ========================================== -->

    <div class="page-header">

        <div>

            <h1 class="page-title">
                <i class="fa-solid fa-users" style="color: #2563eb;"></i>
                Danh sách khách hàng
            </h1>

            <p class="page-description">
                Quản lý thông tin và lịch sử sửa chữa của khách hàng.
            </p>

        </div>

        <a href="them_khach_hang.php" class="btn-add">
            <i class="fa-solid fa-plus"></i>
            Thêm khách hàng
        </a>

    </div>


    <!-- =========================================
         2. THỐNG KÊ KHÁCH HÀNG
    ========================================== -->

    <div class="summary-card">

        <div class="summary-icon">
            <i class="fa-solid fa-user-group"></i>
        </div>

        <div>

            <div class="summary-label">
                Số khách hàng tìm thấy
            </div>

            <div class="summary-number">
                <?php echo number_format($totalCustomers); ?>
            </div>

        </div>

    </div>


    <!-- =========================================
         3. TÌM KIẾM VÀ BỘ LỌC
    ========================================== -->

    <div class="filter-box">

        <h3 class="filter-heading">
            <i class="fa-solid fa-filter" style="color: #2563eb;"></i>
            Tìm kiếm và lọc khách hàng
        </h3>

        <form method="GET" action="khach_hang.php" class="filter-form">

            <input
                type="text"
                name="keyword"
                class="search-input"
                placeholder="Nhập tên hoặc số điện thoại khách hàng..."
                value="<?php echo eCustomer($keyword); ?>"
            >

            <select name="ticket_filter" class="filter-select">

                <option
                    value="all"
                    <?php echo $ticketFilter === 'all' ? 'selected' : ''; ?>
                >
                    Tất cả khách hàng
                </option>

                <option
                    value="has_tickets"
                    <?php echo $ticketFilter === 'has_tickets' ? 'selected' : ''; ?>
                >
                    Đã có phiếu sửa chữa
                </option>

                <option
                    value="no_tickets"
                    <?php echo $ticketFilter === 'no_tickets' ? 'selected' : ''; ?>
                >
                    Chưa có phiếu sửa chữa
                </option>

            </select>

            <button type="submit" class="btn-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                Tìm kiếm
            </button>

            <a href="khach_hang.php" class="btn-reset">
                <i class="fa-solid fa-rotate-right"></i>
                Làm mới
            </a>

        </form>

    </div>


    <!-- =========================================
         4. BẢNG DANH SÁCH KHÁCH HÀNG
    ========================================== -->

    <div class="table-card">

        <div class="table-header">

            <div>

                <h3 class="table-title">
                    Danh sách khách hàng
                </h3>

                <p class="table-subtitle">
                    Thông tin khách hàng và số lượng phiếu sửa chữa.
                </p>

            </div>

            <div class="result-count">

                <i class="fa-solid fa-users"></i>

                <?php echo number_format($totalCustomers); ?> khách hàng

            </div>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Họ và tên</th>
                        <th>Số điện thoại</th>
                        <th>Email</th>
                        <th>Địa chỉ</th>
                        <th>Số phiếu sửa chữa</th>
                        <th>Thao tác</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (!empty($customers)): ?>

                        <?php foreach ($customers as $customer): ?>

                            <tr>

                                <td class="customer-id">
                                    #<?php echo (int) $customer['id']; ?>
                                </td>

                                <td class="customer-name">
                                    <?php echo eCustomer($customer['full_name']); ?>
                                </td>

                                <td class="customer-phone">
                                    <i class="fa-solid fa-phone" style="color: #94a3b8;"></i>
                                    <?php echo eCustomer($customer['phone']); ?>
                                </td>

                                <td class="customer-email">
                                    <?php
                                    echo eCustomer($customer['email'] ?? '');
                                    ?>
                                </td>

                                <td class="customer-address">
                                    <?php
                                    echo eCustomer($customer['address'] ?? '');
                                    ?>
                                </td>

                                <td>

                                    <span class="ticket-count <?php echo (int) $customer['total_tickets'] === 0 ? 'zero' : ''; ?>">

                                        <?php
                                        echo (int) $customer['total_tickets'];
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <div class="action">

                                        <!-- CHI TIẾT -->

                                        <a
                                            class="btn btn-detail"
                                            href="chi_tiet_khach_hang.php?id=<?php echo (int) $customer['id']; ?>"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                            Chi tiết
                                        </a>


                                        <!-- SỬA -->

                                        <a
                                            class="btn btn-edit"
                                            href="sua_khach_hang.php?id=<?php echo (int) $customer['id']; ?>"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                            Sửa
                                        </a>


                                        <!-- XÓA -->

                                        <form
                                            action="xoa_khach_hang.php"
                                            method="POST"
                                            onsubmit="return confirm('Bạn có chắc chắn muốn xóa khách hàng này không?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int) $customer['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-delete"
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

                            <td colspan="7" class="empty">

                                <i class="fa-solid fa-folder-open"></i>

                                <div class="empty-text">
                                    Không tìm thấy khách hàng phù hợp.
                                </div>

                                <p>
                                    Hãy thử thay đổi từ khóa hoặc bộ lọc tìm kiếm.
                                </p>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- =========================================
             5. PHÂN TRANG
        ========================================== -->

        <div class="pagination-container">

            <div class="pagination-info">

                Trang <?php echo $currentPage; ?>
                / <?php echo $totalPages; ?>

                — Hiển thị <?php echo count($customers); ?>
                / <?php echo $totalCustomers; ?> khách hàng

            </div>


            <?php if ($totalPages > 1): ?>

                <div class="pagination">

                    <!-- TRANG TRƯỚC -->

                    <?php
                    $previousParams = $paginationParams;
                    $previousParams['page'] = $currentPage - 1;
                    ?>

                    <a
                        href="?<?php echo eCustomer(http_build_query($previousParams)); ?>"
                        class="<?php echo $currentPage <= 1 ? 'disabled' : ''; ?>"
                        title="Trang trước"
                    >
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>


                    <!-- CÁC SỐ TRANG -->

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                        <?php
                        $pageParams = $paginationParams;
                        $pageParams['page'] = $i;
                        ?>

                        <a
                            href="?<?php echo eCustomer(http_build_query($pageParams)); ?>"
                            class="<?php echo $currentPage === $i ? 'active' : ''; ?>"
                        >
                            <?php echo $i; ?>
                        </a>

                    <?php endfor; ?>


                    <!-- TRANG SAU -->

                    <?php
                    $nextParams = $paginationParams;
                    $nextParams['page'] = $currentPage + 1;
                    ?>

                    <a
                        href="?<?php echo eCustomer(http_build_query($nextParams)); ?>"
                        class="<?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>"
                        title="Trang sau"
                    >
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

</body>

</html>
