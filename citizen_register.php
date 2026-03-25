<?php
session_start();
require_once 'config.php';

if (!empty($_SESSION['user_id']) && $_SESSION['role']==='citizen') { header('Location: citizen_dashboard.php'); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $first=$conn->real_escape_string(trim($_POST['first_name']??'')); $last=$conn->real_escape_string(trim($_POST['last_name']??''));
    $email=$conn->real_escape_string(strtolower(trim($_POST['email']??''))); $phone=$conn->real_escape_string(trim($_POST['phone']??''));
    $dob=$conn->real_escape_string(trim($_POST['dob']??'')); $pw=$_POST['password']??''; $cpw=$_POST['confirm_password']??'';
    if ($pw!==$cpw) $message='<div class="alert a-err"><span class="alert-icon">⚠</span> Passwords do not match.</div>';
    elseif (strlen($pw)<8) $message='<div class="alert a-err"><span class="alert-icon">⚠</span> Password must be at least 8 characters.</div>';
    else {
        $chk=$conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1"); $chk->bind_param('s',$email); $chk->execute();
        if ($chk->get_result()->num_rows>0) $message='<div class="alert a-err"><span class="alert-icon">⚠</span> Email already registered. <a href="citizen_login.php">Sign in →</a></div>';
        else {
            $hash=password_hash($pw,PASSWORD_DEFAULT); $name=$first.' '.$last;
            $ins=$conn->prepare("INSERT INTO users(name,email,phone,password_hash,role) VALUES(?,?,?,?,'citizen')");
            $ins->bind_param('ssss',$name,$email,$phone,$hash);
            if ($ins->execute()) { header('Location: citizen_login.php?registered=1'); exit; }
            else $message='<div class="alert a-err"><span class="alert-icon">⚠</span> Registration failed. Please try again.</div>';
            $ins->close();
        }
        $chk->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Account — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;0,900;1,400;1,700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --ink:       #0d1f1c;
  --ink2:      #1e3b36;
  --teal:      #0d9e88;
  --teal-l:    #16c9af;
  --teal-glow: rgba(13,158,136,.18);
  --sage:      #6aab9c;
  --cream:     #f5f9f8;
  --cream2:    #eaf3f1;
  --white:     #ffffff;
  --border:    rgba(13,31,28,.12);
  --border2:   rgba(13,31,28,.22);
  --muted:     #5a7972;
  --red:       #c0392b;
  --red-bg:    rgba(192,57,43,.07);
  --green:     #0a8a6a;
  --green-bg:  rgba(10,138,106,.07);
  --radius:    14px;
  --radius-sm: 9px;
  --shadow-sm: 0 1px 3px rgba(13,31,28,.08), 0 4px 16px rgba(13,31,28,.06);
  --shadow-md: 0 4px 24px rgba(13,31,28,.12), 0 1px 4px rgba(13,31,28,.08);
  --shadow-lg: 0 12px 48px rgba(13,31,28,.16), 0 2px 8px rgba(13,31,28,.1);
  --trans:     all .22s cubic-bezier(.4,0,.2,1);
}

html { scroll-behavior: smooth; }

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--cream);
  color: var(--ink);
  min-height: 100vh;
  -webkit-font-smoothing: antialiased;
  display: flex;
  flex-direction: column;
}

a { text-decoration: none; color: inherit; }

/* ── BACKGROUND ─────────────────────────────────── */
.bg-wrap {
  position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden;
}
.bg-blob {
  position: absolute; border-radius: 50%; filter: blur(80px); will-change: transform;
}
.bb1 {
  width: 560px; height: 560px;
  background: radial-gradient(circle, rgba(13,158,136,.14), transparent 70%);
  top: -180px; left: -100px;
  animation: floatA 20s ease-in-out infinite alternate;
}
.bb2 {
  width: 420px; height: 420px;
  background: radial-gradient(circle, rgba(22,201,175,.1), transparent 70%);
  bottom: -100px; right: -80px;
  animation: floatB 25s ease-in-out infinite alternate;
}
.bb3 {
  width: 280px; height: 280px;
  background: radial-gradient(circle, rgba(106,171,156,.12), transparent 70%);
  top: 50%; left: 40%;
  animation: floatA 18s ease-in-out infinite alternate-reverse;
}
@keyframes floatA { 0% { transform: translate(0,0); } 100% { transform: translate(24px,18px); } }
@keyframes floatB { 0% { transform: translate(0,0); } 100% { transform: translate(-18px,24px); } }

