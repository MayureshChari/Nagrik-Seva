<?php
session_start();
require_once 'config.php';

if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'officer') {
    header('Location: officer_dashboard.php'); exit;
}

$error = $success = '';
$depts = ['Road & PWD','Water Supply','Electricity','Sanitation & Health','Property & Revenue','Lost & Found','General Administration'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pass   = trim($_POST['password'] ?? '');
    $cpass  = trim($_POST['confirm_password'] ?? '');
    $dept   = trim($_POST['department'] ?? '');
    $empid  = trim($_POST['employee_id'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');

    if (!$name || !$email || !$pass || !$dept || !$empid) {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $cpass) {
        $error = 'Passwords do not match.';
    } else {
        $st = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $st->bind_param('s', $email); $st->execute(); $st->store_result();
        if ($st->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $st->close();
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st = $conn->prepare("INSERT INTO users(name,email,password,role,department,employee_id,phone,status) VALUES(?,?,?,'officer',?,?,?,'pending')");
            $st->bind_param('ssssss', $name,$email,$hash,$dept,$empid,$phone);
            if ($st->execute()) {
                $success = 'Registration submitted! Your account is pending admin approval. You will receive an email once activated.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $st->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Officer Registration — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g900:#011a18;--g800:#042e2a;--g750:#053d37;--g700:#065449;--g650:#0a6e60;
  --g600:#0d8572;--g500:#109e88;--g450:#14b89f;--g400:#18cfb4;--g350:#3ddbc3;
  --g300:#6ce5d2;--g200:#adf2e8;--g150:#cef7f2;--g100:#e2faf7;--g050:#f0fdfb;
  --white:#ffffff;--accent:#18cfb4;--bg:#f0f9f4;--text:#0d2b1b;--muted:#4a7260;
  --border:#c8e8d8;--radius:14px;
  --shadow-lg:0 20px 56px rgba(13,43,27,0.16),0 4px 16px rgba(13,43,27,0.08);
}
html,body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;min-height:100vh;}
body{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:36px 20px;position:relative;overflow-x:hidden;}
a{text-decoration:none;color:inherit;}
.bg-dots{position:fixed;inset:0;pointer-events:none;background-image:radial-gradient(circle,rgba(24,207,180,0.12) 1px,transparent 1px);background-size:30px 30px;z-index:0;}
.bg-glow{position:fixed;top:-100px;right:-100px;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(24,207,180,0.1),transparent 70%);pointer-events:none;z-index:0;}

.container{position:relative;z-index:1;width:100%;max-width:560px;}

.logo-wrap{text-align:center;margin-bottom:28px;}
.logo-mark{width:58px;height:58px;background:linear-gradient(135deg,var(--g800),var(--g700));border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:1.75rem;margin-bottom:12px;box-shadow:0 8px 24px rgba(6,84,73,0.35),0 0 0 4px rgba(24,207,180,0.15);}
.logo-name{font-size:1.35rem;font-weight:800;color:var(--g800);letter-spacing:-.3px;display:block;}
.logo-sub{font-size:.68rem;color:var(--muted);letter-spacing:1.2px;text-transform:uppercase;margin-top:3px;}

.card{background:var(--white);border:1.5px solid var(--border);border-radius:20px;padding:36px;box-shadow:var(--shadow-lg);animation:card-in .4s cubic-bezier(.4,0,.2,1) both;}
@keyframes card-in{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

.role-badge{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--g800),var(--g700));color:var(--white);padding:8px 16px;border-radius:9px;font-size:.76rem;font-weight:700;letter-spacing:.3px;margin-bottom:22px;}
.dot{width:7px;height:7px;border-radius:50%;background:var(--accent);animation:pulse 2s ease-in-out infinite;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(24,207,180,0.5)}50%{box-shadow:0 0 0 4px rgba(24,207,180,0)}}

.ch-title{font-size:1.15rem;font-weight:800;color:var(--text);letter-spacing:-.3px;margin-bottom:5px;}
.ch-sub{font-size:.78rem;color:var(--muted);margin-bottom:26px;line-height:1.65;}

.toast{display:flex;align-items:flex-start;gap:9px;padding:12px 15px;border-radius:10px;font-size:.8rem;font-weight:500;margin-bottom:18px;border:1.5px solid transparent;line-height:1.6;}
.t-err{background:#fff0f0;border-color:#f5b8b8;color:#a02020;}
.t-ok{background:var(--g100);border-color:var(--g300);color:var(--g700);}

.section-label{font-size:.58rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:var(--accent);margin-bottom:14px;margin-top:22px;display:flex;align-items:center;gap:8px;}
.section-label::after{content:'';flex:1;height:1px;background:var(--border);}

.fg{margin-bottom:14px;}
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;}
.fl{font-size:.6rem;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;display:block;}
.req{color:var(--g600);}
.fi-wrap{position:relative;}
.fi-ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.85rem;pointer-events:none;}
input.fi,select.fi{
  width:100%;padding:11px 14px 11px 38px;
  background:var(--g050);border:1.5px solid var(--border);border-radius:10px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;color:var(--text);
  outline:none;transition:all .17s;-webkit-appearance:none;
}
input.fi:focus,select.fi:focus{border-color:var(--accent);background:var(--white);box-shadow:0 0 0 3px rgba(24,207,180,0.12);}
input.fi::placeholder{color:var(--g300);}
select.fi{cursor:pointer;}

