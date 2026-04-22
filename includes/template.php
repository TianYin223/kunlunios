<?php
function renderHeader($title = '', $extraStyles = '') {
    $currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
    $roleClass = $currentUser && ($currentUser['role'] ?? '') === 'admin' ? 'role-admin' : 'role-inspector';
    $siteName = function_exists('getSiteName') ? getSiteName() : SITE_NAME;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> - <?= h($siteName) ?></title>
    <style>
        :root {
            --bg: #f4f7fb;
            --surface: #ffffff;
            --surface-muted: #f8fafe;
            --text: #0f172a;
            --text-secondary: #64748b;
            --line: #e2e8f0;
            --line-strong: #d7dfeb;
            --primary: #1d4ed8;
            --primary-hover: #1e40af;
            --primary-soft: #eef4ff;
            --success: #0f9f6b;
            --danger: #d13232;
            --warning: #b7791f;
            --focus-ring: 0 0 0 3px rgba(59, 130, 246, 0.16);
            --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06);
            --shadow-md: 0 12px 24px rgba(15, 23, 42, 0.08);
            --radius-md: 12px;
            --radius-sm: 9px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html {
            -webkit-text-size-adjust: 100%;
        }
        body {
            min-height: 100vh;
            color: var(--text);
            background: var(--bg);
            font-family: "MiSans", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", "Noto Sans SC", sans-serif;
            line-height: 1.5;
            letter-spacing: 0.2px;
        }
        img {
            max-width: 100%;
            height: auto;
        }
        a {
            color: inherit;
        }
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1100;
            height: 64px;
            padding: 0 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--line);
            box-shadow: var(--shadow-sm);
        }
        .navbar-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #0b1220;
            text-decoration: none;
            font-size: 18px;
            font-weight: 700;
        }
        .navbar-brand::before {
            content: "";
            width: 16px;
            height: 16px;
            border-radius: 5px;
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
            box-shadow: 0 2px 8px rgba(29, 78, 216, 0.35);
        }
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar-user span {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--text-secondary);
            font-size: 13px;
        }
        .navbar-user a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 13px;
            border-radius: 10px;
            text-decoration: none;
            border: 1px solid var(--primary);
            background: var(--primary);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .navbar-user a:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        .container {
            max-width: 1380px;
            margin: 0 auto;
            padding: 22px;
        }
        body.role-admin .container {
            max-width: none;
            width: 100%;
            padding: 22px clamp(16px, 2vw, 36px);
        }
        .sidebar {
            width: 248px;
            flex: 0 0 248px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            padding: 12px 10px;
            box-shadow: var(--shadow-sm);
        }
        .sidebar-menu {
            list-style: none;
        }
        .sidebar-menu li + li {
            margin-top: 3px;
        }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            min-height: 42px;
            padding: 0 13px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            text-decoration: none;
            color: #334155;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .sidebar-menu li a:hover {
            background: var(--surface-muted);
            border-color: var(--line);
            color: #0f172a;
        }
        .sidebar-menu li a.active {
            color: var(--primary);
            background: var(--primary-soft);
            border-color: #cfdcfe;
            box-shadow: inset 2px 0 0 var(--primary);
            font-weight: 600;
        }
        .main-content {
            flex: 1;
            min-width: 0;
            margin-left: 18px;
        }
        .page-header {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
        }
        .page-header h2 {
            font-size: 22px;
            color: #0f172a;
            letter-spacing: 0.3px;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 38px;
            padding: 0 14px;
            border: 1px solid var(--primary);
            border-radius: 10px;
            background: var(--primary);
            color: #fff;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.15px;
            white-space: nowrap;
            user-select: none;
            box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04), 0 6px 14px rgba(29, 78, 216, 0.15);
            transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }
        .btn:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08), 0 10px 18px rgba(29, 78, 216, 0.18);
        }
        .btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12), 0 4px 10px rgba(15, 23, 42, 0.12);
        }
        .btn:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring), 0 1px 2px rgba(15, 23, 42, 0.08);
        }
        .btn[disabled],
        .btn:disabled,
        .btn.disabled {
            opacity: 0.55;
            cursor: not-allowed;
            pointer-events: none;
            transform: none;
            box-shadow: none;
        }
        .btn-sm {
            min-height: 32px;
            padding: 0 10px;
            font-size: 12px;
            border-radius: 8px;
        }
        .btn-secondary {
            background: #fff;
            border-color: var(--line-strong);
            color: #334155;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #b8c6dc;
            color: #0f172a;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
        }
        .btn-success {
            background: var(--success);
            border-color: var(--success);
            box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04), 0 6px 14px rgba(15, 159, 107, 0.18);
        }
        .btn-success:hover {
            background: #0c8459;
            border-color: #0c8459;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08), 0 10px 18px rgba(12, 132, 89, 0.2);
        }
        .btn-danger {
            background: var(--danger);
            border-color: var(--danger);
            box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04), 0 6px 14px rgba(209, 50, 50, 0.18);
        }
        .btn-danger:hover {
            background: #b22626;
            border-color: #b22626;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08), 0 10px 18px rgba(178, 38, 38, 0.2);
        }
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-scroll {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid var(--line);
            border-radius: 10px;
        }
        .table th,
        .table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
            white-space: nowrap;
            vertical-align: middle;
        }
        .table th {
            font-size: 13px;
            color: #475569;
            font-weight: 600;
            background: #f8fafc;
        }
        .table tr:last-child td {
            border-bottom: none;
        }
        .table tbody tr:hover {
            background: #fbfdff;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #94a3b8;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            min-height: 40px;
            padding: 0 12px;
            border: 1px solid var(--line-strong);
            border-radius: 10px;
            background: #fff linear-gradient(#fff, #fff) padding-box;
            color: var(--text);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        .form-group select {
            padding-right: 34px;
            appearance: none;
            background-image:
                linear-gradient(45deg, transparent 50%, #64748b 50%),
                linear-gradient(135deg, #64748b 50%, transparent 50%),
                linear-gradient(#fff, #fff);
            background-position:
                calc(100% - 17px) calc(50% - 2px),
                calc(100% - 12px) calc(50% - 2px),
                0 0;
            background-size: 5px 5px, 5px 5px, 100% 100%;
            background-repeat: no-repeat;
        }
        .form-group textarea {
            padding: 10px 12px;
            min-height: 88px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #93b4ff;
            box-shadow: var(--focus-ring);
            background-color: #fff;
        }
        .form-group input[readonly],
        .form-group textarea[readonly],
        .form-group input:disabled,
        .form-group select:disabled,
        .form-group textarea:disabled {
            background: #f8fafc;
            color: #64748b;
            cursor: not-allowed;
        }
        .form-group .hint,
        .form-group small {
            display: block;
            margin-top: 6px;
            line-height: 1.45;
        }
        form[style*="display: flex"][style*="gap: 10px"] input,
        form[style*="display: flex"][style*="gap: 10px"] select,
        form[style*="display: flex"][style*="gap: 10px"] button {
            min-height: 38px;
            border-radius: 10px;
        }
        input[type="checkbox"],
        input[type="radio"] {
            accent-color: var(--primary);
        }
        input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }
        input[type="radio"] {
            width: 16px;
            height: 16px;
        }
        small {
            color: var(--text-secondary);
        }
        .alert {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid transparent;
            font-size: 14px;
        }
        .alert-success {
            color: #0f5132;
            background: #ecfdf5;
            border-color: #b7ebcf;
        }
        .alert-danger {
            color: #7f1d1d;
            background: #fef2f2;
            border-color: #fecaca;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 22px;
            padding: 0 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .badge-success {
            color: #166534;
            background: #ecfdf3;
            border-color: #bbf7d0;
        }
        .badge-danger {
            color: #991b1b;
            background: #fef2f2;
            border-color: #fecaca;
        }
        .badge-info {
            color: #1e3a8a;
            background: #eff6ff;
            border-color: #bfdbfe;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .mt-20 {
            margin-top: 20px;
        }
        .mb-20 {
            margin-bottom: 20px;
        }
        .flex {
            display: flex;
        }
        .flex-between {
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .ranking-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #edf2f7;
        }
        .ranking-item:last-child {
            border-bottom: none;
        }
        .ranking-num {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            background: #94a3b8;
        }
        .ranking-num.top-1 {
            background: #f59e0b;
        }
        .ranking-num.top-2 {
            background: #9ca3af;
        }
        .ranking-num.top-3 {
            background: #b45309;
        }
        .ranking-num.normal {
            color: #475569;
            background: #e2e8f0;
        }
        .ranking-info {
            flex: 1;
            min-width: 0;
        }
        .ranking-score {
            color: var(--primary);
            font-weight: 700;
        }
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 18px;
        }
        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff;
            color: #334155;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .page-link:hover {
            border-color: #aac2f8;
            color: var(--primary);
            background: #f7faff;
        }
        .page-link.active {
            color: #fff;
            background: var(--primary);
            border-color: var(--primary);
        }
        .page-ellipsis {
            color: #94a3b8;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            padding: 18px;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        .stat-value {
            font-size: 30px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .stat-label {
            color: var(--text-secondary);
            font-size: 13px;
        }
        .loading {
            text-align: center;
            color: #94a3b8;
            padding: 20px;
        }
        .empty-state {
            text-align: center;
            color: #94a3b8;
            padding: 40px 20px;
        }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .action-buttons form {
            margin: 0;
        }
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1200;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.45);
            padding: 20px;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            width: 100%;
            max-width: 560px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-md);
            padding: 20px;
        }
        .modal-content .form-group:last-of-type {
            margin-bottom: 18px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .modal-close {
            font-size: 22px;
            color: #94a3b8;
            cursor: pointer;
            line-height: 1;
        }
        .modal-close:hover {
            color: #334155;
        }
        @media (max-width: 1100px) {
            .container {
                padding: 16px;
            }
            body.role-admin .container {
                padding: 16px;
            }
            .sidebar {
                width: 220px;
                flex-basis: 220px;
            }
        }
        @media (max-width: 860px) {
            .navbar {
                height: auto;
                min-height: 56px;
                padding: 10px 12px;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .navbar-user {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            .container {
                padding: 12px;
            }
            body.role-admin .container {
                padding: 12px;
            }
            .container > div[style*="display: flex"] {
                flex-direction: column !important;
                gap: 12px !important;
            }
            .sidebar {
                width: 100%;
                flex-basis: auto;
                padding: 8px;
                overflow-x: auto;
            }
            .sidebar-menu {
                display: flex;
                gap: 8px;
                white-space: nowrap;
            }
            .sidebar-menu li {
                flex: 0 0 auto;
            }
            .sidebar-menu li + li {
                margin-top: 0;
            }
            .sidebar-menu li a {
                min-height: 38px;
                padding: 0 11px;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .page-header,
            .card {
                padding: 14px;
                margin-bottom: 12px;
                border-radius: 12px;
            }
            .page-header h2 {
                font-size: 18px;
            }
            .btn {
                min-height: 40px;
                padding: 0 12px;
            }
            .btn-sm {
                min-height: 34px;
            }
            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 16px;
            }
            .table {
                min-width: 620px;
            }
            .table th,
            .table td {
                padding: 10px 12px;
                font-size: 13px;
            }
            .table.mobile-table-card {
                min-width: 0;
                border-collapse: separate;
                border-spacing: 0;
            }
            .table.mobile-table-card thead {
                display: none;
            }
            .table.mobile-table-card tbody {
                display: block;
            }
            .table.mobile-table-card tr {
                display: block;
                margin-bottom: 10px;
                border: 1px solid var(--line);
                border-radius: 10px;
                background: #fff;
                padding: 6px 10px;
            }
            .table.mobile-table-card td {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 10px;
                border-bottom: 1px dashed #e2e8f0;
                white-space: normal;
                text-align: right !important;
                padding: 8px 0;
            }
            .table.mobile-table-card td:last-child {
                border-bottom: none;
            }
            .table.mobile-table-card td::before {
                content: attr(data-label);
                color: #64748b;
                font-weight: 600;
                text-align: left;
                min-width: 82px;
                flex: 0 0 82px;
            }
            .table.mobile-table-card td[data-label=""]::before {
                content: "";
            }
            .table.mobile-table-card td > .btn,
            .table.mobile-table-card td > form {
                margin-left: auto;
            }
            form[style*="display: flex"] {
                width: 100%;
            }
            form[style*="display: flex"][style*="gap: 10px"] {
                flex-direction: column !important;
                align-items: stretch !important;
            }
            [style*="width: 100px"],
            [style*="width: 180px"],
            [style*="width: 200px"] {
                width: 100% !important;
                max-width: 100% !important;
            }
            [style*="display: flex"][style*="gap: 20px"] {
                flex-direction: column !important;
                gap: 12px !important;
            }
            [style*="display: flex"][style*="gap: 10px"] {
                flex-wrap: wrap !important;
            }
            [style*="width: 400px"],
            [style*="width: 500px"],
            [style*="max-width: 900px"] {
                width: calc(100vw - 24px) !important;
                max-width: calc(100vw - 24px) !important;
            }
            [style*="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr))"] {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
            .pagination {
                flex-wrap: wrap;
            }
        }
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
            body.role-admin .container {
                padding: 10px;
            }
            .navbar-brand {
                font-size: 16px;
            }
            .table {
                min-width: 540px;
            }
            [style*="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr))"] {
                grid-template-columns: 1fr !important;
            }
        }
        <?= $extraStyles ?>
    </style>
</head>
<body class="<?= $roleClass ?>">
    <nav class="navbar">
        <a href="index.php" class="navbar-brand"><?= h($siteName) ?></a>
        <?php if ($currentUser): ?>
            <div class="navbar-user">
                <span>
                    <?= h($currentUser['real_name']) ?>
                    (<?= $currentUser['role'] === 'admin' ? '管理员端' : '用户端' ?>)
                </span>
                <a href="../logout.php">退出登录</a>
            </div>
        <?php endif; ?>
    </nav>
<?php
}

function renderFooter() {
?>
<script>
(function () {
    var liveClockTimer = null;

    function enableResponsiveTables() {
        var tables = document.querySelectorAll('table.table');
        tables.forEach(function (table) {
            var parent = table.parentElement;
            if (!parent || parent.classList.contains('table-scroll')) {
                return;
            }
            var wrapper = document.createElement('div');
            wrapper.className = 'table-scroll';
            parent.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });
    }

    function annotateTablesForMobile() {
        var tables = document.querySelectorAll('table.table');
        tables.forEach(function (table) {
            if (table.dataset.mobileAnnotated === '1') {
                return;
            }
            var headers = [];
            table.querySelectorAll('thead th').forEach(function (th) {
                headers.push((th.textContent || '').trim());
            });
            if (headers.length === 0) {
                return;
            }
            table.querySelectorAll('tbody tr').forEach(function (tr) {
                tr.querySelectorAll('td').forEach(function (td, idx) {
                    td.setAttribute('data-label', headers[idx] || '');
                });
            });
            table.classList.add('mobile-table-card');
            table.dataset.mobileAnnotated = '1';
        });
    }

    function formatTwoDigits(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function formatDatetime(date) {
        var year = date.getFullYear();
        var month = formatTwoDigits(date.getMonth() + 1);
        var day = formatTwoDigits(date.getDate());
        var hour = formatTwoDigits(date.getHours());
        var minute = formatTwoDigits(date.getMinutes());
        var second = formatTwoDigits(date.getSeconds());
        return year + '/' + month + '/' + day + ' ' + hour + ':' + minute + ':' + second;
    }

    function formatWeekdayCn(date) {
        var weekdays = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
        return weekdays[date.getDay()];
    }

    function refreshLiveClocks() {
        var now = new Date();
        document.querySelectorAll('[data-live-clock]').forEach(function (node) {
            var type = (node.getAttribute('data-live-clock') || '').toLowerCase();
            if (type === 'weekday') {
                node.textContent = formatWeekdayCn(now);
            } else {
                node.textContent = formatDatetime(now);
            }
        });
    }

    function initLiveClocks() {
        if (!document.querySelector('[data-live-clock]')) {
            return;
        }
        refreshLiveClocks();
        if (liveClockTimer !== null) {
            window.clearInterval(liveClockTimer);
        }
        liveClockTimer = window.setInterval(refreshLiveClocks, 1000);
    }

    function init() {
        enableResponsiveTables();
        annotateTablesForMobile();
        initLiveClocks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
</body>
</html>
<?php
}

