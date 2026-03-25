<?php
session_start();
require_once 'config.php';

if ((empty($_SESSION['user_id']) && !isset($_SESSION['is_demo'])) || $_SESSION['role'] !== 'officer') {
    header('Location: officer_login.php'); exit;
}

$uid      = (int)$_SESSION['user_id'];
$name     = $_SESSION['name'] ?? 'Officer';
$initials = strtoupper(substr($name, 0, 1));
$first    = explode(' ', $name)[0];

$r = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$r->bind_param('i', $uid); $r->execute();
$officer = $r->get_result()->fetch_assoc(); $r->close();
$zone = $officer['zone'] ?? 'All Zones';

// ── Filters ──
$filter_status = $_GET['filter'] ?? $_GET['status'] ?? '';
$filter_cat    = $_GET['cat'] ?? '';
$filter_pri    = $_GET['priority'] ?? '';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;
$offset        = ($page - 1) * $per_page;

// ── Determine scope: own vs all-zone ──
$scope = $_GET['scope'] ?? 'mine';   // 'mine' | 'zone'

// ── Build query ──
$where  = [];
$params = [];
$types  = '';

if ($scope === 'zone') {
    $where[]  = 'c.officer_id IS NULL';
    $where[]  = "c.status = 'new'";
    $zone_esc = $conn->real_escape_string($zone);
    if ($zone) $where[] = "(c.zone = '$zone_esc' OR c.zone IS NULL OR c.zone = '')";
} else {
    $where[]  = "c.officer_id = $uid";
}

if ($filter_status) { $where[] = 'c.status = ?'; $params[] = $filter_status; $types .= 's'; }
if ($filter_cat)    { $where[] = 'c.category = ?'; $params[] = $filter_cat;   $types .= 's'; }
if ($filter_pri)    { $where[] = 'c.priority = ?'; $params[] = $filter_pri;   $types .= 's'; }
if ($search)        { $where[] = '(c.title LIKE ? OR c.location LIKE ? OR c.complaint_no LIKE ?)';
                      $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'sss'; }

$w_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

// Total count
$cnt_sql = "SELECT COUNT(*) as c FROM complaints c LEFT JOIN users u ON c.citizen_id = u.id $w_sql";
$cnt_stmt = $conn->prepare($cnt_sql);
if ($types && $params) { $cnt_stmt->bind_param($types, ...$params); }
$cnt_stmt->execute();
$total_rows = (int)$cnt_stmt->get_result()->fetch_assoc()['c'];
$cnt_stmt->close();
$total_pages = max(1, ceil($total_rows / $per_page));

// Data
$data_sql = "SELECT c.*, u.name as citizen_name FROM complaints c
             LEFT JOIN users u ON c.citizen_id = u.id
             $w_sql ORDER BY c.updated_at DESC LIMIT $per_page OFFSET $offset";
$data_stmt = $conn->prepare($data_sql);
if ($types && $params) { $data_stmt->bind_param($types, ...$params); }
$data_stmt->execute();
$complaints = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

// ── Stats for sidebar ──
$stats = ['assigned'=>0,'in_progress'=>0,'resolved'=>0,'escalated'=>0,'total'=>0];
$r2 = $conn->query("SELECT status, COUNT(*) as c FROM complaints WHERE officer_id=$uid GROUP BY status");
while ($row = $r2->fetch_assoc()) {
    $stats['total'] += $row['c'];
    if (array_key_exists($row['status'], $stats)) $stats[$row['status']] = (int)$row['c'];
}
$unassigned_count = (int)($conn->query("SELECT COUNT(*) as c FROM complaints WHERE officer_id IS NULL AND status='new'")->fetch_assoc()['c'] ?? 0);

