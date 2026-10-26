<?php

session_start();
require_once __DIR__ . '/../includes/role_helpers.php';

if (!isset($_SESSION['user_id'])) {

    header(
        "Location: /smart_inventory/login.php"
    );

    exit();
}


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirectToRoleDashboard($_SESSION['role'] ?? 'user');
}


/* ========================================
   CHECK LOGIN
======================================== */

if (!isset($_SESSION['user_id'])) {

    header(
        "Location: /smart_inventory/login.php"
    );

    exit();
}


/* ========================================
   ADMIN ONLY
======================================== */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirectToRoleDashboard($_SESSION['role'] ?? 'user');
}


/* ========================================
   DATABASE CONNECTION
======================================== */

require_once "../config/database.php";


/* ========================================
   TOTAL PRODUCTS
======================================== */

$productQuery = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE status = 'active'"
);

$productData =
    $productQuery
    ? $productQuery->fetch_assoc()
    : ['total' => 0];

$totalProducts =
    $productData['total'] ?? 0;


/* ========================================
   TOTAL STOCK
======================================== */

$stockQuery = $conn->query(
    "SELECT
        COALESCE(
            SUM(stock_quantity),
            0
        ) AS total

     FROM products

     WHERE status = 'active'"
);

$stockData =
    $stockQuery
    ? $stockQuery->fetch_assoc()
    : ['total' => 0];

$totalStock =
    $stockData['total'] ?? 0;


/* ========================================
   TODAY'S SALES
======================================== */

$salesQuery = $conn->query(
    "SELECT
        COALESCE(
            SUM(total_amount),
            0
        ) AS total

     FROM sales

     WHERE DATE(sale_date) = CURDATE()"
);

$salesData =
    $salesQuery
    ? $salesQuery->fetch_assoc()
    : ['total' => 0];

$todaySales =
    $salesData['total'] ?? 0;


/* ========================================
   TODAY'S PROFIT
======================================== */

$profitQuery = $conn->query(
    "SELECT
        COALESCE(
            SUM(total_profit),
            0
        ) AS total

     FROM sales

     WHERE DATE(sale_date) = CURDATE()"
);

$profitData =
    $profitQuery
    ? $profitQuery->fetch_assoc()
    : ['total' => 0];

$todayProfit =
    $profitData['total'] ?? 0;


/* ========================================
   LOW STOCK PRODUCTS

   Low stock only:
   stock must still be greater than 0
======================================== */

$lowStockQuery = $conn->query(
    "SELECT
        id,
        product_name,
        stock_quantity,
        minimum_stock

     FROM products

     WHERE
        stock_quantity > 0
        AND stock_quantity <= minimum_stock
        AND status = 'active'

     ORDER BY stock_quantity ASC

     LIMIT 5"
);


/* ========================================
   LOW STOCK COUNT
======================================== */

$lowStockCountQuery = $conn->query(
    "SELECT COUNT(*) AS total

     FROM products

     WHERE
        stock_quantity > 0
        AND stock_quantity <= minimum_stock
        AND status = 'active'"
);

$lowStockCountData =
    $lowStockCountQuery
    ? $lowStockCountQuery->fetch_assoc()
    : ['total' => 0];

$lowStockCount =
    $lowStockCountData['total'] ?? 0;


/* ========================================
   OUT OF STOCK COUNT
======================================== */

$outOfStockQuery = $conn->query(
    "SELECT COUNT(*) AS total

     FROM products

     WHERE
        stock_quantity <= 0
        AND status = 'active'"
);

$outOfStockData =
    $outOfStockQuery
    ? $outOfStockQuery->fetch_assoc()
    : ['total' => 0];

$outOfStockCount =
    $outOfStockData['total'] ?? 0;


/* ========================================
   RECENT PRODUCTS
======================================== */

$recentProductsQuery = $conn->query(
    "SELECT
        id,
        product_name,
        selling_price,
        stock_quantity,
        minimum_stock

     FROM products

     WHERE status = 'active'

     ORDER BY id DESC

     LIMIT 5"
);


/* ========================================
   RECENT ACTIVITY
======================================== */

$recentActivityQuery = $conn->query(
    "SELECT
        activity_logs.action,
        activity_logs.description,
        activity_logs.created_at,
        users.name

     FROM activity_logs

     LEFT JOIN users
        ON activity_logs.user_id = users.id

     ORDER BY activity_logs.created_at DESC

     LIMIT 5"
);


/* ========================================
   ALL-TIME SALES REVENUE
======================================== */

$totalSalesQuery = $conn->query(
    "SELECT
        COALESCE(
            SUM(total_amount),
            0
        ) AS total

     FROM sales"
);

$totalSalesData =
    $totalSalesQuery
    ? $totalSalesQuery->fetch_assoc()
    : ['total' => 0];

$totalSales =
    $totalSalesData['total'] ?? 0;


/* ========================================
   ALL-TIME PROFIT
======================================== */

$totalProfitQuery = $conn->query(
    "SELECT
        COALESCE(
            SUM(total_profit),
            0
        ) AS total

     FROM sales"
);

$totalProfitData =
    $totalProfitQuery
    ? $totalProfitQuery->fetch_assoc()
    : ['total' => 0];

$totalProfit =
    $totalProfitData['total'] ?? 0;


/* ========================================
   TOTAL TRANSACTIONS
======================================== */

$totalTransactionsQuery = $conn->query(
    "SELECT COUNT(*) AS total
     FROM sales"
);

