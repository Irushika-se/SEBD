<?php require_once __DIR__.'/../includes/auth.php'; require_role('DONOR');
$d=my_donor($pdo); $bg=get('blood_group'); if(!in_array($bg,BLOOD,true)) $bg='';
$q=get('q'); $city=get('city'); if($city!=='' && !array_key_exists($city,SL_CITIES)) $city='';
$from=get('from'); if($from!=='' && !(($fo=DateTime::createFromFormat('!Y-m-d',$from)) && $fo->format('Y-m-d')===$from)) $from='';
$view=get('view'); if(!in_array($view,['upcoming','past','mine'],true)) $view='upcoming';
$isCleared=$d['verification_status']==='VERIFIED'&&$d['health_status']==='CLEARED';
if($_SERVER['REQUEST_METHOD']==='POST' && in_array(post('action'),['BOOK_WALKIN','CANCEL_WALKIN'],true)){
  if(post('action')==='CANCEL_WALKIN'){
    $u=$pdo->prepare("UPDATE donor_appointments SET status='CANCELLED' WHERE appointment_id=? AND donor_id=? AND status IN ('PENDING','CONFIRMED')"); $u->execute([(int)post('appointment_id'),$d['donor_id']]);
    if($u->rowCount()) flash('Your walk-in booking was cancelled.'); else flash('No active booking was found.','err');
  } else {
    $oid=(int)post('organization_id'); $wd=post('walkin_date'); $wt=post('walkin_time');
    $okDate=($x=DateTime::createFromFormat('!Y-m-d',$wd)) && $x->format('Y-m-d')===$wd && $wd>=date('Y-m-d') && $wd<=date('Y-m-d',strtotime('+5 days'));
    $okTime=preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$wt)===1;
    $os=$pdo->prepare("SELECT organization_id,user_id,organization_name FROM healthcare_organizations WHERE organization_id=? AND verification_status='VERIFIED'"); $os->execute([$oid]); $org=$os->fetch();
    $ex=$pdo->prepare("SELECT COUNT(*) FROM donor_appointments WHERE donor_id=? AND status IN ('PENDING','CONFIRMED') AND requested_date>=CURDATE()"); $ex->execute([$d['donor_id']]);
    $nx=donor_next_eligible_date($d['last_donation_date']);
    if(!$isCleared) flash('Only verified, health-cleared donors can book through SEBD.','err');
    elseif(!$org) flash('Choose a verified organization.','err');
    elseif(!$okDate) flash('Choose a date from today up to 5 days ahead.','err');
    elseif(!$okTime) flash('Choose a valid time.','err');
    elseif($wd===date('Y-m-d') && $wt<=date('H:i')) flash('Choose a time later than now.','err');
    elseif($nx && $nx>$wd) flash('You cannot donate on this date. Your next eligible date is '.$nx.' ('.DONATION_GAP_MONTHS.' months after your last donation).','err');
    elseif((int)$ex->fetchColumn()>0) flash('You already have an active walk-in booking. Cancel it first to book another.','err');
    else{
      $pdo->prepare("INSERT INTO donor_appointments(donor_id,organization_id,requested_date,requested_time,reason,status) VALUES(?,?,?,?,'WALK_IN','CONFIRMED')")->execute([$d['donor_id'],$oid,$wd,$wt.':00']);
      notify($pdo,(int)$org['user_id'],'WALKIN_BOOKING','New walk-in booking',$d['full_name'].' booked a walk-in donation on '.$wd.' at '.$wt.'.');
      audit($pdo,$_SESSION['uid'],'CREATE','donor_appointments',(int)$pdo->lastInsertId(),'Donor booked a walk-in donation.'); flash('Walk-in booked for '.$wd.' at '.$wt.' at '.$org['organization_name'].'.');
    }
  }
  redirect('donor/campaigns.php');
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  $cid=(int)post('campaign_id'); $act=post('action');
  $c=$pdo->prepare("SELECT * FROM donation_campaigns WHERE campaign_id=? AND status IN ('UPCOMING','ACTIVE') AND TIMESTAMP(campaign_date,end_time)>=NOW()"); $c->execute([$cid]); $campaign=$c->fetch();
  if(!$campaign) flash('This campaign is no longer open for registration.','err');
  elseif($act==='REGISTER'){
    if(!$isCleared) flash('Only verified, health-cleared donors can register through SEBD.','err');
    else{
      $nextOk=donor_next_eligible_date($d['last_donation_date']);
      $pdo->beginTransaction();
      try{
        // one registration at a time per campaign, so the last slot is not given twice. The campaign is re-read UNDER the lock (it may have been closed / edited since the page loaded)
        $lk=$pdo->prepare("SELECT * FROM donation_campaigns WHERE campaign_id=? AND status IN ('UPCOMING','ACTIVE') AND TIMESTAMP(campaign_date,end_time)>=NOW() FOR UPDATE"); $lk->execute([$cid]); $campaign=$lk->fetch();
        if(!$campaign) throw new RuntimeException('This campaign is no longer open for registration.');
        // lock the donor row too: serialises the same donor's registrations for DIFFERENT campaigns (overlap / interval checks) and gives fresh eligibility
        $fd=$pdo->prepare("SELECT d.verification_status,d.health_status,d.last_donation_date,u.status AS account_status FROM donors d JOIN users u ON u.user_id=d.user_id WHERE d.donor_id=? FOR UPDATE"); $fd->execute([$d['donor_id']]); $fresh=$fd->fetch();
        if(!$fresh || $fresh['account_status']!=='ACTIVE' || $fresh['verification_status']!=='VERIFIED' || $fresh['health_status']!=='CLEARED') throw new RuntimeException('Only verified, health-cleared donors can register through SEBD.');
        $nextOk=donor_next_eligible_date($fresh['last_donation_date']);
        $existing=$pdo->prepare('SELECT status FROM donor_campaign_registrations WHERE campaign_id=? AND donor_id=?'); $existing->execute([$cid,$d['donor_id']]); $status=$existing->fetchColumn();
        $tk=$pdo->prepare("SELECT COUNT(*) FROM donor_campaign_registrations WHERE campaign_id=? AND status IN ('REGISTERED','ATTENDED')"); $tk->execute([$cid]); $isFull=!empty($campaign['max_donors']) && (int)$tk->fetchColumn()>=(int)$campaign['max_donors'];
        $ov=$pdo->prepare("SELECT c2.campaign_name FROM donor_campaign_registrations r2 JOIN donation_campaigns c2 USING(campaign_id) WHERE r2.donor_id=? AND r2.status='REGISTERED' AND c2.campaign_id<>? AND c2.status IN ('UPCOMING','ACTIVE') AND c2.campaign_date=? AND c2.start_time<? AND c2.end_time>? LIMIT 1"); $ov->execute([$d['donor_id'],$cid,$campaign['campaign_date'],$campaign['end_time'],$campaign['start_time']]); $clash=$ov->fetchColumn();
        // a donor can give blood only once per interval, so two upcoming registrations must also be at least DONATION_GAP_MONTHS apart
        $og=$pdo->prepare("SELECT c2.campaign_name,c2.campaign_date FROM donor_campaign_registrations r2 JOIN donation_campaigns c2 USING(campaign_id) WHERE r2.donor_id=? AND r2.status='REGISTERED' AND c2.campaign_id<>? AND c2.status IN ('UPCOMING','ACTIVE')"); $og->execute([$d['donor_id'],$cid]); $gapClash=null;
        foreach($og->fetchAll() as $x){ if($campaign['campaign_date']<add_months_clamped($x['campaign_date'],DONATION_GAP_MONTHS) && $x['campaign_date']<add_months_clamped($campaign['campaign_date'],DONATION_GAP_MONTHS)){ $gapClash=$x; break; } }
        if($status==='REGISTERED') flash('You are already registered for this campaign.','err');
        elseif($status==='ATTENDED') flash('You already attended this campaign.','err');
        elseif($nextOk && $nextOk>$campaign['campaign_date']) flash('You cannot donate on this date. Your next eligible date is '.$nextOk.' ('.DONATION_GAP_MONTHS.' months after your last donation).','err');
        elseif($clash) flash('You are already registered for "'.$clash.'" at the same time.','err');
        elseif($gapClash) flash('You are already registered for "'.$gapClash['campaign_name'].'" on '.$gapClash['campaign_date'].'. Donors must wait '.DONATION_GAP_MONTHS.' months between donations, so you cannot register for another session within that period.','err');
        elseif($isFull) flash('Sorry, this campaign is full.','err');
        elseif($status!==false){$pdo->prepare("UPDATE donor_campaign_registrations SET status='REGISTERED' WHERE campaign_id=? AND donor_id=?")->execute([$cid,$d['donor_id']]); flash('Your campaign registration has been restored.'); audit($pdo,$_SESSION['uid'],'REGISTER','donor_campaign_registrations',$cid,'Donor campaign registration updated.');}
        else{$pdo->prepare("INSERT INTO donor_campaign_registrations(campaign_id,donor_id,status) VALUES(?,?,'REGISTERED')")->execute([$cid,$d['donor_id']]); flash('You are registered for this campaign. Please attend at the stated location and date.'); audit($pdo,$_SESSION['uid'],'REGISTER','donor_campaign_registrations',$cid,'Donor campaign registration updated.');}
        $pdo->commit();
      }catch(RuntimeException $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash($e->getMessage(),'err');
      }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('Could not register. Please try again.','err'); }
    }
  } elseif($act==='CANCEL'){
    $u=$pdo->prepare("UPDATE donor_campaign_registrations SET status='CANCELLED' WHERE campaign_id=? AND donor_id=? AND status='REGISTERED'"); $u->execute([$cid,$d['donor_id']]);
    if($u->rowCount()) flash('Your campaign registration was cancelled.'); else flash('No active registration was found.','err');
  }
  $qs=http_build_query(array_intersect_key($_GET,array_flip(['blood_group','q','city','from','view']))); redirect('donor/campaigns.php'.($qs?'?'.$qs:''));
}
$sql="SELECT c.*,o.organization_name,o.city org_city,o.latitude olat,o.longitude olng,
 (SELECT status FROM donor_campaign_registrations r WHERE r.campaign_id=c.campaign_id AND r.donor_id=?) registration_status,(SELECT COUNT(*) FROM donor_campaign_registrations rc2 WHERE rc2.campaign_id=c.campaign_id AND rc2.status IN ('REGISTERED','ATTENDED')) taken
 FROM donation_campaigns c JOIN healthcare_organizations o ON o.organization_id=c.organization_id WHERE 1=1"; $p=[$d['donor_id']];
