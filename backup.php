<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='backup';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];

if(isset($_GET['download'])){
    $tables=['users','students','rooms','fees','attendance','notifications','complaints','leave_requests','activity_logs','settings','otp_codes','password_resets','maintenance'];
    $sql="-- ============================================================\n-- HOSTEL PRO ULTIMATE — Database Backup\n-- Generated: ".date('Y-m-d H:i:s')."\n-- By: ".htmlspecialchars($_SESSION['name'])."\n-- ============================================================\n\nCREATE DATABASE IF NOT EXISTS hostel_ultimate;\nUSE hostel_ultimate;\n\n";
    foreach($tables as $table){
        $res=$conn->query("SHOW CREATE TABLE `$table`");
        if(!$res)continue;
        $row=$res->fetch_row();
        $sql.="-- Table: $table\nDROP TABLE IF EXISTS `$table`;\n".$row[1].";\n\n";
        $data=$conn->query("SELECT * FROM `$table`");
        if($data&&$data->num_rows>0){
            while($d=$data->fetch_assoc()){
                $vals=array_map(fn($v)=>$v===null?'NULL':"'".$conn->real_escape_string($v)."'",array_values($d));
                $sql.="INSERT INTO `$table` VALUES (".implode(',',$vals).");\n";
            }
            $sql.="\n";
        }
    }
    logActivity($conn,$uid,'Database Backup','Downloaded full database backup');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=HostelPro_Backup_'.date('Y-m-d_His').'.sql');
    echo $sql;exit();
}

$tables_info=[];
foreach(['users','students','rooms','fees','attendance','notifications','complaints','leave_requests','activity_logs','settings','maintenance'] as $t){
    $r=$conn->query("SELECT COUNT(*) as c FROM `$t`");
    $tables_info[$t]=$r?$r->fetch_assoc()['c']:0;
}
$page_title='Backup & Restore';$page_subtitle='Download and manage database backups';
?>
<!DOCTYPE html><html lang="en" data-theme="<?=$theme_data['theme']?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Backup</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in"><?php include('../includes/topbar.php');?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="glass-card" style="padding:36px;text-align:center">
        <div style="font-size:52px;margin-bottom:16px">💾</div>
        <h2 style="font-size:18px;font-weight:700;margin-bottom:10px">Download Full Backup</h2>
        <p style="color:var(--text3);font-size:13px;margin-bottom:24px;line-height:1.7">Download a complete SQL backup of all <?=count($tables_info)?> tables including all data, structure, and relationships.</p>
        <a href="backup.php?download=1" class="btn btn-primary btn-lg">⬇ Download Backup (.sql)</a>
        <div style="margin-top:22px;text-align:left;font-size:12px;color:var(--text3);line-height:2.2">
            <div>✅ All <?=count($tables_info)?> database tables</div>
            <div>✅ DROP + CREATE + INSERT statements</div>
            <div>✅ Timestamped filename</div>
            <div>✅ Import via phpMyAdmin → Import</div>
            <div>✅ Compatible with MySQL 5.7+</div>
        </div>
    </div>
    <div class="glass-card" style="padding:24px">
        <div class="form-card-title">📊 Database Overview</div>
        <?php foreach($tables_info as $table=>$count):?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--glass-border)">
            <span style="font-size:13px;color:var(--text2)">📋 <?=ucfirst(str_replace('_',' ',$table))?></span>
            <span style="font-size:13px;font-weight:600;color:var(--accent)"><?=$count?> rows</span>
        </div>
        <?php endforeach;?>
        <div style="margin-top:14px;padding:12px;background:rgba(0,212,255,0.05);border-radius:8px;border:1px solid rgba(0,212,255,0.15);font-size:12px;color:var(--text3)">
            💡 Tip: Back up regularly. Store outside the server. Test restores periodically.
        </div>
    </div>
</div>
</div></body></html>
