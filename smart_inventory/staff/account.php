<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['staff']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/avatar_helper.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$message = '';
$error = '';

if (!isset($_SESSION['staff_account_csrf'])) {
    $_SESSION['staff_account_csrf'] = bin2hex(random_bytes(32));
}

function staffAccountCsrfValid(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && isset($_SESSION['staff_account_csrf'])
        && hash_equals($_SESSION['staff_account_csrf'], $token);
}

$stmt = $conn->prepare('SELECT id, name, username, password, created_at FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: /smart_inventory/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staffAccountCsrfValid()) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');

            if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                $error = 'Name must be between 2 and 100 characters.';
            } elseif (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $username)) {
                $error = 'Username must be 4 to 50 characters and may only contain letters, numbers, dots, underscores, and hyphens.';
            } else {
                $check = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
                $check->bind_param('si', $username, $userId);
                $check->execute();
                $duplicate = $check->get_result()->num_rows > 0;
                $check->close();

                if ($duplicate) {
                    $error = 'That username is already being used.';
                } else {
                    $update = $conn->prepare('UPDATE users SET name = ?, username = ? WHERE id = ?');
                    $update->bind_param('ssi', $name, $username, $userId);
                    $update->execute();
                    $update->close();

                    $_SESSION['name'] = $name;
                    $_SESSION['username'] = $username;
                    $user['name'] = $name;
                    $user['username'] = $username;
                    $message = 'Account information updated.';
                }
            }
        } elseif ($action === 'upload_avatar') {
            [$ok, $avatarMessage] = saveProfileAvatar($userId, $_FILES['avatar'] ?? []);
            if ($ok) {
                $message = $avatarMessage;
            } else {
                $error = $avatarMessage;
            }
        } elseif ($action === 'remove_avatar') {
            removeProfileAvatar($userId);
            $message = 'Profile picture removed.';
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $currentOk = password_verify($currentPassword, (string)$user['password']);
            if (!$currentOk && hash_equals((string)$user['password'], (string)$currentPassword)) {
                $currentOk = true;
            }

            if (!$currentOk) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($newPassword) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match.';
            } elseif ($currentPassword === $newPassword) {
                $error = 'Choose a password that is different from your current password.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update->bind_param('si', $newHash, $userId);
                $update->execute();
                $update->close();
                $user['password'] = $newHash;
                $message = 'Password changed successfully.';
            }
        }
    }
}

