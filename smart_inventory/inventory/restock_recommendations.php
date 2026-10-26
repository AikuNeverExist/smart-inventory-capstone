<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();


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
        "Location: /smart_inventory/staff/dashboard.php"
    );

    exit();
}


require_once "../config/database.php";


/* =========================
   RESTOCK QUERY
========================= */

$query = $conn->query(
    "SELECT
        products.id,
        products.product_name,
        products.stock_quantity,
        products.minimum_stock,
        products.selling_price,

        COALESCE(
            SUM(
                CASE

                    WHEN sales.sale_date >=
                        DATE_SUB(
                            CURDATE(),
                            INTERVAL 30 DAY
                        )

                    THEN sale_items.quantity

                    ELSE 0

                END
            ),
            0
        ) AS sold_30_days

     FROM products

     LEFT JOIN sale_items
        ON products.id =
           sale_items.product_id

     LEFT JOIN sales
        ON sale_items.sale_id =
           sales.id

     WHERE products.status = 'active'

     GROUP BY
        products.id,
        products.product_name,
        products.stock_quantity,
        products.minimum_stock,
        products.selling_price

     HAVING
        products.stock_quantity
        <= products.minimum_stock

     ORDER BY
        products.stock_quantity ASC"
);


$recommendations = [];


/* =========================
   CALCULATE RECOMMENDATION
========================= */

if ($query) {

    while (
        $product =
        $query->fetch_assoc()
    ) {

        $currentStock =
            intval(
                $product[
                    'stock_quantity'
                ]
            );


        $minimumStock =
            intval(
                $product[
                    'minimum_stock'
                ]
            );


        $sold30 =
            intval(
                $product[
                    'sold_30_days'
                ]
            );


        /* Average units sold per day */

        $averageDailySales =
            $sold30 / 30;


        /* Estimated demand for 7 days */

        $sevenDayDemand =
            ceil(
                $averageDailySales * 7
            );


        /*
         * Target stock:
         * minimum stock
         * +
         * estimated 7-day demand
         */

        $targetStock =
            $minimumStock +
            $sevenDayDemand;


        /*
         * Make sure target is
         * at least minimum stock.
         */

        if (
            $targetStock <
            $minimumStock
        ) {

            $targetStock =
                $minimumStock;
        }


        $recommendedRestock =
            max(
                0,
                $targetStock -
                $currentStock
            );


        /* =========================
           URGENCY
        ========================= */

        if (
            $currentStock <= 0
        ) {

            $urgency =
                "Critical";

        } elseif (
            $currentStock <=
            ($minimumStock / 2)
        ) {

            $urgency =
                "High";

        } else {

            $urgency =
                "Low";
        }


        $recommendations[] = [

            'id' =>
                $product['id'],

            'product_name' =>
                $product[
                    'product_name'
                ],

            'current_stock' =>
                $currentStock,

            'minimum_stock' =>
                $minimumStock,

            'sold_30_days' =>
                $sold30,

            'average_daily_sales' =>
                $averageDailySales,

            'seven_day_demand' =>
                $sevenDayDemand,

            'target_stock' =>
                $targetStock,

            'recommended_restock' =>
                $recommendedRestock,

            'urgency' =>
                $urgency

        ];
    }
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
    Restock Recommendations
</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


body {

    background: #f4f7fb;

    color: #111111;
}


/* =========================
   NAVBAR
========================= */

.navbar {

    background: #111111;

    color: white;

    padding: 18px 28px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;
}


.navbar h2 {

    font-size: 18px;
}


.navbar div {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


.navbar a {

    color: #d0d0d0;

    text-decoration: none;

    padding: 9px 11px;

    border-radius: 7px;

    font-size: 13px;
}


.navbar a:hover {

    color: white;

    background: #1b1b1b;
}


/* =========================
   CONTAINER
========================= */

.container {

    max-width: 1500px;

    margin: auto;

    padding: 30px;
}


/* =========================
   HEADER
========================= */

.page-header {

    margin-bottom: 23px;
}


.page-header h1 {

    font-size: 27px;

    margin-bottom: 7px;
}


.page-header p {

    color: #707070;

    font-size: 14px;

    line-height: 1.6;
}


/* =========================
   INFO
========================= */

.info-box {

    background: #f5f5f5;

    color: #111111;

    border: 1px solid #dddddd;

    border-radius: 10px;

    padding: 15px 17px;

    margin-bottom: 20px;

    font-size: 13px;

    line-height: 1.6;
}


/* =========================
   SUMMARY
========================= */

.summary {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 17px;

    margin-bottom: 22px;
}


.summary-card {

    background: white;

    border: 1px solid #e3e3e3;

    border-radius: 13px;

    padding: 20px;

    box-shadow:
        0 4px 15px
        rgba(15,23,42,0.04);
}


.summary-card span {

    color: #707070;

    font-size: 11px;

    font-weight: bold;

    text-transform: uppercase;
}


.summary-card h2 {

    margin-top: 9px;

    font-size: 26px;
}


/* =========================
   PANEL
========================= */

.panel {

    background: white;

    border: 1px solid #e3e3e3;

    border-radius: 14px;

    overflow: hidden;

    box-shadow:
        0 4px 16px
        rgba(15,23,42,0.04);
}


.panel-header {

    padding: 18px 20px;

    border-bottom:
        1px solid #e3e3e3;
}


.panel-header h2 {

    font-size: 17px;

    margin-bottom: 4px;
}


.panel-header p {

    color: #707070;

    font-size: 12px;
}


/* =========================
   TABLE
========================= */

.table-responsive {

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 1050px;

    border-collapse: collapse;
}


th {

    background: #fafafa;

    color: #707070;

    padding: 13px 11px;

    text-align: left;

    font-size: 10px;

    text-transform: uppercase;

    border-bottom:
        1px solid #e3e3e3;
}


td {

    padding: 14px 11px;

    border-bottom:
        1px solid #f3f3f3;

    font-size: 12px;
}


tbody tr:hover {

    background: #fafafa;
}


.recommended {

    font-weight: bold;

    color: #111111;

    font-size: 14px;
}


/* =========================
   BADGES
========================= */

.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: bold;
}


.critical {

    background: #eeeeee;

    color: #111111;
}


.high {

    background: #ececec;

    color: #333333;
}


.low {

    background: #ededed;

    color: #444444;
}


/* =========================
   ACTION
========================= */

.stock-btn {

    display: inline-block;

    padding: 7px 10px;

    border-radius: 6px;

    background: #eeeeee;

    color: #222222;

    text-decoration: none;

    font-size: 11px;

    font-weight: bold;
}


.stock-btn:hover {

    background: #dddddd;
}


.empty {

    text-align: center;

    color: #707070;

    padding: 35px !important;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 800px) {

    .navbar {

        flex-direction: column;

        align-items: flex-start;
    }


    .summary {

        grid-template-columns: 1fr;
    }


    .container {

        padding: 20px 14px;
    }

}

</style>

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">

<style>
/* Compact Restock List: keeps the Stock In action visible on common screens. */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(192,108,235,.55) rgba(255,255,255,.05);
}

