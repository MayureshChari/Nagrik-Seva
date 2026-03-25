<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header('Location: citizen_login.php'); exit;
}

$uid      = (int)$_SESSION['user_id'];
$name     = $_SESSION['name'] ?? 'Ramesh Naik';
$initials = strtoupper(substr($name, 0, 1));

// ── Stats ──
$stats = ['total'=>0,'new'=>0,'in_progress'=>0,'resolved'=>0];
$r = $conn->query("SELECT status, COUNT(*) as c FROM complaints WHERE citizen_id=$uid GROUP BY status");
while ($row = $r->fetch_assoc()) {
    $stats['total'] += $row['c'];
    if (array_key_exists($row['status'], $stats)) $stats[$row['status']] = (int)$row['c'];
}

// ── Complaints ──
$complaints = [];
$r = $conn->query("SELECT c.*, u.name as officer_name FROM complaints c LEFT JOIN users u ON c.officer_id = u.id WHERE c.citizen_id = $uid ORDER BY c.created_at DESC LIMIT 20");
while ($row = $r->fetch_assoc()) $complaints[] = $row;

// ── Dummy fallback ──
if (empty($complaints)) {
    $stats = ['total'=>7,'new'=>2,'in_progress'=>3,'resolved'=>2];
    $complaints = [
        ['id'=>1,'complaint_no'=>'GRV-A4C7E2','category'=>'road',       'title'=>'Large pothole near NH17 petrol pump, Panaji',    'location'=>'Panaji, North Goa',     'status'=>'in_progress','created_at'=>date('Y-m-d H:i:s',strtotime('-4 days')), 'photo_path'=>null,            'officer_name'=>'Suresh Kamat'],
        ['id'=>2,'complaint_no'=>'GRV-B3D9F1','category'=>'water',      'title'=>'Burst water pipe flooding street — Caranzalem',  'location'=>'Caranzalem, Panaji',    'status'=>'assigned',   'created_at'=>date('Y-m-d H:i:s',strtotime('-6 days')), 'photo_path'=>null,            'officer_name'=>'Priya Dessai'],
        ['id'=>3,'complaint_no'=>'GRV-C8A2B4','category'=>'electricity','title'=>'Street light outage — Miramar Beach Road',        'location'=>'Miramar, Panaji',       'status'=>'in_progress','created_at'=>date('Y-m-d H:i:s',strtotime('-8 days')), 'photo_path'=>'placeholder.jpg','officer_name'=>null],
        ['id'=>4,'complaint_no'=>'GRV-D5E1C9','category'=>'sanitation', 'title'=>'Overflowing garbage bins near Panjim Market',    'location'=>'Panjim Market, Panaji', 'status'=>'resolved',   'created_at'=>date('Y-m-d H:i:s',strtotime('-11 days')),'photo_path'=>null,            'officer_name'=>'Anita Borkar'],
        ['id'=>5,'complaint_no'=>'GRV-E2F7A3','category'=>'road',       'title'=>'Broken footpath tiles causing hazard — Altinho', 'location'=>'Altinho, Panaji',       'status'=>'new',        'created_at'=>date('Y-m-d H:i:s',strtotime('-15 days')),'photo_path'=>null,            'officer_name'=>null],
        ['id'=>6,'complaint_no'=>'GRV-F9B4D8','category'=>'water',      'title'=>'No water supply for 3 days — Dona Paula Ward',   'location'=>'Dona Paula, Panaji',    'status'=>'in_progress','created_at'=>date('Y-m-d H:i:s',strtotime('-20 days')),'photo_path'=>'placeholder.jpg','officer_name'=>'Rajan Naik'],
        ['id'=>7,'complaint_no'=>'GRV-G1C6E5','category'=>'lost',       'title'=>'Lost wallet — Goa Secretariat, 15 May 2025',     'location'=>'Secretariat, Panaji',   'status'=>'new',        'created_at'=>date('Y-m-d H:i:s',strtotime('-32 days')),'photo_path'=>null,            'officer_name'=>null],
    ];
}

// ── Notifications ──
$notifs = [];
$r = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 8");
while ($row = $r->fetch_assoc()) $notifs[] = $row;
$unread = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0")->fetch_assoc()['c'];

