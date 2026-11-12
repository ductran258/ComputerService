
<?php

require_once __DIR__ . '/config/database.php';

$loi = '';
$thongBao = '';

// 1. DANH SACH DANH MUC LINH KIEN
$categoryLabels = [
    'storage' => 'Ổ cứng',
    'ram' => 'RAM',
    'cpu' => 'CPU',
    'gpu' => 'Card đồ họa',
    'accessory' => 'Phụ kiện',
    'other' => 'Khác'
];

// 2. XU LY KHI NGUOI DUNG GUI FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $partName = trim($_POST['part_name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category = $_POST['category'] ?? '';
    $quantityInput = $_POST['quantity'] ?? '';
    $importPriceInput = $_POST['import_price'] ?? '';
    $sellingPriceInput = $_POST['selling_price'] ?? '';

    // 3. KIEM TRA DU LIEU
    if (
        $partName === '' ||
        $category === '' ||
        $quantityInput === '' ||
        $importPriceInput === '' ||
        $sellingPriceInput === ''
    ) {
        $loi = 'Vui lòng nhập đầy đủ các thông tin bắt buộc.';
    } elseif (!array_key_exists($category, $categoryLabels)) {
        $loi = 'Danh mục linh kiện không hợp lệ.';
    } elseif (
        filter_var($quantityInput, FILTER_VALIDATE_INT) === false ||
        (int) $quantityInput < 0
    ) {
        $loi = 'Số lượng phải là số nguyên không âm.';
    } elseif (
        !is_numeric($importPriceInput) ||
        !is_numeric($sellingPriceInput) ||
        (float) $importPriceInput < 0 ||
        (float) $sellingPriceInput < 0
    ) {
        $loi = 'Giá nhập và giá bán phải là số không âm.';
    } else {

        $quantity = (int) $quantityInput;
        $importPrice = (float) $importPriceInput;
        $sellingPrice = (float) $sellingPriceInput;

        try {

            // 4. KIEM TRA SKU CO BI TRUNG KHONG
            if ($sku !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM parts
                    WHERE sku = :sku
                    LIMIT 1
                ");

                $stmt->execute([
                    ':sku' => $sku
                ]);

                if ($stmt->fetch()) {
                    $loi = 'Mã SKU đã tồn tại. Vui lòng nhập mã khác.';
                }
            }

            // 5. THEM LINH KIEN VAO DATABASE
            if ($loi === '') {

                $stmt = $pdo->prepare("
                    INSERT INTO parts (
                        part_name,
                        sku,
                        category,
                        quantity,
                        import_price,
                        selling_price,
                        created_at
                    )
                    VALUES (
                        :part_name,
                        :sku,
                        :category,
                        :quantity,
                        :import_price,
                        :selling_price,
                        NOW()
                    )
                ");

                $stmt->execute([
                    ':part_name' => $partName,
                    ':sku' => $sku !== '' ? $sku : null,
                    ':category' => $category,
                    ':quantity' => $quantity,
                    ':import_price' => $importPrice,
                    ':selling_price' => $sellingPrice
                ]);

                $thongBao = 'Thêm linh kiện thành công!';

                // XOA DU LIEU FORM SAU KHI THEM THANH CONG
                $_POST = [];
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $loi = 'Không thể thêm linh kiện. Vui lòng kiểm tra lại dữ liệu.';
        }
    }
}

// 6. HAM BAO VE DU LIEU HIEN THI
function e($value)
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

    <title>Thêm linh kiện mới</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 28px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1e293b;
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
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .page-title {
            margin: 0;
            font-size: 25px;
            font-weight: 700;
            color: #172554;
        }

        .page-subtitle {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            border: 1px solid #dbe3ef;
            border-radius: 9px;
            background: #ffffff;
            color: #334155;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
        }

        .back-button:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        /* FORM CARD */

        .form-card {
            background: #ffffff;
            border: 1px solid #e5eaf2;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
        }

        .card-heading {
            display: flex;
            align-items: center;
            gap: 13px;
            padding-bottom: 20px;
            margin-bottom: 22px;
            border-bottom: 1px solid #edf1f7;
        }

        .heading-icon {
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 20px;
        }

        .card-heading h2 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
        }

        .card-heading p {
            margin: 5px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        /* NOTIFICATION */

        .notification {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.5;
        }

        .notification.success {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .notification.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        /* FORM FIELDS */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 9px;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        .required {
            color: #dc2626;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            pointer-events: none;
        }

        input,
        select {
            width: 100%;
            height: 45px;
            padding: 0 13px 0 40px;
            border: 1px solid #dbe3ef;
            border-radius: 9px;
            background: #ffffff;
            color: #1e293b;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        input:focus,
        select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        input::placeholder {
            color: #a0aec0;
        }

        select {
            cursor: pointer;
        }

        .note {
            margin: 8px 0 0;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
        }

        /* SECTION */

        .form-section {
            margin-top: 28px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0 0 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #edf1f7;
            color: #1e293b;
            font-size: 15px;
            font-weight: 700;
        }

        .section-title i {
            color: #2563eb;
        }

        /* BUTTONS */

        .form-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            margin-top: 30px;
            padding-top: 22px;
            border-top: 1px solid #edf1f7;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 11px 19px;
            border: none;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* RESPONSIVE */

        @media (max-width: 650px) {

            body {
                padding: 16px;
            }

            .form-card {
                padding: 20px 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .page-title {
                font-size: 21px;
            }

            .form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .form-actions .btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="page-container">

    <!-- HEADER -->

    <div class="page-header">

        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-box-open"></i>
                Thêm linh kiện mới
            </h1>

            <p class="page-subtitle">
                Nhập thông tin linh kiện để thêm vào hệ thống quản lý kho.
            </p>
        </div>

        <a href="linh_kien.php"
           class="back-button"
           target="mainContent">

            <i class="fa-solid fa-arrow-left"></i>
            Quay lại kho linh kiện

        </a>

    </div>

    <!-- FORM CARD -->

    <div class="form-card">

        <div class="card-heading">

            <div class="heading-icon">
                <i class="fa-solid fa-cubes"></i>
            </div>

            <div>
                <h2>Thông tin linh kiện</h2>

                <p>
                    Các trường có dấu (*) là thông tin bắt buộc.
                </p>
            </div>

        </div>

        <!-- THONG BAO -->

        <?php if ($thongBao !== ''): ?>

            <div class="notification success">

                <i class="fa-solid fa-circle-check"></i>

                <span><?php echo e($thongBao); ?></span>

            </div>

        <?php endif; ?>

        <?php if ($loi !== ''): ?>

            <div class="notification error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <span><?php echo e($loi); ?></span>

            </div>

        <?php endif; ?>

        <!-- FORM -->

        <form method="POST">

            <!-- THONG TIN CO BAN -->

            <div class="form-section">

                <h3 class="section-title">
                    <i class="fa-solid fa-circle-info"></i>
                    Thông tin cơ bản
                </h3>

                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            Tên linh kiện <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">

                            <i class="fa-solid fa-cube"></i>

                            <input
                                type="text"
                                name="part_name"
                                required
                                value="<?php echo e($_POST['part_name'] ?? ''); ?>"
                                placeholder="Ví dụ: RAM DDR4 8GB"
                            >

                        </div>

                    </div>

                    <div class="form-group">

                        <label>Mã SKU</label>

                        <div class="input-wrapper">

                            <i class="fa-solid fa-barcode"></i>

                            <input
                                type="text"
                                name="sku"
                                value="<?php echo e($_POST['sku'] ?? ''); ?>"
                                placeholder="Ví dụ: RAM8GB-001"
                            >

                        </div>

                        <p class="note">
                            Không bắt buộc. Nếu nhập, mã SKU không được trùng.
                        </p>

                    </div>

                    <div class="form-group full-width">

                        <label>
                            Danh mục linh kiện <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">

                            <i class="fa-solid fa-layer-group"></i>

                            <select name="category" required>

                                <option value="">-- Chọn danh mục --</option>

                                <?php foreach ($categoryLabels as $value => $label): ?>

                                    <option
                                        value="<?php echo e($value); ?>"
                                        <?php echo (($_POST['category'] ?? '') === $value) ? 'selected' : ''; ?>
                                    >
                                        <?php echo e($label); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                </div>

            </div>

            <!-- THONG TIN KHO -->

            <div class="form-section">

                <h3 class="section-title">
                    <i class="fa-solid fa-warehouse"></i>
                    Thông tin kho và giá
                </h3>

                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            Số lượng tồn kho <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">

                            <i class="fa-solid fa-boxes-stacked"></i>

                            <input
                                type="number"
                                name="quantity"
                                min="0"
                                step="1"
                                required
                                value="<?php echo e($_POST['quantity'] ?? ''); ?>"
                                placeholder="Ví dụ: 20"
                            >

                        </div>

                        <p class="note">
                            Nhập số lượng linh kiện hiện có ban đầu.
                        </p>

                    </div>

                    <div class="form-group">

                        <label>
                            Giá nhập (VNĐ) <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">

                            <i class="fa-solid fa-money-bill-transfer"></i>

                            <input
                                type="number"
                                name="import_price"
                                min="0"
                                step="1"
                                required
                                value="<?php echo e($_POST['import_price'] ?? ''); ?>"
                                placeholder="Ví dụ: 350000"
                            >

                        </div>

                    </div>

                    <div class="form-group">

                        <label>
                            Giá bán (VNĐ) <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">

                            <i class="fa-solid fa-tags"></i>

                            <input
                                type="number"
                                name="selling_price"
                                min="0"
                                step="1"
                                required
                                value="<?php echo e($_POST['selling_price'] ?? ''); ?>"
                                placeholder="Ví dụ: 450000"
                            >

                        </div>

                    </div>

                </div>

            </div>

            <!-- BUTTONS -->

            <div class="form-actions">

                <a href="linh_kien.php"
                   class="btn btn-secondary"
                   target="mainContent">

                    <i class="fa-solid fa-xmark"></i>
                    Hủy

                </a>

                <button type="submit" class="btn btn-primary">

                    <i class="fa-solid fa-floppy-disk"></i>
                    Lưu linh kiện

                </button>

            </div>

        </form>

    </div>

</div>

</body>
</html>
