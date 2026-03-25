<?php
session_start();
require_once 'config.php';

if ((empty($_SESSION['user_id']) && !isset($_SESSION['is_demo'])) || $_SESSION['role'] !== 'officer') {
    header('Location: officer_login.php'); exit;
}

$uid      = (int)$_SESSION['user_id'];
$name     = $_SESSION['name'] ?? 'Officer';
$initials = strtoupper(substr($name, 0, 1));

$r = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$r->bind_param('i',$uid);$r->execute();
$officer = $r->get_result()->fetch_assoc();$r->close();
$zone = $officer['zone'] ?? '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: officer_complaints.php'); exit; }

// ── Fetch complaint ──
$st = $conn->prepare(
    "SELECT c.*, u.name AS citizen_name, u.email AS citizen_email, u.phone AS citizen_phone
     FROM complaints c LEFT JOIN users u ON c.citizen_id = u.id
     WHERE c.id = ? LIMIT 1"
);
$st->bind_param('i',$id);$st->execute();
$c = $st->get_result()->fetch_assoc();$st->close();

if (!$c) { header('Location: officer_complaints.php'); exit; }

// ── Handle POST: update status/notes ──
$msg_ok = $msg_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        $notes      = trim($_POST['notes'] ?? '');
        $allowed    = ['assigned','in_progress','resolved','closed','escalated'];
        if (!in_array($new_status, $allowed)) {
            $msg_err = 'Invalid status.';
        } else {
            $resolved_at = ($new_status === 'resolved' && !$c['resolved_at']) ? date('Y-m-d H:i:s') : $c['resolved_at'];
            $st2 = $conn->prepare("UPDATE complaints SET status=?, officer_notes=?, resolved_at=?, updated_at=NOW() WHERE id=?");
            $st2->bind_param('sssi',$new_status,$notes,$resolved_at,$id);
            $st2->execute();$st2->close();

            // Notify citizen
            $notif_msg = "Your complaint {$c['complaint_no']} has been updated to: " . ucwords(str_replace('_',' ',$new_status));
            if ($notes) $notif_msg .= ". Officer note: $notes";
            $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message) VALUES({$c['citizen_id']},$id,'status_update','".addslashes($notif_msg)."')");

            // Re-fetch
            $st3 = $conn->prepare("SELECT c.*,u.name AS citizen_name,u.email AS citizen_email,u.phone AS citizen_phone FROM complaints c LEFT JOIN users u ON c.citizen_id=u.id WHERE c.id=? LIMIT 1");
            $st3->bind_param('i',$id);$st3->execute();
            $c = $st3->get_result()->fetch_assoc();$st3->close();
            $msg_ok = 'Complaint updated successfully.';
        }
    }

    if ($action === 'self_assign' && !$c['officer_id']) {
        $conn->query("UPDATE complaints SET officer_id=$uid,status='assigned',updated_at=NOW() WHERE id=$id");
        $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message) VALUES({$c['citizen_id']},$id,'assigned','Your complaint {$c['complaint_no']} has been assigned to an officer.')");
        header("Location: officer_complaint_detail.php?id=$id&assigned=1"); exit;
    }
}

if (isset($_GET['assigned'])) $msg_ok = 'Complaint assigned to you.';

$s_cfg = [
    'new'        =>['label'=>'New',        'cls'=>'s-new'],
    'assigned'   =>['label'=>'Assigned',   'cls'=>'s-assigned'],
    'in_progress'=>['label'=>'In Progress','cls'=>'s-prog'],
    'resolved'   =>['label'=>'Resolved',   'cls'=>'s-resolved'],
    'closed'     =>['label'=>'Closed',     'cls'=>'s-closed'],
    'escalated'  =>['label'=>'Escalated',  'cls'=>'s-esc'],
];
$cat_icon = ['road'=>'🚧','water'=>'💧','electricity'=>'⚡','sanitation'=>'🧹','property'=>'🏛️','lost'=>'🔍'];
$sc = $s_cfg[$c['status']] ?? ['label'=>ucfirst($c['status']),'cls'=>'s-new'];
$ico = $cat_icon[$c['category']] ?? '📋';

