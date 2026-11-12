
<?php
require_once __DIR__ . '/includes/auth.php';

requireAdmin();
require_once __DIR__ . '/config/database.php';

$loi = '';
$categories = [
    'storage' => 'Ổ cứng',
    'ram' => 'RAM',
    'cpu' => 'CPU',
    'gpu' => 'Card đồ họa',
    'accessory' => 'Phụ kiện',
    'other' => 'Khác'
];

// Lấy ID linh kiện từ URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    exit('ID linh kiện không hợp lệ.');
}

// Lấy thông tin linh kiện hiện tại
$stmt = $pdo->prepare("SELECT * FROM parts WHERE id = :id");
$stmt->execute(['id' => $id]);
$part = $stmt->fetch();

if (!$part) {
    exit('Không tìm thấy linh kiện.');
}

// Xử lý khi người dùng nhấn nút Cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partName = trim($_POST['part_name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category = $_POST['category'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $importPrice = $_POST['import_price'] ?? '';
    $sellingPrice = $_POST['selling_price'] ?? '';

    // Kiểm tra dữ liệu
    if ($partName === '') {
        $loi = 'Vui lòng nhập tên linh kiện.';
    } elseif (!array_key_exists($category, $categories)) {
        $loi = 'Danh mục linh kiện không hợp lệ.';
    } elseif (
        filter_var($quantity, FILTER_VALIDATE_INT) === false ||
        (int)$quantity < 0
    ) {
        $loi = 'Số lượng phải là số nguyên lớn hơn hoặc bằng 0.';
    } elseif (
        !is_numeric($importPrice) ||
        (float)$importPrice < 0
    ) {
        $loi = 'Giá nhập không hợp lệ.';
    } elseif (
        !is_numeric($sellingPrice) ||
        (float)$sellingPrice < 0
    ) {
        $loi = 'Giá bán không hợp lệ.';
    } else {
        // Kiểm tra SKU có bị trùng với linh kiện khác không
        if ($sku !== '') {
            $stmt = $pdo->prepare("
                SELECT id
                FROM parts
                WHERE sku = :sku AND id != :id
                LIMIT 1
            ");

            $stmt->execute([
                'sku' => $sku,
                'id' => $id
            ]);

            if ($stmt->fetch()) {
                $loi = 'Mã SKU này đã được sử dụng bởi linh kiện khác.';
            }
        }

        // Nếu không có lỗi thì cập nhật database
        if ($loi === '') {
            $stmt = $pdo->prepare("
                UPDATE parts
                SET
                    part_name = :part_name,
                    sku = :sku,
                    category = :category,
                    quantity = :quantity,
                    import_price = :import_price,
                    selling_price = :selling_price
                WHERE id = :id
            ");

            $stmt->execute([
                'part_name' => $partName,
                'sku' => $sku !== '' ? $sku : null,
                'category' => $category,
                'quantity' => (int)$quantity,
                'import_price' => (float)$importPrice,
                'selling_price' => (float)$sellingPrice,
                'id' => $id
            ]);

            header('Location: linh_kien.php?updated=1');
            exit;
        }
    }

    // Giữ lại dữ liệu vừa nhập nếu có lỗi
    $part['part_name'] = $partName;
    $part['sku'] = $sku;
    $part['category'] = $category;
    $part['quantity'] = $quantity;
    $part['import_price'] = $importPrice;
    $part['selling_price'] = $sellingPrice;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa linh kiện</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            margin-top: 20px;
            padding: 11px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }

        .error {
            padding: 10px;
            background: #ffe5e5;
            color: #b00020;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .back {
            display: inline-block;
            margin-left: 10px;
            color: #333;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="container">

    <h2>Sửa thông tin linh kiện</h2>

    <?php if ($loi !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($loi, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Tên linh kiện</label>
        <input
            type="text"
            name="part_name"
            value="<?= htmlspecialchars($part['part_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label>Mã SKU</label>
        <input
            type="text"
            name="sku"
            value="<?= htmlspecialchars($part['sku'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >

        <label>Danh mục</label>
        <select name="category" required>
            <?php foreach ($categories as $value => $label): ?>
                <option
                    value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                    <?= ($part['category'] ?? '') === $value ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Số lượng</label>
        <input
            type="number"
            name="quantity"
            min="0"
            step="1"
            value="<?= htmlspecialchars((string)($part['quantity'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label>Giá nhập (VNĐ)</label>
        <input
            type="number"
            name="import_price"
            min="0"
            step="1"
            value="<?= htmlspecialchars((string)($part['import_price'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label>Giá bán (VNĐ)</label>
        <input
            type="number"
            name="selling_price"
            min="0"
            step="1"
            value="<?= htmlspecialchars((string)($part['selling_price'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <button type="submit">Cập nhật linh kiện</button>

        <a class="back" href="linh_kien.php">Quay lại</a>

    </form>

</div>

</body>
</html>
