<?php
session_start();
require_once __DIR__ . '/../includes/role_helpers.php';
requireAnyRole(['supplier']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/supplier_account.php';

$userId = (int)$_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Supplier';
$profile = ensureSupplierProfile($conn, $userId, $name);
$supplierId = (int)$profile['id'];

$stmt = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(stock_quantity),0) stock FROM products WHERE supplier_id=? AND status='active'");
$stmt->bind_param('i',$supplierId);$stmt->execute();$stats=$stmt->get_result()->fetch_assoc();$stmt->close();
$stmt = $conn->prepare("SELECT COUNT(*) total FROM products WHERE supplier_id=? AND status='active' AND stock_quantity<=minimum_stock");
$stmt->bind_param('i',$supplierId);$stmt->execute();$low=$stmt->get_result()->fetch_assoc()['total']??0;$stmt->close();
$stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) total FROM inventory_transactions WHERE user_id=? AND transaction_type='stock_in' AND DATE(transaction_date)=CURDATE()");
$stmt->bind_param('i',$userId);$stmt->execute();$today=$stmt->get_result()->fetch_assoc()['total']??0;$stmt->close();
$stmt = $conn->prepare("SELECT it.quantity,it.reason,it.transaction_date,p.product_name FROM inventory_transactions it JOIN products p ON p.id=it.product_id WHERE it.user_id=? AND it.transaction_type='stock_in' ORDER BY it.transaction_date DESC LIMIT 8");
$stmt->bind_param('i',$userId);$stmt->execute();$recent=$stmt->get_result();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Supplier Dashboard</title>
<style>*{box-sizing:border-box;font-family:Arial,sans-serif}body{margin:0;min-height:100vh;color:#fff;background:radial-gradient(circle at 12% 18%,rgba(192,108,235,.22),transparent 27%),radial-gradient(circle at 86% 22%,rgba(93,240,157,.17),transparent 28%),#121426}.wrap{max-width:1180px;margin:auto;padding:34px 22px}.hero,.card,.panel{background:rgba(38,39,64,.94);border:1px solid #3c3e62;border-radius:16px}.hero{padding:28px;margin-bottom:20px}.hero h1{margin:6px 0 8px}.hero p,.muted{color:#b8bdd6}.eyebrow{color:#5df09d;font-size:12px;font-weight:900;letter-spacing:1px}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px}.card{padding:22px}.card strong{display:block;color:#b8bdd6;font-size:12px;margin-bottom:9px}.num{font-size:30px;font-weight:900}.panel{padding:22px}.panel-top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}.btn{display:inline-block;text-decoration:none;padding:10px 14px;border-radius:9px;background:linear-gradient(135deg,#c06ceb,#5df09d);color:#17182c;font-weight:900}table{width:100%;border-collapse:collapse}th,td{padding:12px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08)}th{color:#cbd0e6;background:#202036}.empty{color:#b8bdd6;text-align:center}@media(max-width:760px){.cards{grid-template-columns:1fr}.panel{overflow:auto}}</style></head><body>
<?php include __DIR__ . '/nav.php'; ?>
<main class="wrap"><section class="hero"><div class="eyebrow">SUPPLIER ACCOUNT</div><h1><?php echo htmlspecialchars($profile['supplier_name']); ?></h1><p>You can view products assigned to your supplier account, supply stock, and manage your supplier contact information.</p></section>
<div class="cards"><div class="card"><strong>ASSIGNED PRODUCTS</strong><div class="num"><?php echo (int)($stats['total']??0); ?></div></div><div class="card"><strong>LOW STOCK PRODUCTS</strong><div class="num"><?php echo (int)$low; ?></div></div><div class="card"><strong>UNITS SUPPLIED TODAY</strong><div class="num"><?php echo (int)$today; ?></div></div></div>
<section class="panel"><div class="panel-top"><div><h2 style="margin:0">Recent Supplies</h2><div class="muted">Stock added using your supplier account.</div></div><a class="btn" href="/smart_inventory/supplier/supply.php">+ Supply Stock</a></div>
<table><thead><tr><th>Product</th><th>Quantity</th><th>Reason</th><th>Date</th></tr></thead><tbody><?php if($recent->num_rows): while($r=$recent->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($r['product_name']); ?></td><td>+<?php echo (int)$r['quantity']; ?></td><td><?php echo htmlspecialchars($r['reason']); ?></td><td><?php echo htmlspecialchars($r['transaction_date']); ?></td></tr><?php endwhile; else: ?><tr><td colspan="4" class="empty">No supplier stock entries yet.</td></tr><?php endif; ?></tbody></table></section></main></body></html>
