
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

function eDatLaiMK($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int) ($_POST['user_id'] ?? $_GET['id'] ?? 0);

$thongBaoLoi = '';
$matKhauVuaDat = null;

if ($userId <= 0) {
    http_response_code(400);
    exit('ID nhân viên không hợp lệ.');
}

// Lấy thông tin nhân viên
$stmt = $pdo->prepare("
    SELECT id, full_name, username, role
    FROM users
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $userId
]);

$nhanVien = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nhanVien) {
    http_response_code(404);
    exit('Không tìm thấy nhân viên.');
}

// Xử lý đặt lại mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';
    $matKhauMoi = $_POST['new_password'] ?? '';
    $xacNhanMatKhau = $_POST['confirm_password'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {

        $thongBaoLoi = 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.';

    } elseif (strlen($matKhauMoi) < 8) {

        $thongBaoLoi = 'Mật khẩu mới phải có ít nhất 8 ký tự.';

    } elseif ($matKhauMoi !== $xacNhanMatKhau) {

        $thongBaoLoi = 'Mật khẩu xác nhận không khớp.';

    } else {

        try {

            $matKhauDaMaHoa = password_hash(
                $matKhauMoi,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    password = :password,
                    password_changed_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $stmt->execute([
                'password' => $matKhauDaMaHoa,
                'id' => $userId
            ]);

            $matKhauVuaDat = $matKhauMoi;

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $thongBaoLoi = 'Không thể cập nhật mật khẩu. Vui lòng thử lại.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Đặt lại mật khẩu nhân viên</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #222;
        }

        .container {
            max-width: 600px;
            margin: 30px auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        h2 {
            margin-top: 0;
            margin-bottom: 20px;
        }

        .employee-info {
            padding: 15px;
            margin-bottom: 20px;
            background: #f1f5f9;
            border-radius: 6px;
            line-height: 1.8;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 11px;
            margin-bottom: 18px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        button {
            padding: 11px 18px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            font-size: 15px;
            cursor: pointer;
        }

        button:hover {
            opacity: 0.85;
        }

        .back {
            display: inline-block;
            margin-left: 10px;
            color: #475569;
            text-decoration: none;
        }

        .error {
            padding: 12px;
            margin-bottom: 18px;
            color: #b91c1c;
            background: #fee2e2;
            border-radius: 6px;
        }

        .success {
            padding: 15px;
            margin-bottom: 20px;
            color: #166534;
            background: #dcfce7;
            border-radius: 6px;
        }

        .password-result {
            margin-top: 12px;
            padding: 12px;
            background: white;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            color: #166534;
            font-size: 18px;
            font-weight: bold;
            word-break: break-all;
        }

        .note {
            margin-top: 5px;
            margin-bottom: 18px;
            color: #64748b;
            font-size: 13px;
        }

        .security-note {
            margin-top: 12px;
            font-size: 13px;
            font-weight: normal;
            color: #475569;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Đặt lại mật khẩu nhân viên</h2>

    <div class="employee-info">
        <strong>Họ tên:</strong>
        <?= eDatLaiMK($nhanVien['full_name']) ?>
        <br>

        <strong>Tên đăng nhập:</strong>
        <?= eDatLaiMK($nhanVien['username']) ?>
        <br>

        <strong>Vai trò:</strong>
        <?= $nhanVien['role'] === 'admin' ? 'Admin' : 'Technician' ?>
    </div>

    <?php if ($thongBaoLoi !== ''): ?>
        <div class="error">
            <?= eDatLaiMK($thongBaoLoi) ?>
        </div>
    <?php endif; ?>

    <?php if ($matKhauVuaDat !== null): ?>

        <div class="success">

            <strong>Đặt lại mật khẩu thành công!</strong>

            <p>Mật khẩu mới của nhân viên là:</p>

            <div class="password-result">
                <?= eDatLaiMK($matKhauVuaDat) ?>
            </div>

            <p class="security-note">
                Hãy thông báo mật khẩu này cho nhân viên qua kênh riêng tư.
                Mật khẩu chỉ hiển thị trong lần này. Nếu rời trang hoặc tải lại,
                bạn sẽ không thể xem lại mật khẩu này.
            </p>

        </div>

        <a class="back" href="nhan_vien.php" target="mainContent">
            Quay lại danh sách nhân viên
        </a>

    <?php else: ?>

        <form method="POST">

            <input
                type="hidden"
                name="user_id"
                value="<?= (int) $nhanVien['id'] ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= eDatLaiMK($_SESSION['csrf_token']) ?>"
            >

            <label for="new_password">Mật khẩu mới</label>

            <input
                type="password"
                id="new_password"
                name="new_password"
                minlength="8"
                required
                autocomplete="new-password"
                placeholder="Nhập mật khẩu mới"
            >

            <p class="note">
                Mật khẩu phải có ít nhất 8 ký tự.
            </p>

            <label for="confirm_password">
                Xác nhận mật khẩu mới
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                required
                autocomplete="new-password"
                placeholder="Nhập lại mật khẩu mới"
            >

            <button type="submit">
                Xác nhận đặt lại mật khẩu
            </button>

            <a class="back" href="nhan_vien.php" target="mainContent">
                Quay lại
            </a>

        </form>

    <?php endif; ?>

</div>

</body>
</html>