if (empty($notifs)) {
    $unread = 3;
    $notifs = [
        ['id'=>1,'complaint_id'=>1,'type'=>'in_progress','message'=>'GRV-A4C7E2 updated to In Progress. Officer Suresh Kamat assigned.',        'created_at'=>date('Y-m-d H:i:s',strtotime('-2 hours')), 'is_read'=>0],
        ['id'=>2,'complaint_id'=>2,'type'=>'assigned',   'message'=>'GRV-B3D9F1 assigned to Officer Priya Dessai (Water & Sanitation Dept).', 'created_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),   'is_read'=>0],
        ['id'=>3,'complaint_id'=>4,'type'=>'resolved',   'message'=>'GRV-D5E1C9 — Garbage bins near Panjim Market — marked Resolved. ✅',      'created_at'=>date('Y-m-d H:i:s',strtotime('-9 days')),  'is_read'=>0],
        ['id'=>4,'complaint_id'=>5,'type'=>'submitted',  'message'=>'GRV-E2F7A3 submitted successfully. Pending officer review.',              'created_at'=>date('Y-m-d H:i:s',strtotime('-15 days')), 'is_read'=>1],
        ['id'=>5,'complaint_id'=>7,'type'=>'submitted',  'message'=>'GRV-G1C6E5 — Lost wallet report — received and under review.',           'created_at'=>date('Y-m-d H:i:s',strtotime('-32 days')), 'is_read'=>1],
    ];
}

if (isset($_GET['read_notifs'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    header('Location: citizen_dashboard.php'); exit;
}

// ── File complaint ──
$post_error = $post_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'file_complaint') {
    $title = trim($_POST['title'] ?? '');
    $cat   = trim($_POST['category'] ?? '');
    $loc   = trim($_POST['location'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $lat   = (float)($_POST['lat'] ?? 0);
    $lng   = (float)($_POST['lng'] ?? 0);
    if (!$title || !$cat || !$loc) {
        $post_error = 'Title, category and location are required.';
    } else {
        $photo_path = null;
        if (!empty($_FILES['photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $post_error = 'Only JPG, PNG or WEBP images allowed.';
            } else {
                $dir = 'uploads/complaints/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'comp_'.$uid.'_'.time().'.'.$ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir.$fname)) $photo_path = $dir.$fname;
            }
        }
        if (!$post_error) {
            $cno = 'GRV-'.strtoupper(substr(md5(uniqid()), 0, 6));
            $st = $conn->prepare("INSERT INTO complaints(complaint_no,citizen_id,category,title,description,location,latitude,longitude,photo_path,status,priority) VALUES(?,?,?,?,?,?,?,?,?,'new','medium')");
            $st->bind_param('sissssdds', $cno,$uid,$cat,$title,$desc,$loc,$lat,$lng,$photo_path);
            $st->execute();
            $nid = $st->insert_id; $st->close();
            $msg = "Complaint '$title' submitted. Reference: $cno";
            $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message) VALUES($uid,$nid,'submitted','".addslashes($msg)."')");
            header('Location: citizen_dashboard.php?success=1'); exit;
        }
    }
}
if (isset($_GET['success'])) $post_success = 'Complaint submitted successfully.';

$cat_icon = ['road'=>'🚧','water'=>'💧','electricity'=>'⚡','sanitation'=>'🧹','property'=>'🏛️','lost'=>'🔍'];
$s_cfg = [
    'new'        =>['label'=>'New',        'cls'=>'s-new'],
    'assigned'   =>['label'=>'Assigned',   'cls'=>'s-assigned'],
    'in_progress'=>['label'=>'In Progress','cls'=>'s-prog'],
    'resolved'   =>['label'=>'Resolved',   'cls'=>'s-resolved'],
    'closed'     =>['label'=>'Closed',     'cls'=>'s-closed'],
    'escalated'  =>['label'=>'Escalated',  'cls'=>'s-esc'],
];
$notif_icons = [
    'submitted'  =>['ico'=>'📥','cls'=>'nt-a'],
    'assigned'   =>['ico'=>'👮','cls'=>'nt-b'],
    'in_progress'=>['ico'=>'🔧','cls'=>'nt-c'],
    'resolved'   =>['ico'=>'✅','cls'=>'nt-d'],
    'escalated'  =>['ico'=>'🚨','cls'=>'nt-e'],
    'message'    =>['ico'=>'💬','cls'=>'nt-a'],
    'info'       =>['ico'=>'ℹ️','cls'=>'nt-b'],
];
$hour = (int)date('H');
$greeting   = $hour<12 ? 'Good morning' : ($hour<18 ? 'Good afternoon' : 'Good evening');
$first_name = explode(' ', $name)[0];

$resolved_count = 0;
foreach ($complaints as $c) { if (in_array($c['status'],['resolved','closed'])) $resolved_count++; }
$total_count  = count($complaints);
$progress_pct = $total_count > 0 ? round(($resolved_count / $total_count) * 100) : 0;

// Find latest in-progress complaint for the featured card
$featured = null;
foreach ($complaints as $c) {
    if ($c['status'] === 'in_progress') { $featured = $c; break; }
}
if (!$featured) $featured = $complaints[0] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  /* ── Cyan-green palette — sidebar & nav only ── */
  --g900:#011a18;
  --g800:#042e2a;   /* sidebar background */
  --g750:#053d37;   /* topbar */
  --g700:#065449;
  --g650:#0a6e60;
  --g600:#0d8572;
  --g500:#109e88;
  --g450:#14b89f;
  --g400:#18cfb4;   /* primary accent */
  --g350:#3ddbc3;
  --g300:#6ce5d2;
  --g200:#adf2e8;
  --g150:#cef7f2;
  --g100:#e2faf7;
  --g050:#f0fdfb;
  --white:#ffffff;
  --accent:#18cfb4;
  --accent-glow:rgba(24,207,180,0.18);
  --bg:#f0f9f4;
  --card:#ffffff;
  --text:#0d2b1b;
  --text2:#163d27;
  --muted:#4a7260;
  --muted2:#5e8a72;
  --border:#c8e8d8;
  --border2:#a0d4b8;
  --radius:14px;
  --shadow:0 2px 12px rgba(13,43,27,0.07),0 1px 3px rgba(13,43,27,0.05);
  --shadow-md:0 8px 28px rgba(13,43,27,0.11),0 2px 8px rgba(13,43,27,0.07);
  --shadow-lg:0 20px 56px rgba(13,43,27,0.16),0 4px 16px rgba(13,43,27,0.08);
}
html,body{height:100%;overflow:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;font-feature-settings:'kern' 1,'liga' 1;}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:var(--g300);border-radius:4px}
::-webkit-scrollbar-track{background:transparent}

/* ════════ SIDEBAR ════════ */
.sidebar{
  width:220px;min-width:220px;height:100vh;
  background:var(--g800);
  display:flex;flex-direction:column;z-index:51;overflow-y:auto;
  position:relative;
}
.sidebar::after{
  content:'';position:absolute;right:0;top:0;bottom:0;width:1px;
  background:linear-gradient(180deg,transparent,var(--g500) 20%,var(--g400) 60%,var(--g500) 80%,transparent);
}
.sb-logo{
  padding:24px 20px 22px;
  display:flex;align-items:center;gap:13px;
}
.sb-mark{
  width:42px;height:42px;
  background:linear-gradient(135deg,var(--g400),var(--g350));
  border-radius:12px;display:flex;align-items:center;
  justify-content:center;font-size:20px;flex-shrink:0;
  box-shadow:0 4px 14px rgba(24,207,180,0.35);
}
.sb-name{font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:700;color:var(--white);letter-spacing:-.2px;line-height:1.1}
.sb-sub{font-size:.58rem;color:var(--g300);text-transform:uppercase;letter-spacing:1.2px;margin-top:3px;font-weight:500}

.sb-divider{height:1px;background:linear-gradient(90deg,transparent,var(--g650),transparent);margin:4px 0 8px;}
.sb-sec{padding:14px 20px 5px;font-size:.54rem;font-weight:700;letter-spacing:2.2px;text-transform:uppercase;color:var(--g450);}
.nav-a{
  display:flex;align-items:center;gap:10px;padding:9px 16px;margin:1px 10px;
  border-radius:10px;font-size:.79rem;font-weight:450;color:var(--g200);
  cursor:pointer;transition:all .18s cubic-bezier(.4,0,.2,1);
  border:1px solid transparent;position:relative;letter-spacing:.01em;
}
.nav-a:hover{background:rgba(255,255,255,0.06);color:var(--white);border-color:rgba(255,255,255,0.08);transform:translateX(2px)}
.nav-a.on{
  background:linear-gradient(135deg,rgba(24,207,180,0.2),rgba(24,207,180,0.08));
  color:var(--white);font-weight:600;
  border-color:rgba(24,207,180,0.4);
}
.nav-a.on::before{
  content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);
  width:3px;height:60%;background:var(--accent);border-radius:0 3px 3px 0;
}
.nav-ico{font-size:.85rem;width:20px;text-align:center;flex-shrink:0}

.sb-foot{margin-top:auto;padding:12px 10px;border-top:1px solid rgba(255,255,255,0.06)}
.u-card{
  display:flex;align-items:center;gap:10px;padding:11px 13px;
  border-radius:11px;background:rgba(255,255,255,0.05);
  border:1px solid rgba(255,255,255,0.08);
  transition:all .18s;
}
.u-card:hover{background:rgba(255,255,255,0.08)}
.u-av{
  width:34px;height:34px;border-radius:50%;
  background:linear-gradient(135deg,var(--g400),var(--g350));
  border:2px solid rgba(24,207,180,0.4);
  display:flex;align-items:center;justify-content:center;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:.78rem;font-weight:700;color:var(--white);flex-shrink:0;
  box-shadow:0 2px 8px rgba(24,207,180,0.25);
}
.u-name{font-size:.77rem;font-weight:600;color:var(--white)}
.u-role{font-size:.58rem;color:var(--g300);margin-top:1px;letter-spacing:.5px;text-transform:uppercase}
.u-logout{
  margin-left:auto;background:none;border:none;color:var(--g300);
  cursor:pointer;padding:5px;border-radius:7px;transition:all .15s;
}
.u-logout:hover{color:var(--white);background:rgba(255,255,255,0.1);transform:scale(1.1)}

/* ════════ TOPBAR ════════ */
.main{flex:1;height:100vh;overflow-y:auto;display:flex;flex-direction:column;}
.topbar{
  background:var(--g750);
  padding:0 28px;height:62px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:40;flex-shrink:0;
  border-bottom:1px solid rgba(24,207,180,0.25);
  backdrop-filter:blur(12px);
}
.tb-left{display:flex;align-items:center;gap:14px;flex:1}
.tb-greeting{font-size:.88rem;font-weight:500;color:var(--white);letter-spacing:-.1px}
.tb-sep{width:1px;height:16px;background:rgba(255,255,255,0.15)}
.tb-date{font-size:.71rem;color:var(--g300);font-weight:400}
.tb-center{position:absolute;left:50%;transform:translateX(-50%);text-align:center;pointer-events:none;white-space:nowrap;}
.tb-brand{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700;color:var(--white);letter-spacing:-.2px}
.tb-tagline{font-size:.57rem;font-weight:400;color:var(--g300);letter-spacing:.6px;text-transform:uppercase;margin-top:2px}
.tb-right{display:flex;align-items:center;gap:10px;flex:1;justify-content:flex-end}
.tb-notif{
  position:relative;width:38px;height:38px;border-radius:10px;
  background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);
  display:flex;align-items:center;justify-content:center;font-size:.95rem;
  cursor:pointer;transition:all .18s;
}
.tb-notif:hover{background:rgba(24,207,180,0.2);border-color:rgba(24,207,180,0.4);transform:scale(1.05)}
.tb-ndot{
  position:absolute;top:7px;right:7px;width:8px;height:8px;border-radius:50%;
  background:var(--accent);border:2px solid var(--g750);
  animation:pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot{0%,100%{box-shadow:0 0 0 0 rgba(24,207,180,0.5)}50%{box-shadow:0 0 0 4px rgba(24,207,180,0)}}
.tb-btn{
  display:flex;align-items:center;gap:7px;padding:9px 20px;border-radius:10px;
  background:linear-gradient(135deg,var(--g400),var(--g350));
  color:var(--g900);font-size:.78rem;font-weight:600;
  border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;
  transition:all .18s cubic-bezier(.4,0,.2,1);
  box-shadow:0 2px 10px rgba(24,207,180,0.3);
  letter-spacing:-.1px;
}
.tb-btn:hover{transform:translateY(-2px) scale(1.02);box-shadow:0 6px 20px rgba(24,207,180,0.45)}
.tb-btn:active{transform:translateY(0) scale(.99)}

/* ════════ BODY ════════ */
.body{padding:22px 28px;flex:1;}
.toast{
  display:flex;align-items:center;gap:9px;padding:12px 16px;
  border-radius:10px;font-size:.8rem;margin-bottom:18px;
  border:1px solid transparent;font-weight:500;animation:slide-in-down .3s ease;
}
@keyframes slide-in-down{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.t-ok{background:var(--g100);border-color:var(--g300);color:var(--g700)}
.t-err{background:#fff0f0;border-color:#f5b8b8;color:#a02020}

/* ════════ WELCOME BANNER ════════ */
.welcome-banner{
  background:linear-gradient(135deg,var(--g700) 0%,var(--g600) 60%,var(--g500) 100%);
  border-radius:16px;padding:24px 28px;margin-bottom:22px;
  display:flex;align-items:center;justify-content:space-between;
  position:relative;overflow:hidden;
  box-shadow:var(--shadow-md);
}
.welcome-banner::before{
  content:'';position:absolute;top:-40px;right:-40px;
  width:200px;height:200px;border-radius:50%;
  background:radial-gradient(circle,rgba(24,207,180,0.15),transparent 70%);
  pointer-events:none;
}
.welcome-banner::after{
  content:'';position:absolute;bottom:-30px;left:20%;
  width:120px;height:120px;border-radius:50%;
  background:radial-gradient(circle,rgba(109,229,210,0.1),transparent 70%);
  pointer-events:none;
}
.wb-text{}
.wb-greeting{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:700;color:var(--white);letter-spacing:-.3px;line-height:1.15;margin-bottom:6px}
.wb-sub{font-size:.8rem;color:rgba(255,255,255,0.7);font-weight:400;line-height:1.6;letter-spacing:.01em}
.wb-stat{
  display:flex;flex-direction:column;align-items:flex-end;gap:6px;
  position:relative;z-index:1;
}
.wb-big-num{font-family:'Plus Jakarta Sans',sans-serif;font-size:3rem;font-weight:800;color:var(--white);line-height:1;letter-spacing:-2px}
.wb-big-lbl{font-size:.62rem;font-weight:500;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:.9px;text-align:right}
.wb-prog-row{display:flex;align-items:center;gap:10px;margin-top:6px}
.wb-prog-bar{width:120px;height:5px;background:rgba(255,255,255,0.15);border-radius:3px;overflow:hidden}
.wb-prog-fill{height:100%;background:linear-gradient(90deg,var(--g300),var(--white));border-radius:3px;transition:width .8s cubic-bezier(.4,0,.2,1)}
.wb-prog-pct{font-size:.68rem;color:var(--g200);font-weight:600}

/* ════════ STAT CARDS ════════ */
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px}
.sc{
  border-radius:var(--radius);padding:16px 18px;
  display:flex;align-items:center;gap:13px;
  background:var(--card);border:1.5px solid var(--border);
  transition:all .22s cubic-bezier(.4,0,.2,1);
  cursor:default;position:relative;overflow:hidden;
  box-shadow:var(--shadow);
}
.sc::after{
  content:'';position:absolute;inset:0;opacity:0;
  background:linear-gradient(135deg,rgba(24,207,180,0.05),transparent);
  transition:opacity .22s;
}
.sc:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--border2)}
.sc:hover::after{opacity:1}
.sc-accent{border-left:3px solid var(--accent)}
.sc-ico{
  width:44px;height:44px;border-radius:11px;
  display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;
  transition:transform .22s;
}
.sc:hover .sc-ico{transform:scale(1.1) rotate(-3deg)}
.sc-ico-total{background:var(--g100);border:1.5px solid var(--border)}
.sc-ico-new  {background:#fff8e8;border:1.5px solid #f5d98a}
.sc-ico-prog {background:var(--g100);border:1.5px solid var(--g300)}
.sc-ico-done {background:#e8f8ee;border:1.5px solid var(--g300)}
.sc-num{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.9rem;font-weight:700;letter-spacing:-.8px;line-height:1;color:var(--text)}
.sc-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.6px;margin-top:3px;font-weight:500;color:var(--muted)}
.sc-badge{
  margin-left:auto;padding:3px 8px;border-radius:6px;
  font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
}
.sc-badge-new{background:#fff8e8;color:#b07b00;border:1px solid #f5d98a}
.sc-badge-done{background:var(--g100);color:var(--g600);border:1px solid var(--g300)}

/* ════════ DASH GRID ════════ */
.dash-grid{display:grid;grid-template-columns:1fr 308px;gap:18px}
.card{
  background:var(--card);border:1.5px solid var(--border);
  border-radius:var(--radius);box-shadow:var(--shadow);
  overflow:hidden;
}
.ch{
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 20px;border-bottom:1.5px solid var(--border);
  background:linear-gradient(180deg,rgba(244,253,248,0.8),rgba(255,255,255,0));
}
.ch-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;font-weight:700;color:var(--text);letter-spacing:-.15px}
.ch-sub{font-size:.63rem;color:var(--muted);margin-top:2px;font-weight:500}
.ch-act{
  font-size:.72rem;font-weight:700;color:var(--g600);
  background:var(--g050);border:1.5px solid var(--border);
  padding:5px 13px;border-radius:7px;cursor:pointer;
  font-family:'Plus Jakarta Sans',sans-serif;transition:all .16s;
}
.ch-act:hover{background:var(--accent);color:var(--white);border-color:var(--accent);transform:translateY(-1px);box-shadow:0 4px 12px rgba(24,207,180,0.3)}

/* FEATURED COMPLAINT */
.featured-complaint{
  margin:0;background:linear-gradient(135deg,var(--g050),var(--white));
  border-bottom:1.5px solid var(--border);padding:16px 20px;
  position:relative;overflow:hidden;
}
.fc-label{
  display:inline-flex;align-items:center;gap:5px;
  font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;
  color:var(--g500);background:var(--g100);border:1px solid var(--g300);
  padding:3px 10px;border-radius:5px;margin-bottom:10px;
}
.fc-label::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--accent);animation:pulse-dot 2s ease-in-out infinite}
.fc-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;font-weight:600;color:var(--text);margin-bottom:6px;line-height:1.4}
.fc-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.fc-pill{display:inline-flex;align-items:center;gap:5px;font-size:.62rem;font-weight:600;color:var(--muted)}
.fc-officer{font-size:.63rem;font-weight:600;color:var(--g500)}
.fc-track-btn{
  margin-left:auto;font-size:.68rem;font-weight:700;
  color:var(--g600);background:var(--g100);
  border:1.5px solid var(--g300);padding:5px 12px;border-radius:7px;
  cursor:pointer;transition:all .16s;font-family:'Plus Jakarta Sans',sans-serif;
  white-space:nowrap;
}
.fc-track-btn:hover{background:var(--g700);color:var(--white);border-color:var(--g700);transform:translateX(2px)}

