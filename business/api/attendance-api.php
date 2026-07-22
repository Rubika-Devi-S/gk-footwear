<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

date_default_timezone_set('Asia/Kolkata');

function att_json(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function att_payload(): array {
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $d = json_decode(file_get_contents('php://input') ?: '{}', true);
        return is_array($d) ? $d : [];
    }
    return $_POST;
}
function att_admin(mysqli $conn): void {
    if (!is_business_admin($conn)) att_json(['ok'=>false,'message'=>'Admin permission required.'],403);
}
function att_rows(mysqli $conn, mysqli_stmt $stmt): array {
    mysqli_stmt_execute($stmt);
    $rs=mysqli_stmt_get_result($stmt);$rows=[];
    while($r=mysqli_fetch_assoc($rs))$rows[]=$r;
    mysqli_stmt_close($stmt);return $rows;
}
function att_recalculate(mysqli $conn,int $businessId,int $employeeId,string $date): void {
    $stmt=mysqli_prepare($conn,"SELECT ae.branch_id,ae.default_shift_id,s.* FROM attendance_employees ae LEFT JOIN attendance_shifts s ON s.shift_id=ae.default_shift_id AND s.business_id=ae.business_id WHERE ae.business_id=? AND ae.attendance_employee_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt,'ii',$businessId,$employeeId);mysqli_stmt_execute($stmt);
    $emp=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));mysqli_stmt_close($stmt);if(!$emp)return;

    $stmt=mysqli_prepare($conn,"SELECT punch_time FROM attendance_raw_logs WHERE business_id=? AND attendance_employee_id=? AND DATE(punch_time)=? ORDER BY punch_time");
    mysqli_stmt_bind_param($stmt,'iis',$businessId,$employeeId,$date);
    $logs=att_rows($conn,$stmt);$first=$logs[0]['punch_time']??null;$last=count($logs)>1?$logs[count($logs)-1]['punch_time']:null;
    $work=$late=$early=$ot=0;$status='ABSENT';
    if($first&&$last){
        $in=new DateTimeImmutable($first);$out=new DateTimeImmutable($last);
        $work=max(0,(int)(($out->getTimestamp()-$in->getTimestamp())/60));
        $half=(int)($emp['minimum_half_day_minutes']??240);$full=(int)($emp['minimum_full_day_minutes']??480);
        $status=$work>=$full?'PRESENT':($work>=$half?'HALF_DAY':'MISSING_PUNCH');
        if(!empty($emp['start_time'])){
            $ss=new DateTimeImmutable($date.' '.$emp['start_time']);
            $late=max(0,(int)(($in->getTimestamp()-$ss->getTimestamp())/60)-(int)$emp['grace_in_minutes']);
            $endDate=(int)$emp['crosses_midnight']===1?(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d'):$date;
            $se=new DateTimeImmutable($endDate.' '.$emp['end_time']);
            $early=max(0,(int)(($se->getTimestamp()-$out->getTimestamp())/60)-(int)$emp['grace_out_minutes']);
            $ot=max(0,$work-$full-(int)$emp['overtime_after_minutes']);
        }
    }elseif($first){$status='MISSING_PUNCH';}

    $branch=(int)($emp['branch_id']??0);$shift=(int)($emp['default_shift_id']??0);
    $stmt=mysqli_prepare($conn,"INSERT INTO attendance_daily(business_id,branch_id,attendance_employee_id,attendance_date,shift_id,first_check_in,last_check_out,total_work_minutes,late_minutes,early_exit_minutes,overtime_minutes,status,source)
    VALUES(?,?,?, ?,NULLIF(?,0),?,?,?,?,?,?,?,'BIOMETRIC')
    ON DUPLICATE KEY UPDATE branch_id=VALUES(branch_id),shift_id=VALUES(shift_id),first_check_in=VALUES(first_check_in),last_check_out=VALUES(last_check_out),total_work_minutes=VALUES(total_work_minutes),late_minutes=VALUES(late_minutes),early_exit_minutes=VALUES(early_exit_minutes),overtime_minutes=VALUES(overtime_minutes),status=VALUES(status),source='BIOMETRIC'");
    mysqli_stmt_bind_param($stmt,'iiisissiiiis',$businessId,$branch,$employeeId,$date,$shift,$first,$last,$work,$late,$early,$ot,$status);
    mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
}

$action=$_GET['action']??$_POST['action']??'';

if($action==='device_push'){
    $d=att_payload();$code=trim((string)($d['device_code']??$_SERVER['HTTP_X_DEVICE_CODE']??''));$token=trim((string)($d['token']??$_SERVER['HTTP_X_DEVICE_TOKEN']??''));
    $stmt=mysqli_prepare($conn,"SELECT * FROM biometric_devices WHERE device_code=? AND is_active=1 LIMIT 1");
    mysqli_stmt_bind_param($stmt,'s',$code);mysqli_stmt_execute($stmt);$device=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));mysqli_stmt_close($stmt);
    if(!$device||!password_verify($token,(string)$device['api_token_hash']))att_json(['ok'=>false,'message'=>'Invalid device credentials.'],401);

    $businessId=(int)$device['business_id'];$deviceId=(int)$device['device_id'];$branchId=(int)($device['branch_id']??0);$logs=$d['logs']??[];$inserted=0;$days=[];
    mysqli_begin_transaction($conn);
    try{
        foreach($logs as $row){
            $deviceUser=trim((string)($row['device_user_id']??''));$time=trim((string)($row['punch_time']??''));
            if($deviceUser===''||$time==='')continue;
            $key=trim((string)($row['device_log_key']??''));if($key==='')$key=sha1($deviceUser.'|'.$time.'|'.json_encode($row));
            $map=mysqli_prepare($conn,"SELECT attendance_employee_id FROM biometric_device_user_map WHERE business_id=? AND device_id=? AND device_user_id=? AND is_active=1 LIMIT 1");
            mysqli_stmt_bind_param($map,'iis',$businessId,$deviceId,$deviceUser);mysqli_stmt_execute($map);$mapped=mysqli_fetch_assoc(mysqli_stmt_get_result($map));mysqli_stmt_close($map);
            $employeeId=$mapped?(int)$mapped['attendance_employee_id']:null;
            $type=strtoupper((string)($row['punch_type']??'UNKNOWN'));if(!in_array($type,['IN','OUT','BREAK_IN','BREAK_OUT','UNKNOWN'],true))$type='UNKNOWN';
            $verify=strtoupper((string)($row['verification_mode']??'UNKNOWN'));if(!in_array($verify,['FINGERPRINT','FACE','CARD','PASSWORD','MANUAL','UNKNOWN'],true))$verify='UNKNOWN';
            $payload=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $stmt=mysqli_prepare($conn,"INSERT IGNORE INTO attendance_raw_logs(business_id,branch_id,device_id,device_user_id,attendance_employee_id,punch_time,punch_type,verification_mode,device_log_key,payload_json) VALUES(?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt,'iiisisssss',$businessId,$branchId,$deviceId,$deviceUser,$employeeId,$time,$type,$verify,$key,$payload);
            mysqli_stmt_execute($stmt);
            if(mysqli_stmt_affected_rows($stmt)>0){$inserted++;if($employeeId)$days[$employeeId.'|'.substr($time,0,10)]=[$employeeId,substr($time,0,10)];}
            mysqli_stmt_close($stmt);
        }
        $stmt=mysqli_prepare($conn,"UPDATE biometric_devices SET last_seen_at=NOW(),last_sync_at=NOW(),connection_status='ONLINE',last_error=NULL WHERE business_id=? AND device_id=?");
        mysqli_stmt_bind_param($stmt,'ii',$businessId,$deviceId);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
        foreach($days as [$emp,$date])att_recalculate($conn,$businessId,$emp,$date);
        mysqli_commit($conn);att_json(['ok'=>true,'message'=>'Attendance synchronized.','inserted'=>$inserted]);
    }catch(Throwable $e){mysqli_rollback($conn);att_json(['ok'=>false,'message'=>$e->getMessage()],500);}
}

