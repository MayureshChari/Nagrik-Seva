<?php
session_start();
require_once 'config.php';

if ((empty($_SESSION['user_id']) && !isset($_SESSION['is_demo'])) || $_SESSION['role'] !== 'regulator') {
    header('Location: regulator_login.php'); exit;
}

$uid      = (int)$_SESSION['user_id'];
$name     = $_SESSION['name'] ?? 'Adv. Meera Kamat';
$dept     = $_SESSION['dept'] ?? 'Goa Grievance Authority';
$initials = strtoupper(substr($name, 0, 1));
$first    = explode(' ', $name)[0];
$is_demo_session = !empty($_SESSION['is_demo']);
$toast_ok = $toast_err = '';

// Load profile from DB
$profile = [
    'name'        => $name,
    'email'       => $_SESSION['email'] ?? 'regulator@nagrikseva.gov.in',
    'dept'        => $dept,
    'phone'       => $_SESSION['phone'] ?? '+91 98230 00000',
    'designation' => $_SESSION['designation'] ?? 'Senior Grievance Regulator',
    'address'     => $_SESSION['address'] ?? 'Goa Grievance Authority, Panaji, Goa – 403001',
    'joined'      => $_SESSION['joined'] ?? '2022-04-01',
    'last_login'  => date('d M Y, H:i'),
    'id_number'   => 'REG-' . str_pad($uid ?: 1, 4, '0', STR_PAD_LEFT),
    'jurisdiction'=> 'State of Goa',
    'authority'   => 'Goa Grievance Redressal Act, 2024',
];

if ($uid > 0) {
    $r = $conn->query("SELECT * FROM users WHERE id=$uid AND role='regulator' LIMIT 1");
    if ($r && $row=$r->fetch_assoc()) {
        $profile['name']  = $row['name']  ?: $profile['name'];
        $profile['email'] = $row['email'] ?: $profile['email'];
        $profile['phone'] = $row['phone'] ?: $profile['phone'];
        $profile['dept']  = $row['dept']  ?: $profile['dept'];
    }
}

// POST — update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($is_demo_session) {
        $toast_ok = 'Demo: Profile updated. (No DB write in demo mode)';
    } elseif ($uid > 0) {
        if ($action === 'update_profile') {
            $new_name  = trim($_POST['name']  ?? '');
            $new_phone = trim($_POST['phone'] ?? '');
            $new_dept  = trim($_POST['dept']  ?? '');
            if ($new_name) {
                $conn->query("UPDATE users SET name='".addslashes($new_name)."', phone='".addslashes($new_phone)."', dept='".addslashes($new_dept)."' WHERE id=$uid");
                $_SESSION['name'] = $new_name;
                $toast_ok = 'Profile updated successfully.';
            }
        } elseif ($action === 'change_password') {
            $cur = trim($_POST['current_password'] ?? '');
            $new = trim($_POST['new_password']     ?? '');
            $cnf = trim($_POST['confirm_password'] ?? '');
            if ($new !== $cnf) { $toast_err = 'New passwords do not match.'; }
            elseif (strlen($new) < 8) { $toast_err = 'Password must be at least 8 characters.'; }
            else {
                $r = $conn->query("SELECT password FROM users WHERE id=$uid");
                if ($r && $row=$r->fetch_assoc()) {
                    if (password_verify($cur, $row['password'])) {
                        $hash = password_hash($new, PASSWORD_DEFAULT);
                        $conn->query("UPDATE users SET password='$hash' WHERE id=$uid");
                        $toast_ok = 'Password changed successfully.';
                    } else { $toast_err = 'Current password is incorrect.'; }
                }
            }
        }
    }
    if ($toast_ok || $toast_err) {
        header('Location: regulator_profile.php?msg='.urlencode($toast_ok?:$toast_err).'&type='.($toast_ok?'ok':'err')); exit;
    }
}
if (isset($_GET['msg'])) {
    if (($_GET['type']??'') === 'ok') $toast_ok = $_GET['msg'];
    else $toast_err = $_GET['msg'];
}

