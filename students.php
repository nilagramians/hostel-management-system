<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='students';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];
$message='';

// ADD STUDENT
if(isset($_POST['add_student'])){
    $name=trim($_POST['name']);$email=trim($_POST['email']);
    $password=password_hash(trim($_POST['password']),PASSWORD_BCRYPT);
    $cnic=trim($_POST['cnic']);$phone=trim($_POST['phone']);
    $address=trim($_POST['address']);$room_id=intval($_POST['room_id']);
    $gender=$_POST['gender']??'male';$dob=$_POST['dob']??null;
    $guardian=trim($_POST['guardian']??'');$guardian_phone=trim($_POST['guardian_phone']??'');
    $avatar_colors=['#00D4FF','#00FF88','#FF4D6D','#FFAA00','#7C3AED','#EC4899','#00FFCC'];
    $avatar_color=$avatar_colors[array_rand($avatar_colors)];

    if(!validateCNIC($cnic)){$message='error:Invalid CNIC. Use format: 35202-1234567-1';}
    elseif(!validatePhone($phone)){$message='error:Invalid phone. Use: 03001234567 or 0300-1234567';}
    else{
        $pic_name=null;
        if(isset($_FILES['profile_pic'])&&$_FILES['profile_pic']['error']===0){
    $ext=strtolower(pathinfo($_FILES['profile_pic']['name'],PATHINFO_EXTENSION));
    $allowed=['jpg','jpeg','png','gif','webp'];
    $max_size=5*1024*1024; // 5MB

    if(!in_array($ext,$allowed)){
        $message='error:Invalid file type. Use JPG, PNG or GIF.';
    } elseif($_FILES['profile_pic']['size']>$max_size){
        $message='error:File too large. Max 5MB.';
    } else {
        $upload_dir=dirname(__DIR__).'/assets/uploads/profiles/';

        // Create folder if it doesn't exist
        if(!is_dir($upload_dir)){
            mkdir($upload_dir, 0755, true);
        }

        $pic_name=uniqid('s_',true).'.'.$ext;
        $upload_path=$upload_dir.$pic_name;

        if(move_uploaded_file($_FILES['profile_pic']['tmp_name'],$upload_path)){
            // success - $pic_name is ready to save to DB
        } else {
            $message='error:Upload failed. Check folder permissions.';
            $pic_name=null;
        }
    }
}
        $stmt=$conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'student')");
        $stmt->bind_param("sss",$name,$email,$password);
        if($stmt->execute()){
            $new_uid=$conn->insert_id;
            $code=generateStudentCode();
            $stmt2=$conn->prepare("INSERT INTO students (user_id,student_code,cnic,phone,address,room_id,profile_pic,avatar_color,gender,dob,guardian,guardian_phone,enrollment_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,CURDATE())");
            $stmt2->bind_param("issssissssss",$new_uid,$code,$cnic,$phone,$address,$room_id,$pic_name,$avatar_color,$gender,$dob,$guardian,$guardian_phone);
            $stmt2->execute();
            if($room_id)$conn->query("UPDATE rooms SET occupied=occupied+1 WHERE room_id=$room_id");
            $conn->query("INSERT INTO settings (user_id,theme) VALUES ($new_uid,'dark')");
            sendNotification($conn,$new_uid,'🎉 Welcome to Hostel Pro!',"Hi $name! Your account has been created. Student Code: $code. Login to view your dashboard.",'success','../student/dashboard.php');
            logActivity($conn,$uid,'Add Student',"Added student: $name ($code)");
            $message='success:Student added! Code: '.$code;
        }else{$message='error:Email already exists.';}
    }
}

