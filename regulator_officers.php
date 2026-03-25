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

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $off_id = (int)($_POST['officer_id'] ?? 0);
    $msg    = trim($_POST['message'] ?? '');

    if ($is_demo_session) {
        if ($action === 'send_notification') $toast_ok = 'Demo: Performance notification sent.';
        elseif ($action === 'send_legal')    $toast_ok = 'Demo: Legal notice issued.';
        elseif ($action === 'terminate')     $toast_ok = 'Demo: Officer account terminated.';
        elseif ($action === 'reinstate')     $toast_ok = 'Demo: Officer account reinstated.';
        if ($toast_ok) { header('Location: regulator_officers.php?msg='.urlencode($toast_ok).'&type=ok'); exit; }
    } elseif ($uid > 0) {
        if ($action === 'send_notification' && $off_id && $msg) {
            $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message,created_at) VALUES($off_id,NULL,'regulator_notice','".addslashes($msg)."',NOW())");
            $conn->query("UPDATE users SET last_notice=NOW(), notice_count=IFNULL(notice_count,0)+1 WHERE id=$off_id AND role='officer'");
            $toast_ok = 'Performance notification sent.';
        } elseif ($action === 'send_legal' && $off_id && $msg) {
            $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message,created_at) VALUES($off_id,NULL,'legal_notice','⚖️ LEGAL NOTICE: ".addslashes($msg)."',NOW())");
            $conn->query("UPDATE users SET last_legal=NOW(), legal_count=IFNULL(legal_count,0)+1 WHERE id=$off_id AND role='officer'");
            $toast_ok = 'Legal notice issued.';
        } elseif ($action === 'terminate' && $off_id) {
            $reason = trim($_POST['reason'] ?? 'Terminated by regulator.');
            $conn->query("UPDATE users SET is_active=0, terminated_at=NOW(), termination_reason='".addslashes($reason)."' WHERE id=$off_id AND role='officer'");
            $toast_ok = 'Officer account terminated.';
        } elseif ($action === 'reinstate' && $off_id) {
            $conn->query("UPDATE users SET is_active=1, terminated_at=NULL, termination_reason=NULL WHERE id=$off_id AND role='officer'");
            $toast_ok = 'Officer account reinstated.';
        }
        if ($toast_ok || $toast_err) { header('Location: regulator_officers.php?msg='.urlencode($toast_ok ?: $toast_err).'&type='.($toast_ok?'ok':'err')); exit; }
    }
}
if (isset($_GET['msg'])) {
    if (($_GET['type'] ?? '') === 'ok') $toast_ok = $_GET['msg'];
    else $toast_err = $_GET['msg'];
}

// Filters
$filter_status = $_GET['status'] ?? 'all';
$filter_dept   = $_GET['dept']   ?? 'all';
$search        = trim($_GET['q'] ?? '');

