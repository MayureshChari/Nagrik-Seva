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

// ── POST actions ──
$toast_ok = $toast_err = '';
$is_demo_session = !empty($_SESSION['is_demo']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $off_id  = (int)($_POST['officer_id'] ?? 0);
    $msg     = trim($_POST['message'] ?? '');

    if ($is_demo_session) {
        // Demo mode: simulate actions without touching DB
        if ($action === 'send_notification') $toast_ok = 'Demo: Performance notification sent to officer. (No DB write in demo mode)';
        elseif ($action === 'send_legal')    $toast_ok = 'Demo: Legal notice issued to officer. (No DB write in demo mode)';
        elseif ($action === 'terminate')     $toast_ok = 'Demo: Officer account terminated. (No DB write in demo mode)';
        elseif ($action === 'reinstate')     $toast_ok = 'Demo: Officer account reinstated. (No DB write in demo mode)';
        elseif ($action === 'escalate_complaint') $toast_ok = 'Demo: Complaint escalated. (No DB write in demo mode)';
        if ($toast_ok) { header('Location: regulator_dashboard.php?msg='.urlencode($toast_ok).'&type=ok'); exit; }
    } elseif ($uid > 0) {
        if ($action === 'send_notification' && $off_id && $msg) {
            $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message,created_at) VALUES($off_id,NULL,'regulator_notice','".addslashes($msg)."',NOW())");
            $conn->query("UPDATE users SET last_notice=NOW(), notice_count=IFNULL(notice_count,0)+1 WHERE id=$off_id AND role='officer'");
            $toast_ok = 'Performance notification sent to officer.';
        } elseif ($action === 'send_legal' && $off_id && $msg) {
            $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message,created_at) VALUES($off_id,NULL,'legal_notice','⚖️ LEGAL NOTICE: ".addslashes($msg)."',NOW())");
            $conn->query("UPDATE users SET last_legal=NOW(), legal_count=IFNULL(legal_count,0)+1 WHERE id=$off_id AND role='officer'");
            $toast_ok = 'Legal notice issued to officer.';
        } elseif ($action === 'terminate' && $off_id) {
            $reason = trim($_POST['reason'] ?? 'Account terminated by regulator due to non-compliance.');
            $conn->query("UPDATE users SET is_active=0, terminated_at=NOW(), termination_reason='".addslashes($reason)."' WHERE id=$off_id AND role='officer'");
            $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message,created_at) VALUES($off_id,NULL,'terminated','Your officer account has been terminated. Reason: ".addslashes($reason)."',NOW())");
            $toast_ok = 'Officer account has been terminated.';
        } elseif ($action === 'reinstate' && $off_id) {
            $conn->query("UPDATE users SET is_active=1, terminated_at=NULL, termination_reason=NULL WHERE id=$off_id AND role='officer'");
            $toast_ok = 'Officer account has been reinstated.';
        } elseif ($action === 'escalate_complaint') {
            $cid = (int)($_POST['complaint_id'] ?? 0);
            if ($cid) {
                $conn->query("UPDATE complaints SET status='escalated', updated_at=NOW() WHERE id=$cid");
                $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message) SELECT officer_id,$cid,'escalated','Complaint escalated by regulator. Immediate action required.' FROM complaints WHERE id=$cid AND officer_id IS NOT NULL");
                $toast_ok = 'Complaint escalated.';
            }
        }
        if ($toast_ok || $toast_err) { header('Location: regulator_dashboard.php?msg='.urlencode($toast_ok ?: $toast_err).'&type='.($toast_ok?'ok':'err')); exit; }
    }
}
if (isset($_GET['msg'])) {
    if (($_GET['type'] ?? '') === 'ok') $toast_ok = $_GET['msg'];
    else $toast_err = $_GET['msg'];
}

// ── Platform stats ──
$platform = ['total_complaints'=>0,'resolved'=>0,'pending'=>0,'escalated'=>0,'total_officers'=>0,'active_officers'=>0,'inactive_officers'=>0];
if ($uid > 0) {
    $r = $conn->query("SELECT status, COUNT(*) as c FROM complaints GROUP BY status");
    if ($r) while ($row = $r->fetch_assoc()) {
        $platform['total_complaints'] += $row['c'];
        if ($row['status'] === 'resolved' || $row['status'] === 'closed') $platform['resolved'] += $row['c'];
        if (in_array($row['status'], ['new','assigned','in_progress'])) $platform['pending'] += $row['c'];
        if ($row['status'] === 'escalated') $platform['escalated'] += $row['c'];
    }
    $r2 = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='officer'"); if($r2) $platform['total_officers'] = (int)$r2->fetch_assoc()['c'];
    $r3 = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='officer' AND is_active=1"); if($r3) $platform['active_officers'] = (int)$r3->fetch_assoc()['c'];
    $platform['inactive_officers'] = $platform['total_officers'] - $platform['active_officers'];
}

// ── Officers with performance data ──
$officers = [];
if ($uid > 0) {
    // Add missing columns to users table if they don't exist yet
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS notice_count      INT          NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS legal_count       INT          NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_notice       DATETIME     NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_legal        DATETIME     NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS terminated_at     DATETIME     NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS termination_reason VARCHAR(500) NULL");

    $r = $conn->query(
        "SELECT u.*,
            COUNT(c.id) as total_assigned,
            SUM(c.status IN ('resolved','closed')) as resolved_count,
            SUM(c.status IN ('new','assigned','in_progress')) as pending_count,
            SUM(c.status = 'escalated') as escalated_count,
            MAX(c.updated_at) as last_activity,
            IFNULL(u.notice_count,0) as notice_count,
            IFNULL(u.legal_count,0) as legal_count
         FROM users u
         LEFT JOIN complaints c ON c.officer_id = u.id
         WHERE u.role = 'officer'
         GROUP BY u.id
         ORDER BY pending_count DESC, u.name ASC"
    );
    if ($r) while ($row = $r->fetch_assoc()) $officers[] = $row;
}

// ── Dummy fallback ──
if (empty($officers)) {
    $platform = ['total_complaints'=>47,'resolved'=>29,'pending'=>15,'escalated'=>3,'total_officers'=>6,'active_officers'=>5,'inactive_officers'=>1];
    $officers = [
        ['id'=>1,'name'=>'Suresh Kamat',    'email'=>'s.kamat@nagrikseva.gov',    'zone'=>'Panaji',  'department'=>'Road & PWD',   'is_active'=>1,'total_assigned'=>12,'resolved_count'=>9,'pending_count'=>3,'escalated_count'=>0,'last_activity'=>date('Y-m-d H:i:s',strtotime('-2 hours')), 'notice_count'=>0,'legal_count'=>0,'created_at'=>date('Y-m-d',strtotime('-180 days'))],
        ['id'=>2,'name'=>'Priya Dessai',    'email'=>'p.dessai@nagrikseva.gov',   'zone'=>'Margao',  'department'=>'Water Supply', 'is_active'=>1,'total_assigned'=>8, 'resolved_count'=>5,'pending_count'=>2,'escalated_count'=>1,'last_activity'=>date('Y-m-d H:i:s',strtotime('-1 day')),  'notice_count'=>1,'legal_count'=>0,'created_at'=>date('Y-m-d',strtotime('-150 days'))],
        ['id'=>3,'name'=>'Anton Fernandes', 'email'=>'a.fernandes@nagrikseva.gov','zone'=>'Vasco',   'department'=>'Electricity',  'is_active'=>1,'total_assigned'=>10,'resolved_count'=>7,'pending_count'=>3,'escalated_count'=>0,'last_activity'=>date('Y-m-d H:i:s',strtotime('-3 hours')),'notice_count'=>0,'legal_count'=>0,'created_at'=>date('Y-m-d',strtotime('-200 days'))],
        ['id'=>4,'name'=>'Raj Naik',        'email'=>'r.naik@nagrikseva.gov',     'zone'=>'Mapusa',  'department'=>'Road & PWD',   'is_active'=>1,'total_assigned'=>6, 'resolved_count'=>1,'pending_count'=>5,'escalated_count'=>2,'last_activity'=>date('Y-m-d H:i:s',strtotime('-8 days')), 'notice_count'=>2,'legal_count'=>1,'created_at'=>date('Y-m-d',strtotime('-90 days'))],
        ['id'=>5,'name'=>'Sunita Borkar',   'email'=>'s.borkar@nagrikseva.gov',   'zone'=>'Ponda',   'department'=>'Sanitation',   'is_active'=>1,'total_assigned'=>9, 'resolved_count'=>7,'pending_count'=>2,'escalated_count'=>0,'last_activity'=>date('Y-m-d H:i:s',strtotime('-5 hours')),'notice_count'=>0,'legal_count'=>0,'created_at'=>date('Y-m-d',strtotime('-220 days'))],
        ['id'=>6,'name'=>'David Gomes',     'email'=>'d.gomes@nagrikseva.gov',    'zone'=>'Calangute','department'=>'Property',    'is_active'=>0,'total_assigned'=>2, 'resolved_count'=>0,'pending_count'=>2,'escalated_count'=>0,'last_activity'=>date('Y-m-d H:i:s',strtotime('-22 days')),'notice_count'=>3,'legal_count'=>2,'created_at'=>date('Y-m-d',strtotime('-60 days'))],
    ];
}

