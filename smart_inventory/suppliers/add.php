<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['admin']);
header('Location: /smart_inventory/users/index.php');
exit();