$totalTransactionsData =
    $totalTransactionsQuery
    ? $totalTransactionsQuery->fetch_assoc()
    : ['total' => 0];

$totalTransactions =
    $totalTransactionsData['total'] ?? 0;


/* ========================================
   LAST 7 DAYS SALES

   Get actual values from database first.
======================================== */

$salesTrendQuery = $conn->query(
    "SELECT
        DATE(sale_date) AS sale_day,
        COALESCE(
            SUM(total_amount),
            0
        ) AS total_sales

     FROM sales

     WHERE DATE(sale_date)
        BETWEEN
            DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            AND CURDATE()

     GROUP BY DATE(sale_date)

     ORDER BY DATE(sale_date) ASC"
);


/* ========================================
   STORE SALES BY DATE
======================================== */

$salesByDate = [];

if ($salesTrendQuery) {

    while (
        $row =
        $salesTrendQuery->fetch_assoc()
    ) {

        $salesByDate[
            $row['sale_day']
        ] =
            (float)$row['total_sales'];
    }
}


/* ========================================
   CREATE COMPLETE 7 DAYS

   Even days with no sales will show 0.
======================================== */

$salesLabels = [];

$salesValues = [];

for ($i = 6; $i >= 0; $i--) {

    $date =
        date(
            'Y-m-d',
            strtotime("-$i day")
        );

    $salesLabels[] =
        date(
            'M d',
            strtotime($date)
        );

    $salesValues[] =
        isset($salesByDate[$date])
        ? (float)$salesByDate[$date]
        : 0;
}


/* ========================================
   TOP SELLING PRODUCTS
======================================== */

$topProductsQuery = $conn->query(
    "SELECT
        products.product_name,

        COALESCE(
            SUM(sale_items.quantity),
            0
        ) AS total_quantity

     FROM sale_items

     INNER JOIN products
        ON sale_items.product_id =
           products.id

     GROUP BY
        products.id,
        products.product_name

     ORDER BY total_quantity DESC

     LIMIT 5"
);

$productLabels = [];

$productValues = [];

if ($topProductsQuery) {

    while (
        $row =
        $topProductsQuery->fetch_assoc()
    ) {

        $productLabels[] =
            $row['product_name'];

        $productValues[] =
            (int)$row['total_quantity'];
    }
}


/* ========================================
   ADMIN NAME
======================================== */

$adminName =
    $_SESSION['name']
    ?? 'Administrator';

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
    Dashboard | Smart Inventory
</title>


<!-- ====================================
     CHART.JS
==================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/chart.js">
</script>


<style>

/* ========================================
   GLOBAL
======================================== */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


:root {

    --dark:
        #111111;

    --dark-light:
        #1b1b1b;

    --primary:
        #111111;

    --primary-dark:
        #000000;

    --background:
        #f4f7fb;

    --white:
        #ffffff;

    --text:
        #111111;

    --muted:
        #707070;

    --border:
        #e3e3e3;

    --success:
        #292929;

    --danger:
        #111111;

    --warning:
        #555555;

    --purple:
        #333333;

    --cyan:
        #444444;
}


html {
    scroll-behavior: smooth;
}


body {

    min-height: 100vh;

    background:
        var(--background);

    color:
        var(--text);
}


/* ========================================
   MAIN LAYOUT
======================================== */

.dashboard-layout {

    display: flex;

    min-height: 100vh;
}


/* ========================================
   SIDEBAR
======================================== */

.sidebar {

    width: 270px;

    position: fixed;

    top: 0;
    left: 0;
    bottom: 0;

    background:
        linear-gradient(
            180deg,
            #111111 0%,
            #111827 100%
        );

    color: white;

    overflow-y: auto;

    z-index: 1000;

    transition:
        transform 0.3s ease;
}


.sidebar::-webkit-scrollbar {

    width: 5px;
}


.sidebar::-webkit-scrollbar-thumb {

    background:
        rgba(255,255,255,0.18);

    border-radius: 20px;
}


/* ========================================
   LOGO / BRAND
======================================== */

.brand {

    padding: 25px 20px;

    border-bottom:
        1px solid
        rgba(255,255,255,0.08);
}


.brand-content {

    display: flex;

    align-items: center;

    gap: 12px;
}


.brand-icon {

    width: 47px;
    height: 47px;

    display: flex;

    justify-content: center;
    align-items: center;

    border-radius: 13px;

    font-size: 22px;

    background:
        linear-gradient(
            135deg,
            #111111,
            #555555
        );

    box-shadow:
        0 8px 18px
        rgba(37,99,235,0.28);
}


.brand-text h2 {

    font-size: 17px;

    line-height: 1.2;
}


.brand-text p {

    color: #9a9a9a;

    font-size: 11px;

    margin-top: 4px;
}


/* ========================================
   SIDEBAR MENU
======================================== */

.menu {

    padding:
        18px 13px 30px;
}


.menu-title {

    color: #707070;

    font-size: 10px;

    font-weight: bold;

    letter-spacing: 1.2px;

    text-transform: uppercase;

    padding:
        15px 12px 8px;
}


.menu a {

    display: flex;

    align-items: center;

    gap: 11px;

    color: #d0d0d0;

    text-decoration: none;

    padding:
        11px 13px;

    margin-bottom: 4px;

    border-radius: 9px;

    font-size: 14px;

    transition: 0.2s;
}


