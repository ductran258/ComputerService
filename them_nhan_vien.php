
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// =====================================================
// 1. KHOI TAO DU LIEU
// =====================================================

$loi = '';
$thongBao = '';

$fullName = '';
$username = '';
$email = '';
$phone = '';
$role = 'technician';

// =====================================================
// 2. XU LY KHI GUI FORM
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $role = $_POST['role'] ?? 'technician';

    // KIEM TRA DU LIEU

    if ($fullName === '' || $username === '' || $password === '') {

        $loi = 'Vui lòng nhập đầy đủ họ tên, tên đăng nhập và mật khẩu.';

    } elseif (mb_strlen($fullName) > 100) {

        $loi = 'Họ tên không được vượt quá 100 ký tự.';

    } elseif (mb_strlen($username) > 50) {

        $loi = 'Tên đăng nhập không được vượt quá 50 ký tự.';

    } elseif (
        $email !== '' &&
        (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100)
    ) {

        $loi = 'Email không hợp lệ hoặc vượt quá 100 ký tự.';

    } elseif (mb_strlen($phone) > 20) {

        $loi = 'Số điện thoại không được vượt quá 20 ký tự.';

    } elseif (strlen($password) < 8) {

        $loi = 'Mật khẩu phải có ít nhất 8 ký tự.';

    } elseif ($password !== $confirmPassword) {

        $loi = 'Mật khẩu xác nhận không khớp.';

    } elseif (!in_array($role, ['admin', 'technician'], true)) {

        $loi = 'Vai trò không hợp lệ.';

    } else {

        // KIEM TRA TEN DANG NHAP DA TON TAI

        $check = $pdo->prepare(
            "SELECT id FROM users WHERE username = :username LIMIT 1"
        );

        $check->execute([
            'username' => $username
        ]);

        if ($check->fetch()) {

            $loi = 'Tên đăng nhập này đã tồn tại. Vui lòng chọn tên khác.';

        } else {

            // MA HOA MAT KHAU

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            try {

                $sql = "
                    INSERT INTO users
                        (full_name, username, email, password, role, phone)
                    VALUES
                        (:full_name, :username, :email, :password, :role, :phone)
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    'full_name' => $fullName,
                    'username' => $username,
                    'email' => $email !== '' ? $email : null,
                    'password' => $passwordHash,
                    'role' => $role,
                    'phone' => $phone !== '' ? $phone : null
                ]);

                $thongBao = 'Tạo tài khoản nhân viên thành công!';

                // XOA DU LIEU FORM SAU KHI TAO THANH CONG

                $fullName = '';
                $username = '';
                $email = '';
                $phone = '';
                $role = 'technician';

            } catch (PDOException $e) {

                error_log($e->getMessage());

                $loi = 'Không thể tạo tài khoản. Vui lòng kiểm tra lại dữ liệu.';

            }

        }

    }

}

// =====================================================
// 3. HAM ESCAPE DU LIEU
// =====================================================

