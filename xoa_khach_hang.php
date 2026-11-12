
<?php
require_once __DIR__ . '/includes/auth.php';

requireAdmin();
// 1. KET NOI DATABASE
require_once __DIR__ . '/config/database.php';

// 2. CHI CHO PHEP XOA BANG POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: khach_hang.php');
    exit;
}

// 3. KIEM TRA ID
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: khach_hang.php');
    exit;
}

try {

    // 4. KIEM TRA KHACH HANG CO THIET BI HAY KHONG
    $sqlDevices = "
        SELECT COUNT(*)
        FROM devices
        WHERE customer_id = :customer_id
    ";

    $stmtDevices = $pdo->prepare($sqlDevices);
    $stmtDevices->execute([
        ':customer_id' => $id
    ]);

    $deviceCount = (int) $stmtDevices->fetchColumn();

    // 5. KIEM TRA KHACH HANG CO PHIEU SUA CHUA HAY KHONG
    $sqlTickets = "
        SELECT COUNT(*)
        FROM repair_tickets
        WHERE customer_id = :customer_id
    ";

    $stmtTickets = $pdo->prepare($sqlTickets);
    $stmtTickets->execute([
        ':customer_id' => $id
    ]);

    $ticketCount = (int) $stmtTickets->fetchColumn();

    // 6. NEU CO DU LIEU LIEN QUAN THI KHONG CHO XOA
    if ($deviceCount > 0 || $ticketCount > 0) {
        echo "
            <script>
                alert('Không thể xóa khách hàng này vì đã có thiết bị hoặc phiếu sửa chữa trong hệ thống!');
                window.location.href = 'khach_hang.php';
            </script>
        ";
        exit;
    }

    // 7. TIEN HANH XOA KHACH HANG
    $sqlDelete = "
        DELETE FROM customers
        WHERE id = :id
    ";

    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->execute([
        ':id' => $id
    ]);

    if ($stmtDelete->rowCount() > 0) {
        echo "
            <script>
                alert('Xóa khách hàng thành công!');
                window.location.href = 'khach_hang.php';
            </script>
        ";
    } else {
        echo "
            <script>
                alert('Không tìm thấy khách hàng cần xóa!');
                window.location.href = 'khach_hang.php';
            </script>
        ";
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    echo "
        <script>
            alert('Đã xảy ra lỗi khi xóa khách hàng!');
            window.location.href = 'khach_hang.php';
        </script>
    ";
}

exit;

?>
