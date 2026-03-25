<?php
session_start();
require_once 'config.php';

$role      = $_SESSION['role'] ?? null;
$name_nav  = $_SESSION['name'] ?? null;
$initials  = $name_nav ? strtoupper(substr($name_nav,0,1)) : null;
$dash_link = match($role){ 'officer'=>'officer_dashboard.php','regulator'=>'regulator_dashboard.php',default=>'citizen_dashboard.php' };

$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) DEFAULT NULL,
  subject VARCHAR(200) NOT NULL, category VARCHAR(60) DEFAULT NULL, message TEXT NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0, replied_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email(email), INDEX idx_unread(is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$result_type = ''; $result_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $phone     = trim($_POST['phone'] ?? '');
    $subject   = trim($_POST['subject'] ?? '');
    $category  = trim($_POST['category'] ?? '');
    $message   = trim($_POST['message'] ?? '');
    $uid       = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? null;

    if (!$full_name || !$email || !$subject || !$message) {
        $result_type = 'error'; $result_msg = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result_type = 'error'; $result_msg = 'Please enter a valid email address.';
    } elseif (strlen($message) < 20) {
        $result_type = 'error'; $result_msg = 'Your message is too short (at least 20 characters).';
    } else {
        $stmt = $conn->prepare("INSERT INTO contact_messages (full_name,email,phone,subject,category,message,user_id,ip_address) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssss',$full_name,$email,$phone,$subject,$category,$message,$uid,$ip);
        $db_ok = $stmt->execute(); $stmt->close();
        $to = 'grievance@goa.gov.in';
        $mail_subj = "[Nagrik Seva Contact] [{$category}] {$subject}";
        $mail_body = "New contact message.\n\nName: {$full_name}\nEmail: {$email}\nPhone: ".($phone?:'Not provided')."\nCategory: {$category}\nSubject: {$subject}\n".str_repeat('-',60)."\n".$message."\nIP: {$ip}";
        @mail($to,$mail_subj,$mail_body,"From: noreply@nagrikseva.goa.gov.in\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8");
        $ack_body = "Dear {$full_name},\n\nThank you for reaching out to Nagrik Seva.\n\nSubject: {$subject}\nCategory: {$category}\nReference: MSG-".strtoupper(substr(md5($email.time()),0,8))."\n\nOur team will respond within 24–48 business hours.\n\nHelpline: 1800-233-1100 (Toll Free · Mon–Sat 9am–6pm)\n\nRegards,\nNagrik Seva Support Team\nGovernment of Goa";
        @mail($email,"We received your message — Nagrik Seva",$ack_body,"From: support@nagrikseva.goa.gov.in\r\nContent-Type: text/plain; charset=UTF-8");
        if ($db_ok) { $result_type = 'success'; $result_msg = "Thank you, <strong>".htmlspecialchars($full_name)."</strong>! Your message has been received. We'll get back to you at <strong>".htmlspecialchars($email)."</strong> within 24–48 hours."; }
        else { $result_type = 'error'; $result_msg = 'Something went wrong. Please try again or email <a href="mailto:grievance@goa.gov.in">grievance@goa.gov.in</a> directly.'; }
    }
}

$prefill_name  = $name_nav  ?? ($_POST['full_name'] ?? '');
$prefill_email = $_SESSION['email'] ?? ($_POST['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Contact Us — Nagrik Seva</title>
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
  --radius:14px;--red:#dc2626;--green:#059669;
}
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;overflow-x:hidden}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-thumb{background:rgba(24,207,180,.3);border-radius:3px}

/* ── SCROLL PROGRESS BAR ── */
#scroll-progress{position:fixed;top:0;left:0;height:3px;z-index:9999;background:linear-gradient(90deg,var(--a2),#7de8d8);width:0%;transition:width .1s linear;box-shadow:0 0 8px rgba(24,207,180,.6)}

/* ── BACKGROUND ── */
.bg-canvas{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.mesh{position:absolute;border-radius:50%;filter:blur(80px);animation:drift 20s ease-in-out infinite alternate}
.m1{width:700px;height:700px;background:radial-gradient(circle,rgba(24,207,180,.08),transparent);top:-150px;left:-100px}
.m2{width:500px;height:500px;background:radial-gradient(circle,rgba(109,229,210,.06),transparent);bottom:-100px;right:-80px;animation-delay:-8s}
.dots{position:fixed;inset:0;z-index:0;background-image:radial-gradient(rgba(4,46,42,.04) 1px,transparent 1px);background-size:28px 28px;pointer-events:none}
@keyframes drift{0%{transform:translate(0,0)}100%{transform:translate(24px,18px)}}

/* ── NAVBAR ── */
.topnav{position:sticky;top:0;z-index:200;background:rgba(255,255,255,.93);backdrop-filter:blur(20px) saturate(180%);border-bottom:1px solid var(--border);height:62px;box-shadow:0 1px 0 rgba(255,255,255,.8),0 2px 12px rgba(4,46,42,.06);transition:box-shadow .3s}
.topnav.scrolled{box-shadow:0 2px 20px rgba(4,46,42,.12)}
.nav-mark{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,var(--a),var(--a2));display:flex;align-items:center;justify-content:center;font-size:17px;box-shadow:0 3px 12px rgba(4,46,42,.22);flex-shrink:0;transition:transform .25s cubic-bezier(.34,1.56,.64,1)}
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

/* ── HERO ── */
.hero{background:linear-gradient(135deg,var(--a) 0%,#065449 45%,#0a8f7a 80%,var(--a2) 100%);padding:64px 0 80px;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 30% 50%,rgba(24,207,180,.18),transparent 60%),radial-gradient(ellipse at 80% 20%,rgba(255,255,255,.05),transparent 50%)}
.hero::after{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.04) 1px,transparent 1px);background-size:32px 32px}
.hero-inner{max-width:680px;position:relative;z-index:1}
.hero-badge{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;border-radius:20px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);font-size:.63rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,.9);margin-bottom:20px;
  animation:hero-in .6s cubic-bezier(.34,1.56,.64,1) both}
