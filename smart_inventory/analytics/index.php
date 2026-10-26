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


/* =========================================
   DEFAULT VALUES
========================================= */

$totalSales = 0;
$totalProfit = 0;
$totalTransactions = 0;
$totalProductsSold = 0;

$todaySales = 0;
$todayProfit = 0;

$error = "";


/* =========================================
   TOTAL SALES
========================================= */

try {

    $result = $conn->query(
        "SELECT
            COALESCE(SUM(total_amount), 0) AS total
         FROM sales"
    );

    $row = $result->fetch_assoc();

    $totalSales = $row['total'];

} catch (Throwable $e) {

    $error = $e->getMessage();

}


/* =========================================
   TOTAL PROFIT
========================================= */

try {

    $result = $conn->query(
        "SELECT
            COALESCE(SUM(total_profit), 0) AS total
         FROM sales"
    );

    $row = $result->fetch_assoc();

    $totalProfit = $row['total'];

} catch (Throwable $e) {

    $error = $e->getMessage();

}


/* =========================================
   TOTAL TRANSACTIONS
========================================= */

try {

    $result = $conn->query(
        "SELECT
            COUNT(*) AS total
         FROM sales"
    );

    $row = $result->fetch_assoc();

    $totalTransactions = $row['total'];

} catch (Throwable $e) {

    $error = $e->getMessage();

}


/* =========================================
   TOTAL PRODUCTS SOLD
========================================= */

try {

    $result = $conn->query(
        "SELECT
            COALESCE(SUM(quantity), 0) AS total
         FROM sale_items"
    );

    $row = $result->fetch_assoc();

    $totalProductsSold = $row['total'];

} catch (Throwable $e) {

    $error = $e->getMessage();

}


/* =========================================
   TODAY'S SALES AND PROFIT
========================================= */

try {

    $result = $conn->query(
        "SELECT
            COALESCE(SUM(total_amount), 0)
                AS sales,

            COALESCE(SUM(total_profit), 0)
                AS profit

         FROM sales

         WHERE DATE(sale_date) = CURDATE()"
    );

    $row = $result->fetch_assoc();

    $todaySales = $row['sales'];

    $todayProfit = $row['profit'];

} catch (Throwable $e) {

    $error = $e->getMessage();

}


/* =========================================
   BEST SELLING PRODUCTS
========================================= */

$bestSelling = null;

try {

    $bestSelling = $conn->query(
        "SELECT
            p.id,
            p.product_name,

            SUM(si.quantity)
                AS total_quantity,

            SUM(si.subtotal)
                AS total_revenue,

            SUM(si.profit)
                AS total_profit

         FROM sale_items si

         INNER JOIN products p
            ON si.product_id = p.id

         GROUP BY
            p.id,
            p.product_name

         ORDER BY total_quantity DESC

         LIMIT 5"
    );

} catch (Throwable $e) {

    $error = $e->getMessage();

}


/* =========================================
   SALES LAST 7 DAYS
========================================= */

$sevenDaySales = null;

try {

    $sevenDaySales = $conn->query(
        "SELECT
            DATE(sale_date) AS sale_day,

            COUNT(*) AS transactions,

            COALESCE(
                SUM(total_amount),
                0
            ) AS total_sales,

            COALESCE(
                SUM(total_profit),
                0
            ) AS total_profit

         FROM sales

         WHERE sale_date >=
            DATE_SUB(
                CURDATE(),
                INTERVAL 6 DAY
            )

         GROUP BY DATE(sale_date)

         ORDER BY sale_day DESC"
    );

} catch (Throwable $e) {

    $error = $e->getMessage();

}


/* =========================================
   LOW STOCK PRODUCTS
========================================= */

$lowStock = null;

try {

    $lowStock = $conn->query(
        "SELECT
            id,
            product_name,
            stock_quantity,
            minimum_stock

         FROM products

         WHERE
            stock_quantity <= minimum_stock

         AND status = 'active'

         ORDER BY stock_quantity ASC

         LIMIT 10"
    );

} catch (Throwable $e) {

    $error = $e->getMessage();

}