.menu a:hover {

    color: white;

    background:
        #1b1b1b;

    transform:
        translateX(3px);
}


.menu a.active {

    color: white;

    background:
        linear-gradient(
            135deg,
            #111111,
            #000000
        );

    box-shadow:
        0 5px 15px
        rgba(37,99,235,0.25);
}


.menu-icon {

    width: 21px;

    text-align: center;
}


.menu .logout {

    color: #dddddd;
}


.menu .logout:hover {

    background:
        rgba(220,38,38,0.14);
}


/* ========================================
   MAIN CONTENT
======================================== */

.main {

    flex: 1;

    margin-left: 270px;

    min-width: 0;
}


/* ========================================
   TOPBAR
======================================== */

.topbar {

    height: 76px;

    background:
        rgba(255,255,255,0.97);

    border-bottom:
        1px solid var(--border);

    padding:
        0 30px;

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    position: sticky;

    top: 0;

    z-index: 500;

    box-shadow:
        0 2px 10px
        rgba(15,23,42,0.03);
}


.topbar-left {

    display: flex;

    align-items: center;

    gap: 13px;
}


.page-title h2 {

    font-size: 21px;
}


.page-title p {

    margin-top: 3px;

    font-size: 12px;

    color: var(--muted);
}


/* ========================================
   ADMIN PROFILE
======================================== */

.profile {

    display: flex;

    align-items: center;

    gap: 11px;
}


.profile-info {

    text-align: right;
}


.profile-info strong {

    display: block;

    font-size: 13px;
}


.profile-info span {

    color:
        var(--muted);

    font-size: 11px;
}


.avatar {

    width: 42px;
    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    color: white;

    font-weight: bold;

    background:
        linear-gradient(
            135deg,
            #111111,
            #333333
        );

    box-shadow:
        0 5px 13px
        rgba(37,99,235,0.2);
}


/* ========================================
   MOBILE MENU
======================================== */

.mobile-menu-btn {

    display: none;

    width: 42px;
    height: 42px;

    border: none;

    border-radius: 8px;

    background:
        var(--dark);

    color: white;

    font-size: 20px;

    cursor: pointer;
}


/* ========================================
   CONTENT
======================================== */

.content {

    padding: 30px;

    max-width: 1700px;

    margin: auto;
}


/* ========================================
   WELCOME AREA
======================================== */

.welcome {

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 20px;

    margin-bottom: 24px;
}


.welcome h1 {

    font-size: 27px;

    margin-bottom: 6px;
}


.welcome p {

    color:
        var(--muted);

    font-size: 14px;
}


/* ========================================
   QUICK ACTIONS
======================================== */

.quick-actions {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;
}


.quick-btn {

    text-decoration: none;

    padding:
        10px 14px;

    border-radius: 8px;

    font-size: 13px;

    font-weight: bold;

    transition: 0.2s;
}


.quick-btn:hover {

    transform:
        translateY(-2px);
}


.btn-sale {

    background:
        #111111;

    color: white;
}


.btn-stock {

    background:
        #eeeeee;

    color:
        #222222;
}


.btn-product {

    background:
        #eeeeee;

    color:
        #222222;
}


/* ========================================
   ALERTS
======================================== */

.alerts {

    display: grid;

    gap: 11px;

    margin-bottom: 23px;
}


.alert {

    padding:
        15px 17px;

    border-radius: 11px;

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 15px;

    font-size: 13px;
}


.alert-left {

    display: flex;

    align-items: center;

    gap: 11px;
}


.alert-icon {

    font-size: 22px;
}


.alert strong {

    display: block;

    margin-bottom: 3px;
}


.alert a {

    color: inherit;

    font-weight: bold;

    text-decoration: none;
}


.alert a:hover {

    text-decoration:
        underline;
}


.alert-danger {

    background:
        #f5f5f5;

    border:
        1px solid #dddddd;

    color:
        #111111;
}


.alert-warning {

    background:
        #f6f6f6;

    border:
        1px solid #dedede;

    color:
        #333333;
}


/* ========================================
   STAT CARDS
======================================== */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(5, minmax(0, 1fr));

    gap: 17px;

    margin-bottom: 28px;
}


.stat-card {

    background: white;

    border:
        1px solid var(--border);

    border-radius: 15px;

    padding: 20px;

    box-shadow:
        0 4px 18px
        rgba(15,23,42,0.04);

    transition: 0.2s;
}


.stat-card:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 10px 25px
        rgba(15,23,42,0.08);
}


.stat-top {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 10px;

    margin-bottom: 16px;
}


.stat-label {

    color:
        var(--muted);

    font-size: 11px;

    font-weight: bold;

    letter-spacing: 0.4px;
}


.stat-icon {

    width: 42px;
    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 11px;

    font-size: 18px;
}


.icon-blue {

    color: #000000;

    background: #eeeeee;
}


.icon-green {

    color: #111111;

    background: #eeeeee;
}


.icon-red {

    color: #111111;

    background: #eeeeee;
}


.icon-purple {

    color: #333333;

    background: #eeeeee;
}


.icon-orange {

    color: #555555;

    background: #ececec;
}


.stat-card h2 {

    font-size: 25px;

    margin-bottom: 5px;

    overflow-wrap: anywhere;
}


.stat-card small {

    color: #9a9a9a;

    font-size: 11px;
}


/* ========================================
   SECTION HEADER
======================================== */

.section-header {

    margin:
        8px 0 17px;
}