// Timeline steps
$timeline = [
    ['status'=>'new',         'label'=>'Submitted',    'ico'=>'📥'],
    ['status'=>'assigned',    'label'=>'Assigned',     'ico'=>'👮'],
    ['status'=>'in_progress', 'label'=>'In Progress',  'ico'=>'🔧'],
    ['status'=>'resolved',    'label'=>'Resolved',     'ico'=>'✅'],
];
$status_order = ['new'=>0,'assigned'=>1,'in_progress'=>2,'resolved'=>3,'closed'=>3,'escalated'=>1];
$current_step = $status_order[$c['status']] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Complaint <?= htmlspecialchars($c['complaint_no']) ?> — Officer Portal</title>
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
.tb-id{font-family:'DM Mono',monospace;font-size:.82rem;font-weight:600;color:var(--g500);letter-spacing:.5px}
.tb-title{font-size:.88rem;font-weight:700;color:var(--text);margin-left:10px}
.tb-right{display:flex;align-items:center;gap:10px}
.tb-back{padding:7px 14px;border-radius:8px;font-size:.76rem;font-weight:600;background:var(--g100);color:var(--g600);border:1px solid var(--border);transition:all .14s;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif}
.tb-back:hover{background:var(--border)}
.body{padding:24px 28px 32px;flex:1}
.toast{display:flex;align-items:center;gap:9px;padding:11px 15px;border-radius:9px;font-size:.79rem;margin-bottom:16px;border:1px solid transparent;font-weight:500;animation:toast-in .22s ease}
@keyframes toast-in{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.t-ok{background:rgba(5,150,105,.08);border-color:rgba(5,150,105,.2);color:#059669}
.t-err{background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.18);color:#dc2626}

/* LAYOUT */
.detail-grid{display:grid;grid-template-columns:1fr 360px;gap:18px;align-items:start}

/* COMPLAINT HEADER */
.complaint-header{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:22px;margin-bottom:14px;box-shadow:var(--sh-sm)}
.ch-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}
.ch-id{font-family:'DM Mono',monospace;font-size:.68rem;font-weight:600;color:var(--g500);letter-spacing:.8px;margin-bottom:5px}
.ch-title{font-size:1.05rem;font-weight:700;color:var(--text);letter-spacing:-.2px;line-height:1.3;margin-bottom:8px}
.ch-meta{display:flex;flex-wrap:wrap;gap:10px}
.ch-meta-item{display:flex;align-items:center;gap:5px;font-size:.73rem;color:var(--muted)}
.ch-badges{display:flex;align-items:center;gap:8px;flex-shrink:0;flex-wrap:wrap}
.pill{display:inline-flex;align-items:center;padding:3px 8px;border-radius:4px;font-size:.58rem;font-weight:700;letter-spacing:.4px;white-space:nowrap;text-transform:uppercase;border:1px solid transparent}
.s-new{background:var(--g050);color:var(--g700);border-color:var(--g200)}
.s-assigned{background:var(--g050);color:var(--g600);border-color:var(--g200)}
.s-prog{background:rgba(139,92,246,.08);color:#5b21b6;border-color:rgba(139,92,246,.25)}
.s-resolved{background:rgba(16,185,129,.08);color:#065f46;border-color:rgba(16,185,129,.22)}
.s-closed{background:var(--g100);color:var(--muted);border-color:var(--border2)}
.s-esc{background:rgba(239,68,68,.08);color:#7f1d1d;border-color:rgba(239,68,68,.2)}
.p-high{background:rgba(239,68,68,.08);color:#7f1d1d;border-color:rgba(239,68,68,.2)}
.p-med{background:var(--g050);color:var(--g700);border-color:var(--g200)}
.p-low{background:var(--g100);color:var(--muted);border-color:var(--border2)}
.pcat{background:var(--g100);color:var(--g600);border-color:var(--border2);font-size:.62rem}

/* DESCRIPTION */
.desc-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:14px;box-shadow:var(--sh-sm)}
.card-label{font-size:.6rem;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--muted);margin-bottom:10px}
.desc-text{font-size:.85rem;color:var(--text-soft);line-height:1.8}

/* PHOTO */
.photo-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:14px;box-shadow:var(--sh-sm)}
.photo-img{width:100%;max-height:280px;object-fit:cover}
.photo-placeholder{padding:32px;text-align:center;color:var(--muted);background:var(--g100);font-size:.8rem}

/* TIMELINE */
.timeline-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;margin-bottom:14px;box-shadow:var(--sh-sm)}
.tl-steps{display:flex;align-items:center;justify-content:space-between;position:relative;padding:0 8px}
.tl-steps::before{content:'';position:absolute;left:20px;right:20px;top:16px;height:2px;background:var(--border);z-index:0}
.tl-step{display:flex;flex-direction:column;align-items:center;gap:6px;position:relative;z-index:1}
.tl-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;border:3px solid var(--border);background:var(--card);transition:all .2s}
.tl-dot.done{background:linear-gradient(135deg,var(--g400),var(--accent));border-color:var(--g400);box-shadow:0 2px 8px rgba(24,207,180,.35)}
.tl-dot.current{background:linear-gradient(135deg,var(--g500),var(--g400));border-color:var(--g400);box-shadow:0 2px 8px rgba(14,165,233,.35);animation:step-pulse .25s ease both}
.tl-lbl{font-size:.61rem;font-weight:600;color:var(--muted);text-align:center}
.tl-dot.done+.tl-lbl,.tl-dot.current+.tl-lbl{color:var(--text)}

