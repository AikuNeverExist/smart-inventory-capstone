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

/* GET CATEGORIES */

$categories = $conn->query(
    "SELECT * FROM categories ORDER BY category_name ASC"
);

/* GET SUPPLIERS */

$suppliers = $conn->query(
    "SELECT s.*
     FROM suppliers s
     INNER JOIN users u ON u.id = s.user_id
     WHERE u.role = 'supplier'
     ORDER BY s.supplier_name ASC"
);


/* SAVE PRODUCT */

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

    $imageName = "";


    /* IMAGE UPLOAD */

    if (!empty($_FILES['image']['name'])) {

        $uploadDirectory =
            "../assets/images/products/";

        $imageName =
            time() . "_" .
            basename($_FILES['image']['name']);

        $targetFile =
            $uploadDirectory . $imageName;


        $allowedTypes = [
            "jpg",
            "jpeg",
            "png",
            "gif",
            "webp"
        ];

        $fileExtension =
            strtolower(
                pathinfo(
                    $_FILES['image']['name'],
                    PATHINFO_EXTENSION
                )
            );


        if (!in_array(
            $fileExtension,
            $allowedTypes
        )) {

            $message =
                "Invalid image type.";

        } else {

            if (!move_uploaded_file(
                $_FILES['image']['tmp_name'],
                $targetFile
            )) {

                $message =
                    "Failed to upload image.";

            }

        }

    }


    /* INSERT PRODUCT */

    if ($message === "") {

        $sql = "INSERT INTO products
        (
            category_id,
            supplier_id,
            product_name,
            description,
            cost_price,
            selling_price,
            stock_quantity,
            minimum_stock,
            image
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";


        $stmt = $conn->prepare($sql);


        $stmt->bind_param(
            "iissddiis",
            $category_id,
            $supplier_id,
            $product_name,
            $description,
            $cost_price,
            $selling_price,
            $stock_quantity,
            $minimum_stock,
            $imageName
        );


        if ($stmt->execute()) {

            header(
                "Location: index.php"
            );

            exit();

        } else {

            $message =
                "Database error: " .
                $stmt->error;

        }

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Add Product</title>


<style>

* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {

    margin: 0;

    background: #262740;

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

    border: 1px solid #ddd;

    border-radius: 5px;

}

textarea {

    height: 100px;

    resize: vertical;

}

button {

    padding: 11px 20px;

    border: none;

    border-radius: 5px;

    cursor: pointer;

}

.save {

    background: #111111;

    color: white;

}

.cancel {

    background: #707070;

    color: white;

    padding: 11px 18px;

    border-radius: 5px;

    text-decoration: none;

    margin-left: 5px;

}

.error {

    background: #eeeeee;

    color: #000000;

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



<div class="container">


<h1>
    Add Product
</h1>


<?php if ($message !== ""): ?>

<div class="error">

    <?php
    echo htmlspecialchars($message);
    ?>

</div>

<?php endif; ?>


<form
    method="POST"
    enctype="multipart/form-data"
>


<!-- PRODUCT NAME -->

<div class="form-group">

<label>
Product Name
</label>

<input
    type="text"
    name="product_name"
    placeholder="Enter product name"
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
    placeholder="Enter product description"
></textarea>

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
    name="cost_price"
    step="0.01"
    min="0"
    placeholder="0.00"
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
    name="selling_price"
    step="0.01"
    min="0"
    placeholder="0.00"
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
    placeholder="0"
    required
>

</div>


<!-- MINIMUM STOCK -->

<div class="form-group">

<label>
Minimum Stock Level
</label>

<input
    type="number"
    name="minimum_stock"
    min="0"
    value="5"
    required
>

</div>


<!-- IMAGE -->

<div class="form-group">

<label>
Product Image
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
    class="save"
>
    Save Product
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