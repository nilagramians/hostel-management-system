<?php
session_start();
if (isset($_SESSION['role'])) {
    header("Location: ../".($_SESSION['role']==='admin'?'admin':'student')."/dashboard.php"); exit();
}
include('../config/db.php');
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) { $error = 'Please fill in all fields.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Invalid email format.'; }
    else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND is_active=1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) { $error = 'No active account found with this email.'; }
        elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $mins = ceil((strtotime($user['locked_until'])-time())/60);
            $error = "Account temporarily locked. Try again in $mins minute(s).";
        } elseif (!password_verify($password, $user['password'])) {
            $attempts = $user['login_attempts'] + 1;
            if ($attempts >= 5) {
                $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $conn->query("UPDATE users SET login_attempts=$attempts, locked_until='$lockUntil' WHERE user_id={$user['user_id']}");
                $error = 'Too many failed attempts. Account locked for 15 minutes.';
            } else {
                $conn->query("UPDATE users SET login_attempts=$attempts WHERE user_id={$user['user_id']}");
                $left = 5 - $attempts;
                $error = "Incorrect password. $left attempt(s) remaining.";
            }
        } else {
            // Reset attempts, update last login
            $now = date('Y-m-d H:i:s');
            $conn->query("UPDATE users SET login_attempts=0, locked_until=NULL, last_login='$now' WHERE user_id={$user['user_id']}");
            // Init settings
            $conn->query("INSERT IGNORE INTO settings (user_id) VALUES ({$user['user_id']})");
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            logActivity($conn, $user['user_id'], 'Login', 'User logged in successfully');
            header("Location: ../".($user['role']==='admin'?'admin':'student')."/dashboard.php"); exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Hostel Pro Ultimate</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--accent:#00D4FF;--danger:#FF4D6D;--success:#00FF88;--text:#E2EEFF;--muted:#8FAFC8}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#050A14;color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.wrap{position:relative;z-index:10;width:100%;max-width:460px;padding:20px}
.card{background:rgba(13,21,32,0.92);backdrop-filter:blur(30px);border:1px solid rgba(0,212,255,0.12);border-radius:20px;padding:44px 40px;box-shadow:0 30px 80px rgba(0,0,0,0.5),inset 0 1px 0 rgba(255,255,255,0.06);animation:cardIn .5s ease}
@keyframes cardIn{from{opacity:0;transform:translateY(24px) scale(0.98)}to{opacity:1;transform:translateY(0) scale(1)}}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(0,212,255,0.5),transparent);border-radius:20px 20px 0 0}
.logo{text-align:center;margin-bottom:30px}
.logo-ring{width:72px;height:72px;border-radius:50%;background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.2);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:32px;animation:ringPulse 3s ease infinite}
@keyframes ringPulse{0%,100%{box-shadow:0 0 0 0 rgba(0,212,255,0.2)}50%{box-shadow:0 0 0 12px rgba(0,212,255,0)}}
.logo h1{font-size:20px;font-weight:800;background:linear-gradient(135deg,var(--text),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo p{color:rgba(143,175,200,0.6);font-size:12px;margin-top:3px}
.tabs{display:flex;gap:0;background:rgba(0,0,0,0.3);border-radius:10px;padding:3px;margin-bottom:24px}
.tab{flex:1;padding:8px;text-align:center;font-size:13px;font-weight:600;border-radius:8px;cursor:pointer;color:var(--muted);transition:all .2s;border:none;background:none;font-family:inherit}
.tab.active{background:rgba(0,212,255,0.12);color:var(--accent)}
.tab-panel{display:none}.tab-panel.active{display:block}
.field{margin-bottom:16px}
.field label{display:block;font-size:11px;color:rgba(143,175,200,0.7);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;font-weight:600}
.input-wrap{position:relative}
.input-wrap input,.input-wrap select,.input-wrap textarea{width:100%;padding:12px 42px 12px 40px;background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.07);border-radius:10px;color:var(--text);font-size:14px;outline:none;transition:all .2s;font-family:inherit}
.input-wrap input:focus,.input-wrap select:focus{border-color:rgba(0,212,255,0.4);background:rgba(0,212,255,0.03);box-shadow:0 0 0 4px rgba(0,212,255,0.07)}
.input-wrap input.error{border-color:rgba(255,77,109,0.5)}
.input-wrap input.valid{border-color:rgba(0,255,136,0.4)}
.input-wrap input::placeholder{color:rgba(143,175,200,0.3)}
.icon-l{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:14px;opacity:.5;pointer-events:none}
.icon-r{position:absolute;right:13px;top:50%;transform:translateY(-50%);font-size:14px;cursor:pointer;opacity:.5;transition:opacity .2s;background:none;border:none;color:inherit;padding:4px}
.icon-r:hover{opacity:1}
.field-hint{font-size:11px;margin-top:4px}
.field-hint.error{color:var(--danger)}.field-hint.success{color:var(--success)}.field-hint.muted{color:var(--muted)}
.pw-strength{height:3px;border-radius:10px;background:rgba(255,255,255,.1);margin-top:6px;overflow:hidden}
.pw-bar{height:100%;border-radius:10px;transition:width .3s,background .3s;width:0}
.check-row{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);margin-bottom:14px}
.check-row input{width:14px;height:14px;cursor:pointer;accent-color:var(--accent)}
.forgot-link{float:right;font-size:12px;color:var(--accent);text-decoration:none;line-height:1.8;transition:opacity .2s}
.forgot-link:hover{opacity:.7}
.btn-login{width:100%;padding:13px;background:linear-gradient(135deg,#00d4ff,#0099cc);color:#000;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;letter-spacing:.3px;position:relative;overflow:hidden;font-family:inherit}
.btn-login::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.15),transparent);opacity:0;transition:opacity .2s}
.btn-login:hover{transform:translateY(-1px);box-shadow:0 8px 30px rgba(0,212,255,.35)}
.btn-login:hover::before{opacity:1}
.btn-login:active{transform:translateY(0)}
.btn-login.loading{opacity:.8;pointer-events:none}
.spinner{display:none;width:16px;height:16px;border:2px solid rgba(0,0,0,.3);border-top-color:#000;border-radius:50%;animation:spin .6s linear infinite;margin:0 auto}
@keyframes spin{to{transform:rotate(360deg)}}
.alert-box{padding:11px 14px;border-radius:10px;font-size:13px;display:flex;align-items:center;gap:10px;margin-bottom:16px;animation:slideIn .3s ease}
@keyframes slideIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.alert-box.error{background:rgba(255,77,109,.08);border:1px solid rgba(255,77,109,.25);color:#ff4d6d}
.alert-box.success{background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.25);color:#00ff88}
.alert-box.info{background:rgba(0,212,255,.08);border:1px solid rgba(0,212,255,.25);color:#00d4ff}
.divider{height:1px;background:rgba(255,255,255,.06);margin:20px 0;position:relative;text-align:center}
.divider span{position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:#0d1520;padding:0 10px;font-size:11px;color:var(--muted)}
.demo-row{display:flex;gap:8px;margin-top:16px}
.demo-btn{flex:1;padding:7px;background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.15);border-radius:8px;color:rgba(0,212,255,.7);cursor:pointer;font-size:11px;transition:all .2s;font-family:inherit}
.demo-btn:hover{background:rgba(0,212,255,.12);color:var(--accent)}
/* OTP fields */
.otp-wrap{display:flex;gap:10px;justify-content:center;margin-bottom:16px}
.otp-wrap input{width:46px;height:52px;text-align:center;font-size:20px;font-weight:700;background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.07);border-radius:10px;color:var(--text);outline:none;transition:all .2s;padding:0}
.otp-wrap input:focus{border-color:rgba(0,212,255,.4);box-shadow:0 0 0 4px rgba(0,212,255,.07)}
.otp-wrap input.filled{border-color:rgba(0,212,255,.4);background:rgba(0,212,255,.05)}
.countdown{text-align:center;font-size:12px;color:var(--muted);margin-bottom:16px}
.resend-link{color:var(--accent);cursor:pointer;text-decoration:none}
.resend-link.disabled{color:var(--muted);pointer-events:none}
</style>
</head>
<body>
<canvas id="canvas"></canvas>
<div class="wrap">
<div class="card">
    <div class="logo">
        <div class="logo-ring">🏠</div>
        <h1>HOSTEL PRO ULTIMATE</h1>
        <p>AI-Powered Smart Management System</p>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="switchTab('login')">🔐 Login</button>
        <button class="tab" onclick="switchTab('forgot')">🔑 Forgot Password</button>
    </div>

    <!-- LOGIN PANEL -->
    <div class="tab-panel active" id="tab-login">
        <?php if($error):?><div class="alert-box error">⚠ <?=htmlspecialchars($error)?></div><?php endif;?>
        <?php if($success):?><div class="alert-box success">✅ <?=htmlspecialchars($success)?></div><?php endif;?>

        <form method="POST" id="loginForm" novalidate>
            <div class="field">
                <label>Email Address</label>
                <div class="input-wrap">
                    <span class="icon-l">✉</span>
                    <input type="email" name="email" id="emailIn" placeholder="your@email.com"
                           value="<?=htmlspecialchars($_POST['email']??'')?>" autocomplete="email" required>
                    <span class="icon-r" id="emailIcon"></span>
                </div>
                <div class="field-hint muted" id="emailHint"></div>
            </div>
            <div class="field">
                <label>Password <a href="#" class="forgot-link" onclick="switchTab('forgot');return false">Forgot?</a></label>
                <div class="input-wrap">
                    <span class="icon-l">🔒</span>
                    <input type="password" name="password" id="pwIn" placeholder="••••••••" autocomplete="current-password" required>
                    <button type="button" class="icon-r" id="togglePw" title="Show/Hide">👁</button>
                </div>
            </div>
            <div class="check-row">
                <input type="checkbox" name="remember" id="remember">
                <label for="remember">Remember me</label>
            </div>
            <button type="submit" name="login" class="btn-login" id="loginBtn">
                <span id="btnText">LOGIN →</span>
                <div class="spinner" id="btnSpinner"></div>
            </button>
        </form>

        <div class="divider"><span>Quick Demo</span></div>
        <div class="demo-row">
            <button class="demo-btn" onclick="fillDemo('admin@hostel.com','admin123')">👨‍💼 Admin Login</button>
            <button class="demo-btn" onclick="fillDemo('ali@student.com','pass123')">👨‍🎓 Student Login</button>
        </div>
    </div>

    <!-- FORGOT PASSWORD PANEL -->
    <div class="tab-panel" id="tab-forgot">
        <div id="step-email">
            <div class="alert-box info" style="margin-bottom:16px">ℹ Enter your email to receive a password reset OTP.</div>
            <div class="field">
                <label>Your Registered Email</label>
                <div class="input-wrap">
                    <span class="icon-l">✉</span>
                    <input type="email" id="resetEmail" placeholder="your@email.com">
                </div>
                <div class="field-hint muted" id="resetHint"></div>
            </div>
            <button class="btn-login" onclick="sendOTP()" id="sendOtpBtn">Send Reset OTP</button>
        </div>

        <div id="step-otp" style="display:none">
            <div class="alert-box success" id="otpSentMsg"></div>
            <div style="text-align:center;margin-bottom:12px;font-size:13px;color:var(--muted)">Enter the 6-digit OTP sent to your email</div>
            <div class="otp-wrap">
                <input type="text" maxlength="1" class="otp-digit" oninput="otpInput(this)">
                <input type="text" maxlength="1" class="otp-digit" oninput="otpInput(this)">
                <input type="text" maxlength="1" class="otp-digit" oninput="otpInput(this)">
                <input type="text" maxlength="1" class="otp-digit" oninput="otpInput(this)">
                <input type="text" maxlength="1" class="otp-digit" oninput="otpInput(this)">
                <input type="text" maxlength="1" class="otp-digit" oninput="otpInput(this)">
            </div>
            <div class="countdown" id="countdown">OTP expires in <span id="timer">05:00</span> · <a class="resend-link disabled" id="resendLink" onclick="resendOTP()">Resend</a></div>
            <button class="btn-login" onclick="verifyOTP()" id="verifyBtn">Verify OTP</button>
        </div>

        <div id="step-newpw" style="display:none">
            <div class="alert-box success">✅ OTP verified! Set your new password.</div>
            <div class="field">
                <label>New Password</label>
                <div class="input-wrap">
                    <span class="icon-l">🔒</span>
                    <input type="password" id="newPw" placeholder="Min 8 characters" oninput="checkPwStrength(this.value)">
                    <button type="button" class="icon-r" onclick="togglePwField('newPw')">👁</button>
                </div>
                <div class="pw-strength"><div class="pw-bar" id="pwBar"></div></div>
                <div class="field-hint muted" id="pwHint">Use 8+ chars, uppercase, number</div>
            </div>
            <div class="field">
                <label>Confirm Password</label>
                <div class="input-wrap">
                    <span class="icon-l">🔒</span>
                    <input type="password" id="confirmPw" placeholder="Repeat password">
                    <button type="button" class="icon-r" onclick="togglePwField('confirmPw')">👁</button>
                </div>
                <div class="field-hint error" id="confirmHint"></div>
            </div>
            <button class="btn-login" onclick="resetPassword()" id="resetBtn">Reset Password</button>
        </div>

        <div style="text-align:center;margin-top:16px">
            <a href="#" onclick="switchTab('login');return false" style="font-size:12px;color:var(--accent);text-decoration:none">← Back to Login</a>
        </div>
    </div>

</div>
</div>

<script>
// Canvas particle background
const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');
canvas.width = innerWidth; canvas.height = innerHeight;
window.addEventListener('resize',()=>{canvas.width=innerWidth;canvas.height=innerHeight;});
const pts = Array.from({length:60},()=>({x:Math.random()*innerWidth,y:Math.random()*innerHeight,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,r:Math.random()*2+.5}));
function drawCanvas(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    ctx.fillStyle='rgba(0,212,255,0.4)';
    pts.forEach(p=>{p.x+=p.vx;p.y+=p.vy;if(p.x<0||p.x>canvas.width)p.vx*=-1;if(p.y<0||p.y>canvas.height)p.vy*=-1;ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fill();});
    pts.forEach((a,i)=>pts.slice(i+1).forEach(b=>{const d=Math.hypot(a.x-b.x,a.y-b.y);if(d<100){ctx.strokeStyle=`rgba(0,212,255,${.15*(1-d/100)})`;ctx.lineWidth=.5;ctx.beginPath();ctx.moveTo(a.x,a.y);ctx.lineTo(b.x,b.y);ctx.stroke();}}));
    requestAnimationFrame(drawCanvas);
}
drawCanvas();

// Tab switching
function switchTab(t){
    document.querySelectorAll('.tab').forEach((el,i)=>el.classList.toggle('active',['login','forgot'][i]===t));
    document.querySelectorAll('.tab-panel').forEach((el,i)=>el.classList.toggle('active',['tab-login','tab-forgot'][i]==='tab-'+t));
}

// Login form
const pwIn = document.getElementById('pwIn');
const togglePw = document.getElementById('togglePw');
let pwVis = false;
togglePw.addEventListener('click',()=>{pwVis=!pwVis;pwIn.type=pwVis?'text':'password';togglePw.textContent=pwVis?'🙈':'👁';});

const emailIn = document.getElementById('emailIn');
const emailIcon = document.getElementById('emailIcon');
const emailHint = document.getElementById('emailHint');
emailIn.addEventListener('input',()=>{
    const v = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailIn.value);
    emailIcon.textContent = emailIn.value?(v?'✅':'❌'):'';
    emailIn.className = emailIn.value?(v?'valid':'error'):'';
    emailHint.textContent = (emailIn.value&&!v)?'Please enter a valid email':'';
    emailHint.className = 'field-hint '+(emailIn.value&&!v?'error':'muted');
});

document.getElementById('loginForm').addEventListener('submit',e=>{
    if(!emailIn.value||!pwIn.value){e.preventDefault();return;}
    document.getElementById('btnText').style.display='none';
    document.getElementById('btnSpinner').style.display='block';
    document.getElementById('loginBtn').classList.add('loading');
});

function fillDemo(e,p){emailIn.value=e;pwIn.value=p;emailIn.dispatchEvent(new Event('input'));}

// Password strength
function checkPwStrength(v){
    const bar=document.getElementById('pwBar');
    const hint=document.getElementById('pwHint');
    let score=0;
    if(v.length>=8)score++;if(v.length>=12)score++;
    if(/[A-Z]/.test(v))score++;if(/[0-9]/.test(v))score++;if(/[^A-Za-z0-9]/.test(v))score++;
    const colors=['#ff4d6d','#ffaa00','#ffaa00','#00d4ff','#00ff88'];
    const labels=['Very weak','Weak','Fair','Strong','Very strong'];
    bar.style.width=(score*20)+'%';bar.style.background=colors[score-1]||'rgba(255,255,255,0.1)';
    hint.textContent=score?labels[score-1]:'Use 8+ chars, uppercase, number';
    hint.className='field-hint '+(score>=3?'success':'muted');
}
function togglePwField(id){const el=document.getElementById(id);el.type=el.type==='password'?'text':'password';}

// OTP handling
let resetEmailVal='', otpTimer=null, resetToken='';

function sendOTP(){
    const email=document.getElementById('resetEmail').value.trim();
    const hint=document.getElementById('resetHint');
    if(!email||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){hint.textContent='Enter a valid email.';hint.className='field-hint error';return;}
    document.getElementById('sendOtpBtn').textContent='Sending...';
    document.getElementById('sendOtpBtn').disabled=true;
    fetch('send_otp.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'email='+encodeURIComponent(email)})
    .then(r=>r.json()).then(d=>{
        if(d.success){
            resetEmailVal=email;
            document.getElementById('step-email').style.display='none';
            document.getElementById('step-otp').style.display='block';
            document.getElementById('otpSentMsg').textContent='✅ OTP sent to '+email+' (check console/DB for demo)';
            startTimer(300);
        } else {
            hint.textContent=d.message||'Email not found.';hint.className='field-hint error';
            document.getElementById('sendOtpBtn').textContent='Send Reset OTP';
            document.getElementById('sendOtpBtn').disabled=false;
        }
    }).catch(()=>{hint.textContent='Error. Try again.';hint.className='field-hint error';document.getElementById('sendOtpBtn').textContent='Send Reset OTP';document.getElementById('sendOtpBtn').disabled=false;});
}

function otpInput(el){
    if(el.value){el.classList.add('filled');el.nextElementSibling&&el.nextElementSibling.focus();}
    else{el.classList.remove('filled');el.previousElementSibling&&el.previousElementSibling.focus();}
}

function getOTPValue(){return Array.from(document.querySelectorAll('.otp-digit')).map(el=>el.value).join('');}

function verifyOTP(){
    const otp=getOTPValue();
    if(otp.length<6){alert('Enter complete 6-digit OTP');return;}
    document.getElementById('verifyBtn').textContent='Verifying...';
    fetch('verify_otp.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'email='+encodeURIComponent(resetEmailVal)+'&otp='+otp})
    .then(r=>r.json()).then(d=>{
        if(d.success){resetToken=d.token;document.getElementById('step-otp').style.display='none';document.getElementById('step-newpw').style.display='block';clearInterval(otpTimer);}
        else{alert(d.message||'Invalid OTP');document.getElementById('verifyBtn').textContent='Verify OTP';}
    }).catch(()=>{alert('Error. Try again.');document.getElementById('verifyBtn').textContent='Verify OTP';});
}

function resetPassword(){
    const np=document.getElementById('newPw').value;
    const cp=document.getElementById('confirmPw').value;
    const ch=document.getElementById('confirmHint');
    if(np.length<8){alert('Password must be at least 8 characters');return;}
    if(np!==cp){ch.textContent='Passwords do not match';return;}
    ch.textContent='';
    document.getElementById('resetBtn').textContent='Resetting...';
    fetch('reset_password.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'token='+encodeURIComponent(resetToken)+'&password='+encodeURIComponent(np)})
    .then(r=>r.json()).then(d=>{
        if(d.success){alert('✅ Password reset successfully! Please login.');switchTab('login');}
        else{alert(d.message||'Reset failed');document.getElementById('resetBtn').textContent='Reset Password';}
    });
}

function resendOTP(){sendOTP();}

function startTimer(secs){
    clearInterval(otpTimer);let s=secs;
    const el=document.getElementById('timer');const rl=document.getElementById('resendLink');
    rl.className='resend-link disabled';
    otpTimer=setInterval(()=>{s--;const m=Math.floor(s/60),sec=s%60;
        el.textContent=(m<10?'0'+m:m)+':'+(sec<10?'0'+sec:sec);
        if(s<=0){clearInterval(otpTimer);el.textContent='expired';rl.className='resend-link';}},1000);
}
</script>
</body>
</html>