.hero h1{font-family:'Fraunces',serif;font-size:clamp(2rem,4vw,3rem);font-weight:900;color:#fff;letter-spacing:-1.5px;line-height:1.1;margin-bottom:14px;
  animation:hero-in .7s cubic-bezier(.22,1,.36,1) .12s both}
.hero h1 span{color:#7de8d8}
.hero p{font-size:.9rem;color:rgba(255,255,255,.68);line-height:1.75;max-width:520px;margin:0;
  animation:hero-in .7s cubic-bezier(.22,1,.36,1) .24s both}
@keyframes hero-in{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.hero-wave{position:absolute;bottom:-2px;left:0;right:0;height:48px;background:var(--bg);clip-path:ellipse(55% 100% at 50% 100%)}

/* ── FORM CARD ── */
.form-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-md);overflow:hidden;
  animation:slide-up .65s cubic-bezier(.22,1,.36,1) .08s both}
@keyframes slide-up{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.form-header{padding:24px 28px 20px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,rgba(240,253,251,.8),rgba(255,255,255,0))}
.form-title{font-family:'Fraunces',serif;font-size:1.2rem;font-weight:900;color:var(--text);letter-spacing:-.4px;margin-bottom:4px}
.form-sub{font-size:.76rem;color:var(--muted2);line-height:1.6}
.form-body{padding:28px}

/* ── ALERTS ── */
.alert-custom{padding:14px 16px;border-radius:11px;font-size:.81rem;margin-bottom:20px;border:1px solid transparent;line-height:1.6;animation:fadeUp .35s cubic-bezier(.22,1,.36,1)}
@keyframes fadeUp{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.alert-success-c{background:rgba(5,150,105,.08);border-color:rgba(5,150,105,.2);color:var(--green)}
.alert-error-c{background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.18);color:var(--red)}
.alert-custom a{font-weight:700;color:inherit;text-decoration:underline}