.pw-wrap{position:relative;}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:.82rem;color:var(--muted);padding:4px;}
.pw-toggle:hover{color:var(--g500);}

/* Dept picker */
.dept-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:14px;}
.dept-btn{
  padding:11px 10px;text-align:center;border-radius:10px;
  border:1.5px solid var(--border);background:var(--g050);cursor:pointer;
  transition:all .16s;font-family:inherit;
}
.dept-btn:hover{border-color:var(--accent);background:var(--white);transform:translateY(-1px);}
.dept-btn.sel{border-color:var(--accent);background:var(--g100);box-shadow:inset 0 0 0 1.5px var(--accent),0 3px 10px rgba(24,207,180,0.15);}
.dept-ico{font-size:1.1rem;margin-bottom:3px;}
.dept-lbl{font-size:.65rem;font-weight:700;color:var(--text);}

/* Terms */
.terms-row{display:flex;align-items:flex-start;gap:10px;margin-bottom:20px;padding:12px 14px;background:var(--g050);border:1.5px solid var(--border);border-radius:10px;}
.terms-row input[type=checkbox]{width:16px;height:16px;margin-top:2px;accent-color:var(--accent);flex-shrink:0;cursor:pointer;}
.terms-row label{font-size:.75rem;color:var(--muted);line-height:1.6;cursor:pointer;}
.terms-row label a{color:var(--g500);font-weight:600;}

.btn-submit{
  width:100%;padding:14px;background:linear-gradient(135deg,var(--g800),var(--g600));
  color:var(--white);border:none;border-radius:11px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:700;
  cursor:pointer;transition:all .2s;letter-spacing:-.1px;
  box-shadow:0 4px 18px rgba(6,84,73,0.3);
}
.btn-submit:hover{background:linear-gradient(135deg,var(--g700),var(--g500));transform:translateY(-2px);box-shadow:0 8px 28px rgba(6,84,73,0.38);}
.btn-submit:active{transform:translateY(0);}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none;}

.notice{display:flex;align-items:flex-start;gap:9px;padding:12px 14px;background:var(--g050);border:1.5px solid var(--g200);border-left:4px solid var(--accent);border-radius:10px;margin-top:16px;font-size:.76rem;color:var(--muted);line-height:1.65;}

.link-row{text-align:center;font-size:.78rem;color:var(--muted);margin-top:20px;}
.link-row a{color:var(--g500);font-weight:700;}
.link-row a:hover{color:var(--g700);}

