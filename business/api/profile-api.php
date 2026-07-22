<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/permissions.php';
require_once __DIR__.'/../includes/csrf.php';
if(function_exists('require_business_login')) require_business_login();

function pf_json(array $p,int $c=200):void{http_response_code($c);header('Content-Type: application/json; charset=utf-8');echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function pf_uid():int{return function_exists('current_user_id')?(int)current_user_id():(int)($_SESSION['user_id']??$_SESSION['business_user_id']??$_SESSION['id']??0);}
function pf_bid():int{return function_exists('current_business_id')?(int)current_business_id():(int)($_SESSION['business_id']??0);}
function pf_column_exists(mysqli $c,string $table,string $column):bool{
 $s=mysqli_prepare($c,"SELECT COUNT(*) total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
 mysqli_stmt_bind_param($s,'ss',$table,$column);mysqli_stmt_execute($s);$r=mysqli_fetch_assoc(mysqli_stmt_get_result($s));mysqli_stmt_close($s);return (int)($r['total']??0)>0;
}
function pf_ensure_profile_columns(mysqli $c):void{
 $columns=[
  'profile_image'=>"VARCHAR(255) DEFAULT NULL",
  'designation'=>"VARCHAR(120) DEFAULT NULL",
  'date_of_birth'=>"DATE DEFAULT NULL",
  'gender'=>"VARCHAR(30) DEFAULT NULL",
  'emergency_contact'=>"VARCHAR(30) DEFAULT NULL",
  'profile_address'=>"TEXT DEFAULT NULL",
  'profile_bio'=>"TEXT DEFAULT NULL",
  'profile_extra'=>"LONGTEXT DEFAULT NULL"
 ];
 foreach($columns as $name=>$type){if(!pf_column_exists($c,'users',$name)){mysqli_query($c,"ALTER TABLE users ADD COLUMN `$name` $type");}}
}
function pf_delete_old_image(?string $path):void{
 if(!$path||strpos($path,'uploads/profile/')!==0)return;
 $full=dirname(__DIR__).'/'.$path;if(is_file($full))@unlink($full);
}

function pf_csrf():void{
 if($_SERVER['REQUEST_METHOD']!=='POST')return;
 $posted=(string)($_POST['csrf_token']??$_POST['_token']??'');
 $session=(string)($_SESSION['business_csrf_token']??$_SESSION['csrf_token']??'');
 if($session!==''&&$posted!==''&&hash_equals($session,$posted))return;
 if(function_exists('csrf_token')){$expected=(string)csrf_token();if($posted!==''&&hash_equals($expected,$posted))return;}
 pf_json(['success'=>false,'message'=>'Invalid security token. Please refresh and try again.'],403);
}
function pf_profile(mysqli $c,int $bid,int $uid):array{
 $sql="SELECT u.user_id,u.business_id,u.default_branch_id,u.role_id,
 COALESCE(NULLIF(u.full_name,''),u.name) display_name,u.name,u.mobile,u.email,u.username,
 u.last_login_at,u.status,u.created_at,u.updated_at,u.profile_image,u.designation,u.date_of_birth,u.gender,u.emergency_contact,u.profile_address,u.profile_bio,u.profile_extra,
 b.business_name,b.business_code,b.logo_path,
 r.role_name,r.role_type,
 br.branch_name default_branch_name,br.floor_name default_floor_name
 FROM users u JOIN businesses b ON b.business_id=u.business_id
 LEFT JOIN roles r ON r.role_id=u.role_id AND r.business_id=u.business_id
 LEFT JOIN branches br ON br.branch_id=u.default_branch_id AND br.business_id=u.business_id
 WHERE u.user_id=? AND u.business_id=? LIMIT 1";
 $s=mysqli_prepare($c,$sql);if(!$s)throw new RuntimeException(mysqli_error($c));
 mysqli_stmt_bind_param($s,'ii',$uid,$bid);mysqli_stmt_execute($s);$r=mysqli_fetch_assoc(mysqli_stmt_get_result($s));mysqli_stmt_close($s);
 if(!$r)throw new RuntimeException('Profile not found.');
 return $r;
}
function pf_branches(mysqli $c,int $bid,int $uid,array $p):array{
 $s=mysqli_prepare($c,"SELECT DISTINCT b.branch_id,b.branch_name,b.floor_name,b.branch_code
 FROM user_branch_access uba JOIN branches b ON b.branch_id=uba.branch_id AND b.business_id=uba.business_id
 WHERE uba.business_id=? AND uba.user_id=? AND uba.access_status=1 AND b.status=1 ORDER BY b.branch_name,b.floor_name");
 mysqli_stmt_bind_param($s,'ii',$bid,$uid);mysqli_stmt_execute($s);$rows=[];$rs=mysqli_stmt_get_result($s);while($x=mysqli_fetch_assoc($rs))$rows[]=$x;mysqli_stmt_close($s);
 if(!$rows&&!empty($p['default_branch_id']))$rows[]=['branch_id'=>$p['default_branch_id'],'branch_name'=>$p['default_branch_name']??'-','floor_name'=>$p['default_floor_name']??null,'branch_code'=>''];
 return $rows;
}
function pf_log(mysqli $c,string $action,int $uid,$old,$new):void{
 if(function_exists('log_activity')){try{log_activity($c,'My Profile',$action,$uid,$old,$new);}catch(Throwable $e){}}
}
try{
 pf_ensure_profile_columns($conn);
 $uid=pf_uid();$bid=pf_bid();if($uid<=0||$bid<=0)pf_json(['success'=>false,'message'=>'Login session missing.'],401);
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='GET'){
  $p=pf_profile($conn,$bid,$uid);$branches=pf_branches($conn,$bid,$uid,$p);
  $scope=$branches?'Assigned branches':(($p['role_type']??'')==='admin'?'All branches / business level':'Business level');
  pf_json(['success'=>true,'data'=>['profile'=>$p,'branches'=>$branches,'access_scope'=>$scope]]);
 }
 if($method!=='POST')pf_json(['success'=>false,'message'=>'Method not allowed.'],405);
 pf_csrf();$action=(string)($_POST['action']??'');
 if($action==='update_profile'){
  $old=pf_profile($conn,$bid,$uid);
  $name=trim((string)($_POST['name']??''));$mobile=trim((string)($_POST['mobile']??''));$email=trim((string)($_POST['email']??''));$designation=trim((string)($_POST['designation']??''));$dob=trim((string)($_POST['date_of_birth']??''));$gender=trim((string)($_POST['gender']??''));$emergency=trim((string)($_POST['emergency_contact']??''));$address=trim((string)($_POST['profile_address']??''));$bio=trim((string)($_POST['profile_bio']??''));
  $len=function_exists('mb_strlen')?mb_strlen($name):strlen($name);
  if($len<2||$len>150)pf_json(['success'=>false,'message'=>'Name must contain 2 to 150 characters.'],422);
  if($mobile!==''&&!preg_match('/^[0-9+\-\s()]{6,20}$/',$mobile))pf_json(['success'=>false,'message'=>'Enter a valid mobile number.'],422);
  if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))pf_json(['success'=>false,'message'=>'Enter a valid email address.'],422);
  if($dob!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dob))pf_json(['success'=>false,'message'=>'Invalid date of birth.'],422);
  $allowedGender=['','male','female','other','prefer_not_to_say'];if(!in_array($gender,$allowedGender,true))pf_json(['success'=>false,'message'=>'Invalid gender value.'],422);
  $s=mysqli_prepare($conn,"UPDATE users SET name=?,full_name=?,mobile=NULLIF(?,''),email=NULLIF(?,''),designation=NULLIF(?,''),date_of_birth=NULLIF(?,''),gender=NULLIF(?,''),emergency_contact=NULLIF(?,''),profile_address=NULLIF(?,''),profile_bio=NULLIF(?,''),updated_at=NOW() WHERE user_id=? AND business_id=?");
  mysqli_stmt_bind_param($s,'ssssssssssii',$name,$name,$mobile,$email,$designation,$dob,$gender,$emergency,$address,$bio,$uid,$bid);mysqli_stmt_execute($s);
  if(mysqli_stmt_affected_rows($s)<0)throw new RuntimeException(mysqli_stmt_error($s));mysqli_stmt_close($s);
  $_SESSION['name']=$name;$_SESSION['full_name']=$name;$_SESSION['display_name']=$name;
  pf_log($conn,'update_profile',$uid,['name'=>$old['display_name'],'mobile'=>$old['mobile'],'email'=>$old['email']],['name'=>$name,'mobile'=>$mobile,'email'=>$email]);
  pf_json(['success'=>true,'message'=>'Profile updated successfully.']);
 }

 if($action==='upload_profile_image'){
  if(empty($_FILES['profile_image'])||!is_array($_FILES['profile_image']))pf_json(['success'=>false,'message'=>'Select an image.'],422);
  $f=$_FILES['profile_image'];if((int)$f['error']!==UPLOAD_ERR_OK)pf_json(['success'=>false,'message'=>'Image upload failed.'],422);
  if((int)$f['size']<=0||(int)$f['size']>10*1024*1024)pf_json(['success'=>false,'message'=>'Image must be 10 MB or smaller.'],422);
  $mime='';
  if(function_exists('finfo_open')){$fi=finfo_open(FILEINFO_MIME_TYPE);$mime=(string)finfo_file($fi,$f['tmp_name']);finfo_close($fi);}
  if($mime===''&&function_exists('mime_content_type'))$mime=(string)mime_content_type($f['tmp_name']);
  $allowed=[
   'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/bmp'=>'bmp','image/x-ms-bmp'=>'bmp',
   'image/avif'=>'avif','image/heic'=>'heic','image/heif'=>'heif','image/tiff'=>'tiff','image/x-tiff'=>'tiff'
  ];
  if(!isset($allowed[$mime]))pf_json(['success'=>false,'message'=>'Unsupported image format. Use JPG, PNG, GIF, WebP, BMP, AVIF, HEIC/HEIF or TIFF.'],422);
  $dir=dirname(__DIR__).'/uploads/profile';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Unable to create profile upload directory.');
  $old=pf_profile($conn,$bid,$uid);$name='user-'.$uid.'-'.bin2hex(random_bytes(8)).'.'.$allowed[$mime];$full=$dir.'/'.$name;
  if(!move_uploaded_file($f['tmp_name'],$full))throw new RuntimeException('Unable to save uploaded image.');
  $relative='uploads/profile/'.$name;$s=mysqli_prepare($conn,"UPDATE users SET profile_image=?,updated_at=NOW() WHERE user_id=? AND business_id=?");mysqli_stmt_bind_param($s,'sii',$relative,$uid,$bid);mysqli_stmt_execute($s);mysqli_stmt_close($s);
  pf_delete_old_image($old['profile_image']??null);$_SESSION['profile_image']=$relative;pf_log($conn,'upload_profile_image',$uid,['profile_image'=>$old['profile_image']??null],['profile_image'=>$relative]);
  pf_json(['success'=>true,'message'=>'Profile photo updated successfully.','profile_image'=>$relative]);
 }

 if($action==='remove_profile_image'){
  $old=pf_profile($conn,$bid,$uid);
  $s=mysqli_prepare($conn,"UPDATE users SET profile_image=NULL,updated_at=NOW() WHERE user_id=? AND business_id=?");
  mysqli_stmt_bind_param($s,'ii',$uid,$bid);mysqli_stmt_execute($s);mysqli_stmt_close($s);
  pf_delete_old_image($old['profile_image']??null);unset($_SESSION['profile_image']);
  pf_log($conn,'remove_profile_image',$uid,['profile_image'=>$old['profile_image']??null],['profile_image'=>null]);
  pf_json(['success'=>true,'message'=>'Profile photo removed successfully.']);
 }

 if($action==='change_password'){
  $current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
  if($current==='')pf_json(['success'=>false,'message'=>'Enter your current password.'],422);
  if(strlen($new)<8)pf_json(['success'=>false,'message'=>'New password must contain at least 8 characters.'],422);
  if($new!==$confirm)pf_json(['success'=>false,'message'=>'New password and confirmation do not match.'],422);
  $s=mysqli_prepare($conn,"SELECT password FROM users WHERE user_id=? AND business_id=? LIMIT 1");mysqli_stmt_bind_param($s,'ii',$uid,$bid);mysqli_stmt_execute($s);$row=mysqli_fetch_assoc(mysqli_stmt_get_result($s));mysqli_stmt_close($s);
  if(!$row||!password_verify($current,(string)$row['password']))pf_json(['success'=>false,'message'=>'Current password is incorrect.'],422);
  if(password_verify($new,(string)$row['password']))pf_json(['success'=>false,'message'=>'New password must be different from current password.'],422);
  $hash=password_hash($new,PASSWORD_DEFAULT);$s=mysqli_prepare($conn,"UPDATE users SET password=?,password_reset_required=0,updated_at=NOW() WHERE user_id=? AND business_id=?");mysqli_stmt_bind_param($s,'sii',$hash,$uid,$bid);mysqli_stmt_execute($s);mysqli_stmt_close($s);
  $_SESSION['password_reset_required']=0;pf_log($conn,'change_password',$uid,null,['password_changed'=>true]);
  pf_json(['success'=>true,'message'=>'Password changed successfully.']);
 }
 pf_json(['success'=>false,'message'=>'Invalid action.'],400);
}catch(Throwable $e){error_log('Profile API: '.$e->getMessage());pf_json(['success'=>false,'message'=>'Unable to process profile request.'],500);}
