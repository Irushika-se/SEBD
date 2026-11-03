<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
// Donor search for blood banks (approval sheet: "Searches and filters donors"). Verified + health-cleared donors only.
// Privacy: no phone, e-mail, address or exact position is shown here; contact details appear only after a donor accepts a request (Matches page).
$o=my_org($pdo); require_verified_org($o);
$q=get('q'); $bg=get('blood_group'); $city=get('city'); $av=get('availability'); $el=get('eligible')==='1';
$sql="SELECT d.donor_id,d.blood_group,d.city,d.availability_status,d.last_donation_date,u.full_name FROM donors d JOIN users u ON u.user_id=d.user_id
      WHERE d.verification_status='VERIFIED' AND d.health_status='CLEARED' AND u.status='ACTIVE'"; $p=[];
if($q!==''){ $sql.=" AND u.full_name LIKE ?"; $p[]="%$q%"; }
if(in_array($bg,BLOOD,true)){ $sql.=" AND d.blood_group=?"; $p[]=$bg; }
if(array_key_exists($city,SL_CITIES)){ $sql.=" AND d.city=?"; $p[]=$city; }
if(in_array($av,['AVAILABLE','UNAVAILABLE'],true)){ $sql.=" AND d.availability_status=?"; $p[]=$av; }
if($el) $sql.=" AND (d.last_donation_date IS NULL OR DATE_ADD(d.last_donation_date,INTERVAL ".DONATION_GAP_MONTHS." MONTH)<=CURDATE())";
$s=$pdo->prepare($sql." ORDER BY d.blood_group,u.full_name LIMIT 300"); $s->execute($p); $rows=$s->fetchAll();
$title='Donors'; $page_intro='Verified, health-cleared donors. Contact details are shared only after a donor accepts one of your requests.'; require '../includes/header.php'; ?>
<form class="filter"><input name="q" placeholder="Donor name" value="<?= h($q) ?>"><?= blood_select('blood_group',$bg,true) ?>
<select name="city"><option value="">All cities</option><?php foreach(SL_CITIES as $c=>$_) echo "<option ".($city===$c?'selected':'').">".h($c)."</option>"; ?></select>
<select name="availability" title="Available = can be asked to donate now. Not available = donated recently, already accepted a request, or paused by the donor."><option value="">Any availability</option><option value="AVAILABLE" <?= $av==='AVAILABLE'?'selected':'' ?>>Available now</option><option value="UNAVAILABLE" <?= $av==='UNAVAILABLE'?'selected':'' ?>>Not available</option></select>
<label><input type="checkbox" name="eligible" value="1" <?= $el?'checked':'' ?>> Past the <?= DONATION_GAP_MONTHS ?>-month gap</label><button class="btn">Search</button></form>
<div class="tw"><table><thead><tr><th>Donor</th><th>Group</th><th>City</th><th>Availability</th><th>Last donation</th><th>Next eligible</th></tr></thead><tbody>
<?php foreach($rows as $r): $ne=donor_next_eligible_date($r['last_donation_date']); ?><tr><td><?= h($r['full_name']) ?></td><td><?= h($r['blood_group']) ?></td><td><?= h($r['city']) ?></td><td><?= badge($r['availability_status']) ?></td><td><?= h($r['last_donation_date']?:'—') ?></td><td><?= $ne&&$ne>date('Y-m-d')?h($ne):'now' ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6">No donors match these filters.</td></tr><?php endif; ?></tbody></table></div>
<?php require '../includes/footer.php';
