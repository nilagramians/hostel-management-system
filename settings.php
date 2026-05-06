<?php
session_start();
if(!isset($_SESSION['role'])||$_SESSION['role']!=='admin'){header("Location: ../auth/login.php");exit();}
include('../config/db.php');include('../includes/notifications.php');
$current_page='settings';$uid=$_SESSION['user_id'];
$unread=getUnreadCount($conn,$uid);$theme_data=getUserTheme($conn,$uid);
$open_complaints=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
$pending_leaves=$conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];
$message='';

// Change password
if(isset($_POST['change_password'])){
    $current=trim($_POST['current_password']);
    $new=trim($_POST['new_password']);
    $confirm=trim($_POST['confirm_password']);
    $user=$conn->query("SELECT password FROM users WHERE user_id=$uid")->fetch_assoc();
    if(!password_verify($current,$user['password'])){$message='error:Current password is incorrect.';}
    elseif(strlen($new)<8){$message='error:New password must be at least 8 characters.';}
    elseif($new!==$confirm){$message='error:New passwords do not match.';}
    else{
        $h=password_hash($new,PASSWORD_BCRYPT);
        $conn->query("UPDATE users SET password='$h' WHERE user_id=$uid");
        logActivity($conn,$uid,'Password Changed','Admin changed password');
        $message='success:Password changed successfully!';
    }
}
// Update profile
if(isset($_POST['update_profile'])){
    $name=trim($_POST['name']);
    $email=trim($_POST['email']);
    if(!$name||!$email){$message='error:Name and email are required.';}
    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){$message='error:Invalid email format.';}
    else{
        $stmt=$conn->prepare("UPDATE users SET name=?,email=? WHERE user_id=?");
        $stmt->bind_param("ssi",$name,$email,$uid);
        if($stmt->execute()){$_SESSION['name']=$name;$message='success:Profile updated!';}
        else{$message='error:Email already in use.';}
    }
}
$user=$conn->query("SELECT * FROM users WHERE user_id=$uid")->fetch_assoc();
$settings=$conn->query("SELECT * FROM settings WHERE user_id=$uid")->fetch_assoc();
$page_title='Settings';$page_subtitle='Manage your account and preferences';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?=$theme_data['theme']?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings — Hostel Pro</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-theme="<?=$theme_data['theme']?>">
<?php include('../includes/sidebar.php');?>
<div class="main animate-in">
    <?php include('../includes/topbar.php');?>
    <?php if($message):[$t,$m]=explode(":",$message,2);?><div class="alert <?=$t?>"><?=$t==='success'?'✅':'⚠'?> <?=htmlspecialchars($m)?></div><?php endif;?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <!-- Profile -->
        <div class="form-card">
            <div class="form-card-title">👤 Update Profile</div>
            <form method="POST">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="field"><label>Full Name</label><input type="text" name="name" value="<?=htmlspecialchars($user['name'])?>" required></div>
                    <div class="field"><label>Email Address</label><input type="email" name="email" value="<?=htmlspecialchars($user['email'])?>" required></div>
                    <div class="field"><label>Role</label><input type="text" value="<?=ucfirst($user['role'])?>" disabled style="opacity:.5"></div>
                </div>
                <div class="action-row"><button type="submit" name="update_profile" class="btn btn-primary">Save Profile</button></div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="form-card">
            <div class="form-card-title">🔐 Change Password</div>
            <form method="POST">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="field">
                        <label>Current Password</label>
                        <div class="input-group">
                            <input type="password" name="current_password" placeholder="Enter current password" required>
                            <button type="button" class="input-icon" onclick="tpw(this)">👁</button>
                        </div>
                    </div>
                    <div class="field">
                        <label>New Password</label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="np" placeholder="Min 8 characters" required oninput="pwStr(this.value)">
                            <button type="button" class="input-icon" onclick="tpw(this)">👁</button>
                        </div>
                        <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
                        <div class="field-hint muted" id="pwHint">Min 8 chars, uppercase, number</div>
                    </div>
                    <div class="field">
                        <label>Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" placeholder="Repeat new password" required oninput="checkMatch(this.value)">
                            <button type="button" class="input-icon" onclick="tpw(this)">👁</button>
                        </div>
                        <div class="field-hint error" id="matchHint"></div>
                    </div>
                </div>
                <div class="action-row"><button type="submit" name="change_password" class="btn btn-warning">🔑 Change Password</button></div>
            </form>
        </div>

        <!-- Theme Settings -->
        <div class="form-card">
            <div class="form-card-title">🎨 Appearance</div>
            <div style="margin-bottom:16px">
                <div style="font-size:12px;color:var(--text3);margin-bottom:10px;text-transform:uppercase;letter-spacing:.8px">Theme</div>
                <div class="theme-switcher">
                <?php foreach([['dark','🌑 Dark','#050A14'],['light','☀️ Light','#F0F4F8'],['cyber','💜 Cyber','#0A0010'],['ocean','🌊 Ocean','#020E1A'],['sunset','🌅 Sunset','#140808']] as [$t,$l,$bg]):?>
                    <button onclick="setTheme('<?=$t?>')" style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:<?=($settings['theme']??'dark')===$t?'rgba(0,212,255,0.1)':'var(--glass)'?>;border:1px solid <?=($settings['theme']??'dark')===$t?'rgba(0,212,255,0.3)':'var(--glass-border)'?>;border-radius:8px;color:var(--text);cursor:pointer;font-size:12px;transition:all .2s;font-family:inherit">
                        <div style="width:14px;height:14px;border-radius:50%;background:<?=$bg?>;border:2px solid rgba(255,255,255,.2)"></div>
                        <?=$l?>
                    </button>
                <?php endforeach;?>
                </div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text3);margin-bottom:10px;text-transform:uppercase;letter-spacing:.8px">Accent Color</div>
                <div style="display:flex;gap:10px;align-items:center">
                    <input type="color" value="<?=htmlspecialchars($settings['accent_color']??'#00D4FF')?>" id="accentPicker" style="width:48px;height:38px;border-radius:8px;border:1px solid var(--glass-border);background:none;cursor:pointer;padding:2px">
                    <button onclick="setAccent()" class="btn btn-outline btn-sm">Apply Color</button>
                </div>
            </div>
        </div>

        <!-- Account Info -->
        <div class="form-card">
            <div class="form-card-title">ℹ️ Account Information</div>
            <?php
            $lastLogin=$conn->query("SELECT last_login FROM users WHERE user_id=$uid")->fetch_assoc()['last_login'];
            $loginCount=$conn->query("SELECT COUNT(*) as c FROM activity_logs WHERE user_id=$uid AND action='Login'")->fetch_assoc()['c'];
            $created=$conn->query("SELECT created_at FROM users WHERE user_id=$uid")->fetch_assoc()['created_at'];
            ?>
            <div style="display:flex;flex-direction:column;gap:12px">
                <?php foreach([
                    ['User ID','#'.$uid],['Role',ucfirst($_SESSION['role'])],
                    ['Last Login',$lastLogin?date('d M Y, h:i A',strtotime($lastLogin)):'Never'],
                    ['Total Logins',$loginCount.' times'],
                    ['Account Created',date('d M Y',strtotime($created))],
                    ['Account Status','✅ Active'],
                ] as [$lbl,$val]):?>
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--glass-border)">
                    <span style="font-size:13px;color:var(--text3)"><?=$lbl?></span>
                    <span style="font-size:13px;color:var(--text);font-weight:500"><?=$val?></span>
                </div>
                <?php endforeach;?>
            </div>
            <div class="action-row" style="margin-top:16px">
                <a href="logs.php" class="btn btn-outline btn-sm">📝 View Activity Logs</a>
            </div>
        </div>
    </div>
