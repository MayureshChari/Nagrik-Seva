<?php
session_start();
require_once 'config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: public_board.php'); exit; }

// ── Fetch complaint ───────────────────────────────────────
$st = $conn->prepare(
    "SELECT c.*, 
            u.name  AS citizen_name,  u.email AS citizen_email,
            o.name  AS officer_name,  o.email AS officer_email,
            o.zone  AS officer_zone
     FROM complaints c
     LEFT JOIN users u ON c.citizen_id = u.id
     LEFT JOIN users o ON c.officer_id = o.id
     WHERE c.id = ? LIMIT 1"
);
$st->bind_param('i', $id);
$st->execute();
$c = $st->get_result()->fetch_assoc();
$st->close();

if (!$c) { header('Location: public_board.php'); exit; }

// ── Auth checks ───────────────────────────────────────────
$uid       = (int)($_SESSION['user_id'] ?? 0);
$role      = $_SESSION['role'] ?? '';
$is_owner  = $uid === (int)$c['citizen_id'];
$is_officer   = $role === 'officer';
$is_regulator = $role === 'regulator';
$can_update   = $is_officer || $is_regulator;

// ── Officer status update ─────────────────────────────────
$msg_ok = $msg_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_update) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        $notes      = trim($_POST['notes'] ?? '');
        $allowed    = ['assigned','in_progress','resolved','closed','escalated'];
        if (!in_array($new_status, $allowed)) {
            $msg_err = 'Invalid status.';
        } else {
            $resolved_at = $new_status === 'resolved' ? date('Y-m-d H:i:s') : $c['resolved_at'];
            $st2 = $conn->prepare("UPDATE complaints SET status=?, officer_notes=?, resolved_at=?, updated_at=NOW() WHERE id=?");
            $st2->bind_param('sssi', $new_status, $notes, $resolved_at, $id);
            $st2->execute(); $st2->close();

            // notify citizen
            $notif_msg = "Your complaint '{$c['title']}' status updated to: " . strtoupper($new_status);
            $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message) VALUES({$c['citizen_id']},$id,'status_update','".addslashes($notif_msg)."')");

            header("Location: complaint_detail.php?id=$id&updated=1"); exit;
        }
    }
}
if (isset($_GET['updated'])) $msg_ok = 'Complaint status updated successfully.';

// ── Re-fetch after possible update ───────────────────────
$st = $conn->prepare("SELECT c.*, u.name AS citizen_name, u.email AS citizen_email, o.name AS officer_name, o.email AS officer_email FROM complaints c LEFT JOIN users u ON c.citizen_id=u.id LEFT JOIN users o ON c.officer_id=o.id WHERE c.id=? LIMIT 1");
$st->bind_param('i',$id); $st->execute();
$c = $st->get_result()->fetch_assoc(); $st->close();

// ── Helpers ───────────────────────────────────────────────
$cat_icon  = ['road'=>'🚧','water'=>'💧','electricity'=>'⚡','sanitation'=>'🧹','property'=>'🏛️','lost'=>'🔍'];
$cat_label = ['road'=>'Road & Infrastructure','water'=>'Water Issues','electricity'=>'Electricity Faults','sanitation'=>'Sanitation & Garbage','property'=>'Public Property','lost'=>'Lost & Found'];
$s_cfg = [
    'new'        =>['label'=>'New',         'cls'=>'s-new',  'desc'=>'Complaint submitted, awaiting assignment'],
    'assigned'   =>['label'=>'Assigned',    'cls'=>'s-asgn', 'desc'=>'Assigned to a department officer'],
    'in_progress'=>['label'=>'In Progress', 'cls'=>'s-prog', 'desc'=>'Field team is actively working on this'],
    'resolved'   =>['label'=>'Resolved',    'cls'=>'s-res',  'desc'=>'Issue has been resolved'],
    'escalated'  =>['label'=>'Escalated',   'cls'=>'s-esc',  'desc'=>'Escalated to senior authority'],
    'closed'     =>['label'=>'Closed',      'cls'=>'s-cls',  'desc'=>'Complaint closed'],
];
$timeline_steps = ['new','assigned','in_progress','resolved'];
$current_step   = array_search($c['status'], $timeline_steps);
if ($c['status'] === 'escalated') $current_step = 1.5;
if ($c['status'] === 'closed')    $current_step = 3;

$sc = $s_cfg[$c['status']] ?? ['label'=>ucfirst($c['status']),'cls'=>'s-new','desc'=>''];

function time_ago($dt) {
    $d = time() - strtotime($dt);
    if($d<60)    return 'just now';
    if($d<3600)  return floor($d/60).'m ago';
    if($d<86400) return floor($d/3600).'h ago';
    if($d<604800)return floor($d/86400).'d ago';
    return date('d M Y', strtotime($dt));
}

