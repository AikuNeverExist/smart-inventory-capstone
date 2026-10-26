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

$message = "";


/* ==========================================
   PROCESS STOCK OUT
   ========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_id = intval($_POST['product_id']);
    $quantity   = intval($_POST['quantity']);
    $reason     = trim($_POST['reason']);

    /* VALIDATION */

    if ($product_id <= 0) {

        $message = "Please select a product.";

    } elseif ($quantity <= 0) {

        $message = "Quantity must be greater than zero.";

    } else {

        /* GET CURRENT STOCK */

        $stmt = $conn->prepare(
            "SELECT stock_quantity
             FROM products
             WHERE id = ?
             AND status = 'active'"
        );

        $stmt->bind_param(
            "i",
            $product_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $product = $result->fetch_assoc();


        if (!$product) {

            $message = "Product not found.";

        } else {

            /* CURRENT STOCK */

            $previous_stock =
                intval($product['stock_quantity']);


            /* CHECK AVAILABLE STOCK */

            if ($quantity > $previous_stock) {

                $message =
                    "Not enough stock available. " .
                    "Current stock: " .
                    $previous_stock;

            } else {

                /* CALCULATE NEW STOCK */

                $new_stock =
                    $previous_stock - $quantity;


                /* START DATABASE TRANSACTION */

                $conn->begin_transaction();


                try {

                    /* =====================================
                       UPDATE PRODUCT STOCK
                       ===================================== */

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


                    /* =====================================
                       RECORD INVENTORY TRANSACTION
                       ===================================== */

                    $transaction_type = "stock_out";

                    $user_id =
                        intval($_SESSION['user_id']);


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


                    /* SAVE */

                    $conn->commit();

                    /* =========================
   ACTIVITY LOG
========================= */

$action = "Stock Out";

$description =
    "Removed " .
    $quantity .
    " unit(s) from product ID " .
    $product_id .
    ". Previous stock: " .
    $previous_stock .
    ", New stock: " .
    $new_stock .
    ". Reason: " .
    $reason;

$logStmt = $conn->prepare(
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


                    /* RETURN TO INVENTORY */

                    header(
                        "Location: index.php"
                    );

                    exit();


                } catch (Exception $e) {

                    $conn->rollback();

                    $message =
                        "Error: " .
                        $e->getMessage();

                }

            }

        }

    }

}


/* ==========================================
   GET PRODUCTS
   ========================================== */

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

<title>Stock Out</title>


<style>

* {

    box-sizing: border-box;

    font-family: Arial, sans-serif;

}


body {

    margin: 0;

    background: #f4f6f9;

}


.container {

    width: 600px;

    max-width: 90%;

    margin: 50px auto;

    background: white;

    padding: 30px;

    border-radius: 10px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);

}


h1 {

    margin-bottom: 25px;

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

    font-size: 15px;

}


textarea {

    height: 90px;

    resize: vertical;

}


button {

    background: #111111;

    color: white;

    border: none;

    padding: 12px 20px;

    border-radius: 5px;

    cursor: pointer;

    font-size: 15px;

}


button:hover {

    background: #000000;

}


.cancel {

    background: #707070;

    color: white;

    text-decoration: none;

    padding: 12px 20px;

    border-radius: 5px;

    margin-left: 5px;

}


.cancel:hover {

    background: #505050;

}


.error {

    background: #eeeeee;

    color: #000000;

    padding: 12px;

    border-radius: 5px;

    margin-bottom: 20px;

}


.warning {

    margin-top: 8px;

    color: #111111;

    font-size: 14px;

}

</style>

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
</head>


<body class="admin-layout">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/smart_inventory/includes/admin_sidebar.php'; ?>



<div class="container">


<h1>
    - Stock Out
</h1>


<?php if ($message !== ""): ?>

<div class="error">

<?php

echo htmlspecialchars(
    $message
);

?>

</div>

<?php endif; ?>


<form method="POST">


<!-- PRODUCT -->

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

if (
    $products &&
    $products->num_rows > 0
):


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

echo " — Current Stock: ";

echo $product['stock_quantity'];

?>

</option>


<?php

endwhile;

else:

?>

<option value="">
No products available
</option>

<?php endif; ?>


</select>

</div>


<!-- QUANTITY -->

<div class="form-group">

<label>
Quantity
</label>


<input
    type="number"
    name="quantity"
    min="1"
    required
>


<div class="warning">

You cannot remove more stock
than the current inventory.

</div>

</div>


<!-- REASON -->

<div class="form-group">

<label>
Reason
</label>


<textarea
    name="reason"
    placeholder="Example: Damaged product"
></textarea>

</div>


<button type="submit">

    Save Stock Out

</button>


<a
    href="index.php"
    class="cancel"
>

    Cancel

</a>


</form>


</div>


</body>

</html>