.grid-overlay {
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(13,31,28,.035) 1px, transparent 1px),
    linear-gradient(90deg, rgba(13,31,28,.035) 1px, transparent 1px);
  background-size: 44px 44px;
}

/* ── TOPBAR ─────────────────────────────────────── */
.topbar {
  position: sticky; top: 0; z-index: 100;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 40px; height: 62px;
  background: rgba(245,249,248,.9);
  backdrop-filter: blur(20px) saturate(180%);
  border-bottom: 1px solid var(--border);
  box-shadow: 0 1px 0 rgba(255,255,255,.6);
}

.logo {
  display: flex; align-items: center; gap: 11px;
}
.logo-mark {
  width: 38px; height: 38px;
  background: linear-gradient(140deg, var(--ink2), var(--teal));
  border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 17px;
  box-shadow: 0 3px 12px rgba(13,158,136,.25);
  flex-shrink: 0;
}
.logo-text { line-height: 1; }
.logo-name {
  font-family: 'Fraunces', serif;
  font-size: .95rem; font-weight: 700;
  letter-spacing: .3px; color: var(--ink);
}
.logo-tagline {
  font-size: .58rem; color: var(--muted);
  letter-spacing: .5px; margin-top: 2px;
  text-transform: uppercase;
}

.topbar-right {
  display: flex; align-items: center; gap: 8px;
  font-size: .8rem; color: var(--muted);
}
.topbar-right a {
  font-weight: 600; color: var(--teal);
  padding: 6px 14px; border-radius: 20px;
  border: 1.5px solid rgba(13,158,136,.25);
  background: rgba(13,158,136,.06);
  transition: var(--trans);
  font-size: .78rem;
}
.topbar-right a:hover {
  background: rgba(13,158,136,.12);
  border-color: rgba(13,158,136,.45);
}

/* ── LAYOUT ─────────────────────────────────────── */
.layout {
  position: relative; z-index: 1;
  display: flex;
  flex: 1;
  min-height: calc(100vh - 62px);
}

/* ── LEFT PANEL ─────────────────────────────────── */
.panel-left {
  width: 42%;
  flex-shrink: 0;
  background: linear-gradient(155deg, #0d1f1c 0%, #1a4038 55%, #0d9e88 100%);
  display: flex; flex-direction: column; justify-content: center;
  padding: 60px 52px;
  position: relative; overflow: hidden;
}

.panel-left::before {
  content: '';
  position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}

.panel-left::after {
  content: '';
  position: absolute; bottom: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.12), transparent);
}

.lp-shine {
  position: absolute;
  width: 300px; height: 300px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(22,201,175,.15), transparent 70%);
  bottom: -80px; right: -80px;
  pointer-events: none;
}

.lp-content { position: relative; z-index: 1; }

.lp-badge {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 6px 14px; border-radius: 20px;
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.18);
  font-size: .65rem; font-weight: 600;
  letter-spacing: 1.8px; text-transform: uppercase;
  color: rgba(255,255,255,.85);
  margin-bottom: 28px;
}
.lp-badge-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: #16c9af;
  animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.8)} }

.lp-heading {
  font-family: 'Fraunces', serif;
  font-size: clamp(2rem, 2.8vw, 3rem);
  font-weight: 900;
  line-height: 1.05;
  letter-spacing: -1.5px;
  color: #ffffff;
  margin-bottom: 18px;
}
.lp-heading em {
  font-style: italic;
  color: #7de8d8;
}

