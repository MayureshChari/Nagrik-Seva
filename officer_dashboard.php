<?php
session_start();
require_once 'config.php';

if ((empty($_SESSION['user_id']) && !isset($_SESSION['is_demo'])) || $_SESSION['role'] !== 'officer') {
    header('Location: officer_login.php'); exit;
}

$uid      = (int)$_SESSION['user_id'];
$name     = $_SESSION['name'] ?? 'Suresh Kamat';
$dept     = $_SESSION['dept'] ?? 'Road & PWD';
$initials = strtoupper(substr($name, 0, 1));

$officer = [];
if ($uid > 0) {
    $r = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $r->bind_param('i', $uid); $r->execute();
    $officer = $r->get_result()->fetch_assoc(); $r->close();
}
$zone = $officer['zone'] ?? 'All Zones';

$stats = ['assigned'=>0,'in_progress'=>0,'resolved'=>0,'escalated'=>0,'total'=>0];
if ($uid > 0) {
    $r = $conn->query("SELECT status, COUNT(*) as c FROM complaints WHERE officer_id=$uid GROUP BY status");
    while ($row = $r->fetch_assoc()) {
        $stats['total'] += $row['c'];
        if (array_key_exists($row['status'], $stats)) $stats[$row['status']] = (int)$row['c'];
    }
}

$my_complaints = [];
if ($uid > 0) {
    $r = $conn->query("SELECT c.*, u.name as citizen_name FROM complaints c LEFT JOIN users u ON c.citizen_id = u.id WHERE c.officer_id = $uid ORDER BY c.updated_at DESC LIMIT 15");
    while ($row = $r->fetch_assoc()) $my_complaints[] = $row;
}

$unassigned = [];
if ($uid > 0) {
    $zone_cond = $zone ? "AND (c.zone = '".$conn->real_escape_string($zone)."' OR c.zone IS NULL OR c.zone = '')" : "";
    $r = $conn->query("SELECT c.*, u.name as citizen_name FROM complaints c LEFT JOIN users u ON c.citizen_id = u.id WHERE c.officer_id IS NULL AND c.status = 'new' $zone_cond ORDER BY c.created_at ASC LIMIT 10");
    while ($row = $r->fetch_assoc()) $unassigned[] = $row;
}