// Load officers
$officers = [];
if ($uid > 0) {
    $where = "u.role='officer'";
    if ($filter_status === 'active')   $where .= " AND u.is_active=1";
    if ($filter_status === 'inactive') $where .= " AND u.is_active=0";
    if ($filter_dept !== 'all')        $where .= " AND u.department='".addslashes($filter_dept)."'";
    if ($search)                       $where .= " AND (u.name LIKE '%".addslashes($search)."%' OR u.email LIKE '%".addslashes($search)."%' OR u.zone LIKE '%".addslashes($search)."%')";

    $r = $conn->query("SELECT u.*,
        COUNT(c.id) as total_assigned,
        SUM(c.status IN ('resolved','closed')) as resolved_count,
        SUM(c.status IN ('new','assigned','in_progress')) as pending_count,
        SUM(c.status='escalated') as escalated_count,
        MAX(c.updated_at) as last_activity,
        IFNULL(u.notice_count,0) as notice_count,
        IFNULL(u.legal_count,0) as legal_count
        FROM users u
        LEFT JOIN complaints c ON c.officer_id=u.id
        WHERE $where
        GROUP BY u.id ORDER BY pending_count DESC, u.name ASC");
    if ($r) while ($row=$r->fetch_assoc()) $officers[] = $row;
}

// Dummy fallback
if (empty($officers)) {
    $officers = [
        ['id'=>1,'name'=>'Suresh Kamat',    'email'=>'s.kamat@nagrikseva.gov',    'zone'=>'Panaji',   'department'=>'Road & PWD',   'phone'=>'9823001001','is_active'=>1,'total_assigned'=>12,'resolved_count'=>9,'pending_count'=>3,'escalated_count'=>0,'last_activity'=>date('Y-m-d H:i:s',strtotime('-2 hours')), 'notice_count'=>0,'legal_count'=>0,'created_at'=>date('Y-m-d',strtotime('-180 days'))],
        ['id'=>2,'name'=>'Priya Dessai',    'email'=>'p.dessai@nagrikseva.gov',   'zone'=>'Margao',   'department'=>'Water Supply', 'phone'=>'9823001002','is_active'=>1,'total_assigned'=>8, 'resolved_count'=>5,'pending_count'=>2,'escalated_count'=>1,'last_activity'=>date('Y-m-d H:i:s',strtotime('-1 day')),  'notice_count'=>1,'legal_count'=>0,'created_at'=>date('Y-m-d',strtotime('-150 days'))],
        ['id'=>3,'name'=>'Anton Fernandes', 'email'=>'a.fernandes@nagrikseva.gov','zone'=>'Vasco',    'department'=>'Electricity',  'phone'=>'9823001003','is_active'=>1,'total_assigned'=>10,'resolved_count'=>7,'pending_count'=>3,'escalated_count'=>0,'last_activity'=>date('Y-m-d H:i:s',strtotime('-3 hours')),'notice_count'=>0,'legal_count'=>0,'created_at'=>date('Y-m-d',strtotime('-200 days'))],
        ['id'=>4,'name'=>'Raj Naik',        'email'=>'r.naik@nagrikseva.gov',     'zone'=>'Mapusa',   'department'=>'Road & PWD',   'phone'=>'9823001004','is_active'=>1,'total_assigned'=>6, 'resolved_count'=>1,'pending_count'=>5,'escalated_count'=>2,'last_activity'=>date('Y-m-d H:i:s',strtotime('-8 days')), 'notice_count'=>2,'legal_count'=>1,'created_at'=>date('Y-m-d',strtotime('-90 days'))],
        ['id'=>5,'name'=>'Sunita Borkar',   'email'=>'s.borkar@nagrikseva.gov',   'zone'=>'Ponda',    'department'=>'Sanitation',   'phone'=>'9823001005','is_active'=>1,'total_assigned'=>9, 'resolved_count'=>7,'pending_count'=>2,'escalated_count'=>0,'last_activity'=>date('Y-m-d H:i:s',strtotime('-5 hours')),'notice_count'=>0,'legal_count'=>0,'created_at'=>date('Y-m-d',strtotime('-220 days'))],
        ['id'=>6,'name'=>'David Gomes',     'email'=>'d.gomes@nagrikseva.gov',    'zone'=>'Calangute','department'=>'Property',     'phone'=>'9823001006','is_active'=>0,'total_assigned'=>2, 'resolved_count'=>0,'pending_count'=>2,'escalated_count'=>0,'last_activity'=>date('Y-m-d H:i:s',strtotime('-22 days')),'notice_count'=>3,'legal_count'=>2,'created_at'=>date('Y-m-d',strtotime('-60 days'))],
    ];
    // Apply dummy filters
    if ($filter_status === 'active')   $officers = array_filter($officers, fn($o) => $o['is_active']);
    if ($filter_status === 'inactive') $officers = array_filter($officers, fn($o) => !$o['is_active']);
    if ($filter_dept !== 'all')        $officers = array_filter($officers, fn($o) => $o['department'] === $filter_dept);
    if ($search) $officers = array_filter($officers, fn($o) => stripos($o['name'],$search)!==false || stripos($o['zone'],$search)!==false);
    $officers = array_values($officers);
}

$all_depts = ['Road & PWD','Water Supply','Electricity','Sanitation','Property','Health','Education'];

function days_since($dt) { if(!$dt) return 999; return floor((time()-strtotime($dt))/86400); }
function officer_status($o) {
    if(!$o['is_active']) return ['label'=>'Terminated','cls'=>'os-term'];
    $days=$o['total_assigned']>0?days_since($o['last_activity']??null):0;
    $rate=$o['total_assigned']>0?round(($o['resolved_count']/$o['total_assigned'])*100):0;
    if($days>7)  return ['label'=>'Inactive','cls'=>'os-inactive'];
    if($days>3)  return ['label'=>'Slow','cls'=>'os-slow'];
    if($rate<30) return ['label'=>'Low Perf.','cls'=>'os-low'];
    return ['label'=>'Active','cls'=>'os-active'];
}

$hour=$hour=(int)date('H');
$greeting=$hour<12?'Good morning':($hour<18?'Good afternoon':'Good evening');

$stats = [
    'total'    => count($officers),
    'active'   => count(array_filter($officers, fn($o)=>$o['is_active'])),
    'inactive' => count(array_filter($officers, fn($o)=>!$o['is_active'])),
    'flagged'  => count(array_filter($officers, fn($o)=>($o['notice_count']>0||$o['legal_count']>0))),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>All Officers — Nagrik Seva</title>
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
.nav-badge-red{background:#dc2626;color:#fff}
.sb-foot{margin-top:auto;padding:12px 10px;border-top:1px solid rgba(255,255,255,0.06)}
.u-card{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:11px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);}
.u-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));border:2px solid rgba(24,207,180,0.4);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--white);flex-shrink:0;}
.u-name{font-size:.77rem;font-weight:600;color:var(--white)}
.u-role{font-size:.58rem;color:var(--g300);margin-top:1px;letter-spacing:.5px;text-transform:uppercase}
.u-logout{margin-left:auto;background:none;border:none;color:var(--g300);cursor:pointer;padding:5px;border-radius:7px;transition:all .15s;}
.u-logout:hover{color:var(--white);background:rgba(255,255,255,0.1)}