.section-header h2 {

    font-size: 21px;

    margin-bottom: 4px;
}


.section-header p {

    color:
        var(--muted);

    font-size: 13px;
}


/* ========================================
   ANALYTICS SUMMARY
======================================== */

.analytics-summary {

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0,1fr));

    gap: 17px;

    margin-bottom: 20px;
}


.analytics-card {

    background: white;

    border:
        1px solid var(--border);

    border-radius: 14px;

    padding: 20px;

    box-shadow:
        0 4px 15px
        rgba(15,23,42,0.04);
}


.analytics-card span {

    color:
        var(--muted);

    font-weight: bold;

    font-size: 11px;

    text-transform:
        uppercase;

    letter-spacing:
        0.5px;
}


.analytics-card h2 {

    font-size: 25px;

    margin-top: 10px;
}


/* ========================================
   CHARTS
======================================== */

.chart-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0,1fr));

    gap: 20px;

    margin-bottom: 28px;
}


.chart-card {

    background: white;

    border:
        1px solid var(--border);

    border-radius: 15px;

    padding: 20px;

    box-shadow:
        0 4px 18px
        rgba(15,23,42,0.04);
}


.chart-header {

    margin-bottom: 18px;
}


.chart-header h3 {

    font-size: 16px;

    margin-bottom: 4px;
}


.chart-header p {

    color:
        var(--muted);

    font-size: 12px;
}


.chart-container {

    position: relative;

    width: 100%;

    height: 300px;
}


/* ========================================
   PANELS
======================================== */

.dashboard-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0,1fr));

    gap: 20px;
}


.panel {

    background: white;

    border:
        1px solid var(--border);

    border-radius: 15px;

    overflow: hidden;

    box-shadow:
        0 4px 18px
        rgba(15,23,42,0.04);
}


.panel.full {

    grid-column:
        1 / -1;
}


.panel-header {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 10px;

    padding:
        18px 20px;

    border-bottom:
        1px solid var(--border);
}


.panel-header h3 {

    font-size: 16px;

    margin-bottom: 4px;
}


.panel-header p {

    color:
        var(--muted);

    font-size: 11px;
}


.panel-body {

    padding:
        0 20px;
}


.panel-footer {

    padding:
        15px 20px 20px;
}


/* ========================================
   TABLE
======================================== */

.table-responsive {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 500px;

    border-collapse:
        collapse;
}


th {

    padding:
        13px 12px;

    text-align: left;

    background:
        #fafafa;

    color:
        #707070;

    font-size: 10px;

    letter-spacing:
        0.5px;

    text-transform:
        uppercase;

    border-bottom:
        1px solid var(--border);
}


td {

    padding:
        14px 12px;

    font-size: 13px;

    border-bottom:
        1px solid #f3f3f3;

    vertical-align: middle;
}


tbody tr {

    transition: 0.15s;
}


tbody tr:hover {

    background:
        #fafafa;
}


tbody tr:last-child td {

    border-bottom: none;
}


/* ========================================
   BADGES
======================================== */

.badge {

    display: inline-block;

    padding:
        5px 9px;

    border-radius: 30px;

    font-size: 11px;

    font-weight: bold;
}


.badge-danger {

    color:
        #000000;

    background:
        #eeeeee;
}


.badge-success {

    color:
        #222222;

    background:
        #eeeeee;
}


.activity-action {

    display: inline-block;

    padding:
        5px 9px;

    border-radius: 20px;

    background:
        #f5f5f5;

    color:
        #000000;

    font-size: 11px;

    font-weight: bold;

    white-space: nowrap;
}


.activity-user {

    font-weight: bold;
}


.activity-date {

    color:
        var(--muted);

    font-size: 11px;

    white-space: nowrap;
}


.description-cell {

    max-width: 520px;
}


/* ========================================
   VIEW BUTTON
======================================== */

.view-btn {

    display: inline-block;

    padding:
        9px 13px;

    border-radius: 8px;

    background:
        #f5f5f5;

    color:
        #000000;

    font-size: 12px;

    font-weight: bold;

    text-decoration: none;

    transition: 0.2s;
}


.view-btn:hover {

    background:
        #eeeeee;
}


/* ========================================
   EMPTY STATE
======================================== */

.empty {

    text-align: center;

    color:
        var(--muted);

    padding:
        25px !important;
}


/* ========================================
   SIDEBAR OVERLAY
======================================== */

.overlay {

    display: none;
}


/* ========================================
   RESPONSIVE
======================================== */

@media (max-width: 1350px) {

    .stats-grid {

        grid-template-columns:
            repeat(3, 1fr);
    }

}


@media (max-width: 1050px) {

    .sidebar {

        transform:
            translateX(-100%);
    }


    .sidebar.open {

        transform:
            translateX(0);
    }


    .main {

        margin-left: 0;
    }


    .mobile-menu-btn {

        display: block;
    }


    .overlay {

        display: block;

        position: fixed;

        inset: 0;

        background:
            rgba(15,23,42,0.55);

        z-index: 900;

        opacity: 0;

        visibility: hidden;

        transition: 0.3s;
    }


    .overlay.show {

        opacity: 1;

        visibility: visible;
    }

}


@media (max-width: 900px) {

    .stats-grid {

        grid-template-columns:
            repeat(2,1fr);
    }


    .analytics-summary {

        grid-template-columns: 1fr;
    }


    .chart-grid {

        grid-template-columns: 1fr;
    }


    .dashboard-grid {

        grid-template-columns: 1fr;
    }


    .panel.full {

        grid-column: auto;
    }


    .welcome {

        flex-direction: column;

        align-items:
            flex-start;
    }

}