if (empty($my_complaints)) {
    $stats = ['assigned'=>3,'in_progress'=>4,'resolved'=>12,'escalated'=>1,'total'=>20];
    $my_complaints = [
        ['id'=>1,'complaint_no'=>'GRV-A4C7E2','category'=>'road',       'title'=>'Large pothole near NH17 petrol pump, Panaji',    'location'=>'Panaji, North Goa',     'status'=>'in_progress','priority'=>'high',  'created_at'=>date('Y-m-d H:i:s',strtotime('-4 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-1 day')), 'citizen_name'=>'Rahul Naik',    'photo_path'=>null],
        ['id'=>2,'complaint_no'=>'GRV-B3D9F1','category'=>'water',      'title'=>'Burst water pipe flooding street — Caranzalem',  'location'=>'Caranzalem, Panaji',    'status'=>'assigned',   'priority'=>'high',  'created_at'=>date('Y-m-d H:i:s',strtotime('-6 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-6 days')),'citizen_name'=>'Priya Dessai',  'photo_path'=>null],
        ['id'=>3,'complaint_no'=>'GRV-C8A2B4','category'=>'electricity','title'=>'Street light outage — Miramar Beach Road',        'location'=>'Miramar, Panaji',       'status'=>'in_progress','priority'=>'medium','created_at'=>date('Y-m-d H:i:s',strtotime('-8 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-2 days')),'citizen_name'=>'Anton Fernandes','photo_path'=>'placeholder.jpg'],
        ['id'=>4,'complaint_no'=>'GRV-D5E1C9','category'=>'sanitation', 'title'=>'Overflowing garbage bins near Panjim Market',    'location'=>'Panjim Market',         'status'=>'resolved',   'priority'=>'medium','created_at'=>date('Y-m-d H:i:s',strtotime('-11 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-3 days')),'citizen_name'=>'Sunita Gaonkar','photo_path'=>null],
        ['id'=>5,'complaint_no'=>'GRV-E2F7A3','category'=>'road',       'title'=>'Broken footpath tiles causing hazard — Altinho', 'location'=>'Altinho, Panaji',       'status'=>'assigned',   'priority'=>'medium','created_at'=>date('Y-m-d H:i:s',strtotime('-15 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-15 days')),'citizen_name'=>'Maria Coelho',  'photo_path'=>null],
        ['id'=>6,'complaint_no'=>'GRV-F9B4D8','category'=>'water',      'title'=>'No water supply for 3 days — Dona Paula Ward',   'location'=>'Dona Paula',            'status'=>'escalated',  'priority'=>'high',  'created_at'=>date('Y-m-d H:i:s',strtotime('-20 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-5 days')),'citizen_name'=>'David Gomes',   'photo_path'=>null],
        ['id'=>7,'complaint_no'=>'GRV-G1C6E5','category'=>'road',       'title'=>'Road divider damaged on Calangute highway',      'location'=>'Calangute-Mapusa Road', 'status'=>'resolved',   'priority'=>'high',  'created_at'=>date('Y-m-d H:i:s',strtotime('-22 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-7 days')),'citizen_name'=>'Rohan Sawant',  'photo_path'=>null],
    ];
}
if (empty($unassigned)) {
    $unassigned = [
        ['id'=>8, 'complaint_no'=>'GRV-H2K1P9','category'=>'sanitation', 'title'=>'Illegal dump near Panaji cemetery road',         'location'=>'Panaji',           'status'=>'new','priority'=>'medium','created_at'=>date('Y-m-d H:i:s',strtotime('-2 days')),'citizen_name'=>'Teresa Vaz'],
        ['id'=>9, 'complaint_no'=>'GRV-I5L8Q2','category'=>'road',       'title'=>'Pothole outside St. Xavier college',              'location'=>'Panaji',           'status'=>'new','priority'=>'high',  'created_at'=>date('Y-m-d H:i:s',strtotime('-1 day')), 'citizen_name'=>'Vishnu Lotlikar'],
        ['id'=>10,'complaint_no'=>'GRV-J3M6R5','category'=>'electricity','title'=>'Broken street lamp outside pharmacy, Fontainhas', 'location'=>'Fontainhas, Panaji','status'=>'new','priority'=>'low',  'created_at'=>date('Y-m-d H:i:s',strtotime('-3 days')),'citizen_name'=>'Agnes Monteiro'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'self_assign') {
    $cid = (int)($_POST['complaint_id'] ?? 0);
    if ($cid && $uid > 0) {
        $conn->query("UPDATE complaints SET officer_id=$uid, status='assigned', updated_at=NOW() WHERE id=$cid AND officer_id IS NULL");
        $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message) SELECT citizen_id,$cid,'assigned','Your complaint has been assigned to an officer.' FROM complaints WHERE id=$cid");
    }
    header('Location: officer_dashboard.php'); exit;
}

$notifs = [];
$unread = 0;
if ($uid > 0) {
    // ── FIX: fetch ALL notification types including legal_notice and regulator_notice ──
    $r = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 8");
    while ($row = $r->fetch_assoc()) $notifs[] = $row;
    $unread = (int)($conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0")->fetch_assoc()['c'] ?? 0);
}

// ── FIX: dummy fallback now includes legal_notice and regulator_notice examples ──
if (empty($notifs)) {
    $unread = 3;
    $notifs = [
        ['id'=>1,'complaint_id'=>2, 'type'=>'new_assignment',  'message'=>'New complaint GRV-B3D9F1 assigned to you — Water pipe flooding.','created_at'=>date('Y-m-d H:i:s',strtotime('-3 hours')),'is_read'=>0],
        ['id'=>2,'complaint_id'=>6, 'type'=>'escalated',       'message'=>'GRV-F9B4D8 has been escalated by the regulator. Urgent action required.','created_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'is_read'=>0],
        ['id'=>3,'complaint_id'=>null,'type'=>'regulator_notice','message'=>'You have unresolved complaints pending for more than 5 days. Immediate action required.','created_at'=>date('Y-m-d H:i:s',strtotime('-2 days')),'is_read'=>0],
        ['id'=>4,'complaint_id'=>null,'type'=>'legal_notice',  'message'=>'⚖️ LEGAL NOTICE: You are hereby formally notified that your failure to address assigned complaints constitutes dereliction of duty under the Goa Grievance Redressal Act, 2024.','created_at'=>date('Y-m-d H:i:s',strtotime('-3 days')),'is_read'=>1],
        ['id'=>5,'complaint_id'=>4, 'type'=>'closed',          'message'=>'GRV-D5E1C9 marked resolved and closed by regulator. Good work!','created_at'=>date('Y-m-d H:i:s',strtotime('-5 days')),'is_read'=>1],
    ];
}

if (isset($_GET['read_notifs'])) {
    if ($uid > 0) $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    header('Location: officer_dashboard.php'); exit;
}

$cat_icon = ['road'=>'🚧','water'=>'💧','electricity'=>'⚡','sanitation'=>'🧹','property'=>'🏛️','lost'=>'🔍'];
$s_cfg    = ['new'=>['label'=>'New','cls'=>'s-new'],'assigned'=>['label'=>'Assigned','cls'=>'s-assigned'],'in_progress'=>['label'=>'In Progress','cls'=>'s-prog'],'resolved'=>['label'=>'Resolved','cls'=>'s-resolved'],'closed'=>['label'=>'Closed','cls'=>'s-closed'],'escalated'=>['label'=>'Escalated','cls'=>'s-esc']];
$pri_cfg  = ['high'=>['cls'=>'p-high','label'=>'High'],'medium'=>['cls'=>'p-med','label'=>'Medium'],'low'=>['cls'=>'p-low','label'=>'Low']];

// ── FIX: added legal_notice and regulator_notice with distinct icons/styles ──
$notif_icons = [
    'new_assignment'   => ['ico'=>'📥','cls'=>'nt-a'],
    'assigned'         => ['ico'=>'👮','cls'=>'nt-b'],
    'in_progress'      => ['ico'=>'🔧','cls'=>'nt-c'],
    'resolved'         => ['ico'=>'✅','cls'=>'nt-d'],
    'escalated'        => ['ico'=>'🚨','cls'=>'nt-e'],
    'closed'           => ['ico'=>'✅','cls'=>'nt-d'],
    'message'          => ['ico'=>'💬','cls'=>'nt-a'],
    'info'             => ['ico'=>'ℹ️', 'cls'=>'nt-b'],
    // ── these two were missing — now fixed ──
    'regulator_notice' => ['ico'=>'📢','cls'=>'nt-warn'],
    'legal_notice'     => ['ico'=>'⚖️','cls'=>'nt-legal'],
    'terminated'       => ['ico'=>'🚫','cls'=>'nt-e'],
];

$hour        = (int)date('H');
$greeting    = $hour<12 ? 'Good morning' : ($hour<18 ? 'Good afternoon' : 'Good evening');
$first_name  = explode(' ', $name)[0];
$resolved_count = count(array_filter($my_complaints, fn($c) => in_array($c['status'],['resolved','closed'])));
$total_count    = count($my_complaints);
$progress_pct   = $total_count > 0 ? round(($resolved_count / $total_count) * 100) : 0;
$pending_count  = $stats['assigned'] + $stats['in_progress'];
$featured = null;
foreach ($my_complaints as $c) { if ($c['status'] === 'in_progress') { $featured = $c; break; } }
if (!$featured && !empty($my_complaints)) $featured = $my_complaints[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Officer Dashboard — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g900:#011a18;--g800:#042e2a;--g750:#053d37;--g700:#065449;--g650:#0a6e60;
  --g600:#0d8572;--g500:#109e88;--g450:#14b89f;--g400:#18cfb4;--g350:#3ddbc3;
  --g300:#6ce5d2;--g200:#adf2e8;--g150:#cef7f2;--g100:#e2faf7;--g050:#f0fdfb;
  --white:#ffffff;--accent:#18cfb4;--accent-glow:rgba(24,207,180,0.18);
  --bg:#f0f9f4;--card:#ffffff;--text:#0d2b1b;--text2:#163d27;
  --muted:#4a7260;--muted2:#5e8a72;--border:#c8e8d8;--border2:#a0d4b8;--radius:14px;
  --shadow:0 2px 12px rgba(13,43,27,0.07),0 1px 3px rgba(13,43,27,0.05);
  --shadow-md:0 8px 28px rgba(13,43,27,0.11),0 2px 8px rgba(13,43,27,0.07);
  --shadow-lg:0 20px 56px rgba(13,43,27,0.16),0 4px 16px rgba(13,43,27,0.08);
}
html,body{height:100%;overflow:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;-webkit-font-smoothing:antialiased;}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--g300);border-radius:4px}::-webkit-scrollbar-track{background:transparent}

/* SIDEBAR */
.sidebar{width:220px;min-width:220px;height:100vh;background:var(--g800);display:flex;flex-direction:column;z-index:51;overflow-y:auto;position:relative;}
.sidebar::after{content:'';position:absolute;right:0;top:0;bottom:0;width:1px;background:linear-gradient(180deg,transparent,var(--g500) 20%,var(--g400) 60%,var(--g500) 80%,transparent);}
.sb-logo{padding:24px 20px 22px;display:flex;align-items:center;gap:13px;}
.sb-mark{width:42px;height:42px;background:linear-gradient(135deg,var(--g400),var(--g350));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;box-shadow:0 4px 14px rgba(24,207,180,0.35);}
.sb-name{font-size:.9rem;font-weight:700;color:var(--white);letter-spacing:-.2px;line-height:1.1}
.sb-sub{font-size:.58rem;color:var(--g300);text-transform:uppercase;letter-spacing:1.2px;margin-top:3px;font-weight:500}
.sb-divider{height:1px;background:linear-gradient(90deg,transparent,var(--g650),transparent);margin:4px 0 8px;}
.sb-sec{padding:14px 20px 5px;font-size:.54rem;font-weight:700;letter-spacing:2.2px;text-transform:uppercase;color:var(--g450);}
.nav-a{display:flex;align-items:center;gap:10px;padding:9px 16px;margin:1px 10px;border-radius:10px;font-size:.79rem;font-weight:450;color:var(--g200);cursor:pointer;transition:all .18s;border:1px solid transparent;position:relative;}
.nav-a:hover{background:rgba(255,255,255,0.06);color:var(--white);border-color:rgba(255,255,255,0.08);transform:translateX(2px)}
.nav-a.on{background:linear-gradient(135deg,rgba(24,207,180,0.2),rgba(24,207,180,0.08));color:var(--white);font-weight:600;border-color:rgba(24,207,180,0.4);}
.nav-a.on::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:60%;background:var(--accent);border-radius:0 3px 3px 0;}
.nav-ico{font-size:.85rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--accent);color:var(--g900);font-size:.58rem;font-weight:800;padding:2px 6px;border-radius:4px}
.sb-foot{margin-top:auto;padding:12px 10px;border-top:1px solid rgba(255,255,255,0.06)}
.u-card{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:11px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);transition:all .18s;}
.u-card:hover{background:rgba(255,255,255,0.08)}
.u-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));border:2px solid rgba(24,207,180,0.4);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--white);flex-shrink:0;}
.u-name{font-size:.77rem;font-weight:600;color:var(--white)}
.u-role{font-size:.58rem;color:var(--g300);margin-top:1px;letter-spacing:.5px;text-transform:uppercase}
.u-logout{margin-left:auto;background:none;border:none;color:var(--g300);cursor:pointer;padding:5px;border-radius:7px;transition:all .15s;}
.u-logout:hover{color:var(--white);background:rgba(255,255,255,0.1)}

