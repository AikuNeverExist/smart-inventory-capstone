<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['admin']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/supplier_account.php';
require_once __DIR__ . '/../includes/avatar_helper.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Invalid user ID.');
}

$message = '';
$success = isset($_GET['created']) ? 'User account created. You can now assign its role.' : '';

if (!isset($_SESSION['admin_user_edit_csrf'])) {
    $_SESSION['admin_user_edit_csrf'] = bin2hex(random_bytes(32));
}

function adminUserEditCsrfValid(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && isset($_SESSION['admin_user_edit_csrf'])
        && hash_equals($_SESSION['admin_user_edit_csrf'], $token);
}

$stmt = $conn->prepare('SELECT id, name, username, role, created_at FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die('User not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adminUserEditCsrfValid()) {
        $message = 'Invalid request. Refresh the page and try again.';
    } else {
        $formAction = $_POST['form_action'] ?? 'update_user';

        if ($formAction === 'upload_avatar') {
            [$ok, $avatarMessage] = saveProfileAvatar($id, $_FILES['avatar'] ?? []);
            if ($ok) {
                $success = $avatarMessage;
            } else {
                $message = $avatarMessage;
            }
        } elseif ($formAction === 'remove_avatar') {
            removeProfileAvatar($id);
            $success = 'Profile picture removed.';
        } else {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $password = $_POST['password'] ?? '';
            $roles = ['user', 'staff', 'supplier', 'admin'];

            if ($name === '' || $username === '') {
                $message = 'Name and username are required.';
            } elseif (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $username)) {
                $message = 'Username must be 4 to 50 characters and may only contain letters, numbers, dots, underscores, and hyphens.';
            } elseif (!in_array($role, $roles, true)) {
                $message = 'Invalid role.';
            } elseif ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
                $message = 'You cannot remove your own Admin role while you are logged in.';
            } else {
                $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
                $stmt->bind_param('si', $username, $id);
                $stmt->execute();
                $duplicate = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if ($duplicate) {
                    $message = 'Username already exists.';
                } else {
                    $conn->begin_transaction();
                    try {
                        $oldRole = $user['role'];

                        if ($password !== '') {
                            if (strlen($password) < 8) {
                                throw new Exception('New password must be at least 8 characters.');
                            }
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare('UPDATE users SET name = ?, username = ?, password = ?, role = ? WHERE id = ?');
                            $stmt->bind_param('ssssi', $name, $username, $hash, $role, $id);
                        } else {
                            $stmt = $conn->prepare('UPDATE users SET name = ?, username = ?, role = ? WHERE id = ?');
                            $stmt->bind_param('sssi', $name, $username, $role, $id);
                        }

                        $stmt->execute();
                        $stmt->close();

                        if ($role === 'supplier') {
                            ensureSupplierProfile($conn, $id, $name);
                        }

                        $action = 'Edit User';
                        $description = "Updated user ID $id ($username) role from $oldRole to $role.";
                        $adminId = (int)$_SESSION['user_id'];
                        $log = $conn->prepare('INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)');
                        $log->bind_param('iss', $adminId, $action, $description);
                        $log->execute();
                        $log->close();

                        $conn->commit();

                        if ($id === (int)$_SESSION['user_id']) {
                            $_SESSION['name'] = $name;
                            $_SESSION['username'] = $username;
                            $_SESSION['role'] = $role;
                        }

                        header('Location: /smart_inventory/users/index.php?updated=1');
                        exit();
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $message = $e->getMessage();
                    }
                }
            }
        }
    }
}

