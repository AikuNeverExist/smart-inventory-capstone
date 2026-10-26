<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['admin']);
require_once __DIR__ . '/../config/database.php';
$id=(int)($_GET['id']??0);if($id<=0){header('Location:index.php');exit();}
$stmt=$conn->prepare('SELECT user_id FROM suppliers WHERE id=? LIMIT 1');$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$row){header('Location:index.php');exit();}
$userId=(int)($row['user_id']??0);
$conn->begin_transaction();
try{
    if($userId>0){$role='user';$stmt=$conn->prepare('UPDATE users SET role=? WHERE id=?');$stmt->bind_param('si',$role,$userId);$stmt->execute();$stmt->close();}
    $conn->commit();
}catch(Throwable $e){$conn->rollback();}
header('Location:index.php');exit();