/* TOPBAR */
.main{flex:1;height:100vh;overflow-y:auto;display:flex;flex-direction:column;}
.topbar{background:var(--g750);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;flex-shrink:0;border-bottom:1px solid rgba(24,207,180,0.25);}
.tb-left{display:flex;align-items:center;gap:14px;flex:1}
.tb-greeting{font-size:.88rem;font-weight:500;color:var(--white);}
.tb-sep{width:1px;height:16px;background:rgba(255,255,255,0.15)}
.tb-date{font-size:.71rem;color:var(--g300);}
.tb-center{position:absolute;left:50%;transform:translateX(-50%);text-align:center;pointer-events:none;white-space:nowrap;}
.tb-brand{font-size:1rem;font-weight:700;color:var(--white);letter-spacing:-.2px}
.tb-tagline{font-size:.57rem;color:var(--g300);letter-spacing:.6px;text-transform:uppercase;margin-top:2px}
.tb-right{display:flex;align-items:center;gap:10px;flex:1;justify-content:flex-end}
.tb-badge{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:rgba(24,207,180,.15);border:1px solid rgba(24,207,180,.3);font-size:.74rem;font-weight:600;color:var(--g200);}
.tb-back{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);font-size:.74rem;font-weight:600;color:var(--g200);transition:all .15s;}
.tb-back:hover{background:rgba(255,255,255,.14);color:var(--white)}

/* BODY */
.body{padding:22px 28px;flex:1;}
.toast{display:flex;align-items:center;gap:9px;padding:12px 16px;border-radius:10px;font-size:.8rem;margin-bottom:18px;border:1px solid transparent;font-weight:500;animation:slide-in-down .3s ease;}
@keyframes slide-in-down{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.t-ok{background:var(--g100);border-color:var(--g300);color:var(--g700)}
.t-err{background:#fff0f0;border-color:#f5b8b8;color:#a02020}

/* PAGE HEADER */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.ph-title{font-size:1.35rem;font-weight:800;color:var(--text);letter-spacing:-.4px;}
.ph-sub{font-size:.78rem;color:var(--muted);margin-top:3px;}

/* STAT ROW */
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.sc{border-radius:var(--radius);padding:14px 16px;display:flex;align-items:center;gap:11px;background:var(--card);border:1.5px solid var(--border);box-shadow:var(--shadow);transition:all .22s;}
.sc:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);}
.sc-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.sc-num{font-size:1.65rem;font-weight:700;letter-spacing:-.6px;line-height:1;color:var(--text)}
.sc-lbl{font-size:.59rem;text-transform:uppercase;letter-spacing:.6px;margin-top:2px;font-weight:500;color:var(--muted)}

/* FILTERS */
.filters{display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap;}
.filter-form{display:flex;align-items:center;gap:8px;flex:1;flex-wrap:wrap;}
.fi-search{flex:1;min-width:180px;padding:9px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;color:var(--text);background:var(--card);outline:none;transition:all .17s;}
.fi-search:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(24,207,180,.1)}
.fi-sel{padding:9px 13px;border:1.5px solid var(--border);border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;color:var(--text);background:var(--card);outline:none;cursor:pointer;transition:all .17s;}
.fi-sel:focus{border-color:var(--accent)}
.fi-btn{padding:9px 18px;border-radius:10px;background:linear-gradient(135deg,var(--g700),var(--g500));color:var(--white);border:none;font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .15s;}
.fi-btn:hover{transform:translateY(-1px)}
.fi-clear{padding:9px 14px;border-radius:10px;background:var(--card);border:1.5px solid var(--border);color:var(--muted);font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;cursor:pointer;text-decoration:none;}