/* PROGRESS */
.prog-wrap{padding:13px 20px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;gap:14px}
.prog-meta{font-size:.63rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.prog-meta strong{color:var(--g500);font-size:.78rem}
.prog-track{flex:1;height:6px;background:var(--g100);border-radius:3px;overflow:hidden;border:1px solid var(--border)}
.prog-fill{height:100%;background:linear-gradient(90deg,var(--g500),var(--accent));border-radius:3px;transition:width .8s cubic-bezier(.4,0,.2,1)}
.prog-pct{font-family:'DM Mono',monospace;font-size:.7rem;font-weight:700;color:var(--g500);white-space:nowrap}

/* ════════ COMPLAINT ROWS ════════ */
.comp-list{display:flex;flex-direction:column}
.cr{
  display:flex;align-items:center;gap:12px;
  padding:12px 20px;border-bottom:1px solid var(--border);
  transition:all .18s cubic-bezier(.4,0,.2,1);
  position:relative;
}
.cr::before{
  content:'';position:absolute;left:0;top:0;bottom:0;width:3px;
  background:transparent;transition:background .18s;border-radius:0 3px 3px 0;
}
.cr:last-child{border-bottom:none}
.cr:hover{background:var(--g050);padding-left:24px}
.cr:hover::before{background:var(--accent)}
.cr:hover .cr-actions{opacity:1;pointer-events:all}
.cr-ico{
  width:38px;height:38px;border-radius:10px;
  background:var(--g100);border:1.5px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:1.05rem;flex-shrink:0;cursor:pointer;
  transition:all .18s;
}
.cr:hover .cr-ico{background:var(--g200);border-color:var(--g300);transform:scale(1.05)}
.cr-body{flex:1;min-width:0;cursor:pointer;}
.cr-id{font-size:.59rem;font-weight:600;color:var(--accent);font-family:'DM Mono',monospace;letter-spacing:.8px;margin-bottom:3px}
.cr-title{font-size:.82rem;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px;transition:color .15s;letter-spacing:.01em}
.cr:hover .cr-title{color:var(--g600)}
.cr-meta{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.cr-loc,.cr-date{font-size:.62rem;color:var(--muted)}
.cr-officer{font-size:.62rem;color:var(--g500);font-weight:600}
.cr-right{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0}
.cr-actions{
  display:flex;align-items:center;gap:4px;
  opacity:0;pointer-events:none;transition:opacity .18s;
  flex-shrink:0;margin-left:4px;
}
.cra{
  width:30px;height:30px;border-radius:8px;
  border:1.5px solid var(--border);background:var(--white);
  display:flex;align-items:center;justify-content:center;
  font-size:.72rem;cursor:pointer;transition:all .14s;text-decoration:none;
}
.cra:hover{background:var(--g100);border-color:var(--g300);transform:scale(1.1)}
.cra-del:hover{background:#fff0f0!important;border-color:#f5b8b8!important}
.cra-lock{
  width:30px;height:30px;border-radius:8px;
  border:1px dashed var(--g200);background:transparent;
  display:flex;align-items:center;justify-content:center;
  font-size:.72rem;cursor:not-allowed;opacity:.35;position:relative;
}
.cra-lock:hover::after{
  content:attr(data-tip);position:absolute;right:36px;top:50%;transform:translateY(-50%);
  background:var(--g800);color:var(--g100);font-size:.6rem;font-weight:600;
  padding:4px 9px;border-radius:5px;white-space:nowrap;font-family:'Plus Jakarta Sans',sans-serif;
  pointer-events:none;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,0.2);
}

/* ════════ STATUS PILLS ════════ */
.pill{
  display:inline-flex;align-items:center;padding:3px 9px;border-radius:5px;
  font-size:.59rem;font-weight:800;letter-spacing:.5px;white-space:nowrap;
  text-transform:uppercase;border:1px solid transparent;
}
.s-new      {background:#fff8e8;color:#8a6200;border-color:#f5d98a}
.s-assigned {background:var(--g100);color:var(--g600);border-color:var(--g300)}
.s-prog     {background:linear-gradient(135deg,var(--g400),var(--g350));color:var(--white);border-color:var(--g300);box-shadow:0 2px 6px rgba(24,207,180,0.25)}
.s-resolved {background:var(--g700);color:var(--white);border-color:var(--g600)}
.s-closed   {background:var(--g100);color:var(--muted);border-color:var(--g200)}
.s-esc      {background:#fff0f0;color:#a02020;border-color:#f5b8b8}

/* ════════ SIDEBAR RIGHT CARDS ════════ */
.quick-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)}
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:14px}
.qa{
  display:flex;flex-direction:column;align-items:flex-start;gap:4px;padding:14px 13px;
  background:var(--g050);border:1.5px solid var(--border);border-radius:11px;
  cursor:pointer;transition:all .18s cubic-bezier(.4,0,.2,1);
  font-family:'Plus Jakarta Sans',sans-serif;text-align:left;
}
.qa:hover{background:var(--white);border-color:var(--accent);transform:translateY(-2px);box-shadow:0 4px 14px rgba(24,207,180,0.15)}
.qa:active{transform:translateY(0)}
.qa-ico{font-size:1.2rem;margin-bottom:2px}
.qa-lbl{font-size:.76rem;font-weight:700;color:var(--text)}
.qa-desc{font-size:.61rem;color:var(--muted)}

/* NOTIF LIST */
.notif-list{display:flex;flex-direction:column}
.ni{
  display:flex;align-items:flex-start;gap:10px;padding:12px 16px;
  border-bottom:1px solid var(--border);cursor:pointer;transition:all .15s;
}
.ni:last-child{border-bottom:none}
.ni:hover{background:var(--g050)}
.ni.unread{background:rgba(24,207,180,0.04);border-left:3px solid var(--accent)}
.ni-dot{width:7px;height:7px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:5px}
.ni-dot.read{background:var(--g200)}
.ni-msg{font-size:.73rem;color:var(--text);line-height:1.5;margin-bottom:2px}
.ni-time{font-size:.6rem;color:var(--muted);font-weight:500}

/* HELPLINE */
.helpline{padding:16px 18px}
.hl-label{font-size:.57rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-bottom:12px}
.hl-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.hl-ico{
  width:32px;height:32px;border-radius:9px;background:var(--g100);
  border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;
  transition:all .15s;
}
.hl-row:hover .hl-ico{background:var(--g200);border-color:var(--g300);transform:scale(1.05)}
.hl-text{font-size:.78rem;font-weight:700;color:var(--text)}
.hl-sub{font-size:.61rem;color:var(--muted)}

/* EMPTY */
.empty{text-align:center;padding:44px 20px}
.empty-ico{font-size:2.5rem;margin-bottom:10px;opacity:.35;animation:float 3s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.empty-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:800;color:var(--text);margin-bottom:5px}
.empty-sub{font-size:.74rem;color:var(--muted);margin-bottom:18px;line-height:1.65}

/* ════════ MODALS ════════ */
.overlay{
  position:fixed;inset:0;background:rgba(6,26,15,.6);z-index:200;
  display:flex;align-items:center;justify-content:center;
  opacity:0;pointer-events:none;transition:opacity .22s;backdrop-filter:blur(4px);
}
.overlay.on{opacity:1;pointer-events:all}
.modal{
  background:var(--white);border:1.5px solid var(--border);border-radius:18px;
  width:92%;max-width:540px;max-height:92vh;overflow-y:auto;padding:28px;
  box-shadow:var(--shadow-lg);
  transform:scale(.95) translateY(16px);transition:transform .24s cubic-bezier(.4,0,.2,1);
}
.overlay.on .modal{transform:scale(1) translateY(0)}
.mh{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px}
.mh-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700;color:var(--text);letter-spacing:-.2px}
.mh-sub{font-size:.73rem;color:var(--muted);margin-top:4px;line-height:1.5}
.mh-close{
  width:32px;height:32px;border-radius:9px;background:var(--g100);
  border:1.5px solid var(--border);color:var(--muted);cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:.8rem;
  transition:all .15s;flex-shrink:0;
}
.mh-close:hover{background:var(--g200);color:var(--text);transform:scale(1.08)}

