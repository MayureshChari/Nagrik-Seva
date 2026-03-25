<?php
session_start();
require_once 'config.php';

if ((empty($_SESSION['user_id']) && !isset($_SESSION['is_demo'])) || $_SESSION['role'] !== 'officer') {
    header('Location: officer_login.php'); exit;
}

$uid      = (int)$_SESSION['user_id'];
$name     = $_SESSION['name'] ?? 'Officer';
$initials = strtoupper(substr($name, 0, 1));

$user = [];
if ($uid > 0) {
    $r = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $r->bind_param('i',$uid);$r->execute();
    $user = $r->get_result()->fetch_assoc() ?? [];$r->close();
}
// Demo fallback
if (empty($user)) {
    $user = [
        'name'       => $name,
        'email'      => 'demo.officer@nagrikseva.gov',
        'phone'      => '+91 98000 00000',
        'zone'       => 'Panaji',
        'is_active'  => 1,
        'created_at' => date('Y-m-d H:i:s', strtotime('-90 days')),
        'last_login' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'password_hash' => '',
    ];
}
$zone = $user['zone'] ?? '';

// ── Stats ──
$stats = ['total'=>0,'resolved'=>0,'in_progress'=>0,'assigned'=>0,'escalated'=>0];
if ($uid > 0) {
    $r2 = $conn->query("SELECT status, COUNT(*) as c FROM complaints WHERE officer_id=$uid GROUP BY status");
    while ($row = $r2->fetch_assoc()) {
        $stats['total'] += $row['c'];
        if (array_key_exists($row['status'], $stats)) $stats[$row['status']] = (int)$row['c'];
    }
}
$resolution_rate = $stats['total'] > 0 ? round(($stats['resolved']/$stats['total'])*100) : 0;

// ── Handle POST ──
$msg_ok = $msg_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $new_name  = trim($_POST['name']  ?? '');
        $new_phone = trim($_POST['phone'] ?? '');
        $new_zone  = trim($_POST['zone']  ?? '');
        if (!$new_name) { $msg_err = 'Name cannot be empty.'; }
        elseif ($uid <= 0) {
            $_SESSION['name'] = $new_name; $name = $new_name; $initials = strtoupper(substr($name,0,1));
            $zone = $new_zone; $user['name'] = $new_name; $user['phone'] = $new_phone; $user['zone'] = $new_zone;
            $msg_ok = 'Profile updated (demo — changes not saved to DB).';
        } else {
            $st = $conn->prepare("UPDATE users SET name=?,phone=?,zone=?,updated_at=NOW() WHERE id=?");
            $st->bind_param('sssi',$new_name,$new_phone,$new_zone,$uid);
            $st->execute();$st->close();
            $_SESSION['name'] = $new_name;
            $name = $new_name; $initials = strtoupper(substr($name,0,1));
            $zone = $new_zone;
            $r3 = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
            $r3->bind_param('i',$uid);$r3->execute();
            $user = $r3->get_result()->fetch_assoc();$r3->close();
            $msg_ok = 'Profile updated successfully.';
        }
    }

    if ($action === 'change_password') {
        $curr = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (empty($user['password_hash'])) {
            $msg_err = 'Password changes are not available for demo accounts.';
        } elseif (!password_verify($curr, $user['password_hash'])) {
            $msg_err = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $msg_err = 'New password must be at least 8 characters.';
        } elseif ($new !== $conf) {
            $msg_err = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $st = $conn->prepare("UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?");
            $st->bind_param('si',$hash,$uid);$st->execute();$st->close();
            $msg_ok = 'Password changed successfully.';
        }
    }
}

$zones_list = ['Panaji','Margao','Vasco','Mapusa','Ponda','Calangute','Candolim','Colva','Pervem','Sanguem','Canacona'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile — Nagrik Seva Officer</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g900:#011a18;--g800:#042e2a;--g750:#053d37;--g700:#065449;--g650:#0a6e60;
  --g600:#0d8572;--g500:#109e88;--g450:#14b89f;--g400:#18cfb4;--g350:#3ddbc3;
  --g300:#6ce5d2;--g200:#adf2e8;--g150:#cef7f2;--g100:#e2faf7;--g050:#f0fdfb;
  --white:#ffffff;--accent:#18cfb4;
  --bg:#f0f9f4;--card:#ffffff;
  --text:#0d2b1b;--text2:#163d27;--muted:#4a7260;--muted2:#5e8a72;
  --border:#c8e8d8;--border2:#a0d4b8;--radius:13px;
  --sh-xs:0 1px 2px rgba(13,43,27,.05);
  --sh-sm:0 2px 12px rgba(13,43,27,.07),0 1px 3px rgba(13,43,27,.05);
  --sh-md:0 8px 28px rgba(13,43,27,.11),0 2px 8px rgba(13,43,27,.07);
  --sh-lg:0 20px 56px rgba(13,43,27,.16),0 4px 16px rgba(13,43,27,.08);
}
html,body{height:100%;overflow:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:3px}

