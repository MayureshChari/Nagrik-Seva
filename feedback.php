<?php
session_start();
require_once 'config.php';

$conn->query("CREATE TABLE IF NOT EXISTS feedback (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL, rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
  category VARCHAR(80) DEFAULT NULL, message TEXT DEFAULT NULL,
  name VARCHAR(120) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rating(rating), INDEX idx_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$uid      = $_SESSION['user_id'] ?? null;
$role     = $_SESSION['role']    ?? null;
$name_s   = $_SESSION['name']    ?? null;
$email_s  = $_SESSION['email']   ?? null;
$initials = $name_s ? strtoupper(substr($name_s,0,1)) : null;
$dash_link = match($role){ 'officer'=>'officer_dashboard.php','regulator'=>'regulator_dashboard.php',default=>'citizen_dashboard.php' };

$msg_err = ''; $submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating   = max(0, min(5, (int)($_POST['rating'] ?? 0)));
    $category = trim($_POST['category'] ?? '');
    $message  = trim($_POST['message']  ?? '');
    $fb_name  = trim($_POST['fb_name']  ?? ($name_s ?? ''));
    $fb_email = strtolower(trim($_POST['fb_email'] ?? ($email_s ?? '')));
    $ip       = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($rating === 0) {
        $msg_err = 'Please select a star rating before submitting.';
    } elseif (strlen($message) < 10) {
        $msg_err = 'Please write at least 10 characters in your feedback.';
    } else {
        $st = $conn->prepare("INSERT INTO feedback (user_id,rating,category,message,name,email,ip) VALUES (?,?,?,?,?,?,?)");
        $st->bind_param('iisssss', $uid, $rating, $category, $message, $fb_name, $fb_email, $ip);
        if ($st->execute()) { $submitted = true; } else { $msg_err = 'Something went wrong. Please try again.'; }
        $st->close();
    }
}

$avg_r = 0; $total_fb = 0;
$r = $conn->query("SELECT ROUND(AVG(rating),1) as avg_r, COUNT(*) as total FROM feedback");
if ($r && $row = $r->fetch_assoc()) { $avg_r = (float)$row['avg_r']; $total_fb = (int)$row['total']; }
$dist = [5=>0,4=>0,3=>0,2=>0,1=>0];
$r = $conn->query("SELECT rating, COUNT(*) as c FROM feedback GROUP BY rating");
if ($r) { while($row=$r->fetch_assoc()) $dist[(int)$row['rating']] = (int)$row['c']; }
$recent_fb = [];
$r = $conn->query("SELECT rating,category,message,name,created_at FROM feedback WHERE message IS NOT NULL AND LENGTH(message)>20 ORDER BY created_at DESC LIMIT 5");
if ($r) { while($row=$r->fetch_assoc()) $recent_fb[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Feedback — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --a:#042e2a;--a2:#18cfb4;--a3:rgba(24,207,180,.12);--ag:rgba(24,207,180,.08);
  --bg:#f0fdfb;--white:#ffffff;
  --text:#042e2a;--text2:#065449;--muted:#4a7260;--muted2:#2a7d4f;
  --border:rgba(4,46,42,.12);--border2:rgba(4,46,42,.24);
  --shadow:0 1px 4px rgba(4,46,42,.08),0 4px 14px rgba(4,46,42,.06);
  --shadow-md:0 4px 20px rgba(4,46,42,.12);--shadow-lg:0 16px 48px rgba(4,46,42,.14);
  --radius:14px;--red:#dc2626;--green:#059669;--amber:#d97706;
}
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;overflow-x:hidden}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-thumb{background:rgba(24,207,180,.3);border-radius:3px}

/* SCROLL PROGRESS */
#scroll-progress{position:fixed;top:0;left:0;height:3px;z-index:9999;background:linear-gradient(90deg,var(--a2),#7de8d8);width:0%;transition:width .1s linear;box-shadow:0 0 8px rgba(24,207,180,.6)}