/* UPDATE FORM */
.update-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--sh-sm);margin-bottom:14px}
.uc-head{padding:14px 18px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,var(--g050),rgba(255,255,255,0));display:flex;align-items:center;gap:8px}
.uc-head-title{font-size:.85rem;font-weight:700;color:var(--text)}
.uc-body{padding:18px}
.fg{margin-bottom:14px}
.fl{display:block;font-size:.61rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin-bottom:5px}
.fi{width:100%;padding:9px 12px;background:var(--card);border:1.5px solid var(--border);border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.84rem;color:var(--text);outline:none;transition:all .15s}
.fi:focus{border-color:var(--g400);box-shadow:0 0 0 3px rgba(24,207,180,.1)}
select.fi{cursor:pointer}
textarea.fi{resize:vertical;min-height:90px;line-height:1.6}
.btn-update{width:100%;padding:11px;background:linear-gradient(135deg,var(--g700),var(--g500));color:var(--white);border:none;border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.87rem;font-weight:700;cursor:pointer;transition:all .16s;box-shadow:0 3px 10px rgba(24,207,180,.3);letter-spacing:-.1px}
.btn-update:hover{background:linear-gradient(135deg,var(--g700),var(--g600));transform:translateY(-1px);box-shadow:0 5px 14px rgba(24,207,180,.35)}

/* CITIZEN INFO */
.citizen-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--sh-sm);margin-bottom:14px}
.cc-head{padding:14px 18px;border-bottom:1px solid var(--border)}
.cc-body{padding:16px 18px}
.cc-row{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border)}
.cc-row:last-child{border-bottom:none}
.cc-ico{width:30px;height:30px;border-radius:7px;background:var(--g100);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0}
.cc-label{font-size:.7rem;font-weight:600;color:var(--text);flex:1}
.cc-val{font-size:.71rem;color:var(--muted)}

/* ASSIGN SELF BTN */
.assign-self{width:100%;padding:11px;background:linear-gradient(135deg,var(--g700),var(--g500));color:var(--white);border:none;border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.86rem;font-weight:700;cursor:pointer;transition:all .16s;box-shadow:0 3px 10px rgba(24,207,180,.3);margin-bottom:12px}
.assign-self:hover{transform:translateY(-1px);box-shadow:0 5px 14px rgba(24,207,180,.35)}

/* NOTES BOX */
.notes-box{background:var(--g050);border:1.5px solid var(--g100);border-left:4px solid var(--g400);border-radius:10px;padding:13px 15px;font-size:.81rem;color:var(--text-soft);line-height:1.7}