require_business_login();
$businessId=(int)current_business_id();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();att_admin($conn);$d=att_payload();

    if($action==='save_employee'){
        $id=(int)($d['attendance_employee_id']??0);$code=trim((string)($d['employee_code']??''));$name=trim((string)($d['employee_name']??''));$branch=(int)($d['branch_id']??0);$department=trim((string)($d['department']??''));$designation=trim((string)($d['designation']??''));$shift=(int)($d['default_shift_id']??0);
        if($code===''||$name==='')att_json(['ok'=>false,'message'=>'Employee code and name are required.'],422);
        if($id>0){$stmt=mysqli_prepare($conn,"UPDATE attendance_employees SET branch_id=NULLIF(?,0),employee_code=?,employee_name=?,department=?,designation=?,default_shift_id=NULLIF(?,0) WHERE business_id=? AND attendance_employee_id=?");mysqli_stmt_bind_param($stmt,'issssiii',$branch,$code,$name,$department,$designation,$shift,$businessId,$id);}
        else{$stmt=mysqli_prepare($conn,"INSERT INTO attendance_employees(business_id,branch_id,employee_code,employee_name,department,designation,default_shift_id) VALUES(?,NULLIF(?,0),?,?,?,?,NULLIF(?,0))");mysqli_stmt_bind_param($stmt,'iissssi',$businessId,$branch,$code,$name,$department,$designation,$shift);}
        mysqli_stmt_execute($stmt);$recordId=$id?:mysqli_insert_id($conn);mysqli_stmt_close($stmt);
        log_activity($conn,'Attendance Employees',$id?'update':'create',$recordId,null,['employee_code'=>$code,'employee_name'=>$name]);
        att_json(['ok'=>true,'message'=>'Attendance employee saved successfully.']);
    }

    if($action==='save_device'){
        $id=(int)($d['device_id']??0);$code=trim((string)($d['device_code']??''));$name=trim((string)($d['device_name']??''));$brand=strtoupper(trim((string)($d['device_brand']??'GENERIC')));$branch=(int)($d['branch_id']??0);$ip=trim((string)($d['ip_address']??''));$port=(int)($d['port']??4370);$token=trim((string)($d['api_token']??''));$hash=$token!==''?password_hash($token,PASSWORD_DEFAULT):null;
        if($code===''||$name==='')att_json(['ok'=>false,'message'=>'Device code and name are required.'],422);
        if($id>0&&$hash){$stmt=mysqli_prepare($conn,"UPDATE biometric_devices SET branch_id=NULLIF(?,0),device_code=?,device_name=?,device_brand=?,ip_address=?,port=?,api_token_hash=? WHERE business_id=? AND device_id=?");mysqli_stmt_bind_param($stmt,'issssisii',$branch,$code,$name,$brand,$ip,$port,$hash,$businessId,$id);}
        elseif($id>0){$stmt=mysqli_prepare($conn,"UPDATE biometric_devices SET branch_id=NULLIF(?,0),device_code=?,device_name=?,device_brand=?,ip_address=?,port=? WHERE business_id=? AND device_id=?");mysqli_stmt_bind_param($stmt,'issssiiii',$branch,$code,$name,$brand,$ip,$port,$businessId,$id);}
        else{if(!$hash)att_json(['ok'=>false,'message'=>'API token is required for a new device.'],422);$user=(int)current_user_id();$stmt=mysqli_prepare($conn,"INSERT INTO biometric_devices(business_id,branch_id,device_code,device_name,device_brand,ip_address,port,api_token_hash,created_by) VALUES(?,NULLIF(?,0),?,?,?,?,?,?,?)");mysqli_stmt_bind_param($stmt,'iissssisi',$businessId,$branch,$code,$name,$brand,$ip,$port,$hash,$user);}
        mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);att_json(['ok'=>true,'message'=>'Biometric device saved successfully.']);
    }

    if($action==='save_mapping'){
        $device=(int)($d['device_id']??0);$employee=(int)($d['attendance_employee_id']??0);$deviceUser=trim((string)($d['device_user_id']??''));
        if($device<=0||$employee<=0||$deviceUser==='')att_json(['ok'=>false,'message'=>'Device, employee and device user ID are required.'],422);
        $stmt=mysqli_prepare($conn,"INSERT INTO biometric_device_user_map(business_id,device_id,attendance_employee_id,device_user_id,is_active) VALUES(?,?,?,?,1) ON DUPLICATE KEY UPDATE attendance_employee_id=VALUES(attendance_employee_id),device_user_id=VALUES(device_user_id),is_active=1");
        mysqli_stmt_bind_param($stmt,'iiis',$businessId,$device,$employee,$deviceUser);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
        att_json(['ok'=>true,'message'=>'Device user mapping saved successfully.']);
    }

    if($action==='save_shift'){
        $id=(int)($d['shift_id']??0);$code=trim((string)($d['shift_code']??''));$name=trim((string)($d['shift_name']??''));$branch=(int)($d['branch_id']??0);$start=trim((string)($d['start_time']??''));$end=trim((string)($d['end_time']??''));$graceIn=(int)($d['grace_in_minutes']??0);$graceOut=(int)($d['grace_out_minutes']??0);$half=(int)($d['minimum_half_day_minutes']??240);$full=(int)($d['minimum_full_day_minutes']??480);
        if($id>0){$stmt=mysqli_prepare($conn,"UPDATE attendance_shifts SET branch_id=NULLIF(?,0),shift_code=?,shift_name=?,start_time=?,end_time=?,grace_in_minutes=?,grace_out_minutes=?,minimum_half_day_minutes=?,minimum_full_day_minutes=? WHERE business_id=? AND shift_id=?");mysqli_stmt_bind_param($stmt,'issssiiiiii',$branch,$code,$name,$start,$end,$graceIn,$graceOut,$half,$full,$businessId,$id);}
        else{$stmt=mysqli_prepare($conn,"INSERT INTO attendance_shifts(business_id,branch_id,shift_code,shift_name,start_time,end_time,grace_in_minutes,grace_out_minutes,minimum_half_day_minutes,minimum_full_day_minutes) VALUES(?,NULLIF(?,0),?,?,?,?,?,?,?,?)");mysqli_stmt_bind_param($stmt,'iissssiiii',$businessId,$branch,$code,$name,$start,$end,$graceIn,$graceOut,$half,$full);}
        mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);att_json(['ok'=>true,'message'=>'Shift saved successfully.']);
    }

    if($action==='save_leave'){
        $employee=(int)($d['attendance_employee_id']??0);$type=trim((string)($d['leave_type']??'CASUAL'));$from=trim((string)($d['from_date']??''));$to=trim((string)($d['to_date']??''));$reason=trim((string)($d['reason']??''));
        $stmt=mysqli_prepare($conn,"SELECT branch_id FROM attendance_employees WHERE business_id=? AND attendance_employee_id=? LIMIT 1");mysqli_stmt_bind_param($stmt,'ii',$businessId,$employee);mysqli_stmt_execute($stmt);$emp=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));mysqli_stmt_close($stmt);
        if(!$emp)att_json(['ok'=>false,'message'=>'Employee not found.'],422);
        $branch=(int)($emp['branch_id']??0);$days=((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days??0)+1;$status='APPROVED';
        $stmt=mysqli_prepare($conn,"INSERT INTO attendance_leave_requests(business_id,branch_id,attendance_employee_id,leave_type,from_date,to_date,total_days,reason,status) VALUES(?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt,'iiisssdss',$businessId,$branch,$employee,$type,$from,$to,$days,$reason,$status);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
        $date=new DateTimeImmutable($from);$endDate=new DateTimeImmutable($to);
        while($date<=$endDate){$day=$date->format('Y-m-d');$stmt=mysqli_prepare($conn,"INSERT INTO attendance_daily(business_id,branch_id,attendance_employee_id,attendance_date,status,source) VALUES(?,?,?,?,'LEAVE','MANUAL') ON DUPLICATE KEY UPDATE status='LEAVE',source='MANUAL'");mysqli_stmt_bind_param($stmt,'iiis',$businessId,$branch,$employee,$day);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);$date=$date->modify('+1 day');}
        att_json(['ok'=>true,'message'=>'Leave saved successfully.']);
    }
}