.lp-desc {
  font-size: .84rem;
  color: rgba(255,255,255,.65);
  line-height: 1.8;
  margin-bottom: 40px;
  max-width: 320px;
}

.features { display: flex; flex-direction: column; gap: 10px; }

.feature-item {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 16px;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: var(--radius-sm);
  transition: var(--trans);
  cursor: default;
}
.feature-item:hover {
  background: rgba(255,255,255,.13);
  border-color: rgba(255,255,255,.22);
  transform: translateX(5px);
}
.feat-icon {
  width: 38px; height: 38px; flex-shrink: 0;
  border-radius: 9px;
  background: rgba(255,255,255,.1);
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
}
.feat-body {}
.feat-title {
  font-size: .8rem; font-weight: 600;
  color: rgba(255,255,255,.95);
  margin-bottom: 2px;
}
.feat-desc { font-size: .67rem; color: rgba(255,255,255,.5); }

.lp-divider {
  height: 1px;
  background: linear-gradient(90deg, rgba(255,255,255,.12), transparent);
  margin: 32px 0 20px;
}

.lp-stats { display: flex; gap: 28px; }
.lp-stat-val {
  font-family: 'Fraunces', serif;
  font-size: 1.6rem; font-weight: 700;
  color: #7de8d8;
  letter-spacing: -1px;
  display: block;
}
.lp-stat-lbl { font-size: .65rem; color: rgba(255,255,255,.5); margin-top: 1px; }

/* ── FORM PANEL ─────────────────────────────────── */
.panel-right {
  flex: 1;
  display: flex; align-items: flex-start; justify-content: center;
  padding: 52px 32px;
  overflow-y: auto;
}

.form-card {
  width: 100%; max-width: 460px;
  animation: cardIn .5s cubic-bezier(.4,0,.2,1) both;
}
@keyframes cardIn { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }

/* Steps */
.steps {
  display: flex; align-items: center; gap: 6px;
  margin-bottom: 36px;
}
.step {
  display: flex; align-items: center; gap: 6px;
}
.step-dot {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .65rem; font-weight: 700;
  border: 2px solid var(--border2);
  color: var(--muted);
  background: var(--white);
  transition: var(--trans);
}
.step.active .step-dot {
  background: var(--ink);
  border-color: var(--ink);
  color: #fff;
  box-shadow: 0 0 0 4px rgba(13,31,28,.1);
}
.step.done .step-dot {
  background: var(--teal);
  border-color: var(--teal);
  color: #fff;
}
.step-label {
  font-size: .67rem; font-weight: 600; color: var(--muted);
  letter-spacing: .5px;
}
.step.active .step-label { color: var(--ink); }
.step-line {
  flex: 1; height: 2px;
  background: var(--border);
  border-radius: 2px;
  overflow: hidden;
}
.step-line-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--ink), var(--teal));
  transform: scaleX(0); transform-origin: left;
  transition: transform .5s cubic-bezier(.4,0,.2,1);
}
.step-line.done .step-line-fill { transform: scaleX(1); }

/* Header */
.form-eyebrow {
  font-size: .62rem; font-weight: 700;
  letter-spacing: 2px; text-transform: uppercase;
  color: var(--teal); margin-bottom: 8px;
}
.form-title {
  font-family: 'Fraunces', serif;
  font-size: 2.1rem; font-weight: 900;
  letter-spacing: -1.2px; line-height: 1.1;
  color: var(--ink); margin-bottom: 6px;
}
.form-sub {
  font-size: .8rem; color: var(--muted);
  margin-bottom: 26px; line-height: 1.6;
}
.form-sub a { color: var(--teal); font-weight: 600; }

