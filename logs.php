<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='logs';$uid=$_SESSION['user_id'];$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];
$logs=$conn->query("SELECT l.*,u.name FROM activity_logs l LEFT JOIN users u ON l.user_id=u.user_id ORDER BY l.created_at DESC LIMIT 100");
$page_title='Activity Logs';$page_subtitle='Monitor all system activity';
?>
<!DOCTYPE html><html lang="en" data-theme="<?=$theme_data['theme']?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Logs</title><link rel="stylesheet" href="../assets/css/style.css"></head><body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in"><?php include('../includes/topbar.php');?>
<div class="table-wrap"><table><thead><tr><th>#</th><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>Time</th></tr></thead><tbody>
<?php $i=1;while($l=$logs->fetch_assoc()):?>
<tr><td><?=$i++?></td><td><?=htmlspecialchars($l['name']??'System')?></td><td><?=htmlspecialchars($l['action'])?></td><td style="max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($l['details']??'—')?></td><td><?=htmlspecialchars($l['ip_address']??'—')?></td><td><?=date('d M Y H:i',strtotime($l['created_at']))?></td></tr>
<?php endwhile;?></tbody></table></div></div></body></html>