if($action==='masters'){
    $stmt=mysqli_prepare($conn,"SELECT branch_id,branch_code,branch_name FROM branches WHERE business_id=? AND status=1 ORDER BY branch_name");mysqli_stmt_bind_param($stmt,'i',$businessId);$branches=att_rows($conn,$stmt);
    $stmt=mysqli_prepare($conn,"SELECT shift_id,shift_name FROM attendance_shifts WHERE business_id=? AND is_active=1 ORDER BY shift_name");mysqli_stmt_bind_param($stmt,'i',$businessId);$shifts=att_rows($conn,$stmt);
    $stmt=mysqli_prepare($conn,"SELECT attendance_employee_id,employee_code,employee_name FROM attendance_employees WHERE business_id=? AND employment_status='ACTIVE' ORDER BY employee_name");mysqli_stmt_bind_param($stmt,'i',$businessId);$employees=att_rows($conn,$stmt);
    $stmt=mysqli_prepare($conn,"SELECT device_id,device_code,device_name FROM biometric_devices WHERE business_id=? AND is_active=1 ORDER BY device_name");mysqli_stmt_bind_param($stmt,'i',$businessId);$devices=att_rows($conn,$stmt);
    att_json(['ok'=>true,'branches'=>$branches,'shifts'=>$shifts,'employees'=>$employees,'devices'=>$devices]);
}

