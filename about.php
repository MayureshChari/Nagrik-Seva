<?php
session_start();
require_once 'config.php';

$role      = $_SESSION['role'] ?? null;
$name_nav  = $_SESSION['name'] ?? null;
$initials  = $name_nav ? strtoupper(substr($name_nav,0,1)) : null;
$dash_link = match($role){ 'officer'=>'officer_dashboard.php','regulator'=>'regulator_dashboard.php',default=>'citizen_dashboard.php' };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>About — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,700;0,900;1,700;1,900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --a:#042e2a;--a2:#18cfb4;--a3:rgba(24,207,180,.12);--ag:rgba(24,207,180,.08);
  --bg:#f0fdfb;--white:#ffffff;
  --text:#042e2a;--text2:#065449;--muted:#4a7260;--muted2:#2a7d4f;
  --border:rgba(4,46,42,.12);--border2:rgba(4,46,42,.24);
  --shadow:0 1px 4px rgba(4,46,42,.08),0 4px 14px rgba(4,46,42,.06);
  --shadow-md:0 4px 20px rgba(4,46,42,.12);--shadow-lg:0 16px 48px rgba(4,46,42,.14);
  --radius:14px;
}
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;overflow-x:hidden}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-thumb{background:rgba(24,207,180,.3);border-radius:3px}

/* SCROLL PROGRESS */
#scroll-progress{position:fixed;top:0;left:0;height:3px;z-index:9999;background:linear-gradient(90deg,var(--a2),#7de8d8);width:0%;transition:width .1s linear;box-shadow:0 0 8px rgba(24,207,180,.6)}

/* BG */
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
.hero{background:linear-gradient(135deg,var(--a) 0%,#065449 45%,#0a8f7a 80%,var(--a2) 100%);padding:80px 0 88px;text-align:center;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 30% 50%,rgba(24,207,180,.2),transparent 60%),radial-gradient(ellipse at 75% 30%,rgba(255,255,255,.06),transparent 50%)}
.hero::after{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.04) 1px,transparent 1px);background-size:32px 32px}
.hero-badge{display:inline-flex;align-items:center;gap:7px;padding:6px 16px;border-radius:20px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);font-size:.65rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,.9);margin-bottom:24px;position:relative;z-index:1;animation:hero-in .6s cubic-bezier(.34,1.56,.64,1) both}
.hero h1{font-family:'Fraunces',serif;font-size:clamp(2.4rem,5vw,3.8rem);font-weight:900;color:#fff;letter-spacing:-2px;line-height:1.05;margin-bottom:18px;position:relative;z-index:1;animation:hero-in .7s cubic-bezier(.22,1,.36,1) .12s both}
.hero h1 span{color:#7de8d8}
.hero p{font-size:1rem;color:rgba(255,255,255,.72);line-height:1.75;max-width:580px;margin:0 auto 36px;position:relative;z-index:1;animation:hero-in .7s cubic-bezier(.22,1,.36,1) .24s both}
.hero-btns{position:relative;z-index:1;animation:hero-in .7s cubic-bezier(.22,1,.36,1) .36s both}
@keyframes hero-in{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
.hero-btn-pri{padding:12px 28px;border-radius:10px;background:#fff;color:var(--a);font-size:.85rem;font-weight:700;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;box-shadow:0 4px 16px rgba(0,0,0,.15);transition:all .18s}
.hero-btn-pri:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.2)}
.hero-btn-sec{padding:12px 28px;border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:.85rem;font-weight:600;border:1px solid rgba(255,255,255,.25);transition:all .18s;display:inline-block}
.hero-btn-sec:hover{background:rgba(255,255,255,.2);transform:translateY(-1px)}
.hero-wave{position:absolute;bottom:-2px;left:0;right:0;height:48px;background:var(--bg);clip-path:ellipse(55% 100% at 50% 100%)}

/* SECTION LABELS */
.section-label{font-size:.6rem;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:var(--a2);margin-bottom:10px}
.section-title{font-family:'Fraunces',serif;font-size:clamp(1.7rem,3vw,2.4rem);font-weight:900;color:var(--text);letter-spacing:-1.2px;line-height:1.1;margin-bottom:14px}
.section-sub{font-size:.88rem;color:var(--muted2);line-height:1.8;max-width:580px}

/* MISSION */
.mission-text p{font-size:.88rem;color:var(--muted2);line-height:1.9;margin-bottom:16px}
.mission-text p:last-child{margin-bottom:0}
.mv-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:20px 16px;text-align:center;box-shadow:var(--shadow);transition:all .28s cubic-bezier(.34,1.56,.64,1);height:100%}
.mv-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-md);border-color:rgba(24,207,180,.3)}
.mv-ico{font-size:1.8rem;margin-bottom:8px}
.mv-num{font-family:'Fraunces',serif;font-size:1.6rem;font-weight:900;color:var(--a);letter-spacing:-1px;margin-bottom:2px}
.mv-lbl{font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--muted)}

