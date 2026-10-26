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
    header("Location: /smart_inventory/login.php");
    exit();
}

require_once "../config/database.php";


/* =========================
   TOTAL STOCK
========================= */

$stockQuery = $conn->query(
    "SELECT COALESCE(SUM(stock_quantity), 0) AS total
     FROM products
     WHERE status = 'active'"
);

$totalStock = $stockQuery->fetch_assoc()['total'];


/* =========================
   LOW STOCK
========================= */

$lowStockQuery = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE stock_quantity <= minimum_stock
     AND status = 'active'"
);

$lowStock = $lowStockQuery->fetch_assoc()['total'];


/* =========================
   STOCK IN TODAY
========================= */

$inQuery = $conn->query(
    "SELECT COALESCE(SUM(quantity), 0) AS total
     FROM inventory_transactions
     WHERE transaction_type = 'stock_in'
     AND DATE(transaction_date) = CURDATE()"
);

$stockInToday = $inQuery->fetch_assoc()['total'];


/* =========================
   STOCK OUT TODAY
========================= */

$outQuery = $conn->query(
    "SELECT COALESCE(SUM(quantity), 0) AS total
     FROM inventory_transactions
     WHERE transaction_type = 'stock_out'
     AND DATE(transaction_date) = CURDATE()"
);

$stockOutToday = $outQuery->fetch_assoc()['total'];


/* =========================
   LOW STOCK PRODUCTS
========================= */

$result = $conn->query(
    "SELECT product_name,
            stock_quantity,
            minimum_stock
     FROM products
     WHERE stock_quantity <= minimum_stock
     AND status = 'active'
     ORDER BY stock_quantity ASC"
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

<title>
    Inventory Management
</title>


<style>

* {

    box-sizing: border-box;

    font-family: Arial, sans-serif;

}


body {

    margin: 0;

    background: #f4f6f9;

}


/* =========================
   NAVBAR
========================= */

.navbar {

    background: #1b1b1b;

    color: white;

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.navbar strong {

    font-size: 20px;

}


.navbar div {

    display: flex;

    align-items: center;

    gap: 8px;

}


.navbar a {

    color: white;

    text-decoration: none;

    padding: 9px 12px;

    border-radius: 6px;

    transition: 0.2s;

}


.navbar a:hover {

    background: #333333;

}


/* ACTIVE INVENTORY BUTTON */

.inventory-link {

    background: #292929;

}


.inventory-link:hover {

    background: #111111 !important;

}


/* =========================
   CONTAINER
========================= */

.container {

    padding: 30px;

}


/* =========================
   TITLE
========================= */

.container > h1 {

    margin-bottom: 25px;

}


/* =========================
   CARDS
========================= */

.cards {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;

    margin-bottom: 30px;

}


.card {

    background: white;

    padding: 25px;

    border-radius: 10px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);

}


.card h3 {

    color: #707070;

    margin-bottom: 10px;

    font-size: 15px;

}


.card h1 {

    font-size: 28px;

}


.low {

    color: #111111;

    font-weight: bold;

}


/* =========================
   ACTION BUTTONS
========================= */

.actions {

    display: flex;

    gap: 15px;

    margin-bottom: 30px;

    flex-wrap: wrap;

}


.button {

    padding: 13px 20px;

    border-radius: 6px;

    text-decoration: none;

    color: white;

    font-weight: bold;

}


.stock-in {

    background: #292929;

}


.stock-in:hover {

    background: #111111;

}


.stock-out {

    background: #111111;

}


.stock-out:hover {

    background: #000000;

}


.history {

    background: #111111;

}


.history:hover {

    background: #000000;

}


/* =========================
   PANEL
========================= */

.panel {

    background: white;

    padding: 25px;

    border-radius: 10px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);

}


.panel h2 {

    margin-bottom: 20px;

}


/* =========================
   TABLE
========================= */

table {

    width: 100%;

    border-collapse: collapse;

}


th,
td {

    padding: 12px;

    border-bottom:
        1px solid #eee;

    text-align: left;

}


