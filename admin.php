<?php
session_start();
require_once 'config.php';

$admin_password = 'admin123';
$page_msg = '';

// ── Handle lock panel ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout_admin'])) {
    unset($_SESSION['admin_unlocked']);
    header('Location: admin.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pw'])) {
    if ($_POST['admin_pw'] === $admin_password) {
        $_SESSION['admin_unlocked'] = true;
    } else {
        $page_msg = 'error:Incorrect admin password.';
    }
}

// ── Handle user creation ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user']) && !empty($_SESSION['admin_unlocked'])) {
    $name  = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $dept  = trim($_POST['department'] ?? '');
    $role  = $_POST['role'] ?? '';
    $pw    = $_POST['password'] ?? '';
    $cpw   = $_POST['confirm_password'] ?? '';

    if (!$name || !$email || !$role || !$pw) {
        $page_msg = 'error:Please fill in all required fields.';
    } elseif (!in_array($role, ['officer', 'regulator'])) {
        $page_msg = 'error:Invalid role selected.';
    } elseif ($pw !== $cpw) {
        $page_msg = 'error:Passwords do not match.';
    } elseif (strlen($pw) < 8) {
        $page_msg = 'error:Password must be at least 8 characters.';
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $chk->bind_param('s', $email); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $page_msg = 'error:Email already registered.';
        } else {
            // Detect which optional columns actually exist in users table
            $_existing_cols = [];
            $_cr = $conn->query("SHOW COLUMNS FROM users");
            if ($_cr) while ($_col = $_cr->fetch_assoc()) $_existing_cols[] = $_col['Field'];

            // Always-present columns
            $ins_cols  = ['name', 'email', 'password_hash', 'role'];
            $ins_types = 'ssss';
            $hash      = password_hash($pw, PASSWORD_DEFAULT);
            $ins_vals  = [&$name, &$email, &$hash, &$role];

            // Optional columns — only add if they exist
            if (in_array('phone', $_existing_cols) && $phone !== '') {
                $ins_cols[]  = 'phone';
                $ins_types  .= 's';
                $ins_vals[]  = &$phone;
            }
            if (in_array('department', $_existing_cols) && $dept !== '') {
                $ins_cols[]  = 'department';
                $ins_types  .= 's';
                $ins_vals[]  = &$dept;
            }
            if (in_array('is_active', $_existing_cols)) {
                $ins_cols[] = 'is_active';
                $ins_types .= 'i';
                $_active_val = 1;
                $ins_vals[]  = &$_active_val;
            }
            if (in_array('password', $_existing_cols) && !in_array('password_hash', $_existing_cols)) {
                // Some installs use 'password' column instead of 'password_hash'
                // Replace the password_hash reference with password
                $key = array_search('password_hash', $ins_cols);
                if ($key !== false) $ins_cols[$key] = 'password';
            }

            $col_sql = implode(',', $ins_cols);
            $ph_sql  = implode(',', array_fill(0, count($ins_cols), '?'));
            $ins     = $conn->prepare("INSERT INTO users($col_sql) VALUES($ph_sql)");
            $ins->bind_param($ins_types, ...$ins_vals);

            if ($ins->execute()) {
                $page_msg = 'success:Account created! ' . ucfirst($role) . ' <strong>' . htmlspecialchars($name) . '</strong> can now log in with <strong>' . htmlspecialchars($email) . '</strong>.';
            } else {
                $page_msg = 'error:Database error: ' . htmlspecialchars($conn->error);
            }
            $ins->close();
        }
        $chk->close();
    }
}

