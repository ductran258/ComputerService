
<?php

require_once __DIR__ . '/config/database.php';

// 1. HAM BAO VE DU LIEU
function eCapNhatHD($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// 2. HAM DINH DANG TIEN
function tienCapNhatHD($value)
{
    return number_format((float) $value, 0, ',', '.') . ' đ';
}

// 3. LAY ID HOA DON
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var(
        $_POST['invoice_id'] ?? null,
        FILTER_VALIDATE_INT
    );
}

if (!$id || $id <= 0) {
    exit('ID hóa đơn không hợp lệ.');
}

// 4. LAY THONG TIN HOA DON
$stmt = $pdo->prepare("
    SELECT
        i.*,
        rt.ticket_code,
        c.full_name AS customer_name
    FROM invoices i
    INNER JOIN repair_tickets rt
        ON i.ticket_id = rt.id
    INNER JOIN customers c
        ON rt.customer_id = c.id
    WHERE i.id = :id
");

$stmt->execute(['id' => $id]);

$invoice = $stmt->fetch();

if (!$invoice) {
    exit('Không tìm thấy hóa đơn.');
}

// 5. KHOI TAO THONG BAO
$loi = '';

// 6. XU LY CAP NHAT HOA DON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $laborCost = $_POST['labor_cost'] ?? '';
    $discount = $_POST['discount'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? '';
    $paymentStatus = $_POST['payment_status'] ?? '';

    // Kiem tra tien cong
    if (
        !is_numeric($laborCost)
        || (float) $laborCost < 0
    ) {
        $loi = 'Tiền công phải là số và không được âm.';
    }

    // Kiem tra giam gia
    elseif (
        !is_numeric($discount)
        || (float) $discount < 0
    ) {
        $loi = 'Giảm giá phải là số và không được âm.';
    }

    // Kiem tra phuong thuc thanh toan
    elseif (!in_array(
        $paymentMethod,
        ['cash', 'bank_transfer'],
        true
    )) {
        $loi = 'Phương thức thanh toán không hợp lệ.';
    }

    // Kiem tra trang thai thanh toan
    elseif (!in_array(
        $paymentStatus,
        ['paid', 'unpaid'],
        true
    )) {
        $loi = 'Trạng thái thanh toán không hợp lệ.';
    }

    else {

        $laborCost = (float) $laborCost;
        $discount = (float) $discount;

        try {

            // BAT DAU GIAO DICH
            $pdo->beginTransaction();

            // KHOA HOA DON DE TRANH CAP NHAT DONG THOI
            $stmt = $pdo->prepare("
                SELECT id, ticket_id, payment_status, paid_at
FROM invoices
WHERE id = :id
FOR UPDATE
            ");

            $stmt->execute(['id' => $id]);

            $invoiceLocked = $stmt->fetch();

            if (!$invoiceLocked) {
                throw new Exception('Không tìm thấy hóa đơn.');
            }

            // TINH LAI TONG TIEN LINH KIEN TU CHI TIET
            $stmt = $pdo->prepare("
                SELECT COALESCE(
                    SUM(quantity * unit_price),
                    0
                )
                FROM ticket_parts
                WHERE ticket_id = :ticket_id
            ");

            $stmt->execute([
                'ticket_id' => $invoiceLocked['ticket_id']
            ]);

            $partsTotal = (float) $stmt->fetchColumn();

            // TINH TONG TIEN
            $subtotal = $laborCost + $partsTotal;

            if ($discount > $subtotal) {
                throw new Exception(
                    'Giảm giá không được lớn hơn tổng tiền trước giảm giá.'
                );
            }

            $totalAmount = $subtotal - $discount;

            // XAC DINH THOI DIEM THANH TOAN
$paidAt = $invoiceLocked['paid_at'];

if ($paymentStatus === 'paid') {

    // Chuyen tu chua thanh toan sang da thanh toan
    if ($invoiceLocked['payment_status'] !== 'paid') {
        $paidAt = date('Y-m-d H:i:s');
    }

} else {

    // Chuyen ve chua thanh toan
    $paidAt = null;
}

// CAP NHAT HOA DON
$stmt = $pdo->prepare("
    UPDATE invoices
    SET
        labor_cost = :labor_cost,
        parts_total = :parts_total,
        discount = :discount,
        total_amount = :total_amount,
        payment_method = :payment_method,
        payment_status = :payment_status,
        paid_at = :paid_at
    WHERE id = :id
");

$stmt->execute([
    'labor_cost' => $laborCost,
    'parts_total' => $partsTotal,
    'discount' => $discount,
    'total_amount' => $totalAmount,
    'payment_method' => $paymentMethod,
    'payment_status' => $paymentStatus,
    'paid_at' => $paidAt,
    'id' => $id
]);

            // HOAN TAT GIAO DICH
            $pdo->commit();

            header(
                'Location: chi_tiet_hoa_don.php?id=' . $id . '&updated=1'
            );
            exit;

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $loi = $e->getMessage();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $loi = 'Có lỗi xảy ra khi cập nhật hóa đơn. Vui lòng thử lại.';
        }
    }

    // Cap nhat gia tri hien thi neu co loi
    $invoice['labor_cost'] = $laborCost;
    $invoice['discount'] = $discount;
    $invoice['payment_method'] = $paymentMethod;
    $invoice['payment_status'] = $paymentStatus;
}

// 7. LAY LAI TONG TIEN LINH KIEN
$stmt = $pdo->prepare("
    SELECT COALESCE(
        SUM(quantity * unit_price),
        0
    )
    FROM ticket_parts
    WHERE ticket_id = :ticket_id
");

$stmt->execute([
    'ticket_id' => $invoice['ticket_id']
]);

$partsTotal = (float) $stmt->fetchColumn();

// TINH TONG TIEN DU KIEN
$tongTruocGiam = (float) $invoice['labor_cost'] + $partsTotal;
$tongSauGiam = $tongTruocGiam - (float) $invoice['discount'];

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cập nhật hóa đơn</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            padding: 30px;
            color: #222;
        }

        .container {
            max-width: 750px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        h1 {
            margin-top: 0;
        }

        .info {
            background: #f0f2f5;
            padding: 15px;
            border-radius: 6px;
            line-height: 1.9;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 17px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 11px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
        }

        .calculation {
            background: #f0f2f5;
            padding: 18px;
            border-radius: 6px;
            margin-top: 22px;
            line-height: 2;
        }

        .total {
            font-size: 22px;
            font-weight: bold;
            color: #2563eb;
        }

        button, .back {
            display: inline-block;
            padding: 12px 18px;
            margin-top: 20px;
            border: none;
            border-radius: 5px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .back {
            background: #64748b;
            margin-left: 8px;
        }

        .error {
            padding: 12px;
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .hint {
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>Cập nhật hóa đơn</h1>

        <div class="info">

            <strong>Mã hóa đơn:</strong>

            HD-<?php echo str_pad(
                (string) $invoice['id'],
                5,
                '0',
                STR_PAD_LEFT
            ); ?>

            <br>

            <strong>Mã phiếu sửa chữa:</strong>

            <?php echo eCapNhatHD($invoice['ticket_code']); ?>

            <br>

            <strong>Khách hàng:</strong>

            <?php echo eCapNhatHD($invoice['customer_name']); ?>

        </div>

        <?php if ($loi !== ''): ?>

            <div class="error">
                <?php echo eCapNhatHD($loi); ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="invoice_id"
                value="<?php echo (int) $id; ?>"
            >

            <label>Tiền công sửa chữa (VNĐ)</label>

            <input
                type="number"
                name="labor_cost"
                min="0"
                step="1"
                value="<?php echo eCapNhatHD($invoice['labor_cost']); ?>"
                required
            >

            <label>Tiền linh kiện (VNĐ)</label>

            <input
                type="text"
                value="<?php echo eCapNhatHD($partsTotal); ?>"
                readonly
            >

            <p class="hint">
                Tiền linh kiện được hệ thống tự động tính từ danh sách linh kiện đã sử dụng.
            </p>

            <label>Giảm giá (VNĐ)</label>

            <input
                type="number"
                name="discount"
                min="0"
                step="1"
                value="<?php echo eCapNhatHD($invoice['discount']); ?>"
                required
            >

            <label>Phương thức thanh toán</label>

            <select name="payment_method" required>

                <option
                    value="cash"
                    <?php echo $invoice['payment_method'] === 'cash' ? 'selected' : ''; ?>
                >
                    Tiền mặt
                </option>

                <option
                    value="bank_transfer"
                    <?php echo $invoice['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>
                >
                    Chuyển khoản
                </option>

            </select>

            <label>Trạng thái thanh toán</label>

            <select name="payment_status" required>

                <option
                    value="unpaid"
                    <?php echo $invoice['payment_status'] === 'unpaid' ? 'selected' : ''; ?>
                >
                    Chưa thanh toán
                </option>

                <option
                    value="paid"
                    <?php echo $invoice['payment_status'] === 'paid' ? 'selected' : ''; ?>
                >
                    Đã thanh toán
                </option>

            </select>

            <div class="calculation">

                <div>
                    Tiền công:
                    <strong>
                        <?php echo tienCapNhatHD($invoice['labor_cost']); ?>
                    </strong>
                </div>

                <div>
                    Tiền linh kiện:
                    <strong>
                        <?php echo tienCapNhatHD($partsTotal); ?>
                    </strong>
                </div>

                <div>
                    Tổng trước giảm giá:
                    <strong>
                        <?php echo tienCapNhatHD($tongTruocGiam); ?>
                    </strong>
                </div>

                <div>
                    Giảm giá:
                    <strong>
                        <?php echo tienCapNhatHD($invoice['discount']); ?>
                    </strong>
                </div>

                <hr>

                <div class="total">
                    Tổng thanh toán:
                    <?php echo tienCapNhatHD($tongSauGiam); ?>
                </div>

            </div>

            <button type="submit">
                Lưu thay đổi hóa đơn
            </button>

            <a
                href="chi_tiet_hoa_don.php?id=<?php echo (int) $id; ?>"
                class="back"
            >
                Quay lại
            </a>

        </form>

    </div>

</div>

</body>

</html>