/* Alert */
.alert {
  display: flex; align-items: flex-start; gap: 9px;
  padding: 11px 14px;
  border-radius: var(--radius-sm);
  font-size: .79rem; line-height: 1.5;
  margin-bottom: 16px;
  border: 1px solid transparent;
  animation: slideIn .25s ease both;
}
@keyframes slideIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
.alert-icon { flex-shrink: 0; font-size: .9rem; }
.a-err { background: var(--red-bg); border-color: rgba(192,57,43,.2); color: var(--red); }
.a-err a { font-weight: 700; color: var(--red); text-decoration: underline; }
.a-ok  { background: var(--green-bg); border-color: rgba(10,138,106,.2); color: var(--green); }

/* Form groups */
.field-row { display: flex; gap: 12px; }
.field-group { flex: 1; margin-bottom: 14px; }

.field-label {
  display: block;
  font-size: .63rem; font-weight: 600;
  letter-spacing: .8px; text-transform: uppercase;
  color: var(--ink2); margin-bottom: 6px;
}

.field-input {
  width: 100%;
  padding: 11px 14px;
  background: var(--white);
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: 'DM Sans', sans-serif;
  font-size: .87rem; color: var(--ink);
  outline: none;
  transition: var(--trans);
  box-shadow: 0 1px 2px rgba(13,31,28,.04);
}
.field-input::placeholder { color: rgba(90,121,114,.5); }
.field-input:hover { border-color: var(--border2); }
.field-input:focus {
  border-color: var(--teal);
  box-shadow: 0 0 0 3px rgba(13,158,136,.12), 0 1px 2px rgba(13,31,28,.04);
}
.field-input[type="date"]::-webkit-calendar-picker-indicator {
  filter: opacity(.4); cursor: pointer;
}

.field-wrap { position: relative; }
.field-wrap .field-input { padding-right: 44px; }
.eye-toggle {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: var(--muted); font-size: .85rem; padding: 2px;
  transition: color .2s; line-height: 1;
}
.eye-toggle:hover { color: var(--ink); }

/* Section separator */
.section-sep {
  display: flex; align-items: center; gap: 10px;
  margin: 6px 0 16px;
}
.section-sep-line { flex: 1; height: 1px; background: var(--border); }
.section-sep-label {
  font-size: .6rem; font-weight: 700;
  letter-spacing: 1.8px; text-transform: uppercase;
  color: var(--teal); display: flex; align-items: center; gap: 5px;
}

/* Strength meter */
.strength-wrap { margin-top: 7px; display: flex; align-items: center; gap: 5px; }
.strength-bar {
  flex: 1; height: 3px; border-radius: 2px;
  background: var(--border); overflow: hidden;
}
.strength-fill {
  height: 100%; border-radius: 2px;
  transition: background .35s, width .35s;
  width: 0;
}
.strength-label {
  font-size: .62rem; color: var(--muted); white-space: nowrap; min-width: 42px;
  text-align: right; transition: color .35s;
}

/* Submit */
.submit-btn {
  width: 100%; padding: 13.5px;
  background: linear-gradient(135deg, var(--ink2) 0%, var(--teal) 100%);
  color: #fff;
  border: none; border-radius: var(--radius-sm);
  font-family: 'DM Sans', sans-serif;
  font-size: .9rem; font-weight: 700;
  letter-spacing: .3px;
  cursor: pointer;
  transition: var(--trans);
  margin-top: 10px;
  box-shadow: 0 4px 18px rgba(13,158,136,.28);
  position: relative; overflow: hidden;
}
.submit-btn::after {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,.1), transparent);
  opacity: 0; transition: opacity .2s;
}
.submit-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(13,158,136,.38);
}
.submit-btn:hover::after { opacity: 1; }
.submit-btn:active { transform: translateY(0); }

/* Terms */
.terms {
  font-size: .69rem; color: var(--muted);
  text-align: center; margin-top: 14px; line-height: 1.7;
}
.terms a { color: var(--teal); font-weight: 600; }

/* ── RESPONSIVE ─────────────────────────────────── */
@media (max-width: 860px) {
  .panel-left { display: none; }
  .panel-right { padding: 32px 20px; }
}
@media (max-width: 480px) {
  .topbar { padding: 0 20px; }
  .field-row { flex-direction: column; gap: 0; }
}
</style>
</head>
<body>

