
<?php
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

require_once __DIR__ . '/config/database.php';

// Chỉ cho phép xóa bằng phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: thiet_bi.php');
    exit;
}

// Lấy ID thiết bị
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

// Kiểm tra ID hợp lệ
if (!$id || $id <= 0) {
    header('Location: thiet_bi.php?error=invalid_id');
    exit;
}

try {
    // Kiểm tra thiết bị có tồn tại không
    $stmt = $pdo->prepare(
        "SELECT id FROM devices WHERE id = :id LIMIT 1"
    );

    $stmt->execute([
        'id' => $id
    ]);

    $device = $stmt->fetch();

    if (!$device) {
        header('Location: thiet_bi.php?error=not_found');
        exit;
    }

    // Kiểm tra thiết bị đã có phiếu sửa chữa chưa
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM repair_tickets WHERE device_id = :id"
    );

    $stmt->execute([
        'id' => $id
    ]);

    $ticketCount = (int) $stmt->fetchColumn();

    // Nếu đã có lịch sử sửa chữa thì không cho xóa
    if ($ticketCount > 0) {
        header('Location: thiet_bi.php?error=in_use');
        exit;
    }

    // Nếu chưa có lịch sử thì tiến hành xóa
    $stmt = $pdo->prepare(
        "DELETE FROM devices WHERE id = :id"
    );

    $stmt->execute([
        'id' => $id
    ]);

    header('Location: thiet_bi.php?deleted=1');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header('Location: thiet_bi.php?error=delete_failed');
    exit;
}