@media (max-width: 600px) {

    .content {

        padding:
            20px 14px;
    }


    .topbar {

        padding:
            0 14px;

        height: 70px;
    }


    .page-title h2 {

        font-size: 17px;
    }


    .page-title p {

        display: none;
    }


    .profile-info {

        display: none;
    }


    .stats-grid {

        grid-template-columns: 1fr;

        gap: 12px;
    }


    .quick-actions {

        width: 100%;
    }


    .quick-btn {

        flex: 1;

        text-align: center;
    }


    .alert {

        flex-direction: column;

        align-items:
            flex-start;
    }


    .welcome h1 {

        font-size: 23px;
    }


    .analytics-card h2,
    .stat-card h2 {

        font-size: 22px;
    }

}

</style>

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<style>.brand-mark{width:27px;height:27px;display:block;object-fit:contain}</style>
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
</head>


<body>


<div class="dashboard-layout">


<!-- ========================================
     SIDEBAR
======================================== -->

<aside
    class="sidebar"
    id="sidebar"
>


    <div class="brand">

        <div class="brand-content">


            <div class="brand-icon"><img class="brand-mark" src="/smart_inventory/assets/brand.svg" alt=""></div>


            <div class="brand-text">

                <h2>
                    Smart Inventory
                </h2>

                <p>
                    Sales Management System
                </p>

            </div>


        </div>

    </div>


    <nav class="menu">


        <div class="menu-title">
            Overview
        </div>


        <a
            href="/smart_inventory/admin/dashboard.php"
            class="active"
        >
            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/home.svg" alt="">
            </span>

            Dashboard
        </a>


        <div class="menu-title">
            Management
        </div>


        <a href="/smart_inventory/products/index.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt="">
            </span>

            Products
        </a>


        <a href="/smart_inventory/inventory/index.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/clipboard.svg" alt="">
            </span>

            Inventory
        </a>


        <a href="/smart_inventory/suppliers/index.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/truck.svg" alt="">
            </span>

            Suppliers
        </a>


        <a href="/smart_inventory/users/index.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/users.svg" alt="">
            </span>

            Users
        </a>


        <div class="menu-title">
            Sales
        </div>


        <a href="/smart_inventory/sales/sales.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt="">
            </span>

            Point of Sale
        </a>


        <a href="/smart_inventory/sales/history.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/history.svg" alt="">
            </span>

            Sales History
        </a>


        <div class="menu-title">
            Reports & Analytics
        </div>


        <a href="/smart_inventory/analytics/index.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt="">
            </span>

            Business Analytics
        </a>


        <a href="/smart_inventory/reports/sales_report.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/report.svg" alt="">
            </span>

            Sales Report
        </a>


        <a href="/smart_inventory/reports/inventory_report.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt="">
            </span>

            Inventory Report
        </a>


        <a href="/smart_inventory/reports/profit_report.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/money.svg" alt="">
            </span>

            Profit Report
        </a>


        <div class="menu-title">
            System
        </div>


        <a href="/smart_inventory/admin/activity_logs.php">

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/activity.svg" alt="">
            </span>

            Activity Logs
        </a>


        <a
            href="/smart_inventory/logout.php"
            class="logout"
        >

            <span class="menu-icon">
                <img class="ui-icon" src="/smart_inventory/assets/icons/logout.svg" alt="">
            </span>

            Logout
        </a>


    </nav>


</aside>


<!-- ========================================
     MOBILE OVERLAY
======================================== -->

<div
    class="overlay"
    id="overlay"
    onclick="closeSidebar()"
>
</div>


<!-- ========================================
     MAIN
======================================== -->

<main class="main">


<!-- ========================================
     TOPBAR
======================================== -->

<header class="topbar">


    <div class="topbar-left">


        <button
            class="mobile-menu-btn"
            onclick="toggleSidebar()"
            type="button"
        >
            ☰
        </button>


        <div class="page-title">

            <h2>
                Admin Dashboard
            </h2>

            <p>
                Inventory, sales and business analytics
            </p>

        </div>


    </div>


    <div class="profile">


        <div class="profile-info">

            <strong>

                <?php
                echo htmlspecialchars(
                    $adminName
                );
                ?>

            </strong>

            <span>
                Administrator
            </span>

        </div>


        <div class="avatar">

            <?php

            echo htmlspecialchars(
                strtoupper(
                    substr(
                        $adminName,
                        0,
                        1
                    )
                )
            );

            ?>

        </div>


    </div>


</header>


<!-- ========================================
     CONTENT
======================================== -->

<div class="content">


<!-- ========================================
     WELCOME
======================================== -->

<section class="welcome">


    <div>

        <h1>

            Welcome back,

            <?php

            echo htmlspecialchars(
                $adminName
            );

            ?> 

        </h1>


        <p>
            Here's your inventory and sales overview for today.
        </p>

    </div>


    <div class="quick-actions">


        <a
            href="/smart_inventory/sales/sales.php"
            class="quick-btn btn-sale"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt=""> New Sale
        </a>


        <a
            href="/smart_inventory/inventory/stock_in.php"
            class="quick-btn btn-stock"
        >
            ➕ Stock In
        </a>


        <a
            href="/smart_inventory/products/add.php"
            class="quick-btn btn-product"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt=""> Add Product
        </a>


    </div>


