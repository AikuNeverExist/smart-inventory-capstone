<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/role_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /smart_inventory/login.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: /smart_inventory/login.php?error=' . urlencode('Please enter username and password.'));
    exit();
}

$stmt = $conn->prepare(
    "SELECT id, name, username, password, role
     FROM users
     WHERE username = ?
     LIMIT 1"
);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header('Location: /smart_inventory/login.php?error=' . urlencode('Username not found.'));
    exit();
}

$user = $result->fetch_assoc();
$passwordCorrect = password_verify($password, $user['password']);

// Temporary compatibility for old plain-text passwords.
if (!$passwordCorrect && hash_equals((string)$user['password'], (string)$password)) {
    $passwordCorrect = true;
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $update->bind_param('si', $newHash, $user['id']);
    $update->execute();
    $update->close();
}

if (!$passwordCorrect) {
    header('Location: /smart_inventory/login.php?error=' . urlencode('Incorrect password.'));
    exit();
}

$allowedRoles = ['admin', 'staff', 'user', 'supplier'];
$role = in_array($user['role'], $allowedRoles, true) ? $user['role'] : 'user';

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['name'] = $user['name'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $role;

redirectToRoleDashboard($role);
