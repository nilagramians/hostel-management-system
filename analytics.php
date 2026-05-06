<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='analytics';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];

// Monthly collection
$monthly=$conn->query("SELECT DATE_FORMAT(created_at,'%b %Y') as m,SUM(amount-discount+fine) as total FROM fees WHERE status='paid' GROUP BY YEAR(created_at),MONTH(created_at) ORDER BY YEAR(created_at),MONTH(created_at) LIMIT 12");
$ml=$md=[];while($r=$monthly->fetch_assoc()){$ml[]=$r['m'];$md[]=(float)$r['total'];}

// Attendance trend (last 7 days)
$att=$conn->query("SELECT date,SUM(status='present') as p,SUM(status='absent') as a FROM attendance WHERE date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY date ORDER BY date");
$al=$ap=$aa=[];while($r=$att->fetch_assoc()){$al[]=date('d M',strtotime($r['date']));$ap[]=(int)$r['p'];$aa[]=(int)$r['a'];}

// Fee by status
$pc=$conn->query("SELECT COUNT(*) as c FROM fees WHERE status='paid'")->fetch_assoc()['c'];
$uc=$conn->query("SELECT COUNT(*) as c FROM fees WHERE status='unpaid'")->fetch_assoc()['c'];

// Room occupancy
$rooms_q=$conn->query("SELECT room_number,capacity,occupied FROM rooms ORDER BY room_id");
$rl=$ro=$rf=[];while($r=$rooms_q->fetch_assoc()){$rl[]=$r['room_number'];$ro[]=(int)$r['occupied'];$rf[]=(int)($r['capacity']-$r['occupied']);}

// Stats
$total_s=$conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'];
$total_col=$conn->query("SELECT COALESCE(SUM(amount-discount+fine),0) as t FROM fees WHERE status='paid'")->fetch_assoc()['t'];
$att_rate_q=$conn->query("SELECT COUNT(*) as total,SUM(status='present') as present FROM attendance WHERE date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)");
$att_r=$att_rate_q->fetch_assoc();
$att_rate=$att_r['total']>0?round(($att_r['present']/$att_r['total'])*100):0;

$page_title='Analytics';$page_subtitle='Insights and performance metrics';
?>
<!DOCTYPE html><html lang="en" data-theme="<?=$theme_data['theme']?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Analytics</title>
<link rel="stylesheet" href="../assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in"><?php include('../includes/topbar.php');?>

<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card"><span class="stat-icon">👥</span><div class="stat-num"><?=$total_s?></div><div class="stat-label">Active Students</div></div>
    <div class="stat-card" style="--c:linear-gradient(90deg,#00ff88,#00aa55)"><span class="stat-icon">💰</span><div class="stat-num" style="color:var(--success)">PKR <?=number_format($total_col,0)?></div><div class="stat-label">Total Collected</div></div>
    <div class="stat-card" style="--c:linear-gradient(90deg,#ffaa00,#cc7700)"><span class="stat-icon">📋</span><div class="stat-num" style="color:var(--warning)"><?=$att_rate?>%</div><div class="stat-label">30-Day Attendance Rate</div></div>
    <div class="stat-card" style="--c:linear-gradient(90deg,#7c3aed,#4c1d95)"><span class="stat-icon">🏨</span><div class="stat-num" style="color:#a78bfa"><?=count($rl)?></div><div class="stat-label">Total Rooms</div></div>
</div>

<div class="charts-grid">
    <div class="chart-card"><h3>📊 Fee Status Distribution</h3><canvas id="feeChart" height="220"></canvas></div>
    <div class="chart-card"><h3>🛏️ Room Occupancy by Room</h3><canvas id="roomChart" height="220"></canvas></div>
</div>
<?php if(count($ml)>0):?>
<div class="chart-card" style="margin-bottom:20px"><h3>📈 Monthly Fee Collection (PKR)</h3><canvas id="monthChart" height="90"></canvas></div>
<?php endif;?>
<?php if(count($al)>0):?>
<div class="chart-card" style="margin-bottom:20px"><h3>📋 Daily Attendance (Last 7 Days)</h3><canvas id="attChart" height="100"></canvas></div>
<?php endif;?>
</div>

<script>
const co={plugins:{legend:{labels:{color:'var(--text2)',font:{size:12}}}}};
const gs={x:{ticks:{color:'#8fafc8'},grid:{color:'rgba(255,255,255,0.04)'}},y:{ticks:{color:'#8fafc8'},grid:{color:'rgba(255,255,255,0.04)'}}};
new Chart(document.getElementById('feeChart'),{type:'doughnut',data:{labels:['Paid','Unpaid'],datasets:[{data:[<?=$pc?>,<?=$uc?>],backgroundColor:['rgba(0,255,136,0.2)','rgba(255,77,109,0.2)'],borderColor:['#00ff88','#ff4d6d'],borderWidth:2,hoverOffset:8}]},options:{...co,cutout:'70%'}});
new Chart(document.getElementById('roomChart'),{type:'bar',data:{labels:<?=json_encode($rl)?>,datasets:[{label:'Occupied',data:<?=json_encode($ro)?>,backgroundColor:'rgba(0,212,255,0.25)',borderColor:'#00d4ff',borderWidth:2,borderRadius:6},{label:'Free',data:<?=json_encode($rf)?>,backgroundColor:'rgba(255,255,255,0.06)',borderColor:'rgba(255,255,255,0.12)',borderWidth:2,borderRadius:6}]},options:{...co,scales:{x:{...gs.x,stacked:true},y:{...gs.y,stacked:true}}}});
<?php if(count($ml)>0):?>
new Chart(document.getElementById('monthChart'),{type:'line',data:{labels:<?=json_encode($ml)?>,datasets:[{label:'PKR Collected',data:<?=json_encode($md)?>,borderColor:'#00d4ff',backgroundColor:'rgba(0,212,255,0.08)',borderWidth:2.5,pointBackgroundColor:'#00d4ff',pointRadius:5,tension:0.4,fill:true}]},options:{...co,scales:gs}});
<?php endif;?>
<?php if(count($al)>0):?>
new Chart(document.getElementById('attChart'),{type:'bar',data:{labels:<?=json_encode($al)?>,datasets:[{label:'Present',data:<?=json_encode($ap)?>,backgroundColor:'rgba(0,255,136,0.25)',borderColor:'#00ff88',borderWidth:2,borderRadius:4},{label:'Absent',data:<?=json_encode($aa)?>,backgroundColor:'rgba(255,77,109,0.2)',borderColor:'#ff4d6d',borderWidth:2,borderRadius:4}]},options:{...co,scales:gs}});
<?php endif;?>
</script>
</body></html>