// ── Recent escalated complaints ──
$escalated = [];
if ($uid > 0) {
    $r = $conn->query("SELECT c.*,u.name as citizen_name,o.name as officer_name FROM complaints c LEFT JOIN users u ON c.citizen_id=u.id LEFT JOIN users o ON c.officer_id=o.id WHERE c.status='escalated' ORDER BY c.updated_at DESC LIMIT 10");
    if ($r) while ($row=$r->fetch_assoc()) $escalated[] = $row;
}
if (empty($escalated)) {
    $escalated = [
        ['id'=>6,'complaint_no'=>'GRV-F9B4D8','category'=>'water',     'title'=>'No water supply — Dona Paula Ward',    'location'=>'Dona Paula','priority'=>'high',  'status'=>'escalated','citizen_name'=>'David Gomes',  'officer_name'=>'Raj Naik',     'created_at'=>date('Y-m-d H:i:s',strtotime('-20 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-5 days'))],
        ['id'=>9,'complaint_no'=>'GRV-I5L8Q2','category'=>'electricity','title'=>'Transformer sparking in Mapusa',      'location'=>'Mapusa',    'priority'=>'high',  'status'=>'escalated','citizen_name'=>"Conceicao D'M",'officer_name'=>'Raj Naik',     'created_at'=>date('Y-m-d H:i:s',strtotime('-4 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-2 days'))],
        ['id'=>6,'complaint_no'=>'GRV-0006',   'category'=>'road',      'title'=>'Broken road divider — Calangute Hwy', 'location'=>'Calangute', 'priority'=>'high',  'status'=>'escalated','citizen_name'=>'Rohan Sawant',  'officer_name'=>null,           'created_at'=>date('Y-m-d H:i:s',strtotime('-10 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-6 days'))],
    ];
}

// ── Notifications for regulator ──
$notifs = [];
$unread = 0;
if ($uid > 0) {
    $r = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 8");
    if ($r) while ($row=$r->fetch_assoc()) $notifs[] = $row;
    $r2 = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0");
    if ($r2) $unread = (int)($r2->fetch_assoc()['c'] ?? 0);
}
if (empty($notifs)) {
    $unread = 2;
    $notifs = [
        ['id'=>1,'type'=>'escalated','message'=>'GRV-F9B4D8 escalated — officer Raj Naik has not responded in 5 days.','created_at'=>date('Y-m-d H:i:s',strtotime('-3 hours')),'is_read'=>0],
        ['id'=>2,'type'=>'alert',    'message'=>'Officer David Gomes inactive for 22 days. Legal notice already issued.','created_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'is_read'=>0],
        ['id'=>3,'type'=>'resolved', 'message'=>'Monthly resolution rate: 74% — up 6% from last month.','created_at'=>date('Y-m-d H:i:s',strtotime('-2 days')),'is_read'=>1],
    ];
}
if (isset($_GET['read_notifs'])) {
    if ($uid > 0 && !$is_demo_session) $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    header('Location: regulator_dashboard.php'); exit;
}

// ── Helpers ──
$cat_icon = ['road'=>'🚧','water'=>'💧','electricity'=>'⚡','sanitation'=>'🧹','property'=>'🏛️','lost'=>'🔍'];
$hour     = (int)date('H');
$greeting = $hour<12 ? 'Good morning' : ($hour<18 ? 'Good afternoon' : 'Good evening');

function days_since($dt) {
    if (!$dt) return 999;
    return floor((time() - strtotime($dt)) / 86400);
}
function officer_status($o) {
    if (!$o['is_active']) return ['label'=>'Terminated','cls'=>'os-term','color'=>'#a02020'];
    $days = days_since($o['last_activity'] ?? null);
    $rate = $o['total_assigned'] > 0 ? round(($o['resolved_count'] / $o['total_assigned']) * 100) : 0;
    if ($days > 7)  return ['label'=>'Inactive',    'cls'=>'os-inactive','color'=>'#a02020'];
    if ($days > 3)  return ['label'=>'Slow',        'cls'=>'os-slow',    'color'=>'#b07b00'];
    if ($rate < 30) return ['label'=>'Low Perf.',   'cls'=>'os-low',     'color'=>'#b07b00'];
    return            ['label'=>'Active',        'cls'=>'os-active',  'color'=>'#065449'];
}

$overall_rate = $platform['total_complaints'] > 0 ? round(($platform['resolved'] / $platform['total_complaints']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Regulator Dashboard — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g900:#011a18;--g800:#042e2a;--g750:#053d37;--g700:#065449;--g650:#0a6e60;
  --g600:#0d8572;--g500:#109e88;--g450:#14b89f;--g400:#18cfb4;--g350:#3ddbc3;
  --g300:#6ce5d2;--g200:#adf2e8;--g150:#cef7f2;--g100:#e2faf7;--g050:#f0fdfb;
  --white:#ffffff;--accent:#18cfb4;
  --bg:#f0f9f4;--card:#ffffff;--text:#0d2b1b;--muted:#4a7260;--muted2:#5e8a72;
  --border:#c8e8d8;--border2:#a0d4b8;--radius:14px;
  --shadow:0 2px 12px rgba(13,43,27,0.07),0 1px 3px rgba(13,43,27,0.05);
  --shadow-md:0 8px 28px rgba(13,43,27,0.11),0 2px 8px rgba(13,43,27,0.07);
  --shadow-lg:0 20px 56px rgba(13,43,27,0.16),0 4px 16px rgba(13,43,27,0.08);
}
html,body{height:100%;overflow:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;-webkit-font-smoothing:antialiased;}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:var(--g300);border-radius:4px}
::-webkit-scrollbar-track{background:transparent}

/* ════ SIDEBAR ════ */
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
.nav-badge-red{background:#dc2626;color:#fff}
.sb-foot{margin-top:auto;padding:12px 10px;border-top:1px solid rgba(255,255,255,0.06)}
.u-card{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:11px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);transition:all .18s;}
.u-card:hover{background:rgba(255,255,255,0.08)}
.u-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));border:2px solid rgba(24,207,180,0.4);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--white);flex-shrink:0;box-shadow:0 2px 8px rgba(24,207,180,0.25);}
.u-name{font-size:.77rem;font-weight:600;color:var(--white)}
.u-role{font-size:.58rem;color:var(--g300);margin-top:1px;letter-spacing:.5px;text-transform:uppercase}
.u-logout{margin-left:auto;background:none;border:none;color:var(--g300);cursor:pointer;padding:5px;border-radius:7px;transition:all .15s;}
.u-logout:hover{color:var(--white);background:rgba(255,255,255,0.1);transform:scale(1.1)}

