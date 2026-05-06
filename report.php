<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');

if(!file_exists('../fpdf/fpdf.php')){
    $current_page='report';$uid=$_SESSION['user_id'];
    $unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
    $open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
    $pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];
    $page_title='PDF Report';$page_subtitle='Download comprehensive hostel report';
    ?>
    <!DOCTYPE html><html lang="en" data-theme="<?=$theme_data['theme']?>">
    <head><meta charset="UTF-8"><title>Report</title><link rel="stylesheet" href="../assets/css/style.css"></head>
    <body data-theme="<?=$theme_data['theme']?>">
    <?php include('../includes/sidebar.php');?>
    <div class="main animate-in"><?php include('../includes/topbar.php');?>
    <div class="glass-card" style="padding:48px;text-align:center;max-width:500px;margin:60px auto">
        <div style="font-size:52px;margin-bottom:20px">📄</div>
        <h2 style="font-size:20px;margin-bottom:12px">FPDF Library Required</h2>
        <p style="color:var(--text3);margin-bottom:24px;line-height:1.7">To generate PDF reports, install FPDF library.</p>
        <div style="background:rgba(0,0,0,.3);border-radius:10px;padding:16px;text-align:left;font-size:13px;color:var(--text2);line-height:2.2">
            <div>1. Download from <a href="http://www.fpdf.org" target="_blank" style="color:var(--accent)">fpdf.org</a></div>
            <div>2. Extract the <code style="color:var(--accent)">fpdf</code> folder</div>
            <div>3. Place at: <code style="color:var(--accent)">hostel-ultimate/fpdf/fpdf.php</code></div>
            <div>4. Refresh this page ✅</div>
        </div>
    </div></div></body></html>
    <?php exit();
}

require('../fpdf/fpdf.php');
$students=$conn->query("SELECT u.name,u.email,s.phone,s.cnic,s.student_code,s.gender,r.room_number,COALESCE(SUM(f.amount-f.discount+f.fine),0) as total_fees,COALESCE(SUM(CASE WHEN f.status='unpaid' THEN f.amount-f.discount+f.fine ELSE 0 END),0) as unpaid,COALESCE(SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END),0) as present_days,COALESCE(COUNT(DISTINCT a.attendance_id),0) as total_days FROM students s JOIN users u ON s.user_id=u.user_id LEFT JOIN rooms r ON s.room_id=r.room_id LEFT JOIN fees f ON s.student_id=f.student_id LEFT JOIN attendance a ON s.student_id=a.student_id WHERE s.status='active' GROUP BY s.student_id ORDER BY u.name");
$ts=$conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'];
$tr=$conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'];
$col=$conn->query("SELECT COALESCE(SUM(amount-discount+fine),0) as t FROM fees WHERE status='paid'")->fetch_assoc()['t'];
$pen=$conn->query("SELECT COALESCE(SUM(amount-discount+fine),0) as t FROM fees WHERE status='unpaid'")->fetch_assoc()['t'];

$pdf=new FPDF('L','mm','A4');$pdf->AddPage();$pdf->SetMargins(8,8,8);
$pdf->SetFillColor(5,10,20);$pdf->Rect(0,0,297,32,'F');
$pdf->SetTextColor(0,212,255);$pdf->SetFont('Arial','B',18);$pdf->SetXY(0,4);
$pdf->Cell(297,12,'AI-POWERED SECURE SMART HOSTEL MANAGEMENT SYSTEM',0,1,'C');
$pdf->SetFont('Arial','',9);$pdf->SetTextColor(100,150,180);
$pdf->Cell(297,6,'Complete Student Report  ·  Generated: '.date('d M Y, h:i A').'  ·  By: '.htmlspecialchars($_SESSION['name']),0,1,'C');
$pdf->Ln(4);

$sums=[['Active Students',$ts,[0,100,160]],['Total Rooms',$tr,[0,80,130]],['Collected','PKR '.number_format($col,0),[0,140,80]],['Pending','PKR '.number_format($pen,0),[160,50,60]]];
$bx=8;$bw=69;
foreach($sums as $s){[$r,$g,$b]=$s[2];$pdf->SetFillColor($r,$g,$b);$pdf->SetXY($bx,35);$pdf->SetTextColor(255,255,255);$pdf->SetFont('Arial','',8);$pdf->Cell($bw,6,$s[0],0,0,'C',true);$pdf->SetXY($bx,41);$pdf->SetFont('Arial','B',13);$pdf->Cell($bw,10,(string)$s[1],1,0,'C',true);$bx+=$bw+3;}
$pdf->SetXY(8,57);

$cols=[['#',8],['Name',40],['Code',20],['Email',48],['CNIC',32],['Room',16],['Total',26],['Unpaid',26],['Att%',14]];
$pdf->SetFillColor(0,120,180);$pdf->SetTextColor(255,255,255);$pdf->SetFont('Arial','B',8);
foreach($cols as $c)$pdf->Cell($c[1],8,$c[0],1,0,'C',true);$pdf->Ln();
$pdf->SetFont('Arial','',7.5);$i=1;$fill=false;
while($r=$students->fetch_assoc()){
    $ap=$r['total_days']>0?round(($r['present_days']/$r['total_days'])*100):0;
    if($r['unpaid']>0)$pdf->SetFillColor(255,235,235);else{$fill=!$fill;$pdf->SetFillColor($fill?248:255,$fill?248:255,$fill?248:255);}
    $pdf->SetTextColor(30,30,30);
    $pdf->Cell(8,7,$i++,1,0,'C',true);$pdf->Cell(40,7,$r['name'],1,0,'L',true);$pdf->Cell(20,7,$r['student_code']??'—',1,0,'C',true);$pdf->Cell(48,7,$r['email'],1,0,'L',true);$pdf->Cell(32,7,$r['cnic'],1,0,'C',true);$pdf->Cell(16,7,$r['room_number']??'N/A',1,0,'C',true);$pdf->Cell(26,7,'PKR '.number_format($r['total_fees'],0),1,0,'R',true);$pdf->Cell(26,7,$r['unpaid']>0?'PKR '.number_format($r['unpaid'],0):'✓ Clear',1,0,'R',true);$pdf->Cell(14,7,$ap.'%',1,0,'C',true);$pdf->Ln();
}
$pdf->Ln(4);$pdf->SetFont('Arial','I',7);$pdf->SetTextColor(130,130,130);$pdf->Cell(0,5,'Red rows = outstanding fees  |  CONFIDENTIAL  |  Hostel Pro Ultimate Management System',0,1,'C');
logActivity($conn,$_SESSION['user_id'],'PDF Report','Downloaded PDF student report');
$pdf->Output('D','HostelPro_Report_'.date('Y-m-d').'.pdf');exit();
?>
