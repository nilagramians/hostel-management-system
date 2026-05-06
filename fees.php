<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='fees';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];
$message='';$today=date('Y-m-d');

if(isset($_GET['autofine'])){
    $conn->query("UPDATE fees SET fine=fine+500 WHERE status='unpaid' AND due_date<'$today' AND fine=0");
    $message='success:Auto fines (PKR 500) applied to all overdue records!';
    logActivity($conn,$uid,'Auto Fine','Applied auto fines to overdue fees');
}
if(isset($_POST['add_fee'])){
    $sid=intval($_POST['student_id']);$amount=floatval($_POST['amount']);
    $discount=floatval($_POST['discount']??0);$fine=floatval($_POST['fine']??0);
    $due=$_POST['due_date'];$mon=$_POST['month_year']??date('Y-m');
    $pm=$_POST['payment_method']??'cash';$notes=trim($_POST['notes']??'');
    $inv=generateInvoiceNo();
    $stmt=$conn->prepare("INSERT INTO fees (student_id,amount,discount,fine,status,due_date,month_year,payment_method,invoice_no,notes) VALUES (?,?,?,?,'unpaid',?,?,?,?,?)");
    $stmt->bind_param("idddssss s",$sid,$amount,$discount,$fine,$due,$mon,$pm,$inv,$notes);
    $stmt->execute();
    $new_uid=$conn->query("SELECT user_id FROM students WHERE student_id=$sid")->fetch_assoc()['user_id'];
    $net=$amount-$discount+$fine;
    sendNotification($conn,$new_uid,'💰 New Fee Added',"Fee of PKR ".number_format($net,0)." added. Invoice: $inv. Due: $due.",'warning','fees.php');
    logActivity($conn,$uid,'Add Fee',"Added fee for student ID: $sid, amount: PKR $amount");
    $message='success:Fee added! Invoice: '.$inv;
}
if(isset($_GET['pay'])){
    $fid=intval($_GET['pay']);
    $f=$conn->query("SELECT f.*,s.user_id FROM fees f JOIN students s ON f.student_id=s.student_id WHERE f.fee_id=$fid")->fetch_assoc();
    $conn->query("UPDATE fees SET status='paid',paid_date=CURDATE() WHERE fee_id=$fid");
    if($f)sendNotification($conn,$f['user_id'],'✅ Payment Confirmed',"Your fee payment of PKR ".number_format($f['amount']+$f['fine']-$f['discount'],0)." has been confirmed. Invoice: {$f['invoice_no']}.",'success');
    header("Location: fees.php?msg=paid");exit();
}
if(isset($_GET['delete'])){$conn->query("DELETE FROM fees WHERE fee_id=".intval($_GET['delete']));header("Location: fees.php?msg=deleted");exit();}

$fees=$conn->query("SELECT f.*,u.name,r.room_number FROM fees f JOIN students s ON f.student_id=s.student_id JOIN users u ON s.user_id=u.user_id LEFT JOIN rooms r ON s.room_id=r.room_id ORDER BY f.fee_id DESC");
$students=$conn->query("SELECT s.student_id,u.name,s.student_code FROM students s JOIN users u ON s.user_id=u.user_id WHERE s.status='active' ORDER BY u.name");
// Summary
$total_col=$conn->query("SELECT COALESCE(SUM(amount-discount+fine),0) as t FROM fees WHERE status='paid'")->fetch_assoc()['t'];
$total_pen=$conn->query("SELECT COALESCE(SUM(amount-discount+fine),0) as t FROM fees WHERE status='unpaid'")->fetch_assoc()['t'];
$overdue_count=$conn->query("SELECT COUNT(*) as c FROM fees WHERE status='unpaid' AND due_date<'$today'")->fetch_assoc()['c'];
$page_title='Fee Management';$page_subtitle='Track, manage and collect student fees';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?=$theme_data['theme']?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fees — Hostel Pro</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in">
<?php include('../includes/topbar.php');?>
<?php if($message):[$t,$m]=explode(":",$message,2);?><div class="alert <?=$t?>"><?=$t==='success'?'✅':'⚠'?> <?=htmlspecialchars($m)?></div><?php endif;?>
<?php if(isset($_GET['msg'])):?><div class="alert success">✅ <?=$_GET['msg']==='paid'?'Fee marked paid!':($_GET['msg']==='deleted'?'Fee deleted.':'Done.')?></div><?php endif;?>

