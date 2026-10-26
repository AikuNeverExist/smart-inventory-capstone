<?php

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


/* =========================
   STAFF ONLY
========================= */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    redirectToRoleDashboard($_SESSION['role'] ?? 'user');
}


require_once "../config/database.php";
require_once __DIR__ . '/../includes/avatar_helper.php';


$user_id =
    intval($_SESSION['user_id']);

$staffName =
    $_SESSION['name']
    ?? 'Staff';

$staffAvatar = profileAvatarWebPath($user_id);
$staffInitial = strtoupper(mb_substr((string)$staffName, 0, 1));


/* =========================
   TOTAL PRODUCTS
========================= */

$productQuery = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE status = 'active'"
);

$productData =
    $productQuery->fetch_assoc();

$totalProducts =
    $productData['total'] ?? 0;


/* =========================
   LOW STOCK COUNT
========================= */

$lowStockQuery = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE stock_quantity > 0
     AND stock_quantity <= minimum_stock
     AND status = 'active'"
);

$lowStockData =
    $lowStockQuery->fetch_assoc();

$lowStockCount =
    $lowStockData['total'] ?? 0;


/* =========================
   STAFF TODAY SALES
========================= */

$todaySalesStmt = $conn->prepare(
    "SELECT
        COALESCE(
            SUM(total_amount),
            0
        ) AS total

     FROM sales

     WHERE user_id = ?
     AND DATE(sale_date) = CURDATE()"
);

$todaySalesStmt->bind_param(
    "i",
    $user_id
);

$todaySalesStmt->execute();

$todaySalesData =
    $todaySalesStmt
    ->get_result()
    ->fetch_assoc();

$todaySales =
    $todaySalesData['total'] ?? 0;


/* =========================
   STAFF TODAY TRANSACTIONS
========================= */

$transactionStmt = $conn->prepare(
    "SELECT COUNT(*) AS total

     FROM sales

     WHERE user_id = ?
     AND DATE(sale_date) = CURDATE()"
);

$transactionStmt->bind_param(
    "i",
    $user_id
);

$transactionStmt->execute();

$transactionData =
    $transactionStmt
    ->get_result()
    ->fetch_assoc();

$todayTransactions =
    $transactionData['total'] ?? 0;


/* =========================
   RECENT SALES OF STAFF
========================= */

$recentSalesStmt = $conn->prepare(
    "SELECT
        id,
        transaction_number,
        total_amount,
        sale_date

     FROM sales

     WHERE user_id = ?

     ORDER BY sale_date DESC

     LIMIT 5"
);

$recentSalesStmt->bind_param(
    "i",
    $user_id
);

$recentSalesStmt->execute();

$recentSales =
    $recentSalesStmt->get_result();

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
    Staff Dashboard | Smart Inventory
</title>


<style>

    .sale-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-block;
    padding: 7px 10px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 11px;
    font-weight: bold;
    transition: 0.2s;
}

.view-details-btn {
    background: #eeeeee;
    color: #000000;
}

.view-details-btn:hover {
    background: #dddddd;
}

.receipt-btn {
    background: #eeeeee;
    color: #222222;
}

.receipt-btn:hover {
    background: #dddddd;
}

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

    --green:
        #292929;

    --orange:
        #555555;

    --purple:
        #333333;

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
}


body {

    background:
        var(--background);

    color:
        var(--text);

    min-height: 100vh;
}


/* =========================
   NAVBAR
========================= */

.navbar {

    height: 70px;

    padding:
        0 28px;

    background:
        #111111;

    color: white;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,0.15);
}


.brand {

    display: flex;

    align-items: center;

    gap: 11px;
}


.brand-icon {

    width: 42px;
    height: 42px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 11px;

    font-size: 20px;

    background:
        linear-gradient(
            135deg,
            #111111,
            #555555
        );
}


.brand h2 {

    font-size: 17px;
}


.brand small {

    display: block;

    color:
        #9a9a9a;

    font-size: 10px;

    margin-top: 3px;
}


.nav-links {

    display: flex;

    align-items: center;

    gap: 5px;
}


