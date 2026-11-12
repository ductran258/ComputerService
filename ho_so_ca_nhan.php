
<?php
require_once __DIR__ . '/includes/auth.php';

$userId = (int) $_SESSION['user_id'];

$errors = [];
$success = '';

$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

// =====================================================
// 1. LẤY THÔNG TIN TÀI KHOẢN HIỆN TẠI
// =====================================================

try {
    $stmt = $pdo->prepare("
        SELECT id, full_name, username, email, phone, role, created_at
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        exit('Không tìm thấy tài khoản.');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Không thể tải thông tin tài khoản.');
}

// =====================================================
// 2. XỬ LÝ CẬP NHẬT HỒ SƠ
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $token)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '') {
            $errors[] = 'Vui lòng nhập họ và tên.';
        } elseif (mb_strlen($fullName) > 100) {
            $errors[] = 'Họ và tên không được vượt quá 100 ký tự.';
        }

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Địa chỉ email không hợp lệ.';
            } elseif (strlen($email) > 100) {
                $errors[] = 'Email không được vượt quá 100 ký tự.';
            }
        } else {
            $email = null;
        }

        if (strlen($phone) > 20) {
            $errors[] = 'Số điện thoại không được vượt quá 20 ký tự.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = :full_name,
                        email = :email,
                        phone = :phone
                    WHERE id = :id
                ");

                $stmt->execute([
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'id' => $userId
                ]);

                // Cập nhật tên trong session để giao diện thay đổi ngay
                $_SESSION['full_name'] = $fullName;

                header('Location: ho_so_ca_nhan.php?updated=1');
                exit;

            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errors[] = 'Không thể cập nhật hồ sơ. Vui lòng thử lại.';
            }
        }

        // Giữ lại dữ liệu người dùng vừa nhập nếu có lỗi
        $user['full_name'] = $fullName;
        $user['email'] = $email ?? '';
        $user['phone'] = $phone;
    }
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $success = 'Cập nhật hồ sơ cá nhân thành công.';
}

// =====================================================
// 3. HÀM HỖ TRỢ
// =====================================================

