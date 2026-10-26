<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
require_once __DIR__ . '/../includes/role_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /smart_inventory/login.php");
    exit();
}

if (!in_array($_SESSION['role'] ?? 'user', ['admin', 'staff'], true)) {
    redirectToRoleDashboard($_SESSION['role'] ?? 'user');
}

require_once "../config/database.php";

$user_id = intval($_SESSION['user_id']);
$user_name = $_SESSION['name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'staff';
$dashboardUrl = ($user_role === 'admin')
    ? '/smart_inventory/admin/dashboard.php'
    : '/smart_inventory/staff/dashboard.php';


/* =========================================
   CREATE CART
========================================= */

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = "";
$error = "";
$success = "";


/* =========================================
   SUCCESS MESSAGE AFTER CHECKOUT
========================================= */

if (isset($_GET['success'])) {

    $transaction = $_GET['success'];

    $success =
        "Sale completed successfully. Transaction: " .
        $transaction;
}


/* =========================================
   ADD TO CART
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['add_to_cart'])
) {

    $product_id = intval(
        $_POST['product_id'] ?? 0
    );

    $quantity = intval(
        $_POST['quantity'] ?? 0
    );


    if ($product_id <= 0) {

        $error = "Please select a product.";

    } elseif ($quantity <= 0) {

        $error =
            "Quantity must be at least 1.";

    } else {

        try {

   $stmt = $conn->prepare(
    "SELECT
        id,
        product_name,
        cost_price,
        selling_price,
        stock_quantity
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

                $error =
                    "Product not found.";

            } else {

                $existingQuantity = 0;

                if (
                    isset(
                        $_SESSION['cart']
                        [$product_id]
                    )
                ) {

                    $existingQuantity =
                        $_SESSION['cart']
                        [$product_id]
                        ['quantity'];
                }


                $newQuantity =
                    $existingQuantity +
                    $quantity;


                if (
                    $newQuantity >
                    $product['stock_quantity']
                ) {

                    $error =
                        "Not enough stock. Available stock: " .
                        $product['stock_quantity'];

                } else {

                    $_SESSION['cart']
                    [$product_id] = [

                        'id' =>
                            intval(
                                $product['id']
                            ),

                        'product_name' =>
                            $product[
                                'product_name'
                            ],

                        'quantity' =>
                            $newQuantity

                    ];


                    $message =
                        "Product added to cart.";

                }

            }

        } catch (Throwable $e) {

            $error =
                $e->getMessage();

        }

    }

}


/* =========================================
   REMOVE FROM CART
========================================= */

if (isset($_GET['remove'])) {

    $remove_id =
        intval($_GET['remove']);

    if (
        isset(
            $_SESSION['cart']
            [$remove_id]
        )
    ) {

        unset(
            $_SESSION['cart']
            [$remove_id]
        );
    }


    header(
        "Location: sales.php"
    );

    exit();
}


/* =========================================
   CLEAR CART
========================================= */

if (isset($_GET['clear'])) {

    $_SESSION['cart'] = [];

    header(
        "Location: sales.php"
    );

    exit();
}


/* =========================================
   COMPLETE SALE
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['complete_sale'])
) {

    if (
        empty(
            $_SESSION['cart']
        )
    ) {

        $error =
            "Your cart is empty.";

    } else {

        /*
         * Generate transaction number
         */

        $transaction_number =
            "SALE-" .
            date("Ymd-His") .
            "-" .
            rand(100, 999);


        $user_id =
            intval(
                $_SESSION['user_id']
            );


        $total_amount = 0;
        $total_cost = 0;
        $total_profit = 0;


        try {

            /*
             * START TRANSACTION
             */

            $conn->begin_transaction();


            /*
             * =====================================
             * RECHECK PRODUCTS
             * =====================================
             */

            $saleProducts = [];


            foreach (
                $_SESSION['cart']
                as $cartItem
            ) {

                $product_id =
                    intval(
                        $cartItem['id']
                    );

                $quantity =
                    intval(
                        $cartItem['quantity']
                    );


                /*
                 * Lock product row
                 */

                $stmt =
                    $conn->prepare(
                        "SELECT
                            id,
                            product_name,
                            cost_price,
                            selling_price,
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
                        "A product in your cart no longer exists."
                    );
                }


                if (
                    $quantity >
                    $product[
                        'stock_quantity'
                    ]
                ) {

                    throw new Exception(
                        "Not enough stock for " .
                        $product[
                            'product_name'
                        ] .
                        ". Available: " .
                        $product[
                            'stock_quantity'
                        ]
                    );
                }


                /*
                 * Calculate values
                 */

                $cost_price =
                    floatval(
                        $product[
                            'cost_price'
                        ]
                    );


                $selling_price =
                    floatval(
                        $product[
                            'selling_price'
                        ]
                    );


                $subtotal =
                    $selling_price *
                    $quantity;


                $itemCost =
                    $cost_price *
                    $quantity;


                $profit =
                    $subtotal -
                    $itemCost;


                $total_amount +=
                    $subtotal;


                $total_cost +=
                    $itemCost;


                $total_profit +=
                    $profit;


                $saleProducts[] = [

                    'id' =>
                        $product_id,

                    'product_name' =>
                        $product[
                            'product_name'
                        ],

                    'quantity' =>
                        $quantity,

                    'cost_price' =>
                        $cost_price,

                    'selling_price' =>
                        $selling_price,

                    'subtotal' =>
                        $subtotal,

                    'profit' =>
                        $profit,

                    'previous_stock' =>
                        intval(
                            $product[
                                'stock_quantity'
                            ]
                        )

                ];

            }


            /*
             * =====================================
             * INSERT MAIN SALE
             * =====================================
             */

            $stmt =
                $conn->prepare(
                    "INSERT INTO sales
                    (
                        transaction_number,
                        user_id,
                        total_amount,
                        total_cost,
                        total_profit
                    )
                    VALUES (?, ?, ?, ?, ?)"
                );


            $stmt->bind_param(
                "siddd",
                $transaction_number,
                $user_id,
                $total_amount,
                $total_cost,
                $total_profit
            );


            $stmt->execute();


            /*
             * Get new sale ID
             */

            $sale_id =
                $conn->insert_id;


            /*
             * =====================================
             * PROCESS EVERY SALE ITEM
             * =====================================
             */

            foreach (
                $saleProducts
                as $item
            ) {

                $product_id =
                    $item['id'];

                $quantity =
                    $item['quantity'];

                $cost_price =
                    $item['cost_price'];

                $selling_price =
                    $item[
                        'selling_price'
                    ];

                $subtotal =
                    $item['subtotal'];

                $profit =
                    $item['profit'];

                $previous_stock =
                    $item[
                        'previous_stock'
                    ];

                $new_stock =
                    $previous_stock -
                    $quantity;


                /*
                 * =================================
                 * INSERT SALE ITEM
                 * =================================
                 */

                $stmt = $conn->prepare(
    "INSERT INTO sale_items
    (
        sale_id,
        product_id,
        quantity,
        cost,
        price,
        subtotal,
        profit
    )
    VALUES (?, ?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "iiidddd",
    $sale_id,
    $product_id,
    $quantity,
    $cost_price,
    $selling_price,
    $subtotal,
    $profit
);



                $stmt->execute();


                /*
                 * =================================
                 * UPDATE PRODUCT STOCK
                 * =================================
                 */

                $stmt =
                    $conn->prepare(
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


                /*
                 * =================================
                 * INVENTORY HISTORY
                 * =================================
                 */

                $transaction_type =
                    "stock_out";


                $reason =
                    "Sale: " .
                    $transaction_number;


                $stmt =
                    $conn->prepare(
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

            }


            /*
             * =====================================
             * SAVE EVERYTHING
             * =====================================
             */

            /* =========================
               SAVE SALES ACTIVITY LOG
            ========================= */

            $action = "Completed Sale";

            $description =
                "Completed transaction " .
                $transaction_number .
                " with total sales of ₱" .
                number_format($total_amount, 2) .
                " and profit of ₱" .
                number_format($total_profit, 2);

            $logStmt = $conn->prepare(
                "INSERT INTO activity_logs
                (user_id, action, description)
                VALUES (?, ?, ?)"
            );

            $logStmt->bind_param(
                "iss",
                $user_id,
                $action,
                $description
            );

            $logStmt->execute();

            /* Save sale, stock updates and log together */
            $conn->commit();

            /*
             * Clear cart
             */

            $_SESSION['cart'] = [];


            /*
             * Redirect
             */

            header(
                "Location: /smart_inventory/sales/receipt.php?id=" .
                intval($sale_id)
            );

            exit();


        } catch (Throwable $e) {

            /*
             * Cancel everything if
             * one part fails
             */

            $conn->rollback();


            $error =
                "Sale failed: " .
                $e->getMessage();

        }

    }

}


/* =========================================
   GET AVAILABLE PRODUCTS
========================================= */

$products = null;

try {

    $products =
        $conn->query(
            "SELECT
                id,
                product_name,
                selling_price,
                stock_quantity
             FROM products
             WHERE status = 'active'
             AND stock_quantity > 0
             ORDER BY product_name ASC"
        );

} catch (Throwable $e) {

    $error =
        $e->getMessage();
}


/* =========================================
   BUILD CART DISPLAY
========================================= */

$cartTotal = 0;
$cartItemCount = 0;

$cartDisplay = [];


foreach (
    $_SESSION['cart']
    as $cartItem
) {

    $product_id =
        intval(
            $cartItem['id']
        );


    try {

        $stmt =
            $conn->prepare(
                "SELECT
                    id,
                    product_name,
                    selling_price,
                    stock_quantity
                 FROM products
                 WHERE id = ?"
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


        if ($product) {

            $quantity =
                intval(
                    $cartItem[
                        'quantity'
                    ]
                );


            $subtotal =
                floatval(
                    $product[
                        'selling_price'
                    ]
                ) *
                $quantity;


            $cartTotal +=
                $subtotal;

            $cartItemCount +=
                $quantity;


            $cartDisplay[] = [

                'id' =>
                    $product['id'],

                'product_name' =>
                    $product[
                        'product_name'
                    ],

                'selling_price' =>
                    $product[
                        'selling_price'
                    ],

                'quantity' =>
                    $quantity,

                'subtotal' =>
                    $subtotal,

                'stock_quantity' =>
                    $product[
                        'stock_quantity'
                    ]

            ];

        }

    } catch (Throwable $e) {

        $error =
            $e->getMessage();

    }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales / POS | Smart Inventory</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:Arial,Helvetica,sans-serif}
:root{--dark:#111111;--dark2:#1b1b1b;--blue:#111111;--blue2:#000000;--green:#292929;--green2:#111111;--red:#111111;--bg:#f4f7fb;--white:#fff;--text:#111111;--muted:#707070;--border:#e3e3e3}
body{min-height:100vh;background:var(--bg);color:var(--text)}
.navbar{min-height:72px;background:var(--dark);color:#fff;padding:0 26px;display:flex;justify-content:space-between;align-items:center;gap:18px;box-shadow:0 3px 12px rgba(15,23,42,.18)}
.brand{display:flex;align-items:center;gap:11px}.brand-icon{width:43px;height:43px;border-radius:11px;display:grid;place-items:center;background:linear-gradient(135deg,#111111,#555555);font-size:20px}.brand h2{font-size:16px}.brand small{display:block;color:#9a9a9a;font-size:10px;margin-top:3px}
.nav-right{display:flex;align-items:center;gap:12px}.user-box{text-align:right}.user-box strong{display:block;font-size:12px}.user-box span{font-size:10px;color:#9a9a9a;text-transform:capitalize}.nav-links{display:flex;align-items:center;gap:4px}.nav-links a{color:#d0d0d0;text-decoration:none;padding:9px 11px;border-radius:7px;font-size:12px;transition:.2s}.nav-links a:hover{background:var(--dark2);color:#fff}.nav-links .active{background:var(--blue);color:#fff}.nav-links .logout{color:#dddddd}
.container{max-width:1500px;margin:auto;padding:28px}.page-header{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:22px}.page-header h1{font-size:27px;margin-bottom:5px}.page-header p{color:var(--muted);font-size:13px}.back-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 13px;border:1px solid var(--border);border-radius:8px;background:#fff;color:var(--text);text-decoration:none;font-size:12px;font-weight:bold}.back-btn:hover{background:#f5f5f5;border-color:#bdbdbd}
.alert{padding:13px 15px;margin-bottom:16px;border-radius:9px;border:1px solid transparent;font-size:13px;display:flex;align-items:center;gap:9px}.alert-info{color:#111111;background:#f5f5f5;border-color:#dddddd}.alert-error{color:#111111;background:#f5f5f5;border-color:#dddddd}
.sales-grid{display:grid;grid-template-columns:minmax(300px,.8fr) minmax(0,1.7fr);gap:20px;align-items:start}.panel{background:#fff;border:1px solid var(--border);border-radius:14px;box-shadow:0 4px 16px rgba(15,23,42,.05);overflow:hidden}.panel-header{padding:18px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:10px}.panel-header h2{font-size:17px;margin-bottom:3px}.panel-header p{color:var(--muted);font-size:11px}.panel-body{padding:20px}
.form-group{margin-bottom:18px}.form-group label{display:block;margin-bottom:7px;font-size:12px;font-weight:bold;color:#333333}select,input{width:100%;padding:12px 13px;border:1px solid #d0d0d0;border-radius:8px;background:#fafafa;color:var(--text);outline:none;font-size:14px;transition:.2s}select:focus,input:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.product-preview{margin-bottom:18px;padding:13px;border:1px solid #eeeeee;background:#fafafa;border-radius:9px;display:none}.product-preview.show{display:block}.preview-row{display:flex;justify-content:space-between;gap:10px;font-size:12px;margin-bottom:7px}.preview-row:last-child{margin-bottom:0}.preview-label{color:var(--muted)}.preview-value{font-weight:bold}
.add-button{width:100%;border:0;border-radius:9px;padding:13px 16px;background:var(--blue);color:#fff;font-size:14px;font-weight:bold;cursor:pointer;transition:.2s;box-shadow:0 6px 14px rgba(37,99,235,.18)}.add-button:hover{background:var(--blue2);transform:translateY(-1px)}
.staff-note{margin-top:16px;padding:12px 13px;border-radius:8px;background:#fafafa;border:1px solid var(--border);color:var(--muted);font-size:11px;line-height:1.5}.cart-badge{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;border-radius:20px;color:#000000;background:#eeeeee;font-size:11px;font-weight:bold}
.table-responsive{width:100%;overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:620px}th{padding:12px 10px;text-align:left;background:#fafafa;color:var(--muted);border-bottom:1px solid var(--border);font-size:10px;text-transform:uppercase;letter-spacing:.4px}td{padding:14px 10px;border-bottom:1px solid #f3f3f3;font-size:13px;vertical-align:middle}tbody tr:hover{background:#fafafa}.product-name{font-weight:bold}.stock-text{display:block;color:var(--muted);font-size:10px;margin-top:3px}.remove{color:var(--red);text-decoration:none;font-weight:bold;font-size:11px}.remove:hover{text-decoration:underline}
.cart-bottom{padding:18px 20px 20px;border-top:1px solid var(--border);background:#fafafa}.total-row{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:17px}.total-label{color:var(--muted);font-size:13px;font-weight:bold}.total-amount{font-size:28px;font-weight:bold;color:var(--green)}.cart-actions{display:grid;grid-template-columns:auto 1fr;gap:10px}.clear{display:flex;justify-content:center;align-items:center;padding:12px 15px;border-radius:8px;background:#eeeeee;color:#000000;text-decoration:none;font-size:12px;font-weight:bold}.clear:hover{background:#dddddd}.complete{width:100%;border:0;border-radius:8px;padding:13px 18px;background:var(--green);color:#fff;cursor:pointer;font-size:14px;font-weight:bold;transition:.2s;box-shadow:0 6px 15px rgba(22,163,74,.18)}.complete:hover{background:var(--green2);transform:translateY(-1px)}
.empty-cart{min-height:300px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:35px;color:var(--muted)}.empty-icon{width:70px;height:70px;display:grid;place-items:center;margin-bottom:15px;border-radius:50%;background:#f3f3f3;font-size:31px}.empty-cart h3{color:var(--text);margin-bottom:6px;font-size:16px}.empty-cart p{font-size:12px;max-width:280px;line-height:1.5}
@media(max-width:1000px){.sales-grid{grid-template-columns:1fr}}@media(max-width:850px){.navbar{padding:14px 16px;align-items:flex-start;flex-direction:column}.nav-right{width:100%;justify-content:space-between;align-items:flex-end}.nav-links{flex-wrap:wrap}.user-box{display:none}}@media(max-width:600px){.container{padding:20px 13px}.page-header{flex-direction:column;align-items:flex-start}.page-header h1{font-size:23px}.cart-actions{grid-template-columns:1fr}.total-row{align-items:flex-start;flex-direction:column;gap:5px}.total-amount{font-size:25px}}
</style>
<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
</head>
<body class="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin-layout' : ''; ?>">

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { include $_SERVER['DOCUMENT_ROOT'] . '/smart_inventory/includes/admin_sidebar.php'; } ?>
<header class="navbar">
    <div class="brand">
        <div class="brand-icon"><img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt=""></div>
        <div><h2>Smart Inventory</h2><small>Sales / Point of Sale</small></div>
    </div>
    <div class="nav-right">
        <div class="user-box">
            <strong><?php echo htmlspecialchars($user_name); ?></strong>
            <span><?php echo htmlspecialchars($user_role); ?></span>
        </div>
        <nav class="nav-links">
            <a href="<?php echo htmlspecialchars($dashboardUrl); ?>">Dashboard</a>
            <?php if ($user_role === 'admin'): ?>
                <a href="/smart_inventory/products/index.php">Products</a>
                <a href="/smart_inventory/inventory/index.php">Inventory</a>
                <a href="/smart_inventory/suppliers/index.php">Suppliers</a>
            <?php endif; ?>
            <a href="/smart_inventory/sales/sales.php" class="active">Sales / POS</a>
            <a href="/smart_inventory/sales/history.php">Sales History</a>
            <?php if ($user_role === 'staff'): ?>
                <a href="/smart_inventory/staff/account.php">Account</a>
            <?php endif; ?>
            <a href="/smart_inventory/logout.php" class="logout">Logout</a>
        </nav>
    </div>
</header>

<main class="container">
    <section class="page-header">
        <div>
            <h1>Sales / POS</h1>
            <p>Select products, review the cart, then complete the customer transaction.</p>
        </div>
        <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="back-btn">← Back to Dashboard</a>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert alert-info">✅ <span><?php echo htmlspecialchars($message); ?></span></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error">⚠️ <span><?php echo htmlspecialchars($error); ?></span></div>
    <?php endif; ?>

    <section class="sales-grid">
        <div class="panel">
            <div class="panel-header"><div><h2>➕ Add Product</h2><p>Add an available item to the cart</p></div></div>
            <div class="panel-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="product_id">Product</label>
                        <select name="product_id" id="product_id" required onchange="updateProductPreview()">
                            <option value="">Select Product</option>
                            <?php if ($products && $products->num_rows > 0): ?>
                                <?php while ($product = $products->fetch_assoc()): ?>
                                    <option
                                        value="<?php echo intval($product['id']); ?>"
                                        data-price="<?php echo htmlspecialchars($product['selling_price']); ?>"
                                        data-stock="<?php echo intval($product['stock_quantity']); ?>"
                                        data-name="<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES); ?>"
                                    >
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                        | ₱<?php echo number_format($product['selling_price'], 2); ?>
                                        | Stock: <?php echo number_format($product['stock_quantity']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="" disabled>No available products</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="product-preview" id="productPreview">
                        <div class="preview-row"><span class="preview-label">Selected</span><span class="preview-value" id="previewName">-</span></div>
                        <div class="preview-row"><span class="preview-label">Price</span><span class="preview-value" id="previewPrice">₱0.00</span></div>
                        <div class="preview-row"><span class="preview-label">Available Stock</span><span class="preview-value" id="previewStock">0</span></div>
                    </div>

                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                        <input type="number" name="quantity" id="quantity" value="1" min="1" required>
                    </div>

                    <button type="submit" name="add_to_cart" class="add-button"><img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt=""> Add to Cart</button>
                </form>

                <div class="staff-note">The system checks stock again before checkout, so staff cannot complete a sale with more units than are currently available.</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div><h2><img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt=""> Current Cart</h2><p>Review the products before checkout</p></div>
                <span class="cart-badge"><?php echo number_format($cartItemCount); ?></span>
            </div>

            <?php if (count($cartDisplay) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($cartDisplay as $item): ?>
                                <tr>
                                    <td><span class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></span><span class="stock-text">Available stock: <?php echo number_format($item['stock_quantity']); ?></span></td>
                                    <td>₱<?php echo number_format($item['selling_price'], 2); ?></td>
                                    <td><?php echo number_format($item['quantity']); ?></td>
                                    <td><strong>₱<?php echo number_format($item['subtotal'], 2); ?></strong></td>
                                    <td><a class="remove" href="/smart_inventory/sales/sales.php?remove=<?php echo intval($item['id']); ?>" onclick="return confirm('Remove this product from the cart?');">Remove</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cart-bottom">
                    <div class="total-row"><span class="total-label">CART TOTAL</span><span class="total-amount">₱<?php echo number_format($cartTotal, 2); ?></span></div>
                    <div class="cart-actions">
                        <a href="/smart_inventory/sales/sales.php?clear=1" class="clear" onclick="return confirm('Clear all products from the cart?');"><img class="ui-icon" src="/smart_inventory/assets/icons/trash.svg" alt=""> Clear Cart</a>
                        <form method="POST">
                            <button type="submit" name="complete_sale" class="complete" onclick="return confirm('Complete this sale and generate the receipt?');">✓ Complete Sale</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-cart">
                    <div class="empty-icon"><img class="ui-icon" src="/smart_inventory/assets/icons/cart.svg" alt=""></div>
                    <h3>Your cart is empty</h3>
                    <p>Choose an available product on the left and add it to begin a sale.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
function updateProductPreview() {
    const select = document.getElementById('product_id');
    const quantity = document.getElementById('quantity');
    const preview = document.getElementById('productPreview');
    const option = select.options[select.selectedIndex];

    if (!option || !option.value) {
        preview.classList.remove('show');
        quantity.removeAttribute('max');
        quantity.value = 1;
        return;
    }

    const price = Number(option.dataset.price || 0);
    const stock = Number(option.dataset.stock || 0);
    const name = option.dataset.name || '';

    document.getElementById('previewName').textContent = name;
    document.getElementById('previewPrice').textContent = '₱' + price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('previewStock').textContent = stock.toLocaleString();

    quantity.max = stock;
    if (Number(quantity.value) > stock) quantity.value = stock;
    preview.classList.add('show');
}
</script>
</body>
</html>
