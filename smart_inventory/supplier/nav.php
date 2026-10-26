<?php
$currentSupplierPage = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../includes/avatar_helper.php';
$supplierNavUserId = (int)($_SESSION['user_id'] ?? 0);
$supplierNavName = (string)($_SESSION['name'] ?? 'Supplier');
$supplierNavAvatar = $supplierNavUserId > 0 ? profileAvatarWebPath($supplierNavUserId) : null;
$supplierNavInitial = strtoupper(mb_substr($supplierNavName, 0, 1));
?>
<nav class="supplier-nav">
    <div class="supplier-brand">Smart Inventory <span>Supplier</span></div>
    <div class="supplier-links">
        <a class="<?php echo $currentSupplierPage === 'dashboard.php' ? 'active' : ''; ?>" href="/smart_inventory/supplier/dashboard.php">Dashboard</a>
        <a class="<?php echo $currentSupplierPage === 'products.php' ? 'active' : ''; ?>" href="/smart_inventory/supplier/products.php">Products</a>
        <a class="<?php echo $currentSupplierPage === 'supply.php' ? 'active' : ''; ?>" href="/smart_inventory/supplier/supply.php">Supply Stock</a>
        <a class="<?php echo $currentSupplierPage === 'profile.php' ? 'active' : ''; ?>" href="/smart_inventory/supplier/profile.php">Supplier Profile</a>
        <a class="supplier-account-link <?php echo $currentSupplierPage === 'account.php' ? 'active' : ''; ?>" href="/smart_inventory/supplier/account.php">
            <span class="supplier-nav-avatar">
                <?php if ($supplierNavAvatar): ?>
                    <img src="<?php echo htmlspecialchars($supplierNavAvatar); ?>" alt="Profile picture">
                <?php else: ?>
                    <?php echo htmlspecialchars($supplierNavInitial); ?>
                <?php endif; ?>
            </span>
            <span>Account</span>
        </a>
        <a href="/smart_inventory/logout.php">Logout</a>
    </div>
</nav>
<style>
.supplier-nav{padding:16px 26px;display:flex;justify-content:space-between;align-items:center;gap:20px;background:rgba(32,32,54,.95);border-bottom:1px solid rgba(255,255,255,.08);position:sticky;top:0;z-index:20;backdrop-filter:blur(14px)}
.supplier-brand{font-weight:900;color:#fff}.supplier-brand span{color:#5df09d}.supplier-links{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.supplier-links a{color:#dfe2f2;text-decoration:none;padding:9px 12px;border-radius:9px;font-size:13px;display:inline-flex;align-items:center;gap:7px}.supplier-links a:hover,.supplier-links a.active{background:linear-gradient(135deg,rgba(192,108,235,.28),rgba(93,240,157,.20));color:#fff}.supplier-nav-avatar{width:25px;height:25px;border-radius:50%;overflow:hidden;display:grid;place-items:center;flex:0 0 25px;background:linear-gradient(135deg,#c06ceb,#5df09d);color:#151629;font-size:11px;font-weight:900;border:1px solid rgba(255,255,255,.18)}.supplier-nav-avatar img{width:100%;height:100%;object-fit:cover}@media(max-width:900px){.supplier-nav{align-items:flex-start;flex-direction:column}.supplier-links{width:100%}}
</style>
