<?php
require __DIR__ . '/../../session.php';
require __DIR__ . '/../../db.php';

/* =========================================
   SECURITY
========================================= */
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
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$branchId  = $_GET['branch_id'] ?? '';
$productIds = $_GET['product_ids'] ?? [];

/* =========================================
   WHERE BUILDER
========================================= */
$where = "WHERE 1=1";
$params = [];

$where .= " AND DATE(oi.created_at) BETWEEN :start_date AND :end_date";
$params[':start_date'] = $startDate;
$params[':end_date'] = $endDate;

if (!empty($branchId)) {
    $where .= " AND o.branch_id = :branch_id";
    $params[':branch_id'] = $branchId;
}

if (!empty($productIds) && is_array($productIds)) {
    $placeholders = [];
    foreach ($productIds as $i => $id) {
        $key = ":pid$i";
        $placeholders[] = $key;
        $params[$key] = $id;
    }

    $where .= " AND p.id IN (" . implode(",", $placeholders) . ")";
}


/* =========================================
   MAIN QUERY
========================================= */
$sql = "
SELECT
    DATE(oi.created_at) AS period,
    b.branch_name,
    p.id AS product_id,
    p.name AS product_name,

   SUM(oi.quantity) AS gross_qty,
   SUM(oi.subtotal) AS gross_sales

FROM order_items oi
LEFT JOIN orders o ON o.id = oi.order_id
LEFT JOIN branches b ON b.branch_id = o.branch_id
LEFT JOIN products p ON p.id = oi.product_id

$where

GROUP BY o.branch_id, p.id, DATE(oi.created_at)
ORDER BY period ASC
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasData = count($reports) > 0;

/* =========================================
   SUMMARY
========================================= */
$summarySql = "
SELECT
    SUM(oi.quantity) AS gross_qty,
SUM(oi.subtotal) AS gross_sales
FROM order_items oi
LEFT JOIN orders o ON o.id = oi.order_id
LEFT JOIN products p ON p.id = oi.product_id
$where
";

$summaryStmt = $pdo->prepare($summarySql);

foreach ($params as $key => $value) {
    $summaryStmt->bindValue($key, $value);
}

$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
$grossQty = $summary['gross_qty'] ?? 0;
$grossSales = $summary['gross_sales'] ?? 0;


$netQty = $grossQty;
$finalSales = $grossSales;
?>

<!DOCTYPE html>
<html>
<head>
<title>Stock Reconciliation</title>