.bg-canvas{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.mesh{position:absolute;border-radius:50%;filter:blur(80px);animation:drift 20s ease-in-out infinite alternate}
.m1{width:700px;height:700px;background:radial-gradient(circle,rgba(24,207,180,.08),transparent);top:-150px;left:-100px}
.m2{width:500px;height:500px;background:radial-gradient(circle,rgba(109,229,210,.06),transparent);bottom:-100px;right:-80px;animation-delay:-8s}
.dots{position:fixed;inset:0;z-index:0;background-image:radial-gradient(rgba(4,46,42,.04) 1px,transparent 1px);background-size:28px 28px;pointer-events:none}
@keyframes drift{0%{transform:translate(0,0)}100%{transform:translate(24px,18px)}}

/* NAV */
.topnav{position:sticky;top:0;z-index:200;background:rgba(255,255,255,.93);backdrop-filter:blur(20px) saturate(180%);border-bottom:1px solid var(--border);height:62px;box-shadow:0 1px 0 rgba(255,255,255,.8),0 2px 12px rgba(4,46,42,.06);transition:box-shadow .3s}
.topnav.scrolled{box-shadow:0 2px 20px rgba(4,46,42,.12)}
.nav-mark{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,var(--a),var(--a2));display:flex;align-items:center;justify-content:center;font-size:17px;box-shadow:0 3px 12px rgba(4,46,42,.22);flex-shrink:0;transition:transform .2s}
.nav-logo:hover .nav-mark{transform:scale(1.06) rotate(-5deg)}
.nav-name{font-family:'Fraunces',serif;font-size:.92rem;font-weight:700;color:var(--text);line-height:1.2}
.nav-tagline{font-size:.54rem;color:var(--muted);letter-spacing:.4px;text-transform:uppercase}
.nav-link-item{padding:7px 14px;border-radius:8px;font-size:.79rem;font-weight:500;color:var(--muted2);transition:all .16s;border:1px solid transparent;white-space:nowrap;cursor:pointer;display:inline-block}
.nav-link-item:hover{background:var(--ag);color:var(--text);border-color:var(--a3)}
.nav-link-item.active{background:var(--a3);color:var(--a);font-weight:700;border-color:rgba(24,207,180,.3)}
.nav-signin{padding:7px 15px;border-radius:8px;font-size:.79rem;font-weight:600;color:var(--muted2);border:1px solid transparent;transition:all .16s}
.nav-signin:hover{background:var(--ag);color:var(--text)}
.nav-cta{padding:8px 18px;border-radius:9px;font-size:.8rem;font-weight:700;background:linear-gradient(135deg,var(--a),var(--a2));color:#fff;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;box-shadow:0 3px 10px rgba(4,46,42,.22);transition:all .18s}
.nav-cta:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(4,46,42,.3)}
.nav-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--a2),rgba(24,207,180,.6));border:2px solid rgba(24,207,180,.4);display:flex;align-items:center;justify-content:center;font-size:.73rem;font-weight:700;color:#fff;flex-shrink:0}
.nav-username{font-size:.78rem;font-weight:600;color:var(--text)}

