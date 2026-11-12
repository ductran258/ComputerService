
<?php

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/config/database.php';

// =====================================================
// 1. HAM HO TRO
// =====================================================

function eNhanVien($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// =====================================================
// 2. LAY DANH SACH NHAN VIEN
// =====================================================

$sql = "
    SELECT
        id,
        full_name,
        username,
        email,
        role,
        phone,
        is_active,
        created_at
    FROM users
    ORDER BY created_at DESC, id DESC
";

$stmt = $pdo->query($sql);
$nhanViens = $stmt->fetchAll();

// =====================================================
// 3. LAY THONG BAO
// =====================================================

$thongBao = $_SESSION['employee_message'] ?? '';
unset($_SESSION['employee_message']);

// =====================================================
// 4. THONG KE
// =====================================================

$tongTaiKhoan = count($nhanViens);

$tongAdmin = 0;
$tongKyThuatVien = 0;
$tongHoatDong = 0;
$tongDaKhoa = 0;

foreach ($nhanViens as $nhanVien) {

    if ($nhanVien['role'] === 'admin') {
        $tongAdmin++;
    } else {
        $tongKyThuatVien++;
    }

    if ((int) $nhanVien['is_active'] === 1) {
        $tongHoatDong++;
    } else {
        $tongDaKhoa++;
    }

}

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quản lý nhân viên</title>

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

        .container {
            max-width: 1500px;
            margin: auto;
        }

        /* HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: #dbeafe;
            color: #2563eb;
            font-size: 22px;
        }

        .header-title h1 {
            margin: 0 0 6px;
            color: #172554;
            font-size: 25px;
        }

        .header-title p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 17px;
            border-radius: 9px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: 0.2s;
        }

        .btn-add:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
        }

        /* STATISTICS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 22px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #fff;
            border: 1px solid #e5eaf1;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            font-size: 19px;
        }

        .stat-icon.blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .stat-icon.purple {
            background: #ede9fe;
            color: #7c3aed;
        }

        .stat-icon.green {
            background: #dcfce7;
            color: #16a34a;
        }

        .stat-icon.red {
            background: #fee2e2;
            color: #dc2626;
        }

        .stat-info span {
            display: block;
            margin-bottom: 6px;
            color: #64748b;
            font-size: 12px;
        }

        .stat-info strong {
            color: #1e293b;
            font-size: 23px;
        }

        /* NOTIFICATION */

        .notification {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 14px 16px;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            border-radius: 9px;
            color: #166534;
            font-size: 13px;
            line-height: 1.5;
        }

        .notification i {
            margin-top: 2px;
        }

        /* TABLE */

        .table-card {
            background: #fff;
            border: 1px solid #e5eaf1;
            border-radius: 13px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 20px 22px;
            border-bottom: 1px solid #edf0f5;
            flex-wrap: wrap;
        }

        .table-header h2 {
            margin: 0;
            color: #1e293b;
            font-size: 16px;
        }

        .table-header p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 12px;
        }

        .total-label {
            padding: 7px 12px;
            border-radius: 20px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1250px;
            border-collapse: collapse;
        }

        th {
            padding: 14px 13px;
            background: #f8fafc;
            color: #64748b;
            text-align: left;
            white-space: nowrap;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        td {
            padding: 15px 13px;
            border-bottom: 1px solid #edf0f5;
            color: #475569;
            font-size: 12px;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .id-text {
            color: #64748b;
            font-weight: 700;
        }

        .employee-name {
            color: #1e293b;
            font-weight: 700;
            font-size: 13px;
        }

        .username {
            color: #475569;
            font-weight: 600;
        }

        .email-text,
        .phone-text {
            color: #64748b;
        }

        /* ROLE */

        .role,
        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            white-space: nowrap;
            font-size: 11px;
            font-weight: 700;
        }

        .role.admin {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .role.technician {
            background: #dcfce7;
            color: #15803d;
        }

        .status.active {
            background: #dcfce7;
            color: #15803d;
        }

        .status.inactive {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* PASSWORD */

        .password-protected {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 11px;
        }

        .password-protected i {
            color: #16a34a;
        }

        /* ACTIONS */

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 32px;
            padding: 7px 10px;
            border: none;
            border-radius: 7px;
            color: #fff;
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-action:hover {
            opacity: 0.85;
            transform: translateY(-1px);
        }

        .btn-edit {
            background: #0d9488;
        }

        .btn-password {
            background: #7c3aed;
        }

        .btn-lock {
            background: #dc2626;
        }

        .btn-unlock {
            background: #16a34a;
        }

        .lock-form {
            display: inline;
            margin: 0;
        }

        .self-account {
            color: #94a3b8;
            font-size: 11px;
            font-style: italic;
        }

        /* EMPTY */

        .empty {
            padding: 45px 20px;
            text-align: center;
            color: #94a3b8;
        }

        .empty i {
            display: block;
            margin-bottom: 13px;
            color: #cbd5e1;
            font-size: 35px;
        }

        .empty strong {
            display: block;
            margin-bottom: 7px;
            color: #475569;
            font-size: 14px;
        }

        .empty span {
            font-size: 12px;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 600px) {

            body {
                padding: 14px;
            }

            .header-title h1 {
                font-size: 21px;
            }

            .header-icon {
                width: 45px;
                height: 45px;
            }

            .stats-grid {
                gap: 10px;
            }

            .stat-card {
                padding: 14px;
                gap: 10px;
            }

            .stat-icon {
                width: 38px;
                height: 38px;
                font-size: 16px;
            }

            .stat-info span {
                font-size: 10px;
            }

            .stat-info strong {
                font-size: 19px;
            }

            .table-header {
                padding: 16px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="page-header">

        <div class="header-title">

            <div class="header-icon">
                <i class="fa-solid fa-users-gear"></i>
            </div>

            <div>
                <h1>Quản lý nhân viên</h1>
                <p>Quản lý tài khoản, vai trò và trạng thái nhân viên trong hệ thống</p>
            </div>

        </div>

        <a
            href="them_nhan_vien.php"
            class="btn-add"
            target="mainContent"
        >
            <i class="fa-solid fa-user-plus"></i>
            Thêm nhân viên
        </a>

    </div>

    <!-- THONG BAO -->

    <?php if ($thongBao !== ''): ?>

        <div class="notification">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= eNhanVien($thongBao) ?></span>
        </div>

    <?php endif; ?>

    <!-- STATISTICS -->

    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-icon blue">
                <i class="fa-solid fa-users"></i>
            </div>

            <div class="stat-info">
                <span>Tổng tài khoản</span>
                <strong><?= $tongTaiKhoan ?></strong>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon purple">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div class="stat-info">
                <span>Quản trị viên</span>
                <strong><?= $tongAdmin ?></strong>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon green">
                <i class="fa-solid fa-user-gear"></i>
            </div>

            <div class="stat-info">
                <span>Đang hoạt động</span>
                <strong><?= $tongHoatDong ?></strong>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon red">
                <i class="fa-solid fa-user-lock"></i>
            </div>

            <div class="stat-info">
                <span>Đã khóa</span>
                <strong><?= $tongDaKhoa ?></strong>
            </div>

        </div>

    </div>

    <!-- EMPLOYEE TABLE -->

    <div class="table-card">

        <div class="table-header">

            <div>
                <h2>
                    <i class="fa-solid fa-list"></i>
                    Danh sách tài khoản
                </h2>

                <p>Thông tin các tài khoản đang được quản lý trong hệ thống</p>
            </div>

            <span class="total-label">
                <?= $tongTaiKhoan ?> tài khoản
            </span>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Họ và tên</th>
                        <th>Tên đăng nhập</th>
                        <th>Mật khẩu</th>
                        <th>Email</th>
                        <th>Số điện thoại</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($nhanViens)): ?>

                        <tr>
                            <td colspan="10">

                                <div class="empty">
                                    <i class="fa-solid fa-users-slash"></i>
                                    <strong>Chưa có tài khoản nhân viên</strong>
                                    <span>Danh sách tài khoản hiện đang trống.</span>
                                </div>

                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($nhanViens as $nhanVien): ?>

                            <tr>

                                <td>
                                    <span class="id-text">
                                        #<?= (int) $nhanVien['id'] ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="employee-name">
                                        <?= eNhanVien($nhanVien['full_name']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="username">
                                        <?= eNhanVien($nhanVien['username']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="password-protected">
                                        <i class="fa-solid fa-shield-halved"></i>
                                        Đã được bảo vệ
                                    </span>
                                </td>

                                <td>
                                    <span class="email-text">
                                        <?= eNhanVien($nhanVien['email']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="phone-text">
                                        <?= eNhanVien($nhanVien['phone']) ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if ($nhanVien['role'] === 'admin'): ?>

                                        <span class="role admin">
                                            <i class="fa-solid fa-user-shield"></i>
                                            Admin
                                        </span>

                                    <?php else: ?>

                                        <span class="role technician">
                                            <i class="fa-solid fa-screwdriver-wrench"></i>
                                            Technician
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if ((int) $nhanVien['is_active'] === 1): ?>

                                        <span class="status active">
                                            <i class="fa-solid fa-circle-check"></i>
                                            Đang hoạt động
                                        </span>

                                    <?php else: ?>

                                        <span class="status inactive">
                                            <i class="fa-solid fa-circle-xmark"></i>
                                            Đã khóa
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= date('d/m/Y H:i', strtotime($nhanVien['created_at'])) ?>
                                </td>

                                <td>

                                    <div class="action-buttons">

                                        <!-- SUA THONG TIN -->

                                        <a
                                            href="cap_nhat_nhan_vien.php?id=<?= (int) $nhanVien['id'] ?>"
                                            class="btn-action btn-edit"
                                            target="mainContent"
                                            title="Sửa thông tin"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                            Sửa
                                        </a>

                                        <!-- DAT LAI MAT KHAU -->

                                        <a
                                            href="dat_lai_mat_khau.php?id=<?= (int) $nhanVien['id'] ?>"
                                            class="btn-action btn-password"
                                            target="mainContent"
                                            title="Đặt lại mật khẩu"
                                        >
                                            <i class="fa-solid fa-key"></i>
                                            Đặt lại mật khẩu
                                        </a>

                                        <!-- KHOA / MO KHOA -->

                                        <?php if ((int) $nhanVien['id'] !== (int) $_SESSION['user_id']): ?>

                                            <form
                                                method="POST"
                                                action="khoa_nhan_vien.php"
                                                class="lock-form"
                                                onsubmit="return confirm('Bạn có chắc chắn muốn thực hiện thao tác này?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= eNhanVien($_SESSION['csrf_token'] ?? '') ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $nhanVien['id'] ?>"
                                                >

                                                <?php if ((int) $nhanVien['is_active'] === 1): ?>

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="lock"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn-action btn-lock"
                                                        title="Khóa tài khoản"
                                                    >
                                                        <i class="fa-solid fa-lock"></i>
                                                        Khóa
                                                    </button>

                                                <?php else: ?>

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="unlock"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn-action btn-unlock"
                                                        title="Mở khóa tài khoản"
                                                    >
                                                        <i class="fa-solid fa-lock-open"></i>
                                                        Mở khóa
                                                    </button>

                                                <?php endif; ?>

                                            </form>

                                        <?php else: ?>

                                            <span class="self-account">
                                                Tài khoản của bạn
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
