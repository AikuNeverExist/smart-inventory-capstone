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

$search = $_GET['search'] ?? '';

if ($search !== '') {

    $sql = "SELECT 
                p.*,
                c.category_name,
                s.supplier_name
            FROM products p
            LEFT JOIN categories c
                ON p.category_id = c.id
            LEFT JOIN suppliers s
                ON p.supplier_id = s.id
            WHERE p.product_name LIKE ?
            ORDER BY p.id DESC";

    $stmt = $conn->prepare($sql);

    $searchTerm = "%" . $search . "%";

    $stmt->bind_param("s", $searchTerm);

    $stmt->execute();

    $products = $stmt->get_result();

} else {

    $sql = "SELECT 
                p.*,
                c.category_name,
                s.supplier_name
            FROM products p
            LEFT JOIN categories c
                ON p.category_id = c.id
            LEFT JOIN suppliers s
                ON p.supplier_id = s.id
            ORDER BY p.id DESC";

    $products = $conn->query($sql);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Products - Smart Inventory</title>

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

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .add-btn {
            background: #111111;
            color: white;
            padding: 11px 16px;
            border-radius: 6px;
            text-decoration: none;
        }

        .search-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 300px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .search-box button {
            padding: 10px 15px;
            border: none;
            background: #333333;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #e3e3e3;
        }

        .product-image {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 6px;
        }

        .no-image {
            width: 55px;
            height: 55px;
            background: #e3e3e3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #707070;
        }

        .edit {
            position: relative;
            background: #666666;
            color: white;
            padding: 5px 10px;
            border-radius: 50px;
            text-decoration: none;
        }

        .delete {
            background: #111111;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
        }
            .actions-cell {
            min-width: 135px;
            white-space: nowrap;
        }

        .actions-buttons {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
        }

        .edit,
        .delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .low-stock {
            color: #111111;
            font-weight: bold;
        }

        .in-stock {
            color: #292929;
            font-weight: bold;
        }

    </style>
    

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
<style>

body.admin-layout .search-box {
    background: #262740 !important;
    color: #ffffff !important;
}

body.admin-layout .search-box input {
    background: #202036 !important;
    color: #ffffff !important;
}

body.admin-layout .search-box input::placeholder {
    color: #b8b8c9 !important;
}

body.admin-layout table {
    background: #262740 !important;
    color: #ffffff !important;
}

body.admin-layout table thead,
body.admin-layout table thead tr,
body.admin-layout table th {
    background: #202036 !important;
    color: #ffffff !important;
}

body.admin-layout table tbody,
body.admin-layout table tbody tr,
body.admin-layout table td {
    background: #262740 !important;
    color: #ffffff !important;
}

body.admin-layout table tbody tr:hover,
body.admin-layout table tbody tr:hover td {
    background: #2d2e4d !important;
}

body.admin-layout .low-stock,
body.admin-layout .in-stock {
    color: #ffffff !important;
}

body.admin-layout .no-image {
    background: #202036 !important;
    color: #ffffff !important;
}

body.admin-layout table tbody tr td,
body.admin-layout table tbody tr td span,
body.admin-layout table tbody tr td strong {
    color: #ffffff !important;
}

</style>
</head>

<body class="admin-layout">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/smart_inventory/includes/admin_sidebar.php'; ?>


<div class="navbar">

    <strong>Smart Inventory System</strong>

    <div>
        <a href="../admin/dashboard.php">Dashboard</a>
        <a href="../logout.php">Logout</a>
    </div>

</div>

<div class="container">

    <div class="top">

        <h1 style="color: #ffffff !important;">Product Management</h1>

        <a  href="/smart_inventory/products/add.php" class="add-btn">
            + Add Product
        </a>

    </div>

    <div class="search-box">

        <form method="GET">

            <input
                type="text"
                name="search"
                placeholder="Search product..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <button type="submit">
                Search
            </button>

        </form>

    </div>

    <table>

        <thead>

        <tr>

            <th>Image</th>
            <th>Product</th>
            <th>Category</th>
            <th>Cost Price</th>
            <th>Selling Price</th>
            <th>Stock</th>
            <th>Status</th>
            <th>Actions</th>

        </tr>

        </thead>

        <tbody>

        <?php if ($products->num_rows > 0): ?>

            <?php while ($product = $products->fetch_assoc()): ?>

                <tr>

                    <td>

                        <?php if (!empty($product['image'])): ?>

                            <img
                                src="../assets/images/products/<?php echo htmlspecialchars($product['image']); ?>"
                                class="product-image"
                            >

                        <?php else: ?>

                            <div class="no-image">
                                No Image
                            </div>

                        <?php endif; ?>

                    </td>

                    <td>
                        <?php echo htmlspecialchars($product['product_name']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($product['category_name'] ?? 'None'); ?>
                    </td>

                    <td>
                        ₱<?php echo number_format($product['cost_price'], 2); ?>
                    </td>

                    <td>
                        ₱<?php echo number_format($product['selling_price'], 2); ?>
                    </td>

                    <td>
                        <?php echo $product['stock_quantity']; ?>
                    </td>

                    <td>

                        <?php if ($product['stock_quantity'] <= $product['minimum_stock']): ?>

                            <span class="low-stock">
                                Low Stock
                            </span>

                        <?php else: ?>

                            <span class="in-stock">
                                In Stock
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>

                        <a
                            href="edit.php?id=<?php echo $product['id']; ?>"
                            class="edit"
                        >
                            Edit
                        </a>

                        <a
                            href="delete.php?id=<?php echo $product['id']; ?>"
                            class="delete"
                            onclick="return confirm('Are you sure you want to delete this product?');"
                        >
                            Delete
                        </a>

                    </td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>

                <td colspan="8" style="text-align:center;">
                    No products found.
                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>

</body>

</html>