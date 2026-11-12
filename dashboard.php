
<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Computer Service | Admin</title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --navy: #172554;
            --navy-light: #1e3a8a;
            --blue: #3b82f6;
            --blue-light: #eff6ff;
            --background: #f1f5f9;
            --white: #ffffff;
            --text: #1e293b;
            --muted: #94a3b8;
            --border: rgba(255,255,255,.09);
            --sidebar-width: 260px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            background: var(--background);
            color: var(--text);
        }

        /* HEADER */
        .header {
            height: 76px;
            padding: 0 28px;
            background: var(--navy);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 3px 12px rgba(15,23,42,.12);
            position: relative;
            z-index: 10;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(59,130,246,.2);
            border: 1px solid rgba(147,197,253,.25);
            border-radius: 12px;
        }

        .brand-icon svg {
            width: 24px;
            height: 24px;
            color: #93c5fd;
        }

        .header-info h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        .header-info p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #cbd5e1;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #2563eb;
            color: white;
            font-weight: 700;
        }

        .user-text {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: white;
        }

        .user-role {
            font-size: 11px;
            color: #bfdbfe;
        }

        .logout {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: rgba(239,68,68,.13);
            border: 1px solid rgba(248,113,113,.25);
            color: #fecaca;
            text-decoration: none;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .logout:hover {
            background: #dc2626;
            color: white;
        }

        .logout svg {
            width: 17px;
            height: 17px;
        }

        /* MAIN LAYOUT */
        .container {
            display: grid;
            grid-template-columns: var(--sidebar-width) minmax(0,1fr);
            gap: 20px;
            max-width: 1800px;
            margin: auto;
            padding: 20px;
            height: calc(100vh - 76px);
            transition: grid-template-columns .25s ease;
        }

        /* SIDEBAR */
        .sidebar {
            background: var(--navy);
            border-radius: 14px;
            padding: 20px 13px;
            height: 100%;
            overflow: visible;
            position: relative;
            box-shadow: 0 5px 18px rgba(15,23,42,.12);
        }

        .sidebar-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 10px 17px;
            margin-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-heading span {
            color: #94a3b8;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.2px;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 13px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 500;
            transition: .2s ease;
            position: relative;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,.08);
            color: white;
            transform: translateX(2px);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(37,99,235,.25);
        }

        .menu-icon {
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .menu-icon svg {
            width: 18px;
            height: 18px;
            stroke-width: 1.9;
        }

        /* CONTENT */
        .content {
            min-width: 0;
            height: 100%;
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(15,23,42,.06);
            border: 1px solid #e2e8f0;
        }

        .content iframe {
            display: block;
            width: 100%;
            height: 100%;
            min-height: 650px;
            border: none;
            background: var(--background);
        }

        /* COLLAPSED SIDEBAR */
        .container.collapsed {
            grid-template-columns: 76px minmax(0,1fr);
        }

        .container.collapsed .sidebar {
            padding-left: 9px;
            padding-right: 9px;
        }

        .container.collapsed .sidebar-heading span,
        .container.collapsed .menu-label {
            display: none;
        }

        .container.collapsed .sidebar-menu a {
            justify-content: center;
            padding: 13px 8px;
        }

        .container.collapsed .menu-icon {
            width: 24px;
            height: 24px;
        }

        /* TOOLTIP */
        .container.collapsed .sidebar-menu a::after {
            content: attr(data-tooltip);

            position: absolute;
            left: calc(100% + 14px);
            top: 50%;

            transform: translate(-6px, -50%);

            padding: 10px 14px;

            background: #0f172a;
            color: #ffffff;

            font-size: 12px;
            font-weight: 600;
            line-height: 1.4;
            white-space: nowrap;

            border: 1px solid rgba(255,255,255,.1);
            border-radius: 8px;

            box-shadow: 0 6px 18px rgba(0,0,0,.22);

            opacity: 0;
            visibility: hidden;
            pointer-events: none;

            transition:
                opacity .2s ease,
                transform .2s ease,
                visibility .2s ease;

            z-index: 9999;
        }

        .container.collapsed .sidebar-menu a:hover {
            z-index: 100;
        }

        .container.collapsed .sidebar-menu a:hover::after {
            opacity: 1;
            visibility: visible;
            transform: translate(0, -50%);
        }

        /* TOOLTIP ARROW */
        .container.collapsed .sidebar-menu a::before {
            content: "";

            position: absolute;
            left: calc(100% + 8px);
            top: 50%;

            transform: translateY(-50%);

            border-width: 5px;
            border-style: solid;
            border-color: transparent #0f172a transparent transparent;

            opacity: 0;
            visibility: hidden;
            pointer-events: none;

            transition: opacity .2s ease;
            z-index: 9999;
        }

        .container.collapsed .sidebar-menu a:hover::before {
            opacity: 1;
            visibility: visible;
        }

        /* TOGGLE BUTTON */
        .toggle-sidebar {
            border: none;
            background: rgba(255,255,255,.1);
            color: white;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: .2s;
            flex-shrink: 0;
        }

        .toggle-sidebar:hover {
            background: rgba(255,255,255,.2);
        }

        .toggle-sidebar svg {
            width: 18px;
            height: 18px;
        }

        /* RESPONSIVE */
        @media (max-width: 850px) {
            .container {
                grid-template-columns: 1fr;
                height: auto;
                min-height: calc(100vh - 76px);
                overflow: visible;
            }

            .sidebar {
                height: auto;
                padding: 15px;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .container.collapsed {
                grid-template-columns: 1fr;
            }

            .container.collapsed .sidebar-heading span,
            .container.collapsed .menu-label {
                display: block;
            }

            .container.collapsed .sidebar-menu a {
                justify-content: flex-start;
            }

            /* Không hiện tooltip trên màn hình nhỏ */
            .container.collapsed .sidebar-menu a::after,
            .container.collapsed .sidebar-menu a::before {
                display: none;
            }

            .content {
                height: 750px;
            }

            .toggle-sidebar {
                display: none;
            }
        }

        @media (max-width: 550px) {
            .header {
                height: auto;
                min-height: 76px;
                padding: 12px 14px;
                gap: 10px;
            }

            .brand-icon {
                width: 36px;
                height: 36px;
            }

            .header-info h1 {
                font-size: 16px;
            }

            .header-info p {
                font-size: 10px;
            }

            .user-info {
                display: none;
            }

            .logout {
                padding: 9px 10px;
                font-size: 11px;
            }

            .container {
                padding: 10px;
                gap: 12px;
            }

            .sidebar-menu {
                grid-template-columns: 1fr;
            }

            .content {
                height: 750px;
            }
        }
    </style>
</head>

<body>

<header class="header">

    <div class="header-left">

        <div class="brand-icon">
            <i data-lucide="monitor-cog"></i>
        </div>

        <div class="header-info">
            <h1>Computer Service</h1>
            <p>Hệ thống quản lý sửa chữa máy tính</p>
        </div>

    </div>

    <div class="header-right">

        <div class="user-info">

            <div class="avatar">
                <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
            </div>

            <div class="user-text">
                <span class="user-name">
                    <?= htmlspecialchars($_SESSION['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </span>

                <span class="user-role">Quản trị viên</span>
            </div>

        </div>

        <a href="dang_xuat.php" class="logout">
            <i data-lucide="log-out"></i>
            Đăng xuất
        </a>

    </div>

</header>

<main class="container" id="mainContainer">

    <aside class="sidebar">

        <div class="sidebar-heading">

            <span>QUẢN LÝ CHỨC NĂNG</span>

            <button
                type="button"
                class="toggle-sidebar"
                id="toggleSidebar"
                title="Thu gọn hoặc mở rộng menu"
            >
                <i data-lucide="panel-left-close"></i>
            </button>

        </div>

        <nav class="sidebar-menu">

            <a href="tong_quan.php" target="mainContent"
               class="active" data-tooltip="Tổng quan">
                <span class="menu-icon"><i data-lucide="layout-dashboard"></i></span>
                <span class="menu-label">Tổng quan</span>
            </a>

            <a href="bao_cao_thong_ke.php" target="mainContent"
               data-tooltip="Báo cáo thống kê">
                <span class="menu-icon"><i data-lucide="chart-no-axes-combined"></i></span>
                <span class="menu-label">Báo cáo thống kê</span>
            </a>

            <a href="khach_hang.php" target="mainContent"
               data-tooltip="Quản lý khách hàng">
                <span class="menu-icon"><i data-lucide="users"></i></span>
                <span class="menu-label">Quản lý khách hàng</span>
            </a>

            <a href="thiet_bi.php" target="mainContent"
               data-tooltip="Quản lý thiết bị">
                <span class="menu-icon"><i data-lucide="monitor"></i></span>
                <span class="menu-label">Quản lý thiết bị</span>
            </a>

            <a href="phieu_sua_chua.php" target="mainContent"
               data-tooltip="Quản lý phiếu sửa chữa">
                <span class="menu-icon"><i data-lucide="wrench"></i></span>
                <span class="menu-label">Quản lý phiếu sửa chữa</span>
            </a>

            <a href="linh_kien.php" target="mainContent"
               data-tooltip="Kho linh kiện">
                <span class="menu-icon"><i data-lucide="package"></i></span>
                <span class="menu-label">Kho linh kiện</span>
            </a>

            <a href="bao_cao_tieu_thu_kho.php" target="mainContent"
               data-tooltip="Báo cáo tiêu thụ kho">
                <span class="menu-icon"><i data-lucide="chart-pie"></i></span>
                <span class="menu-label">Báo cáo tiêu thụ kho</span>
            </a>

            <a href="hoa_don.php" target="mainContent"
               data-tooltip="Quản lý hóa đơn">
                <span class="menu-icon"><i data-lucide="receipt"></i></span>
                <span class="menu-label">Quản lý hóa đơn</span>
            </a>

            <a href="nhan_vien.php" target="mainContent"
               data-tooltip="Quản lý nhân viên">
                <span class="menu-icon"><i data-lucide="user-cog"></i></span>
                <span class="menu-label">Quản lý nhân viên</span>
            </a>

            <a href="lich_su_dang_nhap.php" target="mainContent"
               data-tooltip="Lịch sử đăng nhập">
                <span class="menu-icon"><i data-lucide="history"></i></span>
                <span class="menu-label">Lịch sử đăng nhập</span>
            </a>

            <a href="doi_mat_khau.php" target="mainContent"
               data-tooltip="Đổi mật khẩu">
                <span class="menu-icon"><i data-lucide="lock-keyhole"></i></span>
                <span class="menu-label">Đổi mật khẩu</span>
            </a>

            <a href="ho_so_ca_nhan.php" target="mainContent"
               data-tooltip="Hồ sơ cá nhân">
                <span class="menu-icon"><i data-lucide="circle-user-round"></i></span>
                <span class="menu-label">Hồ sơ cá nhân</span>
            </a>

        </nav>

    </aside>

    <section class="content">

        <iframe
            src="tong_quan.php"
            name="mainContent"
            title="Nội dung quản lý"
        ></iframe>

    </section>

</main>

<script>
    lucide.createIcons();

    // Xử lý menu đang được chọn
    const menuLinks = document.querySelectorAll('.sidebar-menu a');

    menuLinks.forEach(link => {
        link.addEventListener('click', function () {

            menuLinks.forEach(item => {
                item.classList.remove('active');
            });

            this.classList.add('active');
        });
    });

    // Thu gọn và mở rộng Sidebar
    const mainContainer = document.getElementById('mainContainer');
    const toggleSidebar = document.getElementById('toggleSidebar');

    toggleSidebar.addEventListener('click', function () {

        mainContainer.classList.toggle('collapsed');

        const isCollapsed = mainContainer.classList.contains('collapsed');

        this.innerHTML = isCollapsed
            ? '<i data-lucide="panel-left-open"></i>'
            : '<i data-lucide="panel-left-close"></i>';

        lucide.createIcons();
    });
</script>

</body>
</html>
