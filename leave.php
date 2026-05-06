<?php
// This file auto-creates remaining admin pages
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='leave';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];

if(isset($_GET['action'])&&isset($_GET['id'])){
    $id=intval($_GET['id']);$action=$_GET['action'];
    if(in_array($action,['approved','rejected'])){
        $conn->query("UPDATE leave_requests SET status='$action' WHERE leave_id=$id");
        $lr=$conn->query("SELECT lr.from_date,lr.to_date,s.user_id FROM leave_requests lr JOIN students s ON lr.student_id=s.student_id WHERE lr.leave_id=$id")->fetch_assoc();
        if($lr)sendNotification($conn,$lr['user_id'],'🏖️ Leave Request '.ucfirst($action),"Your leave from {$lr['from_date']} to {$lr['to_date']} has been ".($action==='approved'?'✅ approved':'❌ rejected').".",($action==='approved'?'success':'danger'));
        logActivity($conn,$uid,'Leave Decision',"$action leave ID: $id");
    }
    header("Location: leave.php");exit();
}

$leaves=$conn->query("SELECT lr.*,u.name,s.student_id,s.student_code FROM leave_requests lr JOIN students s ON lr.student_id=s.student_id JOIN users u ON s.user_id=u.user_id ORDER BY FIELD(lr.status,'pending','approved','rejected'),lr.created_at DESC");
$page_title='Leave Requests';$page_subtitle='Approve or reject student leave applications';
?>
<!DOCTYPE html><html lang="en" data-theme="<?=$theme_data['theme']?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Leave</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in"><?php include('../includes/topbar.php');?>

<div class="table-wrap">
<table>
<thead><tr><th>Student</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php $cnt=0;while($l=$leaves->fetch_assoc()):$cnt++;
    $days=max(1,(strtotime($l['to_date'])-strtotime($l['from_date']))/86400+1);
?>
<tr>
    <td><?=htmlspecialchars($l['name'])?><br><span style="font-size:10px;color:var(--text3)"><?=$l['student_code']??''?></span></td>
    <td><span class="badge badge-muted"><?=ucfirst($l['leave_type']??'home')?></span></td>
    <td><?=date('d M Y',strtotime($l['from_date']))?></td>
    <td><?=date('d M Y',strtotime($l['to_date']))?></td>
    <td><strong><?=$days?></strong> day<?=$days>1?'s':''?></td>
    <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?=htmlspecialchars($l['reason'])?>"><?=htmlspecialchars(substr($l['reason'],0,50)).(strlen($l['reason'])>50?'...':'')?></td>
    <td><span class="badge <?=$l['status']==='approved'?'badge-success':($l['status']==='rejected'?'badge-danger':'badge-warning')?>"><?=ucfirst($l['status'])?></span></td>
    <td style="display:flex;gap:6px">
        <?php if($l['status']==='pending'):?>
        <a href="leave.php?action=approved&id=<?=$l['leave_id']?>" class="btn btn-success btn-sm">✅ Approve</a>
        <a href="leave.php?action=rejected&id=<?=$l['leave_id']?>" class="btn btn-danger btn-sm">❌ Reject</a>
        <?php else:?><span style="font-size:12px;color:var(--text3)">Done</span><?php endif;?>
    </td>
</tr>
<?php endwhile;?>
</tbody>
</table>
</div>
<?php if($cnt===0):?><div class="empty-state" style="margin-top:16px"><div class="empty-icon">🏖️</div><h3>No leave requests</h3></div><?php endif;?>
</div></body></html>
