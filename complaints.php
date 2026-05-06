<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='complaints';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];
$message='';

if(isset($_POST['reply'])){
    $cid=intval($_POST['complaint_id']);$reply=trim($_POST['reply_text']);$status=$_POST['new_status']??'resolved';
    $stmt=$conn->prepare("UPDATE complaints SET admin_reply=?,status=?,updated_at=NOW() WHERE complaint_id=?");
    $stmt->bind_param("ssi",$reply,$status,$cid);$stmt->execute();
    $c=$conn->query("SELECT user_id,subject FROM complaints WHERE complaint_id=$cid")->fetch_assoc();
    sendNotification($conn,$c['user_id'],'🧾 Complaint '.ucfirst($status),"Your complaint '{$c['subject']}' has been $status by admin.",($status==='resolved'?'success':'info'));
    logActivity($conn,$uid,'Reply Complaint',"Replied to complaint ID: $cid");
    $message='success:Reply sent!';
}
if(isset($_GET['delete'])){$conn->query("DELETE FROM complaints WHERE complaint_id=".intval($_GET['delete']));header("Location: complaints.php?msg=deleted");exit();}
if(isset($_GET['reopen'])){$conn->query("UPDATE complaints SET status='open' WHERE complaint_id=".intval($_GET['reopen']));header("Location: complaints.php");exit();}

$complaints=$conn->query("SELECT c.*,u.name FROM complaints c JOIN users u ON c.user_id=u.user_id ORDER BY FIELD(c.status,'open','in_progress','resolved','closed'),c.created_at DESC");
$page_title='Complaints';$page_subtitle='Review and respond to student complaints';
?>
<!DOCTYPE html><html lang="en" data-theme="<?=$theme_data['theme']?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Complaints</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in"><?php include('../includes/topbar.php');?>
<?php if($message):[$t,$m]=explode(":",$message,2);?><div class="alert <?=$t?>"><?=$t==='success'?'✅':'⚠'?> <?=htmlspecialchars($m)?></div><?php endif;?>
<?php if(isset($_GET['msg'])&&$_GET['msg']==='deleted'):?><div class="alert error">Complaint deleted.</div><?php endif;?>

<?php $cnt=0;while($c=$complaints->fetch_assoc()):$cnt++;?>
<div class="complaint-card <?=$c['status']?>" style="margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
        <div>
            <div style="font-size:15px;font-weight:600;margin-bottom:3px"><?=htmlspecialchars($c['subject'])?>
                <span class="badge badge-muted" style="font-size:10px;margin-left:6px"><?=ucfirst($c['category']??'other')?></span>
                <span class="badge <?=$c['priority']==='urgent'?'badge-danger':($c['priority']==='high'?'badge-warning':'badge-muted')?>" style="font-size:10px;margin-left:4px"><?=ucfirst($c['priority']??'medium')?></span>
            </div>
            <div style="font-size:12px;color:var(--text3)">👤 <?=htmlspecialchars($c['name'])?> · 🕒 <?=date('d M Y',strtotime($c['created_at']))?></div>
        </div>
        <div style="display:flex;gap:6px;align-items:center">
            <span class="badge <?=$c['status']==='open'?'badge-warning':($c['status']==='resolved'?'badge-success':'badge-info')?>"><?=ucfirst(str_replace('_',' ',$c['status']))?></span>
            <?php if($c['status']==='resolved'||$c['status']==='closed'):?><a href="complaints.php?reopen=<?=$c['complaint_id']?>" class="btn btn-warning btn-sm">Reopen</a><?php endif;?>
            <a href="complaints.php?delete=<?=$c['complaint_id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">🗑</a>
        </div>
    </div>
    <div style="background:rgba(0,0,0,.2);border-radius:8px;padding:12px;font-size:13px;color:var(--text2);line-height:1.6;margin-bottom:10px"><?=htmlspecialchars($c['message'])?></div>
    <?php if($c['admin_reply']):?>
    <div style="background:rgba(0,212,255,.05);border:1px solid rgba(0,212,255,.15);border-radius:8px;padding:12px;font-size:13px;color:var(--text2)"><strong style="color:var(--accent);font-size:11px;display:block;margin-bottom:4px">ADMIN REPLY:</strong><?=htmlspecialchars($c['admin_reply'])?></div>
    <?php endif;?>
    <?php if($c['status']==='open'||$c['status']==='in_progress'):?>
    <form method="POST" style="margin-top:12px">
        <input type="hidden" name="complaint_id" value="<?=$c['complaint_id']?>">
        <div style="display:flex;gap:10px;margin-bottom:8px">
            <select name="new_status" style="padding:8px 12px;background:rgba(0,0,0,.3);border:1px solid var(--glass-border);border-radius:8px;color:var(--text);font-size:13px;outline:none">
                <option value="in_progress">🔄 In Progress</option>
                <option value="resolved">✅ Resolved</option>
                <option value="closed">🔒 Closed</option>
            </select>
        </div>
        <textarea name="reply_text" placeholder="Write your reply..." required style="width:100%;padding:10px 14px;background:rgba(0,0,0,.3);border:1px solid var(--glass-border);border-radius:8px;color:var(--text);font-size:13px;resize:vertical;min-height:70px;outline:none;font-family:inherit;margin-bottom:8px"></textarea>
        <button type="submit" name="reply" class="btn btn-primary btn-sm">Send Reply</button>
    </form>
    <?php endif;?>
</div>
<?php endwhile;?>
<?php if($cnt===0):?><div class="empty-state"><div class="empty-icon">🧾</div><h3>No complaints yet</h3><p>Students haven't submitted any complaints</p></div><?php endif;?>
</div></body></html>
