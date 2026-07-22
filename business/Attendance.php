<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'attendance.php');

$pageTitle = 'Attendance Management';
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>
    <style>
        .att-tabs{display:flex;gap:8px;flex-wrap:wrap}
        .att-tab{border:1px solid var(--border-soft);background:var(--card-bg);color:var(--text-main);border-radius:999px;padding:8px 14px;font-size:11px;font-weight:900}
        .att-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,var(--brand-1),var(--brand-2))}
        .att-panel{display:none}.att-panel.active{display:block}
        .att-stat{border:1px solid var(--border-soft);background:var(--card-bg);border-radius:18px;padding:16px;height:100%}
        .att-stat span{display:block;color:var(--text-muted);font-size:10px;font-weight:900;text-transform:uppercase}
        .att-stat strong{display:block;font-size:26px;color:var(--text-main);margin-top:4px}
        .att-device{border:1px solid var(--border-soft);background:var(--card-bg);border-radius:16px;padding:13px}
        .att-dot{width:10px;height:10px;border-radius:50%;display:inline-block}
        .online{background:#22c55e}.offline{background:#ef4444}.unknown{background:#94a3b8}.error{background:#f59e0b}
        .att-status{border-radius:999px;padding:4px 8px;font-size:10px;font-weight:900}
        .present{background:#dcfce7;color:#15803d}.absent{background:#fee2e2;color:#b91c1c}
        .half_day{background:#fef3c7;color:#b45309}.leave{background:#dbeafe;color:#1d4ed8}
        .missing_punch{background:#ffedd5;color:#c2410c}
        .att-table th{font-size:10px;text-transform:uppercase;white-space:nowrap}
        .att-table td{font-size:11px;vertical-align:middle}
        .att-empty{text-align:center;color:var(--text-muted);padding:28px!important}
        @media(max-width:767px){.att-tabs{flex-wrap:nowrap;overflow-x:auto}.att-tab{white-space:nowrap}.att-table{min-width:900px}}
    </style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>

<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>
        <section class="page-section p-3">
            <div class="page-head-card mb-3">
                <div>
                    <h1 class="h4 fw-bold mb-1">Attendance Management</h1>
                    <p class="small text-muted-custom mb-0">Biometric devices, employees, shifts, attendance, leave and reports.</p>
                </div>
                <div class="d-flex gap-2">
                    <input type="date" id="dashboardDate" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                    <button class="btn brand-gradient btn-sm fw-bold" type="button" onclick="loadDashboard()">Refresh</button>
                </div>
            </div>

            <div class="att-tabs mb-3">
                <button class="att-tab active" data-panel="dashboardPanel">Dashboard</button>
                <button class="att-tab" data-panel="employeesPanel">Employees</button>
                <button class="att-tab" data-panel="devicesPanel">Devices</button>
                <button class="att-tab" data-panel="mappingPanel">Device Mapping</button>
                <button class="att-tab" data-panel="shiftsPanel">Shifts</button>
                <button class="att-tab" data-panel="attendancePanel">Attendance</button>
                <button class="att-tab" data-panel="leavePanel">Leave</button>
                <button class="att-tab" data-panel="reportsPanel">Reports</button>
            </div>

            <section id="dashboardPanel" class="att-panel active">
                <div class="row g-3 mb-3" id="summaryCards"></div>
                <div class="row g-3">
                    <div class="col-lg-7">
                        <section class="card-ui p-3">
                            <h2 class="fs-6 fw-bold mb-3">Attendance Overview</h2>
                            <canvas id="attendanceChart" height="130"></canvas>
                        </section>
                    </div>
                    <div class="col-lg-5">
                        <section class="card-ui p-3">
                            <h2 class="fs-6 fw-bold mb-3">Device Status</h2>
                            <div id="dashboardDevices" class="d-grid gap-2"></div>
                        </section>
                    </div>
                </div>
            </section>

            <section id="employeesPanel" class="att-panel">
                <section class="card-ui p-3 mb-3">
                    <form id="employeeForm" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_employee">
                        <input type="hidden" name="attendance_employee_id">
                        <div class="col-md-2"><label class="form-label">Employee Code</label><input class="form-control" name="employee_code" required></div>
                        <div class="col-md-3"><label class="form-label">Employee Name</label><input class="form-control" name="employee_name" required></div>
                        <div class="col-md-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id" id="employeeBranch"></select></div>
                        <div class="col-md-2"><label class="form-label">Department</label><input class="form-control" name="department"></div>
                        <div class="col-md-2"><label class="form-label">Designation</label><input class="form-control" name="designation"></div>
                        <div class="col-md-1"><label class="form-label">Shift</label><select class="form-select" name="default_shift_id" id="employeeShift"></select></div>
                        <div class="col-12 text-end"><button class="btn brand-gradient px-4">Save Employee</button></div>
                    </form>
                </section>
                <section class="card-ui p-3"><div class="table-responsive"><table class="table att-table"><thead><tr><th>Code</th><th>Employee</th><th>Branch</th><th>Department</th><th>Designation</th><th>Shift</th><th>Status</th><th></th></tr></thead><tbody id="employeeRows"></tbody></table></div></section>
            </section>

            <section id="devicesPanel" class="att-panel">
                <section class="card-ui p-3 mb-3">
                    <form id="deviceForm" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_device">
                        <input type="hidden" name="device_id">
                        <div class="col-md-2"><label class="form-label">Device Code</label><input class="form-control" name="device_code" required></div>
                        <div class="col-md-2"><label class="form-label">Device Name</label><input class="form-control" name="device_name" required></div>
                        <div class="col-md-2"><label class="form-label">Brand</label><select class="form-select" name="device_brand"><option>ZKTECO</option><option>ESSL</option><option>REALAND</option><option>GENERIC</option></select></div>
                        <div class="col-md-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id" id="deviceBranch"></select></div>
                        <div class="col-md-2"><label class="form-label">IP Address</label><input class="form-control" name="ip_address"></div>
                        <div class="col-md-1"><label class="form-label">Port</label><input type="number" class="form-control" name="port" value="4370"></div>
                        <div class="col-md-1"><label class="form-label">API Token</label><input class="form-control" name="api_token"></div>
                        <div class="col-12 text-end"><button class="btn brand-gradient px-4">Save Device</button></div>
                    </form>
                </section>
                <section class="card-ui p-3"><div class="table-responsive"><table class="table att-table"><thead><tr><th>Code</th><th>Device</th><th>Branch</th><th>Brand</th><th>IP</th><th>Status</th><th>Last Sync</th></tr></thead><tbody id="deviceRows"></tbody></table></div></section>
            </section>

            <section id="mappingPanel" class="att-panel">
                <section class="card-ui p-3 mb-3">
                    <form id="mappingForm" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_mapping">
                        <div class="col-md-4"><label class="form-label">Device</label><select class="form-select" name="device_id" id="mappingDevice" required></select></div>
                        <div class="col-md-4"><label class="form-label">Employee</label><select class="form-select" name="attendance_employee_id" id="mappingEmployee" required></select></div>
                        <div class="col-md-3"><label class="form-label">Device User ID</label><input class="form-control" name="device_user_id" required></div>
                        <div class="col-md-1"><button class="btn brand-gradient w-100">Save</button></div>
                    </form>
                </section>
                <section class="card-ui p-3"><div class="table-responsive"><table class="table att-table"><thead><tr><th>Device</th><th>Employee</th><th>Device User ID</th><th>Status</th></tr></thead><tbody id="mappingRows"></tbody></table></div></section>
            </section>

            <section id="shiftsPanel" class="att-panel">
                <section class="card-ui p-3 mb-3">
                    <form id="shiftForm" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_shift">
                        <input type="hidden" name="shift_id">
                        <div class="col-md-2"><label class="form-label">Code</label><input class="form-control" name="shift_code" required></div>
                        <div class="col-md-2"><label class="form-label">Shift Name</label><input class="form-control" name="shift_name" required></div>
                        <div class="col-md-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id" id="shiftBranch"></select></div>
                        <div class="col-md-1"><label class="form-label">Start</label><input type="time" class="form-control" name="start_time" required></div>
                        <div class="col-md-1"><label class="form-label">End</label><input type="time" class="form-control" name="end_time" required></div>
                        <div class="col-md-1"><label class="form-label">Grace In</label><input type="number" class="form-control" name="grace_in_minutes" value="10"></div>
                        <div class="col-md-1"><label class="form-label">Grace Out</label><input type="number" class="form-control" name="grace_out_minutes" value="10"></div>
                        <div class="col-md-1"><label class="form-label">Half Day</label><input type="number" class="form-control" name="minimum_half_day_minutes" value="240"></div>
                        <div class="col-md-1"><label class="form-label">Full Day</label><input type="number" class="form-control" name="minimum_full_day_minutes" value="480"></div>
                        <div class="col-12 text-end"><button class="btn brand-gradient px-4">Save Shift</button></div>
                    </form>
                </section>
                <section class="card-ui p-3"><div class="table-responsive"><table class="table att-table"><thead><tr><th>Code</th><th>Shift</th><th>Branch</th><th>Time</th><th>Grace</th><th>Half Day</th><th>Full Day</th></tr></thead><tbody id="shiftRows"></tbody></table></div></section>
            </section>

            <section id="attendancePanel" class="att-panel">
                <section class="card-ui p-3 mb-3">
                    <form id="attendanceFilter" class="row g-2 align-items-end">
                        <div class="col-md-3"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?= date('Y-m-01') ?>"></div>
                        <div class="col-md-3"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?= date('Y-m-t') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Branch</label><select class="form-select" name="branch_id" id="attendanceBranch"></select></div>
                        <div class="col-md-3"><button type="button" class="btn brand-gradient w-100" onclick="loadAttendance()">Load Attendance</button></div>
                    </form>
                </section>
                <section class="card-ui p-3"><div class="table-responsive"><table class="table att-table"><thead><tr><th>Date</th><th>Employee</th><th>Branch</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Work</th><th>Late</th><th>Early</th><th>OT</th></tr></thead><tbody id="attendanceRows"></tbody></table></div></section>
            </section>

            <section id="leavePanel" class="att-panel">
                <section class="card-ui p-3 mb-3">
                    <form id="leaveForm" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_leave">
                        <div class="col-md-3"><label class="form-label">Employee</label><select class="form-select" name="attendance_employee_id" id="leaveEmployee" required></select></div>
                        <div class="col-md-2"><label class="form-label">Leave Type</label><select class="form-select" name="leave_type"><option>CASUAL</option><option>SICK</option><option>UNPAID</option></select></div>
                        <div class="col-md-2"><label class="form-label">From</label><input type="date" class="form-control" name="from_date" required></div>
                        <div class="col-md-2"><label class="form-label">To</label><input type="date" class="form-control" name="to_date" required></div>
                        <div class="col-md-2"><label class="form-label">Reason</label><input class="form-control" name="reason"></div>
                        <div class="col-md-1"><button class="btn brand-gradient w-100">Save</button></div>
                    </form>
                </section>
                <section class="card-ui p-3"><div class="table-responsive"><table class="table att-table"><thead><tr><th>Employee</th><th>Branch</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th></tr></thead><tbody id="leaveRows"></tbody></table></div></section>
            </section>

            <section id="reportsPanel" class="att-panel">
                <section class="card-ui p-3">
                    <form id="reportForm" class="row g-3 align-items-end">
                        <div class="col-md-3"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?= date('Y-m-01') ?>"></div>
                        <div class="col-md-3"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?= date('Y-m-t') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Branch</label><select class="form-select" name="branch_id" id="reportBranch"></select></div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="button" class="btn btn-success" onclick="exportReport('csv')">Excel</button>
                            <button type="button" class="btn btn-danger" onclick="exportReport('html')">PDF / Print</button>
                        </div>
                    </form>
                </section>
            </section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const API = 'api/attendance-api.php';
let attendanceChart;
let masters = {branches:[], shifts:[], employees:[], devices:[]};

function esc(v){return String(v ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
function emptyRow(cols,text){return `<tr><td colspan="${cols}" class="att-empty">${text}</td></tr>`;}
function fillSelect(id, rows, valueKey, labelFn, allLabel='Select'){
    const el=document.getElementById(id); if(!el)return;
    el.innerHTML=`<option value="">${allLabel}</option>`+rows.map(r=>`<option value="${esc(r[valueKey])}">${esc(labelFn(r))}</option>`).join('');
}

async function apiGet(action,params={}){
    const q=new URLSearchParams({action,...params});
    const r=await fetch(API+'?'+q.toString());
    const d=await r.json();
    if(!d.ok) throw new Error(d.message||'API error');
    return d;
}

async function submitForm(form){
    const btn=form.querySelector('button[type="submit"],button:not([type])');
    const old=btn?btn.innerHTML:'';
    if(btn){btn.disabled=true;btn.innerHTML='Saving...';}
    try{
        const r=await fetch(API,{method:'POST',body:new FormData(form)});
        const d=await r.json();
        alert(d.message||'Completed');
        return d;
    }catch(e){alert(e.message);return {ok:false};}
    finally{if(btn){btn.disabled=false;btn.innerHTML=old;}}
}

async function loadMasters(){
    const d=await apiGet('masters');
    masters=d;
    const branchSelects=['employeeBranch','deviceBranch','shiftBranch','attendanceBranch','reportBranch'];
    branchSelects.forEach(id=>fillSelect(id,d.branches,'branch_id',r=>`${r.branch_code} - ${r.branch_name}`,id.includes('attendance')||id.includes('report')?'All Branches':'Select Branch'));
    fillSelect('employeeShift',d.shifts,'shift_id',r=>r.shift_name,'No Shift');
    fillSelect('mappingDevice',d.devices,'device_id',r=>`${r.device_code} - ${r.device_name}`);
    fillSelect('mappingEmployee',d.employees,'attendance_employee_id',r=>`${r.employee_code} - ${r.employee_name}`);
    fillSelect('leaveEmployee',d.employees,'attendance_employee_id',r=>`${r.employee_code} - ${r.employee_name}`);
}

document.querySelectorAll('.att-tab').forEach(btn=>btn.addEventListener('click',()=>{
    document.querySelectorAll('.att-tab').forEach(x=>x.classList.remove('active'));
    document.querySelectorAll('.att-panel').forEach(x=>x.classList.remove('active'));
    btn.classList.add('active');document.getElementById(btn.dataset.panel).classList.add('active');
    const map={employeesPanel:loadEmployees,devicesPanel:loadDevices,mappingPanel:loadMappings,shiftsPanel:loadShifts,attendancePanel:loadAttendance,leavePanel:loadLeaves};
    if(map[btn.dataset.panel])map[btn.dataset.panel]();
}));

async function loadDashboard(){
    const d=await apiGet('dashboard',{date:document.getElementById('dashboardDate').value});
    const s=d.summary||{};
    const cards={total_employees:'Employees',present_count:'Present',absent_count:'Absent',half_day_count:'Half Day',leave_count:'Leave',late_count:'Late',early_exit_count:'Early Exit',overtime_count:'Overtime'};
    summaryCards.innerHTML=Object.entries(cards).map(([k,l])=>`<div class="col-6 col-md-3"><div class="att-stat"><span>${l}</span><strong>${Number(s[k]||0)}</strong></div></div>`).join('');
    if(window.Chart){
        if(attendanceChart)attendanceChart.destroy();
        attendanceChart=new Chart(document.getElementById('attendanceChart'),{type:'bar',data:{labels:['Present','Absent','Half Day','Leave','Late','OT'],datasets:[{data:[s.present_count||0,s.absent_count||0,s.half_day_count||0,s.leave_count||0,s.late_count||0,s.overtime_count||0]}]},options:{plugins:{legend:{display:false}}}});
    }
    dashboardDevices.innerHTML=(d.devices||[]).map(v=>`<div class="att-device"><div class="d-flex justify-content-between"><div><b>${esc(v.device_name)}</b><div class="small text-muted-custom">${esc(v.device_brand)} · ${esc(v.branch_name||'-')}</div></div><div class="small fw-bold"><span class="att-dot ${String(v.connection_status).toLowerCase()}"></span> ${esc(v.connection_status)}</div></div><div class="small text-muted-custom mt-2">Last sync: ${esc(v.last_sync_at||'-')}</div></div>`).join('')||'<div class="small text-muted">No devices configured.</div>';
}

async function loadEmployees(){
    const d=await apiGet('employees');
    employeeRows.innerHTML=(d.rows||[]).map(r=>`<tr><td>${esc(r.employee_code)}</td><td><b>${esc(r.employee_name)}</b></td><td>${esc(r.branch_name||'-')}</td><td>${esc(r.department||'-')}</td><td>${esc(r.designation||'-')}</td><td>${esc(r.shift_name||'-')}</td><td>${esc(r.employment_status)}</td><td><button class="btn btn-sm btn-outline-primary" onclick='editEmployee(${JSON.stringify(r)})'>Edit</button></td></tr>`).join('')||emptyRow(8,'No attendance employees found.');
}
function editEmployee(r){const f=employeeForm;Object.keys(r).forEach(k=>{if(f.elements[k])f.elements[k].value=r[k]??''});window.scrollTo({top:0,behavior:'smooth'});}

async function loadDevices(){
    const d=await apiGet('devices');
    deviceRows.innerHTML=(d.rows||[]).map(r=>`<tr><td>${esc(r.device_code)}</td><td><b>${esc(r.device_name)}</b></td><td>${esc(r.branch_name||'-')}</td><td>${esc(r.device_brand)}</td><td>${esc(r.ip_address||'-')}:${esc(r.port||'')}</td><td>${esc(r.connection_status)}</td><td>${esc(r.last_sync_at||'-')}</td></tr>`).join('')||emptyRow(7,'No biometric devices found.');
}

async function loadMappings(){
    const d=await apiGet('mappings');
    mappingRows.innerHTML=(d.rows||[]).map(r=>`<tr><td>${esc(r.device_name)}</td><td>${esc(r.employee_code)} - ${esc(r.employee_name)}</td><td>${esc(r.device_user_id)}</td><td>${Number(r.is_active)===1?'Active':'Inactive'}</td></tr>`).join('')||emptyRow(4,'No device mappings found.');
}

async function loadShifts(){
    const d=await apiGet('shifts');
    shiftRows.innerHTML=(d.rows||[]).map(r=>`<tr><td>${esc(r.shift_code)}</td><td><b>${esc(r.shift_name)}</b></td><td>${esc(r.branch_name||'All Branches')}</td><td>${esc(r.start_time)} - ${esc(r.end_time)}</td><td>${r.grace_in_minutes}/${r.grace_out_minutes} min</td><td>${r.minimum_half_day_minutes}</td><td>${r.minimum_full_day_minutes}</td></tr>`).join('')||emptyRow(7,'No shifts found.');
}

async function loadAttendance(){
    const q=Object.fromEntries(new FormData(attendanceFilter).entries());
    const d=await apiGet('attendance',q);
    attendanceRows.innerHTML=(d.rows||[]).map(r=>`<tr><td>${esc(r.attendance_date)}</td><td><b>${esc(r.employee_name)}</b><div class="small text-muted-custom">${esc(r.employee_code)}</div></td><td>${esc(r.branch_name||'-')}</td><td><span class="att-status ${String(r.status).toLowerCase()}">${esc(r.status)}</span></td><td>${esc(r.first_check_in||'-')}</td><td>${esc(r.last_check_out||'-')}</td><td>${r.total_work_minutes}</td><td>${r.late_minutes}</td><td>${r.early_exit_minutes}</td><td>${r.overtime_minutes}</td></tr>`).join('')||emptyRow(10,'No attendance records found.');
}

async function loadLeaves(){
    const d=await apiGet('leaves');
    leaveRows.innerHTML=(d.rows||[]).map(r=>`<tr><td>${esc(r.employee_name)}</td><td>${esc(r.branch_name||'-')}</td><td>${esc(r.leave_type)}</td><td>${esc(r.from_date)}</td><td>${esc(r.to_date)}</td><td>${esc(r.total_days)}</td><td>${esc(r.status)}</td></tr>`).join('')||emptyRow(7,'No leave records found.');
}

function exportReport(format){const q=new URLSearchParams(new FormData(reportForm));q.set('action','export');q.set('format',format);window.open(API+'?'+q.toString(),'_blank');}

employeeForm.addEventListener('submit',async e=>{e.preventDefault();const d=await submitForm(e.target);if(d.ok){e.target.reset();await loadMasters();loadEmployees();}});
deviceForm.addEventListener('submit',async e=>{e.preventDefault();const d=await submitForm(e.target);if(d.ok){e.target.reset();await loadMasters();loadDevices();loadDashboard();}});
mappingForm.addEventListener('submit',async e=>{e.preventDefault();const d=await submitForm(e.target);if(d.ok){e.target.reset();loadMappings();}});
shiftForm.addEventListener('submit',async e=>{e.preventDefault();const d=await submitForm(e.target);if(d.ok){e.target.reset();await loadMasters();loadShifts();}});
leaveForm.addEventListener('submit',async e=>{e.preventDefault();const d=await submitForm(e.target);if(d.ok){e.target.reset();loadLeaves();loadAttendance();}});

document.addEventListener('DOMContentLoaded',async()=>{await loadMasters();await loadDashboard();});
setInterval(loadDashboard,60000);
</script>
</body>
</html>
