<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['user']);
header('Location: /smart_inventory/user/account.php');
exit();
