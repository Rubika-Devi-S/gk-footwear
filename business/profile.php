<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/permissions.php';
require_once __DIR__.'/includes/csrf.php';
if(function_exists('require_business_login')) require_business_login();

function pf_e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pf_csrf(){
    if(function_exists('csrf_token')) return (string)csrf_token();
    if(empty($_SESSION['business_csrf_token'])) $_SESSION['business_csrf_token']=bin2hex(random_bytes(32));
    return (string)$_SESSION['business_csrf_token'];
}
$pageTitle='My Profile';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=pf_e($pageTitle)?> - GK Footwear POS</title>
<?php if(file_exists(__DIR__.'/includes/links.php')) include __DIR__.'/includes/links.php'; ?>
<style>
.profile-page{font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:12px;color:var(--text-main,#0f172a)}
.profile-hero,.profile-card{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:18px;box-shadow:0 10px 28px rgba(15,23,42,.06)}
.profile-hero{padding:16px}.profile-card{overflow:hidden}.profile-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#dbe4f0)}
.profile-body{padding:16px}.profile-grid{display:grid;grid-template-columns:320px minmax(0,1fr);gap:14px}
.avatar-wrap{position:relative;width:124px;height:124px;margin:auto}.avatar{width:124px;height:124px;border-radius:34px;display:grid;place-items:center;background:linear-gradient(135deg,#2563eb,#06b6d4);color:#fff;font-size:30px;font-weight:900;object-fit:cover;border:4px solid #fff;box-shadow:0 8px 24px rgba(15,23,42,.16)}.avatar-edit{position:absolute;right:4px;bottom:4px;width:34px;height:34px;padding:0;border-radius:50%;display:grid;place-items:center;border:2px solid #fff;box-shadow:0 5px 14px rgba(15,23,42,.22);z-index:3}.avatar-edit svg{width:15px;height:15px}.photo-actions{display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin-top:12px}.photo-action{border-radius:999px;font-size:10px;font-weight:800;padding:6px 10px}.photo-editor-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.65);z-index:2000;display:none;align-items:center;justify-content:center;padding:16px}.photo-editor-backdrop.show{display:flex}.photo-editor{width:min(720px,100%);background:#fff;border-radius:20px;box-shadow:0 24px 70px rgba(0,0,0,.28);overflow:hidden}.photo-editor-head,.photo-editor-foot{padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid #e2e8f0}.photo-editor-foot{border-top:1px solid #e2e8f0;border-bottom:0}.photo-editor-body{padding:16px;display:grid;grid-template-columns:300px minmax(0,1fr);gap:18px}.crop-frame{width:280px;height:280px;border-radius:28px;overflow:hidden;background:#e2e8f0;position:relative;touch-action:none;cursor:grab;margin:auto}.crop-frame:active{cursor:grabbing}.crop-frame img{position:absolute;left:50%;top:50%;max-width:none;user-select:none;pointer-events:none;transform-origin:center}.editor-controls{display:grid;gap:12px}.position-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}.position-btn{border:1px solid #dbe4f0;background:#f8fafc;border-radius:10px;padding:7px;font-size:10px;font-weight:800}.image-help{font-size:10px;color:#64748b;text-align:center;margin-top:8px}.bio-field{min-height:94px;resize:vertical}
.profile-name{font-size:20px;font-weight:900;text-align:center;margin-top:12px}.muted{color:#64748b}.badge-soft{display:inline-flex;padding:5px 9px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-weight:800}
.info-row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #eef2f7}.info-row:last-child{border-bottom:0}
.form-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.form-control{border-radius:12px;min-height:40px}
.btn-profile{border-radius:999px;font-size:11px;font-weight:800;padding:8px 14px}
.branch-chip{display:inline-flex;padding:5px 8px;border-radius:999px;background:#ecfeff;color:#0e7490;font-weight:800;margin:2px}
@media(max-width:991px){.profile-grid{grid-template-columns:1fr}}
</style></head><body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035"></div>
<?php if(file_exists(__DIR__.'/includes/page-message.php')) include __DIR__.'/includes/page-message.php'; ?>
<?php if(file_exists(__DIR__.'/includes/common-toast.php')) include __DIR__.'/includes/common-toast.php'; ?>
<div class="min-vh-100 d-flex">
<?php if(file_exists(__DIR__.'/includes/sidebar.php')) include __DIR__.'/includes/sidebar.php'; ?>
<main id="main">
<?php if(file_exists(__DIR__.'/includes/nav.php')) include __DIR__.'/includes/nav.php'; ?>
<section class="page-section profile-page p-3">
<div class="profile-hero mb-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
<div><h1 class="h4 fw-bold mb-1">My Profile</h1><div class="muted">Manage your personal information and account password.</div></div>
<a href="dashboard.php" class="btn btn-light btn-profile"><i data-lucide="layout-dashboard"></i> Dashboard</a>
</div>
<div class="profile-grid">
<div class="profile-card">
<div class="profile-body">
<div class="avatar-wrap">
<img class="avatar d-none" id="profileImage" alt="Profile photo">
<div class="avatar" id="avatarText">U</div>
<button type="button" class="btn btn-primary avatar-edit" id="cameraButton" title="Change profile photo"><i data-lucide="camera"></i></button>
</div>
<form id="imageForm" enctype="multipart/form-data">
<input type="hidden" name="action" value="upload_profile_image">
<input type="hidden" name="csrf_token" value="<?=pf_e(pf_csrf())?>">
<input type="file" class="d-none" id="profileImageInput" name="profile_image" accept="image/*,.jpg,.jpeg,.png,.gif,.webp,.bmp,.avif,.heic,.heif,.tif,.tiff" required>
</form>
<div class="photo-actions">
<button type="button" class="btn btn-light photo-action" id="changePhotoBtn"><i data-lucide="image-plus"></i> Change</button>
<button type="button" class="btn btn-light photo-action d-none" id="adjustPhotoBtn"><i data-lucide="move"></i> Adjust</button>
<button type="button" class="btn btn-outline-danger photo-action d-none" id="removePhotoBtn"><i data-lucide="trash-2"></i> Remove</button>
</div>
<div class="image-help">Choose image, move it, zoom it and set the correct profile position.</div>
<div class="profile-name" id="displayName">Loading...</div>
<div class="text-center mt-2"><span class="badge-soft" id="roleName">-</span></div>
<div class="text-center muted mt-2" id="businessName">-</div>
<hr>
<div class="info-row"><span class="muted">Username</span><strong id="username">-</strong></div>
<div class="info-row"><span class="muted">Default Branch</span><strong id="defaultBranch">-</strong></div>
<div class="info-row"><span class="muted">Last Login</span><strong id="lastLogin">-</strong></div>
<div class="info-row"><span class="muted">Member Since</span><strong id="createdAt">-</strong></div>
<div class="mt-3"><div class="fw-bold mb-2">Accessible Branches</div><div id="branchAccess"><span class="muted">Loading...</span></div></div>
</div></div>
<div>
<div class="profile-card mb-3">
<div class="profile-head"><h2 class="h6 fw-bold mb-0">Personal Information</h2></div>
<div class="profile-body">
<form id="profileForm">
<input type="hidden" name="action" value="update_profile"><input type="hidden" name="csrf_token" value="<?=pf_e(pf_csrf())?>">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="name" id="name" maxlength="150" required></div>
<div class="col-md-6"><label class="form-label">Mobile</label><input class="form-control" name="mobile" id="mobile" maxlength="20"></div>
<div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="email" maxlength="150"></div>
<div class="col-md-6"><label class="form-label">Username</label><input class="form-control" id="usernameReadonly" readonly></div>
<div class="col-md-6"><label class="form-label">Designation</label><input class="form-control" name="designation" id="designation" maxlength="120"></div>
<div class="col-md-3"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="date_of_birth" id="dateOfBirth"></div>
<div class="col-md-3"><label class="form-label">Gender</label><select class="form-control" name="gender" id="gender"><option value="">Select</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option><option value="prefer_not_to_say">Prefer not to say</option></select></div>
<div class="col-md-6"><label class="form-label">Emergency Contact</label><input class="form-control" name="emergency_contact" id="emergencyContact" maxlength="30"></div>
<div class="col-12"><label class="form-label">Address</label><textarea class="form-control bio-field" name="profile_address" id="profileAddress" maxlength="500"></textarea></div>
<div class="col-12"><label class="form-label">About / Bio</label><textarea class="form-control bio-field" name="profile_bio" id="profileBio" maxlength="1000"></textarea></div>
</div>
<div class="mt-3 text-end"><button class="btn btn-primary btn-profile" id="saveProfile"><i data-lucide="save"></i> Save Profile</button></div>
</form></div></div>
<div class="profile-card">
<div class="profile-head"><h2 class="h6 fw-bold mb-0">Change Password</h2></div>
<div class="profile-body">
<form id="passwordForm">
<input type="hidden" name="action" value="change_password"><input type="hidden" name="csrf_token" value="<?=pf_e(pf_csrf())?>">
<div class="row g-3">
<div class="col-12"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required autocomplete="current-password"></div>
<div class="col-md-6"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" minlength="8" required autocomplete="new-password"></div>
<div class="col-md-6"><label class="form-label">Confirm Password</label><input type="password" class="form-control" name="confirm_password" minlength="8" required autocomplete="new-password"></div>
</div>
<div class="muted mt-2">Use at least 8 characters.</div>
<div class="mt-3 text-end"><button class="btn btn-dark btn-profile" id="changePassword"><i data-lucide="key-round"></i> Update Password</button></div>
</form></div></div>
</div></div>
<?php if(file_exists(__DIR__.'/includes/footer.php')) include __DIR__.'/includes/footer.php'; ?>
</section></main></div>

<div class="photo-editor-backdrop" id="photoEditorBackdrop">
<div class="photo-editor">
<div class="photo-editor-head"><div><strong>Adjust Profile Photo</strong><div class="muted">Drag the photo and use zoom to set the correct position.</div></div><button type="button" class="btn-close" id="closePhotoEditor"></button></div>
<div class="photo-editor-body">
<div class="crop-frame" id="cropFrame"><img id="editorImage" alt="Adjust profile photo"></div>
<div class="editor-controls">
<div><label class="form-label">Zoom</label><input type="range" class="form-range" id="zoomRange" min="1" max="3" value="1" step="0.01"></div>
<div><label class="form-label">Quick Position</label><div class="position-grid">
<button type="button" class="position-btn" data-position="top-left">Top Left</button><button type="button" class="position-btn" data-position="top">Top</button><button type="button" class="position-btn" data-position="top-right">Top Right</button>
<button type="button" class="position-btn" data-position="left">Left</button><button type="button" class="position-btn" data-position="center">Center</button><button type="button" class="position-btn" data-position="right">Right</button>
<button type="button" class="position-btn" data-position="bottom-left">Bottom Left</button><button type="button" class="position-btn" data-position="bottom">Bottom</button><button type="button" class="position-btn" data-position="bottom-right">Bottom Right</button>
</div></div>
<div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-light btn-profile" id="rotateLeft"><i data-lucide="rotate-ccw"></i> Left</button><button type="button" class="btn btn-light btn-profile" id="rotateRight"><i data-lucide="rotate-cw"></i> Right</button><button type="button" class="btn btn-light btn-profile" id="resetPhoto"><i data-lucide="refresh-cw"></i> Reset</button></div>
<div class="muted">The saved photo is cropped to a square and works correctly in navbar and profile cards.</div>
</div></div>
<div class="photo-editor-foot"><button type="button" class="btn btn-light btn-profile" id="cancelPhotoEditor">Cancel</button><button type="button" class="btn btn-primary btn-profile" id="saveAdjustedPhoto"><i data-lucide="check"></i> Set Profile Photo</button></div>
</div></div>

<?php if(file_exists(__DIR__.'/includes/script.php')) include __DIR__.'/includes/script.php'; ?>
<script>
(function(){'use strict';
const api='api/profile-api.php';
function toast(type,msg){if(window.AppToast&&AppToast.show)return AppToast.show(type,msg);if(window.showToast)return showToast(msg,type==='error'?'danger':type);alert(msg)}
function esc(v){return String(v??'').replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]))}
function initials(n){return String(n||'U').trim().split(/\s+/).slice(0,2).map(x=>x.charAt(0)).join('').toUpperCase()||'U'}
function fmt(v){if(!v)return '-';const d=new Date(String(v).replace(' ','T'));return isNaN(d)?v:d.toLocaleString('en-IN')}
async function load(){
 try{const r=await fetch(api+'?action=get_profile&_='+Date.now(),{credentials:'same-origin',cache:'no-store'});const j=await r.json();if(!j.success)throw Error(j.message);
 const p=j.data.profile,b=j.data.branches||[];
 document.getElementById('displayName').textContent=p.display_name||'-';document.getElementById('avatarText').textContent=initials(p.display_name);
 document.getElementById('roleName').textContent=p.role_name||'-';document.getElementById('businessName').textContent=p.business_name||'-';
 ['username','defaultBranch'].forEach(()=>{});
 document.getElementById('username').textContent=p.username||'-';document.getElementById('usernameReadonly').value=p.username||'';
 document.getElementById('defaultBranch').textContent=p.default_branch_name||'Business level';document.getElementById('lastLogin').textContent=fmt(p.last_login_at);document.getElementById('createdAt').textContent=fmt(p.created_at);
 document.getElementById('name').value=p.display_name||'';document.getElementById('mobile').value=p.mobile||'';document.getElementById('email').value=p.email||'';
 document.getElementById('designation').value=p.designation||'';document.getElementById('dateOfBirth').value=p.date_of_birth||'';document.getElementById('gender').value=p.gender||'';document.getElementById('emergencyContact').value=p.emergency_contact||'';document.getElementById('profileAddress').value=p.profile_address||'';document.getElementById('profileBio').value=p.profile_bio||'';
 const img=document.getElementById('profileImage'),av=document.getElementById('avatarText');currentProfileImage=p.profile_image||'';if(p.profile_image){img.src=p.profile_image+'?v='+(p.updated_at||Date.now());img.classList.remove('d-none');av.classList.add('d-none');document.getElementById('adjustPhotoBtn').classList.remove('d-none');document.getElementById('removePhotoBtn').classList.remove('d-none')}else{img.classList.add('d-none');av.classList.remove('d-none');document.getElementById('adjustPhotoBtn').classList.add('d-none');document.getElementById('removePhotoBtn').classList.add('d-none')}
 document.getElementById('branchAccess').innerHTML=b.length?b.map(x=>'<span class="branch-chip">'+esc(x.branch_name+(x.floor_name?' / '+x.floor_name:''))+'</span>').join(''):'<span class="muted">'+esc(j.data.access_scope||'Business level')+'</span>';
 if(window.lucide)lucide.createIcons();
 }catch(e){toast('error',e.message||'Unable to load profile.')}
}
async function send(form,button){
 const old=button.innerHTML;button.disabled=true;button.innerHTML='Please wait...';
 try{const r=await fetch(api,{method:'POST',body:new FormData(form),credentials:'same-origin'});const j=await r.json();if(!j.success)throw Error(j.message);toast('success',j.message);if(form.id==='passwordForm')form.reset();await load();}
 catch(e){toast('error',e.message||'Request failed.')}finally{button.disabled=false;button.innerHTML=old;if(window.lucide)lucide.createIcons();}
}
document.getElementById('profileForm').addEventListener('submit',e=>{e.preventDefault();send(e.currentTarget,document.getElementById('saveProfile'))});
document.getElementById('passwordForm').addEventListener('submit',e=>{e.preventDefault();send(e.currentTarget,document.getElementById('changePassword'))});


let currentProfileImage='';
let editorSource='', editorNaturalW=1, editorNaturalH=1, editorZoom=1, editorX=0, editorY=0, editorRotation=0, dragging=false, dragStartX=0, dragStartY=0, startX=0, startY=0;
const backdrop=document.getElementById('photoEditorBackdrop'), editorImg=document.getElementById('editorImage'), frame=document.getElementById('cropFrame');
function updateEditor(){editorImg.style.transform='translate(calc(-50% + '+editorX+'px),calc(-50% + '+editorY+'px)) rotate('+editorRotation+'deg) scale('+editorZoom+')'}
function resetEditor(){editorZoom=1;editorX=0;editorY=0;editorRotation=0;document.getElementById('zoomRange').value='1';updateEditor()}
function openEditor(src){editorSource=src;editorImg.onload=function(){editorNaturalW=this.naturalWidth||1;editorNaturalH=this.naturalHeight||1;const scale=Math.max(280/editorNaturalW,280/editorNaturalH);this.style.width=(editorNaturalW*scale)+'px';this.style.height=(editorNaturalH*scale)+'px';resetEditor();backdrop.classList.add('show')};editorImg.onerror=function(){toast('error','This image cannot be previewed in the browser. Please use JPG, PNG or WebP.');};editorImg.src=src}
function closeEditor(){backdrop.classList.remove('show')}
function choosePhoto(){document.getElementById('profileImageInput').click()}
document.getElementById('cameraButton').onclick=choosePhoto;document.getElementById('changePhotoBtn').onclick=choosePhoto;
document.getElementById('adjustPhotoBtn').onclick=function(){if(currentProfileImage)openEditor(currentProfileImage+'?v='+Date.now())};
document.getElementById('closePhotoEditor').onclick=closeEditor;document.getElementById('cancelPhotoEditor').onclick=closeEditor;
document.getElementById('zoomRange').oninput=function(){editorZoom=parseFloat(this.value)||1;updateEditor()};
frame.addEventListener('pointerdown',e=>{dragging=true;frame.setPointerCapture(e.pointerId);dragStartX=e.clientX;dragStartY=e.clientY;startX=editorX;startY=editorY});
frame.addEventListener('pointermove',e=>{if(!dragging)return;editorX=startX+(e.clientX-dragStartX);editorY=startY+(e.clientY-dragStartY);updateEditor()});
frame.addEventListener('pointerup',()=>dragging=false);frame.addEventListener('pointercancel',()=>dragging=false);
document.querySelectorAll('.position-btn').forEach(b=>b.onclick=function(){const p=this.dataset.position;editorX=p.includes('left')?60:p.includes('right')?-60:0;editorY=p.includes('top')?60:p.includes('bottom')?-60:0;updateEditor()});
document.getElementById('rotateLeft').onclick=()=>{editorRotation-=90;updateEditor()};document.getElementById('rotateRight').onclick=()=>{editorRotation+=90;updateEditor()};document.getElementById('resetPhoto').onclick=resetEditor;
document.getElementById('profileImageInput').addEventListener('change',function(){const f=this.files&&this.files[0];if(!f)return;if(f.size>10*1024*1024){toast('error','Image must be 10 MB or smaller.');this.value='';return;}openEditor(URL.createObjectURL(f))});
document.getElementById('saveAdjustedPhoto').onclick=async function(){
 const canvas=document.createElement('canvas');canvas.width=800;canvas.height=800;const ctx=canvas.getContext('2d');ctx.fillStyle='#ffffff';ctx.fillRect(0,0,800,800);
 const base=Math.max(800/editorNaturalW,800/editorNaturalH);ctx.save();ctx.translate(400+editorX*(800/280),400+editorY*(800/280));ctx.rotate(editorRotation*Math.PI/180);ctx.scale(base*editorZoom,base*editorZoom);ctx.drawImage(editorImg,-editorNaturalW/2,-editorNaturalH/2);ctx.restore();
 canvas.toBlob(async blob=>{if(!blob)return toast('error','Unable to prepare image.');const fd=new FormData();fd.append('action','upload_profile_image');fd.append('csrf_token',document.querySelector('#imageForm [name=csrf_token]').value);fd.append('profile_image',blob,'profile-'+Date.now()+'.jpg');this.disabled=true;try{const r=await fetch(api,{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json();if(!j.success)throw Error(j.message);toast('success',j.message);closeEditor();document.getElementById('profileImageInput').value='';await load()}catch(e){toast('error',e.message||'Upload failed.')}finally{this.disabled=false}},'image/jpeg',0.9)
};
document.getElementById('removePhotoBtn').onclick=async function(){if(!confirm('Remove your profile photo?'))return;const fd=new FormData();fd.append('action','remove_profile_image');fd.append('csrf_token',document.querySelector('#imageForm [name=csrf_token]').value);try{const r=await fetch(api,{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json();if(!j.success)throw Error(j.message);toast('success',j.message);await load()}catch(e){toast('error',e.message)}};

load();
})();
</script></body></html>