.sidebar{width:220px;min-width:220px;height:100vh;background:var(--g800);display:flex;flex-direction:column;z-index:51;overflow-y:auto;position:relative;}
.sidebar::after{content:'';position:absolute;right:0;top:0;bottom:0;width:1px;background:linear-gradient(180deg,transparent,var(--g500) 20%,var(--g400) 60%,var(--g500) 80%,transparent);}
.sb-logo{padding:24px 20px 22px;display:flex;align-items:center;gap:13px;}
.sb-mark{width:42px;height:42px;background:linear-gradient(135deg,var(--g400),var(--g350));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;box-shadow:0 4px 14px rgba(24,207,180,0.35);}
.sb-name{font-size:.9rem;font-weight:700;color:var(--white);letter-spacing:-.2px;line-height:1.1}
.sb-sub{font-size:.58rem;color:var(--g300);text-transform:uppercase;letter-spacing:1.2px;margin-top:3px;font-weight:500}
.sb-divider{height:1px;background:linear-gradient(90deg,transparent,var(--g650),transparent);margin:4px 0 8px;}
.sb-sec{padding:14px 20px 5px;font-size:.54rem;font-weight:700;letter-spacing:2.2px;text-transform:uppercase;color:var(--g450);}
.nav-a{display:flex;align-items:center;gap:10px;padding:9px 16px;margin:1px 10px;border-radius:10px;font-size:.79rem;font-weight:450;color:var(--g200);cursor:pointer;transition:all .18s cubic-bezier(.4,0,.2,1);border:1px solid transparent;position:relative;letter-spacing:.01em;}
.nav-a:hover{background:rgba(255,255,255,0.06);color:var(--white);border-color:rgba(255,255,255,0.08);transform:translateX(2px)}
.nav-a.on{background:linear-gradient(135deg,rgba(24,207,180,0.2),rgba(24,207,180,0.08));color:var(--white);font-weight:600;border-color:rgba(24,207,180,0.4);}
.nav-a.on::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:60%;background:var(--accent);border-radius:0 3px 3px 0;}
.nav-ico{font-size:.85rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--accent);color:var(--g900);font-size:.58rem;font-weight:800;padding:2px 6px;border-radius:4px}
.sb-foot{margin-top:auto;padding:12px 10px;border-top:1px solid rgba(255,255,255,0.06)}
.u-card{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:11px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);transition:all .18s;}
.u-card:hover{background:rgba(255,255,255,0.08)}
.u-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));border:2px solid rgba(24,207,180,0.4);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--white);flex-shrink:0;box-shadow:0 2px 8px rgba(24,207,180,0.25);}
.u-name{font-size:.77rem;font-weight:600;color:var(--white)}
.u-role{font-size:.58rem;color:var(--g300);margin-top:1px;letter-spacing:.5px;text-transform:uppercase}
.u-logout{margin-left:auto;background:none;border:none;color:var(--g300);cursor:pointer;padding:5px;border-radius:7px;transition:all .15s;}
.u-logout:hover{color:var(--white);background:rgba(255,255,255,0.1);transform:scale(1.1)}