if($view==='upcoming') $sql.=" AND c.status IN ('UPCOMING','ACTIVE') AND ".CAMPAIGN_END_SQL.">=NOW()";
elseif($view==='past') $sql.=" AND (".CAMPAIGN_END_SQL."<NOW() OR c.status IN ('COMPLETED','CANCELLED'))";
else { $sql.=" AND EXISTS(SELECT 1 FROM donor_campaign_registrations rr WHERE rr.campaign_id=c.campaign_id AND rr.donor_id=? AND rr.status IN ('REGISTERED','ATTENDED'))"; $p[]=$d['donor_id']; }
if($q!==''){$sql.=' AND (c.campaign_name LIKE ? OR c.location LIKE ? OR o.organization_name LIKE ?)';array_push($p,"%$q%","%$q%","%$q%");}
if($city){$sql.=' AND o.city=?';$p[]=$city;}
if($from){$sql.=' AND c.campaign_date>=?';$p[]=$from;}
$s=$pdo->prepare($sql.($view==='past'?' ORDER BY c.campaign_date DESC':' ORDER BY c.campaign_date')); $s->execute($p); $rows=$s->fetchAll();
$nextEligible=donor_next_eligible_date($d['last_donation_date']);
$title='Donation Campaigns'; $page_intro='Donation campaigns you can reserve a place in, and walk-in donations you can book.'; require '../includes/header.php'; ?>
<?php if(!$isCleared): ?><div class="alert">You can browse campaigns, but SEBD campaign registration is available to verified, health-cleared donors. A healthcare professional determines your actual eligibility to donate.</div><?php endif; ?>
<div class="filter"><?php foreach(['upcoming'=>'Upcoming','mine'=>'My registrations','past'=>'Past'] as $k=>$l): ?><a class="btn sm <?= $view===$k?'':'alt' ?>" href="?view=<?= $k ?>"><?= h($l) ?></a> <?php endforeach; ?></div><br>
<?php if($view!=='past'):
 $wo=$pdo->query("SELECT organization_id,organization_name,city FROM healthcare_organizations WHERE verification_status='VERIFIED' ORDER BY organization_name")->fetchAll();
 $wb=$pdo->prepare("SELECT a.*,o.organization_name,o.city FROM donor_appointments a JOIN healthcare_organizations o USING(organization_id) WHERE a.donor_id=? AND a.status IN ('PENDING','CONFIRMED') AND a.requested_date>=CURDATE() ORDER BY a.requested_date,a.requested_time"); $wb->execute([$d['donor_id']]); $wbk=$wb->fetchAll(); ?>
