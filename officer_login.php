<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ((!empty($_SESSION['user_id']) || !empty($_SESSION['is_demo'])) && ($_SESSION['role'] ?? '') === 'officer') { header('Location: officer_dashboard.php'); exit; }

// ── SEND OTP EMAIL via PHPMailer + Gmail SMTP ──────────────────────────────
function sendOtpEmail($to_email, $to_name, $otp) {
    $smtp_user     = 'mayureshchari05@gmail.com';
    $smtp_password = 'mtoo iicq yyin uiid';

    $html_body = '
    <!DOCTYPE html>
    <html>
    <body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:30px;">
      <div style="max-width:480px;margin:auto;background:#fff;border-radius:10px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <h2 style="color:#1a1a2e;margin-bottom:6px;">Nagrik Seva</h2>
        <p style="color:#555;">Hi <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
        <p style="color:#555;">Use the code below to sign in to the <strong>Officer Portal</strong>. It expires in <strong>10 minutes</strong>.</p>
        <div style="text-align:center;margin:28px 0;">
          <span style="display:inline-block;font-size:36px;font-weight:700;letter-spacing:10px;color:#2563eb;background:#eff6ff;padding:16px 28px;border-radius:8px;">' . $otp . '</span>
        </div>
        <p style="color:#888;font-size:13px;">If you did not request this, please ignore this email.</p>
        <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
        <p style="color:#aaa;font-size:12px;">Nagrik Seva · Officer Portal</p>
      </div>
    </body>
    </html>';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom($smtp_user, 'Nagrik Seva');
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = 'Your Nagrik Seva Officer Login OTP';
        $mail->Body    = $html_body;
        $mail->AltBody = "Your OTP is: $otp  (expires in 10 minutes)";
        $mail->send();
        return true;
    } catch (Exception $e) {
        $_SESSION['mail_error'] = $mail->ErrorInfo;
        return false;
    }
}