// Stats for regulator
$reg_stats = ['total_actions'=>0,'notices_sent'=>0,'legal_issued'=>0,'escalations'=>0];
if ($uid > 0) {
    $r=$conn->query("SELECT COUNT(*) as c FROM notifications WHERE type IN ('regulator_notice') LIMIT 1");
    if($r) $reg_stats['notices_sent']=(int)$r->fetch_assoc()['c'];
    $r=$conn->query("SELECT COUNT(*) as c FROM notifications WHERE type='legal_notice' LIMIT 1");
    if($r) $reg_stats['legal_issued']=(int)$r->fetch_assoc()['c'];
    $r=$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='escalated'");
    if($r) $reg_stats['escalations']=(int)$r->fetch_assoc()['c'];
}
if ($reg_stats['total_actions'] === 0) {
    $reg_stats = ['total_actions'=>42,'notices_sent'=>18,'legal_issued'=>7,'escalations'=>12];
}

$hour=(int)date('H'); $greeting=$hour<12?'Good morning':($hour<18?'Good afternoon':'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--g900:#011a18;--g800:#042e2a;--g750:#053d37;--g700:#065449;--g650:#0a6e60;--g600:#0d8572;--g500:#109e88;--g450:#14b89f;--g400:#18cfb4;--g350:#3ddbc3;--g300:#6ce5d2;--g200:#adf2e8;--g100:#e2faf7;--g050:#f0fdfb;--white:#ffffff;--accent:#18cfb4;--bg:#f0f9f4;--card:#ffffff;--text:#0d2b1b;--muted:#4a7260;--border:#c8e8d8;--radius:14px;--shadow:0 2px 12px rgba(13,43,27,0.07);--shadow-md:0 8px 28px rgba(13,43,27,0.11);}
html,body{height:100%;overflow:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;-webkit-font-smoothing:antialiased;}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--g300);border-radius:4px}
.sidebar{width:220px;min-width:220px;height:100vh;background:var(--g800);display:flex;flex-direction:column;z-index:51;overflow-y:auto;position:relative;}
.sidebar::after{content:'';position:absolute;right:0;top:0;bottom:0;width:1px;background:linear-gradient(180deg,transparent,var(--g500) 20%,var(--g400) 60%,var(--g500) 80%,transparent);}
.sb-logo{padding:24px 20px 22px;display:flex;align-items:center;gap:13px;}
.sb-mark{width:42px;height:42px;background:linear-gradient(135deg,var(--g400),var(--g350));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.sb-name{font-size:.9rem;font-weight:700;color:var(--white);line-height:1.1}
.sb-sub{font-size:.58rem;color:var(--g300);text-transform:uppercase;letter-spacing:1.2px;margin-top:3px;font-weight:500}
.sb-divider{height:1px;background:linear-gradient(90deg,transparent,var(--g650),transparent);margin:4px 0 8px;}
.sb-sec{padding:14px 20px 5px;font-size:.54rem;font-weight:700;letter-spacing:2.2px;text-transform:uppercase;color:var(--g450);}
.nav-a{display:flex;align-items:center;gap:10px;padding:9px 16px;margin:1px 10px;border-radius:10px;font-size:.79rem;font-weight:450;color:var(--g200);transition:all .18s;border:1px solid transparent;position:relative;}
.nav-a:hover{background:rgba(255,255,255,0.06);color:var(--white);transform:translateX(2px)}
.nav-a.on{background:linear-gradient(135deg,rgba(24,207,180,0.2),rgba(24,207,180,0.08));color:var(--white);font-weight:600;border-color:rgba(24,207,180,0.4);}
.nav-a.on::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:60%;background:var(--accent);border-radius:0 3px 3px 0;}
.nav-ico{font-size:.85rem;width:20px;text-align:center;flex-shrink:0}
.sb-foot{margin-top:auto;padding:12px 10px;border-top:1px solid rgba(255,255,255,0.06)}
.u-card{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:11px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);}
.u-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));border:2px solid rgba(24,207,180,0.4);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--white);}
.u-name{font-size:.77rem;font-weight:600;color:var(--white)}
.u-role{font-size:.58rem;color:var(--g300);margin-top:1px;text-transform:uppercase}
.u-logout{margin-left:auto;background:none;border:none;color:var(--g300);cursor:pointer;padding:5px;border-radius:7px;transition:all .15s;}
.u-logout:hover{color:var(--white);background:rgba(255,255,255,0.1)}
.main{flex:1;height:100vh;overflow-y:auto;display:flex;flex-direction:column;}
.topbar{background:var(--g750);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;flex-shrink:0;border-bottom:1px solid rgba(24,207,180,0.25);}
.tb-left{display:flex;align-items:center;gap:14px;flex:1}
.tb-greeting{font-size:.88rem;font-weight:500;color:var(--white);}
.tb-sep{width:1px;height:16px;background:rgba(255,255,255,0.15)}
.tb-date{font-size:.71rem;color:var(--g300);}
.tb-center{position:absolute;left:50%;transform:translateX(-50%);text-align:center;pointer-events:none;white-space:nowrap;}
.tb-brand{font-size:1rem;font-weight:700;color:var(--white);}
.tb-tagline{font-size:.57rem;color:var(--g300);letter-spacing:.6px;text-transform:uppercase;margin-top:2px}
.tb-right{display:flex;align-items:center;gap:10px;flex:1;justify-content:flex-end}
.tb-badge{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:rgba(24,207,180,.15);border:1px solid rgba(24,207,180,.3);font-size:.74rem;font-weight:600;color:var(--g200);}
.tb-back{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);font-size:.74rem;font-weight:600;color:var(--g200);transition:all .15s;}
.tb-back:hover{background:rgba(255,255,255,.14);color:var(--white)}
.body{padding:22px 28px;flex:1;}
.toast{display:flex;align-items:center;gap:9px;padding:12px 16px;border-radius:10px;font-size:.8rem;margin-bottom:18px;border:1px solid transparent;font-weight:500;}
.t-ok{background:var(--g100);border-color:var(--g300);color:var(--g700)}
.t-err{background:#fff0f0;border-color:#f5b8b8;color:#a02020}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.ph-title{font-size:1.35rem;font-weight:800;color:var(--text);letter-spacing:-.4px;}

/* PROFILE LAYOUT */
.profile-grid{display:grid;grid-template-columns:320px 1fr;gap:18px;}

/* LEFT CARD */
.profile-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.profile-banner{background:linear-gradient(135deg,var(--g700),var(--g500));padding:28px 24px;text-align:center;position:relative;}
.profile-banner::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 70% 30%,rgba(24,207,180,0.15),transparent 60%);pointer-events:none;}
.p-avatar{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g300));border:3px solid rgba(255,255,255,0.4);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:700;color:var(--white);margin:0 auto 12px;box-shadow:0 4px 18px rgba(0,0,0,.2);}
.p-name{font-size:1.05rem;font-weight:800;color:var(--white);margin-bottom:4px;}
.p-role-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:20px;font-size:.68rem;font-weight:700;color:rgba(255,255,255,.9);letter-spacing:.5px;}
.p-id{font-size:.62rem;color:rgba(255,255,255,.6);margin-top:8px;font-family:'DM Mono',monospace;}

