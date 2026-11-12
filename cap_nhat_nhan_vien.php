
<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function eCapNhatNhanVien($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$loi = '';
$thongBao = '';

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$userId || $userId <= 0) {
    exit('ID nhân viên không hợp lệ.');
}

$stmt = $pdo->prepare("
    SELECT id, full_name, username, email, role, phone
    FROM users
    WHERE id = :id
    LIMIT 1
");
$stmt->execute(['id' => $userId]);

$nhanVien = $stmt->fetch();

if (!$nhanVien) {
    exit('Không tìm thấy nhân viên.');
}

$fullName = $nhanVien['full_name'];
$username = $nhanVien['username'];
$email = $nhanVien['email'] ?? '';
$phone = $nhanVien['phone'] ?? '';
$role = $nhanVien['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? '';

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $loi = 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.';
    } elseif (
        $fullName === '' ||
        $username === ''
    ) {
        $loi = 'Vui lòng nhập họ tên và tên đăng nhập.';
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
    } elseif (!in_array($role, ['admin', 'technician'], true)) {
        $loi = 'Vai trò không hợp lệ.';
    } elseif (
        (int) $_SESSION['user_id'] === (int) $userId &&
        $role !== 'admin'
    ) {
        $loi = 'Bạn không thể tự hạ quyền tài khoản của mình.';
    } else {
        try {
            $pdo->beginTransaction();

            // Khóa bản ghi nhân viên cần sửa
            $checkUser = $pdo->prepare("
                SELECT id, role
                FROM users
                WHERE id = :id
                FOR UPDATE
            ");
            $checkUser->execute(['id' => $userId]);
            $userCurrent = $checkUser->fetch();

            if (!$userCurrent) {
                throw new RuntimeException('Không tìm thấy nhân viên.');
            }

            // Không cho hạ quyền Admin cuối cùng
            if (
                $userCurrent['role'] === 'admin' &&
                $role !== 'admin'
            ) {
                $countAdmin = $pdo->query("
                    SELECT COUNT(*)
                    FROM users
                    WHERE role = 'admin'
                    FOR UPDATE
                ")->fetchColumn();

                if ((int) $countAdmin <= 1) {
                    throw new RuntimeException(
                        'Hệ thống phải có ít nhất một tài khoản Admin.'
                    );
                }
            }

            // Kiểm tra tên đăng nhập đã được sử dụng chưa
            $checkUsername = $pdo->prepare("
                SELECT id
                FROM users
                WHERE username = :username
                AND id <> :id
                LIMIT 1
            ");
            $checkUsername->execute([
                'username' => $username,
                'id' => $userId
            ]);

            if ($checkUsername->fetch()) {
                throw new RuntimeException(
                    'Tên đăng nhập này đã tồn tại. Vui lòng chọn tên khác.'
                );
            }

            // Cập nhật thông tin nhân viên
            $update = $pdo->prepare("
                UPDATE users
                SET
                    full_name = :full_name,
                    username = :username,
                    email = :email,
                    phone = :phone,
                    role = :role
                WHERE id = :id
            ");

            $update->execute([
                'full_name' => $fullName,
                'username' => $username,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'role' => $role,
                'id' => $userId
            ]);

            $pdo->commit();

            // Nếu Admin sửa chính tài khoản mình,
            // cập nhật lại thông tin trong session.
            if ((int) $_SESSION['user_id'] === (int) $userId) {
                $_SESSION['full_name'] = $fullName;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
            }

            $thongBao = 'Cập nhật thông tin nhân viên thành công!';

            $nhanVien['full_name'] = $fullName;
            $nhanVien['username'] = $username;
            $nhanVien['email'] = $email;
            $nhanVien['phone'] = $phone;
            $nhanVien['role'] = $role;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof RuntimeException) {
                $loi = $e->getMessage();
            } else {
                error_log($e->getMessage());
                $loi = 'Không thể cập nhật nhân viên. Vui lòng thử lại.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật nhân viên</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #333;
        }

        .container {
            max-width: 650px;
            margin: auto;
        }

        .form-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
        }

        h2 {
            margin-top: 0;
            margin-bottom: 22px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
        }

        input, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .btn {
            display: inline-block;
            padding: 11px 17px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .btn-back {
            background: #64748b;
            margin-left: 7px;
        }

        .message {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 6px;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .note {
            margin-top: 7px;
            color: #777;
            font-size: 12px;
        }
    </style>
</head>

<body>
<div class="container">
    <div class="form-box">

        <h2>Cập nhật nhân viên</h2>

        <?php if ($loi !== ''): ?>
            <div class="message error">
                <?= eCapNhatNhanVien($loi) ?>
            </div>
        <?php endif; ?>

        <?php if ($thongBao !== ''): ?>
            <div class="message success">
                <?= eCapNhatNhanVien($thongBao) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= eCapNhatNhanVien($_SESSION['csrf_token']) ?>"
            >

            <div class="form-group">
                <label>Họ và tên</label>
                <input
                    type="text"
                    name="full_name"
                    maxlength="100"
                    value="<?= eCapNhatNhanVien($fullName) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input
                    type="text"
                    name="username"
                    maxlength="50"
                    value="<?= eCapNhatNhanVien($username) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Email</label>
                <input
                    type="email"
                    name="email"
                    maxlength="100"
                    value="<?= eCapNhatNhanVien($email) ?>"
                >
            </div>

            <div class="form-group">
                <label>Số điện thoại</label>
                <input
                    type="text"
                    name="phone"
                    maxlength="20"
                    value="<?= eCapNhatNhanVien($phone) ?>"
                >
            </div>

            <div class="form-group">
                <label>Vai trò</label>

                <select name="role">
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

                <div class="note">
                    Chỉ Admin mới có quyền thay đổi vai trò nhân viên.
                </div>
            </div>

            <button type="submit" class="btn">
                Lưu thay đổi
            </button>

            <a href="nhan_vien.php" class="btn btn-back" target="mainContent">
                Quay lại
            </a>

        </form>

    </div>
</div>
</body>
</html>