.nav-links a {

    color:
        #d0d0d0;

    text-decoration: none;

    font-size: 13px;

    padding:
        9px 12px;

    border-radius: 7px;

    transition: 0.2s;
}


.nav-links a:hover {

    background:
        #1b1b1b;

    color: white;
}


.nav-links .active {

    color: white;

    background:
        #111111;
}


.logout {

    color:
        #dddddd
        !important;
}


/* =========================
   CONTAINER
========================= */

.container {

    max-width: 1450px;

    margin: auto;

    padding: 30px;
}


/* =========================
   WELCOME
========================= */

.welcome {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 27px;
}


.welcome h1 {

    font-size: 28px;

    margin-bottom: 7px;
}


.welcome p {

    color:
        var(--muted);

    font-size: 14px;
}


/* =========================
   NEW SALE BUTTON
========================= */

.new-sale-btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        13px 18px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #111111,
            #000000
        );

    border-radius: 9px;

    text-decoration: none;

    font-size: 14px;

    font-weight: bold;

    box-shadow:
        0 7px 18px
        rgba(37,99,235,0.22);

    transition: 0.2s;
}


.new-sale-btn:hover {

    transform:
        translateY(-2px);
}


/* =========================
   STATISTICS
========================= */

.cards {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 26px;
}


.card {

    background: white;

    padding: 21px;

    border-radius: 14px;

    border:
        1px solid
        var(--border);

    box-shadow:
        0 4px 16px
        rgba(15,23,42,0.04);

    transition: 0.2s;
}


.card:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 9px 24px
        rgba(15,23,42,0.08);
}


.card-header {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    margin-bottom: 17px;
}


.card-label {

    color:
        var(--muted);

    font-size: 11px;

    font-weight: bold;

    text-transform:
        uppercase;
}


.card-icon {

    width: 42px;
    height: 42px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 11px;

    font-size: 18px;
}


.blue {

    background:
        #eeeeee;

    color:
        #000000;
}


.green {

    background:
        #eeeeee;

    color:
        #111111;
}


.orange {

    background:
        #ececec;

    color:
        #555555;
}


.purple {

    background:
        #eeeeee;

    color:
        #333333;
}


.card h2 {

    font-size: 27px;

    margin-bottom: 4px;
}


.card small {

    color:
        #9a9a9a;

    font-size: 11px;
}


/* =========================
   MAIN GRID
========================= */

.dashboard-grid {

    display: grid;

    grid-template-columns:
        1.4fr 0.8fr;

    gap: 20px;
}


/* =========================
   PANEL
========================= */

.panel {

    background: white;

    border-radius: 14px;

    border:
        1px solid
        var(--border);

    box-shadow:
        0 4px 16px
        rgba(15,23,42,0.04);

    overflow: hidden;
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

    margin-bottom: 4px;
}


.panel-header p {

    font-size: 12px;

    color:
        var(--muted);
}


.panel-body {

    padding:
        0 20px 20px;
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

    border-collapse:
        collapse;

    min-width: 550px;
}


th {

    padding:
        13px 10px;

    text-align: left;

    color:
        var(--muted);

    background:
        #fafafa;

    font-size: 10px;

    text-transform:
        uppercase;

    border-bottom:
        1px solid
        var(--border);
}


td {

    padding:
        14px 10px;

    border-bottom:
        1px solid
        #f3f3f3;

    font-size: 13px;
}


tbody tr:hover {

    background:
        #fafafa;
}


.amount {

    font-weight: bold;

    color:
        #111111;
}


.view-btn {

    color:
        #111111;

    font-weight: bold;

    text-decoration: none;

    font-size: 12px;
}


/* =========================
   QUICK MENU
========================= */

.quick-menu {

    display: grid;

    gap: 11px;

    padding: 20px;
}


.quick-item {

    display: flex;

    align-items: center;

    gap: 14px;

    padding: 15px;

    border:
        1px solid
        var(--border);

    border-radius: 11px;

    text-decoration: none;

    color:
        var(--text);

    transition: 0.2s;
}


