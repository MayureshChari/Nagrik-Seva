<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── GOOGLE OAUTH CALLBACK (POST from GSI) ──────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'google_login') {
    $id_token = trim($_POST['credential'] ?? '');
    if ($id_token) {
        $verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $resp = @file_get_contents($verify_url, false, $ctx);
        $payload = $resp ? json_decode($resp, true) : null;

        $google_client_id = 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com';

        if ($payload && isset($payload['email'])
            && ($payload['aud'] === $google_client_id)
            && in_array($payload['iss'], ['accounts.google.com','https://accounts.google.com'])
        ) {
            $gemail = strtolower($payload['email']);
            $gname  = $payload['name'] ?? $gemail;
            handleGoogleUser($conn, $gemail, $gname);
        } else {
            $error = 'Google sign-in failed. Invalid token. Please try again.';
        }
    } else {
        $error = 'Google sign-in failed. No credential received.';
    }
}

// ── GOOGLE OAUTH via redirect + userinfo API ───────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'google_userinfo') {
    $gemail = strtolower(trim($_POST['g_email'] ?? ''));
    $gname  = trim($_POST['g_name']  ?? '');
    $gsub   = trim($_POST['g_sub']   ?? '');
    if ($gemail && $gsub) {
        handleGoogleUser($conn, $gemail, $gname);
    } else {
        $error = 'Google sign-in failed. Could not retrieve account info.';
    }
}

function handleGoogleUser($conn, $gemail, $gname) {
    $st = $conn->prepare("SELECT id,name,role,is_active FROM users WHERE email=? AND role='citizen' LIMIT 1");
    $st->bind_param('s', $gemail); $st->execute();
    $u = $st->get_result()->fetch_assoc(); $st->close();

    if ($u && $u['is_active']) {
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['name']    = $u['name'];
        $_SESSION['email']   = $gemail;
        $_SESSION['role']    = 'citizen';
        $conn->query("UPDATE users SET last_login=NOW() WHERE id=".(int)$u['id']);
        header('Location: citizen_dashboard.php'); exit;
    } elseif (!$u) {
        header('Location: citizen_register.php?google_email=' . urlencode($gemail) . '&google_name=' . urlencode($gname));
        exit;
    } else {
        global $error;
        $error = 'Your Google account is linked to an inactive citizen account. Contact support.';
    }
}

// ── SEND OTP EMAIL via PHPMailer + Gmail SMTP ──────────────────────────────
function sendOtpEmail($to_email, $to_name, $otp) {

    // ✏️  Fill in YOUR Gmail address and App Password below
    $smtp_user     = 'mayureshchari05@gmail.com';       // ← your Gmail address
    $smtp_password = 'mtoo iicq yyin uiid';  // ← 16-char Gmail App Password

    $html_body = '
    <!DOCTYPE html>
    <html>
    <body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:30px;">
      <div style="max-width:480px;margin:auto;background:#fff;border-radius:10px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <h2 style="color:#1a1a2e;margin-bottom:6px;">Nagrik Seva</h2>
        <p style="color:#555;">Hi <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
        <p style="color:#555;">Use the code below to sign in. It expires in <strong>10 minutes</strong>.</p>
        <div style="text-align:center;margin:28px 0;">
          <span style="display:inline-block;font-size:36px;font-weight:700;letter-spacing:10px;color:#2563eb;background:#eff6ff;padding:16px 28px;border-radius:8px;">' . $otp . '</span>
        </div>
        <p style="color:#888;font-size:13px;">If you did not request this, please ignore this email.</p>
        <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
        <p style="color:#aaa;font-size:12px;">Nagrik Seva · Citizen Services Portal</p>
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
        $mail->Subject = 'Your Nagrik Seva Login OTP';
        $mail->Body    = $html_body;
        $mail->AltBody = "Your OTP is: $otp  (expires in 10 minutes)";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Store error in session so we can display it on screen for debugging
        $_SESSION['mail_error'] = $mail->ErrorInfo;
        return false;
    }
}

if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'citizen') {
    header('Location: citizen_dashboard.php'); exit;
}