// ── DEMO QUICK LOGIN ──
if (isset($_GET['demo'])) {
    $_SESSION['user_id'] = -1;   // -1 = demo; must be non-zero so empty() passes
    $_SESSION['role']    = 'officer';
    $_SESSION['name']    = 'Suresh Kamat';
    $_SESSION['dept']    = 'Road & PWD';
    $_SESSION['is_demo'] = true;
    header('Location: officer_dashboard.php'); exit;
}
$error=$success=''; $mode=isset($_SESSION['otp_pending_email'])?'otp_verify':'password';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=trim($_POST['action']??''); $email=strtolower(trim($_POST['email']??''));
    if ($action==='login_password') {
        $pw=$_POST['password']??'';
        if (!$email||!$pw) { $error='Please fill in both fields.'; }
        else {
            $st=$conn->prepare("SELECT id,name,role,password_hash,is_active FROM users WHERE email=? AND role='officer' LIMIT 1");
            $st->bind_param('s',$email); $st->execute(); $u=$st->get_result()->fetch_assoc(); $st->close();
            if (!$u) $error='No officer account found with that email.';
            elseif (!$u['is_active']) $error='Account inactive. Contact admin.';
            elseif (!password_verify($pw,$u['password_hash'])) $error='Incorrect password.';
            else {
                $otp=str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT); $exp=date('Y-m-d H:i:s',strtotime('+10 minutes'));
                $ins=$conn->prepare("INSERT INTO otp_tokens(email,otp,expires_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE otp=?,expires_at=?");
                $ins->bind_param('sssss',$email,$otp,$exp,$otp,$exp); $ins->execute(); $ins->close();
                $_SESSION['otp_pending_email']=$email; $_SESSION['otp_pending_uid']=$u['id']; $_SESSION['otp_pending_name']=$u['name']; $_SESSION['otp_pending_role']=$u['role'];

                $mail_sent = sendOtpEmail($email, $u['name'], $otp);
                if ($mail_sent) {
                    $mode = 'otp_verify';
                    $success = 'A 6-digit OTP has been sent to <strong>' . htmlspecialchars($email) . '</strong>. It expires in 10 minutes.';
                } else {
                    $_SESSION['dev_otp'] = $otp;
                    $mode = 'otp_verify';
                    $mail_err = $_SESSION['mail_error'] ?? 'Unknown error'; unset($_SESSION['mail_error']);
                    $success = 'OTP generated but email failed — check dev box below.<br><small style="color:red">Mail error: ' . htmlspecialchars($mail_err) . '</small>';
                }
            }
        }
    } elseif ($action==='verify_otp') {
        $mode='otp_verify'; $otp_i=trim($_POST['otp']??''); $omail=$_SESSION['otp_pending_email']??'';
        if (strlen($otp_i)<6) $error='Enter all 6 digits.';
        elseif (!$omail) { $error='Session expired. <a href="officer_login.php">Start again</a>'; $mode='password'; }
        else {
            $st=$conn->prepare("SELECT otp,expires_at FROM otp_tokens WHERE email=? ORDER BY created_at DESC LIMIT 1");
            $st->bind_param('s',$omail); $st->execute(); $tok=$st->get_result()->fetch_assoc(); $st->close();
            if (!$tok) $error='No OTP found.'; elseif (strtotime($tok['expires_at'])<time()) $error='OTP expired.'; elseif ($tok['otp']!==$otp_i) $error='Wrong code.';
            else {
                $del=$conn->prepare("DELETE FROM otp_tokens WHERE email=?"); $del->bind_param('s',$omail); $del->execute(); $del->close();
                $_SESSION['user_id']=$_SESSION['otp_pending_uid']; $_SESSION['name']=$_SESSION['otp_pending_name']; $_SESSION['email']=$omail; $_SESSION['role']='officer';
                $conn->query("UPDATE users SET last_login=NOW() WHERE id=".(int)$_SESSION['user_id']);
                unset($_SESSION['otp_pending_email'],$_SESSION['otp_pending_uid'],$_SESSION['otp_pending_name'],$_SESSION['otp_pending_role'],$_SESSION['dev_otp']);
                header('Location: officer_dashboard.php'); exit;
            }
        }
    } elseif ($action==='cancel_otp') {
        unset($_SESSION['otp_pending_email'],$_SESSION['otp_pending_uid'],$_SESSION['otp_pending_name'],$_SESSION['otp_pending_role'],$_SESSION['dev_otp']);
        header('Location: officer_login.php'); exit;
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Officer Login — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --a:#042e2a;--a2:#18cfb4;--a3:rgba(24,207,180,.12);--ag:rgba(24,207,180,.08);
  --bg:#f0fdfb;--bg2:#e2faf7;--bg3:#cef7f2;
  --white:#ffffff;--card:#ffffff;
  --text:#042e2a;--text2:#065449;
  --muted:#4a7260;--muted2:#2a7d4f;
  --border:rgba(4,46,42,.14);--border2:rgba(4,46,42,.28);
  --shadow:0 1px 4px rgba(4,46,42,.1),0 4px 14px rgba(4,46,42,.08);
  --shadow2:0 4px 20px rgba(4,46,42,.18),0 1px 4px rgba(4,46,42,.1);
  --red:#dc2626;--red-bg:rgba(220,38,38,.08);
  --green:#059669;--green-bg:rgba(5,150,105,.08);
  --amber:#d97706;--amber-bg:rgba(217,119,6,.08);
  --blue:#042e2a;--blue-bg:rgba(4,46,42,.08);
}
html,body{height:100%;overflow:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;display:flex;min-height:100vh}
a{text-decoration:none;color:inherit}
.bg-canvas{position:fixed;inset:0;z-index:0;overflow:hidden}
.mesh{position:absolute;border-radius:50%;filter:blur(75px);animation:drift 18s ease-in-out infinite alternate}
.m1{width:600px;height:600px;background:radial-gradient(circle,rgba(24,207,180,.10),transparent);top:-100px;left:-80px}
.m2{width:500px;height:500px;background:radial-gradient(circle,rgba(230,81,0,.1),transparent);bottom:-80px;right:-60px;animation-delay:-7s}
.m3{width:300px;height:300px;background:radial-gradient(circle,rgba(255,243,224,.9),transparent);top:35%;left:40%;animation-delay:-3s}
@keyframes drift{0%{transform:translate(0,0)}100%{transform:translate(20px,14px)}}
.dots{position:fixed;inset:0;z-index:0;background-image:radial-gradient(rgba(230,81,0,.05) 1px,transparent 1px);background-size:28px 28px}
.wrap{position:relative;z-index:1;display:flex;width:100%;min-height:100vh}
.lp{width:50%;display:flex;flex-direction:column;justify-content:space-between;padding:52px 56px;border-right:1px solid var(--border);overflow:hidden;flex-shrink:0;position:relative}
.lp::after{content:'';position:absolute;top:0;right:0;bottom:0;width:1px;background:linear-gradient(to bottom,transparent,var(--a2),transparent)}
.watermark{position:absolute;font-size:20rem;font-weight:900;color:rgba(255,109,0,.04);right:-30px;top:50%;transform:translateY(-50%);pointer-events:none;user-select:none;letter-spacing:-15px;line-height:1}
.lp-top{position:relative;z-index:1}
.logo-row{display:flex;align-items:center;gap:13px;margin-bottom:60px}
.logo-mark{width:44px;height:44px;background:linear-gradient(135deg,var(--a),var(--a2));border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 4px 20px var(--shadow);position:relative;flex-shrink:0}
.logo-mark::after{content:'';position:absolute;inset:-3px;border-radius:15px;border:1.5px solid rgba(255,109,0,.3);animation:pr 2.5s ease-in-out infinite}
@keyframes pr{0%,100%{opacity:.7}50%{opacity:.15}}
.logo-n{font-size:1rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text)}
.logo-t{font-size:.6rem;color:var(--muted);letter-spacing:.4px;margin-top:2px}
.role-badge{display:inline-flex;align-items:center;gap:7px;padding:6px 14px;border-radius:20px;background:var(--ag);border:1.5px solid rgba(230,81,0,.2);font-size:.65rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--a);margin-bottom:18px}
.role-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--a);animation:blink 1.5s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
.hl{font-size:clamp(2.2rem,3.3vw,3.2rem);font-weight:900;line-height:1.0;letter-spacing:-2px;margin-bottom:18px}
.hl em{font-style:normal;color:var(--a);display:block}
.hl-sub{font-size:.88rem;color:var(--muted2);line-height:1.8;max-width:320px;margin-bottom:44px}
.duties{display:flex;flex-direction:column;gap:10px}
.duty{display:flex;align-items:center;gap:12px;padding:13px 16px;border-radius:13px;background:var(--white);border:1px solid var(--border);box-shadow:0 1px 5px rgba(0,0,0,.04);transition:all .25s}
.duty:hover{border-color:var(--a2);transform:translateX(4px);box-shadow:0 3px 14px var(--shadow)}
.duty-ico{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.d1{background:rgba(230,81,0,.09)}.d2{background:rgba(255,152,0,.12)}.d3{background:rgba(76,175,80,.1)}
.duty-n{font-size:.79rem;font-weight:600;color:var(--text)}
.duty-d{font-size:.67rem;color:var(--muted);margin-top:2px}
.lp-foot{position:relative;z-index:1}
.other-portals{display:flex;gap:9px;margin-bottom:12px}
.op{padding:6px 12px;border-radius:8px;border:1px solid var(--border2);background:var(--white);font-size:.69rem;font-weight:600;color:var(--muted2);transition:all .2s}
.op:hover{border-color:var(--a);color:var(--a)}
.foot-txt{font-size:.66rem;color:var(--muted)}
.rp{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 32px}
.fb{width:100%;max-width:400px;animation:fadeUp .45s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.step-dots{display:flex;gap:7px;align-items:center;margin-bottom:32px}
.sd{width:8px;height:8px;border-radius:50%;background:var(--border2);transition:all .3s}
.sd.on{background:var(--a);box-shadow:0 0 8px rgba(230,81,0,.35);width:22px;border-radius:4px}
.sd.done{background:var(--a2)}
.sd-lbl{font-size:.67rem;color:var(--muted);margin-left:6px}
.eyebrow{font-size:.63rem;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:var(--a);margin-bottom:9px}
.title{font-size:2rem;font-weight:900;letter-spacing:-1px;line-height:1.1;margin-bottom:7px;color:var(--text)}
.sub{font-size:.82rem;color:var(--muted2);margin-bottom:26px;line-height:1.6}
.sub a{color:var(--a);font-weight:600}
.fg{margin-bottom:15px}
.fl{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.fl label{font-size:.65rem;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--muted2)}
.fl a{font-size:.72rem;color:var(--a);font-weight:600}
.fi{width:100%;padding:12px 15px;background:var(--white);border:1.5px solid var(--border);border-radius:11px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;color:var(--text);outline:none;transition:all .25s;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.fi::placeholder{color:var(--muted)}
.fi:focus{border-color:var(--a2);box-shadow:0 0 0 3px rgba(255,109,0,.1)}
.fi-w{position:relative}.fi-w .fi{padding-right:44px}
.eye{position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.88rem;padding:0;transition:color .2s}.eye:hover{color:var(--a)}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,var(--a),var(--a2));color:#f0f4ff;border:none;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:.9rem;letter-spacing:.5px;cursor:pointer;transition:all .25s;margin-top:4px;box-shadow:0 4px 20px var(--shadow)}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 26px rgba(230,81,0,.28)}
.back-btn{background:none;border:none;color:var(--muted);font-size:.78rem;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;padding:0;margin-bottom:22px;display:inline-flex;align-items:center;gap:5px;transition:color .2s}.back-btn:hover{color:var(--a)}
.alert{padding:10px 13px;border-radius:10px;font-size:.79rem;margin-bottom:14px;line-height:1.5;border:1px solid transparent}
.a-err{background:var(--red-g);border-color:rgba(198,40,40,.15);color:var(--red)}
.a-ok{background:var(--green-g);border-color:rgba(46,125,50,.15);color:var(--green)}
.alert a{font-weight:700;color:inherit;text-decoration:underline}
.otp-badge{background:var(--a3);border:1px solid rgba(255,109,0,.2);border-radius:11px;padding:11px 15px;margin-bottom:18px;display:flex;align-items:center;gap:9px;font-size:.79rem;color:var(--muted2)}
.otp-badge strong{color:var(--text);font-weight:600}
.dev-box{background:#fffbeb;border:1.5px dashed #f59e0b;border-radius:11px;padding:13px;margin-bottom:16px;text-align:center}
.dev-lbl{font-size:.59rem;font-weight:700;color:#92400e;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:5px}
.dev-code{font-size:2.4rem;font-weight:900;color:#d97706;letter-spacing:12px}
.dev-hint{font-size:.61rem;color:rgba(217,119,6,.7);margin-top:3px}
.otp-row{display:flex;justify-content:center;gap:9px;margin-bottom:20px}
.ob{width:48px;height:56px;text-align:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:1.45rem;font-weight:800;border:1.5px solid var(--border);border-radius:11px;background:var(--white);outline:none;transition:all .25s;color:var(--text)}
.ob:focus{border-color:var(--a2);box-shadow:0 0 0 3px rgba(255,109,0,.1)}
.resend{text-align:center;font-size:.77rem;color:var(--muted);margin-top:12px}
.resend button{background:none;border:none;color:var(--a);font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:inherit}
.resend button:disabled{color:var(--muted);cursor:default}
.bottom{text-align:center;margin-top:20px;font-size:.79rem;color:var(--muted)}
.bottom a{color:var(--a);font-weight:600}
@media(max-width:820px){.lp{display:none}.rp{padding:28px 16px}html,body{overflow:auto}}

/* ── LIGHT THEME OVERRIDES ── */
html,body{background:var(--bg);color:var(--text)}
.bg-canvas .m1{background:radial-gradient(circle,rgba(24,207,180,.10),transparent)}
.bg-canvas .m2{background:radial-gradient(circle,rgba(109,229,210,.08),transparent)}
.bg-canvas .m3{background:radial-gradient(circle,rgba(206,247,242,.5),transparent)}
.dots{background-image:radial-gradient(rgba(4,46,42,.06) 1px,transparent 1px) !important}
.topbar,.nav{background:rgba(255,255,255,.88) !important;backdrop-filter:blur(20px);border-bottom:1px solid var(--border) !important;box-shadow:0 1px 12px rgba(4,46,42,.08) !important;color:var(--text) !important;}
.sidebar{background:linear-gradient(180deg,#042e2a,#065449) !important;border-right:1px solid rgba(4,46,42,.2) !important;}
.sidebar *,.nav-a,.sb-logo,.sb-name,.sb-role{color:#ffffff !important}
.nav-a.on{background:rgba(255,255,255,.18) !important;border-color:rgba(255,255,255,.3) !important;color:#fff !important}
.nav-a:hover{background:rgba(255,255,255,.1) !important}
.card,.sc,.detail-card,.map-card,.all-table,.notice,.u-card,.feat,.al,.officer-note,.nd-modal{background:#ffffff !important;border:1px solid var(--border) !important;box-shadow:0 2px 12px rgba(4,46,42,.08),inset 0 1px 0 rgba(255,255,255,.9) !important;}
.card{border-top:1.5px solid rgba(4,46,42,.25) !important}
.sc:hover,.card:hover{box-shadow:0 6px 24px rgba(4,46,42,.14),0 1px 4px rgba(4,46,42,.08) !important;transform:translateY(-2px);}
.lp{background:linear-gradient(160deg,#042e2a 0%,#065449 60%,#18cfb4 100%) !important;border-right:1px solid rgba(255,255,255,.12) !important;}
.lp,.lp *,.lp .hl,.lp .hl-sub,.lp .logo-n,.lp .logo-t,.lp .foot-txt,.lp .role-badge,.lp .duty-n,.lp .duty-d,.lp .stat-l,.lp .stat-n,.lp .op,.lp .lp-foot *{color:#ffffff !important}
.lp .role-badge{background:rgba(255,255,255,.15) !important;border-color:rgba(255,255,255,.25) !important}
.lp .duty{background:rgba(255,255,255,.1) !important;border-color:rgba(255,255,255,.15) !important}
.lp .duty:hover{background:rgba(255,255,255,.18) !important;border-color:rgba(255,255,255,.3) !important}
.lp .op{background:rgba(255,255,255,.12) !important;border-color:rgba(255,255,255,.2) !important}
.lp .op:hover{background:rgba(255,255,255,.22) !important}
.lp .hl em{color:#adf2e8 !important}
.lp .stat-n{color:#adf2e8 !important}
.rp{background:transparent !important}
input.fi,select.fi,textarea.fi,.fi,.ob,.search-input{background:#ffffff !important;border:1.5px solid var(--border) !important;color:#042e2a !important;box-shadow:0 1px 3px rgba(4,46,42,.06) !important;}
input.fi::placeholder,textarea.fi::placeholder{color:var(--muted) !important}
input.fi:focus,select.fi:focus,textarea.fi:focus,.fi:focus,.ob:focus,.search-input:focus{border-color:rgba(4,46,42,.5) !important;box-shadow:0 0 0 3px rgba(4,46,42,.1),0 1px 3px rgba(4,46,42,.06) !important;background:#ffffff !important;}
.btn,.btn-submit,.tb-btn,.search-btn,.dc-btn-primary,.nd-act-primary,.demo-btn{background:linear-gradient(135deg,#042e2a,#18cfb4) !important;color:#ffffff !important;box-shadow:0 4px 14px rgba(4,46,42,.25) !important;border:none !important;}
.btn:hover,.btn-submit:hover,.tb-btn:hover,.search-btn:hover,.demo-btn:hover{box-shadow:0 8px 24px rgba(4,46,42,.35) !important;transform:translateY(-2px) !important;}
.ch-act,.np-mark-btn,.dc-btn-ghost,.nd-act-sec,.g-btn{background:#ffffff !important;border:1.5px solid var(--border) !important;color:var(--text) !important;}
.ch-act:hover,.np-mark-btn:hover,.g-btn:hover{border-color:rgba(4,46,42,.4) !important;background:#f0f4ff !important;}
.otp-badge{background:rgba(4,46,42,.07) !important;border-color:var(--border) !important;color:var(--muted2) !important}
.dev-box{background:#fffbeb !important;border-color:#f59e0b !important}
.a-err,.t-err{background:rgba(220,38,38,.07) !important;border-color:rgba(220,38,38,.2) !important;color:#dc2626 !important}
.a-ok,.t-ok{background:rgba(5,150,105,.07) !important;border-color:rgba(5,150,105,.2) !important;color:#059669 !important}
.sd{background:rgba(4,46,42,.2) !important}
.sd.on{background:#042e2a !important;box-shadow:0 0 8px rgba(4,46,42,.3) !important}
.sd.done{background:#18cfb4 !important}
::-webkit-scrollbar-track{background:#e8eeff}
::-webkit-scrollbar-thumb{background:rgba(4,46,42,.25)}
/* ── ADDED: demo button + divider (identical to citizen login) ── */
.demo-btn{width:100%;padding:12px 18px;background:linear-gradient(135deg,var(--a),var(--a2));border:none;border-radius:12px;display:flex;align-items:center;justify-content:center;gap:10px;font-size:.84rem;font-weight:700;color:#f0f4ff;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .25s;margin-bottom:12px;box-shadow:0 4px 20px var(--shadow);text-decoration:none;}
.demo-btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(16,158,136,.35)}
.divider{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.divider span{flex:1;height:1px;background:var(--border)}
.divider p{font-size:.63rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px}
</style></head><body>
<div class="bg-canvas"><div class="mesh m1"></div><div class="mesh m2"></div><div class="mesh m3"></div></div>
<div class="dots"></div>
<div class="wrap">
<div class="lp">
  <div class="watermark">O</div>
  <div class="lp-top">
    <div class="logo-row"><div class="logo-mark">🏛️</div><div><div class="logo-n">Nagrik Seva</div><div class="logo-t">Goa Civic Portal · 2026</div></div></div>
    <div class="role-badge">🏢 Officer Portal</div>
    <div class="hl">Act fast.<br>Resolve<em>every issue.</em></div>
    <div class="hl-sub">Officers are the backbone of civic resolution. Receive complaints, update statuses, and close issues efficiently.</div>
    <div class="duties">
      <div class="duty"><div class="duty-ico d1">📋</div><div><div class="duty-n">Review assigned complaints</div><div class="duty-d">See all complaints in your zone</div></div></div>
      <div class="duty"><div class="duty-ico d2">🔧</div><div><div class="duty-n">Update resolution status</div><div class="duty-d">Mark in-progress or resolved</div></div></div>
      <div class="duty"><div class="duty-ico d3">✅</div><div><div class="duty-n">Close verified complaints</div><div class="duty-d">Confirm resolution with notes</div></div></div>
    </div>
  </div>
  <div class="lp-foot">
    <div class="other-portals"><a href="citizen_login.php" class="op">👤 Citizen Login</a><a href="regulator_login.php" class="op">⚖️ Regulator Login</a></div>
    <div class="foot-txt">© 2026 Government of Goa · Nagrik Seva</div>
  </div>
</div>
<div class="rp"><div class="fb">
<?php if ($mode==='password'): ?>
<div class="step-dots"><div class="sd on"></div><div class="sd"></div><span class="sd-lbl">Step 1 of 2</span></div>
<div class="eyebrow">Officer Portal</div>
<div class="title">Officer Sign In</div>
<div class="sub">Your account is created by admin. Contact support if you need access.</div>
<?php if ($error): ?><div class="alert a-err">⚠ <?= $error ?></div><?php endif; ?>

<!-- ADDED: Demo quick login button (same as citizen login) -->
<a href="officer_login.php?demo=1" class="demo-btn">
  ⚡ &nbsp;Quick Demo Login — Skip to Dashboard
</a>
<div class="divider"><span></span><p>or sign in manually</p><span></span></div>

<form method="POST">
  <input type="hidden" name="action" value="login_password">
  <div class="fg"><div class="fl"><label>Official Email</label></div><input class="fi" type="email" name="email" placeholder="officer@nagrikseva.gov" value="<?= htmlspecialchars($_POST['email']??'') ?>" required autofocus></div>
  <div class="fg"><div class="fl"><label>Password</label><a href="forgot_password.php">Forgot?</a></div><div class="fi-w"><input class="fi" type="password" id="pw" name="password" placeholder="Your password" required><button type="button" class="eye" onclick="toggleEye('pw')">👁</button></div></div>
  <button type="submit" class="btn">Continue →</button>
</form>
<div class="bottom">Not an officer? <a href="citizen_login.php">Citizen login →</a></div>

<?php elseif ($mode==='otp_verify'): ?>
<div class="step-dots"><div class="sd done"></div><div class="sd on"></div><span class="sd-lbl">Step 2 of 2</span></div>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="cancel_otp"><button type="submit" class="back-btn">← Back</button></form>
<div class="eyebrow">Verification</div>
<div class="title">Enter code</div>
<div class="sub">6-digit OTP sent to your official email.</div>
<div class="otp-badge">📧 Sent to <strong>&nbsp;<?= htmlspecialchars($_SESSION['otp_pending_email']??'') ?></strong></div>
<?php if (!empty($_SESSION['dev_otp'])): ?><div class="dev-box"><div class="dev-lbl">🛠 Dev Mode</div><div class="dev-code"><?= $_SESSION['dev_otp'] ?></div><div class="dev-hint">Enter this below</div></div><?php endif; ?>
<?php if ($error): ?><div class="alert a-err">⚠ <?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert a-ok">✅ <?= $success ?></div><?php endif; ?>
<form method="POST" id="otp-form" onsubmit="submitOtp(event)">
  <input type="hidden" name="action" value="verify_otp"><input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['otp_pending_email']??'') ?>"><input type="hidden" name="otp" id="otp-val">
  <div class="otp-row"><?php for($i=0;$i<6;$i++): ?><input class="ob" type="text" maxlength="1" inputmode="numeric" autocomplete="off" id="ob<?=$i?>"><?php endfor; ?></div>
  <button type="submit" class="btn">Verify &amp; Sign In →</button>
</form>
<div class="resend">Didn't get it? <button id="rbtn" onclick="resendOtp()" disabled>Resend (<span id="cd">60</span>s)</button></div>
<?php endif; ?>
</div></div>
</div>
<script>
function toggleEye(id){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}
document.querySelectorAll('.ob').forEach((b,i,all)=>{b.addEventListener('input',e=>{e.target.value=e.target.value.replace(/\D/,'');if(e.target.value&&i<5)all[i+1].focus();});b.addEventListener('keydown',e=>{if(e.key==='Backspace'&&!b.value&&i>0)all[i-1].focus();});b.addEventListener('paste',e=>{e.preventDefault();const p=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);[...p].forEach((c,j)=>{if(all[j])all[j].value=c;});all[Math.min(p.length,5)].focus();});});
function submitOtp(e){const d=[...document.querySelectorAll('.ob')].map(b=>b.value).join('');if(d.length<6){e.preventDefault();alert('Enter all 6 digits.');return;}document.getElementById('otp-val').value=d;}
(function(){const btn=document.getElementById('rbtn'),cd=document.getElementById('cd');if(!btn)return;let s=60;const t=setInterval(()=>{s--;cd.textContent=s;if(s<=0){clearInterval(t);btn.disabled=false;btn.innerHTML='Resend OTP';}},1000);})();
function resendOtp(){const f=document.createElement('form');f.method='POST';f.innerHTML='<input type="hidden" name="action" value="cancel_otp">';document.body.appendChild(f);f.submit();}
const ob0=document.getElementById('ob0');if(ob0)ob0.focus();
</script>
</body></html>