.main{flex:1;height:100vh;overflow-y:auto;display:flex;flex-direction:column}
.topbar{background:var(--card);padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;flex-shrink:0;border-bottom:1px solid var(--border);box-shadow:var(--sh-xs)}
.tb-title{font-size:.88rem;font-weight:700;color:var(--text)}
.tb-back{padding:7px 14px;border-radius:8px;font-size:.76rem;font-weight:600;background:var(--g100);color:var(--g600);border:1px solid var(--border);transition:all .14s;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif}
.tb-back:hover{background:var(--border)}
.body{padding:24px 28px 32px;flex:1}
.toast{display:flex;align-items:center;gap:9px;padding:11px 15px;border-radius:9px;font-size:.79rem;margin-bottom:16px;border:1px solid transparent;font-weight:500;animation:toast-in .22s ease}
@keyframes toast-in{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.t-ok{background:rgba(5,150,105,.08);border-color:rgba(5,150,105,.2);color:#059669}
.t-err{background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.18);color:#dc2626}

/* PROFILE LAYOUT */
.profile-grid{display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start}

/* PROFILE CARD */
.p-id-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--sh-sm);margin-bottom:14px}
.p-banner{height:80px;background:linear-gradient(135deg,var(--g800) 0%,var(--g700) 50%,var(--g500) 100%);position:relative}
.p-av-wrap{position:absolute;bottom:-32px;left:20px}
.p-av{
  width:64px;height:64px;border-radius:50%;
  background:linear-gradient(135deg,var(--g400),var(--g200));
  border:3px solid var(--card);
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;font-weight:700;color:var(--g800);
  box-shadow:0 4px 12px rgba(24,207,180,.3);
}
.p-info{padding:42px 20px 18px}
.p-name{font-size:1rem;font-weight:700;color:var(--text);letter-spacing:-.2px;margin-bottom:3px}
.p-zone{font-size:.72rem;color:var(--muted)}
.p-badge{display:inline-flex;align-items:center;gap:5px;margin-top:8px;padding:4px 10px;border-radius:5px;background:var(--g050);border:1px solid var(--g200);font-size:.62rem;font-weight:700;color:var(--g700);text-transform:uppercase;letter-spacing:.5px}
.p-stats{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--border);margin-top:14px}
.p-stat{background:var(--card);padding:12px 14px;text-align:center}
.p-stat-num{font-size:1.4rem;font-weight:700;color:var(--text);letter-spacing:-.6px}
.p-stat-lbl{font-size:.6rem;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-top:2px;font-weight:500}
.perf-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--sh-sm)}
.pc-head{padding:14px 18px;border-bottom:1px solid var(--border)}
.pc-label{font-size:.6rem;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--muted)}
.pc-body{padding:16px 18px}
.pb-row{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border)}
.pb-row:last-child{border-bottom:none}
.pb-lbl{font-size:.73rem;font-weight:500;color:var(--text);min-width:90px}
.pb-track{flex:1;height:5px;background:var(--g100);border-radius:3px;overflow:hidden}
.pb-fill{height:100%;border-radius:3px;transition:width .8s ease}
.pf-g{background:linear-gradient(90deg,#10b981,#34d399)}
.pf-w{background:linear-gradient(90deg,var(--g400),var(--accent))}
.pf-d{background:linear-gradient(90deg,#ef4444,#f87171)}
.pb-val{font-size:.67rem;font-weight:700;color:var(--muted);min-width:30px;text-align:right;font-family:'DM Mono',monospace}

/* FORMS */
.form-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--sh-sm);margin-bottom:14px}
.fc-head{padding:14px 18px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,var(--g050),rgba(255,255,255,0))}
.fc-head-title{font-size:.85rem;font-weight:700;color:var(--text)}
.fc-body{padding:20px 18px}
.fg{margin-bottom:14px}
.fg-row{display:grid;grid-template-columns:1fr 1fr;gap:13px;margin-bottom:14px}
.fl{display:block;font-size:.61rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin-bottom:5px}
.fi{width:100%;padding:9px 12px;background:var(--card);border:1.5px solid var(--border);border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.84rem;color:var(--text);outline:none;transition:all .15s}
.fi:focus{border-color:var(--g400);box-shadow:0 0 0 3px rgba(24,207,180,.1)}
select.fi{cursor:pointer}
.fi[disabled]{background:var(--g050);color:var(--muted);cursor:not-allowed}
.fi-w{position:relative}
.fi-w .fi{padding-right:40px}
.eye{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.85rem;padding:0;transition:color .14s}
.eye:hover{color:var(--text)}
.btn-save{padding:10px 22px;background:linear-gradient(135deg,var(--g700),var(--g500));color:var(--white);border:none;border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.86rem;font-weight:700;cursor:pointer;transition:all .16s;box-shadow:0 3px 10px rgba(24,207,180,.3)}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 5px 14px rgba(24,207,180,.35)}
.btn-ghost{padding:10px 20px;background:var(--card);color:var(--muted);border:1.5px solid var(--border);border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.84rem;font-weight:500;cursor:pointer;transition:all .14s}
.btn-ghost:hover{background:var(--g100);color:var(--text)}
.field-note{font-size:.67rem;color:var(--muted);margin-top:5px;line-height:1.5}

