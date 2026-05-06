<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='maintenance';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];
$message='';

if(isset($_POST['add_maint'])){
    $rid=intval($_POST['room_id']);$issue=trim($_POST['issue']);$desc=trim($_POST['description']??'');
    $stmt=$conn->prepare("INSERT INTO maintenance (room_id,issue,description,reported_by) VALUES (?,?,?,?)");
    $stmt->bind_param("issi",$rid,$issue,$desc,$uid);$stmt->execute();
    $conn->query("UPDATE rooms SET status='maintenance' WHERE room_id=$rid");
    $message='success:Maintenance issue logged!';
}
if(isset($_GET['resolve'])){
    $mid=intval($_GET['resolve']);
    $m=$conn->query("SELECT room_id FROM maintenance WHERE maint_id=$mid")->fetch_assoc();
    $conn->query("UPDATE maintenance SET status='completed' WHERE maint_id=$mid");
    if($m)$conn->query("UPDATE rooms SET status='available' WHERE room_id={$m['room_id']}");
    header("Location: maintenance.php?msg=resolved");exit();
}

$maints=$conn->query("SELECT m.*,r.room_number FROM maintenance m JOIN rooms r ON m.room_id=r.room_id ORDER BY FIELD(m.status,'pending','in_progress','completed'),m.created_at DESC");
$rooms=$conn->query("SELECT room_id,room_number FROM rooms ORDER BY room_number");
$page_title='Maintenance';$page_subtitle='Track and resolve room maintenance issues';
?>
<!DOCTYPE html><html lang="en" data-theme="<?=$theme_data['theme']?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Maintenance</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in"><?php include('../includes/topbar.php');?>
<?php if($message):[$t,$m]=explode(":",$message,2);?><div class="alert <?=$t?>"><?=$t==='success'?'✅':'⚠'?> <?=htmlspecialchars($m)?></div><?php endif;?>
<?php if(isset($_GET['msg'])):?><div class="alert success">✅ Issue marked resolved!</div><?php endif;?>

<div class="form-card">
    <div class="form-card-title">🔧 Log Maintenance Issue</div>
    <form method="POST">
        <div class="form-grid">
            <select name="room_id" required>
                <option value="">— Select Room —</option>
                <?php while($r=$rooms->fetch_assoc()):?><option value="<?=$r['room_id']?>">Room <?=htmlspecialchars($r['room_number'])?></option><?php endwhile;?>
            </select>
            <input type="text" name="issue" placeholder="Issue (e.g. Broken AC, Plumbing)" required>
            <textarea name="description" placeholder="Detailed description..." style="min-height:44px;resize:vertical"></textarea>
        </div>
        <div class="action-row"><button type="submit" name="add_maint" class="btn btn-primary">Log Issue</button></div>
    </form>
</div>

<div class="table-wrap">
<table>
<thead><tr><th>#</th><th>Room</th><th>Issue</th><th>Description</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
<tbody>
<?php $i=1;$cnt=0;while($m=$maints->fetch_assoc()):$cnt++;?>
<tr>
    <td><?=$i++?></td>
    <td><?=htmlspecialchars($m['room_number'])?></td>
    <td><?=htmlspecialchars($m['issue'])?></td>
    <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($m['description']??'—')?></td>
    <td><span class="badge <?=$m['status']==='completed'?'badge-success':($m['status']==='in_progress'?'badge-info':'badge-warning')?>"><?=ucfirst(str_replace('_',' ',$m['status']))?></span></td>
    <td><?=date('d M Y',strtotime($m['created_at']))?></td>
    <td>
        <?php if($m['status']!=='completed'):?>
        <a href="maintenance.php?resolve=<?=$m['maint_id']?>" class="btn btn-success btn-sm">✅ Resolve</a>
        <?php else:?><span style="color:var(--text3);font-size:12px">Done</span><?php endif;?>
    </td>
</tr>
<?php endwhile;?>
</tbody>
</table>
</div>
<?php if($cnt===0):?><div class="empty-state" style="margin-top:16px"><div class="empty-icon">🔧</div><h3>No maintenance issues</h3></div><?php endif;?>
</div></body></html>
