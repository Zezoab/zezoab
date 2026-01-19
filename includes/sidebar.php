<aside class="sidebar" id="sidebar">
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-text">Dashboard</span>
        </a>

        <a href="calendar.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📆</span>
            <span class="nav-text">Calendar</span>
        </a>

        <a href="appointments.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📋</span>
            <span class="nav-text">Appointments</span>
        </a>

        <a href="clients.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : ''; ?>">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Clients</span>
        </a>

        <a href="staff.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'active' : ''; ?>">
            <span class="nav-icon">👤</span>
            <span class="nav-text">Staff</span>
        </a>

        <a href="services.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
            <span class="nav-icon">✂️</span>
            <span class="nav-text">Services</span>
        </a>

        <a href="reports.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Reports</span>
        </a>

        <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <span class="nav-icon">⚙️</span>
            <span class="nav-text">Settings</span>
        </a>

        <div class="sidebar-divider"></div>

        <a href="book/<?php echo $business['booking_page_slug']; ?>" target="_blank" class="nav-item">
            <span class="nav-icon">🔗</span>
            <span class="nav-text">View Booking Page</span>
        </a>

        <a href="logout.php" class="nav-item">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">Logout</span>
        </a>
    </nav>
</aside>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}
</script>