<div class="card"><h2>Book a walk-in donation</h2><p class="muted">Pick a verified organization and a date within the next 5 days.</p>
<?php foreach($wbk as $b): ?><p><b><?= h($b['organization_name']) ?></b> (<?= h($b['city']) ?>) &ndash; <?= h($b['requested_date']) ?> at <?= h(substr($b['requested_time'],0,5)) ?> <?= badge($b['status']) ?> <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int)$b['appointment_id'] ?>"><button class="btn sm alt" name="action" value="CANCEL_WALKIN" data-confirm="Cancel this walk-in booking?">Cancel</button></form></p><?php endforeach; ?>
<?php if(!$wbk && $isCleared): ?><form method="post"><?= csrf_field() ?><div class="row"><div><label>Organization</label><select name="organization_id" required><?php foreach($wo as $x) echo "<option value='{$x['organization_id']}'>".h($x['organization_name'])." (".h($x['city']).")</option>"; ?></select></div><div><label>Date</label><input type="date" name="walkin_date" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d',strtotime('+5 days')) ?>" required></div><div><label>Time</label><input type="time" name="walkin_time" value="09:00" required></div></div><button class="btn" name="action" value="BOOK_WALKIN">Book walk-in</button></form>
<?php elseif(!$isCleared): ?><span class="muted">Verification is required to book through SEBD.</span><?php endif; ?></div>
<?php endif; ?>
<form class="filter" method="get"><input type="hidden" name="view" value="<?= h($view) ?>"><input name="q" placeholder="Search name, location, organizer" value="<?= h($q) ?>"><select name="city"><option value="">All cities</option><?php foreach(SL_CITIES as $cn=>$_): ?><option <?= $city===$cn?'selected':'' ?>><?= h($cn) ?></option><?php endforeach; ?></select><label class="dl">On or after <input type="date" name="from" value="<?= h($from) ?>"></label><button class="btn">Filter</button> <a class="btn alt" href="campaigns.php">Reset</a></form>
<?php foreach($rows as $c): $full=!empty($c['max_donors'])&&(int)$c['taken']>=(int)$c['max_donors']; $km=distance_km($d['latitude'],$d['longitude'],$c['olat'],$c['olng']); $notEligibleOnDate=$nextEligible && $nextEligible>$c['campaign_date']; ?><div class="card"><div class="row"><div><h2><?= h($c['campaign_name']) ?></h2><?= badge($c['status']) ?></div><div><?= $c['registration_status']==='REGISTERED'?'<span class="badge b-verified">Registered</span>':($c['registration_status']==='ATTENDED'?'<span class="badge b-verified">Attended</span>':'') ?></div></div><p><?= nl2br(h($c['description']??'')) ?></p>
<dl class="meta"><div><dt>Organizer</dt><dd><?= h($c['organization_name']) ?> (<?= h($c['org_city']) ?>)</dd></div><div><dt>Date</dt><dd><?= h($c['campaign_date']) ?></dd></div><?php if(!empty($c['start_time'])): ?><div><dt>Time</dt><dd><?= h(date('g:i A',strtotime($c['start_time']))) ?><?php if(!empty($c['end_time'])): ?> &ndash; <?= h(date('g:i A',strtotime($c['end_time']))) ?><?php endif; ?></dd></div><?php endif; ?><div><dt>Location</dt><dd><?= h($c['location']) ?><?php if($km!==null): ?> <span class="muted">(approx. <?= h($km) ?> km from you)</span><?php endif; ?></dd></div><?php if(!empty($c['max_donors'])): ?><div><dt>Slots</dt><dd><?= max(0,(int)$c['max_donors']-(int)$c['taken']) ?> left of <?= (int)$c['max_donors'] ?></dd></div><?php endif; ?></dl>
<?php if($view!=='past' && $notEligibleOnDate): ?><div class="alert">You will not be eligible to donate on this date. Your next eligible date is <?= h($nextEligible) ?> (<?= DONATION_GAP_MONTHS ?> months after your last donation).</div><?php endif; ?>
<?php if($view==='past'): ?><span class="muted">This event has ended.</span>
<?php elseif($c['registration_status']==='REGISTERED'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="campaign_id" value="<?= (int)$c['campaign_id'] ?>"><button class="btn alt" name="action" value="CANCEL" data-confirm="Cancel your campaign registration?">Cancel registration</button></form>
<?php elseif($c['registration_status']==='ATTENDED'): ?>
<?php elseif($full): ?><span class="muted">This campaign is full.</span>
<?php elseif($isCleared && in_array($c['status'],['UPCOMING','ACTIVE'],true)): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="campaign_id" value="<?= (int)$c['campaign_id'] ?>"><button class="btn" name="action" value="REGISTER">Register to attend</button></form>
<?php else: ?><span class="muted">Verification is required to register through SEBD.</span><?php endif; ?></div><?php endforeach; ?>
<?php if(!$rows): ?><div class="empty">No campaigns match. Try other filters or check back later.</div><?php endif; ?>
<?php require '../includes/footer.php'; ?>
