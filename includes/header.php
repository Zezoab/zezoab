<header class="main-header">
    <div class="header-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
        <h1 class="header-logo"><?php echo SITE_NAME; ?></h1>
    </div>
    <div class="header-right">
        <div class="header-business-name">
            <?php echo htmlspecialchars($business['business_name']); ?>
        </div>
        <div class="header-menu">
            <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
        </div>
    </div>
</header>