.p-info-list{padding:18px 20px;}
.p-info-row{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);}
.p-info-row:last-child{border-bottom:none}
.p-info-ico{width:32px;height:32px;border-radius:8px;background:var(--g100);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.p-info-lbl{font-size:.6rem;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);font-weight:600;margin-bottom:2px;}
.p-info-val{font-size:.8rem;color:var(--text);font-weight:500;}

/* STATS ROW */
.p-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-top:1.5px solid var(--border);}
.p-stat{text-align:center;padding:12px 8px;border-right:1px solid var(--border);}
.p-stat:last-child{border-right:none}
.p-stat-num{font-size:1.3rem;font-weight:800;color:var(--text);line-height:1;}
.p-stat-lbl{font-size:.58rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-top:3px;}

/* RIGHT FORMS */
.form-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:16px;}
.form-card:last-child{margin-bottom:0}
.ch{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1.5px solid var(--border);background:linear-gradient(180deg,rgba(244,253,248,.8),rgba(255,255,255,0));}
.ch-title{font-size:.88rem;font-weight:700;color:var(--text);}
.ch-sub{font-size:.63rem;color:var(--muted);margin-top:2px;}
.form-body{padding:20px}
.fg{margin-bottom:14px}
.fl{font-size:.6rem;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;display:block}
input.fi,textarea.fi,select.fi{width:100%;padding:10px 13px;background:var(--white);border:1.5px solid var(--border);border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.84rem;color:var(--text);outline:none;transition:all .17s;}
input.fi:focus,textarea.fi:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(24,207,180,.12)}
input.fi[disabled]{background:var(--g050);color:var(--muted);cursor:not-allowed;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.btn-submit{padding:11px 24px;background:linear-gradient(135deg,var(--g700),var(--g500));color:var(--white);border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.84rem;font-weight:700;cursor:pointer;transition:all .18s;}
.btn-submit:hover{transform:translateY(-1px)}
.btn-danger{background:linear-gradient(135deg,#7f1d1d,#dc2626)!important}
.info-row{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--g050);border:1.5px solid var(--border);border-radius:9px;font-size:.76rem;color:var(--text);margin-bottom:14px;}

/* AUTHORITY CARD */
.authority-card{background:linear-gradient(135deg,var(--g750),var(--g700));border-radius:var(--radius);padding:16px 20px;margin-bottom:16px;border:1.5px solid var(--g600);}
.auth-title{font-size:.7rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--g300);margin-bottom:10px;}
.auth-row{display:flex;gap:10px;margin-bottom:8px;align-items:flex-start;}
.auth-lbl{font-size:.63rem;color:var(--g200);width:100px;flex-shrink:0;}
.auth-val{font-size:.72rem;color:var(--white);font-weight:500;}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-logo"><div class="sb-mark">🏛️</div><div><div class="sb-name">Nagrik Seva</div><div class="sb-sub">Regulator Portal</div></div></div>
  <div class="sb-divider"></div>
  <div class="sb-sec">Oversight</div>
  <a class="nav-a" href="regulator_dashboard.php"><span class="nav-ico">⊞</span> Dashboard</a>
  <a class="nav-a" href="regulator_officers.php"><span class="nav-ico">👮</span> All Officers</a>
  <a class="nav-a" href="regulator_complaints.php"><span class="nav-ico">📋</span> All Complaints</a>
  <a class="nav-a" href="regulator_reports.php"><span class="nav-ico">📊</span> Reports</a>
  <a class="nav-a" href="track.php"><span class="nav-ico">⊙</span> Track Complaint</a>
  <a class="nav-a" href="public_board.php"><span class="nav-ico">◎</span> Public Board</a>
  <div class="sb-sec">Account</div>
  <a class="nav-a on" href="regulator_profile.php"><span class="nav-ico">○</span> My Profile</a>
  <div class="sb-sec">Info</div>
  <a class="nav-a" href="about.php"><span class="nav-ico">ℹ</span> About</a>
  <a class="nav-a" href="contact.php"><span class="nav-ico">✉</span> Contact</a>
  <div class="sb-foot">
    <div class="u-card">
      <div class="u-av"><?= $initials ?></div>
      <div><div class="u-name"><?= htmlspecialchars($name) ?></div><div class="u-role">Regulator</div></div>
      <a href="logout.php" class="u-logout" title="Sign out"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="tb-left"><div class="tb-greeting"><?= $greeting ?>, <?= htmlspecialchars($first) ?> 👋</div><div class="tb-sep"></div><div class="tb-date"><?= date('D, d M Y') ?></div></div>
    <div class="tb-center"><div class="tb-brand">🏛️ Nagrik Seva Portal</div><div class="tb-tagline">Regulator Oversight Centre</div></div>
    <div class="tb-right"><a href="regulator_dashboard.php" class="tb-back">← Dashboard</a><div class="tb-badge">⚖️ <?= htmlspecialchars($dept) ?></div></div>
  </div>

  <div class="body">
    <?php if($toast_ok): ?><div class="toast t-ok">✓ &nbsp;<?= htmlspecialchars($toast_ok) ?></div><?php endif; ?>
    <?php if($toast_err): ?><div class="toast t-err">⚠ &nbsp;<?= htmlspecialchars($toast_err) ?></div><?php endif; ?>

    <div class="page-header"><div><div class="ph-title">👤 My Profile</div></div></div>

    <div class="profile-grid">

      <!-- LEFT: Profile card -->
      <div>
        <div class="profile-card">
          <div class="profile-banner">
            <div class="p-avatar"><?= $initials ?></div>
            <div class="p-name"><?= htmlspecialchars($profile['name']) ?></div>
            <div class="p-role-badge">⚖️ Regulator</div>
            <div class="p-id"><?= htmlspecialchars($profile['id_number']) ?></div>
          </div>
          <div class="p-info-list">
            <div class="p-info-row">
              <div class="p-info-ico">✉️</div>
              <div><div class="p-info-lbl">Email</div><div class="p-info-val"><?= htmlspecialchars($profile['email']) ?></div></div>
            </div>
            <div class="p-info-row">
              <div class="p-info-ico">📞</div>
              <div><div class="p-info-lbl">Phone</div><div class="p-info-val"><?= htmlspecialchars($profile['phone']) ?></div></div>
            </div>
            <div class="p-info-row">
              <div class="p-info-ico">🏢</div>
              <div><div class="p-info-lbl">Department</div><div class="p-info-val"><?= htmlspecialchars($profile['dept']) ?></div></div>
            </div>
            <div class="p-info-row">
              <div class="p-info-ico">📅</div>
              <div><div class="p-info-lbl">Joined</div><div class="p-info-val"><?= date('d M Y', strtotime($profile['joined'])) ?></div></div>
            </div>
            <div class="p-info-row">
              <div class="p-info-ico">🔐</div>
              <div><div class="p-info-lbl">Last Login</div><div class="p-info-val"><?= htmlspecialchars($profile['last_login']) ?></div></div>
            </div>
            <div class="p-info-row">
              <div class="p-info-ico">📍</div>
              <div><div class="p-info-lbl">Jurisdiction</div><div class="p-info-val"><?= htmlspecialchars($profile['jurisdiction']) ?></div></div>
            </div>
          </div>
          <div class="p-stats">
            <div class="p-stat"><div class="p-stat-num"><?= $reg_stats['notices_sent'] ?></div><div class="p-stat-lbl">Notices</div></div>
            <div class="p-stat"><div class="p-stat-num"><?= $reg_stats['legal_issued'] ?></div><div class="p-stat-lbl">Legal</div></div>
            <div class="p-stat"><div class="p-stat-num"><?= $reg_stats['escalations'] ?></div><div class="p-stat-lbl">Escalated</div></div>
            <div class="p-stat"><div class="p-stat-num"><?= $reg_stats['total_actions'] ?></div><div class="p-stat-lbl">Actions</div></div>
          </div>
        </div>

        <!-- Authority Info -->
        <div class="authority-card" style="margin-top:16px">
          <div class="auth-title">⚖️ Regulatory Authority</div>
          <div class="auth-row"><div class="auth-lbl">Authority under:</div><div class="auth-val"><?= htmlspecialchars($profile['authority']) ?></div></div>
          <div class="auth-row"><div class="auth-lbl">Designation:</div><div class="auth-val"><?= htmlspecialchars($profile['designation']) ?></div></div>
          <div class="auth-row"><div class="auth-lbl">Office:</div><div class="auth-val"><?= htmlspecialchars($profile['address']) ?></div></div>
          <div class="auth-row"><div class="auth-lbl">Powers:</div><div class="auth-val">Investigate, Issue notices, Terminate officers, Escalate complaints</div></div>
        </div>
      </div>

      <!-- RIGHT: Forms -->
      <div>
        <!-- Edit Profile -->
        <div class="form-card">
          <div class="ch"><div><div class="ch-title">✏️ Edit Profile</div><div class="ch-sub">Update your personal information</div></div></div>
          <div class="form-body">
            <?php if($is_demo_session): ?>
            <div class="info-row">ℹ️ You are in demo mode. Changes will not be saved.</div>
            <?php endif; ?>
            <form method="POST">
              <input type="hidden" name="action" value="update_profile">
              <div class="form-grid">
                <div class="fg"><label class="fl">Full Name</label><input type="text" name="name" class="fi" value="<?= htmlspecialchars($profile['name']) ?>" required></div>
                <div class="fg"><label class="fl">Email Address</label><input type="email" class="fi" value="<?= htmlspecialchars($profile['email']) ?>" disabled></div>
                <div class="fg"><label class="fl">Phone Number</label><input type="text" name="phone" class="fi" value="<?= htmlspecialchars($profile['phone']) ?>"></div>
                <div class="fg"><label class="fl">Department</label><input type="text" name="dept" class="fi" value="<?= htmlspecialchars($profile['dept']) ?>"></div>
              </div>
              <div class="fg"><label class="fl">Office Address</label><input type="text" name="address" class="fi" value="<?= htmlspecialchars($profile['address']) ?>"></div>
              <button type="submit" class="btn-submit">💾 Save Changes</button>
            </form>
          </div>
        </div>

        <!-- Change Password -->
        <div class="form-card">
          <div class="ch"><div><div class="ch-title">🔒 Change Password</div><div class="ch-sub">Update your account security</div></div></div>
          <div class="form-body">
            <form method="POST">
              <input type="hidden" name="action" value="change_password">
              <div class="fg"><label class="fl">Current Password</label><input type="password" name="current_password" class="fi" placeholder="Enter current password" required></div>
              <div class="form-grid">
                <div class="fg"><label class="fl">New Password</label><input type="password" name="new_password" class="fi" placeholder="Min. 8 characters" required minlength="8"></div>
                <div class="fg"><label class="fl">Confirm New Password</label><input type="password" name="confirm_password" class="fi" placeholder="Repeat new password" required></div>
              </div>
              <button type="submit" class="btn-submit">🔒 Change Password</button>
            </form>
          </div>
        </div>

        <!-- Session / Security -->
        <div class="form-card">
          <div class="ch"><div><div class="ch-title">🔐 Session & Security</div><div class="ch-sub">Account access details</div></div></div>
          <div class="form-body">
            <div class="info-row">🟢 You are currently logged in as <strong><?= htmlspecialchars($profile['name']) ?></strong> — Regulator</div>
            <div style="display:flex;gap:10px">
              <a href="logout.php" style="padding:10px 20px;background:#fff0f0;border:1.5px solid #f5b8b8;border-radius:10px;font-size:.82rem;font-weight:700;color:#a02020;transition:all .15s" onmouseover="this.style.background='#ffe0e0'" onmouseout="this.style.background='#fff0f0'">🚪 Sign Out</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
setTimeout(()=>{const t=document.querySelector('.toast');if(t){t.style.transition='opacity .5s';t.style.opacity='0';setTimeout(()=>t.remove(),500);}},4000);
</script>
</body>
</html>
