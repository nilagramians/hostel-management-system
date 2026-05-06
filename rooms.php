<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='rooms';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];
$message='';

if(isset($_POST['add_room'])){
    $rn=trim($_POST['room_number']);$cap=intval($_POST['capacity']);
    $floor=trim($_POST['floor']??'1');$block=trim($_POST['block']??'A');
    $type=$_POST['room_type']??'double';$price=floatval($_POST['price']??8000);
    $amenities=trim($_POST['amenities']??'');
    $stmt=$conn->prepare("INSERT INTO rooms (room_number,floor,block,room_type,capacity,price,amenities) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssdis",$rn,$floor,$block,$type,$cap,$price,$amenities);
    $message=$stmt->execute()?'success:Room added!':'error:Room number already exists.';
    logActivity($conn,$uid,'Add Room',"Added room: $rn");
}
if(isset($_POST['edit_room'])){
    $rid=intval($_POST['room_id']);
    $cap=intval($_POST['capacity']);$price=floatval($_POST['price']);
    $status=$_POST['status'];$amenities=trim($_POST['amenities']??'');$notes=trim($_POST['notes']??'');
    $conn->query("UPDATE rooms SET capacity=$cap,price=$price,status='$status',amenities='".addslashes($amenities)."',notes='".addslashes($notes)."' WHERE room_id=$rid");
    $message='success:Room updated!';
    logActivity($conn,$uid,'Edit Room',"Edited room ID: $rid");
}
if(isset($_GET['delete'])){
    $rid=intval($_GET['delete']);
    $r=$conn->query("SELECT occupied FROM rooms WHERE room_id=$rid")->fetch_assoc();
    if($r&&$r['occupied']>0){$message='error:Cannot delete — students assigned here.';}
    else{$conn->query("DELETE FROM rooms WHERE room_id=$rid");header("Location: rooms.php?msg=deleted");exit();}
}

$rooms=$conn->query("SELECT * FROM rooms ORDER BY block,floor,room_number");
$page_title='Rooms';$page_subtitle='Manage room inventory and occupancy';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?=$theme_data['theme']?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rooms — Hostel Pro</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in">
<?php include('../includes/topbar.php');?>
<?php if($message):[$t,$m]=explode(":",$message,2);?><div class="alert <?=$t?>"><?=$t==='success'?'✅':'⚠'?> <?=htmlspecialchars($m)?></div><?php endif;?>
<?php if(isset($_GET['msg'])&&$_GET['msg']==='deleted'):?><div class="alert success">✅ Room deleted.</div><?php endif;?>

<div class="form-card">
    <div class="form-card-title">➕ Add New Room</div>
    <form method="POST">
        <div class="form-grid">
            <input type="text"   name="room_number" placeholder="Room Number (e.g. A-105)" required>
            <input type="text"   name="floor"       placeholder="Floor (e.g. 1)" value="1">
            <input type="text"   name="block"       placeholder="Block (e.g. A)" value="A">
            <select name="room_type">
                <option value="single">🛏️ Single (1)</option>
                <option value="double" selected>🛏️🛏️ Double (2)</option>
                <option value="triple">🛏️🛏️🛏️ Triple (3)</option>
                <option value="quad">🏨 Quad (4)</option>
            </select>
            <input type="number" name="capacity"    placeholder="Capacity" min="1" max="10" required>
            <input type="number" name="price"       placeholder="Monthly Price (PKR)" step="100" value="8000">
            <input type="text"   name="amenities"   placeholder="Amenities (e.g. AC, WiFi, Attached Bath)" style="grid-column:span 3">
        </div>
        <div class="action-row"><button type="submit" name="add_room" class="btn btn-primary">Add Room</button></div>
    </form>
</div>

<div class="section-title" style="margin-bottom:14px">All Rooms</div>
<div class="rooms-grid">
<?php while($r=$rooms->fetch_assoc()):
    $pct=$r['capacity']>0?round(($r['occupied']/$r['capacity'])*100):0;
    $full=$r['occupied']>=$r['capacity'];$free=$r['capacity']-$r['occupied'];
    $clr=$r['status']==='maintenance'?'var(--warning)':($full?'var(--danger)':'var(--accent)');
    $type_icons=['single'=>'🛏️','double'=>'🛏️🛏️','triple'=>'🛏️🛏️🛏️','quad'=>'🏨'];