.fg{margin-bottom:15px}
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:15px}
.fl{font-size:.61rem;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;display:block}
input.fi,select.fi,textarea.fi{
  width:100%;padding:10px 14px;background:var(--white);
  border:1.5px solid var(--border);border-radius:9px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:.84rem;color:var(--text);
  outline:none;transition:all .17s;
}
input.fi:focus,select.fi:focus,textarea.fi:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(24,207,180,0.12)}
input.fi::placeholder,textarea.fi::placeholder{color:var(--g300)}
textarea.fi{resize:vertical;min-height:75px;line-height:1.65}

.cat-pick{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:15px}
.cat-btn{
  padding:12px 6px;text-align:center;border-radius:11px;
  border:1.5px solid var(--border);background:var(--white);cursor:pointer;
  transition:all .17s;font-family:inherit;
}
.cat-btn:hover{border-color:var(--accent);background:var(--g050);transform:translateY(-1px)}
.cat-btn.sel{border-color:var(--accent);background:var(--g100);box-shadow:inset 0 0 0 1.5px var(--accent),0 4px 12px rgba(24,207,180,0.15)}
.cat-ico{font-size:1.25rem;margin-bottom:4px}
.cat-lbl{font-size:.62rem;font-weight:700;color:var(--text)}

.upzone{
  border:2px dashed var(--g300);border-radius:11px;padding:20px;
  text-align:center;cursor:pointer;transition:all .18s;
  background:var(--g050);position:relative;
}
.upzone:hover,.upzone.drag{border-color:var(--accent);background:rgba(24,207,180,0.05)}
.upzone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.uz-ico{font-size:1.6rem;margin-bottom:6px}
.uz-txt{font-size:.75rem;color:var(--muted);line-height:1.6}
.uz-txt strong{color:var(--g500)}
.prev-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.prev-img{width:70px;height:70px;border-radius:9px;object-fit:cover;border:2px solid var(--border);box-shadow:0 2px 8px rgba(0,0,0,0.1)}

.gps-fi{
  width:100%;padding:10px 14px;background:var(--white);
  border:1.5px solid var(--border);border-radius:9px;
  font-family:inherit;font-size:.8rem;color:var(--muted);
  cursor:pointer;text-align:left;transition:all .17s;
}
.gps-fi:hover{border-color:var(--accent);background:var(--g050);color:var(--text)}

.btn-submit{
  width:100%;padding:13px;
  background:linear-gradient(135deg,var(--g700),var(--g600));
  color:var(--white);border:none;border-radius:11px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;font-weight:600;
  cursor:pointer;transition:all .18s;margin-top:6px;
  letter-spacing:-.1px;box-shadow:0 4px 16px rgba(6,84,73,0.25);
}
.btn-submit:hover{background:linear-gradient(135deg,var(--g600),var(--g500));transform:translateY(-2px);box-shadow:0 8px 24px rgba(6,84,73,0.3)}
.btn-submit:active{transform:translateY(0)}

