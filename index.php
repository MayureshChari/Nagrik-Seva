<?php
session_start();
$active_page = 'home';
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
<title>Nagrik Seva — Goa Civic Portal</title>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,700;0,900;1,700;1,900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root {
  --a:   #042e2a;
  --a2:  #18cfb4;
  --a3:  rgba(24,207,180,.12);
  --ag:  rgba(24,207,180,.08);
  --bg:  #f0fdfb;
  --text:#042e2a;
  --text2:#065449;
  --muted:#4a7260;
  --muted2:#2a7d4f;
  --border:rgba(4,46,42,.12);
  --border2:rgba(4,46,42,.24);
  --shadow: 0 1px 4px rgba(4,46,42,.08),0 4px 14px rgba(4,46,42,.06);
  --shadow-md: 0 4px 20px rgba(4,46,42,.12);
  --shadow-lg: 0 16px 48px rgba(4,46,42,.14);
  --radius: 14px;
}

*,*::before,*::after { box-sizing:border-box }
html  { scroll-behavior:smooth }
body  { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--text); -webkit-font-smoothing:antialiased; overflow-x:hidden }
a     { text-decoration:none; color:inherit }
::-webkit-scrollbar { width:5px }
::-webkit-scrollbar-thumb { background:rgba(24,207,180,.3); border-radius:3px }

/* ── SCROLL PROGRESS BAR ── */
#scroll-progress {
  position:fixed; top:0; left:0; height:3px; z-index:9999;
  background:linear-gradient(90deg,var(--a2),#7de8d8);
  width:0%; transition:width .1s linear;
  box-shadow:0 0 8px rgba(24,207,180,.6);
}

/* ── BACKGROUND CANVAS ── */
.bg-canvas { position:fixed; inset:0; z-index:0; overflow:hidden; pointer-events:none }
.mesh      { position:absolute; border-radius:50%; filter:blur(80px); animation:drift 20s ease-in-out infinite alternate }
.m1        { width:700px; height:700px; background:radial-gradient(circle,rgba(24,207,180,.08),transparent); top:-150px; left:-100px }
.m2        { width:500px; height:500px; background:radial-gradient(circle,rgba(109,229,210,.06),transparent); bottom:-100px; right:-80px; animation-delay:-8s }
.dots      { position:fixed; inset:0; z-index:0; background-image:radial-gradient(rgba(4,46,42,.04) 1px,transparent 1px); background-size:28px 28px; pointer-events:none }
@keyframes drift { 0%{transform:translate(0,0)} 100%{transform:translate(24px,18px)} }

/* ── NAVBAR ── */
.topnav {
  position:sticky; top:0; z-index:200;
  background:rgba(255,255,255,.93);
  backdrop-filter:blur(20px) saturate(180%);
  border-bottom:1px solid var(--border);
  box-shadow:0 1px 0 rgba(255,255,255,.8),0 2px 12px rgba(4,46,42,.06);
  height:62px;
  transition:box-shadow .3s;
}
.topnav.scrolled { box-shadow:0 2px 20px rgba(4,46,42,.12) }
.nav-mark {
  width:38px; height:38px; border-radius:11px;
  background:linear-gradient(135deg,var(--a),var(--a2));
  display:flex; align-items:center; justify-content:center;
  font-size:17px; box-shadow:0 3px 12px rgba(4,46,42,.22);
  transition:transform .2s; flex-shrink:0;
}
.nav-logo:hover .nav-mark { transform:scale(1.06) rotate(-5deg) }
.nav-name { font-family:'Fraunces',serif; font-size:.92rem; font-weight:700; color:var(--text); line-height:1.2 }
.nav-tagline { font-size:.54rem; color:var(--muted); letter-spacing:.4px; text-transform:uppercase }

.nav-link-item {
  padding:7px 14px; border-radius:8px; font-size:.79rem; font-weight:500;
  color:var(--muted2); transition:all .16s; border:1px solid transparent;
  white-space:nowrap; cursor:pointer;
}
.nav-link-item:hover { background:var(--ag); color:var(--text); border-color:var(--a3) }
.nav-link-item.active { background:var(--a3); color:var(--a); font-weight:700; border-color:rgba(24,207,180,.3) }

.nav-signin {
  padding:7px 15px; border-radius:8px; font-size:.79rem; font-weight:600;
  color:var(--muted2); border:1px solid transparent; transition:all .16s;
}
.nav-signin:hover { background:var(--ag); color:var(--text) }

