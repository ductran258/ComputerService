
<?php

require_once __DIR__ . '/includes/auth.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];

$thongBao = '';
$loaiThongBao = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ======================================================
// 1. XỬ LÝ ĐỔI MẬT KHẨU
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {

        $thongBao = 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.';
        $loaiThongBao = 'error';

    } else {

        $matKhauHienTai = $_POST['mat_khau_hien_tai'] ?? '';
        $matKhauMoi = $_POST['mat_khau_moi'] ?? '';
        $xacNhanMatKhau = $_POST['xac_nhan_mat_khau'] ?? '';

        if (
            $matKhauHienTai === '' ||
            $matKhauMoi === '' ||
            $xacNhanMatKhau === ''
        ) {

            $thongBao = 'Vui lòng nhập đầy đủ các trường.';
            $loaiThongBao = 'error';

        } elseif (strlen($matKhauMoi) < 8) {

            $thongBao = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
            $loaiThongBao = 'error';

        } elseif ($matKhauMoi !== $xacNhanMatKhau) {

            $thongBao = 'Mật khẩu xác nhận không khớp.';
            $loaiThongBao = 'error';

        } else {

            try {

                $stmt = $pdo->prepare("
                    SELECT password
                    FROM users
                    WHERE id = :id
                    LIMIT 1
                ");

                $stmt->execute(['id' => $userId]);
                $user = $stmt->fetch();

                if (
                    !$user ||
                    !password_verify($matKhauHienTai, $user['password'])
                ) {

                    $thongBao = 'Mật khẩu hiện tại không chính xác.';
                    $loaiThongBao = 'error';

                } elseif (
                    password_verify($matKhauMoi, $user['password'])
                ) {

                    $thongBao = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
                    $loaiThongBao = 'error';

                } else {

                    $matKhauDaMaHoa = password_hash(
                        $matKhauMoi,
                        PASSWORD_DEFAULT
                    );

                    $update = $pdo->prepare("
                        UPDATE users
                        SET
                            password = :password,
                            password_changed_at = NOW()
                        WHERE id = :id
                    ");

                    $update->execute([
                        'password' => $matKhauDaMaHoa,
                        'id' => $userId
                    ]);

                    $thongBao = 'Đổi mật khẩu thành công!';
                    $loaiThongBao = 'success';

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }

            } catch (PDOException $e) {

                error_log($e->getMessage());

                $thongBao = 'Không thể đổi mật khẩu. Vui lòng thử lại sau.';
                $loaiThongBao = 'error';
            }
        }
    }
}

// ======================================================
// 2. HÀM HỖ TRỢ
// ======================================================

function eDoiMatKhau($value): string
{
    return htmlspecialchars(
        (string) $value,
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

    <title>Đổi mật khẩu</title>

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
            padding: 30px;
            min-height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f7fb;
            color: #1e293b;
        }

        .container {
            max-width: 850px;
            margin: 20px auto;
        }

        /* ==============================
           HEADER
        ============================== */

        .page-header {
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

        /* ==============================
           MAIN LAYOUT
        ============================== */

        .content-grid {
            display: grid;
            grid-template-columns: 1.5fr 0.85fr;
            gap: 20px;
            align-items: start;
        }

        .form-card,
        .security-card {
            background: #fff;
            border: 1px solid #edf0f5;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .form-card {
            padding: 28px;
        }

        .security-card {
            padding: 22px;
        }

        .card-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: #1e293b;
            font-size: 16px;
            font-weight: 700;
        }

        .card-heading i {
            color: #2563eb;
        }

        .card-description {
            margin: 0 0 25px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }

        /* ==============================
           MESSAGE
        ============================== */

        .message {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 15px;
            margin-bottom: 22px;
            border-radius: 9px;
            font-size: 13px;
            line-height: 1.5;
        }

        .message i {
            margin-top: 2px;
            font-size: 15px;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* ==============================
           FORM
        ============================== */

        .form-group {
            margin-bottom: 21px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            width: 100%;
            height: 46px;
            padding: 0 48px 0 14px;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            outline: none;
            background: #fff;
            color: #1e293b;
            font-family: inherit;
            font-size: 14px;
            transition: 0.2s;
        }

        .password-wrapper input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .password-wrapper input::placeholder {
            color: #94a3b8;
        }

        .toggle-password {
            position: absolute;
            top: 0;
            right: 0;
            width: 45px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            transition: 0.2s;
        }

        .toggle-password:hover {
            color: #2563eb;
        }

        .field-hint {
            display: block;
            margin-top: 7px;
            color: #94a3b8;
            font-size: 11px;
        }

        /* ==============================
           PASSWORD STRENGTH
        ============================== */

        .strength-section {
            margin-top: -10px;
            margin-bottom: 22px;
        }

        .strength-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #64748b;
            font-size: 11px;
        }

        .strength-label strong {
            color: #64748b;
        }

        .strength-track {
            width: 100%;
            height: 5px;
            overflow: hidden;
            border-radius: 10px;
            background: #e2e8f0;
        }

        .strength-bar {
            width: 0;
            height: 100%;
            border-radius: 10px;
            background: #94a3b8;
            transition: 0.25s;
        }

        /* ==============================
           PASSWORD REQUIREMENTS
        ============================== */

        .requirements {
            padding: 15px;
            margin-bottom: 22px;
            border-radius: 9px;
            background: #f8fafc;
        }

        .requirements-title {
            margin-bottom: 11px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .requirement-item {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-top: 8px;
            color: #94a3b8;
            font-size: 12px;
            transition: 0.2s;
        }

        .requirement-item i {
            width: 14px;
            font-size: 11px;
        }

        .requirement-item.valid {
            color: #16a34a;
        }

        /* ==============================
           BUTTONS
        ============================== */

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 18px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-primary {
            flex: 1;
            background: #2563eb;
            color: #fff;
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

        /* ==============================
           SECURITY INFORMATION
        ============================== */

        .security-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            border-radius: 12px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 21px;
        }

        .security-card h3 {
            margin: 0 0 8px;
            color: #1e293b;
            font-size: 15px;
        }

        .security-description {
            margin: 0 0 20px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.7;
        }

        .security-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .security-item {
            display: flex;
            align-items: flex-start;
            gap: 11px;
        }

        .security-item i {
            margin-top: 2px;
            color: #16a34a;
            font-size: 14px;
        }

        .security-item strong {
            display: block;
            margin-bottom: 4px;
            color: #334155;
            font-size: 12px;
        }

        .security-item span {
            display: block;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.5;
        }

        .security-footer {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            margin-top: 22px;
            border-radius: 8px;
            background: #f0fdf4;
            color: #15803d;
            font-size: 11px;
            line-height: 1.5;
        }

        /* ==============================
           RESPONSIVE
        ============================== */

        @media (max-width: 750px) {

            body {
                padding: 16px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .form-card {
                padding: 22px;
            }

            .security-card {
                padding: 20px;
            }

        }

        @media (max-width: 480px) {

            .page-title h2 {
                font-size: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- ==============================
         HEADER
    ============================== -->

    <div class="page-header">

        <div class="page-title">

            <div class="page-icon">
                <i class="fa-solid fa-key"></i>
            </div>

            <div>

                <h2>Đổi mật khẩu</h2>

                <p class="description">
                    Quản lý và bảo vệ mật khẩu tài khoản của bạn.
                </p>

            </div>

        </div>

    </div>

    <!-- ==============================
         CONTENT
    ============================== -->

    <div class="content-grid">

        <!-- FORM ĐỔI MẬT KHẨU -->

        <div class="form-card">

            <div class="card-heading">
                <i class="fa-solid fa-lock"></i>
                Cập nhật mật khẩu
            </div>

            <p class="card-description">
                Vui lòng nhập mật khẩu hiện tại và thiết lập mật khẩu mới cho tài khoản.
            </p>

            <?php if ($thongBao !== ''): ?>

                <div class="message <?= eDoiMatKhau($loaiThongBao) ?>">

                    <?php if ($loaiThongBao === 'success'): ?>

                        <i class="fa-solid fa-circle-check"></i>

                    <?php else: ?>

                        <i class="fa-solid fa-circle-exclamation"></i>

                    <?php endif; ?>

                    <span>
                        <?= eDoiMatKhau($thongBao) ?>
                    </span>

                </div>

            <?php endif; ?>

            <form method="POST" id="changePasswordForm">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= eDoiMatKhau($_SESSION['csrf_token']) ?>"
                >

                <!-- MẬT KHẨU HIỆN TẠI -->

                <div class="form-group">

                    <label for="mat_khau_hien_tai">
                        Mật khẩu hiện tại
                    </label>

                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="mat_khau_hien_tai"
                            name="mat_khau_hien_tai"
                            placeholder="Nhập mật khẩu hiện tại"
                            required
                            autocomplete="current-password"
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            data-target="mat_khau_hien_tai"
                            aria-label="Hiện mật khẩu"
                        >
                            <i class="fa-regular fa-eye"></i>
                        </button>

                    </div>

                </div>

                <!-- MẬT KHẨU MỚI -->

                <div class="form-group">

                    <label for="mat_khau_moi">
                        Mật khẩu mới
                    </label>

                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="mat_khau_moi"
                            name="mat_khau_moi"
                            placeholder="Nhập mật khẩu mới"
                            minlength="8"
                            required
                            autocomplete="new-password"
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            data-target="mat_khau_moi"
                            aria-label="Hiện mật khẩu"
                        >
                            <i class="fa-regular fa-eye"></i>
                        </button>

                    </div>

                    <span class="field-hint">
                        Mật khẩu mới phải có ít nhất 8 ký tự.
                    </span>

                </div>

                <!-- ĐÁNH GIÁ MẬT KHẨU -->

                <div class="strength-section">

                    <div class="strength-label">

                        <span>Độ dài mật khẩu</span>

                        <strong id="strengthText">
                            Chưa nhập
                        </strong>

                    </div>

                    <div class="strength-track">

                        <div
                            class="strength-bar"
                            id="strengthBar"
                        ></div>

                    </div>

                </div>

                <!-- XÁC NHẬN MẬT KHẨU -->

                <div class="form-group">

                    <label for="xac_nhan_mat_khau">
                        Xác nhận mật khẩu mới
                    </label>

                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="xac_nhan_mat_khau"
                            name="xac_nhan_mat_khau"
                            placeholder="Nhập lại mật khẩu mới"
                            minlength="8"
                            required
                            autocomplete="new-password"
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            data-target="xac_nhan_mat_khau"
                            aria-label="Hiện mật khẩu"
                        >
                            <i class="fa-regular fa-eye"></i>
                        </button>

                    </div>

                    <span class="field-hint" id="confirmHint">
                        Nhập lại chính xác mật khẩu mới.
                    </span>

                </div>

                <!-- YÊU CẦU MẬT KHẨU -->

                <div class="requirements">

                    <div class="requirements-title">
                        Yêu cầu mật khẩu
                    </div>

                    <div class="requirement-item" id="requireLength">

                        <i class="fa-solid fa-circle"></i>

                        Ít nhất 8 ký tự

                    </div>

                    <div class="requirement-item" id="requireMatch">

                        <i class="fa-solid fa-circle"></i>

                        Mật khẩu xác nhận trùng khớp

                    </div>

                </div>

                <!-- BUTTONS -->

                <div class="form-actions">

                    <button type="submit" class="btn btn-primary">

                        <i class="fa-solid fa-floppy-disk"></i>

                        Cập nhật mật khẩu

                    </button>

                    <a
                        href="javascript:history.back()"
                        class="btn btn-secondary"
                    >

                        <i class="fa-solid fa-arrow-left"></i>

                        Quay lại

                    </a>

                </div>

            </form>

        </div>

        <!-- SECURITY INFORMATION -->

        <div class="security-card">

            <div class="security-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>

            <h3>Bảo mật tài khoản</h3>

            <p class="security-description">
                Một số lưu ý giúp bạn bảo vệ tài khoản khi sử dụng hệ thống quản lý.
            </p>

            <div class="security-list">

                <div class="security-item">

                    <i class="fa-solid fa-circle-check"></i>

                    <div>

                        <strong>Sử dụng mật khẩu riêng</strong>

                        <span>
                            Không sử dụng lại mật khẩu của các tài khoản khác.
                        </span>

                    </div>

                </div>

                <div class="security-item">

                    <i class="fa-solid fa-circle-check"></i>

                    <div>

                        <strong>Không chia sẻ mật khẩu</strong>

                        <span>
                            Không cung cấp mật khẩu cho người khác.
                        </span>

                    </div>

                </div>

                <div class="security-item">

                    <i class="fa-solid fa-circle-check"></i>

                    <div>

                        <strong>Kiểm tra trước khi xác nhận</strong>

                        <span>
                            Đảm bảo mật khẩu mới và mật khẩu xác nhận giống nhau.
                        </span>

                    </div>

                </div>

                <div class="security-item">

                    <i class="fa-solid fa-circle-check"></i>

                    <div>

                        <strong>Đổi mật khẩu khi cần thiết</strong>

                        <span>
                            Nên thay đổi mật khẩu nếu nghi ngờ tài khoản bị lộ.
                        </span>

                    </div>

                </div>

            </div>

            <div class="security-footer">

                <i class="fa-solid fa-lock"></i>

                Mật khẩu được mã hóa trước khi lưu vào hệ thống.

            </div>

        </div>

    </div>

</div>

<script>

// ======================================================
// 1. ẨN / HIỆN MẬT KHẨU
// ======================================================

document.querySelectorAll('.toggle-password').forEach(button => {

    button.addEventListener('click', function () {

        const targetId = this.dataset.target;
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');

        if (input.type === 'password') {

            input.type = 'text';

            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');

            this.setAttribute('aria-label', 'Ẩn mật khẩu');

        } else {

            input.type = 'password';

            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');

            this.setAttribute('aria-label', 'Hiện mật khẩu');
        }

    });

});

// ======================================================
// 2. LẤY CÁC Ô NHẬP MẬT KHẨU
// ======================================================

const passwordInput = document.getElementById('mat_khau_moi');

const confirmInput = document.getElementById('xac_nhan_mat_khau');

const strengthBar = document.getElementById('strengthBar');

const strengthText = document.getElementById('strengthText');

const confirmHint = document.getElementById('confirmHint');

const requireLength = document.getElementById('requireLength');

const requireMatch = document.getElementById('requireMatch');

// ======================================================
// 3. CẬP NHẬT ĐỘ DÀI MẬT KHẨU
// ======================================================

function updatePasswordStrength() {

    const password = passwordInput.value;

    if (password.length === 0) {

        strengthBar.style.width = '0%';

        strengthBar.style.background = '#94a3b8';

        strengthText.textContent = 'Chưa nhập';

    } else if (password.length < 8) {

        strengthBar.style.width = '30%';

        strengthBar.style.background = '#ef4444';

        strengthText.textContent = 'Chưa đủ độ dài';

    } else {

        strengthBar.style.width = '100%';

        strengthBar.style.background = '#16a34a';

        strengthText.textContent = 'Đạt yêu cầu';
    }

    updateRequirements();
}

// ======================================================
// 4. KIỂM TRA YÊU CẦU MẬT KHẨU
// ======================================================

function updateRequirements() {

    const password = passwordInput.value;

    const confirmPassword = confirmInput.value;

    // Kiểm tra ít nhất 8 ký tự.

    if (password.length >= 8) {

        requireLength.classList.add('valid');

        requireLength.querySelector('i').className =
            'fa-solid fa-circle-check';

    } else {

        requireLength.classList.remove('valid');

        requireLength.querySelector('i').className =
            'fa-solid fa-circle';
    }

    // Kiểm tra xác nhận mật khẩu.

    if (
        confirmPassword !== '' &&
        password === confirmPassword
    ) {

        requireMatch.classList.add('valid');

        requireMatch.querySelector('i').className =
            'fa-solid fa-circle-check';

        confirmHint.textContent = 'Mật khẩu xác nhận khớp.';

        confirmHint.style.color = '#16a34a';

        confirmInput.style.borderColor = '#16a34a';

    } else {

        requireMatch.classList.remove('valid');

        requireMatch.querySelector('i').className =
            'fa-solid fa-circle';

        confirmInput.style.borderColor = '';

        if (confirmPassword !== '') {

            confirmHint.textContent = 'Mật khẩu xác nhận chưa khớp.';

            confirmHint.style.color = '#dc2626';

            confirmInput.style.borderColor = '#dc2626';

        } else {

            confirmHint.textContent =
                'Nhập lại chính xác mật khẩu mới.';

            confirmHint.style.color = '#94a3b8';
        }
    }

}

// ======================================================
// 5. LẮNG NGHE THAY ĐỔI
// ======================================================

passwordInput.addEventListener('input', updatePasswordStrength);

confirmInput.addEventListener('input', updateRequirements);

// ======================================================
// 6. KIỂM TRA TRƯỚC KHI GỬI FORM
// ======================================================

document.getElementById('changePasswordForm').addEventListener(
    'submit',
    function (event) {

        const password = passwordInput.value;

        const confirmPassword = confirmInput.value;

        if (password.length < 8) {

            event.preventDefault();

            alert('Mật khẩu mới phải có ít nhất 8 ký tự.');

            passwordInput.focus();

            return;
        }

        if (password !== confirmPassword) {

            event.preventDefault();

            alert('Mật khẩu xác nhận không khớp.');

            confirmInput.focus();

            return;
        }

    }
);

</script>

</body>
</html>
