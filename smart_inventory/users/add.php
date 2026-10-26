<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['admin']);
require_once __DIR__ . '/../config/database.php';

$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=trim($_POST['name']??'');$username=trim($_POST['username']??'');$password=$_POST['password']??'';
    if($name===''||$username===''||$password===''){$message='Please complete all fields.';}
    elseif(!preg_match('/^[A-Za-z0-9._-]{4,50}$/',$username)){$message='Username must be 4 to 50 characters and may contain letters, numbers, dots, underscores, and hyphens.';}
    elseif(strlen($password)<8){$message='Password must be at least 8 characters.';}
    else{
        $stmt=$conn->prepare('SELECT id FROM users WHERE username=? LIMIT 1');$stmt->bind_param('s',$username);$stmt->execute();$exists=$stmt->get_result()->num_rows>0;$stmt->close();
        if($exists){$message='Username already exists.';}else{
            $role='user';$hash=password_hash($password,PASSWORD_DEFAULT);
            $stmt=$conn->prepare('INSERT INTO users (name,username,password,role) VALUES (?,?,?,?)');$stmt->bind_param('ssss',$name,$username,$hash,$role);$stmt->execute();$newId=$stmt->insert_id;$stmt->close();
            $action='Add User';$description="Created user '$username' with role user.";$adminId=(int)$_SESSION['user_id'];
            $log=$conn->prepare('INSERT INTO activity_logs (user_id,action,description) VALUES (?,?,?)');$log->bind_param('iss',$adminId,$action,$description);$log->execute();$log->close();
            header('Location: /smart_inventory/users/edit.php?id='.$newId.'&created=1');exit();
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Add User</title><style>*{box-sizing:border-box;font-family:Arial,sans-serif}.container{width:620px;max-width:90%;margin:50px auto;background:#262740;color:#fff;padding:30px;border:1px solid #3c3e62;border-radius:14px}.group{margin-bottom:18px}.group label{display:block;font-weight:800;margin-bottom:7px}.group input{width:100%;padding:11px;border:1px solid #4a4c71;border-radius:7px;background:#202036;color:#fff}.note{color:#b8bdd6;font-size:13px;line-height:1.55}.error{padding:12px;border-radius:8px;background:rgba(192,108,235,.13);border:1px solid rgba(192,108,235,.4);margin-bottom:18px}.btn{border:0;border-radius:8px;padding:11px 18px;background:linear-gradient(135deg,#c06ceb,#5df09d);color:#17182c;font-weight:900;cursor:pointer}.cancel{display:inline-block;margin-left:7px;padding:11px 18px;border-radius:8px;background:#202036;color:#fff;text-decoration:none}</style><link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4"><link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4"></head><body class="admin-layout"><?php include $_SERVER['DOCUMENT_ROOT'].'/smart_inventory/includes/admin_sidebar.php'; ?><main class="container"><h1>Add User</h1><p class="note">Admin-created accounts start as <strong>USER</strong>. After creation, use Edit User to promote the account to Staff, Supplier, or Admin.</p><?php if($message): ?><div class="error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><form method="post"><div class="group"><label>Full Name</label><input name="name" required></div><div class="group"><label>Username</label><input name="username" minlength="4" maxlength="50" required></div><div class="group"><label>Password</label><input type="password" name="password" minlength="8" required></div><button class="btn" type="submit">Create User Account</button><a class="cancel" href="index.php">Cancel</a></form></main></body></html>
