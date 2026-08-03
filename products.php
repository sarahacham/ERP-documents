<?php
session_start();



require __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../middle_login.php");
    exit;
}

$role = $_SESSION['role'];

// ONLY admin + procurement allowed
if (!in_array($role, ['admin', 'procurement'])) {
    header("Location: ../unauthorized.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$isAdmin = ($role === 'admin');

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;
$isAdmin = ($role === 'admin');

// CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$message = "";

/* =========================
   HANDLE POST
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['csrf_token'])
    && $_POST['csrf_token'] === $csrf) {

    $action = $_POST['action'] ?? '';
    $product_id = $_POST['product_id'] ?? null;

    $isAdmin = ($role === 'admin');

    /* =========================
       ADD PRODUCT
    ========================= */
    if ($action === 'add_product') {

    if (!in_array($role, ['admin', 'procurement'])) {
        $message = "You are not allowed to add products.";
    } else {

            $name = trim($_POST['name'] ?? '');
            $buying_price = trim($_POST['buying_price'] ?? 0);
            $selling_price = trim($_POST['selling_price'] ?? 0);
            $tax_percent = trim($_POST['tax_percent'] ?? 0);
            $stock_qty = (int)($_POST['stock_qty'] ?? 0);
            $tax_amount = ($selling_price * $tax_percent) / 100;
            $final_price = $selling_price + $tax_amount;
            $restock_threshold = trim($_POST['restock_threshold'] ?? '');
            $barcode = trim($_POST['barcode'] ?? '');
            $category_id = $_POST['category_id'] ?? null;
            $brand_id = $_POST['brand_id'] ?? null;
            $expiry_date = $_POST['expiry_date'] ?? null;

           if ($name && $selling_price !== '' && $stock_qty !== ''){

                // duplicate barcode check
                if ($barcode) {
                    $chk = $pdo->prepare("SELECT id FROM products WHERE barcode=? LIMIT 1");
                    $chk->execute([$barcode]);
                    if ($chk->fetchColumn()) {
                        $message = "Barcode already exists.";
                    }
                }

                if (!$message) {

                    

$stmt = $pdo->prepare("
INSERT INTO products
(name, buying_price, selling_price, tax_percent, tax_amount, final_price,
stock_qty, restock_threshold, barcode, category_id, brand_id, expiry_date,
is_active, created_at, updated_at, added_by)
VALUES
(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), ?)
");





$stmt->execute([
    $name,
    $buying_price,
    $selling_price,
    $tax_percent,
    $tax_amount,
    $final_price,
    $stock_qty,
    $restock_threshold,
    $barcode,
    $category_id ?: null,
    $brand_id ?: null,
    $expiry_date ?: null,
    $user_id
]);

$product_id = $pdo->lastInsertId();



                    $message = "Product added successfully!";
                }

            } else {
                $message = "Fill all required fields.";
            }
        }
    }

    /* =========================
       EDIT PRODUCT
    ========================= */
    if ($action === 'edit_product' && $product_id) {

    if (!in_array($role, ['admin', 'procurement'])) {
        $message = "Not allowed to edit this product.";
    } else {

        $stmt = $pdo->prepare("
           UPDATE products SET
name=?,
buying_price=?,
selling_price=?,
tax_percent=?,
tax_amount=?,
final_price=?,
restock_threshold=?,
barcode=?,
category_id=?,
brand_id=?,
expiry_date=?,
updated_at=NOW()
WHERE id=?
        ");

    $buying_price = $_POST['buying_price'] ?? 0;
$selling_price = $_POST['selling_price'] ?? 0;
$tax_percent = $_POST['tax_percent'] ?? 0;

$tax_amount = ($selling_price * $tax_percent) / 100;
$final_price = $selling_price + $tax_amount;

$stmt->execute([
    $_POST['name'],
    $buying_price,
    $selling_price,
    $tax_percent,
    $tax_amount,
    $final_price,
    $_POST['restock_threshold'],
    $_POST['barcode'],
    $_POST['category_id'] ?: null,
    $_POST['brand_id'] ?: null,
    $_POST['expiry_date'] ?: null,
    $product_id
]);

        $message = "Product updated successfully!";
    }
}
    
    /* =========================
       DELETE PRODUCT
    ========================= */
   if ($action === 'temp_delete' && $product_id) {

    // get product
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {

        // insert into deleted_products
        $stmt = $pdo->prepare("
            INSERT INTO deleted_products (
                id, branch_id, added_by, name,
                buying_price, selling_price,
                tax_percent, tax_amount, final_price,
                stock_qty, barcode, restock_threshold,
                category_id, brand_id,
                created_at, updated_at,
                deleted_by, expiry_date
            ) VALUES (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?
            )
        ");

        $stmt->execute([
            $product['id'],
            $product['branch_id'],
            $product['added_by'],
            $product['name'],
            $product['buying_price'],
            $product['selling_price'],
            $product['tax_percent'],
            $product['tax_amount'],
            $product['final_price'],
            $product['stock_qty'],
            $product['barcode'],
            $product['restock_threshold'],
            $product['category_id'],
            $product['brand_id'],
            $product['created_at'],
            $product['updated_at'],
            $user_id,
            $product['expiry_date']
        ]);

        // remove from products table
        $pdo->prepare("DELETE FROM products WHERE id=?")
            ->execute([$product_id]);

        $message = "Product moved to deleted products.";
    }
}
    }
/* =========================
   FETCH PRODUCTS
========================= */
$search = trim($_GET['search'] ?? '');

$sql = "
SELECT p.*, c.name AS category_name, b.name AS brand_name,
       e.name AS added_by_name
FROM products p
LEFT JOIN categories c ON p.category_id=c.id
LEFT JOIN brands b ON p.brand_id=b.id
LEFT JOIN employees e ON p.added_by=e.id
WHERE p.is_active=1
";

$params = [];



if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// stock already comes from products table

/* =========================
   LOOKUPS
========================= */
$categories = $pdo->query("SELECT id,name FROM categories")->fetchAll();
$brands = $pdo->query("SELECT id,name FROM brands")->fetchAll();
?>

<!-- KEEP YOUR HTML + JS EXACTLY AS IT WAS BELOW -->




<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Products Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/products.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="top-nav">
   <a href="../sales_reports/procurement/procurement_sales_dashboard.php">
    view sales</a>
    <a href="products.php">📦 Products</a>
    <a href="brands_categories.php">📂brands and Categories</a>
    
    <a href="../sales_reports/procurement/reconciliation_dashboard.php">
    reconciliation_ Dashboard</a>
    <a href="../sales_reports/procurement/stock_reconciliation.php">
    reconcile stock</a>
    
    <a href="../logout.php">🚪 Logout</a>
</div>



<h2>Products Management</h2>
<?php if($message) echo "<p style='color:green;'>$message</p>"; ?>

<h3>Add Product</h3>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= $csrf ?>">
<input type="hidden" name="action" value="add_product">



<div class="table-wrapper">
<table class="add-products-table">
<tr>
<th>Name</th>
<th>Buying Price</th>
<th>Selling Price</th>
<th>Tax %</th>
<th>Tax Amount</th>
<th>Final Price</th>
<th>Stock</th>
<th>Restock</th>
<th>Barcode</th>
<th>Category</th>
<th>Brand</th>
<th>Expiry</th>
<th>Action</th>
</tr>

<tr>
<td>
    <input type="text" name="name" required pattern="[A-Za-z0-9\s]+" title="Only letters and numbers allowed">
</td>

<td>
    <input type="number"
           name="buying_price"
           step="0.01"
           min="0"
           required>
</td>

<td>
    <input type="number"
           name="selling_price"
           step="0.01"
           min="0"
           required>
</td>

<td>
    <input type="number"
           name="tax_percent"
           step="0.01"
           min="0"
           value="0">
</td>

<td>
    <input type="text"
           value="Auto"
           readonly>
</td>

<td>
    <input type="text"
           value="Auto"
           readonly>
</td>
<td>
    <input type="number" name="stock_qty" min="0" required>
</td>

<td>
    <input type="number" name="restock_threshold" min="0">
</td>

<td>
    <input type="text" name="barcode">
</td>

<td>
    <select name="category_id">
        <option value="">-</option>
        <?php foreach($categories as $c): ?>
            <option value="<?= $c['id'] ?>">
                <?= htmlspecialchars($c['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</td>

<td>
    <select name="brand_id">
        <option value="">-</option>
        <?php foreach($brands as $b): ?>
            <option value="<?= $b['id'] ?>">
                <?= htmlspecialchars($b['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</td>

<td>
    <input type="date" name="expiry_date">
</td>

<td>
    <button type="submit">Add Product</button>
</td>
</tr>
</table>
</div>



</form>



<h3>Product List</h3>

<table class="products-list-table">
<tr>
<th>#</th>
<th>Name</th>
<th>Buying Price</th>
<th>Selling Price</th>
<th>Tax %</th>
<th>Tax Amount</th>
<th>Final Price</th>
<th>Stock</th>
<th>Restock</th>
<th>Barcode</th>
<th>Category</th>
<th>Brand</th>
<th>Added By</th>
<th>Expiry</th>
<th>Actions</th>
</tr>

<?php foreach($products as $i=>$p): ?>
<tr class="<?= $p['stock_qty'] <= $p['restock_threshold'] ? 'low-stock' : '' ?>">

<form method="post">

<input type="hidden" name="csrf_token" value="<?= $csrf ?>">
<input type="hidden" name="product_id" value="<?= $p['id'] ?>">

<td><?= $i+1 ?></td>

<td><input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required></td>

<td>
<input type="number"
       step="0.01"
       name="buying_price"
       value="<?= (float)$p['buying_price'] ?>"
       required>
</td>

<td>
<input type="number"
       step="0.01"
       name="selling_price"
       value="<?= (float)$p['selling_price'] ?>"
       required>
</td>

<td>
<input type="number"
       step="0.01"
       name="tax_percent"
       value="<?= (float)$p['tax_percent'] ?>">
</td>

<td>
<input type="number"
       step="0.01"
       value="<?= (float)$p['tax_amount'] ?>"
       readonly>
</td>

<td>
<input type="number"
       step="0.01"
       value="<?= (float)$p['final_price'] ?>"
       readonly>
</td>

<td><input type="number" name="stock_qty" value="<?= (int)$p['stock_qty'] ?>" required></td>

<td><input type="number" name="restock_threshold" value="<?= (int)$p['restock_threshold'] ?>"></td>

<td><input type="text" name="barcode" value="<?= htmlspecialchars($p['barcode']) ?>"></td>

<td>
<select name="category_id">
<option value="">-</option>
<?php foreach($categories as $c): ?>
<option value="<?= $c['id'] ?>" <?= ($c['id']==$p['category_id'])?'selected':'' ?>>
<?= htmlspecialchars($c['name']) ?>
</option>
<?php endforeach; ?>
</select>
</td>

<td>
<select name="brand_id">
<option value="">-</option>
<?php foreach($brands as $b): ?>
<option value="<?= $b['id'] ?>" <?= ($b['id']==$p['brand_id'])?'selected':'' ?>>
<?= htmlspecialchars($b['name']) ?>
</option>
<?php endforeach; ?>
</select>
</td>


<td><?= htmlspecialchars($p['added_by_name'] ?? 'System') ?></td>

<td><input type="date" name="expiry_date" value="<?= htmlspecialchars($p['expiry_date']) ?>"></td>

<td>
    <button type="submit" name="action" value="edit_product">
        💾 Save
    </button>
</td>

</form>

</tr>
<?php endforeach; ?>

</table>






<script>
// Arrow key navigation
function setupArrowKeys() {
    const inputs = Array.from(document.querySelectorAll('input, select, button'));
    inputs.forEach((input,i)=>{
        input.addEventListener('keydown',e=>{
            if(e.key==='ArrowDown'){e.preventDefault();inputs[i+1]?.focus();}
            if(e.key==='ArrowUp'){e.preventDefault();inputs[i-1]?.focus();}
            if(e.key==='ArrowRight'){e.preventDefault();inputs[i+1]?.focus();}
            if(e.key==='ArrowLeft'){e.preventDefault();inputs[i-1]?.focus();}
            if(e.key==='Enter'){e.preventDefault();inputs[i+1]?.focus();}
        });
    });
}
setupArrowKeys();






// ================================
// AUTO CALCULATE TAX + FINAL PRICE
// ================================

function setupAutoCalculation() {

    // ALL product rows
    const rows = document.querySelectorAll(".products-list-table tr");

    rows.forEach(row => {

        const sellingInput = row.querySelector('input[name="selling_price"]');
        const taxPercentInput = row.querySelector('input[name="tax_percent"]');

        // readonly fields
        const taxAmountInput = row.querySelectorAll('input[readonly]')[0];
        const finalPriceInput = row.querySelectorAll('input[readonly]')[1];

        if (
            sellingInput &&
            taxPercentInput &&
            taxAmountInput &&
            finalPriceInput
        ) {

            function calculate() {

                let sellingPrice = parseFloat(sellingInput.value) || 0;
                let taxPercent = parseFloat(taxPercentInput.value) || 0;

                let taxAmount = (sellingPrice * taxPercent) / 100;
                let finalPrice = sellingPrice + taxAmount;

                taxAmountInput.value = taxAmount.toFixed(2);
                finalPriceInput.value = finalPrice.toFixed(2);
            }

            // calculate when typing
            sellingInput.addEventListener('input', calculate);
            taxPercentInput.addEventListener('input', calculate);

            // initial calculation
            calculate();
        }
    });


    // ================================
    // ADD PRODUCT TABLE
    // ================================

    const addSelling = document.querySelector(
        '.add-products-table input[name="selling_price"]'
    );

    const addTaxPercent = document.querySelector(
        '.add-products-table input[name="tax_percent"]'
    );

    const addReadonlyInputs = document.querySelectorAll(
        '.add-products-table input[readonly]'
    );

    if (
        addSelling &&
        addTaxPercent &&
        addReadonlyInputs.length >= 2
    ) {

        const addTaxAmount = addReadonlyInputs[0];
        const addFinalPrice = addReadonlyInputs[1];

        function calculateAddProduct() {

            let sellingPrice = parseFloat(addSelling.value) || 0;
            let taxPercent = parseFloat(addTaxPercent.value) || 0;

            let taxAmount = (sellingPrice * taxPercent) / 100;
            let finalPrice = sellingPrice + taxAmount;

            addTaxAmount.value = taxAmount.toFixed(2);
            addFinalPrice.value = finalPrice.toFixed(2);
        }

        addSelling.addEventListener('input', calculateAddProduct);
        addTaxPercent.addEventListener('input', calculateAddProduct);

        calculateAddProduct();
    }
}

// run
setupAutoCalculation();



</script>



<?php include __DIR__ . '/../../includes/footer.php'; ?>

</body>
</html>