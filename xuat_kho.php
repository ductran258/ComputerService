
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

$userId = (int) $_SESSION['user_id'];

$thongBaoLoi = '';

$csrfToken = $_SESSION['csrf_xuat_kho'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_xuat_kho'] = $csrfToken;

// Lấy danh sách linh kiện
$stmt = $pdo->query("
    SELECT id, part_name, sku, quantity
    FROM parts
    ORDER BY part_name ASC
");

$parts = $stmt->fetchAll();

// Xử lý xuất kho
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $partId = filter_input(INPUT_POST, 'part_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $note = trim($_POST['note'] ?? '');
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $submittedToken)) {

        $thongBaoLoi = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';

    } elseif (!$partId || !$quantity || $quantity < 1) {

        $thongBaoLoi = 'Vui lòng chọn linh kiện và nhập số lượng hợp lệ.';

    } else {

        try {

            $pdo->beginTransaction();

            // Khóa dòng linh kiện để kiểm tra tồn kho an toàn
            $stmt = $pdo->prepare("
                SELECT id, quantity
                FROM parts
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$partId]);
            $part = $stmt->fetch();

            if (!$part) {

                throw new Exception('Không tìm thấy linh kiện.');

            }

            $tonKhoHienTai = (int) $part['quantity'];

            // Kiểm tra số lượng tồn kho
            if ($quantity > $tonKhoHienTai) {

                throw new Exception(
                    'Số lượng xuất vượt quá tồn kho. Hiện chỉ còn '
                    . $tonKhoHienTai . ' sản phẩm.'
                );

            }

            // Trừ số lượng tồn kho
            $stmt = $pdo->prepare("
                UPDATE parts
                SET quantity = quantity - ?
                WHERE id = ?
            ");

            $stmt->execute([$quantity, $partId]);

            // Ghi lịch sử xuất kho
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements
                    (part_id, movement_type, quantity, note, created_by)
                VALUES (?, 'export', ?, ?, ?)
            ");

            $stmt->execute([
                $partId,
                $quantity,
                $note !== '' ? $note : null,
                $userId
            ]);

            $pdo->commit();

            header('Location: linh_kien.php?exported=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $thongBaoLoi = $e instanceof Exception
                ? $e->getMessage()
                : 'Có lỗi xảy ra khi xuất kho. Vui lòng thử lại.';

            error_log($e->getMessage());
        }
    }
}

// Hàm bảo vệ dữ liệu hiển thị
function eXuatKho($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Xuất kho linh kiện</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 28px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1e293b;
        }

        .page-container {
            max-width: 850px;
            margin: 0 auto;
        }

        /* HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .page-title {
            margin: 0;
            font-size: 25px;
            font-weight: 700;
            color: #172554;
        }

        .page-subtitle {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            background: #ffffff;
            border: 1px solid #dbe3ef;
            border-radius: 9px;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
        }

        .back-button:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        /* INTRO */

        .intro-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            margin-bottom: 22px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
        }

        .intro-icon {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #dc2626;
            color: #ffffff;
            font-size: 22px;
        }

        .intro-card h2 {
            margin: 0 0 6px;
            color: #991b1b;
            font-size: 17px;
        }

        .intro-card p {
            margin: 0;
            color: #b91c1c;
            font-size: 13px;
            line-height: 1.5;
        }

        /* FORM CARD */

        .form-card {
            padding: 28px;
            background: #ffffff;
            border: 1px solid #e5eaf2;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
        }

        .card-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 20px;
            margin-bottom: 22px;
            border-bottom: 1px solid #edf1f7;
        }

        .heading-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fef2f2;
            color: #dc2626;
            border-radius: 11px;
            font-size: 19px;
        }

        .card-heading h2 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
        }

        .card-heading p {
            margin: 5px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        /* ERROR */

        .error {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 16px;
            margin-bottom: 22px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.5;
        }

        /* FORM */

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            margin-bottom: 9px;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        .required {
            color: #dc2626;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper > i {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        select,
        input,
        textarea {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1px solid #dbe3ef;
            border-radius: 9px;
            background: #ffffff;
            color: #1e293b;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        select,
        input {
            height: 46px;
        }

        select {
            cursor: pointer;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
            line-height: 1.5;
        }

        .textarea-wrapper {
            position: relative;
        }

        .textarea-wrapper > i {
            position: absolute;
            top: 15px;
            left: 14px;
            color: #94a3b8;
        }

        select:focus,
        input:focus,
        textarea:focus {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
        }

        .note {
            margin: 8px 0 0;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
        }

        /* WARNING */

        .warning-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 15px;
            margin-top: 5px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 9px;
            color: #92400e;
            font-size: 13px;
            line-height: 1.5;
        }

        /* BUTTONS */

        .form-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #edf1f7;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 11px 19px;
            border: none;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-submit {
            background: #dc2626;
            color: #ffffff;
        }

        .btn-submit:hover {
            background: #b91c1c;
            box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2);
        }

        .btn-back {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-back:hover {
            background: #e2e8f0;
        }

        @media (max-width: 600px) {

            body {
                padding: 16px;
            }

            .page-title {
                font-size: 21px;
            }

            .form-card {
                padding: 20px 16px;
            }

            .intro-card {
                align-items: flex-start;
            }

            .form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .form-actions .btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="page-container">

    <!-- HEADER -->

    <div class="page-header">

        <div>

            <h1 class="page-title">
                <i class="fa-solid fa-boxes-stacked"></i>
                Xuất kho linh kiện
            </h1>

            <p class="page-subtitle">
                Cập nhật số lượng linh kiện được xuất khỏi kho.
            </p>

        </div>

        <a href="linh_kien.php"
           class="back-button"
           target="mainContent">

            <i class="fa-solid fa-arrow-left"></i>
            Quay lại kho linh kiện

        </a>

    </div>

    <!-- INTRO -->

    <div class="intro-card">

        <div class="intro-icon">
            <i class="fa-solid fa-truck-fast"></i>
        </div>

        <div>

            <h2>Xuất linh kiện khỏi kho</h2>

            <p>
                Chọn linh kiện và nhập số lượng cần xuất.
                Hệ thống sẽ kiểm tra tồn kho trước khi cập nhật và lưu lịch sử xuất.
            </p>

        </div>

    </div>

    <!-- FORM -->

    <div class="form-card">

        <div class="card-heading">

            <div class="heading-icon">
                <i class="fa-solid fa-file-export"></i>
            </div>

            <div>

                <h2>Thông tin phiếu xuất kho</h2>

                <p>
                    Vui lòng kiểm tra số lượng tồn kho trước khi xác nhận.
                </p>

            </div>

        </div>

        <?php if ($thongBaoLoi !== ''): ?>

            <div class="error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <span>
                    <?php echo eXuatKho($thongBaoLoi); ?>
                </span>

            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo eXuatKho($csrfToken); ?>"
            >

            <!-- CHON LINH KIEN -->

            <div class="form-group">

                <label>
                    Chọn linh kiện <span class="required">*</span>
                </label>

                <div class="input-wrapper">

                    <i class="fa-solid fa-cube"></i>

                    <select name="part_id" required>

                        <option value="">-- Chọn linh kiện --</option>

                        <?php foreach ($parts as $part): ?>

                            <option
                                value="<?php echo (int) $part['id']; ?>"
                                <?php echo ((string) ($_POST['part_id'] ?? '') === (string) $part['id']) ? 'selected' : ''; ?>
                            >
                                <?php
                                echo eXuatKho($part['part_name']);
                                echo ' - Tồn kho: ' . (int) $part['quantity'];

                                if (!empty($part['sku'])) {
                                    echo ' - SKU: ' . eXuatKho($part['sku']);
                                }
                                ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <p class="note">
                    Số lượng tồn kho hiện tại được hiển thị bên cạnh tên linh kiện.
                </p>

            </div>

            <!-- SO LUONG -->

            <div class="form-group">

                <label>
                    Số lượng xuất <span class="required">*</span>
                </label>

                <div class="input-wrapper">

                    <i class="fa-solid fa-boxes-stacked"></i>

                    <input
                        type="number"
                        name="quantity"
                        min="1"
                        step="1"
                        value="<?php echo eXuatKho($_POST['quantity'] ?? ''); ?>"
                        placeholder="Ví dụ: 2"
                        required
                    >

                </div>

                <p class="note">
                    Số lượng xuất phải là số nguyên lớn hơn 0 và không vượt quá tồn kho.
                </p>

            </div>

            <!-- GHI CHU -->

            <div class="form-group">

                <label>Ghi chú</label>

                <div class="textarea-wrapper">

                    <i class="fa-solid fa-pen-to-square"></i>

                    <textarea
                        name="note"
                        placeholder="Ví dụ: Xuất 2 thanh RAM để sửa chữa máy tính..."
                    ><?php echo eXuatKho($_POST['note'] ?? ''); ?></textarea>

                </div>

                <p class="note">
                    Có thể ghi lý do xuất kho, người nhận hoặc thông tin bổ sung.
                </p>

            </div>

            <!-- WARNING -->

            <div class="warning-note">

                <i class="fa-solid fa-triangle-exclamation"></i>

                <span>
                    Lưu ý: Sau khi xác nhận, số lượng linh kiện sẽ bị trừ khỏi kho.
                    Hãy kiểm tra kỹ thông tin trước khi thực hiện.
                </span>

            </div>

            <!-- BUTTONS -->

            <div class="form-actions">

                <a
                    href="linh_kien.php"
                    class="btn btn-back"
                    target="mainContent"
                >
                    <i class="fa-solid fa-xmark"></i>
                    Hủy
                </a>

                <button type="submit" class="btn btn-submit">

                    <i class="fa-solid fa-circle-check"></i>
                    Xác nhận xuất kho

                </button>

            </div>

        </form>

    </div>

</div>

</body>
</html>