.table-responsive table {
    width: 100% !important;
    min-width: 900px !important;
    table-layout: fixed !important;
}

.table-responsive th,
.table-responsive td {
    padding: 10px 7px !important;
    font-size: 10.5px !important;
    line-height: 1.25 !important;
    white-space: normal !important;
    word-break: normal !important;
}

.table-responsive th {
    font-size: 9px !important;
}

.table-responsive th:nth-child(1),
.table-responsive td:nth-child(1) { width: 18%; }
.table-responsive th:nth-child(2),
.table-responsive td:nth-child(2) { width: 7%; text-align:center; }
.table-responsive th:nth-child(3),
.table-responsive td:nth-child(3) { width: 7%; text-align:center; }
.table-responsive th:nth-child(4),
.table-responsive td:nth-child(4) { width: 9%; text-align:center; }
.table-responsive th:nth-child(5),
.table-responsive td:nth-child(5) { width: 9%; text-align:center; }
.table-responsive th:nth-child(6),
.table-responsive td:nth-child(6) { width: 9%; text-align:center; }
.table-responsive th:nth-child(7),
.table-responsive td:nth-child(7) { width: 8%; text-align:center; }
.table-responsive th:nth-child(8),
.table-responsive td:nth-child(8) { width: 11%; text-align:center; }
.table-responsive th:nth-child(9),
.table-responsive td:nth-child(9) { width: 9%; text-align:center; }
.table-responsive th:nth-child(10),
.table-responsive td:nth-child(10) {
    width: 13%;
    text-align:center;
    position: sticky;
    right: 0;
    z-index: 2;
    background: #202036 !important;
    box-shadow: -10px 0 18px rgba(8,10,20,.16);
}

.table-responsive th:nth-child(10) {
    z-index: 4;
    background: #262740 !important;
}

.stock-btn {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    min-width: 76px;
    padding: 7px 8px !important;
    font-size: 10px !important;
    white-space: nowrap !important;
}

.recommended {
    font-size: 12px !important;
}

@media (max-width: 1250px) {
    .table-responsive table {
        min-width: 820px !important;
    }
    .table-responsive th:nth-child(5),
    .table-responsive td:nth-child(5),
    .table-responsive th:nth-child(7),
    .table-responsive td:nth-child(7) {
        display: none;
    }
}

