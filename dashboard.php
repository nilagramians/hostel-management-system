<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='dashboard';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);
$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];

// Stats
$total_students=$conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'];
$total_rooms=$conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'];
$empty_rooms=$conn->query("SELECT COUNT(*) as c FROM rooms WHERE occupied<capacity AND status='available'")->fetch_assoc()['c'];
$collected=$conn->query("SELECT COALESCE(SUM(amount-discount+fine),0) as t FROM fees WHERE status='paid'")->fetch_assoc()['t'];
$pending=$conn->query("SELECT COALESCE(SUM(amount-discount+fine),0) as t FROM fees WHERE status='unpaid'")->fetch_assoc()['t'];
$today_present=$conn->query("SELECT COUNT(*) as c FROM attendance WHERE date=CURDATE() AND status='present'")->fetch_assoc()['c'];

// Chart data
$pc=$conn->query("SELECT COUNT(*) as c FROM fees WHERE status='paid'")->fetch_assoc()['c'];
$uc=$conn->query("SELECT COUNT(*) as c FROM fees WHERE status='unpaid'")->fetch_assoc()['c'];
$rooms_data=$conn->query("SELECT room_number,capacity,occupied FROM rooms ORDER BY room_id LIMIT 8");
$rl=$ro=$rf=[];
while($r=$rooms_data->fetch_assoc()){$rl[]=$r['room_number'];$ro[]=(int)$r['occupied'];$rf[]=(int)($r['capacity']-$r['occupied']);}
$monthly=$conn->query("SELECT DATE_FORMAT(created_at,'%b %Y') as m,SUM(amount+fine-discount) as t FROM fees WHERE status='paid' GROUP BY YEAR(created_at),MONTH(created_at) ORDER BY YEAR(created_at),MONTH(created_at) DESC LIMIT 6");
$ml=$md=[];while($r=$monthly->fetch_assoc()){$ml[]=$r['m'];$md[]=(float)$r['t'];}
$ml=array_reverse($ml);$md=array_reverse($md);

// Recent students
$recent=$conn->query("SELECT u.name,u.email,s.phone,s.profile_pic,s.avatar_color,s.student_code,r.room_number,COALESCE(f.status,'unpaid') as fee_status FROM students s JOIN users u ON s.user_id=u.user_id LEFT JOIN rooms r ON s.room_id=r.room_id LEFT JOIN fees f ON s.student_id=f.student_id WHERE s.status='active' ORDER BY s.student_id DESC LIMIT 8");

