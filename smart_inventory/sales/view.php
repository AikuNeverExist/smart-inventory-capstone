<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
require_once __DIR__ . '/../includes/role_helpers.php';


/* =========================
   CHECK LOGIN
========================= */

if (!isset($_SESSION['user_id'])) {

    header(
        "Location: /smart_inventory/login.php"
    );

    exit();
}


if (!in_array($_SESSION['role'] ?? 'user', ['admin', 'staff'], true)) {
    redirectToRoleDashboard($_SESSION['role'] ?? 'user');
}

require_once "../config/database.php";


/* =========================
   USER INFORMATION
========================= */

$user_id =
    intval(
        $_SESSION['user_id']
    );

$user_name =
    $_SESSION['name']
    ?? 'User';

$user_role =
    $_SESSION['role']
    ?? 'staff';


/* =========================
   DASHBOARD LINK
========================= */

if ($user_role === 'admin') {

    $dashboardUrl =
        "/smart_inventory/admin/dashboard.php";

} else {

    $dashboardUrl =
        "/smart_inventory/staff/dashboard.php";
}


/* =========================
   GET SALE ID
========================= */

$sale_id =
    isset($_GET['id'])
    ? intval($_GET['id'])
    : 0;


if ($sale_id <= 0) {

    die(
        "Invalid sale ID."
    );
}


/* =========================
   GET SALE WITH PERMISSION
========================= */

if ($user_role === 'admin') {

    /*
     * Admin can view any sale.
     */

    $stmt =
        $conn->prepare(
            "SELECT
                sales.id,
                sales.transaction_number,
                sales.user_id,
                sales.total_amount,
                sales.total_cost,
                sales.total_profit,
                sales.sale_date,
                users.name AS cashier_name

             FROM sales

             LEFT JOIN users
                ON sales.user_id = users.id

             WHERE sales.id = ?"
        );


    $stmt->bind_param(
        "i",
        $sale_id
    );

} else {

    /*
     * Staff can only view
     * their own sale.
     */

    $stmt =
        $conn->prepare(
            "SELECT
                sales.id,
                sales.transaction_number,
                sales.user_id,
                sales.total_amount,
                sales.sale_date,
                users.name AS cashier_name

             FROM sales

             LEFT JOIN users
                ON sales.user_id = users.id

             WHERE sales.id = ?
             AND sales.user_id = ?"
        );


    $stmt->bind_param(
        "ii",
        $sale_id,
        $user_id
    );
}


$stmt->execute();

$result =
    $stmt->get_result();

$sale =
    $result->fetch_assoc();


/* =========================
   ACCESS DENIED / NOT FOUND
========================= */

if (!$sale) {

    http_response_code(
        403
    );

    die(
        "Sale not found or you do not have permission to view this transaction."
    );
}


/* =========================
   GET SALE ITEMS
========================= */

if ($user_role === 'admin') {

    /*
     * Admin receives cost
     * and profit information.
     */

    $itemStmt =
        $conn->prepare(
            "SELECT
                sale_items.id,
                sale_items.product_id,
                sale_items.quantity,
                sale_items.price,
                sale_items.cost,
                sale_items.subtotal,
                sale_items.profit,
                products.product_name

             FROM sale_items

             INNER JOIN products
                ON sale_items.product_id =
                   products.id

             WHERE sale_items.sale_id = ?

             ORDER BY sale_items.id ASC"
        );

} else {

    /*
     * Staff does not need
     * cost and profit.
     */

    $itemStmt =
        $conn->prepare(
            "SELECT
                sale_items.id,
                sale_items.product_id,
                sale_items.quantity,
                sale_items.price,
                sale_items.subtotal,
                products.product_name

             FROM sale_items

             INNER JOIN products
                ON sale_items.product_id =
                   products.id

             WHERE sale_items.sale_id = ?

             ORDER BY sale_items.id ASC"
        );
}


$itemStmt->bind_param(
    "i",
    $sale_id
);

$itemStmt->execute();

$items =
    $itemStmt->get_result();

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
    Sale Details | Smart Inventory
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


:root {

    --dark: #111111;
    --dark-light: #1b1b1b;
    --primary: #111111;
    --green: #292929;
    --purple: #333333;
    --background: #f4f7fb;
    --white: #ffffff;
    --text: #111111;
    --muted: #707070;
    --border: #e3e3e3;
}


body {

    min-height: 100vh;

    background:
        var(--background);

    color:
        var(--text);
}


/* =========================
   NAVBAR
========================= */

.navbar {

    min-height: 72px;

    padding:
        0 26px;

    background:
        var(--dark);

    color: white;

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 20px;
}


.brand {

    display: flex;

    align-items: center;

    gap: 11px;
}


.brand-icon {

    width: 43px;
    height: 43px;

    border-radius: 11px;

    display: flex;

    justify-content: center;
    align-items: center;

    background:
        linear-gradient(
            135deg,
            #111111,
            #555555
        );

    font-size: 20px;
}


.brand h2 {

    font-size: 16px;
}


