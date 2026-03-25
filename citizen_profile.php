<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header('Location: citizen_login.php'); exit;
}

$uid      = (int)$_SESSION['user_id'];
$name     = $_SESSION['name']  ?? '';
$email    = $_SESSION['email'] ?? '';
$initials = strtoupper(substr($name, 0, 1));

// ── Fetch full user from DB ──────────────────────────────
$st = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$st->bind_param('i', $uid); $st->execute();
$user = $st->get_result()->fetch_assoc(); $st->close();

// ── Stats ────────────────────────────────────────────────
$stats = ['total'=>0,'resolved'=>0,'pending'=>0];
$r = $conn->query("SELECT status,COUNT(*) as c FROM complaints WHERE citizen_id=$uid GROUP BY status");
while($row=$r->fetch_assoc()){
    $stats['total'] += $row['c'];
    if($row['status']==='resolved') $stats['resolved'] += $row['c'];
    if(in_array($row['status'],['new','assigned','in_progress'])) $stats['pending'] += $row['c'];
}

// ── Handle POST ──────────────────────────────────────────
$msg_ok = $msg_err = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? '';

    // UPDATE PROFILE
    if($action === 'update_profile'){
        $new_name  = trim($_POST['name']  ?? '');
        $new_phone = trim($_POST['phone'] ?? '');

        if(!$new_name){ $msg_err = 'Name cannot be empty.'; }
        else {
            $st = $conn->prepare("UPDATE users SET name=?, phone=?, updated_at=NOW() WHERE id=?");
            $st->bind_param('ssi',$new_name,$new_phone,$uid);
            $st->execute(); $st->close();
            $_SESSION['name'] = $new_name;
            $name = $new_name;
            $initials = strtoupper(substr($name,0,1));
            // Re-fetch
            $st = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
            $st->bind_param('i',$uid); $st->execute();
            $user = $st->get_result()->fetch_assoc(); $st->close();
            $msg_ok = 'Profile updated successfully.';
        }
    }

    // CHANGE PASSWORD
    elseif($action === 'change_password'){
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password']     ?? '';
        $cnf = $_POST['confirm_password'] ?? '';

        if(!$cur||!$new||!$cnf){ $msg_err = 'Please fill in all password fields.'; }
        elseif(strlen($new)<6)  { $msg_err = 'New password must be at least 6 characters.'; }
        elseif($new !== $cnf)   { $msg_err = 'New passwords do not match.'; }
        else {
            $st = $conn->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
            $st->bind_param('i',$uid); $st->execute();
            $row = $st->get_result()->fetch_assoc(); $st->close();
            if(!password_verify($cur,$row['password_hash'])){
                $msg_err = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $st = $conn->prepare("UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?");
                $st->bind_param('si',$hash,$uid); $st->execute(); $st->close();
                $msg_ok = 'Password changed successfully.';
            }
        }
    }

    // UPLOAD AVATAR
    elseif($action === 'upload_avatar'){
        if(!empty($_FILES['avatar']['name'])){
            $ext = strtolower(pathinfo($_FILES['avatar']['name'],PATHINFO_EXTENSION));
            if(!in_array($ext,['jpg','jpeg','png','webp','gif'])){
                $msg_err = 'Only JPG, PNG or WEBP allowed.';
            } else {
                $dir = 'uploads/avatars/';
                if(!is_dir($dir)) mkdir($dir,0755,true);
                $fname = 'avatar_'.$uid.'.'.$ext;
                if(move_uploaded_file($_FILES['avatar']['tmp_name'],$dir.$fname)){
                    $st = $conn->prepare("UPDATE users SET avatar=?,updated_at=NOW() WHERE id=?");
                    $apath = $dir.$fname;
                    $st->bind_param('si',$apath,$uid); $st->execute(); $st->close();
                    $user['avatar'] = $apath;
                    $msg_ok = 'Profile photo updated.';
                }
            }
        }
    }
}