?>
<div class="room-card <?=$r['status']==='maintenance'?'maintenance':($full?'full':'available')?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
        <div>
            <div style="font-size:17px;font-weight:700;color:var(--accent)">🚪 <?=htmlspecialchars($r['room_number'])?></div>
            <div style="font-size:11px;color:var(--text3)">Block <?=$r['block']?> · Floor <?=$r['floor']?></div>
        </div>
        <span class="badge <?=$r['status']==='maintenance'?'badge-warning':($full?'badge-danger':'badge-success')?>"><?=$r['status']==='maintenance'?'Maintenance':($full?'Full':"$free free")?></span>
    </div>
    <div style="font-size:12px;color:var(--text3);margin-bottom:4px"><?=$type_icons[$r['room_type']]??'🛏️'?> <?=ucfirst($r['room_type'])?> · Capacity: <?=$r['capacity']?></div>
    <div style="font-size:12px;color:var(--text3);margin-bottom:8px">💰 PKR <?=number_format($r['price'],0)?>/mo · Occupied: <?=$r['occupied']?></div>
    <?php if($r['amenities']):?><div style="font-size:11px;color:var(--text3);margin-bottom:8px">✨ <?=htmlspecialchars($r['amenities'])?></div><?php endif;?>
    <div class="progress-bar" style="height:5px;margin-bottom:12px">
        <div class="progress-fill" style="width:<?=$pct?>%;background:<?=$clr?>;box-shadow:0 0 6px <?=$clr?>40"></div>
    </div>
    <div style="display:flex;gap:6px">
        <button onclick="openRoomEdit(<?=htmlspecialchars(json_encode($r))?> )" class="btn btn-outline btn-sm" style="flex:1">✏️ Edit</button>
        <?php if(!$full&&$r['status']!=='maintenance'):?>
        <a href="?delete=<?=$r['room_id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete room?')">🗑</a>
        <?php endif;?>
    </div>
</div>
<?php endwhile;?>
</div>

<!-- Edit Room Modal -->
<div class="modal-overlay" id="roomEditModal">
    <div class="modal">
        <button class="modal-close" onclick="closeRoomEdit()">✕</button>
        <div class="modal-title">✏️ Edit Room</div>
        <form method="POST">
            <input type="hidden" name="room_id" id="er_id">
            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="field"><label>Room Number (read only)</label><input type="text" id="er_num" disabled style="opacity:.5"></div>
                <div class="field"><label>Capacity</label><input type="number" name="capacity" id="er_cap" min="1" max="20" required></div>
                <div class="field"><label>Monthly Price (PKR)</label><input type="number" name="price" id="er_price" step="100" required></div>
                <div class="field"><label>Status</label>
                    <select name="status" id="er_status">
                        <option value="available">✅ Available</option>
                        <option value="maintenance">🔧 Maintenance</option>
                        <option value="closed">🔒 Closed</option>
                    </select>
                </div>
                <div class="field"><label>Amenities</label><input type="text" name="amenities" id="er_amenities" placeholder="AC, WiFi, etc."></div>
                <div class="field"><label>Notes</label><textarea name="notes" id="er_notes" style="min-height:60px;resize:vertical"></textarea></div>
            </div>
            <div class="action-row">
                <button type="submit" name="edit_room" class="btn btn-primary">Save Changes</button>
                <button type="button" onclick="closeRoomEdit()" class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>
</div>
<script>
function openRoomEdit(r){
    document.getElementById('er_id').value=r.room_id;
    document.getElementById('er_num').value=r.room_number;
    document.getElementById('er_cap').value=r.capacity;
    document.getElementById('er_price').value=r.price;
    document.getElementById('er_status').value=r.status;
    document.getElementById('er_amenities').value=r.amenities||'';
    document.getElementById('er_notes').value=r.notes||'';
    document.getElementById('roomEditModal').classList.add('active');
}
function closeRoomEdit(){document.getElementById('roomEditModal').classList.remove('active');}
document.getElementById('roomEditModal').addEventListener('click',function(e){if(e.target===this)closeRoomEdit();});
</script>
</body>
</html>