</section>


<!-- ========================================
     ALERTS
======================================== -->

<?php if (
    $outOfStockCount > 0 ||
    $lowStockCount > 0
): ?>


<div class="alerts">


    <?php if (
        $outOfStockCount > 0
    ): ?>


    <div class="alert alert-danger">


        <div class="alert-left">


            <div class="alert-icon">
                ⛔
            </div>


            <div>

                <strong>

                    <?php
                    echo number_format(
                        $outOfStockCount
                    );
                    ?>

                    product<?php
                    echo
                    $outOfStockCount != 1
                    ? 's are'
                    : ' is';
                    ?>

                    out of stock.

                </strong>

                Product inventory requires attention.

            </div>


        </div>


        <a href="/smart_inventory/inventory/index.php">
            View Inventory →
        </a>


    </div>


    <?php endif; ?>


    <?php if (
        $lowStockCount > 0
    ): ?>


    <div class="alert alert-warning">


        <div class="alert-left">


            <div class="alert-icon">
                ⚠️
            </div>


            <div>

                <strong>

                    <?php
                    echo number_format(
                        $lowStockCount
                    );
                    ?>

                    product<?php
                    echo
                    $lowStockCount != 1
                    ? 's are'
                    : ' is';
                    ?>

                    low in stock.

                </strong>

                These products reached their minimum stock level.

            </div>


        </div>


        <a href="/smart_inventory/inventory/index.php">
            Check Inventory →
        </a>


    </div>


    <?php endif; ?>


</div>


<?php endif; ?>


<!-- ========================================
     TODAY STATISTICS
======================================== -->

<section class="stats-grid">


<!-- TOTAL PRODUCTS -->

<div class="stat-card">

    <div class="stat-top">

        <span class="stat-label">
            TOTAL PRODUCTS
        </span>

        <div class="stat-icon icon-blue">
            <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt="">
        </div>

    </div>


    <h2>

        <?php
        echo number_format(
            $totalProducts
        );
        ?>

    </h2>

    <small>
        Active products
    </small>

</div>


<!-- TOTAL STOCK -->

<div class="stat-card">

    <div class="stat-top">

        <span class="stat-label">
            TOTAL STOCK
        </span>

        <div class="stat-icon icon-green">
            <img class="ui-icon" src="/smart_inventory/assets/icons/clipboard.svg" alt="">
        </div>

    </div>


    <h2>

        <?php
        echo number_format(
            $totalStock
        );
        ?>

    </h2>

    <small>
        Available units
    </small>

</div>


<!-- OUT OF STOCK -->

<div class="stat-card">

    <div class="stat-top">

        <span class="stat-label">
            OUT OF STOCK
        </span>

        <div class="stat-icon icon-red">
            ⛔
        </div>

    </div>


    <h2>

        <?php
        echo number_format(
            $outOfStockCount
        );
        ?>

    </h2>

    <small>
        Unavailable products
    </small>

</div>


<!-- TODAY SALES -->

<div class="stat-card">

    <div class="stat-top">

        <span class="stat-label">
            TODAY'S SALES
        </span>

        <div class="stat-icon icon-purple">
            <img class="ui-icon" src="/smart_inventory/assets/icons/money.svg" alt="">
        </div>

    </div>


    <h2>

        ₱<?php

        echo number_format(
            $todaySales,
            2
        );

        ?>

    </h2>

    <small>
        Today's revenue
    </small>

</div>


<!-- TODAY PROFIT -->

<div class="stat-card">

    <div class="stat-top">

        <span class="stat-label">
            TODAY'S PROFIT
        </span>

        <div class="stat-icon icon-orange">
            <img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt="">
        </div>

    </div>


    <h2>

        ₱<?php

        echo number_format(
            $todayProfit,
            2
        );

        ?>

    </h2>

    <small>
        Today's profit
    </small>

</div>


</section>


<!-- ========================================
     BUSINESS ANALYTICS TITLE
======================================== -->

<div class="section-header">

    <h2>
        <img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt=""> Business Analytics
    </h2>

    <p>
        Overall sales performance and product trends.
    </p>

</div>


<!-- ========================================
     ANALYTICS SUMMARY
======================================== -->

<section class="analytics-summary">


<div class="analytics-card">

    <span>
        Total Sales Revenue
    </span>

    <h2>

        ₱<?php

        echo number_format(
            $totalSales,
            2
        );

        ?>

    </h2>

</div>


<div class="analytics-card">

    <span>
        Total Profit
    </span>

    <h2>

        ₱<?php

        echo number_format(
            $totalProfit,
            2
        );

        ?>

    </h2>

</div>


<div class="analytics-card">

    <span>
        Total Transactions
    </span>

    <h2>

        <?php

        echo number_format(
            $totalTransactions
        );

        ?>

    </h2>

</div>


</section>


<!-- ========================================
     CHARTS
======================================== -->

<section class="chart-grid">


<!-- SALES TREND -->

<div class="chart-card">


    <div class="chart-header">

        <h3>
            <img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt=""> Sales Trend
        </h3>

        <p>
            Revenue generated during the last 7 days.
        </p>

    </div>


    <div class="chart-container">

        <canvas
            id="salesTrendChart"
        >
        </canvas>

    </div>


</div>


<!-- TOP SELLING PRODUCTS -->