$is_logged_in = !empty($_SESSION['user_id']);
$dash_link = match($role){
    'citizen'=>'citizen_dashboard.php',
    'officer'=>'officer_dashboard.php',
    'regulator'=>'regulator_dashboard.php',
    default=>'citizen_login.php'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($c['complaint_no']) ?> — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --a:#042e2a;--a2:#18cfb4;--a3:rgba(24,207,180,.12);--ag:rgba(24,207,180,.08);
  --bg:#f0fdfb;--bg2:#e2faf7;--bg3:#cef7f2;
  --white:#ffffff;--card:#ffffff;
  --text:#042e2a;--text2:#065449;
  --muted:#4a7260;--muted2:#2a7d4f;
  --border:rgba(4,46,42,.14);--border2:rgba(4,46,42,.28);
  --shadow:0 1px 4px rgba(4,46,42,.1),0 4px 14px rgba(4,46,42,.08);
  --shadow2:0 4px 20px rgba(4,46,42,.18),0 1px 4px rgba(4,46,42,.1);
  --red:#dc2626;--red-bg:rgba(220,38,38,.08);
  --green:#059669;--green-bg:rgba(5,150,105,.08);
  --amber:#d97706;--amber-bg:rgba(217,119,6,.08);
  --blue:#042e2a;--blue-bg:rgba(4,46,42,.08);
}
html,body{min-height:100vh}
body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--bg);
  color:var(--text);
  -webkit-font-smoothing:antialiased;
}
body::before{content:'';position:fixed;inset:0;z-index:-1;
  background:radial-gradient(ellipse 60% 50% at 10% 0%,rgba(24,207,180,.16) 0%,transparent 55%),
  radial-gradient(ellipse 50% 60% at 90% 100%,rgba(79,156,249,.15) 0%,transparent 50%),var(--bg);
  pointer-events:none;}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:3px}

/* ── NAV ── */
.nav{background:rgba(178,235,242,.8);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
.nav-in{max-width:1100px;margin:0 auto;padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;gap:16px;}
.nav-brand{display:flex;align-items:center;gap:10px}
.nav-mark{width:32px;height:32px;background:var(--a);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;}
.nav-name{font-size:.82rem;font-weight:700;color:var(--text)}
.nav-sub{font-size:.58rem;color:var(--muted);text-transform:uppercase;letter-spacing:.7px}
.nav-right{display:flex;align-items:center;gap:8px}
.nav-link{font-size:.78rem;font-weight:500;color:var(--muted2);padding:6px 12px;border-radius:7px;transition:all .14s;}
.nav-link:hover{background:var(--bg);color:var(--text)}
.nav-btn{padding:7px 16px;border-radius:7px;background:var(--a);color:#f0f4ff;font-size:.78rem;font-weight:600;transition:all .14s;}
.nav-btn:hover{background:var(--a2)}

/* AVATAR */
.nav-avatar{width:34px;height:34px;border-radius:50%;background:var(--a);color:#f0f4ff;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;cursor:pointer;flex-shrink:0;position:relative;transition:box-shadow .17s;text-decoration:none;}
.nav-avatar:hover{box-shadow:0 0 0 3px rgba(16,158,136,.25)}
.nav-avatar-dropdown{position:absolute;top:calc(100% + 10px);right:0;background:var(--white);border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.12);min-width:180px;opacity:0;pointer-events:none;transform:translateY(6px);transition:all .17s;z-index:200;overflow:hidden;}
.nav-avatar:hover .nav-avatar-dropdown,.nav-avatar:focus-within .nav-avatar-dropdown{opacity:1;pointer-events:all;transform:translateY(0);}
.nad-name{font-size:.78rem;font-weight:700;color:var(--text);padding:12px 14px 2px;}
.nad-role{font-size:.63rem;color:var(--muted);padding:0 14px 10px;text-transform:capitalize;}
.nad-divider{height:1px;background:var(--border);}
.nad-item{display:flex;align-items:center;padding:9px 14px;font-size:.75rem;font-weight:500;color:var(--muted2);transition:all .13s;text-decoration:none;}
.nad-item:hover{background:var(--bg);color:var(--text)}
.nad-logout{color:var(--red)!important}.nad-logout:hover{background:var(--red-bg)!important}

/* ── BREADCRUMB ── */
.breadcrumb{background:rgba(178,235,242,.5);border-bottom:1px solid var(--border);padding:10px 0;}
.bc-in{max-width:1100px;margin:0 auto;padding:0 28px;display:flex;align-items:center;gap:6px;font-size:.72rem;color:var(--muted);}
.bc-in a{color:var(--a);transition:opacity .14s}.bc-in a:hover{opacity:.7}
.bc-sep{color:var(--border2)}
.bc-cur{color:var(--text);font-weight:500}

/* ── PAGE LAYOUT ── */
.page{max-width:1100px;margin:0 auto;padding:24px 28px;display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;}

/* ── MAIN CARD ── */
.main-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;}

/* PHOTO */
.photo-wrap{
  width:100%;height:280px;
  background:var(--bg2);
  position:relative;overflow:hidden;
}
.photo-wrap img{width:100%;height:100%;object-fit:cover;}
.photo-placeholder{
  width:100%;height:100%;
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;
}
.pp-ico{font-size:3rem;opacity:.2}
.pp-txt{font-size:.75rem;color:var(--muted);opacity:.6}
.photo-badge{
  position:absolute;top:14px;left:14px;
  background:rgba(13,31,45,.72);
  backdrop-filter:blur(8px);
  padding:5px 13px;border-radius:20px;
  font-size:.67rem;font-weight:600;color:#f0f4ff;
  display:flex;align-items:center;gap:6px;
}
.photo-status{
  position:absolute;top:14px;right:14px;
}

/* COMPLAINT HEADER */
.comp-head{padding:22px 24px;border-bottom:1px solid var(--border);background:rgba(16,158,136,.03);}
.comp-no{font-size:.62rem;font-weight:700;letter-spacing:1px;color:var(--a);font-family:monospace;margin-bottom:7px;}
.comp-title{font-size:1.25rem;font-weight:800;color:var(--text);line-height:1.25;letter-spacing:-.3px;margin-bottom:12px;}
.comp-meta-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
.cm-item{display:flex;align-items:center;gap:5px;font-size:.72rem;color:var(--muted);}
.cm-ico{font-size:.78rem}

/* DESCRIPTION */
.comp-section{padding:20px 24px;border-bottom:1px solid var(--border);}
.comp-section:last-child{border-bottom:none}
.cs-label{font-size:.6rem;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--muted);margin-bottom:10px;}
.cs-text{font-size:.82rem;color:var(--muted2);line-height:1.75;}

