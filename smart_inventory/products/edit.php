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


// Get product ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Invalid product ID.");
}


// Get product
$stmt = $conn->prepare(
    "SELECT * FROM products WHERE id = ?"
);

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Product not found.");
}

$product = $result->fetch_assoc();


// Get categories
$categories = $conn->query(
    "SELECT * FROM categories ORDER BY category_name ASC"
);


// Get suppliers
$currentSupplierId = intval($product['supplier_id'] ?? 0);
$suppliers = $conn->query(
    "SELECT DISTINCT s.*
     FROM suppliers s
     LEFT JOIN users u ON u.id = s.user_id
     WHERE u.role = 'supplier'
        OR s.id = " . $currentSupplierId . "
     ORDER BY s.supplier_name ASC"
);


// Update product
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);

    $category_id = !empty($_POST['category_id'])
        ? intval($_POST['category_id'])
        : null;

    $supplier_id = !empty($_POST['supplier_id'])
        ? intval($_POST['supplier_id'])
        : null;

    $cost_price = floatval($_POST['cost_price']);

    $selling_price = floatval($_POST['selling_price']);

    $stock_quantity = intval($_POST['stock_quantity']);

    $minimum_stock = intval($_POST['minimum_stock']);

    $oldImage = $product['image'];

    $imageName = $oldImage;


    // Upload new image
    if (!empty($_FILES['image']['name'])) {

        $imageName =
            time() . "_" .
            basename($_FILES['image']['name']);

        $target =
            "../assets/images/products/" .
            $imageName;

        if (move_uploaded_file(
            $_FILES['image']['tmp_name'],
            $target
        )) {

            // Delete old image
            if (
                !empty($oldImage) &&
                file_exists(
                    "../assets/images/products/" .
                    $oldImage
                )
            ) {

                unlink(
                    "../assets/images/products/" .
                    $oldImage
                );

            }

        } else {

            $imageName = $oldImage;

        }

    }


    // Update database
    $sql = "UPDATE products SET

            category_id = ?,
            supplier_id = ?,
            product_name = ?,
            description = ?,
            cost_price = ?,
            selling_price = ?,
            stock_quantity = ?,
            minimum_stock = ?,
            image = ?

            WHERE id = ?";


    $stmt = $conn->prepare($sql);


    $stmt->bind_param(
        "iissddiisi",
        $category_id,
        $supplier_id,
        $product_name,
        $description,
        $cost_price,
        $selling_price,
        $stock_quantity,
        $minimum_stock,
        $imageName,
        $id
    );


    if ($stmt->execute()) {

        header("Location: index.php");

        exit();

    } else {

        echo "Error updating product: " .
             $stmt->error;

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Edit Product</title>


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

    width: 700px;

    margin: 40px auto;

    background: white;

    padding: 30px;

    border-radius: 10px;

    box-shadow:
    0 3px 10px rgba(0,0,0,0.08);

}

h1 {

    margin-bottom: 25px;

}

.form-group {

    margin-bottom: 18px;

}

label {

    display: block;

    font-weight: bold;

    margin-bottom: 7px;

}

input,
textarea,
select {

    width: 100%;

    padding: 11px;

    border:
    1px solid #ddd;

    border-radius: 5px;

}

textarea {

    height: 100px;

}

.current-image {

    margin-top: 10px;

}

.current-image img {

    width: 100px;

    height: 100px;

    object-fit: cover;

    border-radius: 8px;

}

.btn {

    display: inline-block;

    padding: 11px 18px;

    border: none;

    border-radius: 5px;

    cursor: pointer;

    text-decoration: none;

}

.save {

    background: #111111;

    color: white;

}

.cancel {

    background: #707070;

    color: white;

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

<h1>Edit Product</h1>


<form method="POST"
      enctype="multipart/form-data">


<!-- PRODUCT NAME -->

<div class="form-group">
<label>
Product Name
</label>

<input
    type="text"
    name="product_name"
    value="<?php
        echo htmlspecialchars(
            $product['product_name']
        );
    ?>"
    required
>

</div>


<!-- DESCRIPTION -->

<div class="form-group">

<label>
Description
</label>

<textarea
    name="description"
><?php
echo htmlspecialchars(
    $product['description']
);
?></textarea>

</div>


<!-- CATEGORY -->

<div class="form-group">

<label>
Category
</label>

<select name="category_id">

<option value="">
Select Category
</option>


<?php while (
    $category =
    $categories->fetch_assoc()
): ?>

<option
    value="<?php
        echo $category['id'];
    ?>"
    <?php
    if (
        $category['id']
        ==
        $product['category_id']
    ) {
        echo "selected";
    }
    ?>
>

<?php
echo htmlspecialchars(
    $category['category_name']
);
?>

</option>

<?php endwhile; ?>

</select>

</div>


<!-- SUPPLIER -->

<div class="form-group">

<label>
Supplier
</label>

<select name="supplier_id">

<option value="">
Select Supplier
</option>


<?php while (
    $supplier =
    $suppliers->fetch_assoc()
): ?>

<option
    value="<?php
        echo $supplier['id'];
    ?>"
    <?php
    if (
        $supplier['id']
        ==
        $product['supplier_id']
    ) {
        echo "selected";
    }
    ?>
>

<?php
echo htmlspecialchars(
    $supplier['supplier_name']
);
?>

</option>

<?php endwhile; ?>

</select>

</div>


<!-- COST -->

<div class="form-group">

<label>
Cost Price
</label>

<input
    type="number"
    step="0.01"
    name="cost_price"
    value="<?php
        echo $product['cost_price'];
    ?>"
    required
>

</div>


<!-- SELLING PRICE -->

<div class="form-group">

<label>
Selling Price
</label>

<input
    type="number"
    step="0.01"
    name="selling_price"
    value="<?php
        echo $product['selling_price'];
    ?>"
    required
>

</div>


<!-- STOCK -->

<div class="form-group">

<label>
Stock Quantity
</label>

<input
    type="number"
    name="stock_quantity"
    min="0"
    value="<?php
        echo $product['stock_quantity'];
    ?>"
    required
>

</div>


<!-- MINIMUM STOCK -->

<div class="form-group">

<label>
Minimum Stock
</label>

<input
    type="number"
    name="minimum_stock"
    min="0"
    value="<?php
        echo $product['minimum_stock'];
    ?>"
    required
>

</div>


<!-- CURRENT IMAGE -->

<div class="form-group">

<label>
Current Image
</label>


<?php if (
    !empty($product['image'])
): ?>

<div class="current-image">

<img
    src="../assets/images/products/<?php
        echo htmlspecialchars(
            $product['image']
        );
    ?>"
>

</div>

<?php else: ?>

<p>
No image uploaded.
</p>

<?php endif; ?>

</div>


<!-- NEW IMAGE -->

<div class="form-group">

<label>
Change Image
</label>

<input
    type="file"
    name="image"
    accept="image/*"
>

</div>


<!-- BUTTONS -->

<button
    type="submit"
    class="btn save"
>
    Update Product
</button>


<a
    href="index.php"
    class="btn cancel"
>
    Cancel
</a>


</form>

</div>

</body>

</html>