// EDIT STUDENT
if(isset($_POST['edit_student'])){
    $sid=intval($_POST['student_id']);
    $name=trim($_POST['name']);$email=trim($_POST['email']);
    $phone=trim($_POST['phone']);$address=trim($_POST['address']);
    $room_id=intval($_POST['room_id'])?:null;$gender=$_POST['gender'];
    $guardian=trim($_POST['guardian']??'');$guardian_phone=trim($_POST['guardian_phone']??'');
    $status=$_POST['status']??'active';

    if(!validatePhone($phone)){$message='error:Invalid phone number.';}
    else{
        // Handle room change
        $old=$conn->query("SELECT room_id FROM students WHERE student_id=$sid")->fetch_assoc();
        if($old['room_id']!=$room_id){
            if($old['room_id'])$conn->query("UPDATE rooms SET occupied=occupied-1 WHERE room_id={$old['room_id']}");
            if($room_id)$conn->query("UPDATE rooms SET occupied=occupied+1 WHERE room_id=$room_id");
        }
        $rstmt=$conn->prepare("UPDATE students SET phone=?,address=?,room_id=?,gender=?,guardian=?,guardian_phone=?,status=? WHERE student_id=?");
        $rstmt->bind_param("ssissssi",$phone,$address,$room_id,$gender,$guardian,$guardian_phone,$status,$sid);
        $rstmt->execute();
        $ustmt=$conn->prepare("UPDATE users SET name=?,email=? WHERE user_id=(SELECT user_id FROM students WHERE student_id=?)");
        $ustmt->bind_param("ssi",$name,$email,$sid);
        $ustmt->execute();
        logActivity($conn,$uid,'Edit Student',"Edited student ID: $sid");
        $message='success:Student updated successfully!';
    }
}

// DELETE
if(isset($_GET['delete'])){
    $sid=intval($_GET['delete']);
    $s=$conn->query("SELECT s.user_id,s.room_id,s.profile_pic FROM students s WHERE s.student_id=$sid")->fetch_assoc();
    if($s){
        if($s['room_id'])$conn->query("UPDATE rooms SET occupied=occupied-1 WHERE room_id={$s['room_id']}");
        if($s['profile_pic']&&file_exists('../assets/uploads/profiles/'.$s['profile_pic']))@unlink('../assets/uploads/profiles/'.$s['profile_pic']);
        $conn->query("DELETE FROM users WHERE user_id={$s['user_id']}");
        logActivity($conn,$uid,'Delete Student',"Deleted student ID: $sid");
    }
    header("Location: students.php?msg=deleted");exit();
}

// TOGGLE STATUS
if(isset($_GET['toggle'])){
    $sid=intval($_GET['toggle']);
    $s=$conn->query("SELECT status FROM students WHERE student_id=$sid")->fetch_assoc();
    $ns=$s['status']==='active'?'inactive':'active';
    $conn->query("UPDATE students SET status='$ns' WHERE student_id=$sid");
    header("Location: students.php?msg=updated");exit();
}

$students=$conn->query("SELECT s.*,u.name,u.email,r.room_number FROM students s JOIN users u ON s.user_id=u.user_id LEFT JOIN rooms r ON s.room_id=r.room_id ORDER BY s.student_id DESC");
$rooms=$conn->query("SELECT * FROM rooms WHERE occupied<capacity AND status='available' ORDER BY room_number");
$all_rooms=$conn->query("SELECT * FROM rooms ORDER BY room_number");
$page_title='Students';$page_subtitle='Manage all hostel students';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?=$theme_data['theme']?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Students — Hostel Pro</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in">
<?php include('../includes/topbar.php');?>

<?php if($message):[$t,$m]=explode(":",$message,2);?><div class="alert <?=$t?>"><?=$t==='success'?'✅':'⚠'?> <?=htmlspecialchars($m)?></div><?php endif;?>
<?php if(isset($_GET['msg'])):?><div class="alert success">✅ <?=$_GET['msg']==='deleted'?'Student deleted.':'Record updated.'?></div><?php endif;?>