.quick-item:hover {

    border-color:
        #bdbdbd;

    background:
        #fafafa;

    transform:
        translateX(3px);
}


.quick-icon {

    width: 44px;
    height: 44px;

    display: flex;

    justify-content: center;
    align-items: center;

    border-radius: 11px;

    font-size: 20px;

    flex-shrink: 0;
}


.quick-item h3 {

    font-size: 14px;

    margin-bottom: 3px;
}


.quick-item p {

    color:
        var(--muted);

    font-size: 11px;
}


/* =========================
   EMPTY
========================= */

.empty {

    text-align: center;

    padding:
        30px !important;

    color:
        var(--muted);
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1000px) {

    .cards {

        grid-template-columns:
            repeat(2,1fr);
    }


    .dashboard-grid {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 700px) {

    .navbar {

        height: auto;

        padding: 15px;

        align-items:
            flex-start;

        flex-direction: column;

        gap: 12px;
    }


    .nav-links {

        flex-wrap: wrap;
    }


    .container {

        padding: 20px 14px;
    }


    .welcome {

        flex-direction:
            column;

        align-items:
            flex-start;
    }


    .cards {

        grid-template-columns: 1fr;
    }

}

.staff-account-link{display:inline-flex!important;align-items:center;gap:7px}.staff-nav-avatar{width:25px;height:25px;border-radius:50%;overflow:hidden;display:grid;place-items:center;flex:0 0 25px;background:linear-gradient(135deg,#c06ceb,#5df09d);color:#151629;font-size:10px;font-weight:900;border:1px solid rgba(255,255,255,.18)}.staff-nav-avatar img{width:100%;height:100%;object-fit:cover;filter:none!important}

</style>

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<style>.brand-mark{width:27px;height:27px;display:block;object-fit:contain}</style>
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
</head>


<body>




<!-- =========================
     NAVBAR
========================= -->

<header class="navbar">






    <div class="brand">


        <div class="brand-icon"><img class="brand-mark" src="/smart_inventory/assets/brand.svg" alt=""></div>


        <div>

            <h2>
                Smart Inventory
            </h2>

            <small>
                Staff Portal
            </small>

        </div>


    </div>


    <nav class="nav-links">


        <a
            href="/smart_inventory/staff/dashboard.php"
            class="active"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/home.svg" alt=""> Dashboard
        </a>


        <a
            href="/smart_inventory/sales/sales.php"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt=""> Sales / POS
        </a>


        <a
            href="/smart_inventory/sales/history.php"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/history.svg" alt=""> Sales History
        </a>


        <a
            href="/smart_inventory/staff/account.php"
            class="staff-account-link"
        >
            <span class="staff-nav-avatar">
                <?php if ($staffAvatar): ?>
                    <img src="<?php echo htmlspecialchars($staffAvatar); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo htmlspecialchars($staffInitial); ?>
                <?php endif; ?>
            </span>
            Account
        </a>


        <a
            href="/smart_inventory/logout.php"
            class="logout"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/logout.svg" alt=""> Logout
        </a>


    </nav>


</header>


<!-- =========================
     CONTENT
========================= -->

<main class="container">


    <!-- WELCOME -->

    <section class="welcome">


        <div>


            <h1>

                Welcome,

                <?php

                echo htmlspecialchars(
                    $staffName
                );

                ?> 

            </h1>


            <p>

                Here's your sales overview for today.

            </p>


        </div>


        <a
            href="/smart_inventory/sales/sales.php"
            class="new-sale-btn"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt=""> Start New Sale
        </a>


    </section>


    <!-- =========================
         CARDS
    ========================= -->

    <section class="cards">


        <!-- TODAY SALES -->

        <div class="card">


            <div class="card-header">


                <span class="card-label">
                    My Sales Today
                </span>


                <div class="card-icon green">
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
                Sales processed by you today
            </small>


        </div>


        <!-- TRANSACTIONS -->

        <div class="card">


            <div class="card-header">


                <span class="card-label">
                    My Transactions
                </span>


                <div class="card-icon purple">
                    <img class="ui-icon" src="/smart_inventory/assets/icons/receipt.svg" alt="">
                </div>


            </div>


            <h2>

                <?php

                echo number_format(
                    $todayTransactions
                );

                ?>

            </h2>


            <small>
                Transactions completed today
            </small>


        </div>


        <!-- PRODUCTS -->

        <div class="card">


            <div class="card-header">


                <span class="card-label">
                    Available Products
                </span>


                <div class="card-icon blue">
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
                Active products in the system
            </small>


        </div>


        <!-- LOW STOCK -->

        <div class="card">


            <div class="card-header">


                <span class="card-label">
                    Low Stock
                </span>


                <div class="card-icon orange">
                    ⚠️
                </div>


            </div>


            <h2>

                <?php

                echo number_format(
                    $lowStockCount
                );

                ?>

            </h2>


            <small>
                Products requiring attention
            </small>


        </div>


    </section>


    <!-- =========================
         LOWER CONTENT
    ========================= -->

    <section class="dashboard-grid">


        <!-- RECENT SALES -->

        <div class="panel">


            <div class="panel-header">

                <h2>
                    <img class="ui-icon" src="/smart_inventory/assets/icons/receipt.svg" alt=""> My Recent Sales
                </h2>

                <p>
                    Your latest completed transactions
                </p>

            </div>


            <div class="panel-body">


                <div class="table-responsive">


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
                                Amount
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                        </thead>


                        <tbody>


                        <?php

                        if (
                            $recentSales->num_rows > 0
                        ):

                            while (
                                $sale =
                                $recentSales->fetch_assoc()
                            ):

                        ?>


                        <tr>


                            <td>

                                <strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $sale[
                                            'transaction_number'
                                        ]
                                    );

                                    ?>

                                </strong>

                            </td>


                            <td>

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

                            </td>


                            <td class="amount">

                                ₱<?php

                                echo number_format(
                                    $sale[
                                        'total_amount'
                                    ],
                                    2
                                );

                                ?>

                            </td>


                            <td>

    <div class="sale-actions">

        <a
            href="/smart_inventory/sales/view.php?id=<?php echo intval($sale['id']); ?>"
            class="action-btn view-details-btn"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/eye.svg" alt=""> View
        </a>

        <a
            href="/smart_inventory/sales/receipt.php?id=<?php echo intval($sale['id']); ?>"
            class="action-btn receipt-btn"
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/print.svg" alt=""> Receipt
        </a>

    </div>

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

                                No sales transactions yet.

                            </td>

                        </tr>


                        <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </div>


        </div>


        <!-- QUICK ACTIONS -->

        <div class="panel">


            <div class="panel-header">

                <h2>
                    ⚡ Quick Actions
                </h2>

                <p>
                    Common staff functions
                </p>

            </div>


            <div class="quick-menu">


                <a
                    href="/smart_inventory/sales/sales.php"
                    class="quick-item"
                >


                    <div class="quick-icon blue">
                        <img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt="">
                    </div>


                    <div>

                        <h3>
                            New Sale
                        </h3>

                        <p>
                            Process a customer purchase
                        </p>

                    </div>


                </a>


                <a
                    href="/smart_inventory/sales/history.php"
                    class="quick-item"
                >


                    <div class="quick-icon purple">
                        <img class="ui-icon" src="/smart_inventory/assets/icons/history.svg" alt="">
                    </div>


                    <div>

                        <h3>
                            Sales History
                        </h3>

                        <p>
                            Review completed transactions
                        </p>

                    </div>


                </a>


                <a
                    href="/smart_inventory/logout.php"
                    class="quick-item"
                >


                    <div class="quick-icon orange">
                        <img class="ui-icon" src="/smart_inventory/assets/icons/logout.svg" alt="">
                    </div>


                    <div>

                        <h3>
                            Logout
                        </h3>

                        <p>
                            Securely exit your account
                        </p>

                    </div>


                </a>


            </div>


        </div>


    </section>


</main>


</body>

</html>