/* DELETE MODAL */
.del-modal{max-width:400px}
.del-ico{
  width:58px;height:58px;border-radius:16px;
  background:var(--g100);border:2px solid var(--g300);
  display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 14px;
  box-shadow:var(--shadow);
}
.del-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:.96rem;font-weight:700;color:var(--text);text-align:center;margin-bottom:6px}
.del-sub{font-size:.76rem;color:var(--muted);text-align:center;line-height:1.65;margin-bottom:14px}
.del-cname{
  font-size:.78rem;font-weight:600;color:var(--text);
  background:var(--g050);border:1.5px solid var(--border);
  border-radius:9px;padding:10px 14px;text-align:center;margin-bottom:14px;
  word-break:break-word;
}
.del-warn{
  font-size:.71rem;color:var(--g700);background:var(--g100);
  border:1.5px solid var(--g300);border-radius:9px;
  padding:10px 13px;margin-bottom:18px;line-height:1.65;
}
.del-btns{display:flex;gap:10px}
.del-cancel{
  flex:1;padding:11px;border-radius:10px;background:var(--white);
  color:var(--text);border:1.5px solid var(--border);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:.83rem;font-weight:600;cursor:pointer;transition:all .15s;
}
.del-cancel:hover{background:var(--g100);border-color:var(--g300)}
.del-confirm{
  flex:1;padding:11px;border-radius:10px;background:#c0392b;color:var(--white);border:none;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:.83rem;font-weight:700;cursor:pointer;transition:all .15s;
}
.del-confirm:hover{background:#a93226;transform:translateY(-1px);box-shadow:0 4px 12px rgba(192,57,43,0.3)}
.del-confirm:disabled{opacity:.6;cursor:not-allowed;transform:none}

/* NOTIF PANEL */
.notif-backdrop{position:fixed;inset:0;z-index:149;background:transparent;pointer-events:none;transition:background .22s}
.notif-backdrop.on{pointer-events:all;background:rgba(6,26,15,.45);backdrop-filter:blur(3px)}
.notif-panel{
  position:fixed;top:0;right:0;width:360px;max-width:96vw;height:100vh;z-index:150;
  background:var(--white);border-left:1.5px solid var(--border);
  box-shadow:-8px 0 40px rgba(6,26,15,.12);
  display:flex;flex-direction:column;
  transform:translateX(100%);transition:transform .28s cubic-bezier(.4,0,.2,1);
}
.notif-panel.open{transform:translateX(0)}
.np-head{
  padding:20px 18px 16px;border-bottom:1.5px solid var(--border);
  display:flex;align-items:flex-start;justify-content:space-between;flex-shrink:0;
  background:var(--g050);
}
.np-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:.95rem;font-weight:800;color:var(--text);letter-spacing:-.2px}
.np-sub{font-size:.67rem;color:var(--muted);margin-top:2px}
.np-mark-btn{
  font-size:.67rem;font-weight:700;color:var(--g600);background:var(--white);
  border:1.5px solid var(--g300);padding:4px 10px;border-radius:7px;
  text-decoration:none;white-space:nowrap;transition:all .15s;cursor:pointer;
}
.np-mark-btn:hover{background:var(--accent);color:var(--white);border-color:var(--accent)}
.np-close{
  width:28px;height:28px;border-radius:7px;background:var(--white);
  border:1.5px solid var(--border);color:var(--muted);cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:.72rem;transition:all .15s;flex-shrink:0;
}
.np-close:hover{background:var(--g200);color:var(--text)}
.np-list{flex:1;overflow-y:auto;padding:4px 0}
.np-item{
  display:flex;align-items:flex-start;gap:12px;padding:14px 16px;
  cursor:pointer;transition:all .15s;border-bottom:1px solid var(--border);
}
.np-item:last-child{border-bottom:none}
.np-item:hover{background:var(--g050)}
.np-item.np-unread{background:rgba(24,207,180,0.04);border-left:3px solid var(--accent)}
.np-ico{
  width:38px;height:38px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;
}
.nt-a{background:var(--g050);border:1.5px solid var(--g200)}
.nt-b{background:var(--g100);border:1.5px solid var(--g300)}
.nt-c{background:var(--g200);border:1.5px solid var(--g400)}
.nt-d{background:linear-gradient(135deg,var(--g400),var(--g350));border:1.5px solid var(--g300)}
.nt-e{background:#fff0f0;border:1.5px solid #f5b8b8}
.np-body{flex:1;min-width:0}
.np-msg{font-size:.76rem;color:var(--text);line-height:1.55;margin-bottom:3px;font-weight:500}
.np-time{font-size:.62rem;color:var(--muted)}
.np-dot{width:8px;height:8px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:5px}
.np-footer{padding:12px 16px;border-top:1.5px solid var(--border);text-align:center;flex-shrink:0}
.np-view-all{font-size:.73rem;font-weight:700;color:var(--g500)}

/* NOTIF DETAIL */
.nd-overlay{position:fixed;inset:0;background:rgba(6,26,15,.6);z-index:300;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s;backdrop-filter:blur(4px)}
.nd-overlay.on{opacity:1;pointer-events:all}
.nd-modal{background:var(--white);border:1.5px solid var(--border);border-radius:18px;width:92%;max-width:460px;padding:28px;box-shadow:var(--shadow-lg);transform:scale(.95) translateY(16px);transition:transform .23s cubic-bezier(.4,0,.2,1)}
.nd-overlay.on .nd-modal{transform:scale(1) translateY(0)}
.nd-big-ico{width:50px;height:50px;border-radius:14px;background:var(--g100);border:2px solid var(--g300);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;box-shadow:var(--shadow)}
.nd-msg-box{background:var(--g050);border:1.5px solid var(--border);border-left:4px solid var(--accent);border-radius:11px;padding:16px 18px;font-size:.84rem;color:var(--text);line-height:1.75;margin:18px 0 16px}
.nd-actions{display:flex;gap:10px;flex-wrap:wrap}
.nd-act-btn{flex:1;min-width:120px;padding:10px 16px;border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.79rem;font-weight:700;cursor:pointer;transition:all .15s;border:1.5px solid var(--border);text-align:center;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px}
.nd-act-primary{background:linear-gradient(135deg,var(--g700),var(--g600));color:var(--white);border-color:var(--g600)}
.nd-act-primary:hover{background:linear-gradient(135deg,var(--g600),var(--g500));transform:translateY(-1px)}
.nd-act-sec{background:var(--g050);color:var(--muted);border-color:var(--border)}
.nd-act-sec:hover{background:var(--g100);color:var(--text)}

/* ANIMATIONS */
@keyframes bell-shake{0%,100%{transform:rotate(0)}15%{transform:rotate(10deg)}30%{transform:rotate(-10deg)}45%{transform:rotate(6deg)}60%{transform:rotate(-6deg)}75%{transform:rotate(3deg)}}
.bell-ring{animation:bell-shake .65s ease-in-out}
@keyframes fade-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.cr{animation:fade-in .3s ease both}
<?php foreach($complaints as $i=>$_): ?>.cr:nth-child(<?= $i+1 ?>){animation-delay:<?= $i*.04 ?>s}<?php endforeach; ?>

/* ════════ HAMBURGER BUTTON ════════ */
.mob-menu-btn{
  display:none;flex-direction:column;justify-content:center;gap:5px;
  width:38px;height:38px;background:rgba(255,255,255,0.08);
  border:1px solid rgba(255,255,255,0.15);border-radius:10px;cursor:pointer;
  padding:9px 10px;flex-shrink:0;transition:all .18s;
}
.mob-menu-btn span{display:block;width:18px;height:2px;background:var(--white);border-radius:2px;transition:all .25s;}
.mob-menu-btn:hover{background:rgba(24,207,180,0.2);border-color:rgba(24,207,180,0.4)}

/* ════════ MOBILE SIDEBAR DRAWER ════════ */
.mob-backdrop{
  display:none;position:fixed;inset:0;z-index:98;
  background:rgba(4,26,15,.55);backdrop-filter:blur(3px);
  opacity:0;transition:opacity .25s;
}
.mob-backdrop.on{opacity:1}
.sidebar.mob-open{transform:translateX(0)!important}

/* ════════ RESPONSIVE BREAKPOINTS ════════ */
@media(max-width:960px){
  html,body{overflow:auto}
  body{display:block}

  .sidebar{
    position:fixed;top:0;left:0;height:100%;z-index:99;
    transform:translateX(-100%);transition:transform .28s cubic-bezier(.4,0,.2,1);
    box-shadow:8px 0 40px rgba(4,26,15,.3);
  }
  .main{height:auto;overflow:visible}
  .topbar{position:sticky;top:0;z-index:50}
  .mob-menu-btn{display:flex}
  .mob-backdrop{display:block;pointer-events:none}
  .mob-backdrop.on{pointer-events:all}

  .stat-row{grid-template-columns:1fr 1fr}
  .dash-grid{grid-template-columns:1fr}
  .tb-center{display:none}
  .body{overflow:visible}
}

@media(max-width:600px){
  .body{padding:14px 12px}
  .topbar{padding:0 14px}
  .tb-greeting{font-size:.78rem}
  .tb-date{display:none}
  .tb-sep{display:none}
  .tb-btn{padding:8px 12px;font-size:.72rem}

  .welcome-banner{flex-direction:column;gap:14px;padding:18px 16px}
  .wb-stat{align-items:flex-start}
  .wb-big-num{font-size:2.2rem}
  .wb-prog-bar{width:100px}

  .stat-row{grid-template-columns:1fr 1fr;gap:8px}
  .sc{padding:12px 12px}
  .sc-ico{width:36px;height:36px;font-size:1rem}
  .sc-num{font-size:1.5rem}
  .sc-badge{display:none}

  .cr{padding:10px 12px;gap:8px}
  .cr:hover{padding-left:14px}
  .cr-ico{width:32px;height:32px;font-size:.9rem}
  .cr-title{font-size:.78rem}
  .cr-actions{opacity:1;pointer-events:all}

  .fg2{grid-template-columns:1fr}
  .cat-pick{grid-template-columns:repeat(3,1fr)}
  .modal{padding:20px 16px}

  .qa-grid{grid-template-columns:1fr 1fr}
  .prog-wrap{flex-wrap:wrap;gap:8px}
  .prog-meta{width:100%}
}

@media(max-width:400px){
  .stat-row{grid-template-columns:1fr 1fr}
  .tb-btn{display:none}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-mark">🏛️</div>
    <div><div class="sb-name">Nagrik Seva</div><div class="sb-sub">Citizen Portal</div></div>
  </div>
  <div class="sb-divider"></div>
  <div class="sb-sec">Main</div>
  <a class="nav-a on" href="citizen_dashboard.php"><span class="nav-ico">⊞</span> Dashboard</a>
  <a class="nav-a" href="#" onclick="openModal();return false"><span class="nav-ico">＋</span> File Complaint</a>
  <a class="nav-a" href="#complaints"><span class="nav-ico">≡</span> My Complaints</a>
  <a class="nav-a" href="public_board.php"><span class="nav-ico">◎</span> Public Board</a>
  <div class="sb-sec">Account</div>
  <a class="nav-a" href="citizen_profile.php"><span class="nav-ico">○</span> Profile</a>
  <a class="nav-a" href="track.php"><span class="nav-ico">⊙</span> Track Complaint</a>
  <div class="sb-sec">Info</div>
  <a class="nav-a" href="about.php"><span class="nav-ico">ℹ</span> About</a>
  <a class="nav-a" href="contact.php"><span class="nav-ico">✉</span> Contact</a>
  <div class="sb-foot">
    <div class="u-card">
      <div class="u-av"><?= $initials ?></div>
      <div><div class="u-name"><?= htmlspecialchars($name) ?></div><div class="u-role">Citizen</div></div>
      <a href="logout.php" class="u-logout" title="Sign out">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </a>
    </div>
  </div>
</aside>

<!-- MOBILE BACKDROP -->
<div class="mob-backdrop" id="mob-backdrop" onclick="closeMobSidebar()"></div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <button class="mob-menu-btn" id="mob-menu-btn" onclick="toggleMobSidebar()" aria-label="Open menu"><span></span><span></span><span></span></button>
      <div class="tb-greeting"><?= $greeting ?>, <?= htmlspecialchars($first_name) ?> 👋</div>
      <div class="tb-sep"></div>
      <div class="tb-date"><?= date('D, d M Y') ?></div>
    </div>
    <div class="tb-center">
      <div class="tb-brand">🏛️ Nagrik Seva Portal</div>
      <div class="tb-tagline">Your Voice · Our Responsibility</div>
    </div>
    <div class="tb-right">
      <button class="tb-notif" id="bell-btn" onclick="toggleNotifPanel()" type="button">
        🔔<?php if($unread>0): ?><span class="tb-ndot" id="bell-dot"></span><?php endif; ?>
      </button>
      <button class="tb-btn" onclick="openModal()">＋ New Complaint</button>
    </div>
  </div>

  <div class="body">
    <?php if($post_success): ?><div class="toast t-ok">✓ &nbsp;<?= htmlspecialchars($post_success) ?></div><?php endif; ?>
    <?php if($post_error): ?><div class="toast t-err">⚠ &nbsp;<?= htmlspecialchars($post_error) ?></div><?php endif; ?>

    <!-- WELCOME BANNER -->
    <div class="welcome-banner">
      <div class="wb-text">
        <div class="wb-greeting"><?= $greeting ?>, <?= htmlspecialchars($first_name) ?> 👋</div>
        <div class="wb-sub">You have <strong style="color:var(--g200)"><?= $stats['new'] + $stats['in_progress'] ?> active complaints</strong> being tracked.<br>Your voice is making Goa better, one report at a time.</div>
      </div>
      <div class="wb-stat">
        <div class="wb-big-num"><?= $stats['total'] ?></div>
        <div class="wb-big-lbl">Total Filed</div>
        <div class="wb-prog-row">
          <div class="wb-prog-bar"><div class="wb-prog-fill" style="width:<?= $progress_pct ?>%"></div></div>
          <div class="wb-prog-pct"><?= $progress_pct ?>% resolved</div>
        </div>
      </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-row">
      <div class="sc sc-accent">
        <div class="sc-ico sc-ico-total">📋</div>
        <div style="flex:1"><div class="sc-num" id="s-total"><?= $stats['total'] ?></div><div class="sc-lbl">Total Filed</div></div>
      </div>
      <div class="sc">
        <div class="sc-ico sc-ico-new">🕐</div>
        <div style="flex:1"><div class="sc-num"><?= $stats['new'] ?></div><div class="sc-lbl">Awaiting</div></div>
        <?php if($stats['new']>0): ?><span class="sc-badge sc-badge-new">Pending</span><?php endif; ?>
      </div>
      <div class="sc">
        <div class="sc-ico sc-ico-prog">⚙️</div>
        <div style="flex:1"><div class="sc-num"><?= $stats['in_progress'] ?></div><div class="sc-lbl">In Progress</div></div>
      </div>
      <div class="sc">
        <div class="sc-ico sc-ico-done">✅</div>
        <div style="flex:1"><div class="sc-num"><?= $stats['resolved'] ?></div><div class="sc-lbl">Resolved</div></div>
        <?php if($stats['resolved']>0): ?><span class="sc-badge sc-badge-done">Done</span><?php endif; ?>
      </div>
    </div>

    <div class="dash-grid">
      <!-- LEFT: COMPLAINTS -->
      <div id="complaints">
        <div class="card">
          <div class="ch">
            <div><div class="ch-title">My Complaints</div><div class="ch-sub"><?= count($complaints) ?> total · latest first</div></div>
            <button class="ch-act" onclick="openModal()">＋ New</button>
          </div>

          <!-- FEATURED ACTIVE COMPLAINT -->
          <?php if($featured): $fc_sc=$s_cfg[$featured['status']]??['label'=>ucfirst($featured['status']),'cls'=>'s-new']; ?>
          <div class="featured-complaint">
            <div class="fc-label">Active Complaint</div>
            <div class="fc-title"><?= htmlspecialchars($featured['title']) ?></div>
            <div class="fc-meta">
              <span class="fc-pill">📍 <?= htmlspecialchars($featured['location']) ?></span>
              <span class="pill <?= $fc_sc['cls'] ?>"><?= $fc_sc['label'] ?></span>
              <?php if(!empty($featured['officer_name'])): ?>
              <span class="fc-officer">· 👮 <?= htmlspecialchars($featured['officer_name']) ?></span>
              <?php endif; ?>
              <a href="track.php?id=<?= urlencode($featured['complaint_no']) ?>" class="fc-track-btn">Track →</a>
            </div>
          </div>
          <?php endif; ?>

          <!-- PROGRESS BAR -->
          <div class="prog-wrap">
            <div class="prog-meta">Resolution <strong><?= $resolved_count ?>/<?= $total_count ?></strong></div>
            <div class="prog-track"><div class="prog-fill" style="width:<?= $progress_pct ?>%"></div></div>
            <div class="prog-pct"><?= $progress_pct ?>%</div>
          </div>

          <?php if(empty($complaints)): ?>
          <div class="empty">
            <div class="empty-ico">📭</div>
            <div class="empty-title">No complaints yet</div>
            <div class="empty-sub">File your first complaint and track its resolution in real time.</div>
            <button class="tb-btn" onclick="openModal()" style="margin:0 auto">＋ File Complaint</button>
          </div>
          <?php else: ?>
          <div class="comp-list">
            <?php foreach($complaints as $c):
              $sc=$s_cfg[$c['status']]??['label'=>ucfirst($c['status']),'cls'=>'s-new'];
              $ico=$cat_icon[$c['category']]??'📋';
              $editable=$deletable=($c['status']==='new');
            ?>
            <div class="cr" id="cr-<?= $c['id'] ?>">
              <div class="cr-ico" onclick="location.href='complaint_detail.php?id=<?= $c['id'] ?>'"><?= $ico ?></div>
              <div class="cr-body" onclick="location.href='complaint_detail.php?id=<?= $c['id'] ?>'">
                <div class="cr-id"><?= htmlspecialchars($c['complaint_no']) ?></div>
                <div class="cr-title"><?= htmlspecialchars($c['title']) ?></div>
                <div class="cr-meta">
                  <span class="cr-loc">📍 <?= htmlspecialchars($c['location']) ?></span>
                  <span class="cr-date">· <?= date('d M Y',strtotime($c['created_at'])) ?></span>
                  <?php if(!empty($c['officer_name'])): ?><span class="cr-officer">· 👮 <?= htmlspecialchars($c['officer_name']) ?></span><?php endif; ?>
                </div>
              </div>
              <div class="cr-right">
                <span class="pill <?= $sc['cls'] ?>"><?= $sc['label'] ?></span>
                <?php if($c['photo_path']): ?><span style="font-size:.6rem;color:var(--muted)">📷</span><?php endif; ?>
              </div>
              <div class="cr-actions">
                <a href="track.php?id=<?= urlencode($c['complaint_no']) ?>" class="cra" title="Track" onclick="event.stopPropagation()">🗺️</a>
                <?php if($editable): ?>
                <a href="edit_complaint.php?id=<?= $c['id'] ?>" class="cra" title="Edit" onclick="event.stopPropagation()">✏️</a>
                <?php else: ?>
                <div class="cra-lock" data-tip="Cannot edit — <?= $sc['label'] ?>">✏️</div>
                <?php endif; ?>
                <?php if($deletable): ?>
                <button class="cra cra-del" title="Delete" onclick="event.stopPropagation();openDelModal(<?= $c['id'] ?>,'<?= addslashes(htmlspecialchars($c['complaint_no'])) ?>','<?= addslashes(htmlspecialchars($c['title'])) ?>')">🗑️</button>
                <?php else: ?>
                <div class="cra-lock" data-tip="Cannot delete — <?= $sc['label'] ?>">🗑️</div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div style="display:flex;flex-direction:column;gap:14px">
        <!-- QUICK ACTIONS -->
        <div class="quick-card">
          <div class="ch"><div class="ch-title">Quick Actions</div></div>
          <div class="qa-grid">
            <button class="qa" onclick="openModal()"><span class="qa-ico">📸</span><span class="qa-lbl">File Complaint</span><span class="qa-desc">Upload photo</span></button>
            <a class="qa" href="track.php"><span class="qa-ico">🔍</span><span class="qa-lbl">Track Issue</span><span class="qa-desc">Check status</span></a>
            <a class="qa" href="public_board.php"><span class="qa-ico">🌐</span><span class="qa-lbl">Public Board</span><span class="qa-desc">All issues</span></a>
            <a class="qa" href="citizen_profile.php"><span class="qa-ico">👤</span><span class="qa-lbl">My Profile</span><span class="qa-desc">Edit details</span></a>
          </div>
        </div>

        <!-- NOTIFICATIONS -->
        <div class="quick-card">
          <div class="ch">
            <div><div class="ch-title">Notifications</div><?php if($unread>0): ?><div class="ch-sub"><?= $unread ?> unread</div><?php endif; ?></div>
            <?php if($unread>0): ?><a href="citizen_dashboard.php?read_notifs=1" class="ch-act">Mark read</a><?php endif; ?>
          </div>
          <?php if(empty($notifs)): ?>
          <div class="empty" style="padding:24px"><div class="empty-ico" style="font-size:1.5rem">🔔</div><div class="empty-title">All caught up</div><div class="empty-sub" style="margin-bottom:0">Updates will appear here.</div></div>
          <?php else: ?>
          <div class="notif-list">
            <?php foreach(array_slice($notifs,0,5) as $n):
              $type=$n['type']??'info'; $ic=$notif_icons[$type]??$notif_icons['info'];
              $ts=strtotime($n['created_at']); $diff=time()-$ts;
              $tago=$diff<60?'Just now':($diff<3600?floor($diff/60).'m ago':($diff<86400?floor($diff/3600).'h ago':date('d M',$ts)));
            ?>
            <div class="ni <?= $n['is_read']?'':'unread' ?>" onclick="openNotifDetail(<?= htmlspecialchars(json_encode(['id'=>(int)$n['id'],'type'=>$type,'ico'=>$ic['ico'],'message'=>$n['message'],'time'=>date('d M Y · H:i',$ts),'is_read'=>(bool)$n['is_read'],'complaint_id'=>isset($n['complaint_id'])?(int)$n['complaint_id']:null]),ENT_QUOTES) ?>)">
              <div class="ni-dot <?= $n['is_read']?'read':'' ?>"></div>
              <div><div class="ni-msg"><?= htmlspecialchars($n['message']) ?></div><div class="ni-time"><?= $tago ?></div></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- HELPLINE -->
        <div class="quick-card" style="border-top:3px solid var(--g500)">
          <div class="helpline">
            <div class="hl-label">Support Helpline</div>
            <div class="hl-row"><div class="hl-ico">📞</div><div><div class="hl-text">1800-233-1100</div><div class="hl-sub">Toll Free · Mon–Sat 9am–6pm</div></div></div>
            <div class="hl-row"><div class="hl-ico">✉️</div><div><div class="hl-text">grievance@goa.gov.in</div><div class="hl-sub">Response within 24 hours</div></div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="overlay" id="del-overlay" onclick="if(event.target===this)closeDelModal()">
  <div class="modal del-modal">
    <div style="text-align:center;padding:4px 0 6px"><div class="del-ico">🗑️</div><div class="del-title">Delete this complaint?</div><div class="del-sub">This is permanent and cannot be undone.</div></div>
    <div class="del-cname" id="del-cname">—</div>
    <div class="del-warn">⚠ Only <strong>New</strong> complaints can be deleted. Once assigned or in progress, contact support to withdraw.</div>
    <div class="del-btns"><button class="del-cancel" onclick="closeDelModal()">Cancel</button><button class="del-confirm" id="del-confirm" onclick="confirmDelete()">Yes, Delete</button></div>
  </div>
</div>

<!-- NOTIF PANEL -->
<div class="notif-backdrop" id="notif-backdrop" onclick="closeNotifPanel()"></div>
<div class="notif-panel" id="notif-panel">
  <div class="np-head">
    <div><div class="np-title">🔔 Notifications</div><div class="np-sub" id="np-sub-txt"><?= $unread>0?"$unread unread":"All caught up" ?></div></div>
    <div style="display:flex;align-items:center;gap:8px">
      <?php if($unread>0): ?><a href="citizen_dashboard.php?read_notifs=1" class="np-mark-btn">Mark all read</a><?php endif; ?>
      <button class="np-close" onclick="closeNotifPanel()">✕</button>
    </div>
  </div>
  <div class="np-list">
    <?php if(empty($notifs)): ?>
    <div style="padding:48px 24px;text-align:center;color:var(--muted)"><div style="font-size:2rem;margin-bottom:8px">🔔</div><div style="font-size:.83rem;font-weight:700;margin-bottom:4px">No notifications yet</div></div>
    <?php else: foreach($notifs as $n):
      $type=$n['type']??'info'; $ic=$notif_icons[$type]??$notif_icons['info'];
      $ts=strtotime($n['created_at']); $diff=time()-$ts;
      $tago=$diff<60?'Just now':($diff<3600?floor($diff/60).'m ago':($diff<86400?floor($diff/3600).'h ago':($diff<604800?floor($diff/86400).'d ago':date('d M',$ts))));
    ?>
    <div class="np-item <?= $n['is_read']?'':'np-unread' ?>" onclick="openNotifDetail(<?= htmlspecialchars(json_encode(['id'=>(int)$n['id'],'type'=>$type,'ico'=>$ic['ico'],'message'=>$n['message'],'time'=>date('d M Y · H:i',$ts),'is_read'=>(bool)$n['is_read'],'complaint_id'=>isset($n['complaint_id'])?(int)$n['complaint_id']:null]),ENT_QUOTES) ?>)">
      <div class="np-ico <?= $ic['cls'] ?>"><?= $ic['ico'] ?></div>
      <div class="np-body"><div class="np-msg"><?= htmlspecialchars($n['message']) ?></div><div class="np-time"><?= $tago ?></div></div>
      <?php if(!$n['is_read']): ?><div class="np-dot"></div><?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
  </div>
  <?php if(count($notifs)>=8): ?><div class="np-footer"><a href="#" class="np-view-all">View all notifications →</a></div><?php endif; ?>
</div>

<!-- NOTIF DETAIL -->
<div class="nd-overlay" id="nd-overlay" onclick="if(event.target===this)closeNotifDetail()">
  <div class="nd-modal">
    <div class="mh">
      <div style="display:flex;align-items:center;gap:13px"><div class="nd-big-ico" id="nd-big-ico">📥</div><div><div class="mh-title" id="nd-title">Notification</div><div class="mh-sub" id="nd-time"></div></div></div>
      <button class="mh-close" onclick="closeNotifDetail()">✕</button>
    </div>
    <div class="nd-msg-box" id="nd-msg"></div>
    <div class="nd-actions" id="nd-actions"></div>
  </div>
</div>

<!-- FILE COMPLAINT MODAL -->
<div class="overlay" id="overlay" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="mh">
      <div><div class="mh-title">File a Complaint</div><div class="mh-sub">Describe the issue and attach a photo. We'll route it to the right department.</div></div>
      <button class="mh-close" onclick="closeModal()">✕</button>
    </div>
    <?php if($post_error): ?><div class="toast t-err" style="margin-bottom:14px">⚠ <?= htmlspecialchars($post_error) ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data" id="cf">
      <input type="hidden" name="action" value="file_complaint">
      <input type="hidden" name="category" id="cat-val">
      <input type="hidden" name="lat" id="lat-val">
      <input type="hidden" name="lng" id="lng-val">
      <div class="fg">
        <label class="fl">Photo Evidence <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)">(recommended)</span></label>
        <div class="upzone" id="upzone">
          <input type="file" name="photo" accept="image/*" id="photo-in" onchange="prevPhoto(this)">
          <div class="uz-ico" id="uz-ico">📷</div>
          <div class="uz-txt" id="uz-txt">Tap to take photo or <strong>browse files</strong><br>JPG · PNG · WEBP up to 5MB</div>
          <div class="prev-row" id="prev-row"></div>
        </div>
      </div>
      <div class="fg">
        <label class="fl">Category <span style="color:var(--g600)">*</span></label>
        <div class="cat-pick">
          <button type="button" class="cat-btn" onclick="selCat('road',this)"><div class="cat-ico">🚧</div><div class="cat-lbl">Roads</div></button>
          <button type="button" class="cat-btn" onclick="selCat('water',this)"><div class="cat-ico">💧</div><div class="cat-lbl">Water</div></button>
          <button type="button" class="cat-btn" onclick="selCat('electricity',this)"><div class="cat-ico">⚡</div><div class="cat-lbl">Electricity</div></button>
          <button type="button" class="cat-btn" onclick="selCat('sanitation',this)"><div class="cat-ico">🧹</div><div class="cat-lbl">Sanitation</div></button>
          <button type="button" class="cat-btn" onclick="selCat('property',this)"><div class="cat-ico">🏛️</div><div class="cat-lbl">Property</div></button>
          <button type="button" class="cat-btn" onclick="selCat('lost',this)"><div class="cat-ico">🔍</div><div class="cat-lbl">Lost &amp; Found</div></button>
        </div>
      </div>
      <div class="fg">
        <label class="fl">Title <span style="color:var(--g600)">*</span></label>
        <input class="fi" type="text" name="title" placeholder="e.g. Large pothole near NH17 petrol pump" required>
      </div>
      <div class="fg2">
        <div>
          <label class="fl">Location <span style="color:var(--g600)">*</span></label>
          <input class="fi" type="text" name="location" placeholder="Area or landmark" required>
        </div>
        <div>
          <label class="fl">GPS Coordinates</label>
          <button type="button" class="gps-fi" id="gps-btn" onclick="getGPS()">📍 Detect my location</button>
        </div>
      </div>
      <div class="fg">
        <label class="fl">Description <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)">(optional)</span></label>
        <textarea class="fi" name="description" placeholder="When did you notice it? How severe is it?"></textarea>
      </div>
      <button type="submit" class="btn-submit">Submit Complaint →</button>
    </form>
  </div>
