<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/db.php';
require_once dirname(__DIR__).'/includes/functions.php';
require_once dirname(__DIR__).'/includes/auth.php';
require_once dirname(__DIR__).'/includes/csrf.php';
require_business_login();

$businessId=function_exists('current_business_id')?(int)current_business_id():(int)($_SESSION['business_id']??0);
$userId=(int)($_SESSION['user_id']??$_SESSION['id']??0);
$userName=$_SESSION['name']??$_SESSION['username']??'User';

function out(bool $ok,string $msg='',array $extra=[]):never{header('Content-Type: application/json; charset=utf-8');echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$extra),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function hasTable(mysqli $c,string $t):bool{$s=mysqli_real_escape_string($c,$t);$r=mysqli_query($c,"SHOW TABLES LIKE '{$s}'");return $r&&mysqli_num_rows($r)>0;}
function hasCol(mysqli $c,string $t,string $col):bool{$t=preg_replace('/[^a-zA-Z0-9_]/','',$t);$s=mysqli_real_escape_string($c,$col);$r=mysqli_query($c,"SHOW COLUMNS FROM `{$t}` LIKE '{$s}'");return $r&&mysqli_num_rows($r)>0;}
function bootstrapTables(mysqli $c):void{
mysqli_query($c,"CREATE TABLE IF NOT EXISTS business_expense_categories(
category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
business_id INT UNSIGNED NOT NULL,
category_name VARCHAR(150) NOT NULL,
is_active TINYINT(1) NOT NULL DEFAULT 1,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
UNIQUE KEY uq_expense_category(business_id,category_name),
KEY idx_expense_category_business(business_id,is_active)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
mysqli_query($c,"CREATE TABLE IF NOT EXISTS business_expenses(
expense_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
business_id INT UNSIGNED NOT NULL,
branch_id INT UNSIGNED NOT NULL,
category_id INT UNSIGNED NOT NULL,
expense_no VARCHAR(60) NOT NULL,
transaction_no VARCHAR(60) NULL,
expense_date DATE NOT NULL,
expense_name VARCHAR(200) NOT NULL,
description TEXT NULL,
amount DECIMAL(14,2) NOT NULL DEFAULT 0,
payment_method ENUM('Cash','UPI','Card','Bank') NOT NULL DEFAULT 'Cash',
paid_to VARCHAR(200) NULL,
reference_number VARCHAR(150) NULL,
receipt_path VARCHAR(500) NULL,
remarks TEXT NULL,
status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
created_by INT UNSIGNED NOT NULL DEFAULT 0,
created_by_name VARCHAR(150) NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_by INT UNSIGNED NULL,
updated_at DATETIME NULL,
deleted_at DATETIME NULL,
is_deleted TINYINT(1) NOT NULL DEFAULT 0,
recurring_enabled TINYINT(1) NOT NULL DEFAULT 0,
approval_status VARCHAR(30) NOT NULL DEFAULT 'not_required',
approved_by INT UNSIGNED NULL,
approved_at DATETIME NULL,
vendor_id INT UNSIGNED NULL,
UNIQUE KEY uq_business_expense_no(business_id,expense_no),
KEY idx_expense_transaction(business_id,transaction_no),
KEY idx_expense_business_date(business_id,expense_date),
KEY idx_expense_branch(business_id,branch_id),
KEY idx_expense_category(business_id,category_id),
KEY idx_expense_method(business_id,payment_method),
KEY idx_expense_status(business_id,status,is_deleted)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function seedCategories(mysqli $c,int $b):void{$names=['Rent','Electricity','Water','Salary','Transport','Maintenance','Purchase','Marketing','Food','Internet','Telephone','Office Supplies','Miscellaneous'];$s=mysqli_prepare($c,"INSERT IGNORE INTO business_expense_categories(business_id,category_name)VALUES(?,?)");foreach($names as $n){mysqli_stmt_bind_param($s,'is',$b,$n);mysqli_stmt_execute($s);}mysqli_stmt_close($s);}
function branchMeta(mysqli $c):?array{
foreach(['shops','business_branches','branches'] as $table){
 if(!hasTable($c,$table)||!hasCol($c,$table,'business_id'))continue;
 $id=null;foreach(['id','shop_id','branch_id'] as $candidate){if(hasCol($c,$table,$candidate)){$id=$candidate;break;}}
 $name=null;foreach(['shop_name','branch_name','name','firm_name'] as $candidate){if(hasCol($c,$table,$candidate)){$name=$candidate;break;}}
 if($id&&$name)return[$table,$id,$name];
}
return null;
}
function branches(mysqli $c,int $b):array{$meta=branchMeta($c);if(!$meta)return[['branch_id'=>1,'branch_name'=>'Main Branch']];[$t,$id,$name]=$meta;$active=hasCol($c,$t,'is_active')?' AND is_active=1':'';$q="SELECT `{$id}` branch_id,`{$name}` branch_name FROM `{$t}` WHERE business_id=?{$active} ORDER BY `{$name}`";$s=mysqli_prepare($c,$q);if(!$s)return[];mysqli_stmt_bind_param($s,'i',$b);mysqli_stmt_execute($s);$rows=mysqli_fetch_all(mysqli_stmt_get_result($s),MYSQLI_ASSOC);mysqli_stmt_close($s);return$rows?:[['branch_id'=>1,'branch_name'=>'Main Branch']];}
function categories(mysqli $c,int $b):array{$s=mysqli_prepare($c,"SELECT category_id,category_name FROM business_expense_categories WHERE business_id=? AND is_active=1 ORDER BY category_name");mysqli_stmt_bind_param($s,'i',$b);mysqli_stmt_execute($s);$r=mysqli_fetch_all(mysqli_stmt_get_result($s),MYSQLI_ASSOC);mysqli_stmt_close($s);return $r;}
function branchExpr(mysqli $c):string{$meta=branchMeta($c);if(!$meta)return "CONCAT('Branch #',e.branch_id)";[$t,$id,$name]=$meta;return "(SELECT `{$name}` FROM `{$t}` b WHERE b.`{$id}`=e.branch_id LIMIT 1)";}
function filters():array{return['search'=>trim((string)($_GET['search']??'')),'date_from'=>trim((string)($_GET['date_from']??'')),'date_to'=>trim((string)($_GET['date_to']??'')),'branch_id'=>(int)($_GET['branch_id']??0),'category_id'=>(int)($_GET['category_id']??0),'payment_method'=>trim((string)($_GET['payment_method']??'')),'status'=>trim((string)($_GET['status']??''))];}
function whereSql(mysqli $c,int $b,array $f):string{$w=["e.business_id={$b}","e.is_deleted=0"];if($f['search']!==''){$s=mysqli_real_escape_string($c,$f['search']);$w[]="(e.expense_no LIKE '%{$s}%' OR e.expense_name LIKE '%{$s}%' OR e.paid_to LIKE '%{$s}%' OR e.reference_number LIKE '%{$s}%' OR e.description LIKE '%{$s}%')";}if($f['date_from']!==''){$d=mysqli_real_escape_string($c,$f['date_from']);$w[]="e.expense_date>='{$d}'";}if($f['date_to']!==''){$d=mysqli_real_escape_string($c,$f['date_to']);$w[]="e.expense_date<='{$d}'";}if($f['branch_id']>0)$w[]="e.branch_id=".(int)$f['branch_id'];if($f['category_id']>0)$w[]="e.category_id=".(int)$f['category_id'];if(in_array($f['payment_method'],['Cash','UPI','Card','Bank'],true)){$m=mysqli_real_escape_string($c,$f['payment_method']);$w[]="e.payment_method='{$m}'";}if(in_array($f['status'],['active','cancelled'],true)){$s=mysqli_real_escape_string($c,$f['status']);$w[]="e.status='{$s}'";}return implode(' AND ',$w);}
function rows(mysqli $c,int $b,array $f,?int $limit=null,?int $offset=null):array{$w=whereSql($c,$b,$f);$be=branchExpr($c);$q="SELECT e.*,c.category_name,{$be} branch_name FROM business_expenses e LEFT JOIN business_expense_categories c ON c.category_id=e.category_id WHERE {$w} ORDER BY e.expense_date DESC,e.expense_id DESC";if($limit!==null)$q.=" LIMIT ".(int)$limit." OFFSET ".(int)$offset;$r=mysqli_query($c,$q);return$r?mysqli_fetch_all($r,MYSQLI_ASSOC):[];}
function stats(mysqli $c,int $b):array{$today=date('Y-m-d');$month=date('Y-m');$q="SELECT SUM(CASE WHEN expense_date='{$today}' AND status='active' THEN amount ELSE 0 END)today_amount,SUM(CASE WHEN expense_date='{$today}' AND status='active' THEN 1 ELSE 0 END)today_count,SUM(CASE WHEN DATE_FORMAT(expense_date,'%Y-%m')='{$month}' AND status='active' THEN amount ELSE 0 END)month_amount,SUM(CASE WHEN DATE_FORMAT(expense_date,'%Y-%m')='{$month}' AND status='active' THEN 1 ELSE 0 END)month_count,SUM(CASE WHEN status='active' THEN amount ELSE 0 END)active_amount,SUM(CASE WHEN status='active' THEN 1 ELSE 0 END)active_count,SUM(amount)total_amount,COUNT(*)total_count FROM business_expenses WHERE business_id={$b} AND is_deleted=0";$r=mysqli_query($c,$q);$x=$r?mysqli_fetch_assoc($r):[];return array_map(fn($v)=>$v??0,$x?:[]);}
function reports(mysqli $c,int $b,array $f):array{$w=whereSql($c,$b,$f);$base=mysqli_fetch_assoc(mysqli_query($c,"SELECT COALESCE(SUM(e.amount),0)range_amount,COUNT(*)range_count FROM business_expenses e WHERE {$w}"))?:[];$cat=mysqli_fetch_assoc(mysqli_query($c,"SELECT c.category_name top_category_name,SUM(e.amount)top_category_amount FROM business_expenses e LEFT JOIN business_expense_categories c ON c.category_id=e.category_id WHERE {$w} GROUP BY e.category_id ORDER BY top_category_amount DESC LIMIT 1"))?:[];$be=branchExpr($c);$br=mysqli_fetch_assoc(mysqli_query($c,"SELECT {$be} top_branch_name,SUM(e.amount)top_branch_amount FROM business_expenses e WHERE {$w} GROUP BY e.branch_id ORDER BY top_branch_amount DESC LIMIT 1"))?:[];$pm=mysqli_fetch_assoc(mysqli_query($c,"SELECT e.payment_method top_method_name,SUM(e.amount)top_method_amount FROM business_expenses e WHERE {$w} GROUP BY e.payment_method ORDER BY top_method_amount DESC LIMIT 1"))?:[];return array_merge(['range_amount'=>0,'range_count'=>0,'top_category_name'=>'','top_category_amount'=>0,'top_branch_name'=>'','top_branch_amount'=>0,'top_method_name'=>'','top_method_amount'=>0],$base,$cat,$br,$pm);}
function nextNo(mysqli $c,int $b,string $date):string{$p='EXP-'.date('Ym',strtotime($date)).'-';$s=mysqli_prepare($c,"SELECT MAX(CAST(SUBSTRING_INDEX(expense_no,'-',-1) AS UNSIGNED))m FROM business_expenses WHERE business_id=? AND expense_no LIKE CONCAT(?, '%')");mysqli_stmt_bind_param($s,'is',$b,$p);mysqli_stmt_execute($s);$r=mysqli_fetch_assoc(mysqli_stmt_get_result($s));mysqli_stmt_close($s);return$p.str_pad((string)(((int)($r['m']??0))+1),5,'0',STR_PAD_LEFT);}
bootstrapTables($conn);if(!hasCol($conn,'business_expenses','transaction_no')){mysqli_query($conn,"ALTER TABLE business_expenses ADD COLUMN transaction_no VARCHAR(60) NULL AFTER expense_no, ADD KEY idx_expense_transaction(business_id,transaction_no)");}seedCategories($conn,$businessId);
$action=(string)($_GET['action']??$_POST['action']??'list');

if($_SERVER['REQUEST_METHOD']==='GET'){
$f=filters();
if($action==='get'){$id=(int)($_GET['expense_id']??0);$r=mysqli_query($conn,"SELECT e.*,c.category_name,".branchExpr($conn)." branch_name FROM business_expenses e LEFT JOIN business_expense_categories c ON c.category_id=e.category_id WHERE e.business_id={$businessId} AND e.expense_id={$id} AND e.is_deleted=0 LIMIT 1");$e=$r?mysqli_fetch_assoc($r):null;if(!$e)out(false,'Expense not found.');out(true,'',['expense'=>$e]);}
if($action==='export_excel'){$rs=rows($conn,$businessId,$f);header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition:attachment; filename="expense-report-'.date('Ymd-His').'.csv"');$o=fopen('php://output','w');fputcsv($o,['Expense No','Date','Branch','Category','Expense Name','Description','Amount','Payment Method','Paid To','Reference No','Status','Created By','Created At']);foreach($rs as $r)fputcsv($o,[$r['expense_no'],$r['expense_date'],$r['branch_name'],$r['category_name'],$r['expense_name'],$r['description'],$r['amount'],$r['payment_method'],$r['paid_to'],$r['reference_number'],$r['status'],$r['created_by_name'],$r['created_at']]);fclose($o);exit;}
if($action==='report_data')out(true,'',['expenses'=>rows($conn,$businessId,$f)]);
$page=max(1,(int)($_GET['page']??1));$per=max(1,min(100,(int)($_GET['per_page']??20)));$w=whereSql($conn,$businessId,$f);$count=(int)(mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*)c FROM business_expenses e WHERE {$w}"))['c']??0);$extra=['expenses'=>rows($conn,$businessId,$f,$per,($page-1)*$per),'stats'=>stats($conn,$businessId),'reports'=>reports($conn,$businessId,$f),'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$count,'total_pages'=>max(1,(int)ceil($count/$per))]];if($action==='init')$extra['masters']=['branches'=>branches($conn,$businessId),'categories'=>categories($conn,$businessId)];out(true,'',$extra);
}
if(function_exists('csrf_verify')){try{csrf_verify();}catch(Throwable $e){out(false,'Invalid security token.');}}


function saveExpenseUpload(string $field,int $businessId):?string{
    if($field===''||!isset($_FILES[$field])||($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    if($_FILES[$field]['error']!==UPLOAD_ERR_OK)out(false,'Attachment upload failed for '.$field.'.');
    if($_FILES[$field]['size']>5*1024*1024)out(false,'Each attachment must be below 5 MB.');
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    if(!isset($allowed[$mime]))out(false,'Only JPG, PNG, WEBP and PDF attachments are allowed.');
    $dir=dirname(__DIR__).'/uploads/expenses/'.date('Y/m');
    if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))out(false,'Unable to create expense attachment folder.');
    $fn='EXP-'.$businessId.'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
    if(!move_uploaded_file($_FILES[$field]['tmp_name'],$dir.'/'.$fn))out(false,'Unable to save expense attachment.');
    return 'uploads/expenses/'.date('Y/m').'/'.$fn;
}
if($action==='save'){
$id=(int)($_POST['expense_id']??0);$date=trim((string)($_POST['expense_date']??''));$branch=(int)($_POST['branch_id']??0);$method=trim((string)($_POST['payment_method']??''));$paid=trim((string)($_POST['paid_to']??''));$ref=trim((string)($_POST['reference_number']??''));$remarks=trim((string)($_POST['remarks']??''));$status=trim((string)($_POST['status']??'active'));$items=json_decode((string)($_POST['items_json']??'[]'),true);
if(!$date)out(false,'Expense date is required.');
if(!in_array($status,['active','cancelled'],true))$status='active';
if(!is_array($items)||count($items)<1)out(false,'Add at least one expense item.');
$clean=[];
foreach($items as $i=>$item){
    $cat=(int)($item['category_id']??0);
    $name=trim((string)($item['expense_name']??''));
    $desc=trim((string)($item['description']??''));
    $amount=(float)($item['amount']??0);
    $lineMethod=trim((string)($item['payment_method']??$method));
    $lineBranch=(int)($item['branch_id']??$branch);
    $fileField=preg_replace('/[^a-zA-Z0-9_]/','',(string)($item['file_field_name']??''));
    if($cat<=0||$name===''||$amount<=0||$lineBranch<=0||!in_array($lineMethod,['Cash','UPI','Card','Bank'],true)){
        out(false,'Line '.($i+1).': shared category, shared payment method, shared branch, expense name and amount are required.');
    }
    $clean[]=[
        'category_id'=>$cat,
        'expense_name'=>$name,
        'description'=>$desc,
        'amount'=>$amount,
        'payment_method'=>$lineMethod,
        'branch_id'=>$lineBranch,
        'file_field_name'=>$fileField
    ];
}
$receipt=saveExpenseUpload('receipt_file',$businessId);
if($id>0){$item=$clean[0];$sql="UPDATE business_expenses SET branch_id=?,category_id=?,expense_date=?,expense_name=?,description=?,amount=?,payment_method=?,paid_to=?,reference_number=?,remarks=?,status=?,updated_by=?,updated_at=NOW()";$types='iisssdsssssi';$params=[$item['branch_id'],$item['category_id'],$date,$item['expense_name'],$item['description'],$item['amount'],$item['payment_method'],$paid,$ref,$remarks,$status,$userId];if($receipt!==null){$sql.=",receipt_path=?";$types.='s';$params[]=$receipt;}$sql.=" WHERE expense_id=? AND business_id=? AND is_deleted=0";$types.='ii';$params[]=$id;$params[]=$businessId;$s=mysqli_prepare($conn,$sql);if(!$s)out(false,'Unable to prepare expense update.');mysqli_stmt_bind_param($s,$types,...$params);$ok=mysqli_stmt_execute($s);mysqli_stmt_close($s);out($ok,$ok?'Expense updated successfully.':'Unable to update expense.');}
$transactionNo=count($clean)>1?'EXT-'.date('YmdHis').'-'.str_pad((string)random_int(1,9999),4,'0',STR_PAD_LEFT):null;
mysqli_begin_transaction($conn);
try{$s=mysqli_prepare($conn,"INSERT INTO business_expenses(business_id,branch_id,category_id,expense_no,transaction_no,expense_date,expense_name,description,amount,payment_method,paid_to,reference_number,receipt_path,remarks,status,created_by,created_by_name)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");if(!$s)throw new RuntimeException(mysqli_error($conn));$created=[];foreach($clean as $item){$no=nextNo($conn,$businessId,$date);$lineReceipt=$item['file_field_name']!==''?saveExpenseUpload($item['file_field_name'],$businessId):null;if($lineReceipt===null&&count($clean)===1)$lineReceipt=$receipt;mysqli_stmt_bind_param($s,'iiisssssdssssssis',$businessId,$item['branch_id'],$item['category_id'],$no,$transactionNo,$date,$item['expense_name'],$item['description'],$item['amount'],$item['payment_method'],$paid,$ref,$lineReceipt,$remarks,$status,$userId,$userName);if(!mysqli_stmt_execute($s))throw new RuntimeException(mysqli_stmt_error($s));$created[]=['expense_id'=>mysqli_insert_id($conn),'expense_no'=>$no];}mysqli_stmt_close($s);mysqli_commit($conn);out(true,count($created)>1?count($created).' expenses saved successfully.':'Expense saved successfully.',['transaction_no'=>$transactionNo,'created_expenses'=>$created]);}catch(Throwable $e){mysqli_rollback($conn);out(false,'Unable to save expense: '.$e->getMessage());}
}
if($action==='delete'){$id=(int)($_POST['expense_id']??0);if($id<=0)out(false,'Invalid expense.');$s=mysqli_prepare($conn,"UPDATE business_expenses SET is_deleted=1,deleted_at=NOW(),updated_by=?,updated_at=NOW() WHERE expense_id=? AND business_id=?");mysqli_stmt_bind_param($s,'iii',$userId,$id,$businessId);$ok=mysqli_stmt_execute($s);mysqli_stmt_close($s);out($ok,$ok?'Expense deleted successfully.':'Unable to delete expense.');}
out(false,'Invalid action.');