th {

    background: #f3f3f3;

}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1000px) {

    .navbar {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }

    .navbar div {

        flex-wrap: wrap;

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



<!-- =========================
     NAVBAR
========================= -->

<div class="navbar">


    <strong>
        Smart Inventory System
    </strong>


    <div>


        <a href="/smart_inventory/admin/dashboard.php">
            <img class="ui-icon" src="/smart_inventory/assets/icons/home.svg" alt=""> Dashboard
        </a>


        <a href="/smart_inventory/products/index.php">
            <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt=""> Products
        </a>


        <a
            href="/smart_inventory/inventory/index.php"
            class="inventory-link"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/clipboard.svg" alt=""> Inventory Management
        </a>


        <a href="/smart_inventory/suppliers/index.php">
            <img class="ui-icon" src="/smart_inventory/assets/icons/truck.svg" alt=""> Suppliers
        </a>


        <a href="/smart_inventory/logout.php">
            <img class="ui-icon" src="/smart_inventory/assets/icons/logout.svg" alt=""> Logout
        </a>
        <a
    href="/smart_inventory/inventory/restock_recommendations.php"
    class="button"
    style="background:#111111;"
>
    <img class="ui-icon" src="/smart_inventory/assets/icons/bot.svg" alt=""> Restock Recommendations
</a>
<a
    href="/smart_inventory/inventory/restock_recommendations.php"
>
    <img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt=""> Restock Recommendations
</a>

    </div>

</div>


<!-- =========================
     MAIN CONTENT
========================= -->

<div class="container">


    <h1>
        Inventory Management
    </h1>


    <!-- =========================
         STATISTICS
    ========================= -->

    <div class="cards">


        <!-- TOTAL STOCK -->

        <div class="card">

            <h3>
                Total Stock
            </h3>

            <h1>

                <?php

                echo number_format(
                    $totalStock
                );

                ?>

            </h1>

        </div>


        <!-- LOW STOCK -->

        <div class="card">

            <h3>
                Low Stock Products
            </h3>

            <h1 class="low">

                <?php

                echo number_format(
                    $lowStock
                );

                ?>

            </h1>

        </div>


        <!-- STOCK IN -->

        <div class="card">

            <h3>
                Stock In Today
            </h3>

            <h1>

                <?php

                echo number_format(
                    $stockInToday
                );

                ?>

            </h1>

        </div>


        <!-- STOCK OUT -->

        <div class="card">

            <h3>
                Stock Out Today
            </h3>

            <h1>

                <?php

                echo number_format(
                    $stockOutToday
                );

                ?>

            </h1>

        </div>


    </div>


    <!-- =========================
         ACTION BUTTONS
    ========================= -->

    <div class="actions">

    <a
        href="/smart_inventory/inventory/stock_in.php"
        class="button stock-in"
    >
        + Stock In
    </a>

    <a
        href="/smart_inventory/inventory/stock_out.php"
        class="button stock-out"
    >
        - Stock Out
    </a>

    <a
        href="/smart_inventory/inventory/adjustment.php"
        class="button"
        style="background:#333333;"
    >
        ⚙ Stock Adjustment
    </a>

    <a
        href="/smart_inventory/inventory/history.php"
        class="button history"
    >
        <img class="ui-icon" src="/smart_inventory/assets/icons/clipboard.svg" alt=""> Inventory History
    </a>

</div>


    <!-- =========================
         LOW STOCK TABLE
    ========================= -->

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
                        Current Stock
                    </th>

                    <th>
                        Minimum Stock
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php

            if ($result->num_rows > 0):

                while (
                    $row =
                    $result->fetch_assoc()
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

                        echo $row[
                            'stock_quantity'
                        ];

                        ?>

                    </td>


                    <td>

                        <?php

                        echo $row[
                            'minimum_stock'
                        ];

                        ?>

                    </td>


                </tr>


            <?php

                endwhile;

            else:

            ?>


                <tr>

                    <td colspan="3">

                        No low-stock products.

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>


        </table>


    </div>


</div>


</body>

</html>