// ── DEMO QUICK LOGIN — triggered when logo is clicked ──────
if (isset($_GET['demo'])) {
    $demo_email = 'citizen@demo.com';
    $st = $conn->prepare("SELECT id,name,role,is_active FROM users WHERE email=? AND role='citizen' LIMIT 1");
    $st->bind_param('s', $demo_email);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();
    if ($u && $u['is_active']) {
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['name']    = $u['name'];
        $_SESSION['email']   = $demo_email;
        $_SESSION['role']    = 'citizen';
        $conn->query("UPDATE users SET last_login=NOW() WHERE id=".(int)$u['id']);
        header('Location: citizen_dashboard.php'); exit;
    }
}

$error = $success = '';
$mode  = isset($_SESSION['otp_pending_email']) ? 'otp_verify' : 'password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $email  = strtolower(trim($_POST['email'] ?? ''));

    if ($action === 'login_password') {
        $pw = $_POST['password'] ?? '';
        if (!$email || !$pw) { $error = 'Please fill in both fields.'; }
        else {
            $st = $conn->prepare("SELECT id,name,role,password_hash,is_active FROM users WHERE email=? AND role='citizen' LIMIT 1");
            $st->bind_param('s',$email); $st->execute();
            $u = $st->get_result()->fetch_assoc(); $st->close();
            if (!$u)                  $error = 'No citizen account found with that email.';
            elseif (!$u['is_active']) $error = 'Account inactive. Contact support.';
            elseif (!password_verify($pw,$u['password_hash'])) $error = 'Incorrect password.';
            else {
                $otp = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
                $exp = date('Y-m-d H:i:s',strtotime('+10 minutes'));
                $ins = $conn->prepare("INSERT INTO otp_tokens(email,otp,expires_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE otp=?,expires_at=?");
                $ins->bind_param('sssss',$email,$otp,$exp,$otp,$exp); $ins->execute(); $ins->close();
                $_SESSION['otp_pending_email']=$email; $_SESSION['otp_pending_uid']=$u['id'];
                $_SESSION['otp_pending_name']=$u['name']; $_SESSION['otp_pending_role']=$u['role'];

                // ── SEND OTP TO EMAIL ──────────────────────────────────────
                $mail_sent = sendOtpEmail($email, $u['name'], $otp);
                if ($mail_sent) {
                    $mode = 'otp_verify';
                    $success = 'A 6-digit OTP has been sent to <strong>' . htmlspecialchars($email) . '</strong>. It expires in 10 minutes.';
                } else {
                    // Fallback: show OTP on screen if mail fails (remove in production)
                    $_SESSION['dev_otp'] = $otp;
                    $mode = 'otp_verify';
                    $mail_err = $_SESSION['mail_error'] ?? 'Unknown error'; unset($_SESSION['mail_error']); $success = 'OTP generated but email failed 2014 check dev box below.<br><small style="color:red">D83dDce7 Mail error: ' . htmlspecialchars($mail_err) . '</small>';
                }
            }
        }
    }
    elseif ($action === 'verify_otp') {
        $mode='otp_verify'; $otp_i=trim($_POST['otp'] ?? ''); $omail=$_SESSION['otp_pending_email'] ?? '';
        if (strlen($otp_i)<6) $error='Enter all 6 digits.';
        elseif (!$omail) { $error='Session expired. <a href="citizen_login.php">Start again</a>'; $mode='password'; }
        else {
            $st=$conn->prepare("SELECT otp,expires_at FROM otp_tokens WHERE email=? ORDER BY created_at DESC LIMIT 1");
            $st->bind_param('s',$omail); $st->execute(); $tok=$st->get_result()->fetch_assoc(); $st->close();
            if (!$tok)                                    $error='No OTP found. <a href="citizen_login.php">Start again</a>';
            elseif (strtotime($tok['expires_at'])<time()) $error='OTP expired. <a href="citizen_login.php">Start again</a>';
            elseif ($tok['otp']!==$otp_i)                 $error='Wrong code. Try again.';
            else {
                $del=$conn->prepare("DELETE FROM otp_tokens WHERE email=?");
                $del->bind_param('s',$omail); $del->execute(); $del->close();
                $_SESSION['user_id']=$_SESSION['otp_pending_uid']; $_SESSION['name']=$_SESSION['otp_pending_name'];
                $_SESSION['email']=$omail; $_SESSION['role']='citizen';
                $conn->query("UPDATE users SET last_login=NOW() WHERE id=".(int)$_SESSION['user_id']);
                unset($_SESSION['otp_pending_email'],$_SESSION['otp_pending_uid'],$_SESSION['otp_pending_name'],$_SESSION['otp_pending_role'],$_SESSION['dev_otp']);
                header('Location: citizen_dashboard.php'); exit;
            }
        }
    }
    elseif ($action==='cancel_otp') {
        unset($_SESSION['otp_pending_email'],$_SESSION['otp_pending_uid'],$_SESSION['otp_pending_name'],$_SESSION['otp_pending_role'],$_SESSION['dev_otp']);
        header('Location: citizen_login.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Citizen Login — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://accounts.google.com/gsi/client" async defer></script>
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
.bg-canvas{position:fixed;inset:0;z-index:0;overflow:hidden}
.mesh{position:absolute;border-radius:50%;filter:blur(70px);animation:drift 18s ease-in-out infinite alternate}
.m1{width:600px;height:600px;background:radial-gradient(circle,rgba(24,207,180,.10),transparent);top:-100px;left:-80px}
.m2{width:500px;height:500px;background:radial-gradient(circle,rgba(109,229,210,.12),transparent);bottom:-80px;right:-60px;animation-delay:-7s}
.m3{width:350px;height:350px;background:radial-gradient(circle,rgba(206,247,242,.6),transparent);top:40%;left:45%;animation-delay:-3s}
@keyframes drift{0%{transform:translate(0,0)}100%{transform:translate(20px,14px)}}
.dots{position:fixed;inset:0;z-index:0;background-image:radial-gradient(rgba(4,46,42,.05) 1px,transparent 1px);background-size:28px 28px}
.wrap{position:relative;z-index:1;display:flex;width:100%;min-height:100vh}

/* LEFT */
.lp{width:50%;display:flex;flex-direction:column;justify-content:space-between;padding:52px 56px;border-right:1px solid var(--border);overflow:hidden;flex-shrink:0;position:relative}
.lp::after{content:'';position:absolute;top:0;right:0;bottom:0;width:1px;background:linear-gradient(to bottom,transparent,var(--a2),transparent)}
.watermark{position:absolute;font-size:20rem;font-weight:900;color:rgba(24,207,180,.05);right:-40px;top:50%;transform:translateY(-50%);pointer-events:none;user-select:none;letter-spacing:-15px;line-height:1}
.lp-top{position:relative;z-index:1}

/* LOGO — clickable for demo login */
.logo-row{display:flex;align-items:center;gap:13px;margin-bottom:64px;text-decoration:none;cursor:pointer;}
.logo-row:hover .logo-mark{box-shadow:0 6px 28px var(--shadow);transform:scale(1.05);}
.logo-mark{width:44px;height:44px;background:linear-gradient(135deg,var(--a),var(--a2));border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 4px 20px var(--shadow);position:relative;flex-shrink:0;transition:all .2s;}
.logo-mark::after{content:'';position:absolute;inset:-3px;border-radius:15px;border:1.5px solid rgba(24,207,180,.3);animation:pr 2.5s ease-in-out infinite}
@keyframes pr{0%,100%{opacity:.7}50%{opacity:.15}}
.logo-n{font-size:1rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text)}
.logo-t{font-size:.6rem;color:var(--muted);letter-spacing:.4px;margin-top:2px}
.logo-demo-hint{font-size:.55rem;color:var(--a);font-weight:600;letter-spacing:.3px;margin-top:3px;opacity:.7}

.role-badge{display:inline-flex;align-items:center;gap:7px;padding:6px 14px;border-radius:20px;background:var(--ag);border:1.5px solid rgba(16,158,136,.2);font-size:.65rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--a);margin-bottom:18px}
.role-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--a);animation:blink 1.5s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
.hl{font-size:clamp(2.4rem,3.5vw,3.4rem);font-weight:900;line-height:1.0;letter-spacing:-2px;margin-bottom:18px;color:var(--text)}
.hl em{font-style:normal;color:var(--a);display:block}
.hl-sub{font-size:.88rem;color:var(--muted2);line-height:1.8;max-width:330px;margin-bottom:48px}
.stats{display:flex;gap:28px}
.stat-n{font-size:1.8rem;font-weight:900;color:var(--a);letter-spacing:-1px}
.stat-l{font-size:.68rem;color:var(--muted);margin-top:2px;font-weight:500}
.lp-foot{position:relative;z-index:1;font-size:.67rem;color:var(--muted);letter-spacing:.3px}
.other-portals{display:flex;gap:10px;margin-bottom:14px}
.op{padding:6px 12px;border-radius:8px;border:1px solid var(--border2);background:var(--white);font-size:.7rem;font-weight:600;color:var(--muted2);text-decoration:none;transition:all .2s}
.op:hover{border-color:var(--a);color:var(--a);background:var(--a3)}

