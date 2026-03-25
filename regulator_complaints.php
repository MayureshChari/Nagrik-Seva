<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'regulator') {
    header('Location: regulator_login.php'); exit;
}

$uid      = (int)($_SESSION['user_id'] ?? 0);
$name     = $_SESSION['name'] ?? 'Adv. Meera Kamat';
$dept     = $_SESSION['dept'] ?? 'Goa Grievance Authority';
$initials = strtoupper(substr($name, 0, 1));
$first    = explode(' ', $name)[0];
$toast_ok = $toast_err = '';

// ── Only trust these params — strip redirect noise ─────────────────
$CLEAN = [];
foreach (['status','category','priority','q','sort','order','page'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') $CLEAN[$k] = $_GET[$k];
}
$f_status   = $CLEAN['status']   ?? 'all';
$f_category = $CLEAN['category'] ?? 'all';
$f_priority = $CLEAN['priority'] ?? 'all';
$search     = trim($CLEAN['q']   ?? '');
$page       = max(1, (int)($CLEAN['page'] ?? 1));
$per_page   = 15;

$allowed_sort  = ['created_at','updated_at','priority','status','title'];
$allowed_order = ['asc','desc'];
$sort  = in_array($CLEAN['sort']  ?? '', $allowed_sort)  ? $CLEAN['sort']  : 'created_at';
$order = in_array($CLEAN['order'] ?? '', $allowed_order) ? $CLEAN['order'] : 'desc';

// Clean URL builder — never leaks msg/type/other junk
function make_url(array $over = []): string {
    global $f_status,$f_category,$f_priority,$search,$sort,$order;
    $p = [];
    if ($f_status   !== 'all') $p['status']   = $f_status;
    if ($f_category !== 'all') $p['category'] = $f_category;
    if ($f_priority !== 'all') $p['priority'] = $f_priority;
    if ($search !== '')        $p['q']         = $search;
    if ($sort !== 'created_at') $p['sort']     = $sort;
    if ($order !== 'desc')      $p['order']    = $order;
    $m = array_merge($p, $over);
    if (($m['page']??1) == 1) unset($m['page']);
    if (($m['status']??'') === 'all') unset($m['status']);
    $qs = http_build_query($m);
    return 'regulator_complaints.php'.($qs?"?$qs":'');
}

// ── POST ACTIONS — always write to DB ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $cid    = (int)($_POST['complaint_id'] ?? 0);

    if ($cid <= 0) {
        $toast_err = 'Invalid complaint ID.';
    } else {
        if ($action === 'escalate') {
            $st = $conn->prepare("UPDATE complaints SET status='escalated', updated_at=NOW() WHERE id=? AND status NOT IN ('resolved','closed','escalated')");
            $st->bind_param('i', $cid); $st->execute();
            if ($st->affected_rows > 0) {
                $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message) SELECT officer_id,{$cid},'escalated','Complaint escalated by regulator. Immediate action required.' FROM complaints WHERE id={$cid} AND officer_id IS NOT NULL");
                $toast_ok = '🚨 Complaint escalated — officer notified.';
            } else {
                $toast_err = 'Cannot escalate — complaint is already escalated, resolved, or closed.';
            }
            $st->close();

        } elseif ($action === 'close') {
            $st = $conn->prepare("UPDATE complaints SET status='closed', updated_at=NOW() WHERE id=? AND status != 'closed'");
            $st->bind_param('i', $cid); $st->execute();
            if ($st->affected_rows > 0) {
                $toast_ok = '✅ Complaint closed.';
            } else {
                $toast_err = 'Complaint is already closed.';
            }
            $st->close();

        } elseif ($action === 'reopen') {
            $st = $conn->prepare("UPDATE complaints SET status='new', updated_at=NOW() WHERE id=? AND status IN ('closed','resolved')");
            $st->bind_param('i', $cid); $st->execute();
            if ($st->affected_rows > 0) {
                $toast_ok = '↩ Complaint reopened — status set to New.';
            } else {
                $toast_err = 'Cannot reopen — complaint is not closed or resolved.';
            }
            $st->close();

        } elseif ($action === 'reassign') {
            $noff = (int)($_POST['new_officer_id'] ?? 0);
            if ($noff > 0) {
                $st = $conn->prepare("UPDATE complaints SET officer_id=?, status='assigned', updated_at=NOW() WHERE id=?");
                $st->bind_param('ii', $noff, $cid); $st->execute();
                if ($st->affected_rows > 0) {
                    $conn->query("INSERT INTO notifications(user_id,complaint_id,type,message) VALUES({$noff},{$cid},'new_assignment','A complaint has been reassigned to you by the regulator.')");
                    $toast_ok = '👮 Complaint reassigned successfully.';
                } else {
                    $toast_err = 'Reassign failed — complaint not found.';
                }
                $st->close();
            } else {
                $toast_err = 'No officer selected. Please select an officer to reassign to.';
            }

        } else {
            $toast_err = 'Unknown action: ' . htmlspecialchars($action);
        }
    }

    $redir = make_url(['page' => $page]);
    $sep   = strpos($redir, '?') !== false ? '&' : '?';
    if ($toast_ok)  $redir .= $sep . 'msg=' . urlencode($toast_ok)  . '&type=ok';
    if ($toast_err) $redir .= $sep . 'msg=' . urlencode($toast_err) . '&type=err';
    header('Location: ' . $redir); exit;
}
if (isset($_GET['msg'])) {
    ($_GET['type']??'')==='ok' ? $toast_ok=htmlspecialchars($_GET['msg']) : $toast_err=htmlspecialchars($_GET['msg']);
}

// ── LOAD DATA — always from DB ─────────────────────────────────────
$complaints=[];$total_complaints=0;$officers_list=[];$use_dummy=false;

// Officers for reassign — detect which columns exist to avoid fatal errors
$_cols = [];
$_cr = $conn->query("SHOW COLUMNS FROM users");
if ($_cr) while ($_col = $_cr->fetch_assoc()) $_cols[] = $_col['Field'];
$_dept_sel   = in_array('department', $_cols) ? "COALESCE(department,'')" : "''";
$_zone_sel   = in_array('zone',       $_cols) ? "COALESCE(zone,'')"       : "''";
$_active_cnd = in_array('is_active',  $_cols) ? "AND (is_active IS NULL OR is_active=1)" : "";
$ro = $conn->query("SELECT id, name, {$_dept_sel} as department, {$_zone_sel} as zone FROM users WHERE role='officer' {$_active_cnd} ORDER BY name ASC");
if ($ro) while ($r = $ro->fetch_assoc()) $officers_list[] = $r;

$wp=['1=1'];$bt='';$bv=[];
if ($f_status!=='all')  { $wp[]="c.status=?";   $bt.='s';$bv[]=$f_status; }
if ($f_category!=='all'){ $wp[]="c.category=?"; $bt.='s';$bv[]=$f_category; }
if ($f_priority!=='all'){ $wp[]="c.priority=?"; $bt.='s';$bv[]=$f_priority; }
if ($search!=='') {
    $like='%'.$search.'%';
    $wp[]="(c.title LIKE ? OR c.complaint_no LIKE ? OR c.location LIKE ? OR u.name LIKE ?)";
    $bt.='ssss';$bv[]=$like;$bv[]=$like;$bv[]=$like;$bv[]=$like;
}
$where=implode(' AND ',$wp);
$sort_sql=match($sort){'priority'=>"CASE c.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END",'title'=>"c.title",'status'=>"c.status",'updated_at'=>"c.updated_at",default=>"c.created_at"};
$ord=strtoupper($order);
$off=($page-1)*$per_page;

$csql="SELECT COUNT(*) as c FROM complaints c LEFT JOIN users u ON c.citizen_id=u.id WHERE $where";
if ($bt){$st=$conn->prepare($csql);$st->bind_param($bt,...$bv);$st->execute();$cr=$st->get_result();$st->close();}else{$cr=$conn->query($csql);}
if ($cr) $total_complaints=(int)$cr->fetch_assoc()['c'];