/* STEP CARDS */
.step-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:28px 20px 24px;position:relative;box-shadow:var(--shadow);transition:all .28s cubic-bezier(.34,1.56,.64,1);height:100%}
.step-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-md);border-color:rgba(24,207,180,.35)}
.step-num{position:absolute;top:20px;right:20px;width:28px;height:28px;border-radius:50%;background:var(--a3);border:1.5px solid rgba(24,207,180,.3);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;color:var(--a2);font-family:'DM Mono',monospace}
.step-ico{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--ag),var(--a3));border:1.5px solid rgba(24,207,180,.25);display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:14px;transition:transform .3s cubic-bezier(.34,1.56,.64,1)}
.step-card:hover .step-ico{transform:scale(1.15) rotate(-6deg)}
.step-title{font-size:.87rem;font-weight:700;color:var(--text);margin-bottom:7px}
.step-desc{font-size:.75rem;color:var(--muted2);line-height:1.7;margin:0}

/* VALUES */
.val-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:28px 24px;box-shadow:var(--shadow);transition:all .28s cubic-bezier(.34,1.56,.64,1);height:100%}
.val-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-md);border-color:rgba(24,207,180,.3)}
.val-ico-wrap{width:50px;height:50px;border-radius:13px;margin-bottom:16px;background:linear-gradient(135deg,var(--ag),var(--a3));border:1.5px solid rgba(24,207,180,.2);display:flex;align-items:center;justify-content:center;font-size:1.4rem;transition:transform .3s cubic-bezier(.34,1.56,.64,1)}
.val-card:hover .val-ico-wrap{transform:scale(1.1) rotate(-5deg)}
.val-title{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:8px}
.val-desc{font-size:.77rem;color:var(--muted2);line-height:1.75;margin:0}

/* TEAM */
.team-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:28px 18px 22px;text-align:center;box-shadow:var(--shadow);transition:all .28s cubic-bezier(.34,1.56,.64,1);height:100%}
.team-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-md);border-color:rgba(24,207,180,.3)}
.team-av{width:64px;height:64px;border-radius:50%;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;border:3px solid rgba(24,207,180,.25);background:linear-gradient(135deg,var(--ag),var(--a3));transition:transform .3s cubic-bezier(.34,1.56,.64,1)}
.team-card:hover .team-av{transform:scale(1.08)}
.team-name{font-size:.86rem;font-weight:700;color:var(--text);margin-bottom:4px}
.team-role{font-size:.67rem;font-weight:600;text-transform:uppercase;letter-spacing:.7px;color:var(--a2);margin-bottom:8px}
.team-bio{font-size:.72rem;color:var(--muted2);line-height:1.65;margin:0}