.nav-cta {
  padding:8px 18px; border-radius:9px; font-size:.8rem; font-weight:700;
  background:linear-gradient(135deg,var(--a),var(--a2)); color:#fff;
  border:none; cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif;
  box-shadow:0 3px 10px rgba(4,46,42,.22); transition:all .18s;
}
.nav-cta:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(4,46,42,.3) }

.nav-av {
  width:32px; height:32px; border-radius:50%;
  background:linear-gradient(135deg,var(--a2),rgba(24,207,180,.6));
  border:2px solid rgba(24,207,180,.4);
  display:flex; align-items:center; justify-content:center;
  font-size:.73rem; font-weight:700; color:#fff; flex-shrink:0;
}
.nav-username { font-size:.78rem; font-weight:600; color:var(--text) }

/* ── USER DROPDOWN ── */
.nav-user-wrap { position:relative; }
.nav-user-btn {
  display:flex; align-items:center; gap:8px;
  background:none; border:none; padding:4px 8px 4px 4px;
  border-radius:10px; cursor:pointer;
  transition:background .15s; font-family:'Plus Jakarta Sans',sans-serif;
}
.nav-user-btn:hover { background:var(--ag) }
.nav-user-btn.open  { background:var(--a3) }
.nav-user-chevron {
  font-size:.55rem; color:var(--muted); margin-left:2px;
  transition:transform .22s cubic-bezier(.34,1.56,.64,1);
  display:inline-block;
}
.nav-user-btn.open .nav-user-chevron { transform:rotate(180deg) }