.footer{text-align:center;margin-top:24px;font-size:.65rem;color:var(--muted);}
@media(max-width:520px){.fg2{grid-template-columns:1fr}.dept-grid{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<div class="bg-dots"></div><div class="bg-glow"></div>
<div class="container">
  <div class="logo-wrap">
    <div class="logo-mark">🏛️</div>
    <span class="logo-name">Nagrik Seva</span>
    <div class="logo-sub">Government Grievance Portal</div>
  </div>
  <div class="card">
    <div class="role-badge"><span class="dot"></span> Officer Registration</div>
    <div class="ch-title">Create Officer Account 👮</div>
    <div class="ch-sub">Register to access the officer portal and start resolving citizen complaints in your department.</div>

    <?php if($error): ?><div class="toast t-err">⚠ &nbsp;<?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="toast t-ok">✅ &nbsp;<?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if(!$success): ?>
    <form method="POST" id="reg-form">
      <input type="hidden" name="department" id="dept-val">

      <div class="section-label">Personal Information</div>
      <div class="fg2">
        <div>
          <label class="fl">Full Name <span class="req">*</span></label>
          <div class="fi-wrap"><span class="fi-ico">👤</span><input class="fi" type="text" name="name" placeholder="Suresh Kamat" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></div>
        </div>
        <div>
          <label class="fl">Phone Number</label>
          <div class="fi-wrap"><span class="fi-ico">📞</span><input class="fi" type="tel" name="phone" placeholder="+91 98XXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"></div>
        </div>
      </div>
      <div class="fg2">
        <div>
          <label class="fl">Official Email <span class="req">*</span></label>
          <div class="fi-wrap"><span class="fi-ico">✉️</span><input class="fi" type="email" name="email" placeholder="officer@goa.gov.in" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
        </div>
        <div>
          <label class="fl">Employee ID <span class="req">*</span></label>
          <div class="fi-wrap"><span class="fi-ico">🪪</span><input class="fi" type="text" name="employee_id" placeholder="GOA-2024-XXXX" required value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>"></div>
        </div>
      </div>

      <div class="section-label">Department</div>
      <div class="dept-grid">
        <button type="button" class="dept-btn" onclick="selDept('Road & PWD',this)"><div class="dept-ico">🚧</div><div class="dept-lbl">Road & PWD</div></button>
        <button type="button" class="dept-btn" onclick="selDept('Water Supply',this)"><div class="dept-ico">💧</div><div class="dept-lbl">Water Supply</div></button>
        <button type="button" class="dept-btn" onclick="selDept('Electricity',this)"><div class="dept-ico">⚡</div><div class="dept-lbl">Electricity</div></button>
        <button type="button" class="dept-btn" onclick="selDept('Sanitation & Health',this)"><div class="dept-ico">🧹</div><div class="dept-lbl">Sanitation</div></button>
        <button type="button" class="dept-btn" onclick="selDept('Property & Revenue',this)"><div class="dept-ico">🏛️</div><div class="dept-lbl">Property</div></button>
        <button type="button" class="dept-btn" onclick="selDept('General Administration',this)"><div class="dept-ico">📋</div><div class="dept-lbl">General Admin</div></button>
      </div>

      <div class="section-label">Security</div>
      <div class="fg2">
        <div>
          <label class="fl">Password <span class="req">*</span></label>
          <div class="fi-wrap pw-wrap"><span class="fi-ico">🔑</span><input class="fi" type="password" name="password" id="pw1" placeholder="Min 8 characters" required><button type="button" class="pw-toggle" onclick="togglePw('pw1','ic1')" id="ic1">👁️</button></div>
        </div>
        <div>
          <label class="fl">Confirm Password <span class="req">*</span></label>
          <div class="fi-wrap pw-wrap"><span class="fi-ico">🔒</span><input class="fi" type="password" name="confirm_password" id="pw2" placeholder="Repeat password" required><button type="button" class="pw-toggle" onclick="togglePw('pw2','ic2')" id="ic2">👁️</button></div>
        </div>
      </div>

      <div class="terms-row">
        <input type="checkbox" id="terms" required>
        <label for="terms">I confirm that I am a verified government employee and agree to the <a href="#">Officer Code of Conduct</a> and <a href="#">Data Privacy Policy</a> of Nagrik Seva.</label>
      </div>

      <button type="submit" class="btn-submit" id="sub-btn">Register as Officer →</button>

      <div class="notice">ℹ️ &nbsp;Your account will be reviewed and approved by an administrator before you can log in. This typically takes 1–2 business days.</div>
    </form>
    <?php endif; ?>
    <div class="link-row">Already have an account? <a href="officer_login.php">Sign In →</a> &nbsp;·&nbsp; <a href="citizen_login.php">Citizen Portal</a></div>
  </div>
  <div class="footer">© <?= date('Y') ?> Nagrik Seva · Government of Goa · All rights reserved</div>
</div>
<script>
function togglePw(id,ico){const i=document.getElementById(id),ic=document.getElementById(ico);i.type=i.type==='password'?'text':'password';ic.textContent=i.type==='password'?'👁️':'🙈';}
function selDept(v,btn){document.querySelectorAll('.dept-btn').forEach(b=>b.classList.remove('sel'));btn.classList.add('sel');document.getElementById('dept-val').value=v;}
document.getElementById('reg-form')?.addEventListener('submit',function(e){
  if(!document.getElementById('dept-val').value){e.preventDefault();alert('Please select your department.');}
  const p1=document.getElementById('pw1').value,p2=document.getElementById('pw2').value;
  if(p1&&p2&&p1!==p2){e.preventDefault();alert('Passwords do not match.');}
});
// Pre-select if POST failed
<?php if(!empty($_POST['department'])): ?>
document.querySelectorAll('.dept-btn').forEach(b=>{if(b.querySelector('.dept-lbl')?.textContent==='<?= addslashes($_POST['department']) ?>'){b.classList.add('sel');document.getElementById('dept-val').value='<?= addslashes($_POST['department']) ?>';}});
<?php endif; ?>
</script>
</body>
</html>