<!-- Summary -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
    <div class="stat-card" style="padding:16px"><div class="stat-num" style="font-size:20px;color:var(--success)">PKR <?=number_format($total_col,0)?></div><div class="stat-label">Collected</div></div>
    <div class="stat-card" style="padding:16px"><div class="stat-num" style="font-size:20px;color:var(--danger)">PKR <?=number_format($total_pen,0)?></div><div class="stat-label">Pending</div></div>
    <div class="stat-card" style="padding:16px"><div class="stat-num" style="font-size:20px;color:var(--warning)"><?=$overdue_count?></div><div class="stat-label">Overdue Records</div></div>
</div>

<div class="form-card">
    <div class="form-card-title">➕ Add Fee Record</div>
    <form method="POST">
        <div class="form-grid-4">
            <select name="student_id" required>
                <option value="">— Select Student —</option>
                <?php while($s=$students->fetch_assoc()):?><option value="<?=$s['student_id']?>"><?=htmlspecialchars($s['name'])?> (<?=$s['student_code']??'—'?>)</option><?php endwhile;?>
            </select>
            <input type="number" name="amount"   placeholder="Amount (PKR)" step="0.01" required>
            <input type="number" name="discount" placeholder="Discount (PKR)" step="0.01" value="0">
            <input type="number" name="fine"     placeholder="Fine (PKR)" step="0.01" value="0">
            <input type="date"   name="due_date" required>
            <input type="text"   name="month_year" placeholder="Month (2025-04)" value="<?=date('Y-m')?>">
            <select name="payment_method">
                <option value="cash">💵 Cash</option>
                <option value="online">💻 Online</option>
                <option value="bank">🏦 Bank</option>
                <option value="wallet">📱 Wallet</option>
            </select>
            <input type="text" name="notes" placeholder="Notes (optional)">
        </div>
        <div class="action-row">
            <button type="submit" name="add_fee" class="btn btn-primary">Add Fee</button>
            <a href="fees.php?autofine=1" class="btn btn-warning" onclick="return confirm('Apply PKR 500 fine to ALL overdue unpaid fees?')">⚡ Auto Fine Overdue</a>
            <a href="notifications.php?auto=1" class="btn btn-outline">🔔 Send Fee Alerts</a>
        </div>
    </form>
</div>

<!-- Filter -->
<div class="filter-bar">
    <input type="text" id="feeSearch" placeholder="🔍 Search student name or invoice..." oninput="filterFees()">
    <select id="statusF" onchange="filterFees()"><option value="">💰 All Status</option><option value="paid">✅ Paid</option><option value="unpaid">❌ Unpaid</option></select>
    <select id="overdueF" onchange="filterFees()"><option value="">📅 All</option><option value="overdue">⚠ Overdue</option></select>
    <select id="pmF" onchange="filterFees()"><option value="">💳 All Methods</option><option value="cash">Cash</option><option value="online">Online</option><option value="bank">Bank</option><option value="wallet">Wallet</option></select>
    <button class="btn btn-outline btn-sm" onclick="clearFeeFilters()">✕ Clear</button>
    <div id="result-count"></div>
</div>
<div id="fee-summary" style="margin-bottom:12px"></div>
<div class="empty-state" id="no-fee-results" style="display:none;margin-bottom:16px"><div class="empty-icon">💰</div><h3>No records found</h3></div>

