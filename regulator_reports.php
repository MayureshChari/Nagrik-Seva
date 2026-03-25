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

// Load DB stats or use dummy data
$stats = ['total'=>0,'resolved'=>0,'pending'=>0,'escalated'=>0,'new'=>0,'in_progress'=>0,'closed'=>0];
$by_cat  = [];
$by_zone = [];
$by_officer = [];
$monthly = [];

if ($uid > 0) {
    $r=$conn->query("SELECT status,COUNT(*) as c FROM complaints GROUP BY status");
    if($r) while($row=$r->fetch_assoc()){
        $stats['total']+=$row['c'];
        $stats[$row['status']] = (int)$row['c'];
        if(in_array($row['status'],['resolved','closed'])) $stats['resolved']+=$row['c'];
        if(in_array($row['status'],['new','assigned','in_progress'])) $stats['pending']+=$row['c'];
    }
    $r2=$conn->query("SELECT category,COUNT(*) as c FROM complaints GROUP BY category ORDER BY c DESC");
    if($r2) while($row=$r2->fetch_assoc()) $by_cat[] = $row;
    $r3=$conn->query("SELECT location,COUNT(*) as c FROM complaints GROUP BY location ORDER BY c DESC LIMIT 8");
    if($r3) while($row=$r3->fetch_assoc()) $by_zone[] = $row;
    $r4=$conn->query("SELECT u.name,COUNT(c.id) as total,SUM(c.status IN ('resolved','closed')) as resolved FROM users u LEFT JOIN complaints c ON c.officer_id=u.id WHERE u.role='officer' GROUP BY u.id ORDER BY resolved DESC LIMIT 8");
    if($r4) while($row=$r4->fetch_assoc()) $by_officer[] = $row;
    $r5=$conn->query("SELECT DATE_FORMAT(created_at,'%b %Y') as month, COUNT(*) as filed, SUM(status IN ('resolved','closed')) as resolved FROM complaints GROUP BY DATE_FORMAT(created_at,'%Y%m') ORDER BY DATE_FORMAT(created_at,'%Y%m') DESC LIMIT 6");
    if($r5) while($row=$r5->fetch_assoc()) $monthly[]=$row;
    $monthly = array_reverse($monthly);
}

// Dummy fallback
if ($stats['total'] === 0) {
    $stats = ['total'=>47,'resolved'=>29,'pending'=>15,'escalated'=>3,'new'=>4,'in_progress'=>8,'closed'=>3,'assigned'=>3];
    $by_cat = [['category'=>'road','c'=>18],['category'=>'water','c'=>12],['category'=>'electricity','c'=>9],['category'=>'sanitation','c'=>5],['category'=>'property','c'=>3]];
    $by_zone = [['location'=>'Panaji','c'=>14],['location'=>'Margao','c'=>11],['location'=>'Vasco','c'=>9],['location'=>'Mapusa','c'=>7],['location'=>'Ponda','c'=>4],['location'=>'Calangute','c'=>2]];
    $by_officer = [
        ['name'=>'Suresh Kamat','total'=>12,'resolved'=>9],
        ['name'=>'Sunita Borkar','total'=>9,'resolved'=>7],
        ['name'=>'Anton Fernandes','total'=>10,'resolved'=>7],
        ['name'=>'Priya Dessai','total'=>8,'resolved'=>5],
        ['name'=>'Raj Naik','total'=>6,'resolved'=>1],
        ['name'=>'David Gomes','total'=>2,'resolved'=>0],
    ];
    $monthly = [
        ['month'=>'Oct 2024','filed'=>6,'resolved'=>4],
        ['month'=>'Nov 2024','filed'=>8,'resolved'=>6],
        ['month'=>'Dec 2024','filed'=>10,'resolved'=>7],
        ['month'=>'Jan 2025','filed'=>9,'resolved'=>6],
        ['month'=>'Feb 2025','filed'=>7,'resolved'=>4],
        ['month'=>'Mar 2025','filed'=>7,'resolved'=>2],
    ];
}

$resolution_rate = $stats['total'] > 0 ? round(($stats['resolved']/$stats['total'])*100) : 0;
$hour=(int)date('H'); $greeting=$hour<12?'Good morning':($hour<18?'Good afternoon':'Good evening');

