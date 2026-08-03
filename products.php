<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/../db.php';  // Adjusted path
require __DIR__ . '/../vendor/autoload.php';

// 🔐 Role-based access
if (!isset($_SESSION['role'])) {
    die('Unauthorized');
}

$role = $_SESSION['role']; // 'employee' or 'admin'

// Generate CSRF token for safety
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// -----------------------------
// Handle Product Addition
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    if ($_POST['csrf_token'] !== $csrf_token) die('Invalid CSRF token');

    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $stock_qty = (int)$_POST['stock_qty'];
    $restock_threshold = (int)$_POST['restock_threshold'];
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $brand_id = isset($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;

    if ($name !== '' && $price > 0 && $stock_qty >= 0 && $restock_threshold >= 0) {
        $stmt = $pdo->prepare("
            INSERT INTO products 
            (name, price, stock_qty, restock_threshold, category_id, brand_id, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$name, $price, $stock_qty, $restock_threshold, $category_id, $brand_id]);
        $message = "Product added!";
    } else {
        $message = "Invalid input!";
    }
}

// -----------------------------
// Handle Temporary Delete (Cashier)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'temp_delete' && $role === 'employee') {
    if ($_POST['csrf_token'] !== $csrf_token) die('Invalid CSRF token');

    $product_id = (int)$_POST['product_id'];
    $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $stmt->execute([$product_id]);
    $message = "Product temporarily deleted!";
}

// -----------------------------
// Handle Permanent Delete (Employer)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'perm_delete' && $role === 'admin') {
    if ($_POST['csrf_token'] !== $csrf_token) die('Invalid CSRF token');

    $product_id = (int)$_POST['product_id'];
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $message = "Product permanently deleted!";
}

// -----------------------------
// Handle Edit Product (Employer)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product' && $role === 'admin') {
    if ($_POST['csrf_token'] !== $csrf_token) die('Invalid CSRF token');

    $product_id = (int)$_POST['product_id'];
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $stock_qty = (int)$_POST['stock_qty'];
    $restock_threshold = (int)$_POST['restock_threshold'];
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $brand_id = isset($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;

    $stmt = $pdo->prepare("
        UPDATE products SET
        name = ?, price = ?, stock_qty = ?, restock_threshold = ?, category_id = ?, brand_id = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$name, $price, $stock_qty, $restock_threshold, $category_id, $brand_id, $product_id]);
    $message = "Product updated!";
}

// -----------------------------
// Fetch Products
// -----------------------------
$search = trim($_GET['search'] ?? '');
$query = "SELECT * FROM products WHERE is_active = 1";
$params = [];

if ($search !== '') {
    $query .= " AND name LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Products Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
table { width:100%; border-collapse: collapse; }
th, td { border:1px solid #ccc; padding:5px; text-align:left; }
.low-stock { background-color:#ffcccc; }
</style>
</head>
<body>

<h2>Products Management</h2>

<?php if (!empty($message)) echo "<p style='color:green;'>$message</p>"; ?>

<h3>Add Product</h3>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="action" value="add_product">
Name: <input type="text" name="name" required>
Price: <input type="number" name="price" step="0.01" min="0" required>
Stock Qty: <input type="number" name="stock_qty" min="0" required>
Restock Threshold: <input type="number" name="restock_threshold" min="0" required>
<button type="submit">Add Product</button>
</form>

<h3>Search Products</h3>
<form method="get">
Search: <input type="text" name="search" value="<?= htmlspecialchars($search) ?>">
<button type="submit">Filter</button>
</form>

<h3>Product List</h3>
<table>
<tr>
<th>Name</th><th>Price</th><th>Stock</th><th>Restock Threshold</th><th>Actions</th>
</tr>
<?php foreach($products as $p): ?>
<tr class="<?= $p['stock_qty'] <= $p['restock_threshold'] ? 'low-stock' : '' ?>">
<td><?= htmlspecialchars($p['name']) ?></td>
<td><?= number_format((float)$p['price'], 2) ?>
</td>
<td><?= $p['stock_qty'] ?></td>
<td><?= $p['restock_threshold'] ?></td>
<td>
<?php if($role==='employee'): ?>
<form method="post" style="display:inline">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="action" value="temp_delete">
<input type="hidden" name="product_id" value="<?= $p['id'] ?>">
<button type="submit">Temporary Delete</button>
</form>
<?php endif; ?>
<?php if($role==='admin'): ?>
<form method="post" style="display:inline">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="action" value="perm_delete">
<input type="hidden" name="product_id" value="<?= $p['id'] ?>">
<button type="submit">Delete</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>

</body>
</html>