/* TOPBAR */
.main{flex:1;height:100vh;overflow-y:auto;display:flex;flex-direction:column;}
.topbar{background:var(--g750);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;flex-shrink:0;border-bottom:1px solid rgba(24,207,180,0.25);backdrop-filter:blur(12px);}
.tb-left{display:flex;align-items:center;gap:14px;flex:1}
.tb-greeting{font-size:.88rem;font-weight:500;color:var(--white);}
.tb-sep{width:1px;height:16px;background:rgba(255,255,255,0.15)}
.tb-date{font-size:.71rem;color:var(--g300);}
.tb-center{position:absolute;left:50%;transform:translateX(-50%);text-align:center;pointer-events:none;white-space:nowrap;}
.tb-brand{font-size:1rem;font-weight:700;color:var(--white);letter-spacing:-.2px}
.tb-tagline{font-size:.57rem;color:var(--g300);letter-spacing:.6px;text-transform:uppercase;margin-top:2px}
.tb-right{display:flex;align-items:center;gap:10px;flex:1;justify-content:flex-end}
.tb-notif{position:relative;width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;font-size:.95rem;cursor:pointer;transition:all .18s;}
.tb-notif:hover{background:rgba(24,207,180,0.2);border-color:rgba(24,207,180,0.4);transform:scale(1.05)}
.tb-ndot{position:absolute;top:7px;right:7px;width:8px;height:8px;border-radius:50%;background:var(--accent);border:2px solid var(--g750);animation:pulse-dot 2s ease-in-out infinite;}
@keyframes pulse-dot{0%,100%{box-shadow:0 0 0 0 rgba(24,207,180,0.5)}50%{box-shadow:0 0 0 4px rgba(24,207,180,0)}}
.tb-btn{display:flex;align-items:center;gap:7px;padding:9px 20px;border-radius:10px;background:linear-gradient(135deg,var(--g400),var(--g350));color:var(--g900);font-size:.78rem;font-weight:600;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .18s;box-shadow:0 2px 10px rgba(24,207,180,0.3);}
.tb-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(24,207,180,0.45)}