/* OFFICERS TABLE */
.officers-table{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.ot-head{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 130px;gap:0;padding:10px 18px;background:linear-gradient(180deg,rgba(244,253,248,0.9),rgba(255,255,255,0));border-bottom:1.5px solid var(--border);}
.ot-th{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);}
.ot-row{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 130px;gap:0;padding:13px 18px;border-bottom:1px solid var(--border);align-items:center;transition:background .15s;}
.ot-row:last-child{border-bottom:none}
.ot-row:hover{background:var(--g050)}
.off-av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;color:var(--white);flex-shrink:0;border:2px solid rgba(24,207,180,.3);}
.off-av.inactive{background:linear-gradient(135deg,#aaa,#888);border-color:rgba(0,0,0,.1);}
.off-name{font-size:.84rem;font-weight:700;color:var(--text);}
.off-email{font-size:.63rem;color:var(--muted);margin-top:1px;}
.off-zone{display:inline-flex;align-items:center;gap:4px;font-size:.66rem;font-weight:600;color:var(--g600);}
.off-dept{font-size:.68rem;color:var(--muted2);}
.prog-bar{height:4px;background:var(--g100);border-radius:2px;overflow:hidden;border:1px solid var(--border);margin-top:4px;}
.prog-fill{height:100%;border-radius:2px;}
.rate-num{font-size:.75rem;font-weight:700;font-family:'DM Mono',monospace;}
.off-actions{display:flex;gap:5px;flex-wrap:wrap;}
.oa{padding:4px 10px;border-radius:6px;font-size:.63rem;font-weight:700;cursor:pointer;border:1.5px solid;font-family:'Plus Jakarta Sans',sans-serif;transition:all .14s;display:inline-flex;align-items:center;gap:3px;}
.oa:hover{transform:translateY(-1px)}
.oa-notif{color:var(--g600);border-color:var(--g300);background:var(--g050)}.oa-notif:hover{background:var(--g100)}
.oa-legal{color:#b07b00;border-color:#f5d98a;background:#fffdf0}.oa-legal:hover{background:#fff8e8}
.oa-term {color:#a02020;border-color:#f5b8b8;background:#fff8f8}.oa-term:hover{background:#fff0f0}
.oa-reinstate{color:var(--g600);border-color:var(--g300);background:var(--g100)}

/* STATUS BADGES */
.os-badge{padding:3px 8px;border-radius:5px;font-size:.58rem;font-weight:800;letter-spacing:.5px;text-transform:uppercase;border:1px solid transparent;white-space:nowrap;}
.os-active  {background:var(--g100);color:var(--g600);border-color:var(--g300)}
.os-slow    {background:#fff8e8;color:#8a6200;border-color:#f5d98a}
.os-low     {background:#fff8e8;color:#8a6200;border-color:#f5d98a}
.os-inactive{background:#fff0f0;color:#a02020;border-color:#f5b8b8}
.os-term    {background:#3d0000;color:#ffb3b3;border-color:#a02020}
.nb{padding:2px 6px;border-radius:4px;font-size:.55rem;font-weight:700;margin-left:3px;}
.nb-notice{background:#fff8e8;color:#8a6200;border:1px solid #f5d98a}
.nb-legal {background:#fff0f0;color:#a02020;border:1px solid #f5b8b8}

/* EMPTY */
.empty{text-align:center;padding:48px 20px}

/* MODALS */
.overlay{position:fixed;inset:0;background:rgba(6,26,15,.6);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s;backdrop-filter:blur(4px);}
.overlay.on{opacity:1;pointer-events:all}
.modal{background:var(--white);border:1.5px solid var(--border);border-radius:18px;width:92%;max-width:480px;padding:28px;box-shadow:var(--shadow-lg);transform:scale(.95) translateY(16px);transition:transform .24s cubic-bezier(.4,0,.2,1);}
.overlay.on .modal{transform:scale(1) translateY(0)}
.mh{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px}
.mh-title{font-size:.98rem;font-weight:700;color:var(--text);}
.mh-sub{font-size:.72rem;color:var(--muted);margin-top:4px;}
.mh-close{width:30px;height:30px;border-radius:8px;background:var(--g100);border:1.5px solid var(--border);color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:all .15s;flex-shrink:0;}
.mh-close:hover{background:var(--g200)}
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
.qm:hover{background:var(--g100);border-color:var(--g300)}
.btn-submit{width:100%;padding:12px;background:linear-gradient(135deg,var(--g700),var(--g500));color:var(--white);border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;font-weight:700;cursor:pointer;transition:all .18s;}
.btn-submit:hover{transform:translateY(-2px)}
.btn-legal{background:linear-gradient(135deg,#8a6200,#f59e0b)!important}
.btn-term{background:linear-gradient(135deg,#7f1d1d,#dc2626)!important}
.warn-box{background:#fff8e8;border:1.5px solid #f5d98a;border-radius:9px;padding:11px 14px;font-size:.75rem;color:#8a6200;line-height:1.65;margin-bottom:14px}
.danger-box{background:#fff0f0;border:1.5px solid #f5b8b8;border-left:4px solid #dc2626;border-radius:9px;padding:11px 14px;font-size:.75rem;color:#a02020;line-height:1.65;margin-bottom:14px}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-mark">🏛️</div>
    <div><div class="sb-name">Nagrik Seva</div><div class="sb-sub">Regulator Portal</div></div>
  </div>
  <div class="sb-divider"></div>
  <div class="sb-sec">Oversight</div>
  <a class="nav-a" href="regulator_dashboard.php"><span class="nav-ico">⊞</span> Dashboard</a>
  <a class="nav-a on" href="regulator_officers.php"><span class="nav-ico">👮</span> All Officers</a>
  <a class="nav-a" href="regulator_complaints.php"><span class="nav-ico">📋</span> All Complaints</a>
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
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <div class="tb-greeting"><?= $greeting ?>, <?= htmlspecialchars($first) ?> 👋</div>
      <div class="tb-sep"></div>
      <div class="tb-date"><?= date('D, d M Y') ?></div>
    </div>
    <div class="tb-center">
      <div class="tb-brand">🏛️ Nagrik Seva Portal</div>
      <div class="tb-tagline">Regulator Oversight Centre</div>
    </div>
    <div class="tb-right">
      <a href="regulator_dashboard.php" class="tb-back">← Dashboard</a>
      <div class="tb-badge">⚖️ <?= htmlspecialchars($dept) ?></div>
    </div>
  </div>

  <div class="body">
    <?php if($toast_ok): ?><div class="toast t-ok">✓ &nbsp;<?= htmlspecialchars($toast_ok) ?></div><?php endif; ?>
    <?php if($toast_err): ?><div class="toast t-err">⚠ &nbsp;<?= htmlspecialchars($toast_err) ?></div><?php endif; ?>

    <div class="page-header">
      <div>
        <div class="ph-title">👮 Officer Management</div>
        <div class="ph-sub">Monitor performance, issue notices, and manage officer accounts</div>
      </div>
    </div>

    <div class="stat-row">
      <div class="sc"><div class="sc-ico" style="background:var(--g100);border:1.5px solid var(--border)">👮</div><div><div class="sc-num"><?= $stats['total'] ?></div><div class="sc-lbl">Total Officers</div></div></div>
      <div class="sc"><div class="sc-ico" style="background:#e8f8ee;border:1.5px solid var(--g300)">✅</div><div><div class="sc-num"><?= $stats['active'] ?></div><div class="sc-lbl">Active</div></div></div>
      <div class="sc"><div class="sc-ico" style="background:#fff0f0;border:1.5px solid #f5b8b8">🚫</div><div><div class="sc-num"><?= $stats['inactive'] ?></div><div class="sc-lbl">Terminated</div></div></div>
      <div class="sc"><div class="sc-ico" style="background:#fff8e8;border:1.5px solid #f5d98a">⚠️</div><div><div class="sc-num"><?= $stats['flagged'] ?></div><div class="sc-lbl">Flagged</div></div></div>
    </div>

    <div class="filters">
      <form class="filter-form" method="GET" action="">
        <input type="text" name="q" class="fi-search" placeholder="🔍 Search officer, zone, email…" value="<?= htmlspecialchars($search) ?>">
        <select name="status" class="fi-sel">
          <option value="all" <?= $filter_status==='all'?'selected':'' ?>>All Status</option>
          <option value="active" <?= $filter_status==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $filter_status==='inactive'?'selected':'' ?>>Terminated</option>
        </select>
        <select name="dept" class="fi-sel">
          <option value="all" <?= $filter_dept==='all'?'selected':'' ?>>All Departments</option>
          <?php foreach($all_depts as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $filter_dept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="fi-btn">Filter</button>
        <?php if($search||$filter_status!=='all'||$filter_dept!=='all'): ?>
        <a href="regulator_officers.php" class="fi-clear">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="officers-table">
      <div class="ot-head">
        <div class="ot-th">Officer</div>
        <div class="ot-th">Zone / Dept</div>
        <div class="ot-th">Status</div>
        <div class="ot-th">Resolution</div>
        <div class="ot-th">Notices</div>
        <div class="ot-th">Actions</div>
      </div>
      <?php if(empty($officers)): ?>
      <div class="empty"><div style="font-size:2rem;margin-bottom:8px;opacity:.3">👮</div><div style="font-size:.84rem;font-weight:700;color:var(--text)">No officers found</div><div style="font-size:.75rem;color:var(--muted);margin-top:4px">Try adjusting filters</div></div>
      <?php else: ?>
      <?php foreach($officers as $o):
        $os   = officer_status($o);
        $rate = $o['total_assigned']>0 ? round(($o['resolved_count']/$o['total_assigned'])*100) : 0;
        $prog = $rate>=70?'var(--g500)':($rate>=40?'#f59e0b':'#dc2626');
        $init = strtoupper(substr($o['name'],0,1));
        $days = days_since($o['last_activity']??null);
      ?>
      <div class="ot-row">
        <!-- Officer -->
        <div style="display:flex;align-items:center;gap:10px">
          <div class="off-av <?= !$o['is_active']?'inactive':'' ?>"><?= $init ?></div>
          <div>
            <div class="off-name"><?= htmlspecialchars($o['name']) ?></div>
            <div class="off-email"><?= htmlspecialchars($o['email']??'') ?></div>
            <div style="font-size:.6rem;color:var(--muted);margin-top:1px;">Joined <?= date('d M Y',strtotime($o['created_at']??'now')) ?></div>
          </div>
        </div>
        <!-- Zone/Dept -->
        <div>
          <div class="off-zone">📍 <?= htmlspecialchars($o['zone']??'—') ?></div>
          <div class="off-dept" style="margin-top:3px">🏢 <?= htmlspecialchars($o['department']??'—') ?></div>
          <div style="font-size:.6rem;color:var(--muted);margin-top:2px;">Last active: <?= $days===0?'Today':($days===1?'Yesterday':"$days d ago") ?></div>
        </div>
        <!-- Status -->
        <div>
          <span class="os-badge <?= $os['cls'] ?>"><?= $os['label'] ?></span>
          <div style="font-size:.61rem;color:var(--muted);margin-top:5px;">📋 <?= (int)$o['total_assigned'] ?> assigned</div>
        </div>
        <!-- Resolution -->
        <div>
          <div class="rate-num" style="color:<?= $prog ?>"><?= $rate ?>%</div>
          <div class="prog-bar"><div class="prog-fill" style="width:<?= $rate ?>%;background:<?= $prog ?>"></div></div>
          <div style="font-size:.6rem;color:var(--muted);margin-top:3px;">✅ <?= (int)$o['resolved_count'] ?> · ⏳ <?= (int)$o['pending_count'] ?><?php if($o['escalated_count']>0): ?> · 🚨 <?= (int)$o['escalated_count'] ?><?php endif; ?></div>
        </div>
        <!-- Notices -->
        <div>
          <?php if($o['notice_count']>0||$o['legal_count']>0): ?>
            <?php if($o['notice_count']>0): ?><span class="nb nb-notice">📢 <?= $o['notice_count'] ?></span><?php endif; ?>
            <?php if($o['legal_count']>0): ?><span class="nb nb-legal">⚖️ <?= $o['legal_count'] ?></span><?php endif; ?>
          <?php else: ?>
            <span style="font-size:.68rem;color:var(--g300)">None</span>
          <?php endif; ?>
        </div>
        <!-- Actions -->
        <div class="off-actions">
          <?php if($o['is_active']): ?>
          <button class="oa oa-notif" onclick="openNotifModal(<?=$o['id']?>,'<?=addslashes(htmlspecialchars($o['name']))?>','<?=htmlspecialchars($o['department']??'')?>','<?=htmlspecialchars($o['zone']??'')?>')">🔔</button>
          <button class="oa oa-legal" onclick="openLegalModal(<?=$o['id']?>,'<?=addslashes(htmlspecialchars($o['name']))?>','<?=htmlspecialchars($o['department']??'')?>')">⚖️</button>
          <button class="oa oa-term"  onclick="openTermModal(<?=$o['id']?>,'<?=addslashes(htmlspecialchars($o['name']))?>','<?=htmlspecialchars($o['department']??'')?>')">🚫</button>
          <?php else: ?>
          <button class="oa oa-reinstate" onclick="openReinstateModal(<?=$o['id']?>,'<?=addslashes(htmlspecialchars($o['name']))?>')">✅ Reinstate</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- SEND NOTIFICATION MODAL -->
<div class="overlay" id="notif-overlay" onclick="if(event.target===this)closeNotifModal()">
  <div class="modal">
    <div class="mh"><div><div class="mh-title">🔔 Send Performance Notification</div><div class="mh-sub" id="nm-sub">Notify officer</div></div><button class="mh-close" onclick="closeNotifModal()">✕</button></div>
    <div class="modal-officer-info"><div class="modal-av" id="nm-av">S</div><div><div class="modal-name" id="nm-name">Officer</div><div class="modal-dept" id="nm-dept">Dept · Zone</div></div></div>
    <div class="quick-msgs">
      <button class="qm" onclick="setMsg('notif','You have unresolved complaints pending for more than 5 days. Immediate action required.')">⏰ Overdue reminder</button>
      <button class="qm" onclick="setMsg('notif','Your resolution rate is below the required threshold. Please close pending complaints by end of this week.')">📉 Low performance</button>
      <button class="qm" onclick="setMsg('notif','Please provide a status update on all complaints assigned to you that have been in progress for over 7 days.')">📝 Status update</button>
    </div>
    <form method="POST"><input type="hidden" name="action" value="send_notification"><input type="hidden" name="officer_id" id="nm-officer-id">
    <div class="fg"><label class="fl">Message</label><textarea class="fi" name="message" id="nm-msg" placeholder="Write your notification message…" required></textarea></div>
    <button type="submit" class="btn-submit">🔔 Send Notification</button></form>
  </div>
</div>

<!-- LEGAL MODAL -->
<div class="overlay" id="legal-overlay" onclick="if(event.target===this)closeLegalModal()">
  <div class="modal">
    <div class="mh"><div><div class="mh-title">⚖️ Issue Legal Notice</div><div class="mh-sub" id="lm-sub">Formal legal warning</div></div><button class="mh-close" onclick="closeLegalModal()">✕</button></div>
    <div class="modal-officer-info"><div class="modal-av" id="lm-av">S</div><div><div class="modal-name" id="lm-name">Officer</div><div class="modal-dept" id="lm-dept">Dept</div></div></div>
    <div class="warn-box">⚠ A legal notice is formally logged. Multiple legal notices may result in account termination.</div>
    <div class="quick-msgs">
      <button class="qm" onclick="setMsg('legal','You are hereby formally notified that your failure to address assigned complaints constitutes dereliction of duty under the Goa Grievance Redressal Act, 2024.')">📜 Dereliction of duty</button>
      <button class="qm" onclick="setMsg('legal','This legal notice is issued for continued failure to respond to escalated complaints. Further non-compliance will result in account termination.')">🔴 Final warning</button>
    </div>
    <form method="POST"><input type="hidden" name="action" value="send_legal"><input type="hidden" name="officer_id" id="lm-officer-id">
    <div class="fg"><label class="fl">Legal Notice Content</label><textarea class="fi" name="message" id="lm-msg" placeholder="Write the formal legal notice…" required></textarea></div>
    <button type="submit" class="btn-submit btn-legal">⚖️ Issue Legal Notice</button></form>
  </div>
</div>

<!-- TERMINATE MODAL -->
<div class="overlay" id="term-overlay" onclick="if(event.target===this)closeTermModal()">
  <div class="modal">
    <div class="mh"><div><div class="mh-title">🚫 Terminate Account</div><div class="mh-sub">This deactivates the officer account immediately</div></div><button class="mh-close" onclick="closeTermModal()">✕</button></div>
    <div class="modal-officer-info"><div class="modal-av inactive" id="tm-av">S</div><div><div class="modal-name" id="tm-name">Officer</div><div class="modal-dept" id="tm-dept">Dept</div></div></div>
    <div class="danger-box">🚨 <strong>Irreversible without reinstatement.</strong> Officer will be logged out immediately and unable to access the system.</div>
    <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to terminate this officer?')"><input type="hidden" name="action" value="terminate"><input type="hidden" name="officer_id" id="tm-officer-id">
    <div class="fg"><label class="fl">Reason for Termination</label><textarea class="fi" name="reason" id="tm-reason" placeholder="State the official reason…" required></textarea></div>
    <button type="submit" class="btn-submit btn-term">🚫 Terminate Account</button></form>
  </div>
</div>

<!-- REINSTATE MODAL -->
<div class="overlay" id="reinstate-overlay" onclick="if(event.target===this)closeReinstateModal()">
  <div class="modal" style="max-width:400px">
    <div class="mh"><div><div class="mh-title">✅ Reinstate Officer</div><div class="mh-sub">Restore system access</div></div><button class="mh-close" onclick="closeReinstateModal()">✕</button></div>
    <div class="modal-officer-info"><div class="modal-av" id="ri-av">S</div><div><div class="modal-name" id="ri-name">Officer</div></div></div>
    <div style="background:var(--g100);border:1.5px solid var(--g300);border-radius:9px;padding:11px 14px;font-size:.75rem;color:var(--g700);line-height:1.65;margin-bottom:14px">✅ Reinstating will restore the officer's account access. They will be able to log in and manage complaints again.</div>
    <form method="POST"><input type="hidden" name="action" value="reinstate"><input type="hidden" name="officer_id" id="ri-officer-id">
    <button type="submit" class="btn-submit">✅ Reinstate Account</button></form>
  </div>
</div>

<script>
function openNotifModal(id,name,dept,zone){
  document.getElementById('nm-officer-id').value=id;
  document.getElementById('nm-av').textContent=name.charAt(0).toUpperCase();
  document.getElementById('nm-name').textContent=name;
  document.getElementById('nm-dept').textContent=dept+' · '+zone;
  document.getElementById('nm-sub').textContent='Notifying: '+name;
  document.getElementById('nm-msg').value='';
  document.getElementById('notif-overlay').classList.add('on');
  document.body.style.overflow='hidden';
}
function closeNotifModal(){document.getElementById('notif-overlay').classList.remove('on');document.body.style.overflow='';}

function openLegalModal(id,name,dept){
  document.getElementById('lm-officer-id').value=id;
  document.getElementById('lm-av').textContent=name.charAt(0).toUpperCase();
  document.getElementById('lm-name').textContent=name;
  document.getElementById('lm-dept').textContent=dept;
  document.getElementById('lm-sub').textContent='Issuing to: '+name;
  document.getElementById('lm-msg').value='';
  document.getElementById('legal-overlay').classList.add('on');
  document.body.style.overflow='hidden';
}
function closeLegalModal(){document.getElementById('legal-overlay').classList.remove('on');document.body.style.overflow='';}

function openTermModal(id,name,dept){
  document.getElementById('tm-officer-id').value=id;
  document.getElementById('tm-av').textContent=name.charAt(0).toUpperCase();
  document.getElementById('tm-name').textContent=name;
  document.getElementById('tm-dept').textContent=dept;
  document.getElementById('tm-reason').value='';
  document.getElementById('term-overlay').classList.add('on');
  document.body.style.overflow='hidden';
}
function closeTermModal(){document.getElementById('term-overlay').classList.remove('on');document.body.style.overflow='';}

function openReinstateModal(id,name){
  document.getElementById('ri-officer-id').value=id;
  document.getElementById('ri-av').textContent=name.charAt(0).toUpperCase();
  document.getElementById('ri-name').textContent=name;
  document.getElementById('reinstate-overlay').classList.add('on');
  document.body.style.overflow='hidden';
}
function closeReinstateModal(){document.getElementById('reinstate-overlay').classList.remove('on');document.body.style.overflow='';}

function setMsg(type,msg){
  document.getElementById(type==='notif'?'nm-msg':'lm-msg').value=msg;
}

document.addEventListener('keydown',e=>{
  if(e.key!=='Escape')return;
  ['notif','legal','term','reinstate'].forEach(t=>{
    const el=document.getElementById(t+'-overlay')||document.getElementById('reinstate-overlay');
    if(el&&el.classList.contains('on'))el.classList.remove('on');
  });
  document.body.style.overflow='';
});

setTimeout(()=>{const t=document.querySelector('.toast');if(t){t.style.transition='opacity .5s';t.style.opacity='0';setTimeout(()=>t.remove(),500);}},4000);
</script>
</body>
</html>