/* TIMELINE */
.timeline{display:flex;align-items:flex-start;gap:0;position:relative;padding:4px 0 4px;}
.timeline::before{
  content:'';position:absolute;
  top:16px;left:16px;right:16px;height:2px;
  background:var(--border2);z-index:0;
}
.tl-progress{
  position:absolute;
  top:16px;left:16px;height:2px;
  background:var(--a);z-index:1;
  transition:width .5s ease;
}
.tl-step{
  flex:1;display:flex;flex-direction:column;align-items:center;
  gap:7px;position:relative;z-index:2;
}
.tl-dot{
  width:32px;height:32px;border-radius:50%;
  border:2px solid var(--border2);
  background:var(--white);
  display:flex;align-items:center;justify-content:center;
  font-size:.7rem;color:var(--muted2);
  transition:all .3s;flex-shrink:0;
}
.tl-step.done .tl-dot{background:var(--a);border-color:var(--a);color:#f0f4ff;}
.tl-step.current .tl-dot{background:var(--a2);border-color:var(--a2);color:#f0f4ff;box-shadow:0 0 0 4px rgba(24,207,180,.2);}
.tl-step.esc .tl-dot{background:var(--red);border-color:var(--red);color:#f0f4ff;}
.tl-lbl{font-size:.6rem;font-weight:600;color:var(--muted);text-align:center;letter-spacing:.2px;}
.tl-step.done .tl-lbl,.tl-step.current .tl-lbl{color:var(--text);}

/* OFFICER NOTES */
.notes-box{
  background:var(--bg);border:1px solid var(--border);
  border-left:3px solid var(--a);
  border-radius:8px;padding:14px 16px;
}
.nb-label{font-size:.6rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--a);margin-bottom:6px;}
.nb-text{font-size:.8rem;color:var(--text);line-height:1.65;}

/* GPS MAP LINK */
.gps-row{
  display:inline-flex;align-items:center;gap:8px;
  background:var(--a3);border:1px solid rgba(24,207,180,.2);
  border-radius:8px;padding:8px 14px;font-size:.75rem;
  color:var(--a2);font-weight:600;transition:all .15s;
  margin-top:6px;
}
.gps-row:hover{background:rgba(24,207,180,.15)}

/* ── SIDEBAR CARDS ── */
.side-stack{display:flex;flex-direction:column;gap:14px;position:sticky;top:78px;}
.sc{background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);overflow:hidden;}
.sch{padding:13px 16px;border-bottom:1px solid var(--border);font-size:.78rem;font-weight:700;color:var(--text);background:rgba(16,158,136,.05);}
.scb{padding:14px 16px;}

/* INFO ROWS */
.ir{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px solid var(--border);}
.ir:last-child{border-bottom:none}
.ir-k{font-size:.65rem;font-weight:600;letter-spacing:.3px;color:var(--muted);text-transform:uppercase;flex-shrink:0;padding-top:1px;}
.ir-v{font-size:.78rem;font-weight:500;color:var(--text);text-align:right;line-height:1.4;}

/* STATUS PILLS */
.pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.62rem;font-weight:700;letter-spacing:.3px;white-space:nowrap;}
.s-new{background:rgba(16,158,136,.1);color:var(--a)}
.s-asgn{background:var(--amber-bg);color:var(--amber)}
.s-prog{background:var(--blue-bg);color:var(--blue)}
.s-res{background:var(--green-bg);color:var(--green)}
.s-esc{background:var(--red-bg);color:var(--red)}
.s-cls{background:var(--bg2);color:var(--muted)}

/* OFFICER UPDATE FORM */
.update-form{display:flex;flex-direction:column;gap:10px;}
.fl{font-size:.62rem;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:var(--muted2);margin-bottom:5px;display:block;}
select.fi,textarea.fi{
  width:100%;padding:9px 12px;
  background:var(--bg);border:1.5px solid var(--border);
  border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;
  font-size:.8rem;color:var(--text);outline:none;transition:all .17s;
}
select.fi:focus,textarea.fi:focus{border-color:var(--a2);background:var(--white);box-shadow:0 0 0 3px rgba(24,207,180,.1)}
textarea.fi{resize:vertical;min-height:70px;line-height:1.6}
.btn-update{
  width:100%;padding:10px;
  background:var(--a);color:#f0f4ff;
  border:none;border-radius:8px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:.8rem;font-weight:700;
  cursor:pointer;transition:all .16s;
}
.btn-update:hover{background:var(--a2);transform:translateY(-1px)}