if($action==='dashboard'){
    $date=$_GET['date']??date('Y-m-d');
    $stmt=mysqli_prepare($conn,"SELECT COUNT(ae.attendance_employee_id) total_employees,SUM(ad.status='PRESENT') present_count,SUM(ad.status='ABSENT' OR ad.status IS NULL) absent_count,SUM(ad.status='HALF_DAY') half_day_count,SUM(ad.status='LEAVE') leave_count,SUM(ad.late_minutes>0) late_count,SUM(ad.early_exit_minutes>0) early_exit_count,SUM(ad.overtime_minutes>0) overtime_count FROM attendance_employees ae LEFT JOIN attendance_daily ad ON ad.business_id=ae.business_id AND ad.attendance_employee_id=ae.attendance_employee_id AND ad.attendance_date=? WHERE ae.business_id=? AND ae.employment_status='ACTIVE'");
    mysqli_stmt_bind_param($stmt,'si',$date,$businessId);mysqli_stmt_execute($stmt);$summary=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));mysqli_stmt_close($stmt);
    $stmt=mysqli_prepare($conn,"SELECT d.*,b.branch_name FROM biometric_devices d LEFT JOIN branches b ON b.branch_id=d.branch_id AND b.business_id=d.business_id WHERE d.business_id=? AND d.is_active=1 ORDER BY d.device_name");mysqli_stmt_bind_param($stmt,'i',$businessId);$devices=att_rows($conn,$stmt);
    att_json(['ok'=>true,'summary'=>$summary,'devices'=>$devices]);
}

