
<?php
require_once __DIR__ . '/includes/auth.php';

requireAdmin();
require_once __DIR__ . '/config/database.php';

$thongBao = '';
$loi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $username === '' || $password === '') {
        $loi = 'Vui lòng nhập đầy đủ họ tên, tên đăng nhập và mật khẩu.';
    } elseif (strlen($password) < 8) {
        $loi = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif ($password !== $confirmPassword) {
        $loi = 'Mật khẩu xác nhận không khớp.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loi = 'Địa chỉ email không hợp lệ.';
    } else {
        $check = $pdo->prepare(
            "SELECT id FROM users WHERE username = :username LIMIT 1"
        );

        $check->execute(['username' => $username]);

        if ($check->fetch()) {
            $loi = 'Tên đăng nhập này đã tồn tại.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $sql = "
                INSERT INTO users (
                    full_name,
                    username,
                    email,
                    password,
                    role
                )
                VALUES (
                    :full_name,
                    :username,
                    :email,
                    :password,
                    'admin'
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                'full_name' => $fullName,
                'username' => $username,
                'email' => $email !== '' ? $email : null,
                'password' => $hashedPassword
            ]);

            $thongBao = 'Tạo tài khoản Admin thành công!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo tài khoản Admin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
        }

        .container {
            max-width: 450px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        h2 {
            margin-top: 0;
            text-align: center;
            color: #1e293b;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .success {
            padding: 12px;
            background: #dcfce7;
            color: #166534;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .error {
            padding: 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .note {
            margin-top: 18px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Tạo tài khoản Admin</h2>

    <?php if ($thongBao !== ''): ?>
        <div class="success">
            <?php echo htmlspecialchars($thongBao); ?>
        </div>
    <?php endif; ?>

    <?php if ($loi !== ''): ?>
        <div class="error">
            <?php echo htmlspecialchars($loi); ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Họ và tên</label>
        <input
            type="text"
            name="full_name"
            required
        >

        <label>Tên đăng nhập</label>
        <input
            type="text"
            name="username"
            required
        >

        <label>Email</label>
        <input
            type="email"
            name="email"
        >

        <label>Mật khẩu</label>
        <input
            type="password"
            name="password"
            minlength="8"
            required
        >

        <label>Xác nhận mật khẩu</label>
        <input
            type="password"
            name="confirm_password"
            minlength="8"
            required
        >

        <button type="submit">Tạo tài khoản Admin</button>

    </form>

    <p class="note">
        Mật khẩu sẽ được mã hóa trước khi lưu vào cơ sở dữ liệu.
    </p>

</div>

</body>
</html>
