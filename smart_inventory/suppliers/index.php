<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['admin']);
require_once __DIR__ . '/../config/database.php';

$sql="SELECT u.id AS user_id,u.name,u.username,u.created_at,s.id AS supplier_id,s.supplier_name,s.contact_person,s.phone,s.email,s.address FROM users u LEFT JOIN suppliers s ON s.user_id=u.id WHERE u.role='supplier' ORDER BY u.id DESC";
$suppliers=$conn->query($sql);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Supplier Accounts</title><style>*{box-sizing:border-box;font-family:Arial,sans-serif}.container{padding:30px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px}.top p{color:#b8bdd6}.btn{display:inline-block;text-decoration:none;padding:10px 14px;border-radius:9px;background:linear-gradient(135deg,#c06ceb,#5df09d);color:#17182c;font-weight:900}.panel{background:#262740;border:1px solid #3c3e62;border-radius:14px;padding:18px;overflow:auto}table{width:100%;border-collapse:collapse;color:#fff}th,td{padding:12px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left}th{background:#202036;color:#d9dcec}.action{display:inline-block;padding:7px 10px;border-radius:7px;background:#202036;color:#fff;text-decoration:none;margin:2px}.remove{border:1px solid rgba(192,108,235,.4)}.muted{color:#b8bdd6}</style><link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4"><link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4"><style id="role-page-white-fix">
body.admin-layout h1,
body.admin-layout .top h1,
body.admin-layout .admin-sidebar-link.active { color:#ffffff !important; }
</style>
<style id="supplier-text-final-fix">
body.admin-layout main.container .top h1{color:#ffffff !important;}
body.admin-layout main.container .top p{color:#b8bdd6 !important;}
body.admin-layout main.container table th,
body.admin-layout main.container table td,
body.admin-layout main.container table strong{color:#ffffff !important;}
body.admin-layout main.container .muted{color:#b8bdd6 !important;}
</style>
</head><body class="admin-layout"><?php include $_SERVER['DOCUMENT_ROOT'].'/smart_inventory/includes/admin_sidebar.php'; ?><main class="container"><div class="top"><div><h1 style="color:#ffffff !important;">Supplier Accounts</h1><p style="color:#b8bdd6 !important;">Supplier accounts and their linked supplier profiles.</p></div><a class="btn" href="/smart_inventory/users/index.php">Manage User Roles</a></div><section class="panel"><table><thead><tr><th>Supplier</th><th>Login</th><th>Contact</th><th>Phone</th><th>Email</th><th>Actions</th></tr></thead><tbody><?php if($suppliers&&$suppliers->num_rows): while($s=$suppliers->fetch_assoc()): ?><tr><td><strong><?php echo htmlspecialchars($s['supplier_name']?:$s['name']); ?></strong></td><td><?php echo htmlspecialchars($s['username']); ?><div class="muted">SUPPLIER</div></td><td><?php echo htmlspecialchars($s['contact_person']??''); ?></td><td><?php echo htmlspecialchars($s['phone']??''); ?></td><td><?php echo htmlspecialchars($s['email']??''); ?></td><td><a class="action" href="/smart_inventory/users/edit.php?id=<?php echo (int)$s['user_id']; ?>">Edit Account</a><?php if($s['supplier_id']): ?><a class="action" href="edit.php?id=<?php echo (int)$s['supplier_id']; ?>">Edit Profile</a><a class="action remove" href="delete.php?id=<?php echo (int)$s['supplier_id']; ?>" onclick="return confirm('Remove Supplier role from this account? Existing product history will be kept.');">Remove Supplier Role</a><?php endif; ?></td></tr><?php endwhile; else: ?><tr><td colspan="6" class="muted" style="text-align:center">No Supplier accounts yet. Change a registered User to Supplier from User Management.</td></tr><?php endif; ?></tbody></table></section></main></body></html>