/* ════ TOPBAR ════ */
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
.tb-badge{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:rgba(24,207,180,.15);border:1px solid rgba(24,207,180,.3);font-size:.74rem;font-weight:600;color:var(--g200);}

/* ════ BODY ════ */
.body{padding:22px 28px;flex:1;}
.toast{display:flex;align-items:center;gap:9px;padding:12px 16px;border-radius:10px;font-size:.8rem;margin-bottom:18px;border:1px solid transparent;font-weight:500;animation:slide-in-down .3s ease;}
@keyframes slide-in-down{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.t-ok{background:var(--g100);border-color:var(--g300);color:var(--g700)}
.t-err{background:#fff0f0;border-color:#f5b8b8;color:#a02020}

/* WELCOME BANNER */
.welcome-banner{background:linear-gradient(135deg,var(--g700) 0%,var(--g600) 60%,var(--g500) 100%);border-radius:16px;padding:24px 28px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;position:relative;overflow:hidden;box-shadow:var(--shadow-md);}
.welcome-banner::before{content:'';position:absolute;top:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(24,207,180,0.15),transparent 70%);pointer-events:none;}
.wb-greeting{font-size:1.4rem;font-weight:700;color:var(--white);letter-spacing:-.3px;line-height:1.15;margin-bottom:6px}
.wb-sub{font-size:.8rem;color:rgba(255,255,255,0.7);line-height:1.6;}
.wb-stat{display:flex;flex-direction:column;align-items:flex-end;gap:6px;position:relative;z-index:1;}
.wb-big-num{font-size:3rem;font-weight:800;color:var(--white);line-height:1;letter-spacing:-2px}
.wb-big-lbl{font-size:.62rem;font-weight:500;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:.9px;text-align:right}
.wb-prog-row{display:flex;align-items:center;gap:10px;margin-top:6px}
.wb-prog-bar{width:120px;height:5px;background:rgba(255,255,255,0.15);border-radius:3px;overflow:hidden}
.wb-prog-fill{height:100%;background:linear-gradient(90deg,var(--g300),var(--white));border-radius:3px;transition:width .8s}
.wb-prog-pct{font-size:.68rem;color:var(--g200);font-weight:600}

/* STAT CARDS */
.stat-row{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:22px}
.sc{border-radius:var(--radius);padding:14px 16px;display:flex;align-items:center;gap:11px;background:var(--card);border:1.5px solid var(--border);transition:all .22s;cursor:default;position:relative;overflow:hidden;box-shadow:var(--shadow);}
.sc::after{content:'';position:absolute;inset:0;opacity:0;background:linear-gradient(135deg,rgba(24,207,180,0.05),transparent);transition:opacity .22s;}
.sc:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--border2)}
.sc:hover::after{opacity:1}
.sc:hover .sc-ico{transform:scale(1.1) rotate(-3deg)}
.sc-accent{border-left:3px solid var(--accent)}
.sc-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;transition:transform .22s;}
.sc-ico-a{background:var(--g100);border:1.5px solid var(--border)}
.sc-ico-b{background:#e8f8ee;border:1.5px solid var(--g300)}
.sc-ico-c{background:#fff8e8;border:1.5px solid #f5d98a}
.sc-ico-d{background:#fff0f0;border:1.5px solid #f5b8b8}
.sc-ico-e{background:var(--g100);border:1.5px solid var(--g300)}
.sc-ico-f{background:#fff0f0;border:1.5px solid #f5b8b8}
.sc-num{font-size:1.65rem;font-weight:700;letter-spacing:-.6px;line-height:1;color:var(--text)}
.sc-lbl{font-size:.59rem;text-transform:uppercase;letter-spacing:.6px;margin-top:2px;font-weight:500;color:var(--muted)}

/* MAIN GRID */
.dash-grid{display:grid;grid-template-columns:1fr 308px;gap:18px}
.card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.ch{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1.5px solid var(--border);background:linear-gradient(180deg,rgba(244,253,248,0.8),rgba(255,255,255,0));}
.ch-title{font-size:.88rem;font-weight:700;color:var(--text);}
.ch-sub{font-size:.63rem;color:var(--muted);margin-top:2px;}
.ch-act{font-size:.72rem;font-weight:700;color:var(--g600);background:var(--g050);border:1.5px solid var(--border);padding:5px 13px;border-radius:7px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .16s;}
.ch-act:hover{background:var(--accent);color:var(--white);border-color:var(--accent);transform:translateY(-1px)}

/* OFFICER CARDS */
.officer-grid{display:flex;flex-direction:column;gap:0}
.oc{display:flex;align-items:flex-start;gap:13px;padding:14px 18px;border-bottom:1px solid var(--border);transition:all .18s;position:relative;}
.oc:last-child{border-bottom:none}
.oc:hover{background:var(--g050)}
.oc-av{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));display:flex;align-items:center;justify-content:center;font-size:.88rem;font-weight:700;color:var(--white);flex-shrink:0;border:2px solid rgba(24,207,180,.3);}
.oc-av.inactive{background:linear-gradient(135deg,#aaa,#888);border-color:rgba(0,0,0,.1);}
.oc-body{flex:1;min-width:0}
.oc-name{font-size:.84rem;font-weight:700;color:var(--text);margin-bottom:2px}
.oc-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px}
.oc-dept{font-size:.63rem;color:var(--muted)}
.oc-zone{font-size:.63rem;color:var(--g500);font-weight:600}
/* mini stat pills */
.oc-stats{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:7px}
.oc-stat{display:flex;align-items:center;gap:3px;font-size:.62rem;padding:2px 7px;border-radius:5px;font-weight:600}
.ocs-total{background:var(--g100);color:var(--g600);border:1px solid var(--g200)}
.ocs-resolve{background:#e8f8ee;color:#065449;border:1px solid var(--g300)}
.ocs-pending{background:#fff8e8;color:#8a6200;border:1px solid #f5d98a}
.ocs-esc{background:#fff0f0;color:#a02020;border:1px solid #f5b8b8}
/* progress bar */
.oc-prog-row{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.oc-prog-track{flex:1;height:4px;background:var(--g100);border-radius:2px;overflow:hidden;border:1px solid var(--border)}
.oc-prog-fill{height:100%;border-radius:2px;transition:width .6s}
.oc-prog-pct{font-size:.6rem;color:var(--muted);font-family:'DM Mono',monospace;min-width:28px;text-align:right}
/* activity */
.oc-last{font-size:.61rem;color:var(--muted)}
/* badges */
.oc-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0}
.os-badge{padding:3px 8px;border-radius:5px;font-size:.58rem;font-weight:800;letter-spacing:.5px;text-transform:uppercase;border:1px solid transparent;}
.os-active  {background:var(--g100);color:var(--g600);border-color:var(--g300)}
.os-slow    {background:#fff8e8;color:#8a6200;border-color:#f5d98a}
.os-low     {background:#fff8e8;color:#8a6200;border-color:#f5d98a}
.os-inactive{background:#fff0f0;color:#a02020;border-color:#f5b8b8}
.os-term    {background:#3d0000;color:#ffb3b3;border-color:#a02020}
/* notice badges */
.notice-badges{display:flex;gap:4px}
.nb{padding:2px 6px;border-radius:4px;font-size:.55rem;font-weight:700}
.nb-notice{background:#fff8e8;color:#8a6200;border:1px solid #f5d98a}
.nb-legal {background:#fff0f0;color:#a02020;border:1px solid #f5b8b8}
/* action buttons */
.oc-actions{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap}
.oa-btn{padding:5px 11px;border-radius:7px;font-size:.68rem;font-weight:700;cursor:pointer;border:1.5px solid var(--border);background:var(--white);font-family:'Plus Jakarta Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:4px;}
.oa-btn:hover{transform:translateY(-1px)}
.oa-notif{color:var(--g600);border-color:var(--g300)}.oa-notif:hover{background:var(--g100)}
.oa-legal{color:#b07b00;border-color:#f5d98a}.oa-legal:hover{background:#fff8e8}
.oa-term {color:#a02020;border-color:#f5b8b8}.oa-term:hover{background:#fff0f0}
.oa-reinstate{color:var(--g600);border-color:var(--g300);background:var(--g100)}.oa-reinstate:hover{background:var(--g200)}

/* ESCALATED TABLE */
.esc-row{display:flex;align-items:center;gap:12px;padding:11px 18px;border-bottom:1px solid var(--border);transition:all .18s;}
.esc-row:last-child{border-bottom:none}
.esc-row:hover{background:var(--g050)}
.esc-ico{width:36px;height:36px;border-radius:9px;background:var(--g100);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;}
.esc-body{flex:1;min-width:0}
.esc-id{font-size:.59rem;font-weight:600;color:var(--accent);font-family:'DM Mono',monospace;letter-spacing:.8px;margin-bottom:2px}
.esc-title{font-size:.8rem;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px}
.esc-meta{font-size:.62rem;color:var(--muted)}
.esc-right{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0}
.esc-btn{padding:4px 10px;border-radius:6px;font-size:.64rem;font-weight:700;background:linear-gradient(135deg,var(--g500),var(--g400));color:var(--g900);border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .14s;}
.esc-btn:hover{transform:translateY(-1px)}

/* STATUS PILLS */
.pill{display:inline-flex;align-items:center;padding:3px 9px;border-radius:5px;font-size:.59rem;font-weight:800;letter-spacing:.5px;white-space:nowrap;text-transform:uppercase;border:1px solid transparent;}
.s-new      {background:#fff8e8;color:#8a6200;border-color:#f5d98a}
.s-assigned {background:var(--g100);color:var(--g600);border-color:var(--g300)}
.s-prog     {background:linear-gradient(135deg,var(--g400),var(--g350));color:var(--white);border-color:var(--g300)}
.s-resolved {background:var(--g700);color:var(--white);border-color:var(--g600)}
.s-esc      {background:#fff0f0;color:#a02020;border-color:#f5b8b8}
.p-high     {background:#fff0f0;color:#a02020;border:1px solid #f5b8b8;font-size:.55rem;font-weight:700;padding:2px 7px;border-radius:4px;}
.p-med      {background:#fff8e8;color:#8a6200;border:1px solid #f5d98a;font-size:.55rem;font-weight:700;padding:2px 7px;border-radius:4px;}

/* NOTIF LIST */
.notif-list{display:flex;flex-direction:column}
.ni{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;transition:all .15s;}
.ni:last-child{border-bottom:none}
.ni:hover{background:var(--g050)}
.ni.unread{background:rgba(24,207,180,0.04);border-left:3px solid var(--accent)}
.ni-dot{width:7px;height:7px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:5px}
.ni-dot.read{background:var(--g200)}
.ni-msg{font-size:.73rem;color:var(--text);line-height:1.5;margin-bottom:2px}
.ni-time{font-size:.6rem;color:var(--muted)}

/* QUICK ACTIONS */
.quick-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)}
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:14px}
.qa{display:flex;flex-direction:column;align-items:flex-start;gap:4px;padding:13px 11px;background:var(--g050);border:1.5px solid var(--border);border-radius:11px;cursor:pointer;transition:all .18s;font-family:'Plus Jakarta Sans',sans-serif;text-align:left;text-decoration:none;}
.qa:hover{background:var(--white);border-color:var(--accent);transform:translateY(-2px);box-shadow:0 4px 14px rgba(24,207,180,.15)}
.qa-ico{font-size:1.2rem;margin-bottom:2px}
.qa-lbl{font-size:.74rem;font-weight:700;color:var(--text)}
.qa-desc{font-size:.6rem;color:var(--muted)}

/* HELPLINE */
.helpline{padding:14px 16px}
.hl-label{font-size:.57rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-bottom:10px}
.hl-row{display:flex;align-items:center;gap:9px;margin-bottom:9px}
.hl-ico{width:30px;height:30px;border-radius:8px;background:var(--g100);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.hl-text{font-size:.76rem;font-weight:700;color:var(--text)}
.hl-sub{font-size:.6rem;color:var(--muted)}

/* EMPTY */
.empty{text-align:center;padding:36px 20px}
.empty-ico{font-size:2rem;margin-bottom:8px;opacity:.3;display:block;animation:float 3s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}

/* ════ MODALS ════ */
.overlay{position:fixed;inset:0;background:rgba(6,26,15,.6);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s;backdrop-filter:blur(4px);}
.overlay.on{opacity:1;pointer-events:all}
.modal{background:var(--white);border:1.5px solid var(--border);border-radius:18px;width:92%;max-width:480px;padding:28px;box-shadow:var(--shadow-lg);transform:scale(.95) translateY(16px);transition:transform .24s cubic-bezier(.4,0,.2,1);}
.overlay.on .modal{transform:scale(1) translateY(0)}
.mh{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px}
.mh-title{font-size:.98rem;font-weight:700;color:var(--text);}
.mh-sub{font-size:.72rem;color:var(--muted);margin-top:4px;line-height:1.5}
.mh-close{width:30px;height:30px;border-radius:8px;background:var(--g100);border:1.5px solid var(--border);color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:all .15s;flex-shrink:0;}
.mh-close:hover{background:var(--g200);color:var(--text)}
.fg{margin-bottom:14px}
.fl{font-size:.6rem;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;display:block}
textarea.fi,input.fi{width:100%;padding:10px 13px;background:var(--white);border:1.5px solid var(--border);border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.84rem;color:var(--text);outline:none;transition:all .17s;}
textarea.fi{resize:vertical;min-height:90px;line-height:1.65}
textarea.fi:focus,input.fi:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(24,207,180,.12)}
.modal-officer-info{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--g050);border:1.5px solid var(--border);border-radius:10px;margin-bottom:16px}
.modal-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;color:var(--white);flex-shrink:0;}
.modal-name{font-size:.84rem;font-weight:700;color:var(--text)}
.modal-dept{font-size:.66rem;color:var(--muted);margin-top:2px}
.quick-msgs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
.qm{padding:5px 11px;border-radius:7px;border:1.5px solid var(--border);background:var(--g050);font-size:.67rem;font-weight:600;color:var(--muted2);cursor:pointer;transition:all .14s;font-family:'Plus Jakarta Sans',sans-serif;}
.qm:hover{background:var(--g100);border-color:var(--g300);color:var(--text)}
.btn-submit{width:100%;padding:12px;background:linear-gradient(135deg,var(--g700),var(--g500));color:var(--white);border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;font-weight:700;cursor:pointer;transition:all .18s;box-shadow:0 4px 14px rgba(13,43,27,.2);}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(13,43,27,.28)}
.btn-legal{background:linear-gradient(135deg,#8a6200,#f59e0b)!important}
.btn-term{background:linear-gradient(135deg,#7f1d1d,#dc2626)!important}
.warn-box{background:#fff8e8;border:1.5px solid #f5d98a;border-radius:9px;padding:11px 14px;font-size:.75rem;color:#8a6200;line-height:1.65;margin-bottom:14px}
.danger-box{background:#fff0f0;border:1.5px solid #f5b8b8;border-left:4px solid #dc2626;border-radius:9px;padding:11px 14px;font-size:.75rem;color:#a02020;line-height:1.65;margin-bottom:14px}

/* NOTIF PANEL */
.notif-backdrop{position:fixed;inset:0;z-index:149;background:transparent;pointer-events:none;transition:background .22s}
.notif-backdrop.on{pointer-events:all;background:rgba(6,26,15,.45);backdrop-filter:blur(3px)}
.notif-panel{position:fixed;top:0;right:0;width:360px;max-width:96vw;height:100vh;z-index:150;background:var(--white);border-left:1.5px solid var(--border);box-shadow:-8px 0 40px rgba(6,26,15,.12);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .28s cubic-bezier(.4,0,.2,1);}
.notif-panel.open{transform:translateX(0)}
.np-head{padding:18px 16px 14px;border-bottom:1.5px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;flex-shrink:0;background:var(--g050);}
.np-title{font-size:.92rem;font-weight:800;color:var(--text)}
.np-sub{font-size:.65rem;color:var(--muted);margin-top:2px}
.np-mark-btn{font-size:.65rem;font-weight:700;color:var(--g600);background:var(--white);border:1.5px solid var(--g300);padding:3px 9px;border-radius:6px;text-decoration:none;transition:all .14s;cursor:pointer;}
.np-mark-btn:hover{background:var(--accent);color:var(--white);border-color:var(--accent)}
.np-close{width:26px;height:26px;border-radius:6px;background:var(--white);border:1.5px solid var(--border);color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.7rem;flex-shrink:0;}
.np-close:hover{background:var(--g200)}
.np-list{flex:1;overflow-y:auto;padding:4px 0}
.np-item{display:flex;align-items:flex-start;gap:11px;padding:13px 15px;cursor:pointer;transition:background .14s;border-bottom:1px solid var(--border);}
.np-item:last-child{border-bottom:none}
.np-item:hover{background:var(--g050)}
.np-item.np-unread{background:rgba(24,207,180,.04);border-left:3px solid var(--accent)}
.np-ico{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:var(--g100);border:1.5px solid var(--g300);}
.np-msg{font-size:.74rem;color:var(--text);line-height:1.5;margin-bottom:2px;font-weight:500}
.np-time{font-size:.61rem;color:var(--muted)}
.np-dot{width:7px;height:7px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:5px}

/* ANIMATIONS */
@keyframes bell-shake{0%,100%{transform:rotate(0)}15%{transform:rotate(10deg)}30%{transform:rotate(-10deg)}45%{transform:rotate(6deg)}60%{transform:rotate(-6deg)}75%{transform:rotate(3deg)}}
.bell-ring{animation:bell-shake .65s ease-in-out}
@keyframes fade-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.oc{animation:fade-in .3s ease both}
<?php foreach($officers as $i=>$_): ?>.oc:nth-child(<?= $i+1 ?>){animation-delay:<?= $i*.04 ?>s}<?php endforeach; ?>

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
  .tb-badge{font-size:.65rem;padding:5px 10px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
}
@media(max-width:600px){
  .body{padding:14px 12px}
  .topbar{padding:0 12px;gap:8px}
  .tb-greeting{font-size:.76rem}
  .tb-date{display:none}
  .tb-sep{display:none}
  .tb-badge{display:none}
  .welcome-banner{flex-direction:column;gap:14px;padding:18px 16px}
  .wb-stat{align-items:flex-start}
  .wb-big-num{font-size:2.2rem}
  .stat-row{grid-template-columns:1fr 1fr;gap:8px}
  .sc{padding:12px 10px;gap:10px}
  .sc-ico{width:36px;height:36px;font-size:1rem}
  .sc-num{font-size:1.5rem}
  .oc{flex-wrap:wrap;gap:10px;padding:14px}
  .oc-av{width:40px;height:40px;font-size:.85rem}
  .oc-actions{flex-wrap:wrap;gap:6px;border-top:1px solid var(--border);padding-top:10px;margin-top:2px;width:100%}
  .oc-act-btn{flex:1;min-width:calc(50% - 3px);font-size:.65rem;padding:7px 6px}
  .oc-stats{flex-wrap:wrap;gap:4px}
  .qa-grid{grid-template-columns:1fr 1fr}
  .ec-item{flex-wrap:wrap;gap:8px}
  .ec-actions{width:100%;justify-content:flex-end}
}
@media(max-width:400px){
  .stat-row{grid-template-columns:1fr 1fr}
  .oc-actions{flex-direction:column}
  .oc-act-btn{min-width:100%}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-mark">🏛️</div>
    <div><div class="sb-name">Nagrik Seva</div><div class="sb-sub">Regulator Portal</div></div>
  </div>
  <div class="sb-divider"></div>
  <div class="sb-sec">Oversight</div>
  <a class="nav-a on" href="regulator_dashboard.php"><span class="nav-ico">⊞</span> Dashboard</a>
  <a class="nav-a" href="regulator_officers.php"><span class="nav-ico">👮</span> All Officers <?php $inactive_ct = count(array_filter($officers, fn($o) => !$o['is_active'])); if($inactive_ct > 0): ?><span class="nav-badge nav-badge-red"><?= $inactive_ct ?></span><?php endif; ?></a>
  <a class="nav-a" href="regulator_complaints.php"><span class="nav-ico">📋</span> All Complaints <?php if($platform['escalated']>0): ?><span class="nav-badge nav-badge-red"><?= $platform['escalated'] ?></span><?php endif; ?></a>
  <a class="nav-a" href="regulator_reports.php"><span class="nav-ico">📊</span> Reports</a>
  <a class="nav-a" href="track.php"><span class="nav-ico">⊙</span> Track Complaint</a>
  <a class="nav-a" href="public_board.php"><span class="nav-ico">◎</span> Public Board</a>
  <div class="sb-sec">Account</div>
  <a class="nav-a" href="regulator_profile.php"><span class="nav-ico">○</span> My Profile</a>
  <div class="sb-sec">Info</div>
  <a class="nav-a" href="about.php"><span class="nav-ico">ℹ</span> About</a>
  <a class="nav-a" href="contact.php"><span class="nav-ico">✉</span> Contact</a>
  <div class="sb-foot">
    <div class="u-card">
      <div class="u-av"><?= $initials ?></div>
      <div><div class="u-name"><?= htmlspecialchars($name) ?></div><div class="u-role">Regulator</div></div>
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
      <div class="tb-greeting"><?= $greeting ?>, <?= htmlspecialchars($first) ?> 👋</div>
      <div class="tb-sep"></div>
      <div class="tb-date"><?= date('D, d M Y') ?></div>
    </div>
    <div class="tb-center">
      <div class="tb-brand">🏛️ Nagrik Seva Portal</div>
      <div class="tb-tagline">Regulator Oversight Centre</div>
    </div>
    <div class="tb-right">
      <button class="tb-notif" id="bell-btn" onclick="toggleNotifPanel()" type="button">
        🔔<?php if($unread>0): ?><span class="tb-ndot" id="bell-dot"></span><?php endif; ?>
      </button>
      <div class="tb-badge">⚖️ <?= htmlspecialchars($dept) ?></div>
    </div>
  </div>

  <div class="body">
    <?php if($toast_ok): ?><div class="toast t-ok">✓ &nbsp;<?= htmlspecialchars($toast_ok) ?></div><?php endif; ?>
    <?php if($toast_err): ?><div class="toast t-err">⚠ &nbsp;<?= htmlspecialchars($toast_err) ?></div><?php endif; ?>

    <!-- WELCOME BANNER -->
    <div class="welcome-banner">
      <div>
        <div class="wb-greeting"><?= $greeting ?>, <?= htmlspecialchars($first) ?> ⚖️</div>
        <div class="wb-sub">Platform oversight: <strong style="color:var(--g200)"><?= $platform['pending'] ?> complaints pending</strong><?php if($platform['escalated']>0): ?>, <strong style="color:#ffb3b3"><?= $platform['escalated'] ?> escalated</strong><?php endif; ?> across <?= $platform['active_officers'] ?> active officers.</div>
      </div>
      <div class="wb-stat">
        <div class="wb-big-num"><?= $platform['total_complaints'] ?></div>
        <div class="wb-big-lbl">Total Complaints</div>
        <div class="wb-prog-row">
          <div class="wb-prog-bar"><div class="wb-prog-fill" style="width:<?= $overall_rate ?>%"></div></div>
          <div class="wb-prog-pct"><?= $overall_rate ?>% resolved</div>
        </div>
      </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-row">
      <div class="sc sc-accent"><div class="sc-ico sc-ico-a">📋</div><div><div class="sc-num"><?= $platform['total_complaints'] ?></div><div class="sc-lbl">Total</div></div></div>
      <div class="sc"><div class="sc-ico sc-ico-b">✅</div><div><div class="sc-num"><?= $platform['resolved'] ?></div><div class="sc-lbl">Resolved</div></div></div>
      <div class="sc"><div class="sc-ico sc-ico-c">⏳</div><div><div class="sc-num"><?= $platform['pending'] ?></div><div class="sc-lbl">Pending</div></div></div>
      <div class="sc"><div class="sc-ico sc-ico-d">🚨</div><div><div class="sc-num"><?= $platform['escalated'] ?></div><div class="sc-lbl">Escalated</div></div></div>
      <div class="sc"><div class="sc-ico sc-ico-e">👮</div><div><div class="sc-num"><?= $platform['active_officers'] ?></div><div class="sc-lbl">Active Off.</div></div></div>
      <div class="sc"><div class="sc-ico sc-ico-f">🚫</div><div><div class="sc-num"><?= $platform['inactive_officers'] ?></div><div class="sc-lbl">Inactive</div></div></div>
    </div>

    <div class="dash-grid">

      <!-- LEFT: OFFICERS LIST -->
      <div style="display:flex;flex-direction:column;gap:18px">

        <!-- OFFICER MONITORING -->
        <div class="card">
          <div class="ch">
            <div><div class="ch-title">Officer Monitoring</div><div class="ch-sub"><?= count($officers) ?> officers · click actions to manage</div></div>
            <a href="regulator_officers.php" class="ch-act">View All →</a>
          </div>
          <div class="officer-grid">
            <?php foreach($officers as $o):
              $os     = officer_status($o);
              $rate   = $o['total_assigned'] > 0 ? round(($o['resolved_count'] / $o['total_assigned']) * 100) : 0;
              $days   = days_since($o['last_activity'] ?? null);
              $prog_color = $rate >= 70 ? 'var(--g500)' : ($rate >= 40 ? '#f59e0b' : '#dc2626');
              $oc_init = strtoupper(substr($o['name'], 0, 1));
            ?>
            <div class="oc" id="oc-<?= $o['id'] ?>">
              <div class="oc-av <?= !$o['is_active'] ? 'inactive' : '' ?>"><?= $oc_init ?></div>
              <div class="oc-body">
                <div class="oc-name"><?= htmlspecialchars($o['name']) ?></div>
                <div class="oc-meta">
                  <span class="oc-dept">🏢 <?= htmlspecialchars($o['department'] ?? '—') ?></span>
                  <span class="oc-zone">📍 <?= htmlspecialchars($o['zone'] ?? '—') ?></span>
                </div>
                <div class="oc-stats">
                  <span class="oc-stat ocs-total">📋 <?= (int)$o['total_assigned'] ?> total</span>
                  <span class="oc-stat ocs-resolve">✅ <?= (int)$o['resolved_count'] ?> resolved</span>
                  <?php if($o['pending_count'] > 0): ?><span class="oc-stat ocs-pending">⏳ <?= (int)$o['pending_count'] ?> pending</span><?php endif; ?>
                  <?php if($o['escalated_count'] > 0): ?><span class="oc-stat ocs-esc">🚨 <?= (int)$o['escalated_count'] ?> escalated</span><?php endif; ?>
                </div>
                <div class="oc-prog-row">
                  <div class="oc-prog-track"><div class="oc-prog-fill" style="width:<?= $rate ?>%;background:<?= $prog_color ?>"></div></div>
                  <span class="oc-prog-pct"><?= $rate ?>%</span>
                </div>
                <div class="oc-last">
                  <?php if($o['is_active']): ?>
                  Last active: <?= $days === 0 ? 'Today' : ($days === 1 ? 'Yesterday' : "$days days ago") ?>
                  <?php if($days > 3): ?> <span style="color:#a02020;font-weight:700">⚠ Inactive</span><?php endif; ?>
                  <?php else: ?><span style="color:#a02020;font-weight:700">🚫 Account Terminated</span><?php endif; ?>
                </div>
                <div class="oc-actions">
                  <?php if($o['is_active']): ?>
                  <button class="oa-btn oa-notif" onclick="openNotifModal(<?= $o['id'] ?>,'<?= addslashes(htmlspecialchars($o['name'])) ?>','<?= htmlspecialchars($o['department']??'') ?>')">🔔 Notify</button>
                  <button class="oa-btn oa-legal" onclick="openLegalModal(<?= $o['id'] ?>,'<?= addslashes(htmlspecialchars($o['name'])) ?>','<?= htmlspecialchars($o['department']??'') ?>')">⚖️ Legal Notice</button>
                  <button class="oa-btn oa-term"  onclick="openTermModal(<?= $o['id'] ?>,'<?= addslashes(htmlspecialchars($o['name'])) ?>','<?= htmlspecialchars($o['department']??'') ?>')">🚫 Terminate</button>
                  <?php else: ?>
                  <button class="oa-btn oa-reinstate" onclick="openReinstateModal(<?= $o['id'] ?>,'<?= addslashes(htmlspecialchars($o['name'])) ?>')">✅ Reinstate</button>
                  <?php endif; ?>
                </div>
              </div>
              <div class="oc-right">
                <span class="os-badge <?= $os['cls'] ?>"><?= $os['label'] ?></span>
                <div class="notice-badges">
                  <?php if($o['notice_count'] > 0): ?><span class="nb nb-notice">📢 <?= $o['notice_count'] ?> notice<?= $o['notice_count']>1?'s':'' ?></span><?php endif; ?>
                  <?php if($o['legal_count'] > 0): ?><span class="nb nb-legal">⚖️ <?= $o['legal_count'] ?> legal</span><?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- ESCALATED COMPLAINTS -->
        <div class="card">
          <div class="ch">
            <div><div class="ch-title">🚨 Escalated Complaints</div><div class="ch-sub"><?= count($escalated) ?> needing urgent review</div></div>
          </div>
          <?php if(empty($escalated)): ?>
          <div class="empty"><span class="empty-ico">🎉</span><div style="font-size:.82rem;font-weight:700;color:var(--text)">No escalated complaints</div></div>
          <?php else: ?>
          <?php foreach($escalated as $e):
            $eico = $cat_icon[$e['category']] ?? '📋';
            $ppri = in_array($e['priority']??'',['high','urgent']) ? 'p-high' : 'p-med';
          ?>
          <div class="esc-row">
            <div class="esc-ico"><?= $eico ?></div>
            <div class="esc-body">
              <div class="esc-id"><?= htmlspecialchars($e['complaint_no']) ?></div>
              <div class="esc-title"><?= htmlspecialchars($e['title']) ?></div>
              <div class="esc-meta">
                📍 <?= htmlspecialchars($e['location']) ?>
                <?php if(!empty($e['officer_name'])): ?> · 👮 <?= htmlspecialchars($e['officer_name']) ?><?php else: ?> · <span style="color:#b07b00;font-weight:600">Unassigned</span><?php endif; ?>
              </div>
            </div>
            <div class="esc-right">
              <span class="<?= $ppri ?>"><?= ucfirst($e['priority']??'medium') ?></span>
              <form method="POST" onsubmit="return confirm('Escalate this complaint to the officer?')">
                <input type="hidden" name="action" value="escalate_complaint">
                <input type="hidden" name="complaint_id" value="<?= $e['id'] ?>">
                <button type="submit" class="esc-btn">🚨 Escalate</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div style="display:flex;flex-direction:column;gap:14px">

        <!-- QUICK ACTIONS -->
        <div class="quick-card">
          <div class="ch"><div class="ch-title">Quick Actions</div></div>
          <div class="qa-grid">
            <a class="qa" href="regulator_officers.php"><span class="qa-ico">👮</span><span class="qa-lbl">All Officers</span><span class="qa-desc">Manage team</span></a>
            <a class="qa" href="regulator_complaints.php"><span class="qa-ico">📋</span><span class="qa-lbl">Complaints</span><span class="qa-desc">Full list</span></a>
            <a class="qa" href="regulator_reports.php"><span class="qa-ico">📊</span><span class="qa-lbl">Reports</span><span class="qa-desc">Analytics</span></a>
            <a class="qa" href="public_board.php"><span class="qa-ico">🌐</span><span class="qa-lbl">Public Board</span><span class="qa-desc">All issues</span></a>
          </div>
        </div>

        <!-- NOTIFICATIONS -->
        <div class="quick-card">
          <div class="ch">
            <div><div class="ch-title">Notifications</div><?php if($unread>0): ?><div class="ch-sub"><?= $unread ?> unread</div><?php endif; ?></div>
            <?php if($unread>0): ?><a href="regulator_dashboard.php?read_notifs=1" class="ch-act">Mark read</a><?php endif; ?>
          </div>
          <?php if(empty($notifs)): ?>
          <div class="empty" style="padding:20px"><span class="empty-ico" style="font-size:1.5rem">🔔</span><div style="font-size:.8rem;font-weight:700;color:var(--text)">All caught up</div></div>
          <?php else: ?>
          <div class="notif-list">
            <?php foreach(array_slice($notifs,0,5) as $n):
              $ts   = strtotime($n['created_at']); $diff = time()-$ts;
              $tago = $diff<60?'Just now':($diff<3600?floor($diff/60).'m ago':($diff<86400?floor($diff/3600).'h ago':date('d M',$ts)));
            ?>
            <div class="ni <?= $n['is_read']?'':'unread' ?>">
              <div class="ni-dot <?= $n['is_read']?'read':'' ?>"></div>
              <div><div class="ni-msg"><?= htmlspecialchars($n['message']) ?></div><div class="ni-time"><?= $tago ?></div></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- PLATFORM HEALTH -->
        <div class="quick-card" style="border-top:3px solid var(--g500)">
          <div class="ch"><div class="ch-title">📊 Platform Health</div></div>
          <div style="padding:14px">
            <?php
            $health = [
                ['label'=>'Resolution Rate', 'val'=>$overall_rate, 'color'=>$overall_rate>=70?'var(--g500)':($overall_rate>=50?'#f59e0b':'#dc2626')],
                ['label'=>'Officer Activity', 'val'=>$platform['total_officers']>0?round(($platform['active_officers']/$platform['total_officers'])*100):0, 'color'=>'var(--g500)'],
                ['label'=>'Escalation Rate', 'val'=>$platform['total_complaints']>0?round(($platform['escalated']/$platform['total_complaints'])*100):0, 'color'=>'#dc2626'],
            ];
            foreach($health as $h): ?>
            <div style="margin-bottom:12px">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:.66rem;font-weight:600;color:var(--muted)"><?= $h['label'] ?></span>
                <span style="font-size:.68rem;font-weight:700;color:var(--text);font-family:'DM Mono',monospace"><?= $h['val'] ?>%</span>
              </div>
              <div style="height:5px;background:var(--g100);border-radius:3px;overflow:hidden;border:1px solid var(--border)">
                <div style="height:100%;width:<?= $h['val'] ?>%;background:<?= $h['color'] ?>;border-radius:3px;transition:width .8s"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- HELPLINE -->
        <div class="quick-card">
          <div class="helpline">
            <div class="hl-label">Regulator Support</div>
            <div class="hl-row"><div class="hl-ico">📞</div><div><div class="hl-text">1800-233-0002</div><div class="hl-sub">Legal & Compliance · 24/7</div></div></div>
            <div class="hl-row"><div class="hl-ico">✉️</div><div><div class="hl-text">regulator@nagrikseva.gov.in</div><div class="hl-sub">Official correspondence</div></div></div>
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
    <div style="display:flex;align-items:center;gap:7px">
      <?php if($unread>0): ?><a href="regulator_dashboard.php?read_notifs=1" class="np-mark-btn">Mark all read</a><?php endif; ?>
      <button class="np-close" onclick="closeNotifPanel()">✕</button>
    </div>
  </div>
  <div class="np-list">
    <?php foreach($notifs as $n):
      $ts=$n['created_at']?strtotime($n['created_at']):time();$diff=time()-$ts;
      $tago=$diff<60?'Just now':($diff<3600?floor($diff/60).'m ago':($diff<86400?floor($diff/3600).'h ago':($diff<604800?floor($diff/86400).'d ago':date('d M',$ts))));
    ?>
    <div class="np-item <?= $n['is_read']?'':'np-unread' ?>">
      <div class="np-ico">🔔</div>
      <div style="flex:1;min-width:0"><div class="np-msg"><?= htmlspecialchars($n['message']) ?></div><div class="np-time"><?= $tago ?></div></div>
      <?php if(!$n['is_read']): ?><div class="np-dot"></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- SEND NOTIFICATION MODAL -->
<div class="overlay" id="notif-overlay" onclick="if(event.target===this)closeNotifModal()">
  <div class="modal">
    <div class="mh">
      <div><div class="mh-title">🔔 Send Performance Notification</div><div class="mh-sub" id="nm-sub">Notify officer about pending complaints</div></div>
      <button class="mh-close" onclick="closeNotifModal()">✕</button>
    </div>
    <div class="modal-officer-info" id="nm-officer-info">
      <div class="modal-av" id="nm-av">S</div>
      <div><div class="modal-name" id="nm-name">Officer</div><div class="modal-dept" id="nm-dept">Department</div></div>
    </div>
    <div class="quick-msgs">
      <button class="qm" onclick="setMsg('notif','You have unresolved complaints pending for more than 5 days. Immediate action required.')">⏰ Overdue reminder</button>
      <button class="qm" onclick="setMsg('notif','You have an escalated complaint requiring urgent attention. Please update the status within 24 hours.')">🚨 Escalation alert</button>
      <button class="qm" onclick="setMsg('notif','Your resolution rate is below the required threshold. Please close pending complaints by end of this week.')">📉 Low performance</button>
      <button class="qm" onclick="setMsg('notif','Please provide an update on all complaints assigned to you that have been in progress for over 7 days.')">📝 Status update request</button>
    </div>
    <form method="POST" id="notif-form">
      <input type="hidden" name="action" value="send_notification">
      <input type="hidden" name="officer_id" id="nm-officer-id">
      <div class="fg"><label class="fl">Message to Officer</label><textarea class="fi" name="message" id="nm-msg" placeholder="Write your notification message…" required></textarea></div>
      <button type="submit" class="btn-submit">🔔 Send Notification</button>
    </form>
  </div>
</div>

<!-- LEGAL NOTICE MODAL -->
<div class="overlay" id="legal-overlay" onclick="if(event.target===this)closeLegalModal()">
  <div class="modal">
    <div class="mh">
      <div><div class="mh-title">⚖️ Issue Legal Notice</div><div class="mh-sub" id="lm-sub">Formal legal warning for non-compliance</div></div>
      <button class="mh-close" onclick="closeLegalModal()">✕</button>
    </div>
    <div class="modal-officer-info" id="lm-officer-info">
      <div class="modal-av" id="lm-av">S</div>
      <div><div class="modal-name" id="lm-name">Officer</div><div class="modal-dept" id="lm-dept">Department</div></div>
    </div>
    <div class="warn-box">⚠ A legal notice is a formal document logged in the system. The officer will be notified and a permanent record will be created. Multiple legal notices may result in account termination.</div>
    <div class="quick-msgs">
      <button class="qm" onclick="setMsg('legal','You are hereby formally notified that your failure to address assigned complaints constitutes dereliction of duty under the Goa Grievance Redressal Act, 2024.')">📜 Dereliction of duty</button>
      <button class="qm" onclick="setMsg('legal','This legal notice is issued for your continued failure to respond to escalated complaints despite prior notifications. Further non-compliance will result in account termination.')">🔴 Final warning</button>
    </div>
    <form method="POST" id="legal-form">
      <input type="hidden" name="action" value="send_legal">
      <input type="hidden" name="officer_id" id="lm-officer-id">
      <div class="fg"><label class="fl">Legal Notice Content</label><textarea class="fi" name="message" id="lm-msg" placeholder="Write the formal legal notice…" required></textarea></div>
      <button type="submit" class="btn-submit btn-legal">⚖️ Issue Legal Notice</button>
    </form>
  </div>
</div>

<!-- TERMINATE MODAL -->
<div class="overlay" id="term-overlay" onclick="if(event.target===this)closeTermModal()">
  <div class="modal">
    <div class="mh">
      <div><div class="mh-title">🚫 Terminate Account</div><div class="mh-sub" id="tm-sub">This action will deactivate the officer account</div></div>
      <button class="mh-close" onclick="closeTermModal()">✕</button>
    </div>
    <div class="modal-officer-info" id="tm-officer-info">
      <div class="modal-av inactive" id="tm-av">S</div>
      <div><div class="modal-name" id="tm-name">Officer</div><div class="modal-dept" id="tm-dept">Department</div></div>
    </div>
    <div class="danger-box">🚨 <strong>This is irreversible without manual reinstatement.</strong> The officer will be logged out immediately and unable to access the system. All their assigned complaints will remain visible but they will be marked as unassigned.</div>
    <form method="POST" id="term-form" onsubmit="return confirm('Are you absolutely sure you want to terminate this officer account? This will remove their system access immediately.')">
      <input type="hidden" name="action" value="terminate">
      <input type="hidden" name="officer_id" id="tm-officer-id">
      <div class="fg"><label class="fl">Reason for Termination</label><textarea class="fi" name="reason" id="tm-reason" placeholder="State the official reason for termination…" required></textarea></div>
      <button type="submit" class="btn-submit btn-term">🚫 Terminate Account</button>
    </form>
  </div>
</div>

<!-- REINSTATE MODAL -->
<div class="overlay" id="reinstate-overlay" onclick="if(event.target===this)closeReinstateModal()">
  <div class="modal" style="max-width:400px">
    <div class="mh">
      <div><div class="mh-title">✅ Reinstate Officer</div><div class="mh-sub">Restore system access to this officer</div></div>
      <button class="mh-close" onclick="closeReinstateModal()">✕</button>
    </div>
    <div class="modal-officer-info" id="ri-officer-info">
      <div class="modal-av" id="ri-av">S</div>
      <div><div class="modal-name" id="ri-name">Officer</div></div>
    </div>
    <div style="background:var(--g100);border:1.5px solid var(--g300);border-radius:9px;padding:11px 14px;font-size:.75rem;color:var(--g700);line-height:1.65;margin-bottom:14px">✅ Reinstating will restore the officer's account access. They will be able to log in and manage complaints again.</div>
    <form method="POST" id="reinstate-form">
      <input type="hidden" name="action" value="reinstate">
      <input type="hidden" name="officer_id" id="ri-officer-id">
      <button type="submit" class="btn-submit">✅ Reinstate Account</button>
    </form>
  </div>
</div>

<script>
// Mobile sidebar
function toggleMobSidebar(){const s=document.querySelector('.sidebar'),b=document.getElementById('mob-backdrop');s.classList.toggle('mob-open');b.classList.toggle('on');document.body.style.overflow=s.classList.contains('mob-open')?'hidden':'';}
function closeMobSidebar(){const s=document.querySelector('.sidebar'),b=document.getElementById('mob-backdrop');s.classList.remove('mob-open');b.classList.remove('on');document.body.style.overflow='';}

// Notif panel
const nPanel=document.getElementById('notif-panel'),nBackdrop=document.getElementById('notif-backdrop');
function toggleNotifPanel(){nPanel.classList.contains('open')?closeNotifPanel():openNotifPanel();}
function openNotifPanel(){nPanel.classList.add('open');nBackdrop.classList.add('on');const b=document.getElementById('bell-btn');b.classList.add('bell-ring');setTimeout(()=>b.classList.remove('bell-ring'),700);}
function closeNotifPanel(){nPanel.classList.remove('open');nBackdrop.classList.remove('on');}

function setOfficerInfo(prefix,id,name,dept){
  document.getElementById(prefix+'-officer-id').value=id;
  document.getElementById(prefix+'-av').textContent=name.charAt(0).toUpperCase();
  document.getElementById(prefix+'-name').textContent=name;
  const deptEl=document.getElementById(prefix+'-dept');
  if(deptEl)deptEl.textContent=dept;
}

// Notification modal
function openNotifModal(id,name,dept){setOfficerInfo('nm',id,name,dept);document.getElementById('nm-sub').textContent='Notifying: '+name;document.getElementById('nm-msg').value='';document.getElementById('notif-overlay').classList.add('on');document.body.style.overflow='hidden';}
function closeNotifModal(){document.getElementById('notif-overlay').classList.remove('on');document.body.style.overflow='';}

// Legal modal
function openLegalModal(id,name,dept){setOfficerInfo('lm',id,name,dept);document.getElementById('lm-sub').textContent='Issuing to: '+name;document.getElementById('lm-msg').value='';document.getElementById('legal-overlay').classList.add('on');document.body.style.overflow='hidden';}
function closeLegalModal(){document.getElementById('legal-overlay').classList.remove('on');document.body.style.overflow='';}

// Terminate modal
function openTermModal(id,name,dept){
  document.getElementById('tm-officer-id').value=id;
  document.getElementById('tm-av').textContent=name.charAt(0).toUpperCase();
  document.getElementById('tm-name').textContent=name;
  document.getElementById('tm-dept').textContent=dept;
  document.getElementById('tm-reason').value='';
  document.getElementById('term-overlay').classList.add('on');document.body.style.overflow='hidden';
}
function closeTermModal(){document.getElementById('term-overlay').classList.remove('on');document.body.style.overflow='';}

// Reinstate modal
function openReinstateModal(id,name){
  document.getElementById('ri-officer-id').value=id;
  document.getElementById('ri-av').textContent=name.charAt(0).toUpperCase();
  document.getElementById('ri-name').textContent=name;
  document.getElementById('reinstate-overlay').classList.add('on');document.body.style.overflow='hidden';
}
function closeReinstateModal(){document.getElementById('reinstate-overlay').classList.remove('on');document.body.style.overflow='';}

// Set quick message
function setMsg(type,msg){
  const id=type==='notif'?'nm-msg':'lm-msg';
  document.getElementById(id).value=msg;
  document.getElementById(id).focus();
}

document.addEventListener('keydown',e=>{
  if(e.key!=='Escape')return;
  if(document.getElementById('notif-overlay').classList.contains('on'))closeNotifModal();
  else if(document.getElementById('legal-overlay').classList.contains('on'))closeLegalModal();
  else if(document.getElementById('term-overlay').classList.contains('on'))closeTermModal();
  else if(document.getElementById('reinstate-overlay').classList.contains('on'))closeReinstateModal();
  else if(nPanel.classList.contains('open'))closeNotifPanel();
  else closeMobSidebar();
});

setTimeout(()=>{const t=document.querySelector('.toast');if(t){t.style.transition='opacity .5s';t.style.opacity='0';setTimeout(()=>t.remove(),500);}},4000);
</script>
</body>
</html>