/* ── SUCCESS STATE ── */
.success-state{padding:48px 32px;text-align:center}
.success-ico{font-size:3.2rem;margin-bottom:16px;display:block;
  animation:bounce-in .7s cubic-bezier(.34,1.56,.64,1) both}
@keyframes bounce-in{from{transform:scale(0) rotate(-15deg);opacity:0}to{transform:scale(1) rotate(0);opacity:1}}
.success-title{font-family:'Fraunces',serif;font-size:1.7rem;font-weight:900;color:var(--text);letter-spacing:-.8px;margin-bottom:10px;
  animation:hero-in .6s cubic-bezier(.22,1,.36,1) .18s both}
.success-sub{font-size:.84rem;color:var(--muted2);line-height:1.75;margin-bottom:28px;
  animation:hero-in .6s cubic-bezier(.22,1,.36,1) .28s both}
.success-ref{display:inline-block;padding:8px 16px;background:var(--ag);border:1px solid var(--a3);border-radius:8px;font-family:'DM Mono',monospace;font-size:.78rem;color:var(--a2);margin-bottom:24px;
  animation:hero-in .6s cubic-bezier(.22,1,.36,1) .36s both}
.sa-btn{padding:11px 22px;border-radius:10px;border:1.5px solid var(--border2);background:#fff;color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;font-size:.83rem;font-weight:600;cursor:pointer;transition:all .17s}
.sa-btn:hover{border-color:var(--a2);transform:translateY(-1px)}
.sa-btn-pri{background:linear-gradient(135deg,var(--a),var(--a2));color:#fff;border:none;box-shadow:0 3px 10px rgba(4,46,42,.2)}
.sa-btn-pri:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(4,46,42,.28)}

/* ── FORM FIELDS ── */
.fg{margin-bottom:16px}
.fl{display:block;font-size:.62rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--muted2);margin-bottom:6px}
.fl-req{color:var(--a2);margin-left:2px}
.fi{width:100%;padding:11px 14px;background:#fff;border:1.5px solid var(--border);border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;color:var(--text);outline:none;transition:border-color .2s,box-shadow .2s,transform .15s;box-shadow:0 1px 3px rgba(4,46,42,.04)}
.fi::placeholder{color:var(--muted);opacity:.7}
.fi:focus{border-color:var(--a2);box-shadow:0 0 0 3px rgba(24,207,180,.1);transform:translateY(-1px)}
textarea.fi{resize:vertical;min-height:120px;line-height:1.65}
.char-count{font-size:.62rem;color:var(--muted);text-align:right;margin-top:4px;font-family:'DM Mono',monospace;transition:color .2s}

/* ── CATEGORY CHIPS ── */
.cat-chips{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px}
.cc{padding:6px 13px;border-radius:20px;border:1.5px solid var(--border);background:#fff;font-size:.72rem;font-weight:600;color:var(--muted2);cursor:pointer;transition:all .2s cubic-bezier(.34,1.56,.64,1);font-family:'Plus Jakarta Sans',sans-serif}
.cc:hover{border-color:rgba(24,207,180,.4);background:var(--ag);color:var(--text);transform:translateY(-2px)}
.cc.sel{background:var(--a3);border-color:rgba(24,207,180,.5);color:var(--a);box-shadow:inset 0 0 0 1px rgba(24,207,180,.3);transform:translateY(-2px)}

/* ── SUBMIT BTN ── */
.btn-submit{width:100%;padding:13px;background:linear-gradient(135deg,var(--a),#065449);color:#fff;border:none;border-radius:11px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:4px;box-shadow:0 4px 16px rgba(4,46,42,.25);position:relative;overflow:hidden}
.btn-submit::after{content:'';position:absolute;inset:0;background:rgba(255,255,255,.12);transform:translateX(-100%);transition:transform .4s ease}
.btn-submit:hover{background:linear-gradient(135deg,#065449,#0a8f7a);transform:translateY(-2px);box-shadow:0 8px 24px rgba(4,46,42,.3)}
.btn-submit:hover::after{transform:translateX(0)}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}

/* ── SIDEBAR CARDS ── */
.info-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);
  animation:slide-up .65s cubic-bezier(.22,1,.36,1) both}
.info-card:nth-child(1){animation-delay:.14s}
.info-card:nth-child(2){animation-delay:.22s}
.info-card:nth-child(3){animation-delay:.30s}
.info-card:nth-child(4){animation-delay:.38s}
.info-head{padding:16px 20px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,rgba(240,253,251,.6),rgba(255,255,255,0))}
.info-head-title{font-size:.84rem;font-weight:700;color:var(--text);letter-spacing:-.1px}

.info-body{padding:16px 20px}
.cm-item{display:flex;align-items:flex-start;gap:13px;padding:12px 0;border-bottom:1px solid var(--border);transition:transform .2s}
.cm-item:last-child{border-bottom:none;padding-bottom:0}
.cm-item:hover{transform:translateX(4px)}
.cm-ico{width:38px;height:38px;border-radius:10px;background:var(--ag);border:1.5px solid var(--a3);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;transition:all .22s cubic-bezier(.34,1.56,.64,1)}
.cm-item:hover .cm-ico{background:var(--a3);border-color:rgba(24,207,180,.4);transform:scale(1.1) rotate(-4deg)}
.cm-label{font-size:.76rem;font-weight:700;color:var(--text);margin-bottom:2px}
.cm-value{font-size:.72rem;color:var(--a2);font-weight:600}
.cm-sub{font-size:.64rem;color:var(--muted);margin-top:1px}

/* ── OFFICE HOURS ── */
.oh-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:.76rem;transition:background .15s;border-radius:6px;margin:0 -4px;padding-left:4px;padding-right:4px}
.oh-row:hover{background:var(--ag)}
.oh-row:last-child{border-bottom:none}
.oh-day{font-weight:600;color:var(--text)}
.oh-time{color:var(--muted2);font-size:.72rem}
.oh-closed{color:var(--muted);font-style:italic;font-size:.71rem}
.oh-badge{display:inline-flex;align-items:center;gap:5px;font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:4px;background:rgba(5,150,105,.1);color:var(--green);border:1px solid rgba(5,150,105,.2)}
.oh-badge::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--green);animation:pulse-dot 1.6s ease-in-out infinite}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}