$avatar = profileAvatarWebPath($id);
$initial = strtoupper(mb_substr((string)$user['name'], 0, 1));
$isSelf = $id === (int)($_SESSION['user_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit User | Smart Inventory</title>
<style>
*{box-sizing:border-box;font-family:Arial,sans-serif}.page{width:900px;max-width:94%;margin:42px auto;color:#fff}.page h1{margin:0 0 8px;color:#fff!important}.subtitle{color:#b8bdd6;margin-bottom:20px}.layout{display:grid;grid-template-columns:260px minmax(0,1fr);gap:18px}.panel{background:#262740;color:#fff;padding:24px;border:1px solid #3c3e62;border-radius:14px;box-shadow:0 18px 45px rgba(0,0,0,.20)}.avatar-card{text-align:center;height:max-content}.avatar{width:112px;height:112px;border-radius:50%;overflow:hidden;display:grid;place-items:center;margin:0 auto 15px;background:linear-gradient(135deg,#c06ceb,#5df09d);color:#151629;font-size:40px;font-weight:900;border:4px solid rgba(255,255,255,.12)}.avatar img{width:100%;height:100%;object-fit:cover;filter:none!important}.avatar-card h2{margin:5px 0}.avatar-card p{margin:0 0 16px;color:#b8bdd6;font-size:12px}.avatar-form{display:grid;gap:9px}.avatar-form input[type=file]{width:100%;padding:8px;background:#202036;color:#fff;border:1px solid #4a4c71;border-radius:8px;font-size:11px}.group{margin-bottom:18px}.group label{display:block;font-weight:800;margin-bottom:7px;color:#fff}.group input,.group select{width:100%;padding:11px;border:1px solid #4a4c71;border-radius:7px;background:#202036;color:#fff}.group select option{background:#202036;color:#fff}.note{color:#b8bdd6;font-size:13px;line-height:1.55}.alert{padding:12px;border-radius:8px;margin-bottom:18px}.error{background:rgba(192,108,235,.13);border:1px solid rgba(192,108,235,.4)}.success{background:rgba(93,240,157,.12);border:1px solid rgba(93,240,157,.4)}.btn{border:0;border-radius:8px;padding:11px 18px;background:linear-gradient(135deg,#c06ceb,#5df09d);color:#17182c;font-weight:900;cursor:pointer}.btn.secondary{background:#202036;color:#fff;border:1px solid #4a4c71}.btn.danger{background:#202036;color:#f5c7d9;border:1px solid rgba(245,199,217,.3)}.cancel{display:inline-block;margin-left:7px;padding:11px 18px;border-radius:8px;background:#202036;color:#fff;text-decoration:none;border:1px solid #4a4c71}.self-badge{display:inline-block;margin-top:8px;padding:5px 8px;border-radius:999px;background:rgba(93,240,157,.12);border:1px solid rgba(93,240,157,.28);color:#bfffd8;font-size:10px;font-weight:900}.actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}@media(max-width:760px){.layout{grid-template-columns:1fr}.page{margin-top:25px}}
</style>
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
</head>
<body class="admin-layout">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/smart_inventory/includes/admin_sidebar.php'; ?>
<main class="page">
    <h1>Edit User</h1>
    <div class="subtitle">Manage account information, role, password, and profile picture.</div>
    <?php if ($message): ?><div class="alert error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="layout">
        <aside class="panel avatar-card">
            <div class="avatar">
                <?php if ($avatar): ?>
                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Profile picture">
                <?php else: ?>
                    <?php echo htmlspecialchars($initial); ?>
                <?php endif; ?>
            </div>
            <h2><?php echo htmlspecialchars($user['name']); ?></h2>
            <p>@<?php echo htmlspecialchars($user['username']); ?> · <?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
            <?php if ($isSelf): ?><span class="self-badge">Your Admin Account</span><?php endif; ?>

            <form class="avatar-form" method="post" enctype="multipart/form-data" style="margin-top:16px">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_user_edit_csrf']); ?>">
                <input type="hidden" name="form_action" value="upload_avatar">
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn" type="submit">Update Picture</button>
            </form>

            <?php if ($avatar): ?>
            <form method="post" style="margin-top:9px">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_user_edit_csrf']); ?>">
                <input type="hidden" name="form_action" value="remove_avatar">
                <button class="btn danger" type="submit">Remove Picture</button>
            </form>
            <?php endif; ?>
        </aside>

        <section class="panel">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_user_edit_csrf']); ?>">
                <input type="hidden" name="form_action" value="update_user">
                <div class="group"><label>Full Name</label><input name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required></div>
                <div class="group"><label>Username</label><input name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required></div>
                <div class="group"><label>New Password</label><input type="password" name="password" minlength="8"><div class="note">Leave blank to keep the current password.</div></div>
                <div class="group">
                    <label>Role</label>
                    <select name="role">
                        <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="staff" <?php echo $user['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                        <option value="supplier" <?php echo $user['role'] === 'supplier' ? 'selected' : ''; ?>>Supplier</option>
                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                    <div class="note">Changing a User to Supplier automatically creates and links a Supplier profile.</div>
                </div>
                <div class="actions"><button class="btn" type="submit">Update User</button><a class="cancel" href="index.php">Cancel</a></div>
            </form>
        </section>
    </div>
</main>
</body>
</html>
