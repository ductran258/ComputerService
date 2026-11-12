
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// =====================================================
// 1. KHỞI TẠO CSRF
// =====================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================================
// 2. HÀM HỖ TRỢ
// =====================================================

function eHoaDon($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function tienVND($value): string
{
    return number_format((float) $value, 0, ',', '.') . ' đ';
}

// =====================================================
// 3. LẤY DANH SÁCH HÓA ĐƠN
// =====================================================

$stmt = $pdo->query("
    SELECT
        i.id,
        i.ticket_id,
        i.labor_cost,
        i.parts_total,
        i.discount,
        i.total_amount,
        i.payment_method,
        i.payment_status,
        i.paid_at,
        i.created_at,

        rt.ticket_code,
        rt.status AS ticket_status,

        c.full_name AS customer_name,
        c.phone AS customer_phone

    FROM invoices i

    INNER JOIN repair_tickets rt
        ON i.ticket_id = rt.id

    INNER JOIN customers c
        ON rt.customer_id = c.id

    ORDER BY i.created_at DESC, i.id DESC
");

$invoices = $stmt->fetchAll();

// =====================================================
// 4. THỐNG KÊ HÓA ĐƠN
// =====================================================

$tongHoaDon = count($invoices);
$tongDoanhThu = 0;
$tongChuaThanhToan = 0;
$tongDaThanhToan = 0;

foreach ($invoices as $invoice) {

    if ($invoice['payment_status'] === 'paid') {
        $tongDaThanhToan += (float) $invoice['total_amount'];
    } else {
        $tongChuaThanhToan += (float) $invoice['total_amount'];
    }

    $tongDoanhThu += (float) $invoice['total_amount'];
}

// =====================================================
// 5. NHÃN PHƯƠNG THỨC THANH TOÁN
// =====================================================

$phuongThuc = [
    'cash' => 'Tiền mặt',
    'bank_transfer' => 'Chuyển khoản'
];

?>

<style>
/* =====================================================
   MODERN PROFESSIONAL - QUẢN LÝ HÓA ĐƠN
===================================================== */

.invoice-container {
    min-height: 100vh;
    padding: 26px;
    background: #f4f7fb;
    color: #1e293b;
    font-family: Arial, sans-serif;
    box-sizing: border-box;
}

.invoice-container * {
    box-sizing: border-box;
}

/* HEADER */

.invoice-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;
    margin-bottom: 24px;
}

.invoice-header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.invoice-header-icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 14px;
    background: #dbeafe;
    color: #2563eb;
    font-size: 23px;
}

.invoice-header h2 {
    margin: 0 0 7px;
    color: #172554;
    font-size: 23px;
    font-weight: 700;
}

.invoice-header p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}

.invoice-header-badge {
    padding: 9px 13px;
    border-radius: 8px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 600;
}

/* BUTTON */

.btn-add {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 11px 17px;
    border-radius: 8px;
    background: #2563eb;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: 0.2s;
}

.btn-add:hover {
    background: #1d4ed8;
    color: #fff;
}

/* STATISTICS */

.invoice-statistics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 23px;
}

.invoice-stat-card {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-width: 0;
    padding: 21px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e8edf5;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
}

.invoice-stat-card::before {
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #2563eb;
    content: "";
}

.invoice-stat-card.green::before {
    background: #16a34a;
}

.invoice-stat-card.orange::before {
    background: #f59e0b;
}

.invoice-stat-card.red::before {
    background: #ef4444;
}

.invoice-stat-info {
    min-width: 0;
}

.invoice-stat-info p {
    margin: 0 0 11px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.invoice-stat-info h3 {
    margin: 0;
    color: #172554;
    font-size: 23px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.invoice-stat-info small {
    display: block;
    margin-top: 7px;
    color: #94a3b8;
    font-size: 11px;
}

.invoice-stat-icon {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 12px;
    background: #dbeafe;
    color: #2563eb;
    font-size: 19px;
}

.invoice-stat-card.green .invoice-stat-icon {
    background: #dcfce7;
    color: #16a34a;
}

.invoice-stat-card.orange .invoice-stat-icon {
    background: #fef3c7;
    color: #d97706;
}

.invoice-stat-card.red .invoice-stat-icon {
    background: #fee2e2;
    color: #dc2626;
}

/* MAIN CARD */

.invoice-card {
    min-width: 0;
    padding: 23px;
    background: #fff;
    border: 1px solid #e8edf5;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
}

.invoice-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.invoice-card-title {
    display: flex;
    align-items: center;
    gap: 11px;
}

.invoice-card-title-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 15px;
}

.invoice-card-title h3 {
    margin: 0 0 5px;
    color: #1e293b;
    font-size: 16px;
}

.invoice-card-title p {
    margin: 0;
    color: #94a3b8;
    font-size: 12px;
}

.invoice-total-label {
    padding: 7px 11px;
    border-radius: 7px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
}

/* PRINT TOOLBAR */

.print-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 13px;
    margin-bottom: 17px;
    padding: 13px 15px;
    border: 1px solid #e8edf5;
    border-radius: 9px;
    background: #f8fafc;
}