?>

<!DOCTYPE html>

<html lang="en">
<?php

/* =========================
   CHART DATA
========================= */

$salesLabels = [];
$salesValues = [];

$chartSalesQuery = $conn->query(
    "SELECT
        DATE(sale_date) AS sale_day,
        COALESCE(SUM(total_amount), 0) AS total_sales
     FROM sales
     WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(sale_date)
     ORDER BY sale_day ASC"
);

while ($row = $chartSalesQuery->fetch_assoc()) {

    $salesLabels[] = date(
        "M d",
        strtotime($row['sale_day'])
    );

    $salesValues[] = floatval(
        $row['total_sales']
    );
}


/* TOP PRODUCTS CHART */

$productLabels = [];
$productValues = [];

$chartProductsQuery = $conn->query(
    "SELECT
        p.product_name,
        SUM(si.quantity) AS total_sold

     FROM sale_items si

     INNER JOIN products p
        ON si.product_id = p.id

     GROUP BY
        p.id,
        p.product_name

     ORDER BY total_sold DESC

     LIMIT 5"
);

while (
    $row =
    $chartProductsQuery->fetch_assoc()
) {

    $productLabels[] =
        $row['product_name'];

    $productValues[] =
        intval($row['total_sold']);
}

?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Business Analytics
</title>


<style>

* {

    box-sizing: border-box;

    margin: 0;

    padding: 0;

    font-family:
        Arial,
        sans-serif;

}


body {

    background: #f4f6f9;

    color: #1b1b1b;

}


/* =========================================
   NAVBAR
========================================= */

