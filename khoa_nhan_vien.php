
<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nhan_vien.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
$userId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    exit('Yêu cầu không hợp lệ. Vui lòng quay lại và thử lại.');
}

if (!$userId || $userId <= 0 || !in_array($action, ['lock', 'unlock'], true)) {
    exit('Thông tin thao tác không hợp lệ.');
}

if ($userId === (int) $_SESSION['user_id'] && $action === 'lock') {
    exit('Bạn không thể khóa tài khoản đang đăng nhập.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, full_name, role, is_active
        FROM users
        WHERE id = :id
        FOR UPDATE
    ");
    $stmt->execute(['id' => $userId]);
    $nhanVien = $stmt->fetch();

    if (!$nhanVien) {
        throw new RuntimeException('Không tìm thấy tài khoản nhân viên.');
    }

    if ($action === 'lock') {
        if ((int) $nhanVien['is_active'] === 0) {
            throw new RuntimeException('Tài khoản này đã bị khóa trước đó.');
        }

        // Không cho khóa Admin cuối cùng đang hoạt động
        if ($nhanVien['role'] === 'admin') {
            $countStmt = $pdo->query("
                SELECT COUNT(*)
                FROM users
                WHERE role = 'admin'
                AND is_active = 1
                FOR UPDATE
            ");

            if ((int) $countStmt->fetchColumn() <= 1) {
                throw new RuntimeException(
                    'Không thể khóa Admin cuối cùng đang hoạt động.'
                );
            }
        }

        $update = $pdo->prepare("
            UPDATE users
            SET is_active = 0
            WHERE id = :id
        ");
        $update->execute(['id' => $userId]);

        $thongBao = 'Đã khóa tài khoản nhân viên thành công.';

    } else {
        if ((int) $nhanVien['is_active'] === 1) {
            throw new RuntimeException('Tài khoản này đang hoạt động.');
        }

        $update = $pdo->prepare("
            UPDATE users
            SET is_active = 1
            WHERE id = :id
        ");
        $update->execute(['id' => $userId]);

        $thongBao = 'Đã mở khóa tài khoản nhân viên thành công.';
    }

    $pdo->commit();

    $_SESSION['employee_message'] = $thongBao;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['employee_message'] = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Không thể thực hiện thao tác. Vui lòng thử lại.';

    if (!$e instanceof RuntimeException) {
        error_log($e->getMessage());
    }
}

header('Location: nhan_vien.php');
exit;