@media (max-width: 850px) {
    .table-responsive table {
        min-width: 720px !important;
    }
    .table-responsive th:nth-child(4),
    .table-responsive td:nth-child(4) {
        display: none;
    }
}
</style>
</head>


<body class="admin-layout">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/smart_inventory/includes/admin_sidebar.php'; ?>



<!-- =========================
     NAVBAR
========================= -->

<header class="navbar">


    <h2>
        <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt=""> Smart Inventory
    </h2>


    <div>


        <a
            href="/smart_inventory/admin/dashboard.php"
        >
            Dashboard
        </a>


        <a
            href="/smart_inventory/inventory/index.php"
        >
            Inventory
        </a>


        <a
            href="/smart_inventory/analytics/index.php"
        >
            Analytics
        </a>


        <a
            href="/smart_inventory/logout.php"
        >
            Logout
        </a>


    </div>


</header>


<main class="container">


<!-- =========================
     PAGE HEADER
========================= -->

<div class="page-header">


    <h1>
        <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt=""> Smart Restock Recommendations
    </h1>


    <p>
        The system analyzes the last 30 days
        of sales and estimates the stock
        needed for the next 7 days.
    </p>


</div>


<div class="info-box">

    <strong>
        How it works:
    </strong>

    The recommendation uses your product's
    minimum stock level together with its
    recent sales activity.

    Products that sell faster will receive
    a higher recommended restock quantity.

</div>


<!-- =========================
     SUMMARY
========================= -->

<?php

$totalRecommendedUnits = 0;

$criticalCount = 0;


foreach (
    $recommendations
    as $item
) {

    $totalRecommendedUnits +=
        $item[
            'recommended_restock'
        ];


    if (
        $item['urgency']
        === 'Critical'
    ) {

        $criticalCount++;
    }
}

?>


<section class="summary">


<div class="summary-card">

    <span>
        Products to Restock
    </span>

    <h2>

        <?php

        echo number_format(
            count(
                $recommendations
            )
        );

        ?>

    </h2>

</div>


<div class="summary-card">

    <span>
        Recommended Units
    </span>

    <h2>

        <?php

        echo number_format(
            $totalRecommendedUnits
        );

        ?>

    </h2>

</div>


<div class="summary-card">

    <span>
        Critical Products
    </span>

    <h2>

        <?php

        echo number_format(
            $criticalCount
        );

        ?>

    </h2>

</div>


</section>


<!-- =========================
     TABLE
========================= -->

<section class="panel">


<div class="panel-header">

    <h2>
        Restock List
    </h2>

    <p>
        Recommendations based on stock
        level and sales activity.
    </p>

</div>


<div class="table-responsive">


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
        Sold in 30 Days
    </th>

    <th>
        Avg. Daily Sales
    </th>

    <th>
        Est. 7-Day Demand
    </th>

    <th>
        Target Stock
    </th>

    <th>
        Recommended Restock
    </th>

    <th>
        Priority
    </th>

    <th>
        Action
    </th>

</tr>

</thead>


<tbody>


<?php if (
    count(
        $recommendations
    ) > 0
): ?>


<?php foreach (
    $recommendations
    as $item
): ?>


<tr>


<td>

    <strong>

        <?php

        echo htmlspecialchars(
            $item[
                'product_name'
            ]
        );

        ?>

    </strong>

</td>


<td>

    <?php

    echo number_format(
        $item[
            'current_stock'
        ]
    );

    ?>

</td>


<td>

    <?php

    echo number_format(
        $item[
            'minimum_stock'
        ]
    );

    ?>

</td>


<td>

    <?php

    echo number_format(
        $item[
            'sold_30_days'
        ]
    );

    ?>

</td>


<td>

    <?php

    echo number_format(
        $item[
            'average_daily_sales'
        ],
        2
    );

    ?>

</td>


<td>

    <?php

    echo number_format(
        $item[
            'seven_day_demand'
        ]
    );

    ?>

</td>


<td>

    <?php

    echo number_format(
        $item[
            'target_stock'
        ]
    );

    ?>

</td>


<td class="recommended">

    +<?php

    echo number_format(
        $item[
            'recommended_restock'
        ]
    );

    ?>

</td>


<td>


<?php

$urgencyClass =
    strtolower(
        $item['urgency']
    );

?>


<span
    class="
        badge
        <?php
        echo htmlspecialchars(
            $urgencyClass
        );
        ?>
    "
>

    <?php

    echo htmlspecialchars(
        $item[
            'urgency'
        ]
    );

    ?>

</span>


</td>


<td>

    <a
        href="/smart_inventory/inventory/stock_in.php"
        class="stock-btn"
    >
        ➕ Stock In
    </a>

</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
    colspan="10"
    class="empty"
>

    ✅ No products currently
    require restocking.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</section>


</main>


</body>

</html>