/* RIGHT */
.rp{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 32px}
.fb{width:100%;max-width:400px;animation:fadeUp .45s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.step-dots{display:flex;gap:7px;align-items:center;margin-bottom:32px}
.sd{width:8px;height:8px;border-radius:50%;background:var(--border2);transition:all .3s}
.sd.on{background:var(--a);box-shadow:0 0 8px rgba(16,158,136,.4);width:22px;border-radius:4px}
.sd.done{background:var(--a2)}
.sd-lbl{font-size:.67rem;color:var(--muted);margin-left:6px}
.eyebrow{font-size:.63rem;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:var(--a);margin-bottom:9px}
.title{font-size:2rem;font-weight:900;letter-spacing:-1px;line-height:1.1;margin-bottom:7px;color:var(--text)}
.sub{font-size:.82rem;color:var(--muted2);margin-bottom:26px;line-height:1.6}
.sub a{color:var(--a);font-weight:600;transition:opacity .2s}.sub a:hover{opacity:.7}

/* ⚡ DEMO CHEAT CODE BUTTON */
.demo-btn{
  width:100%;padding:12px 18px;
  background:linear-gradient(135deg,var(--a),var(--a2));
  border:none;border-radius:12px;
  display:flex;align-items:center;justify-content:center;gap:10px;
  font-size:.84rem;font-weight:700;color:#f0f4ff;
  cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;
  transition:all .25s;margin-bottom:12px;
  box-shadow:0 4px 20px var(--shadow);
  text-decoration:none;
}
.demo-btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(16,158,136,.35)}

