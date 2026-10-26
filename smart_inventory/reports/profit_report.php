<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

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
    header("Location: /smart_inventory/login.php");
    exit();
}

require_once "../config/database.php";

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');

$totalSales = 0;
$totalCost = 0;
$totalProfit = 0;
$profitMargin = 0;
$error = "";

try {

    $stmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(total_amount), 0) AS sales,
            COALESCE(SUM(total_cost), 0) AS cost,
            COALESCE(SUM(total_profit), 0) AS profit
         FROM sales
         WHERE DATE(sale_date)
         BETWEEN ? AND ?"
    );

    $stmt->bind_param(
        "ss",
        $start_date,
        $end_date
    );

    $stmt->execute();

    $row =
        $stmt->get_result()->fetch_assoc();

    $totalSales =
        $row['sales'];

    $totalCost =
        $row['cost'];

    $totalProfit =
        $row['profit'];

    if ($totalSales > 0) {

        $profitMargin =
            ($totalProfit / $totalSales) * 100;

    }

} catch (Throwable $e) {

    $error =
        $e->getMessage();

}


/* GET SALES LIST */

$sales = null;

try {

    $stmt = $conn->prepare(
        "SELECT
            transaction_number,
            total_amount,
            total_cost,
            total_profit,
            sale_date
         FROM sales
         WHERE DATE(sale_date)
         BETWEEN ? AND ?
         ORDER BY sale_date DESC"
    );

    $stmt->bind_param(
        "ss",
        $start_date,
        $end_date
    );

    $stmt->execute();

    $sales =
        $stmt->get_result();

} catch (Throwable $e) {

    $error =
        $e->getMessage();

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Profit Report</title>

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

.navbar {
    background: #1b1b1b;
    color: white;
    padding: 18px 30px;

    display: flex;
    justify-content: space-between;
    align-items: center;
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

.container {
    padding: 30px;
}

.filter-box,
.panel {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);
}

.filter-form {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 6px;
}

.form-group input {
    padding: 10px;
    border: 1px solid #d0d0d0;
    border-radius: 5px;
}

.btn {
    border: none;
    color: white;
    padding: 11px 17px;
    border-radius: 5px;
    cursor: pointer;
}

.filter-btn {
    background: #111111;
}

.print-btn {
    background: #292929;
}

.cards {
    display: grid;
    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;
    margin-bottom: 20px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 10px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);
}

.card h3 {
    color: #707070;
    font-size: 14px;
    margin-bottom: 10px;
}

.profit {
    color: #292929;
    font-weight: bold;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 11px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}

th {
    background: #f3f3f3;
}

.error {
    background: #eeeeee;
    color: #111111;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

@media print {

    .navbar,
    .filter-box,
    .print-btn {
        display: none !important;
    }

    body {
        background: white;
    }

    .card,
    .panel {
        box-shadow: none;
        border: 1px solid #ddd;
    }

}

@media (max-width: 900px) {

    .cards {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 600px) {

    .cards {
        grid-template-columns: 1fr;
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



<div class="navbar">

<strong>
Smart Inventory & Sales Management
</strong>

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

<a href="/smart_inventory/analytics/index.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt=""> Analytics
</a>

<a href="/smart_inventory/reports/sales_report.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/report.svg" alt=""> Sales Report
</a>

<a href="/smart_inventory/reports/inventory_report.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt=""> Inventory Report
</a>

<a href="/smart_inventory/reports/profit_report.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/money.svg" alt=""> Profit Report
</a>

<a href="/smart_inventory/logout.php">
<img class="ui-icon" src="/smart_inventory/assets/icons/logout.svg" alt=""> Logout
</a>

</div>

</div>


<div class="container">

<h1>
<img class="ui-icon" src="/smart_inventory/assets/icons/money.svg" alt=""> Profit Report
</h1>

<br>


<?php if ($error !== ""): ?>

<div class="error">

<?php
echo htmlspecialchars($error);
?>

</div>

<?php endif; ?>


<div class="filter-box">

<form
    method="GET"
    class="filter-form"
>

<div class="form-group">

<label>
Start Date
</label>

<input
    type="date"
    name="start_date"
    value="<?php
        echo htmlspecialchars($start_date);
    ?>"
>

</div>


<div class="form-group">

<label>
End Date
</label>

<input
    type="date"
    name="end_date"
    value="<?php
        echo htmlspecialchars($end_date);
    ?>"
>

</div>


<button
    type="submit"
    class="btn filter-btn"
>
Filter
</button>


<button
    type="button"
    class="btn print-btn"
    onclick="window.print();"
>
<img class="ui-icon" src="/smart_inventory/assets/icons/print.svg" alt=""> Print
</button>

</form>

</div>


<div class="cards">


<div class="card">

<h3>
TOTAL SALES
</h3>

<h2>

₱<?php
echo number_format(
    $totalSales,
    2
);
?>

</h2>

</div>


<div class="card">

<h3>
TOTAL COST
</h3>

<h2>

₱<?php
echo number_format(
    $totalCost,
    2
);
?>

</h2>

</div>


<div class="card">

<h3>
TOTAL PROFIT
</h3>

<h2 class="profit">

₱<?php
echo number_format(
    $totalProfit,
    2
);
?>

</h2>

</div>


<div class="card">

<h3>
PROFIT MARGIN
</h3>

<h2 class="profit">

<?php
echo number_format(
    $profitMargin,
    2
);
?>%

</h2>

</div>


</div>


<div class="panel">

<h2>
Transaction Profit Details
</h2>

<br>


<table>

<thead>

<tr>

<th>
Transaction
</th>

<th>
Date
</th>

<th>
Sales
</th>

<th>
Cost
</th>

<th>
Profit
</th>

</tr>

</thead>


<tbody>


<?php

if (
    $sales &&
    $sales->num_rows > 0
):

while (
    $sale =
    $sales->fetch_assoc()
):

?>

<tr>

<td>

<?php
echo htmlspecialchars(
    $sale['transaction_number']
);
?>

</td>


<td>

<?php
echo date(
    "M d, Y h:i A",
    strtotime(
        $sale['sale_date']
    )
);
?>

</td>


<td>

₱<?php
echo number_format(
    $sale['total_amount'],
    2
);
?>

</td>


<td>

₱<?php
echo number_format(
    $sale['total_cost'],
    2
);
?>

</td>


<td class="profit">

₱<?php
echo number_format(
    $sale['total_profit'],
    2
);
?>

</td>

</tr>


<?php

endwhile;

else:

?>


<tr>

<td colspan="5">
No transactions found.
</td>

</tr>


<?php endif; ?>


</tbody>

</table>

</div>


</div>

</body>

</html>