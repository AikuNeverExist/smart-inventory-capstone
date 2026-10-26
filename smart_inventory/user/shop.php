<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['user']);
require_once __DIR__ . '/../config/database.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['name'] ?? 'User';

if (!isset($_SESSION['user_cart'])) {
    $_SESSION['user_cart'] = [];
}
if (!isset($_SESSION['user_shop_csrf'])) {
    $_SESSION['user_shop_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

function validCsrf(): bool {
    $posted = $_POST['csrf_token'] ?? '';
    return isset($_SESSION['user_shop_csrf']) && is_string($posted)
        && hash_equals($_SESSION['user_shop_csrf'], $posted);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } elseif (isset($_POST['add_to_cart'])) {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        $stmt = $conn->prepare("SELECT id, product_name, selling_price, stock_quantity FROM products WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            $error = 'Product not found.';
        } else {
            $current = (int)($_SESSION['user_cart'][$productId]['quantity'] ?? 0);
            $newQty = $current + $quantity;
            if ($newQty > (int)$product['stock_quantity']) {
                $error = 'Not enough stock. Available: ' . (int)$product['stock_quantity'];
            } else {
                $_SESSION['user_cart'][$productId] = [
                    'id' => (int)$product['id'],
                    'product_name' => $product['product_name'],
                    'quantity' => $newQty,
                ];
                $message = $product['product_name'] . ' added to cart.';
            }
        }
    } elseif (isset($_POST['update_cart'])) {
        foreach (($_POST['qty'] ?? []) as $productId => $qty) {
            $productId = (int)$productId;
            $qty = (int)$qty;
            if ($qty <= 0) {
                unset($_SESSION['user_cart'][$productId]);
            } elseif (isset($_SESSION['user_cart'][$productId])) {
                $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ? AND status = 'active' LIMIT 1");
                $stmt->bind_param('i', $productId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if ($row && $qty <= (int)$row['stock_quantity']) {
                    $_SESSION['user_cart'][$productId]['quantity'] = $qty;
                }
            }
        }
        $message = 'Cart updated.';
    } elseif (isset($_POST['clear_cart'])) {
        $_SESSION['user_cart'] = [];
        $message = 'Cart cleared.';
    } elseif (isset($_POST['complete_purchase'])) {
        if (empty($_SESSION['user_cart'])) {
            $error = 'Your cart is empty.';
        } else {
            $transactionNumber = 'ORDER-' . date('Ymd-His') . '-' . random_int(100, 999);
            $totalAmount = 0.0;
            $totalCost = 0.0;
            $totalProfit = 0.0;

            try {
                $conn->begin_transaction();
                $saleProducts = [];

                foreach ($_SESSION['user_cart'] as $cartItem) {
                    $productId = (int)$cartItem['id'];
                    $quantity = (int)$cartItem['quantity'];

                    $stmt = $conn->prepare("SELECT id, product_name, cost_price, selling_price, stock_quantity FROM products WHERE id = ? AND status = 'active' FOR UPDATE");
                    $stmt->bind_param('i', $productId);
                    $stmt->execute();
                    $product = $stmt->get_result()->fetch_assoc();

                    if (!$product) {
                        throw new Exception('A product in your cart is no longer available.');
                    }
                    if ($quantity <= 0 || $quantity > (int)$product['stock_quantity']) {
                        throw new Exception('Not enough stock for ' . $product['product_name'] . '. Available: ' . (int)$product['stock_quantity']);
                    }

                    $cost = (float)$product['cost_price'];
                    $price = (float)$product['selling_price'];
                    $subtotal = $price * $quantity;
                    $profit = ($price - $cost) * $quantity;

                    $totalAmount += $subtotal;
                    $totalCost += $cost * $quantity;
                    $totalProfit += $profit;

                    $saleProducts[] = [
                        'id' => $productId,
                        'name' => $product['product_name'],
                        'quantity' => $quantity,
                        'cost' => $cost,
                        'price' => $price,
                        'subtotal' => $subtotal,
                        'profit' => $profit,
                        'previous_stock' => (int)$product['stock_quantity'],
                    ];
                }

                $stmt = $conn->prepare("INSERT INTO sales (transaction_number, user_id, total_amount, total_cost, total_profit) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('siddd', $transactionNumber, $userId, $totalAmount, $totalCost, $totalProfit);
                $stmt->execute();
                $saleId = (int)$conn->insert_id;

                foreach ($saleProducts as $item) {
                    $newStock = $item['previous_stock'] - $item['quantity'];

                    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, cost, price, subtotal, profit) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('iiidddd', $saleId, $item['id'], $item['quantity'], $item['cost'], $item['price'], $item['subtotal'], $item['profit']);
                    $stmt->execute();

                    $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                    $stmt->bind_param('ii', $newStock, $item['id']);
                    $stmt->execute();

                    $type = 'stock_out';
                    $reason = 'User purchase: ' . $transactionNumber;
                    $stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, reason, user_id, previous_stock, new_stock) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('isisiii', $item['id'], $type, $item['quantity'], $reason, $userId, $item['previous_stock'], $newStock);
                    $stmt->execute();
                }

                $action = 'Customer Purchase';
                $description = 'Completed ' . $transactionNumber . ' with total of ₱' . number_format($totalAmount, 2);
                $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
                $stmt->bind_param('iss', $userId, $action, $description);
                $stmt->execute();

                $conn->commit();
                $_SESSION['user_cart'] = [];
                header('Location: /smart_inventory/sales/receipt.php?id=' . $saleId);
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                $error = 'Purchase failed: ' . $e->getMessage();
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);

$categories = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");

$sql = "SELECT p.id, p.product_name, p.selling_price, p.stock_quantity, p.image, p.category_id, c.category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active' AND p.stock_quantity > 0";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND p.product_name LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}
if ($categoryId > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
    $types .= 'i';
}
$sql .= " ORDER BY p.product_name ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result();

$cartItems = [];
$cartTotal = 0.0;
foreach ($_SESSION['user_cart'] as $item) {
    $stmt = $conn->prepare("SELECT id, product_name, selling_price, stock_quantity FROM products WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param('i', $item['id']);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    if (!$p) continue;
    $qty = min((int)$item['quantity'], (int)$p['stock_quantity']);
    $subtotal = (float)$p['selling_price'] * $qty;
    $cartTotal += $subtotal;
    $cartItems[] = [
        'id' => (int)$p['id'],
        'name' => $p['product_name'],
        'price' => (float)$p['selling_price'],
        'stock' => (int)$p['stock_quantity'],
        'quantity' => $qty,
        'subtotal' => $subtotal,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shop | Smart Inventory</title>
<style>
*{box-sizing:border-box;font-family:Arial,sans-serif}body{margin:0;min-height:100vh;color:#fff;background:radial-gradient(circle at 12% 20%,rgba(192,108,235,.24),transparent 30%),radial-gradient(circle at 88% 22%,rgba(93,240,157,.18),transparent 30%),linear-gradient(135deg,#101223,#181a31,#101223)}
.nav{position:sticky;top:0;z-index:20;padding:15px 24px;background:rgba(32,32,54,.96);border-bottom:1px solid rgba(255,255,255,.08);display:flex;justify-content:space-between;gap:16px;align-items:center;backdrop-filter:blur(14px)}.nav strong{font-size:18px}.nav-links{display:flex;gap:8px;flex-wrap:wrap}.nav a{color:#fff;text-decoration:none;padding:9px 13px;border-radius:9px;background:#262740;border:1px solid #444667}.nav a.active{background:linear-gradient(135deg,rgba(192,108,235,.30),rgba(93,240,157,.18));border-color:rgba(255,255,255,.14)}
.wrap{max-width:1220px;margin:0 auto;padding:28px 20px 46px}.header{display:flex;justify-content:space-between;align-items:end;gap:18px;margin-bottom:18px}.eyebrow{color:#5df09d;font-size:11px;font-weight:900;letter-spacing:1px;text-transform:uppercase}.header h1{margin:6px 0 5px;font-size:34px}.muted{color:#b8bdd6}.shop-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(320px,.55fr);gap:18px}.panel{background:rgba(38,39,64,.95);border:1px solid #3c3e62;border-radius:18px;box-shadow:0 18px 50px rgba(0,0,0,.23)}.catalog{padding:18px}.search{display:grid;grid-template-columns:minmax(0,1fr) 220px auto auto;gap:9px;margin-bottom:14px}.search input,.search select{min-width:0;padding:11px 13px;border-radius:10px;border:1px solid #4a4c71;background:#202036;color:#fff;outline:none}.search select option{background:#202036;color:#fff}.search button,.search .clear-filter{padding:0 16px;min-height:42px;border-radius:10px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}.search button{border:0;background:linear-gradient(135deg,#c06ceb,#5df09d);color:#151629}.search .clear-filter{background:#202036;color:#fff;border:1px solid #4a4c71}.product-scroll{max-height:650px;overflow-y:auto;padding-right:5px;scrollbar-color:#5b5f84 #202036;scrollbar-width:thin}.products{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.product{background:#202036;border:1px solid #3c3e62;border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:10px}.product h3{margin:0;font-size:17px}.price{font-size:22px;font-weight:900;color:#5df09d}.stock{color:#b8bdd6;font-size:12px}.product form{margin-top:auto;display:grid;grid-template-columns:80px 1fr;gap:8px}.product input{width:100%;padding:10px;border:1px solid #4a4c71;border-radius:9px;background:#18192d;color:#fff}.buy{border:0;border-radius:9px;cursor:pointer;background:linear-gradient(135deg,#c06ceb,#5df09d);font-weight:900;color:#16172a}.cart{padding:18px;position:sticky;top:84px;align-self:start}.cart h2{margin:0 0 12px}.cart-list{max-height:400px;overflow:auto;border-top:1px solid rgba(255,255,255,.08);border-bottom:1px solid rgba(255,255,255,.08)}.cart-item{padding:12px 0;border-bottom:1px solid rgba(255,255,255,.07)}.cart-item:last-child{border-bottom:0}.cart-line{display:flex;justify-content:space-between;gap:10px}.cart-item input{width:72px;margin-top:8px;padding:8px;border-radius:8px;border:1px solid #4a4c71;background:#202036;color:#fff}.total{display:flex;justify-content:space-between;font-size:20px;font-weight:900;padding:16px 0}.cart-actions{display:grid;gap:8px}.cart-actions button{min-height:44px;border:0;border-radius:10px;cursor:pointer;font-weight:900}.checkout{background:linear-gradient(135deg,#c06ceb,#5df09d);color:#151629}.secondary{background:#202036;color:#fff;border:1px solid #4a4c71!important}.alert{margin-bottom:14px;padding:12px 14px;border-radius:11px}.ok{background:rgba(93,240,157,.10);border:1px solid rgba(93,240,157,.35)}.err{background:rgba(192,108,235,.12);border:1px solid rgba(192,108,235,.38)}.empty{padding:26px;text-align:center;color:#b8bdd6}.note{margin-top:12px;color:#8f95b2;font-size:11px;line-height:1.5}@media(max-width:900px){.shop-layout{grid-template-columns:1fr}.cart{position:static}.products{grid-template-columns:1fr 1fr}.search{grid-template-columns:1fr 1fr auto auto}}@media(max-width:600px){.products{grid-template-columns:1fr}.nav{align-items:flex-start;flex-direction:column}.header h1{font-size:28px}.product-scroll{max-height:none}.shop-layout{display:block}.cart{margin-top:16px}.search{grid-template-columns:1fr}.search button,.search .clear-filter{min-height:42px}}
</style>
</head>
<body>
<nav class="nav"><strong>Smart Inventory</strong><div class="nav-links"><a class="active" href="/smart_inventory/user/shop.php">Shop</a><a href="/smart_inventory/user/orders.php">My Purchases</a><a href="/smart_inventory/user/account.php">Account</a><a href="/smart_inventory/logout.php">Logout</a></div></nav>
<main class="wrap">
<div class="header"><div><div class="eyebrow">Customer Shop</div><h1>Available Stocks</h1><div class="muted">Browse and scroll through products that are currently in stock.</div></div><div class="muted">Signed in as <?php echo htmlspecialchars($userName); ?></div></div>
<?php if($message): ?><div class="alert ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if($error): ?><div class="alert err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="shop-layout">
<section class="panel catalog">
<form class="search" method="GET">
<input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search available products...">
<select name="category" aria-label="Category filter">
<option value="0">All categories</option>
<?php if($categories): while($cat=$categories->fetch_assoc()): ?>
<option value="<?php echo (int)$cat['id']; ?>" <?php echo $categoryId===(int)$cat['id']?'selected':''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
<?php endwhile; endif; ?>
</select>
<button type="submit">Search / Filter</button>
<a class="clear-filter" href="/smart_inventory/user/shop.php">Clear</a>
</form>
<div class="product-scroll"><div class="products">
<?php if($products && $products->num_rows): while($p=$products->fetch_assoc()): ?>
<article class="product"><div><h3><?php echo htmlspecialchars($p['product_name']); ?></h3><div class="stock"><?php echo htmlspecialchars($p['category_name'] ?: 'Uncategorized'); ?> · <?php echo (int)$p['stock_quantity']; ?> in stock</div></div><div class="price">₱<?php echo number_format((float)$p['selling_price'],2); ?></div>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['user_shop_csrf']); ?>"><input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>"><input type="number" name="quantity" min="1" max="<?php echo (int)$p['stock_quantity']; ?>" value="1" aria-label="Quantity"><button class="buy" type="submit" name="add_to_cart">Add to Cart</button></form></article>
<?php endwhile; else: ?><div class="empty">No available products found.</div><?php endif; ?>
</div></div>
</section>
<aside class="panel cart"><h2>Your Cart</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['user_shop_csrf']); ?>">
<div class="cart-list">
<?php if($cartItems): foreach($cartItems as $item): ?><div class="cart-item"><div class="cart-line"><strong><?php echo htmlspecialchars($item['name']); ?></strong><span>₱<?php echo number_format($item['subtotal'],2); ?></span></div><div class="muted" style="font-size:12px;margin-top:4px">₱<?php echo number_format($item['price'],2); ?> each</div><input type="number" name="qty[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="0" max="<?php echo $item['stock']; ?>"></div><?php endforeach; else: ?><div class="empty">Your cart is empty.</div><?php endif; ?>
</div>
<div class="total"><span>Total</span><span>₱<?php echo number_format($cartTotal,2); ?></span></div>
<div class="cart-actions"><button class="secondary" type="submit" name="update_cart">Update Cart</button><button class="secondary" type="submit" name="clear_cart" onclick="return confirm('Clear your cart?')">Clear Cart</button><button class="checkout" type="submit" name="complete_purchase" onclick="return confirm('Complete this purchase?')" <?php echo empty($cartItems)?'disabled':''; ?>>Complete Purchase</button></div>
</form><div class="note">This records the purchase in the system and deducts stock. Online card/e-wallet payment is not connected yet.</div></aside>
</div>
</main>
</body>
</html>