</div>

<script>
function toggleMobSidebar(){const s=document.querySelector('.sidebar'),b=document.getElementById('mob-backdrop');s.classList.toggle('mob-open');b.classList.toggle('on');document.body.style.overflow=s.classList.contains('mob-open')?'hidden':'';}
function closeMobSidebar(){const s=document.querySelector('.sidebar'),b=document.getElementById('mob-backdrop');s.classList.remove('mob-open');b.classList.remove('on');document.body.style.overflow='';}
function openModal(){closeMobSidebar();document.getElementById('overlay').classList.add('on');document.body.style.overflow='hidden';}
function closeModal(){document.getElementById('overlay').classList.remove('on');document.body.style.overflow='';}
<?php if($post_error): ?>openModal();<?php endif; ?>
function selCat(v,btn){document.querySelectorAll('.cat-btn').forEach(b=>b.classList.remove('sel'));btn.classList.add('sel');document.getElementById('cat-val').value=v;}
function prevPhoto(inp){const row=document.getElementById('prev-row');row.innerHTML='';if(inp.files&&inp.files[0]){const r=new FileReader();r.onload=e=>{const img=document.createElement('img');img.src=e.target.result;img.className='prev-img';row.appendChild(img);document.getElementById('uz-ico').textContent='✓';document.getElementById('uz-txt').innerHTML='<strong>Photo attached.</strong> Click to change.';};r.readAsDataURL(inp.files[0]);}}
const uz=document.getElementById('upzone');
uz.addEventListener('dragover',e=>{e.preventDefault();uz.classList.add('drag');});
uz.addEventListener('dragleave',()=>uz.classList.remove('drag'));
uz.addEventListener('drop',e=>{e.preventDefault();uz.classList.remove('drag');const f=e.dataTransfer.files[0];if(f){const dt=new DataTransfer();dt.items.add(f);document.getElementById('photo-in').files=dt.files;prevPhoto(document.getElementById('photo-in'));}});
function getGPS(){const btn=document.getElementById('gps-btn');btn.textContent='⏳ Detecting…';btn.disabled=true;if(!navigator.geolocation){btn.textContent='Not supported';return;}navigator.geolocation.getCurrentPosition(p=>{document.getElementById('lat-val').value=p.coords.latitude.toFixed(7);document.getElementById('lng-val').value=p.coords.longitude.toFixed(7);btn.textContent=`✓ ${p.coords.latitude.toFixed(4)}, ${p.coords.longitude.toFixed(4)}`;btn.style.color='var(--g500)';btn.style.borderColor='var(--accent)';},()=>{btn.textContent='Location denied';btn.disabled=false;});}
document.getElementById('cf').addEventListener('submit',function(e){if(!document.getElementById('cat-val').value){e.preventDefault();alert('Please select a category.');}});
setTimeout(()=>{const t=document.querySelector('.toast');if(t){t.style.transition='opacity .5s';t.style.opacity='0';setTimeout(()=>t.remove(),500);}},4000);