.g-btn{width:100%;padding:12px 18px;background:var(--white);border:1.5px solid var(--border2);border-radius:12px;display:flex;align-items:center;justify-content:center;gap:10px;font-size:.84rem;font-weight:600;color:var(--text);cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .25s;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.g-btn:hover{border-color:var(--a2);box-shadow:0 4px 16px var(--shadow);transform:translateY(-1px)}
.divider{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.divider span{flex:1;height:1px;background:var(--border)}
.divider p{font-size:.63rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px}
.fg{margin-bottom:15px}
.fl{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.fl label{font-size:.65rem;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--muted2)}
.fl a{font-size:.72rem;color:var(--a);font-weight:600;transition:opacity .2s}.fl a:hover{opacity:.7}
.fi{width:100%;padding:12px 15px;background:var(--white);border:1.5px solid var(--border);border-radius:11px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;color:var(--text);outline:none;transition:all .25s;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.fi::placeholder{color:var(--muted)}
.fi:focus{border-color:var(--a2);box-shadow:0 0 0 3px rgba(24,207,180,.12)}
.fi-w{position:relative}.fi-w .fi{padding-right:44px}
.eye{position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.88rem;padding:0;transition:color .2s}.eye:hover{color:var(--a)}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,var(--a),var(--a2));color:#f0f4ff;border:none;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:.9rem;letter-spacing:.5px;cursor:pointer;transition:all .25s;margin-top:4px;box-shadow:0 4px 20px var(--shadow)}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(16,158,136,.3)}
.back-btn{background:none;border:none;color:var(--muted);font-size:.78rem;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;padding:0;margin-bottom:22px;display:inline-flex;align-items:center;gap:5px;transition:color .2s}.back-btn:hover{color:var(--a)}
.alert{padding:10px 13px;border-radius:10px;font-size:.79rem;margin-bottom:14px;line-height:1.5;border:1px solid transparent}
.a-err{background:var(--red-g);border-color:rgba(229,57,53,.15);color:var(--red)}
.a-ok{background:var(--green-g);border-color:rgba(0,137,123,.15);color:var(--green)}
.alert a{font-weight:700;color:inherit;text-decoration:underline}
.otp-badge{background:var(--a3);border:1px solid rgba(24,207,180,.25);border-radius:11px;padding:11px 15px;margin-bottom:18px;display:flex;align-items:center;gap:9px;font-size:.79rem;color:var(--muted2)}
.otp-badge strong{color:var(--text);font-weight:600}
.dev-box{background:#fffbeb;border:1.5px dashed #f59e0b;border-radius:11px;padding:13px;margin-bottom:16px;text-align:center}
.dev-lbl{font-size:.59rem;font-weight:700;color:#92400e;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:5px}
.dev-code{font-size:2.4rem;font-weight:900;color:#d97706;letter-spacing:12px}
.dev-hint{font-size:.61rem;color:rgba(217,119,6,.7);margin-top:3px}
.otp-row{display:flex;justify-content:center;gap:9px;margin-bottom:20px}
.ob{width:48px;height:56px;text-align:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:1.45rem;font-weight:800;border:1.5px solid var(--border);border-radius:11px;background:var(--white);outline:none;transition:all .25s;color:var(--text);box-shadow:0 1px 4px rgba(0,0,0,.04)}
.ob:focus{border-color:var(--a2);box-shadow:0 0 0 3px rgba(24,207,180,.12)}
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
.a-info{background:rgba(4,46,42,.07) !important;border-color:var(--border) !important;color:var(--text) !important}
.sc-ico-a{background:rgba(4,46,42,.1) !important;border-color:rgba(4,46,42,.2) !important}
.sc-ico-b{background:rgba(24,207,180,.08) !important;border-color:rgba(24,207,180,.15) !important}
.sc-ico-c{background:rgba(109,229,210,.1) !important;border-color:rgba(109,229,210,.18) !important}
.sc-ico-d{background:rgba(5,150,105,.08) !important;border-color:rgba(5,150,105,.15) !important}
.progress-bar{background:rgba(4,46,42,.1) !important}
.progress-fill{background:linear-gradient(90deg,#042e2a,#18cfb4) !important}
.cr,.at-row,.np-item{border-bottom-color:var(--border) !important}
.cr:hover,.at-row:hover,.np-item:hover{background:rgba(4,46,42,.04) !important}
.s-new{background:rgba(4,46,42,.1) !important;color:#042e2a !important}
.fchip{background:#ffffff !important;border-color:var(--border) !important;color:var(--muted2) !important}
.fchip.on,.fchip:hover{background:rgba(4,46,42,.1) !important;border-color:rgba(4,46,42,.3) !important;color:var(--text) !important}
.filter-bar,.filters-row{background:rgba(255,255,255,.9) !important;border-color:var(--border) !important}
.hero{background:linear-gradient(135deg,#042e2a 0%,#065449 50%,#18cfb4 100%) !important}
.hero *{color:#ffffff !important}
.search-card{background:rgba(255,255,255,.95) !important;box-shadow:0 -4px 20px rgba(4,46,42,.1) !important}
.qa{background:rgba(4,46,42,.07) !important;border-color:var(--border) !important;color:var(--text) !important}
.qa:hover{background:rgba(4,46,42,.14) !important;border-color:rgba(4,46,42,.3) !important}
.notif-panel{background:linear-gradient(180deg,#042e2a,#065449) !important;border-left:1px solid rgba(255,255,255,.15) !important}
.notif-panel *,.np-head *,.np-item *,.np-title,.np-msg,.np-time,.np-close{color:#ffffff !important}
.np-head{background:rgba(255,255,255,.1) !important;border-bottom:1px solid rgba(255,255,255,.15) !important}
.np-item:hover{background:rgba(255,255,255,.08) !important}
.np-mark-btn{background:rgba(255,255,255,.15) !important;border-color:rgba(255,255,255,.2) !important;color:#fff !important}
.np-close{background:rgba(255,255,255,.1) !important;border-color:rgba(255,255,255,.15) !important;color:#fff !important}
.notif-backdrop.on{background:rgba(4,46,42,.25) !important}
.modal,.overlay .modal,.nd-modal{background:#ffffff !important;border:1px solid var(--border) !important;box-shadow:0 24px 64px rgba(4,46,42,.18) !important;}
.overlay{background:rgba(4,46,42,.25) !important}
.del-btn-confirm{background:#dc2626 !important;color:#fff !important;box-shadow:0 4px 14px rgba(220,38,38,.25) !important}
.del-btn-cancel{background:#ffffff !important;border:1.5px solid var(--border) !important;color:var(--text) !important}
.cat-btn{background:#ffffff !important;border-color:var(--border) !important;color:var(--text) !important}
.cat-btn.sel,.cat-btn:hover{background:rgba(4,46,42,.1) !important;border-color:rgba(4,46,42,.3) !important}
.upzone{background:#f8faff !important;border-color:rgba(4,46,42,.25) !important}
.upzone:hover,.upzone.drag{background:rgba(4,46,42,.06) !important;border-color:rgba(4,46,42,.4) !important}
.gps-fi{background:#ffffff !important;border-color:var(--border) !important;color:var(--muted) !important}
.gps-fi:hover{border-color:rgba(4,46,42,.4) !important;color:var(--text) !important;background:#f0f4ff !important}
.profile-card,.p-card,.ps-sect,.ps,.pi-row{background:#ffffff !important;border-color:var(--border) !important}
.sd{background:rgba(4,46,42,.2) !important}
.sd.on{background:#042e2a !important;box-shadow:0 0 8px rgba(4,46,42,.3) !important}
.sd.done{background:#18cfb4 !important}
::-webkit-scrollbar-track{background:#e8eeff}
::-webkit-scrollbar-thumb{background:rgba(4,46,42,.25)}
.officer-note{background:#f8faff !important;border-color:var(--border) !important}
.leaflet-control{background:#ffffff !important;border-color:var(--border) !important;color:var(--text) !important}
.leaflet-control-attribution{background:rgba(255,255,255,.9) !important;color:var(--muted) !important}
.step-item.done .step-dot{background:#042e2a !important;border-color:#042e2a !important}
.step-item.current .step-dot{background:#18cfb4 !important;border-color:#18cfb4 !important;box-shadow:0 0 0 3px rgba(4,46,42,.15) !important}
.feat{background:#f8faff !important;border-color:var(--border) !important}
.feat:hover{background:#f0f4ff !important;border-color:rgba(4,46,42,.28) !important}
.feat-ico{background:rgba(4,46,42,.1) !important}
.notice,.es-tips{background:#f8faff !important;border-color:var(--border) !important}
.al{background:#f8faff !important;border-color:var(--border) !important}
.dc-officer-row.assigned{background:rgba(4,46,42,.06) !important;border-color:rgba(4,46,42,.15) !important}
.dc-officer-row.unassigned{background:rgba(217,119,6,.05) !important;border-color:rgba(217,119,6,.15) !important}
.sb{background:rgba(4,46,42,.1) !important}
</style>
</head>
<body>
<div class="bg-canvas"><div class="mesh m1"></div><div class="mesh m2"></div><div class="mesh m3"></div></div>
<div class="dots"></div>
<div class="wrap">

<!-- LEFT -->
<div class="lp">
  <div class="watermark">C</div>
  <div class="lp-top">

    <!-- LOGO — click to demo login instantly -->
    <a href="citizen_login.php?demo=1" class="logo-row">
      <div class="logo-mark">🏛️</div>
      <div>
        <div class="logo-n">Nagrik Seva</div>
        <div class="logo-t">Goa Civic Portal · 2026</div>
        <div class="logo-demo-hint">▶ Click to enter demo</div>
      </div>
    </a>

    <div class="role-badge">👤 Citizen Portal</div>
    <div class="hl">Your city.<br>Your<em>complaint.</em></div>
    <div class="hl-sub">File civic complaints, upload photo evidence, track resolution status — and hold your city accountable.</div>
    <div class="stats">
      <div><div class="stat-n">12K+</div><div class="stat-l">Active citizens</div></div>
      <div><div class="stat-n">3.4K</div><div class="stat-l">Issues resolved</div></div>
      <div><div class="stat-n">94%</div><div class="stat-l">Satisfaction rate</div></div>
    </div>
  </div>
  <div class="lp-foot">
    <div class="other-portals">
      <a href="officer_login.php" class="op">🏢 Officer Login</a>
      <a href="regulator_login.php" class="op">⚖️ Regulator Login</a>
    </div>
    © 2026 Government of Goa · Nagrik Seva
  </div>
</div>

<!-- RIGHT -->
<div class="rp"><div class="fb">

<?php if ($mode === 'password'): ?>
<div class="step-dots"><div class="sd on"></div><div class="sd"></div><span class="sd-lbl">Step 1 of 2</span></div>
<div class="eyebrow">Citizen Portal</div>
<div class="title">Welcome back</div>
<div class="sub">No account? <a href="citizen_register.php">Register free →</a></div>

<?php if (isset($_GET['registered'])): ?><div class="alert a-ok">✅ Account created! Sign in below.</div><?php endif; ?>
<?php if ($error): ?><div class="alert a-err">⚠ <?= $error ?></div><?php endif; ?>

<!-- ⚡ CHEAT CODE: instant demo login, skips email+OTP entirely -->
<a href="citizen_login.php?demo=1" class="demo-btn">
  ⚡ &nbsp;Quick Demo Login — Skip to Dashboard
</a>

<div class="divider"><span></span><p>or sign in manually</p><span></span></div>

<!-- Google GSI hidden init -->
<div id="g_id_onload"
  data-client_id="YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com"
  data-callback="handleGoogleSignIn"
  data-auto_prompt="false">
</div>

<!-- Real Google Sign-In button (styled to match your UI) -->
<button class="g-btn" type="button" id="g-signin-btn" onclick="triggerGoogleSignIn()">
  <svg width="18" height="18" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
  Continue with Google
</button>

<!-- Hidden form to POST Google credential to PHP -->
<form id="google-form" method="POST" action="citizen_login.php" style="display:none">
  <input type="hidden" name="action" value="google_login">
  <input type="hidden" name="credential" id="google-credential">
</form>

<form method="POST">
  <input type="hidden" name="action" value="login_password">
  <div class="fg"><div class="fl"><label>Email</label></div><input class="fi" type="email" name="email" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus></div>
  <div class="fg"><div class="fl"><label>Password</label><a href="forgot_password.php">Forgot?</a></div><div class="fi-w"><input class="fi" type="password" id="pw" name="password" placeholder="Your password" required><button type="button" class="eye" onclick="toggleEye('pw')">👁</button></div></div>
  <button type="submit" class="btn">Continue →</button>
</form>
<div class="bottom">New here? <a href="citizen_register.php">Create an account</a></div>

<?php elseif ($mode === 'otp_verify'): ?>
<div class="step-dots"><div class="sd done"></div><div class="sd on"></div><span class="sd-lbl">Step 2 of 2</span></div>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="cancel_otp"><button type="submit" class="back-btn">← Back</button></form>
<div class="eyebrow">Verification</div>
<div class="title">Enter code</div>
<div class="sub">6-digit OTP for your account.</div>
<div class="otp-badge">📧 Sent to <strong>&nbsp;<?= htmlspecialchars($_SESSION['otp_pending_email'] ?? '') ?></strong></div>
<?php if (!empty($_SESSION['dev_otp'])): ?>
<div class="dev-box">
  <div class="dev-lbl">🛠 Dev Mode — Your OTP</div>
  <div class="dev-code"><?= $_SESSION['dev_otp'] ?></div>
  <div class="dev-hint">Enter this in the boxes below</div>
</div>
<?php endif; ?>
<?php if ($error):   ?><div class="alert a-err">⚠ <?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert a-ok">✅ <?= $success ?></div><?php endif; ?>
<form method="POST" id="otp-form" onsubmit="submitOtp(event)">
  <input type="hidden" name="action" value="verify_otp">
  <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['otp_pending_email'] ?? '') ?>">
  <input type="hidden" name="otp" id="otp-val">
  <div class="otp-row"><?php for($i=0;$i<6;$i++): ?><input class="ob" type="text" maxlength="1" inputmode="numeric" autocomplete="off" id="ob<?=$i?>"><?php endfor; ?></div>
  <button type="submit" class="btn">Verify &amp; Sign In →</button>
</form>
<div class="resend">Didn't get it? <button id="rbtn" onclick="resendOtp()" disabled>Resend (<span id="cd">60</span>s)</button></div>
<?php endif; ?>

</div></div>
</div>

<script>
function toggleEye(id){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}

// ── REAL GOOGLE SIGN-IN — REDIRECT MODE (no popup, never blocked) ─────
function handleGoogleSignIn(response) {
  document.getElementById('google-credential').value = response.credential;
  document.getElementById('google-form').submit();
}

function triggerGoogleSignIn() {
  const btn = document.getElementById('g-signin-btn');
  btn.innerHTML = '<span>⏳</span> Redirecting to Google…';
  btn.disabled = true;

  // Hardcoded to avoid any dataset parsing issues
  const clientId = '17126270547-b5fj33buvbb85jtuljeveg1gmo8qhc8r.apps.googleusercontent.com';
  const redirectUri = 'http://localhost/innovatX/citizen_login.php';
  const scope = 'openid email profile';
  const state = Math.random().toString(36).substring(2);

  sessionStorage.setItem('oauth_state', state);

  const authUrl = 'https://accounts.google.com/o/oauth2/v2/auth'
    + '?client_id='     + encodeURIComponent(clientId)
    + '&redirect_uri='  + encodeURIComponent(redirectUri)
    + '&response_type=token'
    + '&scope='         + encodeURIComponent(scope)
    + '&state='         + encodeURIComponent(state)
    + '&prompt=select_account';

  window.location.href = authUrl;
}

// ── Handle OAuth redirect return (token in URL hash) ──────────────────
(function() {
  const hash = window.location.hash.substring(1);
  if (!hash) return;
  const params = Object.fromEntries(new URLSearchParams(hash));
  if (!params.access_token) return;

  // Got access token — fetch user info from Google
  const btn = document.getElementById('g-signin-btn');
  if (btn) { btn.innerHTML = '<span>⏳</span> Signing in…'; btn.disabled = true; }

  fetch('https://www.googleapis.com/oauth2/v3/userinfo', {
    headers: { 'Authorization': 'Bearer ' + params.access_token }
  })
  .then(r => r.json())
  .then(user => {
    // Build a minimal credential-like payload and POST to PHP
    const form = document.getElementById('google-form');
    // Switch action to use userinfo instead of id_token
    form.querySelector('[name=action]').value = 'google_userinfo';
    // Add user fields
    const addField = (n, v) => { const i = document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
    addField('g_email',   user.email   || '');
    addField('g_name',    user.name    || '');
    addField('g_sub',     user.sub     || '');
    addField('g_picture', user.picture || '');
    // Clean URL then submit
    history.replaceState(null, '', window.location.pathname);
    form.submit();
  })
  .catch(() => {
    if (btn) { btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg> Continue with Google'; btn.disabled = false; }
  });
})();
// 🎮 GTA-style cheat code — type "admin" anywhere on the page to go to admin.php
(function(){
  const code = 'admin';
  let buffer = '';
  document.addEventListener('keydown', function(e) {
    if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;
    buffer += e.key.toLowerCase();
    buffer = buffer.slice(-code.length);
    if (buffer === code) { window.location.href = 'admin.php'; }
  });
})();

document.querySelectorAll('.ob').forEach((b,i,all)=>{
  b.addEventListener('input',e=>{e.target.value=e.target.value.replace(/\D/,'');if(e.target.value&&i<5)all[i+1].focus();});
  b.addEventListener('keydown',e=>{if(e.key==='Backspace'&&!b.value&&i>0)all[i-1].focus();});
  b.addEventListener('paste',e=>{e.preventDefault();const p=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);[...p].forEach((c,j)=>{if(all[j])all[j].value=c;});all[Math.min(p.length,5)].focus();});
});
function submitOtp(e){const d=[...document.querySelectorAll('.ob')].map(b=>b.value).join('');if(d.length<6){e.preventDefault();alert('Enter all 6 digits.');return;}document.getElementById('otp-val').value=d;}
(function(){const btn=document.getElementById('rbtn'),cd=document.getElementById('cd');if(!btn)return;let s=60;const t=setInterval(()=>{s--;cd.textContent=s;if(s<=0){clearInterval(t);btn.disabled=false;btn.innerHTML='Resend OTP';}},1000);})();
function resendOtp(){const f=document.createElement('form');f.method='POST';f.innerHTML='<input type="hidden" name="action" value="cancel_otp">';document.body.appendChild(f);f.submit();}
const ob0=document.getElementById('ob0');if(ob0)ob0.focus();
</script>
</body>
</html>