<div class="bg-wrap">
  <div class="bg-blob bb1"></div>
  <div class="bg-blob bb2"></div>
  <div class="bg-blob bb3"></div>
</div>
<div class="grid-overlay"></div>

<!-- TOPBAR -->
<header class="topbar">
  <div class="logo">
    <div class="logo-mark">🏛️</div>
    <div class="logo-text">
      <div class="logo-name">Nagrik Seva</div>
      <div class="logo-tagline">Citizen Services Portal</div>
    </div>
  </div>
  <div class="topbar-right">
    Already have an account?
    <a href="citizen_login.php">Sign in →</a>
  </div>
</header>

<!-- LAYOUT -->
<div class="layout">

  <!-- LEFT PANEL -->
  <aside class="panel-left">
    <div class="lp-shine"></div>
    <div class="lp-content">

      <div class="lp-badge">
        <div class="lp-badge-dot"></div>
        Citizen Registration
      </div>

      <h1 class="lp-heading">
        Join the<br>civic <em>movement.</em>
      </h1>

      <p class="lp-desc">
        Be the voice of your community. Report issues, upload evidence,
        and track every complaint until it's resolved — all in one place.
      </p>

      <div class="features">
        <div class="feature-item">
          <div class="feat-icon">📸</div>
          <div class="feat-body">
            <div class="feat-title">Upload photo evidence</div>
            <div class="feat-desc">Attach images to fast-track your complaint</div>
          </div>
        </div>
        <div class="feature-item">
          <div class="feat-icon">📍</div>
          <div class="feat-body">
            <div class="feat-title">GPS location tagging</div>
            <div class="feat-desc">Pinpoint issues for precise reporting</div>
          </div>
        </div>
        <div class="feature-item">
          <div class="feat-icon">🔔</div>
          <div class="feat-body">
            <div class="feat-title">Live resolution updates</div>
            <div class="feat-desc">Get notified at every stage</div>
          </div>
        </div>
        <div class="feature-item">
          <div class="feat-icon">🏆</div>
          <div class="feat-body">
            <div class="feat-title">Community leaderboard</div>
            <div class="feat-desc">Recognising top contributors in your zone</div>
          </div>
        </div>
      </div>

      <div class="lp-divider"></div>

      <div class="lp-stats">
        <div>
          <span class="lp-stat-val">12K+</span>
          <div class="lp-stat-lbl">Issues resolved</div>
        </div>
        <div>
          <span class="lp-stat-val">48</span>
          <div class="lp-stat-lbl">Districts covered</div>
        </div>
        <div>
          <span class="lp-stat-val">96%</span>
          <div class="lp-stat-lbl">Satisfaction rate</div>
        </div>
      </div>

    </div>
  </aside>

  <!-- FORM PANEL -->
  <main class="panel-right">
    <div class="form-card">

      <!-- Progress steps -->
      <div class="steps">
        <div class="step active">
          <div class="step-dot">1</div>
          <span class="step-label">Your details</span>
        </div>
        <div class="step-line"><div class="step-line-fill"></div></div>
        <div class="step">
          <div class="step-dot">2</div>
          <span class="step-label">Verify</span>
        </div>
        <div class="step-line"><div class="step-line-fill"></div></div>
        <div class="step">
          <div class="step-dot">3</div>
          <span class="step-label">Done</span>
        </div>
      </div>

      <div class="form-eyebrow">👤 Citizen Portal</div>
      <h2 class="form-title">Create account</h2>
      <p class="form-sub">Have an account? <a href="citizen_login.php">Sign in →</a></p>

      <?php if ($message) echo $message; ?>

      <!-- FORM -->
      <form method="POST" autocomplete="off" novalidate>

        <div class="field-row">
          <div class="field-group">
            <label class="field-label" for="first_name">First name</label>
            <input
              class="field-input" type="text" id="first_name" name="first_name"
              placeholder="Rahul" required
              value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
          </div>
          <div class="field-group">
            <label class="field-label" for="last_name">Last name</label>
            <input
              class="field-input" type="text" id="last_name" name="last_name"
              placeholder="Naik" required
              value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
          </div>
        </div>

        <div class="field-group">
          <label class="field-label" for="email">Email address</label>
          <input
            class="field-input" type="email" id="email" name="email"
            placeholder="you@example.com" required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="field-row">
          <div class="field-group">
            <label class="field-label" for="phone">Phone number</label>
            <input
              class="field-input" type="tel" id="phone" name="phone"
              placeholder="9876543210"
              value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
          <div class="field-group">
            <label class="field-label" for="dob">Date of birth</label>
            <input
              class="field-input" type="date" id="dob" name="dob"
              value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
          </div>
        </div>

        <div class="section-sep">
          <div class="section-sep-line"></div>
          <div class="section-sep-label">🔒 Security</div>
          <div class="section-sep-line"></div>
        </div>

        <div class="field-group">
          <label class="field-label" for="pw1">Password</label>
          <div class="field-wrap">
            <input
              class="field-input" type="password" id="pw1" name="password"
              placeholder="Min. 8 characters" required
              oninput="updateStrength(this.value)">
            <button type="button" class="eye-toggle" onclick="togglePw('pw1', this)" aria-label="Toggle password">
              <svg id="eye-icon-pw1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <div class="strength-wrap" id="strength-wrap" style="opacity:0;transition:opacity .3s">
            <div class="strength-bar"><div class="strength-fill" id="sf1"></div></div>
            <div class="strength-bar"><div class="strength-fill" id="sf2"></div></div>
            <div class="strength-bar"><div class="strength-fill" id="sf3"></div></div>
            <div class="strength-bar"><div class="strength-fill" id="sf4"></div></div>
            <span class="strength-label" id="strength-lbl"></span>
          </div>
        </div>

        <div class="field-group">
          <label class="field-label" for="pw2">Confirm password</label>
          <div class="field-wrap">
            <input
              class="field-input" type="password" id="pw2" name="confirm_password"
              placeholder="Repeat password" required>
            <button type="button" class="eye-toggle" onclick="togglePw('pw2', this)" aria-label="Toggle password">
              <svg id="eye-icon-pw2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="submit-btn">
          Create Citizen Account →
        </button>

      </form>

      <p class="terms">
        By registering you agree to our <a href="#">Terms of Service</a> &amp; <a href="#">Privacy Policy</a>.
      </p>

    </div>
  </main>