@media(max-width:960px){.sidebar{display:none}.detail-grid{grid-template-columns:1fr}}
@media(max-width:600px){.body{padding:14px}}
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
  <a class="nav-a on" href="officer_complaints.php"><span class="nav-ico">≡</span> My Complaints</a>
  <a class="nav-a" href="officer_complaints.php?scope=zone"><span class="nav-ico">📥</span> Unassigned <?php if(!empty($unassigned) && count($unassigned)>0): ?><span class="nav-badge"><?= count($unassigned) ?></span><?php endif; ?></a>
  <a class="nav-a" href="track.php"><span class="nav-ico">⊙</span> Track Complaint</a>
  <a class="nav-a" href="public_board.php"><span class="nav-ico">◎</span> Public Board</a>
  <div class="sb-sec">Account</div>
  <a class="nav-a" href="officer_profile.php"><span class="nav-ico">○</span> My Profile</a>
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
    <div style="display:flex;align-items:center;gap:8px">
      <div class="tb-id"><?= htmlspecialchars($c['complaint_no']) ?></div>
      <div class="tb-title">— <?= htmlspecialchars(mb_strimwidth($c['title'],0,48,'…')) ?></div>
    </div>
    <div class="tb-right">
      <a href="officer_complaints.php"><button class="tb-back">← Back</button></a>
    </div>
  </div>

  <div class="body">
    <?php if($msg_ok): ?><div class="toast t-ok">✓ <?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
    <?php if($msg_err): ?><div class="toast t-err">⚠ <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

    <div class="detail-grid">

      <!-- LEFT -->
      <div>
        <!-- HEADER -->
        <div class="complaint-header">
          <div class="ch-top">
            <div>
              <div class="ch-id"><?= htmlspecialchars($c['complaint_no']) ?></div>
              <div class="ch-title"><?= htmlspecialchars($c['title']) ?></div>
              <div class="ch-meta">
                <span class="ch-meta-item">📍 <?= htmlspecialchars($c['location']) ?></span>
                <span class="ch-meta-item">📅 <?= date('d M Y · H:i', strtotime($c['created_at'])) ?></span>
                <?php if($c['resolved_at']): ?><span class="ch-meta-item">✅ Resolved: <?= date('d M Y', strtotime($c['resolved_at'])) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="ch-badges">
              <span class="pill <?= $sc['cls'] ?>"><?= $sc['label'] ?></span>
              <span class="pill pcat"><?= ($cat_icon[$c['category']]??'📋').' '.ucfirst($c['category']) ?></span>
              <?php $pri_cls = ['high'=>'p-high','medium'=>'p-med','low'=>'p-low'][$c['priority']??'medium']??'p-med'; ?>
              <span class="pill <?= $pri_cls ?>"><?= ucfirst($c['priority'] ?? 'medium') ?></span>
            </div>
          </div>
        </div>

        <!-- TIMELINE -->
        <div class="timeline-card">
          <div class="card-label" style="margin-bottom:16px">Resolution Timeline</div>
          <div class="tl-steps">
            <?php foreach ($timeline as $i => $step):
              $step_idx = $status_order[$step['status']] ?? $i;
              $done     = $current_step > $step_idx;
              $current  = $current_step === $step_idx;
              $cls      = $done ? 'done' : ($current ? 'current' : '');
            ?>
            <div class="tl-step">
              <div class="tl-dot <?= $cls ?>"><?= $step['ico'] ?></div>
              <div class="tl-lbl <?= $cls ?>"><?= $step['label'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if ($c['status'] === 'escalated'): ?>
          <div style="margin-top:14px;padding:8px 12px;background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.18);border-radius:7px;font-size:.76rem;color:#7f1d1d;text-align:center">🚨 This complaint has been escalated to a regulator. Urgent attention required.</div>
          <?php endif; ?>
        </div>

        <!-- DESCRIPTION -->
        <div class="desc-card">
          <div class="card-label">Complaint Description</div>
          <div class="desc-text"><?= nl2br(htmlspecialchars($c['description'] ?? 'No description provided.')) ?></div>
        </div>

        <!-- OFFICER NOTES -->
        <?php if (!empty($c['officer_notes'])): ?>
        <div class="desc-card">
          <div class="card-label" style="color:var(--g600)">📝 Officer Notes (Last Updated)</div>
          <div class="notes-box"><?= nl2br(htmlspecialchars($c['officer_notes'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- PHOTO -->
        <?php if (!empty($c['photo_path'])): ?>
        <div class="photo-card">
          <div class="card-label" style="padding:14px 20px 0">Photo Evidence</div>
          <?php if (strpos($c['photo_path'],'http') === 0 || file_exists($c['photo_path'])): ?>
          <img class="photo-img" src="<?= htmlspecialchars($c['photo_path']) ?>" alt="Evidence">
          <?php else: ?>
          <div class="photo-placeholder">📷 Photo evidence attached (file not accessible in preview)</div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- RIGHT -->
      <div>
        <!-- SELF-ASSIGN if unassigned -->
        <?php if (!$c['officer_id']): ?>
        <div class="update-card" style="border-color:var(--g200)">
          <div class="uc-head" style="background:var(--g050)">
            <div class="uc-head-title">📥 This complaint is unassigned</div>
          </div>
          <div class="uc-body">
            <p style="font-size:.79rem;color:var(--muted);margin-bottom:14px;line-height:1.6">No officer is currently assigned to this complaint. Click below to take ownership and start working on it.</p>
            <form method="POST">
              <input type="hidden" name="action" value="self_assign">
              <button type="submit" class="assign-self">+ Assign to Me & Start</button>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <!-- UPDATE STATUS FORM -->
        <?php if ($c['officer_id'] == $uid || !$c['officer_id']): ?>
        <div class="update-card">
          <div class="uc-head">
            <span>✏️</span>
            <div class="uc-head-title">Update Complaint</div>
          </div>
          <div class="uc-body">
            <form method="POST">
              <input type="hidden" name="action" value="update_status">
              <div class="fg">
                <label class="fl">New Status</label>
                <select class="fi" name="status">
                  <?php foreach(['assigned','in_progress','resolved','closed','escalated'] as $s): ?>
                  <option value="<?= $s ?>" <?= $c['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="fg">
                <label class="fl">Officer Notes <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)">(visible to citizen)</span></label>
                <textarea class="fi" name="notes" placeholder="Describe what action was taken, any findings, or next steps…"><?= htmlspecialchars($c['officer_notes'] ?? '') ?></textarea>
              </div>
              <button type="submit" class="btn-update">Update Complaint →</button>
            </form>
          </div>
        </div>
        <?php else: ?>
        <div class="update-card">
          <div class="uc-body" style="padding:16px;text-align:center;color:var(--muted);font-size:.79rem">
            🔒 This complaint is assigned to another officer.
          </div>
        </div>
        <?php endif; ?>

        <!-- CITIZEN INFO -->
        <div class="citizen-card">
          <div class="cc-head"><div class="card-label" style="margin:0">👤 Citizen Details</div></div>
          <div class="cc-body">
            <div class="cc-row"><div class="cc-ico">👤</div><div class="cc-label">Name</div><div class="cc-val"><?= htmlspecialchars($c['citizen_name'] ?? 'N/A') ?></div></div>
            <div class="cc-row"><div class="cc-ico">✉️</div><div class="cc-label">Email</div><div class="cc-val"><?= htmlspecialchars($c['citizen_email'] ?? 'N/A') ?></div></div>
            <div class="cc-row"><div class="cc-ico">📞</div><div class="cc-label">Phone</div><div class="cc-val"><?= htmlspecialchars($c['citizen_phone'] ?? 'N/A') ?></div></div>
            <div class="cc-row"><div class="cc-ico">📍</div><div class="cc-label">Location</div><div class="cc-val"><?= htmlspecialchars($c['location']) ?></div></div>
            <?php if($c['latitude'] && $c['longitude']): ?>
            <div class="cc-row">
              <div class="cc-ico">🗺️</div>
              <div class="cc-label">GPS</div>
              <div class="cc-val">
                <a href="https://maps.google.com/?q=<?= $c['latitude'] ?>,<?= $c['longitude'] ?>" target="_blank" style="color:var(--g500);font-weight:600">View on Map →</a>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
</body>
</html>
