
<?php

require_once __DIR__ . '/config/database.php';

function eThemHoaDon($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function tienThemHoaDon($value): string
{
    return number_format((float) $value, 0, ',', '.') . ' đ';
}

$loi = '';
$ticketId = 0;
$laborCost = '';
$discount = '0';
$paymentMethod = 'cash';
$paymentStatus = 'unpaid';

// =====================================================
// 1. LAY DANH SACH PHIEU SUA CHUA DU DIEU KIEN
// =====================================================

$stmt = $pdo->query("
    SELECT
        rt.id,
        rt.ticket_code,
        rt.status,
        c.full_name AS customer_name,
        c.phone AS customer_phone,
        COALESCE(tp.parts_total, 0) AS parts_total
    FROM repair_tickets rt

    INNER JOIN customers c
        ON rt.customer_id = c.id

    LEFT JOIN (
        SELECT
            ticket_id,
            SUM(quantity * unit_price) AS parts_total
        FROM ticket_parts
        GROUP BY ticket_id
    ) tp ON rt.id = tp.ticket_id

    WHERE rt.status IN ('completed', 'delivered')
    AND NOT EXISTS (
        SELECT 1
        FROM invoices i
        WHERE i.ticket_id = rt.id
    )

    ORDER BY rt.id DESC
");

$tickets = $stmt->fetchAll();

// =====================================================
// 2. XU LY KHI NHAN NUT LUU HOA DON
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $laborCost = trim($_POST['labor_cost'] ?? '');
    $discount = trim($_POST['discount'] ?? '0');
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $paymentStatus = $_POST['payment_status'] ?? 'unpaid';

    // KIEM TRA DU LIEU

    if ($ticketId <= 0) {

        $loi = 'Vui lòng chọn phiếu sửa chữa.';

    } elseif (
        $laborCost === '' ||
        !is_numeric($laborCost) ||
        (float) $laborCost < 0
    ) {

        $loi = 'Tiền công không hợp lệ.';

    } elseif (
        $discount === '' ||
        !is_numeric($discount) ||
        (float) $discount < 0
    ) {

        $loi = 'Giảm giá không hợp lệ.';

    } elseif (!in_array($paymentMethod, ['cash', 'bank_transfer'], true)) {

        $loi = 'Phương thức thanh toán không hợp lệ.';

    } elseif (!in_array($paymentStatus, ['paid', 'unpaid'], true)) {

        $loi = 'Trạng thái thanh toán không hợp lệ.';

    } else {

        try {

            $pdo->beginTransaction();

            // KIEM TRA PHIEU SUA CHUA

            $stmt = $pdo->prepare("
                SELECT id, status
                FROM repair_tickets
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([
                ':id' => $ticketId
            ]);

            $ticket = $stmt->fetch();

            if (
                !$ticket ||
                !in_array($ticket['status'], ['completed', 'delivered'], true)
            ) {

                throw new Exception(
                    'Phiếu sửa chữa không tồn tại hoặc chưa hoàn thành.'
                );

            }

            // KIEM TRA HOA DON DA TON TAI

            $stmt = $pdo->prepare("
                SELECT id
                FROM invoices
                WHERE ticket_id = :ticket_id
                LIMIT 1
            ");

            $stmt->execute([
                ':ticket_id' => $ticketId
            ]);

            if ($stmt->fetch()) {

                throw new Exception(
                    'Phiếu sửa chữa này đã có hóa đơn.'
                );

            }

            // TINH TIEN LINH KIEN

            $stmt = $pdo->prepare("
                SELECT COALESCE(
                    SUM(quantity * unit_price),
                    0
                )
                FROM ticket_parts
                WHERE ticket_id = :ticket_id
            ");

            $stmt->execute([
                ':ticket_id' => $ticketId
            ]);

            $partsTotal = (float) $stmt->fetchColumn();

            // TINH TONG TIEN

            $laborCostValue = (float) $laborCost;
            $discountValue = (float) $discount;

            $totalAmount =
                $laborCostValue +
                $partsTotal -
                $discountValue;

            if ($discountValue > $laborCostValue + $partsTotal) {

                throw new Exception(
                    'Giảm giá không được lớn hơn tổng chi phí.'
                );

            }

            // LUU HOA DON

            $stmt = $pdo->prepare("
                INSERT INTO invoices (
                    ticket_id,
                    labor_cost,
                    parts_total,
                    discount,
                    total_amount,
                    payment_method,
                    payment_status,
                    paid_at
                )
                VALUES (
                    :ticket_id,
                    :labor_cost,
                    :parts_total,
                    :discount,
                    :total_amount,
                    :payment_method,
                    :payment_status,
                    :paid_at
                )
            ");

            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':labor_cost' => $laborCostValue,
                ':parts_total' => $partsTotal,
                ':discount' => $discountValue,
                ':total_amount' => $totalAmount,
                ':payment_method' => $paymentMethod,
                ':payment_status' => $paymentStatus,
                ':paid_at' => $paymentStatus === 'paid'
                    ? date('Y-m-d H:i:s')
                    : null
            ]);

            // HOAN TAT GIAO DICH

            $pdo->commit();

            header('Location: hoa_don.php');
            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $loi = 'Không thể tạo hóa đơn. Vui lòng kiểm tra lại dữ liệu.';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $loi = $e->getMessage();

        }

    }

}

// =====================================================
// 3. LAY LAI DANH SACH PHIEU SAU KHI XU LY POST
// =====================================================

$stmt = $pdo->query("
    SELECT
        rt.id,
        rt.ticket_code,
        rt.status,
        c.full_name AS customer_name,
        c.phone AS customer_phone,
        COALESCE(tp.parts_total, 0) AS parts_total
    FROM repair_tickets rt

    INNER JOIN customers c
        ON rt.customer_id = c.id

    LEFT JOIN (
        SELECT
            ticket_id,
            SUM(quantity * unit_price) AS parts_total
        FROM ticket_parts
        GROUP BY ticket_id
    ) tp ON rt.id = tp.ticket_id

    WHERE rt.status IN ('completed', 'delivered')
    AND NOT EXISTS (
        SELECT 1
        FROM invoices i
        WHERE i.ticket_id = rt.id
    )

    ORDER BY rt.id DESC
");

$tickets = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tạo hóa đơn mới</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 28px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f6fb;
            color: #1e293b;
        }

        .page-container {
            max-width: 1050px;
            margin: 0 auto;
        }

        /* HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: #dbeafe;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .header-title h1 {
            margin: 0 0 6px;
            font-size: 25px;
            color: #172554;
        }

        .header-title p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            background: #fff;
            border: 1px solid #dbe2ea;
            border-radius: 9px;
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-back:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* MAIN LAYOUT */

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(280px, 0.85fr);
            gap: 22px;
            align-items: start;
        }

        .card {
            background: #fff;
            border: 1px solid #e5eaf1;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 22px;
            border-bottom: 1px solid #edf0f5;
        }

        .card-header h2 {
            margin: 0;
            font-size: 16px;
            color: #1e293b;
        }

        .card-header p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .card-body {
            padding: 22px;
        }

        /* FORM */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .required {
            color: #dc2626;
        }

        .form-control {
            width: 100%;
            min-height: 44px;
            padding: 11px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            background: #fff;
            color: #1e293b;
            font-size: 14px;
            transition: 0.2s;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .form-hint {
            display: block;
            margin-top: 7px;
            color: #94a3b8;
            font-size: 11px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* CUSTOMER INFORMATION */

        .customer-box {
            margin: 5px 0 22px;
            padding: 17px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .customer-box-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .customer-box-title i {
            color: #3b82f6;
        }

        .customer-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .customer-detail span {
            display: block;
            margin-bottom: 5px;
            color: #94a3b8;
            font-size: 11px;
        }

        .customer-detail strong {
            display: block;
            color: #334155;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        /* BUTTONS */

        .form-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #edf0f5;
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 44px;
            padding: 0 22px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-save:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
        }

        .btn-save:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            background: #fff;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-cancel:hover {
            background: #f8fafc;
        }

        /* SUMMARY */

        .summary-card {
            position: sticky;
            top: 20px;
        }

        .summary-header {
            padding: 19px 20px;
            background: #172554;
            color: #fff;
        }

        .summary-header h2 {
            margin: 0;
            font-size: 15px;
        }

        .summary-header p {
            margin: 6px 0 0;
            color: #bfdbfe;
            font-size: 11px;
        }

        .summary-body {
            padding: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 17px;
            color: #64748b;
            font-size: 12px;
        }

        .summary-row strong {
            color: #334155;
            font-size: 13px;
            text-align: right;
        }

        .summary-divider {
            border: none;
            border-top: 1px dashed #dbe2ea;
            margin: 18px 0;
        }

        .summary-total {
            margin-top: 5px;
            padding: 16px;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 10px;
        }

        .summary-total span {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
        }

        .summary-total strong {
            display: block;
            color: #1d4ed8;
            font-size: 24px;
            overflow-wrap: anywhere;
        }

        .summary-note {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin-top: 18px;
            padding: 12px;
            border-radius: 8px;
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            line-height: 1.6;
        }

        .summary-note i {
            margin-top: 2px;
            color: #3b82f6;
        }

        /* ERROR */

        .error {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 14px 16px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 9px;
            color: #b91c1c;
            font-size: 13px;
            line-height: 1.5;
        }

        .error i {
            margin-top: 2px;
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 45px 25px;
            text-align: center;
        }

        .empty-icon {
            width: 68px;
            height: 68px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #fff7ed;
            color: #ea580c;
            font-size: 27px;
        }

        .empty-state h2 {
            margin: 0 0 9px;
            color: #334155;
            font-size: 17px;
        }

        .empty-state p {
            max-width: 520px;
            margin: 0 auto 22px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.7;
        }

        /* RESPONSIVE */

        @media (max-width: 800px) {

            body {
                padding: 18px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .summary-card {
                position: static;
            }

        }

        @media (max-width: 520px) {

            body {
                padding: 12px;
            }

            .page-header {
                align-items: flex-start;
            }

            .header-title h1 {
                font-size: 21px;
            }

            .header-icon {
                width: 45px;
                height: 45px;
            }

            .card-body {
                padding: 16px;
            }

            .card-header {
                padding: 17px;
            }

            .form-row,
            .customer-details {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-save,
            .btn-cancel {
                width: 100%;
            }

            .summary-total strong {
                font-size: 21px;
            }

        }

    </style>

</head>

<body>

<div class="page-container">

    <!-- HEADER -->

    <div class="page-header">

        <div class="header-title">

            <div class="header-icon">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>

            <div>
                <h1>Tạo hóa đơn mới</h1>
                <p>Tạo hóa đơn thanh toán cho phiếu sửa chữa đã hoàn thành</p>
            </div>

        </div>

        <a href="hoa_don.php" class="btn-back" target="mainContent">
            <i class="fa-solid fa-arrow-left"></i>
            Quay lại
        </a>

    </div>

    <?php if ($loi !== ''): ?>

        <div class="error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?php echo eThemHoaDon($loi); ?></span>
        </div>

    <?php endif; ?>

    <?php if (count($tickets) > 0): ?>

        <div class="content-grid">

            <!-- FORM TAO HOA DON -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        <i class="fa-solid fa-pen-to-square"></i>
                        Thông tin hóa đơn
                    </h2>

                    <p>
                        Vui lòng chọn phiếu sửa chữa và nhập thông tin thanh toán.
                    </p>

                </div>

                <div class="card-body">

                    <form method="POST" id="invoiceForm">

                        <div class="form-group">

                            <label for="ticket_id">
                                Phiếu sửa chữa
                                <span class="required">*</span>
                            </label>

                            <select
                                name="ticket_id"
                                id="ticket_id"
                                class="form-control"
                                required
                            >

                                <option value="">
                                    -- Chọn phiếu sửa chữa --
                                </option>

                                <?php foreach ($tickets as $ticket): ?>

                                    <option
                                        value="<?php echo (int) $ticket['id']; ?>"
                                        data-parts="<?php echo (float) $ticket['parts_total']; ?>"
                                        data-customer="<?php echo eThemHoaDon($ticket['customer_name']); ?>"
                                        data-phone="<?php echo eThemHoaDon($ticket['customer_phone']); ?>"
                                        <?php echo $ticketId === (int) $ticket['id'] ? 'selected' : ''; ?>
                                    >
                                        <?php
                                        echo eThemHoaDon($ticket['ticket_code']);
                                        ?>
                                        -
                                        <?php
                                        echo eThemHoaDon($ticket['customer_name']);
                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <span class="form-hint">
                                Chỉ hiển thị phiếu đã hoàn thành hoặc đã bàn giao và chưa có hóa đơn.
                            </span>

                        </div>

                        <!-- THONG TIN KHACH HANG -->

                        <div class="customer-box">

                            <div class="customer-box-title">
                                <i class="fa-solid fa-user"></i>
                                THÔNG TIN KHÁCH HÀNG
                            </div>

                            <div class="customer-details">

                                <div class="customer-detail">
                                    <span>Khách hàng</span>
                                    <strong id="customerName">Chưa chọn</strong>
                                </div>

                                <div class="customer-detail">
                                    <span>Số điện thoại</span>
                                    <strong id="customerPhone">Chưa chọn</strong>
                                </div>

                            </div>

                        </div>

                        <!-- CHI PHI -->

                        <div class="form-row">

                            <div class="form-group">

                                <label for="labor_cost">
                                    Tiền công sửa chữa (VNĐ)
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="number"
                                    name="labor_cost"
                                    id="labor_cost"
                                    class="form-control"
                                    min="0"
                                    step="1000"
                                    value="<?php echo eThemHoaDon($laborCost); ?>"
                                    placeholder="Nhập tiền công"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label for="discount">
                                    Giảm giá (VNĐ)
                                </label>

                                <input
                                    type="number"
                                    name="discount"
                                    id="discount"
                                    class="form-control"
                                    min="0"
                                    step="1000"
                                    value="<?php echo eThemHoaDon($discount); ?>"
                                    placeholder="Nhập số tiền giảm"
                                >

                            </div>

                        </div>

                        <!-- THANH TOAN -->

                        <div class="form-group">

                            <label for="payment_method">
                                Phương thức thanh toán
                                <span class="required">*</span>
                            </label>

                            <select
                                name="payment_method"
                                id="payment_method"
                                class="form-control"
                                required
                            >

                                <option
                                    value="cash"
                                    <?php echo $paymentMethod === 'cash' ? 'selected' : ''; ?>
                                >
                                    Tiền mặt
                                </option>

                                <option
                                    value="bank_transfer"
                                    <?php echo $paymentMethod === 'bank_transfer' ? 'selected' : ''; ?>
                                >
                                    Chuyển khoản
                                </option>

                            </select>

                        </div>

                        <div class="form-group">

                            <label for="payment_status">
                                Trạng thái thanh toán
                                <span class="required">*</span>
                            </label>

                            <select
                                name="payment_status"
                                id="payment_status"
                                class="form-control"
                                required
                            >

                                <option
                                    value="unpaid"
                                    <?php echo $paymentStatus === 'unpaid' ? 'selected' : ''; ?>
                                >
                                    Chưa thanh toán
                                </option>

                                <option
                                    value="paid"
                                    <?php echo $paymentStatus === 'paid' ? 'selected' : ''; ?>
                                >
                                    Đã thanh toán
                                </option>

                            </select>

                        </div>

                        <!-- BUTTON -->

                        <div class="form-actions">

                            <button
                                type="submit"
                                class="btn-save"
                                id="saveButton"
                            >
                                <i class="fa-solid fa-floppy-disk"></i>
                                Lưu hóa đơn
                            </button>

                            <a href="hoa_don.php"
                               class="btn-cancel"
                               target="mainContent">
                                Hủy bỏ
                            </a>

                        </div>

                    </form>

                </div>

            </div>

            <!-- HOA DON TAM TINH -->

            <div class="card summary-card">

                <div class="summary-header">

                    <h2>
                        <i class="fa-solid fa-calculator"></i>
                        Tổng kết hóa đơn
                    </h2>

                    <p>Thông tin chi phí được cập nhật tự động</p>

                </div>

                <div class="summary-body">

                    <div class="summary-row">
                        <span>Tiền công sửa chữa</span>
                        <strong id="showLabor">0 đ</strong>
                    </div>

                    <div class="summary-row">
                        <span>Tiền linh kiện</span>
                        <strong id="showParts">0 đ</strong>
                    </div>

                    <div class="summary-row">
                        <span>Giảm giá</span>
                        <strong id="showDiscount">0 đ</strong>
                    </div>

                    <hr class="summary-divider">

                    <div class="summary-total">

                        <span>TỔNG THANH TOÁN</span>

                        <strong id="showTotal">0 đ</strong>

                    </div>

                    <div class="summary-note">

                        <i class="fa-solid fa-circle-info"></i>

                        <span>
                            Tiền linh kiện được lấy tự động từ các linh kiện đã sử dụng trong phiếu sửa chữa.
                            Tổng tiền được tính bằng tiền công cộng tiền linh kiện trừ đi giảm giá.
                        </span>

                    </div>

                </div>

            </div>

        </div>

    <?php else: ?>

        <div class="card">

            <div class="empty-state">

                <div class="empty-icon">
                    <i class="fa-solid fa-file-circle-exclamation"></i>
                </div>

                <h2>Không có phiếu sửa chữa phù hợp</h2>

                <p>
                    Hiện không có phiếu sửa chữa nào đủ điều kiện tạo hóa đơn.
                    Phiếu cần ở trạng thái đã hoàn thành hoặc đã bàn giao,
                    đồng thời chưa có hóa đơn.
                </p>

                <a href="hoa_don.php"
                   class="btn-back"
                   target="mainContent">
                    <i class="fa-solid fa-arrow-left"></i>
                    Quay lại danh sách hóa đơn
                </a>

            </div>

        </div>

    <?php endif; ?>

</div>

<script>

    const ticketSelect = document.getElementById('ticket_id');
    const laborInput = document.getElementById('labor_cost');
    const discountInput = document.getElementById('discount');

    let partsAmount = 0;

    // DINH DANG TIEN TE

    function formatMoney(number) {

        return Number(number).toLocaleString('vi-VN') + ' đ';

    }

    // CAP NHAT TONG TIEN

    function updateInvoice() {

        if (!laborInput || !discountInput) {
            return;
        }

        const labor = Number(laborInput.value) || 0;
        const discount = Number(discountInput.value) || 0;

        const total = Math.max(
            0,
            labor + partsAmount - discount
        );

        document.getElementById('showLabor').textContent =
            formatMoney(labor);

        document.getElementById('showParts').textContent =
            formatMoney(partsAmount);

        document.getElementById('showDiscount').textContent =
            formatMoney(discount);

        document.getElementById('showTotal').textContent =
            formatMoney(total);

    }

    // CAP NHAT THONG TIN PHIEU SUA CHUA

    function updateTicketInfo() {

        if (!ticketSelect) {
            return;
        }

        const selected = ticketSelect.options[ticketSelect.selectedIndex];

        if (!selected || !selected.value) {

            partsAmount = 0;

            document.getElementById('customerName').textContent =
                'Chưa chọn';

            document.getElementById('customerPhone').textContent =
                'Chưa chọn';

            updateInvoice();

            return;

        }

        partsAmount = Number(selected.dataset.parts) || 0;

        document.getElementById('customerName').textContent =
            selected.dataset.customer || 'Chưa có thông tin';

        document.getElementById('customerPhone').textContent =
            selected.dataset.phone || 'Chưa có thông tin';

        updateInvoice();

    }

    // LANG NGHE THAY DOI DU LIEU

    if (ticketSelect) {

        ticketSelect.addEventListener(
            'change',
            updateTicketInfo
        );

    }

    if (laborInput) {

        laborInput.addEventListener(
            'input',
            updateInvoice
        );

    }

    if (discountInput) {

        discountInput.addEventListener(
            'input',
            updateInvoice
        );

    }

    // KHOI TAO KHI MO TRANG

    updateTicketInfo();

    // CHONG NHAN NUT LUU NHIEU LAN

    const invoiceForm = document.getElementById('invoiceForm');
    const saveButton = document.getElementById('saveButton');

    if (invoiceForm && saveButton) {

        invoiceForm.addEventListener('submit', function () {

            saveButton.disabled = true;

            saveButton.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu hóa đơn...';

        });

    }

</script>

</body>
</html>
