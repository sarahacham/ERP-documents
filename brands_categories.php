<?php

session_start();
require __DIR__ . '/../db.php';

/* =========================
   ACTION HANDLER
========================= */

$message = "";

/* DELETE */
if(isset($_GET['delete_brand'])){
    $id=(int)$_GET['delete_brand'];

    $stmt=$pdo->prepare("DELETE FROM brands WHERE id=?");
    $stmt->execute([$id]);

    $message="Brand deleted successfully";
}

if(isset($_GET['delete_category'])){
    $id=(int)$_GET['delete_category'];

    $stmt=$pdo->prepare("DELETE FROM categories WHERE id=?");
    $stmt->execute([$id]);

    $message="Category deleted successfully";
}


/* SAVE */
if($_SERVER['REQUEST_METHOD']=="POST"){

    $type=$_POST['type'];
    $name=trim($_POST['name']);
    $id=$_POST['id'] ?? '';

    if($name==""){
        $message="Name required";
    }
    else{

        if($type=="brand"){

            if($id){

                $stmt=$pdo->prepare("
                UPDATE brands 
                SET name=?, updated_at=NOW()
                WHERE id=?
                ");

                $stmt->execute([$name,$id]);

                $message="Brand updated";

            }else{

                $stmt=$pdo->prepare("
                INSERT INTO brands(name,created_at,updated_at)
                VALUES(?,NOW(),NOW())
                ");

                $stmt->execute([$name]);

                $message="Brand added";
            }

        }


        if($type=="category"){

            if($id){

                $stmt=$pdo->prepare("
                UPDATE categories
                SET name=?, updated_at=NOW()
                WHERE id=?
                ");

                $stmt->execute([$name,$id]);

                $message="Category updated";

            }else{

                $stmt=$pdo->prepare("
                INSERT INTO categories(name,created_at,updated_at)
                VALUES(?,NOW(),NOW())
                ");

                $stmt->execute([$name]);

                $message="Category added";
            }

        }

    }

}



/* EDIT DATA */

$editBrand=null;
$editCategory=null;


if(isset($_GET['edit_brand'])){

    $stmt=$pdo->prepare("SELECT * FROM brands WHERE id=?");
    $stmt->execute([(int)$_GET['edit_brand']]);
    $editBrand=$stmt->fetch();

}


if(isset($_GET['edit_category'])){

    $stmt=$pdo->prepare("SELECT * FROM categories WHERE id=?");
    $stmt->execute([(int)$_GET['edit_category']]);
    $editCategory=$stmt->fetch();

}



/* SEARCH */

$brandSearch=$_GET['brand_search'] ?? '';

$categorySearch=$_GET['category_search'] ?? '';



$stmt=$pdo->prepare("
SELECT * FROM brands
WHERE name LIKE ?
ORDER BY id DESC
");

$stmt->execute(["%$brandSearch%"]);

$brands=$stmt->fetchAll();



$stmt=$pdo->prepare("
SELECT * FROM categories
WHERE name LIKE ?
ORDER BY id DESC
");

$stmt->execute(["%$categorySearch%"]);

$categories=$stmt->fetchAll();


?>


<!DOCTYPE html>
<html>
<head>

<title>Brands & Categories</title>

<style>

body{
font-family:Arial;
background:#f4f7f5;
margin:0;
padding:20px;
}


.container{

display:grid;
grid-template-columns:1fr 1fr;
gap:20px;

}


.card{

background:white;
padding:20px;
border-radius:12px;
box-shadow:0 3px 10px #ccc;

}


h2{

color:#0b5d3b;

}


input{

width:100%;
padding:10px;
margin:5px 0;
border:1px solid #ddd;
border-radius:8px;

}


button{

background:#0b5d3b;
color:white;
border:none;
padding:10px;
border-radius:8px;
cursor:pointer;

}


table{

width:100%;
border-collapse:collapse;
margin-top:15px;

}


td,th{

padding:10px;
border-bottom:1px solid #ddd;

}


a{

text-decoration:none;
color:#0b5d3b;

}


.delete{

color:red;

}


.msg{

background:#dff5e8;
padding:10px;
margin-bottom:15px;
border-radius:8px;

}


@media(max-width:800px){

.container{

grid-template-columns:1fr;

}

}

</style>

</head>


<body>


<?php include __DIR__ . '/../../includes/topbar.php'; ?>

<h2>Brands & Categories Management</h2>


<?php if($message): ?>

<div class="msg">
<?=htmlspecialchars($message)?>
</div>

<?php endif; ?>


<div class="container">


<!-- BRANDS -->

<div class="card">


<h2>Brands</h2>


<form method="POST">

<input type="hidden" name="type" value="brand">

<input type="hidden" 
name="id"
value="<?= $editBrand['id'] ?? '' ?>">



<input 
name="name"
placeholder="Brand name"
value="<?=htmlspecialchars($editBrand['name'] ?? '')?>"
>


<button>
<?= $editBrand ? "Update Brand":"Add Brand" ?>
</button>


</form>



<form>

<input name="brand_search"
placeholder="Search brands">

</form>



<table>

<tr>
<th>Name</th>
<th>Action</th>
</tr>


<?php foreach($brands as $b): ?>

<tr>

<td>
<?=$b['name']?>
</td>


<td>

<a href="?edit_brand=<?=$b['id']?>">
Edit
</a>

|

<a class="delete"
onclick="return confirm('Delete brand?')"
href="?delete_brand=<?=$b['id']?>">
Delete
</a>

</td>

</tr>


<?php endforeach; ?>


</table>


</div>





<!-- CATEGORIES -->


<div class="card">


<h2>Categories</h2>


<form method="POST">


<input type="hidden" name="type" value="category">


<input type="hidden"
name="id"
value="<?=$editCategory['id'] ?? ''?>">


<input 
name="name"
placeholder="Category name"
value="<?=htmlspecialchars($editCategory['name'] ?? '')?>"
>


<button>

<?= $editCategory ? "Update Category":"Add Category" ?>

</button>


</form>



<form>

<input name="category_search"
placeholder="Search categories">

</form>




<table>


<tr>

<th>Name</th>
<th>Action</th>

</tr>



<?php foreach($categories as $c): ?>


<tr>

<td>
<?=$c['name']?>
</td>


<td>


<a href="?edit_category=<?=$c['id']?>">
Edit
</a>


|


<a class="delete"
onclick="return confirm('Delete category?')"
href="?delete_category=<?=$c['id']?>">
Delete
</a>


</td>


</tr>


<?php endforeach; ?>


</table>


</div>



</div>


<?php include __DIR__ . '/../../includes/footer.php'; ?>


</body>
</html>