
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

$loi = '';

/**
 * Ghi nhận lịch sử đăng nhập.
 */
function ghiLichSuDangNhap(
    PDO $pdo,
    ?int $userId,
    string $username,
    string $status
): void {
    try {
        $sql = "
            INSERT INTO login_history (
                user_id,
                username,
                status,
                ip_address,
                user_agent
            )
            VALUES (
                :user_id,
                :username,
                :status,
                :ip_address,
                :user_agent
            )
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'user_id' => $userId,
            'username' => $username,
            'status' => $status,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

    } catch (PDOException $e) {
        // Không để lỗi ghi lịch sử làm gián đoạn đăng nhập.
        error_log('Lỗi ghi lịch sử đăng nhập: ' . $e->getMessage());
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        ghiLichSuDangNhap(
            $pdo,
            null,
            $username,
            'failed'
        );

        $loi = 'Vui lòng nhập tên đăng nhập và mật khẩu.';

    } else {

        $sql = "
            SELECT id, full_name, username, password, role, is_active, password_changed_at
FROM users
            WHERE username = :username
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'username' => $username
        ]);

        $user = $stmt->fetch();

        // Kiểm tra tài khoản và mật khẩu.
        if (!$user || !password_verify($password, $user['password'])) {

            ghiLichSuDangNhap(
                $pdo,
                $user ? (int) $user['id'] : null,
                $username,
                'failed'
            );

            $loi = 'Tên đăng nhập hoặc mật khẩu không chính xác.';

        } else {

            // Kiểm tra tài khoản có đang hoạt động không.
            if ((int) $user['is_active'] !== 1) {

                ghiLichSuDangNhap(
                    $pdo,
                    (int) $user['id'],
                    $username,
                    'failed'
                );

                $loi = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.';

            } elseif (
                $user['role'] !== 'admin' &&
                $user['role'] !== 'technician'
            ) {

                ghiLichSuDangNhap(
                    $pdo,
                    (int) $user['id'],
                    $username,
                    'failed'
                );

                $loi = 'Tài khoản không có quyền truy cập hợp lệ.';

            } else {

                // Ghi nhận đăng nhập thành công.
                ghiLichSuDangNhap(
                    $pdo,
                    (int) $user['id'],
                    $username,
                    'success'
                );

                // Tạo lại session ID để tăng bảo mật.
                session_regenerate_id(true);

                // Lưu thông tin tài khoản vào session.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
$_SESSION['password_changed_at'] = $user['password_changed_at'];
                // Điều hướng theo quyền.
                if ($user['role'] === 'admin') {

                    header('Location: dashboard.php');

                } else {

                    header('Location: technician/dashboard.php');

                }

                exit;
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

    <title>Đăng nhập - Computer Service</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 35px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        h1 {
            margin: 0 0 8px;
            text-align: center;
            color: #1e293b;
            font-size: 25px;
        }

        .subtitle {
            margin: 0 0 25px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }

        label {
            display: block;
            margin: 17px 0 7px;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            margin-top: 25px;
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

        .error {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 7px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 14px;
        }

    </style>

</head>

<body>

<div class="login-box">

    <h1>Computer Service</h1>

    <p class="subtitle">
        Đăng nhập hệ thống quản lý sửa chữa
    </p>

    <?php if ($loi !== ''): ?>

        <div class="error">
            <?php
            echo htmlspecialchars(
                $loi,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <label for="username">
            Tên đăng nhập
        </label>

        <input
            type="text"
            id="username"
            name="username"
            autocomplete="username"
            required
        >

        <label for="password">
            Mật khẩu
        </label>

        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">
            Đăng nhập
        </button>

    </form>

</div>

</body>

</html>
