<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); $oid=$o['organization_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_verified_org($o); $type=post('donation_type'); $units=(int)post('units'); $cid=(int)post('campaign_id')?:null;
  $s=$pdo->prepare("SELECT d.* FROM donors d JOIN users u ON u.user_id=d.user_id WHERE d.donor_id=? AND d.verification_status='VERIFIED' AND d.health_status='CLEARED' AND u.status='ACTIVE'"); $s->execute([(int)post('donor_id')]); $d=$s->fetch();
  if(!$d||!in_array($type,['CAMPAIGN','WALK_IN'])||($type==='CAMPAIGN'&&!$cid)){ flash('Choose a verified, health-cleared donor, type and (for campaign) a campaign.','err'); redirect('healthcare/donations.php'); }
  if($type==='CAMPAIGN'){ $owner=$pdo->prepare("SELECT COUNT(*) FROM donation_campaigns WHERE campaign_id=? AND organization_id=? AND status<>'CANCELLED' AND TIMESTAMP(campaign_date,COALESCE(start_time,'00:00:00'))<=NOW() AND TIMESTAMP(campaign_date,end_time)+INTERVAL ".CAMPAIGN_DONATION_GRACE_HOURS." HOUR>=NOW()"); $owner->execute([$cid,$oid]); if(!(int)$owner->fetchColumn()){ flash('Choose one of your campaigns that has already started (start time passed) and ended no more than '.CAMPAIGN_DONATION_GRACE_HOURS.' hours ago.','err'); redirect('healthcare/donations.php'); } }
  // Real world: staff measure haemoglobin / weight / BP on the day, before the bag is taken. SEBD does not store the readings, so staff must confirm it was done.
  if(post('screened')!=='1'){ flash('Confirm that the donor passed the pre-donation check (haemoglobin, weight, blood pressure) before recording.','err'); redirect('healthcare/donations.php'); }
  $hb=trim(post('hb')); if($hb!==''){ if(!is_numeric($hb)||(float)$hb<3||(float)$hb>25){ flash('Enter a valid haemoglobin value in g/dl.','err'); redirect('healthcare/donations.php'); } if((float)$hb<DONOR_MIN_HB){ flash('Haemoglobin '.(float)$hb.' g/dl is below the minimum of '.DONOR_MIN_HB.' g/dl. The donor must not donate today.','err'); redirect('healthcare/donations.php'); } }
  if($units!==MAX_UNITS_PER_DONATION){ flash('One donation is exactly '.MAX_UNITS_PER_DONATION.' unit (one bag). Record each donation separately.','err'); redirect('healthcare/donations.php'); }
  $when=null; if($type==='CAMPAIGN'){ $cs=$pdo->prepare("SELECT campaign_date,end_time FROM donation_campaigns WHERE campaign_id=?"); $cs->execute([$cid]); $cr=$cs->fetch(); if($cr && $cr['campaign_date']<date('Y-m-d')) $when=$cr['campaign_date'].' '.$cr['end_time']; } // entered after the day: keep the real collection date
  try{ $pdo->beginTransaction();
    $why=donation_block_reason($pdo,(int)$d['donor_id'],true); if($why!=='') throw new RuntimeException($why); // gap + open-request check, donor row locked
    $did=record_donation($pdo,$d,$oid,$type,$units,mb_substr(trim(post('notes').($hb!==''?' [Hb '.(float)$hb.' g/dl]':'')),0,500),$type==='CAMPAIGN'?$cid:null,null,$_SESSION['uid'],$when);
    if($type==='WALK_IN') $pdo->prepare("UPDATE donor_appointments SET status='COMPLETED',reviewed_by=?,reviewed_at=NOW() WHERE donor_id=? AND organization_id=? AND requested_date=CURDATE() AND status IN ('PENDING','CONFIRMED')")->execute([$_SESSION['uid'],$d['donor_id'],$oid]); // closes the donor's walk-in booking for today
    audit($pdo,$_SESSION['uid'],'CREATE','donations',$did,"$type donation recorded."); $pdo->commit(); flash('Donation recorded.');
  }catch(RuntimeException $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash($e->getMessage(),'err');
  }catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('Could not record donation.','err'); }
  redirect('healthcare/donations.php');
}
$donors=$pdo->query("SELECT d.donor_id,d.blood_group,u.full_name FROM donors d JOIN users u USING(user_id) WHERE d.verification_status='VERIFIED' AND d.health_status='CLEARED' AND u.status='ACTIVE' ORDER BY u.full_name")->fetchAll();
$camps=$pdo->prepare("SELECT campaign_id,campaign_name FROM donation_campaigns WHERE organization_id=? AND status<>'CANCELLED' AND TIMESTAMP(campaign_date,COALESCE(start_time,'00:00:00'))<=NOW() AND TIMESTAMP(campaign_date,end_time)+INTERVAL ".CAMPAIGN_DONATION_GRACE_HOURS." HOUR>=NOW()"); $camps->execute([$oid]);
$t=get('type'); $fbg=get('blood_group'); $ff=get('from'); $ft=get('to');
$okd=fn($x)=>$x===''||(bool)(DateTime::createFromFormat('Y-m-d',$x)&&DateTime::createFromFormat('Y-m-d',$x)->format('Y-m-d')===$x); if(!$okd($ff))$ff=''; if(!$okd($ft))$ft='';
$sql="SELECT n.*,u.full_name FROM donations n JOIN donors d USING(donor_id) JOIN users u ON u.user_id=d.user_id WHERE n.organization_id=?"; $p=[$oid]; if($t){$sql.=" AND n.donation_type=?";$p[]=$t;}
if(in_array($fbg,BLOOD,true)){$sql.=" AND n.blood_group=?";$p[]=$fbg;} if($ff){$sql.=" AND DATE(n.donation_date)>=?";$p[]=$ff;} if($ft){$sql.=" AND DATE(n.donation_date)<=?";$p[]=$ft;}
$s=$pdo->prepare($sql." ORDER BY n.donation_date DESC"); $s->execute($p);
$title='Donations'; require '../includes/header.php'; ?>
<form method="post" class="card"><?= csrf_field() ?><b>Record walk-in / campaign donation</b><div class="row">
<div><label>Donor</label><select name="donor_id"><?php foreach($donors as $d) echo "<option value='{$d['donor_id']}'>".h($d['full_name'])." ({$d['blood_group']})</option>"; ?></select></div>
<div><label>Type</label><select name="donation_type"><option>WALK_IN</option><option>CAMPAIGN</option></select></div>
<div><label>Campaign (if campaign)</label><select name="campaign_id"><option value="">-</option><?php foreach($camps as $c) echo "<option value='{$c['campaign_id']}'>".h($c['campaign_name'])."</option>"; ?></select></div>
<div><label>Units</label><input type="number" name="units" min="1" max="1" value="1" readonly title="One donation = one unit"></div><div><label>Hb g/dl <span class="muted">(optional)</span></label><input type="number" step="0.1" min="3" max="25" name="hb" placeholder="min <?= DONOR_MIN_HB ?>"></div><div><label>Notes</label><input name="notes"></div></div><label class="chk"><input type="checkbox" name="screened" value="1" required> Pre-donation check done: haemoglobin, weight and blood pressure are acceptable.</label><div></div><br><button class="btn">Record</button></form>
<form class="filter"><select name="type"><option value="">All types</option><?php foreach(['CAMPAIGN','WALK_IN','EMERGENCY'] as $x) echo "<option ".($t===$x?'selected':'').">$x</option>"; ?></select><?= blood_select('blood_group',$fbg,true) ?><label class="dl">Donated from <input type="date" name="from" value="<?= h($ff) ?>"></label><label class="dl">Donated to <input type="date" name="to" value="<?= h($ft) ?>"></label><button class="btn">Filter</button></form>
<div class="tw"><table><thead><tr><th>Date</th><th>Donor</th><th>Group</th><th>Type</th><th>Units</th><th>Status</th></tr></thead><tbody>
<?php foreach($s as $r): ?><tr><td><?= h($r['donation_date']) ?></td><td><?= h($r['full_name']) ?></td><td><?= h($r['blood_group']) ?></td><td><?= h($r['donation_type']) ?></td><td><?= $r['units_donated'] ?></td><td><?= badge($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php require '../includes/footer.php';