/* ALERT */
.toast{display:flex;align-items:center;gap:8px;padding:9px 14px;border-radius:8px;font-size:.76rem;margin-bottom:16px;border:1px solid transparent;}
.t-ok{background:var(--green-bg);border-color:rgba(0,121,107,.2);color:var(--green)}
.t-err{background:var(--red-bg);border-color:rgba(211,47,47,.2);color:var(--red)}

/* BACK BTN */
.back-link{
  display:inline-flex;align-items:center;gap:6px;
  font-size:.75rem;font-weight:600;color:var(--muted2);
  padding:6px 12px;border-radius:7px;background:var(--white);
  border:1px solid var(--border);transition:all .14s;
  margin-bottom:16px;
}
.back-link:hover{color:var(--a);border-color:var(--a2);background:var(--a3)}

/* CTA BOX */
.cta-box{
  background:var(--ag);
  border:1px solid rgba(16,158,136,.15);
  border-radius:10px;padding:16px;text-align:center;
}
.cta-box-title{font-size:.8rem;font-weight:700;color:var(--text);margin-bottom:5px;}
.cta-box-sub{font-size:.7rem;color:var(--muted);margin-bottom:12px;line-height:1.5;}
.cta-box a{
  display:block;padding:9px;border-radius:8px;
  background:var(--a);color:#f0f4ff;
  font-size:.78rem;font-weight:700;
  transition:all .15s;
}
.cta-box a:hover{background:var(--a2)}

/* RESPONSIVE */
@media(max-width:860px){.page{grid-template-columns:1fr}.side-stack{position:static}}
@media(max-width:600px){.page{padding:14px 16px}.photo-wrap{height:200px}}

