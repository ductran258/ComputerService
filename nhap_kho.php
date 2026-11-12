
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

$userId = (int) $_SESSION['user_id'];

$thongBaoLoi = '';

$csrfToken = $_SESSION['csrf_nhap_kho'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_nhap_kho'] = $csrfToken;

// Lấy ID linh kiện từ URL
$partIdTuURL = filter_input(INPUT_GET, 'part_id', FILTER_VALIDATE_INT);

if (!$partIdTuURL || $partIdTuURL < 1) {
    $partIdTuURL = null;
}

// Lấy danh sách linh kiện
$stmt = $pdo->query("
    SELECT id, part_name, sku, quantity
    FROM parts
    ORDER BY part_name ASC
");

$parts = $stmt->fetchAll();

// Xác định linh kiện được chọn
$selectedPartId = $partIdTuURL;

// Xử lý nhập kho
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $partId = filter_input(INPUT_POST, 'part_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $note = trim($_POST['note'] ?? '');
    $submittedToken = $_POST['csrf_token'] ?? '';

    // Giữ lại lựa chọn sau khi gửi biểu mẫu
    $selectedPartId = $partId ?: null;

    if (!hash_equals($csrfToken, $submittedToken)) {

        $thongBaoLoi = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';

    } elseif (!$partId || !$quantity || $quantity < 1) {

        $thongBaoLoi = 'Vui lòng chọn linh kiện và nhập số lượng hợp lệ.';

    } else {

        try {

            $pdo->beginTransaction();

            // Khóa dòng linh kiện để tránh cập nhật tồn kho đồng thời
            $stmt = $pdo->prepare("
                SELECT id
                FROM parts
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$partId]);
            $part = $stmt->fetch();

            if (!$part) {
                throw new Exception('Không tìm thấy linh kiện.');
            }

            // Tăng số lượng tồn kho
            $stmt = $pdo->prepare("
                UPDATE parts
                SET quantity = quantity + ?
                WHERE id = ?
            ");

            $stmt->execute([$quantity, $partId]);

            // Ghi lịch sử nhập kho
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements
                    (part_id, movement_type, quantity, note, created_by)
                VALUES (?, 'import', ?, ?, ?)
            ");

            $stmt->execute([
                $partId,
                $quantity,
                $note !== '' ? $note : null,
                $userId
            ]);

            $pdo->commit();

            header('Location: linh_kien.php?imported=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $thongBaoLoi = 'Có lỗi xảy ra khi nhập kho. Vui lòng thử lại.';
        }
    }
}

// Kiểm tra ID được chọn có tồn tại không
$partIds = array_map('intval', array_column($parts, 'id'));

if (!in_array((int) $selectedPartId, $partIds, true)) {
    $selectedPartId = null;
}

// Hàm bảo vệ dữ liệu hiển thị
function eNhapKho($value): string
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

    <title>Nhập kho linh kiện</title>

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

        /* INTRO CARD */

        .intro-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            margin-bottom: 22px;
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
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
            background: #16a34a;
            color: #ffffff;
            font-size: 22px;
        }

        .intro-card h2 {
            margin: 0 0 6px;
            color: #166534;
            font-size: 17px;
        }

        .intro-card p {
            margin: 0;
            color: #15803d;
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
            background: #ecfdf5;
            color: #16a34a;
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
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
        }

        .note {
            margin: 8px 0 0;
            color: #94a3b8;
            font-size: 12px;
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
            background: #16a34a;
            color: #ffffff;
        }

        .btn-submit:hover {
            background: #15803d;
            box-shadow: 0 4px 10px rgba(22, 163, 74, 0.2);
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
                Nhập kho linh kiện
            </h1>

            <p class="page-subtitle">
                Cập nhật số lượng linh kiện được bổ sung vào kho.
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
            <i class="fa-solid fa-truck-ramp-box"></i>
        </div>

        <div>

            <h2>Bổ sung hàng vào kho</h2>

            <p>
                Chọn linh kiện, nhập số lượng cần bổ sung và ghi chú nếu cần.
                Hệ thống sẽ tự động cập nhật tồn kho và lưu lịch sử nhập.
            </p>

        </div>

    </div>

    <!-- FORM -->

    <div class="form-card">

        <div class="card-heading">

            <div class="heading-icon">
                <i class="fa-solid fa-file-circle-plus"></i>
            </div>

            <div>

                <h2>Thông tin phiếu nhập kho</h2>

                <p>
                    Vui lòng kiểm tra kỹ thông tin trước khi xác nhận.
                </p>

            </div>

        </div>

        <?php if ($thongBaoLoi !== ''): ?>

            <div class="error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <span>
                    <?php echo eNhapKho($thongBaoLoi); ?>
                </span>

            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo eNhapKho($csrfToken); ?>"
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
                                <?php echo ((int) $selectedPartId === (int) $part['id']) ? 'selected' : ''; ?>
                            >
                                <?php
                                echo eNhapKho($part['part_name']);
                                echo ' - Tồn kho: ' . (int) $part['quantity'];

                                if (!empty($part['sku'])) {
                                    echo ' - SKU: ' . eNhapKho($part['sku']);
                                }
                                ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <p class="note">
                    Lựa chọn linh kiện cần bổ sung. Số lượng tồn kho hiện tại được hiển thị bên cạnh tên linh kiện.
                </p>

            </div>

            <!-- SO LUONG -->

            <div class="form-group">

                <label>
                    Số lượng nhập <span class="required">*</span>
                </label>

                <div class="input-wrapper">

                    <i class="fa-solid fa-boxes-stacked"></i>

                    <input
                        type="number"
                        name="quantity"
                        min="1"
                        step="1"
                        value="<?php echo eNhapKho($_POST['quantity'] ?? ''); ?>"
                        placeholder="Ví dụ: 10"
                        required
                    >

                </div>

                <p class="note">
                    Số lượng nhập phải là số nguyên lớn hơn 0.
                </p>

            </div>

            <!-- GHI CHU -->

            <div class="form-group">

                <label>Ghi chú</label>

                <div class="textarea-wrapper">

                    <i class="fa-solid fa-pen-to-square"></i>

                    <textarea
                        name="note"
                        placeholder="Ví dụ: Nhập thêm 10 thanh RAM từ nhà cung cấp..."
                    ><?php echo eNhapKho($_POST['note'] ?? ''); ?></textarea>

                </div>

                <p class="note">
                    Có thể ghi thông tin nhà cung cấp, lý do nhập hoặc thông tin bổ sung.
                </p>

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
                    Xác nhận nhập kho

                </button>

            </div>

        </form>

    </div>

</div>

</body>
</html>
