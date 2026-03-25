<?php
session_start();
require_once 'config.php';
if (!empty($_SESSION['user_id'])&&$_SESSION['role']==='regulator'){header('Location: regulator_dashboard.php');exit;}
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $first=$conn->real_escape_string(trim($_POST['first_name']??'')); $last=$conn->real_escape_string(trim($_POST['last_name']??''));
    $email=$conn->real_escape_string(strtolower(trim($_POST['email']??''))); $phone=$conn->real_escape_string(trim($_POST['phone']??''));
    $designation=$conn->real_escape_string(trim($_POST['designation']??'')); $dept=$conn->real_escape_string(trim($_POST['department']??''));
    $pw=$_POST['password']??''; $cpw=$_POST['confirm_password']??'';
    if ($pw!==$cpw) $message='<div class="alert a-err">⚠ Passwords do not match.</div>';
    elseif (strlen($pw)<8) $message='<div class="alert a-err">⚠ Password must be at least 8 characters.</div>';
    else{
        $chk=$conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1"); $chk->bind_param('s',$email); $chk->execute();
        if ($chk->get_result()->num_rows>0) $message='<div class="alert a-err">⚠ Email already registered. <a href="regulator_login.php">Sign in →</a></div>';
        else{
            $hash=password_hash($pw,PASSWORD_DEFAULT); $name=$first.' '.$last;
            $ins=$conn->prepare("INSERT INTO users(name,email,phone,password_hash,role,zone,is_active) VALUES(?,?,?,?,'regulator',?,0)");
            $ins->bind_param('sssss',$name,$email,$phone,$hash,$dept);
            if ($ins->execute()) $message='<div class="alert a-ok">✅ Application submitted! Senior admin will review and activate your account. You\'ll receive a confirmation email.</div>';
            else $message='<div class="alert a-err">⚠ Submission failed. Try again.</div>';
            $ins->close();
        }
        $chk->close();
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Regulator Registration — Nagrik Seva</title>
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
html{min-height:100%}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;min-height:100vh}
a{text-decoration:none;color:inherit}
.bg-canvas{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.mesh{position:absolute;border-radius:50%;filter:blur(70px);animation:drift 18s ease-in-out infinite alternate}
.m1{width:600px;height:600px;background:radial-gradient(circle,rgba(52,211,153,.18),transparent);top:-120px;left:-80px}
.m2{width:450px;height:450px;background:radial-gradient(circle,rgba(0,105,92,.1),transparent);bottom:-80px;right:-60px;animation-delay:-7s}
@keyframes drift{0%{transform:translate(0,0)}100%{transform:translate(20px,14px)}}
.dots{position:fixed;inset:0;z-index:0;background-image:radial-gradient(rgba(0,105,92,.04) 1px,transparent 1px);background-size:28px 28px;pointer-events:none}
.topbar{position:sticky;top:0;z-index:100;padding:0 44px;height:58px;display:flex;align-items:center;justify-content:space-between;background:rgba(241,250,248,.9);backdrop-filter:blur(16px);border-bottom:1px solid var(--border)}
.logo{display:flex;align-items:center;gap:12px}
.logo-ico{width:36px;height:36px;background:linear-gradient(135deg,var(--a),var(--a2));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;box-shadow:0 3px 12px var(--shadow)}
.logo-n{font-size:.88rem;font-weight:700;letter-spacing:2px;text-transform:uppercase}
.logo-t{font-size:.57rem;color:var(--muted);letter-spacing:.3px;margin-top:2px}
.topbar-r{font-size:.77rem;color:var(--muted2)}
.topbar-r a{color:var(--a);font-weight:600;margin-left:4px}
.main{position:relative;z-index:1;display:flex;min-height:calc(100vh - 58px)}
.lp{width:38%;display:flex;flex-direction:column;justify-content:center;padding:56px 48px;border-right:1px solid var(--border);position:relative;flex-shrink:0;overflow:hidden}
.lp::after{content:'';position:absolute;top:0;right:0;bottom:0;width:1px;background:linear-gradient(to bottom,transparent,var(--a2),transparent)}
.lp-inner{position:relative;z-index:1}
.role-tag{display:inline-flex;align-items:center;gap:7px;padding:5px 13px;border-radius:20px;background:var(--ag);border:1.5px solid rgba(0,105,92,.2);font-size:.63rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--a);margin-bottom:18px}
.role-tag::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--a);animation:blink 1.5s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}
.lp-title{font-size:clamp(1.9rem,2.8vw,2.6rem);font-weight:900;letter-spacing:-1.5px;line-height:1.05;margin-bottom:16px}
.lp-title em{font-style:normal;color:var(--a)}
.lp-body{font-size:.83rem;color:var(--muted2);line-height:1.8;margin-bottom:28px}
.notice{background:var(--a3);border:1.5px solid rgba(0,105,92,.2);border-radius:12px;padding:14px 16px;margin-bottom:20px}
.notice-title{font-size:.75rem;font-weight:700;color:var(--a);margin-bottom:6px;display:flex;align-items:center;gap:6px}
.notice-body{font-size:.75rem;color:var(--muted2);line-height:1.7}
.access-levels{display:flex;flex-direction:column;gap:9px;margin-top:8px}
.al{display:flex;align-items:center;gap:10px;padding:10px 13px;border-radius:10px;background:var(--white);border:1px solid var(--border)}
.al-ico{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;background:rgba(0,105,92,.1)}
.al-n{font-size:.76rem;font-weight:600}
.al-d{font-size:.65rem;color:var(--muted);margin-top:1px}
.rp{flex:1;display:flex;align-items:flex-start;justify-content:center;padding:48px 36px}
.card{width:100%;max-width:460px;animation:fadeUp .45s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.eyebrow{font-size:.62rem;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:var(--a);margin-bottom:8px}
.title{font-size:1.85rem;font-weight:900;letter-spacing:-1px;line-height:1.1;margin-bottom:6px}
.sub{font-size:.81rem;color:var(--muted2);margin-bottom:24px;line-height:1.6}
.sub a{color:var(--a);font-weight:600}
.row{display:flex;gap:11px}
.fg{margin-bottom:13px;flex:1}
.fl{display:block;font-size:.63rem;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--muted2);margin-bottom:5px}
.fi{width:100%;padding:11px 14px;background:var(--white);border:1.5px solid var(--border);border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.87rem;color:var(--text);outline:none;transition:all .25s;box-shadow:0 1px 4px rgba(0,0,0,.03)}
.fi::placeholder{color:var(--muted)}
.fi:focus{border-color:var(--a2);box-shadow:0 0 0 3px rgba(0,137,123,.12)}
.fw{position:relative}.fw .fi{padding-right:42px}
.eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.86rem;padding:0;transition:color .2s}.eye:hover{color:var(--a)}
.sep{display:flex;align-items:center;gap:9px;margin:3px 0 12px}
.sep span{flex:1;height:1px;background:var(--border)}
.sep p{font-size:.6rem;font-weight:700;color:var(--a);text-transform:uppercase;letter-spacing:1.5px}
.strength{display:flex;gap:4px;align-items:center;margin-top:6px}
.sb{flex:1;height:3px;border-radius:2px;background:var(--border);transition:.3s}
.slbl{font-size:.62rem;color:var(--muted);white-space:nowrap;min-width:38px;transition:color .3s}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,var(--a),var(--a2));color:#f0f4ff;border:none;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:.9rem;letter-spacing:.5px;cursor:pointer;transition:all .25s;margin-top:8px;box-shadow:0 4px 18px var(--shadow)}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 26px rgba(0,105,92,.28)}
.terms{font-size:.69rem;color:var(--muted);text-align:center;margin-top:13px;line-height:1.6}
.terms a{color:var(--a);font-weight:600}
.alert{padding:10px 13px;border-radius:9px;font-size:.79rem;margin-bottom:14px;line-height:1.5;border:1px solid transparent}
.a-err{background:var(--red-g);border-color:rgba(198,40,40,.15);color:var(--red)}
.a-ok{background:var(--green-g);border-color:rgba(27,94,32,.15);color:var(--green)}
.alert a{font-weight:700;color:inherit;text-decoration:underline}
@media(max-width:860px){.lp{display:none}.rp{padding:28px 16px}}
@media(max-width:480px){.row{flex-direction:column;gap:0}.topbar{padding:0 18px}}

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
</style></head><body>
<div class="bg-canvas"><div class="mesh m1"></div><div class="mesh m2"></div></div>
<div class="dots"></div>
<div class="topbar">
  <div class="logo"><div class="logo-ico">🏛️</div><div><div class="logo-n">Nagrik Seva</div><div class="logo-t">Regulator Portal</div></div></div>
  <div class="topbar-r">Already registered?<a href="regulator_login.php">Sign in →</a></div>