.selected-count {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #64748b;
    font-size: 13px;
}

.selected-count i {
    color: #2563eb;
}

.selected-count strong {
    color: #2563eb;
    font-size: 15px;
}

.btn-print {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 15px;
    border: none;
    border-radius: 8px;
    background: #15803d;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}

.btn-print:hover:not(:disabled) {
    background: #166534;
}

.btn-print:disabled {
    background: #cbd5e1;
    color: #64748b;
    cursor: not-allowed;
}

/* TABLE */

.invoice-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.invoice-table {
    width: 100%;
    min-width: 1200px;
    border-collapse: collapse;
}

.invoice-table th,
.invoice-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #edf1f5;
    text-align: left;
    vertical-align: middle;
    font-size: 12px;
}

.invoice-table th {
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}

.invoice-table tbody tr {
    transition: background 0.15s;
}

.invoice-table tbody tr:hover {
    background: #f8fbff;
}

.invoice-table tbody tr:last-child td {
    border-bottom: none;
}

.select-column {
    width: 48px;
    text-align: center !important;
}

.select-column input {
    width: 17px;
    height: 17px;
    cursor: pointer;
    accent-color: #2563eb;
}

.invoice-index {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
}

.invoice-code {
    color: #2563eb;
    font-weight: 700;
    white-space: nowrap;
}

.ticket-code {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    background: #eff6ff;
    color: #2563eb;
    font-family: monospace;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.customer-info strong {
    display: block;
    margin-bottom: 5px;
    color: #334155;
    font-size: 12px;
}

.customer-info small {
    color: #94a3b8;
    font-size: 11px;
}

.money-cell {
    color: #475569;
    white-space: nowrap;
}

.total-money {
    color: #172554;
    font-weight: 700;
    white-space: nowrap;
}

.payment-method {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #475569;
    white-space: nowrap;
}

.payment-method i {
    color: #64748b;
}

/* STATUS */

.payment-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.payment-status.paid {
    background: #dcfce7;
    color: #15803d;
}

.payment-status.unpaid {
    background: #fee2e2;
    color: #b91c1c;
}

/* ACTIONS */

.invoice-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 9px;
}

.invoice-action-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #2563eb;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
}

.invoice-action-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.invoice-action-link.update {
    color: #d97706;
}

.invoice-action-link.update:hover {
    color: #b45309;
}

/* EMPTY */

.invoice-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 55px 20px;
    border: 1px dashed #dbe2ea;
    border-radius: 10px;
    background: #f8fafc;
    color: #94a3b8;
    font-size: 13px;
    text-align: center;
}

.invoice-empty i {
    color: #cbd5e1;
    font-size: 35px;
}

/* RESPONSIVE */

