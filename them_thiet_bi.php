
<?php

// ======================================================
// 1. KẾT NỐI DATABASE
// ======================================================

require_once __DIR__ . '/config/database.php';

$thongBao = '';
$loi = '';

// Hàm hiển thị dữ liệu an toàn
function eDeviceForm($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ======================================================
// 2. LẤY DANH SÁCH KHÁCH HÀNG
// ======================================================

$sqlCustomers = "
    SELECT id, full_name, phone
    FROM customers
    ORDER BY full_name ASC
";

$stmtCustomers = $pdo->prepare($sqlCustomers);
$stmtCustomers->execute();

$customers = $stmtCustomers->fetchAll();

// ======================================================
// 3. XỬ LÝ KHI NGƯỜI DÙNG GỬI FORM
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customerId = filter_input(
        INPUT_POST,
        'customer_id',
        FILTER_VALIDATE_INT
    );

    $deviceType = trim($_POST['device_type'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // ==================================================
    // 4. KIỂM TRA DỮ LIỆU
    // ==================================================

    if (!$customerId || $customerId <= 0) {

        $loi = 'Vui lòng chọn khách hàng.';

    } elseif ($deviceType === '') {

        $loi = 'Vui lòng nhập loại thiết bị.';

    } else {

        try {

            // ==========================================
            // 5. KIỂM TRA KHÁCH HÀNG CÓ TỒN TẠI KHÔNG
            // ==========================================

            $sqlCheck = "
                SELECT id
                FROM customers
                WHERE id = :customer_id
            ";

            $stmtCheck = $pdo->prepare($sqlCheck);

            $stmtCheck->execute([
                ':customer_id' => $customerId
            ]);

            if (!$stmtCheck->fetch()) {

                $loi = 'Khách hàng không tồn tại.';

            } else {

                // ======================================
                // 6. THÊM THIẾT BỊ VÀO DATABASE
                // ======================================

                $sqlInsert = "
                    INSERT INTO devices (
                        customer_id,
                        device_type,
                        brand,
                        model,
                        serial_number,
                        description
                    )
                    VALUES (
                        :customer_id,
                        :device_type,
                        :brand,
                        :model,
                        :serial_number,
                        :description
                    )
                ";

                $stmtInsert = $pdo->prepare($sqlInsert);

                $stmtInsert->execute([
                    ':customer_id' => $customerId,
                    ':device_type' => $deviceType,
                    ':brand' => $brand !== '' ? $brand : null,
                    ':model' => $model !== '' ? $model : null,
                    ':serial_number' => $serialNumber !== '' ? $serialNumber : null,
                    ':description' => $description !== '' ? $description : null
                ]);

                $thongBao = 'Thêm thiết bị thành công!';

                // Xóa dữ liệu form sau khi thêm thành công
                $_POST = [];
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $loi = 'Đã xảy ra lỗi khi thêm thiết bị.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Thêm thiết bị mới</title>

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
            padding: 30px;
        }

        .page-container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .page-title h1 {
            font-size: 26px;
            color: #172554;
            margin-bottom: 7px;
        }

        .page-title p {
            color: #64748b;
            font-size: 14px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            background: #ffffff;
            color: #334155;
            text-decoration: none;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            transition: 0.2s;
        }

        .back-button:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        /* FORM CARD */

        .form-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e5eaf2;
            box-shadow: 0 5px 20px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .card-header {
            padding: 22px 26px;
            border-bottom: 1px solid #edf0f5;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .card-icon {
            width: 46px;
            height: 46px;
            background: #eaf2ff;
            color: #2563eb;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .card-header h2 {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .card-header p {
            font-size: 13px;
            color: #64748b;
        }

        .form-body {
            padding: 26px;
        }

        /* ALERT */

        .alert {
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* FORM SECTION */

        .form-section {
            margin-bottom: 28px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: bold;
            color: #1e40af;
            padding-bottom: 13px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e8edf5;
        }

        .section-title i {
            font-size: 16px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            color: #334155;
            margin-bottom: 9px;
        }

        .required {
            color: #dc2626;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            font-family: Arial, sans-serif;
            color: #1e293b;
            background: #ffffff;
            outline: none;
            transition: 0.2s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        input::placeholder,
        textarea::placeholder {
            color: #94a3b8;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }

        .field-hint {
            display: block;
            margin-top: 7px;
            font-size: 12px;
            color: #94a3b8;
        }

        /* EMPTY CUSTOMER */

        .empty-customer {
            padding: 20px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.6;
        }

        /* BUTTONS */

        .form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 22px;
            border-top: 1px solid #edf0f5;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: 0.2s;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* RESPONSIVE */

        @media (max-width: 650px) {

            body {
                padding: 15px;
            }

            .page-title h1 {
                font-size: 22px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 17px;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .card-header,
            .form-body {
                padding: 20px;
            }

            .form-footer {
                flex-direction: column-reverse;
            }

            .form-footer .btn {
                width: 100%;
            }

            .back-button {
                width: 100%;
                justify-content: center;
            }
        }

    </style>

</head>

<body>

<div class="page-container">

    <!-- HEADER -->

    <div class="page-header">

        <div class="page-title">

            <h1>Thêm thiết bị mới</h1>

            <p>Nhập thông tin thiết bị và khách hàng sở hữu</p>

        </div>

        <a href="thiet_bi.php" class="back-button">
            <i class="fa-solid fa-arrow-left"></i>
            Quay lại danh sách
        </a>

    </div>

    <!-- FORM CARD -->

    <div class="form-card">

        <div class="card-header">

            <div class="card-icon">
                <i class="fa-solid fa-laptop"></i>
            </div>

            <div>
                <h2>Thông tin thiết bị</h2>
                <p>Vui lòng nhập đầy đủ các thông tin cần thiết</p>
            </div>

        </div>

        <div class="form-body">

            <?php if ($thongBao !== ''): ?>

                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>

                    <?php echo eDeviceForm($thongBao); ?>
                </div>

            <?php endif; ?>

            <?php if ($loi !== ''): ?>

                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>

                    <?php echo eDeviceForm($loi); ?>
                </div>

            <?php endif; ?>

            <?php if (count($customers) > 0): ?>

                <form method="POST">

                    <!-- SECTION 1 -->

                    <div class="form-section">

                        <div class="section-title">
                            <i class="fa-solid fa-user"></i>
                            Thông tin khách hàng
                        </div>

                        <div class="form-grid">

                            <div class="form-group full-width">

                                <label>
                                    Khách hàng sở hữu
                                    <span class="required">*</span>
                                </label>

                                <select name="customer_id" required>

                                    <option value="">
                                        -- Chọn khách hàng --
                                    </option>

                                    <?php foreach ($customers as $customer): ?>

                                        <option
                                            value="<?php echo (int) $customer['id']; ?>"
                                            <?php
                                            echo (
                                                isset($_POST['customer_id'])
                                                && (int) $_POST['customer_id'] === (int) $customer['id']
                                            ) ? 'selected' : '';
                                            ?>
                                        >
                                            <?php
                                            echo eDeviceForm(
                                                $customer['full_name'] . ' - ' . $customer['phone']
                                            );
                                            ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <span class="field-hint">
                                    Chọn khách hàng đang sở hữu thiết bị này.
                                </span>

                            </div>

                        </div>

                    </div>

                    <!-- SECTION 2 -->

                    <div class="form-section">

                        <div class="section-title">
                            <i class="fa-solid fa-computer"></i>
                            Thông tin nhận dạng thiết bị
                        </div>

                        <div class="form-grid">

                            <div class="form-group">

                                <label>
                                    Loại thiết bị
                                    <span class="required">*</span>
                                </label>

                                <select name="device_type" required>

                                    <option value="">
                                        -- Chọn loại thiết bị --
                                    </option>

                                    <option value="Laptop" <?php echo (($_POST['device_type'] ?? '') === 'Laptop') ? 'selected' : ''; ?>>
                                        Laptop
                                    </option>

                                    <option value="PC" <?php echo (($_POST['device_type'] ?? '') === 'PC') ? 'selected' : ''; ?>>
                                        PC
                                    </option>

                                    <option value="Máy in" <?php echo (($_POST['device_type'] ?? '') === 'Máy in') ? 'selected' : ''; ?>>
                                        Máy in
                                    </option>

                                    <option value="Màn hình" <?php echo (($_POST['device_type'] ?? '') === 'Màn hình') ? 'selected' : ''; ?>>
                                        Màn hình
                                    </option>

                                    <option value="Khác" <?php echo (($_POST['device_type'] ?? '') === 'Khác') ? 'selected' : ''; ?>>
                                        Khác
                                    </option>

                                </select>

                            </div>

                            <div class="form-group">

                                <label>Thương hiệu</label>

                                <input
                                    type="text"
                                    name="brand"
                                    placeholder="Ví dụ: Dell, HP, Asus..."
                                    value="<?php echo eDeviceForm($_POST['brand'] ?? ''); ?>"
                                >

                            </div>

                            <div class="form-group">

                                <label>Model</label>

                                <input
                                    type="text"
                                    name="model"
                                    placeholder="Ví dụ: Inspiron 15"
                                    value="<?php echo eDeviceForm($_POST['model'] ?? ''); ?>"
                                >

                            </div>

                            <div class="form-group">

                                <label>Số Serial</label>

                                <input
                                    type="text"
                                    name="serial_number"
                                    placeholder="Nhập số Serial của thiết bị"
                                    value="<?php echo eDeviceForm($_POST['serial_number'] ?? ''); ?>"
                                >

                            </div>

                        </div>

                    </div>

                    <!-- SECTION 3 -->

                    <div class="form-section">

                        <div class="section-title">
                            <i class="fa-solid fa-clipboard-list"></i>
                            Mô tả thiết bị
                        </div>

                        <div class="form-grid">

                            <div class="form-group full-width">

                                <label>Mô tả tình trạng thiết bị</label>

                                <textarea
                                    name="description"
                                    placeholder="Ví dụ: Máy khởi động chậm, thường xuyên bị treo..."
                                ><?php echo eDeviceForm($_POST['description'] ?? ''); ?></textarea>

                                <span class="field-hint">
                                    Có thể ghi chú tình trạng hiện tại hoặc đặc điểm của thiết bị.
                                </span>

                            </div>

                        </div>

                    </div>

                    <!-- BUTTONS -->

                    <div class="form-footer">

                        <a href="thiet_bi.php" class="btn btn-secondary">
                            <i class="fa-solid fa-xmark"></i>
                            Hủy
                        </a>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Lưu thiết bị
                        </button>

                    </div>

                </form>

            <?php else: ?>

                <div class="empty-customer">

                    <i class="fa-solid fa-circle-info"></i>

                    Hiện chưa có khách hàng. Bạn cần thêm khách hàng trước khi tạo thiết bị.

                    <br>

                    <a href="them_khach_hang.php">
                        Thêm khách hàng mới
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>
</html>
