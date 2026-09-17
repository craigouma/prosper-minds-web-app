<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?> – Prosperminds Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-wrapper">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="../assets/images/fisrt-logo.png" alt="Prosperminds">
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php"     class="nav-item <?php echo ($activePage==='dashboard')     ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i><span>Overview</span>
            </a>
            <a href="registrations.php" class="nav-item <?php echo ($activePage==='registrations') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i><span>Registrations</span>
            </a>
            <a href="events.php"        class="nav-item <?php echo ($activePage==='events')        ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i><span>Events</span>
            </a>
            <?php if (hasPermission('accounting', 'view')): ?>
            <a href="accounting.php"    class="nav-item <?php echo ($activePage==='accounting')    ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i><span>Accounting</span>
            </a>
            <?php endif; ?>
            <a href="users.php"         class="nav-item <?php echo ($activePage==='users')         ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i><span>Users</span>
            </a>
            <a href="settings.php"      class="nav-item <?php echo ($activePage==='settings')      ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i><span>Settings</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div style="font-size:13px;color:#e2e8f0;font-weight:600;">
                        <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                    </div>
                    <div style="font-size:11px;margin-top:1px;">
                        <?php if (isSuper()): ?>
                            <span style="color:#4ade80;">&#9679; Super Admin</span>
                        <?php else: ?>
                            <span style="color:#94a3b8;">&#9679; Editor</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <div class="content-header">
            <h1><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h1>
            <a href="../index.php" target="_blank" class="btn btn-outline btn-sm">
                <i class="fas fa-external-link-alt"></i> View Site
            </a>
        </div>
        <?php if (!empty($_SESSION['perm_error'])): ?>
        <div style="margin:0 28px;margin-top:16px;">
            <div class="alert alert-danger">
                <i class="fas fa-ban"></i>
                <?php echo htmlspecialchars($_SESSION['perm_error']); unset($_SESSION['perm_error']); ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="content-body">
