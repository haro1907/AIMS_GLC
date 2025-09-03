<?php 
require_once __DIR__ . '/../data/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --light-blue: #3b82f6;
            --accent-yellow: #fbbf24;
            --light-yellow: #fef3c7;
            --dark-blue: #1e40af;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --border-gray: #e5e7eb;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: var(--white);
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.3);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.1;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo i {
            background: var(--accent-yellow);
            color: var(--primary-blue);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.3);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            background: var(--accent-yellow);
            color: var(--primary-blue);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .role-badge {
            background: rgba(251, 191, 36, 0.2);
            color: var(--accent-yellow);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        /* Navigation Styles */
        .navigation {
            background: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .nav-btn {
            text-decoration: none;
            padding: 0.7rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-dark);
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .nav-btn:hover {
            background: var(--light-yellow);
            color: var(--primary-blue);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.2);
        }

        .nav-btn.active {
            background: var(--accent-yellow);
            color: var(--primary-blue);
            font-weight: 600;
        }

        .nav-btn.logout {
            margin-left: auto;
            background: var(--error);
            color: var(--white);
        }

        .nav-btn.logout:hover {
            background: #dc2626;
            color: var(--white);
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .content-card {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border-gray);
            position: relative;
            overflow: hidden;
        }

        .content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--accent-yellow) 100%);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid var(--border-gray);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-yellow);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
            transform: translateY(-1px);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 3rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            text-align: center;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: var(--accent-yellow);
            color: var(--primary-blue);
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.3);
        }

        .btn-secondary:hover {
            background: #f59e0b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.4);
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--white);
        }

        .btn-danger {
            background: var(--error);
            color: var(--white);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            margin: 1.5rem 0;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
        }

        .table th {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border: none;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-gray);
        }

        .table tr:hover {
            background: var(--light-gray);
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid var(--error);
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border-left: 4px solid var(--warning);
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid var(--light-blue);
        }

        /* Grid System */
        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        /* Stats Cards */
        .stats-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--accent-yellow);
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .stats-label {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .nav-content {
                flex-direction: column;
                gap: 0.5rem;
            }

            .container {
                padding: 0 1rem;
            }

            .content-card {
                padding: 1.5rem;
            }

            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--accent-yellow);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-gray);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent-yellow);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--warning);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <div>
                    <div style="font-size: 1.2rem;">GLC AIMS</div>
                    <div style="font-size: 0.8rem; opacity: 0.9;">Academic Information Management</div>
                </div>
            </div>
            <?php if (Auth::isLoggedIn()): ?>
                <div class="user-info">
                    <div class="user-avatar">
                        <?= substr($_SESSION['full_name'] ?? $_SESSION['username'], 0, 1) ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></div>
                        <div class="role-badge"><?= htmlspecialchars($_SESSION['role_name']) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="navigation">
        <div class="nav-content">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            $role = $_SESSION['role_name'] ?? '';
            
            if ($role === "Super Admin") {
                echo '<a class="nav-btn ' . ($currentPage == 'dashboard.php' ? 'active' : '') . '" href="/AIMS_ver1/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/admin/users.php"><i class="fas fa-users"></i> Manage Users</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/admin/reports.php"><i class="fas fa-chart-bar"></i> Reports</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/admin/activity_logs.php"><i class="fas fa-history"></i> Activity Logs</a>';
            } elseif ($role === "Registrar") {
                echo '<a class="nav-btn ' . ($currentPage == 'dashboard.php' ? 'active' : '') . '" href="/AIMS_ver1/registrar/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/registrar/upload_grades.php"><i class="fas fa-upload"></i> Upload Grades</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/registrar/upload_files.php"><i class="fas fa-file-upload"></i> Upload Files</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/registrar/manage_grades.php"><i class="fas fa-edit"></i> Manage Grades</a>';
            } elseif ($role === "SAO") {
                echo '<a class="nav-btn ' . ($currentPage == 'dashboard.php' ? 'active' : '') . '" href="/AIMS_ver1/sao/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/sao/announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/sao/inventory.php"><i class="fas fa-boxes"></i> Inventory</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/sao/borrow_requests.php"><i class="fas fa-hand-holding"></i> Borrow Requests</a>';
            } elseif ($role === "Student") {
                echo '<a class="nav-btn ' . ($currentPage == 'dashboard.php' ? 'active' : '') . '" href="/AIMS_ver1/student/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/student/grades.php"><i class="fas fa-chart-line"></i> My Grades</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/student/files.php"><i class="fas fa-folder"></i> My Files</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/student/announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>';
                echo '<a class="nav-btn" href="/AIMS_ver1/student/borrow.php"><i class="fas fa-hand-holding"></i> Borrow Items</a>';
            } else {
                echo '<a class="nav-btn" href="/AIMS_ver1/index.php"><i class="fas fa-sign-in-alt"></i> Login</a>';
            }
            
            if (Auth::isLoggedIn()) {
                echo '<a class="nav-btn logout" href="/AIMS_ver1/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>';
            }
            ?>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <div class="content-card">