// ── Recent complaints ────────────────────────────────────
$recent = [];
$r = $conn->query("SELECT * FROM complaints WHERE citizen_id=$uid ORDER BY created_at DESC LIMIT 5");
while($row=$r->fetch_assoc()) $recent[] = $row;

$cat_icon = ['road'=>'🚧','water'=>'💧','electricity'=>'⚡','sanitation'=>'🧹','property'=>'🏛️','lost'=>'🔍'];
$s_cfg = [
    'new'        =>['label'=>'New',        'cls'=>'s-new'],
    'assigned'   =>['label'=>'Assigned',   'cls'=>'s-asgn'],
    'in_progress'=>['label'=>'In Progress','cls'=>'s-prog'],
    'resolved'   =>['label'=>'Resolved',   'cls'=>'s-res'],
    'escalated'  =>['label'=>'Escalated',  'cls'=>'s-esc'],
    'closed'     =>['label'=>'Closed',     'cls'=>'s-cls'],
];
function time_ago($dt){
    if(!$dt) return '—';
    $d=time()-strtotime($dt);
    if($d<60) return 'just now';
    if($d<3600) return floor($d/60).'m ago';
    if($d<86400) return floor($d/3600).'h ago';
    return date('d M Y',strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile — Nagrik Seva</title>
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
html,body{height:100%;overflow:hidden}
body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--bg);
  color:var(--text);
  -webkit-font-smoothing:antialiased;
  display:flex;
}
body::before{
  content:'';position:fixed;inset:0;z-index:0;
  background:
    radial-gradient(ellipse 60% 50% at 10% 0%,rgba(24,207,180,.15) 0%,transparent 60%),
    radial-gradient(ellipse 50% 60% at 90% 100%,rgba(79,156,249,.15) 0%,transparent 55%),
    var(--bg);
  pointer-events:none;
}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:3px}

