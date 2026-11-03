<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); require_verified_org($o); $oid=$o['organization_id']; expire_stock($pdo,$oid);
$inv=$pdo->prepare("SELECT * FROM blood_inventory WHERE organization_id=?"); $inv->execute([$oid]); $inv=array_column($inv->fetchAll(),null,'blood_group');
$bat=[]; foreach(active_batches($pdo,$oid) as $b) $bat[$b['blood_group']][]=$b; // valid (non-expired) batches per group
if($_SERVER['REQUEST_METHOD']==='POST'){
  $bg=post('blood_group'); $u=(int)post('units_required'); $ur=post('urgency'); $rt=str_replace('T',' ',post('required_time'));
  if(!in_array($bg,BLOOD)||$u<1||!in_array($ur,['CRITICAL','HIGH','NORMAL'])||$rt===''||strtotime($rt)===false||strtotime($rt)<=time()||post('location')===''||!array_key_exists(post('city'),SL_CITIES)){ flash('Fill all required fields correctly.','err'); redirect('healthcare/create_request.php'); }
  if($e=urgency_time_error($ur,$rt)){ flash($e,'err'); redirect('healthcare/create_request.php'); }
  $tot=(int)($inv[$bg]['units_available']??0); $min=(int)($inv[$bg]['minimum_stock']??0);
  $st=usable_stock($pdo,$oid,$bg,$rt); // only units still valid on the required date
  if(stock_sufficient($st,$min,$u)){ flash("Usable stock of $bg is $st unit(s) (valid until the required time, minimum $min) - enough for $u unit(s) without going below the minimum. Fulfil the need from inventory instead of raising a request.",'err'); redirect('healthcare/create_request.php'); }
  [$lat,$lng,$prec,$gerr]=location_from_post(post('city')); if($gerr){ flash($gerr,'err'); redirect('healthcare/create_request.php'); }
  try{
    $pdo->beginTransaction();
    // Serialise this organisation's submissions: two near-simultaneous identical requests (double-click from two tabs / devices) are checked one after the other, so the second sees the first and is refused as a duplicate. Different wards / times are still accepted.
    $pdo->prepare("SELECT organization_id FROM healthcare_organizations WHERE organization_id=? FOR UPDATE")->execute([$oid]);
    if($dup=duplicate_request_exists($pdo,$oid,$bg,post('location'),$rt)){ $pdo->rollBack(); flash("Request #$dup already asks for $bg at the same location within ".(DUP_WINDOW_MIN/60)." hours of this time, so this looks like a duplicate. Edit it instead - or, if this is a different patient, add the ward/bed to the location or change the needed-by time.",'err'); redirect('healthcare/emergency_requests.php'); }
  $pdo->prepare("INSERT INTO emergency_requests(organization_id,blood_group,units_required,urgency,required_time,location,reason,notes,latitude,longitude,location_precise) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
      ->execute([$oid,$bg,$u,$ur,$rt,post('location'),post('reason'),post('notes'),$lat,$lng,$prec]);
  $rid=$pdo->lastInsertId(); close_request_history($pdo,$rid,null,'PENDING','Request created (usable stock '.$st.' of '.$tot.' total, minimum '.$min.').');
  audit($pdo,$_SESSION['uid'],'CREATE','emergency_requests',$rid,'Emergency blood request created.');
    $pdo->commit();
  }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); error_log('create request failed: '.$e->getMessage()); flash('Could not create the request. Nothing was saved - please try again.','err'); redirect('healthcare/create_request.php'); }
  $n=run_matching($pdo,$rid,0,$mi); flash("Request created. $n donor(s) notified".($n?' ('.match_info_text($mi,$bg).')':'').'.'); redirect('healthcare/emergency_requests.php');
}
$title='Create Emergency Request'; require '../includes/header.php'; ?>
<?php if(!array_filter($inv,fn($i)=>(int)$i['units_available']>0||(int)$i['minimum_stock']>0)): ?><div class="alert err">Your inventory has not been set up yet (all groups show 0). Requests are still allowed, but the stock check only works properly after you enter your stock and minimum levels. <a href="inventory.php">Set up inventory</a></div><?php endif; ?>
<div class="card"><b>Step 1 - Check current inventory</b> <span class="muted">(expired units are already removed; blood expires after ~<?= SHELF_LIFE_DAYS ?> days)</span><div class="tw"><table><tr><th>Group</th><th>Total units</th><th>Expiring in <?= EXPIRY_WARN_DAYS ?> days</th><th>Nearest expiry</th><th>Minimum</th></tr>
<?php foreach(BLOOD as $b){ $bs=$bat[$b]??[]; $soon=0; $warn=date('Y-m-d',strtotime('+'.EXPIRY_WARN_DAYS.' days')); foreach($bs as $x) if($x['expiry_date']<=$warn) $soon+=$x['units_remaining'];
  $js=h(json_encode(array_map(fn($x)=>[(int)$x['units_remaining'],$x['expiry_date']],$bs)));
  echo "<tr data-bg='$b' data-m='".(int)($inv[$b]['minimum_stock']??0)."' data-b='$js'><td>$b</td><td>".(int)($inv[$b]['units_available']??0)."</td><td>".($soon?badge('EXPIRING').' '.$soon:'0')."</td><td>".($bs?h($bs[0]['expiry_date']):'-')."</td><td>".(int)($inv[$b]['minimum_stock']??0)."</td></tr>"; } ?></table></div></div>