$dsql="SELECT c.*,u.name as citizen_name,o.name as officer_name,o.id as officer_id_val FROM complaints c LEFT JOIN users u ON c.citizen_id=u.id LEFT JOIN users o ON c.officer_id=o.id WHERE $where ORDER BY $sort_sql $ord,c.id DESC LIMIT ? OFFSET ?";
$at=$bt.'ii';$av=array_merge($bv,[$per_page,$off]);
$st=$conn->prepare($dsql);$st->bind_param($at,...$av);$st->execute();$dr=$st->get_result();$st->close();
if ($dr) while($r=$dr->fetch_assoc()) $complaints[]=$r;

// Only fall back to dummy data when DB is genuinely empty (fresh install, no complaints yet)
if (empty($complaints) && $total_complaints === 0 && $f_status === 'all' && $f_category === 'all' && $f_priority === 'all' && $search === '') {
    $use_dummy = true;
}

// ── DUMMY ──────────────────────────────────────────────────────────
if ($use_dummy) {
    $ad=[
        ['id'=>1,'complaint_no'=>'GRV-A1B2C3','category'=>'road',       'title'=>'Pothole on NH 66 near Panaji Bus Stand',   'location'=>'Panaji',    'priority'=>'high',  'status'=>'in_progress','citizen_name'=>'Ramesh Naik',     'officer_name'=>'Suresh Kamat',   'officer_id_val'=>1,'created_at'=>date('Y-m-d H:i:s',strtotime('-5 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-1 day'))],
        ['id'=>2,'complaint_no'=>'GRV-D4E5F6','category'=>'water',      'title'=>'No water supply for 3 days — Ward 7',      'location'=>'Margao',    'priority'=>'high',  'status'=>'escalated',  'citizen_name'=>'Priya Shet',      'officer_name'=>'Raj Naik',       'officer_id_val'=>4,'created_at'=>date('Y-m-d H:i:s',strtotime('-10 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-5 days'))],
        ['id'=>3,'complaint_no'=>'GRV-G7H8I9','category'=>'electricity','title'=>'Street lights out — Calangute Beach Rd',   'location'=>'Calangute', 'priority'=>'medium','status'=>'resolved',   'citizen_name'=>'Anil Borkar',     'officer_name'=>'Anton Fernandes','officer_id_val'=>3,'created_at'=>date('Y-m-d H:i:s',strtotime('-15 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-2 days'))],
        ['id'=>4,'complaint_no'=>'GRV-J1K2L3','category'=>'sanitation', 'title'=>'Garbage not collected — Baina Area',       'location'=>'Vasco',     'priority'=>'medium','status'=>'assigned',   'citizen_name'=>'Sunita Verma',    'officer_name'=>'Sunita Borkar',  'officer_id_val'=>5,'created_at'=>date('Y-m-d H:i:s',strtotime('-3 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-1 day'))],
        ['id'=>5,'complaint_no'=>'GRV-M4N5O6','category'=>'property',  'title'=>'Illegal construction blocking drain',       'location'=>'Ponda',     'priority'=>'high',  'status'=>'new',        'citizen_name'=>'David Coelho',    'officer_name'=>null,             'officer_id_val'=>null,'created_at'=>date('Y-m-d H:i:s',strtotime('-1 day')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-1 day'))],
        ['id'=>6,'complaint_no'=>'GRV-F9B4D8','category'=>'water',     'title'=>'No water supply — Dona Paula Ward',         'location'=>'Dona Paula','priority'=>'high',  'status'=>'escalated',  'citizen_name'=>'David Gomes',     'officer_name'=>'Raj Naik',       'officer_id_val'=>4,'created_at'=>date('Y-m-d H:i:s',strtotime('-20 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-5 days'))],
        ['id'=>7,'complaint_no'=>'GRV-P7Q8R9','category'=>'road',      'title'=>'Damaged footpath — MG Road',                'location'=>'Panaji',    'priority'=>'low',   'status'=>'resolved',   'citizen_name'=>'Meena Pai',       'officer_name'=>'Suresh Kamat',   'officer_id_val'=>1,'created_at'=>date('Y-m-d H:i:s',strtotime('-8 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-3 days'))],
        ['id'=>8,'complaint_no'=>'GRV-S1T2U3','category'=>'sanitation','title'=>'Sewage overflow — Housing Colony',          'location'=>'Mapusa',    'priority'=>'high',  'status'=>'in_progress','citizen_name'=>'Rakesh Sardessai','officer_name'=>'Sunita Borkar',  'officer_id_val'=>5,'created_at'=>date('Y-m-d H:i:s',strtotime('-4 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-2 days'))],
        ['id'=>9,'complaint_no'=>'GRV-I5L8Q2','category'=>'electricity','title'=>'Transformer sparking in Mapusa',           'location'=>'Mapusa',    'priority'=>'high',  'status'=>'escalated',  'citizen_name'=>"Conceicao D'Mello",'officer_name'=>'Raj Naik',      'officer_id_val'=>4,'created_at'=>date('Y-m-d H:i:s',strtotime('-4 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-2 days'))],
        ['id'=>10,'complaint_no'=>'GRV-V4W5X6','category'=>'road',     'title'=>'Missing manhole cover — Old Goa Rd',        'location'=>'Old Goa',   'priority'=>'high',  'status'=>'new',        'citizen_name'=>'Thomas Pereira',  'officer_name'=>null,             'officer_id_val'=>null,'created_at'=>date('Y-m-d H:i:s',strtotime('-2 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-2 days'))],
        ['id'=>11,'complaint_no'=>'GRV-W2X3Y4','category'=>'road',     'title'=>'Broken road divider — Calangute Hwy',       'location'=>'Calangute', 'priority'=>'high',  'status'=>'new',        'citizen_name'=>'Rohan Sawant',    'officer_name'=>null,             'officer_id_val'=>null,'created_at'=>date('Y-m-d H:i:s',strtotime('-6 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-6 days'))],
        ['id'=>12,'complaint_no'=>'GRV-Z5A6B7','category'=>'water',    'title'=>'Burst pipe flooding street — Margao',       'location'=>'Margao',    'priority'=>'high',  'status'=>'assigned',   'citizen_name'=>'Fatima Sheikh',   'officer_name'=>'Priya Dessai',   'officer_id_val'=>2,'created_at'=>date('Y-m-d H:i:s',strtotime('-7 days')), 'updated_at'=>date('Y-m-d H:i:s',strtotime('-7 days'))],
        ['id'=>13,'complaint_no'=>'GRV-C8D9E0','category'=>'sanitation','title'=>'Open drain overflow near school',          'location'=>'Vasco',     'priority'=>'medium','status'=>'closed',     'citizen_name'=>'Agnes Monteiro',  'officer_name'=>'Sunita Borkar',  'officer_id_val'=>5,'created_at'=>date('Y-m-d H:i:s',strtotime('-18 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-10 days'))],
        ['id'=>14,'complaint_no'=>'GRV-E1F2G3','category'=>'electricity','title'=>'No street lights for 2 weeks — Ponda',   'location'=>'Ponda',     'priority'=>'medium','status'=>'closed',     'citizen_name'=>'Vishnu Lotlikar', 'officer_name'=>'Anton Fernandes','officer_id_val'=>3,'created_at'=>date('Y-m-d H:i:s',strtotime('-25 days')),'updated_at'=>date('Y-m-d H:i:s',strtotime('-15 days'))],
    ];
    // Filter
    $fd=array_values(array_filter($ad,function($c)use($f_status,$f_category,$f_priority,$search){
        if($f_status!=='all'&&$c['status']!==$f_status)return false;
        if($f_category!=='all'&&$c['category']!==$f_category)return false;
        if($f_priority!=='all'&&$c['priority']!==$f_priority)return false;
        if($search!==''){$h=strtolower($c['title'].$c['complaint_no'].$c['location'].($c['citizen_name']??''));if(strpos($h,strtolower($search))===false)return false;}
        return true;
    }));
    // Sort
    $pm=['high'=>1,'medium'=>2,'low'=>3];
    usort($fd,function($a,$b)use($sort,$order,$pm){
        $va=$vb='';
        switch($sort){case'priority':$va=$pm[$a['priority']]??9;$vb=$pm[$b['priority']]??9;break;case'title':$va=$a['title'];$vb=$b['title'];break;case'status':$va=$a['status'];$vb=$b['status'];break;case'updated_at':$va=$a['updated_at'];$vb=$b['updated_at'];break;default:$va=$a['created_at'];$vb=$b['created_at'];}
        $c=is_numeric($va)?($va<=>$vb):strcmp((string)$va,(string)$vb);
        return $order==='asc'?$c:-$c;
    });
    $total_complaints=count($fd);
    $complaints=array_slice($fd,($page-1)*$per_page,$per_page);
    if(empty($officers_list))$officers_list=[['id'=>1,'name'=>'Suresh Kamat','department'=>'Road & PWD','zone'=>'Panaji'],['id'=>2,'name'=>'Priya Dessai','department'=>'Water Supply','zone'=>'Margao'],['id'=>3,'name'=>'Anton Fernandes','department'=>'Electricity','zone'=>'Vasco'],['id'=>5,'name'=>'Sunita Borkar','department'=>'Sanitation','zone'=>'Ponda']];
    // Status counts respect other filters
    $sc_all=[];
    foreach(['all','new','assigned','in_progress','escalated','resolved','closed']as$s){
        $sc_all[$s]=count(array_filter($ad,function($c)use($s,$f_category,$f_priority,$search){
            if($s!=='all'&&$c['status']!==$s)return false;
            if($f_category!=='all'&&$c['category']!==$f_category)return false;
            if($f_priority!=='all'&&$c['priority']!==$f_priority)return false;
            if($search!==''){$h=strtolower($c['title'].$c['complaint_no'].$c['location'].($c['citizen_name']??''));if(strpos($h,strtolower($search))===false)return false;}
            return true;
        }));
    }
    $status_counts=$sc_all;
}

$total_pages=max(1,ceil($total_complaints/$per_page));
$cat_icon=['road'=>'🚧','water'=>'💧','electricity'=>'⚡','sanitation'=>'🧹','property'=>'🏛️','lost'=>'🔍'];
$hour=(int)date('H');$greeting=$hour<12?'Good morning':($hour<18?'Good afternoon':'Good evening');

if(!isset($status_counts)) {
    $status_counts=['all'=>0];
    $sc_wp=['1=1'];$sc_bt='';$sc_bv=[];
    if($f_category!=='all'){$sc_wp[]="c.category=?";$sc_bt.='s';$sc_bv[]=$f_category;}
    if($f_priority!=='all'){$sc_wp[]="c.priority=?";$sc_bt.='s';$sc_bv[]=$f_priority;}
    if($search!==''){$like='%'.$search.'%';$sc_wp[]="(c.title LIKE ? OR c.complaint_no LIKE ? OR c.location LIKE ? OR u.name LIKE ?)";$sc_bt.='ssss';$sc_bv[]=$like;$sc_bv[]=$like;$sc_bv[]=$like;$sc_bv[]=$like;}
    $sc_where=implode(' AND ',$sc_wp);
    $sc_sql="SELECT c.status,COUNT(*) as c FROM complaints c LEFT JOIN users u ON c.citizen_id=u.id WHERE $sc_where GROUP BY c.status";
    if($sc_bt){$st=$conn->prepare($sc_sql);$st->bind_param($sc_bt,...$sc_bv);$st->execute();$scr=$st->get_result();$st->close();}else{$scr=$conn->query($sc_sql);}
    if($scr)while($r=$scr->fetch_assoc()){$status_counts[$r['status']]=(int)$r['c'];$status_counts['all']+=(int)$r['c'];}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>All Complaints — Nagrik Seva</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--g900:#011a18;--g800:#042e2a;--g750:#053d37;--g700:#065449;--g650:#0a6e60;--g600:#0d8572;--g500:#109e88;--g400:#18cfb4;--g350:#3ddbc3;--g300:#6ce5d2;--g200:#adf2e8;--g100:#e2faf7;--g050:#f0fdfb;--white:#fff;--accent:#18cfb4;--bg:#f0f9f4;--card:#fff;--text:#0d2b1b;--muted:#4a7260;--border:#c8e8d8;--border2:#a0d4b8;--radius:14px;--shadow:0 2px 12px rgba(13,43,27,.07),0 1px 3px rgba(13,43,27,.05);--shadow-md:0 8px 28px rgba(13,43,27,.11);--shadow-lg:0 20px 56px rgba(13,43,27,.16);}
html,body{height:100%;overflow:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;-webkit-font-smoothing:antialiased;}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--g300);border-radius:4px}
.sidebar{width:220px;min-width:220px;height:100vh;background:var(--g800);display:flex;flex-direction:column;z-index:51;overflow-y:auto;position:relative;flex-shrink:0}
.sidebar::after{content:'';position:absolute;right:0;top:0;bottom:0;width:1px;background:linear-gradient(180deg,transparent,var(--g500) 20%,var(--g400) 60%,var(--g500) 80%,transparent)}
.sb-logo{padding:24px 20px 22px;display:flex;align-items:center;gap:13px}
.sb-mark{width:42px;height:42px;background:linear-gradient(135deg,var(--g400),var(--g350));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;box-shadow:0 4px 14px rgba(24,207,180,.35)}
.sb-name{font-size:.9rem;font-weight:700;color:#fff;line-height:1.1}.sb-sub{font-size:.58rem;color:var(--g300);text-transform:uppercase;letter-spacing:1.2px;margin-top:3px;font-weight:500}
.sb-divider{height:1px;background:linear-gradient(90deg,transparent,var(--g650),transparent);margin:4px 0 8px}
.sb-sec{padding:14px 20px 5px;font-size:.54rem;font-weight:700;letter-spacing:2.2px;text-transform:uppercase;color:var(--g400)}
.nav-a{display:flex;align-items:center;gap:10px;padding:9px 16px;margin:1px 10px;border-radius:10px;font-size:.79rem;color:var(--g200);transition:all .18s;border:1px solid transparent;position:relative}
.nav-a:hover{background:rgba(255,255,255,.06);color:#fff;transform:translateX(2px)}
.nav-a.on{background:linear-gradient(135deg,rgba(24,207,180,.2),rgba(24,207,180,.08));color:#fff;font-weight:600;border-color:rgba(24,207,180,.4)}
.nav-a.on::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:60%;background:var(--accent);border-radius:0 3px 3px 0}
.nav-ico{font-size:.85rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge-red{margin-left:auto;background:#dc2626;color:#fff;font-size:.58rem;font-weight:800;padding:2px 6px;border-radius:4px}
.sb-foot{margin-top:auto;padding:12px 10px;border-top:1px solid rgba(255,255,255,.06)}
.u-card{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:11px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08)}
.u-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));border:2px solid rgba(24,207,180,.4);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:#fff;flex-shrink:0}
.u-name{font-size:.77rem;font-weight:600;color:#fff}.u-role{font-size:.58rem;color:var(--g300);margin-top:1px;letter-spacing:.5px;text-transform:uppercase}
.u-logout{margin-left:auto;background:none;border:none;color:var(--g300);cursor:pointer;padding:5px;border-radius:7px;transition:all .15s}
.u-logout:hover{color:#fff;background:rgba(255,255,255,.1)}
.main{flex:1;height:100vh;overflow-y:auto;display:flex;flex-direction:column;min-width:0}
.topbar{background:var(--g750);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;flex-shrink:0;border-bottom:1px solid rgba(24,207,180,.25)}
.tb-left{display:flex;align-items:center;gap:14px;flex:1;min-width:0}.tb-greeting{font-size:.88rem;font-weight:500;color:#fff;white-space:nowrap}
.tb-sep{width:1px;height:16px;background:rgba(255,255,255,.15);flex-shrink:0}.tb-date{font-size:.71rem;color:var(--g300);white-space:nowrap}
.tb-center{position:absolute;left:50%;transform:translateX(-50%);text-align:center;pointer-events:none;white-space:nowrap}
.tb-brand{font-size:1rem;font-weight:700;color:#fff}.tb-tagline{font-size:.57rem;color:var(--g300);letter-spacing:.6px;text-transform:uppercase;margin-top:2px}
.tb-right{display:flex;align-items:center;gap:10px;flex-shrink:0}
.tb-badge{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:rgba(24,207,180,.15);border:1px solid rgba(24,207,180,.3);font-size:.74rem;font-weight:600;color:var(--g200)}
.tb-back{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);font-size:.74rem;font-weight:600;color:var(--g200);transition:all .15s}
.tb-back:hover{background:rgba(255,255,255,.14);color:#fff}
.body{padding:22px 28px;flex:1}
.toast{display:flex;align-items:center;gap:9px;padding:12px 16px;border-radius:10px;font-size:.82rem;margin-bottom:14px;border:1px solid transparent;font-weight:500;animation:ti .3s ease}
@keyframes ti{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.t-ok{background:var(--g100);border-color:var(--g300);color:var(--g700)}.t-err{background:#fff0f0;border-color:#f5b8b8;color:#a02020}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap}
.ph-title{font-size:1.3rem;font-weight:800;color:var(--text);letter-spacing:-.4px}.ph-sub{font-size:.77rem;color:var(--muted);margin-top:3px}
.ph-actions{display:flex;gap:8px;align-items:center;position:relative}
.export-wrap{position:relative}
.export-btn{display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:9px;background:var(--card);border:1.5px solid var(--border);font-size:.77rem;font-weight:700;color:var(--g600);cursor:pointer;font-family:inherit;transition:all .15s;white-space:nowrap}
.export-btn:hover{border-color:var(--accent);background:var(--g050)}
.export-menu{position:absolute;right:0;top:calc(100% + 6px);background:var(--card);border:1.5px solid var(--border);border-radius:11px;box-shadow:var(--shadow-md);min-width:170px;z-index:100;overflow:hidden;display:none}
.export-menu.open{display:block}
.export-opt{display:flex;align-items:center;gap:9px;padding:11px 14px;font-size:.78rem;font-weight:600;color:var(--text);cursor:pointer;transition:background .13s;border:none;background:none;width:100%;font-family:inherit;text-align:left}
.export-opt:hover{background:var(--g050);color:var(--g700)}.export-opt-ico{font-size:.95rem;width:20px;text-align:center}
.status-tabs{display:flex;gap:4px;margin-bottom:14px;flex-wrap:wrap}
.s-tab{padding:7px 13px;border-radius:8px;font-size:.72rem;font-weight:600;border:1.5px solid var(--border);background:var(--card);color:var(--muted);text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.s-tab:hover{border-color:var(--accent);color:var(--text)}.s-tab.active{background:var(--g700);color:#fff;border-color:var(--g700)}
.s-tab-ct{font-size:.58rem;padding:2px 6px;border-radius:4px;font-weight:800}
.s-tab.active .s-tab-ct{background:rgba(255,255,255,.2);color:inherit}.s-tab:not(.active) .s-tab-ct{background:var(--g100);color:var(--g600)}
.filters{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center}
.fi-search{flex:1;min-width:180px;padding:9px 13px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.82rem;color:var(--text);background:var(--card);outline:none;transition:border-color .17s}
.fi-search:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(24,207,180,.1)}
.fi-sel{padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.82rem;color:var(--text);background:var(--card);outline:none;cursor:pointer;transition:border-color .15s}
.fi-sel:focus{border-color:var(--accent)}
.fi-btn{padding:9px 18px;border-radius:10px;background:linear-gradient(135deg,var(--g700),var(--g500));color:#fff;border:none;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .15s}
.fi-btn:hover{transform:translateY(-1px)}
.fi-clear{padding:9px 13px;border-radius:10px;background:var(--card);border:1.5px solid var(--border);color:var(--muted);font-size:.82rem;text-decoration:none;transition:all .15s;white-space:nowrap}
.fi-clear:hover{border-color:var(--border2);color:var(--text)}.fi-count{font-size:.72rem;color:var(--muted);white-space:nowrap;padding:0 2px}
.table-wrap{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
.ct-head{display:grid;grid-template-columns:40px 2.3fr 1.1fr .85fr 85px 95px 160px;padding:10px 16px;background:linear-gradient(180deg,rgba(244,253,248,.9),rgba(255,255,255,0));border-bottom:1.5px solid var(--border)}
.ct-th{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);display:flex;align-items:center}
.ct-th a{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:3px;padding:3px 6px;border-radius:5px;transition:all .13s;white-space:nowrap}
.ct-th a:hover{background:var(--g100);color:var(--g700)}.ct-th a.sa{color:var(--g600);background:var(--g050)}
.sar{font-size:.68rem;opacity:.5}.sar.on{opacity:1;color:var(--accent)}
.ct-row{display:grid;grid-template-columns:40px 2.3fr 1.1fr .85fr 85px 95px 160px;padding:11px 16px;border-bottom:1px solid var(--border);align-items:center;transition:background .14s;cursor:pointer}
.ct-row:last-child{border-bottom:none}.ct-row:hover{background:var(--g050)}
.ct-row.row-esc{border-left:3px solid #dc2626;background:rgba(220,38,38,.015)}.ct-row.row-esc:hover{background:rgba(220,38,38,.03)}
.c-ico{width:34px;height:34px;border-radius:9px;background:var(--g100);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0}
.c-no{font-size:.59rem;font-weight:600;color:var(--accent);font-family:'DM Mono',monospace;letter-spacing:.8px;margin-bottom:2px}
.c-title{font-size:.82rem;font-weight:600;color:var(--text);line-height:1.3;margin-bottom:2px}
.c-meta{font-size:.61rem;color:var(--muted)}.c-person{font-size:.73rem;font-weight:600;color:var(--text);margin-bottom:3px}
.c-officer{font-size:.65rem;color:var(--muted)}.c-loc{font-size:.7rem;color:var(--muted)}.unassigned-tag{font-size:.63rem;font-weight:700;color:#b07b00}
.pill{display:inline-flex;align-items:center;padding:3px 9px;border-radius:5px;font-size:.59rem;font-weight:800;letter-spacing:.4px;white-space:nowrap;text-transform:uppercase;border:1px solid transparent}
.s-new{background:#fff8e8;color:#8a6200;border-color:#f5d98a}.s-assigned{background:var(--g100);color:var(--g600);border-color:var(--g300)}
.s-prog{background:linear-gradient(135deg,var(--g400),var(--g350));color:var(--g900);border-color:var(--g300)}.s-resolved{background:var(--g700);color:#fff}
.s-escalated{background:#fff0f0;color:#a02020;border-color:#f5b8b8}.s-closed{background:var(--g100);color:var(--muted)}
.p-high{background:#fff0f0;color:#a02020;border:1px solid #f5b8b8}.p-medium{background:#fff8e8;color:#8a6200;border:1px solid #f5d98a}.p-low{background:var(--g100);color:var(--g500);border:1px solid var(--g300)}
.actions-cell{display:flex;gap:3px;flex-wrap:wrap;align-items:center}
.act-btn{padding:5px 9px;border-radius:6px;font-size:.63rem;font-weight:700;border:1.5px solid;cursor:pointer;font-family:inherit;transition:all .14s;white-space:nowrap;display:inline-flex;align-items:center;gap:3px;background:transparent;line-height:1}
.act-btn:hover{transform:translateY(-1px)}
.act-view{color:var(--g600);border-color:var(--g300)}.act-view:hover{background:var(--g100)}
.act-esc{color:#a02020;border-color:#f5b8b8}.act-esc:hover{background:#fff0f0}
.act-close{color:var(--g600);border-color:var(--g300)}.act-close:hover{background:var(--g100)}
.act-reopen{color:#b07b00;border-color:#f5d98a}.act-reopen:hover{background:#fff8e8}
.act-reassign{color:var(--g700);border-color:var(--g400)}.act-reassign:hover{background:var(--g050)}
.pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-top:1.5px solid var(--border);flex-wrap:wrap;gap:8px}
.pag-info{font-size:.72rem;color:var(--muted)}.pag-btns{display:flex;gap:4px;flex-wrap:wrap}
.pag-btn{padding:6px 12px;border-radius:7px;font-size:.72rem;font-weight:600;border:1.5px solid var(--border);background:var(--card);color:var(--muted);text-decoration:none;transition:all .14s}
.pag-btn:hover{border-color:var(--accent);color:var(--text)}.pag-btn.cur{background:var(--g700);color:#fff;border-color:var(--g700)}
.empty-state{text-align:center;padding:60px 20px}.empty-ico{font-size:2.5rem;opacity:.25;margin-bottom:12px}
.empty-title{font-size:.88rem;font-weight:800;color:var(--text);margin-bottom:5px}.empty-sub{font-size:.75rem;color:var(--muted);line-height:1.6}
.overlay{position:fixed;inset:0;background:rgba(6,26,15,.6);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s;backdrop-filter:blur(4px);padding:16px}
.overlay.on{opacity:1;pointer-events:all}
.modal{background:#fff;border:1.5px solid var(--border);border-radius:18px;width:100%;max-width:480px;max-height:92vh;overflow-y:auto;padding:28px;box-shadow:var(--shadow-lg);transform:scale(.95) translateY(14px);transition:transform .24s cubic-bezier(.4,0,.2,1)}
.overlay.on .modal{transform:scale(1) translateY(0)}.modal-sm{max-width:400px}
.mh{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;gap:12px}
.mh-title{font-size:1rem;font-weight:800;color:var(--text);letter-spacing:-.2px}.mh-sub{font-size:.73rem;color:var(--muted);margin-top:4px;line-height:1.5}
.mh-close{width:30px;height:30px;border-radius:8px;background:var(--g100);border:1.5px solid var(--border);color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:all .15s;flex-shrink:0}
.mh-close:hover{background:var(--g200);color:var(--text)}
.cbox{background:var(--g050);border:1.5px solid var(--border);border-radius:11px;padding:13px 16px;margin-bottom:18px}
.cbox-no{font-size:.59rem;font-weight:700;color:var(--accent);font-family:'DM Mono',monospace;letter-spacing:.8px;margin-bottom:4px}
.cbox-title{font-size:.86rem;font-weight:700;color:var(--text);margin-bottom:6px;line-height:1.35}
.cbox-meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:.68rem;color:var(--muted)}
.info-box{background:var(--g100);border:1.5px solid var(--g300);border-radius:9px;padding:11px 14px;font-size:.76rem;color:var(--g700);line-height:1.65;margin-bottom:16px}
.warn-box{background:#fff8e8;border:1.5px solid #f5d98a;border-radius:9px;padding:11px 14px;font-size:.76rem;color:#8a6200;line-height:1.65;margin-bottom:16px}
.danger-box{background:#fff0f0;border:1.5px solid #f5b8b8;border-left:4px solid #dc2626;border-radius:9px;padding:11px 14px;font-size:.76rem;color:#a02020;line-height:1.65;margin-bottom:16px}
.fg{margin-bottom:14px}.fl{display:block;font-size:.6rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin-bottom:6px}
.modal-btns{display:flex;gap:10px;margin-top:6px;flex-wrap:wrap}
.btn-cancel{flex:1;min-width:90px;padding:11px;border-radius:10px;background:#fff;color:var(--text);border:1.5px solid var(--border);font-family:inherit;font-size:.83rem;font-weight:600;cursor:pointer;transition:all .15s}
.btn-cancel:hover{background:var(--g100)}
.btn-submit{flex:2;min-width:120px;padding:11px;border-radius:10px;background:linear-gradient(135deg,var(--g700),var(--g500));color:#fff;border:none;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;transition:all .18s;box-shadow:0 4px 14px rgba(13,43,27,.2)}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(13,43,27,.28)}.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}
.btn-danger{background:linear-gradient(135deg,#7f1d1d,#dc2626)!important}
.btn-warn{background:linear-gradient(135deg,#78350f,#f59e0b)!important}
.btn-info{background:linear-gradient(135deg,var(--g800),var(--g600))!important}
.off-search-inp{width:100%;padding:9px 13px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.82rem;color:var(--text);outline:none;transition:border-color .15s;margin-bottom:10px}
.off-search-inp:focus{border-color:var(--accent)}.off-scroll{max-height:220px;overflow-y:auto}
.off-opt{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;transition:all .15s;margin-bottom:6px;background:#fff}
.off-opt:hover{border-color:rgba(24,207,180,.5);background:var(--g050)}.off-opt.sel{border-color:var(--g400);background:var(--g100)}
.oo-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g350));display:flex;align-items:center;justify-content:center;font-size:.74rem;font-weight:700;color:#fff;flex-shrink:0}
.oo-name{font-size:.82rem;font-weight:700;color:var(--text)}.oo-meta{font-size:.62rem;color:var(--muted);margin-top:1px}
@media(max-width:1100px){.ct-head,.ct-row{grid-template-columns:40px 2fr 1fr 85px 95px 160px}.ct-head>div:nth-child(4),.ct-row>div:nth-child(4){display:none}}
@media(max-width:900px){.sidebar{display:none}.ct-head,.ct-row{grid-template-columns:40px 1fr 85px 95px 140px}.ct-head>div:nth-child(3),.ct-row>div:nth-child(3),.ct-head>div:nth-child(4),.ct-row>div:nth-child(4){display:none}.tb-center{display:none}}
@media(max-width:640px){.body{padding:12px}.ct-head,.ct-row{grid-template-columns:36px 1fr 90px 120px}.ct-head>div:nth-child(5),.ct-row>div:nth-child(5){display:none}.topbar{padding:0 12px}.tb-date,.tb-sep,.tb-badge{display:none}}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sb-logo"><div class="sb-mark">🏛️</div><div><div class="sb-name">Nagrik Seva</div><div class="sb-sub">Regulator Portal</div></div></div>
  <div class="sb-divider"></div>
  <div class="sb-sec">Oversight</div>
  <a class="nav-a" href="regulator_dashboard.php"><span class="nav-ico">⊞</span> Dashboard</a>
  <a class="nav-a" href="regulator_officers.php"><span class="nav-ico">👮</span> All Officers</a>
  <a class="nav-a on" href="regulator_complaints.php"><span class="nav-ico">📋</span> All Complaints</a>
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
    <?php if($toast_ok): ?><div class="toast t-ok"><?= $toast_ok ?></div><?php endif; ?>
    <?php if($toast_err): ?><div class="toast t-err">⚠ <?= $toast_err ?></div><?php endif; ?>

    <div class="page-header">
      <div>
        <div class="ph-title">📋 All Complaints</div>
        <div class="ph-sub"><?= number_format($total_complaints) ?> complaint<?= $total_complaints!==1?'s':'' ?><?php if($f_status!=='all'||$f_category!=='all'||$f_priority!=='all'||$search): ?> · <span style="color:var(--accent);font-weight:700">Filtered</span><?php endif; ?> · Page <?= $page ?>/<?= $total_pages ?></div>
      </div>
      <div class="ph-actions">
        <div class="export-wrap">
          <button class="export-btn" onclick="toggleExport(event)">⬇ Export ▾</button>
          <div class="export-menu" id="exp-menu">
            <button class="export-opt" onclick="doExport('csv')"><span class="export-opt-ico">📄</span>Export as CSV</button>
            <button class="export-opt" onclick="doExport('xls')"><span class="export-opt-ico">📊</span>Export as XLS (Excel)</button>
          </div>
        </div>
      </div>
    </div>

    <div class="status-tabs">
      <?php
      $tabs=['all'=>['label'=>'All','ico'=>'📋'],'new'=>['label'=>'New','ico'=>'🆕'],'assigned'=>['label'=>'Assigned','ico'=>'👤'],'in_progress'=>['label'=>'In Progress','ico'=>'⚙️'],'escalated'=>['label'=>'Escalated','ico'=>'🚨'],'resolved'=>['label'=>'Resolved','ico'=>'✅'],'closed'=>['label'=>'Closed','ico'=>'🔒']];
      foreach($tabs as $val=>$tab):
        $tp=[];if($val!=='all')$tp['status']=$val;if($f_category!=='all')$tp['category']=$f_category;if($f_priority!=='all')$tp['priority']=$f_priority;if($search!=='')$tp['q']=$search;if($sort!=='created_at')$tp['sort']=$sort;if($order!=='desc')$tp['order']=$order;
        $tu='regulator_complaints.php'.($tp?'?'.http_build_query($tp):'');
        $ct=$status_counts[$val]??0;
      ?>
      <a href="<?= $tu ?>" class="s-tab <?= $f_status===$val?'active':'' ?>"><?= $tab['ico'] ?> <?= $tab['label'] ?> <span class="s-tab-ct"><?= $ct ?></span></a>
      <?php endforeach; ?>
    </div>

    <form class="filters" method="GET" id="filter-form">
      <?php if($f_status!=='all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($f_status) ?>"><?php endif; ?>
      <?php if($sort!=='created_at'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
      <?php if($order!=='desc'): ?><input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>"><?php endif; ?>
      <input type="text" name="q" class="fi-search" placeholder="🔍  Search title, GRV ID, location, citizen…" value="<?= htmlspecialchars($search) ?>" autocomplete="off">
      <select name="category" class="fi-sel" onchange="this.form.submit()">
        <option value="all">All Categories</option>
        <?php foreach(['road','water','electricity','sanitation','property','lost'] as $cat): ?>
        <option value="<?= $cat ?>" <?= $f_category===$cat?'selected':'' ?>><?= ucfirst($cat) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="priority" class="fi-sel" onchange="this.form.submit()">
        <option value="all">All Priority</option>
        <option value="high"   <?= $f_priority==='high'  ?'selected':'' ?>>🔴 High</option>
        <option value="medium" <?= $f_priority==='medium'?'selected':'' ?>>🟡 Medium</option>
        <option value="low"    <?= $f_priority==='low'   ?'selected':'' ?>>🟢 Low</option>
      </select>
      <button type="submit" class="fi-btn">Apply</button>
      <?php if($search||$f_category!=='all'||$f_priority!=='all'):
        $cp=[];if($f_status!=='all')$cp['status']=$f_status;if($sort!=='created_at')$cp['sort']=$sort;if($order!=='desc')$cp['order']=$order;
      ?><a href="regulator_complaints.php<?= $cp?'?'.http_build_query($cp):'' ?>" class="fi-clear">✕ Clear</a><?php endif; ?>
      <span class="fi-count"><?= number_format($total_complaints) ?> result<?= $total_complaints!==1?'s':'' ?></span>
    </form>

    <div class="table-wrap">
      <?php
      function th(string $col, string $lbl, string $cs, string $co): string {
          global $f_status,$f_category,$f_priority,$search,$order;
          $on=$cs===$col;$no=$on?($co==='desc'?'asc':'desc'):'desc';
          $p=[];if($f_status!=='all')$p['status']=$f_status;if($f_category!=='all')$p['category']=$f_category;if($f_priority!=='all')$p['priority']=$f_priority;if($search!=='')$p['q']=$search;$p['sort']=$col;$p['order']=$no;
          $arr=$on?($co==='desc'?'↓':'↑'):'↕';
          return '<a href="regulator_complaints.php?'.htmlspecialchars(http_build_query($p)).'" class="'.($on?'sa':'').'">'.htmlspecialchars($lbl).'<span class="sar'.($on?' on':'').'">'.$arr.'</span></a>';
      }
      ?>
      <div class="ct-head">
        <div class="ct-th"></div>
        <div class="ct-th"><?= th('title','Complaint',$sort,$order) ?></div>
        <div class="ct-th">Citizen · Officer</div>
        <div class="ct-th"><?= th('created_at','Date',$sort,$order) ?></div>
        <div class="ct-th"><?= th('priority','Priority',$sort,$order) ?></div>
        <div class="ct-th"><?= th('status','Status',$sort,$order) ?></div>
        <div class="ct-th">Actions</div>
      </div>

      <?php if(empty($complaints)): ?>
      <div class="empty-state">
        <div class="empty-ico">📭</div>
        <div class="empty-title">No complaints found</div>
        <div class="empty-sub"><?php if($f_status!=='all'||$f_category!=='all'||$f_priority!=='all'||$search): ?>No complaints match these filters. <a href="regulator_complaints.php" style="color:var(--accent);font-weight:700">Clear all</a><?php else: ?>No complaints filed yet.<?php endif; ?></div>
      </div>
      <?php else: foreach($complaints as $c):
        $ico=$cat_icon[$c['category']]??'📋';
        $scls=match($c['status']){'new'=>'s-new','assigned'=>'s-assigned','in_progress'=>'s-prog','resolved'=>'s-resolved','escalated'=>'s-escalated','closed'=>'s-closed',default=>'s-new'};
        $slbl=$c['status']==='in_progress'?'In Progress':ucfirst(str_replace('_',' ',$c['status']));
        $pcls='p-'.($c['priority']??'medium');
        $days=(int)floor((time()-strtotime($c['created_at']))/86400);
        $cj=json_encode(['id'=>(int)$c['id'],'no'=>$c['complaint_no'],'title'=>$c['title'],'status'=>$c['status'],'slbl'=>$slbl,'priority'=>$c['priority']??'medium','location'=>$c['location']??'','citizen'=>$c['citizen_name']??'','officer'=>$c['officer_name']??'','off_id'=>(int)($c['officer_id_val']??0),'category'=>$c['category']??'','days'=>$days,'date'=>date('d M Y',strtotime($c['created_at']))],JSON_HEX_QUOT|JSON_HEX_APOS);
      ?>
      <div class="ct-row <?= $c['status']==='escalated'?'row-esc':'' ?>" onclick='openDetail(<?= $cj ?>)'>
        <div><div class="c-ico"><?= $ico ?></div></div>
        <div>
          <div class="c-no"><?= htmlspecialchars($c['complaint_no']) ?></div>
          <div class="c-title"><?= htmlspecialchars($c['title']) ?></div>
          <div class="c-meta">🕒 <?= $days===0?'Today':($days===1?'Yesterday':"$days days ago") ?> · <?= ucfirst($c['category']??'') ?></div>
        </div>
        <div onclick="event.stopPropagation()">
          <div class="c-person">👤 <?= htmlspecialchars($c['citizen_name']??'—') ?></div>
          <div class="c-officer"><?= !empty($c['officer_name'])?'👮 '.htmlspecialchars($c['officer_name']):'<span class="unassigned-tag">⚠ Unassigned</span>' ?></div>
        </div>
        <div>
          <div class="c-loc">📍 <?= htmlspecialchars($c['location']??'—') ?></div>
          <div class="c-meta" style="margin-top:3px"><?= date('d M Y',strtotime($c['created_at'])) ?></div>
        </div>
        <div><span class="pill <?= $pcls ?>"><?= ucfirst($c['priority']??'medium') ?></span></div>
        <div><span class="pill <?= $scls ?>"><?= $slbl ?></span></div>
        <div class="actions-cell" onclick="event.stopPropagation()">
          <a href="complaint_detail.php?id=<?= $c['id'] ?>" class="act-btn act-view">🔍</a>
          <?php if(!in_array($c['status'],['escalated','resolved','closed'])): ?><button class="act-btn act-esc" onclick='openEsc(<?= $cj ?>)'>🚨 Esc</button><?php endif; ?>
          <?php if(!in_array($c['status'],['closed','resolved'])): ?><button class="act-btn act-close" onclick='openClose(<?= $cj ?>)'>✅ Close</button><?php endif; ?>
          <?php if(in_array($c['status'],['closed','resolved'])): ?><button class="act-btn act-reopen" onclick='openReopen(<?= $cj ?>)'>↩</button><?php endif; ?>
          <button class="act-btn act-reassign" onclick='openReassign(<?= $cj ?>)'>👮</button>
        </div>
      </div>
      <?php endforeach; endif; ?>

      <?php if($total_pages>1):
        function ppq(int $pg, string $fs, string $fc, string $fpr, string $sq, string $so, string $or):string{
            $p=[];if($fs!=='all')$p['status']=$fs;if($fc!=='all')$p['category']=$fc;if($fpr!=='all')$p['priority']=$fpr;if($sq!=='')$p['q']=$sq;if($so!=='created_at')$p['sort']=$so;if($or!=='desc')$p['order']=$or;$p['page']=$pg;return http_build_query($p);
        }
      ?>
      <div class="pagination">
        <div class="pag-info">Showing <?= number_format(($page-1)*$per_page+1) ?>–<?= number_format(min($page*$per_page,$total_complaints)) ?> of <?= number_format($total_complaints) ?></div>
        <div class="pag-btns">
          <?php if($page>1): ?><a href="?<?= ppq($page-1,$f_status,$f_category,$f_priority,$search,$sort,$order) ?>" class="pag-btn">← Prev</a><?php endif; ?>
          <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?><a href="?<?= ppq($i,$f_status,$f_category,$f_priority,$search,$sort,$order) ?>" class="pag-btn <?= $i===$page?'cur':'' ?>"><?= $i ?></a><?php endfor; ?>
          <?php if($page<$total_pages): ?><a href="?<?= ppq($page+1,$f_status,$f_category,$f_priority,$search,$sort,$order) ?>" class="pag-btn">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- DETAIL MODAL -->
<div class="overlay" id="m-detail" onclick="if(event.target===this)closeAll()">
  <div class="modal">
    <div class="mh"><div><div class="mh-title" id="dm-title">—</div><div class="mh-sub" id="dm-sub">—</div></div><button class="mh-close" onclick="closeAll()">✕</button></div>
    <div class="cbox" id="dm-cbox"></div>
    <div id="dm-people" style="font-size:.79rem;color:var(--muted);line-height:1.9;margin-bottom:18px"></div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <a id="dm-link" href="#" class="act-btn act-view" style="padding:8px 14px;font-size:.75rem">🔍 Full Detail</a>
      <button id="dm-esc" class="act-btn act-esc" style="padding:8px 12px;font-size:.75rem" onclick="switchM('esc')">🚨 Escalate</button>
      <button id="dm-close" class="act-btn act-close" style="padding:8px 12px;font-size:.75rem" onclick="switchM('close')">✅ Close</button>
      <button id="dm-reopen" class="act-btn act-reopen" style="padding:8px 12px;font-size:.75rem" onclick="switchM('reopen')">↩ Reopen</button>
      <button id="dm-reassign" class="act-btn act-reassign" style="padding:8px 12px;font-size:.75rem" onclick="switchM('reassign')">👮 Reassign</button>
    </div>
  </div>
</div>

<!-- ESCALATE MODAL -->
<div class="overlay" id="m-esc" onclick="if(event.target===this)closeAll()">
  <div class="modal modal-sm">
    <div class="mh"><div><div class="mh-title">🚨 Escalate Complaint</div><div class="mh-sub">Officer notified immediately with urgent priority</div></div><button class="mh-close" onclick="closeAll()">✕</button></div>
    <div class="cbox" id="esc-cbox"></div>
    <div class="danger-box">⚠ Escalating marks this complaint as urgent and logs a regulator action in the history. The assigned officer will receive an immediate notification.</div>
    <form method="POST" onsubmit="return sbmt(this,'esc-btn','⏳ Escalating…')">
      <input type="hidden" name="action" value="escalate">
      <input type="hidden" name="complaint_id" id="esc-cid">
      <div class="modal-btns"><button type="button" class="btn-cancel" onclick="closeAll()">Cancel</button><button type="submit" class="btn-submit btn-danger" id="esc-btn">🚨 Confirm Escalate</button></div>
    </form>
  </div>
</div>

<!-- CLOSE MODAL -->
<div class="overlay" id="m-close" onclick="if(event.target===this)closeAll()">
  <div class="modal modal-sm">
    <div class="mh"><div><div class="mh-title">✅ Close Complaint</div><div class="mh-sub">Mark as officially closed</div></div><button class="mh-close" onclick="closeAll()">✕</button></div>
    <div class="cbox" id="close-cbox"></div>
    <div class="info-box">ℹ This closes the complaint. Citizens can still view it. Use Reopen if needed.</div>
    <form method="POST" onsubmit="return sbmt(this,'close-btn','⏳ Closing…')">
      <input type="hidden" name="action" value="close">
      <input type="hidden" name="complaint_id" id="close-cid">
      <div class="modal-btns"><button type="button" class="btn-cancel" onclick="closeAll()">Cancel</button><button type="submit" class="btn-submit btn-info" id="close-btn">✅ Confirm Close</button></div>
    </form>
  </div>
</div>

<!-- REOPEN MODAL -->
<div class="overlay" id="m-reopen" onclick="if(event.target===this)closeAll()">
  <div class="modal modal-sm">
    <div class="mh"><div><div class="mh-title">↩ Reopen Complaint</div><div class="mh-sub">Status will be reset to New</div></div><button class="mh-close" onclick="closeAll()">✕</button></div>
    <div class="cbox" id="reopen-cbox"></div>
    <div class="warn-box">⚠ Status will be set back to "New". Consider reassigning an officer after reopening.</div>
    <form method="POST" onsubmit="return sbmt(this,'reopen-btn','⏳ Reopening…')">
      <input type="hidden" name="action" value="reopen">
      <input type="hidden" name="complaint_id" id="reopen-cid">
      <div class="modal-btns"><button type="button" class="btn-cancel" onclick="closeAll()">Cancel</button><button type="submit" class="btn-submit btn-warn" id="reopen-btn">↩ Confirm Reopen</button></div>
    </form>
  </div>
</div>

<!-- REASSIGN MODAL -->
<div class="overlay" id="m-reassign" onclick="if(event.target===this)closeAll()">
  <div class="modal">
    <div class="mh"><div><div class="mh-title">👮 Reassign Complaint</div><div class="mh-sub">Transfer to a different officer</div></div><button class="mh-close" onclick="closeAll()">✕</button></div>
    <div class="cbox" id="reassign-cbox"></div>
    <div class="fg">
      <label class="fl">Select Officer</label>
      <input type="text" class="off-search-inp" id="off-search" placeholder="🔍  Name, department, zone…" oninput="filterOff(this.value)">
      <div class="off-scroll" id="off-list">
        <?php if(empty($officers_list)): ?><div style="text-align:center;padding:20px;color:var(--muted);font-size:.8rem">No active officers found.</div>
        <?php else: foreach($officers_list as $o): ?>
        <div class="off-opt" data-id="<?= (int)$o['id'] ?>" data-name="<?= htmlspecialchars($o['name'],ENT_QUOTES) ?>" data-search="<?= strtolower(htmlspecialchars($o['name'].' '.$o['department'].' '.$o['zone'],ENT_QUOTES)) ?>" onclick="selOff(this)">
          <div class="oo-av"><?= strtoupper(substr($o['name'],0,1)) ?></div>
          <div><div class="oo-name"><?= htmlspecialchars($o['name']) ?></div><div class="oo-meta">🏢 <?= htmlspecialchars($o['department']??'—') ?> · 📍 <?= htmlspecialchars($o['zone']??'—') ?></div></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <form method="POST" id="reassign-form" onsubmit="return sbmtReassign()">
      <input type="hidden" name="action" value="reassign">
      <input type="hidden" name="complaint_id" id="reassign-cid">
      <input type="hidden" name="new_officer_id" id="reassign-oid">
      <div class="modal-btns"><button type="button" class="btn-cancel" onclick="closeAll()">Cancel</button><button type="submit" class="btn-submit" id="reassign-btn" disabled>Select an officer first</button></div>
    </form>
  </div>
</div>

<script>
let _c=null;
function eh(s){return(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function cbox(c){const pl={high:'🔴 High',medium:'🟡 Medium',low:'🟢 Low'};return`<div class="cbox-no">${c.no}</div><div class="cbox-title">${eh(c.title)}</div><div class="cbox-meta"><span class="pill p-${c.priority}">${pl[c.priority]||c.priority}</span><span>📍 ${eh(c.location)}</span><span>📂 ${c.category}</span></div>`;}
function closeAll(){document.querySelectorAll('.overlay.on').forEach(o=>o.classList.remove('on'));document.body.style.overflow='';}
function openM(id){closeAll();document.getElementById(id).classList.add('on');document.body.style.overflow='hidden';}

function openDetail(c){
  _c=c;
  document.getElementById('dm-title').textContent=c.title;
  document.getElementById('dm-sub').textContent=c.no+' · '+c.category;
  document.getElementById('dm-cbox').innerHTML=cbox(c);
  document.getElementById('dm-link').href='complaint_detail.php?id='+c.id;
  const dt=c.days===0?'Today':c.days===1?'Yesterday':c.days+' days ago';
  document.getElementById('dm-people').innerHTML='👤 <strong>'+eh(c.citizen)+'</strong><br>'+(c.officer?'👮 '+eh(c.officer)+'<br>':'<span class="unassigned-tag">⚠ Unassigned</span><br>')+'🕒 Filed '+dt+' · '+c.date;
  const s=c.status;
  document.getElementById('dm-esc').style.display=['escalated','resolved','closed'].includes(s)?'none':'';
  document.getElementById('dm-close').style.display=['closed','resolved'].includes(s)?'none':'';
  document.getElementById('dm-reopen').style.display=['closed','resolved'].includes(s)?'':'none';
  openM('m-detail');
}
function switchM(t){if(!_c)return;closeAll();if(t==='esc')openEsc(_c);else if(t==='close')openClose(_c);else if(t==='reopen')openReopen(_c);else openReassign(_c);}

function openEsc(c){_c=c;document.getElementById('esc-cbox').innerHTML=cbox(c);document.getElementById('esc-cid').value=c.id;rbtn('esc-btn','🚨 Confirm Escalate');openM('m-esc');}
function openClose(c){_c=c;document.getElementById('close-cbox').innerHTML=cbox(c);document.getElementById('close-cid').value=c.id;rbtn('close-btn','✅ Confirm Close');openM('m-close');}
function openReopen(c){_c=c;document.getElementById('reopen-cbox').innerHTML=cbox(c);document.getElementById('reopen-cid').value=c.id;rbtn('reopen-btn','↩ Confirm Reopen');openM('m-reopen');}
function openReassign(c){
  _c=c;
  document.getElementById('reassign-cbox').innerHTML=cbox(c);
  document.getElementById('reassign-cid').value=c.id;
  document.getElementById('reassign-oid').value='';
  document.getElementById('off-search').value='';
  const btn=document.getElementById('reassign-btn');btn.disabled=true;btn.textContent='Select an officer first';
  document.querySelectorAll('.off-opt').forEach(el=>{el.classList.toggle('sel',c.off_id>0&&parseInt(el.dataset.id)===c.off_id);});
  filterOff('');openM('m-reassign');
}
function selOff(el){document.querySelectorAll('.off-opt').forEach(e=>e.classList.remove('sel'));el.classList.add('sel');document.getElementById('reassign-oid').value=el.dataset.id;const btn=document.getElementById('reassign-btn');btn.disabled=false;btn.textContent='👮 Reassign to '+el.dataset.name;}
function filterOff(q){const lq=q.toLowerCase();document.querySelectorAll('.off-opt').forEach(el=>{el.style.display=(!lq||el.dataset.search.includes(lq))?'':'none';});}
function sbmtReassign(){if(!document.getElementById('reassign-oid').value){alert('Select an officer first.');return false;}const b=document.getElementById('reassign-btn');b.disabled=true;b.textContent='⏳ Reassigning…';return true;}
function sbmt(form,bid,lt){const b=document.getElementById(bid);b.disabled=true;b.textContent=lt;return true;}
function rbtn(id,t){const b=document.getElementById(id);if(b){b.disabled=false;b.textContent=t;}}

document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAll();});
setTimeout(()=>{const t=document.querySelector('.toast');if(t){t.style.transition='opacity .5s';t.style.opacity='0';setTimeout(()=>t.remove(),500);}},5000);

function toggleExport(e){e.stopPropagation();document.getElementById('exp-menu').classList.toggle('open');}
document.addEventListener('click',()=>document.getElementById('exp-menu').classList.remove('open'));

const ED=<?php
$er=[];foreach($complaints as $c){$er[]=['id'=>(int)$c['id'],'no'=>$c['complaint_no'],'cat'=>$c['category']??'','title'=>$c['title'],'loc'=>$c['location']??'','pri'=>$c['priority']??'','status'=>$c['status'],'slbl'=>$c['status']==='in_progress'?'In Progress':ucfirst(str_replace('_',' ',$c['status'])),'citizen'=>$c['citizen_name']??'','officer'=>$c['officer_name']??'','filed'=>date('d M Y',strtotime($c['created_at'])),'updated'=>date('d M Y',strtotime($c['updated_at']))];}
echo json_encode($er,JSON_HEX_QUOT|JSON_HEX_APOS);
?>;
const EH=['ID','Complaint No','Category','Title','Location','Priority','Status','Citizen','Officer','Filed','Updated'];
const EK=['id','no','cat','title','loc','pri','slbl','citizen','officer','filed','updated'];

function doExport(fmt){document.getElementById('exp-menu').classList.remove('open');fmt==='csv'?doCSV():doXLS();}
function doCSV(){const rows=[EH,...ED.map(r=>EK.map(k=>r[k]??''))];const csv=rows.map(r=>r.map(v=>'"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\n');dl('\uFEFF'+csv,'nagrik-seva-complaints-<?= date('Y-m-d') ?>.csv','text/csv;charset=utf-8');}
function doXLS(){
  const filt='Status: <?= htmlspecialchars($f_status) ?> | Cat: <?= htmlspecialchars($f_category) ?> | Pri: <?= htmlspecialchars($f_priority) ?><?= $search?' | Search: '.htmlspecialchars($search):'' ?>';
  let h=`<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Complaints</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table>`;
  h+=`<tr><th colspan="${EH.length}" style="font-size:14pt;font-weight:bold;background:#065449;color:white">Nagrik Seva — Complaints Export</th></tr>`;
  h+=`<tr><td colspan="${EH.length}" style="font-size:9pt;color:gray">${filt} · Exported <?= date('d M Y H:i') ?></td></tr><tr></tr>`;
  h+=`<tr>${EH.map(x=>`<th style="background:#e2faf7;font-weight:bold;border:1px solid #c8e8d8">${x}</th>`).join('')}</tr>`;
  ED.forEach(r=>{const bg=r.status==='escalated'?'#fff0f0':r.status==='resolved'?'#e8f8ee':r.status==='new'?'#fff8e8':'';h+=`<tr>${EK.map(k=>`<td style="border:1px solid #c8e8d8${bg?';background:'+bg:''}">${r[k]??''}</td>`).join('')}</tr>`;});
  h+=`</table></body></html>`;
  dl(h,'nagrik-seva-complaints-<?= date('Y-m-d') ?>.xls','application/vnd.ms-excel;charset=utf-8');
}
function dl(cnt,fn,mt){const b=new Blob([cnt],{type:mt}),u=URL.createObjectURL(b),a=Object.assign(document.createElement('a'),{href:u,download:fn});document.body.appendChild(a);a.click();document.body.removeChild(a);URL.revokeObjectURL(u);}
</script>
</body>
</html>