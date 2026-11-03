<?php
// Web base path, derived from the running script's URL so it works under any folder name,
// on Windows/WAMP/XAMPP (case-insensitive drive paths) and with the PHP built-in server.
$__root = str_replace('\\','/',realpath(__DIR__.'/..'));
$__sf   = str_replace('\\','/',realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '');
$__rel  = ($__sf && stripos($__sf,$__root)===0) ? trim(substr(dirname($__sf),strlen($__root)),'/') : '';
$__depth= $__rel==='' ? 0 : substr_count($__rel,'/')+1;
$__segs = explode('/',trim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/')),'/'));
$__segs = array_filter($__segs,'strlen');
$__segs = array_slice($__segs,0,max(0,count($__segs)-$__depth));
define('BASE', $__segs ? '/'.implode('/',$__segs) : '');
const BLOOD = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
const SL_CITIES = [
 'Colombo'=>[6.9271,79.8612],'Kandy'=>[7.2906,80.6337],'Galle'=>[6.0535,80.2210],'Jaffna'=>[9.6615,80.0255],
 'Negombo'=>[7.2008,79.8737],'Panadura'=>[6.7132,79.9026],'Kalutara'=>[6.5854,79.9607],'Gampaha'=>[7.0840,80.0098],
 'Kurunegala'=>[7.4863,80.3647],'Matara'=>[5.9549,80.5550],'Anuradhapura'=>[8.3114,80.4037],'Ratnapura'=>[6.6828,80.3992],
 'Badulla'=>[6.9934,81.0550],'Batticaloa'=>[7.7310,81.6747],'Trincomalee'=>[8.5874,81.2152]];

// Inline SVG icon set (replaces emoji). Call icon('drop') inside a template.
function icon($n){
  static $i=[
  'drop'=>'<path d="M12 2.7s6 6.2 6 10.6a6 6 0 0 1-12 0c0-4.4 6-10.6 6-10.6z"/>',
  'users'=>'<path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20"/><circle cx="10" cy="8" r="3.5"/><path d="M20 20v-1.5a3.5 3.5 0 0 0-2.5-3.3M15.5 4.7a3.5 3.5 0 0 1 0 6.6"/>',
  'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
  'check'=>'<path d="M5 12.5l4.5 4.5L19 7.5"/>',
  'hospital'=>'<path d="M4 21V6a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v15M14 10h5a1 1 0 0 1 1 1v10M2 21h20M9 8v6M6 11h6"/>',
  'activity'=>'<path d="M3 12h4l3-8 4 16 3-8h4"/>',
  'alert'=>'<path d="M12 3.5L21.5 20h-19z"/><path d="M12 10v5M12 17.5v.01"/>',
  'target'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
  'lock'=>'<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
  'message'=>'<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.6A8 8 0 1 1 21 12z"/>',
  'pin'=>'<path d="M12 21s7-6.2 7-11.5a7 7 0 0 0-14 0C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
  'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
  'phone'=>'<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
  'shield'=>'<path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6z"/><path d="M9 12l2.2 2.2L15.5 10"/>',
  'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  'arrow'=>'<path d="M5 12h14M13 6l6 6-6 6"/>',
  'calendar'=>'<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
  'bell'=>'<path d="M6 16v-5a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
  'heart'=>'<path d="M12 20s-7.5-4.6-7.5-10a4.3 4.3 0 0 1 7.5-2.8A4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/>',
  'clipboard'=>'<rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4h6v3H9zM9 12h6M9 16h4"/>',
  'megaphone'=>'<path d="M4 10v4h3l7 4V6L7 10z"/><path d="M17.5 9.5a4 4 0 0 1 0 5"/>',
  'ban'=>'<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/>',
  'file'=>'<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4M10 13h5M10 17h5"/>',
  'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>'
  ];
  if(!isset($i[$n])) return '';
  return "<svg class='ico' viewBox='0 0 24 24' width='1em' height='1em' aria-hidden='true' focusable='false'>".$i[$n].'</svg>';
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect($p){ header('Location: '.BASE.'/'.ltrim($p,'/')); exit; }
function flash($m=null,$t='ok'){
  if($m!==null){ $_SESSION['flash']=[$m,$t]; return; }
  if(isset($_SESSION['flash'])){ [$m,$t]=$_SESSION['flash']; unset($_SESSION['flash']); return "<div class='alert $t'>".h($m)."</div>"; }
  return '';
}
function csrf(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(){ return "<input type='hidden' name='csrf' value='".csrf()."'>"; }
function csrf_check(){ if($_SERVER['REQUEST_METHOD']==='POST'){ $t=$_SESSION['csrf']??''; if($t==='' || !hash_equals($t, (string)($_POST['csrf']??''))) die('Invalid request token.'); } }
// Valid from/to dates for reports and logs: bad input falls back to the defaults, and a reversed range is swapped.
function date_range($df,$dt){ $ok=fn($x)=>(bool)(($o=DateTime::createFromFormat('Y-m-d',$x))&&$o->format('Y-m-d')===$x); $f=get('from',$df); $t=get('to',$dt); if(!$ok($f))$f=$df; if(!$ok($t))$t=$dt; if($f>$t)[$f,$t]=[$t,$f]; return [$f,$t]; }
function post($k,$d=''){ $v=$_POST[$k] ?? $d; return is_string($v)?trim($v):(string)$d; } // array-valued params (?x[]=1) are ignored, not fatal
function get($k,$d=''){ $v=$_GET[$k] ?? $d; return is_string($v)?trim($v):(string)$d; }
function badge($s){ $label=$s==='CLEARED'?'Checkup cleared':str_replace('_',' ',$s); return "<span class='badge b-".strtolower(h($s))."'>".h($label)."</span>"; } // CLEARED = a checkup outcome recorded in SEBD, never a clinical approval to donate
function blood_select($name,$sel='',$all=false){
  $o = $all ? "<option value=''>All groups</option>" : '';
  foreach(BLOOD as $b) $o .= "<option ".($b===$sel?'selected':'').">$b</option>";
  return "<select name='$name'>$o</select>";
}
function city_select($name,$sel=''){
  $o=''; foreach(SL_CITIES as $c=>$_) $o.="<option ".($c===$sel?'selected':'').">$c</option>";
  return "<select name='$name'>$o</select>";
}
function city_coords($c){ return SL_CITIES[$c] ?? [null,null]; }

function audit($pdo,$uid,$action,$table=null,$rid=null,$desc=''){
  $pdo->prepare("INSERT INTO audit_logs(user_id,action,table_name,record_id,description,ip_address) VALUES(?,?,?,?,?,?)")
      ->execute([$uid,$action,$table,$rid,$desc,$_SERVER['REMOTE_ADDR']??null]);
}
function notify($pdo,$uid,$type,$title,$msg,$req=null,$match=null){
  $pdo->prepare("INSERT INTO notifications(user_id,notification_type,title,message,related_request_id,related_match_id) VALUES(?,?,?,?,?,?)")
      ->execute([$uid,$type,$title,$msg,$req,$match]);
}
function my_donor($pdo){ $s=$pdo->prepare("SELECT d.*,u.full_name,u.email,u.phone FROM donors d JOIN users u USING(user_id) WHERE d.user_id=?"); $s->execute([$_SESSION['uid']]); return $s->fetch(); }
function my_org($pdo){ $s=$pdo->prepare("SELECT o.*,u.email FROM healthcare_organizations o JOIN users u USING(user_id) WHERE o.user_id=?"); $s->execute([$_SESSION['uid']]); return $s->fetch(); }

// Which donor groups can give to a recipient group
function compatible_donor_groups($r){
  $m=['A+'=>['A+','A-','O+','O-'],'A-'=>['A-','O-'],'B+'=>['B+','B-','O+','O-'],'B-'=>['B-','O-'],
      'AB+'=>BLOOD,'AB-'=>['AB-','A-','B-','O-'],'O+'=>['O+','O-'],'O-'=>['O-']];
  return $m[$r] ?? [];
}
function distance_km($a,$b,$c,$d){
  if($a===null||$b===null||$c===null||$d===null) return null;
  $r=6371; $dl=deg2rad($c-$a); $dn=deg2rad($d-$b);
  $x=sin($dl/2)**2+cos(deg2rad($a))*cos(deg2rad($c))*sin($dn/2)**2;
  return round(2*$r*asin(sqrt($x)),2);
}

// ---- Blood-group priority: exact group first, then compatible groups, scarce universal donors (O-) last ----
const RESERVED_GROUP='O-';
// Compatible donor groups for a recipient, in the order they should be asked:
// 1) the exact group; 2) every group with the same Rh sign (those that can serve fewer other recipients first, so they are not used up);
// 3) only then the opposite Rh sign - Rh-negative blood is scarce and Rh-negative patients can only use it;
// the order always ends with the universal donor O- (kept in reserve by run_matching).
function donor_group_order($recipient){
  $c=compatible_donor_groups($recipient); $reach=[];
  foreach(BLOOD as $g){ $reach[$g]=0; foreach(BLOOD as $rcp) if(in_array($g,compatible_donor_groups($rcp),true)) $reach[$g]++; }
  $rh=substr($recipient,-1);
  $key=fn($g)=>[$g===$recipient?0:1,substr($g,-1)===$rh?0:1,$reach[$g],array_search($g,BLOOD)];
  usort($c,fn($a,$b)=>$key($a)<=>$key($b));
  return $c;
}
// ---- Precise location (optional): the browser's position, otherwise the city centre ----
const SL_BOUNDS=['lat'=>[5.8,9.95],'lng'=>[79.4,82.0]]; // Sri Lanka bounding box
const GPS_MAX_FROM_CITY_KM=60;                          // a captured position must be near the selected city
// Reads geo_lat / geo_lng from the POST. Returns [lat,lng,precise(0|1),error|null]; empty input -> the city centre.
function location_from_post($city){
  [$clat,$clng]=city_coords($city); $la=post('geo_lat'); $ln=post('geo_lng');
  if($la===''&&$ln==='') return [$clat,$clng,0,null];
  if(!is_numeric($la)||!is_numeric($ln)) return [$clat,$clng,0,'The captured location is not valid. The city centre was not changed.'];
  $la=round((float)$la,3); $ln=round((float)$ln,3); // ~100 m: enough to rank donors, coarse enough for privacy
  if($la<SL_BOUNDS['lat'][0]||$la>SL_BOUNDS['lat'][1]||$ln<SL_BOUNDS['lng'][0]||$ln>SL_BOUNDS['lng'][1]) return [$clat,$clng,0,'The captured location is outside Sri Lanka.'];
  $dk=distance_km($clat,$clng,$la,$ln);
  if($dk===null||$dk>GPS_MAX_FROM_CITY_KM) return [$clat,$clng,0,'The captured location is about '.round((float)$dk).' km from '.$city.'. Select the correct city or capture your location again.'];
  return [$la,$ln,1,null];
}
// City whose centre is closest to a stored position (used to pre-select the city when a precise position is stored).
function nearest_city($lat,$lng,$fallback=''){
  if($lat===null||$lat===''||$lng===null||$lng==='') return $fallback;
  $best=$fallback; $bd=INF; foreach(SL_CITIES as $n=>$c){ $d=distance_km((float)$lat,(float)$lng,$c[0],$c[1]); if($d<$bd){ $bd=$d; $best=$n; } }
  return $best;
}
// Human wording for a stored distance. Exact GPS positions show one decimal; city-centre based distances show a rounded "about N km".
function distance_label($km,$precise){
  if($km===null||$km==='') return 'distance unknown';
  $km=(float)$km;
  return $precise ? number_format($km,1).' km' : ($km<1 ? 'same city' : 'about '.round($km).' km');
}

// Change inventory (delta) + write log. Creates the row if missing.
function adjust_inventory($pdo,$org,$bg,$delta,$type,$ref,$uid,$min=null){
  $s=$pdo->prepare("SELECT * FROM blood_inventory WHERE organization_id=? AND blood_group=? FOR UPDATE"); $s->execute([$org,$bg]); $i=$s->fetch();
  if(!$i){ $pdo->prepare("INSERT INTO blood_inventory(organization_id,blood_group,units_available,minimum_stock) VALUES(?,?,0,0)")->execute([$org,$bg]); $s->execute([$org,$bg]); $i=$s->fetch(); }
  $new=max(0,$i['units_available']+$delta);
  $pdo->prepare("UPDATE blood_inventory SET units_available=?, minimum_stock=? WHERE inventory_id=?")->execute([$new,$min??$i['minimum_stock'],$i['inventory_id']]);
  $pdo->prepare("INSERT INTO inventory_logs(inventory_id,organization_id,blood_group,change_type,quantity_change,previous_units,new_units,reference_id,changed_by) VALUES(?,?,?,?,?,?,?,?,?)")
      ->execute([$i['inventory_id'],$org,$bg,$type,$new-$i['units_available'],$i['units_available'],$new,$ref,$uid]);
}

// ---- Donor eligibility: Sri Lanka National Blood Transfusion Service (NBTS) donor leaflet ----
// age 18-60 (a FIRST-time donor must be 18-55), weight over 50 kg, at least 4 months since the last donation, haemoglobin >= 12.5 g/dl (measured at the checkup).
const DONATION_GAP_MONTHS = 4;
const DONOR_MIN_AGE = 18;
const DONOR_MAX_AGE_FIRST = 55;    // never donated before
const DONOR_MAX_AGE_REGULAR = 60;  // has donated before
const DONOR_MIN_WEIGHT_KG = 50;    // the leaflet says "over 50 kg": the weight must be GREATER than this
const DONOR_MIN_HB = 12.5;         // g/dl, recorded by the healthcare staff at the checkup (not stored in the system)
// date + N months, clamped to the end of a short month (31 Oct + 4 months = 28/29 Feb), the same way MySQL DATE_ADD works
function add_months_clamped($date,$m){
  $d=new DateTime($date); $day=(int)$d->format('j'); $d->modify('first day of this month')->modify("+$m months");
  $d->setDate((int)$d->format('Y'),(int)$d->format('n'),min($day,(int)$d->format('t'))); return $d->format('Y-m-d');
}
function donor_next_eligible_date($last){ return $last?add_months_clamped($last,DONATION_GAP_MONTHS):null; }
function donor_gap_ok($last){ return !$last || donor_next_eligible_date($last)<=date('Y-m-d'); }
function donor_max_age($hasDonated){ return $hasDonated?DONOR_MAX_AGE_REGULAR:DONOR_MAX_AGE_FIRST; }
// age in completed years must be 18..max (a person is "60" until their 61st birthday)
function donor_age_ok($dob,$hasDonated){ $a=(new DateTime($dob))->diff(new DateTime('today'))->y; return $a>=DONOR_MIN_AGE && $a<=donor_max_age($hasDonated); }
function donor_weight_ok($kg){ return (float)$kg>DONOR_MIN_WEIGHT_KG; }

// Automatic donor matching: compatibility -> verified -> available -> location/urgency
// Urgency rules (approval sheet): search radius widens step by step when too few donors are found / on each re-match.
const URGENCY_RADIUS = ['CRITICAL'=>[null],'HIGH'=>[50,100,null],'NORMAL'=>[20,50,100,null]]; // km, null = any distance
const URGENCY_LIMIT  = ['CRITICAL'=>100,'HIGH'=>10,'NORMAL'=>5]; // minimum group size notified per round
// How many non-O- donors per still-needed unit must exist before the universal donor O- is held back (O- stock is scarce).
// Critical = life-threatening, so O- may pad the pool like any compatible group; Normal uses O- only when there are fewer donors than units.
const RESERVED_POOL_FACTOR = ['CRITICAL'=>3,'HIGH'=>2,'NORMAL'=>1];
const MATCH_POOL_FACTOR = 3; // notify about 3 donors per unit still needed (not every notified donor accepts)
// Group-size ceiling = max(URGENCY_LIMIT, donors-needed x this). Keeps High larger than Normal for big requests (units >= 4), Critical stays widest.
const URGENCY_LIMIT_MULT = ['CRITICAL'=>2,'HIGH'=>1.5,'NORMAL'=>1];
// Required time must fit the urgency: Critical = within hours, High = same/next day, Normal = planned (at least a day ahead). Returns '' when OK.
function urgency_time_error($urg,$rt){
  $t=strtotime($rt); if($t===false) return '';
  if($urg==='CRITICAL' && $t>time()+86400) return 'A Critical request must be needed within 24 hours. Use High or Normal for a later need.';
  if($urg==='HIGH' && $t>time()+172800) return 'A High request must be needed within 2 days (same-day or next-day). Use Normal for a later need.';
  if($urg==='NORMAL' && $t<time()+86400) return 'A Normal (planned) request must be needed at least 24 hours ahead. Use High or Critical for an earlier need.';
  return '';
}
// ---- Automatic request upkeep (runs on page loads, no cron needed) ----
const EXPIRY_GRACE_HOURS = 24; // a request stays open this long after its required time so late donors can still be recorded
const ESCALATE_AFTER_MIN = ['CRITICAL'=>30,'HIGH'=>120,'NORMAL'=>720]; // minutes without enough accepted donors before the search is widened
const ESCALATE_MIN_WAIT = 10; // minutes: shortest wait before an automatic widening, even for a very close deadline
const AUTO_MAX_ROUNDS = 3;     // automatic widening rounds per request
function process_requests($pdo,$force=false){ // $force = true only from cron/process_requests.php (CLI, no login session)
  if(empty($_SESSION['uid']) && !$force) return;
  try{
    if(!$pdo->query("SELECT GET_LOCK('sebd_process_requests',0)")->fetchColumn()) return; // another page is already doing it
    $o=$pdo->query("SELECT r.request_id,r.organization_id,r.status,r.units_required,ho.user_id org_user FROM emergency_requests r JOIN healthcare_organizations ho USING(organization_id) WHERE r.status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') AND r.required_time < DATE_SUB(NOW(),INTERVAL ".EXPIRY_GRACE_HOURS." HOUR)")->fetchAll();
    foreach($o as $r){ // 1) required time passed (+grace) without being fulfilled -> close it, free the donors
      $pdo->beginTransaction();
      try{
        $q=$pdo->prepare("SELECT status FROM emergency_requests WHERE request_id=? FOR UPDATE"); $q->execute([$r['request_id']]);
        if(!in_array($q->fetchColumn(),OPEN_REQUEST,true)){ $pdo->rollBack(); continue; }
        $done=completed_units($pdo,$r['request_id']);
        $pdo->prepare("UPDATE emergency_requests SET status='CANCELLED',closed_at=NOW() WHERE request_id=?")->execute([$r['request_id']]);
        $note="Auto-closed: required time passed without the request being fulfilled. Units donated: $done/{$r['units_required']}.";
        $pdo->prepare("INSERT INTO emergency_request_history(request_id,old_status,new_status,changed_by,notes) VALUES(?,?,?,?,?)")->execute([$r['request_id'],$r['status'],'CANCELLED',$r['org_user'],$note]);
        $m=$pdo->prepare("SELECT m.match_id,m.donor_id,m.status,d.user_id FROM donor_matches m JOIN donors d USING(donor_id) WHERE m.request_id=? AND m.status IN ('NOTIFIED','ACCEPTED')"); $m->execute([$r['request_id']]);
        foreach($m->fetchAll() as $x){
          $reason=$x['status']==='ACCEPTED'?'No-show (accepted but not recorded as donated)':'No response - request expired';
          $pdo->prepare("UPDATE donor_matches SET status='DECLINED',responded_at=NOW(),decline_reason=? WHERE match_id=?")->execute([$reason,$x['match_id']]);
          if($x['status']==='ACCEPTED') restore_availability($pdo,(int)$x['donor_id']);
          notify($pdo,(int)$x['user_id'],'REQUEST_CANCELLED','Request closed',"Emergency request #{$r['request_id']} has closed because its required time has passed. You do not need to attend.",$r['request_id'],$x['match_id']);
        }
        notify($pdo,(int)$r['org_user'],'REQUEST_EXPIRED','Request closed automatically',"Request #{$r['request_id']} was closed because its required time passed without it being fulfilled ($done/{$r['units_required']} unit(s) donated). Raise a new request if blood is still needed.",$r['request_id']);
        audit($pdo,(int)$r['org_user'],'UPDATE','emergency_requests',$r['request_id'],$note);
        $pdo->commit();
      }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); }
    }
    // 2) not enough accepted donors after the urgency's waiting time -> widen the search automatically
    $e=$pdo->query("SELECT r.request_id,r.urgency,r.status,r.units_required,r.blood_group,ho.user_id org_user,
        (SELECT COUNT(*) FROM donor_matches m WHERE m.request_id=r.request_id AND m.status IN ('ACCEPTED','COMPLETED')) active,
        (SELECT COUNT(*) FROM emergency_request_history h WHERE h.request_id=r.request_id AND h.notes LIKE 'Re-match round%(automatic)%') autor,
        TIMESTAMPDIFF(MINUTE,r.created_at,r.required_time) win,
        GREATEST(r.created_at,COALESCE((SELECT MAX(h.changed_at) FROM emergency_request_history h WHERE h.request_id=r.request_id AND (h.notes LIKE 'Re-match round%' OR h.notes LIKE 'Request edited%urgency %')),r.created_at)) last_round,
        TIMESTAMPDIFF(MINUTE,GREATEST(r.created_at,COALESCE((SELECT MAX(h.changed_at) FROM emergency_request_history h WHERE h.request_id=r.request_id AND (h.notes LIKE 'Re-match round%' OR h.notes LIKE 'Request edited%urgency %')),r.created_at)),NOW()) waited
      FROM emergency_requests r JOIN healthcare_organizations ho USING(organization_id) WHERE r.status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') AND r.required_time>NOW()")->fetchAll();
    foreach($e as $r){
      $limit=min(ESCALATE_AFTER_MIN[$r['urgency']],max(ESCALATE_MIN_WAIT,intdiv((int)$r['win'],2))); // short deadline: do not wait longer than half of it
      if((int)$r['active']>=(int)$r['units_required'] || (int)$r['autor']>=AUTO_MAX_ROUNDS || (int)$r['waited']<$limit) continue;
      $rd=rematch_rounds($pdo,$r['request_id'])+1; $n=run_matching($pdo,$r['request_id'],$rd,$mi);
      $note="Re-match round $rd (automatic): $n additional donor(s) notified after {$limit} minutes without enough accepted donors (search widened".($n?'; '.match_info_text($mi,$r['blood_group']):'').").";
      $pdo->prepare("INSERT INTO emergency_request_history(request_id,old_status,new_status,changed_by,notes) VALUES(?,?,?,?,?)")->execute([$r['request_id'],$r['status'],$n&&$r['status']==='PENDING'?'MATCHING':$r['status'],$r['org_user'],$note]);
      notify($pdo,(int)$r['org_user'],'REQUEST_ESCALATED','Search widened automatically',"Request #{$r['request_id']} ({$r['urgency']}) still needs donors after $limit minutes. ".($n?"$n more donor(s) were notified.":'No more eligible donors were found - consider contacting other blood banks.'),$r['request_id']);
    }
  }catch(Throwable $x){ /* never break the page */ }
  finally{ try{ $pdo->query("SELECT RELEASE_LOCK('sebd_process_requests')"); }catch(Throwable $x){} }
}
// When the LAST open invitation is declined / withdrawn and the request still lacks accepted donors, widen the search NOW
// instead of waiting for the escalation timer (a Critical request would otherwise sit idle for up to 30 min), and tell the organisation.
// Counts as an automatic round (same note format), so AUTO_MAX_ROUNDS and the escalation timer stay consistent.
function rematch_if_exhausted($pdo,$rid){
  $got=false;
  try{
    $got=(bool)$pdo->query("SELECT GET_LOCK('sebd_rematch_".(int)$rid."',0)")->fetchColumn(); if(!$got) return;
    $s=$pdo->prepare("SELECT r.request_id,r.urgency,r.status,r.units_required,r.blood_group,r.required_time,ho.user_id org_user,
        (SELECT COUNT(*) FROM donor_matches m WHERE m.request_id=r.request_id AND m.status='NOTIFIED') pending,
        (SELECT COUNT(*) FROM donor_matches m WHERE m.request_id=r.request_id AND m.status IN ('ACCEPTED','COMPLETED')) active,
        (SELECT COUNT(*) FROM emergency_request_history h WHERE h.request_id=r.request_id AND h.notes LIKE 'Re-match round%(automatic)%') autor
      FROM emergency_requests r JOIN healthcare_organizations ho USING(organization_id) WHERE r.request_id=?");
    $s->execute([$rid]); $r=$s->fetch();
    if(!$r || !in_array($r['status'],OPEN_REQUEST,true) || strtotime($r['required_time'])<=time()) return;
    if((int)$r['pending']>0 || (int)$r['active']>=(int)$r['units_required']) return; // someone is still deciding, or enough donors accepted
    if((int)$r['autor']>=AUTO_MAX_ROUNDS){
      notify($pdo,(int)$r['org_user'],'REQUEST_ESCALATED','All notified donors declined',"Every donor notified for request #{$r['request_id']} ({$r['urgency']}) has declined or withdrawn and the automatic search limit is reached. Use Re-match or contact other blood banks.",$r['request_id']);
      return;
    }
    $rd=rematch_rounds($pdo,$r['request_id'])+1; $n=run_matching($pdo,$r['request_id'],$rd,$mi);
    $note="Re-match round $rd (automatic): $n additional donor(s) notified because every notified donor declined or withdrew (search widened".($n?'; '.match_info_text($mi,$r['blood_group']):'').").";
    $pdo->prepare("INSERT INTO emergency_request_history(request_id,old_status,new_status,changed_by,notes) VALUES(?,?,?,?,?)")->execute([$r['request_id'],$r['status'],$n&&$r['status']==='PENDING'?'MATCHING':$r['status'],$r['org_user'],$note]);
    notify($pdo,(int)$r['org_user'],'REQUEST_ESCALATED','All notified donors declined',"Every donor notified for request #{$r['request_id']} ({$r['urgency']}) has declined or withdrawn. ".($n?"$n more donor(s) were notified straight away.":'No more eligible donors were found - consider contacting other blood banks.'),$r['request_id']);
  }catch(Throwable $x){ /* never break the donor's response */ }
  finally{ if($got){ try{ $pdo->query("SELECT RELEASE_LOCK('sebd_rematch_".(int)$rid."')"); }catch(Throwable $x){} } }
}
function rematch_rounds($pdo,$rid){ $s=$pdo->prepare("SELECT COUNT(*) FROM emergency_request_history WHERE request_id=? AND notes LIKE 'Re-match round%'"); $s->execute([$rid]); return (int)$s->fetchColumn(); }
// Deadline-aware reach: a donor farther than TRAVEL_KMH x (time left - RESPONSE_BUFFER_MIN) cannot arrive by the needed time.
// Straight-line distance, so 40 km/h is deliberately conservative for Sri Lankan roads. Those donors are only asked AFTER every
// donor who can still arrive in time (they are never dropped: a request is never left without anyone to ask).
const TRAVEL_KMH = 40;
const RESPONSE_BUFFER_MIN = 30; // minutes for the donor to see the alert, decide and set off
function reach_km($requiredTime,$now=null){ $left=(strtotime($requiredTime)-($now??time()))/60; return max(0,($left-RESPONSE_BUFFER_MIN)/60*TRAVEL_KMH); }
// Notifies donors for a request. Order of preference:
//   0) donors who can arrive before the needed time (see reach_km); donors who cannot are asked only after all of those
//   within each of those two sets:
//   1) exact blood group, nearest first;  2) other compatible groups, least-universal first;  3) O- only when the above cannot cover the need.
// Within a group the search radius widens with the urgency ladder / re-match round only while there are still too few donors.
// Ties (e.g. many donors at the same city-centre coordinates) go to the donor who has rested longest, then the lowest id.
// $info (optional, by reference) receives ['exact'=>n,'compatible'=>n,'reserved'=>n,'far'=>n]; 'far' = notified donors who may not arrive in time (they are also counted in a tier).
function run_matching($pdo,$rid,$round=0,&$info=null){
  $info=['exact'=>0,'compatible'=>0,'reserved'=>0,'far'=>0];
  $s=$pdo->prepare("SELECT r.*,COALESCE(r.latitude,o.latitude) lat,COALESCE(r.longitude,o.longitude) lng FROM emergency_requests r JOIN healthcare_organizations o ON o.organization_id=r.organization_id WHERE r.request_id=?");
  $s->execute([$rid]); $r=$s->fetch(); if(!$r) return 0;
  $order=donor_group_order($r['blood_group']); $in=implode(',',array_fill(0,count($order),'?'));
  $q=$pdo->prepare("SELECT d.*,u.user_id uid FROM donors d JOIN users u ON u.user_id=d.user_id
    WHERE d.verification_status='VERIFIED' AND d.health_status='CLEARED' AND d.availability_status='AVAILABLE' AND u.status='ACTIVE' AND d.weight_kg>".DONOR_MIN_WEIGHT_KG." AND d.date_of_birth<=DATE_SUB(CURDATE(),INTERVAL ".DONOR_MIN_AGE." YEAR)
    AND d.date_of_birth>DATE_SUB(CURDATE(),INTERVAL IF(d.last_donation_date IS NOT NULL OR EXISTS(SELECT 1 FROM donations dn WHERE dn.donor_id=d.donor_id),".(DONOR_MAX_AGE_REGULAR+1).",".(DONOR_MAX_AGE_FIRST+1).") YEAR) AND d.blood_group IN ($in)
    AND (d.last_donation_date IS NULL OR DATE_ADD(d.last_donation_date,INTERVAL ".DONATION_GAP_MONTHS." MONTH)<=CURDATE())
    AND NOT EXISTS(SELECT 1 FROM donor_matches m WHERE m.request_id=? AND m.donor_id=d.donor_id)");
  $q->execute([...$order,$rid]);
  $ladder=URGENCY_RADIUS[$r['urgency']];
  $need=max(1,((int)$r['units_required']-completed_units($pdo,$rid))*MATCH_POOL_FACTOR); $limit=max(URGENCY_LIMIT[$r['urgency']],(int)ceil($need*URGENCY_LIMIT_MULT[$r['urgency']]));
  $reach=reach_km($r['required_time']); $pools=['near'=>[],'far'=>[]];
  foreach($q->fetchAll() as $d){ $d['km']=distance_km($r['lat'],$r['lng'],$d['latitude'],$d['longitude']); $pools[($d['km']!==null&&$d['km']<=$reach)?'near':'far'][$d['blood_group']][]=$d; }
  $sort=fn($a,$b)=>[$a['km']??9999,$a['last_donation_date']??'0000-00-00',$a['donor_id']]<=>[$b['km']??9999,$b['last_donation_date']??'0000-00-00',$b['donor_id']];
  $reserved=$r['blood_group']!==RESERVED_GROUP ? RESERVED_GROUP : null; // O- recipients can only use O-, so nothing is reserved for them
  $unitsLeft=max(1,(int)$r['units_required']-completed_units($pdo,$rid)); $reserveNeed=$unitsLeft*RESERVED_POOL_FACTOR[$r['urgency']];
  $start=min($round,count($ladder)-1); $c=[];
  $pick=function(array $byGroup,bool $far) use ($order,$reserved,$reserveNeed,$need,$ladder,$start,$sort,$r,&$c){
    foreach($order as $g){
      if(count($c)>=$need) break;                       // enough donors from the preferred groups: do not dip into the next group
      if($g===$reserved && count($c)>=$reserveNeed) break; // O- stays in reserve unless the other groups really cannot cover the need
      $pool=$byGroup[$g]??[]; if(!$pool) continue;
      $sel=[]; for($i=$start;$i<count($ladder);$i++){   // widen the radius for this group only while the total is still short
        $rad=$ladder[$i]; $sel=array_values(array_filter($pool,fn($d)=>$rad===null||($d['km']!==null&&$d['km']<=$rad)));
        if(count($c)+count($sel)>=$need) break;
      }
      usort($sel,$sort);
      if($g===$reserved) $sel=array_slice($sel,0,max(0,$reserveNeed-count($c))); // universal donors: only as many as are really missing
      foreach($sel as $d){ $d['tier']=$g===$r['blood_group']?'exact':($g===$reserved?'reserved':'compatible'); $d['far']=$far; $c[]=$d; }
    }
  };
  $pick($pools['near'],false);          // 1) everyone who can arrive in time (same group order as before)
  if(count($c)<$need) $pick($pools['far'],true); // 2) only if still short: donors who cannot arrive by the needed time
  $c=array_slice($c,0,$limit); $n=0;
  foreach($c as $d){
    $score=max(0,100-($d['km']??100)); if($d['tier']==='exact') $score=min(100,$score+5);
    $pdo->prepare("INSERT INTO donor_matches(request_id,donor_id,compatibility_status,distance_km,match_score) VALUES(?,?,'COMPATIBLE',?,?)")->execute([$rid,$d['donor_id'],$d['km'],$score]);
    $mid=$pdo->lastInsertId();
    notify($pdo,$d['uid'],'EMERGENCY_REQUEST',strtolower($r['urgency'])==='critical'?'CRITICAL Blood Request':'Emergency Blood Request',
      "{$r['urgency']} request for {$r['blood_group']} ({$r['units_required']} unit/s) at {$r['location']}.".($d['tier']==='exact'?'':" Your blood group {$d['blood_group']} is compatible."),$rid,$mid);
    $info[$d['tier']]++; if($d['far']) $info['far']++; $n++;
  }
  if($n && $r['status']==='PENDING'){
    $pdo->prepare("UPDATE emergency_requests SET status='MATCHING' WHERE request_id=?")->execute([$rid]);
    $pdo->prepare("INSERT INTO emergency_request_history(request_id,old_status,new_status,changed_by,notes) VALUES(?,?,?,?,?)")->execute([$rid,'PENDING','MATCHING',$_SESSION['uid'],"Matching started: $n donor(s) notified (".match_info_text($info,$r['blood_group']).")."]);
  }
  return $n;
}
function match_info_text($i,$bg){
  $p=["{$i['exact']} exact $bg"]; if($i['compatible']) $p[]="{$i['compatible']} compatible"; if($i['reserved']) $p[]="{$i['reserved']} universal O- (exact/compatible donors were not enough)";
  if(!empty($i['far'])) $p[]="{$i['far']} of them too far to arrive by the needed time (asked only because too few donors are within reach)";
  return implode(', ',$p);
}
// ---- Expiry-aware inventory (real-world blood bank rules) ----
// Whole blood / red cells: 35 days (CPDA-1) up to 42 days (additive solution). Platelets (5-7 days) are NOT tracked here.
const SHELF_LIFE_DAYS = 35;      // default when a batch is recorded without an explicit expiry date
const MAX_SHELF_LIFE_DAYS = 42;  // longest valid expiry accepted
const EXPIRY_WARN_DAYS = 7;
const MAX_MANUAL_UNITS = 500;    // sanity cap for one manual stock update (typo guard)      // "expiring soon" window shown to staff
function add_batch($pdo,$oid,$bg,$units,$expiry,$source,$donationId=null,$collected=null,$status='ACTIVE'){
  $pdo->prepare("INSERT INTO blood_batches(organization_id,blood_group,units_remaining,collected_date,expiry_date,source,donation_id,status) VALUES(?,?,?,?,?,?,?,?)")
      ->execute([$oid,$bg,$units,$collected??date('Y-m-d'),$expiry,$source,$donationId,$status]);
}
// First-Expired-First-Out: use the batches that expire soonest first. Call inside a transaction.
function consume_batches($pdo,$oid,$bg,$units){
  $s=$pdo->prepare("SELECT batch_id,units_remaining FROM blood_batches WHERE organization_id=? AND blood_group=? AND status='ACTIVE' AND expiry_date>=? ORDER BY expiry_date,batch_id FOR UPDATE");
  $s->execute([$oid,$bg,date('Y-m-d')]);
  foreach($s->fetchAll() as $b){
    if($units<=0) break; $take=min($units,(int)$b['units_remaining']); $left=(int)$b['units_remaining']-$take; $units-=$take;
    $pdo->prepare("UPDATE blood_batches SET units_remaining=?,status=? WHERE batch_id=?")->execute([$left,$left===0?'USED':'ACTIVE',$b['batch_id']]);
  }
}
// Move every batch past its expiry date out of stock (discard) and write an EXPIRED log. Safe to call on every page load.
function expire_stock($pdo,$oid,$uid=null){
  $uid=$uid??(int)($_SESSION['uid']??0); $today=date('Y-m-d');
  $s=$pdo->prepare("SELECT blood_group,SUM(units_remaining) u FROM blood_batches WHERE organization_id=? AND status='ACTIVE' AND expiry_date<? GROUP BY blood_group"); $s->execute([$oid,$today]); $rows=$s->fetchAll();
  // quarantined bags whose shelf life ran out were never in stock: mark them expired (to be discarded), no inventory change
  $pdo->prepare("UPDATE blood_batches SET status='EXPIRED' WHERE organization_id=? AND status='QUARANTINE' AND expiry_date<?")->execute([$oid,$today]);
  if(!$rows) return 0;
  $own=!$pdo->inTransaction(); if($own) $pdo->beginTransaction(); $tot=0;
  try{
    foreach($rows as $r){ $u=(int)$r['u']; $tot+=$u; if($u>0) adjust_inventory($pdo,$oid,$r['blood_group'],-$u,'EXPIRED',null,$uid); }
    $pdo->prepare("UPDATE blood_batches SET status='EXPIRED' WHERE organization_id=? AND status='ACTIVE' AND expiry_date<?")->execute([$oid,$today]);
    audit($pdo,$uid,'UPDATE','blood_batches',null,"Expired $tot unit(s) removed from stock.");
    if($tot>0){ // tell the organisation what was discarded
      $ou=$pdo->prepare("SELECT user_id FROM healthcare_organizations WHERE organization_id=?"); $ou->execute([$oid]); $ou=(int)$ou->fetchColumn();
      $parts=array_map(fn($r)=>$r['blood_group'].' '.(int)$r['u'],array_filter($rows,fn($r)=>(int)$r['u']>0));
      if($ou) notify($pdo,$ou,'STOCK_EXPIRED','Blood units expired',"$tot unit(s) passed their expiry date and were removed from stock: ".implode(', ',$parts).'. Discard the physical bags and confirm disposal on the Inventory page.');
    }
    if($own) $pdo->commit();
  }catch(Throwable $e){ if($own) $pdo->rollBack(); throw $e; }
  return $tot;
}
// Units that are still valid on the day they are needed (expiry_date >= needed date). This is what the request check uses.
function usable_stock($pdo,$oid,$bg,$needBy=null){
  $d=$needBy?date('Y-m-d',strtotime($needBy)):date('Y-m-d'); if($d<date('Y-m-d')) $d=date('Y-m-d');
  $s=$pdo->prepare("SELECT COALESCE(SUM(units_remaining),0) FROM blood_batches WHERE organization_id=? AND blood_group=? AND status='ACTIVE' AND expiry_date>=?"); $s->execute([$oid,$bg,$d]); return (int)$s->fetchColumn();
}
// Expired batches whose physical bags staff have not yet confirmed as discarded.
function pending_disposal($pdo,$oid){
  $s=$pdo->prepare("SELECT * FROM blood_batches WHERE organization_id=? AND status IN ('EXPIRED','REJECTED') AND disposed_at IS NULL AND units_remaining>0 ORDER BY expiry_date,batch_id"); $s->execute([$oid]); return $s->fetchAll();
}
function active_batches($pdo,$oid){
  $s=$pdo->prepare("SELECT * FROM blood_batches WHERE organization_id=? AND status='ACTIVE' AND expiry_date>=? ORDER BY blood_group,expiry_date"); $s->execute([$oid,date('Y-m-d')]); return $s->fetchAll();
}
// Business rule (approval sheet steps 13-15): a request is only allowed when own stock cannot cover the need
// without dropping below the configured minimum. Returns true when stock is sufficient (request NOT allowed).
function stock_sufficient($stock,$min,$need){ return $stock>=$need && ($stock-$need)>=$min; }
// Several open requests for the same blood group are allowed (different patients / wards / times). It is only treated as a
// duplicate submission when the same organisation asks for the same group at the same location with almost the same needed-by time.
const DUP_WINDOW_MIN=120;
function duplicate_request_exists($pdo,$oid,$bg,$location,$requiredTime,$excludeId=0){
  $s=$pdo->prepare("SELECT request_id FROM emergency_requests WHERE organization_id=? AND blood_group=? AND status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') AND request_id<>? AND LOWER(TRIM(location))=LOWER(TRIM(?)) AND ABS(TIMESTAMPDIFF(MINUTE,required_time,?))<? LIMIT 1");
  $s->execute([$oid,$bg,$excludeId,$location,$requiredTime,DUP_WINDOW_MIN]); return (int)$s->fetchColumn();
}
const CANCEL_REASONS = ['Patient no longer needs blood','Need met from own inventory','Need met from another source','Created by mistake','Other'];
const OPEN_REQUEST = ['PENDING','MATCHING','PARTIALLY_FULFILLED'];
// Tell every donor who is still involved (notified or accepted) about a change to / cancellation of a request.
function notify_request_donors($pdo,$rid,$type,$title,$msg,$restoreAccepted=false){
  $m=$pdo->prepare("SELECT m.match_id,m.donor_id,m.status,d.user_id FROM donor_matches m JOIN donors d USING(donor_id) WHERE m.request_id=? AND m.status IN ('NOTIFIED','ACCEPTED')"); $m->execute([$rid]);
  foreach($m->fetchAll() as $x){ notify($pdo,(int)$x['user_id'],$type,$title,$msg,$rid,$x['match_id']); if($restoreAccepted && $x['status']==='ACCEPTED') restore_availability($pdo,(int)$x['donor_id']); }
}
// Admin suspended an account: release everything that depends on it so nobody waits for / travels to a suspended party.
// Returns request ids that need a re-match after the caller commits (donor case).
function suspend_cleanup($pdo,$uid,$adminUid){
  $rematch=[];
  $d=$pdo->prepare("SELECT donor_id,availability_status FROM donors WHERE user_id=?"); $d->execute([$uid]); $don=$d->fetch();
  if($don){
    $m=$pdo->prepare("SELECT m.match_id,m.status,m.request_id,r.status rstatus,o.user_id org_user FROM donor_matches m JOIN emergency_requests r ON r.request_id=m.request_id JOIN healthcare_organizations o ON o.organization_id=r.organization_id WHERE m.donor_id=? AND m.status IN ('NOTIFIED','ACCEPTED') AND r.status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED')"); $m->execute([$don['donor_id']]);
    foreach($m->fetchAll() as $x){
      $pdo->prepare("UPDATE donor_matches SET status='DECLINED',responded_at=NOW(),decline_reason='Donor account suspended by the administrator' WHERE match_id=? AND status IN ('NOTIFIED','ACCEPTED')")->execute([$x['match_id']]);
      if($x['status']==='ACCEPTED'){ notify($pdo,(int)$x['org_user'],'DONOR_RESPONSE','Accepted donor no longer available',"A donor who accepted emergency request #{$x['request_id']} is no longer available (account suspended). The search is being widened.",$x['request_id'],$x['match_id']); $rematch[]=(int)$x['request_id']; }
    }
    $cr=$pdo->prepare("SELECT r.registration_id FROM donor_campaign_registrations r JOIN donation_campaigns c USING(campaign_id) WHERE r.donor_id=? AND r.status='REGISTERED' AND c.status IN ('UPCOMING','ACTIVE')"); $cr->execute([$don['donor_id']]); // a suspended donor cannot attend: free the campaign slot
    foreach($cr->fetchAll(PDO::FETCH_COLUMN) as $rg) $pdo->prepare("UPDATE donor_campaign_registrations SET status='CANCELLED' WHERE registration_id=? AND status='REGISTERED'")->execute([$rg]);
    if($don['availability_status']==='AVAILABLE'){ $pdo->prepare("UPDATE donors SET availability_status='UNAVAILABLE' WHERE donor_id=?")->execute([$don['donor_id']]); $pdo->prepare("INSERT INTO donor_availability_history(donor_id,availability_status) VALUES(?,'UNAVAILABLE')")->execute([$don['donor_id']]); }
  }
  $o=$pdo->prepare("SELECT organization_id FROM healthcare_organizations WHERE user_id=?"); $o->execute([$uid]); $org=$o->fetch();
  if($org){
    $q=$pdo->prepare("SELECT request_id,status,units_required FROM emergency_requests WHERE organization_id=? AND status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') FOR UPDATE"); $q->execute([$org['organization_id']]);
    foreach($q->fetchAll() as $r){
      $rid=(int)$r['request_id']; $done=completed_units($pdo,$rid);
      $pdo->prepare("UPDATE emergency_requests SET status='CANCELLED',closed_at=NOW() WHERE request_id=?")->execute([$rid]);
      $pdo->prepare("INSERT INTO emergency_request_history(request_id,old_status,new_status,changed_by,notes) VALUES(?,?,?,?,?)")->execute([$rid,$r['status'],'CANCELLED',$adminUid,"Cancelled automatically: the organization's account was suspended by the administrator. Units donated before cancellation: $done/{$r['units_required']}."]);
      notify_request_donors($pdo,$rid,'REQUEST_CANCELLED','Request cancelled',"Emergency request #$rid was cancelled because the organization's account was suspended. You do not need to attend.",true);
      $pdo->prepare("UPDATE donor_matches SET decline_reason=IF(status='ACCEPTED','Organization suspended (you had accepted)','Organization suspended'),status='DECLINED',responded_at=NOW() WHERE request_id=? AND status IN ('NOTIFIED','ACCEPTED')")->execute([$rid]);
    }
    // campaigns of a suspended organization cannot go ahead: cancel them and tell the registered donors
    $cp=$pdo->prepare("SELECT campaign_id,campaign_name FROM donation_campaigns WHERE organization_id=? AND status IN ('UPCOMING','ACTIVE') FOR UPDATE"); $cp->execute([$org['organization_id']]);
    foreach($cp->fetchAll() as $c){
      campaign_notify_donors($pdo,(int)$c['campaign_id'],'Campaign cancelled','"'.$c['campaign_name'].'" was cancelled because the organizing organization\'s account was suspended. You do not need to attend.',true);
      $pdo->prepare("UPDATE donation_campaigns SET status='CANCELLED' WHERE campaign_id=?")->execute([$c['campaign_id']]);
    }
  }
  return array_values(array_unique($rematch));
}
// A donor stopped being eligible (new health information, ...): release everything that depends on them.
// Declines their open NOTIFIED/ACCEPTED matches, cancels their upcoming campaign registrations and sets them UNAVAILABLE.
// Call inside a transaction. Returns request ids that need rematch_if_exhausted() AFTER the caller commits.
function release_donor_commitments($pdo,$donorId,$reason,$why){
  $rematch=[];
  $u=$pdo->prepare("SELECT user_id FROM donors WHERE donor_id=? FOR UPDATE"); $u->execute([$donorId]); $donorUid=(int)$u->fetchColumn();
  $m=$pdo->prepare("SELECT m.match_id,m.status,m.request_id,o.user_id org_user FROM donor_matches m JOIN emergency_requests r ON r.request_id=m.request_id JOIN healthcare_organizations o ON o.organization_id=r.organization_id WHERE m.donor_id=? AND m.status IN ('NOTIFIED','ACCEPTED') AND r.status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') FOR UPDATE"); $m->execute([$donorId]);
  foreach($m->fetchAll() as $x){
    $pdo->prepare("UPDATE donor_matches SET status='DECLINED',responded_at=NOW(),decline_reason=? WHERE match_id=? AND status IN ('NOTIFIED','ACCEPTED')")->execute([mb_substr($reason,0,150),$x['match_id']]);
    if($x['status']==='ACCEPTED'){
      notify($pdo,(int)$x['org_user'],'DONOR_RESPONSE','Accepted donor no longer available',"A donor who accepted emergency request #{$x['request_id']} is no longer available ($why). The search is being widened.",$x['request_id'],$x['match_id']);
      $rematch[]=(int)$x['request_id'];
    }
    if($donorUid) notify($pdo,$donorUid,'REQUEST_UPDATED','Your place was released',"Your place on emergency request #{$x['request_id']} was released: $why.",$x['request_id'],$x['match_id']);
  }
  $cr=$pdo->prepare("SELECT r.registration_id,c.campaign_name FROM donor_campaign_registrations r JOIN donation_campaigns c USING(campaign_id) WHERE r.donor_id=? AND r.status='REGISTERED' AND c.status IN ('UPCOMING','ACTIVE')"); $cr->execute([$donorId]);
  foreach($cr->fetchAll() as $g){
    $pdo->prepare("UPDATE donor_campaign_registrations SET status='CANCELLED' WHERE registration_id=? AND status='REGISTERED'")->execute([$g['registration_id']]);
    if($donorUid) notify($pdo,$donorUid,'CAMPAIGN_UPDATE','Campaign registration cancelled','Your registration for "'.$g['campaign_name'].'" was cancelled: '.$why.'.');
  }
  $a=$pdo->prepare("SELECT availability_status FROM donors WHERE donor_id=?"); $a->execute([$donorId]);
  if($a->fetchColumn()==='AVAILABLE'){ $pdo->prepare("UPDATE donors SET availability_status='UNAVAILABLE' WHERE donor_id=?")->execute([$donorId]); $pdo->prepare("INSERT INTO donor_availability_history(donor_id,availability_status) VALUES(?,'UNAVAILABLE')")->execute([$donorId]); }
  return array_values(array_unique($rematch));
}
function completed_units($pdo,$rid){ $s=$pdo->prepare("SELECT COUNT(*) FROM donor_matches WHERE request_id=? AND status='COMPLETED'"); $s->execute([$rid]); return (int)$s->fetchColumn(); }
function require_verified_org($o){ if(!$o || $o['verification_status']!=='VERIFIED'){ flash('Your organization must be verified by an administrator first.','err'); redirect('healthcare/dashboard.php'); } }
function close_request_history($pdo,$rid,$old,$new,$notes){ $pdo->prepare("INSERT INTO emergency_request_history(request_id,old_status,new_status,changed_by,notes) VALUES(?,?,?,?,?)")->execute([$rid,$old,$new,$_SESSION['uid'],$notes]); }

// Put a donor back to AVAILABLE if they are still eligible (used on withdrawal / cancellation / closure).
function restore_availability($pdo,$donorId){
  $s=$pdo->prepare("SELECT availability_status,verification_status,health_status,last_donation_date FROM donors WHERE donor_id=? FOR UPDATE"); $s->execute([$donorId]); $d=$s->fetch();
  if($d && $d['availability_status']==='UNAVAILABLE' && $d['verification_status']==='VERIFIED' && $d['health_status']==='CLEARED' && donor_gap_ok($d['last_donation_date'])){
    $pdo->prepare("UPDATE donors SET availability_status='AVAILABLE' WHERE donor_id=?")->execute([$donorId]);
    $pdo->prepare("INSERT INTO donor_availability_history(donor_id,availability_status) VALUES(?,'AVAILABLE')")->execute([$donorId]);
    return true;
  }
  return false;
}
// ---- Donation safety rules (real world: 1 donation = 1 bag; a donor cannot give again inside the 4-month gap) ----
const MAX_UNITS_PER_DONATION = 1;
// SQL fragment: request that the system closed because its needed-by time + grace passed (stored as CANCELLED, told apart by its history note).
const AUTOCLOSED_SQL = "EXISTS(SELECT 1 FROM emergency_request_history h WHERE h.request_id=r.request_id AND h.new_status='CANCELLED' AND h.notes LIKE 'Auto-closed%')";
// '' when the donor may donate now, otherwise the reason. Locks the donor row: call inside a transaction.
// $checkOpen = also refuse when the donor is currently ACCEPTED on an open emergency request (that donation belongs on the Matches page).
function donation_block_reason($pdo,$donorId,$checkOpen=false){
  $s=$pdo->prepare("SELECT d.last_donation_date,d.verification_status,d.health_status,u.status AS account_status FROM donors d JOIN users u ON u.user_id=d.user_id WHERE d.donor_id=? FOR UPDATE"); $s->execute([$donorId]); $st=$s->fetch(); $last=$st?$st['last_donation_date']:null;
  if(!$st) return 'Donor not found.';
  if($st['account_status']!=='ACTIVE') return 'The donor account is not active (suspended).';
  if($st['verification_status']!=='VERIFIED' || $st['health_status']!=='CLEARED') return 'The donor is no longer verified and health-cleared in SEBD (verification '.$st['verification_status'].', health '.$st['health_status'].'). A new healthcare checkup is required.';
  if(!donor_gap_ok($last)) return 'Donor donated within the last '.DONATION_GAP_MONTHS.' months (last donation '.$last.'). Next eligible date: '.donor_next_eligible_date($last).'.';
  if($checkOpen){
    $q=$pdo->prepare("SELECT m.request_id FROM donor_matches m JOIN emergency_requests r USING(request_id) WHERE m.donor_id=? AND m.status='ACCEPTED' AND r.status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') LIMIT 1"); $q->execute([$donorId]); $rq=$q->fetchColumn();
    if($rq) return "This donor has accepted open emergency request #$rq. Record that donation from the Matches page (or release the donor from the request first) so it is not counted twice.";
  }
  return '';
}
// One shared routine for every donation (emergency, walk-in, campaign, voluntary). Call inside a transaction.
// $d needs donor_id, blood_group, user_id. Returns the new donation_id.
// $when = when the blood was really collected ('Y-m-d H:i:s'); default now. A campaign donation entered the next day must keep the campaign's date,
// because the 35-day shelf life and the donor's 4-month gap both count from the collection date, not from the day it was typed in.
function record_donation($pdo,$d,$oid,$type,$units,$notes,$campaignId,$matchId,$uid,$when=null){
  $when=$when??date('Y-m-d H:i:s'); $day=substr($when,0,10);
  $pdo->prepare("INSERT INTO donations(donor_id,organization_id,campaign_id,match_id,donation_type,blood_group,units_donated,donation_date,notes) VALUES(?,?,?,?,?,?,?,?,?)")->execute([$d['donor_id'],$oid,$campaignId,$matchId,$type,$d['blood_group'],$units,$when,$notes]);
  $did=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO donation_history(donor_id,donation_id,donation_date,organization_id,blood_group,units_donated) VALUES(?,?,?,?,?,?)")->execute([$d['donor_id'],$did,$when,$oid,$d['blood_group'],$units]);
  // Real world: the bag is held in QUARANTINE until the screening tests are done; it only becomes stock when released (inventory.php).
  add_batch($pdo,$oid,$d['blood_group'],$units,date('Y-m-d',strtotime($day.' +'.SHELF_LIFE_DAYS.' days')),'DONATION',$did,$day,'QUARANTINE');
  $pdo->prepare("UPDATE donors SET last_donation_date=?,availability_status='UNAVAILABLE' WHERE donor_id=?")->execute([$day,$d['donor_id']]);
  $pdo->prepare("INSERT INTO donor_availability_history(donor_id,availability_status) VALUES(?,'UNAVAILABLE')")->execute([$d['donor_id']]);
  if($campaignId) $pdo->prepare("UPDATE donor_campaign_registrations SET status='ATTENDED' WHERE campaign_id=? AND donor_id=? AND status IN ('REGISTERED','CANCELLED')")->execute([$campaignId,$d['donor_id']]); // CANCELLED too: closing the campaign cancels open registrations, but a donor who really donated attended
  notify($pdo,(int)$d['user_id'],'DONATION_RECORDED','Donation recorded','Your '.strtolower(str_replace('_',' ',$type)).' donation of '.$units.' unit(s) was recorded on '.$day.'. Thank you! Your availability is now unavailable for the '.DONATION_GAP_MONTHS.'-month interval between donations.');
  return $did;
}

// ---- Organization registration certificate (stored outside public access: uploads/org_certificates is deny-all) ----
const ORG_CERT_DIR = __DIR__.'/../uploads/org_certificates';
const ORG_CERT_MAX = 2097152; // 2 MB
// Validate an uploaded certificate. Returns ['tmp'=>..,'ext'=>..] or null (and appends to $err).
function org_cert_check($f,&$err){
  if(!$f || ($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE){ $err[]='Upload your registration certificate or licence.'; return null; }
  if($f['error']!==UPLOAD_ERR_OK){ $err[]=in_array($f['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE])?'Certificate file is too large (max 2 MB).':'Certificate upload failed. Please try again.'; return null; }
  if($f['size']<=0){ $err[]='Certificate file is empty.'; return null; }
  if($f['size']>ORG_CERT_MAX){ $err[]='Certificate file is too large (max 2 MB).'; return null; }
  $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
  $ok=['pdf'=>['application/pdf'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png']];
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
  if(!isset($ok[$ext]) || !in_array($mime,$ok[$ext],true)){ $err[]='Certificate must be a valid PDF, JPG or PNG file.'; return null; }
  return ['tmp'=>$f['tmp_name'],'ext'=>$ext==='jpeg'?'jpg':$ext];
}
// Save under a random name; returns the stored file name or null.
function org_cert_save($c){
  if(!is_dir(ORG_CERT_DIR)) @mkdir(ORG_CERT_DIR,0755,true);
  $name=bin2hex(random_bytes(16)).'.'.$c['ext'];
  return move_uploaded_file($c['tmp'],ORG_CERT_DIR.'/'.$name) ? $name : null;
}
function org_cert_valid_name($n){ return is_string($n) && preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/',$n); }
function org_cert_delete($n){ if(org_cert_valid_name($n)) @unlink(ORG_CERT_DIR.'/'.$n); }

// ---- Campaign rules (real world: one organization has a limited team and beds) ----
const CAMPAIGN_MAX_PER_DAY      = 3;    // campaigns one organization may run on the same day
const CAMPAIGN_MIN_MINUTES      = 60;   // shortest session
const CAMPAIGN_MAX_MINUTES      = 720;  // longest session (12 hours)
const CAMPAIGN_MAX_ADVANCE_DAYS = 365;  // how far ahead a campaign may be published
const CAMPAIGN_MAX_DONORS       = 5000;
const CAMPAIGN_DONATION_GRACE_HOURS = 24; // a campaign donation may be recorded until this long after the session ends
// SQL: moment the campaign session ends (alias c)
const CAMPAIGN_END_SQL = "TIMESTAMP(c.campaign_date,c.end_time)";
// '' when the details are acceptable, otherwise the message. $excludeId = the campaign being edited.
function campaign_check($pdo,$oid,$date,$start,$end,$location,$max,$excludeId=0){
  $dt=DateTime::createFromFormat('!Y-m-d',$date);
  if(!$dt || $dt->format('Y-m-d')!==$date) return 'Enter a valid date.';
  if($date<date('Y-m-d')) return 'The date cannot be in the past.';
  if($date>date('Y-m-d',strtotime('+'.CAMPAIGN_MAX_ADVANCE_DAYS.' days'))) return 'A campaign can be published at most '.CAMPAIGN_MAX_ADVANCE_DAYS.' days ahead.';
  if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$start)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$end)) return 'Enter a valid start and end time.';
  $mins=(strtotime("2000-01-01 $end")-strtotime("2000-01-01 $start"))/60;
  if($mins<=0) return 'End time must be after the start time.';
  if($mins<CAMPAIGN_MIN_MINUTES) return 'A session must last at least '.(CAMPAIGN_MIN_MINUTES/60).' hour.';
  if($mins>CAMPAIGN_MAX_MINUTES) return 'A session can last at most '.(CAMPAIGN_MAX_MINUTES/60).' hours.';
  if($date===date('Y-m-d') && $end.':00'<=date('H:i:s')) return 'This session has already ended today. Choose a later time or another date.';
  if($max!==null && $max>CAMPAIGN_MAX_DONORS) return 'Max donors cannot be more than '.CAMPAIGN_MAX_DONORS.'.';
  if($max!==null && $excludeId){
    $r=$pdo->prepare("SELECT COUNT(*) FROM donor_campaign_registrations WHERE campaign_id=? AND status IN ('REGISTERED','ATTENDED')"); $r->execute([$excludeId]);
    if($max<(int)$r->fetchColumn()) return 'Max donors cannot be lower than the donors already registered ('.(int)$r->fetchColumn().').';
  }
  $s=$pdo->prepare("SELECT campaign_id,location,start_time,end_time FROM donation_campaigns WHERE organization_id=? AND campaign_date=? AND status IN ('UPCOMING','ACTIVE') AND campaign_id<>?"); $s->execute([$oid,$date,$excludeId]); $same=$s->fetchAll();
  if(count($same)>=CAMPAIGN_MAX_PER_DAY) return 'Your organization already has '.CAMPAIGN_MAX_PER_DAY.' campaigns on this date.';
  foreach($same as $x){
    if(mb_strtolower(trim($x['location']))===mb_strtolower(trim($location)) && $start.':00'<$x['end_time'] && $end.':00'>$x['start_time'])
      return 'Another campaign of yours is already at this location during these hours.';
  }
  return '';
}
// Tell every donor who is still REGISTERED for a campaign. $cancel = also cancel their registration.
function campaign_notify_donors($pdo,$cid,$title,$msg,$cancel=false){
  $s=$pdo->prepare("SELECT d.user_id FROM donor_campaign_registrations r JOIN donors d USING(donor_id) WHERE r.campaign_id=? AND r.status='REGISTERED'"); $s->execute([$cid]);
  foreach($s->fetchAll(PDO::FETCH_COLUMN) as $uid) notify($pdo,(int)$uid,'CAMPAIGN_UPDATE',$title,$msg);
  if($cancel) $pdo->prepare("UPDATE donor_campaign_registrations SET status='CANCELLED' WHERE campaign_id=? AND status='REGISTERED'")->execute([$cid]);
}
// Close campaigns whose session has ended: still-registered donors (never marked attended) are released and told.
function campaign_auto_complete($pdo){
  try{
    $rows=$pdo->query("SELECT c.campaign_id,c.campaign_name FROM donation_campaigns c WHERE c.status IN ('UPCOMING','ACTIVE') AND ".CAMPAIGN_END_SQL." < NOW()")->fetchAll();
    foreach($rows as $c){
      $pdo->beginTransaction();
      try{
        $u=$pdo->prepare("UPDATE donation_campaigns SET status='COMPLETED' WHERE campaign_id=? AND status IN ('UPCOMING','ACTIVE')"); $u->execute([$c['campaign_id']]);
        if($u->rowCount()) campaign_notify_donors($pdo,(int)$c['campaign_id'],'Campaign ended','"'.$c['campaign_name'].'" has ended and you were not marked as attended, so your registration was closed. You can join another campaign any time.',true);
        $pdo->commit();
      }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); }
    }
  }catch(Throwable $e){}
}