/* ACTIVITY */
.act-item{display:flex;align-items:flex-start;gap:11px;padding:10px 0;border-bottom:1px solid var(--border)}
.act-item:last-child{border-bottom:none}
.act-ico{width:32px;height:32px;border-radius:8px;background:var(--g050);border:1.5px solid var(--g100);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.act-msg{font-size:.75rem;color:var(--text);line-height:1.5;margin-bottom:2px}
.act-time{font-size:.61rem;color:var(--muted)}

@media(max-width:960px){.sidebar{display:none}.profile-grid{grid-template-columns:1fr}}
@media(max-width:600px){.body{padding:14px}.fg-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-mark">🏛️</div>
    <div><div class="sb-name">Nagrik Seva</div><div class="sb-sub">Officer Portal</div></div>
  </div>
  <div class="sb-divider"></div>
  <div class="sb-sec">Main</div>
  <a class="nav-a" href="officer_dashboard.php"><span class="nav-ico">⊞</span> Dashboard</a>
  <a class="nav-a" href="officer_complaints.php"><span class="nav-ico">≡</span> My Complaints</a>
  <a class="nav-a" href="officer_complaints.php?scope=zone"><span class="nav-ico">📥</span> Unassigned <?php if(!empty($unassigned) && count($unassigned)>0): ?><span class="nav-badge"><?= count($unassigned) ?></span><?php endif; ?></a>
  <a class="nav-a" href="track.php"><span class="nav-ico">⊙</span> Track Complaint</a>
  <a class="nav-a" href="public_board.php"><span class="nav-ico">◎</span> Public Board</a>
  <div class="sb-sec">Account</div>
  <a class="nav-a on" href="officer_profile.php"><span class="nav-ico">○</span> My Profile</a>
  <a class="nav-a" href="officer_complaints.php?filter=escalated"><span class="nav-ico">🚨</span> Escalated <?php if(!empty($stats['escalated']) && $stats['escalated']>0): ?><span class="nav-badge"><?= $stats['escalated'] ?></span><?php endif; ?></a>
  <div class="sb-sec">Info</div>
  <a class="nav-a" href="about.php"><span class="nav-ico">ℹ</span> About</a>
  <a class="nav-a" href="contact.php"><span class="nav-ico">✉</span> Contact</a>
  <div class="sb-foot">
    <div class="u-card">
      <div class="u-av"><?= $initials ?></div>
      <div><div class="u-name"><?= htmlspecialchars($name) ?></div><div class="u-role">Officer</div></div>
      <a href="logout.php" class="u-logout" title="Sign out">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </a>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="tb-title">👤 My Profile</div>
    <a href="officer_dashboard.php"><button class="tb-back">← Dashboard</button></a>
  </div>
  <div class="body">
    <?php if($msg_ok): ?><div class="toast t-ok">✓ <?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
    <?php if($msg_err): ?><div class="toast t-err">⚠ <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

    <div class="profile-grid">

      <!-- LEFT SIDEBAR -->
      <div>
        <!-- ID CARD -->
        <div class="p-id-card">
          <div class="p-banner">
            <div class="p-av-wrap">
              <div class="p-av"><?= $initials ?></div>
            </div>
          </div>
          <div class="p-info">
            <div class="p-name"><?= htmlspecialchars($name) ?></div>
            <div class="p-zone">📍 <?= htmlspecialchars($zone ?: 'Zone not set') ?></div>
            <div class="p-badge">🏢 Field Officer</div>
          </div>
          <div class="p-stats">
            <div class="p-stat"><div class="p-stat-num"><?= $stats['total'] ?></div><div class="p-stat-lbl">Total</div></div>
            <div class="p-stat"><div class="p-stat-num"><?= $stats['resolved'] ?></div><div class="p-stat-lbl">Resolved</div></div>
            <div class="p-stat"><div class="p-stat-num"><?= $stats['in_progress'] ?></div><div class="p-stat-lbl">Active</div></div>
            <div class="p-stat"><div class="p-stat-num"><?= $resolution_rate ?>%</div><div class="p-stat-lbl">Rate</div></div>
          </div>
        </div>

        <!-- PERFORMANCE -->
        <div class="perf-card">
          <div class="pc-head"><div class="pc-label">Performance Overview</div></div>
          <div class="pc-body">
            <div class="pb-row"><div class="pb-lbl">Resolution</div><div class="pb-track"><div class="pb-fill pf-g" style="width:<?= $resolution_rate ?>%"></div></div><div class="pb-val"><?= $resolution_rate ?>%</div></div>
            <?php $ip_rate = $stats['total']>0?round($stats['in_progress']/$stats['total']*100):0; ?>
            <div class="pb-row"><div class="pb-lbl">In Progress</div><div class="pb-track"><div class="pb-fill pf-w" style="width:<?= $ip_rate ?>%"></div></div><div class="pb-val"><?= $ip_rate ?>%</div></div>
            <?php $esc_rate = $stats['total']>0?round($stats['escalated']/$stats['total']*100):0; ?>
            <div class="pb-row"><div class="pb-lbl">Escalated</div><div class="pb-track"><div class="pb-fill pf-d" style="width:<?= max($esc_rate,3) ?>%"></div></div><div class="pb-val"><?= $esc_rate ?>%</div></div>
          </div>
        </div>
      </div>

      <!-- RIGHT FORMS -->
      <div>
        <!-- EDIT PROFILE -->
        <div class="form-card">
          <div class="fc-head"><div class="fc-head-title">✏️ Edit Profile Information</div></div>
          <div class="fc-body">
            <form method="POST">
              <input type="hidden" name="action" value="update_profile">
              <div class="fg-row">
                <div class="fg" style="margin-bottom:0">
                  <label class="fl" for="pname">Full Name</label>
                  <input class="fi" type="text" id="pname" name="name" value="<?= htmlspecialchars($user['name']??$name) ?>" required>
                </div>
                <div class="fg" style="margin-bottom:0">
                  <label class="fl">Email Address</label>
                  <input class="fi" type="email" value="<?= htmlspecialchars($user['email']??'') ?>" disabled>
                  <div class="field-note">Email cannot be changed. Contact admin if needed.</div>
                </div>
              </div>
              <div class="fg-row" style="margin-top:13px">
                <div class="fg" style="margin-bottom:0">
                  <label class="fl" for="pphone">Phone Number</label>
                  <input class="fi" type="tel" id="pphone" name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>" placeholder="9876543210">
                </div>
                <div class="fg" style="margin-bottom:0">
                  <label class="fl" for="pzone">Assigned Zone</label>
                  <select class="fi" id="pzone" name="zone">
                    <option value="">Select zone…</option>
                    <?php foreach ($zones_list as $z): ?>
                    <option value="<?= $z ?>" <?= ($user['zone']??'')===$z?'selected':'' ?>><?= $z ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn-save">Save Changes →</button>
              </div>
            </form>
          </div>
        </div>

        <!-- CHANGE PASSWORD -->
        <div class="form-card">
          <div class="fc-head"><div class="fc-head-title">🔒 Change Password</div></div>
          <div class="fc-body">
            <form method="POST">
              <input type="hidden" name="action" value="change_password">
              <div class="fg">
                <label class="fl" for="cp_curr">Current Password</label>
                <div class="fi-w"><input class="fi" type="password" id="cp_curr" name="current_password" placeholder="Your current password" required><button type="button" class="eye" onclick="toggleEye('cp_curr')">👁</button></div>
              </div>
              <div class="fg-row">
                <div class="fg" style="margin-bottom:0">
                  <label class="fl" for="cp_new">New Password</label>
                  <div class="fi-w"><input class="fi" type="password" id="cp_new" name="new_password" placeholder="Min. 8 characters" required><button type="button" class="eye" onclick="toggleEye('cp_new')">👁</button></div>
                </div>
                <div class="fg" style="margin-bottom:0">
                  <label class="fl" for="cp_conf">Confirm New Password</label>
                  <div class="fi-w"><input class="fi" type="password" id="cp_conf" name="confirm_password" placeholder="Repeat new password" required><button type="button" class="eye" onclick="toggleEye('cp_conf')">👁</button></div>
                </div>
              </div>
              <div style="margin-top:18px">
                <button type="submit" class="btn-save">Update Password →</button>
              </div>
            </form>
          </div>
        </div>

        <!-- ACCOUNT INFO -->
        <div class="form-card">
          <div class="fc-head"><div class="fc-head-title">ℹ️ Account Information</div></div>
          <div class="fc-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <div class="fl">Account Status</div>
                <div style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:5px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);font-size:.72rem;font-weight:700;color:#059669;margin-top:4px">
                  <span style="width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block"></span>
                  <?= ($user['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                </div>
              </div>
              <div>
                <div class="fl">Role</div>
                <div style="font-size:.82rem;color:var(--text);font-weight:600;margin-top:6px">Field Officer</div>
              </div>
              <div>
                <div class="fl">Joined</div>
                <div style="font-size:.79rem;color:var(--muted);margin-top:6px;font-family:'DM Mono',monospace"><?= date('d M Y', strtotime($user['created_at'] ?? 'now')) ?></div>
              </div>
              <div>
                <div class="fl">Last Login</div>
                <div style="font-size:.79rem;color:var(--muted);margin-top:6px;font-family:'DM Mono',monospace"><?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'N/A' ?></div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
function toggleEye(id){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password'}
</script>
</body>
</html>
