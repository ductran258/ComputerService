
<?php

// ======================================================
// 1. KẾT NỐI DATABASE
// ======================================================

require_once __DIR__ . '/config/database.php';


// ======================================================
// 2. TRUY VẤN DANH SÁCH THIẾT BỊ
// ======================================================

$sql = "
    SELECT
        devices.id,
        devices.customer_id,
        devices.device_type,
        devices.brand,
        devices.model,
        devices.serial_number,
        devices.description,
        devices.created_at,
        customers.full_name AS customer_name,
        customers.phone AS customer_phone
    FROM devices
    INNER JOIN customers
        ON devices.customer_id = customers.id
    ORDER BY devices.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 3. HÀM HIỂN THỊ AN TOÀN
// ======================================================

function eDevice($value): string
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

    <title>Quản lý thiết bị</title>

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
           BẢNG DANH SÁCH THIẾT BỊ
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
            min-width: 1100px;
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

        /* =========================================
           THÔNG TIN THIẾT BỊ
        ========================================= */

        .device-id {
            color: #64748b;
            font-weight: 600;
        }

        .device-type {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 9px;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 7px;
            font-weight: 600;
            white-space: nowrap;
        }

        .device-brand {
            color: #1e293b;
            font-weight: 600;
        }

        .device-model {
            color: #475569;
        }

        .device-serial {
            color: #64748b;
            font-family: Consolas, monospace;
            font-size: 12px;
        }

        .customer-name {
            color: #1e293b;
            font-weight: 600;
        }

        .customer-phone {
            color: #64748b;
            white-space: nowrap;
        }

        .device-description {
            max-width: 200px;
            line-height: 1.5;
            color: #64748b;
        }

        /* =========================================
           NÚT THAO TÁC
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
            padding: 8px 11px;
            border-radius: 7px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            transition: 0.2s;
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

        .empty-description {
            margin-top: 7px;
            font-size: 12px;
            color: #94a3b8;
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
                <i class="fa-solid fa-laptop" style="color: #2563eb;"></i>
                Danh sách thiết bị
            </h1>

            <p class="page-description">
                Quản lý thông tin thiết bị và khách hàng sở hữu.
            </p>

        </div>

        <a href="them_thiet_bi.php" class="btn-add">

            <i class="fa-solid fa-plus"></i>

            Thêm thiết bị mới

        </a>

    </div>


    <!-- =========================================
         2. THỐNG KÊ THIẾT BỊ
    ========================================== -->

    <div class="summary-card">

        <div class="summary-icon">
            <i class="fa-solid fa-computer"></i>
        </div>

        <div>

            <div class="summary-label">
                Tổng số thiết bị
            </div>

            <div class="summary-number">
                <?php echo number_format(count($devices)); ?>
            </div>

        </div>

    </div>


    <!-- =========================================
         3. BẢNG DANH SÁCH THIẾT BỊ
    ========================================== -->

    <div class="table-card">

        <div class="table-header">

            <div>

                <h3 class="table-title">
                    Danh sách thiết bị
                </h3>

                <p class="table-subtitle">
                    Thông tin thiết bị, số serial và khách hàng sở hữu.
                </p>

            </div>

            <div class="result-count">

                <i class="fa-solid fa-computer"></i>

                <?php echo number_format(count($devices)); ?> thiết bị

            </div>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Khách hàng</th>
                        <th>Số điện thoại</th>
                        <th>Loại thiết bị</th>
                        <th>Thương hiệu</th>
                        <th>Model</th>
                        <th>Số Serial</th>
                        <th>Mô tả</th>
                        <th>Thao tác</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (count($devices) > 0): ?>

                        <?php foreach ($devices as $device): ?>

                            <tr>

                                <td class="device-id">
                                    #<?php echo (int) $device['id']; ?>
                                </td>

                                <td class="customer-name">
                                    <?php
                                    echo eDevice($device['customer_name']);
                                    ?>
                                </td>

                                <td class="customer-phone">

                                    <i
                                        class="fa-solid fa-phone"
                                        style="color: #94a3b8;"
                                    ></i>

                                    <?php
                                    echo eDevice($device['customer_phone']);
                                    ?>

                                </td>

                                <td>

                                    <span class="device-type">

                                        <i class="fa-solid fa-desktop"></i>

                                        <?php
                                        echo eDevice($device['device_type']);
                                        ?>

                                    </span>

                                </td>

                                <td class="device-brand">
                                    <?php
                                    echo eDevice($device['brand'] ?? '');
                                    ?>
                                </td>

                                <td class="device-model">
                                    <?php
                                    echo eDevice($device['model'] ?? '');
                                    ?>
                                </td>

                                <td class="device-serial">
                                    <?php
                                    echo eDevice($device['serial_number'] ?? '');
                                    ?>
                                </td>

                                <td class="device-description">
                                    <?php
                                    echo eDevice($device['description'] ?? '');
                                    ?>
                                </td>

                                <td>

                                    <div class="action">

                                        <!-- SỬA THIẾT BỊ -->

                                        <a
                                            class="btn btn-edit"
                                            href="sua_thiet_bi.php?id=<?php echo (int) $device['id']; ?>"
                                        >

                                            <i class="fa-solid fa-pen"></i>

                                            Sửa

                                        </a>


                                        <!-- XÓA THIẾT BỊ -->

                                        <form
                                            action="xoa_thiet_bi.php"
                                            method="POST"
                                            onsubmit="return confirm('Bạn có chắc chắn muốn xóa thiết bị này không?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int) $device['id']; ?>"
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

                            <td colspan="9" class="empty">

                                <i class="fa-solid fa-computer"></i>

                                <div class="empty-text">
                                    Chưa có thiết bị nào.
                                </div>

                                <div class="empty-description">
                                    Hãy thêm thiết bị mới để quản lý trong hệ thống.
                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</body>

</html>