$unlocked = !empty($_SESSION['admin_unlocked']);
$msg_type = $msg_text = '';
if ($page_msg) { [$msg_type, $msg_text] = explode(':', $page_msg, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Panel — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --a:#042e2a;--a2:#18cfb4;--a3:rgba(24,207,180,.12);
  --bg:#f0fdfb;--white:#ffffff;
  --text:#042e2a;--muted:#4a7260;--muted2:#2a7d4f;
  --border:rgba(4,46,42,.14);--border2:rgba(4,46,42,.28);
  --shadow:0 1px 4px rgba(4,46,42,.1),0 4px 14px rgba(4,46,42,.08);
  --red:#dc2626;--green:#059669;--amber:#d97706;
}
html,body{min-height:100%;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}

/* BG */
.bg-canvas{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.mesh{position:absolute;border-radius:50%;filter:blur(70px);animation:drift 18s ease-in-out infinite alternate}
.m1{width:600px;height:600px;background:radial-gradient(circle,rgba(24,207,180,.08),transparent);top:-120px;left:-80px}
.m2{width:400px;height:400px;background:radial-gradient(circle,rgba(109,229,210,.1),transparent);bottom:-80px;right:-60px;animation-delay:-7s}
@keyframes drift{0%{transform:translate(0,0)}100%{transform:translate(20px,14px)}}
.dots{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:radial-gradient(rgba(4,46,42,.04) 1px,transparent 1px);background-size:28px 28px}

/* GATE SCREEN */
.gate-wrap{position:relative;z-index:1;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.gate-card{width:100%;max-width:380px;animation:fadeUp .45s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.gate-ico{width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,var(--a),var(--a2));display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 20px;box-shadow:0 6px 24px rgba(4,46,42,.25)}
.gate-title{font-size:1.7rem;font-weight:900;letter-spacing:-1px;text-align:center;margin-bottom:6px}
.gate-sub{font-size:.82rem;color:var(--muted2);text-align:center;margin-bottom:28px;line-height:1.6}
.pw-wrap{position:relative;margin-bottom:14px}
.pw-input{width:100%;padding:13px 46px 13px 16px;background:var(--white);border:1.5px solid var(--border);border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.92rem;color:var(--text);outline:none;transition:all .25s;box-shadow:0 1px 4px rgba(0,0,0,.04);letter-spacing:2px}
.pw-input::placeholder{letter-spacing:0;color:var(--muted)}
.pw-input:focus{border-color:var(--a2);box-shadow:0 0 0 3px rgba(24,207,180,.12)}
.pw-eye{position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.9rem;padding:0}
.pw-eye:hover{color:var(--a)}
.gate-btn{width:100%;padding:13px;background:linear-gradient(135deg,var(--a),var(--a2));color:#f0f4ff;border:none;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:.9rem;cursor:pointer;transition:all .25s;box-shadow:0 4px 20px var(--shadow)}
.gate-btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(16,158,136,.3)}
.hint{font-size:.69rem;color:var(--muted);text-align:center;margin-top:12px}

/* ADMIN PANEL */
.admin-wrap{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column}

/* TOP BAR */
.topbar{height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 36px;background:rgba(255,255,255,.9);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);box-shadow:0 1px 0 rgba(255,255,255,.6)}
.tb-logo{display:flex;align-items:center;gap:10px}
.tb-mark{width:36px;height:36px;background:linear-gradient(135deg,var(--a),var(--a2));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;box-shadow:0 3px 10px rgba(4,46,42,.2)}
.tb-name{font-size:.88rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase}
.tb-badge{padding:4px 12px;border-radius:20px;background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2);font-size:.62rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--red)}
.tb-right{display:flex;align-items:center;gap:10px}
.tb-logout{font-size:.75rem;color:var(--muted);font-weight:600;padding:6px 13px;border-radius:8px;border:1.5px solid var(--border);background:var(--white);cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .2s}
.tb-logout:hover{border-color:var(--red);color:var(--red)}

/* MAIN CONTENT */
.admin-main{flex:1;display:grid;grid-template-columns:300px 1fr;min-height:calc(100vh - 60px)}