</div>

<script>
// Eye toggle with icon swap
function togglePw(id, btn) {
  const el = document.getElementById(id);
  const isHidden = el.type === 'password';
  el.type = isHidden ? 'text' : 'password';
  const icon = btn.querySelector('svg');
  icon.innerHTML = isHidden
    ? '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
    : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}

// Strength meter
function updateStrength(pw) {
  const wrap = document.getElementById('strength-wrap');
  const lbl  = document.getElementById('strength-lbl');
  const fills = [1,2,3,4].map(i => document.getElementById('sf'+i));

  wrap.style.opacity = pw.length ? '1' : '0';

  let score = 0;
  if (pw.length >= 8)            score++;
  if (/[A-Z]/.test(pw))         score++;
  if (/[0-9]/.test(pw))         score++;
  if (/[^A-Za-z0-9]/.test(pw))  score++;

  const colors = ['#ef5350','#ff7043','#f59e0b','#10b981'];
  const labels = ['Weak','Fair','Good','Strong'];

  fills.forEach((f, i) => {
    f.style.width      = i < score ? '100%' : '0%';
    f.style.background = i < score ? colors[score - 1] : 'transparent';
  });

  lbl.textContent = score ? labels[score - 1] : '';
  lbl.style.color = score ? colors[score - 1] : '';
}
</script>
</body>
</html>