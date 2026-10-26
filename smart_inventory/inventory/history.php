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
    header("Location: ../login.php");
    exit();
}

require_once "../config/database.php";


// GET INVENTORY TRANSACTIONS

$sql = "
    SELECT
        inventory_transactions.id,
        inventory_transactions.product_id,
        inventory_transactions.transaction_type,
        inventory_transactions.quantity,
        inventory_transactions.reason,
        inventory_transactions.user_id,
        inventory_transactions.transaction_date,
        inventory_transactions.previous_stock,
        inventory_transactions.new_stock,
        products.product_name

    FROM inventory_transactions

    INNER JOIN products
        ON inventory_transactions.product_id = products.id

    ORDER BY
        inventory_transactions.transaction_date DESC,
        inventory_transactions.id DESC
";


$transactions = $conn->query($sql);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Inventory History</title>


<style>

* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}


body {

    margin: 0;

    background: #f4f6f9;

}


.navbar {

    background: #1b1b1b;

    color: white;

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.navbar a {

    color: white;

    text-decoration: none;

    margin-left: 15px;

}


.container {

    padding: 30px;

}


.panel {

    background: white;

    padding: 25px;

    border-radius: 10px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);

    overflow-x: auto;

}


h1 {

    margin-top: 0;

}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 900px;

}


th,
td {

    padding: 13px;

    border-bottom:
        1px solid #e5e7eb;

    text-align: left;

}


th {

    background: #f3f3f3;

    font-weight: bold;

}


.stock-in {

    color: #292929;

    font-weight: bold;

}


.stock-out {

    color: #111111;

    font-weight: bold;

}


.quantity-in {

    color: #292929;

    font-weight: bold;

}


.quantity-out {

    color: #111111;

    font-weight: bold;

}


.no-data {

    text-align: center;

    padding: 30px;

    color: #707070;

}


.back-button {

    display: inline-block;

    margin-bottom: 20px;

    padding: 10px 15px;

    background: #111111;

    color: white;

    text-decoration: none;

    border-radius: 5px;

}


</style>

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
</head>


<body class="admin-layout">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/smart_inventory/includes/admin_sidebar.php'; ?>



<!-- NAVIGATION -->

<div class="navbar">

<strong>
Smart Inventory System
</strong>


<div>

<a href="index.php">
Inventory
</a>

<a href="../products/index.php">
Products
</a>

<a href="../admin/dashboard.php">
Dashboard
</a>

<a href="../logout.php">
Logout
</a>

</div>

</div>


<!-- MAIN CONTENT -->

<div class="container">


<a
    href="index.php"
    class="back-button"
>
← Back to Inventory
</a>


<div class="panel">


<h1>
Inventory History
</h1>


<table>


<thead>

<tr>

<th>
Date
</th>

<th>
Product
</th>

<th>
Transaction
</th>

<th>
Previous Stock
</th>

<th>
Quantity
</th>

<th>
New Stock
</th>

<th>
Reason
</th>

<th>
User ID
</th>

</tr>

</thead>


<tbody>


<?php

if (
    $transactions &&
    $transactions->num_rows > 0
):


while (
    $row =
    $transactions->fetch_assoc()
):


    $type =
        $row['transaction_type'];


    if ($type === 'stock_in') {

        $typeLabel = "STOCK IN";

        $typeClass = "stock-in";

        $quantityClass = "quantity-in";

        $quantityDisplay =
            "+" . $row['quantity'];

    } elseif (
        $type === 'stock_out'
    ) {

        $typeLabel = "STOCK OUT";

        $typeClass = "stock-out";

        $quantityClass = "quantity-out";

        $quantityDisplay =
            "-" . $row['quantity'];

    } else {

        $typeLabel =
            strtoupper(
                str_replace(
                    "_",
                    " ",
                    $type
                )
            );

        $typeClass = "";

        $quantityClass = "";

        $quantityDisplay =
            $row['quantity'];
    }

?>


<tr>


<!-- DATE -->

<td>

<?php

echo date(
    "M d, Y h:i A",
    strtotime(
        $row['transaction_date']
    )
);

?>

</td>


<!-- PRODUCT -->

<td>

<?php

echo htmlspecialchars(
    $row['product_name']
);

?>

</td>


<!-- TRANSACTION -->

<td>

<span class="<?php
    echo $typeClass;
?>">

<?php

echo $typeLabel;

?>

</span>

</td>


<!-- PREVIOUS STOCK -->

<td>

<?php

echo $row['previous_stock'];

?>

</td>


<!-- QUANTITY -->

<td>

<span class="<?php
    echo $quantityClass;
?>">

<?php

echo $quantityDisplay;

?>

</span>

</td>


<!-- NEW STOCK -->

<td>

<?php

echo $row['new_stock'];

?>

</td>


<!-- REASON -->

<td>

<?php

if (
    !empty($row['reason'])
) {

    echo htmlspecialchars(
        $row['reason']
    );

} else {

    echo "-";

}

?>

</td>


<!-- USER -->

<td>

<?php

echo $row['user_id'];

?>

</td>


</tr>


<?php

endwhile;


else:

?>


<tr>

<td
    colspan="8"
    class="no-data"
>

No inventory transactions found.

</td>

</tr>


<?php endif; ?>


</tbody>

</table>


</div>


</div>


</body>

</html>