<!-- Add Form -->
<div class="form-card">
    <div class="form-card-title">➕ Add New Student</div>
    <form method="POST" enctype="multipart/form-data">
        <!-- Photo upload -->
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px;padding:14px;background:rgba(0,0,0,.2);border:1px dashed var(--glass-border);border-radius:10px">
            <div style="position:relative">
    <img id="preview" 
         style="width:60px;height:60px;border-radius:50%;object-fit:cover;
                border:2px solid rgba(0,212,255,0.3);display:none">
    <div id="previewAv" 
         style="width:60px;height:60px;border-radius:50%;background:#00d4ff;
                display:flex;align-items:center;justify-content:center;
                font-size:20px;font-weight:800;color:#000;
                border:2px solid rgba(0,212,255,0.3)">?</div>
</div>
            <div>
                <label style="padding:8px 16px;background:rgba(0,212,255,0.08);color:var(--accent);border:1px solid rgba(0,212,255,0.2);border-radius:8px;cursor:pointer;font-size:12px">
                    📷 Choose Photo (optional)
                    <input type="file" name="profile_pic" accept="image/*" style="display:none" onchange="prevPic(this)">
                </label>
                <div style="font-size:11px;color:var(--text3);margin-top:4px">JPG/PNG/GIF · Max 2MB · If no photo, initials avatar auto-generated</div>
            </div>
        </div>
        <div class="form-grid">
            <input type="text"     name="name"           placeholder="Full Name"                required oninput="updateInitials(this.value)">
            <input type="email"    name="email"          placeholder="Email"                    required>
            <input type="password" name="password"       placeholder="Password (min 8 chars)"   required>
            <input type="text"     name="cnic"           placeholder="CNIC: 35202-1234567-1"    required>
            <input type="text"     name="phone"          placeholder="Phone: 03001234567"       required>
            <select name="gender">
                <option value="male">👨 Male</option>
                <option value="female">👩 Female</option>
                <option value="other">🧑 Other</option>
            </select>
            <input type="date"     name="dob"            placeholder="Date of Birth">
            <input type="text"     name="guardian"       placeholder="Guardian Name">
            <input type="text"     name="guardian_phone" placeholder="Guardian Phone">
            <select name="room_id" required>
                <option value="">— Assign Room —</option>
                <?php $rooms->data_seek(0);while($rm=$rooms->fetch_assoc()):?>
                <option value="<?=$rm['room_id']?>">Room <?=htmlspecialchars($rm['room_number'])?> · <?=ucfirst($rm['room_type'])?> · <?=$rm['capacity']-$rm['occupied']?> free · PKR <?=number_format($rm['price'],0)?>/mo</option>
                <?php endwhile;?>
            </select>
            <textarea name="address" placeholder="Full Address" style="grid-column:span 2;min-height:44px;resize:vertical"></textarea>
        </div>
        <div class="action-row"><button type="submit" name="add_student" class="btn btn-primary">➕ Add Student</button></div>
    </form>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <input type="text" id="searchInput" placeholder="🔍 Search name, email, CNIC, phone, code..." oninput="filterStudents()">
    <select id="roomFilter" onchange="filterStudents()">
        <option value="">🛏️ All Rooms</option>
        <?php $all_rooms->data_seek(0);while($ar=$all_rooms->fetch_assoc()):?><option value="<?=$ar['room_number']?>">Room <?=$ar['room_number']?></option><?php endwhile;?>
    </select>
    <select id="statusFilter" onchange="filterStudents()">
        <option value="">👥 All Status</option>
        <option value="active">✅ Active</option>
        <option value="inactive">❌ Inactive</option>
        <option value="suspended">⛔ Suspended</option>
    </select>
    <select id="genderFilter" onchange="filterStudents()">
        <option value="">⚧ All Gender</option>
        <option value="male">👨 Male</option>
        <option value="female">👩 Female</option>
    </select>
    <button class="btn btn-outline btn-sm" onclick="clearFilters()">✕ Clear</button>
    <div id="result-count"></div>
</div>

<div class="empty-state" id="no-results" style="display:none;margin-bottom:16px">
    <div class="empty-icon">🔍</div><h3>No students found</h3><p>Try a different search term</p>