$cat_icon = ['road'=>'🚧','water'=>'💧','electricity'=>'⚡','sanitation'=>'🧹','property'=>'🏛️','lost'=>'🔍'];
$cat_colors = ['road'=>'#0d8572','water'=>'#109e88','electricity'=>'#f59e0b','sanitation'=>'#3b82f6','property'=>'#8b5cf6','lost'=>'#ec4899'];

$max_cat = max(array_column($by_cat,'c'));
$max_zone = count($by_zone) ? max(array_column($by_zone,'c')) : 1;
$max_monthly_filed = count($monthly) ? max(array_column($monthly,'filed')) : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reports — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--g900:#011a18;--g800:#042e2a;--g750:#053d37;--g700:#065449;--g650:#0a6e60;--g600:#0d8572;--g500:#109e88;--g450:#14b89f;--g400:#18cfb4;--g350:#3ddbc3;--g300:#6ce5d2;--g200:#adf2e8;--g100:#e2faf7;--g050:#f0fdfb;--white:#ffffff;--accent:#18cfb4;--bg:#f0f9f4;--card:#ffffff;--text:#0d2b1b;--muted:#4a7260;--border:#c8e8d8;--radius:14px;--shadow:0 2px 12px rgba(13,43,27,0.07);--shadow-md:0 8px 28px rgba(13,43,27,0.11);}
html,body{height:100%;overflow:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;-webkit-font-smoothing:antialiased;}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--g300);border-radius:4px}
.sidebar{width:220px;min-width:220px;height:100vh;background:var(--g800);display:flex;flex-direction:column;z-index:51;overflow-y:auto;position:relative;}
.sidebar::after{content:'';position:absolute;right:0;top:0;bottom:0;width:1px;background:linear-gradient(180deg,transparent,var(--g500) 20%,var(--g400) 60%,var(--g500) 80%,transparent);}
.sb-logo{padding:24px 20px 22px;display:flex;align-items:center;gap:13px;}
.sb-mark{width:42px;height:42px;background:linear-gradient(135deg,var(--g400),var(--g350));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.sb-name{font-size:.9rem;font-weight:700;color:var(--white);line-height:1.1}
.sb-sub{font-size:.58rem;color:var(--g300);text-transform:uppercase;letter-spacing:1.2px;margin-top:3px;font-weight:500}
.sb-divider{height:1px;background:linear-gradient(90deg,transparent,var(--g650),transparent);margin:4px 0 8px;}
.sb-sec{padding:14px 20px 5px;font-size:.54rem;font-weight:700;letter-spacing:2.2px;text-transform:uppercase;color:var(--g450);}
.nav-a{display:flex;align-items:center;gap:10px;padding:9px 16px;margin:1px 10px;border-radius:10px;font-size:.79rem;font-weight:450;color:var(--g200);transition:all .18s;border:1px solid transparent;position:relative;}
.nav-a:hover{background:rgba(255,255,255,0.06);color:var(--white);transform:translateX(2px)}
.nav-a.on{background:linear-gradient(135deg,rgba(24,207,180,0.2),rgba(24,207,180,0.08));color:var(--white);font-weight:600;border-color:rgba(24,207,180,0.4);}
.nav-a.on::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:60%;background:var(--accent);border-radius:0 3px 3px 0;}
.nav-ico{font-size:.85rem;width:20px;text-align:center;flex-shrink:0}
.sb-foot{margin-top:auto;padding:12px 10px;border-top:1px solid rgba(255,255,255,0.06)}
.u-card{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:11px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);}
.u-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));border:2px solid rgba(24,207,180,0.4);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--white);}
.u-name{font-size:.77rem;font-weight:600;color:var(--white)}
.u-role{font-size:.58rem;color:var(--g300);margin-top:1px;text-transform:uppercase}
.u-logout{margin-left:auto;background:none;border:none;color:var(--g300);cursor:pointer;padding:5px;border-radius:7px;transition:all .15s;}
.u-logout:hover{color:var(--white);background:rgba(255,255,255,0.1)}
.main{flex:1;height:100vh;overflow-y:auto;display:flex;flex-direction:column;}
.topbar{background:var(--g750);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;flex-shrink:0;border-bottom:1px solid rgba(24,207,180,0.25);}
.tb-left{display:flex;align-items:center;gap:14px;flex:1}
.tb-greeting{font-size:.88rem;font-weight:500;color:var(--white);}
.tb-sep{width:1px;height:16px;background:rgba(255,255,255,0.15)}
.tb-date{font-size:.71rem;color:var(--g300);}
.tb-center{position:absolute;left:50%;transform:translateX(-50%);text-align:center;pointer-events:none;white-space:nowrap;}
.tb-brand{font-size:1rem;font-weight:700;color:var(--white);}
.tb-tagline{font-size:.57rem;color:var(--g300);letter-spacing:.6px;text-transform:uppercase;margin-top:2px}
.tb-right{display:flex;align-items:center;gap:10px;flex:1;justify-content:flex-end}
.tb-badge{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:rgba(24,207,180,.15);border:1px solid rgba(24,207,180,.3);font-size:.74rem;font-weight:600;color:var(--g200);}
.tb-back{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);font-size:.74rem;font-weight:600;color:var(--g200);transition:all .15s;}
.tb-back:hover{background:rgba(255,255,255,.14);color:var(--white)}
.body{padding:22px 28px;flex:1;}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.ph-title{font-size:1.35rem;font-weight:800;color:var(--text);letter-spacing:-.4px;}
.ph-sub{font-size:.78rem;color:var(--muted);margin-top:3px;}
.print-btn{padding:9px 18px;border-radius:10px;background:var(--card);border:1.5px solid var(--border);color:var(--muted);font-size:.8rem;font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .15s;}
.print-btn:hover{border-color:var(--accent);color:var(--text)}

