<?php
/**
 * Product Sales Ranking Report API
 * File: business/api/product-sales-ranking-report-api.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_business_login();

$businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);
$userId = function_exists('current_user_id') ? (int)current_user_id() : (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

function psr_json(bool $success, string $message = '', array $extra = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success'=>$success,'message'=>$message],$extra), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function psr_table_exists(mysqli $conn, string $table): bool
{
    $safe = mysqli_real_escape_string($conn, $table);
    $r = mysqli_query($conn, "SHOW TABLES LIKE '{$safe}'");
    return $r && mysqli_num_rows($r) > 0;
}
function psr_column_exists(mysqli $conn, string $table, string $column): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/','',$table);
    $column = mysqli_real_escape_string($conn,$column);
    $r = mysqli_query($conn,"SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $r && mysqli_num_rows($r)>0;
}
function psr_bind(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') return;
    $refs = [$types];
    foreach ($params as $k => $v) $refs[] = &$params[$k];
    call_user_func_array([$stmt,'bind_param'],$refs);
}
function psr_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn,$sql);
    if (!$stmt) throw new RuntimeException('SQL prepare failed: '.mysqli_error($conn));
    psr_bind($stmt,$types,$params);
    if (!mysqli_stmt_execute($stmt)) {
        $err=mysqli_stmt_error($stmt);mysqli_stmt_close($stmt);throw new RuntimeException('SQL execute failed: '.$err);
    }
    $rs=mysqli_stmt_get_result($stmt);$rows=[];
    while($rs && ($row=mysqli_fetch_assoc($rs)))$rows[]=$row;
    mysqli_stmt_close($stmt);return$rows;
}
function psr_one(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $r=psr_rows($conn,$sql,$types,$params);return$r[0]??[];
}
function psr_require_tables(mysqli $conn): void
{
    foreach(['bills','bill_items','stock_inward_items'] as $table) {
        if(!psr_table_exists($conn,$table)) throw new RuntimeException("Required table '{$table}' was not found.");
    }
}
function psr_is_admin(mysqli $conn): bool
{
    return function_exists('is_business_admin') ? (bool)is_business_admin($conn) : true;
}
function psr_allowed_branch_ids(mysqli $conn,int $businessId,int $userId): array
{
    if(!psr_table_exists($conn,'branches'))return[];
    if(psr_is_admin($conn)||!psr_table_exists($conn,'user_branch_access')){
        $rows=psr_rows($conn,"SELECT branch_id FROM branches WHERE business_id=? AND status=1",'i',[$businessId]);
        return array_map(fn($r)=>(int)$r['branch_id'],$rows);
    }
    $rows=psr_rows($conn,"SELECT DISTINCT b.branch_id FROM branches b LEFT JOIN user_branch_access uba ON uba.business_id=b.business_id AND uba.branch_id=b.branch_id AND uba.user_id=? AND uba.access_status=1 LEFT JOIN users u ON u.business_id=b.business_id AND u.default_branch_id=b.branch_id AND u.user_id=? WHERE b.business_id=? AND b.status=1 AND (uba.id IS NOT NULL OR u.user_id IS NOT NULL)",'iii',[$userId,$userId,$businessId]);
    return array_map(fn($r)=>(int)$r['branch_id'],$rows);
}
function psr_dates(array $input): array
{
    $period=(string)($input['period']??'month');
    $today=new DateTimeImmutable('today',new DateTimeZone('Asia/Kolkata'));
    if($period==='today')return[$today->format('Y-m-d'),$today->format('Y-m-d')];
    if($period==='yesterday'){$d=$today->modify('-1 day')->format('Y-m-d');return[$d,$d];}
    if($period==='week')return[$today->modify('monday this week')->format('Y-m-d'),$today->format('Y-m-d')];
    if($period==='month')return[$today->modify('first day of this month')->format('Y-m-d'),$today->format('Y-m-d')];
    if($period==='all')return['',''];
    $from=trim((string)($input['date_from']??''));$to=trim((string)($input['date_to']??''));
    return[$from,$to];
}
function psr_product_key_sql(string $alias='si'): string
{
    return "CONCAT_WS('|',{$alias}.category_id,{$alias}.brand_id,COALESCE({$alias}.article_no,''),COALESCE({$alias}.size,''),COALESCE({$alias}.color,''))";
}
function psr_filters(mysqli $conn,int $businessId,int $userId,array $input,string $alias='si'): array
{
    [$from,$to]=psr_dates($input);
    $where=["{$alias}.business_id=?","{$alias}.item_status='active'"];
    $types='i';$params=[$businessId];
    $branchId=(int)($input['branch_id']??0);
    $allowed=psr_allowed_branch_ids($conn,$businessId,$userId);
    if($branchId>0){
        if($allowed && !in_array($branchId,$allowed,true))throw new RuntimeException('Branch access denied.');
        $where[]="{$alias}.branch_id=?";$types.='i';$params[]=$branchId;
    }elseif(!psr_is_admin($conn)&&$allowed){
        $where[]="{$alias}.branch_id IN (".implode(',',array_fill(0,count($allowed),'?')).")";
        foreach($allowed as $id){$types.='i';$params[]=$id;}
    }elseif(!psr_is_admin($conn)&&!$allowed){$where[]='1=0';}
    $categoryId=(int)($input['category_id']??0);if($categoryId>0){$where[]="{$alias}.category_id=?";$types.='i';$params[]=$categoryId;}
    $brandId=(int)($input['brand_id']??0);if($brandId>0){$where[]="{$alias}.brand_id=?";$types.='i';$params[]=$brandId;}
    $search=trim((string)($input['search']??''));if($search!==''){$s='%'.$search.'%';$where[]="({$alias}.article_name LIKE ? OR {$alias}.article_no LIKE ? OR EXISTS(SELECT 1 FROM stock_barcodes sbx WHERE sbx.business_id={$alias}.business_id AND sbx.stock_item_id={$alias}.stock_item_id AND sbx.barcode_value LIKE ?))";$types.='sss';array_push($params,$s,$s,$s);}
    return['where'=>implode(' AND ',$where),'types'=>$types,'params'=>$params,'from'=>$from,'to'=>$to];
}
function psr_master_data(mysqli $conn,int $businessId,int $userId): array
{
    $allowed=psr_allowed_branch_ids($conn,$businessId,$userId);
    $branchWhere='business_id=? AND status=1';$types='i';$params=[$businessId];
    if(!psr_is_admin($conn)){
        if($allowed){$branchWhere.=' AND branch_id IN ('.implode(',',array_fill(0,count($allowed),'?')).')';foreach($allowed as $id){$types.='i';$params[]=$id;}}
        else $branchWhere.=' AND 1=0';
    }
    $branches=psr_table_exists($conn,'branches')?psr_rows($conn,"SELECT branch_id,CONCAT(branch_name,IF(COALESCE(floor_name,'')<>'',CONCAT(' (',floor_name,')'),'')) branch_name FROM branches WHERE {$branchWhere} ORDER BY branch_name",$types,$params):[];
    $categories=psr_table_exists($conn,'categories')?psr_rows($conn,"SELECT category_id,category_name FROM categories WHERE business_id=? AND status=1 ORDER BY category_name",'i',[$businessId]):[];
    $brands=psr_table_exists($conn,'brands')?psr_rows($conn,"SELECT brand_id,brand_name FROM brands WHERE business_id=? AND status=1 ORDER BY brand_name",'i',[$businessId]):[];
    return compact('branches','categories','brands');
}
function psr_base_sql(mysqli $conn,int $businessId,int $userId,array $input,bool $history=false): array
{
    $f=psr_filters($conn,$businessId,$userId,$input,'si');
    $billDate=[];
    $dateTypes='';$dateParams=[];
    if($f['from']!==''){$billDate[]='b.bill_date>=?';$dateTypes.='s';$dateParams[]=$f['from'];}
    if($f['to']!==''){$billDate[]='b.bill_date<=?';$dateTypes.='s';$dateParams[]=$f['to'];}
    $billDateSql=$billDate?' AND '.implode(' AND ',$billDate):'';
    $billStatus=psr_column_exists($conn,'bills','bill_status')?" AND b.bill_status='active'":'';
    $barcodeJoin=psr_table_exists($conn,'stock_barcodes')
        ? "LEFT JOIN (SELECT business_id,stock_item_id,MAX(CASE WHEN barcode_status='active' THEN barcode_value ELSE NULL END) barcode_value FROM stock_barcodes GROUP BY business_id,stock_item_id) sb ON sb.business_id=si.business_id AND sb.stock_item_id=si.stock_item_id"
        : '';
    $barcodeSelect=psr_table_exists($conn,'stock_barcodes')?"MAX(sb.barcode_value)":"''";
    $categoryJoin=psr_table_exists($conn,'categories')?"LEFT JOIN categories c ON c.business_id=si.business_id AND c.category_id=si.category_id":'';
    $brandJoin=psr_table_exists($conn,'brands')?"LEFT JOIN brands br ON br.business_id=si.business_id AND br.brand_id=si.brand_id":'';
    $categorySelect=psr_table_exists($conn,'categories')?"MAX(c.category_name)":"''";
    $brandSelect=psr_table_exists($conn,'brands')?"MAX(br.brand_name)":"''";
    $purchaseExpr=psr_column_exists($conn,'bill_items','purchase_rate')?'COALESCE(bi.purchase_rate,si.purchase_rate)':'si.purchase_rate';
    $discountExpr="COALESCE(bi.discount_amount, GREATEST(COALESCE(bi.mrp_rate,0)-COALESCE(bi.selling_rate,0),0)) * COALESCE(bi.qty,0)";
    $salesExpr="COALESCE(bi.mrp_rate,0)*COALESCE(bi.qty,0)";
    $netExpr="COALESCE(bi.amount,COALESCE(bi.selling_rate,0)*COALESCE(bi.qty,0))";
    $profitExpr="{$netExpr}-({$purchaseExpr}*COALESCE(bi.qty,0))";
    $key=psr_product_key_sql('si');
    $sql="SELECT {$key} product_key,
        MAX(si.article_no) product_code,{$barcodeSelect} barcode,MAX(si.article_name) product_name,
        {$categorySelect} category_name,{$brandSelect} brand_name,MAX(si.size) size,MAX(si.color) color,
        SUM(COALESCE(si.available_qty,0)) current_stock,
        COALESCE(SUM(CASE WHEN b.bill_id IS NOT NULL THEN bi.qty ELSE 0 END),0) total_qty_sold,
        COALESCE(SUM(CASE WHEN b.bill_id IS NOT NULL THEN {$salesExpr} ELSE 0 END),0) total_sales_amount,
        COALESCE(SUM(CASE WHEN b.bill_id IS NOT NULL THEN {$discountExpr} ELSE 0 END),0) total_discount,
        COALESCE(SUM(CASE WHEN b.bill_id IS NOT NULL THEN {$netExpr} ELSE 0 END),0) net_sales,
        COALESCE(SUM(CASE WHEN b.bill_id IS NOT NULL THEN {$profitExpr} ELSE 0 END),0) total_profit,
        MAX(CASE WHEN b.bill_id IS NOT NULL THEN b.bill_date ELSE NULL END) last_sold_date
        FROM stock_inward_items si
        {$categoryJoin} {$brandJoin} {$barcodeJoin}
        LEFT JOIN bill_items bi ON bi.business_id=si.business_id AND bi.stock_item_id=si.stock_item_id
        LEFT JOIN bills b ON b.business_id=bi.business_id AND b.branch_id=bi.branch_id AND b.bill_id=bi.bill_id {$billStatus} {$billDateSql}
        WHERE {$f['where']}
        GROUP BY {$key}";
    $types=$dateTypes.$f['types'];$params=array_merge($dateParams,$f['params']);
    return compact('sql','types','params','purchaseExpr','discountExpr','salesExpr','netExpr','profitExpr','billDateSql','billStatus');
}
function psr_sort(array $input): string
{
    $sort=(string)($input['sort_by']??'quantity');$dir=strtolower((string)($input['sort_dir']??'desc'))==='asc'?'ASC':'DESC';
    $column=['quantity'=>'total_qty_sold','sales'=>'net_sales','profit'=>'total_profit','name'=>'product_name'][$sort]??'total_qty_sold';
    if($sort==='name')return"CASE WHEN total_qty_sold=0 THEN 1 ELSE 0 END ASC, {$column} {$dir}, product_code ASC";
    return"CASE WHEN total_qty_sold=0 THEN 1 ELSE 0 END ASC, {$column} {$dir}, product_name ASC";
}
function psr_all_rows(mysqli $conn,int $businessId,int $userId,array $input): array
{
    $base=psr_base_sql($conn,$businessId,$userId,$input);
    return psr_rows($conn,"SELECT ranked.* FROM ({$base['sql']}) ranked ORDER BY ".psr_sort($input),$base['types'],$base['params']);
}
function psr_export(mysqli $conn,int $businessId,int $userId,array $input,string $type): never
{
    $rows=psr_all_rows($conn,$businessId,$userId,$input);
    $name='product-sales-ranking-'.date('Ymd-His');
    if($type==='csv'){
        header('Content-Type:text/csv; charset=utf-8');header("Content-Disposition:attachment; filename={$name}.csv");
        $o=fopen('php://output','w');fputcsv($o,['Rank','Product Code','Barcode','Product Name','Category','Brand','Size','Color','Current Stock','Total Quantity Sold','Total Sales Amount','Total Discount','Net Sales','Total Profit','Last Sold Date']);
        foreach($rows as $i=>$r)fputcsv($o,[$i+1,$r['product_code'],$r['barcode'],$r['product_name'],$r['category_name'],$r['brand_name'],$r['size'],$r['color'],$r['current_stock'],$r['total_qty_sold'],$r['total_sales_amount'],$r['total_discount'],$r['net_sales'],$r['total_profit'],$r['last_sold_date']]);
        fclose($o);exit;
    }
    header('Content-Type:application/vnd.ms-excel; charset=utf-8');header("Content-Disposition:attachment; filename={$name}.xls");
    echo '<table border="1"><tr><th>Rank</th><th>Product Code</th><th>Barcode</th><th>Product Name</th><th>Category</th><th>Brand</th><th>Size</th><th>Color</th><th>Current Stock</th><th>Total Quantity Sold</th><th>Total Sales Amount</th><th>Total Discount</th><th>Net Sales</th><th>Total Profit</th><th>Last Sold Date</th></tr>';
    foreach($rows as $i=>$r){echo'<tr>';foreach([$i+1,$r['product_code'],$r['barcode'],$r['product_name'],$r['category_name'],$r['brand_name'],$r['size'],$r['color'],$r['current_stock'],$r['total_qty_sold'],$r['total_sales_amount'],$r['total_discount'],$r['net_sales'],$r['total_profit'],$r['last_sold_date']] as $v)echo'<td>'.htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8').'</td>';echo'</tr>';}echo'</table>';exit;
}
try{
    psr_require_tables($conn);
    if($businessId<=0)throw new RuntimeException('Business session missing.');
    $action=(string)($_GET['action']??'list');
    if($action==='export_csv')psr_export($conn,$businessId,$userId,$_GET,'csv');
    if($action==='export_excel')psr_export($conn,$businessId,$userId,$_GET,'excel');
    if($action==='history'){
        $key=trim((string)($_GET['product_key']??''));if($key==='')throw new RuntimeException('Product key missing.');
        $base=psr_base_sql($conn,$businessId,$userId,$_GET);
        $product=psr_one($conn,"SELECT x.* FROM ({$base['sql']}) x WHERE x.product_key=?",$base['types'].'s',array_merge($base['params'],[$key]));
        if(!$product)throw new RuntimeException('Product not found.');
        [$categoryId,$brandId,$articleNo,$size,$color]=array_pad(explode('|',$key,5),5,'');
        [$from,$to]=psr_dates($_GET);$where=["bi.business_id=?","bi.article_no=?","COALESCE(bi.size,'')=?","COALESCE(si.color,'')=?","si.category_id=?","bi.brand_id=?"];
        $types='isssii';$params=[$businessId,$articleNo,$size,$color,(int)$categoryId,(int)$brandId];
        if($from!==''){$where[]='b.bill_date>=?';$types.='s';$params[]=$from;}if($to!==''){$where[]='b.bill_date<=?';$types.='s';$params[]=$to;}
        $branchId=(int)($_GET['branch_id']??0);if($branchId>0){$where[]='b.branch_id=?';$types.='i';$params[]=$branchId;}
        $purchase=psr_column_exists($conn,'bill_items','purchase_rate')?'COALESCE(bi.purchase_rate,si.purchase_rate)':'si.purchase_rate';
        $discount="COALESCE(bi.discount_amount,GREATEST(COALESCE(bi.mrp_rate,0)-COALESCE(bi.selling_rate,0),0))*bi.qty";
        $net="COALESCE(bi.amount,bi.selling_rate*bi.qty)";
        $history=psr_rows($conn,"SELECT b.bill_date,b.bill_time,b.bill_no,b.customer_name,br.branch_name,bi.qty,bi.mrp_rate,bi.selling_rate,{$discount} discount_amount_total,{$net} net_amount,({$net}-({$purchase}*bi.qty)) profit FROM bill_items bi INNER JOIN bills b ON b.business_id=bi.business_id AND b.branch_id=bi.branch_id AND b.bill_id=bi.bill_id LEFT JOIN stock_inward_items si ON si.business_id=bi.business_id AND si.stock_item_id=bi.stock_item_id LEFT JOIN branches br ON br.business_id=b.business_id AND br.branch_id=b.branch_id WHERE ".implode(' AND ',$where)." AND b.bill_status='active' ORDER BY b.bill_date DESC,b.bill_time DESC,b.bill_id DESC",$types,$params);
        psr_json(true,'',['product'=>$product,'summary'=>$product,'history'=>$history]);
    }
    $base=psr_base_sql($conn,$businessId,$userId,$_GET);
    $countRow=psr_one($conn,"SELECT COUNT(*) total FROM ({$base['sql']}) x",$base['types'],$base['params']);$total=(int)($countRow['total']??0);
    $page=max(1,(int)($_GET['page']??1));$per=max(1,min(500,(int)($_GET['per_page']??20)));$offset=($page-1)*$per;
    $rows=psr_rows($conn,"SELECT ranked.* FROM ({$base['sql']}) ranked ORDER BY ".psr_sort($_GET)." LIMIT {$per} OFFSET {$offset}",$base['types'],$base['params']);
    foreach($rows as $i=>&$row)$row['rank_no']=$offset+$i+1;unset($row);
    $summary=psr_one($conn,"SELECT COUNT(*) total_products,COALESCE(SUM(total_qty_sold),0) total_qty_sold,COALESCE(SUM(net_sales),0) net_sales,COALESCE(SUM(total_profit),0) total_profit FROM ({$base['sql']}) s",$base['types'],$base['params']);
    $extra=['rows'=>$rows,'summary'=>$summary,'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$per))]];
    if($action==='init')$extra['masters']=psr_master_data($conn,$businessId,$userId);
    psr_json(true,'',$extra);
}catch(Throwable $e){psr_json(false,$e->getMessage(),[],500);}