<div class="chart-card">


    <div class="chart-header">

        <h3>
            <img class="ui-icon" src="/smart_inventory/assets/icons/award.svg" alt=""> Top Selling Products
        </h3>

        <p>
            Products ranked by quantity sold.
        </p>

    </div>


    <div class="chart-container">

        <canvas
            id="topProductsChart"
        >
        </canvas>

    </div>


</div>


</section>


<!-- ========================================
     INVENTORY AND RECENT PRODUCTS
======================================== -->

<section class="dashboard-grid">


<!-- ========================================
     LOW STOCK
======================================== -->

<div class="panel">


    <div class="panel-header">

        <div>

            <h3>
                ⚠️ Low Stock Products
            </h3>

            <p>
                Products requiring restocking
            </p>

        </div>

    </div>


    <div class="panel-body">


        <div class="table-responsive">


            <table>


                <thead>

                <tr>

                    <th>
                        Product
                    </th>

                    <th>
                        Stock
                    </th>

                    <th>
                        Minimum
                    </th>

                </tr>

                </thead>


                <tbody>


                <?php

                if (
                    $lowStockQuery &&
                    $lowStockQuery->num_rows > 0
                ):

                    while (
                        $product =
                        $lowStockQuery->fetch_assoc()
                    ):

                ?>


                <tr>


                    <td>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $product[
                                    'product_name'
                                ]
                            );

                            ?>

                        </strong>

                    </td>


                    <td>

                        <span
                            class="
                                badge
                                badge-danger
                            "
                        >

                            <?php

                            echo number_format(
                                $product[
                                    'stock_quantity'
                                ]
                            );

                            ?>

                        </span>

                    </td>


                    <td>

                        <?php

                        echo number_format(
                            $product[
                                'minimum_stock'
                            ]
                        );

                        ?>

                    </td>


                </tr>


                <?php

                    endwhile;

                else:

                ?>


                <tr>

                    <td
                        colspan="3"
                        class="empty"
                    >

                        ✅ No low-stock products.

                    </td>

                </tr>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


    </div>


    <div class="panel-footer">

        <a
            href="/smart_inventory/inventory/index.php"
            class="view-btn"
        >
            View Inventory →
        </a>

    </div>


</div>


<!-- ========================================
     RECENT PRODUCTS
======================================== -->

<div class="panel">


    <div class="panel-header">

        <div>

            <h3>
                <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt=""> Recent Products
            </h3>

            <p>
                Recently added active products
            </p>

        </div>

    </div>


    <div class="panel-body">


        <div class="table-responsive">


            <table>


                <thead>

                <tr>

                    <th>
                        Product
                    </th>

                    <th>
                        Price
                    </th>

                    <th>
                        Stock
                    </th>

                </tr>

                </thead>


                <tbody>


                <?php

                if (
                    $recentProductsQuery &&
                    $recentProductsQuery->num_rows > 0
                ):

                    while (
                        $product =
                        $recentProductsQuery->fetch_assoc()
                    ):

                ?>


                <tr>


                    <td>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $product[
                                    'product_name'
                                ]
                            );

                            ?>

                        </strong>

                    </td>


                    <td>

                        ₱<?php

                        echo number_format(
                            $product[
                                'selling_price'
                            ],
                            2
                        );

                        ?>

                    </td>


                    <td>


                        <?php

                        if (
                            $product[
                                'stock_quantity'
                            ] <= 0
                        ):

                        ?>


                        <span
                            class="
                                badge
                                badge-danger
                            "
                        >

                            Out of Stock

                        </span>


                        <?php

                        elseif (
                            $product[
                                'stock_quantity'
                            ] <=
                            $product[
                                'minimum_stock'
                            ]
                        ):

                        ?>


                        <span
                            class="
                                badge
                                badge-danger
                            "
                        >

                            <?php

                            echo number_format(
                                $product[
                                    'stock_quantity'
                                ]
                            );

                            ?>

                        </span>


                        <?php else: ?>


                        <span
                            class="
                                badge
                                badge-success
                            "
                        >

                            <?php

                            echo number_format(
                                $product[
                                    'stock_quantity'
                                ]
                            );

                            ?>

                        </span>


                        <?php endif; ?>


                    </td>


                </tr>


                <?php

                    endwhile;

                else:

                ?>


                <tr>

                    <td
                        colspan="3"
                        class="empty"
                    >
                        No products available.
                    </td>

                </tr>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


    </div>


    <div class="panel-footer">

        <a
            href="/smart_inventory/products/index.php"
            class="view-btn"
        >
            Manage Products →
        </a>

    </div>


</div>


<!-- ========================================
     RECENT ACTIVITY
======================================== -->