let _delId=null;
function openDelModal(id,cno,title){_delId=id;document.getElementById('del-cname').textContent=cno+' — '+title;const btn=document.getElementById('del-confirm');btn.disabled=false;btn.textContent='Yes, Delete';document.getElementById('del-overlay').classList.add('on');document.body.style.overflow='hidden';}
function closeDelModal(){document.getElementById('del-overlay').classList.remove('on');document.body.style.overflow='';_delId=null;}
function confirmDelete(){
  if(!_delId)return;
  const btn=document.getElementById('del-confirm');btn.disabled=true;btn.textContent='⏳ Deleting…';
  fetch('delete_complaint.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id=${_delId}`})
  .then(r=>r.json()).then(d=>{
    if(d.success){
      const row=document.getElementById('cr-'+_delId);
      if(row){row.style.transition='all .3s';row.style.opacity='0';row.style.overflow='hidden';const h=row.offsetHeight;row.style.maxHeight=h+'px';setTimeout(()=>{row.style.maxHeight='0';row.style.padding='0';row.style.borderBottom='none';setTimeout(()=>row.remove(),320);},180);}
      closeDelModal();const tot=document.getElementById('s-total');if(tot)tot.textContent=Math.max(0,parseInt(tot.textContent)-1);
      showToast('✓ Complaint deleted successfully.','ok');
    }else{btn.disabled=false;btn.textContent='Yes, Delete';closeDelModal();showToast('⚠ '+(d.error||'Could not delete.'),'err');}
  }).catch(()=>{btn.disabled=false;btn.textContent='Yes, Delete';closeDelModal();showToast('⚠ Network error.','err');});
}
function showToast(msg,type){
  const t=document.createElement('div');t.className='toast t-'+(type==='ok'?'ok':'err');
  t.style.cssText='position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:9999;min-width:280px;text-align:center;box-shadow:0 8px 28px rgba(0,0,0,.15);animation:slide-in-down .3s ease;';
  t.textContent=msg;document.body.appendChild(t);
  setTimeout(()=>{t.style.transition='opacity .5s';t.style.opacity='0';setTimeout(()=>t.remove(),500);},3500);
}

const nPanel=document.getElementById('notif-panel'),nBackdrop=document.getElementById('notif-backdrop');
function toggleNotifPanel(){nPanel.classList.contains('open')?closeNotifPanel():openNotifPanel();}
function openNotifPanel(){nPanel.classList.add('open');nBackdrop.classList.add('on');const b=document.getElementById('bell-btn');b.classList.add('bell-ring');setTimeout(()=>b.classList.remove('bell-ring'),700);}
function closeNotifPanel(){nPanel.classList.remove('open');nBackdrop.classList.remove('on');}

const typeLabels={submitted:'Complaint Submitted',assigned:'Officer Assigned',in_progress:'Work In Progress',resolved:'Complaint Resolved',escalated:'Complaint Escalated',message:'Message from Officer',info:'Update'};
const ndO=document.getElementById('nd-overlay');
function openNotifDetail(data){
  document.getElementById('nd-big-ico').textContent=data.ico;
  document.getElementById('nd-title').textContent=typeLabels[data.type]||'Notification';
  document.getElementById('nd-time').textContent=data.time;
  document.getElementById('nd-msg').textContent=data.message;
  const acts=document.getElementById('nd-actions');acts.innerHTML='';
  if(data.complaint_id){const a=document.createElement('a');a.href=`complaint_detail.php?id=${data.complaint_id}`;a.className='nd-act-btn nd-act-primary';a.innerHTML='🔍 &nbsp;View Complaint';acts.appendChild(a);}
  const cb=document.createElement('button');cb.className='nd-act-btn nd-act-sec';cb.textContent='Close';cb.onclick=closeNotifDetail;acts.appendChild(cb);
  ndO.classList.add('on');
  if(!data.is_read&&data.id){fetch('mark_notif_read.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id=${data.id}`}).then(()=>{document.querySelectorAll('.np-dot').forEach(d=>d.remove());document.querySelectorAll('.np-unread,.ni.unread').forEach(el=>el.classList.remove('np-unread','unread'));document.querySelectorAll('.ni-dot').forEach(d=>d.classList.add('read'));const bd=document.getElementById('bell-dot');if(bd)bd.style.display='none';const st=document.getElementById('np-sub-txt');if(st)st.textContent='All caught up';}).catch(()=>{});}
}
function closeNotifDetail(){ndO.classList.remove('on');}
document.addEventListener('keydown',e=>{if(e.key!=='Escape')return;if(ndO.classList.contains('on'))closeNotifDetail();else if(document.getElementById('del-overlay').classList.contains('on'))closeDelModal();else if(nPanel.classList.contains('open'))closeNotifPanel();else if(document.querySelector('.sidebar.mob-open'))closeMobSidebar();else closeModal();});
</script>
</body>
</html>
