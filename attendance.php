<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='attendance';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];
$message='';$date=isset($_GET['date'])?$_GET['date']:date('Y-m-d');

if(isset($_POST['save_attendance'])){
    $adate=$_POST['att_date'];$records=$_POST['status']??[];
    foreach($records as $sid=>$status){
        $sid=intval($sid);
        $stmt=$conn->prepare("INSERT INTO attendance (student_id,date,status,method) VALUES (?,?,'present','manual') ON DUPLICATE KEY UPDATE status=?,method='manual'");
        $stmt->bind_param("iss",$sid,$adate,$status);$stmt->execute();
    }
    $message='success:Attendance saved for '.$adate.'!';$date=$adate;
    logActivity($conn,$uid,'Save Attendance',"Saved attendance for date: $adate");
}

$students=$conn->query("SELECT s.student_id,u.name,r.room_number,s.profile_pic,s.avatar_color FROM students s JOIN users u ON s.user_id=u.user_id LEFT JOIN rooms r ON s.room_id=r.room_id WHERE s.status='active' ORDER BY u.name");
$existing=[];
$res=$conn->query("SELECT student_id,status FROM attendance WHERE date='$date'");
while($r=$res->fetch_assoc())$existing[$r['student_id']]=$r['status'];

$total_s=$conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'];
$present_today=$conn->query("SELECT COUNT(*) as c FROM attendance WHERE date='$date' AND status='present'")->fetch_assoc()['c'];
$absent_today=$conn->query("SELECT COUNT(*) as c FROM attendance WHERE date='$date' AND status='absent'")->fetch_assoc()['c'];
$leave_today=$conn->query("SELECT COUNT(*) as c FROM attendance WHERE date='$date' AND status='leave'")->fetch_assoc()['c'];
$page_title='Attendance';$page_subtitle='Mark and manage daily attendance';
?>
<!DOCTYPE html><html lang="en" data-theme="<?=$theme_data['theme']?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Attendance</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in"><?php include('../includes/topbar.php');?>
<?php if($message):[$t,$m]=explode(":",$message,2);?><div class="alert <?=$t?>"><?=$t==='success'?'✅':'⚠'?> <?=htmlspecialchars($m)?></div><?php endif;?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
    <div class="stat-card" style="padding:16px"><div class="stat-num" style="font-size:20px"><?=$total_s?></div><div class="stat-label">Total Students</div></div>
    <div class="stat-card" style="padding:16px"><div class="stat-num" style="font-size:20px;color:var(--success)"><?=$present_today?></div><div class="stat-label">Present</div></div>
    <div class="stat-card" style="padding:16px"><div class="stat-num" style="font-size:20px;color:var(--danger)"><?=$absent_today?></div><div class="stat-label">Absent</div></div>
    <div class="stat-card" style="padding:16px"><div class="stat-num" style="font-size:20px;color:var(--warning)"><?=$leave_today?></div><div class="stat-label">On Leave</div></div>
</div>

<div class="form-card" style="margin-bottom:18px">
    <div class="form-card-title">📅 Select Date</div>
    <form method="GET" style="display:flex;gap:12px;align-items:center">
        <input type="date" name="date" value="<?=$date?>" max="<?=date('Y-m-d')?>" style="padding:10px 14px;background:rgba(0,0,0,.3);border:1px solid var(--glass-border);border-radius:10px;color:var(--text);font-size:13px;outline:none">
        <button type="submit" class="btn btn-primary btn-sm">View</button>
        <a href="attendance.php" class="btn btn-outline btn-sm">Today</a>
    </form>
</div>

<div style="display:flex;gap:10px;margin-bottom:14px">
    <span style="font-size:13px;color:var(--text3);padding:7px 0">Quick mark:</span>
    <button class="btn btn-success btn-sm" onclick="markAll('present')">✅ All Present</button>
    <button class="btn btn-danger btn-sm" onclick="markAll('absent')">❌ All Absent</button>
    <button class="btn btn-warning btn-sm" onclick="markAll('leave')">🏖️ All Leave</button>
</div>

<form method="POST">
<input type="hidden" name="att_date" value="<?=$date?>">
<div class="table-wrap">
<table>
<thead><tr><th>Student</th><th>Room</th><th>Attendance</th></tr></thead>
<tbody>
<?php $students->data_seek(0);while($s=$students->fetch_assoc()):
    $cur=$existing[$s['student_id']]??'present';
    $av=generateAvatar($s['name'],$s['avatar_color']??'#00D4FF');
    $hasPic=$s['profile_pic']&&file_exists('../assets/uploads/profiles/'.$s['profile_pic']);
?>
<tr>
    <td style="display:flex;align-items:center;gap:0">
        <?php if($hasPic):?><img src="../assets/uploads/profiles/<?=htmlspecialchars($s['profile_pic'])?>" class="avatar-sm" onerror="this.style.display='none'"><?php else:?>
        <span class="avatar-initials-sm" style="background:<?=htmlspecialchars($av['color'])?>"> <?=htmlspecialchars($av['initials'])?></span><?php endif;?>
        <?=htmlspecialchars($s['name'])?>
    </td>
    <td><?=htmlspecialchars($s['room_number']??'N/A')?></td>
    <td>
        <div style="display:flex;gap:8px">
            <?php foreach(['present'=>'✅ Present','absent'=>'❌ Absent','leave'=>'🏖️ Leave','late'=>'⏰ Late'] as $val=>$lbl):?>
            <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:12px;padding:4px 10px;border-radius:6px;border:1px solid var(--glass-border);transition:all .15s;<?=$cur===$val?'background:rgba(0,212,255,0.08);border-color:rgba(0,212,255,0.3);':''?>">
                <input type="radio" name="status[<?=$s['student_id']?>]" value="<?=$val?>" class="att-radio" <?=$cur===$val?'checked':''?> style="accent-color:var(--accent)">
                <?=$lbl?>
            </label>
            <?php endforeach;?>
        </div>
    </td>
</tr>
<?php endwhile;?>
</tbody>
</table>
</div>
<div style="margin-top:16px"><button type="submit" name="save_attendance" class="btn btn-primary">💾 Save Attendance for <?=$date?></button></div>
</form>
</div>
<script>function markAll(v){document.querySelectorAll('.att-radio[value='+v+']').forEach(r=>r.checked=true);}</script>
</body></html>