<div class="panel full">


    <div class="panel-header">

        <div>

            <h3>
                <img class="ui-icon" src="/smart_inventory/assets/icons/activity.svg" alt=""> Recent Activity
            </h3>

            <p>
                Latest actions performed in the system
            </p>

        </div>

    </div>


    <div class="panel-body">


        <div class="table-responsive">


            <table>


                <thead>

                <tr>

                    <th>
                        User
                    </th>

                    <th>
                        Action
                    </th>

                    <th>
                        Description
                    </th>

                    <th>
                        Date
                    </th>

                </tr>

                </thead>


                <tbody>


                <?php

                if (
                    $recentActivityQuery &&
                    $recentActivityQuery->num_rows > 0
                ):

                    while (
                        $activity =
                        $recentActivityQuery->fetch_assoc()
                    ):

                ?>


                <tr>


                    <td
                        class="activity-user"
                    >

                        <?php

                        echo htmlspecialchars(
                            $activity['name']
                            ?? 'Deleted User'
                        );

                        ?>

                    </td>


                    <td>

                        <span
                            class="activity-action"
                        >

                            <?php

                            echo htmlspecialchars(
                                $activity[
                                    'action'
                                ]
                            );

                            ?>

                        </span>

                    </td>


                    <td
                        class="description-cell"
                    >

                        <?php

                        echo htmlspecialchars(
                            $activity[
                                'description'
                            ]
                            ?? ''
                        );

                        ?>

                    </td>


                    <td
                        class="activity-date"
                    >

                        <?php

                        if (
                            !empty(
                                $activity[
                                    'created_at'
                                ]
                            )
                        ) {

                            echo date(
                                "M d, Y h:i A",
                                strtotime(
                                    $activity[
                                        'created_at'
                                    ]
                                )
                            );

                        }

                        ?>

                    </td>


                </tr>


                <?php

                    endwhile;

                else:

                ?>


                <tr>

                    <td
                        colspan="4"
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


    <div class="panel-footer">

        <a
            href="/smart_inventory/admin/activity_logs.php"
            class="view-btn"
        >
            View All Activity Logs →
        </a>

    </div>


</div>


</section>


</div>

</main>


</div>


<!-- ========================================
     JAVASCRIPT
======================================== -->

<script>

/* ========================================
   MOBILE SIDEBAR
======================================== */

function toggleSidebar() {

    document
        .getElementById(
            'sidebar'
        )
        .classList
        .toggle(
            'open'
        );


    document
        .getElementById(
            'overlay'
        )
        .classList
        .toggle(
            'show'
        );
}


function closeSidebar() {

    document
        .getElementById(
            'sidebar'
        )
        .classList
        .remove(
            'open'
        );


    document
        .getElementById(
            'overlay'
        )
        .classList
        .remove(
            'show'
        );
}


/* ========================================
   CLOSE MOBILE SIDEBAR
======================================== */

document
    .querySelectorAll(
        '.sidebar a'
    )
    .forEach(
        function(link) {

            link.addEventListener(
                'click',
                function() {

                    if (
                        window.innerWidth
                        <= 1050
                    ) {

                        closeSidebar();

                    }

                }
            );

        }
    );


/* ========================================
   CHART DATA FROM PHP
======================================== */

const salesLabels =
    <?php
    echo json_encode(
        $salesLabels
    );
    ?>;


const salesValues =
    <?php
    echo json_encode(
        $salesValues
    );
    ?>;


const productLabels =
    <?php
    echo json_encode(
        $productLabels
    );
    ?>;


const productValues =
    <?php
    echo json_encode(
        $productValues
    );
    ?>;


/* ========================================
   CHECK IF CHART.JS LOADED
======================================== */

if (
    typeof Chart !== 'undefined'
) {


    /* ====================================
       SALES TREND CHART
    ==================================== */

    const salesCanvas =
        document.getElementById(
            'salesTrendChart'
        );


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

                        borderColor:
                            '#111111',

                        backgroundColor:
                            'rgba(37,99,235,0.10)',

                        borderWidth:
                            3,

                        pointRadius:
                            4,

                        pointHoverRadius:
                            6,

                        pointBackgroundColor:
                            '#111111',

                        fill:
                            true,

                        tension:
                            0.35

                    }

                ]

            },


            options: {

                responsive:
                    true,

                maintainAspectRatio:
                    false,

                interaction: {

                    intersect:
                        false,

                    mode:
                        'index'

                },

                plugins: {

                    legend: {

                        display:
                            false

                    },

                    tooltip: {

                        callbacks: {

                            label:
                                function(
                                    context
                                ) {

                                    return (
                                        '₱' +
                                        Number(
                                            context.raw
                                        ).toLocaleString(
                                            undefined,
                                            {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2
                                            }
                                        )
                                    );

                                }

                        }

                    }

                },


                scales: {

                    x: {

                        grid: {

                            display:
                                false

                        }

                    },

                    y: {

                        beginAtZero:
                            true,

                        ticks: {

                            callback:
                                function(
                                    value
                                ) {

                                    return (
                                        '₱' +
                                        Number(
                                            value
                                        ).toLocaleString()
                                    );

                                }

                        }

                    }

                }

            }

        }
    );


    /* ====================================
       TOP PRODUCTS CHART
    ==================================== */

    const productsCanvas =
        document.getElementById(
            'topProductsChart'
        );


    new Chart(
        productsCanvas,
        {

            type:
                'bar',

            data: {

                labels:
                    productLabels,

                datasets: [

                    {

                        label:
                            'Quantity Sold',

                        data:
                            productValues,

                        /* Aurora palette: violet, mint, dark core, then soft blends */
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

                        borderWidth:
                            1,

                        borderRadius:
                            7,

                        borderSkipped:
                            false

                    }

                ]

            },


            options: {

                responsive:
                    true,

                maintainAspectRatio:
                    false,

                plugins: {

                    legend: {

                        display:
                            false

                    }

                },

                scales: {

                    x: {

                        grid: {

                            display:
                                false

                        }

                    },

                    y: {

                        beginAtZero:
                            true,

                        ticks: {

                            precision:
                                0

                        }

                    }

                }

            }

        }
    );

}

</script>


</body>

</html>