<form method="post" class="card"><?= csrf_field() ?><b>Step 2 - Request details</b><div class="row">
<div><label>Blood group</label><?= blood_select('blood_group') ?></div><div><label>Units required</label><input type="number" name="units_required" min="1" required></div>
<div><label>Urgency</label><select name="urgency"><option>CRITICAL</option><option selected>HIGH</option><option>NORMAL</option></select></div>
<div><label>Required time</label><input type="datetime-local" name="required_time" required></div>
<div><label>Location (hospital / ward)</label><input name="location" required></div>
<div><label>City</label><?= city_select('city',$o['city']) ?></div>
<div data-geobox><label>Exact patient location (optional)</label><button type="button" class="btn sm alt" data-geo>Use my current location</button><input type="hidden" name="geo_lat"><input type="hidden" name="geo_lng"><small class="geo-status muted">Without it, donors are ranked from the city centre only.</small></div>
<div><label>Reason</label><input name="reason"></div></div><label>Notes</label><textarea name="notes"></textarea>
<p id="stockcheck" class="muted"></p>
<p class="muted">Critical notifies the widest group of compatible donors at any distance. High notifies nearby donors first, and Normal a smaller, closer group. Donors who can reach the hospital in time are asked first, with the exact blood group before other compatible groups. You can raise more than one request for the same group (different patients, wards or times).</p><button class="btn">Submit request</button></form>
<script>
(function(){var f=document.querySelector('form.card'),g=f.querySelector('[name=blood_group]'),u=f.querySelector('[name=units_required]'),t=f.querySelector('[name=required_time]'),o=document.getElementById('stockcheck');
function chk(){var r=document.querySelector('tr[data-bg="'+g.value+'"]');if(!r||!u.value){o.textContent='';return;}
var d=(t.value||'').slice(0,10),today=new Date().toISOString().slice(0,10);if(!d||d<today)d=today;
var st=0,b=JSON.parse(r.dataset.b||'[]');b.forEach(function(x){if(x[1]>=d)st+=x[0];});var mn=+r.dataset.m,n=+u.value;
o.textContent=(st>=n&&st-n>=mn)?'Usable stock on '+d+': '+st+' unit(s) (min '+mn+'). Enough - fulfil from inventory, request will be rejected.':'Usable stock on '+d+': '+st+' unit(s) (min '+mn+'). Not enough / would fall below minimum - request allowed.';}
[g,u,t].forEach(function(e){e.addEventListener('change',chk);e.addEventListener('input',chk);});chk();})();
</script>
<?php require '../includes/footer.php';