</div>
<script>
function tpw(btn){const i=btn.previousElementSibling||btn.parentElement.querySelector('input');if(!i)return;i.type=i.type==='password'?'text':'password';btn.textContent=i.type==='password'?'👁':'🙈';}
function pwStr(v){
    const bar=document.getElementById('pwBar');const hint=document.getElementById('pwHint');let s=0;
    if(v.length>=8)s++;if(v.length>=12)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
    const cols=['#ff4d6d','#ffaa00','#ffaa00','#00d4ff','#00ff88'];const labs=['Very weak','Weak','Fair','Strong','Very strong'];
    bar.style.width=(s*20)+'%';bar.style.background=cols[s-1]||'rgba(255,255,255,.1)';
    hint.textContent=s?labs[s-1]:'Min 8 chars, uppercase, number';hint.className='field-hint '+(s>=3?'success':'muted');
}
function checkMatch(v){const mh=document.getElementById('matchHint');const np=document.getElementById('np').value;mh.textContent=v&&v!==np?'Passwords do not match':'';mh.className='field-hint '+(v&&v!==np?'error':'');}
function setTheme(t){fetch('settings_api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=theme&theme='+t}).then(()=>location.reload());}
function setAccent(){const c=document.getElementById('accentPicker').value;fetch('settings_api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=accent&color='+encodeURIComponent(c)}).then(()=>location.reload());}
</script>
</body>
</html>
