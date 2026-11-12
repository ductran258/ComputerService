
<?php
require_once __DIR__ . '/includes/auth.php';

requireAdmin();
// 1. KET NOI DATABASE
require_once __DIR__ . '/config/database.php';

$thongBao = '';
$loi = '';

// 2. LAY ID THIET BI TU URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    exit('ID thiết bị không hợp lệ.');
}

// 3. LAY THONG TIN THIET BI
$sqlDevice = "
    SELECT *
    FROM devices
    WHERE id = :id
";

$stmtDevice = $pdo->prepare($sqlDevice);
$stmtDevice->execute([
    ':id' => $id
]);

$device = $stmtDevice->fetch();

if (!$device) {
    exit('Không tìm thấy thiết bị.');
}

// 4. LAY DANH SACH KHACH HANG
$sqlCustomers = "
    SELECT id, full_name, phone
    FROM customers
    ORDER BY full_name ASC
";

$stmtCustomers = $pdo->prepare($sqlCustomers);
$stmtCustomers->execute();

$customers = $stmtCustomers->fetchAll();

// 5. XU LY KHI NGUOI DUNG GUI FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customerId = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
    $deviceType = trim($_POST['device_type'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // 6. KIEM TRA DU LIEU
    if (!$customerId || $customerId <= 0) {

        $loi = 'Vui lòng chọn khách hàng.';

    } elseif ($deviceType === '') {

        $loi = 'Vui lòng nhập loại thiết bị.';

    } else {

        try {

            // 7. KIEM TRA KHACH HANG CO TON TAI KHONG
            $sqlCheck = "
                SELECT id
                FROM customers
                WHERE id = :customer_id
            ";

            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([
                ':customer_id' => $customerId
            ]);

            if (!$stmtCheck->fetch()) {

                $loi = 'Khách hàng không tồn tại.';

            } else {

                // 8. CAP NHAT THONG TIN THIET BI
                $sqlUpdate = "
                    UPDATE devices
                    SET
                        customer_id = :customer_id,
                        device_type = :device_type,
                        brand = :brand,
                        model = :model,
                        serial_number = :serial_number,
                        description = :description
                    WHERE id = :id
                ";

                $stmtUpdate = $pdo->prepare($sqlUpdate);

                $stmtUpdate->execute([
                    ':customer_id' => $customerId,
                    ':device_type' => $deviceType,
                    ':brand' => $brand !== '' ? $brand : null,
                    ':model' => $model !== '' ? $model : null,
                    ':serial_number' => $serialNumber !== '' ? $serialNumber : null,
                    ':description' => $description !== '' ? $description : null,
                    ':id' => $id
                ]);

                $thongBao = 'Cập nhật thiết bị thành công!';

                // CAP NHAT LAI DU LIEU DE HIEN THI
                $device['customer_id'] = $customerId;
                $device['device_type'] = $deviceType;
                $device['brand'] = $brand;
                $device['model'] = $model;
                $device['serial_number'] = $serialNumber;
                $device['description'] = $description;
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());
            $loi = 'Đã xảy ra lỗi khi cập nhật thiết bị.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sửa thông tin thiết bị</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            padding: 30px;
        }

        .container {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        h1 {
            color: #222;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 11px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        button {
            margin-top: 20px;
            padding: 12px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .success {
            color: green;
            margin-bottom: 15px;
        }

        .error {
            color: red;
            margin-bottom: 15px;
        }

        a {
            display: inline-block;
            margin-top: 15px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Sửa thông tin thiết bị</h1>

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

        <label>Khách hàng sở hữu</label>

        <select name="customer_id" required>

            <option value="">-- Chọn khách hàng --</option>

            <?php foreach ($customers as $customer): ?>

                <option
                    value="<?php echo (int) $customer['id']; ?>"
                    <?php echo ((int) $device['customer_id'] === (int) $customer['id']) ? 'selected' : ''; ?>
                >
                    <?php
                    echo htmlspecialchars(
                        $customer['full_name'] . ' - ' . $customer['phone']
                    );
                    ?>
                </option>

            <?php endforeach; ?>

        </select>

        <label>Loại thiết bị</label>

        <select name="device_type" required>

            <?php
            $deviceTypes = ['Laptop', 'PC', 'Máy in', 'Màn hình', 'Khác'];
            ?>

            <option value="">-- Chọn loại thiết bị --</option>

            <?php foreach ($deviceTypes as $type): ?>

                <option
                    value="<?php echo htmlspecialchars($type); ?>"
                    <?php echo ($device['device_type'] === $type) ? 'selected' : ''; ?>
                >
                    <?php echo htmlspecialchars($type); ?>
                </option>

            <?php endforeach; ?>

        </select>

        <label>Thương hiệu</label>

        <input
            type="text"
            name="brand"
            value="<?php echo htmlspecialchars($device['brand'] ?? ''); ?>"
        >

        <label>Model</label>

        <input
            type="text"
            name="model"
            value="<?php echo htmlspecialchars($device['model'] ?? ''); ?>"
        >

        <label>Số Serial</label>

        <input
            type="text"
            name="serial_number"
            value="<?php echo htmlspecialchars($device['serial_number'] ?? ''); ?>"
        >

        <label>Mô tả tình trạng thiết bị</label>

        <textarea name="description"><?php echo htmlspecialchars($device['description'] ?? ''); ?></textarea>

        <button type="submit">Lưu thay đổi</button>

    </form>

    <a href="thiet_bi.php">Quay lại danh sách thiết bị</a>

</div>

</body>
</html>
