<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['admin']);
require_once __DIR__ . '/../config/database.php';

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$allowedRoles = ['admin', 'staff', 'user', 'supplier'];

$sql = "SELECT id, name, username, role, created_at FROM users WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (name LIKE ? OR username LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($roleFilter !== '' && in_array($roleFilter, $allowedRoles, true)) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

$sql .= " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management</title>
<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
<style>
*{box-sizing:border-box;font-family:Arial,sans-serif}
.container{padding:30px}.top{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px;flex-wrap:wrap}.top h1{margin:0;color:#fff!important}.add-btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 16px;border-radius:9px;text-decoration:none;font-weight:900;background:linear-gradient(135deg,#c06ceb,#5df09d)!important;color:#111827!important;border:0!important}
.filter-panel{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:18px;padding:16px!important;border-radius:14px!important;background:#262740!important;border:1px solid #3c3e62!important}.filter-group{display:flex;flex-direction:column;gap:6px;min-width:180px;flex:1}.filter-group.search{min-width:260px;flex:2}.filter-group label{font-size:12px;font-weight:800;color:#fff!important}.filter-panel input,.filter-panel select{width:100%;min-height:42px;padding:10px 12px;border-radius:9px;border:1px solid #4a4c71!important;background:#202036!important;color:#fff!important;outline:none}.filter-panel select option{background:#202036;color:#fff}.filter-actions{display:flex;gap:8px}.filter-btn,.clear-btn{min-height:42px;padding:0 15px;border-radius:9px;text-decoration:none;font-weight:900;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.filter-btn{border:0;background:linear-gradient(135deg,#c06ceb,#5df09d)!important;color:#111827!important}.clear-btn{background:#202036!important;color:#fff!important;border:1px solid #4a4c71!important}
.panel{background:#262740!important;padding:16px;border:1px solid #3c3e62!important;border-radius:14px!important;overflow:auto}.result-count{margin:0 0 12px;color:#b8bdd6!important;font-size:13px}table{width:100%;border-collapse:collapse;background:#262740!important;color:#fff!important}th,td{padding:12px;border-bottom:1px solid rgba(255,255,255,.08)!important;text-align:left;color:#fff!important;white-space:nowrap}th{background:#202036!important;color:#d9dcec!important}.staff{color:#5df09d!important;font-weight:900}.user{color:#b8bdd6!important;font-weight:900}.supplier{color:#c06ceb!important;font-weight:900}.admin{color:#fff!important;font-weight:900}.edit,.delete{display:inline-flex;padding:7px 10px;border-radius:7px;text-decoration:none;color:#fff!important;background:#202036!important;border:1px solid #4a4c71!important}.delete{border-color:rgba(192,108,235,.35)!important}.empty{text-align:center;color:#b8bdd6!important;padding:28px!important}
body.admin-layout main.container h1{color:#fff!important}body.admin-layout main.container td,body.admin-layout main.container th,body.admin-layout main.container strong{color:#fff!important}
@media(max-width:700px){.container{padding:20px}.filter-group,.filter-group.search{min-width:100%;}.filter-actions{width:100%}.filter-btn,.clear-btn{flex:1}}
</style>
</head>
<body class="admin-layout">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/smart_inventory/includes/admin_sidebar.php'; ?>
<main class="container">
    <div class="top">
        <h1 style="color:#ffffff!important;">User Management</h1>
        <a href="add.php" class="add-btn">+ Add User Account</a>
    </div>

    <form class="filter-panel" method="GET">
        <div class="filter-group search">
            <label for="search">Search users</label>
            <input id="search" type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name or username...">
        </div>
        <div class="filter-group">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="">All roles</option>
                <?php foreach ($allowedRoles as $r): ?>
                    <option value="<?php echo $r; ?>" <?php echo $roleFilter === $r ? 'selected' : ''; ?>><?php echo ucfirst($r); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button class="filter-btn" type="submit">Filter</button>
            <a class="clear-btn" href="/smart_inventory/users/index.php">Clear</a>
        </div>
    </form>

    <section class="panel">
        <div class="result-count"><?php echo (int)$users->num_rows; ?> user(s) found</div>
        <table>
            <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($users->num_rows > 0): ?>
                <?php while ($user = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><span class="<?php echo htmlspecialchars($user['role']); ?>"><?php echo strtoupper(htmlspecialchars($user['role'])); ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <a class="edit" href="edit.php?id=<?php echo (int)$user['id']; ?>">Edit</a>
                        <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                            <a class="delete" href="delete.php?id=<?php echo (int)$user['id']; ?>" onclick="return confirm('Delete this user?');">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td class="empty" colspan="5">No users match your search or filter.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