/* KPI GRID */
.kpi-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:22px}
.kpi{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);position:relative;overflow:hidden;transition:all .22s;}
.kpi:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.kpi-green::before{background:linear-gradient(90deg,var(--g500),var(--g300))}
.kpi-blue::before{background:linear-gradient(90deg,#3b82f6,#93c5fd)}
.kpi-amber::before{background:linear-gradient(90deg,#f59e0b,#fcd34d)}
.kpi-red::before{background:linear-gradient(90deg,#dc2626,#f87171)}
.kpi-teal::before{background:linear-gradient(90deg,var(--accent),var(--g300))}
.kpi-ico{font-size:1.3rem;margin-bottom:8px;}
.kpi-num{font-size:2rem;font-weight:800;letter-spacing:-1px;color:var(--text);line-height:1}
.kpi-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-top:4px;font-weight:500}
.kpi-trend{font-size:.65rem;color:var(--g500);margin-top:6px;font-weight:600}

/* CHARTS GRID */
.charts-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:16px;margin-bottom:16px}
.charts-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
.chart-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.ch{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1.5px solid var(--border);background:linear-gradient(180deg,rgba(244,253,248,.8),rgba(255,255,255,0));}
.ch-title{font-size:.88rem;font-weight:700;color:var(--text);}
.ch-sub{font-size:.63rem;color:var(--muted);margin-top:2px;}
.chart-body{padding:16px}

/* BAR CHARTS */
.bar-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
.bar-row:last-child{margin-bottom:0}
.bar-label{font-size:.68rem;font-weight:600;color:var(--text);min-width:80px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bar-track{flex:1;height:20px;background:var(--g050);border-radius:5px;overflow:hidden;border:1px solid var(--border);position:relative;}
.bar-fill{height:100%;border-radius:5px;transition:width .8s cubic-bezier(.4,0,.2,1);}
.bar-ct{position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.62rem;font-weight:700;color:var(--white);font-family:'DM Mono',monospace;}
.bar-ct-out{font-size:.65rem;font-weight:700;color:var(--muted);font-family:'DM Mono',monospace;min-width:28px;text-align:right}

/* MONTHLY CHART */
.month-bars{display:flex;align-items:flex-end;gap:6px;height:140px;padding:0 4px}
.month-col{display:flex;flex-direction:column;align-items:center;gap:3px;flex:1;}
.month-bar-wrap{position:relative;width:100%;display:flex;flex-direction:column;align-items:center;gap:2px;height:120px;justify-content:flex-end;}
.month-bar-filed{width:60%;border-radius:4px 4px 0 0;background:var(--g400);min-height:2px;transition:height .8s;}
.month-bar-res{width:60%;border-radius:4px 4px 0 0;background:var(--g700);min-height:2px;transition:height .8s;}
.month-lbl{font-size:.56rem;color:var(--muted);text-align:center;margin-top:5px;white-space:nowrap}

/* OFFICER RANKING */
.rank-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);}
.rank-row:last-child{border-bottom:none}
.rank-num{width:22px;height:22px;border-radius:6px;background:var(--g100);display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;color:var(--g600);flex-shrink:0;}
.rank-num.gold{background:#fef3c7;color:#d97706}
.rank-num.silver{background:#f1f5f9;color:#64748b}
.rank-num.bronze{background:#fef0e8;color:#c2692a}
.rank-name{font-size:.78rem;font-weight:600;color:var(--text);flex:1;}
.rank-rate{font-size:.7rem;font-weight:700;font-family:'DM Mono',monospace}
.rank-bar{height:4px;background:var(--g100);border-radius:2px;margin-top:3px;overflow:hidden}
.rank-bar-fill{height:100%;border-radius:2px;background:var(--g500);}

/* DONUT (CSS only) */
.donut-wrap{display:flex;align-items:center;gap:18px;padding:12px 0}
.donut{width:100px;height:100px;border-radius:50%;flex-shrink:0;position:relative;}
.donut-legend{display:flex;flex-direction:column;gap:8px;}
.dl-item{display:flex;align-items:center;gap:8px;font-size:.72rem}
.dl-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}
.dl-lbl{color:var(--text);font-weight:600}
.dl-ct{color:var(--muted);font-size:.65rem}

/* SUMMARY TABLE */
.summary-table{width:100%;border-collapse:collapse;font-size:.76rem;}
.summary-table th{text-align:left;padding:8px 12px;font-size:.6rem;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);border-bottom:1.5px solid var(--border);font-weight:700;}
.summary-table td{padding:9px 12px;border-bottom:1px solid var(--border);color:var(--text);}
.summary-table tr:last-child td{border-bottom:none}
.summary-table tr:hover td{background:var(--g050)}
.rate-badge{padding:2px 8px;border-radius:5px;font-size:.62rem;font-weight:700;font-family:'DM Mono',monospace;}
.r-high{background:var(--g100);color:var(--g600)}
.r-mid{background:#fff8e8;color:#8a6200}
.r-low{background:#fff0f0;color:#a02020}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-logo"><div class="sb-mark">🏛️</div><div><div class="sb-name">Nagrik Seva</div><div class="sb-sub">Regulator Portal</div></div></div>
  <div class="sb-divider"></div>
  <div class="sb-sec">Oversight</div>
  <a class="nav-a" href="regulator_dashboard.php"><span class="nav-ico">⊞</span> Dashboard</a>
  <a class="nav-a" href="regulator_officers.php"><span class="nav-ico">👮</span> All Officers</a>
  <a class="nav-a" href="regulator_complaints.php"><span class="nav-ico">📋</span> All Complaints</a>
  <a class="nav-a on" href="regulator_reports.php"><span class="nav-ico">📊</span> Reports</a>
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
      <a href="logout.php" class="u-logout" title="Sign out"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="tb-left"><div class="tb-greeting"><?= $greeting ?>, <?= htmlspecialchars($first) ?> 👋</div><div class="tb-sep"></div><div class="tb-date"><?= date('D, d M Y') ?></div></div>
    <div class="tb-center"><div class="tb-brand">🏛️ Nagrik Seva Portal</div><div class="tb-tagline">Regulator Oversight Centre</div></div>
    <div class="tb-right"><a href="regulator_dashboard.php" class="tb-back">← Dashboard</a><div class="tb-badge">⚖️ <?= htmlspecialchars($dept) ?></div></div>
  </div>

  <div class="body">
    <div class="page-header">
      <div><div class="ph-title">📊 Reports & Analytics</div><div class="ph-sub">Platform-wide performance overview · Generated <?= date('d M Y, H:i') ?></div></div>
      <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
      <div class="kpi kpi-green"><div class="kpi-ico">📋</div><div class="kpi-num"><?= $stats['total'] ?></div><div class="kpi-lbl">Total Complaints</div><div class="kpi-trend">↑ All time</div></div>
      <div class="kpi kpi-teal"><div class="kpi-ico">✅</div><div class="kpi-num"><?= $stats['resolved'] ?></div><div class="kpi-lbl">Resolved / Closed</div><div class="kpi-trend"><?= $resolution_rate ?>% resolution rate</div></div>
      <div class="kpi kpi-amber"><div class="kpi-ico">⏳</div><div class="kpi-num"><?= $stats['pending'] ?></div><div class="kpi-lbl">Pending</div><div class="kpi-trend">Requires attention</div></div>
      <div class="kpi kpi-red"><div class="kpi-ico">🚨</div><div class="kpi-num"><?= $stats['escalated'] ?></div><div class="kpi-lbl">Escalated</div><div class="kpi-trend">Urgent action needed</div></div>
      <div class="kpi kpi-blue"><div class="kpi-ico">📈</div><div class="kpi-num"><?= $resolution_rate ?>%</div><div class="kpi-lbl">Resolution Rate</div><div class="kpi-trend"><?= $resolution_rate>=70?'✓ On target':'⚠ Below target' ?></div></div>
    </div>

    <!-- CHARTS ROW 1 -->
    <div class="charts-grid">
      <!-- Monthly trend -->
      <div class="chart-card">
        <div class="ch"><div><div class="ch-title">📅 Monthly Complaint Trend</div><div class="ch-sub">Complaints filed vs resolved (last 6 months)</div></div></div>
        <div class="chart-body">
          <div class="month-bars">
            <?php foreach($monthly as $m):
              $fh = round(($m['filed']/$max_monthly_filed)*110);
              $rh = round(($m['resolved']/$max_monthly_filed)*110);
            ?>
            <div class="month-col">
              <div class="month-bar-wrap">
                <div style="display:flex;gap:3px;align-items:flex-end;height:<?= $fh ?>px;margin-bottom:2px">
                  <div class="month-bar-filed" style="height:100%"></div>
                  <div class="month-bar-res" style="height:<?= round(($m['resolved']/$m['filed'])*100) ?>%;min-height:2px"></div>
                </div>
              </div>
              <div class="month-lbl"><?= htmlspecialchars(explode(' ',$m['month'])[0]) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;gap:14px;margin-top:10px;padding-top:8px;border-top:1px solid var(--border)">
            <span style="font-size:.65rem;color:var(--muted);display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;background:var(--g400);border-radius:2px;display:inline-block"></span>Filed</span>
            <span style="font-size:.65rem;color:var(--muted);display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;background:var(--g700);border-radius:2px;display:inline-block"></span>Resolved</span>
          </div>
        </div>
      </div>

      <!-- By Category -->
      <div class="chart-card">
        <div class="ch"><div><div class="ch-title">🏷️ Complaints by Category</div><div class="ch-sub">Distribution across service types</div></div></div>
        <div class="chart-body">
          <?php foreach($by_cat as $c):
            $pct = $max_cat > 0 ? round(($c['c']/$max_cat)*100) : 0;
            $color = $cat_colors[$c['category']] ?? 'var(--g500)';
            $ico   = $cat_icon[$c['category']]  ?? '📋';
          ?>
          <div class="bar-row">
            <div class="bar-label"><?= $ico ?> <?= ucfirst($c['category']) ?></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"><span class="bar-ct"><?= $c['c'] ?></span></div></div>
            <div class="bar-ct-out"><?= round(($c['c']/$stats['total'])*100) ?>%</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- CHARTS ROW 2 -->
    <div class="charts-grid">
      <!-- By Zone -->
      <div class="chart-card">
        <div class="ch"><div><div class="ch-title">📍 Complaints by Zone</div><div class="ch-sub">Geographic distribution</div></div></div>
        <div class="chart-body">
          <?php foreach($by_zone as $z):
            $pct = $max_zone > 0 ? round(($z['c']/$max_zone)*100) : 0;
          ?>
          <div class="bar-row">
            <div class="bar-label"><?= htmlspecialchars($z['location']) ?></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:linear-gradient(90deg,var(--g600),var(--g400))"><span class="bar-ct"><?= $z['c'] ?></span></div></div>
            <div class="bar-ct-out"><?= round(($z['c']/$stats['total'])*100) ?>%</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Officer Ranking -->
      <div class="chart-card">
        <div class="ch"><div><div class="ch-title">🏆 Officer Performance Ranking</div><div class="ch-sub">By resolution rate</div></div></div>
        <div class="chart-body">
          <?php
          usort($by_officer, fn($a,$b) => ($b['total']>0?($b['resolved']/$b['total']):0) <=> ($a['total']>0?($a['resolved']/$a['total']):0));
          foreach($by_officer as $i=>$o):
            $rate = $o['total']>0 ? round(($o['resolved']/$o['total'])*100) : 0;
            $rcls = $rate>=70?'r-high':($rate>=40?'r-mid':'r-low');
            $nrcls = $i===0?'rank-num gold':($i===1?'rank-num silver':($i===2?'rank-num bronze':'rank-num'));
          ?>
          <div class="rank-row">
            <div class="<?= $nrcls ?>"><?= $i+1 ?></div>
            <div style="flex:1">
              <div class="rank-name"><?= htmlspecialchars($o['name']) ?></div>
              <div class="rank-bar"><div class="rank-bar-fill" style="width:<?= $rate ?>%;background:<?= $rate>=70?'var(--g500)':($rate>=40?'#f59e0b':'#dc2626') ?>"></div></div>
            </div>
            <span class="rate-badge <?= $rcls ?>"><?= $rate ?>%</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- STATUS SUMMARY TABLE -->
    <div class="chart-card" style="margin-top:16px">
      <div class="ch"><div><div class="ch-title">📋 Status Summary</div><div class="ch-sub">Breakdown by complaint status</div></div></div>
      <div class="chart-body" style="padding:0">
        <table class="summary-table">
          <thead><tr><th>Status</th><th>Count</th><th>% of Total</th><th>Target</th><th>Health</th></tr></thead>
          <tbody>
            <?php
            $status_data = [
              ['status'=>'New',         'key'=>'new',         'target'=>'< 10%','ico'=>'🆕'],
              ['status'=>'Assigned',    'key'=>'assigned',    'target'=>'< 15%','ico'=>'👤'],
              ['status'=>'In Progress', 'key'=>'in_progress', 'target'=>'< 20%','ico'=>'⚙️'],
              ['status'=>'Resolved',    'key'=>'resolved',    'target'=>'> 60%','ico'=>'✅'],
              ['status'=>'Escalated',   'key'=>'escalated',   'target'=>'< 5%', 'ico'=>'🚨'],
              ['status'=>'Closed',      'key'=>'closed',      'target'=>'> 5%', 'ico'=>'🔒'],
            ];
            foreach($status_data as $sd):
              $ct  = (int)($stats[$sd['key']] ?? 0);
              $pct = $stats['total']>0 ? round(($ct/$stats['total'])*100) : 0;
              $health = $pct > 0 ? '✓ OK' : '–';
              if($sd['key']==='escalated'&&$pct>5) $health='⚠ High';
              if($sd['key']==='new'&&$pct>15) $health='⚠ High';
              if($sd['key']==='resolved'&&$pct<50) $health='⚠ Low';
            ?>
            <tr>
              <td><?= $sd['ico'] ?> <?= $sd['status'] ?></td>
              <td style="font-weight:700;font-family:'DM Mono',monospace"><?= $ct ?></td>
              <td><div style="display:flex;align-items:center;gap:8px"><div style="width:60px;height:4px;background:var(--g100);border-radius:2px;overflow:hidden"><div style="height:100%;width:<?= $pct ?>%;background:var(--g500);border-radius:2px"></div></div><span style="font-family:'DM Mono',monospace;font-size:.72rem"><?= $pct ?>%</span></div></td>
              <td style="color:var(--muted);font-size:.72rem"><?= $sd['target'] ?></td>
              <td style="font-weight:700;color:<?= strpos($health,'⚠')!==false?'#a02020':'var(--g600)' ?>;font-size:.75rem"><?= $health ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
</body>
</html>