@media (max-width: 1150px) {
    .invoice-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    .invoice-container {
        padding: 14px;
    }

    .invoice-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .invoice-header h2 {
        font-size: 20px;
    }

    .invoice-header-badge {
        margin-left: 67px;
    }

    .btn-add {
        width: 100%;
    }

    .invoice-statistics {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .invoice-stat-card {
        padding: 17px;
    }

    .invoice-stat-info h3 {
        font-size: 21px;
    }

    .invoice-card {
        padding: 15px;
    }

    .invoice-card-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .print-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .btn-print {
        width: 100%;
    }
}
</style>

<div class="invoice-container">

    <!-- HEADER -->
    <div class="invoice-header">

        <div class="invoice-header-left">

            <div class="invoice-header-icon">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>

            <div>
                <h2>Quản lý hóa đơn</h2>
                <p>Quản lý hóa đơn sửa chữa và theo dõi tình trạng thanh toán</p>
            </div>

        </div>

        <a
            href="them_hoa_don.php"
            class="btn-add"
            target="mainContent"
        >
            <i class="fa-solid fa-plus"></i>
            Tạo hóa đơn
        </a>

    </div>

    <!-- THỐNG KÊ -->
    <div class="invoice-statistics">

        <div class="invoice-stat-card">

            <div class="invoice-stat-info">
                <p>Tổng số hóa đơn</p>
                <h3><?php echo number_format($tongHoaDon); ?></h3>
                <small>Tất cả hóa đơn trong hệ thống</small>
            </div>

            <div class="invoice-stat-icon">
                <i class="fa-solid fa-file-invoice"></i>
            </div>

        </div>

        <div class="invoice-stat-card green">

            <div class="invoice-stat-info">
                <p>Tổng giá trị hóa đơn</p>
                <h3><?php echo tienVND($tongDoanhThu); ?></h3>
                <small>Tổng giá trị của tất cả hóa đơn</small>
            </div>

            <div class="invoice-stat-icon">
                <i class="fa-solid fa-coins"></i>
            </div>

        </div>

        <div class="invoice-stat-card orange">

            <div class="invoice-stat-info">
                <p>Đã thanh toán</p>
                <h3><?php echo tienVND($tongDaThanhToan); ?></h3>
                <small>Tổng tiền đã thanh toán</small>
            </div>

            <div class="invoice-stat-icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>

        </div>

        <div class="invoice-stat-card red">

            <div class="invoice-stat-info">
                <p>Chưa thanh toán</p>
                <h3><?php echo tienVND($tongChuaThanhToan); ?></h3>
                <small>Tổng tiền chưa thanh toán</small>
            </div>

            <div class="invoice-stat-icon">
                <i class="fa-solid fa-clock"></i>
            </div>

        </div>

    </div>

    <!-- DANH SÁCH HÓA ĐƠN -->
    <div class="invoice-card">

        <div class="invoice-card-header">

            <div class="invoice-card-title">

                <div class="invoice-card-title-icon">
                    <i class="fa-solid fa-list"></i>
                </div>

                <div>
                    <h3>Danh sách hóa đơn</h3>
                    <p>Thông tin chi tiết các hóa đơn sửa chữa</p>
                </div>

            </div>

            <div class="invoice-total-label">
                <?php echo number_format($tongHoaDon); ?> hóa đơn
            </div>

        </div>

        <?php if (count($invoices) > 0): ?>

            <form
                method="POST"
                action="in_hoa_don.php"
                target="mainContent"
                id="printForm"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo eHoaDon($_SESSION['csrf_token']); ?>"
                >

                <!-- THANH CÔNG CỤ IN -->
                <div class="print-toolbar">

                    <div class="selected-count">
                        <i class="fa-solid fa-square-check"></i>

                        Đã chọn:
                        <strong id="selectedCount">0</strong>
                        hóa đơn
                    </div>

                    <button
                        type="submit"
                        class="btn-print"
                        id="printButton"
                        disabled
                    >
                        <i class="fa-solid fa-print"></i>
                        In hóa đơn đã chọn
                    </button>

                </div>

                <!-- BẢNG DỮ LIỆU -->
                <div class="invoice-table-wrapper">

                    <table class="invoice-table">

                        <thead>

                            <tr>

                                <th class="select-column">
                                    <input
                                        type="checkbox"
                                        id="selectAll"
                                        title="Chọn tất cả hóa đơn"
                                    >
                                </th>

                                <th>STT</th>
                                <th>Mã hóa đơn</th>
                                <th>Mã phiếu</th>
                                <th>Khách hàng</th>
                                <th>Tiền công</th>
                                <th>Tiền linh kiện</th>
                                <th>Giảm giá</th>
                                <th>Tổng tiền</th>
                                <th>Thanh toán</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($invoices as $index => $invoice): ?>

                                <tr>

                                    <td class="select-column">

                                        <input
                                            type="checkbox"
                                            class="invoice-checkbox"
                                            name="invoice_ids[]"
                                            value="<?php echo (int) $invoice['id']; ?>"
                                        >

                                    </td>

                                    <td>
                                        <span class="invoice-index">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="invoice-code">
                                            HD-<?php echo str_pad(
                                                (string) $invoice['id'],
                                                5,
                                                '0',
                                                STR_PAD_LEFT
                                            ); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="ticket-code">
                                            <?php echo eHoaDon($invoice['ticket_code']); ?>
                                        </span>
                                    </td>

                                    <td>

                                        <div class="customer-info">

                                            <strong>
                                                <?php echo eHoaDon($invoice['customer_name']); ?>
                                            </strong>

                                            <small>
                                                <i class="fa-solid fa-phone"></i>
                                                <?php echo eHoaDon($invoice['customer_phone']); ?>
                                            </small>

                                        </div>

                                    </td>

                                    <td class="money-cell">
                                        <?php echo tienVND($invoice['labor_cost']); ?>
                                    </td>

                                    <td class="money-cell">
                                        <?php echo tienVND($invoice['parts_total']); ?>
                                    </td>

                                    <td class="money-cell">
                                        <?php echo tienVND($invoice['discount']); ?>
                                    </td>

                                    <td class="total-money">
                                        <?php echo tienVND($invoice['total_amount']); ?>
                                    </td>

                                    <td>

                                        <span class="payment-method">

                                            <?php if ($invoice['payment_method'] === 'cash'): ?>

                                                <i class="fa-solid fa-money-bill-wave"></i>

                                            <?php elseif ($invoice['payment_method'] === 'bank_transfer'): ?>

                                                <i class="fa-solid fa-building-columns"></i>

                                            <?php else: ?>

                                                <i class="fa-solid fa-wallet"></i>

                                            <?php endif; ?>

                                            <?php
                                            echo eHoaDon(
                                                $phuongThuc[$invoice['payment_method']]
                                                ?? $invoice['payment_method']
                                            );
                                            ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?php if ($invoice['payment_status'] === 'paid'): ?>

                                            <span class="payment-status paid">
                                                <i class="fa-solid fa-circle-check"></i>
                                                Đã thanh toán
                                            </span>

                                        <?php else: ?>

                                            <span class="payment-status unpaid">
                                                <i class="fa-solid fa-clock"></i>
                                                Chưa thanh toán
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <div class="invoice-actions">

                                            <a
                                                class="invoice-action-link"
                                                href="chi_tiet_hoa_don.php?id=<?php echo (int) $invoice['id']; ?>"
                                                target="mainContent"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                                Xem chi tiết
                                            </a>

                                            <a
                                                class="invoice-action-link update"
                                                href="cap_nhat_hoa_don.php?id=<?php echo (int) $invoice['id']; ?>"
                                                target="mainContent"
                                            >
                                                <i class="fa-solid fa-pen-to-square"></i>
                                                Cập nhật hóa đơn
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </form>

        <?php else: ?>

            <div class="invoice-empty">
                <i class="fa-solid fa-file-circle-xmark"></i>
                <strong>Chưa có hóa đơn nào</strong>
                <span>Hiện tại hệ thống chưa có dữ liệu hóa đơn.</span>
            </div>

        <?php endif; ?>

    </div>

</div>

<script>
const selectAll = document.getElementById('selectAll');
const checkboxes = document.querySelectorAll('.invoice-checkbox');
const selectedCount = document.getElementById('selectedCount');
const printButton = document.getElementById('printButton');
const printForm = document.getElementById('printForm');

// Cập nhật số lượng hóa đơn được chọn
function updateSelectedCount() {

    const checkedBoxes = document.querySelectorAll(
        '.invoice-checkbox:checked'
    );

    const count = checkedBoxes.length;

    selectedCount.textContent = count;
    printButton.disabled = count === 0;

    selectAll.checked =
        count === checkboxes.length && checkboxes.length > 0;

    selectAll.indeterminate =
        count > 0 && count < checkboxes.length;
}

// Chọn hoặc bỏ chọn tất cả
selectAll.addEventListener('change', function () {

    checkboxes.forEach(function (checkbox) {
        checkbox.checked = selectAll.checked;
    });

    updateSelectedCount();
});

// Theo dõi từng hóa đơn
checkboxes.forEach(function (checkbox) {

    checkbox.addEventListener('change', function () {
        updateSelectedCount();
    });

});

// Kiểm tra trước khi gửi biểu mẫu in
printForm.addEventListener('submit', function (event) {

    const count = document.querySelectorAll(
        '.invoice-checkbox:checked'
    ).length;

    if (count === 0) {
        event.preventDefault();
        alert('Vui lòng chọn ít nhất một hóa đơn để in.');
    }

});

// Khởi tạo số lượng ban đầu
updateSelectedCount();
</script>
