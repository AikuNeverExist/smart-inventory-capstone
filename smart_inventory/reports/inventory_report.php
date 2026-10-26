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

$error = "";


/* =========================
   GET INVENTORY
========================= */

$products = null;

$totalProducts = 0;
$totalUnits = 0;
$totalStockValue = 0;
$lowStockCount = 0;

try {

    $products = $conn->query(
        "SELECT
            id,
            product_name,
            cost_price,
            selling_price,
            stock_quantity,
            minimum_stock,
            status
         FROM products
         WHERE status = 'active'
         ORDER BY product_name ASC"
    );


    $summary = $conn->query(
        "SELECT
            COUNT(*) AS total_products,
            COALESCE(SUM(stock_quantity), 0) AS total_units,
            COALESCE(SUM(cost_price * stock_quantity), 0) AS stock_value,
            COALESCE(SUM(
                CASE
                    WHEN stock_quantity <= minimum_stock
                    THEN 1
                    ELSE 0
                END
            ), 0) AS low_stock

         FROM products
         WHERE status = 'active'"
    );


    $row = $summary->fetch_assoc();

    $totalProducts =
        $row['total_products'];

    $totalUnits =
        $row['total_units'];

    $totalStockValue =
        $row['stock_value'];

    $lowStockCount =
        $row['low_stock'];


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

<title>
Inventory Report
</title>


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


/* CONTAINER */

.container {

    padding: 30px;
}


/* HEADER */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}


/* CARDS */

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

    margin: 0 0 10px;

    color: #707070;

    font-size: 14px;
}

.card h2 {

    margin: 0;
}


/* PANEL */

.panel {

    background: white;

    padding: 25px;

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

    min-width: 900px;
}

th,
td {

    padding: 11px;

    text-align: left;

    border-bottom:
        1px solid #e5e7eb;
}

th {

    background: #f3f3f3;
}


/* STATUS */

.good {

    color: #292929;

    font-weight: bold;
}

.low {

    color: #111111;

    font-weight: bold;
}

.out {

    color: #111111;

    font-weight: bold;
}


/* BUTTON */

.btn {

    background: #292929;

    color: white;

    border: none;

    padding: 11px 16px;

    border-radius: 5px;

    cursor: pointer;
}


/* ERROR */

.error {

    background: #eeeeee;

    color: #111111;

    padding: 15px;

    margin-bottom: 20px;

    border-radius: 8px;
}


/* PRINT */

@media print {

    body {

        background: white;
    }

    .navbar,
    .btn {

        display: none !important;
    }

    .container {

        padding: 0;
    }

    .panel,
    .card {

        box-shadow: none;

        border: 1px solid #ddd;
    }

}


/* RESPONSIVE */

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

<a href="/smart_inventory/logout.php">
    <img class="ui-icon" src="/smart_inventory/assets/icons/logout.svg" alt=""> Logout
</a>

</div>

</div>


<!-- MAIN -->

<div class="container">


<div class="page-header">

<div>

<h1>
    <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt=""> Inventory Report
</h1>

<p>
    Current inventory levels and stock value.
</p>

</div>


<button
    type="button"
    class="btn"
    onclick="window.print();"
>
    <img class="ui-icon" src="/smart_inventory/assets/icons/print.svg" alt=""> Print Report
</button>

</div>


<?php if ($error !== ""): ?>

<div class="error">

<strong>
Error:
</strong>

<?php
echo htmlspecialchars($error);
?>

</div>

<?php endif; ?>


<!-- SUMMARY -->

<div class="cards">


<div class="card">

<h3>
    TOTAL PRODUCTS
</h3>

<h2>

<?php
echo number_format(
    $totalProducts
);
?>

</h2>

</div>


<div class="card">

<h3>
    TOTAL UNITS
</h3>

<h2>

<?php
echo number_format(
    $totalUnits
);
?>

</h2>

</div>


<div class="card">

<h3>
    INVENTORY COST VALUE
</h3>

<h2>

₱<?php
echo number_format(
    $totalStockValue,
    2
);
?>

</h2>

</div>


<div class="card">

<h3>
    LOW STOCK PRODUCTS
</h3>

<h2 class="low">

<?php
echo number_format(
    $lowStockCount
);
?>

</h2>

</div>


</div>


<!-- TABLE -->

<div class="panel">


<table>

<thead>

<tr>

<th>
    Product
</th>

<th>
    Cost Price
</th>

<th>
    Selling Price
</th>

<th>
    Current Stock
</th>

<th>
    Minimum
</th>

<th>
    Stock Cost Value
</th>

<th>
    Potential Sales Value
</th>

<th>
    Status
</th>

</tr>

</thead>


<tbody>


<?php

if (
    $products &&
    $products->num_rows > 0
):

while (
    $product =
    $products->fetch_assoc()
):


$stockCostValue =
    $product['cost_price'] *
    $product['stock_quantity'];


$salesValue =
    $product['selling_price'] *
    $product['stock_quantity'];


if (
    $product['stock_quantity'] <= 0
) {

    $status =
        "OUT OF STOCK";

    $statusClass =
        "out";

} elseif (
    $product['stock_quantity'] <=
    $product['minimum_stock']
) {

    $status =
        "LOW STOCK";

    $statusClass =
        "low";

} else {

    $status =
        "IN STOCK";

    $statusClass =
        "good";
}

?>


<tr>


<td>

<?php
echo htmlspecialchars(
    $product['product_name']
);
?>

</td>


<td>

₱<?php
echo number_format(
    $product['cost_price'],
    2
);
?>

</td>


<td>

₱<?php
echo number_format(
    $product['selling_price'],
    2
);
?>

</td>


<td>

<?php
echo number_format(
    $product['stock_quantity']
);
?>

</td>


<td>

<?php
echo number_format(
    $product['minimum_stock']
);
?>

</td>


<td>

₱<?php
echo number_format(
    $stockCostValue,
    2
);
?>

</td>


<td>

₱<?php
echo number_format(
    $salesValue,
    2
);
?>

</td>


<td
    class="<?php
        echo $statusClass;
    ?>"
>

<?php
echo $status;
?>

</td>


</tr>


<?php

endwhile;

else:

?>


<tr>

<td colspan="8">

No products found.

</td>

</tr>


<?php endif; ?>


</tbody>

</table>


</div>


</div>


</body>

</html>