<style>
body{font-family:Arial;background:#f6f7f5;padding:20px;}
.container{background:#fff;padding:20px;border-radius:10px;max-width:1400px;margin:auto;}
table{width:100%;border-collapse:collapse;margin-top:15px;}
th{background:#0b3d2e;color:#fff;padding:10px;}
td{padding:8px;border-bottom:1px solid #eee;text-align:center;}

.card{flex:1;padding:10px;border:1px solid #ddd;border-radius:8px;text-align:center;}

.layout {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

/* LEFT SIDE */
.filter-panel {
    width: 280px;
    padding: 15px;
    background: #f4f4f4;
    border-radius: 10px;

    display: flex;
    flex-direction: column;
    gap: 10px;

    position: sticky;
    top: 10px;
    height: fit-content;
}

/* RIGHT SIDE */
.report-panel {
    flex: 1;
}

/* PRODUCT LIST */
.product-list {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 10px;
    background: white;
}

/* SUMMARY CARDS */
.summary {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.card {
    background: #fff;
    padding: 10px;
    border-radius: 6px;
    flex: 1;
    text-align: center;
}

@media (max-width: 768px) {
    .layout {
        flex-direction: column;
    }

    .filter-panel {
        width: 100%;
        position: relative;
    }
}

@media (max-width: 768px) {
    .layout {
        flex-direction: column;
    }

    .filter-panel {
        width: 100%;
        position: relative;
    }
}

@media (max-width: 768px) {
    .summary {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
</head>

<body>
<?php include __DIR__ . '/../../../includes/topbar.php'; ?>
<div class="container">

    <h2>Stock Reconciliation System</h2>


    <div class="layout">

        <!-- LEFT SIDE (FILTER PANEL) -->
        <div class="filter-panel">
            <form method="GET">

                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= $startDate ?>">

                <label>End Date</label>
                <input type="date" name="end_date" value="<?= $endDate ?>">

                <label>Branch</label>
                <select name="branch_id">
                    <option value="">All Branches</option>
                    <?php
                    $branches = $pdo->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
                    while ($b = $branches->fetch(PDO::FETCH_ASSOC)) {
                    ?>
                        <option value="<?= $b['branch_id'] ?>"
                            <?= $branchId==$b['branch_id']?'selected':'' ?>>
                            <?= $b['branch_name'] ?>
                        </option>
                    <?php } ?>
                </select>
                     <input type="text" id="searchProduct" placeholder="Search product...">
                <div class="product-list">
                    <?php
                    $products = $pdo->query("SELECT id, name FROM products ORDER BY name");
                    while ($p = $products->fetch(PDO::FETCH_ASSOC)) {
                    ?>
                        <div>
                            <label>
                                <input type="checkbox"
                                       name="product_ids[]"
                                       value="<?= $p['id'] ?>"
                                       <?= in_array($p['id'], $productIds) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                            </label>
                        </div>
                    <?php } ?>
                </div>

                <label>
                    <input type="checkbox" onclick="toggleAllProducts(this)">
                    Select All Products
                </label>

                <button type="submit">Filter</button>

            </form>
        </div>

        <!-- RIGHT SIDE (REPORT PANEL) -->
        <div class="report-panel">

            <div class="summary">
              
                <div class="card">Quantity: <?= $grossQty ?></div>
                <div class="card">Sales: <?= number_format($grossSales,2) ?></div>
             
            </div>

            <!-- TABLE -->
            <table>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Sales</th>
                </tr>

                <?php if ($hasData): ?>
                    <?php foreach ($reports as $row): ?>
                        <tr>
    <td><?= $row['period'] ?></td>
    <td><?= $row['product_name'] ?></td>
    <td><?= $row['gross_qty'] ?></td>
    <td><?= number_format($row['gross_sales'],2) ?></td>
</tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">No data found</td></tr>
                <?php endif; ?>
            </table>

            <h3 style="margin-top:30px;">Reconciliation</h3>

            <?php if ($hasData): ?>
            <form method="POST" action="save_reconciliation.php">

                <table>
                    <tr>
                        <th>Product</th>
                        <th>Net</th>
                        <th>Physical</th>
                        <th>Variance</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>

                    <?php foreach ($reports as $row): ?>
                        <?php $net = $row['gross_qty']; ?>
                        <tr>
                            <td>
                                <?= $row['product_name'] ?>
                                <input type="hidden" name="product_id[]" value="<?= $row['product_id'] ?>">
                            </td>

                            <td>
                                <?= $net ?>
                                <input type="hidden" name="net_qty[]" value="<?= $net ?>">
                            </td>

                            <td><input type="number" name="physical_qty[]" class="physical" required></td>
                            <td><input type="text" name="variance[]" class="variance" readonly></td>
                            <td><input type="text" name="status[]" class="status" readonly></td>
                            <td><input type="text" name="remarks[]"></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <button type="submit">Save</button>
            </form>
            <?php endif; ?>

        </div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll(".physical").forEach((input, index) => {

        input.addEventListener("input", function () {

            let row = this.closest("tr");

            let net = parseFloat(row.querySelector("input[name='net_qty[]']").value) || 0;
            let physical = parseFloat(this.value) || 0;

            let variance = physical - net;

            row.querySelector(".variance").value = variance;

            if (physical > net) {
                row.querySelector(".status").value = "Overstock";
            } else if (physical < net) {
                row.querySelector(".status").value = "Understock";
            } else {
                row.querySelector(".status").value = "Balanced";
            }
        });

    });

});

function toggleAllProducts(source) {

    let checkboxes = document.querySelectorAll(
        "input[name='product_ids[]']"
    );

    checkboxes.forEach(function(box) {
        box.checked = source.checked;
    });

}

document.getElementById("searchProduct").addEventListener("input", function () {
    let filter = this.value.toLowerCase();
    document.querySelectorAll(".product-list div").forEach(div => {
        div.style.display = div.innerText.toLowerCase().includes(filter) ? "block" : "none";
    });
});

</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
</body>
</html>