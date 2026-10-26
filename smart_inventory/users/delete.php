<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['admin']);
require_once __DIR__ . '/../config/database.php';
$id=(int)($_GET['id']??0);if($id<=0||$id===(int)$_SESSION['user_id']){header('Location: index.php');exit();}
$stmt=$conn->prepare('SELECT id,username,role FROM users WHERE id=? LIMIT 1');$stmt->bind_param('i',$id);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$user){header('Location: index.php');exit();}
$conn->begin_transaction();
try{
    $stmt=$conn->prepare('UPDATE suppliers SET user_id=NULL WHERE user_id=?');$stmt->bind_param('i',$id);$stmt->execute();$stmt->close();
    $stmt=$conn->prepare('DELETE FROM users WHERE id=?');$stmt->bind_param('i',$id);$stmt->execute();$stmt->close();
    $action='Delete User';$description="Deleted user '{$user['username']}' with role {$user['role']}.";$adminId=(int)$_SESSION['user_id'];
    $log=$conn->prepare('INSERT INTO activity_logs (user_id,action,description) VALUES (?,?,?)');$log->bind_param('iss',$adminId,$action,$description);$log->execute();$log->close();
    $conn->commit();
}catch(Throwable $e){$conn->rollback();header('Location: index.php?error='.urlencode('Unable to delete user because the account is still referenced by system records.'));exit();}
header('Location: index.php?deleted=1');exit();