.navbar {

    background: #1b1b1b;

    color: white;

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.navbar h2 {

    font-size: 20px;

}


.navbar div {

    display: flex;

    align-items: center;

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


.analytics-link {

    background: #333333;

}


/* =========================================
   CONTAINER
========================================= */

.container {

    padding: 30px;

}


.page-header {

    margin-bottom: 25px;

}


.page-header h1 {

    margin-bottom: 6px;

}


.page-header p {

    color: #707070;

}


/* =========================================
   ERROR
========================================= */

.error {

    background: #eeeeee;

    color: #111111;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;

}


/* =========================================
   SUMMARY CARDS
========================================= */

.cards {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;

    margin-bottom: 20px;

}


.card {

    background: white;

    padding: 22px;

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


.card h1 {

    font-size: 27px;

}


/* =========================================
   TODAY CARDS
========================================= */

.today-cards {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;

    margin-bottom: 25px;

}


.today {

    background: white;

    padding: 22px;

    border-radius: 10px;

    border-left:
        5px solid #111111;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);

}


.today h3 {

    color: #707070;

    font-size: 14px;

    margin-bottom: 10px;

}


.today h2 {

    font-size: 26px;

}


/* =========================================
   PANELS
========================================= */

.grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;

    margin-bottom: 20px;

}


.panel {

    background: white;

    padding: 22px;

    border-radius: 10px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);

    overflow-x: auto;

}


.panel h2 {

    margin-bottom: 18px;

    font-size: 19px;

}


/* =========================================
   TABLE
========================================= */

table {

    width: 100%;

    border-collapse: collapse;

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

    font-size: 14px;

}


td {

    font-size: 14px;

}


.profit {

    color: #292929;

    font-weight: bold;

}


.low {

    color: #111111;

    font-weight: bold;

}


.empty {

    text-align: center;

    color: #707070;

    padding: 25px;

}


/* =========================================
   RESPONSIVE
========================================= */

@media (max-width: 1000px) {

    .cards {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .grid {

        grid-template-columns:
            1fr;

    }

}


@media (max-width: 650px) {

    .navbar {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }


    .cards {

        grid-template-columns:
            1fr;

    }


    .today-cards {

        grid-template-columns:
            1fr;

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



<!-- =========================================
     NAVBAR
========================================= -->

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

<a href="/smart_inventory/sales/history.php">
    <img class="ui-icon" src="/smart_inventory/assets/icons/history.svg" alt=""> Sales History
</a>

<a
    href="/smart_inventory/analytics/index.php"
    class="analytics-link"
>
    <img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt=""> Analytics
</a>

<a href="/smart_inventory/suppliers/index.php">
    <img class="ui-icon" src="/smart_inventory/assets/icons/truck.svg" alt=""> Suppliers
</a>

<a href="/smart_inventory/logout.php">
    <img class="ui-icon" src="/smart_inventory/assets/icons/logout.svg" alt=""> Logout
</a>

</div>

</div>


<!-- =========================================
     MAIN
========================================= -->

<div class="container">


<div class="page-header">

<h1>
    <img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt=""> Business Analytics
</h1>

<p>
    Sales, profit, inventory, and product performance.
</p>

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


<!-- =========================================
     MAIN STATISTICS
========================================= -->

<div class="cards">


<div class="card">

<h3>
    TOTAL SALES REVENUE
</h3>

<h1>

₱<?php
echo number_format(
    $totalSales,
    2
);
?>

</h1>

</div>


<div class="card">

<h3>
    TOTAL PROFIT
</h3>

<h1 class="profit">

₱<?php
echo number_format(
    $totalProfit,
    2
);
?>

</h1>

</div>


<div class="card">

<h3>
    TOTAL TRANSACTIONS
</h3>

<h1>

<?php
echo number_format(
    $totalTransactions
);
?>

</h1>

</div>


<div class="card">

<h3>
    PRODUCTS SOLD
</h3>

<h1>

<?php
echo number_format(
    $totalProductsSold
);
?>

</h1>

</div>


</div>


<!-- =========================================
     TODAY
========================================= -->

<div class="today-cards">


<div class="today">

<h3>
    TODAY'S SALES
</h3>

<h2>

₱<?php
echo number_format(
    $todaySales,
    2
);
?>

</h2>

</div>


<div class="today">

<h3>
    TODAY'S PROFIT
</h3>

<h2 class="profit">

₱<?php
echo number_format(
    $todayProfit,
    2
);
?>

</h2>

</div>


</div>


<!-- =========================================
     FIRST GRID
========================================= -->

<div class="grid">


<!-- BEST SELLING -->

<div class="panel">

<h2>
    <img class="ui-icon" src="/smart_inventory/assets/icons/award.svg" alt=""> Top Selling Products
</h2>


<table>

<thead>

<tr>

<th>
    Product
</th>

<th>
    Sold
</th>

<th>
    Revenue
</th>

<th>
    Profit
</th>

</tr>

</thead>


<tbody>


<?php

if (
    $bestSelling &&
    $bestSelling->num_rows > 0
):

while (
    $row =
    $bestSelling->fetch_assoc()
):

?>


<tr>

<td>

<?php
echo htmlspecialchars(
    $row['product_name']
);
?>

</td>


<td>

<?php
echo number_format(
    $row['total_quantity']
);
?>

</td>


<td>

₱<?php
echo number_format(
    $row['total_revenue'],
    2
);
?>

</td>


<td class="profit">

₱<?php
echo number_format(
    $row['total_profit'],
    2
);
?>

</td>

</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td
    colspan="4"
    class="empty"
>

No sales data yet.

</td>

</tr>


<?php endif; ?>


</tbody>

</table>

</div>


<!-- LOW STOCK -->

<div class="panel">

<h2>
    ⚠️ Low Stock Products
</h2>


<table>

<thead>

<tr>

<th>
    Product
</th>

<th>
    Current
</th>

<th>
    Minimum
</th>

<th>
    Status
</th>

</tr>

</thead>


<tbody>


<?php

if (
    $lowStock &&
    $lowStock->num_rows > 0
):

while (
    $row =
    $lowStock->fetch_assoc()
):

?>


<tr>

<td>

<?php
echo htmlspecialchars(
    $row['product_name']
);
?>

</td>


<td class="low">

<?php
echo number_format(
    $row['stock_quantity']
);
?>

</td>


<td>

<?php
echo number_format(
    $row['minimum_stock']
);
?>

</td>


<td class="low">

<?php

if (
    $row['stock_quantity'] <= 0
) {

    echo "OUT OF STOCK";

} else {

    echo "LOW STOCK";

}

?>

</td>

</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td
    colspan="4"
    class="empty"
>

No low-stock products.

</td>

</tr>


<?php endif; ?>


</tbody>

</table>

</div>


</div>


<!-- =========================================
     7 DAY SALES
========================================= -->
<div class="grid">

    <div class="panel">

        <h2>
            <img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt=""> Sales Trend
        </h2>

        <canvas id="salesChart"></canvas>

    </div>


    <div class="panel">

        <h2>
            <img class="ui-icon" src="/smart_inventory/assets/icons/award.svg" alt=""> Top Selling Products
        </h2>

        <canvas id="productsChart"></canvas>

    </div>

</div>

<div class="panel">

<h2>
    <img class="ui-icon" src="/smart_inventory/assets/icons/calendar.svg" alt=""> Sales Performance, Last 7 Days
</h2>


<table>

<thead>

<tr>

<th>
    Date
</th>

<th>
    Transactions
</th>

<th>
    Sales
</th>

<th>
    Profit
</th>

</tr>

</thead>


<tbody>


<?php

if (
    $sevenDaySales &&
    $sevenDaySales->num_rows > 0
):

while (
    $row =
    $sevenDaySales->fetch_assoc()
):

?>


<tr>


<td>

<?php

echo date(
    "M d, Y",
    strtotime(
        $row['sale_day']
    )
);

?>

</td>


<td>

<?php
echo number_format(
    $row['transactions']
);
?>

</td>


<td>

₱<?php
echo number_format(
    $row['total_sales'],
    2
);
?>

</td>


<td class="profit">

₱<?php
echo number_format(
    $row['total_profit'],
    2
);
?>

</td>


</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td
    colspan="4"
    class="empty"
>

No sales during the last 7 days.

</td>

</tr>


<?php endif; ?>


</tbody>

</table>


</div>


</div>


</body>
<script>

const salesLabels =
    <?php
    echo json_encode($salesLabels);
    ?>;

const salesValues =
    <?php
    echo json_encode($salesValues);
    ?>;


const productLabels =
    <?php
    echo json_encode($productLabels);
    ?>;

const productValues =
    <?php
    echo json_encode($productValues);
    ?>;


/* =========================
   SALES CHART
========================= */

const salesCanvas =
    document.getElementById(
        'salesChart'
    );

if (salesCanvas) {

    new Chart(
        salesCanvas,
        {

            type: 'line',

            data: {

                labels:
                    salesLabels,

                datasets: [

                    {

                        label:
                            'Sales Revenue',

                        data:
                            salesValues,

                        borderWidth: 2,

                        tension: 0.3

                    }

                ]

            },

            options: {

                responsive: true,

                plugins: {

                    legend: {
                        display: true
                    }

                },

                scales: {

                    y: {

                        beginAtZero: true

                    }

                }

            }

        }
    );

}


/* =========================
   PRODUCTS CHART
========================= */

const productCanvas =
    document.getElementById(
        'productsChart'
    );

if (productCanvas) {

    new Chart(
        productCanvas,
        {

            type: 'bar',

            data: {

                labels:
                    productLabels,

                datasets: [

                    {

                        label:
                            'Quantity Sold',

                        data:
                            productValues,

                        /* Same aurora palette used by the dashboard */
                        backgroundColor: [
                            '#c06ceb',
                            '#5df09d',
                            '#262740',
                            '#9a70d2',
                            '#55cfa0'
                        ],

                        hoverBackgroundColor: [
                            '#cf7cf2',
                            '#70f5aa',
                            '#353654',
                            '#aa7ee0',
                            '#66deae'
                        ],

                        borderColor: [
                            '#d89af5',
                            '#8af8bb',
                            '#4a4b6c',
                            '#c192e7',
                            '#85edbd'
                        ],

                        borderWidth: 1,

                        borderRadius: 7,

                        borderSkipped: false

                    }

                ]

            },

            options: {

                responsive: true,

                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {

                            precision: 0

                        }

                    }

                }

            }

        }
    );

}

</script>
</html>