/* SIDEBAR */
.sidebar{background:linear-gradient(180deg,#042e2a,#065449);padding:28px 20px;border-right:1px solid rgba(255,255,255,.08)}
.sb-section{margin-bottom:28px}
.sb-label{font-size:.58rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:10px;padding:0 8px}
.sb-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;font-size:.8rem;font-weight:600;color:rgba(255,255,255,.7);cursor:pointer;transition:all .2s;border:1px solid transparent;margin-bottom:3px}
.sb-item:hover,.sb-item.on{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.15);color:#fff}
.sb-item.on{background:rgba(24,207,180,.15);border-color:rgba(24,207,180,.25);color:#fff}
.sb-ico{width:30px;height:30px;border-radius:7px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.sb-divider{height:1px;background:rgba(255,255,255,.08);margin:12px 0}
.sb-stats{padding:14px;background:rgba(255,255,255,.06);border-radius:10px;border:1px solid rgba(255,255,255,.08)}
.sb-stat-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.sb-stat-row:last-child{margin-bottom:0}
.sb-stat-lbl{font-size:.67rem;color:rgba(255,255,255,.45)}
.sb-stat-val{font-size:.8rem;font-weight:700;color:rgba(255,255,255,.85)}

/* FORM AREA */
.form-area{padding:36px 40px;overflow-y:auto}
.page-header{margin-bottom:32px}
.page-eyebrow{font-size:.6rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--a2);margin-bottom:6px}
.page-title{font-size:1.7rem;font-weight:900;letter-spacing:-1px;margin-bottom:6px}
.page-desc{font-size:.82rem;color:var(--muted2);line-height:1.65;max-width:520px}

/* FORM CARD */
.form-card{background:var(--white);border-radius:16px;border:1px solid var(--border);box-shadow:0 2px 16px rgba(4,46,42,.07);padding:32px;max-width:580px}
.form-section-title{display:flex;align-items:center;gap:8px;font-size:.65rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--a);margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border)}
.field-row{display:flex;gap:14px}
.fg{flex:1;margin-bottom:16px}
.fl{display:block;font-size:.62rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--muted2);margin-bottom:6px}
.fi{width:100%;padding:11px 14px;background:var(--white);border:1.5px solid var(--border);border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.87rem;color:var(--text);outline:none;transition:all .25s;box-shadow:0 1px 3px rgba(0,0,0,.03)}
.fi::placeholder{color:rgba(74,114,96,.4)}
.fi:hover{border-color:var(--border2)}
.fi:focus{border-color:var(--a2);box-shadow:0 0 0 3px rgba(24,207,180,.1)}
select.fi{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%234a7260' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px}

/* Role selector */
.role-select-group{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.role-opt{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:10px;border:1.5px solid var(--border);background:var(--white);cursor:pointer;transition:all .22s}
.role-opt:hover{border-color:var(--border2)}
.role-opt.selected{border-color:var(--a2);background:rgba(24,207,180,.06)}
.role-opt input[type=radio]{display:none}
.role-ico{width:36px;height:36px;border-radius:9px;background:rgba(4,46,42,.06);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.role-opt.selected .role-ico{background:rgba(24,207,180,.15)}
.role-name{font-size:.82rem;font-weight:700;color:var(--text)}
.role-desc{font-size:.64rem;color:var(--muted);margin-top:2px}

/* Password field */
.pw-field-wrap{position:relative}
.pw-field-wrap .fi{padding-right:42px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.85rem;padding:0;transition:color .2s}
.pw-toggle:hover{color:var(--a)}

/* Strength */
.strength-row{display:flex;gap:4px;align-items:center;margin-top:6px}
.sb-bar{flex:1;height:3px;border-radius:2px;background:var(--border)}
.sb-fill{height:100%;border-radius:2px;width:0;transition:width .3s,background .3s}
.sb-lbl{font-size:.61rem;color:var(--muted);min-width:40px;text-align:right}

.form-sep{display:flex;align-items:center;gap:10px;margin:8px 0 16px}
.form-sep-line{flex:1;height:1px;background:var(--border)}
.form-sep-lbl{font-size:.59rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--a2);display:flex;align-items:center;gap:5px}

/* Submit */
.submit-row{display:flex;align-items:center;gap:12px;margin-top:8px}
.create-btn{flex:1;padding:13px;background:linear-gradient(135deg,var(--a),var(--a2));color:#f0f4ff;border:none;border-radius:11px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:.9rem;cursor:pointer;transition:all .25s;box-shadow:0 4px 18px var(--shadow)}
.create-btn:hover{transform:translateY(-2px);box-shadow:0 8px 26px rgba(16,158,136,.3)}
.reset-btn{padding:13px 18px;background:var(--white);border:1.5px solid var(--border);border-radius:11px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;font-size:.82rem;color:var(--muted);cursor:pointer;transition:all .2s}
.reset-btn:hover{border-color:var(--border2);color:var(--text)}

/* Alert */
.alert{display:flex;align-items:flex-start;gap:9px;padding:12px 15px;border-radius:10px;font-size:.8rem;line-height:1.55;margin-bottom:20px;border:1px solid transparent;animation:slideIn .25s ease both}
@keyframes slideIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.a-err{background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.2);color:var(--red)}
.a-ok{background:rgba(5,150,105,.07);border-color:rgba(5,150,105,.2);color:var(--green)}

/* Required star */
.req{color:var(--red);margin-left:2px}

@media(max-width:900px){.admin-main{grid-template-columns:1fr}.sidebar{display:none}.form-area{padding:24px 20px}}
@media(max-width:480px){.topbar{padding:0 16px}.field-row{flex-direction:column;gap:0}}
</style>
</head>
<body>
<div class="bg-canvas"><div class="mesh m1"></div><div class="mesh m2"></div></div>
<div class="dots"></div>

<?php if (!$unlocked): ?>
<!-- ══ GATE SCREEN ══ -->
<div class="gate-wrap">
  <div class="gate-card">
    <div class="gate-ico">🔐</div>
    <div class="gate-title">Admin Access</div>
    <div class="gate-sub">This panel is restricted to administrators only.<br>Enter the admin password to continue.</div>

    <?php if ($msg_type === 'error'): ?>
    <div class="alert a-err">⚠ <?= $msg_text ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="pw-wrap">
        <input class="pw-input" type="password" name="admin_pw" id="gate-pw" placeholder="Admin password" required autofocus>
        <button type="button" class="pw-eye" onclick="toggleGatePw()">👁</button>
      </div>
      <button type="submit" class="gate-btn">Unlock Panel →</button>
    </form>
    <div class="hint">🏛️ Nagrik Seva · Administrative Console</div>
  </div>
</div>

<?php else: ?>
<!-- ══ ADMIN PANEL ══ -->
<div class="admin-wrap">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="tb-logo">
      <div class="tb-mark">🏛️</div>
      <div class="tb-name">Nagrik Seva</div>
      <div class="tb-badge">Admin Panel</div>
    </div>
    <div class="tb-right">
      <form method="POST" style="display:inline">
        <input type="hidden" name="logout_admin" value="1">
        <button type="button" class="tb-logout" onclick="lockPanel()">🔒 Lock Panel</button>
      </form>
    </div>
  </header>

  <div class="admin-main">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="sb-section">
        <div class="sb-label">Management</div>
        <div class="sb-item on">
          <div class="sb-ico">➕</div> Create Account
        </div>
      </div>
      <div class="sb-divider"></div>
      <div class="sb-section">
        <div class="sb-label">Quick Stats</div>
        <div class="sb-stats">
          <div class="sb-stat-row"><span class="sb-stat-lbl">Total Officers</span><span class="sb-stat-val"><?php $r=$conn->query("SELECT COUNT(*) c FROM users WHERE role='officer'");echo($r?$r->fetch_assoc()['c']:'—');?></span></div>
          <div class="sb-stat-row"><span class="sb-stat-lbl">Regulators</span><span class="sb-stat-val"><?php $r=$conn->query("SELECT COUNT(*) c FROM users WHERE role='regulator'");echo($r?$r->fetch_assoc()['c']:'—');?></span></div>
          <div class="sb-stat-row"><span class="sb-stat-lbl">Citizens</span><span class="sb-stat-val"><?php $r=$conn->query("SELECT COUNT(*) c FROM users WHERE role='citizen'");echo($r?$r->fetch_assoc()['c']:'—');?></span></div>
        </div>
      </div>
      <div class="sb-divider"></div>
      <div class="sb-section">
        <div class="sb-label">Portals</div>
        <div class="sb-item" onclick="location.href='citizen_login.php'"><div class="sb-ico">👤</div> Citizen Login</div>
        <div class="sb-item" onclick="location.href='officer_login.php'"><div class="sb-ico">👮</div> Officer Login</div>
        <div class="sb-item" onclick="location.href='regulator_login.php'"><div class="sb-ico">⚖️</div> Regulator Login</div>
      </div>
    </aside>

    <!-- FORM AREA -->
    <main class="form-area">
      <div class="page-header">
        <div class="page-eyebrow">➕ User Management</div>
        <div class="page-title">Create Officer / Regulator Account</div>
        <div class="page-desc">Create login credentials for officers and regulators. They will use the email and password you set here to sign in to their respective portals.</div>
      </div>

      <?php if ($msg_type): ?>
      <div class="alert <?= $msg_type === 'success' ? 'a-ok' : 'a-err' ?>">
        <?= $msg_type === 'success' ? '✅' : '⚠' ?> <?= $msg_text ?>
      </div>
      <?php endif; ?>

      <div class="form-card">
        <form method="POST" id="create-form">
          <input type="hidden" name="create_user" value="1">

          <!-- Role selection -->
          <div class="form-section-title">👤 Select Role</div>
          <div class="role-select-group">
            <label class="role-opt <?= ($_POST['role'] ?? '') === 'officer' ? 'selected' : '' ?>" id="role-officer">
              <input type="radio" name="role" value="officer" <?= ($_POST['role'] ?? '') === 'officer' ? 'checked' : '' ?> onchange="selectRole(this)">
              <div class="role-ico">👮</div>
              <div>
                <div class="role-name">Officer</div>
                <div class="role-desc">Handles citizen complaints</div>
              </div>
            </label>
            <label class="role-opt <?= ($_POST['role'] ?? '') === 'regulator' ? 'selected' : '' ?>" id="role-regulator">
              <input type="radio" name="role" value="regulator" <?= ($_POST['role'] ?? '') === 'regulator' ? 'checked' : '' ?> onchange="selectRole(this)">
              <div class="role-ico">⚖️</div>
              <div>
                <div class="role-name">Regulator</div>
                <div class="role-desc">Oversees officers &amp; system</div>
              </div>
            </label>
          </div>

          <!-- Personal info -->
          <div class="form-section-title">📋 Personal Details</div>
          <div class="field-row">
            <div class="fg">
              <label class="fl">Full Name <span class="req">*</span></label>
              <input class="fi" type="text" name="full_name" placeholder="e.g. Rahul Naik" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
            </div>
            <div class="fg">
              <label class="fl">Phone Number</label>
              <input class="fi" type="tel" name="phone" placeholder="9876543210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="fg">
            <label class="fl">Department / Designation</label>
            <input class="fi" type="text" name="department" placeholder="e.g. North Goa Municipal Council" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
          </div>

          <!-- Login credentials -->
          <div class="form-sep">
            <div class="form-sep-line"></div>
            <div class="form-sep-lbl">🔑 Login Credentials</div>
            <div class="form-sep-line"></div>
          </div>

          <div class="fg">
            <label class="fl">Email Address <span class="req">*</span></label>
            <input class="fi" type="email" name="email" placeholder="officer@nagrikseva.gov" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>

          <div class="field-row">
            <div class="fg">
              <label class="fl">Password <span class="req">*</span></label>
              <div class="pw-field-wrap">
                <input class="fi" type="password" id="new-pw" name="password" placeholder="Min. 8 characters" required oninput="updateStrength(this.value)">
                <button type="button" class="pw-toggle" onclick="togglePw('new-pw', this)">👁</button>
              </div>
              <div class="strength-row">
                <div class="sb-bar"><div class="sb-fill" id="sf1"></div></div>
                <div class="sb-bar"><div class="sb-fill" id="sf2"></div></div>
                <div class="sb-bar"><div class="sb-fill" id="sf3"></div></div>
                <div class="sb-bar"><div class="sb-fill" id="sf4"></div></div>
                <span class="sb-lbl" id="s-lbl"></span>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Confirm Password <span class="req">*</span></label>
              <div class="pw-field-wrap">
                <input class="fi" type="password" id="conf-pw" name="confirm_password" placeholder="Repeat password" required>
                <button type="button" class="pw-toggle" onclick="togglePw('conf-pw', this)">👁</button>
              </div>
            </div>
          </div>

          <div class="submit-row">
            <button type="submit" class="create-btn">Create Account →</button>
            <button type="reset" class="reset-btn" onclick="resetRoles()">Clear</button>
          </div>
        </form>
      </div>
    </main>
  </div>
</div>
<?php endif; ?>

<script>
// Gate pw toggle
function toggleGatePw(){const e=document.getElementById('gate-pw');e.type=e.type==='password'?'text':'password';}

function lockPanel(){
  const f=document.createElement('form');
  f.method='POST';
  f.innerHTML='<input type="hidden" name="logout_admin" value="1">';
  document.body.appendChild(f);
  f.submit();
}

// Role visual toggle
function selectRole(radio){
  document.querySelectorAll('.role-opt').forEach(el=>el.classList.remove('selected'));
  radio.closest('.role-opt').classList.add('selected');
}
function resetRoles(){document.querySelectorAll('.role-opt').forEach(el=>el.classList.remove('selected'));}

// PW toggle with icon
function togglePw(id,btn){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password';btn.textContent=e.type==='password'?'👁':'🙈';}

// Strength meter
function updateStrength(pw){
  let sc=0;
  if(pw.length>=8)sc++;
  if(/[A-Z]/.test(pw))sc++;
  if(/[0-9]/.test(pw))sc++;
  if(/[^A-Za-z0-9]/.test(pw))sc++;
  const cols=['#ef5350','#ff7043','#f59e0b','#10b981'];
  const labs=['Weak','Fair','Good','Strong'];
  [1,2,3,4].forEach(i=>{
    const f=document.getElementById('sf'+i);
    f.style.width=i<=sc?'100%':'0%';
    f.style.background=i<=sc?cols[sc-1]:'transparent';
  });
  const lbl=document.getElementById('s-lbl');
  lbl.textContent=sc?labs[sc-1]:'';
  lbl.style.color=sc?cols[sc-1]:'';
}

// GTA cheat — type "admin" to go to admin.php (already here)
</script>
</body>
</html>