</div>

<div class="student-grid" id="studentGrid">
<?php while($s=$students->fetch_assoc()):
    $av=generateAvatar($s['name'],$s['avatar_color']??'#00D4FF');
    $hasPic=$s['profile_pic']&&file_exists('../assets/uploads/profiles/'.$s['profile_pic']);
?>
<div class="student-card"
     data-name="<?=strtolower(htmlspecialchars($s['name']))?>"
     data-email="<?=strtolower(htmlspecialchars($s['email']))?>"
     data-cnic="<?=htmlspecialchars($s['cnic'])?>"
     data-phone="<?=htmlspecialchars($s['phone'])?>"
     data-code="<?=strtolower(htmlspecialchars($s['student_code']??''))?>"
     data-room="<?=strtolower(htmlspecialchars($s['room_number']??''))?>"
     data-status="<?=htmlspecialchars($s['status']??'active')?>"
     data-gender="<?=htmlspecialchars($s['gender']??'male')?>">
    <?php if($hasPic):?>
        <img src="../assets/uploads/profiles/<?=htmlspecialchars($s['profile_pic'])?>" class="avatar-lg" style="margin:0 auto 12px;display:block" onerror="this.style.display='none'">
    <?php else:?>
        <div class="avatar-initials-lg" style="background:<?=htmlspecialchars($av['color'])?>"><?=htmlspecialchars($av['initials'])?></div>
    <?php endif;?>
    <div style="font-size:14px;font-weight:700;margin-bottom:4px"><?=htmlspecialchars($s['name'])?></div>
    <?php if($s['student_code']):?><span class="badge badge-muted" style="margin-bottom:6px"><?=htmlspecialchars($s['student_code'])?></span><?php endif;?>
    <div style="font-size:11px;color:var(--text3);margin-bottom:2px">📧 <?=htmlspecialchars($s['email'])?></div>
    <div style="font-size:11px;color:var(--text3);margin-bottom:2px">📞 <?=htmlspecialchars($s['phone'])?></div>
    <div style="font-size:11px;color:var(--text3);margin-bottom:8px">🪪 <?=htmlspecialchars($s['cnic'])?></div>
    <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin-bottom:10px">
        <span style="padding:3px 10px;background:rgba(0,212,255,0.08);color:var(--accent);border-radius:20px;font-size:11px;border:1px solid rgba(0,212,255,0.15)">🛏️ <?=htmlspecialchars($s['room_number']??'No Room')?></span>
        <span class="badge <?=$s['status']==='active'?'badge-success':($s['status']==='suspended'?'badge-danger':'badge-muted')?>"><?=ucfirst($s['status']??'active')?></span>
    </div>
    <div style="display:flex;gap:6px;justify-content:center">
        <button onclick="openEdit(<?=htmlspecialchars(json_encode($s))?>,<?=$all_rooms->num_rows?>)" class="btn btn-outline btn-sm">✏️ Edit</button>
        <a href="?toggle=<?=$s['student_id']?>" class="btn btn-warning btn-sm" onclick="return confirm('Toggle status?')">⚙️</a>
        <a href="?delete=<?=$s['student_id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete student? Cannot be undone.')">🗑</a>
    </div>
