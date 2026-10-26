<?php

function dashboardForRole(string $role): string
{
    switch ($role) {
        case 'admin':
            return '/smart_inventory/admin/dashboard.php';
        case 'staff':
            return '/smart_inventory/staff/dashboard.php';
        case 'supplier':
            return '/smart_inventory/supplier/dashboard.php';
        case 'user':
        default:
            return '/smart_inventory/user/shop.php';
    }
}

function redirectToRoleDashboard(?string $role = null): void
{
    $role = $role ?? ($_SESSION['role'] ?? 'user');
    header('Location: ' . dashboardForRole($role));
    exit();
}

function requireLoggedIn(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /smart_inventory/login.php');
        exit();
    }
}

function requireAnyRole(array $roles): void
{
    requireLoggedIn();
    $role = $_SESSION['role'] ?? 'user';

    if (!in_array($role, $roles, true)) {
        redirectToRoleDashboard($role);
    }
}