/* ── DEPARTMENTS ── */
.dept-item{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);font-size:.76rem;transition:transform .18s,background .15s;border-radius:6px;margin:0 -4px;padding-left:4px;padding-right:4px}
.dept-item:hover{transform:translateX(5px);background:var(--ag)}
.dept-item:last-child{border-bottom:none}
.dept-dot{width:8px;height:8px;border-radius:50%;background:var(--a2);flex-shrink:0;transition:transform .2s cubic-bezier(.34,1.56,.64,1)}
.dept-item:hover .dept-dot{transform:scale(1.5)}
.dept-name{font-weight:600;color:var(--text)}
.dept-email{color:var(--muted2);margin-left:auto;font-size:.68rem}

/* ── FAQ ── */
.faq-item{border-bottom:1px solid var(--border)}
.faq-item:last-child{border-bottom:none}
.faq-q{width:100%;padding:13px 0;background:none;border:none;text-align:left;font-family:'Plus Jakarta Sans',sans-serif;font-size:.8rem;font-weight:600;color:var(--text);cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:10px;transition:color .16s}
.faq-q:hover{color:var(--a2)}
.faq-arr{font-size:.7rem;color:var(--muted);transition:transform .28s cubic-bezier(.34,1.56,.64,1);flex-shrink:0}
.faq-a{font-size:.74rem;color:var(--muted2);line-height:1.75;padding-bottom:13px;display:none;animation:faq-open .28s cubic-bezier(.22,1,.36,1)}
@keyframes faq-open{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.faq-item.open .faq-arr{transform:rotate(180deg);color:var(--a2)}
.faq-item.open .faq-a{display:block}
.faq-item.open .faq-q{color:var(--a2)}

/* ── FOOTER ── */
.site-footer{background:var(--a);padding:40px 0;border-top:1px solid rgba(255,255,255,.1)}
.footer-mark{width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.footer-name{font-family:'Fraunces',serif;font-size:.86rem;font-weight:700;color:#fff}
.footer-tag{font-size:.56rem;color:rgba(255,255,255,.45);margin-top:2px}
.footer-links a{font-size:.72rem;font-weight:500;color:rgba(255,255,255,.5);transition:color .14s}
.footer-links a:hover{color:#fff}
.footer-copy{font-size:.64rem;color:rgba(255,255,255,.35)}

/* ── SCROLL-REVEAL ── */
.reveal{opacity:0;transform:translateY(28px);transition:opacity .62s cubic-bezier(.22,1,.36,1),transform .62s cubic-bezier(.22,1,.36,1)}
.reveal.visible{opacity:1;transform:translateY(0)}
.reveal-left{opacity:0;transform:translateX(-24px);transition:opacity .62s cubic-bezier(.22,1,.36,1),transform .62s cubic-bezier(.22,1,.36,1)}
.reveal-left.visible{opacity:1;transform:translateX(0)}
.delay-1{transition-delay:.07s}.delay-2{transition-delay:.14s}.delay-3{transition-delay:.21s}
.delay-4{transition-delay:.28s}.delay-5{transition-delay:.35s}

/* ── TYPING INDICATOR on submit ── */
@keyframes spin{to{transform:rotate(360deg)}}
.spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px}

@media(max-width:576px){.form-body{padding:20px}.success-state{padding:36px 20px}}
</style>
</head>
<body>

<!-- Scroll progress bar -->
<div id="scroll-progress"></div>

<div class="bg-canvas"><div class="mesh m1"></div><div class="mesh m2"></div></div>
<div class="dots"></div>

<!-- ══ NAVBAR ══ -->
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
        <li class="nav-item"><a class="nav-link-item" href="about.php">About</a></li>
        <li class="nav-item"><a class="nav-link-item active" href="contact.php">Contact</a></li>
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

<!-- ══ MOBILE OFFCANVAS ══ -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileMenu" style="background:rgba(240,253,251,.97);backdrop-filter:blur(16px);">
  <div class="offcanvas-header">
    <h6 class="offcanvas-title" style="font-family:'Fraunces',serif;font-weight:700;color:var(--a)">Nagrik Seva</h6>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body pt-0">
    <a href="index.php" class="nav-link-item d-block mb-1">🏠 Home</a>
    <a href="citizen_dashboard.php" class="nav-link-item d-block mb-1">📊 Dashboard</a>
    <a href="about.php" class="nav-link-item d-block mb-1">ℹ️ About</a>
    <a href="contact.php" class="nav-link-item active d-block mb-1">📞 Contact</a>
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

  <!-- ══ HERO ══ -->
  <section class="hero">
    <div class="container">
      <div class="hero-inner">
        <div class="hero-badge">✉️ Get in Touch</div>
        <h1>We're here<br>to <span>help you</span></h1>
        <p>Whether you have a question about a complaint, need technical support, or want to partner with us — we read every message and respond within 24 hours.</p>
      </div>
    </div>
    <div class="hero-wave"></div>
  </section>

  <!-- ══ MAIN CONTENT ══ -->
  <div class="container py-5">
    <div class="row g-4 align-items-start">

      <!-- FORM COLUMN -->
      <div class="col-12 col-lg-7">
        <div class="form-card">

          <?php if($result_type === 'success'): ?>
          <!-- SUCCESS STATE -->
          <div class="success-state">
            <span class="success-ico">✅</span>
            <div class="success-title">Message sent!</div>
            <div class="success-sub"><?= $result_msg ?><br><br>Our team typically responds within <strong>24–48 business hours</strong>. Check your inbox (and spam folder) for our confirmation email.</div>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
              <a href="contact.php"><button class="sa-btn">Send Another →</button></a>
              <a href="citizen_dashboard.php"><button class="sa-btn sa-btn-pri">Go to Dashboard →</button></a>
            </div>
          </div>

          <?php else: ?>
          <!-- FORM -->
          <div class="form-header">
            <div class="form-title">Send us a message</div>
            <div class="form-sub">All fields marked <span style="color:var(--a2);font-weight:700">*</span> are required. We respond within 24–48 business hours.</div>
          </div>
          <div class="form-body">
            <?php if($result_type === 'error'): ?>
            <div class="alert-custom alert-error-c">⚠ <?= $result_msg ?></div>
            <?php endif; ?>

            <form method="POST" id="contact-form" novalidate>

              <!-- TOPIC CHIPS -->
              <div class="fg reveal">
                <label class="fl">Topic <span class="fl-req">*</span></label>
                <div class="cat-chips" id="cat-chips">
                  <?php
                  $cats = ['General Enquiry','Complaint Support','Technical Issue','Officer / Role Access','Feedback & Suggestions','Media & Press','Partnership'];
                  $sel_cat = $_POST['category'] ?? 'General Enquiry';
                  foreach($cats as $c): ?>
                  <button type="button" class="cc <?= $sel_cat===$c?'sel':'' ?>" onclick="selCat(this,'<?= htmlspecialchars($c,ENT_QUOTES) ?>')"><?= htmlspecialchars($c) ?></button>
                  <?php endforeach; ?>
                </div>
                <input type="hidden" name="category" id="cat-val" value="<?= htmlspecialchars($sel_cat) ?>">
              </div>

              <!-- NAME + EMAIL -->
              <div class="row g-3 mb-3 reveal delay-1">
                <div class="col-12 col-sm-6">
                  <label class="fl" for="full_name">Full Name <span class="fl-req">*</span></label>
                  <input class="fi" type="text" id="full_name" name="full_name" placeholder="Rahul Naik" required value="<?= htmlspecialchars($prefill_name) ?>">
                </div>
                <div class="col-12 col-sm-6">
                  <label class="fl" for="email">Email Address <span class="fl-req">*</span></label>
                  <input class="fi" type="email" id="email" name="email" placeholder="you@example.com" required value="<?= htmlspecialchars($prefill_email) ?>">
                </div>
              </div>

              <!-- PHONE + SUBJECT -->
              <div class="row g-3 mb-3 reveal delay-2">
                <div class="col-12 col-sm-6">
                  <label class="fl" for="phone">Phone Number</label>
                  <input class="fi" type="tel" id="phone" name="phone" placeholder="9876543210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-12 col-sm-6">
                  <label class="fl" for="subject">Subject <span class="fl-req">*</span></label>
                  <input class="fi" type="text" id="subject" name="subject" placeholder="Brief description of your query" required value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                </div>
              </div>

              <!-- MESSAGE -->
              <div class="fg reveal delay-3">
                <label class="fl" for="message">Your Message <span class="fl-req">*</span></label>
                <textarea class="fi" id="message" name="message" placeholder="Please describe your query in detail. If it's about a specific complaint, include the reference number (e.g. GRV-A4C7E2)." required maxlength="2000" oninput="updateCount(this)"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                <div class="char-count" id="char-count">0 / 2000</div>
              </div>

              <div class="reveal delay-4">
                <button type="submit" class="btn-submit" id="submit-btn">Send Message →</button>
              </div>
            </form>
          </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- ══ SIDEBAR ══ -->
      <div class="col-12 col-lg-5">
        <div class="d-flex flex-column gap-3">

          <!-- Direct Contact -->
          <div class="info-card">
            <div class="info-head"><div class="info-head-title">📞 Direct Contact</div></div>
            <div class="info-body">
              <div class="cm-item">
                <div class="cm-ico">📞</div>
                <div><div class="cm-label">Toll-Free Helpline</div><div class="cm-value">1800-233-1100</div><div class="cm-sub">Mon–Sat · 9:00 AM – 6:00 PM</div></div>
              </div>
              <div class="cm-item">
                <div class="cm-ico">✉️</div>
                <div><div class="cm-label">General Enquiries</div><div class="cm-value">grievance@goa.gov.in</div><div class="cm-sub">Response within 24 hours</div></div>
              </div>
              <div class="cm-item">
                <div class="cm-ico">🛠️</div>
                <div><div class="cm-label">Technical Support</div><div class="cm-value">support@nagrikseva.goa.gov.in</div><div class="cm-sub">Portal issues · Login help · Bugs</div></div>
              </div>
              <div class="cm-item">
                <div class="cm-ico">📍</div>
                <div><div class="cm-label">Head Office</div><div class="cm-value">Secretariat, Porvorim</div><div class="cm-sub">Alto Porvorim, Goa 403521</div></div>
              </div>
            </div>
          </div>

          <!-- Office Hours -->
          <div class="info-card">
            <div class="info-head">
              <div class="info-head-title d-flex align-items-center gap-2">🕐 Office Hours
                <?php $h=(int)date('H');$d=(int)date('N');$open=($d<=6&&$h>=9&&$h<18); ?>
                <?php if($open): ?><span class="oh-badge">Open now</span><?php endif; ?>
              </div>
            </div>
            <div class="info-body">
              <div class="oh-row"><span class="oh-day">Monday – Friday</span><span class="oh-time">9:00 AM – 6:00 PM</span></div>
              <div class="oh-row"><span class="oh-day">Saturday</span><span class="oh-time">9:00 AM – 2:00 PM</span></div>
              <div class="oh-row"><span class="oh-day">Sunday</span><span class="oh-closed">Closed</span></div>
              <div class="oh-row"><span class="oh-day">Public Holidays</span><span class="oh-closed">Closed</span></div>
            </div>
          </div>

          <!-- Department Contacts -->
          <div class="info-card">
            <div class="info-head"><div class="info-head-title">🏢 Department Contacts</div></div>
            <div class="info-body">
              <div class="dept-item"><div class="dept-dot"></div><div class="dept-name">Roads & PWD</div><div class="dept-email">pwd@goa.gov.in</div></div>
              <div class="dept-item"><div class="dept-dot"></div><div class="dept-name">Water Resources</div><div class="dept-email">water@goa.gov.in</div></div>
              <div class="dept-item"><div class="dept-dot"></div><div class="dept-name">Electricity Dept.</div><div class="dept-email">elec@goa.gov.in</div></div>
              <div class="dept-item"><div class="dept-dot"></div><div class="dept-name">Sanitation / CCP</div><div class="dept-email">ccp@goa.gov.in</div></div>
              <div class="dept-item"><div class="dept-dot"></div><div class="dept-name">Property / Revenue</div><div class="dept-email">revenue@goa.gov.in</div></div>
            </div>
          </div>

          <!-- FAQ -->
          <div class="info-card">
            <div class="info-head"><div class="info-head-title">❓ Common Questions</div></div>
            <div class="info-body" style="padding-top:8px">
              <div class="faq-item">
                <button class="faq-q" onclick="toggleFaq(this)">How do I track my complaint?<span class="faq-arr">▼</span></button>
                <div class="faq-a">Go to <strong>Track Complaint</strong> in the sidebar or visit <code>track.php</code>. Enter your GRV reference number to see real-time status, assigned officer, and resolution notes.</div>
              </div>
              <div class="faq-item">
                <button class="faq-q" onclick="toggleFaq(this)">My complaint has been pending for weeks.<span class="faq-arr">▼</span></button>
                <div class="faq-a">Complaints not resolved within 14 days are automatically escalated to a regulator. Contact us with your GRV number and we'll escalate manually.</div>
              </div>
              <div class="faq-item">
                <button class="faq-q" onclick="toggleFaq(this)">I forgot my password.<span class="faq-arr">▼</span></button>
                <div class="faq-a">Click <strong>Forgot Password</strong> on the login page. You'll receive a reset OTP on your registered email.</div>
              </div>
              <div class="faq-item">
                <button class="faq-q" onclick="toggleFaq(this)">How do I become an officer/regulator?<span class="faq-arr">▼</span></button>
                <div class="faq-a">Visit the <a href="officer_register.php" style="color:var(--a2);font-weight:600">Officer Registration</a> page and submit your badge ID and zone. Accounts are activated by admin within 24 hours after verification.</div>
              </div>
              <div class="faq-item">
                <button class="faq-q" onclick="toggleFaq(this)">Is my personal data safe?<span class="faq-arr">▼</span></button>
                <div class="faq-a">Yes. Your data is stored on government-secured servers, never sold or shared. Complaint data in public statistics is fully anonymised.</div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- ══ FOOTER ══ -->
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

</div><!-- .page -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── SCROLL PROGRESS + NAV SHADOW ── */
window.addEventListener('scroll', function(){
  const pct = document.documentElement.scrollTop /
              (document.documentElement.scrollHeight - window.innerHeight) * 100;
  document.getElementById('scroll-progress').style.width = pct + '%';
  document.getElementById('topnav').classList.toggle('scrolled', window.scrollY > 20);
});

/* ── SCROLL-REVEAL (IntersectionObserver) ── */
const revObs = new IntersectionObserver(entries => {
  entries.forEach(e => { if(e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });
document.querySelectorAll('.reveal, .reveal-left').forEach(el => revObs.observe(el));

/* ── CATEGORY CHIP SELECTION ── */
function selCat(btn, val){
  document.querySelectorAll('.cc').forEach(b => b.classList.remove('sel'));
  btn.classList.add('sel');
  document.getElementById('cat-val').value = val;
}

/* ── CHARACTER COUNTER ── */
function updateCount(ta){
  const el = document.getElementById('char-count');
  const len = ta.value.length;
  el.textContent = len + ' / 2000';
  el.style.color = len > 1800 ? '#dc2626' : len > 1500 ? '#d97706' : 'var(--muted)';
}
(function(){ const ta = document.getElementById('message'); if(ta) updateCount(ta); })();

/* ── FAQ ACCORDION ── */
function toggleFaq(btn){
  const item = btn.closest('.faq-item');
  const wasOpen = item.classList.contains('open');
  /* close all */
  document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
  /* open clicked if it wasn't open */
  if(!wasOpen) item.classList.add('open');
}

/* ── FORM SUBMIT — loading state ── */
const form = document.getElementById('contact-form');
if(form){
  form.addEventListener('submit', function(e){
    const btn = document.getElementById('submit-btn');
    const cat = document.getElementById('cat-val').value;
    if(!cat){ e.preventDefault(); alert('Please select a topic.'); return; }
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Sending…';
  });
}

/* ── INPUT FOCUS LABEL HIGHLIGHT ── */
document.querySelectorAll('.fi').forEach(input => {
  const row = input.closest('.fg, .col-12');
  if(!row) return;
  const label = row.querySelector('.fl');
  if(!label) return;
  input.addEventListener('focus', () => label.style.color = 'var(--a2)');
  input.addEventListener('blur',  () => label.style.color = '');
});

/* USER DROPDOWN */
function toggleUserMenu(e){e.stopPropagation();const btn=document.getElementById('nav-user-btn');const dd=document.getElementById('nav-dropdown');const open=dd.classList.toggle('show');btn.classList.toggle('open',open);btn.setAttribute('aria-expanded',open);}
document.addEventListener('click',function(){const dd=document.getElementById('nav-dropdown');const btn=document.getElementById('nav-user-btn');if(dd)dd.classList.remove('show');if(btn){btn.classList.remove('open');btn.setAttribute('aria-expanded','false');}});

/* ── EASTER EGG ── */
(function(){
  const c='admin'; let b='';
  document.addEventListener('keydown', function(e){
    if(['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;
    b += e.key.toLowerCase(); b = b.slice(-c.length);
    if(b === c) window.location.href = 'admin.php';
  });
})();
</script>
</body>
</html>