/* ── LIGHT THEME OVERRIDES ── */
html,body{background:var(--bg);color:var(--text)}
/* Mesh blobs */
.bg-canvas .m1{background:radial-gradient(circle,rgba(24,207,180,.10),transparent)}
.bg-canvas .m2{background:radial-gradient(circle,rgba(109,229,210,.08),transparent)}
.bg-canvas .m3{background:radial-gradient(circle,rgba(206,247,242,.5),transparent)}
.dots{background-image:radial-gradient(rgba(4,46,42,.06) 1px,transparent 1px) !important}
/* Topbar / nav */
.topbar,.nav{
  background:rgba(255,255,255,.88) !important;
  backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border) !important;
  box-shadow:0 1px 12px rgba(4,46,42,.08) !important;
  color:var(--text) !important;
}
/* Sidebar */
.sidebar{
  background:linear-gradient(180deg,#042e2a,#065449) !important;
  border-right:1px solid rgba(4,46,42,.2) !important;
}
.sidebar *,.nav-a,.sb-logo,.sb-name,.sb-role{color:#ffffff !important}
.nav-a.on{background:rgba(255,255,255,.18) !important;border-color:rgba(255,255,255,.3) !important;color:#fff !important}
.nav-a:hover{background:rgba(255,255,255,.1) !important}
/* Cards */
.card,.sc,.detail-card,.map-card,.all-table,.notice,.u-card,.feat,.al,.officer-note,.nd-modal{
  background:#ffffff !important;
  border:1px solid var(--border) !important;
  box-shadow:0 2px 12px rgba(4,46,42,.08),inset 0 1px 0 rgba(255,255,255,.9) !important;
}
.card{border-top:1.5px solid rgba(4,46,42,.25) !important}
.sc:hover,.card:hover{
  box-shadow:0 6px 24px rgba(4,46,42,.14),0 1px 4px rgba(4,46,42,.08) !important;
  transform:translateY(-2px);
}
/* Left panel (login/register) */
.lp{
  background:linear-gradient(160deg,#042e2a 0%,#065449 60%,#18cfb4 100%) !important;
  border-right:1px solid rgba(255,255,255,.12) !important;
}
.lp,.lp *,.lp .hl,.lp .hl-sub,.lp .logo-n,.lp .logo-t,.lp .foot-txt,
.lp .role-badge,.lp .duty-n,.lp .duty-d,.lp .stat-l,.lp .stat-n,
.lp .op,.lp .lp-foot *{color:#ffffff !important}
.lp .role-badge{background:rgba(255,255,255,.15) !important;border-color:rgba(255,255,255,.25) !important}
.lp .duty{background:rgba(255,255,255,.1) !important;border-color:rgba(255,255,255,.15) !important}
.lp .duty:hover{background:rgba(255,255,255,.18) !important;border-color:rgba(255,255,255,.3) !important}
.lp .op{background:rgba(255,255,255,.12) !important;border-color:rgba(255,255,255,.2) !important}
.lp .op:hover{background:rgba(255,255,255,.22) !important}
.lp .hl em{color:#adf2e8 !important}
.lp .stat-n{color:#adf2e8 !important}
/* Right panel */
.rp{background:transparent !important}
/* Inputs */
input.fi,select.fi,textarea.fi,.fi,.ob,.search-input{
  background:#ffffff !important;
  border:1.5px solid var(--border) !important;
  color:#042e2a !important;
  box-shadow:0 1px 3px rgba(4,46,42,.06) !important;
}
input.fi::placeholder,textarea.fi::placeholder{color:var(--muted) !important}
input.fi:focus,select.fi:focus,textarea.fi:focus,.fi:focus,.ob:focus,.search-input:focus{
  border-color:rgba(4,46,42,.5) !important;
  box-shadow:0 0 0 3px rgba(4,46,42,.1),0 1px 3px rgba(4,46,42,.06) !important;
  background:#ffffff !important;
}
/* Primary buttons — dark blue bg, white text */
.btn,.btn-submit,.tb-btn,.search-btn,.dc-btn-primary,.nd-act-primary,.demo-btn{
  background:linear-gradient(135deg,#042e2a,#18cfb4) !important;
  color:#ffffff !important;
  box-shadow:0 4px 14px rgba(4,46,42,.25) !important;
  border:none !important;
}
.btn:hover,.btn-submit:hover,.tb-btn:hover,.search-btn:hover,.demo-btn:hover{
  box-shadow:0 8px 24px rgba(4,46,42,.35) !important;
  transform:translateY(-2px) !important;
}
/* Ghost / secondary buttons */
.ch-act,.np-mark-btn,.dc-btn-ghost,.nd-act-sec,.g-btn{
  background:#ffffff !important;
  border:1.5px solid var(--border) !important;
  color:var(--text) !important;
}
.ch-act:hover,.np-mark-btn:hover,.g-btn:hover{
  border-color:rgba(4,46,42,.4) !important;
  background:#f0f4ff !important;
}
/* OTP badge */
.otp-badge{background:rgba(4,46,42,.07) !important;border-color:var(--border) !important;color:var(--muted2) !important}
/* Dev OTP box */
.dev-box{background:#fffbeb !important;border-color:#f59e0b !important}
/* Alerts */
.a-err,.t-err{background:rgba(220,38,38,.07) !important;border-color:rgba(220,38,38,.2) !important;color:#dc2626 !important}
.a-ok,.t-ok{background:rgba(5,150,105,.07) !important;border-color:rgba(5,150,105,.2) !important;color:#059669 !important}
.a-info{background:rgba(4,46,42,.07) !important;border-color:var(--border) !important;color:var(--text) !important}
/* Stat card accents */
.sc-ico-a{background:rgba(4,46,42,.1) !important;border-color:rgba(4,46,42,.2) !important}
.sc-ico-b{background:rgba(24,207,180,.08) !important;border-color:rgba(24,207,180,.15) !important}
.sc-ico-c{background:rgba(109,229,210,.1) !important;border-color:rgba(109,229,210,.18) !important}
.sc-ico-d{background:rgba(5,150,105,.08) !important;border-color:rgba(5,150,105,.15) !important}
/* Progress */
.progress-bar{background:rgba(4,46,42,.1) !important}
.progress-fill{background:linear-gradient(90deg,#042e2a,#18cfb4) !important}
/* Complaint rows */
.cr,.at-row,.np-item{border-bottom-color:var(--border) !important}
.cr:hover,.at-row:hover,.np-item:hover{background:rgba(4,46,42,.04) !important}
/* Status pills */
.s-new{background:rgba(4,46,42,.1) !important;color:#042e2a !important}
/* Public board filter chips */
.fchip{background:#ffffff !important;border-color:var(--border) !important;color:var(--muted2) !important}
.fchip.on,.fchip:hover{background:rgba(4,46,42,.1) !important;border-color:rgba(4,46,42,.3) !important;color:var(--text) !important}
/* Filter bar */
.filter-bar,.filters-row{background:rgba(255,255,255,.9) !important;border-color:var(--border) !important}
/* Hero */
.hero{background:linear-gradient(135deg,#042e2a 0%,#065449 50%,#18cfb4 100%) !important}
.hero *{color:#ffffff !important}
.hero .search-card{background:rgba(255,255,255,.12) !important}
.search-card{background:rgba(255,255,255,.95) !important;box-shadow:0 -4px 20px rgba(4,46,42,.1) !important}
/* Quick actions */
.qa{background:rgba(4,46,42,.07) !important;border-color:var(--border) !important;color:var(--text) !important}
.qa:hover{background:rgba(4,46,42,.14) !important;border-color:rgba(4,46,42,.3) !important}
/* Notification panel */
.notif-panel{background:linear-gradient(180deg,#042e2a,#065449) !important;border-left:1px solid rgba(255,255,255,.15) !important}
.notif-panel *,.np-head *,.np-item *,.np-title,.np-msg,.np-time,.np-close{color:#ffffff !important}
.np-head{background:rgba(255,255,255,.1) !important;border-bottom:1px solid rgba(255,255,255,.15) !important}
.np-item:hover{background:rgba(255,255,255,.08) !important}
.np-mark-btn{background:rgba(255,255,255,.15) !important;border-color:rgba(255,255,255,.2) !important;color:#fff !important}
.np-close{background:rgba(255,255,255,.1) !important;border-color:rgba(255,255,255,.15) !important;color:#fff !important}
.notif-backdrop.on{background:rgba(4,46,42,.25) !important}
/* Modal */
.modal,.overlay .modal,.nd-modal{
  background:#ffffff !important;
  border:1px solid var(--border) !important;
  box-shadow:0 24px 64px rgba(4,46,42,.18) !important;
}
.overlay{background:rgba(4,46,42,.25) !important}
.del-btn-confirm{background:#dc2626 !important;color:#fff !important;box-shadow:0 4px 14px rgba(220,38,38,.25) !important}
.del-btn-cancel{background:#ffffff !important;border:1.5px solid var(--border) !important;color:var(--text) !important}
/* Category pick */
.cat-btn{background:#ffffff !important;border-color:var(--border) !important;color:var(--text) !important}
.cat-btn.sel,.cat-btn:hover{background:rgba(4,46,42,.1) !important;border-color:rgba(4,46,42,.3) !important}
/* Upzone */
.upzone{background:#f8faff !important;border-color:rgba(4,46,42,.25) !important}
.upzone:hover,.upzone.drag{background:rgba(4,46,42,.06) !important;border-color:rgba(4,46,42,.4) !important}
/* GPS button */
.gps-fi{background:#ffffff !important;border-color:var(--border) !important;color:var(--muted) !important}
.gps-fi:hover{border-color:rgba(4,46,42,.4) !important;color:var(--text) !important;background:#f0f4ff !important}
/* Profile */
.profile-card,.p-card,.ps-sect,.ps,.pi-row{background:#ffffff !important;border-color:var(--border) !important}
/* Step dots */
.sd{background:rgba(4,46,42,.2) !important}
.sd.on{background:#042e2a !important;box-shadow:0 0 8px rgba(4,46,42,.3) !important}
.sd.done{background:#18cfb4 !important}
/* Scrollbar */
::-webkit-scrollbar-track{background:#e8eeff}
::-webkit-scrollbar-thumb{background:rgba(4,46,42,.25)}
/* Officer note */
.officer-note{background:#f8faff !important;border-color:var(--border) !important}
/* Leaflet */
.leaflet-control{background:#ffffff !important;border-color:var(--border) !important;color:var(--text) !important}
.leaflet-control-attribution{background:rgba(255,255,255,.9) !important;color:var(--muted) !important}
/* Step items */
.step-item.done .step-dot{background:#042e2a !important;border-color:#042e2a !important}
.step-item.current .step-dot{background:#18cfb4 !important;border-color:#18cfb4 !important;box-shadow:0 0 0 3px rgba(4,46,42,.15) !important}
/* Feat boxes on register */
.feat{background:#f8faff !important;border-color:var(--border) !important}
.feat:hover{background:#f0f4ff !important;border-color:rgba(4,46,42,.28) !important}
.feat-ico{background:rgba(4,46,42,.1) !important}
/* Notice / es-tips */
.notice,.es-tips{background:#f8faff !important;border-color:var(--border) !important}
/* access level */
.al{background:#f8faff !important;border-color:var(--border) !important}
/* officer block */
.dc-officer-row.assigned{background:rgba(4,46,42,.06) !important;border-color:rgba(4,46,42,.15) !important}
.dc-officer-row.unassigned{background:rgba(217,119,6,.05) !important;border-color:rgba(217,119,6,.15) !important}
/* strength bar */
.sb{background:rgba(4,46,42,.1) !important}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <div class="nav-in">
    <div class="nav-brand">
      <div class="nav-mark">🏛️</div>
      <div><div class="nav-name">Nagrik Seva</div><div class="nav-sub">Goa Civic Portal</div></div>
    </div>
    <div class="nav-right">
      <a href="index.php" class="nav-link">Home</a>
      <a href="public_board.php" class="nav-link">Public Board</a>
      <?php if($is_logged_in): ?>
      <a href="<?= $dash_link ?>" class="nav-avatar" title="<?= htmlspecialchars($_SESSION['name'] ?? '') ?>">
        <?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?>
        <div class="nav-avatar-dropdown">
          <div class="nad-name"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></div>
          <div class="nad-role"><?= ucfirst($_SESSION['role'] ?? 'citizen') ?></div>
          <div class="nad-divider"></div>
          <a class="nad-item" href="<?= $dash_link ?>">⊞ &nbsp;Dashboard</a>
          <a class="nad-item nad-logout" href="logout.php">↩ &nbsp;Sign Out</a>
        </div>
      </a>
      <?php else: ?>
      <a href="citizen_login.php" class="nav-link">Sign In</a>
      <a href="citizen_register.php" class="nav-btn">File Complaint →</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- BREADCRUMB -->
<div class="breadcrumb">
  <div class="bc-in">
    <a href="index.php">Home</a>
    <span class="bc-sep">›</span>
    <a href="public_board.php">Public Board</a>
    <span class="bc-sep">›</span>
    <span class="bc-cur"><?= htmlspecialchars($c['complaint_no']) ?></span>
  </div>
</div>

<!-- PAGE -->
<div class="page">

  <!-- ── MAIN COLUMN ── -->
  <div>
    <a href="public_board.php" class="back-link">← Back to Public Board</a>

    <?php if($msg_ok): ?><div class="toast t-ok">✓ &nbsp;<?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
    <?php if($msg_err): ?><div class="toast t-err">⚠ &nbsp;<?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

    <div class="main-card">

      <!-- PHOTO -->
      <div class="photo-wrap">
        <?php if($c['photo_path'] && file_exists($c['photo_path'])): ?>
        <img src="<?= htmlspecialchars($c['photo_path']) ?>" alt="Complaint photo">
        <?php else: ?>
        <div class="photo-placeholder">
          <div class="pp-ico"><?= $cat_icon[$c['category']] ?? '📋' ?></div>
          <div class="pp-txt">No photo attached</div>
        </div>
        <?php endif; ?>
        <div class="photo-badge">
          <?= $cat_icon[$c['category']] ?? '📋' ?> &nbsp;<?= $cat_label[$c['category']] ?? ucfirst($c['category']) ?>
        </div>
        <div class="photo-status">
          <span class="pill <?= $sc['cls'] ?>"><?= $sc['label'] ?></span>
        </div>
      </div>

      <!-- HEADER -->
      <div class="comp-head">
        <div class="comp-no"><?= htmlspecialchars($c['complaint_no']) ?></div>
        <div class="comp-title"><?= htmlspecialchars($c['title']) ?></div>
        <div class="comp-meta-row">
          <span class="cm-item"><span class="cm-ico">📍</span> <?= htmlspecialchars($c['location']) ?></span>
          <span class="cm-item"><span class="cm-ico">🕐</span> Filed <?= time_ago($c['created_at']) ?></span>
          <?php if($c['zone']): ?>
          <span class="cm-item"><span class="cm-ico">🏙️</span> <?= htmlspecialchars($c['zone']) ?></span>
          <?php endif; ?>
          <?php if($c['resolved_at']): ?>
          <span class="cm-item"><span class="cm-ico">✓</span> Resolved <?= time_ago($c['resolved_at']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- TIMELINE -->
      <div class="comp-section">
        <div class="cs-label">Resolution Progress</div>
        <?php
        $steps    = [
            ['key'=>'new',         'icon'=>'○', 'label'=>'Submitted'],
            ['key'=>'assigned',    'icon'=>'◎', 'label'=>'Assigned'],
            ['key'=>'in_progress', 'icon'=>'◷', 'label'=>'In Progress'],
            ['key'=>'resolved',    'icon'=>'✓', 'label'=>'Resolved'],
        ];
        $order_map = ['new'=>0,'assigned'=>1,'in_progress'=>2,'resolved'=>3,'closed'=>3,'escalated'=>2];
        $cur_order = $order_map[$c['status']] ?? 0;
        $pct       = $c['status']==='escalated' ? 50 : ($cur_order / (count($steps)-1) * 100);
        ?>
        <div class="timeline">
          <div class="tl-progress" style="width:calc(<?= $pct ?>% - 32px)"></div>
          <?php foreach($steps as $i=>$s):
            $s_order = $order_map[$s['key']] ?? $i;
            $cls = '';
            if ($c['status'] === 'escalated' && $s['key'] === 'in_progress') $cls = 'esc';
            elseif ($s_order < $cur_order) $cls = 'done';
            elseif ($s_order === $cur_order) $cls = 'current';
          ?>
          <div class="tl-step <?= $cls ?>">
            <div class="tl-dot"><?= $cls==='done'?'✓': ($cls==='esc'?'!':$s['icon']) ?></div>
            <div class="tl-lbl"><?= $s['label'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if($c['status']==='escalated'): ?>
        <div style="margin-top:10px;font-size:.72rem;color:var(--red);background:var(--red-bg);padding:7px 12px;border-radius:7px;border:1px solid rgba(211,47,47,.15)">
          ⚠ This complaint has been escalated to senior authority for priority resolution.
        </div>
        <?php endif; ?>
      </div>

      <!-- DESCRIPTION -->
      <?php if($c['description']): ?>
      <div class="comp-section">
        <div class="cs-label">Description</div>
        <div class="cs-text"><?= nl2br(htmlspecialchars($c['description'])) ?></div>
      </div>
      <?php endif; ?>

      <!-- OFFICER NOTES -->
      <?php if($c['officer_notes']): ?>
      <div class="comp-section">
        <div class="cs-label">Officer Notes</div>
        <div class="notes-box">
          <div class="nb-label">Official Response</div>
          <div class="nb-text"><?= nl2br(htmlspecialchars($c['officer_notes'])) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- GPS -->
      <?php if($c['latitude'] && $c['longitude']): ?>
      <div class="comp-section">
        <div class="cs-label">Location Coordinates</div>
        <div class="cs-text"><?= $c['latitude'] ?>, <?= $c['longitude'] ?></div>
        <a class="gps-row" href="https://maps.google.com/?q=<?= $c['latitude'] ?>,<?= $c['longitude'] ?>" target="_blank">
          📍 View on Google Maps →
        </a>
      </div>
      <?php endif; ?>

    </div><!-- /main-card -->
  </div>

  <!-- ── SIDEBAR ── -->
  <div class="side-stack">

    <!-- STATUS CARD -->
    <div class="sc">
      <div class="sch">Complaint Status</div>
      <div class="scb">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border)">
          <span class="pill <?= $sc['cls'] ?>" style="font-size:.7rem;padding:4px 12px"><?= $sc['label'] ?></span>
          <span style="font-size:.7rem;color:var(--muted)"><?= $sc['desc'] ?></span>
        </div>
        <div class="ir"><span class="ir-k">ID</span><span class="ir-v" style="font-family:monospace;color:var(--a)"><?= htmlspecialchars($c['complaint_no']) ?></span></div>
        <div class="ir"><span class="ir-k">Category</span><span class="ir-v"><?= $cat_icon[$c['category']]??'' ?> <?= $cat_label[$c['category']]??ucfirst($c['category']) ?></span></div>
        <div class="ir"><span class="ir-k">Priority</span><span class="ir-v"><?= ucfirst($c['priority'] ?? 'Medium') ?></span></div>
        <div class="ir"><span class="ir-k">Zone</span><span class="ir-v"><?= htmlspecialchars($c['zone'] ?? '—') ?></span></div>
        <div class="ir"><span class="ir-k">Filed</span><span class="ir-v"><?= date('d M Y, H:i', strtotime($c['created_at'])) ?></span></div>
        <?php if($c['resolved_at']): ?>
        <div class="ir"><span class="ir-k">Resolved</span><span class="ir-v"><?= date('d M Y, H:i', strtotime($c['resolved_at'])) ?></span></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- FILED BY -->
    <div class="sc">
      <div class="sch">Filed By</div>
      <div class="scb">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
          <div style="width:36px;height:36px;border-radius:50%;background:var(--ag);border:1px solid rgba(16,158,136,.2);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;color:var(--a);flex-shrink:0">
            <?= strtoupper(substr($c['citizen_name']??'C',0,1)) ?>
          </div>
          <div>
            <div style="font-size:.8rem;font-weight:600;color:var(--text)"><?= htmlspecialchars($c['citizen_name']??'Citizen') ?></div>
            <div style="font-size:.65rem;color:var(--muted)">Citizen</div>
          </div>
        </div>
        <?php if($c['officer_name']): ?>
        <div style="border-top:1px solid var(--border);padding-top:12px">
          <div style="font-size:.6rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Assigned Officer</div>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;border-radius:50%;background:rgba(24,207,180,.1);border:1px solid rgba(24,207,180,.2);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--a2);flex-shrink:0">
              <?= strtoupper(substr($c['officer_name'],0,1)) ?>
            </div>
            <div>
              <div style="font-size:.78rem;font-weight:600;color:var(--text)"><?= htmlspecialchars($c['officer_name']) ?></div>
              <div style="font-size:.63rem;color:var(--muted)">Department Officer</div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- OFFICER UPDATE PANEL -->
    <?php if($can_update): ?>
    <div class="sc" style="border-top:3px solid var(--a)">
      <div class="sch">Update Status</div>
      <div class="scb">
        <form method="POST" class="update-form">
          <input type="hidden" name="action" value="update_status">
          <div>
            <label class="fl" for="new-status">New Status</label>
            <select class="fi" id="new-status" name="status" required>
              <option value="">— Select status —</option>
              <option value="assigned"    <?= $c['status']==='assigned'   ?'selected':'' ?>>Assigned</option>
              <option value="in_progress" <?= $c['status']==='in_progress'?'selected':'' ?>>In Progress</option>
              <option value="resolved"    <?= $c['status']==='resolved'   ?'selected':'' ?>>Resolved</option>
              <option value="escalated"   <?= $c['status']==='escalated'  ?'selected':'' ?>>Escalated</option>
              <option value="closed"      <?= $c['status']==='closed'     ?'selected':'' ?>>Closed</option>
            </select>
          </div>
          <div>
            <label class="fl" for="notes">Officer Notes</label>
            <textarea class="fi" id="notes" name="notes" placeholder="Add an update or resolution note…"><?= htmlspecialchars($c['officer_notes']??'') ?></textarea>
          </div>
          <button type="submit" class="btn-update">Save Update →</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- CTA for guests -->
    <?php if(!$is_logged_in): ?>
    <div class="cta-box">
      <div class="cta-box-title">Have a similar issue?</div>
      <div class="cta-box-sub">Register free and file your own complaint. We'll track it to resolution.</div>
      <a href="citizen_register.php">File a Complaint →</a>
    </div>
    <?php endif; ?>

    <!-- SHARE -->
    <div class="sc">
      <div class="sch">Share</div>
      <div class="scb" style="display:flex;flex-direction:column;gap:8px">
        <button onclick="copyLink()" style="width:100%;padding:8px;border-radius:7px;background:var(--bg);border:1px solid var(--border);font-family:'Plus Jakarta Sans',sans-serif;font-size:.75rem;font-weight:600;color:var(--text);cursor:pointer;transition:all .14s;" id="copy-btn">
          🔗 &nbsp;Copy Link
        </button>
        <a href="https://wa.me/?text=<?= urlencode('Check this civic complaint on Nagrik Seva: '.htmlspecialchars($c['title']).' — '.'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>" target="_blank"
           style="display:block;width:100%;padding:8px;border-radius:7px;background:#dcfce7;border:1px solid #bbf7d0;font-size:.75rem;font-weight:600;color:#15803d;text-align:center;transition:all .14s;">
          📲 &nbsp;Share on WhatsApp
        </a>
      </div>
    </div>

  </div><!-- /side-stack -->
</div><!-- /page -->

<!-- FOOTER -->
<div style="background:var(--white);border-top:1px solid var(--border);padding:18px 28px;text-align:center;font-size:.7rem;color:var(--muted);">
  © 2026 Government of Goa · Nagrik Seva v3.0 &nbsp;·&nbsp;
  <a href="public_board.php" style="color:var(--a)">Public Board</a> &nbsp;·&nbsp;
  <a href="citizen_login.php" style="color:var(--a)">Sign In</a>
</div>

<script>
function copyLink(){
  navigator.clipboard.writeText(window.location.href).then(()=>{
    const btn=document.getElementById('copy-btn');
    btn.textContent='✓ Link Copied!';
    btn.style.background='var(--a3)';btn.style.borderColor='var(--a2)';btn.style.color='var(--a)';
    setTimeout(()=>{btn.textContent='🔗  Copy Link';btn.style='';},2000);
  });
}
// Auto dismiss toast
setTimeout(()=>{const t=document.querySelector('.toast');if(t)t.style.transition='opacity .5s',t.style.opacity='0',setTimeout(()=>t?.remove(),500);},4000);
</script>
</body>
</html>