function eHoSo($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$roleLabel = [
    'admin' => 'Quản trị viên',
    'technician' => 'Kỹ thuật viên'
];

$createdAt = !empty($user['created_at'])
    ? date('d/m/Y H:i', strtotime($user['created_at']))
    : 'Không xác định';

$avatarName = trim($user['full_name'] ?? '');
$avatarLetter = $avatarName !== ''
    ? mb_strtoupper(mb_substr($avatarName, 0, 1, 'UTF-8'), 'UTF-8')
    : 'U';

$roleName = $roleLabel[$user['role']] ?? 'Không xác định';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f4f6fa;
            font-family: Arial, Helvetica, sans-serif;
            color: #1e293b;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        /* ==============================
           TIÊU ĐỀ TRANG
        ============================== */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-title {
            margin: 0 0 8px;
            font-size: 25px;
            font-weight: 700;
            color: #172554;
        }

        .page-description {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .header-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: #e8efff;
            color: #2563eb;
            font-size: 23px;
            flex-shrink: 0;
        }

        /* ==============================
           BỐ CỤC CHÍNH
        ============================== */

        .profile-layout {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 22px;
            align-items: start;
        }

        .left-column,
        .right-column {
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-width: 0;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e8edf4;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 11px;
            padding-bottom: 18px;
            margin-bottom: 20px;
            border-bottom: 1px solid #edf0f5;
        }

        .card-header-icon {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff4ff;
            color: #2563eb;
            border-radius: 10px;
            font-size: 16px;
        }

        .card-title {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #1e293b;
        }

        .card-subtitle {
            margin: 5px 0 0;
            font-size: 12px;
            color: #94a3b8;
        }

        /* ==============================
           THẺ GIỚI THIỆU TÀI KHOẢN
        ============================== */

        .profile-card {
            text-align: center;
            padding: 30px 22px;
        }

        .avatar {
            width: 95px;
            height: 95px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: #ffffff;
            font-size: 38px;
            font-weight: 700;
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.2);
        }

        .profile-name {
            margin: 0 0 8px;
            font-size: 19px;
            font-weight: 700;
            color: #172554;
            overflow-wrap: anywhere;
        }

        .profile-username {
            margin: 0 0 15px;
            color: #64748b;
            font-size: 13px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 13px;
            background: #eaf1ff;
            color: #1d4ed8;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .profile-divider {
            height: 1px;
            background: #edf0f5;
            margin: 23px 0;
        }

        .profile-detail {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 17px;
            text-align: left;
            font-size: 13px;
        }

        .profile-detail:last-child {
            margin-bottom: 0;
        }

        .profile-detail-label {
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-detail-value {
            color: #334155;
            font-weight: 600;
            text-align: right;
            overflow-wrap: anywhere;
        }

        /* ==============================
           THÔNG TIN CÁ NHÂN
        ============================== */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        label {
            display: block;
            margin-bottom: 9px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .required {
            color: #dc2626;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            pointer-events: none;
        }

        input {
            width: 100%;
            height: 45px;
            padding: 0 13px 0 40px;
            border: 1px solid #dce3ed;
            border-radius: 9px;
            background: #ffffff;
            color: #1e293b;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        input::placeholder {
            color: #b0bac8;
        }

        .input-hint {
            margin-top: 7px;
            color: #94a3b8;
            font-size: 11px;
        }

        /* ==============================
           THÔNG TIN TÀI KHOẢN
        ============================== */

        .account-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .account-item {
            padding: 17px;
            background: #f8fafc;
            border: 1px solid #edf1f6;
            border-radius: 10px;
            min-width: 0;
        }

        .account-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: #64748b;
            font-size: 12px;
        }

        .account-label i {
            color: #3b82f6;
        }

        .account-value {
            color: #1e293b;
            font-size: 14px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        /* ==============================
           BUTTON
        ============================== */

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #edf0f5;
        }

        .btn {
            min-height: 43px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 19px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
            transform: translateY(-1px);
        }

        /* ==============================
           THÔNG BÁO
        ============================== */

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.6;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert ul {
            margin: 0;
            padding-left: 18px;
        }

        .alert i {
            margin-top: 2px;
        }

        /* ==============================
           RESPONSIVE
        ============================== */

        @media (max-width: 850px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }

            .left-column {
                display: grid;
                grid-template-columns: 1fr;
            }

            .profile-card {
                padding: 25px;
            }
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }

            .page-title {
                font-size: 21px;
            }

            .page-header {
                align-items: flex-start;
            }

            .header-icon {
                width: 43px;
                height: 43px;
                font-size: 19px;
            }

            .card {
                padding: 18px;
            }

            .form-row,
            .account-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .account-grid {
                gap: 12px;
            }

            .form-actions {
                justify-content: stretch;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <!-- TIÊU ĐỀ TRANG -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Hồ sơ cá nhân</h1>
            <p class="page-description">
                Quản lý thông tin cá nhân và thông tin tài khoản của bạn.
            </p>
        </div>

        <div class="header-icon">
            <i class="fa-solid fa-user-gear"></i>
        </div>
    </div>

    <!-- THÔNG BÁO -->
    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <div><?= eHoSo($success) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>

            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= eHoSo($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="profile-layout">

        <!-- CỘT TRÁI: TỔNG QUAN TÀI KHOẢN -->
        <div class="left-column">

            <div class="card profile-card">

                <div class="avatar">
                    <?= eHoSo($avatarLetter) ?>
                </div>

                <h2 class="profile-name">
                    <?= eHoSo($user['full_name']) ?>
                </h2>

                <p class="profile-username">
                    @<?= eHoSo($user['username']) ?>
                </p>

                <span class="role-badge">
                    <i class="fa-solid fa-shield-halved"></i>
                    <?= eHoSo($roleName) ?>
                </span>

                <div class="profile-divider"></div>

                <div class="profile-detail">
                    <span class="profile-detail-label">
                        <i class="fa-solid fa-id-card"></i>
                        Mã tài khoản
                    </span>

                    <span class="profile-detail-value">
                        #<?= (int) $user['id'] ?>
                    </span>
                </div>

                <div class="profile-detail">
                    <span class="profile-detail-label">
                        <i class="fa-regular fa-calendar"></i>
                        Ngày tham gia
                    </span>

                    <span class="profile-detail-value">
                        <?= eHoSo($createdAt) ?>
                    </span>
                </div>

            </div>

        </div>

        <!-- CỘT PHẢI: THÔNG TIN VÀ CẬP NHẬT -->
        <div class="right-column">

            <!-- THÔNG TIN CÁ NHÂN -->
            <div class="card">

                <div class="card-header">
                    <div class="card-header-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>

                    <div>
                        <h2 class="card-title">Thông tin cá nhân</h2>
                        <p class="card-subtitle">
                            Cập nhật thông tin liên hệ của bạn.
                        </p>
                    </div>
                </div>

                <form method="POST" action="ho_so_ca_nhan.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= eHoSo($csrfToken) ?>"
                    >

                    <div class="form-group">
                        <label for="full_name">
                            Họ và tên <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">
                            <i class="fa-solid fa-user input-icon"></i>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                maxlength="100"
                                value="<?= eHoSo($user['full_name']) ?>"
                                placeholder="Nhập họ và tên"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="email">Email</label>

                            <div class="input-wrapper">
                                <i class="fa-solid fa-envelope input-icon"></i>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    maxlength="100"
                                    value="<?= eHoSo($user['email']) ?>"
                                    placeholder="example@email.com"
                                >
                            </div>

                            <p class="input-hint">
                                Email dùng để liên hệ.
                            </p>
                        </div>

                        <div class="form-group">
                            <label for="phone">Số điện thoại</label>

                            <div class="input-wrapper">
                                <i class="fa-solid fa-phone input-icon"></i>

                                <input
                                    type="text"
                                    id="phone"
                                    name="phone"
                                    maxlength="20"
                                    value="<?= eHoSo($user['phone']) ?>"
                                    placeholder="Nhập số điện thoại"
                                >
                            </div>

                            <p class="input-hint">
                                Tối đa 20 ký tự.
                            </p>
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Lưu thay đổi
                        </button>
                    </div>

                </form>

            </div>

            <!-- THÔNG TIN TÀI KHOẢN -->
            <div class="card">

                <div class="card-header">
                    <div class="card-header-icon">
                        <i class="fa-solid fa-address-card"></i>
                    </div>

                    <div>
                        <h2 class="card-title">Thông tin tài khoản</h2>
                        <p class="card-subtitle">
                            Thông tin định danh của tài khoản hiện tại.
                        </p>
                    </div>
                </div>

                <div class="account-grid">

                    <div class="account-item">
                        <div class="account-label">
                            <i class="fa-solid fa-user-tag"></i>
                            Tên đăng nhập
                        </div>

                        <div class="account-value">
                            <?= eHoSo($user['username']) ?>
                        </div>
                    </div>

                    <div class="account-item">
                        <div class="account-label">
                            <i class="fa-solid fa-shield-halved"></i>
                            Vai trò
                        </div>

                        <div class="account-value">
                            <?= eHoSo($roleName) ?>
                        </div>
                    </div>

                    <div class="account-item">
                        <div class="account-label">
                            <i class="fa-regular fa-calendar-check"></i>
                            Ngày tạo tài khoản
                        </div>

                        <div class="account-value">
                            <?= eHoSo($createdAt) ?>
                        </div>
                    </div>

                    <div class="account-item">
                        <div class="account-label">
                            <i class="fa-solid fa-fingerprint"></i>
                            Mã tài khoản
                        </div>

                        <div class="account-value">
                            #<?= (int) $user['id'] ?>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>