/* STATS BAR */
.stats-bar-dark{background:linear-gradient(135deg,var(--a) 0%,#065449 50%,#0a6e60 100%);padding:52px 0}
.sb-item{text-align:center}
.sb-num{font-family:'Fraunces',serif;font-size:2.4rem;font-weight:900;color:#fff;letter-spacing:-1.5px;line-height:1}
.sb-lbl{font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.6);margin-top:6px}

/* TIMELINE — animated */
.timeline{position:relative;padding-left:32px;margin-top:40px}
.timeline::before{content:'';position:absolute;left:10px;top:8px;bottom:8px;width:2px;background:linear-gradient(180deg,var(--a2),rgba(24,207,180,.1));border-radius:1px}
.tl-item{position:relative;margin-bottom:36px;opacity:0;transform:translateX(-20px);transition:opacity .55s cubic-bezier(.22,1,.36,1),transform .55s cubic-bezier(.22,1,.36,1)}
.tl-item.visible{opacity:1;transform:translateX(0)}
.tl-item:last-child{margin-bottom:0}
.tl-dot{position:absolute;left:-27px;top:4px;width:14px;height:14px;border-radius:50%;background:var(--a2);border:3px solid var(--bg);box-shadow:0 0 0 2px rgba(24,207,180,.3);transition:transform .25s cubic-bezier(.34,1.56,.64,1)}
.tl-item:hover .tl-dot{transform:scale(1.4)}
.tl-year{font-size:.62rem;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--a2);margin-bottom:4px;font-family:'DM Mono',monospace}
.tl-title{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:5px}
.tl-desc{font-size:.77rem;color:var(--muted2);line-height:1.7}

/* CTA */
.cta-card{max-width:640px;margin:0 auto;background:linear-gradient(135deg,var(--a) 0%,#065449 55%,#0a8f7a 100%);border-radius:20px;padding:56px 48px;position:relative;overflow:hidden;box-shadow:var(--shadow-lg);text-align:center}
.cta-card::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80% 20%,rgba(24,207,180,.2),transparent 60%)}
.cta-card h2{font-family:'Fraunces',serif;font-size:1.9rem;font-weight:900;color:#fff;letter-spacing:-1px;margin-bottom:12px;position:relative;z-index:1}
.cta-card > p{font-size:.86rem;color:rgba(255,255,255,.7);line-height:1.75;margin-bottom:28px;position:relative;z-index:1}
.cta-btns{position:relative;z-index:1}
.cta-pri{padding:12px 26px;border-radius:10px;background:#fff;color:var(--a);font-size:.83rem;font-weight:700;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;box-shadow:0 4px 14px rgba(0,0,0,.15);transition:all .17s}
.cta-pri:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(0,0,0,.2)}
.cta-sec{padding:12px 26px;border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:.83rem;font-weight:600;border:1px solid rgba(255,255,255,.25);display:inline-block;transition:all .17s}
.cta-sec:hover{background:rgba(255,255,255,.2)}

/* FOOTER */
.site-footer{background:var(--a);padding:40px 0;border-top:1px solid rgba(255,255,255,.1)}
.footer-mark{width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.footer-name{font-family:'Fraunces',serif;font-size:.86rem;font-weight:700;color:#fff}
.footer-tag{font-size:.56rem;color:rgba(255,255,255,.45);margin-top:2px}
.footer-links a{font-size:.72rem;font-weight:500;color:rgba(255,255,255,.5);transition:color .14s}
.footer-links a:hover{color:#fff}
.footer-copy{font-size:.64rem;color:rgba(255,255,255,.35)}

/* SCROLL-REVEAL */
.reveal{opacity:0;transform:translateY(32px);transition:opacity .65s cubic-bezier(.22,1,.36,1),transform .65s cubic-bezier(.22,1,.36,1)}
.reveal.visible{opacity:1;transform:translateY(0)}
.reveal-scale{opacity:0;transform:scale(.93);transition:opacity .55s cubic-bezier(.22,1,.36,1),transform .55s cubic-bezier(.22,1,.36,1)}
.reveal-scale.visible{opacity:1;transform:scale(1)}
.delay-1{transition-delay:.08s}.delay-2{transition-delay:.16s}.delay-3{transition-delay:.24s}
.delay-4{transition-delay:.32s}.delay-5{transition-delay:.40s}.delay-6{transition-delay:.48s}

@media(max-width:576px){.cta-card{padding:36px 22px}.site-footer{padding:30px 20px}}
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
        <li class="nav-item"><a class="nav-link-item" href="citizen_dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link-item active" href="about.php">About</a></li>
        <li class="nav-item"><a class="nav-link-item" href="contact.php">Contact</a></li>
        <li class="nav-item"><a class="nav-link-item" href="feedback.php">Feedback</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <?php if($name_nav): ?>
        <div class="nav-user-wrap" id="nav-user-wrap">
          <button class="nav-user-btn" id="nav-user-btn" onclick="toggleUserMenu(event)" aria-expanded="false">
            <div class="nav-av"><?= $initials ?></div>
            <span class="nav-username"><?= htmlspecialchars(explode(' ',$name_nav)[0]) ?></span>
            <span class="nav-user-chevron">▼</span>
          </button>
          <div class="nav-dropdown" id="nav-dropdown">
            <div class="nd-header">
              <div class="nd-name"><?= htmlspecialchars($name_nav) ?></div>
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

<!-- OFFCANVAS MOBILE MENU -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileMenu" style="background:rgba(240,253,251,.97);backdrop-filter:blur(16px);">
  <div class="offcanvas-header">
    <h6 class="offcanvas-title" style="font-family:'Fraunces',serif;font-weight:700;color:var(--a)">Nagrik Seva</h6>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body pt-0">
    <a href="index.php" class="nav-link-item d-block mb-1">🏠 Home</a>
    <a href="citizen_dashboard.php" class="nav-link-item d-block mb-1">📊 Dashboard</a>
    <a href="about.php" class="nav-link-item active d-block mb-1">ℹ️ About</a>
    <a href="contact.php" class="nav-link-item d-block mb-1">📞 Contact</a>
    <a href="feedback.php" class="nav-link-item d-block mb-1">💬 Feedback</a>
    <div class="offcanvas-sep"></div>
    <?php if($name_nav): ?>
    <a href="<?= $dash_link ?>" class="nav-link-item d-block mb-1" style="color:var(--a);font-weight:700">📊 My Dashboard</a>
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
      <div class="hero-badge">🏛️ Nagrik Seva — Est. 2024</div>
      <h1>Empowering Goa's<br><span>Citizens</span>, One<br>Complaint at a Time</h1>
      <p>Nagrik Seva is Goa's official digital civic grievance platform — bridging citizens with government departments for faster, transparent, and accountable resolution of everyday civic issues.</p>
      <div class="hero-btns d-flex gap-3 justify-content-center flex-wrap">
        <a href="citizen_register.php"><button class="hero-btn-pri">Join the Movement →</button></a>
        <a href="contact.php"><button class="hero-btn-sec">Get in Touch</button></a>
      </div>
    </div>
    <div class="hero-wave"></div>
  </section>

  <!-- MISSION -->
  <section class="py-5">
    <div class="container py-4">
      <div class="section-label reveal">Our Mission</div>
      <div class="section-title reveal delay-1">Built for the citizen.<br>Accountable to Goa.</div>
      <div class="row g-5 align-items-start mt-2">
        <div class="col-12 col-lg-6 reveal">
          <div class="mission-text">
            <p>Nagrik Seva was born from a simple frustration: reporting a pothole, a burst pipe, or a broken streetlight in Goa required navigating opaque bureaucratic channels with zero visibility into what happened next.</p>
            <p>We built a platform where any citizen can file a complaint in under two minutes — with photo evidence, GPS location, and a unique reference number — and track it until it's fully resolved.</p>
            <p>Our mission is radical transparency. Every complaint is logged, assigned, escalated if needed, and resolved with a timestamped audit trail that both citizens and officers can see.</p>
            <p>We are not just a grievance portal. We are a civic accountability engine for the state of Goa.</p>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="row g-3">
            <div class="col-6"><div class="mv-card reveal delay-1"><div class="mv-ico">📋</div><div class="mv-num counter" data-target="12400" data-display="12,400+">12,400+</div><div class="mv-lbl">Complaints Filed</div></div></div>
            <div class="col-6"><div class="mv-card reveal delay-2"><div class="mv-ico">✅</div><div class="mv-num counter" data-target="10900" data-display="10,900+">10,900+</div><div class="mv-lbl">Resolved</div></div></div>
            <div class="col-6"><div class="mv-card reveal delay-3"><div class="mv-ico">⚡</div><div class="mv-num">4.2 days</div><div class="mv-lbl">Avg Resolution</div></div></div>
            <div class="col-6"><div class="mv-card reveal delay-4"><div class="mv-ico">👥</div><div class="mv-num counter" data-target="18000" data-display="18,000+">18,000+</div><div class="mv-lbl">Active Citizens</div></div></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section class="py-5" style="background:var(--white)">
    <div class="container py-4">
      <div class="section-label reveal">How It Works</div>
      <div class="section-title reveal delay-1">From complaint to resolution<br>in four steps</div>
      <div class="row g-3 mt-3">
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="step-card reveal delay-1"><div class="step-num">01</div><div class="step-ico">📸</div><div class="step-title">File a Complaint</div><p class="step-desc">Register and submit your issue with a title, category, location pin, and optional photo evidence — all in under 2 minutes.</p></div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="step-card reveal delay-2"><div class="step-num">02</div><div class="step-ico">👮</div><div class="step-title">Officer Assignment</div><p class="step-desc">Your complaint is automatically routed to the relevant department. A field officer is assigned and you're notified instantly.</p></div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="step-card reveal delay-3"><div class="step-num">03</div><div class="step-ico">🔧</div><div class="step-title">On-Ground Action</div><p class="step-desc">The officer investigates, updates the status, and logs progress notes. You can track every update in real time from your dashboard.</p></div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="step-card reveal delay-4"><div class="step-num">04</div><div class="step-ico">🏆</div><div class="step-title">Resolution & Closure</div><p class="step-desc">Once resolved, the complaint is marked closed with a timestamp. You can rate the resolution quality and reopen if unsatisfied.</p></div>
        </div>
      </div>
    </div>
  </section>

  <!-- STATS BAR -->
  <div class="stats-bar-dark" id="stats-dark">
    <div class="container">
      <div class="row g-4 justify-content-center text-center">
        <div class="col-6 col-md-3 reveal delay-1"><div class="sb-item"><div class="sb-num stat-counter" data-target="12400" data-suffix="+">12,400+</div><div class="sb-lbl">Complaints Submitted</div></div></div>
        <div class="col-6 col-md-3 reveal delay-2"><div class="sb-item"><div class="sb-num stat-counter" data-target="94" data-suffix="%">94%</div><div class="sb-lbl">Citizen Satisfaction</div></div></div>
        <div class="col-6 col-md-3 reveal delay-3"><div class="sb-item"><div class="sb-num stat-counter" data-target="87" data-suffix="">87</div><div class="sb-lbl">Active Officers</div></div></div>
        <div class="col-6 col-md-3 reveal delay-4"><div class="sb-item"><div class="sb-num stat-counter" data-target="11" data-suffix="">11</div><div class="sb-lbl">Districts Covered</div></div></div>
      </div>
    </div>
  </div>

  <!-- VALUES -->
  <section class="py-5">
    <div class="container py-4">
      <div class="section-label reveal">Our Values</div>
      <div class="section-title reveal delay-1">What we stand for</div>
      <div class="section-sub mb-4 reveal delay-2">Every design decision, every feature, every policy in Nagrik Seva is guided by three core principles.</div>
      <div class="row g-3 mt-2">
        <div class="col-12 col-md-6 col-lg-4"><div class="val-card reveal delay-1"><div class="val-ico-wrap">🔍</div><div class="val-title">Radical Transparency</div><p class="val-desc">Every complaint, assignment, status change, and resolution is logged with timestamps. Citizens can see exactly what happened and when — no black boxes.</p></div></div>
        <div class="col-12 col-md-6 col-lg-4"><div class="val-card reveal delay-2"><div class="val-ico-wrap">⚖️</div><div class="val-title">Equal Access</div><p class="val-desc">Whether you're filing from a smartphone in Panaji or a basic feature phone in a rural taluka, our platform works for everyone. Accessibility is non-negotiable.</p></div></div>
        <div class="col-12 col-md-6 col-lg-4"><div class="val-card reveal delay-3"><div class="val-ico-wrap">🛡️</div><div class="val-title">Accountability First</div><p class="val-desc">Unresolved complaints automatically escalate to regulators. Officers have resolution SLAs. No complaint can be silently ignored or indefinitely deferred.</p></div></div>
        <div class="col-12 col-md-6 col-lg-4"><div class="val-card reveal delay-4"><div class="val-ico-wrap">🔒</div><div class="val-title">Data Privacy</div><p class="val-desc">Your personal information is never sold or shared. Complaint data is anonymized in public statistics. OTP-based login ensures only you access your account.</p></div></div>
        <div class="col-12 col-md-6 col-lg-4"><div class="val-card reveal delay-5"><div class="val-ico-wrap">🌱</div><div class="val-title">Civic Ownership</div><p class="val-desc">Goa's infrastructure belongs to its citizens. We believe every resident has not just the right, but the responsibility to hold their city accountable.</p></div></div>
        <div class="col-12 col-md-6 col-lg-4"><div class="val-card reveal delay-6"><div class="val-ico-wrap">🚀</div><div class="val-title">Continuous Improvement</div><p class="val-desc">We ship updates based on citizen feedback. Our roadmap is public. If there's a feature that would make the platform more useful, we want to hear it.</p></div></div>
      </div>
    </div>
  </section>

  <!-- TEAM -->
  <section class="py-5" style="background:var(--white)">
    <div class="container py-4">
      <div class="section-label reveal">The Team</div>
      <div class="section-title reveal delay-1">People behind Nagrik Seva</div>
      <div class="section-sub mb-4 reveal delay-2">A small, committed team of civic technologists, government liaisons, and UX designers working to make Goa's infrastructure better.</div>
      <div class="row g-3 mt-2">
        <div class="col-12 col-sm-6 col-lg-3"><div class="team-card reveal delay-1"><div class="team-av">👨‍💼</div><div class="team-name">Arjun Kamat</div><div class="team-role">Director & Co-founder</div><p class="team-bio">Former IAS officer with 14 years in Goa's urban development wing. Initiated the Nagrik Seva project in 2023.</p></div></div>
        <div class="col-12 col-sm-6 col-lg-3"><div class="team-card reveal delay-2"><div class="team-av">👩‍💻</div><div class="team-name">Priya Dessai</div><div class="team-role">Head of Technology</div><p class="team-bio">Full-stack engineer and open-source contributor. Previously led digital transformation at the Goa State IT Department.</p></div></div>
        <div class="col-12 col-sm-6 col-lg-3"><div class="team-card reveal delay-3"><div class="team-av">👨‍🎨</div><div class="team-name">Rohan Naik</div><div class="team-role">UX & Citizen Experience</div><p class="team-bio">Designs for clarity and speed. Spent two years conducting field research with rural citizens in Goa to understand real usability barriers.</p></div></div>
        <div class="col-12 col-sm-6 col-lg-3"><div class="team-card reveal delay-4"><div class="team-av">👩‍⚖️</div><div class="team-name">Sneha Borkar</div><div class="team-role">Government Relations</div><p class="team-bio">Coordinates with district collectors, municipal corporations, and PWD offices to ensure officer responsiveness and SLA compliance.</p></div></div>
      </div>
    </div>
  </section>

  <!-- JOURNEY / TIMELINE -->
  <section class="py-5">
    <div class="container py-4">
      <div class="row g-5 align-items-start">
        <div class="col-12 col-lg-5">
          <div class="section-label reveal">Our Journey</div>
          <div class="section-title reveal delay-1">From idea to<br>statewide platform</div>
          <p class="section-sub reveal delay-2">A two-year journey from a frustrated citizen's idea to Goa's primary civic grievance platform.</p>
        </div>
        <div class="col-12 col-lg-7">
          <div class="timeline">
            <div class="tl-item"><div class="tl-dot"></div><div class="tl-year">Jan 2023</div><div class="tl-title">Project Inception</div><p class="tl-desc">Arjun Kamat and Priya Dessai begin designing the system after a civic hackathon in Panaji.</p></div>
            <div class="tl-item"><div class="tl-dot"></div><div class="tl-year">Jun 2023</div><div class="tl-title">Pilot in North Goa</div><p class="tl-desc">First 200 users in Panaji ward. 47 complaints filed, 39 resolved within 7 days.</p></div>
            <div class="tl-item"><div class="tl-dot"></div><div class="tl-year">Dec 2023</div><div class="tl-title">Statewide Rollout</div><p class="tl-desc">All 11 districts onboarded. Government partnership formalised. 2,400 citizens registered in first month.</p></div>
            <div class="tl-item"><div class="tl-dot"></div><div class="tl-year">Mar 2024</div><div class="tl-title">10,000th Complaint Resolved</div><p class="tl-desc">Major milestone. Average resolution time dropped from 18 days to 4.2 days. Media recognition.</p></div>
            <div class="tl-item"><div class="tl-dot"></div><div class="tl-year">2025–26</div><div class="tl-title">Platform v2 & Expansion</div><p class="tl-desc">Mobile app launch, AI-based complaint routing, integration with municipal ERP systems.</p></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="py-5">
    <div class="container py-4">
      <div class="cta-card reveal-scale">
        <h2>Your city needs your voice</h2>
        <p>Join 18,000+ citizens already making Goa's infrastructure better. File your first complaint in under two minutes — no bureaucracy, just results.</p>
        <div class="cta-btns d-flex gap-3 justify-content-center flex-wrap">
          <a href="citizen_register.php"><button class="cta-pri">Create Free Account →</button></a>
          <a href="contact.php" class="cta-sec">Contact Us</a>
        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="container">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-2">
          <div class="footer-mark">🏛️</div>
          <div><div class="footer-name">Nagrik Seva</div><div class="footer-tag">Goa Civic Portal · 2026</div></div>
        </div>
        <div class="footer-links d-flex gap-4 flex-wrap">
          <a href="about.php">About</a><a href="contact.php">Contact</a><a href="public_board.php">Public Board</a><a href="citizen_login.php">Sign In</a><a href="citizen_register.php">Register</a>
        </div>
        <div class="footer-copy">© 2026 Government of Goa. All rights reserved.</div>
      </div>
    </div>
  </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* SCROLL PROGRESS */
window.addEventListener('scroll',function(){
  document.getElementById('scroll-progress').style.width=(document.documentElement.scrollTop/(document.documentElement.scrollHeight-window.innerHeight)*100)+'%';
  document.getElementById('topnav').classList.toggle('scrolled',window.scrollY>20);
});

/* INTERSECTION OBSERVER */
let countersTriggered=false;
const io=new IntersectionObserver(entries=>{
  entries.forEach(e=>{
    if(e.isIntersecting){
      e.target.classList.add('visible');
      if(e.target.id==='stats-dark'&&!countersTriggered){ countersTriggered=true; animateStats(); }
    }
  });
},{threshold:0.12,rootMargin:'0px 0px -40px 0px'});
document.querySelectorAll('.reveal,.reveal-scale,.tl-item').forEach(el=>io.observe(el));
document.getElementById('stats-dark')&&io.observe(document.getElementById('stats-dark'));

/* STAGGERED TIMELINE */
const tlObs=new IntersectionObserver(entries=>{
  entries.forEach((e,i)=>{
    if(e.isIntersecting){
      setTimeout(()=>e.target.classList.add('visible'),i*120);
    }
  });
},{threshold:0.1});
document.querySelectorAll('.tl-item').forEach(el=>tlObs.observe(el));

/* COUNTER ANIMATION */
function animateStats(){
  document.querySelectorAll('.stat-counter').forEach(el=>{
    const target=+el.dataset.target;
    const suffix=el.dataset.suffix||'';
    const dur=1400; const start=performance.now();
    function tick(now){
      const p=Math.min((now-start)/dur,1);
      const ease=1-Math.pow(1-p,3);
      el.textContent=Math.round(ease*target).toLocaleString()+suffix;
      if(p<1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  });
}

/* MISSION COUNTERS */
const missionObs=new IntersectionObserver(entries=>{
  entries.forEach(e=>{
    if(e.isIntersecting){
      e.target.querySelectorAll('.counter').forEach(el=>{
        const target=+el.dataset.target;
        const display=el.dataset.display;
        const dur=1200; const start=performance.now();
        function tick(now){
          const p=Math.min((now-start)/dur,1);
          const ease=1-Math.pow(1-p,3);
          const val=Math.round(ease*target);
          el.textContent=val>=1000?(val/1000).toFixed(1).replace(/\.0$/,'')+'K+':val+'+';
          if(p===1&&display) el.textContent=display;
          if(p<1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
      });
      missionObs.unobserve(e.target);
    }
  });
},{threshold:0.2});
const missionGrid=document.querySelector('.col-12.col-lg-6:last-child');
if(missionGrid) missionObs.observe(missionGrid);

/* USER DROPDOWN */
function toggleUserMenu(e){e.stopPropagation();const btn=document.getElementById('nav-user-btn');const dd=document.getElementById('nav-dropdown');const open=dd.classList.toggle('show');btn.classList.toggle('open',open);btn.setAttribute('aria-expanded',open);}
document.addEventListener('click',function(){const dd=document.getElementById('nav-dropdown');const btn=document.getElementById('nav-user-btn');if(dd)dd.classList.remove('show');if(btn){btn.classList.remove('open');btn.setAttribute('aria-expanded','false');}});

/* EASTER EGG */
(function(){const c='admin';let b='';document.addEventListener('keydown',function(e){if(['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName))return;b+=e.key.toLowerCase();b=b.slice(-c.length);if(b===c)window.location.href='admin.php';});})();
</script>
</body>
</html>
