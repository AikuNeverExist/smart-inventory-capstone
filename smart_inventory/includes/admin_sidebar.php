<?php
require_once __DIR__ . '/avatar_helper.php';
/* Shared admin navigation. Included only for authenticated admin pages. */
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

function adminNavActive(string $match, bool $exact = false): string {
    global $currentPath;
    if ($exact) {
        return $currentPath === $match ? ' active' : '';
    }
    return strpos($currentPath, $match) === 0 ? ' active' : '';
}
?>

<button
    type="button"
    class="admin-sidebar-toggle"
    id="adminSidebarToggle"
    aria-label="Open navigation"
    aria-controls="adminUnifiedSidebar"
    aria-expanded="false"
>
    <img src="/smart_inventory/assets/icons/menu.svg" alt="">
    <span>Menu</span>
</button>

<div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>

<aside class="admin-unified-sidebar" id="adminUnifiedSidebar">

    <div class="admin-sidebar-brand">
        <div class="admin-sidebar-brand-icon">
            <img src="/smart_inventory/assets/brand.svg" alt="">
        </div>
        <div class="admin-sidebar-brand-copy">
            <strong>Smart Inventory</strong>
            <span>Sales Management System</span>
        </div>
    </div>

    <nav class="admin-sidebar-nav" aria-label="Admin navigation">

        <div class="admin-sidebar-section-label">Overview</div>
        <a class="admin-sidebar-link<?php echo adminNavActive('/smart_inventory/admin/dashboard.php', true); ?>" href="/smart_inventory/admin/dashboard.php">
            <img src="/smart_inventory/assets/icons/home.svg" alt="">
            <span>Dashboard</span>
        </a>

        <div class="admin-sidebar-section-label">Management</div>
        <a class="admin-sidebar-link<?php echo adminNavActive('/smart_inventory/products/'); ?>" href="/smart_inventory/products/index.php">
            <img src="/smart_inventory/assets/icons/box.svg" alt="">
            <span>Products</span>
        </a>
        <a class="admin-sidebar-link<?php echo (strpos($currentPath, '/smart_inventory/inventory/') === 0 && strpos($currentPath, 'restock_recommendations.php') === false) ? ' active' : ''; ?>" href="/smart_inventory/inventory/index.php">
            <img src="/smart_inventory/assets/icons/clipboard.svg" alt="">
            <span>Inventory</span>
        </a>
        <a class="admin-sidebar-link<?php echo (strpos($currentPath, 'restock_recommendations.php') !== false) ? ' active' : ''; ?>" href="/smart_inventory/inventory/restock_recommendations.php">
            <img src="/smart_inventory/assets/icons/bot.svg" alt="">
            <span>Restock Recommendations</span>
        </a>
        <a class="admin-sidebar-link<?php echo adminNavActive('/smart_inventory/suppliers/'); ?>" href="/smart_inventory/suppliers/index.php">
            <img src="/smart_inventory/assets/icons/truck.svg" alt="">
            <span>Suppliers</span>
        </a>
        <a class="admin-sidebar-link<?php echo adminNavActive('/smart_inventory/users/'); ?>" href="/smart_inventory/users/index.php">
            <img src="/smart_inventory/assets/icons/users.svg" alt="">
            <span>Users</span>
        </a>

        <div class="admin-sidebar-section-label">Sales</div>
        <a class="admin-sidebar-link<?php echo adminNavActive('/smart_inventory/sales/sales.php', true); ?>" href="/smart_inventory/sales/sales.php">
            <img src="/smart_inventory/assets/icons/cart.svg" alt="">
            <span>Point of Sale</span>
        </a>
        <a class="admin-sidebar-link<?php echo (strpos($currentPath, '/smart_inventory/sales/history.php') === 0 || strpos($currentPath, '/smart_inventory/sales/view.php') === 0) ? ' active' : ''; ?>" href="/smart_inventory/sales/history.php">
            <img src="/smart_inventory/assets/icons/history.svg" alt="">
            <span>Sales History</span>
        </a>

        <div class="admin-sidebar-section-label">Reports & Analytics</div>
        <a class="admin-sidebar-link<?php echo adminNavActive('/smart_inventory/analytics/'); ?>" href="/smart_inventory/analytics/index.php">
            <img src="/smart_inventory/assets/icons/chart.svg" alt="">
            <span>Business Analytics</span>
        </a>
        <a class="admin-sidebar-link<?php echo adminNavActive('/smart_inventory/reports/sales_report.php', true); ?>" href="/smart_inventory/reports/sales_report.php">
            <img src="/smart_inventory/assets/icons/report.svg" alt="">
            <span>Sales Report</span>
        </a>
        <a class="admin-sidebar-link<?php echo adminNavActive('/smart_inventory/reports/inventory_report.php', true); ?>" href="/smart_inventory/reports/inventory_report.php">
            <img src="/smart_inventory/assets/icons/box.svg" alt="">
            <span>Inventory Report</span>
        </a>
        <a class="admin-sidebar-link<?php echo adminNavActive('/smart_inventory/reports/profit_report.php', true); ?>" href="/smart_inventory/reports/profit_report.php">
            <img src="/smart_inventory/assets/icons/money.svg" alt="">
            <span>Profit Report</span>
        </a>

        <div class="admin-sidebar-section-label">System</div>
        <a class="admin-sidebar-link<?php echo adminNavActive('/smart_inventory/admin/activity_logs.php', true); ?>" href="/smart_inventory/admin/activity_logs.php">
            <img src="/smart_inventory/assets/icons/activity.svg" alt="">
            <span>Activity Logs</span>
        </a>
        <a class="admin-sidebar-link admin-sidebar-logout" href="/smart_inventory/logout.php">
            <img src="/smart_inventory/assets/icons/logout.svg" alt="">
            <span>Logout</span>
        </a>

    </nav>

    <?php
    $adminNameForShell = trim($_SESSION['name'] ?? 'Admin');
    $adminInitial = strtoupper(mb_substr($adminNameForShell, 0, 1));
    $adminUserIdForShell = (int)($_SESSION['user_id'] ?? 0);
    $adminAvatarForShell = $adminUserIdForShell > 0 ? profileAvatarWebPath($adminUserIdForShell) : null;
    ?>
    <div class="admin-sidebar-profile">
        <div class="admin-sidebar-avatar<?php echo $adminAvatarForShell ? ' has-photo' : ''; ?>">
            <?php if ($adminAvatarForShell): ?>
                <img src="<?php echo htmlspecialchars($adminAvatarForShell); ?>" alt="Admin profile picture" style="filter:none!important;">
            <?php else: ?>
                <?php echo htmlspecialchars($adminInitial); ?>
            <?php endif; ?>
        </div>
        <div class="admin-sidebar-profile-copy">
            <strong><?php echo htmlspecialchars($_SESSION['name'] ?? 'Administrator'); ?></strong>
            <span>Administrator</span>
        </div>
    </div>
</aside>

<script>
(function () {
    const sidebar = document.getElementById('adminUnifiedSidebar');
    const toggle = document.getElementById('adminSidebarToggle');
    const overlay = document.getElementById('adminSidebarOverlay');

    if (!sidebar || !toggle || !overlay) return;

    function setOpen(open) {
        sidebar.classList.toggle('is-open', open);
        overlay.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('admin-nav-open', open);
    }

    toggle.addEventListener('click', function () {
        setOpen(!sidebar.classList.contains('is-open'));
    });

    overlay.addEventListener('click', function () {
        setOpen(false);
    });

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 980) setOpen(false);
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 980) setOpen(false);
    });
})();
</script>
