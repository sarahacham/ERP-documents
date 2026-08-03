<?php
require __DIR__ . '/../../session.php';
require __DIR__ . '/../../db.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    die("Unauthorized access");
}

$allowedRoles = ['procurement', 'store', 'admin'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    die("Access denied");
}

/* =========================================
   FILTERS
========================================= */

$filterType = $_GET['filter_type'] ?? 'range';

$year  = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

/* =========================================
   WHERE BUILDER
========================================= */

$where = "WHERE is_active = 1";
$params = [];

if ($filterType === 'year') {
    $where .= " AND YEAR(created_at) = :year";
    $params[':year'] = $year;
}

if ($filterType === 'month') {
    $where .= " AND YEAR(created_at) = :year AND MONTH(created_at) = :month";
    $params[':year'] = $year;
    $params[':month'] = $month;
}

if ($filterType === 'range') {
    $where .= " AND DATE(created_at) BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $startDate;
    $params[':end_date'] = $endDate;
}

/* =========================================
   PRODUCTS
========================================= */

$sql = "SELECT id, name, stock_qty FROM products $where ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   SAVE RECONCILIATION
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($_POST['product_id'] as $pid) {

        $systemStock   = (float)($_POST['system_stock'][$pid] ?? 0);
        $physicalStock = (float)($_POST['physical_stock'][$pid] ?? 0);
        $qtySold       = (float)($_POST['qty_sold'][$pid] ?? 0);
        $damagedQty    = (float)($_POST['damaged_qty'][$pid] ?? 0);
        $stolenQty     = (float)($_POST['stolen_qty'][$pid] ?? 0);
        $remarks       = $_POST['remarks'][$pid] ?? '';

        /* =========================
           CORE LOGIC
        ========================= */

        // expected stock after sales & losses
        $expectedStock = $systemStock - $qtySold - $damagedQty - $stolenQty;

        // variance = difference between physical and expected
        $variance = $physicalStock - $expectedStock;

        $status = ($variance == 0) ? 'MATCHED' : 'MISMATCH';

        $insert = $pdo->prepare("
            INSERT INTO stock_reconciliation
            (product_id, branch_id, stock_date, system_stock, net_sold,
             filter_type, physical_stock, damaged_qty, stolen_qty,
             expected_stock, qty_sold, variance, status, remarks,
             reconciled_by, reconciliation_date)
            VALUES
            (:product_id, NULL, CURDATE(), :system_stock, :net_sold,
             :filter_type, :physical_stock, :damaged_qty, :stolen_qty,
             :expected_stock, :qty_sold, :variance, :status, :remarks,
             :reconciled_by, NOW())
        ");

        $insert->execute([
            ':product_id' => $pid,
            ':system_stock' => $systemStock,
            ':net_sold' => $qtySold,
            ':filter_type' => $filterType,
            ':physical_stock' => $physicalStock,
            ':damaged_qty' => $damagedQty,
            ':stolen_qty' => $stolenQty,
            ':expected_stock' => $expectedStock,
            ':qty_sold' => $qtySold,
            ':variance' => $variance,
            ':status' => $status,
            ':remarks' => $remarks,
            ':reconciled_by' => $_SESSION['user_id']
        ]);
    }

    header("Location: stock_reconciliation.php?saved=1");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Stock Reconciliation</title>

<style>
body{font-family:Arial;background:#f6f7f5;padding:20px}
.container{background:#fff;padding:20px;border-radius:10px}

h2{color:#0b3d2e}

form.filters{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:15px;
    background:#f3f6f2;
    padding:10px;
    border-radius:8px;
}

select,input{
    padding:8px;
    border:1px solid #ddd;
    border-radius:5px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#0b3d2e;
    color:#fff;
    padding:10px;
}

td{
    border:1px solid #eee;
    padding:8px;
    text-align:center;
}

input[type="number"]{
    width:90px;
}

.mismatch{
    background:#fff3cd;
}
</style>
</head>

<body>
<?php include __DIR__ . '/../../../includes/topbar.php'; ?>
<div class="container">

<h2>Stock Reconciliation</h2>

<?php if (isset($_GET['saved'])): ?>
<p style="color:green;">Saved successfully</p>
<?php endif; ?>

<!-- FILTERS -->
<form method="GET" class="filters">

<select name="filter_type">
    <option value="range" <?= $filterType=='range'?'selected':'' ?>>Range</option>
    <option value="month" <?= $filterType=='month'?'selected':'' ?>>Month</option>
    <option value="year" <?= $filterType=='year'?'selected':'' ?>>Year</option>

</select>

<select name="year">
<?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
<option value="<?= $y ?>" <?= $year==$y?'selected':'' ?>><?= $y ?></option>
<?php endfor; ?>
</select>

<select name="month">
<?php
$m=["01"=>"Jan","02"=>"Feb","03"=>"Mar","04"=>"Apr","05"=>"May","06"=>"Jun",
"07"=>"Jul","08"=>"Aug","09"=>"Sep","10"=>"Oct","11"=>"Nov","12"=>"Dec"];

foreach($m as $k=>$v): ?>
<option value="<?= $k ?>" <?= $month==$k?'selected':'' ?>><?= $v ?></option>
<?php endforeach; ?>
</select>

<input type="date" name="start_date" value="<?= $startDate ?>">
<input type="date" name="end_date" value="<?= $endDate ?>">

<button type="submit">Filter</button>
</form>

<form method="POST">

<table>
<tr>
<th>Product</th>
<th>System</th>
<th>Physical</th>
<th>Qty Sold</th>
<th>Damaged</th>
<th>Stolen</th>
<th>Expected</th>
<th>Variance</th>
<th>Remarks</th>
</tr>

<?php foreach ($products as $p): ?>

<?php $expected = $p['stock_qty']; ?>

<tr>
<td>
    <?= htmlspecialchars($p['name']) ?>
    <input type="hidden" name="product_id[]" value="<?= $p['id'] ?>">
</td>

<td>
    <?= $p['stock_qty'] ?>
    <input type="hidden" name="system_stock[<?= $p['id'] ?>]" value="<?= $p['stock_qty'] ?>">
</td>

<td><input type="number" name="physical_stock[<?= $p['id'] ?>]" value="0"></td>
<td><input type="number" name="qty_sold[<?= $p['id'] ?>]" value="0"></td>
<td><input type="number" name="damaged_qty[<?= $p['id'] ?>]" value="0"></td>
<td><input type="number" name="stolen_qty[<?= $p['id'] ?>]" value="0"></td>

<td><?= $expected ?></td>

<td>—</td>

<td><input type="text" name="remarks[<?= $p['id'] ?>]"></td>
</tr>

<?php endforeach; ?>

</table>

<br>
<button type="submit">Save Reconciliation</button>

</form>

</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
</body>
</html>