.brand small {

    display: block;

    color: #9a9a9a;

    font-size: 10px;

    margin-top: 3px;
}


.nav-right {

    display: flex;

    align-items: center;

    gap: 15px;
}


.user-info {

    text-align: right;
}


.user-info strong {

    display: block;

    font-size: 12px;
}


.user-info span {

    color: #9a9a9a;

    font-size: 10px;

    text-transform:
        capitalize;
}


.nav-links {

    display: flex;

    align-items: center;

    gap: 4px;
}


.nav-links a {

    color: #d0d0d0;

    text-decoration: none;

    padding:
        9px 11px;

    border-radius: 7px;

    font-size: 12px;
}


.nav-links a:hover {

    color: white;

    background:
        var(--dark-light);
}


.nav-links .logout {

    color: #dddddd;
}


/* =========================
   CONTAINER
========================= */

.container {

    width: 100%;

    max-width: 1450px;

    margin: auto;

    padding: 28px;
}


/* =========================
   PAGE HEADER
========================= */

.page-header {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 22px;
}


.page-header h1 {

    font-size: 27px;

    margin-bottom: 5px;
}


.page-header p {

    color:
        var(--muted);

    font-size: 13px;
}


.header-actions {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


.button {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding:
        10px 14px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 12px;

    font-weight: bold;
}


.back {

    color:
        var(--text);

    background: white;

    border:
        1px solid
        var(--border);
}


.receipt {

    color: white;

    background:
        var(--green);
}


/* =========================
   PANEL
========================= */

.panel {

    background: white;

    border:
        1px solid
        var(--border);

    border-radius: 14px;

    margin-bottom: 20px;

    overflow: hidden;

    box-shadow:
        0 4px 16px
        rgba(15,23,42,0.04);
}


.panel-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid
        var(--border);
}


.panel-header h2 {

    font-size: 17px;
}


.panel-body {

    padding: 20px;
}


/* =========================
   INFO CARDS
========================= */

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );

    gap: 15px;
}


.info-box {

    padding: 17px;

    border:
        1px solid
        var(--border);

    border-radius: 10px;

    background:
        #fafafa;
}


.info-box small {

    color:
        var(--muted);

    font-size: 11px;
}


.info-box strong {

    display: block;

    margin-top: 7px;

    font-size: 17px;

    overflow-wrap:
        anywhere;
}


.profit {

    color:
        var(--green);

    font-weight: bold;
}


/* =========================
   TABLE
========================= */

.table-responsive {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 720px;

    border-collapse:
        collapse;
}


th {

    padding: 13px 12px;

    text-align: left;

    background:
        #fafafa;

    color:
        var(--muted);

    font-size: 10px;

    text-transform:
        uppercase;

    border-bottom:
        1px solid
        var(--border);
}


td {

    padding: 14px 12px;

    border-bottom:
        1px solid
        #f3f3f3;

    font-size: 12px;
}


tbody tr:hover {

    background:
        #fafafa;
}


tfoot td {

    background:
        #fafafa;

    font-weight: bold;
}


.empty {

    text-align: center;

    color:
        var(--muted);

    padding:
        30px !important;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 900px) {

    .info-grid {

        grid-template-columns:
            repeat(
                2,
                1fr
            );
    }


    .navbar {

        padding:
            14px 16px;

        flex-direction: column;

        align-items:
            flex-start;
    }


    .nav-links {

        flex-wrap: wrap;
    }

}


@media (max-width: 600px) {

    .container {

        padding:
            20px 13px;
    }


    .info-grid {

        grid-template-columns:
            1fr;
    }


    .page-header {

        flex-direction:
            column;

        align-items:
            flex-start;
    }


    .user-info {

        display: none;
    }

}

</style>

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
</head>


<body class="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin-layout' : ''; ?>">

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { include $_SERVER['DOCUMENT_ROOT'] . '/smart_inventory/includes/admin_sidebar.php'; } ?>


<!-- =========================
     NAVBAR
========================= -->

<header class="navbar">


    <div class="brand">


        <div class="brand-icon">
            <img class="ui-icon" src="/smart_inventory/assets/icons/receipt.svg" alt="">
        </div>


        <div>

            <h2>
                Smart Inventory
            </h2>

            <small>
                Sale Details
            </small>

        </div>


    </div>


    <div class="nav-right">


        <div class="user-info">

            <strong>

                <?php

                echo htmlspecialchars(
                    $user_name
                );

                ?>

            </strong>


            <span>

                <?php

                echo htmlspecialchars(
                    $user_role
                );

                ?>

            </span>

        </div>


        <nav class="nav-links">


            <a
                href="<?php
                echo htmlspecialchars(
                    $dashboardUrl
                );
                ?>"
            >
                Dashboard
            </a>


            <a
                href="/smart_inventory/sales/sales.php"
            >
                Sales / POS
            </a>


            <a
                href="/smart_inventory/sales/history.php"
            >
                Sales History
            </a>


            <?php if (
                $user_role === 'admin'
            ): ?>


            <a
                href="/smart_inventory/analytics/index.php"
            >
                Analytics
            </a>


            <?php endif; ?>


            <?php if ($user_role === 'staff'): ?>
                <a href="/smart_inventory/staff/account.php">Account</a>
            <?php endif; ?>


            <a
                href="/smart_inventory/logout.php"
                class="logout"
            >
                Logout
            </a>


        </nav>


    </div>


