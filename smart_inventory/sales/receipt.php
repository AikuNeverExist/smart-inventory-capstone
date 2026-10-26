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


if (!in_array($_SESSION['role'] ?? 'user', ['admin', 'staff', 'user'], true)) {
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

$user_role =
    $_SESSION['role']
    ?? 'staff';


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
   SECURE SALE QUERY
========================= */

if ($user_role === 'admin') {

    /*
     * Admin can print
     * any receipt.
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
                ON sales.user_id =
                   users.id

             WHERE sales.id = ?"
        );


    $stmt->bind_param(
        "i",
        $sale_id
    );

} else {

    /*
     * Staff and normal Users can only print
     * receipts for their own transactions.
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
                ON sales.user_id =
                   users.id

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


$sale =
    $stmt
    ->get_result()
    ->fetch_assoc();


/* =========================
   ACCESS CHECK
========================= */

if (!$sale) {

    http_response_code(
        403
    );

    die(
        "Receipt not found or you do not have permission to view this transaction."
    );
}

$isCustomerOrder = str_starts_with((string)$sale['transaction_number'], 'ORDER-');


/* =========================
   GET SALE ITEMS
========================= */

$itemStmt =
    $conn->prepare(
        "SELECT
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
    Receipt - <?php
    echo htmlspecialchars(
        $sale[
            'transaction_number'
        ]
    );
    ?>
</title>


<style>

* {

    box-sizing:
        border-box;

    margin: 0;

    padding: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


body {

    background:
        #f3f3f3;

    color:
        #111111;

    padding:
        30px 15px;
}


/* =========================
   RECEIPT
========================= */

.receipt {

    width: 100%;

    max-width:
        680px;

    margin: auto;

    background:
        white;

    padding:
        35px;

    border-radius:
        14px;

    box-shadow:
        0 5px 25px
        rgba(
            0,
            0,
            0,
            0.10
        );
}


/* =========================
   HEADER
========================= */

.receipt-header {

    text-align:
        center;

    padding-bottom:
        20px;

    border-bottom:
        2px dashed
        #d0d0d0;
}


.logo {

    width:
        64px;

    height:
        64px;

    margin:
        0 auto 12px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #111111;

    color:
        white;

    border-radius:
        16px;

    font-size:
        29px;
}


.receipt-header h1 {

    font-size:
        23px;

    margin-bottom:
        5px;
}


.receipt-header p {

    color:
        #707070;

    font-size:
        12px;

    margin-top:
        3px;
}


/* =========================
   INFO
========================= */

.transaction-info {

    margin-top:
        20px;

    padding-bottom:
        20px;

    border-bottom:
        2px dashed
        #d0d0d0;
}


.info-row {

    display:
        flex;

    justify-content:
        space-between;

    gap:
        20px;

    margin-bottom:
        9px;

    font-size:
        13px;
}


.info-row:last-child {

    margin-bottom:
        0;
}


.info-label {

    color:
        #707070;
}


.info-value {

    font-weight:
        bold;

    text-align:
        right;
}


/* =========================
   ITEMS
========================= */

.items {

    margin-top:
        22px;

    overflow-x:
        auto;
}


table {

    width:
        100%;

    border-collapse:
        collapse;
}


th {

    padding:
        10px 7px;

    border-bottom:
        1px solid
        #e3e3e3;

    color:
        #707070;

    text-align:
        left;

    font-size:
        11px;

    text-transform:
        uppercase;
}


td {

    padding:
        12px 7px;

    border-bottom:
        1px solid
        #f3f3f3;

    font-size:
        12px;
}


.text-right {

    text-align:
        right;
}


.product-name {

    font-weight:
        bold;
}


/* =========================
   TOTAL
========================= */

.totals {

    width:
        100%;

    max-width:
        330px;

    margin:
        20px 0 0 auto;
}


.grand-total {

    display:
        flex;

    justify-content:
        space-between;

    gap:
        20px;

    padding-top:
        14px;

    border-top:
        2px solid
        #111111;

    font-size:
        20px;

    font-weight:
        bold;
}


/* =========================
   FOOTER
========================= */

.receipt-footer {

    margin-top:
        30px;

    padding-top:
        21px;

    text-align:
        center;

    border-top:
        2px dashed
        #d0d0d0;

    color:
        #707070;

    font-size:
        12px;

    line-height:
        1.6;
}


/* =========================
   ACTIONS
========================= */

.actions {

    width:
        100%;

    max-width:
        680px;

    margin:
        18px auto 0;

    display:
        flex;

    justify-content:
        center;

    flex-wrap:
        wrap;

    gap:
        9px;
}


.btn {

    display:
        inline-block;

    border:
        none;

    padding:
        11px 16px;

    border-radius:
        8px;

    text-decoration:
        none;

    font-size:
        13px;

    font-weight:
        bold;

    cursor:
        pointer;
}


.print-btn {

    color:
        white;

    background:
        #111111;
}


.history-btn {

    color:
        white;

    background:
        #111111;
}


.sale-btn {

    color:
        white;

    background:
        #292929;
}


/* =========================
   PRINT
========================= */

@media print {

    @page {

        margin:
            10mm;
    }


    body {

        background:
            white;

        padding:
            0;
    }


    .receipt {

        max-width:
            none;

        padding:
            0;

        border-radius:
            0;

        box-shadow:
            none;
    }


    .actions {

        display:
            none;
    }

}


/* =========================
   MOBILE
========================= */

@media (
    max-width: 600px
) {

    .receipt {

        padding:
            23px 17px;
    }


    .info-row {

        font-size:
            11px;
    }


    th,
    td {

        font-size:
            10px;

        padding:
            9px 5px;
    }

}

</style>

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
</head>


<body>


<div class="receipt">


<!-- =========================
     HEADER
========================= -->

<div class="receipt-header">


    <div class="logo">
        <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt="">
    </div>


    <h1>
        Smart Inventory
    </h1>


    <p>
        Inventory and Sales Management System
    </p>


    <p>
        SALES RECEIPT
    </p>


</div>


<!-- =========================
     TRANSACTION
========================= -->

<div class="transaction-info">


<div class="info-row">

    <span class="info-label">
        Transaction No.
    </span>


    <span class="info-value">

        <?php

        echo htmlspecialchars(
            $sale[
                'transaction_number'
            ]
        );

        ?>

    </span>

</div>


<div class="info-row">

    <span class="info-label">
        Date
    </span>


    <span class="info-value">

        <?php

        echo date(
            "F d, Y h:i A",
            strtotime(
                $sale[
                    'sale_date'
                ]
            )
        );

        ?>

    </span>

</div>


<div class="info-row">

    <span class="info-label">
        <?php echo $isCustomerOrder ? 'Customer' : 'Cashier'; ?>
    </span>


    <span class="info-value">

        <?php

        echo htmlspecialchars(
            $sale[
                'cashier_name'
            ]
            ?? 'Unknown User'
        );

        ?>

    </span>

</div>


</div>


<!-- =========================
     PRODUCTS
========================= -->

<div class="items">


<table>


<thead>

<tr>

    <th>
        Product
    </th>

    <th class="text-right">
        Price
    </th>

    <th class="text-right">
        Qty
    </th>

    <th class="text-right">
        Subtotal
    </th>

</tr>

</thead>


<tbody>


<?php if (
    $items->num_rows > 0
): ?>


<?php while (
    $item =
    $items->fetch_assoc()
): ?>


<tr>


<td class="product-name">

    <?php

    echo htmlspecialchars(
        $item[
            'product_name'
        ]
    );

    ?>

</td>


<td class="text-right">

    ₱<?php

    echo number_format(
        $item[
            'price'
        ],
        2
    );

    ?>

</td>


<td class="text-right">

    <?php

    echo number_format(
        $item[
            'quantity'
        ]
    );

    ?>

</td>


<td class="text-right">

    ₱<?php

    echo number_format(
        $item[
            'subtotal'
        ],
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
    style="
        text-align:center;
        color:#707070;
        padding:25px;
    "
>

    No items found.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


<!-- =========================
     GRAND TOTAL
========================= -->

<div class="totals">


<div class="grand-total">

    <span>
        TOTAL
    </span>


    <span>

        ₱<?php

        echo number_format(
            $sale[
                'total_amount'
            ],
            2
        );

        ?>

    </span>

</div>


</div>


<!-- =========================
     FOOTER
========================= -->

<div class="receipt-footer">


    <strong>
        Thank you for your purchase!
    </strong>

    <br>

    Please keep this receipt
    for your records.

    <br><br>

    Smart Inventory and Sales
    Management System


</div>


</div>


<!-- =========================
     ACTION BUTTONS
========================= -->

<div class="actions">


<button
    type="button"
    class="btn print-btn"
    onclick="window.print()"
>
    <img class="ui-icon" src="/smart_inventory/assets/icons/print.svg" alt=""> Print Receipt
</button>


<?php if ($user_role === 'user'): ?>
<a href="/smart_inventory/user/orders.php" class="btn history-btn">
    <img class="ui-icon" src="/smart_inventory/assets/icons/history.svg" alt=""> My Purchases
</a>
<a href="/smart_inventory/user/shop.php" class="btn sale-btn">
    <img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt=""> Back to Shop
</a>
<?php else: ?>
<a href="/smart_inventory/sales/history.php" class="btn history-btn">
    <img class="ui-icon" src="/smart_inventory/assets/icons/history.svg" alt=""> Sales History
</a>
<a href="/smart_inventory/sales/sales.php" class="btn sale-btn">
    <img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt=""> New Sale
</a>
<?php endif; ?>


</div>


</body>

</html>