</div>
<?php endwhile;?>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:600px">
        <button class="modal-close" onclick="closeEdit()">✕</button>
        <div class="modal-title">✏️ Edit Student</div>
        <form method="POST" id="editForm">
            <input type="hidden" name="student_id" id="edit_sid">
            <div class="form-grid" style="margin-bottom:14px">
                <input type="text"  name="name"     id="edit_name"    placeholder="Full Name" required>
                <input type="email" name="email"    id="edit_email"   placeholder="Email"     required>
                <input type="text"  name="phone"    id="edit_phone"   placeholder="Phone"     required>
                <select name="gender" id="edit_gender">
                    <option value="male">👨 Male</option>
                    <option value="female">👩 Female</option>
                    <option value="other">🧑 Other</option>
                </select>
                <select name="room_id" id="edit_room">
                    <option value="">— No Room —</option>
                    <?php $all_rooms->data_seek(0);while($ar=$all_rooms->fetch_assoc()):?>
                    <option value="<?=$ar['room_id']?>">Room <?=htmlspecialchars($ar['room_number'])?> (<?=$ar['capacity']-$ar['occupied']?> free)</option>
                    <?php endwhile;?>
                </select>
                <select name="status" id="edit_status">
                    <option value="active">✅ Active</option>
                    <option value="inactive">❌ Inactive</option>
                    <option value="suspended">⛔ Suspended</option>
                </select>
                <input type="text" name="guardian"       id="edit_guardian"    placeholder="Guardian Name">
                <input type="text" name="guardian_phone" id="edit_guardian_ph" placeholder="Guardian Phone">
                <textarea name="address" id="edit_address" placeholder="Address" style="grid-column:span 3;min-height:60px;resize:vertical"></textarea>
            </div>
            <div class="action-row">
                <button type="submit" name="edit_student" class="btn btn-primary">Save Changes</button>
                <button type="button" onclick="closeEdit()" class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>
</div>

<script>
function prevPic(input){
    if(input.files && input.files[0]){
        const reader = new FileReader();
        reader.onload = function(e){
            const img = document.getElementById('preview');
            const av  = document.getElementById('previewAv');

            // Show photo, hide avatar
            img.src = e.target.result;
            img.style.display = 'block';
            av.style.display  = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function updateInitials(n){
    const parts = n.trim().split(' ').filter(Boolean);
    const init  = (parts[0]?.[0] || '') + (parts[1]?.[0] || '');
    const av    = document.getElementById('previewAv');
    const img   = document.getElementById('preview');

    // Only show avatar if no photo uploaded yet
    if(init && img.style.display === 'none'){
        av.textContent  = init.toUpperCase();
        av.style.display = 'flex';
    } else if(init) {
        av.textContent = init.toUpperCase();
    }
}
function filterStudents(){
    const s=document.getElementById('searchInput').value.toLowerCase();
    const rm=document.getElementById('roomFilter').value.toLowerCase();
    const st=document.getElementById('statusFilter').value;
    const gn=document.getElementById('genderFilter').value;
    const cards=document.querySelectorAll('#studentGrid .student-card');
    let v=0;
    cards.forEach(c=>{
        const ok=(!s||(c.dataset.name+c.dataset.email+c.dataset.cnic+c.dataset.phone+c.dataset.code).includes(s))
                &&(!rm||c.dataset.room===rm)&&(!st||c.dataset.status===st)&&(!gn||c.dataset.gender===gn);
        c.style.display=ok?'':'none';if(ok)v++;
    });
    document.getElementById('result-count').textContent=v+' student'+(v!==1?'s':'')+' found';
    document.getElementById('no-results').style.display=v===0?'block':'none';
}
function clearFilters(){['searchInput','roomFilter','statusFilter','genderFilter'].forEach(id=>document.getElementById(id).value='');filterStudents();}
window.onload=filterStudents;

function openEdit(s){
    document.getElementById('edit_sid').value=s.student_id;
    document.getElementById('edit_name').value=s.name;
    document.getElementById('edit_email').value=s.email;
    document.getElementById('edit_phone').value=s.phone;
    document.getElementById('edit_address').value=s.address||'';
    document.getElementById('edit_guardian').value=s.guardian||'';
    document.getElementById('edit_guardian_ph').value=s.guardian_phone||'';
    document.getElementById('edit_gender').value=s.gender||'male';
    document.getElementById('edit_status').value=s.status||'active';
    document.getElementById('edit_room').value=s.room_id||'';
    document.getElementById('editModal').classList.add('active');
}
function closeEdit(){document.getElementById('editModal').classList.remove('active');}
document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)closeEdit();});
</script>
</body>
</html>