</header>


<main class="container">


<!-- =========================
     PAGE HEADER
========================= -->

<section class="page-header">


    <div>

        <h1>
            Sale Details
        </h1>

        <p>
            Complete information about this transaction.
        </p>

    </div>


    <div class="header-actions">


        <a
            href="/smart_inventory/sales/history.php"
            class="button back"
        >
            ← Sales History
        </a>


        <a
            href="/smart_inventory/sales/receipt.php?id=<?php
            echo intval(
                $sale_id
            );
            ?>"
            class="button receipt"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/print.svg" alt=""> Receipt
        </a>


    </div>


</section>


<!-- =========================
     SALE INFORMATION
========================= -->

<section class="panel">


<div class="panel-header">

    <h2>
        Transaction Information
    </h2>

</div>


<div class="panel-body">


<div class="info-grid">


<div class="info-box">

    <small>
        Transaction Number
    </small>

    <strong>

        <?php

        echo htmlspecialchars(
            $sale[
                'transaction_number'
            ]
        );

        ?>

    </strong>

</div>


<div class="info-box">

    <small>
        Sale Date
    </small>

    <strong>

        <?php

        echo date(
            "M d, Y h:i A",
            strtotime(
                $sale[
                    'sale_date'
                ]
            )
        );

        ?>

    </strong>

</div>


<div class="info-box">

    <small>
        Cashier
    </small>

    <strong>

        <?php

        echo htmlspecialchars(
            $sale[
                'cashier_name'
            ]
            ?? 'Unknown User'
        );

        ?>

    </strong>

</div>


<div class="info-box">

    <small>
        Total Amount
    </small>

    <strong>

        ₱<?php

        echo number_format(
            $sale[
                'total_amount'
            ],
            2
        );

        ?>

    </strong>

</div>


<?php if (
    $user_role === 'admin'
): ?>


<div class="info-box">

    <small>
        Total Cost
    </small>

    <strong>

        ₱<?php

        echo number_format(
            $sale[
                'total_cost'
            ],
            2
        );

        ?>

    </strong>

</div>


<div class="info-box">

    <small>
        Total Profit
    </small>

    <strong class="profit">

        ₱<?php

        echo number_format(
            $sale[
                'total_profit'
            ],
            2
        );

        ?>

    </strong>

</div>


<?php endif; ?>


</div>


</div>


</section>


<!-- =========================
     PRODUCTS SOLD
========================= -->

<section class="panel">


<div class="panel-header">

    <h2>
        <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt=""> Products Sold
    </h2>

</div>


<div class="table-responsive">


<table>


<thead>


<tr>

    <th>
        Product
    </th>

    <th>
        Quantity
    </th>

    <th>
        Selling Price
    </th>

    <th>
        Subtotal
    </th>


    <?php if (
        $user_role === 'admin'
    ): ?>


    <th>
        Cost
    </th>

    <th>
        Profit
    </th>


    <?php endif; ?>


</tr>


</thead>


<tbody>


<?php if (
    $items &&
    $items->num_rows > 0
): ?>


<?php while (
    $item =
    $items->fetch_assoc()
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
            'quantity'
        ]
    );

    ?>

</td>


<td>

    ₱<?php

    echo number_format(
        $item[
            'price'
        ],
        2
    );

    ?>

</td>


<td>

    ₱<?php

    echo number_format(
        $item[
            'subtotal'
        ],
        2
    );

    ?>

</td>


<?php if (
    $user_role === 'admin'
): ?>


<td>

    ₱<?php

    echo number_format(
        $item[
            'cost'
        ],
        2
    );

    ?>

</td>


<td class="profit">

    ₱<?php

    echo number_format(
        $item[
            'profit'
        ],
        2
    );

    ?>

</td>


<?php endif; ?>


</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td
    colspan="<?php
    echo
        $user_role === 'admin'
        ? '6'
        : '4';
    ?>"
    class="empty"
>

    No sale items found.

</td>

</tr>


<?php endif; ?>


</tbody>


<tfoot>


<tr>


<td
    colspan="<?php
    echo
        $user_role === 'admin'
        ? '3'
        : '3';
    ?>"
>
    TOTAL
</td>


<td>

    ₱<?php

    echo number_format(
        $sale[
            'total_amount'
        ],
        2
    );

    ?>

</td>


<?php if (
    $user_role === 'admin'
): ?>


<td>
    ₱<?php
    echo number_format(
        $sale[
            'total_cost'
        ],
        2
    );
    ?>
</td>


<td class="profit">

    ₱<?php

    echo number_format(
        $sale[
            'total_profit'
        ],
        2
    );

    ?>

</td>


<?php endif; ?>


</tr>


</tfoot>


</table>


</div>


</section>


</main>


</body>

</html>