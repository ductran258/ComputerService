
<?php

require_once __DIR__ . '/includes/auth.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_notification'])) {
    $_SESSION['csrf_notification'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_notification'];
$thongBaoLoi = '';

function eThongBaoPage($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Xử lý thao tác POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $token)) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ. Vui lòng tải lại trang.');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'read_all') {
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE user_id = :user_id
                  AND is_read = 0
            ");

            $stmt->execute(['user_id' => $userId]);

            header('Location: thong_bao.php?updated=1');
            exit;
        }

        if ($action === 'read_one') {
            $notificationId = (int) ($_POST['notification_id'] ?? 0);

            if ($notificationId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE id = :id
                      AND user_id = :user_id
                ");

                $stmt->execute([
                    'id' => $notificationId,
                    'user_id' => $userId
                ]);
            }

            $link = $_POST['link'] ?? '';

            // Chỉ cho phép chuyển đến trang chi tiết phiếu sửa chữa
            if (preg_match(
                '/^chi_tiet_phieu_sua_chua\.php\?id=[1-9][0-9]*$/',
                $link
            )) {
                header('Location: ' . $link);
                exit;
            }

            header('Location: thong_bao.php');
            exit;
        }

    } catch (PDOException $e) {
        error_log($e->getMessage());
        $thongBaoLoi = 'Không thể xử lý thông báo. Vui lòng thử lại.';
    }
}

// Lấy danh sách thông báo
try {
    $stmt = $pdo->prepare("
        SELECT id, title, message, link, is_read, created_at
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC, id DESC
    ");

    $stmt->execute(['user_id' => $userId]);
    $notifications = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
          AND is_read = 0
    ");

    $stmt->execute(['user_id' => $userId]);
    $unreadCount = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $notifications = [];
    $unreadCount = 0;
    $thongBaoLoi = 'Không thể tải danh sách thông báo.';
}

function dinhDangThoiGianThongBao($datetime): string
{
    if (empty($datetime)) {
        return '';
    }

    $time = strtotime($datetime);

    if ($time === false) {
        return '';
    }

    return date('d/m/Y H:i', $time);
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo hệ thống</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #263238;
        }

        .container {
            max-width: 950px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0;
            font-size: 25px;
        }

        .unread {
            margin-top: 8px;
            color: #64748b;
            font-size: 14px;
        }

        .unread strong {
            color: #e65100;
        }

        .read-all {
            border: none;
            background: #2563eb;
            color: white;
            padding: 11px 16px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .read-all:hover {
            background: #1d4ed8;
        }

        .notification {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            padding: 18px;
            margin-bottom: 12px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .notification.unread-item {
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .notification-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #dbeafe;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .notification-message {
            color: #475569;
            line-height: 1.5;
            margin-bottom: 9px;
            overflow-wrap: anywhere;
        }

        .notification-time {
            color: #94a3b8;
            font-size: 12px;
        }

        .notification-action {
            margin-top: 10px;
        }

        .notification-action button {
            border: none;
            background: #2563eb;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
        }

        .notification-action button:hover {
            background: #1d4ed8;
        }

        .read-label {
            color: #64748b;
            font-size: 12px;
            margin-top: 10px;
        }

        .empty {
            background: white;
            border-radius: 9px;
            padding: 45px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-icon {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }

            h1 {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <h1>🔔 Thông báo hệ thống</h1>

            <div class="unread">
                Bạn có
                <strong><?= $unreadCount ?></strong>
                thông báo chưa đọc.
            </div>
        </div>

        <?php if ($unreadCount > 0): ?>
            <form method="POST">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= eThongBaoPage($csrfToken) ?>"
                >

                <input type="hidden" name="action" value="read_all">

                <button class="read-all" type="submit">
                    Đánh dấu tất cả đã đọc
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($thongBaoLoi !== ''): ?>
        <div class="error">
            <?= eThongBaoPage($thongBaoLoi) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="success">
            Đã cập nhật trạng thái thông báo.
        </div>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>

        <div class="empty">
            <div class="empty-icon">🔕</div>
            <strong>Chưa có thông báo nào</strong>
            <p>Khi có thông báo mới, nội dung sẽ hiển thị tại đây.</p>
        </div>

    <?php else: ?>

        <?php foreach ($notifications as $notification): ?>

            <div class="notification <?= (int) $notification['is_read'] === 0 ? 'unread-item' : '' ?>">

                <div class="notification-icon">
                    <?= (int) $notification['is_read'] === 0 ? '🔔' : '📩' ?>
                </div>

                <div class="notification-content">

                    <div class="notification-title">
                        <?= eThongBaoPage($notification['title']) ?>
                    </div>

                    <div class="notification-message">
                        <?= nl2br(eThongBaoPage($notification['message'])) ?>
                    </div>

                    <div class="notification-time">
                        <?= eThongBaoPage(
                            dinhDangThoiGianThongBao($notification['created_at'])
                        ) ?>
                    </div>

                    <?php if (
                        (int) $notification['is_read'] === 0
                        && !empty($notification['link'])
                    ): ?>

                        <form
                            class="notification-action"
                            method="POST"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= eThongBaoPage($csrfToken) ?>"
                            >

                            <input type="hidden" name="action" value="read_one">

                            <input
                                type="hidden"
                                name="notification_id"
                                value="<?= (int) $notification['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="link"
                                value="<?= eThongBaoPage($notification['link']) ?>"
                            >

                            <button type="submit">
                                Xem phiếu sửa chữa
                            </button>
                        </form>

                    <?php elseif ((int) $notification['is_read'] === 0): ?>

                        <form class="notification-action" method="POST">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= eThongBaoPage($csrfToken) ?>"
                            >

                            <input type="hidden" name="action" value="read_one">

                            <input
                                type="hidden"
                                name="notification_id"
                                value="<?= (int) $notification['id'] ?>"
                            >

                            <button type="submit">
                                Đánh dấu đã đọc
                            </button>
                        </form>

                    <?php else: ?>

                        <div class="read-label">
                            ✓ Đã đọc
                        </div>

                    <?php endif; ?>

                </div>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>
