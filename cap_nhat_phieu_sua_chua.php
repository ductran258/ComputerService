<?php

// ======================================================
// 1. KIỂM TRA ĐĂNG NHẬP VÀ QUYỀN TRUY CẬP
// ======================================================

require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/config/database.php';

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$isAdmin = $role === 'admin';
$isTechnician = $role === 'technician';

if (!$isAdmin && !$isTechnician) {
    http_response_code(403);
    exit('Bạn không có quyền truy cập trang này.');
}

$loi = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


// ======================================================
// 2. KIỂM TRA ID PHIẾU
// ======================================================

$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
}

if (!$ticketId || $ticketId <= 0) {
    exit('Mã phiếu sửa chữa không hợp lệ.');
}


// ======================================================
// 3. LẤY THÔNG TIN PHIẾU
// ======================================================

$stmt = $pdo->prepare("
    SELECT *
    FROM repair_tickets
    WHERE id = :id
");

$stmt->execute([':id' => $ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    exit('Không tìm thấy phiếu sửa chữa.');
}


// ======================================================
// 4. KIỂM TRA QUYỀN KỸ THUẬT VIÊN
// ======================================================

if (
    $isTechnician &&
    (int) $ticket['assigned_to'] !== $userId
) {
    http_response_code(403);
    exit('Bạn không được phép cập nhật phiếu sửa chữa này.');
}


// ======================================================
// 5. DANH SÁCH KỸ THUẬT VIÊN ĐANG HOẠT ĐỘNG
// ======================================================

$technicians = [];

if ($isAdmin) {
    $stmt = $pdo->query("
        SELECT id, full_name
        FROM users
        WHERE role = 'technician'
          AND is_active = 1
        ORDER BY full_name ASC
    ");

    $technicians = $stmt->fetchAll();
}


// ======================================================
// 6. DANH SÁCH TRẠNG THÁI VÀ LUỒNG CHUYỂN
// ======================================================

$statusLabels = [
    'received' => 'Đã tiếp nhận',
    'checking' => 'Đang kiểm tra',
    'waiting_parts' => 'Chờ linh kiện',
    'repairing' => 'Đang sửa chữa',
    'completed' => 'Đã sửa xong',
    'delivered' => 'Đã bàn giao',
    'cancelled' => 'Đã hủy'
];

$allowedTransitions = [
    'received' => [
        'checking',
        'cancelled'
    ],

    'checking' => [
        'waiting_parts',
        'repairing',
        'cancelled'
    ],

    'waiting_parts' => [
        'repairing',
        'cancelled'
    ],

    'repairing' => [
        'waiting_parts',
        'completed',
        'cancelled'
    ],

    'completed' => [
        'delivered'
    ],

    'delivered' => [],

    'cancelled' => []
];

$terminalStatuses = [
    'delivered',
    'cancelled'
];

$isTerminal = in_array(
    $ticket['status'],
    $terminalStatuses,
    true
);


// ======================================================
// 7. XỬ LÝ FORM CẬP NHẬT
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --------------------------------------------------
    // 7.1. KIỂM TRA CSRF
    // --------------------------------------------------

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ. Vui lòng tải lại trang.');
    }


    // --------------------------------------------------
    // 7.2. KIỂM TRA KIỂU DỮ LIỆU ĐẦU VÀO
    // --------------------------------------------------

    $newStatus = $_POST['status'] ?? '';
    $diagnosisInput = $_POST['diagnosis'] ?? '';
    $solutionInput = $_POST['solution'] ?? '';
    $noteInput = $_POST['note'] ?? '';

    if (
        !is_string($newStatus) ||
        !is_string($diagnosisInput) ||
        !is_string($solutionInput) ||
        !is_string($noteInput)
    ) {
        $loi = 'Dữ liệu gửi lên không hợp lệ.';
    }

    if ($loi === '') {
        $diagnosis = trim($diagnosisInput);
        $solution = trim($solutionInput);
        $note = trim($noteInput);
    }


    // --------------------------------------------------
    // 7.3. LẤY KỸ THUẬT VIÊN ĐƯỢC PHÂN CÔNG
    // --------------------------------------------------

    $assignedTo = null;

    if ($loi === '') {

        if ($isAdmin) {

            $assignedInput = $_POST['assigned_to'] ?? '';

            if (!is_string($assignedInput)) {
                $loi = 'Dữ liệu kỹ thuật viên không hợp lệ.';
            } elseif ($assignedInput === '') {
                $assignedTo = null;
            } elseif (
                !ctype_digit($assignedInput) ||
                (int) $assignedInput <= 0
            ) {
                $loi = 'Mã kỹ thuật viên không hợp lệ.';
            } else {
                $assignedTo = (int) $assignedInput;
            }

        } else {

            // Kỹ thuật viên không được tự thay đổi người phụ trách
            $assignedTo = $userId;
        }
    }


    // --------------------------------------------------
    // 7.4. KIỂM TRA TRẠNG THÁI
    // --------------------------------------------------

    if ($loi === '') {

        if (!array_key_exists($newStatus, $statusLabels)) {

            $loi = 'Trạng thái không hợp lệ.';

        } elseif ($isTerminal) {

            // Phiếu đã bàn giao hoặc đã hủy:
            // Không được thay đổi trạng thái.

            if ($newStatus !== $ticket['status']) {
                $loi = 'Phiếu đã kết thúc, không thể thay đổi trạng thái.';
            }

        } elseif (
            $newStatus !== $ticket['status'] &&
            !in_array(
                $newStatus,
                $allowedTransitions[$ticket['status']] ?? [],
                true
            )
        ) {

            $loi = 'Không được phép chuyển sang trạng thái này. Vui lòng thực hiện đúng quy trình sửa chữa.';
        }
    }


    // --------------------------------------------------
    // 7.5. KHÔNG CHO KỸ THUẬT VIÊN SỬA PHIẾU ĐÃ KẾT THÚC
    // --------------------------------------------------

    if (
        $loi === '' &&
        $isTechnician &&
        $isTerminal
    ) {
        $loi = 'Phiếu đã bàn giao hoặc đã hủy. Bạn không thể chỉnh sửa phiếu này.';
    }


    // --------------------------------------------------
    // 7.6. TIẾN HÀNH GIAO DỊCH
    // --------------------------------------------------

    if ($loi === '') {

        try {

            $pdo->beginTransaction();

            // Khóa phiếu để tránh cập nhật đồng thời
            $stmt = $pdo->prepare("
                SELECT *
                FROM repair_tickets
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute([':id' => $ticketId]);
            $ticketLocked = $stmt->fetch();

            if (!$ticketLocked) {
                throw new RuntimeException(
                    'Không tìm thấy phiếu sửa chữa.'
                );
            }

            $oldStatus = $ticketLocked['status'];


            // ------------------------------------------
            // KIỂM TRA LẠI QUYỀN KỸ THUẬT VIÊN
            // ------------------------------------------

            if (
                $isTechnician &&
                (int) $ticketLocked['assigned_to'] !== $userId
            ) {
                throw new RuntimeException(
                    'Bạn không còn được phân công phiếu sửa chữa này.'
                );
            }


            // ------------------------------------------
            // KIỂM TRA LẠI PHIẾU ĐÃ KẾT THÚC
            // ------------------------------------------

            $lockedIsTerminal = in_array(
                $oldStatus,
                $terminalStatuses,
                true
            );

            if ($lockedIsTerminal) {

                if ($isTechnician) {
                    throw new RuntimeException(
                        'Phiếu đã bàn giao hoặc đã hủy. Bạn không thể chỉnh sửa phiếu này.'
                    );
                }

                if ($newStatus !== $oldStatus) {
                    throw new RuntimeException(
                        'Phiếu đã kết thúc, không thể thay đổi trạng thái.'
                    );
                }

            } elseif (
                $newStatus !== $oldStatus &&
                !in_array(
                    $newStatus,
                    $allowedTransitions[$oldStatus] ?? [],
                    true
                )
            ) {

                throw new RuntimeException(
                    'Trạng thái phiếu đã thay đổi hoặc không đúng quy trình. Vui lòng tải lại trang.'
                );
            }


            // ------------------------------------------
            // KIỂM TRA KỸ THUẬT VIÊN ĐƯỢC PHÂN CÔNG
            // ------------------------------------------

            if ($isAdmin && $assignedTo !== null) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE id = :id
                      AND role = 'technician'
                      AND is_active = 1
                ");

                $stmt->execute([
                    ':id' => $assignedTo
                ]);

                if (!$stmt->fetch()) {
                    throw new RuntimeException(
                        'Kỹ thuật viên không hợp lệ hoặc tài khoản đã bị khóa.'
                    );
                }
            }


            // ------------------------------------------
            // 7.7. TỰ ĐỘNG TRỪ KHO KHI HOÀN TẤT SỬA CHỮA
            // ------------------------------------------

            $isCompleting = (
                $oldStatus !== 'completed' &&
                $newStatus === 'completed'
            );

            if (
                $isCompleting &&
                empty($ticketLocked['stock_deducted_at'])
            ) {

                // Lấy tổng số lượng từng linh kiện
                $stmt = $pdo->prepare("
                    SELECT
                        part_id,
                        SUM(quantity) AS total_quantity
                    FROM ticket_parts
                    WHERE ticket_id = :ticket_id
                    GROUP BY part_id
                    ORDER BY part_id ASC
                ");

                $stmt->execute([
                    ':ticket_id' => $ticketId
                ]);

                $partsInTicket = $stmt->fetchAll();


                // Kiểm tra và khóa tồn kho
                foreach ($partsInTicket as $item) {

                    $partId = (int) $item['part_id'];
                    $quantityNeeded = (int) $item['total_quantity'];

                    $stmt = $pdo->prepare("
                        SELECT id, part_name, quantity
                        FROM parts
                        WHERE id = :id
                        FOR UPDATE
                    ");

                    $stmt->execute([
                        ':id' => $partId
                    ]);

                    $part = $stmt->fetch();

                    if (!$part) {
                        throw new RuntimeException(
                            'Không tìm thấy linh kiện có ID ' . $partId . '.'
                        );
                    }

                    if ((int) $part['quantity'] < $quantityNeeded) {
                        throw new RuntimeException(
                            'Không đủ tồn kho linh kiện "' .
                            $part['part_name'] .
                            '". Cần ' . $quantityNeeded .
                            ', hiện còn ' . (int) $part['quantity'] . '.'
                        );
                    }
                }


                // Tiến hành trừ kho sau khi đã kiểm tra đủ
                foreach ($partsInTicket as $item) {

                    $partId = (int) $item['part_id'];
                    $quantityNeeded = (int) $item['total_quantity'];

                    $stmt = $pdo->prepare("
                        UPDATE parts
                        SET quantity = quantity - :quantity
                        WHERE id = :id
                          AND quantity >= :check_quantity
                    ");

                    $stmt->execute([
                        ':quantity' => $quantityNeeded,
                        ':id' => $partId,
                        ':check_quantity' => $quantityNeeded
                    ]);

                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Không thể cập nhật tồn kho. Vui lòng thử lại.'
                        );
                    }


                    // Ghi lịch sử xuất kho
                    $stmt = $pdo->prepare("
                        INSERT INTO stock_movements (
                            part_id,
                            movement_type,
                            quantity,
                            note,
                            created_by
                        )
                        VALUES (
                            :part_id,
                            'export',
                            :quantity,
                            :note,
                            :created_by
                        )
                    ");

                    $stmt->execute([
                        ':part_id' => $partId,
                        ':quantity' => $quantityNeeded,
                        ':note' => 'Xuất linh kiện tự động khi hoàn tất phiếu ' .
                            $ticketLocked['ticket_code'],
                        ':created_by' => $userId
                    ]);
                }


                // Đánh dấu đã trừ kho
                $stmt = $pdo->prepare("
                    UPDATE repair_tickets
                    SET stock_deducted_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':id' => $ticketId
                ]);
            }


            // ------------------------------------------
            // 7.8. CẬP NHẬT THÔNG TIN PHIẾU
            // ------------------------------------------

            $stmt = $pdo->prepare("
                UPDATE repair_tickets
                SET
                    diagnosis = :diagnosis,
                    solution = :solution,
                    assigned_to = :assigned_to,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':diagnosis' => $diagnosis,
                ':solution' => $solution,
                ':assigned_to' => $assignedTo,
                ':status' => $newStatus,
                ':id' => $ticketId
            ]);


            // ------------------------------------------
            // 7.9. GHI LỊCH SỬ THAY ĐỔI
            // ------------------------------------------

            if ($newStatus !== $oldStatus) {

                $historyNote = $note !== ''
                    ? $note
                    : 'Cập nhật trạng thái phiếu sửa chữa.';

                if ($isCompleting) {
                    $historyNote .=
                        ' Hệ thống đã kiểm tra và xử lý xuất kho linh kiện.';
                }

                $stmt = $pdo->prepare("
                    INSERT INTO ticket_status_history (
                        ticket_id,
                        changed_by,
                        old_status,
                        new_status,
                        note
                    )
                    VALUES (
                        :ticket_id,
                        :changed_by,
                        :old_status,
                        :new_status,
                        :note
                    )
                ");

                $stmt->execute([
                    ':ticket_id' => $ticketId,
                    ':changed_by' => $userId,
                    ':old_status' => $oldStatus,
                    ':new_status' => $newStatus,
                    ':note' => $historyNote
                ]);

            } elseif ($note !== '') {

                // Chỉ thêm ghi chú, không thay đổi trạng thái
                $stmt = $pdo->prepare("
                    INSERT INTO ticket_status_history (
                        ticket_id,
                        changed_by,
                        old_status,
                        new_status,
                        note
                    )
                    VALUES (
                        :ticket_id,
                        :changed_by,
                        :old_status,
                        :new_status,
                        :note
                    )
                ");

                $stmt->execute([
                    ':ticket_id' => $ticketId,
                    ':changed_by' => $userId,
                    ':old_status' => $oldStatus,
                    ':new_status' => $oldStatus,
                    ':note' => $note
                ]);
            }


            // ------------------------------------------
            // 7.10. GỬI THÔNG BÁO KHI ĐỔI KỸ THUẬT VIÊN
            // ------------------------------------------

            $oldAssignedTo = $ticketLocked['assigned_to'] !== null
                ? (int) $ticketLocked['assigned_to']
                : null;

            $newAssignedTo = $assignedTo !== null
                ? (int) $assignedTo
                : null;

            if (
                $isAdmin &&
                $newAssignedTo !== null &&
                $newAssignedTo !== $oldAssignedTo
            ) {

                $stmt = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id,
                        title,
                        message,
                        link
                    )
                    VALUES (
                        :user_id,
                        :title,
                        :message,
                        :link
                    )
                ");

                $stmt->execute([
                    ':user_id' => $newAssignedTo,
                    ':title' => 'Bạn được phân công phiếu sửa chữa',
                    ':message' => 'Bạn vừa được phân công xử lý phiếu ' .
                        $ticketLocked['ticket_code'] .
                        '. Vui lòng truy cập để xem chi tiết.',
                    ':link' => 'chi_tiet_phieu_sua_chua.php?id=' . $ticketId
                ]);
            }


            // ------------------------------------------
            // 7.11. HOÀN TẤT GIAO DỊCH
            // ------------------------------------------

            $pdo->commit();

            header(
                'Location: chi_tiet_phieu_sua_chua.php?id=' . $ticketId
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            if ($e instanceof RuntimeException) {
                $loi = $e->getMessage();
            } else {
                $loi = 'Không thể cập nhật phiếu sửa chữa. Vui lòng kiểm tra lại.';
            }
        }
    }


    // Giữ lại dữ liệu đã nhập nếu có lỗi
    if ($loi === '') {
        $ticket['status'] = $newStatus;
        $ticket['diagnosis'] = $diagnosis;
        $ticket['solution'] = $solution;
        $ticket['assigned_to'] = $assignedTo;
    } else {
        if (isset($diagnosis) && is_string($diagnosis)) {
            $ticket['diagnosis'] = $diagnosis;
        }

        if (isset($solution) && is_string($solution)) {
            $ticket['solution'] = $solution;
        }
    }
}


// ======================================================
// 8. XÁC ĐỊNH TRẠNG THÁI HIỆN TẠI
// ======================================================

$isTerminal = in_array(
    $ticket['status'],
    $terminalStatuses,
    true
);

// Kỹ thuật viên chỉ được xem, không được sửa phiếu đã kết thúc
$canEdit = !($isTechnician && $isTerminal);

// Admin vẫn được sửa thông tin phiếu đã kết thúc,
// nhưng không được thay đổi trạng thái.

$availableStatuses = [$ticket['status']];

if (!$isTerminal) {
    $availableStatuses = array_merge(
        $availableStatuses,
        $allowedTransitions[$ticket['status']] ?? []
    );
}


// ======================================================
// 9. HÀM BẢO VỆ DỮ LIỆU HIỂN THỊ
// ======================================================

function e($value): string
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

    <title>Cập nhật phiếu sửa chữa</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            padding: 30px;
        }

        .container {
            max-width: 750px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        h1 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 11px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        button {
            margin-top: 22px;
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .error {
            padding: 12px;
            background: #fee2e2;
            color: #991b1b;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .notice {
            padding: 12px;
            background: #fef3c7;
            color: #92400e;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .readonly {
            background: #f3f4f6;
            color: #555;
            cursor: not-allowed;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>Cập nhật phiếu sửa chữa</h1>

    <p>
        Mã phiếu:
        <strong><?php echo e($ticket['ticket_code']); ?></strong>
    </p>

    <p>
        Tài khoản:
        <strong>
            <?php echo $isAdmin ? 'Admin' : 'Technician'; ?>
        </strong>
    </p>


    <?php if ($loi !== ''): ?>

        <div class="error">
            <?php echo e($loi); ?>
        </div>

    <?php endif; ?>


    <?php if ($isTerminal): ?>

        <div class="notice">
            <?php if ($isAdmin): ?>
                Phiếu đã kết thúc. Admin vẫn có thể chỉnh sửa thông tin,
                nhưng không thể thay đổi trạng thái.
            <?php else: ?>
                Phiếu đã bàn giao hoặc đã hủy. Bạn chỉ có thể xem thông tin,
                không thể chỉnh sửa.
            <?php endif; ?>
        </div>

    <?php endif; ?>


    <?php if ($canEdit): ?>

        <form method="POST">

            <input
                type="hidden"
                name="ticket_id"
                value="<?php echo (int) $ticketId; ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($csrfToken); ?>"
            >


            <label>Trạng thái sửa chữa</label>

            <?php if ($isTerminal): ?>

                <select class="readonly" disabled>
                    <option selected>
                        <?php echo e($statusLabels[$ticket['status']]); ?>
                    </option>
                </select>

                <input
                    type="hidden"
                    name="status"
                    value="<?php echo e($ticket['status']); ?>"
                >

            <?php else: ?>

                <select name="status" required>

                    <?php foreach ($availableStatuses as $value): ?>

                        <option
                            value="<?php echo e($value); ?>"
                            <?php echo $ticket['status'] === $value ? 'selected' : ''; ?>
                        >
                            <?php echo e($statusLabels[$value]); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            <?php endif; ?>


            <label>Chẩn đoán kỹ thuật</label>

            <textarea name="diagnosis"><?php
                echo e($ticket['diagnosis']);
            ?></textarea>


            <label>Phương án sửa chữa</label>

            <textarea name="solution"><?php
                echo e($ticket['solution']);
            ?></textarea>


            <?php if ($isAdmin): ?>

                <label>Kỹ thuật viên phụ trách</label>

                <select name="assigned_to">

                    <option value="">-- Chưa phân công --</option>

                    <?php foreach ($technicians as $technician): ?>

                        <option
                            value="<?php echo (int) $technician['id']; ?>"
                            <?php
                            echo (string) $ticket['assigned_to'] === (string) $technician['id']
                                ? 'selected'
                                : '';
                            ?>
                        >
                            <?php echo e($technician['full_name']); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            <?php else: ?>

                <label>Kỹ thuật viên phụ trách</label>

                <p>
                    Bạn đang được phân công xử lý phiếu sửa chữa này.
                </p>

            <?php endif; ?>


            <label>Ghi chú thay đổi</label>

            <textarea
                name="note"
                placeholder="Ví dụ: Đã kiểm tra ổ cứng, cần thay SSD..."
            ></textarea>


            <button type="submit">
                Lưu thay đổi
            </button>

        </form>

    <?php endif; ?>


    <a
        class="back"
        href="chi_tiet_phieu_sua_chua.php?id=<?php echo (int) $ticketId; ?>"
    >
        ← Quay lại chi tiết phiếu
    </a>

</div>

</body>

</html>