if($action==='employees'){
    $stmt=mysqli_prepare($conn,"SELECT ae.*,b.branch_name,s.shift_name FROM attendance_employees ae LEFT JOIN branches b ON b.branch_id=ae.branch_id AND b.business_id=ae.business_id LEFT JOIN attendance_shifts s ON s.shift_id=ae.default_shift_id AND s.business_id=ae.business_id WHERE ae.business_id=? ORDER BY ae.employee_name");
    mysqli_stmt_bind_param($stmt,'i',$businessId);att_json(['ok'=>true,'rows'=>att_rows($conn,$stmt)]);
}
if($action==='devices'){
    $stmt=mysqli_prepare($conn,"SELECT d.*,b.branch_name FROM biometric_devices d LEFT JOIN branches b ON b.branch_id=d.branch_id AND b.business_id=d.business_id WHERE d.business_id=? ORDER BY d.device_name");
    mysqli_stmt_bind_param($stmt,'i',$businessId);att_json(['ok'=>true,'rows'=>att_rows($conn,$stmt)]);
}
if($action==='mappings'){
    $stmt=mysqli_prepare($conn,"SELECT m.*,d.device_name,ae.employee_code,ae.employee_name FROM biometric_device_user_map m INNER JOIN biometric_devices d ON d.device_id=m.device_id AND d.business_id=m.business_id INNER JOIN attendance_employees ae ON ae.attendance_employee_id=m.attendance_employee_id AND ae.business_id=m.business_id WHERE m.business_id=? ORDER BY d.device_name,ae.employee_name");
    mysqli_stmt_bind_param($stmt,'i',$businessId);att_json(['ok'=>true,'rows'=>att_rows($conn,$stmt)]);
}
if($action==='shifts'){
    $stmt=mysqli_prepare($conn,"SELECT s.*,b.branch_name FROM attendance_shifts s LEFT JOIN branches b ON b.branch_id=s.branch_id AND b.business_id=s.business_id WHERE s.business_id=? ORDER BY s.shift_name");
    mysqli_stmt_bind_param($stmt,'i',$businessId);att_json(['ok'=>true,'rows'=>att_rows($conn,$stmt)]);
}
if($action==='attendance'){
    $from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-t');$branch=(int)($_GET['branch_id']??0);
    $sql="SELECT ad.*,ae.employee_code,ae.employee_name,b.branch_name FROM attendance_daily ad INNER JOIN attendance_employees ae ON ae.attendance_employee_id=ad.attendance_employee_id AND ae.business_id=ad.business_id LEFT JOIN branches b ON b.branch_id=ad.branch_id AND b.business_id=ad.business_id WHERE ad.business_id=? AND ad.attendance_date BETWEEN ? AND ?";
    if($branch>0)$sql.=" AND ad.branch_id=?";$sql.=" ORDER BY ad.attendance_date DESC,ae.employee_name";
    $stmt=mysqli_prepare($conn,$sql);if($branch>0)mysqli_stmt_bind_param($stmt,'issi',$businessId,$from,$to,$branch);else mysqli_stmt_bind_param($stmt,'iss',$businessId,$from,$to);
    att_json(['ok'=>true,'rows'=>att_rows($conn,$stmt)]);
}
if($action==='leaves'){
    $stmt=mysqli_prepare($conn,"SELECT lr.*,ae.employee_name,b.branch_name FROM attendance_leave_requests lr INNER JOIN attendance_employees ae ON ae.attendance_employee_id=lr.attendance_employee_id AND ae.business_id=lr.business_id LEFT JOIN branches b ON b.branch_id=lr.branch_id AND b.business_id=lr.business_id WHERE lr.business_id=? ORDER BY lr.leave_request_id DESC");
    mysqli_stmt_bind_param($stmt,'i',$businessId);att_json(['ok'=>true,'rows'=>att_rows($conn,$stmt)]);
}
if($action==='export'){
    $from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-t');$branch=(int)($_GET['branch_id']??0);$format=$_GET['format']??'csv';
    $sql="SELECT ad.attendance_date,ae.employee_code,ae.employee_name,b.branch_name,ad.status,ad.first_check_in,ad.last_check_out,ad.total_work_minutes,ad.late_minutes,ad.early_exit_minutes,ad.overtime_minutes FROM attendance_daily ad INNER JOIN attendance_employees ae ON ae.attendance_employee_id=ad.attendance_employee_id AND ae.business_id=ad.business_id LEFT JOIN branches b ON b.branch_id=ad.branch_id AND b.business_id=ad.business_id WHERE ad.business_id=? AND ad.attendance_date BETWEEN ? AND ?";
    if($branch>0)$sql.=" AND ad.branch_id=?";$sql.=" ORDER BY ad.attendance_date,ae.employee_name";
    $stmt=mysqli_prepare($conn,$sql);if($branch>0)mysqli_stmt_bind_param($stmt,'issi',$businessId,$from,$to,$branch);else mysqli_stmt_bind_param($stmt,'iss',$businessId,$from,$to);$rows=att_rows($conn,$stmt);
    if($format==='csv'){header('Content-Type:text/csv;charset=UTF-8');header('Content-Disposition:attachment;filename="attendance-'.$from.'-'.$to.'.csv"');echo "\xEF\xBB\xBF";$o=fopen('php://output','w');fputcsv($o,['Date','Code','Employee','Branch','Status','Check In','Check Out','Work','Late','Early','OT']);foreach($rows as $r)fputcsv($o,array_values($r));fclose($o);exit;}
    header('Content-Type:text/html;charset=UTF-8');echo '<!doctype html><html><head><style>body{font:11px Arial}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px}th{background:#eee}@media print{button{display:none}}</style></head><body><button onclick="print()">Print / Save PDF</button><h2>GK Footwear Attendance Report</h2><p>'.htmlspecialchars($from).' to '.htmlspecialchars($to).'</p><table><thead><tr><th>Date</th><th>Code</th><th>Employee</th><th>Branch</th><th>Status</th><th>In</th><th>Out</th><th>Work</th><th>Late</th><th>Early</th><th>OT</th></tr></thead><tbody>';foreach($rows as $r){echo '<tr>';foreach($r as $v)echo '<td>'.htmlspecialchars((string)$v).'</td>';echo '</tr>';}echo '</tbody></table></body></html>';exit;
}

att_json(['ok'=>false,'message'=>'Invalid attendance API action.'],400);
