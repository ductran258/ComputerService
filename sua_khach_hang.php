
<?php
require_once __DIR__ . '/includes/auth.php';

requireAdmin();
require_once __DIR__ . '/config/database.php';

$loi = '';
$thongBao = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    exit('ID khách hàng không hợp lệ.');
}

// Lấy thông tin khách hàng
$stmt = $pdo->prepare(
    "SELECT * FROM customers WHERE id = :id"
);

$stmt->execute([':id' => $id]);

$customer = $stmt->fetch();

if (!$customer) {
    exit('Không tìm thấy khách hàng.');
}

// Khởi tạo dữ liệu hiển thị
$fullName = $customer['full_name'];
$phone = $customer['phone'];
$email = $customer['email'] ?? '';
$address = $customer['address'] ?? '';

// Xử lý khi gửi biểu mẫu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($fullName === '') {
        $loi = 'Vui lòng nhập họ và tên.';
    } elseif ($phone === '') {
        $loi = 'Vui lòng nhập số điện thoại.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loi = 'Email không hợp lệ.';
    } else {

        try {

            $sql = "UPDATE customers SET
                        full_name = :full_name,
                        phone = :phone,
                        email = :email,
                        address = :address
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':full_name' => $fullName,
                ':phone' => $phone,
                ':email' => $email !== '' ? $email : null,
                ':address' => $address !== '' ? $address : null,
                ':id' => $id
            ]);

            $thongBao = 'Cập nhật khách hàng thành công!';

        } catch (PDOException $e) {

            error_log($e->getMessage());
            $loi = 'Không thể cập nhật khách hàng.';

        }
    }
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sửa thông tin khách hàng</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            padding: 30px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            margin-top: 7px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        button {
            margin-top: 20px;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Sửa thông tin khách hàng</h1>

    <?php if ($thongBao !== ''): ?>
        <p class="success">
            <?php echo htmlspecialchars($thongBao); ?>
        </p>
    <?php endif; ?>

    <?php if ($loi !== ''): ?>
        <p class="error">
            <?php echo htmlspecialchars($loi); ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <label>Họ và tên *</label>
        <input
            type="text"
            name="full_name"
            value="<?php echo htmlspecialchars($fullName); ?>"
            required
        >

        <label>Số điện thoại *</label>
        <input
            type="text"
            name="phone"
            value="<?php echo htmlspecialchars($phone); ?>"
            required
        >

        <label>Email</label>
        <input
            type="email"
            name="email"
            value="<?php echo htmlspecialchars($email); ?>"
        >

        <label>Địa chỉ</label>
        <textarea name="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea>

        <button type="submit">Lưu thay đổi</button>

    </form>

    <a href="khach_hang.php">← Quay lại danh sách</a>

</div>

</body>
</html>