/* ── SIDEBAR ── */
.sidebar{width:var(--sidebar);min-width:var(--sidebar);height:100vh;background:linear-gradient(180deg,#042e2a,#065449);border-right:1px solid rgba(4,46,42,.2);display:flex;flex-direction:column;position:sticky;top:0;z-index:50;overflow-y:auto;}
.sb-logo{padding:20px 20px 18px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:11px;}
.sb-mark{width:36px;height:36px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.sb-name{font-size:.82rem;font-weight:700;color:#ffffff}
.sb-sub{font-size:.58rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.8px;margin-top:1px}
.sb-sec{padding:20px 16px 6px;font-size:.57rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,.35);}
.nav-a{display:flex;align-items:center;gap:10px;padding:9px 14px;margin:1px 8px;border-radius:8px;font-size:.8rem;font-weight:500;color:rgba(255,255,255,.65);cursor:pointer;transition:all .15s;border:1px solid transparent;text-decoration:none;}
.nav-a:hover{background:rgba(255,255,255,.1);color:#ffffff}
.nav-a.on{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.3);color:#ffffff;font-weight:600}
.nav-ico{font-size:.9rem;width:18px;text-align:center;flex-shrink:0;opacity:.7}
.nav-a.on .nav-ico{opacity:1}
.sb-foot{margin-top:auto;padding:14px 12px;border-top:1px solid rgba(255,255,255,.1)}
.u-card{display:flex;align-items:center;gap:12px;padding:11px 13px;border-radius:11px;background:rgba(255,255,255,.08) !important;border:1px solid rgba(255,255,255,.14) !important;box-shadow:none !important;}
.u-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#18cfb4,#0d8572);border:2px solid rgba(24,207,180,.6);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden;box-shadow:0 2px 10px rgba(24,207,180,.35);}
.u-av img{width:100%;height:100%;object-fit:cover}
.u-name{font-size:.82rem;font-weight:700;color:#ffffff;letter-spacing:-.1px}
.u-role{font-size:.58rem;font-weight:700;color:rgba(255,255,255,.55);margin-top:2px;text-transform:uppercase;letter-spacing:1.2px}
.u-logout{margin-left:auto;background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;padding:5px;border-radius:6px;transition:all .15s;display:flex;align-items:center;justify-content:center;}
.u-logout:hover{color:#fff;background:rgba(255,255,255,.12)}

/* ── MAIN ── */
.main{flex:1;height:100vh;overflow-y:auto;display:flex;flex-direction:column;position:relative;z-index:1;}

/* TOPBAR */
.topbar{background:rgba(224,247,250,.88);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;flex-shrink:0;}
.tb-title{font-size:.95rem;font-weight:700;color:var(--text)}
.tb-sub{font-size:.7rem;color:var(--muted)}
.tb-back{display:flex;align-items:center;gap:6px;font-size:.78rem;font-weight:600;color:var(--muted2);padding:7px 14px;border-radius:8px;background:var(--bg);border:1px solid var(--border);transition:all .14s;}
.tb-back:hover{color:var(--a);border-color:var(--a2);background:var(--a3)}

/* ── BODY ── */
.body{padding:24px 28px;flex:1}
.page-grid{display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start;}

/* ALERTS */
.toast{display:flex;align-items:center;gap:9px;padding:10px 14px;border-radius:8px;font-size:.78rem;margin-bottom:18px;border:1px solid transparent;}
.t-ok{background:var(--green-bg);border-color:rgba(0,121,107,.18);color:var(--green)}
.t-err{background:var(--red-bg);border-color:rgba(211,47,47,.18);color:var(--red)}

/* ── PROFILE CARD (left) ── */
.profile-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;}

/* COVER + AVATAR */
.pc-cover{
  height:90px;
  background:linear-gradient(135deg,var(--a) 0%,var(--a2) 100%);
  position:relative;
}
.pc-cover-pattern{
  position:absolute;inset:0;
  background-image:radial-gradient(rgba(255,255,255,.1) 1px,transparent 1px);
  background-size:18px 18px;
}
.pc-av-wrap{
  position:absolute;bottom:-28px;left:50%;transform:translateX(-50%);
  width:60px;height:60px;border-radius:50%;
  border:3px solid var(--white);
  box-shadow:0 2px 10px rgba(0,0,0,.15);
  cursor:pointer;overflow:hidden;
  background:var(--a);
}
.pc-av-wrap:hover .pc-av-overlay{opacity:1}
.pc-av{width:100%;height:100%;object-fit:cover;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800;color:#f0f4ff;}
.pc-av-overlay{
  position:absolute;inset:0;
  background:rgba(0,0,0,.45);
  display:flex;align-items:center;justify-content:center;
  opacity:0;transition:opacity .17s;
  font-size:.55rem;font-weight:700;color:#f0f4ff;text-align:center;
}

.pc-body{padding:40px 20px 20px;text-align:center;}
.pc-name{font-size:1rem;font-weight:800;color:var(--text);margin-bottom:2px;}
.pc-email{font-size:.72rem;color:var(--muted);margin-bottom:12px}
.pc-badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:4px 12px;border-radius:20px;
  background:var(--ag);border:1px solid rgba(16,158,136,.15);
  font-size:.65rem;font-weight:700;color:var(--a);
  margin-bottom:16px;
}
.pc-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:0;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:16px;}
.ps{padding:12px 8px;text-align:center;border-right:1px solid var(--border);}
.ps:last-child{border-right:none}
.ps-num{font-size:1.3rem;font-weight:800;color:var(--text);letter-spacing:-.5px;}
.ps-lbl{font-size:.58rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-top:2px;}

.pc-info{text-align:left;border-top:1px solid var(--border);padding-top:14px;}
.pi-row{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border);}
.pi-row:last-child{border-bottom:none}
.pi-ico{width:26px;height:26px;border-radius:7px;background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
.pi-label{font-size:.62rem;color:var(--muted);font-weight:500;}
.pi-val{font-size:.75rem;color:var(--text);font-weight:500;}

/* ── TABS ── */
.tabs{display:flex;gap:0;background:var(--bg2);padding:4px;border-radius:9px;margin-bottom:18px;border:1px solid var(--border);}
.tab{
  flex:1;padding:8px 12px;text-align:center;
  border-radius:6px;font-size:.75rem;font-weight:600;
  color:var(--muted);cursor:pointer;transition:all .15s;
  user-select:none;
}
.tab.on{background:var(--white);color:var(--text);box-shadow:var(--shadow);}

/* ── RIGHT COLUMN CARDS ── */
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);margin-bottom:16px;}
.card:last-child{margin-bottom:0}
.ch{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.ch-title{font-size:.85rem;font-weight:700;color:var(--text)}
.ch-sub{font-size:.65rem;color:var(--muted);margin-top:2px}
.cb{padding:18px}

/* FORM ELEMENTS */
.fg{margin-bottom:14px}
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}
.fl{display:block;font-size:.62rem;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:var(--muted2);margin-bottom:6px;}
input.fi,select.fi{
  width:100%;padding:10px 13px;
  background:var(--bg);border:1.5px solid var(--border);
  border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;
  font-size:.82rem;color:var(--text);outline:none;transition:all .17s;
}
input.fi:focus,select.fi:focus{border-color:var(--a2);background:var(--white);box-shadow:0 0 0 3px rgba(24,207,180,.1);}
input.fi::placeholder{color:var(--muted)}
input.fi:disabled{opacity:.5;cursor:not-allowed}
.fi-pw{position:relative}
.fi-pw input{padding-right:40px}
.fi-eye{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.85rem;padding:0;}
.fi-eye:hover{color:var(--text)}
.fhint{font-size:.65rem;color:var(--muted2);margin-top:4px;}

/* AVATAR UPLOAD ZONE */
.av-upload{
  border:1.5px dashed var(--border2);border-radius:10px;
  padding:18px;text-align:center;cursor:pointer;
  transition:all .17s;background:var(--bg);position:relative;
  margin-bottom:14px;
}
.av-upload:hover{border-color:var(--a);background:var(--a3)}
.av-upload input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.av-upload-ico{font-size:1.8rem;margin-bottom:6px;opacity:.5}
.av-upload-txt{font-size:.72rem;color:var(--muted)}
.av-upload-txt strong{color:var(--a)}
#av-preview{width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--border);margin:8px auto 0;display:none;}

/* SUBMIT BTN */
.btn-save{
  padding:10px 24px;border-radius:8px;
  background:var(--a);color:#f0f4ff;
  border:none;font-family:'Plus Jakarta Sans',sans-serif;
  font-size:.82rem;font-weight:700;cursor:pointer;
  transition:all .15s;
}
.btn-save:hover{background:var(--a2);transform:translateY(-1px)}
.btn-danger{background:var(--red-bg);color:var(--red);border:1px solid rgba(211,47,47,.2);}
.btn-danger:hover{background:var(--red);color:#f0f4ff}

/* RECENT COMPLAINTS */
.rc-row{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--border);}
.rc-row:last-child{border-bottom:none}
.rc-ico{width:32px;height:32px;border-radius:8px;background:var(--bg2);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.rc-body{flex:1;min-width:0}
.rc-no{font-size:.58rem;font-weight:600;color:var(--a);font-family:monospace;letter-spacing:.3px;}
.rc-title{font-size:.75rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.rc-meta{font-size:.62rem;color:var(--muted);margin-top:2px}

/* STATUS PILLS */
.pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:4px;font-size:.6rem;font-weight:700;letter-spacing:.3px;white-space:nowrap;}
.s-new{background:rgba(16,158,136,.1);color:var(--a)}
.s-asgn{background:var(--amber-bg);color:var(--amber)}
.s-prog{background:var(--blue-bg);color:var(--blue)}
.s-res{background:var(--green-bg);color:var(--green)}
.s-esc{background:var(--red-bg);color:var(--red)}
.s-cls{background:var(--bg2);color:var(--muted)}

/* DANGER ZONE */
.danger-zone{border:1px solid rgba(211,47,47,.2);border-radius:10px;padding:16px;}
.dz-title{font-size:.78rem;font-weight:700;color:var(--red);margin-bottom:4px;}
.dz-sub{font-size:.72rem;color:var(--muted);margin-bottom:12px;line-height:1.55}

/* SECTION TABS */
.tab-content{display:none}.tab-content.on{display:block}

@media(max-width:960px){
  .sidebar{display:none}
  .page-grid{grid-template-columns:1fr}
  .fg2{grid-template-columns:1fr}
}
@media(max-width:600px){.body{padding:14px}}

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
/* Sidebar already styled above — no override needed */
/* Cards */
.card,.sc,.detail-card,.map-card,.all-table,.notice,.feat,.al,.officer-note,.nd-modal{
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

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-mark">🏛️</div>
    <div><div class="sb-name">Nagrik Seva</div><div class="sb-sub">Citizen Portal</div></div>
  </div>
  <div class="sb-sec">Main</div>
  <a class="nav-a" href="citizen_dashboard.php"><span class="nav-ico">⊞</span> Dashboard</a>
  <a class="nav-a" href="citizen_dashboard.php" onclick="event.preventDefault();history.back()"><span class="nav-ico">＋</span> File Complaint</a>
  <a class="nav-a" href="citizen_dashboard.php#complaints"><span class="nav-ico">≡</span> My Complaints</a>
  <a class="nav-a" href="public_board.php"><span class="nav-ico">◎</span> Public Board</a>
  <div class="sb-sec">Account</div>
  <a class="nav-a on" href="citizen_profile.php"><span class="nav-ico">○</span> Profile</a>
  <a class="nav-a" href="track.php"><span class="nav-ico">⊙</span> Track Complaint</a>
  <div class="sb-sec">Info</div>
  <a class="nav-a" href="about.php"><span class="nav-ico">ℹ</span> About</a>
  <a class="nav-a" href="contact.php"><span class="nav-ico">✉</span> Contact</a>
  <div class="sb-foot">
    <div class="u-card">
      <div class="u-av">
        <?php if(!empty($user['avatar']) && file_exists($user['avatar'])): ?>
        <img src="<?=htmlspecialchars($user['avatar'])?>" alt="">
        <?php else: ?><?=$initials?><?php endif; ?>
      </div>
      <div>
        <div class="u-name"><?=htmlspecialchars($name)?></div>
        <div class="u-role">Citizen</div>
      </div>
      <a href="logout.php" class="u-logout" title="Sign out">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
  </div>
</aside>

<!-- ── MAIN ── -->
<div class="main">
  <div class="topbar">
    <div>
      <div class="tb-title">My Profile</div>
      <div class="tb-sub">Manage your account details and preferences</div>
    </div>
    <a href="citizen_dashboard.php" class="tb-back">← Back to Dashboard</a>
  </div>

  <div class="body">
    <?php if($msg_ok): ?><div class="toast t-ok">✓ &nbsp;<?=htmlspecialchars($msg_ok)?></div><?php endif; ?>
    <?php if($msg_err): ?><div class="toast t-err">⚠ &nbsp;<?=htmlspecialchars($msg_err)?></div><?php endif; ?>

    <div class="page-grid">

      <!-- ── LEFT — PROFILE CARD ── -->
      <div>
        <div class="profile-card">
          <div class="pc-cover">
            <div class="pc-cover-pattern"></div>
            <div class="pc-av-wrap" onclick="document.getElementById('av-file').click()" title="Change photo">
              <?php if(!empty($user['avatar']) && file_exists($user['avatar'])): ?>
              <img class="pc-av" src="<?=htmlspecialchars($user['avatar'])?>" alt="">
              <?php else: ?>
              <div class="pc-av"><?=$initials?></div>
              <?php endif; ?>
              <div class="pc-av-overlay">📷<br>Change</div>
            </div>
          </div>
          <div class="pc-body">
            <div class="pc-name"><?=htmlspecialchars($name)?></div>
            <div class="pc-email"><?=htmlspecialchars($email)?></div>
            <div class="pc-badge">👤 Citizen</div>
            <div class="pc-stats">
              <div class="ps"><div class="ps-num"><?=$stats['total']?></div><div class="ps-lbl">Total</div></div>
              <div class="ps"><div class="ps-num"><?=$stats['pending']?></div><div class="ps-lbl">Pending</div></div>
              <div class="ps"><div class="ps-num"><?=$stats['resolved']?></div><div class="ps-lbl">Resolved</div></div>
            </div>
            <div class="pc-info">
              <div class="pi-row">
                <div class="pi-ico">📧</div>
                <div><div class="pi-label">Email</div><div class="pi-val"><?=htmlspecialchars($email)?></div></div>
              </div>
              <div class="pi-row">
                <div class="pi-ico">📞</div>
                <div><div class="pi-label">Phone</div><div class="pi-val"><?=htmlspecialchars($user['phone']??'Not set')?></div></div>
              </div>
              <div class="pi-row">
                <div class="pi-ico">📅</div>
                <div><div class="pi-label">Member Since</div><div class="pi-val"><?=date('d M Y',strtotime($user['created_at']??'now'))?></div></div>
              </div>
              <div class="pi-row">
                <div class="pi-ico">🕐</div>
                <div><div class="pi-label">Last Login</div><div class="pi-val"><?=date('d M Y, H:i',strtotime($user['last_login']??'now'))?></div></div>
              </div>
            </div>
          </div>
        </div>

        <!-- RECENT COMPLAINTS MINI -->
        <?php if($recent): ?>
        <div class="card" style="margin-top:16px">
          <div class="ch"><div class="ch-title">Recent Complaints</div><a href="citizen_dashboard.php#complaints" style="font-size:.68rem;color:var(--a);font-weight:600">View all →</a></div>
          <div class="cb" style="padding:14px 18px">
            <?php foreach($recent as $rc):
              $sc2=$s_cfg[$rc['status']]??['label'=>ucfirst($rc['status']),'cls'=>'s-new'];
              $ico2=$cat_icon[$rc['category']]??'📋';
            ?>
            <div class="rc-row">
              <div class="rc-ico"><?=$ico2?></div>
              <div class="rc-body">
                <div class="rc-no"><?=htmlspecialchars($rc['complaint_no'])?></div>
                <div class="rc-title"><?=htmlspecialchars($rc['title'])?></div>
                <div class="rc-meta"><?=time_ago($rc['created_at'])?></div>
              </div>
              <span class="pill <?=$sc2['cls']?>"><?=$sc2['label']?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── RIGHT — SETTINGS ── -->
      <div>
        <!-- TABS -->
        <div class="tabs">
          <div class="tab on" onclick="switchTab('info')">Personal Info</div>
          <div class="tab" onclick="switchTab('photo')">Profile Photo</div>
          <div class="tab" onclick="switchTab('password')">Password</div>
          <div class="tab" onclick="switchTab('danger')">Account</div>
        </div>

        <!-- TAB: PERSONAL INFO -->
        <div class="tab-content on" id="tab-info">
          <div class="card">
            <div class="ch">
              <div>
                <div class="ch-title">Personal Information</div>
                <div class="ch-sub">Update your name and contact details</div>
              </div>
            </div>
            <div class="cb">
              <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="fg2">
                  <div>
                    <label class="fl" for="p-name">Full Name</label>
                    <input class="fi" type="text" id="p-name" name="name"
                           value="<?=htmlspecialchars($user['name']??'')?>" required>
                  </div>
                  <div>
                    <label class="fl" for="p-phone">Phone Number</label>
                    <input class="fi" type="tel" id="p-phone" name="phone"
                           placeholder="98765 43210"
                           value="<?=htmlspecialchars($user['phone']??'')?>">
                  </div>
                </div>
                <div class="fg">
                  <label class="fl">Email Address</label>
                  <input class="fi" type="email" value="<?=htmlspecialchars($email)?>" disabled>
                  <div class="fhint">Email cannot be changed. Contact support if needed.</div>
                </div>
                <div class="fg">
                  <label class="fl">Role</label>
                  <input class="fi" type="text" value="Citizen" disabled>
                </div>
                <button type="submit" class="btn-save">Save Changes</button>
              </form>
            </div>
          </div>
        </div>

        <!-- TAB: PHOTO -->
        <div class="tab-content" id="tab-photo">
          <div class="card">
            <div class="ch">
              <div>
                <div class="ch-title">Profile Photo</div>
                <div class="ch-sub">Upload a photo for your account</div>
              </div>
            </div>
            <div class="cb">
              <!-- Current avatar preview -->
              <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border)">
                <div style="width:72px;height:72px;border-radius:50%;overflow:hidden;border:3px solid var(--border2);background:var(--a);display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:800;color:#f0f4ff;flex-shrink:0">
                  <?php if(!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                  <img src="<?=htmlspecialchars($user['avatar'])?>" style="width:100%;height:100%;object-fit:cover" alt="">
                  <?php else: ?><?=$initials?><?php endif; ?>
                </div>
                <div>
                  <div style="font-size:.82rem;font-weight:600;color:var(--text);margin-bottom:3px"><?=htmlspecialchars($name)?></div>
                  <div style="font-size:.7rem;color:var(--muted)"><?=empty($user['avatar'])?'No photo set — using initials':'Custom photo active'?></div>
                </div>
              </div>
              <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_avatar">
                <div class="av-upload" id="av-zone">
                  <input type="file" name="avatar" id="av-file" accept="image/*" onchange="previewAvatar(this)">
                  <div class="av-upload-ico" id="av-ico">📷</div>
                  <div class="av-upload-txt" id="av-txt">Click to upload or drag a photo<br><strong>JPG, PNG, WEBP</strong> · Max 2MB</div>
                  <img id="av-preview" src="" alt="">
                </div>
                <button type="submit" class="btn-save">Upload Photo</button>
              </form>
            </div>
          </div>
        </div>

        <!-- TAB: PASSWORD -->
        <div class="tab-content" id="tab-password">
          <div class="card">
            <div class="ch">
              <div>
                <div class="ch-title">Change Password</div>
                <div class="ch-sub">Use a strong password with letters, numbers and symbols</div>
              </div>
            </div>
            <div class="cb">
              <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="fg">
                  <label class="fl" for="cur-pw">Current Password</label>
                  <div class="fi-pw">
                    <input class="fi" type="password" id="cur-pw" name="current_password" placeholder="Your current password" required>
                    <button type="button" class="fi-eye" onclick="togglePw('cur-pw')">👁</button>
                  </div>
                </div>
                <div class="fg">
                  <label class="fl" for="new-pw">New Password</label>
                  <div class="fi-pw">
                    <input class="fi" type="password" id="new-pw" name="new_password" placeholder="Min. 6 characters" required oninput="checkPwStrength(this.value)">
                    <button type="button" class="fi-eye" onclick="togglePw('new-pw')">👁</button>
                  </div>
                  <div id="pw-strength" style="margin-top:6px;display:none">
                    <div style="display:flex;gap:4px;margin-bottom:4px">
                      <div style="flex:1;height:3px;border-radius:2px;background:var(--border)" id="ps0"></div>
                      <div style="flex:1;height:3px;border-radius:2px;background:var(--border)" id="ps1"></div>
                      <div style="flex:1;height:3px;border-radius:2px;background:var(--border)" id="ps2"></div>
                      <div style="flex:1;height:3px;border-radius:2px;background:var(--border)" id="ps3"></div>
                    </div>
                    <div style="font-size:.63rem;color:var(--muted)" id="ps-hint"></div>
                  </div>
                </div>
                <div class="fg">
                  <label class="fl" for="cnf-pw">Confirm New Password</label>
                  <div class="fi-pw">
                    <input class="fi" type="password" id="cnf-pw" name="confirm_password" placeholder="Repeat new password" required>
                    <button type="button" class="fi-eye" onclick="togglePw('cnf-pw')">👁</button>
                  </div>
                </div>
                <button type="submit" class="btn-save">Update Password</button>
              </form>
            </div>
          </div>
        </div>

        <!-- TAB: ACCOUNT / DANGER ZONE -->
        <div class="tab-content" id="tab-danger">
          <div class="card">
            <div class="ch"><div class="ch-title">Account Settings</div></div>
            <div class="cb">
              <!-- Account info -->
              <div style="padding:14px;background:var(--bg);border-radius:9px;margin-bottom:16px">
                <div style="font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px">Account Details</div>
                <div style="display:flex;justify-content:space-between;font-size:.75rem;padding:5px 0;border-bottom:1px solid var(--border)"><span style="color:var(--muted)">Account ID</span><span style="font-family:monospace;color:var(--a)">#<?=str_pad($uid,6,'0',STR_PAD_LEFT)?></span></div>
                <div style="display:flex;justify-content:space-between;font-size:.75rem;padding:5px 0;border-bottom:1px solid var(--border)"><span style="color:var(--muted)">Status</span><span style="color:var(--green);font-weight:600">● Active</span></div>
                <div style="display:flex;justify-content:space-between;font-size:.75rem;padding:5px 0"><span style="color:var(--muted)">Joined</span><span><?=date('d M Y',strtotime($user['created_at']??'now'))?></span></div>
              </div>
              <!-- Sign out -->
              <div style="margin-bottom:16px">
                <div style="font-size:.75rem;font-weight:600;color:var(--text);margin-bottom:6px">Sign Out</div>
                <div style="font-size:.72rem;color:var(--muted);margin-bottom:10px">Sign out from all sessions on this device.</div>
                <a href="logout.php" class="btn-save" style="display:inline-block;background:var(--text);color:#f0f4ff;border-radius:8px;padding:9px 20px;font-size:.78rem;font-weight:700">Sign Out</a>
              </div>
              <!-- Danger zone -->
              <div class="danger-zone">
                <div class="dz-title">⚠ Danger Zone</div>
                <div class="dz-sub">Deleting your account is permanent and cannot be undone. All your complaints and data will be removed.</div>
                <button class="btn-save btn-danger" onclick="confirmDelete()">Delete My Account</button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
// Tabs
function switchTab(t){
  document.querySelectorAll('.tab').forEach((el,i)=>{
    const ids=['info','photo','password','danger'];
    el.classList.toggle('on',ids[i]===t);
    document.getElementById('tab-'+ids[i]).classList.toggle('on',ids[i]===t);
  });
}

// Password toggle
function togglePw(id){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}

// PW strength
function checkPwStrength(v){
  const wrap=document.getElementById('pw-strength');
  if(!v){wrap.style.display='none';return;}
  wrap.style.display='block';
  let s=0;
  if(v.length>=6)s++;if(v.length>=10)s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
  const cols=['#d32f2f','#b45309','#1565c0','#00796b'];
  const lbls=['Weak','Fair','Good','Strong'];
  for(let i=0;i<4;i++){
    const b=document.getElementById('ps'+i);
    b.style.background=i<s?cols[s-1]:'var(--border)';
  }
  document.getElementById('ps-hint').textContent=lbls[s-1]||'';
}

// Avatar preview
function previewAvatar(inp){
  if(inp.files&&inp.files[0]){
    const r=new FileReader();
    r.onload=e=>{
      const pr=document.getElementById('av-preview');
      pr.src=e.target.result;pr.style.display='block';
      document.getElementById('av-ico').textContent='✓';
      document.getElementById('av-txt').innerHTML='<strong>Photo ready.</strong> Click Upload to save.';
    };
    r.readAsDataURL(inp.files[0]);
  }
}

// Delete confirm
function confirmDelete(){
  if(confirm('Are you sure you want to delete your account? This cannot be undone.')){
    if(confirm('Final confirmation — all your data will be permanently deleted.')){
      window.location.href='delete_account.php';
    }
  }
}

// Auto-dismiss toast
setTimeout(()=>{const t=document.querySelector('.toast');if(t){t.style.transition='opacity .5s';t.style.opacity='0';setTimeout(()=>t.remove(),500);}},4000);

// Open correct tab if URL has hash
const hash=location.hash.replace('#','');
if(['info','photo','password','danger'].includes(hash)) switchTab(hash);
</script>
</body>
</html>