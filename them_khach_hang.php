
<?php

// ======================================================
// 1. KIỂM TRA QUYỀN VÀ KẾT NỐI DATABASE
// ======================================================

require_once __DIR__ . '/includes/auth.php';
requireAdminOrTechnician();

require_once __DIR__ . '/config/database.php';

// ======================================================
// 2. KHỞI TẠO DỮ LIỆU
// ======================================================

$thongBao = '';
$loi = '';

$fullName = '';
$phone = '';
$email = '';
$address = '';

// Hàm hiển thị dữ liệu an toàn
function eCustomerForm($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ======================================================
// 3. XỬ LÝ KHI NGƯỜI DÙNG GỬI FORM
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Lấy dữ liệu từ form
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Kiểm tra họ tên
    if ($fullName === '') {

        $loi = 'Vui lòng nhập họ và tên khách hàng.';

    }

    // Kiểm tra số điện thoại
    elseif ($phone === '') {

        $loi = 'Vui lòng nhập số điện thoại.';

    }

    // Kiểm tra email nếu có nhập
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $loi = 'Địa chỉ email không hợp lệ.';

    }

    else {

        try {

            // Thêm khách hàng vào database
            $sql = "
                INSERT INTO customers
                (full_name, phone, email, address)
                VALUES
                (:full_name, :phone, :email, :address)
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':full_name' => $fullName,
                ':phone' => $phone,
                ':email' => $email !== '' ? $email : null,
                ':address' => $address !== '' ? $address : null
            ]);

            $thongBao = 'Thêm khách hàng thành công!';

            // Xóa dữ liệu form sau khi thêm thành công
            $fullName = '';
            $phone = '';
            $email = '';
            $address = '';

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $loi = 'Không thể thêm khách hàng. Vui lòng thử lại.';

        }

    }
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Thêm khách hàng mới</title>

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
            max-width: 850px;
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
            font-size: 14px;
            color: #64748b;
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
            border: 1px solid #e5eaf2;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 22px 26px;
            border-bottom: 1px solid #edf0f5;
        }

        .card-icon {
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eaf2ff;
            color: #2563eb;
            border-radius: 12px;
            font-size: 20px;
            flex-shrink: 0;
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
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.5;
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
            padding-bottom: 13px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e8edf5;
            color: #1e40af;
            font-size: 15px;
            font-weight: bold;
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
            margin-bottom: 9px;
            color: #334155;
            font-size: 14px;
            font-weight: bold;
        }

        .required {
            color: #dc2626;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            color: #1e293b;
            font-family: Arial, sans-serif;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        input:focus,
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
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
        }

        /* BUTTONS */

        .form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 22px;
            margin-top: 25px;
            border-top: 1px solid #edf0f5;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
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

            .back-button {
                width: 100%;
                justify-content: center;
            }

            .form-footer {
                flex-direction: column-reverse;
            }

            .form-footer .btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="page-container">

    <!-- HEADER -->

    <div class="page-header">

        <div class="page-title">

            <h1>Thêm khách hàng mới</h1>

            <p>Nhập thông tin để tạo hồ sơ khách hàng trong hệ thống</p>

        </div>

        <a href="khach_hang.php" class="back-button">
            <i class="fa-solid fa-arrow-left"></i>
            Quay lại danh sách
        </a>

    </div>

    <!-- FORM CARD -->

    <div class="form-card">

        <div class="card-header">

            <div class="card-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>

            <div>
                <h2>Thông tin khách hàng</h2>
                <p>Vui lòng nhập các thông tin cần thiết bên dưới</p>
            </div>

        </div>

        <div class="form-body">

            <?php if ($thongBao !== ''): ?>

                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php echo eCustomerForm($thongBao); ?>
                </div>

            <?php endif; ?>

            <?php if ($loi !== ''): ?>

                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo eCustomerForm($loi); ?>
                </div>

            <?php endif; ?>

            <form method="POST">

                <!-- SECTION 1 -->

                <div class="form-section">

                    <div class="section-title">
                        <i class="fa-solid fa-address-card"></i>
                        Thông tin cá nhân
                    </div>

                    <div class="form-grid">

                        <div class="form-group full-width">

                            <label>
                                Họ và tên
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                placeholder="Nhập họ và tên khách hàng"
                                value="<?php echo eCustomerForm($fullName); ?>"
                                required
                            >

                            <span class="field-hint">
                                Nhập đầy đủ họ và tên của khách hàng.
                            </span>

                        </div>

                    </div>

                </div>

                <!-- SECTION 2 -->

                <div class="form-section">

                    <div class="section-title">
                        <i class="fa-solid fa-address-book"></i>
                        Thông tin liên hệ
                    </div>

                    <div class="form-grid">

                        <div class="form-group">

                            <label>
                                Số điện thoại
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                name="phone"
                                placeholder="Ví dụ: 0912345678"
                                value="<?php echo eCustomerForm($phone); ?>"
                                required
                            >

                            <span class="field-hint">
                                Số điện thoại dùng để liên hệ với khách hàng.
                            </span>

                        </div>

                        <div class="form-group">

                            <label>Email</label>

                            <input
                                type="email"
                                name="email"
                                placeholder="Ví dụ: khachhang@gmail.com"
                                value="<?php echo eCustomerForm($email); ?>"
                            >

                            <span class="field-hint">
                                Có thể bỏ trống nếu khách hàng chưa cung cấp email.
                            </span>

                        </div>

                        <div class="form-group full-width">

                            <label>Địa chỉ</label>

                            <textarea
                                name="address"
                                placeholder="Nhập địa chỉ của khách hàng..."
                            ><?php echo eCustomerForm($address); ?></textarea>

                            <span class="field-hint">
                                Có thể bỏ trống nếu chưa có thông tin địa chỉ.
                            </span>

                        </div>

                    </div>

                </div>

                <!-- BUTTONS -->

                <div class="form-footer">

                    <a href="khach_hang.php" class="btn btn-secondary">
                        <i class="fa-solid fa-xmark"></i>
                        Hủy
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Thêm khách hàng
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>
