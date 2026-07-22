<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

require_business_login();
require_page_access($conn, 'stock-inward.php');

$businessId = (int)current_business_id();
$batchId = (int)($_GET['batch_id'] ?? 0);

if ($businessId <= 0 || $batchId <= 0) {
    die('Invalid stock inward batch.');
}


/* GET BATCH */

$stmt = mysqli_prepare($conn,"
SELECT 
b.*,
br.branch_name,
br.floor_name,
s.supplier_name,
s.mobile AS supplier_mobile,
COALESCE(u.name,'System') created_by_name

FROM stock_inward_batches b

LEFT JOIN branches br 
ON br.branch_id=b.branch_id 
AND br.business_id=b.business_id

LEFT JOIN suppliers s 
ON s.supplier_id=b.supplier_id
AND s.business_id=b.business_id

LEFT JOIN users u
ON u.user_id=b.created_by
AND u.business_id=b.business_id

WHERE b.business_id=? 
AND b.batch_id=?

LIMIT 1
");

mysqli_stmt_bind_param($stmt,'ii',$businessId,$batchId);
mysqli_stmt_execute($stmt);

$batch=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

mysqli_stmt_close($stmt);


if(!$batch){
    die('Stock inward batch not found.');
}



/* ITEMS */

$stmt=mysqli_prepare($conn,"
SELECT 
i.*,
c.category_name,
bd.brand_name,
sb.barcode_value

FROM stock_inward_items i

LEFT JOIN categories c
ON c.category_id=i.category_id
AND c.business_id=i.business_id

LEFT JOIN brands bd
ON bd.brand_id=i.brand_id
AND bd.business_id=i.business_id

LEFT JOIN stock_barcodes sb
ON sb.stock_item_id=i.stock_item_id
AND sb.barcode_status!='deleted'

WHERE i.business_id=?
AND i.batch_id=?

ORDER BY i.stock_item_id ASC

");


mysqli_stmt_bind_param($stmt,'ii',$businessId,$batchId);
mysqli_stmt_execute($stmt);


$result=mysqli_stmt_get_result($stmt);

$items=[];

while($row=mysqli_fetch_assoc($result)){
    $items[]=$row;
}

mysqli_stmt_close($stmt);

?>


<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>
Stock Inward - <?=e($batch['batch_no'])?>
</title>


<style>


@page{
    size:A4;
    margin:12mm;
}


*{
    box-sizing:border-box;
}


body{

    font-family:
    Arial,
    Helvetica,
    sans-serif;

    font-size:12px;

    color:#000;

    margin:0;

}



.print-btn{

    margin-bottom:15px;

}


.header{

    text-align:center;

    margin-bottom:15px;

}


.company{

    font-size:20px;

    font-weight:bold;

}



.title{

    font-size:16px;

    font-weight:bold;

    margin-top:8px;

}



.info-table{

    width:100%;

    border-collapse:collapse;

    margin-bottom:15px;

}



.info-table td{

    border:1px solid #000;

    padding:6px;

}



.label{

    font-weight:bold;

    width:25%;

}



.items{

    width:100%;

    border-collapse:collapse;

}



.items th{

    background:#eee;

    font-weight:bold;

}



.items th,
.items td{

    border:1px solid #000;

    padding:6px;

    font-size:11px;

}



.text-right{

    text-align:right;

}



.footer{

    margin-top:20px;

    text-align:right;

}



@media print{


.print-btn{

display:none!important;

}



body{

    -webkit-print-color-adjust:exact;

    print-color-adjust:exact;

}



}



</style>


</head>


<body>


<button class="print-btn" onclick="window.print()">
Print
</button>



<div class="header">

<div class="company">

<?=e($_SESSION['business_name'] ?? 'GK FOOTWEAR')?>

</div>


<div class="title">

STOCK INWARD REPORT

</div>


</div>




<table class="info-table">


<tr>

<td class="label">
Batch Number
</td>

<td>
<?=e($batch['batch_no'])?>
</td>


<td class="label">
Inward Date
</td>

<td>
<?=date('d-m-Y',strtotime($batch['inward_date']))?>
</td>


</tr>



<tr>

<td class="label">
Branch / Firm
</td>

<td>
<?=e($batch['branch_name'] ?? '-')?>
</td>


<td class="label">
Supplier
</td>

<td>
<?=e($batch['supplier_name'] ?? '-')?>
</td>


</tr>



<tr>

<td class="label">
Invoice Number
</td>

<td>
<?=e($batch['invoice_number'] ?? '-')?>
</td>


<td class="label">
Invoice Date
</td>

<td>
<?=!empty($batch['invoice_date'])?
date('d-m-Y',strtotime($batch['invoice_date'])):'-'?>
</td>


</tr>



<tr>

<td class="label">
Total Quantity
</td>

<td>
<?=number_format($batch['total_qty'],2)?>
</td>


<td class="label">
Purchase Value
</td>

<td>
₹<?=number_format($batch['purchase_total_value'],2)?>
</td>


</tr>


</table>





<table class="items">


<thead>

<tr>

<th>#</th>
<th>Category</th>
<th>Brand</th>
<th>Article</th>
<th>Name</th>
<th>Size</th>
<th>Color</th>
<th>Qty</th>
<th>Purchase</th>
<th>MRP</th>
<th>Selling</th>

</tr>

</thead>



<tbody>


<?php foreach($items as $i=>$item): ?>

<tr>

<td>
<?=$i+1?>
</td>


<td>
<?=e($item['category_name'] ?? '-')?>
</td>


<td>
<?=e($item['brand_name'] ?? '-')?>
</td>


<td>
<?=e($item['article_no'])?>
</td>


<td>
<?=e($item['article_name'])?>
</td>


<td>
<?=e($item['size'])?>
</td>


<td>
<?=e($item['color'])?>
</td>


<td class="text-right">
<?=number_format($item['qty'],2)?>
</td>


<td class="text-right">
₹<?=number_format($item['purchase_rate'],2)?>
</td>


<td class="text-right">
₹<?=number_format($item['mrp_rate'],2)?>
</td>


<td class="text-right">
₹<?=number_format($item['selling_rate'],2)?>
</td>



</tr>


<?php endforeach; ?>


</tbody>


</table>



<div class="footer">

Prepared By :
<?=e($batch['created_by_name'])?>

</div>



</body>
</html>