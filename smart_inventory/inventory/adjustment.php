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

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    die("Access denied. Admin only.");
}

require_once "../config/database.php";

$message = "";
$error = "";


/* =========================
   PROCESS ADJUSTMENT
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_id =
        intval($_POST['product_id'] ?? 0);

    $new_stock =
        intval($_POST['new_stock'] ?? -1);

    $reason =
        trim($_POST['reason'] ?? '');


    if ($product_id <= 0) {

        $error =
            "Please select a product.";

    } elseif ($new_stock < 0) {

        $error =
            "New stock cannot be negative.";

    } elseif ($reason === "") {

        $error =
            "Please enter a reason.";

    } else {

        try {

            $conn->begin_transaction();


            /* GET CURRENT STOCK */

            $stmt = $conn->prepare(
                "SELECT
                    stock_quantity
                 FROM products
                 WHERE id = ?
                 AND status = 'active'
                 FOR UPDATE"
            );

            $stmt->bind_param(
                "i",
                $product_id
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            $product =
                $result->fetch_assoc();


            if (!$product) {

                throw new Exception(
                    "Product not found."
                );
            }


            $previous_stock =
                intval(
                    $product['stock_quantity']
                );


            if (
                $previous_stock === $new_stock
            ) {

                throw new Exception(
                    "No stock change detected."
                );
            }


            $difference =
                $new_stock -
                $previous_stock;


            /* UPDATE PRODUCT */

            $stmt = $conn->prepare(
                "UPDATE products
                 SET stock_quantity = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "ii",
                $new_stock,
                $product_id
            );

            $stmt->execute();


            /* SAVE INVENTORY HISTORY */

            $transaction_type =
                "adjustment";

            $user_id =
                intval(
                    $_SESSION['user_id']
                );

            $quantity =
                abs($difference);


            $stmt = $conn->prepare(
                "INSERT INTO inventory_transactions
                (
                    product_id,
                    transaction_type,
                    quantity,
                    reason,
                    user_id,
                    previous_stock,
                    new_stock
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );


            $stmt->bind_param(
                "isisiii",
                $product_id,
                $transaction_type,
                $quantity,
                $reason,
                $user_id,
                $previous_stock,
                $new_stock
            );


           $stmt->execute();


/* =========================
   SAVE ACTIVITY LOG
========================= */

$action =
    "Stock Adjustment";

$description =
    "Adjusted product ID " .
    $product_id .
    " from stock " .
    $previous_stock .
    " to " .
    $new_stock .
    ". Reason: " .
    $reason;


$logStmt =
    $conn->prepare(
        "INSERT INTO activity_logs
        (
            user_id,
            action,
            description
        )
        VALUES (?, ?, ?)"
    );


$logStmt->bind_param(
    "iss",
    $user_id,
    $action,
    $description
);


$logStmt->execute();


/* SAVE EVERYTHING */

$conn->commit();


$message =
    "Stock adjusted successfully.";


        } catch (Throwable $e) {

            $conn->rollback();

            $error =
                $e->getMessage();

        }

    }

}


/* =========================
   GET PRODUCTS
========================= */

$products = $conn->query(
    "SELECT
        id,
        product_name,
        stock_quantity
     FROM products
     WHERE status = 'active'
     ORDER BY product_name ASC"
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
Stock Adjustment
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
    width: 650px;
    max-width: 90%;

    margin: 40px auto;

    background: white;

    padding: 30px;

    border-radius: 10px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

select,
input,
textarea {
    width: 100%;

    padding: 11px;

    border: 1px solid #ddd;

    border-radius: 5px;
}

textarea {
    height: 90px;
    resize: vertical;
}

button {
    background: #333333;

    color: white;

    border: none;

    padding: 12px 18px;

    border-radius: 5px;

    cursor: pointer;
}

.cancel {
    background: #707070;
    color: white;

    text-decoration: none;

    padding: 12px 18px;

    border-radius: 5px;
}

.success {
    background: #eeeeee;
    color: #222222;

    padding: 12px;

    border-radius: 5px;

    margin-bottom: 20px;
}

.error {
    background: #eeeeee;
    color: #111111;

    padding: 12px;

    border-radius: 5px;

    margin-bottom: 20px;
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
Dashboard
</a>

<a href="/smart_inventory/inventory/index.php">
Inventory
</a>

<a href="/smart_inventory/logout.php">
Logout
</a>

</div>

</div>


<div class="container">


<h1>
Stock Adjustment
</h1>

<br>


<?php if ($message !== ""): ?>

<div class="success">

<?php
echo htmlspecialchars(
    $message
);
?>

</div>

<?php endif; ?>


<?php if ($error !== ""): ?>

<div class="error">

<?php
echo htmlspecialchars(
    $error
);
?>

</div>

<?php endif; ?>


<form method="POST">


<div class="form-group">

<label>
Product
</label>

<select
    name="product_id"
    required
>

<option value="">
Select Product
</option>


<?php

while (
    $product =
    $products->fetch_assoc()
):

?>

<option
    value="<?php
        echo $product['id'];
    ?>"
>

<?php

echo htmlspecialchars(
    $product['product_name']
);

echo " | Current Stock: ";

echo $product[
    'stock_quantity'
];

?>

</option>


<?php endwhile; ?>


</select>

</div>


<div class="form-group">

<label>
New Actual Stock
</label>

<input
    type="number"
    name="new_stock"
    min="0"
    required
>

</div>


<div class="form-group">

<label>
Reason
</label>

<textarea
    name="reason"
    placeholder="Example: Physical inventory count correction"
    required
></textarea>

</div>


<button type="submit">
Save Adjustment
</button>


<a
    href="/smart_inventory/inventory/index.php"
    class="cancel"
>
Cancel
</a>


</form>


</div>


</body>

</html>