<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='notifications';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];
$message='';

if(isset($_GET['auto'])){autoFeeNotifications($conn);$message='success:Auto fee alerts sent to all students!';}
if(isset($_GET['delete'])){$conn->query("DELETE FROM notifications WHERE notif_id=".intval($_GET['delete']));header("Location: notifications.php?msg=deleted");exit();}
if(isset($_GET['clear_all'])){$conn->query("DELETE FROM notifications");header("Location: notifications.php?msg=cleared");exit();}
if(isset($_POST['send_notif'])){
    $title=trim($_POST['title']);$msg=trim($_POST['message']);$type=$_POST['type'];$target=$_POST['target'];
    if($target==='all'){sendToAllStudents($conn,$title,$msg,$type);$message='success:Sent to all students!';}
    else{sendNotification($conn,intval($target),$title,$msg,$type);$message='success:Notification sent!';}
    logActivity($conn,$uid,'Send Notification',"Sent: $title to ".($target==='all'?'all':'user '.$target));
}

$notifs=$conn->query("SELECT n.*,u.name FROM notifications n JOIN users u ON n.user_id=u.user_id ORDER BY n.created_at DESC LIMIT 100");
$students=$conn->query("SELECT user_id,name FROM users WHERE role='student' ORDER BY name");
$page_title='Notifications';$page_subtitle='Send announcements and manage alerts';
?>
<!DOCTYPE html><html lang="en" data-theme="<?=$theme_data['theme']?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notifications</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in"><?php include('../includes/topbar.php');?>
<?php if($message):[$t,$m]=explode(":",$message,2);?><div class="alert <?=$t?>"><?=$t==='success'?'✅':'⚠'?> <?=htmlspecialchars($m)?></div><?php endif;?>
<?php if(isset($_GET['msg'])):?><div class="alert success">✅ <?=htmlspecialchars($_GET['msg'])==='deleted'?'Deleted.':($_GET['msg']==='cleared'?'All cleared.':'')?></div><?php endif;?>

<div class="form-card">
    <div class="form-card-title">📢 Send Announcement</div>
    <form method="POST">
        <div class="form-grid-2">
            <input type="text" name="title" placeholder="Notification Title" required>
            <select name="type" required>
                <option value="info">ℹ️ Info</option>
                <option value="warning">⚠️ Warning</option>
                <option value="danger">🚨 Danger / Urgent</option>
                <option value="success">✅ Success</option>
            </select>
            <select name="target" required style="grid-column:span 2">
                <option value="all">📢 All Students</option>
                <?php while($s=$students->fetch_assoc()):?><option value="<?=$s['user_id']?>">👤 <?=htmlspecialchars($s['name'])?></option><?php endwhile;?>
            </select>
            <textarea name="message" placeholder="Write your message..." required style="grid-column:span 2;min-height:80px;resize:vertical"></textarea>
        </div>
        <div class="action-row">
            <button type="submit" name="send_notif" class="btn btn-primary">📤 Send Notification</button>
            <a href="notifications.php?auto=1" class="btn btn-warning">⚡ Auto Fee Alerts</a>
            <a href="notifications.php?clear_all=1" class="btn btn-danger" onclick="return confirm('Clear ALL notifications?')">🗑 Clear All</a>
        </div>
    </form>
</div>

<div class="section-bar"><div class="section-title">All Notifications (Last 100)</div></div>
<div style="display:flex;flex-direction:column;gap:10px">
<?php $cnt=0;while($n=$notifs->fetch_assoc()):$cnt++;?>
<div class="notif-item <?=$n['type']?> <?=!$n['is_read']?'unread':''?>" style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px">
    <?php if(!$n['is_read']):?><div class="unread-dot"></div><?php endif;?>
    <div style="flex:1">
        <div class="notif-title"><?=htmlspecialchars($n['title'])?> <span class="badge badge-<?=$n['type']==='danger'?'danger':($n['type']==='warning'?'warning':($n['type']==='success'?'success':'info'))?>" style="font-size:10px"><?=ucfirst($n['type'])?></span></div>
        <div class="notif-msg"><?=htmlspecialchars($n['message'])?></div>
        <div class="notif-meta"><span>👤 <?=htmlspecialchars($n['name'])?></span><span style="margin-left:12px">🕒 <?=date('d M Y, h:i A',strtotime($n['created_at']))?></span><span style="margin-left:12px"><?=$n['is_read']?'✅ Read':'🔵 Unread'?></span></div>
    </div>
    <a href="notifications.php?delete=<?=$n['notif_id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">🗑</a>
</div>
<?php endwhile;?>
<?php if($cnt===0):?><div class="empty-state"><div class="empty-icon">🔔</div><h3>No notifications yet</h3></div><?php endif;?>
</div>
</div></body></html>