$summaryStmt = $conn->prepare('SELECT COUNT(*) AS tx_count, COALESCE(SUM(total_amount), 0) AS total_sales FROM sales WHERE user_id = ?');
$summaryStmt->bind_param('i', $userId);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$avatar = profileAvatarWebPath($userId);
$initial = strtoupper(mb_substr((string)$user['name'], 0, 1));
$transactionCount = (int)($summary['tx_count'] ?? 0);
$totalSales = (float)($summary['total_sales'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Staff Account | Smart Inventory</title>
<style>
*{box-sizing:border-box;font-family:Arial,sans-serif}body{margin:0;min-height:100vh;color:#fff;background:radial-gradient(circle at 12% 18%,rgba(192,108,235,.23),transparent 30%),radial-gradient(circle at 88% 22%,rgba(93,240,157,.17),transparent 30%),linear-gradient(135deg,#101223,#181a31,#101223)}
.nav{padding:15px 24px;background:rgba(32,32,54,.96);border-bottom:1px solid rgba(255,255,255,.08);display:flex;justify-content:space-between;align-items:center;gap:16px}.brand{display:flex;align-items:center;gap:11px}.brand img{width:34px;height:34px}.nav-links{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.nav a{color:#fff;text-decoration:none;padding:9px 13px;border-radius:9px;background:#262740;border:1px solid #444667}.nav a.active{background:linear-gradient(135deg,rgba(192,108,235,.32),rgba(93,240,157,.20))}.wrap{max-width:1100px;margin:auto;padding:36px 20px 60px}.header{margin-bottom:20px}.eyebrow{color:#5df09d;font-size:11px;font-weight:900;letter-spacing:1px;text-transform:uppercase}.header h1{margin:7px 0 7px;font-size:32px}.muted{color:#b8bdd6}.grid{display:grid;grid-template-columns:330px minmax(0,1fr);gap:18px}.panel{background:rgba(38,39,64,.96);border:1px solid #3c3e62;border-radius:18px;box-shadow:0 18px 45px rgba(0,0,0,.22)}.profile-card{padding:24px;text-align:center;height:max-content}.avatar{width:116px;height:116px;margin:0 auto 15px;border-radius:50%;display:grid;place-items:center;overflow:hidden;background:linear-gradient(135deg,#c06ceb,#5df09d);color:#151629;font-size:42px;font-weight:900;border:4px solid rgba(255,255,255,.12);box-shadow:0 12px 30px rgba(0,0,0,.28)}.avatar img{width:100%;height:100%;object-fit:cover}.profile-card h2{margin:6px 0 3px}.username{color:#b8bdd6;margin-bottom:18px}.upload{display:grid;gap:9px}.upload input[type=file]{width:100%;padding:9px;background:#202036;color:#dfe2f2;border:1px solid #4a4c71;border-radius:9px;font-size:12px}.btn{min-height:42px;border-radius:9px;border:0;cursor:pointer;font-weight:900;padding:10px 13px}.primary{background:linear-gradient(135deg,#c06ceb,#5df09d);color:#151629}.secondary{background:#202036;color:#fff;border:1px solid #4a4c71}.danger{background:#202036;color:#f5c7d9;border:1px solid rgba(245,199,217,.25)}.stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:20px}.stat{background:#202036;border:1px solid #3c3e62;border-radius:12px;padding:13px}.stat strong{display:block;font-size:20px}.stat span{font-size:11px;color:#aeb4cf}.content{display:grid;gap:18px}.section{padding:22px}.section h2{margin:0 0 18px;font-size:20px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:grid;gap:7px}.field.full{grid-column:1/-1}.field label{font-size:12px;font-weight:800;color:#dfe2f2}.field input{width:100%;min-height:44px;padding:11px 12px;border:1px solid #4a4c71;border-radius:9px;background:#202036;color:#fff;outline:none}.field input:focus{border-color:#5df09d;box-shadow:0 0 0 3px rgba(93,240,157,.10)}.actions{margin-top:16px}.alert{margin-bottom:18px;padding:12px 14px;border-radius:11px}.ok{background:rgba(93,240,157,.10);border:1px solid rgba(93,240,157,.35)}.err{background:rgba(192,108,235,.12);border:1px solid rgba(192,108,235,.38)}.account-date{margin-top:16px;color:#8f95b2;font-size:11px}@media(max-width:800px){.grid{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}.nav{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<header class="nav">
    <div class="brand"><img src="/smart_inventory/assets/brand.svg" alt=""><strong>Smart Inventory · Staff</strong></div>
    <nav class="nav-links">
        <a href="/smart_inventory/staff/dashboard.php">Dashboard</a>
        <a href="/smart_inventory/sales/sales.php">Sales / POS</a>
        <a href="/smart_inventory/sales/history.php">Sales History</a>
        <a class="active" href="/smart_inventory/staff/account.php">Account</a>
        <a href="/smart_inventory/logout.php">Logout</a>
    </nav>
</header>
<main class="wrap">
    <div class="header"><div class="eyebrow">Staff Account</div><h1>My Account</h1><div class="muted">Manage your profile picture, account details, and password.</div></div>
    <?php if ($message !== ''): ?><div class="alert ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <div class="grid">
        <aside class="panel profile-card">
            <div class="avatar"><?php if ($avatar): ?><img src="<?php echo htmlspecialchars($avatar); ?>" alt="Profile picture"><?php else: ?><?php echo htmlspecialchars($initial); ?><?php endif; ?></div>
            <h2><?php echo htmlspecialchars($user['name']); ?></h2>
            <div class="username">@<?php echo htmlspecialchars($user['username']); ?></div>
            <form class="upload" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_account_csrf']); ?>">
                <input type="hidden" name="action" value="upload_avatar">
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn primary" type="submit">Update Profile Picture</button>
            </form>
            <?php if ($avatar): ?>
            <form method="post" style="margin-top:9px">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_account_csrf']); ?>">
                <input type="hidden" name="action" value="remove_avatar">
                <button class="btn danger" type="submit">Remove Picture</button>
            </form>
            <?php endif; ?>
            <div class="stats">
                <div class="stat"><strong><?php echo number_format($transactionCount); ?></strong><span>Sales Processed</span></div>
                <div class="stat"><strong>₱<?php echo number_format($totalSales, 2); ?></strong><span>Total Sales</span></div>
            </div>
            <div class="account-date">Account created <?php echo htmlspecialchars(date('M d, Y', strtotime((string)$user['created_at']))); ?></div>
        </aside>
        <section class="content">
            <div class="panel section">
                <h2>Profile Information</h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_account_csrf']); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid">
                        <div class="field"><label>Full Name</label><input name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required></div>
                        <div class="field"><label>Username</label><input name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required></div>
                    </div>
                    <div class="actions"><button class="btn primary" type="submit">Save Changes</button></div>
                </form>
            </div>
            <div class="panel section">
                <h2>Change Password</h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_account_csrf']); ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-grid">
                        <div class="field full"><label>Current Password</label><input type="password" name="current_password" required></div>
                        <div class="field"><label>New Password</label><input type="password" name="new_password" minlength="8" required></div>
                        <div class="field"><label>Confirm New Password</label><input type="password" name="confirm_password" minlength="8" required></div>
                    </div>
                    <div class="actions"><button class="btn primary" type="submit">Change Password</button></div>
                </form>
            </div>
        </section>
    </div>
</main>
</body>
</html>
