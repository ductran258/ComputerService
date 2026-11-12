
<?php
require_once __DIR__ . '/includes/auth.php';

requireAdmin();
require_once __DIR__ . '/config/database.php';

// 1. CHI CHO PHEP XOA BANG PHUONG THUC POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: linh_kien.php');
    exit;
}

// 2. LAY ID LINH KIEN
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: linh_kien.php?error=invalid_id');
    exit;
}

try {

    // 3. KIEM TRA LINH KIEN CO TON TAI KHONG
    $stmt = $pdo->prepare("
        SELECT id, part_name
        FROM parts
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id
    ]);

    $part = $stmt->fetch();

    if (!$part) {
        header('Location: linh_kien.php?error=not_found');
        exit;
    }

    // 4. KIEM TRA LINH KIEN DA DUOC SU DUNG CHUA
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ticket_parts
        WHERE part_id = :id
    ");

    $stmt->execute([
        'id' => $id
    ]);

    $soLanSuDung = (int) $stmt->fetchColumn();

    if ($soLanSuDung > 0) {
        header('Location: linh_kien.php?error=in_use');
        exit;
    }

    // 5. NEU CHUA DUOC SU DUNG THI TIEN HANH XOA
    $stmt = $pdo->prepare("
        DELETE FROM parts
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id
    ]);

    header('Location: linh_kien.php?deleted=1');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header('Location: linh_kien.php?error=delete_failed');
    exit;
}