</div>
<div class="main">
<div class="lp"><div class="lp-inner">
  <div class="role-tag">⚖️ Regulator Registration</div>
  <div class="lp-title">Uphold civic<em>accountability.</em></div>
  <div class="lp-body">Regulators are senior government officials responsible for auditing the entire complaint resolution pipeline.</div>
  <div class="notice">
    <div class="notice-title">🔒 Senior Admin Approval Required</div>
    <div class="notice-body">Regulator accounts carry the highest access level and require approval from a senior government administrator.</div>
  </div>
  <div class="access-levels">
    <div class="al"><div class="al-ico">📊</div><div><div class="al-n">Full analytics access</div><div class="al-d">City-wide performance dashboards</div></div></div>
    <div class="al"><div class="al-ico">🔍</div><div><div class="al-n">Officer audit tools</div><div class="al-d">Review all officer actions &amp; notes</div></div></div>
    <div class="al"><div class="al-ico">📢</div><div><div class="al-n">Escalation authority</div><div class="al-d">Directly escalate stale complaints</div></div></div>
  </div>
</div></div>
<div class="rp"><div class="card">
  <div class="eyebrow">⚖️ Regulator Portal</div>
  <div class="title">Request Access</div>
  <div class="sub">Already approved? <a href="regulator_login.php">Sign in →</a></div>
  <?php if ($message) echo $message; ?>
  <form method="POST" autocomplete="off">
    <div class="row">
      <div class="fg"><label class="fl">First name</label><input class="fi" type="text" name="first_name" placeholder="Inspector" required value="<?= htmlspecialchars($_POST['first_name']??'') ?>"></div>
      <div class="fg"><label class="fl">Last name</label><input class="fi" type="text" name="last_name" placeholder="Dias" required value="<?= htmlspecialchars($_POST['last_name']??'') ?>"></div>
    </div>
    <div class="fg"><label class="fl">Official Government Email</label><input class="fi" type="email" name="email" placeholder="regulator@goa.gov.in" required value="<?= htmlspecialchars($_POST['email']??'') ?>"></div>
    <div class="fg"><label class="fl">Phone</label><input class="fi" type="tel" name="phone" placeholder="9876543210" value="<?= htmlspecialchars($_POST['phone']??'') ?>"></div>
    <div class="row">
      <div class="fg"><label class="fl">Designation</label><input class="fi" type="text" name="designation" placeholder="Joint Commissioner" value="<?= htmlspecialchars($_POST['designation']??'') ?>"></div>
      <div class="fg"><label class="fl">Department</label>
        <select class="fi" name="department">
          <option value="">-- Select --</option>
          <option value="Urban Development"<?= ($_POST['department']??'')==='Urban Development'?' selected':'' ?>>Urban Development</option>
          <option value="Public Works"<?= ($_POST['department']??'')==='Public Works'?' selected':'' ?>>Public Works</option>
          <option value="Municipal Affairs"<?= ($_POST['department']??'')==='Municipal Affairs'?' selected':'' ?>>Municipal Affairs</option>
          <option value="Health"<?= ($_POST['department']??'')==='Health'?' selected':'' ?>>Health</option>
          <option value="Other"<?= ($_POST['department']??'')==='Other'?' selected':'' ?>>Other</option>
        </select>
      </div>
    </div>
    <div class="sep"><span></span><p>🔒 Security</p><span></span></div>
    <div class="fg"><label class="fl">Password</label><div class="fw"><input class="fi" type="password" id="pw1" name="password" placeholder="Min. 8 characters" required oninput="strength(this.value)"><button type="button" class="eye" onclick="toggleEye('pw1')">👁</button></div><div class="strength"><div class="sb" id="s1"></div><div class="sb" id="s2"></div><div class="sb" id="s3"></div><div class="sb" id="s4"></div><span class="slbl" id="slbl"></span></div></div>
    <div class="fg"><label class="fl">Confirm password</label><div class="fw"><input class="fi" type="password" id="pw2" name="confirm_password" placeholder="Repeat password" required><button type="button" class="eye" onclick="toggleEye('pw2')">👁</button></div></div>
    <button type="submit" class="btn">Submit Access Request →</button>
  </form>
  <div class="terms">By submitting you agree to the <a href="#">Government Terms</a> &amp; <a href="#">Data Policy</a>.</div>
</div></div>
</div>
<script>
function toggleEye(id){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}
function strength(pw){const bars=[1,2,3,4].map(i=>document.getElementById('s'+i));const lbl=document.getElementById('slbl');let sc=0;if(pw.length>=8)sc++;if(/[A-Z]/.test(pw))sc++;if(/[0-9]/.test(pw))sc++;if(/[^A-Za-z0-9]/.test(pw))sc++;const cols=['#ef5350','#ff7043','#ffa726','#26a69a'];const labs=['Weak','Fair','Good','Strong'];bars.forEach((b,i)=>{b.style.background=i<sc?cols[sc-1]:'var(--border)';});lbl.textContent=sc?labs[sc-1]:'';lbl.style.color=sc?cols[sc-1]:'';}
</script>
</body></html>
