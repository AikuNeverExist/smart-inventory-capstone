<?php

session_start();

if (!isset($_SESSION['user_id'])) {

    header(
        "Location: /smart_inventory/login.php"
    );

    exit();
}


if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {

    header(
        "Location: /smart_inventory/staff/dashboard.php"
    );

    exit();
}


if (!isset($_SESSION['user_id'])) {

    header(
        "Location: /smart_inventory/login.php"
    );

    exit();
}


if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {

    header(
        "Location: /smart_inventory/staff/dashboard.php"
    );

    exit();
}

/* =========================
   CHECK LOGIN
========================= */

if (!isset($_SESSION['user_id'])) {

    header(
        "Location: /smart_inventory/login.php"
    );

    exit();
}


/* =========================
   ADMIN ONLY
========================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {

    header(
        "Location: /smart_inventory/sales/sales.php"
    );

    exit();
}


require_once "../config/database.php";


/* =========================
   GET ACTIVITY LOGS
========================= */

$logs = $conn->query(
    "SELECT
        activity_logs.id,
        activity_logs.action,
        activity_logs.description,
        activity_logs.created_at,
        users.name,
        users.username
     FROM activity_logs
     LEFT JOIN users
        ON activity_logs.user_id = users.id
     ORDER BY activity_logs.created_at DESC"
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Activity Logs</title>


<style>

* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    margin: 0;
    background: #f4f6f9;
    color: #1b1b1b;
}


/* NAVBAR */

.navbar {

    background: #1b1b1b;

    color: white;

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.navbar h2 {
    font-size: 21px;
}

.navbar div {

    display: flex;

    flex-wrap: wrap;

    gap: 5px;
}

.navbar a {

    color: white;

    text-decoration: none;

    padding: 9px 12px;

    border-radius: 6px;
}

.navbar a:hover {
    background: #333333;
}


/* MAIN */

.container {

    padding: 30px;
}

.page-title {

    margin-bottom: 25px;
}

.page-title p {

    color: #707070;

    margin-top: 5px;
}


/* PANEL */

.panel {

    background: white;

    padding: 20px;

    border-radius: 10px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);

    overflow-x: auto;
}


/* TABLE */

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 800px;
}

th,
td {

    padding: 12px;

    text-align: left;

    border-bottom:
        1px solid #e5e7eb;
}

th {

    background: #f3f3f3;
}

.action {

    font-weight: bold;

    color: #111111;
}

.date {

    color: #707070;

    white-space: nowrap;
}


/* EMPTY */

.empty {

    text-align: center;

    padding: 30px;

    color: #707070;
}


/* RESPONSIVE */

@media (max-width: 900px) {

    .navbar {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .container {

        padding: 15px;
    }

}

</style>

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
</head>


<body class="admin-layout">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/smart_inventory/includes/admin_sidebar.php'; ?>



<!-- NAVBAR -->

<div class="navbar">

<h2>
Smart Inventory & Sales Management
</h2>


<div>

<a href="/smart_inventory/admin/dashboard.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/home.svg" alt=""> Dashboard
</a>

<a href="/smart_inventory/products/index.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt=""> Products
</a>

<a href="/smart_inventory/inventory/index.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/clipboard.svg" alt=""> Inventory
</a>

<a href="/smart_inventory/sales/sales.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/money.svg" alt=""> Sales
</a>

<a href="/smart_inventory/admin/activity_logs.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/activity.svg" alt=""> Activity Logs
</a>

<a href="/smart_inventory/users/index.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/users.svg" alt=""> Users
</a>

<a href="/smart_inventory/logout.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/logout.svg" alt=""> Logout
</a>

</div>

</div>


<!-- MAIN -->

<div class="container">


<div class="page-title">

<h1>
<img class="ui-icon" src="/smart_inventory/assets/icons/activity.svg" alt=""> Activity Logs
</h1>

<p>
Track important actions performed inside the system.
</p>

</div>


<div class="panel">


<table>

<thead>

<tr>

<th>
Date & Time
</th>

<th>
User
</th>

<th>
Username
</th>

<th>
Action
</th>

<th>
Description
</th>

</tr>

</thead>


<tbody>


<?php if (
    $logs &&
    $logs->num_rows > 0
): ?>


<?php while (
    $log = $logs->fetch_assoc()
): ?>


<tr>


<td class="date">

<?php

echo date(
    "M d, Y h:i A",
    strtotime(
        $log['created_at']
    )
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $log['name'] ?? 'Deleted User'
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $log['username'] ?? '-'
);

?>

</td>


<td class="action">

<?php

echo htmlspecialchars(
    $log['action']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $log['description'] ?? ''
);

?>

</td>


</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td
    colspan="5"
    class="empty"
>

No activity logs yet.

</td>

</tr>


<?php endif; ?>


</tbody>

</table>


</div>


</div>


</body>

</html>