/* USER DROPDOWN */
.nav-user-wrap{position:relative}
.nav-user-btn{display:flex;align-items:center;gap:8px;background:none;border:none;padding:4px 8px 4px 4px;border-radius:10px;cursor:pointer;transition:background .15s;font-family:'Plus Jakarta Sans',sans-serif}
.nav-user-btn:hover{background:var(--ag)}
.nav-user-btn.open{background:var(--a3)}
.nav-user-chevron{font-size:.55rem;color:var(--muted);margin-left:2px;transition:transform .22s cubic-bezier(.34,1.56,.64,1);display:inline-block}
.nav-user-btn.open .nav-user-chevron{transform:rotate(180deg)}
.nav-dropdown{position:absolute;top:calc(100% + 8px);right:0;min-width:180px;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(4,46,42,.14);padding:6px;z-index:300;opacity:0;transform:translateY(-8px) scale(.97);pointer-events:none;transition:opacity .2s cubic-bezier(.22,1,.36,1),transform .2s cubic-bezier(.22,1,.36,1);transform-origin:top right}
.nav-dropdown.show{opacity:1;transform:translateY(0) scale(1);pointer-events:auto}
.nd-header{padding:10px 12px 8px;border-bottom:1px solid var(--border);margin-bottom:4px}
.nd-name{font-size:.82rem;font-weight:700;color:var(--text)}
.nd-role{font-size:.62rem;color:var(--muted);text-transform:capitalize;margin-top:1px}
.nd-item{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:8px;font-size:.78rem;font-weight:500;color:var(--text2);cursor:pointer;transition:background .14s,color .14s;text-decoration:none;width:100%;background:none;border:none;font-family:'Plus Jakarta Sans',sans-serif;text-align:left}
.nd-item:hover{background:var(--ag);color:var(--text)}
.nd-item.danger{color:#dc2626}
.nd-item.danger:hover{background:rgba(220,38,38,.07);color:#dc2626}
.nd-sep{height:1px;background:var(--border);margin:4px 0}
.nav-dash{padding:7px 13px;border-radius:8px;font-size:.76rem;font-weight:600;background:var(--ag);color:var(--text2);border:1px solid var(--a3);transition:all .15s}
.nav-dash:hover{background:var(--a3);color:var(--a)}
.navbar-toggler{border:none;padding:4px}
.navbar-toggler:focus{box-shadow:none}
.navbar-toggler-icon{background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(4,46,42,0.9)' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e")}
.offcanvas-sep{height:1px;background:var(--border);margin:8px 0}
.page{position:relative;z-index:1}

/* HERO */
.hero{background:linear-gradient(135deg,var(--a) 0%,#065449 45%,#0a8f7a 80%,var(--a2) 100%);padding:64px 0 80px;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 30% 50%,rgba(24,207,180,.18),transparent 60%)}
.hero::after{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.04) 1px,transparent 1px);background-size:32px 32px}
.hero-inner{max-width:700px;position:relative;z-index:1}
.hero-badge{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;border-radius:20px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);font-size:.63rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,.9);margin-bottom:20px;animation:hero-in .6s cubic-bezier(.34,1.56,.64,1) both}
.hero h1{font-family:'Fraunces',serif;font-size:clamp(2rem,4vw,3rem);font-weight:900;color:#fff;letter-spacing:-1.5px;line-height:1.1;margin-bottom:14px;animation:hero-in .7s cubic-bezier(.22,1,.36,1) .12s both}
.hero h1 span{color:#7de8d8}
.hero p{font-size:.9rem;color:rgba(255,255,255,.68);line-height:1.75;max-width:520px;margin:0;animation:hero-in .7s cubic-bezier(.22,1,.36,1) .24s both}
@keyframes hero-in{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.hero-wave{position:absolute;bottom:-2px;left:0;right:0;height:48px;background:var(--bg);clip-path:ellipse(55% 100% at 50% 100%)}

/* FORM CARD */
.form-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-md);overflow:hidden;animation:slide-up .6s cubic-bezier(.22,1,.36,1) .1s both}
@keyframes slide-up{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}
.form-header{padding:24px 28px 18px;border-bottom:1px solid var(--border)}
.form-header-title{font-family:'Fraunces',serif;font-size:1.2rem;font-weight:900;color:var(--text);letter-spacing:-.4px;margin-bottom:4px}
.form-header-sub{font-size:.76rem;color:var(--muted2);line-height:1.6}
.form-body{padding:28px}

.alert-err{padding:13px 16px;border-radius:10px;font-size:.81rem;margin-bottom:18px;border:1px solid rgba(220,38,38,.18);background:rgba(220,38,38,.07);color:var(--red);line-height:1.6;animation:fadeUp .3s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

/* SUCCESS */
.success-wrap{padding:52px 32px;text-align:center}
.success-ico{font-size:3.5rem;margin-bottom:20px;display:block;animation:success-bounce .7s cubic-bezier(.34,1.56,.64,1) both}
@keyframes success-bounce{from{transform:scale(0) rotate(-20deg);opacity:0}to{transform:scale(1) rotate(0);opacity:1}}
.success-title{font-family:'Fraunces',serif;font-size:1.9rem;font-weight:900;letter-spacing:-1px;margin-bottom:10px;animation:hero-in .6s cubic-bezier(.22,1,.36,1) .15s both}
.success-sub{font-size:.84rem;color:var(--muted2);line-height:1.75;margin-bottom:28px;animation:hero-in .6s cubic-bezier(.22,1,.36,1) .25s both}
.sbtn{padding:11px 22px;border-radius:10px;border:1.5px solid var(--border2);background:#fff;color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;font-size:.83rem;font-weight:600;cursor:pointer;transition:all .17s}
.sbtn:hover{border-color:var(--a2)}
.sbtn-pri{background:linear-gradient(135deg,var(--a),var(--a2));color:#fff;border:none;box-shadow:0 3px 10px rgba(4,46,42,.2)}
.sbtn-pri:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(4,46,42,.28)}

/* STARS */
.fg{margin-bottom:16px}
.fl{display:block;font-size:.62rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--muted2);margin-bottom:8px}
.stars-wrap{display:flex;gap:8px;margin-bottom:8px}
.star-btn{width:50px;height:50px;border-radius:12px;border:1.5px solid var(--border);background:var(--bg);font-size:22px;cursor:pointer;transition:all .22s cubic-bezier(.34,1.56,.64,1);display:flex;align-items:center;justify-content:center}
.star-btn:hover{background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.45);transform:scale(1.18) rotate(-4deg)}
.star-btn.lit{background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.5);box-shadow:0 2px 10px rgba(251,191,36,.2)}
.star-hint{font-size:.74rem;color:var(--muted);min-height:20px;transition:color .2s}

/* CHIPS */
.chip-wrap{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:4px}
.chip{padding:6px 13px;border-radius:20px;border:1.5px solid var(--border);background:#fff;font-size:.72rem;font-weight:600;color:var(--muted2);cursor:pointer;transition:all .18s cubic-bezier(.34,1.56,.64,1);font-family:'Plus Jakarta Sans',sans-serif}
.chip:hover{border-color:rgba(24,207,180,.4);background:var(--ag);color:var(--text);transform:translateY(-2px)}
.chip.on{background:var(--a3);border-color:rgba(24,207,180,.5);color:var(--a);transform:translateY(-2px)}

/* FIELDS */
.fi{width:100%;padding:11px 14px;background:#fff;border:1.5px solid var(--border);border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;color:var(--text);outline:none;transition:all .2s;box-shadow:0 1px 3px rgba(4,46,42,.04)}
.fi::placeholder{color:var(--muted);opacity:.7}
.fi:focus{border-color:var(--a2);box-shadow:0 0 0 3px rgba(24,207,180,.12),0 1px 3px rgba(4,46,42,.04)}
textarea.fi{resize:vertical;min-height:120px;line-height:1.65}
.char-count{font-size:.62rem;color:var(--muted);text-align:right;margin-top:5px;font-family:'DM Mono',monospace}
.submit-btn{width:100%;padding:13px;background:linear-gradient(135deg,var(--a),var(--a2));color:#fff;border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:8px;box-shadow:0 4px 16px rgba(4,46,42,.22)}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(4,46,42,.3)}
.submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none}

/* SIDEBAR / RATING CARDS */
.rcard{background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;animation:slide-up .6s cubic-bezier(.22,1,.36,1) both}
.rcard:nth-child(1){animation-delay:.18s}
.rcard:nth-child(2){animation-delay:.28s}
.rcard:nth-child(3){animation-delay:.38s}
.rcard-head{padding:15px 20px;border-bottom:1px solid var(--border);font-size:.84rem;font-weight:700;color:var(--text)}
.rcard-body{padding:20px}
.big-rating{text-align:center;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border)}
.big-num{font-family:'Fraunces',serif;font-size:3.8rem;font-weight:900;color:var(--a);letter-spacing:-3px;line-height:1}
.big-stars{font-size:1.2rem;margin:7px 0 4px}
.big-lbl{font-size:.67rem;color:var(--muted);text-transform:uppercase;letter-spacing:.8px}
.dist-row{display:flex;align-items:center;gap:8px;margin-bottom:9px}
.dist-row:last-child{margin-bottom:0}
.dist-lbl{font-size:.72rem;color:var(--muted2);min-width:28px;text-align:right;font-family:'DM Mono',monospace}
.dist-bar-wrap{flex:1;height:7px;border-radius:4px;background:var(--border);overflow:hidden}
.dist-bar{height:100%;border-radius:4px;background:linear-gradient(90deg,var(--a),var(--a2));width:0%;transition:width 1s cubic-bezier(.4,0,.2,1)}
.dist-count{font-size:.66rem;color:var(--muted);min-width:22px;text-align:right;font-family:'DM Mono',monospace}
.fb-item{padding:14px 0;border-bottom:1px solid var(--border);opacity:0;transform:translateY(10px);transition:opacity .4s,transform .4s}
.fb-item.visible{opacity:1;transform:translateY(0)}
.fb-item:last-child{border-bottom:none;padding-bottom:0}
.fb-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:7px}
.fb-stars{font-size:.9rem;letter-spacing:1px}
.fb-cat{font-size:.59rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;padding:3px 8px;border-radius:4px;background:var(--ag);color:var(--a2);border:1px solid rgba(24,207,180,.2)}
.fb-msg{font-size:.77rem;color:var(--text);line-height:1.65;margin-bottom:6px}
.fb-meta{font-size:.63rem;color:var(--muted)}
.tip-item{display:flex;gap:11px;align-items:flex-start;margin-bottom:12px;transition:transform .2s cubic-bezier(.34,1.56,.64,1)}
.tip-item:hover{transform:translateX(4px)}
.tip-item:last-child{margin-bottom:0}
.tip-ico{width:34px;height:34px;border-radius:9px;background:var(--ag);border:1px solid rgba(24,207,180,.2);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.tip-title{font-size:.78rem;font-weight:600;color:var(--text);margin-bottom:2px}
.tip-desc{font-size:.7rem;color:var(--muted2);line-height:1.55}

/* FOOTER */
.site-footer{background:var(--a);padding:36px 0;border-top:1px solid rgba(255,255,255,.1);margin-top:48px}
.footer-mark{width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.footer-name{font-family:'Fraunces',serif;font-size:.86rem;font-weight:700;color:#fff}
.footer-tag{font-size:.56rem;color:rgba(255,255,255,.45);margin-top:2px}
.footer-links a{font-size:.72rem;font-weight:500;color:rgba(255,255,255,.5);transition:color .14s}
.footer-links a:hover{color:#fff}
.footer-copy{font-size:.64rem;color:rgba(255,255,255,.35)}

/* SCROLL REVEAL */
.reveal{opacity:0;transform:translateY(28px);transition:opacity .6s cubic-bezier(.22,1,.36,1),transform .6s cubic-bezier(.22,1,.36,1)}
.reveal.visible{opacity:1;transform:translateY(0)}

@media(max-width:576px){.stars-wrap{gap:5px}.star-btn{width:42px;height:42px}}
</style>
</head>
<body>

<div id="scroll-progress"></div>
<div class="bg-canvas"><div class="mesh m1"></div><div class="mesh m2"></div></div>
<div class="dots"></div>

<!-- NAVBAR -->
<nav class="topnav navbar navbar-expand-lg px-0" id="topnav">
  <div class="container-fluid px-4 px-lg-5 h-100">
    <a href="index.php" class="nav-logo d-flex align-items-center gap-2 text-decoration-none me-3">
      <div class="nav-mark">🏛️</div>
      <div><div class="nav-name">Nagrik Seva</div><div class="nav-tagline">Goa Civic Portal</div></div>
    </a>
    <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-label="Menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav mx-auto gap-1 align-items-center">
        <li class="nav-item"><a class="nav-link-item" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link-item" href="<?= $uid ? $dash_link : 'citizen_login.php' ?>">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link-item" href="about.php">About</a></li>
        <li class="nav-item"><a class="nav-link-item" href="contact.php">Contact</a></li>
        <li class="nav-item"><a class="nav-link-item active" href="feedback.php">Feedback</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <?php if($name_s): ?>
        <div class="nav-user-wrap" id="nav-user-wrap">
          <button class="nav-user-btn" id="nav-user-btn" onclick="toggleUserMenu(event)" aria-expanded="false">
            <div class="nav-av"><?= $initials ?></div>
            <span class="nav-username"><?= htmlspecialchars(explode(' ',$name_s)[0]) ?></span>
            <span class="nav-user-chevron">▼</span>
          </button>
          <div class="nav-dropdown" id="nav-dropdown">
            <div class="nd-header">
              <div class="nd-name"><?= htmlspecialchars($name_s) ?></div>
              <div class="nd-role"><?= htmlspecialchars($role ?? 'citizen') ?></div>
            </div>
            <a href="<?= $dash_link ?>" class="nd-item">📊 Dashboard</a>
            <div class="nd-sep"></div>
            <a href="logout.php" class="nd-item danger">🚪 Log Out</a>
          </div>
        </div>
        <?php else: ?>
        <a href="citizen_login.php" class="nav-signin">Sign In</a>
        <a href="citizen_register.php"><button class="nav-cta">Sign Up →</button></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- OFFCANVAS -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileMenu" style="background:rgba(240,253,251,.97);backdrop-filter:blur(16px);">
  <div class="offcanvas-header">
    <h6 class="offcanvas-title" style="font-family:'Fraunces',serif;font-weight:700;color:var(--a)">Nagrik Seva</h6>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body pt-0">
    <a href="index.php" class="nav-link-item d-block mb-1">🏠 Home</a>
    <a href="<?= $uid ? $dash_link : 'citizen_login.php' ?>" class="nav-link-item d-block mb-1">📊 Dashboard</a>
    <a href="about.php" class="nav-link-item d-block mb-1">ℹ️ About</a>
    <a href="contact.php" class="nav-link-item d-block mb-1">📞 Contact</a>
    <a href="feedback.php" class="nav-link-item active d-block mb-1">💬 Feedback</a>
    <div class="offcanvas-sep"></div>
    <?php if($name_s): ?>
    <a href="<?= $dash_link ?>" class="nav-link-item d-block mb-1" style="color:var(--a);font-weight:700">📊 Dashboard</a>
    <?php else: ?>
    <a href="citizen_login.php" class="nav-link-item d-block mb-1">🔑 Sign In</a>
    <a href="citizen_register.php" class="nav-link-item d-block mb-1" style="color:var(--a);font-weight:700">➕ Sign Up</a>
    <?php endif; ?>
  </div>
</div>

<div class="page">

  <!-- HERO -->
  <section class="hero">
    <div class="container">
      <div class="hero-inner">
        <div class="hero-badge">💬 Share Your Feedback</div>
        <h1>Help us build a<br>better <span>Nagrik Seva</span></h1>
        <p>Your experience shapes how we improve the platform. Tell us what's working, what isn't, and what you'd love to see next.</p>
      </div>
    </div>
    <div class="hero-wave"></div>
  </section>

  <!-- MAIN CONTENT -->
  <div class="container py-5">
    <div class="row g-4 align-items-start">

      <!-- FORM -->
      <div class="col-12 col-lg-7">
        <div class="form-card">
          <?php if($submitted): ?>
          <div class="success-wrap">
            <span class="success-ico">🎉</span>
            <div class="success-title">Thank you!</div>
            <p class="success-sub">Your feedback has been recorded. Our team reads every response and uses it to improve Nagrik Seva for all citizens.</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
              <a href="feedback.php"><button class="sbtn">Submit Another</button></a>
              <a href="index.php"><button class="sbtn sbtn-pri">Back to Home →</button></a>
            </div>
          </div>
          <?php else: ?>
          <div class="form-header">
            <div class="form-header-title">Share your experience</div>
            <div class="form-header-sub">Takes less than 2 minutes. All feedback is read by our team.</div>
          </div>
          <div class="form-body">
            <?php if($msg_err): ?><div class="alert-err">⚠ <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>
            <form method="POST" id="fb-form" novalidate>

              <!-- STAR RATING -->
              <div class="fg">
                <label class="fl">Overall Rating <span style="color:var(--red)">*</span></label>
                <div class="stars-wrap" id="stars-row">
                  <?php for($i=1;$i<=5;$i++): ?>
                  <button type="button" class="star-btn <?= (int)($_POST['rating']??0)>=$i?'lit':'' ?>" data-val="<?=$i?>"
                    onclick="setRating(<?=$i?>)" onmouseenter="hoverRating(<?=$i?>)" onmouseleave="resetHover()">⭐</button>
                  <?php endfor; ?>
                </div>
                <div class="star-hint" id="star-hint"><?= (int)($_POST['rating']??0)>0 ? ['','Terrible','Poor','Okay','Good','Excellent'][(int)$_POST['rating']].' — '.(int)$_POST['rating'].'/5 stars' : 'Click a star to rate' ?></div>
                <input type="hidden" name="rating" id="rating-val" value="<?= (int)($_POST['rating']??0) ?>">
              </div>

              <!-- CATEGORY CHIPS -->
              <div class="fg">
                <label class="fl">What are you rating?</label>
                <div class="chip-wrap">
                  <?php
                  $cats = ['Ease of Use','Officer Response','Speed of Resolution','Portal Design','Communication','File Complaint Feature','Overall System'];
                  $sel = $_POST['category'] ?? '';
                  foreach($cats as $c): ?>
                  <span class="chip <?= $sel===$c?'on':'' ?>" onclick="selectChip(this,'<?= htmlspecialchars(addslashes($c)) ?>')"><?= htmlspecialchars($c) ?></span>
                  <?php endforeach; ?>
                </div>
                <input type="hidden" name="category" id="cat-val" value="<?= htmlspecialchars($sel) ?>">
              </div>

              <!-- MESSAGE -->
              <div class="fg">
                <label class="fl" for="fb-msg">Your Feedback <span style="color:var(--red)">*</span></label>
                <textarea class="fi" id="fb-msg" name="message" rows="5" maxlength="2000"
                  placeholder="Tell us what you experienced. What worked well? What was frustrating? Any features you'd love to see?"
                  oninput="document.getElementById('charcount').textContent=this.value.length+' / 2000'"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                <div class="char-count" id="charcount"><?= strlen($_POST['message']??'') ?> / 2000</div>
              </div>

              <!-- OPTIONAL INFO -->
              <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6">
                  <label class="fl" for="fb-name">Name <span style="font-size:.6rem;color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
                  <input class="fi" type="text" id="fb-name" name="fb_name" placeholder="Anonymous" value="<?= htmlspecialchars($_POST['fb_name'] ?? ($name_s ?? '')) ?>">
                </div>
                <div class="col-12 col-sm-6">
                  <label class="fl" for="fb-email">Email <span style="font-size:.6rem;color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
                  <input class="fi" type="email" id="fb-email" name="fb_email" placeholder="For follow-up" value="<?= htmlspecialchars($_POST['fb_email'] ?? ($email_s ?? '')) ?>">
                </div>
              </div>

              <button type="submit" class="submit-btn" id="submit-btn">Submit Feedback →</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- SIDEBAR -->
      <div class="col-12 col-lg-5">
        <div class="d-flex flex-column gap-3">

          <!-- Rating Summary -->
          <div class="rcard">
            <div class="rcard-head">📊 Community Ratings</div>
            <div class="rcard-body">
              <div class="big-rating">
                <div class="big-num"><?= $total_fb > 0 ? number_format($avg_r,1) : '—' ?></div>
                <div class="big-stars">
                  <?php if($total_fb>0): for($i=1;$i<=5;$i++) echo $i<=$avg_r?'⭐':'☆'; else: ?>—<?php endif; ?>
                </div>
                <div class="big-lbl"><?= number_format($total_fb) ?> response<?= $total_fb!==1?'s':'' ?></div>
              </div>
              <?php $max_d = max(array_values($dist)) ?: 1; ?>
              <?php foreach([5,4,3,2,1] as $star): ?>
              <div class="dist-row">
                <div class="dist-lbl"><?= $star ?>★</div>
                <div class="dist-bar-wrap"><div class="dist-bar" data-width="<?= $total_fb>0?round($dist[$star]/$max_d*100):0 ?>"></div></div>
                <div class="dist-count"><?= $dist[$star] ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Recent Feedback -->
          <div class="rcard">
            <div class="rcard-head">💬 Recent Responses</div>
            <div class="rcard-body" style="padding-top:<?= $recent_fb?'8':'20' ?>px">
              <?php if($recent_fb): foreach($recent_fb as $fb): ?>
              <div class="fb-item">
                <div class="fb-top">
                  <div class="fb-stars"><?php for($i=1;$i<=5;$i++) echo $i<=$fb['rating']?'⭐':'☆'; ?></div>
                  <?php if($fb['category']): ?><span class="fb-cat"><?= htmlspecialchars($fb['category']) ?></span><?php endif; ?>
                </div>
                <div class="fb-msg">"<?= htmlspecialchars(mb_substr($fb['message'],0,110)).(mb_strlen($fb['message'])>110?'…':'') ?>"</div>
                <div class="fb-meta"><?= $fb['name'] ? htmlspecialchars($fb['name']) : 'Anonymous' ?> &nbsp;·&nbsp; <?= date('d M Y', strtotime($fb['created_at'])) ?></div>
              </div>
              <?php endforeach; else: ?>
              <div style="text-align:center;color:var(--muted);font-size:.8rem;padding:8px 0">Be the first to share your experience!</div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Tips -->
          <div class="rcard">
            <div class="rcard-head">💡 What to include</div>
            <div class="rcard-body">
              <?php foreach([
                ['🎯','Be specific','Tell us exactly what worked or what didn\'t'],
                ['🐛','Report bugs','Screenshots or error messages help us fix things faster'],
                ['💡','Suggest features','We ship ideas from citizen feedback regularly'],
                ['🔢','Complaint reference','Include your GRV number if it\'s about a specific case'],
              ] as [$ico,$t,$d]): ?>
              <div class="tip-item">
                <div class="tip-ico"><?=$ico?></div>
                <div><div class="tip-title"><?=$t?></div><div class="tip-desc"><?=$d?></div></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="container">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-2">
          <div class="footer-mark">🏛️</div>
          <div><div class="footer-name">Nagrik Seva</div><div class="footer-tag">Goa Civic Portal · 2026</div></div>
        </div>
        <div class="footer-links d-flex gap-4 flex-wrap">
          <a href="about.php">About</a><a href="contact.php">Contact</a><a href="feedback.php">Feedback</a><a href="citizen_login.php">Sign In</a><a href="citizen_register.php">Register</a>
        </div>
        <div class="footer-copy">© 2026 Government of Goa. All rights reserved.</div>
      </div>
    </div>
  </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* SCROLL PROGRESS & NAV */
window.addEventListener('scroll',function(){
  document.getElementById('scroll-progress').style.width=(document.documentElement.scrollTop/(document.documentElement.scrollHeight-window.innerHeight)*100)+'%';
  document.getElementById('topnav').classList.toggle('scrolled',window.scrollY>20);
});

/* STAR RATING */
let curRating = <?= (int)($_POST['rating'] ?? 0) ?>;
const hints = ['','Terrible','Poor','Okay','Good','Excellent'];
const hintColors = ['','var(--red)','var(--red)','var(--amber)','var(--green)','var(--green)'];
function setRating(n){
  curRating=n;
  document.getElementById('rating-val').value=n;
  document.querySelectorAll('.star-btn').forEach((b,i)=>{
    b.classList.toggle('lit',i<n);
    if(i<n) b.style.transform='scale(1.1)';
    else b.style.transform='';
  });
  const hint=document.getElementById('star-hint');
  hint.textContent=hints[n]+' — '+n+' / 5 stars';
  hint.style.color=hintColors[n];
}
function hoverRating(n){
  document.querySelectorAll('.star-btn').forEach((b,i)=>{
    b.style.transform=i<n?'scale(1.18) rotate(-4deg)':'scale(1)';
  });
  const hint=document.getElementById('star-hint');
  hint.textContent=hints[n];
}
function resetHover(){
  document.querySelectorAll('.star-btn').forEach((b,i)=>{
    b.style.transform=i<curRating?'scale(1.1)':'';
  });
  if(curRating){document.getElementById('star-hint').textContent=hints[curRating]+' — '+curRating+' / 5 stars';}
  else{document.getElementById('star-hint').textContent='Click a star to rate';document.getElementById('star-hint').style.color='';}
}
function selectChip(el,val){
  document.querySelectorAll('.chip').forEach(c=>c.classList.remove('on'));
  el.classList.add('on');
  document.getElementById('cat-val').value=val;
}

/* FORM SUBMIT */
document.getElementById('fb-form')&&document.getElementById('fb-form').addEventListener('submit',function(e){
  const r=parseInt(document.getElementById('rating-val').value);
  if(!r){e.preventDefault();alert('Please click a star to rate your experience.');return;}
  const btn=document.getElementById('submit-btn');
  btn.disabled=true;btn.textContent='⏳ Submitting…';
});

/* ANIMATE DIST BARS on load */
window.addEventListener('load',function(){
  setTimeout(()=>{
    document.querySelectorAll('.dist-bar').forEach(bar=>{
      bar.style.width=bar.dataset.width+'%';
    });
  },600);
});

/* ANIMATE FEEDBACK ITEMS */
const fbObs=new IntersectionObserver(entries=>{
  entries.forEach((e,i)=>{
    if(e.isIntersecting) setTimeout(()=>e.target.classList.add('visible'),i*80);
  });
},{threshold:0.1});
document.querySelectorAll('.fb-item').forEach(el=>fbObs.observe(el));

/* SCROLL REVEAL */
const revObs=new IntersectionObserver(entries=>{
  entries.forEach(e=>{if(e.isIntersecting) e.target.classList.add('visible');});
},{threshold:0.12});
document.querySelectorAll('.reveal').forEach(el=>revObs.observe(el));

/* USER DROPDOWN */
function toggleUserMenu(e){e.stopPropagation();const btn=document.getElementById('nav-user-btn');const dd=document.getElementById('nav-dropdown');const open=dd.classList.toggle('show');btn.classList.toggle('open',open);btn.setAttribute('aria-expanded',open);}
document.addEventListener('click',function(){const dd=document.getElementById('nav-dropdown');const btn=document.getElementById('nav-user-btn');if(dd)dd.classList.remove('show');if(btn){btn.classList.remove('open');btn.setAttribute('aria-expanded','false');}});
</script>
</body>
</html>