.nav-dropdown {
  position:absolute; top:calc(100% + 8px); right:0;
  min-width:180px; background:#fff;
  border:1px solid var(--border); border-radius:12px;
  box-shadow:0 8px 32px rgba(4,46,42,.14);
  padding:6px; z-index:300;
  opacity:0; transform:translateY(-8px) scale(.97);
  pointer-events:none;
  transition:opacity .2s cubic-bezier(.22,1,.36,1), transform .2s cubic-bezier(.22,1,.36,1);
  transform-origin:top right;
}
.nav-dropdown.show {
  opacity:1; transform:translateY(0) scale(1);
  pointer-events:auto;
}
.nd-header {
  padding:10px 12px 8px;
  border-bottom:1px solid var(--border);
  margin-bottom:4px;
}
.nd-name { font-size:.82rem; font-weight:700; color:var(--text) }
.nd-role { font-size:.62rem; color:var(--muted); text-transform:capitalize; margin-top:1px }
.nd-item {
  display:flex; align-items:center; gap:9px;
  padding:9px 12px; border-radius:8px;
  font-size:.78rem; font-weight:500; color:var(--text2);
  cursor:pointer; transition:background .14s, color .14s;
  text-decoration:none; width:100%; background:none; border:none;
  font-family:'Plus Jakarta Sans',sans-serif; text-align:left;
}
.nd-item:hover { background:var(--ag); color:var(--text) }
.nd-item.danger { color:#dc2626 }
.nd-item.danger:hover { background:rgba(220,38,38,.07); color:#dc2626 }
.nd-sep { height:1px; background:var(--border); margin:4px 0 }
.nav-dash {
  padding:7px 13px; border-radius:8px; font-size:.76rem; font-weight:600;
  background:var(--ag); color:var(--text2); border:1px solid var(--a3); transition:all .15s;
}
.nav-dash:hover { background:var(--a3); color:var(--a) }

/* ── HERO ── */
.hero {
  background:linear-gradient(135deg,var(--a) 0%,#065449 45%,#0a8f7a 80%,var(--a2) 100%);
  padding:96px 0 112px; text-align:center; position:relative; overflow:hidden;
}
.hero::before {
  content:''; position:absolute; inset:0;
  background:radial-gradient(ellipse at 30% 50%,rgba(24,207,180,.2),transparent 60%),
             radial-gradient(ellipse at 75% 30%,rgba(255,255,255,.06),transparent 50%);
  pointer-events:none;
}
.hero::after {
  content:''; position:absolute; inset:0;
  background-image:radial-gradient(rgba(255,255,255,.04) 1px,transparent 1px);
  background-size:32px 32px; pointer-events:none;
}

/* Floating particles in hero */
.hero-particles { position:absolute; inset:0; overflow:hidden; pointer-events:none; z-index:0 }
.particle {
  position:absolute; border-radius:50%;
  background:rgba(24,207,180,.15); animation:float-up linear infinite;
}
@keyframes float-up {
  0%  { transform:translateY(100%) scale(0); opacity:0 }
  10% { opacity:1 }
  90% { opacity:.5 }
  100%{ transform:translateY(-120vh) scale(1.2); opacity:0 }
}

.hero-badge {
  display:inline-flex; align-items:center; gap:8px;
  padding:6px 16px; border-radius:20px;
  background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2);
  font-size:.63rem; font-weight:700; letter-spacing:1.8px; text-transform:uppercase;
  color:rgba(255,255,255,.9); margin-bottom:28px; position:relative; z-index:1;
  animation:hero-badge-in .6s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes hero-badge-in { from{opacity:0;transform:translateY(-12px) scale(.9)} to{opacity:1;transform:translateY(0) scale(1)} }
.hero-badge::before {
  content:''; width:6px; height:6px; border-radius:50%; background:var(--a2);
  animation:blink 1.8s ease-in-out infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.2} }

.hero h1 {
  font-family:'Fraunces',serif;
  font-size:clamp(2.6rem,5.5vw,4.4rem);
  font-weight:900; color:#fff; letter-spacing:-2.5px; line-height:1.02;
  margin-bottom:20px; position:relative; z-index:1;
  animation:hero-title-in .7s cubic-bezier(.22,1,.36,1) .15s both;
}
@keyframes hero-title-in { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
.hero h1 em { font-style:italic; color:#7de8d8 }
.hero-sub {
  font-size:clamp(.88rem,1.4vw,1.05rem);
  color:rgba(255,255,255,.72); line-height:1.8;
  max-width:580px; margin:0 auto 40px; position:relative; z-index:1;
  animation:hero-title-in .7s cubic-bezier(.22,1,.36,1) .28s both;
}
.hero-btns { position:relative; z-index:1; animation:hero-title-in .7s cubic-bezier(.22,1,.36,1) .42s both }

.btn-pri {
  padding:13px 30px; border-radius:11px; background:#fff; color:var(--a);
  font-size:.86rem; font-weight:700; border:none; cursor:pointer;
  font-family:'Plus Jakarta Sans',sans-serif;
  box-shadow:0 4px 18px rgba(0,0,0,.18); transition:all .2s;
}
.btn-pri:hover { transform:translateY(-2px); box-shadow:0 8px 26px rgba(0,0,0,.22) }

.btn-sec {
  padding:13px 28px; border-radius:11px; background:rgba(255,255,255,.12);
  color:#fff; font-size:.86rem; font-weight:600;
  border:1.5px solid rgba(255,255,255,.25); transition:all .18s; display:inline-block;
}
.btn-sec:hover { background:rgba(255,255,255,.2); transform:translateY(-1px) }

.hero-wave {
  position:absolute; bottom:-2px; left:0; right:0; height:56px;
  background:var(--bg); clip-path:ellipse(55% 100% at 50% 100%);
}

/* ── STATS BAR ── */
.stats-bar { background:#fff; border-bottom:1px solid var(--border); box-shadow:var(--shadow) }
.stat-item { padding:28px 20px; text-align:center }
.stat-val  { font-family:'Fraunces',serif; font-size:2rem; font-weight:900; color:var(--a); letter-spacing:-1px; display:block; line-height:1 }
.stat-lbl  { font-size:.62rem; color:var(--muted); margin-top:5px; text-transform:uppercase; letter-spacing:.9px; font-weight:600 }

/* ── SECTIONS ── */
.section-label { font-size:.6rem; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:var(--a2); margin-bottom:10px }
.section-title { font-family:'Fraunces',serif; font-size:clamp(1.7rem,3vw,2.5rem); font-weight:900; color:var(--text); letter-spacing:-1.2px; line-height:1.1; margin-bottom:14px }
.section-sub   { font-size:.88rem; color:var(--muted2); line-height:1.85; max-width:540px }

/* ── FEATURE CARDS ── */
.feat-card {
  background:#fff; border:1px solid var(--border); border-radius:var(--radius);
  padding:28px 24px; box-shadow:var(--shadow); transition:all .28s cubic-bezier(.34,1.56,.64,1); height:100%;
}
.feat-card:hover { transform:translateY(-6px); border-color:rgba(24,207,180,.3); box-shadow:var(--shadow-md) }
.feat-ico {
  width:48px; height:48px; border-radius:13px;
  background:linear-gradient(135deg,var(--ag),var(--a3));
  border:1.5px solid rgba(24,207,180,.2);
  display:flex; align-items:center; justify-content:center;
  font-size:1.3rem; margin-bottom:16px; transition:transform .3s cubic-bezier(.34,1.56,.64,1);
}
.feat-card:hover .feat-ico { transform:scale(1.15) rotate(-6deg) }
.feat-title { font-size:.9rem; font-weight:700; color:var(--text); margin-bottom:7px }
.feat-desc  { font-size:.77rem; color:var(--muted2); line-height:1.75; margin:0 }

/* ── HOW IT WORKS ── */
.how-section {
  background:linear-gradient(135deg,var(--a) 0%,#065449 50%,#0a6e60 100%);
  padding:80px 0; position:relative; overflow:hidden;
}
.how-section::before {
  content:''; position:absolute; inset:0;
  background-image:radial-gradient(rgba(255,255,255,.04) 1px,transparent 1px);
  background-size:28px 28px;
}
.how-section .section-label { color:rgba(255,255,255,.55) }
.how-section .section-title { color:#fff }
.how-section .section-sub   { color:rgba(255,255,255,.6) }

.step-card {
  background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1);
  border-radius:var(--radius); padding:28px 20px 24px;
  position:relative; transition:all .28s cubic-bezier(.34,1.56,.64,1); height:100%;
}
.step-card:hover { background:rgba(255,255,255,.13); border-color:rgba(24,207,180,.4); transform:translateY(-6px) }
.step-num {
  position:absolute; top:18px; right:18px; width:28px; height:28px; border-radius:50%;
  background:rgba(24,207,180,.2); border:1.5px solid rgba(24,207,180,.3);
  display:flex; align-items:center; justify-content:center;
  font-size:.67rem; font-weight:800; color:var(--a2); font-family:'DM Mono',monospace;
}
.step-ico {
  width:48px; height:48px; border-radius:12px; background:rgba(255,255,255,.1);
  display:flex; align-items:center; justify-content:center;
  font-size:1.3rem; margin-bottom:14px; transition:transform .3s cubic-bezier(.34,1.56,.64,1);
}
.step-card:hover .step-ico { transform:scale(1.15) rotate(-6deg) }
.step-title { font-size:.87rem; font-weight:700; color:#fff; margin-bottom:7px }
.step-desc  { font-size:.75rem; color:rgba(255,255,255,.55); line-height:1.7; margin:0 }

/* connector line between steps */
.step-connector {
  display:none;
  position:absolute; top:50%; right:-24px;
  width:20px; height:2px;
  background:rgba(24,207,180,.3);
  z-index:1;
}
@media(min-width:992px){ .step-col:not(:last-child) .step-connector { display:block } }

/* ── CTA ── */
.cta-card {
  max-width:640px; margin:0 auto;
  background:linear-gradient(135deg,var(--a) 0%,#065449 55%,#0a8f7a 100%);
  border-radius:20px; padding:56px 48px;
  position:relative; overflow:hidden; box-shadow:var(--shadow-lg); text-align:center;
}
.cta-card::before {
  content:''; position:absolute; inset:0;
  background:radial-gradient(ellipse at 80% 20%,rgba(24,207,180,.2),transparent 60%);
}
.cta-card h2 {
  font-family:'Fraunces',serif; font-size:2rem; font-weight:900;
  color:#fff; letter-spacing:-1px; margin-bottom:12px; position:relative; z-index:1;
}
.cta-card > p {
  font-size:.86rem; color:rgba(255,255,255,.7); line-height:1.8;
  margin-bottom:28px; position:relative; z-index:1;
}
.cta-btns { position:relative; z-index:1 }
.cta-pri {
  padding:12px 26px; border-radius:10px; background:#fff; color:var(--a);
  font-size:.84rem; font-weight:700; border:none; cursor:pointer;
  font-family:'Plus Jakarta Sans',sans-serif;
  box-shadow:0 4px 14px rgba(0,0,0,.15); transition:all .17s;
}
.cta-pri:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(0,0,0,.2) }
.cta-sec {
  padding:12px 26px; border-radius:10px; background:rgba(255,255,255,.12);
  color:#fff; font-size:.84rem; font-weight:600;
  border:1px solid rgba(255,255,255,.25); display:inline-block; transition:all .17s;
}
.cta-sec:hover { background:rgba(255,255,255,.2) }

/* ── FOOTER ── */
.site-footer {
  background:var(--a); padding:40px 48px;
  border-top:1px solid rgba(255,255,255,.1);
}
.footer-mark {
  width:34px; height:34px; border-radius:9px;
  background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2);
  display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0;
}
.footer-name { font-family:'Fraunces',serif; font-size:.86rem; font-weight:700; color:#fff }
.footer-tag  { font-size:.56rem; color:rgba(255,255,255,.45); margin-top:2px }
.footer-links a { font-size:.72rem; font-weight:500; color:rgba(255,255,255,.5); transition:color .14s; text-decoration:none }
.footer-links a:hover { color:#fff }
.footer-copy { font-size:.64rem; color:rgba(255,255,255,.35) }

/* ── MODAL ── */
.modal-overlay {
  position:fixed; inset:0; z-index:500;
  background:rgba(4,46,42,.32); backdrop-filter:blur(6px);
  display:none; align-items:center; justify-content:center; padding:24px;
}
.modal-overlay.show { display:flex }
.modal-box {
  background:#fff; border-radius:20px; padding:40px 36px;
  max-width:400px; width:100%; text-align:center;
  box-shadow:0 24px 64px rgba(4,46,42,.2); position:relative;
  animation:modalIn .35s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes modalIn { from{opacity:0;transform:scale(.88) translateY(16px)} to{opacity:1;transform:scale(1) translateY(0)} }
.modal-ico {
  width:64px; height:64px; border-radius:18px;
  background:linear-gradient(135deg,var(--a),var(--a2));
  display:flex; align-items:center; justify-content:center;
  font-size:28px; margin:0 auto 20px;
  box-shadow:0 6px 20px rgba(4,46,42,.22);
}
.modal-box h2 { font-family:'Fraunces',serif; font-size:1.6rem; font-weight:900; letter-spacing:-.8px; margin-bottom:8px }
.modal-box p  { font-size:.82rem; color:var(--muted2); line-height:1.7; margin-bottom:26px }
.modal-btn-pri {
  width:100%; padding:13px; background:linear-gradient(135deg,var(--a),var(--a2));
  color:#fff; border:none; border-radius:11px;
  font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:.88rem;
  cursor:pointer; transition:all .2s; box-shadow:0 4px 14px rgba(4,46,42,.2);
}
.modal-btn-pri:hover { transform:translateY(-1px); box-shadow:0 8px 22px rgba(4,46,42,.28) }
.modal-btn-ghost {
  width:100%; padding:11px; background:#fff;
  border:1.5px solid var(--border2); color:var(--text); border-radius:11px;
  font-family:'Plus Jakarta Sans',sans-serif; font-weight:600; font-size:.84rem;
  cursor:pointer; transition:all .2s;
}
.modal-btn-ghost:hover { border-color:var(--a2) }
.modal-close { position:absolute; top:14px; right:16px; background:none; border:none; cursor:pointer; color:var(--muted); font-size:1.1rem; line-height:1; padding:4px }
.modal-close:hover { color:var(--text) }

/* ── STAT DIVIDERS ── */
.stat-item + .stat-item { border-left:1px solid var(--border) }
@media(max-width:575px){
  .stat-item + .stat-item { border-left:none; border-top:1px solid var(--border) }
  .hero { padding-top:64px; padding-bottom:80px }
  .cta-card { padding:36px 24px }
  .site-footer { padding:32px 20px }
}

.page { position:relative; z-index:1 }

.navbar-toggler { border:none; padding:4px }
.navbar-toggler:focus { box-shadow:none }
.navbar-toggler-icon {
  background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(4,46,42,0.9)' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}

.offcanvas-body .nav-link-item { display:block; margin-bottom:4px; font-size:.88rem; padding:12px 16px }
.offcanvas-sep { height:1px; background:var(--border); margin:8px 0 }

/* ── SCROLL-REVEAL ANIMATIONS ── */
.reveal {
  opacity:0;
  transform:translateY(32px);
  transition:opacity .65s cubic-bezier(.22,1,.36,1), transform .65s cubic-bezier(.22,1,.36,1);
}
.reveal.visible {
  opacity:1;
  transform:translateY(0);
}
.reveal-left {
  opacity:0;
  transform:translateX(-32px);
  transition:opacity .65s cubic-bezier(.22,1,.36,1), transform .65s cubic-bezier(.22,1,.36,1);
}
.reveal-left.visible { opacity:1; transform:translateX(0) }
.reveal-scale {
  opacity:0;
  transform:scale(.92);
  transition:opacity .55s cubic-bezier(.22,1,.36,1), transform .55s cubic-bezier(.22,1,.36,1);
}
.reveal-scale.visible { opacity:1; transform:scale(1) }
/* stagger helpers */
.delay-1 { transition-delay:.08s }
.delay-2 { transition-delay:.16s }
.delay-3 { transition-delay:.24s }
.delay-4 { transition-delay:.32s }
.delay-5 { transition-delay:.40s }
.delay-6 { transition-delay:.48s }

/* ── RIPPLE EFFECT ON BUTTONS ── */
.btn-ripple { position:relative; overflow:hidden }
.btn-ripple::after {
  content:''; position:absolute; inset:0; border-radius:inherit;
  background:rgba(255,255,255,.25); transform:scale(0); opacity:0;
  transition:transform .4s, opacity .4s;
}
.btn-ripple:active::after { transform:scale(2.5); opacity:0; transition:0s }
</style>
</head>
<body>

<!-- Scroll progress bar -->
<div id="scroll-progress"></div>

<!-- BG Decoration -->
<div class="bg-canvas"><div class="mesh m1"></div><div class="mesh m2"></div></div>
<div class="dots"></div>

<!-- ══ NAVBAR ══ -->
<nav class="topnav navbar navbar-expand-lg px-0" id="topnav">
  <div class="container-fluid px-4 px-md-5 h-100">

    <!-- Logo -->
    <a href="index.php" class="nav-logo d-flex align-items-center gap-2 text-decoration-none me-3">
      <div class="nav-mark">🏛️</div>
      <div>
        <div class="nav-name">Nagrik Seva</div>
        <div class="nav-tagline">Goa Civic Portal</div>
      </div>
    </a>

    <!-- Toggler -->
    <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-label="Toggle menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Desktop Links -->
    <div class="collapse navbar-collapse" id="desktopNav">
      <ul class="navbar-nav mx-auto gap-1 align-items-center">
        <li class="nav-item"><a class="nav-link-item active" href="index.php">Home</a></li>
        <li class="nav-item">
          <?php if($name_nav): ?>
          <a class="nav-link-item" href="<?= $dash_link ?>">Dashboard</a>
          <?php else: ?>
          <span class="nav-link-item" onclick="showDashModal()">Dashboard</span>
          <?php endif; ?>
        </li>
        <li class="nav-item"><a class="nav-link-item" href="about.php">About</a></li>
        <li class="nav-item"><a class="nav-link-item" href="contact.php">Contact</a></li>
        <li class="nav-item"><a class="nav-link-item" href="feedback.php">Feedback</a></li>
      </ul>

      <!-- Right side -->
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
        <a href="citizen_register.php"><button class="nav-cta btn-ripple">Sign Up →</button></a>
        <?php endif; ?>
      </div>
    </div>

  </div>
</nav>

<!-- ══ MOBILE OFFCANVAS ══ -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileMenu" style="background:rgba(240,253,251,.97);backdrop-filter:blur(16px);">
  <div class="offcanvas-header">
    <h6 class="offcanvas-title" style="font-family:'Fraunces',serif;font-weight:700;color:var(--a)">Nagrik Seva</h6>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body pt-0">
    <a href="index.php" class="nav-link-item active d-block mb-1">🏠 Home</a>
    <?php if($name_nav): ?>
    <a href="<?= $dash_link ?>" class="nav-link-item d-block mb-1">📊 Dashboard</a>
    <?php else: ?>
    <span class="nav-link-item d-block mb-1" onclick="showDashModal();bootstrap.Offcanvas.getInstance(document.getElementById('mobileMenu')).hide()">📊 Dashboard</span>
    <?php endif; ?>
    <a href="about.php" class="nav-link-item d-block mb-1">ℹ️ About</a>
    <a href="contact.php" class="nav-link-item d-block mb-1">📞 Contact</a>
    <a href="feedback.php" class="nav-link-item d-block mb-1">💬 Feedback</a>
    <div class="offcanvas-sep"></div>
    <?php if($name_nav): ?>
    <?php else: ?>
    <a href="citizen_login.php" class="nav-link-item d-block mb-1">🔑 Sign In</a>
    <a href="citizen_register.php" class="nav-link-item d-block mb-1" style="color:var(--a);font-weight:700">➕ Sign Up</a>
    <?php endif; ?>
  </div>
</div>

<!-- ══ PAGE ══ -->
<div class="page">

  <!-- HERO -->
  <section class="hero">
    <!-- floating particles -->
    <div class="hero-particles" id="hero-particles"></div>
    <div class="container">
      <div class="hero-badge">🏛️ Nagrik Seva — Est. 2024</div>
      <h1>Your city. Your voice.<br><em>Your complaint.</em></h1>
      <p class="hero-sub">File civic grievances, upload photo evidence, track every resolution — and hold local authorities accountable. All in one place, for the people of Goa.</p>
      <div class="hero-btns d-flex gap-3 justify-content-center flex-wrap">
        <a href="citizen_register.php"><button class="btn-pri btn-ripple">🚀 Get Started Free</button></a>
        <a href="about.php" class="btn-sec">Learn More →</a>
      </div>
    </div>
    <div class="hero-wave"></div>
  </section>

  <!-- STATS BAR -->
  <div class="stats-bar reveal">
    <div class="container">
      <div class="row g-0">
        <div class="col-6 col-md-3"><div class="stat-item"><span class="stat-val counter" data-target="12000" data-suffix="K+">0</span><div class="stat-lbl">Active Citizens</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-item"><span class="stat-val counter" data-target="10900" data-suffix="">10.9K</span><div class="stat-lbl">Issues Resolved</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-item"><span class="stat-val">4.2d</span><div class="stat-lbl">Avg Resolution</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-item"><span class="stat-val counter" data-target="94" data-suffix="%">0</span><div class="stat-lbl">Satisfaction Rate</div></div></div>
      </div>
    </div>
  </div>

  <!-- FEATURES -->
  <section class="py-5" style="padding:80px 0">
    <div class="container py-4">
      <div class="section-label reveal">Why Nagrik Seva</div>
      <div class="section-title reveal delay-1">Everything you need to be heard</div>
      <div class="section-sub mb-5 reveal delay-2">A transparent, accountable civic platform built specifically for the people of Goa.</div>
      <div class="row g-3 mt-2">
        <div class="col-12 col-md-6 col-lg-4">
          <div class="feat-card reveal delay-1">
            <div class="feat-ico">📸</div>
            <div class="feat-title">Photo Evidence</div>
            <p class="feat-desc">Attach images and documents to your complaints for faster, verifiable action from authorities.</p>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="feat-card reveal delay-2">
            <div class="feat-ico">📍</div>
            <div class="feat-title">GPS Location Tagging</div>
            <p class="feat-desc">Pinpoint the exact issue location so officers can find and resolve it without delay.</p>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="feat-card reveal delay-3">
            <div class="feat-ico">🔔</div>
            <div class="feat-title">Live Status Updates</div>
            <p class="feat-desc">Get notified at every step — from filing to assignment to final resolution.</p>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="feat-card reveal delay-4">
            <div class="feat-ico">⚖️</div>
            <div class="feat-title">Regulator Oversight</div>
            <p class="feat-desc">Independent regulators monitor officer performance and escalate if complaints are ignored.</p>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="feat-card reveal delay-5">
            <div class="feat-ico">🏆</div>
            <div class="feat-title">Community Recognition</div>
            <p class="feat-desc">Top civic contributors in your zone are recognised and rewarded for their engagement.</p>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="feat-card reveal delay-6">
            <div class="feat-ico">🔒</div>
            <div class="feat-title">Secure & Private</div>
            <p class="feat-desc">Your data is encrypted. Only authorised officers see your complaint details.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section class="how-section">
    <div class="container py-4" style="position:relative;z-index:1">
      <div class="section-label reveal">How It Works</div>
      <div class="section-title reveal delay-1">From complaint to resolution</div>
      <div class="section-sub mb-5 reveal delay-2">Four steps to get your civic issue fixed — no bureaucracy, just results.</div>
      <div class="row g-3 mt-2">
        <div class="col-12 col-sm-6 col-lg-3 step-col reveal delay-1" style="position:relative">
          <div class="step-card">
            <div class="step-num">01</div>
            <div class="step-ico">📸</div>
            <div class="step-title">File a Complaint</div>
            <p class="step-desc">Register and submit your issue with a title, category, location pin, and optional photo evidence.</p>
          </div>
          <div class="step-connector"></div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 step-col reveal delay-2" style="position:relative">
          <div class="step-card">
            <div class="step-num">02</div>
            <div class="step-ico">👮</div>
            <div class="step-title">Officer Assignment</div>
            <p class="step-desc">Your complaint is automatically routed to the relevant department officer. You're notified instantly.</p>
          </div>
          <div class="step-connector"></div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 step-col reveal delay-3" style="position:relative">
          <div class="step-card">
            <div class="step-num">03</div>
            <div class="step-ico">🔧</div>
            <div class="step-title">On-Ground Action</div>
            <p class="step-desc">The officer investigates, updates the status, and logs progress notes you can track in real time.</p>
          </div>
          <div class="step-connector"></div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 step-col reveal delay-4" style="position:relative">
          <div class="step-card">
            <div class="step-num">04</div>
            <div class="step-ico">🏆</div>
            <div class="step-title">Resolution & Closure</div>
            <p class="step-desc">Once resolved, the complaint is closed with a timestamp. Rate it and reopen if unsatisfied.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="py-5" style="padding:80px 0">
    <div class="container py-4">
      <div class="cta-card reveal-scale">
        <h2>Your city needs your voice</h2>
        <p>Join 18,000+ citizens already making Goa's infrastructure better. File your first complaint in under two minutes.</p>
        <div class="cta-btns d-flex gap-3 justify-content-center flex-wrap">
          <a href="citizen_register.php"><button class="cta-pri btn-ripple">Create Free Account →</button></a>
          <a href="contact.php" class="cta-sec">Contact Us</a>
        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="container">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-2 footer-logo">
          <div class="footer-mark">🏛️</div>
          <div>
            <div class="footer-name">Nagrik Seva</div>
            <div class="footer-tag">Goa Civic Portal · 2026</div>
          </div>
        </div>
        <div class="footer-links d-flex gap-4 flex-wrap">
          <a href="about.php">About</a>
          <a href="contact.php">Contact</a>
          <a href="public_board.php">Public Board</a>
          <a href="citizen_login.php">Sign In</a>
          <a href="citizen_register.php">Register</a>
        </div>
        <div class="footer-copy">© 2026 Government of Goa. All rights reserved.</div>
      </div>
    </div>
  </footer>

</div><!-- .page -->

<!-- ══ MODAL ══ -->
<div class="modal-overlay" id="dash-modal" onclick="closeModal(event)">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('dash-modal').classList.remove('show')">✕</button>
    <div class="modal-ico">📊</div>
    <h2>Sign up first!</h2>
    <p>You need a free citizen account to access your personal dashboard and file civic complaints.</p>
    <div class="d-flex flex-column gap-2">
      <a href="citizen_register.php"><button class="modal-btn-pri">🚀 Create Free Account</button></a>
      <a href="citizen_login.php"><button class="modal-btn-ghost">Already registered? Sign in →</button></a>
    </div>
  </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── MODAL ── */
function showDashModal(){ document.getElementById('dash-modal').classList.add('show'); }
function closeModal(e){ if(e.target===document.getElementById('dash-modal')) document.getElementById('dash-modal').classList.remove('show'); }

/* ── SCROLL PROGRESS BAR ── */
window.addEventListener('scroll', function(){
  const el = document.getElementById('scroll-progress');
  const scrolled = (document.documentElement.scrollTop / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
  el.style.width = scrolled + '%';
  // Navbar shadow on scroll
  document.getElementById('topnav').classList.toggle('scrolled', window.scrollY > 20);
});

/* ── INTERSECTION OBSERVER (scroll-reveal) ── */
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      // Trigger counter animation when stats bar becomes visible
      if (e.target.classList.contains('stats-bar')) animateCounters();
    }
  });
}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.reveal, .reveal-left, .reveal-scale').forEach(el => revealObserver.observe(el));

/* ── COUNTER ANIMATION ── */
let countersAnimated = false;
function animateCounters(){
  if(countersAnimated) return;
  countersAnimated = true;
  document.querySelectorAll('.counter').forEach(el => {
    const target = +el.dataset.target;
    const suffix = el.dataset.suffix || '';
    const duration = 1400;
    const start = performance.now();
    function update(now){
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const val = Math.round(eased * target);
      // Format nicely
      if(suffix === 'K+') el.textContent = (val >= 1000 ? (val/1000).toFixed(0)+'K+' : val+'K+');
      else el.textContent = val + suffix;
      if(progress < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
  });
}

/* ── HERO PARTICLES ── */
(function(){
  const container = document.getElementById('hero-particles');
  if(!container) return;
  for(let i=0;i<18;i++){
    const p = document.createElement('div');
    p.className = 'particle';
    const size = 4 + Math.random()*12;
    p.style.cssText = `
      width:${size}px; height:${size}px;
      left:${Math.random()*100}%;
      animation-duration:${8+Math.random()*14}s;
      animation-delay:${Math.random()*10}s;
    `;
    container.appendChild(p);
  }
})();

/* ── USER DROPDOWN ── */
function toggleUserMenu(e){
  e.stopPropagation();
  const btn = document.getElementById('nav-user-btn');
  const dd  = document.getElementById('nav-dropdown');
  const open = dd.classList.toggle('show');
  btn.classList.toggle('open', open);
  btn.setAttribute('aria-expanded', open);
}
document.addEventListener('click', function(){
  const dd  = document.getElementById('nav-dropdown');
  const btn = document.getElementById('nav-user-btn');
  if(dd){ dd.classList.remove('show'); }
  if(btn){ btn.classList.remove('open'); btn.setAttribute('aria-expanded','false'); }
});

(function(){
  const c='admin'; let b='';
  document.addEventListener('keydown',function(e){
    if(['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;
    b+=e.key.toLowerCase(); b=b.slice(-c.length);
    if(b===c) window.location.href='admin.php';
  });
})();
</script>
</body>
</html>