function eThemNhanVien($value): string
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

    <title>Thêm nhân viên mới</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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

        .page-container {
            max-width: 850px;
            margin: auto;
        }

        /* HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 23px;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: #dbeafe;
            color: #2563eb;
            font-size: 22px;
        }

        .header-title h1 {
            margin: 0 0 6px;
            color: #172554;
            font-size: 25px;
        }

        .header-title p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 15px;
            border: 1px solid #dbe2ea;
            border-radius: 9px;
            background: #fff;
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-back:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* FORM CARD */

        .form-card {
            overflow: hidden;
            background: #fff;
            border: 1px solid #e5eaf1;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
        }

        .card-header {
            padding: 21px 24px;
            border-bottom: 1px solid #edf0f5;
        }

        .card-header h2 {
            margin: 0;
            color: #1e293b;
            font-size: 16px;
        }

        .card-header p {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .card-body {
            padding: 25px;
        }

        /* SECTION */

        .form-section {
            margin-bottom: 25px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 17px;
            padding-bottom: 11px;
            border-bottom: 1px solid #edf0f5;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .section-title i {
            color: #2563eb;
        }

        /* FORM INPUT */

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            min-width: 0;
            margin-bottom: 17px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
        }

        .required {
            color: #dc2626;
        }

        .form-control {
            width: 100%;
            min-height: 44px;
            padding: 11px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            background: #fff;
            color: #1e293b;
            font-family: inherit;
            font-size: 13px;
            transition: 0.2s;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .form-hint {
            display: block;
            margin-top: 7px;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.5;
        }

        /* PASSWORD */

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 13px;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            font-size: 14px;
        }

        .toggle-password:hover {
            color: #2563eb;
        }

        /* ROLE INFO */

        .role-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 13px;
            padding: 12px 14px;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #eff6ff;
            color: #475569;
            font-size: 11px;
            line-height: 1.6;
        }

        .role-note i {
            margin-top: 2px;
            color: #2563eb;
        }

        /* ALERT */

        .message {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 9px;
            font-size: 13px;
            line-height: 1.6;
        }

        .message i {
            margin-top: 2px;
        }

        .message.error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
        }

        .message.success {
            border: 1px solid #bbf7d0;
            background: #dcfce7;
            color: #166534;
        }

        .success-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 8px;
            color: #166534;
            font-weight: 700;
            text-decoration: underline;
        }

        /* ACTIONS */

        .form-actions {
            display: flex;
            align-items: center;
            gap: 11px;
            padding-top: 21px;
            border-top: 1px solid #edf0f5;
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 44px;
            padding: 0 21px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-submit:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
        }

        .btn-submit:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            background: #fff;
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-cancel:hover {
            background: #f8fafc;
        }

        /* RESPONSIVE */

        @media (max-width: 600px) {

            body {
                padding: 13px;
            }

            .header-title h1 {
                font-size: 21px;
            }

            .header-icon {
                width: 45px;
                height: 45px;
            }

            .card-header {
                padding: 17px;
            }

            .card-body {
                padding: 17px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-submit,
            .btn-cancel {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="page-container">

    <!-- HEADER -->

    <div class="page-header">

        <div class="header-title">

            <div class="header-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>

            <div>
                <h1>Thêm nhân viên mới</h1>
                <p>Tạo tài khoản đăng nhập cho nhân viên trong hệ thống</p>
            </div>

        </div>

        <a href="nhan_vien.php"
           class="btn-back"
           target="mainContent">

            <i class="fa-solid fa-arrow-left"></i>
            Quay lại

        </a>

    </div>

    <!-- FORM CARD -->

    <div class="form-card">

        <div class="card-header">

            <h2>
                <i class="fa-solid fa-address-card"></i>
                Thông tin tài khoản
            </h2>

            <p>
                Nhập đầy đủ thông tin cần thiết để tạo tài khoản nhân viên mới.
            </p>

        </div>

        <div class="card-body">

            <!-- THONG BAO LOI -->

            <?php if ($loi !== ''): ?>

                <div class="message error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>
                        <?= eThemNhanVien($loi) ?>
                    </span>

                </div>

            <?php endif; ?>

            <!-- THONG BAO THANH CONG -->

            <?php if ($thongBao !== ''): ?>

                <div class="message success">

                    <i class="fa-solid fa-circle-check"></i>

                    <div>

                        <strong>
                            <?= eThemNhanVien($thongBao) ?>
                        </strong>

                        <br>

                        <a href="nhan_vien.php"
                           class="success-link"
                           target="mainContent">

                            <i class="fa-solid fa-arrow-left"></i>
                            Quay lại danh sách nhân viên

                        </a>

                    </div>

                </div>

            <?php endif; ?>

            <form method="POST" id="employeeForm">

                <!-- THONG TIN CA NHAN -->

                <div class="form-section">

                    <div class="section-title">
                        <i class="fa-solid fa-user"></i>
                        THÔNG TIN CÁ NHÂN
                    </div>

                    <div class="form-grid">

                        <div class="form-group full-width">

                            <label for="full_name">
                                Họ và tên
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                id="full_name"
                                class="form-control"
                                maxlength="100"
                                value="<?= eThemNhanVien($fullName) ?>"
                                placeholder="Nhập họ và tên nhân viên"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="email">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-control"
                                maxlength="100"
                                value="<?= eThemNhanVien($email) ?>"
                                placeholder="example@email.com"
                            >

                        </div>

                        <div class="form-group">

                            <label for="phone">
                                Số điện thoại
                            </label>

                            <input
                                type="text"
                                name="phone"
                                id="phone"
                                class="form-control"
                                maxlength="20"
                                value="<?= eThemNhanVien($phone) ?>"
                                placeholder="Nhập số điện thoại"
                            >

                        </div>

                    </div>

                </div>

                <!-- THONG TIN DANG NHAP -->

                <div class="form-section">

                    <div class="section-title">
                        <i class="fa-solid fa-key"></i>
                        THÔNG TIN ĐĂNG NHẬP
                    </div>

                    <div class="form-grid">

                        <div class="form-group full-width">

                            <label for="username">
                                Tên đăng nhập
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                name="username"
                                id="username"
                                class="form-control"
                                maxlength="50"
                                value="<?= eThemNhanVien($username) ?>"
                                placeholder="Nhập tên đăng nhập"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="password">
                                Mật khẩu
                                <span class="required">*</span>
                            </label>

                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    minlength="8"
                                    placeholder="Nhập mật khẩu"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    data-target="password"
                                    aria-label="Hiện hoặc ẩn mật khẩu"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                            </div>

                            <span class="form-hint">
                                Mật khẩu phải có ít nhất 8 ký tự.
                            </span>

                        </div>

                        <div class="form-group">

                            <label for="confirm_password">
                                Xác nhận mật khẩu
                                <span class="required">*</span>
                            </label>

                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    name="confirm_password"
                                    id="confirm_password"
                                    class="form-control"
                                    minlength="8"
                                    placeholder="Nhập lại mật khẩu"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    data-target="confirm_password"
                                    aria-label="Hiện hoặc ẩn mật khẩu xác nhận"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- PHAN QUYEN -->

                <div class="form-section">

                    <div class="section-title">
                        <i class="fa-solid fa-user-shield"></i>
                        PHÂN QUYỀN TÀI KHOẢN
                    </div>

                    <div class="form-group">

                        <label for="role">
                            Vai trò
                        </label>

                        <select
                            name="role"
                            id="role"
                            class="form-control"
                        >

                            <option
                                value="technician"
                                <?= $role === 'technician' ? 'selected' : '' ?>
                            >
                                Technician - Kỹ thuật viên
                            </option>

                            <option
                                value="admin"
                                <?= $role === 'admin' ? 'selected' : '' ?>
                            >
                                Admin - Quản trị viên
                            </option>

                        </select>

                        <div class="role-note">

                            <i class="fa-solid fa-circle-info"></i>

                            <span>
                                <strong>Technician:</strong> Tài khoản dành cho nhân viên kỹ thuật thực hiện công việc sửa chữa.
                                <br>
                                <strong>Admin:</strong> Tài khoản có quyền quản trị và quản lý hệ thống.
                            </span>

                        </div>

                    </div>

                </div>

                <!-- BUTTON -->

                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn-submit"
                        id="submitButton"
                    >
                        <i class="fa-solid fa-user-plus"></i>
                        Tạo tài khoản
                    </button>

                    <a
                        href="nhan_vien.php"
                        class="btn-cancel"
                        target="mainContent"
                    >
                        <i class="fa-solid fa-xmark"></i>
                        Hủy bỏ
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

<script>

// =====================================================
// 1. HIEN / AN MAT KHAU
// =====================================================

document.querySelectorAll('.toggle-password').forEach(function(button) {

    button.addEventListener('click', function() {

        const targetId = this.dataset.target;
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');

        if (input.type === 'password') {

            input.type = 'text';

            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');

        } else {

            input.type = 'password';

            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');

        }

    });

});

// =====================================================
// 2. KIEM TRA MAT KHAU XAC NHAN
// =====================================================

const employeeForm = document.getElementById('employeeForm');

const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');

confirmPasswordInput.addEventListener('input', function() {

    if (this.value !== '' && this.value !== passwordInput.value) {

        this.setCustomValidity('Mật khẩu xác nhận không khớp.');

    } else {

        this.setCustomValidity('');

    }

});

passwordInput.addEventListener('input', function() {

    if (confirmPasswordInput.value !== '') {

        if (confirmPasswordInput.value !== this.value) {

            confirmPasswordInput.setCustomValidity(
                'Mật khẩu xác nhận không khớp.'
            );

        } else {

            confirmPasswordInput.setCustomValidity('');

        }

    }

});

// =====================================================
// 3. CHONG NHAN NUT TAO TAI KHOAN NHIEU LAN
// =====================================================

const submitButton = document.getElementById('submitButton');

employeeForm.addEventListener('submit', function(event) {

    if (passwordInput.value !== confirmPasswordInput.value) {

        event.preventDefault();

        confirmPasswordInput.setCustomValidity(
            'Mật khẩu xác nhận không khớp.'
        );

        confirmPasswordInput.reportValidity();

        return;

    }

    submitButton.disabled = true;

    submitButton.innerHTML =
        '<i class="fa-solid fa-spinner fa-spin"></i> Đang tạo tài khoản...';

});

</script>

</body>
</html>