$page_title='Dashboard';$page_subtitle='Welcome back, '.htmlspecialchars($_SESSION['name']).' 👋';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?=$theme_data['theme']?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — Hostel Pro Ultimate</title>
<link rel="stylesheet" href="../assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main <?=isset($_COOKIE['sidebar_mini'])&&$_COOKIE['sidebar_mini']==='1'?'mini':''?> animate-in">
    <?php include('../includes/topbar.php');?>

    <!-- Quick Actions -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
        <a href="students.php" class="btn btn-primary btn-sm">➕ Add Student</a>
        <a href="rooms.php" class="btn btn-outline btn-sm">🛏️ Add Room</a>
        <a href="fees.php" class="btn btn-outline btn-sm">💰 Add Fee</a>
        <a href="attendance.php" class="btn btn-outline btn-sm">📋 Attendance</a>
        <a href="report.php" class="btn btn-outline btn-sm">📄 PDF Report</a>
        <a href="notifications.php?auto=1" class="btn btn-warning btn-sm">⚡ Fee Alerts</a>
        <a href="backup.php?download=1" class="btn btn-outline btn-sm">💾 Backup</a>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card" style="--c:linear-gradient(90deg,#00d4ff,#0099cc);--nc:#00d4ff;--gc:#00d4ff">
            <div class="stat-glow"></div><span class="stat-icon">👥</span>
            <div class="stat-num"><?=$total_students?></div>
            <div class="stat-label">Active Students</div>
            <div class="stat-trend up">↑ Enrolled</div>
        </div>
        <div class="stat-card" style="--c:linear-gradient(90deg,#7c3aed,#4c1d95);--nc:#a78bfa;--gc:#7c3aed">
            <div class="stat-glow"></div><span class="stat-icon">🛏️</span>
            <div class="stat-num" style="color:#a78bfa"><?=$total_rooms?></div>
            <div class="stat-label">Total Rooms</div>
            <div class="stat-sub"><?=$empty_rooms?> available</div>
        </div>
        <div class="stat-card" style="--c:linear-gradient(90deg,#00ff88,#00aa55);--nc:#00ff88;--gc:#00ff88">
            <div class="stat-glow"></div><span class="stat-icon">💰</span>
            <div class="stat-num" style="color:var(--success)">PKR <?=number_format($collected,0)?></div>
            <div class="stat-label">Fees Collected</div>
            <div class="stat-trend up">↑ This month</div>
        </div>
        <div class="stat-card" style="--c:linear-gradient(90deg,#ff4d6d,#aa1133);--nc:#ff4d6d;--gc:#ff4d6d">
            <div class="stat-glow"></div><span class="stat-icon">⚠️</span>
            <div class="stat-num" style="color:var(--danger)">PKR <?=number_format($pending,0)?></div>
            <div class="stat-label">Pending Fees</div>
            <div class="stat-trend down">↓ Need attention</div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
        <div class="stat-card" style="padding:16px">
            <span class="stat-icon" style="font-size:18px;margin-bottom:6px">📋</span>
            <div class="stat-num" style="font-size:20px;color:var(--warning)"><?=$today_present?></div>
            <div class="stat-label">Present Today</div>
        </div>
        <div class="stat-card" style="padding:16px">
            <span class="stat-icon" style="font-size:18px;margin-bottom:6px">🧾</span>
            <div class="stat-num" style="font-size:20px;color:var(--danger)"><?=$open_complaints?></div>
            <div class="stat-label">Open Complaints</div>
        </div>
        <div class="stat-card" style="padding:16px">
            <span class="stat-icon" style="font-size:18px;margin-bottom:6px">🏖️</span>
            <div class="stat-num" style="font-size:20px;color:var(--accent)"><?=$pending_leaves?></div>
            <div class="stat-label">Pending Leaves</div>
        </div>
        <div class="stat-card" style="padding:16px">
            <span class="stat-icon" style="font-size:18px;margin-bottom:6px">🏠</span>
            <div class="stat-num" style="font-size:20px;color:var(--success)"><?=$empty_rooms?></div>
            <div class="stat-label">Available Rooms</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="chart-card"><h3>📊 Fee Status</h3><canvas id="feeChart" height="200"></canvas></div>
        <div class="chart-card"><h3>🛏️ Room Occupancy</h3><canvas id="roomChart" height="200"></canvas></div>
    </div>
    <?php if(count($ml)>0):?>
    <div class="chart-card" style="margin-bottom:20px"><h3>📈 Monthly Collection</h3><canvas id="monthChart" height="80"></canvas></div>
    <?php endif;?>

    <!-- Recent Students -->
    <div class="section-bar">
        <div class="section-title">Recent Students</div>
        <a href="students.php" class="btn btn-outline btn-sm">View All →</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Code</th><th>Email</th><th>Room</th><th>Fee</th></tr></thead>
            <tbody>
            <?php while($r=$recent->fetch_assoc()):
                $av=generateAvatar($r['name'],$r['avatar_color']??'#00D4FF');
                $hasPic=$r['profile_pic']&&file_exists('../assets/uploads/profiles/'.$r['profile_pic']);
            ?>
            <tr>
                <td style="display:flex;align-items:center;gap:0">
                    <?php if($hasPic):?>
                        <img src="../assets/uploads/profiles/<?=htmlspecialchars($r['profile_pic'])?>" class="avatar-sm">
                    <?php else:?>
                        <span class="avatar-initials-sm" style="background:<?=htmlspecialchars($av['color'])?>"><?=htmlspecialchars($av['initials'])?></span>
                    <?php endif;?>
                    <?=htmlspecialchars($r['name'])?>
                </td>
                <td><span class="badge badge-muted"><?=htmlspecialchars($r['student_code']??'—')?></span></td>
                <td><?=htmlspecialchars($r['email'])?></td>
                <td><?=htmlspecialchars($r['room_number']??'N/A')?></td>
                <td><span class="badge <?=$r['fee_status']==='paid'?'badge-success':'badge-danger'?>"><?=ucfirst($r['fee_status'])?></span></td>
            </tr>
            <?php endwhile;?>
            </tbody>
        </table>
    </div>
</div>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<script>
const co = { plugins:{legend:{labels:{color:'var(--text2)',font:{size:12}}}} };
new Chart(document.getElementById('feeChart'),{type:'doughnut',data:{labels:['Paid','Unpaid'],datasets:[{data:[<?=$pc?>,<?=$uc?>],backgroundColor:['rgba(0,255,136,0.2)','rgba(255,77,109,0.2)'],borderColor:['#00ff88','#ff4d6d'],borderWidth:2,hoverOffset:8}]},options:{...co,cutout:'72%'}});
new Chart(document.getElementById('roomChart'),{type:'bar',data:{labels:<?=json_encode($rl)?>,datasets:[{label:'Occupied',data:<?=json_encode($ro)?>,backgroundColor:'rgba(0,212,255,0.25)',borderColor:'#00d4ff',borderWidth:2,borderRadius:6},{label:'Free',data:<?=json_encode($rf)?>,backgroundColor:'rgba(255,255,255,0.05)',borderColor:'rgba(255,255,255,0.1)',borderWidth:2,borderRadius:6}]},options:{...co,scales:{x:{stacked:true,ticks:{color:'#8fafc8'},grid:{color:'rgba(255,255,255,0.04)'}},y:{stacked:true,ticks:{color:'#8fafc8',stepSize:1},grid:{color:'rgba(255,255,255,0.04)'}}}}});
<?php if(count($ml)>0):?>
new Chart(document.getElementById('monthChart'),{type:'line',data:{labels:<?=json_encode($ml)?>,datasets:[{label:'PKR Collected',data:<?=json_encode($md)?>,borderColor:'#00d4ff',backgroundColor:'rgba(0,212,255,0.06)',borderWidth:2,pointBackgroundColor:'#00d4ff',pointRadius:5,tension:0.4,fill:true}]},options:{...co,scales:{x:{ticks:{color:'#8fafc8'},grid:{color:'rgba(255,255,255,0.04)'}},y:{ticks:{color:'#8fafc8'},grid:{color:'rgba(255,255,255,0.04)'}}}}});
<?php endif;?>

// Toast helper
function showToast(msg,type='info'){
    const tc=document.getElementById('toastContainer');
    const t=document.createElement('div');
    const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};
    t.className=`toast`;t.innerHTML=`<span class="toast-icon">${icons[type]||'ℹ️'}</span><span class="toast-msg">${msg}</span><button class="toast-close" onclick="this.parentElement.remove()">✕</button>`;
    tc.appendChild(t);setTimeout(()=>{t.classList.add('removing');setTimeout(()=>t.remove(),300)},4000);
}
<?php if(isset($_GET['msg'])):?>showToast('<?=htmlspecialchars($_GET['msg'])?>','success');<?php endif;?>
</script>
</body>
</html>