<div class="table-wrap">
<table>
<thead><tr><th>Invoice</th><th>Student</th><th>Room</th><th>Amount</th><th>Discount</th><th>Fine</th><th>Net</th><th>Due</th><th>Method</th><th>Status</th><th>Actions</th></tr></thead>
<tbody id="feeTableBody">
<?php while($f=$fees->fetch_assoc()):
    $net=($f['amount']-$f['discount'])+$f['fine'];
    $ov=($f['status']==='unpaid'&&$f['due_date']<$today);
?>
<tr class="fee-row <?=$ov?'overdue':''?>"
    data-name="<?=strtolower(htmlspecialchars($f['name']))?>"
    data-inv="<?=strtolower(htmlspecialchars($f['invoice_no']??''))?>"
    data-status="<?=$f['status']?>"
    data-overdue="<?=$ov?'overdue':'ok'?>"
    data-pm="<?=$f['payment_method']?>"
    data-amount="<?=$net?>">
    <td><span class="badge badge-muted" style="font-size:10px"><?=htmlspecialchars($f['invoice_no']??'—')?></span></td>
    <td><?=htmlspecialchars($f['name'])?></td>
    <td><?=htmlspecialchars($f['room_number']??'N/A')?></td>
    <td>PKR <?=number_format($f['amount'],0)?></td>
    <td><?=$f['discount']>0?'PKR '.number_format($f['discount'],0):'—'?></td>
    <td><?=$f['fine']>0?'<span style="color:var(--danger)">PKR '.number_format($f['fine'],0).'</span>':'—'?></td>
    <td><strong>PKR <?=number_format($net,0)?></strong></td>
    <td><?=$f['due_date']?><?php if($ov):?><span style="font-size:10px;color:var(--danger);margin-left:4px;font-weight:700">⚠ OVERDUE</span><?php endif;?></td>
    <td><span class="badge badge-muted"><?=ucfirst($f['payment_method'])?></span></td>
    <td><span class="badge <?=$f['status']==='paid'?'badge-success':'badge-danger'?>"><?=ucfirst($f['status'])?></span></td>
    <td style="display:flex;gap:4px">
        <?php if($f['status']==='unpaid'):?><a href="fees.php?pay=<?=$f['fee_id']?>" class="btn btn-success btn-sm">✅</a><?php endif;?>
        <a href="fees.php?delete=<?=$f['fee_id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">🗑</a>
    </td>
</tr>
<?php endwhile;?>
</tbody>
</table>
</div>
</div>
<script>
function filterFees(){
    const s=document.getElementById('feeSearch').value.toLowerCase();
    const st=document.getElementById('statusF').value;
    const ov=document.getElementById('overdueF').value;
    const pm=document.getElementById('pmF').value;
    const rows=document.querySelectorAll('.fee-row');
    let v=0,tot=0;
    rows.forEach(r=>{
        const ok=(!s||(r.dataset.name+r.dataset.inv).includes(s))&&(!st||r.dataset.status===st)&&(!ov||r.dataset.overdue===ov)&&(!pm||r.dataset.pm===pm);
        r.style.display=ok?'':'none';if(ok){v++;tot+=parseFloat(r.dataset.amount)||0;}
    });
    document.getElementById('result-count').textContent=v+' record'+(v!==1?'s':'')+' found';
    document.getElementById('fee-summary').innerHTML=v>0?`<span style="padding:6px 14px;background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.15);border-radius:8px;font-size:13px;color:var(--accent)">Showing total: <strong>PKR ${tot.toLocaleString('en-PK',{minimumFractionDigits:0})}</strong></span>`:'';
    document.getElementById('no-fee-results').style.display=v===0?'block':'none';
}
function clearFeeFilters(){['feeSearch','statusF','overdueF','pmF'].forEach(id=>document.getElementById(id).value='');filterFees();}
window.onload=filterFees;
</script>
</body>
</html>