// ── Dummy fallback ──
if ($total_rows === 0 && !$types) {
    $complaints = [
        ['id'=>1,'complaint_no'=>'GRV-A4C7E2','category'=>'road','title'=>'Large pothole near NH17 petrol pump, Panaji','location'=>'Panaji, North Goa','status'=>'in_progress','priority'=>'high','created_at'=>date('Y-m-d H:i:s',strtotime('-4 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'citizen_name'=>'Rahul Naik','photo_path'=>null],
        ['id'=>2,'complaint_no'=>'GRV-B3D9F1','category'=>'water','title'=>'Burst water pipe flooding street — Caranzalem','location'=>'Caranzalem, Panaji','status'=>'assigned','priority'=>'high','created_at'=>date('Y-m-d H:i:s',strtotime('-6 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-6 days')),'citizen_name'=>'Priya Dessai','photo_path'=>null],
        ['id'=>3,'complaint_no'=>'GRV-C8A2B4','category'=>'electricity','title'=>'Street light outage — Miramar Beach Road','location'=>'Miramar, Panaji','status'=>'in_progress','priority'=>'medium','created_at'=>date('Y-m-d H:i:s',strtotime('-8 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-2 days')),'citizen_name'=>'Anton Fernandes','photo_path'=>'placeholder.jpg'],
        ['id'=>4,'complaint_no'=>'GRV-D5E1C9','category'=>'sanitation','title'=>'Overflowing garbage bins near Panjim Market','location'=>'Panjim Market','status'=>'resolved','priority'=>'medium','created_at'=>date('Y-m-d H:i:s',strtotime('-11 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-3 days')),'citizen_name'=>'Sunita Gaonkar','photo_path'=>null],
        ['id'=>5,'complaint_no'=>'GRV-E2F7A3','category'=>'road','title'=>'Broken footpath tiles causing hazard — Altinho','location'=>'Altinho, Panaji','status'=>'assigned','priority'=>'medium','created_at'=>date('Y-m-d H:i:s',strtotime('-15 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-15 days')),'citizen_name'=>'Maria Coelho','photo_path'=>null],
        ['id'=>6,'complaint_no'=>'GRV-F9B4D8','category'=>'water','title'=>'No water supply for 3 days — Dona Paula Ward','location'=>'Dona Paula','status'=>'escalated','priority'=>'high','created_at'=>date('Y-m-d H:i:s',strtotime('-20 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-5 days')),'citizen_name'=>'David Gomes','photo_path'=>null],
        ['id'=>7,'complaint_no'=>'GRV-G1C6E5','category'=>'road','title'=>'Road divider damaged on Calangute highway','location'=>'Calangute-Mapusa Road','status'=>'resolved','priority'=>'high','created_at'=>date('Y-m-d H:i:s',strtotime('-22 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-7 days')),'citizen_name'=>'Rohan Sawant','photo_path'=>null],
        ['id'=>8,'complaint_no'=>'GRV-H2K1P9','category'=>'lost','title'=>'Lost wallet — Goa Secretariat, near main gate','location'=>'Secretariat, Panaji','status'=>'assigned','priority'=>'low','created_at'=>date('Y-m-d H:i:s',strtotime('-32 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-30 days')),'citizen_name'=>'Agnes Monteiro','photo_path'=>null],
    ];
    $total_rows = count($complaints);
    $total_pages = 1;
}

$cat_icon = ['road'=>'🚧','water'=>'💧','electricity'=>'⚡','sanitation'=>'🧹','property'=>'🏛️','lost'=>'🔍'];
$s_cfg = [
    'new'        =>['label'=>'New',        'cls'=>'s-new'],
    'assigned'   =>['label'=>'Assigned',   'cls'=>'s-assigned'],
    'in_progress'=>['label'=>'In Progress','cls'=>'s-prog'],
    'resolved'   =>['label'=>'Resolved',   'cls'=>'s-resolved'],
    'closed'     =>['label'=>'Closed',     'cls'=>'s-closed'],
    'escalated'  =>['label'=>'Escalated',  'cls'=>'s-esc'],
];
$pri_cfg = ['high'=>['cls'=>'p-high','label'=>'High'],'medium'=>['cls'=>'p-med','label'=>'Medium'],'low'=>['cls'=>'p-low','label'=>'Low']];

function qstr($extra=[], $base=[]) {
    $p = array_merge($_GET, $base, $extra);
    unset($p['page']);
    return http_build_query(array_filter($p, fn($v)=>$v!==''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Complaints — Nagrik Seva Officer</title>
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

/* Sidebar — reuse exact same CSS */
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

/* TOPBAR */
.main{flex:1;height:100vh;overflow-y:auto;display:flex;flex-direction:column}
.topbar{background:var(--card);padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;flex-shrink:0;border-bottom:1px solid var(--border);box-shadow:var(--sh-xs)}
.tb-left{display:flex;align-items:center;gap:10px}
.tb-page-title{font-size:.88rem;font-weight:700;color:var(--text)}
.tb-right{display:flex;align-items:center;gap:10px}
.tb-back{padding:7px 14px;border-radius:8px;font-size:.76rem;font-weight:600;background:var(--g100);color:var(--g600);border:1px solid var(--border);transition:all .14s;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif}
.tb-back:hover{background:var(--border)}

/* BODY */
.body{padding:22px 28px 32px;flex:1}

/* FILTER BAR */
.filter-bar{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;box-shadow:var(--sh-xs)}
.search-wrap{position:relative;flex:1;min-width:200px}
.search-ico{position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:.8rem;pointer-events:none}
.search-fi{width:100%;padding:8px 12px 8px 32px;border:1.5px solid var(--border);border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;color:var(--text);outline:none;transition:all .15s;background:var(--card)}
.search-fi:focus{border-color:var(--g400);box-shadow:0 0 0 3px rgba(24,207,180,.1)}
.filter-select{padding:8px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.79rem;color:var(--text);outline:none;background:var(--card);cursor:pointer;transition:border-color .14s;min-width:130px}
.filter-select:focus{border-color:var(--g400)}
.filter-btn{padding:8px 16px;border-radius:9px;font-size:.78rem;font-weight:600;background:linear-gradient(135deg,var(--g600),var(--g400));color:#fff;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .14s;box-shadow:0 2px 8px rgba(24,207,180,.25)}
.filter-btn:hover{transform:translateY(-1px)}
.filter-clear{padding:8px 12px;border-radius:9px;font-size:.76rem;font-weight:500;background:var(--g100);color:var(--muted);border:1px solid var(--border);cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .14s;text-decoration:none;display:inline-flex;align-items:center}
.filter-clear:hover{background:var(--border);color:var(--text)}

/* SCOPE TABS */
.scope-tabs{display:flex;gap:1px;background:var(--border);border-radius:9px;overflow:hidden;margin-bottom:14px}
.scope-tab{flex:1;padding:9px 16px;text-align:center;font-size:.77rem;font-weight:600;color:var(--muted);background:var(--card);cursor:pointer;transition:all .14s;text-decoration:none;display:block}
.scope-tab.on{background:linear-gradient(135deg,var(--g600),var(--g400));color:#fff}
.scope-tab:first-child{border-radius:8px 0 0 8px}
.scope-tab:last-child{border-radius:0 8px 8px 0}

/* RESULTS META */
.results-meta{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.results-count{font-size:.75rem;color:var(--muted);font-weight:500}
.results-count strong{color:var(--text)}

/* COMPLAINT CARDS GRID */
.complaints-grid{display:flex;flex-direction:column;gap:8px}
.cc-card{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  padding:14px 16px;display:flex;align-items:center;gap:12px;
  box-shadow:var(--sh-xs);transition:all .15s;cursor:pointer;position:relative;
}
.cc-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:var(--radius) 0 0 var(--radius);background:transparent;transition:background .14s}
.cc-card:hover{transform:translateY(-1px);box-shadow:var(--sh-sm);border-color:var(--g200)}
.cc-card:hover::before{background:var(--g400)}
.cc-ico{width:38px;height:38px;border-radius:9px;background:var(--g100);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;transition:all .14s}
.cc-card:hover .cc-ico{background:var(--g050);border-color:var(--g200)}
.cc-body{flex:1;min-width:0}
.cc-id{font-size:.58rem;font-weight:600;color:var(--g500);font-family:'DM Mono',monospace;letter-spacing:.6px;margin-bottom:2px}
.cc-title{font-size:.82rem;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px}
.cc-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.cc-meta-item{font-size:.63rem;color:var(--muted)}
.cc-citizen{font-size:.63rem;font-weight:500;color:var(--g500)}
.cc-right{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0}
.cc-actions{display:flex;gap:5px;opacity:0;pointer-events:none;transition:opacity .14s}
.cc-card:hover .cc-actions{opacity:1;pointer-events:all}
.cta{width:28px;height:28px;border-radius:7px;border:1.5px solid var(--border);background:var(--card);display:flex;align-items:center;justify-content:center;font-size:.7rem;cursor:pointer;transition:all .12s;text-decoration:none}
.cta:hover{background:var(--g050);border-color:var(--g200)}

/* STATUS + PRIORITY PILLS */
.pill{display:inline-flex;align-items:center;padding:2px 7px;border-radius:4px;font-size:.57rem;font-weight:700;letter-spacing:.4px;white-space:nowrap;text-transform:uppercase;border:1px solid transparent}
.s-new      {background:var(--g050);color:var(--g700);border-color:var(--g200)}
.s-assigned {background:var(--g050);color:var(--g600);border-color:var(--g200)}
.s-prog     {background:rgba(139,92,246,.08);color:#5b21b6;border-color:rgba(139,92,246,.25)}
.s-resolved {background:rgba(16,185,129,.08);color:#065f46;border-color:rgba(16,185,129,.22)}
.s-closed   {background:var(--g100);color:var(--muted);border-color:var(--border2)}
.s-esc      {background:rgba(239,68,68,.08);color:#7f1d1d;border-color:rgba(239,68,68,.2)}
.p-high     {background:rgba(239,68,68,.08);color:#7f1d1d;border-color:rgba(239,68,68,.2)}
.p-med      {background:var(--g050);color:var(--g700);border-color:var(--g200)}
.p-low      {background:var(--g100);color:var(--muted);border-color:var(--border2)}

/* ASSIGN BTN */
.assign-btn{flex-shrink:0;padding:5px 11px;border-radius:7px;font-size:.68rem;font-weight:600;background:linear-gradient(135deg,var(--g600),var(--g400));color:#fff;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .14s;box-shadow:0 2px 6px rgba(24,207,180,.25)}
.assign-btn:hover{transform:translateY(-1px)}

/* PAGINATION */
.pagination{display:flex;justify-content:center;gap:6px;margin-top:20px}
.pg{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;font-size:.76rem;font-weight:600;color:var(--muted);border:1px solid var(--border);background:var(--card);text-decoration:none;transition:all .13s}
.pg:hover{border-color:var(--accent);color:var(--g600)}
.pg.on{background:linear-gradient(135deg,var(--g600),var(--g400));color:#fff;border-color:var(--g400)}
.pg-dots{display:flex;align-items:center;justify-content:center;width:32px;height:32px;font-size:.76rem;color:var(--muted)}

/* EMPTY */
.empty-state{text-align:center;padding:60px 24px}
.empty-ico{font-size:2.5rem;margin-bottom:12px;opacity:.25;display:block;animation:float 3s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
.empty-title{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:6px}
.empty-sub{font-size:.76rem;color:var(--muted);line-height:1.65}

@media(max-width:960px){.sidebar{display:none}}
@media(max-width:600px){.body{padding:14px}.filter-bar{flex-direction:column}}
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
    <div class="tb-left">
      <div class="tb-page-title">📋 <?= $scope === 'zone' ? 'Unassigned in Zone' : 'My Complaints' ?></div>
    </div>
    <div class="tb-right">
      <a href="officer_dashboard.php"><button class="tb-back">← Dashboard</button></a>
    </div>
  </div>

  <div class="body">

    <!-- SCOPE TABS -->
    <div class="scope-tabs">
      <a href="?<?= qstr([],['scope'=>'mine']) ?>" class="scope-tab <?= $scope!=='zone'?'on':'' ?>">📋 My Complaints (<?= $stats['total'] ?>)</a>
      <a href="?<?= qstr([],['scope'=>'zone']) ?>" class="scope-tab <?= $scope==='zone'?'on':'' ?>">📥 Unassigned in Zone (<?= $unassigned_count ?>)</a>
    </div>

    <!-- FILTERS -->
    <form method="GET" class="filter-bar">
      <input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>">
      <div class="search-wrap">
        <span class="search-ico">🔍</span>
        <input class="search-fi" type="text" name="q" placeholder="Search by title, location, reference…" value="<?= htmlspecialchars($search) ?>">
      </div>
      <?php if ($scope !== 'zone'): ?>
      <select class="filter-select" name="status">
        <option value="">All Statuses</option>
        <?php foreach(['assigned','in_progress','resolved','closed','escalated'] as $s): ?>
        <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <select class="filter-select" name="cat">
        <option value="">All Categories</option>
        <?php foreach(['road','water','electricity','sanitation','property','lost'] as $c): ?>
        <option value="<?= $c ?>" <?= $filter_cat===$c?'selected':'' ?>><?= ucfirst($c) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="filter-select" name="priority">
        <option value="">All Priorities</option>
        <option value="high" <?= $filter_pri==='high'?'selected':'' ?>>High</option>
        <option value="medium" <?= $filter_pri==='medium'?'selected':'' ?>>Medium</option>
        <option value="low" <?= $filter_pri==='low'?'selected':'' ?>>Low</option>
      </select>
      <button type="submit" class="filter-btn">Apply</button>
      <?php if($search||$filter_status||$filter_cat||$filter_pri): ?>
      <a href="?scope=<?= htmlspecialchars($scope) ?>" class="filter-clear">✕ Clear</a>
      <?php endif; ?>
    </form>

    <!-- RESULTS META -->
    <div class="results-meta">
      <div class="results-count">Showing <strong><?= count($complaints) ?></strong> of <strong><?= $total_rows ?></strong> complaints</div>
    </div>

    <!-- COMPLAINT CARDS -->
    <?php if (empty($complaints)): ?>
    <div class="empty-state"><span class="empty-ico">📭</span><div class="empty-title">No complaints found</div><div class="empty-sub">Try adjusting your filters or search terms.</div></div>
    <?php else: ?>
    <div class="complaints-grid">
      <?php foreach ($complaints as $c):
        $sc  = $s_cfg[$c['status']]  ?? ['label'=>ucfirst($c['status']),'cls'=>'s-new'];
        $pri = $pri_cfg[$c['priority'] ?? 'medium'] ?? ['label'=>'Medium','cls'=>'p-med'];
        $ico = $cat_icon[$c['category']] ?? '📋';
      ?>
      <div class="cc-card" onclick="location.href='officer_complaint_detail.php?id=<?= $c['id'] ?>'">
        <div class="cc-ico"><?= $ico ?></div>
        <div class="cc-body">
          <div class="cc-id"><?= htmlspecialchars($c['complaint_no']) ?></div>
          <div class="cc-title"><?= htmlspecialchars($c['title']) ?></div>
          <div class="cc-meta">
            <span class="cc-meta-item">📍 <?= htmlspecialchars($c['location']) ?></span>
            <span class="cc-meta-item">· 👤 <?= htmlspecialchars($c['citizen_name'] ?? 'Citizen') ?></span>
            <span class="cc-meta-item">· <?= date('d M Y', strtotime($c['created_at'])) ?></span>
          </div>
        </div>
        <div class="cc-right">
          <span class="pill <?= $sc['cls'] ?>"><?= $sc['label'] ?></span>
          <span class="pill <?= $pri['cls'] ?>"><?= $pri['label'] ?></span>
        </div>
        <?php if ($scope === 'zone'): ?>
        <form method="POST" action="officer_dashboard.php" onclick="event.stopPropagation()">
          <input type="hidden" name="action" value="self_assign">
          <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
          <button type="submit" class="assign-btn">+ Assign to Me</button>
        </form>
        <?php else: ?>
        <div class="cc-actions" onclick="event.stopPropagation()">
          <a href="officer_complaint_detail.php?id=<?= $c['id'] ?>" class="cta" title="Update">✏️</a>
          <a href="track.php?id=<?= urlencode($c['complaint_no']) ?>" class="cta" title="Track">🗺️</a>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?><a class="pg" href="?<?= qstr(['page'=>$page-1]) ?>">‹</a><?php endif; ?>
      <?php for ($i=1;$i<=$total_pages;$i++):
        if ($i===1||$i===$total_pages||abs($i-$page)<=1): ?>
        <a class="pg <?= $i===$page?'on':'' ?>" href="?<?= qstr(['page'=>$i]) ?>"><?= $i ?></a>
        <?php elseif (abs($i-$page)===2): ?><span class="pg-dots">…</span><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $total_pages): ?><a class="pg" href="?<?= qstr(['page'=>$page+1]) ?>">›</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