/* BODY */
.body{padding:22px 28px;flex:1;}
.toast{display:flex;align-items:center;gap:9px;padding:12px 16px;border-radius:10px;font-size:.8rem;margin-bottom:18px;border:1px solid transparent;font-weight:500;animation:slide-in-down .3s ease;}
@keyframes slide-in-down{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.t-ok{background:var(--g100);border-color:var(--g300);color:var(--g700)}

/* WELCOME BANNER */
.welcome-banner{background:linear-gradient(135deg,var(--g500) 0%,var(--g400) 55%,var(--g350) 100%);border-radius:16px;padding:24px 28px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;position:relative;overflow:hidden;box-shadow:var(--shadow-md);}
.wb-greeting{font-size:1.4rem;font-weight:700;color:var(--g900);letter-spacing:-.3px;line-height:1.15;margin-bottom:6px}
.wb-sub{font-size:.8rem;color:var(--g800);line-height:1.6}
.wb-stat{display:flex;flex-direction:column;align-items:flex-end;gap:6px;}
.wb-big-num{font-size:3rem;font-weight:800;color:var(--g900);line-height:1;letter-spacing:-2px}
.wb-big-lbl{font-size:.62rem;font-weight:600;color:var(--g800);text-transform:uppercase;letter-spacing:.9px;text-align:right}
.wb-prog-row{display:flex;align-items:center;gap:10px;margin-top:6px}
.wb-prog-bar{width:120px;height:5px;background:rgba(4,46,42,0.15);border-radius:3px;overflow:hidden}
.wb-prog-fill{height:100%;background:linear-gradient(90deg,var(--g800),var(--g700));border-radius:3px;transition:width .8s}
.wb-prog-pct{font-size:.68rem;color:var(--g800);font-weight:700}

/* STAT CARDS */
.stat-row{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:22px}
.sc{border-radius:var(--radius);padding:16px 18px;display:flex;align-items:center;gap:13px;background:var(--card);border:1.5px solid var(--border);transition:all .22s;cursor:default;position:relative;overflow:hidden;box-shadow:var(--shadow);}
.sc:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--border2)}
.sc-accent{border-left:3px solid var(--accent)}
.sc-ico{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
.sc-ico-total{background:var(--g100);border:1.5px solid var(--border)}
.sc-ico-new  {background:#fff8e8;border:1.5px solid #f5d98a}
.sc-ico-prog {background:var(--g100);border:1.5px solid var(--g300)}
.sc-ico-done {background:#e8f8ee;border:1.5px solid var(--g300)}
.sc-ico-esc  {background:#fff0f0;border:1.5px solid #f5b8b8}
.sc-num{font-size:1.9rem;font-weight:700;letter-spacing:-.8px;line-height:1;color:var(--text)}
.sc-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.6px;margin-top:3px;font-weight:500;color:var(--muted)}
.sc-badge{margin-left:auto;padding:3px 8px;border-radius:6px;font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.sc-badge-warn{background:#fff8e8;color:#b07b00;border:1px solid #f5d98a}
.sc-badge-done{background:var(--g100);color:var(--g600);border:1px solid var(--g300)}
.sc-badge-esc {background:#fff0f0;color:#a02020;border:1px solid #f5b8b8}

/* DASH GRID */
.dash-grid{display:grid;grid-template-columns:1fr 308px;gap:18px}
.card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.ch{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1.5px solid var(--border);background:linear-gradient(180deg,rgba(244,253,248,0.8),rgba(255,255,255,0));}
.ch-title{font-size:.88rem;font-weight:700;color:var(--text);}
.ch-sub{font-size:.63rem;color:var(--muted);margin-top:2px;}
.ch-act{font-size:.72rem;font-weight:700;color:var(--g600);background:var(--g050);border:1.5px solid var(--border);padding:5px 13px;border-radius:7px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .16s;}
.ch-act:hover{background:var(--accent);color:var(--white);border-color:var(--accent);transform:translateY(-1px)}

/* FEATURED */
.featured-complaint{background:linear-gradient(135deg,var(--g050),var(--white));border-bottom:1.5px solid var(--border);padding:16px 20px;}
.fc-label{display:inline-flex;align-items:center;gap:5px;font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--g500);background:var(--g100);border:1px solid var(--g300);padding:3px 10px;border-radius:5px;margin-bottom:10px;}
.fc-label::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--accent);animation:pulse-dot 2s ease-in-out infinite}
.fc-title{font-size:.88rem;font-weight:600;color:var(--text);margin-bottom:6px;line-height:1.4}
.fc-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.fc-pill{font-size:.62rem;font-weight:600;color:var(--muted)}
.fc-citizen{font-size:.63rem;font-weight:600;color:var(--g500)}
.fc-track-btn{margin-left:auto;font-size:.68rem;font-weight:700;color:var(--g600);background:var(--g100);border:1.5px solid var(--g300);padding:5px 12px;border-radius:7px;transition:all .16s;font-family:'Plus Jakarta Sans',sans-serif;white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;}
.fc-track-btn:hover{background:var(--g700);color:var(--white);border-color:var(--g700)}

/* PROGRESS */
.prog-wrap{padding:13px 20px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;gap:14px}
.prog-meta{font-size:.63rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.prog-meta strong{color:var(--g500);font-size:.78rem}
.prog-track{flex:1;height:6px;background:var(--g100);border-radius:3px;overflow:hidden;border:1px solid var(--border)}
.prog-fill{height:100%;background:linear-gradient(90deg,var(--g500),var(--accent));border-radius:3px;transition:width .8s}
.prog-pct{font-family:'DM Mono',monospace;font-size:.7rem;font-weight:700;color:var(--g500);white-space:nowrap}

/* COMPLAINT ROWS */
.comp-list{display:flex;flex-direction:column}
.cr{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--border);transition:all .18s;position:relative;}
.cr::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:transparent;transition:background .18s;border-radius:0 3px 3px 0;}
.cr:last-child{border-bottom:none}
.cr:hover{background:var(--g050);padding-left:24px}
.cr:hover::before{background:var(--accent)}
.cr:hover .cr-actions{opacity:1;pointer-events:all}
.cr-ico{width:38px;height:38px;border-radius:10px;background:var(--g100);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;cursor:pointer;transition:all .18s;}
.cr-body{flex:1;min-width:0;cursor:pointer;}
.cr-id{font-size:.59rem;font-weight:600;color:var(--accent);font-family:'DM Mono',monospace;letter-spacing:.8px;margin-bottom:3px}
.cr-title{font-size:.82rem;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px;}
.cr-meta{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.cr-loc,.cr-date{font-size:.62rem;color:var(--muted)}
.cr-citizen{font-size:.62rem;color:var(--g500);font-weight:600}
.cr-right{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0}
.cr-actions{display:flex;align-items:center;gap:4px;opacity:0;pointer-events:none;transition:opacity .18s;flex-shrink:0;margin-left:4px;}
.cra{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--white);display:flex;align-items:center;justify-content:center;font-size:.72rem;cursor:pointer;transition:all .14s;text-decoration:none;}
.cra:hover{background:var(--g100);border-color:var(--g300);transform:scale(1.1)}

/* STATUS PILLS */
.pill{display:inline-flex;align-items:center;padding:3px 9px;border-radius:5px;font-size:.59rem;font-weight:800;letter-spacing:.5px;white-space:nowrap;text-transform:uppercase;border:1px solid transparent;}
.s-new      {background:#fff8e8;color:#8a6200;border-color:#f5d98a}
.s-assigned {background:var(--g100);color:var(--g600);border-color:var(--g300)}
.s-prog     {background:linear-gradient(135deg,var(--g400),var(--g350));color:var(--white);border-color:var(--g300)}
.s-resolved {background:var(--g700);color:var(--white);border-color:var(--g600)}
.s-closed   {background:var(--g100);color:var(--muted);border-color:var(--g200)}
.s-esc      {background:#fff0f0;color:#a02020;border-color:#f5b8b8}
.p-high     {background:#fff0f0;color:#a02020;border:1px solid #f5b8b8;font-size:.55rem;font-weight:700;padding:2px 7px;border-radius:4px;}
.p-med      {background:#fff8e8;color:#8a6200;border:1px solid #f5d98a;font-size:.55rem;font-weight:700;padding:2px 7px;border-radius:4px;}
.p-low      {background:var(--g050);color:var(--muted);border:1px solid var(--border);font-size:.55rem;font-weight:700;padding:2px 7px;border-radius:4px;}

/* UNASSIGNED */
.ua-item{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--border);transition:all .18s;}
.ua-item:last-child{border-bottom:none}
.ua-item:hover{background:var(--g050)}
.ua-ico{width:38px;height:38px;border-radius:10px;background:var(--g100);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;}
.ua-body{flex:1;min-width:0}
.ua-id{font-size:.59rem;font-weight:600;color:var(--accent);font-family:'DM Mono',monospace;letter-spacing:.8px;margin-bottom:2px}
.ua-title{font-size:.82rem;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px}
.ua-meta{font-size:.62rem;color:var(--muted)}
.assign-btn{flex-shrink:0;padding:5px 12px;border-radius:7px;font-size:.68rem;font-weight:700;background:linear-gradient(135deg,var(--g500),var(--g400));color:var(--white);border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .16s;}
.assign-btn:hover{transform:translateY(-1px)}

/* RIGHT CARDS */
.quick-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)}
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:14px}
.qa{display:flex;flex-direction:column;align-items:flex-start;gap:4px;padding:14px 13px;background:var(--g050);border:1.5px solid var(--border);border-radius:11px;cursor:pointer;transition:all .18s;font-family:'Plus Jakarta Sans',sans-serif;text-align:left;text-decoration:none;}
.qa:hover{background:var(--white);border-color:var(--accent);transform:translateY(-2px);box-shadow:0 4px 14px rgba(24,207,180,0.15)}
.qa-ico{font-size:1.2rem;margin-bottom:2px}
.qa-lbl{font-size:.76rem;font-weight:700;color:var(--text)}
.qa-desc{font-size:.61rem;color:var(--muted)}

/* NOTIF LIST (inline card) */
.notif-list{display:flex;flex-direction:column}
.ni{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;transition:all .15s;}
.ni:last-child{border-bottom:none}
.ni:hover{background:var(--g050)}
.ni.unread{background:rgba(24,207,180,0.04);border-left:3px solid var(--accent)}
/* FIX: warn/legal styles for inline notification dots */
.ni.ni-warn{background:rgba(245,217,138,0.08);border-left:3px solid #f5d98a}
.ni.ni-legal{background:rgba(160,32,32,0.04);border-left:3px solid #f5b8b8}
.ni-dot{width:7px;height:7px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:5px}
.ni-dot.read{background:var(--g200)}
.ni-dot.warn{background:#f5a623}
.ni-dot.legal{background:#dc2626}
.ni-msg{font-size:.73rem;color:var(--text);line-height:1.5;margin-bottom:2px}
.ni-time{font-size:.6rem;color:var(--muted);}

/* HELPLINE */
.helpline{padding:16px 18px}
.hl-label{font-size:.57rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-bottom:12px}
.hl-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.hl-ico{width:32px;height:32px;border-radius:9px;background:var(--g100);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
.hl-text{font-size:.78rem;font-weight:700;color:var(--text)}
.hl-sub{font-size:.61rem;color:var(--muted)}

/* EMPTY */
.empty{text-align:center;padding:44px 20px}
.empty-ico{font-size:2.5rem;margin-bottom:10px;opacity:.35;animation:float 3s ease-in-out infinite;display:block}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.empty-title{font-size:.9rem;font-weight:800;color:var(--text);margin-bottom:5px}
.empty-sub{font-size:.74rem;color:var(--muted);margin-bottom:18px;line-height:1.65}

/* NOTIF PANEL (slide-in) */
.notif-backdrop{position:fixed;inset:0;z-index:149;background:transparent;pointer-events:none;transition:background .22s}
.notif-backdrop.on{pointer-events:all;background:rgba(6,26,15,.45);backdrop-filter:blur(3px)}
.notif-panel{position:fixed;top:0;right:0;width:360px;max-width:96vw;height:100vh;z-index:150;background:var(--white);border-left:1.5px solid var(--border);box-shadow:-8px 0 40px rgba(6,26,15,.12);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .28s cubic-bezier(.4,0,.2,1);}
.notif-panel.open{transform:translateX(0)}
.np-head{padding:20px 18px 16px;border-bottom:1.5px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;flex-shrink:0;background:var(--g050);}
.np-title{font-size:.95rem;font-weight:800;color:var(--text);}
.np-sub{font-size:.67rem;color:var(--muted);margin-top:2px}
.np-mark-btn{font-size:.67rem;font-weight:700;color:var(--g600);background:var(--white);border:1.5px solid var(--g300);padding:4px 10px;border-radius:7px;text-decoration:none;white-space:nowrap;transition:all .15s;cursor:pointer;}
.np-mark-btn:hover{background:var(--accent);color:var(--white);border-color:var(--accent)}
.np-close{width:28px;height:28px;border-radius:7px;background:var(--white);border:1.5px solid var(--border);color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0;}
.np-close:hover{background:var(--g200)}
.np-list{flex:1;overflow-y:auto;padding:4px 0}
.np-item{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;cursor:pointer;transition:all .15s;border-bottom:1px solid var(--border);}
.np-item:last-child{border-bottom:none}
.np-item:hover{background:var(--g050)}
.np-item.np-unread{background:rgba(24,207,180,0.04);border-left:3px solid var(--accent)}
/* FIX: distinct highlight for notice/legal types in slide panel */
.np-item.np-warn {background:rgba(245,217,138,0.08);border-left:3px solid #f5a623}
.np-item.np-legal{background:rgba(160,32,32,0.04);border-left:3px solid #dc2626}
.np-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;}
/* existing icon bg classes */
.nt-a{background:var(--g050);border:1.5px solid var(--g200)}
.nt-b{background:var(--g100);border:1.5px solid var(--g300)}
.nt-c{background:var(--g200);border:1.5px solid var(--g400)}
.nt-d{background:linear-gradient(135deg,var(--g400),var(--g350));border:1.5px solid var(--g300)}
.nt-e{background:#fff0f0;border:1.5px solid #f5b8b8}
/* FIX: new icon bg classes for notice types */
.nt-warn {background:#fff8e8;border:1.5px solid #f5d98a}
.nt-legal{background:#fff0f0;border:1.5px solid #f5b8b8}
.np-body{flex:1;min-width:0}
.np-msg{font-size:.76rem;color:var(--text);line-height:1.55;margin-bottom:3px;font-weight:500}
.np-time{font-size:.62rem;color:var(--muted)}
.np-dot{width:8px;height:8px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:5px}

@keyframes bell-shake{0%,100%{transform:rotate(0)}15%{transform:rotate(10deg)}30%{transform:rotate(-10deg)}45%{transform:rotate(6deg)}60%{transform:rotate(-6deg)}75%{transform:rotate(3deg)}}
.bell-ring{animation:bell-shake .65s ease-in-out}
@keyframes fade-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.cr,.ua-item{animation:fade-in .3s ease both}
<?php foreach($my_complaints as $i=>$_): ?>.cr:nth-child(<?= $i+1 ?>){animation-delay:<?= $i*.04 ?>s}<?php endforeach; ?>
/* ════════ HAMBURGER ════════ */
.mob-menu-btn{display:none;flex-direction:column;justify-content:center;gap:5px;width:38px;height:38px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);border-radius:10px;cursor:pointer;padding:9px 10px;flex-shrink:0;transition:all .18s;}
.mob-menu-btn span{display:block;width:18px;height:2px;background:var(--white);border-radius:2px;transition:all .25s;}
.mob-menu-btn:hover{background:rgba(24,207,180,0.2);border-color:rgba(24,207,180,0.4)}
.mob-backdrop{display:none;position:fixed;inset:0;z-index:98;background:rgba(4,26,15,.55);backdrop-filter:blur(3px);opacity:0;transition:opacity .25s;}
.mob-backdrop.on{opacity:1}
.sidebar.mob-open{transform:translateX(0)!important}

@media(max-width:960px){
  html,body{overflow:auto}
  body{display:block}
  .sidebar{position:fixed;top:0;left:0;height:100%;z-index:99;transform:translateX(-100%);transition:transform .28s cubic-bezier(.4,0,.2,1);box-shadow:8px 0 40px rgba(4,26,15,.3);}
  .main{height:auto;overflow:visible}
  .topbar{position:sticky;top:0;z-index:50}
  .mob-menu-btn{display:flex}
  .mob-backdrop{display:block;pointer-events:none}
  .mob-backdrop.on{pointer-events:all}
  .stat-row{grid-template-columns:repeat(3,1fr)}
  .dash-grid{grid-template-columns:1fr}
  .tb-center{display:none}
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
  .stat-row{grid-template-columns:1fr 1fr;gap:8px}
  .sc{padding:12px 12px}
  .sc-ico{width:36px;height:36px;font-size:1rem}
  .sc-num{font-size:1.5rem}
  .sc-badge{display:none}
  .cr{padding:10px 12px;gap:8px}
  .cr:hover{padding-left:14px}
  .cr-ico{width:32px;height:32px}
  .cr-title{font-size:.78rem}
  .cr-actions{opacity:1;pointer-events:all}
  .ua-item{flex-wrap:wrap;gap:8px}
  .assign-btn{width:100%}
  .qa-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:400px){
  .stat-row{grid-template-columns:1fr 1fr}
  .tb-btn{display:none}
}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-logo"><div class="sb-mark">🏛️</div><div><div class="sb-name">Nagrik Seva</div><div class="sb-sub">Officer Portal</div></div></div>
  <div class="sb-divider"></div>
  <div class="sb-sec">Main</div>
  <a class="nav-a on" href="officer_dashboard.php"><span class="nav-ico">⊞</span> Dashboard</a>
  <a class="nav-a" href="officer_complaints.php"><span class="nav-ico">≡</span> My Complaints</a>
  <a class="nav-a" href="officer_complaints.php?scope=zone"><span class="nav-ico">📥</span> Unassigned <?php if(!empty($unassigned)): ?><span class="nav-badge"><?= count($unassigned) ?></span><?php endif; ?></a>
  <a class="nav-a" href="track.php"><span class="nav-ico">⊙</span> Track Complaint</a>
  <a class="nav-a" href="public_board.php"><span class="nav-ico">◎</span> Public Board</a>
  <div class="sb-sec">Account</div>
  <a class="nav-a" href="officer_profile.php"><span class="nav-ico">○</span> My Profile</a>
  <a class="nav-a" href="officer_complaints.php?filter=escalated"><span class="nav-ico">🚨</span> Escalated <?php if(!empty($stats['escalated'])&&$stats['escalated']>0): ?><span class="nav-badge"><?= $stats['escalated'] ?></span><?php endif; ?></a>
  <div class="sb-sec">Info</div>
  <a class="nav-a" href="about.php"><span class="nav-ico">ℹ</span> About</a>
  <a class="nav-a" href="contact.php"><span class="nav-ico">✉</span> Contact</a>
  <div class="sb-foot">
    <div class="u-card">
      <div class="u-av"><?= $initials ?></div>
      <div><div class="u-name"><?= htmlspecialchars($name) ?></div><div class="u-role">Officer</div></div>
      <a href="logout.php" class="u-logout" title="Sign out"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
  </div>
</aside>

<!-- MOBILE BACKDROP -->
<div class="mob-backdrop" id="mob-backdrop" onclick="closeMobSidebar()"></div>

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
      <div class="tb-tagline">Officer Command Centre</div>
    </div>
    <div class="tb-right">
      <button class="tb-notif" id="bell-btn" onclick="toggleNotifPanel()" type="button">
        🔔<?php if($unread>0): ?><span class="tb-ndot" id="bell-dot"></span><?php endif; ?>
      </button>
      <a href="officer_complaints.php"><button class="tb-btn">📋 All Complaints</button></a>
    </div>
  </div>

  <div class="body">
    <div class="welcome-banner">
      <div>
        <div class="wb-greeting"><?= $greeting ?>, <?= htmlspecialchars($first_name) ?> 👮</div>
        <div class="wb-sub">You have <strong style="color:var(--g900)"><?= $pending_count ?> active complaints</strong> to resolve.<?php if($stats['escalated']>0): ?> <strong style="color:#7f1d1d"><?= $stats['escalated'] ?> escalated</strong> — needs urgent attention.<?php else: ?> Your resolution rate is excellent — keep it up!<?php endif; ?></div>
      </div>
      <div class="wb-stat">
        <div class="wb-big-num"><?= $stats['total'] ?></div>
        <div class="wb-big-lbl">Total Assigned</div>
        <div class="wb-prog-row">
          <div class="wb-prog-bar"><div class="wb-prog-fill" style="width:<?= $progress_pct ?>%"></div></div>
          <div class="wb-prog-pct"><?= $progress_pct ?>% resolved</div>
        </div>
      </div>
    </div>

    <div class="stat-row">
      <div class="sc sc-accent"><div class="sc-ico sc-ico-total">📋</div><div style="flex:1"><div class="sc-num"><?= $stats['total'] ?></div><div class="sc-lbl">Total Assigned</div></div></div>
      <div class="sc"><div class="sc-ico sc-ico-new">⏳</div><div style="flex:1"><div class="sc-num"><?= $stats['assigned'] ?></div><div class="sc-lbl">Pending Start</div></div><?php if($stats['assigned']>0): ?><span class="sc-badge sc-badge-warn">Pending</span><?php endif; ?></div>
      <div class="sc"><div class="sc-ico sc-ico-prog">🔧</div><div style="flex:1"><div class="sc-num"><?= $stats['in_progress'] ?></div><div class="sc-lbl">In Progress</div></div></div>
      <div class="sc"><div class="sc-ico sc-ico-done">✅</div><div style="flex:1"><div class="sc-num"><?= $stats['resolved'] ?></div><div class="sc-lbl">Resolved</div></div><?php if($stats['resolved']>0): ?><span class="sc-badge sc-badge-done">Done</span><?php endif; ?></div>
      <div class="sc"><div class="sc-ico sc-ico-esc">🚨</div><div style="flex:1"><div class="sc-num"><?= $stats['escalated'] ?></div><div class="sc-lbl">Escalated</div></div><?php if($stats['escalated']>0): ?><span class="sc-badge sc-badge-esc">Urgent</span><?php endif; ?></div>
    </div>

    <div class="dash-grid">
      <div id="complaints">
        <div class="card" style="margin-bottom:18px">
          <div class="ch">
            <div><div class="ch-title">My Assigned Complaints</div><div class="ch-sub"><?= count($my_complaints) ?> total · recently updated first</div></div>
            <a href="officer_complaints.php" class="ch-act">View All →</a>
          </div>
          <?php if($featured): $fc_sc=$s_cfg[$featured['status']]??['label'=>ucfirst($featured['status']),'cls'=>'s-new']; ?>
          <div class="featured-complaint">
            <div class="fc-label">Active Complaint</div>
            <div class="fc-title"><?= htmlspecialchars($featured['title']) ?></div>
            <div class="fc-meta">
              <span class="fc-pill">📍 <?= htmlspecialchars($featured['location']) ?></span>
              <span class="pill <?= $fc_sc['cls'] ?>"><?= $fc_sc['label'] ?></span>
              <?php if(!empty($featured['citizen_name'])): ?><span class="fc-citizen">· 👤 <?= htmlspecialchars($featured['citizen_name']) ?></span><?php endif; ?>
              <a href="officer_complaint_detail.php?id=<?= $featured['id'] ?>" class="fc-track-btn">Update →</a>
            </div>
          </div>
          <?php endif; ?>
          <div class="prog-wrap">
            <div class="prog-meta">Resolution <strong><?= $resolved_count ?>/<?= $total_count ?></strong></div>
            <div class="prog-track"><div class="prog-fill" style="width:<?= $progress_pct ?>%"></div></div>
            <div class="prog-pct"><?= $progress_pct ?>%</div>
          </div>
          <?php if(empty($my_complaints)): ?>
          <div class="empty"><span class="empty-ico">📭</span><div class="empty-title">No complaints assigned yet</div><div class="empty-sub">Check the Unassigned panel below.</div></div>
          <?php else: ?>
          <div class="comp-list">
            <?php foreach($my_complaints as $c):
              $sc  = $s_cfg[$c['status']]??['label'=>ucfirst($c['status']),'cls'=>'s-new'];
              $pri = $pri_cfg[$c['priority']??'medium']??['label'=>'Medium','cls'=>'p-med'];
              $ico = $cat_icon[$c['category']]??'📋';
            ?>
            <div class="cr" id="cr-<?= $c['id'] ?>">
              <div class="cr-ico" onclick="location.href='officer_complaint_detail.php?id=<?= $c['id'] ?>'"><?= $ico ?></div>
              <div class="cr-body" onclick="location.href='officer_complaint_detail.php?id=<?= $c['id'] ?>'">
                <div class="cr-id"><?= htmlspecialchars($c['complaint_no']) ?></div>
                <div class="cr-title"><?= htmlspecialchars($c['title']) ?></div>
                <div class="cr-meta">
                  <span class="cr-loc">📍 <?= htmlspecialchars($c['location']) ?></span>
                  <span class="cr-date">· <?= date('d M Y',strtotime($c['created_at'])) ?></span>
                  <?php if(!empty($c['citizen_name'])): ?><span class="cr-citizen">· 👤 <?= htmlspecialchars($c['citizen_name']) ?></span><?php endif; ?>
                </div>
              </div>
              <div class="cr-right">
                <span class="pill <?= $sc['cls'] ?>"><?= $sc['label'] ?></span>
                <span class="<?= $pri['cls'] ?>"><?= $pri['label'] ?></span>
              </div>
              <div class="cr-actions">
                <a href="officer_complaint_detail.php?id=<?= $c['id'] ?>" class="cra" title="Update" onclick="event.stopPropagation()">✏️</a>
                <a href="track.php?no=<?= urlencode($c['complaint_no']) ?>" class="cra" title="Track" onclick="event.stopPropagation()">🗺️</a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="ch"><div><div class="ch-title">📥 Unassigned in Your Zone</div><div class="ch-sub"><?= count($unassigned) ?> waiting · click to self-assign</div></div></div>
          <?php if(empty($unassigned)): ?>
          <div class="empty" style="padding:28px"><span class="empty-ico" style="font-size:1.8rem">🎉</span><div class="empty-title">All clear!</div><div class="empty-sub" style="margin-bottom:0">No unassigned complaints in your zone.</div></div>
          <?php else: ?>
          <div>
            <?php foreach($unassigned as $u):
              $ico=$cat_icon[$u['category']]??'📋';
              $pri=$pri_cfg[$u['priority']??'medium']??['label'=>'Medium','cls'=>'p-med'];
            ?>
            <div class="ua-item">
              <div class="ua-ico"><?= $ico ?></div>
              <div class="ua-body">
                <div class="ua-id"><?= htmlspecialchars($u['complaint_no']) ?></div>
                <div class="ua-title"><?= htmlspecialchars($u['title']) ?></div>
                <div class="ua-meta">📍 <?= htmlspecialchars($u['location']) ?> · 👤 <?= htmlspecialchars($u['citizen_name']??'Citizen') ?> · <?= date('d M',strtotime($u['created_at'])) ?></div>
              </div>
              <span class="<?= $pri['cls'] ?>" style="margin-right:8px;flex-shrink:0"><?= $pri['label'] ?></span>
              <form method="POST" onclick="event.stopPropagation()">
                <input type="hidden" name="action" value="self_assign">
                <input type="hidden" name="complaint_id" value="<?= $u['id'] ?>">
                <button type="submit" class="assign-btn">+ Assign to Me</button>
              </form>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="quick-card">
          <div class="ch"><div class="ch-title">Quick Actions</div></div>
          <div class="qa-grid">
            <a class="qa" href="officer_complaints.php?filter=in_progress"><span class="qa-ico">🔧</span><span class="qa-lbl">In Progress</span><span class="qa-desc">Update status</span></a>
            <a class="qa" href="officer_complaints.php?scope=zone"><span class="qa-ico">📥</span><span class="qa-lbl">Unassigned</span><span class="qa-desc">Pick up cases</span></a>
            <a class="qa" href="public_board.php"><span class="qa-ico">🌐</span><span class="qa-lbl">Public Board</span><span class="qa-desc">All issues</span></a>
            <a class="qa" href="officer_profile.php"><span class="qa-ico">👤</span><span class="qa-lbl">My Profile</span><span class="qa-desc">Edit details</span></a>
          </div>
        </div>

        <!-- NOTIFICATIONS CARD -->
        <div class="quick-card">
          <div class="ch">
            <div><div class="ch-title">Notifications</div><?php if($unread>0): ?><div class="ch-sub"><?= $unread ?> unread</div><?php endif; ?></div>
            <?php if($unread>0): ?><a href="officer_dashboard.php?read_notifs=1" class="ch-act">Mark read</a><?php endif; ?>
          </div>
          <?php if(empty($notifs)): ?>
          <div class="empty" style="padding:24px"><span class="empty-ico" style="font-size:1.5rem">🔔</span><div class="empty-title">All caught up</div><div class="empty-sub" style="margin-bottom:0">Updates will appear here.</div></div>
          <?php else: ?>
          <div class="notif-list">
            <?php foreach(array_slice($notifs,0,5) as $n):
              $type = $n['type'] ?? 'info';
              // FIX: safe icon lookup with default fallback
              $ic   = $notif_icons[$type] ?? ['ico'=>'🔔','cls'=>'nt-a'];
              $ts   = strtotime($n['created_at']);
              $diff = time()-$ts;
              $tago = $diff<60?'Just now':($diff<3600?floor($diff/60).'m ago':($diff<86400?floor($diff/3600).'h ago':date('d M',$ts)));
              // extra row class for notice types
              $row_cls = '';
              if($type==='legal_notice')     $row_cls='ni-legal';
              elseif($type==='regulator_notice') $row_cls='ni-warn';
              elseif(!$n['is_read'])         $row_cls='unread';
              // dot class
              $dot_cls = $n['is_read']?'read':($type==='legal_notice'?'legal':($type==='regulator_notice'?'warn':''));
            ?>
            <div class="ni <?= $row_cls ?>">
              <div class="ni-dot <?= $dot_cls ?>"></div>
              <div>
                <div class="ni-msg"><?= htmlspecialchars($n['message']) ?></div>
                <div class="ni-time"><?= $ic['ico'] ?> <?= $tago ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <div class="quick-card" style="border-top:3px solid var(--g500)">
          <div class="helpline">
            <div class="hl-label">Officer Support</div>
            <div class="hl-row"><div class="hl-ico">📞</div><div><div class="hl-text">1800-233-0001</div><div class="hl-sub">Admin Support · 24/7</div></div></div>
            <div class="hl-row"><div class="hl-ico">✉️</div><div><div class="hl-text">admin@nagrikseva.gov.in</div><div class="hl-sub">Escalation requests</div></div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- NOTIF PANEL -->
<div class="notif-backdrop" id="notif-backdrop" onclick="closeNotifPanel()"></div>
<div class="notif-panel" id="notif-panel">
  <div class="np-head">
    <div><div class="np-title">🔔 Notifications</div><div class="np-sub"><?= $unread>0?"$unread unread":"All caught up" ?></div></div>
    <div style="display:flex;align-items:center;gap:8px">
      <?php if($unread>0): ?><a href="officer_dashboard.php?read_notifs=1" class="np-mark-btn">Mark all read</a><?php endif; ?>
      <button class="np-close" onclick="closeNotifPanel()">✕</button>
    </div>
  </div>
  <div class="np-list">
    <?php if(empty($notifs)): ?>
    <div style="padding:48px 24px;text-align:center;color:var(--muted)"><div style="font-size:2rem;margin-bottom:8px">🔔</div><div style="font-size:.83rem;font-weight:700;margin-bottom:4px">No notifications yet</div></div>
    <?php else: foreach($notifs as $n):
      $type=$n['type']??'info';
      // FIX: safe icon lookup
      $ic  =$notif_icons[$type]??['ico'=>'🔔','cls'=>'nt-a'];
      $ts  =strtotime($n['created_at']); $diff=time()-$ts;
      $tago=$diff<60?'Just now':($diff<3600?floor($diff/60).'m ago':($diff<86400?floor($diff/3600).'h ago':($diff<604800?floor($diff/86400).'d ago':date('d M',$ts))));
      // FIX: pick the right highlight class
      $item_cls = 'np-item';
      if($type==='legal_notice')      $item_cls.=' np-legal';
      elseif($type==='regulator_notice') $item_cls.=' np-warn';
      elseif(!$n['is_read'])          $item_cls.=' np-unread';
    ?>
    <div class="<?= $item_cls ?>">
      <div class="np-ico <?= $ic['cls'] ?>"><?= $ic['ico'] ?></div>
      <div class="np-body"><div class="np-msg"><?= htmlspecialchars($n['message']) ?></div><div class="np-time"><?= $tago ?></div></div>
      <?php if(!$n['is_read']): ?><div class="np-dot"></div><?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<script>
function toggleMobSidebar(){const s=document.querySelector('.sidebar'),b=document.getElementById('mob-backdrop');s.classList.toggle('mob-open');b.classList.toggle('on');document.body.style.overflow=s.classList.contains('mob-open')?'hidden':'';}
function closeMobSidebar(){const s=document.querySelector('.sidebar'),b=document.getElementById('mob-backdrop');s.classList.remove('mob-open');b.classList.remove('on');document.body.style.overflow='';}
const nPanel=document.getElementById('notif-panel'),nBackdrop=document.getElementById('notif-backdrop');
function toggleNotifPanel(){nPanel.classList.contains('open')?closeNotifPanel():openNotifPanel();}
function openNotifPanel(){nPanel.classList.add('open');nBackdrop.classList.add('on');const b=document.getElementById('bell-btn');b.classList.add('bell-ring');setTimeout(()=>b.classList.remove('bell-ring'),700);}
function closeNotifPanel(){nPanel.classList.remove('open');nBackdrop.classList.remove('on');}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){if(nPanel.classList.contains('open'))closeNotifPanel();else closeMobSidebar();}});
</script>
</body>
</html>